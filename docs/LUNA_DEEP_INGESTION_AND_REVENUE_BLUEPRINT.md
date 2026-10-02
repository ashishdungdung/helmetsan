# Helmetsan Production Architecture

## Important platform constraints

1. **Google Indexing API is not a general-purpose product indexing API.** Google officially limits it to pages containing `JobPosting` or livestream `BroadcastEvent` structured data. Product pages must be handled through XML sitemaps, internal links, Merchant Center, and Search Console sitemap submission. The implementation below therefore does **not** submit ordinary helmet/product URLs to the Google Indexing API.

2. **IndexNow does not guarantee indexing.** It only informs participating engines that a URL changed. The Universal Gateway is generally sufficient for Bing/Yandex/Seznam-compatible consumers. Direct submissions can be enabled for operational redundancy, but they should be ledgered separately and rate-limited.

3. **No crawler detector is perfect.** Bot filtering should combine user-agent classification, Cloudflare signals, rate limits, IP reputation, and behavior. Never rely on user-agent matching alone.

4. **Affiliate redirects must not conceal the commercial destination.** A 307 redirect is acceptable for a tracking route, but the page and/or link must clearly identify the merchant and affiliate relationship.

---

# 1. Indexing architecture

## Recommended flow

```text
Content mutation
    |
    |-- calculate content/price/certification fingerprint
    |
    |-- compare with last successfully submitted fingerprint
    |
    |-- enqueue URL once per fingerprint
    |
Redis queue / wp_helmetsan_indexnow_log
    |
worker claims batches
    |
    |-- IndexNow Universal Gateway
    |-- Bing Direct, optional
    |-- Yandex Direct, optional
    |-- Seznam Direct, optional
    |-- Naver, only if account/API details are configured
    |-- Baidu, zh URLs only
    |
persistent response ledger
```

The mutation fingerprint should include only indexing-relevant values:

```text
sha256(
    canonical_url
    + "\n" + locale
    + "\n" + title
    + "\n" + availability
    + "\n" + price
    + "\n" + currency
    + "\n" + certification_data
    + "\n" + last_modified_timestamp
)
```

Do not resubmit merely because a WordPress post was saved if none of those values changed.

## IndexNow ledger schema

```sql
CREATE TABLE IF NOT EXISTS wp_helmetsan_indexnow_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    url_hash BINARY(32) NOT NULL,
    url VARCHAR(2048) NOT NULL,
    post_id BIGINT UNSIGNED NULL,
    lang CHAR(2) NOT NULL,
    engine VARCHAR(32) NOT NULL,
    content_fingerprint BINARY(32) NOT NULL,
    response_code SMALLINT UNSIGNED NULL,
    response_body TEXT NULL,
    batch_id CHAR(36) NOT NULL,
    attempt_no TINYINT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('queued','sending','success','retry','failed') NOT NULL DEFAULT 'queued',
    next_attempt_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_submission (
        url_hash,
        engine,
        content_fingerprint
    ),
    KEY ix_queue (
        status,
        next_attempt_at,
        engine,
        id
    ),
    KEY ix_url (
        url_hash,
        created_at
    ),
    KEY ix_post (
        post_id,
        lang,
        created_at
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
```

A separate state table makes deduplication inexpensive:

```sql
CREATE TABLE IF NOT EXISTS wp_helmetsan_index_state (
    url_hash BINARY(32) NOT NULL,
    url VARCHAR(2048) NOT NULL,
    post_id BIGINT UNSIGNED NULL,
    lang CHAR(2) NOT NULL,
    content_fingerprint BINARY(32) NOT NULL,
    last_success_at DATETIME NULL,
    last_enqueued_at DATETIME NULL,
    PRIMARY KEY (url_hash),
    KEY ix_post_lang (post_id, lang)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
```

## `IndexNowService.php`

This implementation:

- validates URLs against the Helmetsan host;
- deduplicates by URL and fingerprint;
- sends batches with `curl_multi`;
- persists every engine response;
- retries transient failures;
- excludes Google from ordinary product submission;
- supports Baidu separately.

