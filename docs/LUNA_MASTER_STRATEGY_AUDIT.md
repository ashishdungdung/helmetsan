# Helmetsan Full-Stack Architectural Audit

## Executive assessment

The architecture is fundamentally viable, but several controls currently operate as if they were stronger than they are:

1. **`robots.txt` is being treated as a security and bot-control mechanism.** It is neither. Attackers ignore it, and legitimate crawlers may interpret directives differently.
2. **The origin IP is a major strategic exposure.** Unless the firewall permits only Cloudflare IP ranges, all Cloudflare protections can be bypassed.
3. **The Cloudflare ASN challenge may block legitimate AI/search traffic while failing to stop sophisticated scrapers.**
4. **The cache design is insufficiently variant-aware for multilingual, authenticated, cookie-driven WordPress traffic.**
5. **The September 10 GA4 event is almost certainly automated traffic or low-quality traffic and should not be treated as a real acquisition event.**
6. **The current AI discoverability assets are useful but incomplete. `llms.txt` is not an established ranking signal and cannot substitute for structured product data, crawlable pages, feeds, and authoritative citations.**
7. **The architecture needs explicit controls for canonical URLs, alternate languages, Product/Offer schema, Google Merchant feeds, affiliate attribution, and cache invalidation.**

The highest-priority work is:

- Lock the origin behind Cloudflare.
- Make cache behavior explicitly safe for language, cookies, query parameters, redirects, and content negotiation.
- Implement a proper product data and feed layer.
- Separate legitimate crawler access from generic bot access.
- Reconcile robots, sitemaps, canonical, hreflang, and AI content policies.
- Establish server-side telemetry rather than relying on GA4 bot/session behavior.

---

# 1. Infrastructure and origin-server audit

## 1.1 Origin exposure is the most important unresolved risk

The server IP is publicly known:

```text
31.70.136.154
```

Cloudflare protection is only effective if the origin firewall rejects direct traffic from all sources except:

- Cloudflare IPv4 ranges
- Cloudflare IPv6 ranges
- Explicit administrative access sources, ideally through VPN, WireGuard, Tailscale, or a bastion
- Monitoring providers, if required

The Nginx `real_ip` configuration does not protect the origin. It only rewrites the client IP after a request has already reached Nginx.

### Required firewall policy

At the network firewall level:

```text
Allow TCP 80/443 from Cloudflare IPv4 ranges
Allow TCP 80/443 from Cloudflare IPv6 ranges
Allow SSH only from management VPN/bastion
Deny all other inbound traffic
```

Do not rely on Nginx alone. A direct attacker can otherwise bypass:

- Cloudflare WAF
- Cloudflare rate limits
- ASN challenge
- Cloudflare cache
- Cloudflare bot signals
- Cloudflare TLS and request normalization

Also verify:

- No DNS-only records expose the same origin.
- Mail, staging, development, API, and monitoring subdomains do not point to the origin.
- TLS certificates do not reveal additional hostnames through certificate transparency.
- Historical DNS records and passive DNS do not expose alternate IPs.
- The origin does not emit its IP in headers, error pages, sitemap URLs, or image URLs.

## 1.2 `real_ip` configuration requires strict validation

The following approach is correct only if every listed network is an official Cloudflare range and the origin cannot be reached through an untrusted intermediary.

Validate:

- Every Cloudflare IPv4 and IPv6 CIDR is current.
- No broad internal or provider ranges have accidentally been included.
- `real_ip_recursive on` is used only with trusted proxies.
- Application logs record both the TCP peer address and the derived real client IP during testing.

The logging format should include at least:

```text
$remote_addr
$realip_remote_addr
$http_cf_connecting_ip
$http_x_forwarded_for
$http_cf_ray
$request
$status
$request_time
$upstream_cache_status
$upstream_response_time
$http_user_agent
```

This is essential for detecting forged forwarding headers and diagnosing Cloudflare/origin discrepancies.

## 1.3 FastCGI cache key is incomplete

Current key:

```nginx
$scheme$host$request_uri
```

