## Executive verdict

The blueprint has sound high-level instincts, but it is not production-hardened as written. The most serious defects are:

1. **IndexNow state can suppress retries after a failed submission.**
2. **The dispatch implementation claims `curl_multi` but performs serial single-request calls.**
3. **A worker crash can leave rows permanently stuck in `sending`.**
4. **Multiple IndexNow endpoints are treated as independent success paths without a clear quota/fallback policy.**
5. **Baidu submissions lack quota accounting, response parsing, durable ledgering, and China-specific delivery controls.**
6. **Bot detection on the redirect path cannot safely depend on synchronous reputation lookups or database work if the target is under 2 ms.**
7. **`INSERT ... ON DUPLICATE KEY UPDATE counter = counter + 1` is not idempotent under retries.**
8. **A live `ALTER TABLE` on a 440,000-row MariaDB table is unsafe unless the exact operation is known to be instant/in-place and metadata-lock behavior is controlled.**

---

# 1. Multi-engine IndexNow and Baidu

## 1.1 Direct submission to multiple IndexNow endpoints

Submitting the same URL to:

- `api.indexnow.org`
- Bing
- Yandex
- Seznam

does not normally create four independent indexing obligations. IndexNow is specifically designed so that a participating engine can propagate a notification to other participating engines.

Direct parallel submission is therefore usually **redundant**, not more authoritative.

Potential consequences include:

- duplicate notifications;
- duplicate crawling triggers;
- quota consumption at multiple providers;
- rate-limit responses;
- inconsistent response semantics;
- difficulty determining whether the URL was accepted by the network or merely accepted by one endpoint;
- noisy operational metrics.

It should not be assumed that duplicate notifications will necessarily cause a ranking penalty, but it is still unnecessary traffic and may look abusive if performed at scale.

### Hardened policy

Use one of these modes:

### Preferred mode: one authoritative endpoint

```text
Primary: api.indexnow.org
Fallback: Bing direct only after transport failure or sustained gateway failure
```

Do not submit simultaneously to all endpoints.

### Alternative mode: provider-specific routing

If operational evidence requires direct submissions:

```text
URL event
  -> one selected endpoint
  -> retry that endpoint
  -> fail over only after a defined outage policy
```

Do not send the same URL to all endpoints concurrently.

Maintain a delivery ledger with at least:

```text
notification_id
url
fingerprint
provider
attempt
request_id
response_code
accepted_at
retry_after
failure_class
```

The ledger must distinguish:

- transport failure;
- HTTP acceptance;
- HTTP rejection;
- rate limiting;
- malformed request;
- provider outage;
- unknown result due to timeout.

A timeout is not proof that the provider rejected the request. Retrying a timeout can produce a duplicate notification, so retries must be bounded and treated as at-least-once delivery.

## 1.2 Problems in the supplied IndexNow code

### A. The state table suppresses failed submissions

This is the most important correctness defect.

`enqueue()` updates `wp_helmetsan_index_state.content_fingerprint` before any submission succeeds. Later, if the same fingerprint is enqueued again:

```php
if ($existing !== false && hash_equals($existing, $fingerprint)) {
    return false;
}
```

The event is suppressed even if the prior log rows are:

- `failed`;
- permanently abandoned;
- stuck in `sending`;
- never dispatched due to a worker crash.

The state table is therefore not “last successfully submitted fingerprint”; it is “last attempted/enqueued fingerprint.”

### Fix

Track desired and successfully delivered state separately:

```sql
last_enqueued_fingerprint BINARY(32) NULL,
last_success_fingerprint  BINARY(32) NULL,
last_success_at           DATETIME NULL
```

Deduplicate only if the relevant provider already has a successful record for that fingerprint.

Alternatively, use the log table as the source of truth:

```sql
SELECT 1
FROM wp_helmetsan_indexnow_log
WHERE url_hash = ?
  AND engine = ?
  AND content_fingerprint = ?
  AND status = 'success'
LIMIT 1;
```

### B. Global fingerprint suppression breaks engine onboarding

The state check occurs before the engine loop. Suppose IndexNow was successfully sent, and later Bing direct is enabled for the same fingerprint. The state check returns early, so Bing never receives a row.

Deduplication must be scoped by:

```text
URL + fingerprint + provider
```

not merely:

```text
URL + fingerprint
```

### C. Claimed `curl_multi` is not implemented

`sendIndexNowBatch()` uses one `curl_init()` call. It sends one HTTP request per engine batch, which is appropriate for IndexNow batch submission, but it is not `curl_multi`.

If the intended design is concurrent provider requests, use `curl_multi`. If the intended design is one provider at a time, remove the claim.

### D. A worker crash permanently strands `sending` rows

Rows are marked `sending` before network activity. If the process dies, they remain `sending` indefinitely because dispatch only selects:

```sql
status IN ('queued','retry')
```

Add leases:

```sql
lease_until DATETIME NULL,
worker_id VARCHAR(64) NULL,
```