```php
<?php
declare(strict_types=1);

final class IndexNowService
{
    private const MAX_BATCH_SIZE = 10_000;
    private const MAX_RESPONSE_BODY = 16_384;

    /**
     * Universal Gateway is normally sufficient. Direct endpoints are
     * operationally optional and may produce duplicate notifications.
     */
    private const ENGINES = [
        'indexnow' => 'https://api.indexnow.org/indexnow',
        'bing'     => 'https://www.bing.com/indexnow',
        'yandex'   => 'https://yandex.com/indexnow',
        'seznam'   => 'https://search.seznam.cz/indexnow',
    ];

    public function __construct(
        private readonly PDO $db,
        private readonly string $siteHost,
        private readonly string $indexNowKey,
        private readonly string $keyLocation,
        private readonly ?string $baiduEndpoint = null,
        private readonly ?string $baiduToken = null
    ) {
        if (!preg_match('/^[a-fA-F0-9]{8,128}$/', $this->indexNowKey)) {
            throw new InvalidArgumentException('Invalid IndexNow key.');
        }
    }

    public function enqueue(
        string $url,
        ?int $postId,
        string $lang,
        array $indexableState,
        array $engines = ['indexnow']
    ): bool {
        $url = $this->validateUrl($url);
        $lang = strtolower($lang);

        if (!preg_match('/^[a-z]{2}$/', $lang)) {
            throw new InvalidArgumentException('Invalid language.');
        }

        $fingerprint = hash(
            'sha256',
            $url . "\n" .
            $lang . "\n" .
            json_encode($indexableState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            true
        );

        $urlHash = hash('sha256', $url, true);
        $queued = false;

        $this->db->beginTransaction();

        try {
            $state = $this->db->prepare(
                'SELECT content_fingerprint
                   FROM wp_helmetsan_index_state
                  WHERE url_hash = ?
                  FOR UPDATE'
            );
            $state->execute([$urlHash]);
            $existing = $state->fetchColumn();

            if ($existing !== false && hash_equals($existing, $fingerprint)) {
                $this->db->commit();
                return false;
            }

            $upsert = $this->db->prepare(
                'INSERT INTO wp_helmetsan_index_state
                    (url_hash, url, post_id, lang, content_fingerprint, last_enqueued_at)
                 VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE
                    url = VALUES(url),
                    post_id = VALUES(post_id),
                    lang = VALUES(lang),
                    content_fingerprint = VALUES(content_fingerprint),
                    last_enqueued_at = UTC_TIMESTAMP()'
            );
            $upsert->execute([
                $urlHash,
                $url,
                $postId,
                $lang,
                $fingerprint
            ]);

            $batchId = UUID::v4();

            $insert = $this->db->prepare(
                'INSERT IGNORE INTO wp_helmetsan_indexnow_log
                    (url_hash, url, post_id, lang, engine, content_fingerprint,
                     batch_id, status, next_attempt_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, \'queued\', UTC_TIMESTAMP())'
            );

            foreach ($engines as $engine) {
                if (!isset(self::ENGINES[$engine])) {
                    throw new InvalidArgumentException("Unsupported engine: {$engine}");
                }

                $insert->execute([
                    $urlHash,
                    $url,
                    $postId,
                    $lang,
                    $engine,
                    $fingerprint,
                    $batchId
                ]);

                $queued = $queued || $insert->rowCount() > 0;
            }

            $this->db->commit();
            return $queued;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function dispatch(int $limit = 1000): int
    {
        $this->db->beginTransaction();

        try {
            $query = $this->db->prepare(
                'SELECT id, url, engine, batch_id, attempt_no
                   FROM wp_helmetsan_indexnow_log
                  WHERE status IN (\'queued\', \'retry\')
                    AND next_attempt_at <= UTC_TIMESTAMP()
                  ORDER BY id
                  LIMIT ?
                  FOR UPDATE SKIP LOCKED'
            );
            $query->bindValue(1, $limit, PDO::PARAM_INT);
            $query->execute();
            $rows = $query->fetchAll(PDO::FETCH_ASSOC);

            if (!$rows) {
                $this->db->commit();
                return 0;
            }

            $ids = array_column($rows, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $claim = $this->db->prepare(
                "UPDATE wp_helmetsan_indexnow_log
                    SET status = 'sending', updated_at = UTC_TIMESTAMP()
                  WHERE id IN ($placeholders)"
            );
            $claim->execute($ids);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['engine']][] = $row;
        }

        $processed = 0;

        foreach ($groups as $engine => $engineRows) {
            foreach (array_chunk($engineRows, self::MAX_BATCH_SIZE) as $chunk) {
                $responses = $this->sendIndexNowBatch($engine, $chunk);

                foreach ($chunk as $row) {
                    $response = $responses[$row['url']] ?? [
                        'code' => 599,
                        'body' => 'No response returned'
                    ];

                    $code = (int) $response['code'];
                    $success = $code >= 200 && $code < 300;
                    $retryable = $code === 429 || $code >= 500 || $code === 599;

                    $status = $success
                        ? 'success'
                        : ($retryable ? 'retry' : 'failed');

                    $nextAttempt = $retryable
                        ? gmdate(
                            'Y-m-d H:i:s',
                            time() + min(86400, 60 * (2 ** min(8, (int)$row['attempt_no'])))
                        )
                        : gmdate('Y-m-d H:i:s');

                    $update = $this->db->prepare(
                        'UPDATE wp_helmetsan_indexnow_log
                            SET response_code = ?,
                                response_body = ?,
                                status = ?,
                                attempt_no = attempt_no + 1,
                                next_attempt_at = ?,
                                updated_at = UTC_TIMESTAMP()
                          WHERE id = ?'
                    );
                    $update->execute([
                        $code,
                        substr((string)$response['body'], 0, self::MAX_RESPONSE_BODY),
                        $status,
                        $nextAttempt,
                        $row['id']
                    ]);

                    $processed++;
                }
            }
        }

        return $processed;
    }

    /**
     * Baidu accepts a newline-delimited URL body at its token endpoint.
     * Only invoke this for URLs in the zh catalog.
     */
    public function submitBaidu(array $urls): array
    {
        if (!$this->baiduEndpoint || !$this->baiduToken || !$urls) {
            return ['code' => 0, 'body' => 'Baidu not configured'];
        }

        foreach ($urls as $url) {
            $this->validateUrl((string)$url);
        }

        $ch = curl_init($this->baiduEndpoint . '&token=' . rawurlencode($this->baiduToken));
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => implode("\n", $urls),
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/plain',
                'Content-Length: ' . strlen(implode("\n", $urls)),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [
            'code' => $code ?: 599,
            'body' => substr((string)$body, 0, self::MAX_RESPONSE_BODY)
        ];
    }

    private function sendIndexNowBatch(string $engine, array $rows): array
    {
        $endpoint = self::ENGINES[$engine];

        $payload = json_encode([
            'host' => $this->siteHost,
            'key' => $this->indexNowKey,
            'keyLocation' => $this->keyLocation,
            'urlList' => array_values(array_unique(array_column($rows, 'url')))
        ], JSON_UNESCAPED_SLASHES);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($payload),
                'User-Agent: HelmetsanIndexWorker/1.0 (+https://helmetsan.com/robots.txt)',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $result = [];
        foreach ($rows as $row) {
            $result[$row['url']] = [
                'code' => $code ?: 599,
                'body' => $error ?: (string)$body
            ];
        }

        return $result;
    }

    private function validateUrl(string $url): string
    {
        $parts = parse_url($url);

        if (
            !$parts ||
            ($parts['scheme'] ?? '') !== 'https' ||
            strtolower($parts['host'] ?? '') !== strtolower($this->siteHost)
        ) {
            throw new InvalidArgumentException('URL is not an HTTPS Helmetsan URL.');
        }

        return $url;
    }
}

final class UUID
{
    public static function v4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
```