This includes the entire query string. That avoids some accidental collisions, but introduces other issues.

### Problems

#### Query fragmentation

These URLs become separate cache objects:

```text
/product/
?utm_source=google
?utm_source=facebook
?fbclid=...
?gclid=...
?format=md
```

Marketing parameters can produce substantial cache fragmentation and reduce hit ratio.

#### Cache poisoning through unapproved parameters

If application behavior changes based on arbitrary query parameters, an attacker may create cache variants or cause an unsafe response to be cached.

#### Content negotiation ambiguity

`?format=md` must always produce a distinct representation with:

- Correct `Content-Type`
- Correct canonical metadata
- Correct `Vary` behavior, if applicable
- No possibility of being served to ordinary HTML requests

#### Redirect variants

Because 301 and 302 are cached for ten minutes, malformed or temporary redirects could be cached and propagated.

### Recommended key strategy

Normalize only known functional parameters. Remove tracking parameters from the cache key:

```nginx
map $args $cache_args {
    default $args;
    "~^(.*)(^|&)(utm_source|utm_medium|utm_campaign|utm_term|utm_content|gclid|fbclid|msclkid|yclid)=[^&]*(&|$)(.*)$" ...;
}
```

In practice, robust query normalization is easier and safer at the application or edge layer. Recommended policy:

- Cache key includes only explicitly supported functional parameters.
- Ignore known analytics parameters.
- Treat `format=md` as a dedicated representation.
- Do not cache unknown query parameters unless required.
- Never let arbitrary query parameters alter cacheable HTML behavior.

For example:

```text
/product/                         -> HTML cache object
/product/?format=md               -> Markdown cache object
/product/?sort=price               -> either explicitly supported or bypassed
/product/?utm_source=x             -> same cache object as /product/
```

## 1.4 Cache bypass rules are not sufficient by themselves

The stated bypass conditions cover POST, administrative paths, cart, checkout, and login cookies. Audit all additional dynamic conditions:

- WooCommerce session cookies
- Cart fragments
- Currency cookies
- Polylang language cookies
- Consent cookies
- Personalization cookies
- Preview cookies
- A/B testing cookies
- Affiliate attribution cookies
- WordPress comment cookies
- `wordpress_logged_in_*`
- `woocommerce_items_in_cart`
- `wp_woocommerce_session_*`

If any of these alter HTML, the request must bypass or vary correctly.

A particularly dangerous configuration is:

1. First request has a personalization or language cookie.
2. The cache bypass condition misses it.
3. Personalized content is cached.
4. Anonymous visitors receive it.

Conversely, bypassing for every cookie destroys cache efficiency. Define a strict allowlist of cookies that matter and ignore irrelevant cookies such as analytics consent state when rendering is unaffected.

## 1.5 Cache-control headers must be aligned across Nginx, PHP, WordPress, and Cloudflare

Inspect actual response headers for:

- Product pages
- Category pages
- `/en/`, `/zh/`, and other localized pages
- `/robots.txt`
- `/llms.txt`
- `/llms-full.txt`
- `?format=md`
- 404 pages
- Redirects
- Logged-in pages
- Cart and checkout

Required controls include:

```http
Cache-Control: public, max-age=60, s-maxage=600
ETag: ...
Last-Modified: ...
Content-Type: text/html; charset=UTF-8
```

For private or dynamic responses:

```http
Cache-Control: private, no-store
```

Do not let upstream WordPress send contradictory cache headers that Cloudflare or Nginx silently override.

## 1.6 Purge endpoint is a high-value target

`/purge/helmets/` requires strict protection.

It should have:

- Authentication or a cryptographically strong secret
- IP allowlisting
- POST-only enforcement
- CSRF protection if browser-accessible
- Request signing and timestamp replay protection
- Audit logging
- Rate limiting
- No information leakage about purge status

It should not be reachable as an unauthenticated public URL. If using Nginx cache purge, ensure wildcard purge cannot be abused to cause cache exhaustion or purge the entire site.

A safer design is event-driven invalidation:

```text
Product update
  -> WordPress hook
  -> queue/webhook
  -> purge exact product URL
  -> purge affected category URLs
  -> purge language variants
  -> purge structured feed if applicable
```

Avoid purging the entire site for every product update.

## 1.7 Rate limiting needs endpoint-specific design

Current zones with large bursts are useful as a baseline but should be evaluated against real traffic.

Potential weaknesses:

- `nodelay` can allow a large burst to hit PHP simultaneously.
- Rate limits based on derived IP fail if the origin is directly reachable or if proxy trust is misconfigured.
- Shared IPs and corporate NAT can cause false positives.
- Search crawlers and AI crawlers may be throttled without identifying the affected endpoint.
- Login protection should include application-level controls, not just Nginx.

Recommended limits:

- Strict `/wp-login.php`
- Strict `/xmlrpc.php`, or disable XML-RPC if unused
- Strict `/wp-json/` write endpoints
- Separate limits for search, catalog, and expensive search endpoints
- Connection limits for PHP-FPM
- Per-IP and global concurrency controls
- Slow request controls for POST bodies

Add:

```nginx
limit_conn
client_body_timeout
client_header_timeout
send_timeout
keepalive_requests
```

Review PHP-FPM:

- `pm.max_children`
- `pm.max_requests`
- `request_terminate_timeout`
- slowlog
- status endpoint restricted to localhost
- OPcache sizing and hit rate
- JIT suitability for WordPress, which is often less important than OPcache and database tuning

## 1.8 Security drop rule is effective but incomplete

Returning `444` is reasonable for obvious exploit probes. However:

- It should not be treated as the primary security control.
- Ensure the regex cannot accidentally match legitimate product URLs or filenames.
- Include common variants such as URL-encoded paths only if Nginx normalizes them as expected.
- Monitor the rule with a separate counter or log sampling; otherwise attacks become invisible.
- Add upstream WAF and file-integrity detection.

The listed probes suggest opportunistic scanning, not necessarily a compromise. Confirm with:

- Webroot file integrity
- PHP file creation times
- WordPress admin users
- Cron jobs
- Database users
- Plugin/theme hashes
- Nginx access and error logs
- Shell history and authentication logs
- Outbound network connections

The malicious IP should be blocked at the firewall or Cloudflare level only if the address is stable and blocking it has no operational downside. Since attackers rotate addresses, behavior-based blocking matters more.

---

# 2. Cloudflare audit

## 2.1 “Free plan with Enterprise CDN edge routing” needs verification

The stated plan and capabilities should be reconciled against the actual account entitlement. Verify that the following are genuinely available and active:

- Cache Rules
- Custom WAF rules
- Bot Management features
- Edge TTL controls
- Cache Reserve, if used
- Origin rules
- Logpush
- Advanced rate limiting
- Workers or Transform Rules

Do not design around an Enterprise-only feature unless the account contract and zone configuration confirm it.

## 2.2 Cache rule path matching is likely incomplete

The rule matches:

```text
http.host eq "helmetsan.com"
and http.request.uri.path contains "/helmets/"
```

Potential gaps:

- `www.helmetsan.com`
- localized paths
- uppercase or encoded path variants
- category paths not containing `/helmets/`
- trailing slash variants
- product URLs outside `/helmets/`
- `/zh/helmets/`, `/de/helmets/`, etc., if applicable
- query parameter behavior
- responses with cookies
- origin `Set-Cookie`
- `no-cache` headers

Use explicit host normalization and a canonical redirect strategy. Decide whether `www` redirects to apex or vice versa, then make all canonical, sitemap, hreflang, and feed URLs consistent.

The 600-second edge TTL and 60-second browser TTL are sensible for a catalog, but only if:

- Product changes purge immediately.
- Price and availability changes are not stale beyond business tolerance.
- Structured data and visible content update together.
- Browser caching does not retain obsolete stock or price data.

## 2.3 ASN challenge rule has significant false-positive risk

Rule:

```text
ip.geoip.asnum in {132203 45102 150436}
and not cf.client.bot
```