Claim using a lease, then recover expired rows:

```sql
UPDATE wp_helmetsan_indexnow_log
SET status = 'retry',
    worker_id = NULL,
    lease_until = NULL,
    next_attempt_at = UTC_TIMESTAMP()
WHERE status = 'sending'
  AND lease_until < UTC_TIMESTAMP();
```

The claim operation should set `lease_until`, for example, to five minutes in the future.

### E. No maximum retry count

`attempt_no` is `TINYINT UNSIGNED`, and retryable rows can continue indefinitely. Add:

```text
max_attempts
failure_class
dead_lettered_at
```

After a bounded number of attempts, mark the event `failed` or `dead_lettered` and alert.

### F. Batch-level responses are incorrectly assigned to every URL

The service assigns the same HTTP result to every URL in a batch. That is often the only response available, but the schema should identify it as a **batch response**, not pretend that every individual URL was independently accepted.

Use separate tables or columns:

```text
batch_id
batch_response_code
batch_response_body
url_status NULL
```

A batch-level `202` means accepted for processing; it does not necessarily prove that every URL was crawled or indexed.

### G. IndexNow key validation is likely too restrictive

The regex:

```php
/^[a-fA-F0-9]{8,128}$/
```

rejects valid key formats if the protocol permits non-hex characters such as hyphens. Validate according to the provider’s actual key grammar, not an assumed hexadecimal-only grammar.

Also validate:

- `keyLocation` is HTTPS;
- `keyLocation` belongs to the same host;
- the key file is publicly retrievable;
- the key file contains exactly the expected key;
- redirects are not required;
- canonical URL host, port, and scheme match policy.

### H. URL validation is incomplete

`parse_url()` validation should reject or normalize:

- userinfo;
- explicit unexpected ports;
- fragments;
- noncanonical host forms;
- control characters;
- invalid percent encoding;
- URLs that differ only through normalization;
- alternate hostnames not covered by the canonical policy.

A URL such as:

```text
https://helmetsan.com:443/path
```

may be semantically valid but should have a single canonical representation for deduplication.

### I. `Baidu` is not actually integrated into the IndexNow ledger

`submitBaidu()` returns a result but does not record:

- URL batch identity;
- attempt number;
- quota remaining;
- response body;
- retry state;
- per-URL outcome;
- success timestamp.

It should use a separate Baidu queue and ledger. Baidu is not an IndexNow-compatible engine in the same operational sense.

---

## 1.3 Baidu quota and operational risks

Baidu’s push API has account/site-specific quotas and commonly returns quota information such as remaining daily allowance. The exact quota must be treated as provider-configured and verified against the current Baidu account/API documentation rather than hard-coded.

The client must parse and persist:

- accepted count;
- rejected count;
- remaining quota;
- malformed URL count;
- exact error code/message;
- site/token identity.

Do not retry blindly on quota exhaustion. A quota response should pause until the provider’s reset period.

### Recommended Baidu controls

```text
Separate queue: baidu
Separate daily token bucket
Separate host/site configuration
Separate retry policy
Separate quota telemetry
```

Before submission:

- deduplicate URLs;
- enforce a maximum batch size;
- submit only canonical URLs;
- restrict to URLs authorized for the configured Baidu site/token;
- do not assume “zh catalog” alone is sufficient authorization.

### China firewall and network considerations

Outbound requests from a European or US origin to Baidu may experience:

- DNS inconsistency;
- TLS handshake delays;
- intermittent timeouts;
- regional blocking;
- packet loss;
- asymmetric connectivity;
- provider-side throttling.

Do not put Baidu submission on the mutation or request path. Use an asynchronous worker with:

- short connect timeout;
- bounded total timeout;
- exponential backoff with jitter;
- circuit breaker;
- regional egress testing;
- DNS/TLS observability;
- a dead-letter queue.

If China delivery is strategically important, use a compliant regional worker or cloud egress path, subject to legal and contractual review. Never treat a failed non-China connection as evidence that Baidu rejected the URLs.

### Security issue

The code constructs:

```php
$this->baiduEndpoint . '&token=' . rawurlencode($this->baiduToken)
```

This assumes the endpoint already contains `?site=...`. Use a URL builder that correctly handles either `?` or `&`, and ensure tokens never appear in application logs, exception messages, proxy logs, or metrics labels.

---

# 2. Revenue Service and bot elimination

## 2.1 False positives are a material revenue risk

Blocking “bots” aggressively can block real users who use:

- iCloud Private Relay;
- VPNs;
- Tor;
- corporate egress gateways;
- mobile carrier NAT;
- privacy browsers;
- Firefox strict tracking protection;
- disabled JavaScript;
- unusual but legitimate user agents;
- accessibility tools;
- security-focused browsers.

A privacy VPN is not evidence of automation. IP reputation is probabilistic and often marks entire shared networks.

### Do not use binary bot logic

Use risk tiers:

```text
low risk       -> redirect normally
medium risk    -> redirect with soft controls
high risk      -> rate limit, challenge, or delayed response
confirmed abuse -> block
```

