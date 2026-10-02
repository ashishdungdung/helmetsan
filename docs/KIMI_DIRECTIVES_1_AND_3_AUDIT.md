# Executive verdict

The blueprint is directionally sound but not production-hardened. Its principal weaknesses are:

1. It treats IndexNow submission as a more predictable capacity mechanism than it is.
2. It can create a severe cache-warming stampede:  
   `98,115 URLs × 10 countries = 981,150 GETs`, potentially multiplied by retries and cache variants.
3. It does not establish that Nginx FastCGI cache, Cloudflare cache, WordPress cookies, headers, and user-agent behavior produce the same cache key.
4. It couples affiliate redirect latency to MariaDB availability.
5. It leaves ASIN resolution vulnerable to synchronous scraping during user clicks.
6. It risks overstating Amazon redirect requirements. A `301` is permanent; a `307` is temporary. They are not interchangeable, and there is no safe basis for claiming a universal Associates rule that mandates “301/307” without consulting the current locale-specific agreement.

The system should be redesigned around:

```text
User click:
  validate identifier
  select prevalidated destination
  enqueue telemetry asynchronously
  issue redirect

Background:
  resolve ASINs
  refresh product data
  batch telemetry
  submit IndexNow changes
  warm only selected cache tiers and URLs
```

---

# 1. IndexNow audit

## 1.1 Batch size and quotas

The blueprint’s `MAX_BATCH = 10_000` is appropriate for the documented JSON batch maximum.

However:

- `10,000` is a per-request maximum, not a guaranteed per-day allowance.
- There is no generally applicable universal “10,000 URLs per host per day” rule that should be hard-coded as fact.
- Participating search engines may impose undocumented or account/host-specific throttles.
- `429` is the operational signal to reduce rate.
- A successful submission is not an indexing guarantee.
- IndexNow propagates change notifications; it does not force crawling or ranking.

The worker should therefore use an internally imposed budget, for example:

```text
per-host soft budget: 25,000 submissions/day
per-request maximum: 10,000 URLs
initial rate: 1 request/minute
increase only after observing stable 2xx responses
```

The daily budget should be configurable, not presented as an IndexNow protocol quota.

## 1.2 HTTP response handling

The proposed handling is mostly correct:

- `200`: request accepted/successful
- `202`: accepted for processing where returned
- `400`: malformed request
- `403`: key/key-location problem or authorization failure
- `422`: invalid or non-owned URLs
- `429`: throttling
- `5xx`: transient service failure

Important correction: `200` or `202` means accepted by the submission service, not that every URL has been crawled or indexed.

The worker should record:

```text
batch_id
submission_timestamp
url_count
HTTP status
response body
retry count
first failure
final outcome
```

Without this, the system cannot distinguish:

```text
submitted successfully
from
submitted but never crawled
from
rejected due to malformed URLs
```

## 1.3 Key verification

The key file procedure is valid only if all of the following are true:

- The public file is reachable without authentication.
- It is served by the canonical host exactly matching the `host` value.
- It does not redirect through another hostname.
- Its body contains exactly the key, with no HTML wrapper or whitespace surprises.
- Cloudflare does not challenge or block the verification request.
- The file is not accidentally routed to WordPress.
- The hostname and scheme used in `host`, `keyLocation`, and submitted URLs are consistent.

A particularly important issue is hostname identity:

```text
helmetsan.com
www.helmetsan.com
```

These are not interchangeable for protocol validation. Pick one canonical host and reject all other forms in the submission pipeline.

The static file should be checked with headers as well as body:

```bash
curl --fail --silent --show-error \
  --location=false \
  --dump-header /tmp/indexnow.headers \
  --output /tmp/indexnow.body \
  https://helmetsan.com/c9a72e8140db4e5fb3d6812975ef83a0.txt

test "$(cat /tmp/indexnow.body)" = \
  "c9a72e8140db4e5fb3d6812975ef83a0"
```

Do not use `install` with identical source and destination paths:

```bash
install /opt/helmetsan/bin/indexnow_submit.py \
        /opt/helmetsan/bin/indexnow_submit.py
```