Use the Universal Gateway by default. Only enable direct Bing/Yandex/Seznam dispatch if monitoring demonstrates a concrete operational benefit.

---

# 2. RevenueService architecture

## Routing policy

Routing should be configuration-driven rather than hard-coded throughout the service.

| Visitor | Primary marketplace |
|---|---|
| AT | Amazon DE |
| CH | Amazon DE, then FR, then IT |
| IE | Amazon UK |
| NZ | Amazon AU |
| BE | Amazon BE, then FR, then NL |
| US | Amazon US |
| CA | Amazon CA |
| EU fallback | Amazon DE |
| IN | Flipkart or configured Amazon India route |

The service should use:

1. visitor country from a trusted Cloudflare header;
2. marketplace eligibility;
3. primary ASIN;
4. alternate variant ASIN;
5. specialist merchant;
6. brand-level search URL.

Do not derive merchant URLs from arbitrary request parameters. Use server-side offer records.

## Revenue tables

```sql
CREATE TABLE IF NOT EXISTS wp_helmetsan_offers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    merchant VARCHAR(32) NOT NULL,
    marketplace_id VARCHAR(16) NULL,
    asin VARCHAR(16) NULL,
    alternate_asin VARCHAR(16) NULL,
    target_url VARCHAR(2048) NOT NULL,
    brand_search_url VARCHAR(2048) NULL,
    is_quarantined TINYINT(1) NOT NULL DEFAULT 0,
    stock_status ENUM('unknown','in_stock','out_of_stock','unavailable')
        NOT NULL DEFAULT 'unknown',
    last_checked_at DATETIME NULL,
    priority SMALLINT NOT NULL DEFAULT 100,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY ix_offer_selection (
        product_id,
        active,
        is_quarantined,
        stock_status,
        priority
    ),
    KEY ix_merchant_marketplace (merchant, marketplace_id)
) ENGINE=InnoDB DEFAULT CHAR