This is not a reliable scraper classifier.

Problems:

- Legitimate users may be located behind Tencent, Alibaba, or ByteDance infrastructure.
- Corporate proxies and mobile networks can map to these ASNs.
- AI/search crawlers may use cloud infrastructure in those ASNs.
- Attackers can operate from other ASNs.
- `cf.client.bot` is not a universal proof of legitimacy.
- A managed challenge may be inaccessible to non-browser crawlers.
- Some legitimate fetchers do not execute JavaScript and will fail.

This rule could reduce:

- AI citation crawling
- Product discovery from Asian markets
- Search preview retrieval
- Affiliate and merchant feed verification
- Legitimate API clients

Recommended replacement:

1. Use Cloudflare bot signals and WAF rules where available.
2. Challenge based on behavioral signals, not ASN alone.
3. Create explicit exceptions for verified search and AI crawlers where verification is possible.
4. Monitor challenge outcomes by ASN, path, user agent, country, and `cf-ray`.
5. Use rate limits and request scoring for catalog scraping.
6. Block only confirmed abusive patterns.

Do not automatically trust a user agent claiming to be `Googlebot`, `GPTBot`, or `ClaudeBot`. Verify reverse and forward DNS where appropriate, but use bot verification carefully because some services do not publish stable IP ranges.

## 2.4 Cloudflare logs should be integrated into the audit pipeline

GA4 cannot show all bot traffic. Configure, where available:

- HTTP request logs
- WAF events
- Firewall events
- Bot scores
- Cache status
- ASN and country
- User agent
- Ray ID
- Origin response time
- Challenge outcome

Create dashboards for:

- Cache hit ratio by path and language
- Origin request rate
- PHP upstream latency
- 4xx/5xx by bot
- Challenges by ASN
- Verified bot traffic
- Unknown high-volume agents
- Query parameter distribution
- Cache bypass reasons

---

# 3. WordPress, PHP, plugin, and theme audit

## 3.1 PHP 8.5 compatibility must be tested, not assumed

PHP 8.5.3 is a relatively new runtime in the stated context. Verify compatibility of:

- WordPress core
- WooCommerce, if present
- Polylang
- `helmetsan-core`
- `helmetsan-theme`
- SEO plugins
- Payment plugins
- Cache plugins
- Feed generators
- Schema libraries

Use a staging environment with:

- Production database snapshot anonymized
- PHP error logging enabled
- Deprecation logging
- Full crawl tests
- Checkout/cart tests
- REST and admin tests
- Plugin conflict tests

Do not expose detailed PHP errors publicly.

## 3.2 Theme/plugin boundary needs architectural discipline

The `helmetsan-core` plugin should own:

- Product data models
- Structured data generation
- Feeds
- AI manifests
- robots policy
- content APIs
- cache purge events
- telemetry hooks

The theme should own:

- Presentation
- Templates
- accessibility
- layout
- visual components

SEO-critical functionality should not disappear if the theme is changed.

The plugin must avoid:

- Expensive uncached queries on every request
- Generating full catalogs for every `llms-full.txt` request
- Running external API calls synchronously during page rendering
- Adding nonces or session cookies to public GET pages
- Loading admin libraries on frontend requests
- Injecting inconsistent schema across translated pages

## 3.3 Database and query performance

Audit:

- Slow query log
- `wp_options` autoload size
- Product metadata query count
- Taxonomy joins
- Polylang translation lookups
- Search queries
- Related-product queries
- Feed generation queries
- REST API queries
- Object cache hit rate

Recommended:

- Redis object cache if operationally justified
- Proper indexes for custom product tables and lookup fields
- Precomputed product aggregates
- Background generation of feeds and AI manifests
- Pagination for all administrative and feed queries
- Avoid `LIKE '%term%'` searches on large metadata tables
- Use a search index if catalog search grows

## 3.4 `robots.txt` architecture has several conceptual problems

A six-tier dynamic policy is organized, but `robots.txt` has limitations:

- `Crawl-delay` is ignored by Google.
-