That is at best pointless and may fail depending on the platform. Write the file first, then install it to its final location.

## 1.4 URL validation is insufficient

The current `normalise_urls()` function checks only:

```python
url.startswith("https://helmetsan.com/")
```

That allows several dangerous or invalid cases:

- query strings;
- fragments;
- encoded duplicate paths;
- non-canonical paths;
- URLs returning redirects;
- URLs with `noindex`;
- URLs whose canonical points elsewhere;
- URLs excluded by robots policy;
- malformed URL structures that merely share the prefix.

Validation should parse the URL and enforce:

```python
parts.scheme == "https"
parts.hostname == "helmetsan.com"
parts.username is None
parts.password is None
parts.fragment == ""
parts.query == ""
```

Then perform a pre-submission eligibility check from a trusted database or sitemap manifest:

```text
status = 200
indexable = true
robots_allowed = true
canonical_url == submitted_url
content_type = text/html
```

Do not perform live validation against 98,000 URLs immediately before every submission. Build a durable eligibility manifest during publishing.

## 1.5 Queue semantics

The queue must be event-driven and idempotent.

Recommended record:

```text
url
event_type
content_hash
last_modified
priority
attempts
next_attempt_at
status
last_http_status
last_error
deduplication_key
```

Deduplicate on:

```text
(url, material_content_version)
```

not merely on URL. A URL with a new material version may legitimately be resubmitted.

The proposed queues also have an ambiguity:

```text
P1: price/dealer changes
```

Frequent price changes may generate noisy submissions without improving crawl outcomes. Separate:

```text
content/indexability changes
from
volatile commercial data changes
```

A price-only change should generally not force a full-page search-engine notification unless the page’s visible or structured content materially changes and the change is important.

---

# 2. Sitemap audit

## 2.1 Protocol limits

The sitemap plan needs to explicitly enforce the standard limits:

```text
maximum URLs per sitemap: 50,000
maximum uncompressed sitemap size: 50 MB
```

The proposed shard size of 5,000 is conservative and acceptable.

For localized content, the unit of sitemap inclusion should be a URL, not a product record. If one product has ten language URLs, it consumes ten URL entries.

Every URL should satisfy:

```text
HTTP 200
not noindex
self-canonical
included in the intended hreflang cluster
stable canonical hostname
```

`lastmod` must be the last material page modification, not the database synchronization time and not the current time on every regeneration.

Artificially changing `lastmod` on every sitemap build creates crawl noise and can reduce trust.

## 2.2 Hreflang risks

The phrase “language-consistent” is too vague for a 98,115-record multilingual system.

For each localized cluster:

- every alternate should return `200`;
- every alternate should list the others;
- each URL should include a self-reference;
- `x-default` should be deliberate, not automatic;
- language-region codes must be valid;
- translated URL paths must not be silently redirected to another language;
- canonical and hreflang must not conflict.

Example failure:

```text
/de/helme/foo/
canonical: https://helmetsan.com/en/helmets/foo/
hreflang: de -> /de/helme/foo/
```

That is a contradictory cluster and may cause the localized URL to be ignored.

## 2.3 GSC submission

The Search Console API submission is a notification mechanism, not a request to index all URLs.

Submit the sitemap index once, then monitor:

```text
submitted URLs
indexed URLs
excluded URLs
crawl anomalies
sitemap fetch errors
```

The API credentials must be stored outside the web root, but the blueprint should also require:

- refresh-token encryption or root-only permissions;
- service account/property access verification;
- audit logs;
- token rotation;
- no access tokens in shell history or process arguments.

The blueprint’s `feedpath` encoding must be generated by a proper URL encoder rather than manually assembled. Property formats differ between:

```text
sc-domain:helmetsan.com
https://helmetsan.com/
```

The exact property string must match the verified Search Console property.

---

# 3. Edge pre-warming audit

## 3.1 The scale is unsafe

The current model is potentially catastrophic:

```text
98,115 URLs × 10 countries = 981,150 requests
```

At concurrency 64, this can:

- saturate PHP-FPM;
- exhaust database connections;
- consume Nginx workers;
- trigger WordPress object-cache misses;
- cause Cloudflare bot challenges;
- create cache stampedes;
- evict more valuable cache entries;
- compete with real users;
- generate a large origin bill.

Ten “representative countries” do not warm ten Cloudflare POPs reliably. A request originates from one actual network location and generally reaches one selected Cloudflare edge. A header named `X-Helmetsan-Target-Country` does not move the request to a country-specific POP.

Therefore this comment is correct:

```python
# This is an application hint only.
```

But the existence of the header may still be dangerous if application or cache logic begins varying on it.

## 3.2 Do not warm by synthetic country headers

`CF-IPCountry` must not be forged.

If actual country-specific content is required, use one of:

1. Cloudflare Worker logic that deliberately normalizes country into a controlled cache key;
2. geographically distributed authorized workers;
3. real regional egress infrastructure;
4. no country-specific page cache, with country selection deferred to the redirect endpoint.

The fourth option is usually preferable for affiliate sites:

```text
cached product page = country-neutral
affiliate click endpoint = country-aware and dynamic
```

This avoids multiplying page-cache variants.

## 3.3 Cache-key audit

The blueprint does not establish the cache key at either layer.

You must explicitly document:

```text
Cloudflare cache key:
  scheme?
  hostname?
  path?
  query string?
  selected headers?
  cookies?
  device class?
  language?
  country?

Nginx FastCGI cache key:
  scheme?
  host?
  request URI?
  query string?
  method?
  cookies?
  authorization?
  user-agent?
  language?
```

A typical safe public HTML key might be:

```nginx
fastcgi_cache_key "$scheme$request_method$host$request_uri";
```

But only if the application does not vary on cookies, language headers, user-agent, country, or authorization.

If page content varies by any of these, either:

- include the relevant normalized value in the key; or
- bypass caching for that request; or
- remove the variation from the page.

Do not accidentally cache:

- logged-in pages;
- cart/account pages;
- pages with user-specific cookies;
- affiliate redirect responses;
- preview URLs;
- query-based tracking variants;
- pages containing country-specific prices under a country-neutral key.

A strong default is:

```text
GET/HEAD only
no Authorization
no WordPress login/session cookies
no preview parameters
no affiliate redirect paths
no unapproved query strings
```

## 3.4 Prewarmer request method

Use `GET`, not `HEAD`, if the goal is to populate application and edge HTML caches. Many cache layers treat `HEAD` specially or do not populate the same object.

The prewarmer must verify response headers:

```text
HTTP 200
Content-Type: text/html
Cache-Control permits caching
CF-Cache-Status: HIT or MISS, not BYPASS/DYNAMIC
X-FastCGI-Cache: HIT or MISS
```

A `200` alone is not evidence that anything was cached.

## 3.5 Origin protection

A hardened prewarmer needs all of the following:

### Bounded concurrency

Do not use a fixed `64` globally. Use a token bucket based on origin capacity.

Let:

```text
P = configured PHP-FPM worker capacity
R = reserved workers for real traffic
C = prewarm concurrency
```

Require:

```text
C <= max(1, floor(0.25 × (P - R)))
```

For example, if PHP-FPM has 40 workers and 20 are reserved for users:

```text
C <= floor(0.25 × 20) = 5
```

The exact factor must be load-tested, not assumed.

### Rate limiting

Use both:

```text
requests per second
and
maximum in-flight requests
```

Start at perhaps:

```text
1–2 requests/second
C = 2–5
```

Then increase only if:

```text
PHP-FPM utilization < 60%
DB CPU < 60%
Redis latency stable
5xx < 0.1%
Cloudflare challenge rate = 0
```

### Backoff

Back off on:

```text
429
403 challenge responses
502/503/504
origin queue growth
database saturation
```

A `403` from Cloudflare is not necessarily a permanent URL failure. It may be a security challenge or bot-management decision.

### Scheduling

Warm only:

1. newly published high-value pages;
2. pages about to receive a campaign;