Do not block solely because of:

- missing cookies;
- VPN/hosting ASN;
- unusual user agent;
- privacy browser;
- absent JavaScript;
- Cloudflare score below an arbitrary threshold.

A legitimate rider should still be able to reach the merchant through a soft fallback.

## 2.2 Separate abuse prevention from attribution

Bot filtering should protect against:

- automated click inflation;
- scraping;
- redirect flooding;
- credential abuse;
- denial-of-service behavior.

It should not be used to make aggressive assumptions about user identity.

A safer design:

1. Resolve the offer server-side.
2. Apply a lightweight edge risk score.
3. Record a privacy-preserving event.
4. Redirect unless the request is clearly abusive.
5. Use delayed aggregation or fraud adjustment downstream.

For affiliate compliance and analytics, it is often safer to mark a click as:

```text
suspected_automated = 1
```

than to deny the redirect.

## 2.3 The `<2 ms` requirement changes the architecture

A synchronous call to any of these is incompatible with a reliable sub-2-ms redirect budget:

- external IP reputation API;
- Redis over a remote network;
- database lookup;
- DNS lookup;
- Cloudflare API;
- merchant availability check;
- bot challenge generation;
- complex PHP bootstrap.

Even a local database query may exceed 2 ms under contention. The target must be measured from the edge or redirect worker, not assumed from application code.

### Hardened redirect path

Precompute and cache all routing data:

```text
edge request
  -> validate signed route token or opaque route ID
  -> read in-memory/edge-cache offer
  -> apply cheap local risk rules
  -> emit asynchronous click event
  -> 307 redirect
```

Possible implementations:

- Cloudflare Worker with KV/Cache;
- Nginx map or local shared memory;
- in-process immutable configuration;
- Redis only if colocated and benchmarked;
- asynchronous queue via edge-compatible mechanism.

The origin should not be involved for normal redirects.

### Do not synchronously log to MySQL

The redirect must not wait for:

```sql
INSERT INTO wp_helmetsan_clicks ...
```

Use:

- buffered edge logs;
- Kafka/Redpanda;
- Redis Streams;
- Cloudflare Logpush;
- local append-only event files;
- a durable ingestion endpoint with fire-and-forget semantics.

Do not use unauthenticated “fire and forget” requests as the sole source of accounting; they can be lost. Treat access logs or edge event streams as the durable raw event source.

## 2.4 Trusted Cloudflare headers

Cloudflare headers are useful only if the origin is protected so attackers cannot send requests directly with forged values.

Required controls:

- firewall origin to Cloudflare IP ranges;
- reject direct origin traffic;
- verify provider-specific headers at the edge;
- do not trust arbitrary `CF-*` headers from the public internet;
- pin the deployment to the expected Cloudflare zone and configuration.

Country detection should be cached and treated as routing input, not identity proof.

## 2.5 Affiliate and redirect compliance

The route should not conceal the commercial destination. The page or surrounding link should disclose:

- merchant name;
- affiliate relationship;
- that the user is leaving the site;
- the applicable marketplace/merchant.

The server should select an offer from a server-side allowlist. Never accept arbitrary destination URLs or merchant IDs from query parameters.

Use a signed or opaque route identifier:

```text
/go/offer/8f3a...
```

rather than:

```text
/go?url=https://arbitrary.example
```

A `307` preserves the method. For ordinary affiliate clicks, `302` or `303` is often more appropriate unless method preservation is explicitly required. Whichever status is used, test caching behavior carefully.

---

# 3. Database performance and schema migration

## 3.1 A 440,000-row table is not automatically huge, but live DDL remains risky

The number of rows alone does not determine safety. The risk depends on:

- MariaDB version;
- storage engine;
- column type;
- existing indexes;
- row width;
- replication topology;
- workload;
- whether the alteration is `ALGORITHM=INSTANT`, `INPLACE`, or `COPY`;
- metadata lock duration;
- foreign keys and triggers.

An `ALTER TABLE` can appear to run online while waiting indefinitely for a metadata lock. A pending DDL can also block later traffic that needs a metadata lock.

### Never execute an unqualified production ALTER

First test:

```sql
SHOW CREATE TABLE wp_helmetsan_clicks_daily;
SELECT VERSION();
SHOW VARIABLES LIKE 'lock_wait_timeout';
SHOW VARIABLES LIKE 'max_statement_time';
```

Determine whether the operation supports:

```sql
ALGORITHM=INSTANT
LOCK=NONE
```

If the server rejects those clauses, stop rather than silently falling back to a table-copy operation.

## 3.2 Recommended migration methods

### Option A: Instant/in-place DDL

Use only after testing on a production-size clone:

```sql
ALTER TABLE wp_helmetsan_clicks_daily
  ADD COLUMN suspected_automated TINYINT(1) NOT NULL DEFAULT 0,
  ALGORITHM=INSTANT,
  LOCK=NONE;
```

The exact