# Adversarial cross-audit

## Executive verdict

The architecture is viable, but it currently assumes that:

- Cloudflare sees all traffic.
- ASN filtering meaningfully identifies scrapers.
- cache keys are equivalent to safe content variants.
- Polylang language selection is deterministic.
- query strings are harmless.
- GA4 reflects actual traffic quality.
- public catalog pages can be cached without a strict cookie/header policy.

Those assumptions are unsafe.

The highest-risk failure is not a sophisticated Cloudflare bypass. It is **variant confusion**:

> A response generated for one language, cookie state, query format, redirect decision, currency, or authentication state is stored and served to another request.

That can cause:

- cross-language content leakage
- incorrect canonical and hreflang tags
- wrong price or availability
- cached authenticated or personalized content
- JSON or Markdown served as HTML
- permanent or semi-permanent redirect poisoning
- search-engine indexing corruption
- cache amplification by query-string flooding

---

# 1. Critical flaws and unrealistic assumptions

## 1.1 The origin IP remains a complete bypass unless firewall-enforced

Nginx `real_ip` configuration does not protect the origin. If `31.70.136.154` accepts public TCP connections on ports 80 or 443, an attacker can bypass Cloudflare entirely.

The origin firewall must enforce:

```text
TCP/80, TCP/443:
    allow Cloudflare IPv4 ranges
    allow Cloudflare IPv6 ranges
    deny all

SSH:
    allow only management VPN, bastion, or fixed administrative IPs
    deny all

All other inbound ports:
    deny
```

Do not allow “known monitoring providers” unless absolutely necessary. Prefer monitoring through Cloudflare or an authenticated private path.

Also check:

- `www`, `staging`, `dev`, `api`, image, mail, and legacy DNS records
- IPv6 records
- alternate HTTPS ports
- origin certificates
- certificate-transparency hostnames
- historical DNS and passive DNS
- WordPress-generated absolute URLs
- sitemap and feed URLs
- error pages and response headers

An overlooked IPv6 address or old DNS-only hostname can make the Cloudflare controls irrelevant.

---

## 1.2 ASN blocking is weak against residential and mobile proxy networks

The ASN rule:

```text
ip.geoip.asnum in {132203 45102 150436}
and not cf.client.bot
```

will not stop residential proxy scraping. It also risks blocking legitimate users and legitimate machine clients.

Residential proxy traffic may appear as:

- consumer broadband
- mobile carrier
- hotel or university networks
- rotating IPv4 addresses
- rotating IPv6 prefixes
- thousands of unrelated IPs across many ASNs

A scraper can maintain a low request rate per IP while producing a high aggregate request rate.

Therefore:

- Do not use ASN as the primary anti-scraping control.
- Do not use IP reputation alone.
- Do not trust `User-Agent`.
- Do not challenge an entire country or cloud provider unless the business accepts the loss.
- Do not assume `cf.client.bot` identifies every legitimate crawler.

Use layered behavioral controls:

- per-IP rate limits
- per-session and per-cookie limits
- per-prefix limits where appropriate
- global origin concurrency limits
- expensive-path controls
- request-cost scoring
- anomaly detection by URL sequence
- query-string abuse detection
- cache-hit and origin-miss monitoring
- account or API authentication for machine access

The strongest scraper signal is often not request volume. It is behavior:

```text
robots.txt
-> sitemap
-> every product URL sequentially
-> every language variant
-> ?format=json
-> ?format=md
-> image and feed enumeration
```

That pattern should be detectable even when the IP changes on every request.

---

## 1.3 Residential proxy defenses must not be based on CAPTCHA alone

A CAPTCHA or managed challenge is not a complete control. Headless browsers, challenge-solving services, and real browser automation can pass it.

Use challenge escalation rather than universal challenge:

```text
Low-risk public catalog request:
    allow

High request rate:
    rate-limit or managed challenge

Repeated cache misses:
    challenge

Sequential full-catalog traversal:
    challenge or throttle

Malformed query formats:
    block

POST, login, XML-RPC, REST write:
    strict rate limit and WAF controls
```

Avoid requiring JavaScript for legitimate search, AI, merchant, or accessibility clients unless you have verified alternatives.

---

## 1.4 Cookie and `Accept-Language` negotiation is a cache-poisoning risk

Polylang often uses some combination of:

- URL language prefixes
- language cookies
- browser `Accept-Language`
- root-domain detection
- redirects
- query or path negotiation

If the response depends on a cookie or header, the cache must either:

1. vary on that input, or
2. bypass caching.

The current key:

```nginx
$scheme$host$request_uri
```

does not include:

- `Cookie`
- `Accept-Language`
- `User-Agent`
- `Accept`
- currency
- consent state
- authentication state

Consequently, this sequence is dangerous:

```text
GET /helmets/product-x/
Cookie: pll_language=zh
Accept-Language: zh-CN
```

The origin emits:

```text
301 Location: /zh/helmets/product-x/
```

If that redirect is cached by Cloudflare or Nginx, a later English visitor may receive the Chinese redirect.

The reverse is also possible.

### Required rule

Public localized pages should use deterministic language paths:

```text
/en/helmets/product-x/
/zh/helmets/product-x/
/de/helmets/product-x/
```

Do not use cookies or `Accept-Language` to vary the response for an already-localized path.

For the root path only, use one of these safer approaches:

### Preferred

Redirect `/` to one fixed default language:

```text
/ -> /en/
```

### Acceptable

Perform language negotiation at `/` but never cache the result:

```http
Cache-Control: private, no-store
Vary: Cookie, Accept-Language
```

Do not cache language-negotiated redirects.

---

## 1.5 Query strings create both cache fragmentation and representation confusion

The architecture mentions:

```text
?format=md
?format=json
```

but the cache strategy does not sufficiently distinguish:

- HTML
- Markdown
- JSON
- arbitrary unknown parameters
- tracking parameters
- malicious parameter combinations

Potential failures include:

```text
/product/?format=json
```

being cached and returned for:

```text
/product/
```

or:

```text
/product/?format=md&utm_source=x
```

creating a new object for every campaign URL.

Other dangerous examples:

```text
/product/?format=JSON
/product/?format=json&x=1
/product/?format=md&lang=zh
/product/?format=json&callback=evil
/product/?_format=json
```

### Safe policy

Use an allowlist:

- no query string: normal HTML
- exactly `format=md`: Markdown
- exactly `format=json`: JSON
- known functional parameters: explicitly handled
- all other query strings: bypass cache or redirect to a normalized URL

Do not cache JSONP. Do not let arbitrary query parameters alter output.

---

# 2. Highest-risk integration points

| Priority | Risk | Failure mode | Impact | Required treatment |
|---|---|---|---|---|
| Critical | Public origin | Direct requests bypass Cloudflare | WAF, rate limits, cache, bot controls bypassed | Firewall allow only Cloudflare and admin VPN |
| Critical | Language redirect caching | Cookie or `Accept-Language` redirect cached globally | Cross-language redirects and indexing corruption | Never cache negotiation responses |
| Critical | Cookie-insensitive cache | Personalized/authenticated/language content cached publicly | Data leakage and wrong content | Strict bypass policy; public pages must be cookie-independent |
| Critical | Query representation collision | JSON/Markdown object served as HTML or vice versa | Broken pages, schema/API corruption | Exact format allowlist and separate cache policy |
| High | Residential proxy scraping | IP/ASN controls become ineffective | Catalog extraction and origin load | Behavioral controls, global concurrency, request-cost controls |
| High | Cloudflare/NGINX trust mismatch | Forged forwarding headers or incorrect client identity | Rate-limit bypass and bad logs | Trust only Cloudflare ranges; log peer and derived IP |
| High | Stale price/availability | CDN cache retains old Offer data | Commercial and legal risk | Purge on inventory/price changes; short TTL where necessary |
| High | Purge endpoint abuse | Attacker flushes cache repeatedly | Origin overload and cache exhaustion | Authenticated signed POST, allowlist, rate limit |
| High | Alternate origin hostname | DNS-only or legacy endpoint exposes origin | Cloudflare bypass | Full DNS and certificate inventory |
| Medium | ASN challenge false positives | Legitimate crawlers/users challenged | Lost discovery and conversions | Behavioral challenge with verified exceptions |
| Medium | Query flooding | Thousands of cache objects or origin misses | Cache fragmentation and PHP load | Bypass unknown args; normalize tracking args |
| Medium | Schema inconsistency | Visible price/language differs from JSON-LD | Search penalties and poor eligibility | Generate schema from same product object as template |
| Medium | GA4 overreliance | Bot traffic interpreted as acquisition | Incorrect decisions | Use server logs and Cloudflare telemetry |

---

# 3. Server Nginx configuration

The following is intentionally conservative. It favors correctness over maximum cache hit ratio.

## 3.1 Trusted proxy and logging configuration

Place `set_real_ip_from` directives in the `http` context or the appropriate server-wide configuration. Maintain the Cloudflare ranges from the official published list rather than hard-coding an obsolete list.

```nginx
# http context
include /etc/nginx/cloudflare-realip.conf;

real_ip_header CF-Connecting-IP;
real_ip_recursive on;

log_format edge_audit
    '$remote_addr peer=$realip_remote_addr '
    'cfip=$http_cf_connecting_ip '
    'xff="$http_x_forwarded_for" '
    'ray=$http_cf_ray '
    'host=$host request="$request" '
    'status=$status bytes=$body_bytes_sent '
    'rt=$request_time urt=$upstream_response_time '
    'cache=$upstream_cache_status '
    'ua="$http_user_agent" '
    'cookie="$http_cookie"';

access_log /var/log/nginx/edge_audit.log edge_audit;
```

Operationally, avoid retaining full cookies indefinitely. Redact or hash sensitive cookie values in long-term logs.

The server should also reject direct requests whose TCP peer is not an approved proxy. Network firewall enforcement remains the primary control.

---

## 3.2 Safe cache decision maps

These maps belong in the `http` context.

```nginx
map $request_method $skip_method_cache {
    default 1;
    GET     0;
    HEAD    0;
}

map $http_authorization $skip_authorization {
    default 1;
    ""      0;
}

map $http_cookie $skip_cookie_cache {
    default 0;

    # Authentication and WordPress state
    "~*wordpress_logged_in_"             1;
    "~*wordpress_sec_"                   1;
    "~*comment_author_"                  1;
    "~*wp-postpass_"                     1;

    # WooCommerce state
    "~*woocommerce_items_in_cart"       1;
    "~*woocommerce_cart_hash"            1;
    "~*wp_woocommerce_session_"          1;

    # Preview and editing state
    "~*wordpress_test_cookie"            1;
    "~*wp-settings-"                     1;
    "~*wp-saving-post"                   1;
    "~*preview=true"                     1;

    # Only retain these if they actually alter rendered HTML
    "~*pll_language="                    1;
    "~*currency="                        1;
    "~*affiliate"                        1;
    "~*ab_test"                          1;
}

map $request_uri $skip_private_paths {
    default              0;
    "~^/wp-admin(?:/|$)" 1;
    "~^/wp-login\.php$"  1;
    "~^/wp-cron\.php$"   1;
    "~^/xmlrpc\.php$"    1;
    "~^/wp-json(?:/|$)"  1;
    "~^/cart(?:/|$)"     1;
    "~^/checkout(?:/|$)" 1;
    "~^/my-account(?:/|$)" 1;
    "~^/purge/"          1;
}

# Cache only exact, explicitly supported public representations.
map $args $representation {
    ""                html;
    "format=md"       markdown;
    "format=json"     json;
    default           other;
}

map $representation $skip_representation_cache {
    html       0;
    markdown   0;
    json       0;
    other      1;
}

# Root language negotiation must not be cached.
map $request_uri $skip_language_negotiation {
    default 0;
    "~^/$"  1;
}

map "$skip_method_cache:$skip_authorization:$skip_cookie_cache:$skip_private_paths:$skip_representation_cache:$skip_language_negotiation" $skip_cache {
    default 1;
    "0:0:0:0:0:0" 0;
}
```

Important: if Polylang or custom code uses additional cookies, add them only after verifying that they affect the rendered response.

---

## 3.3 Cache key

A conservative key that separates representations:

```nginx
map $representation $cache_suffix {
    html       "|html";
    markdown   "|md";
    json       "|json";
    default    "|uncacheable";
}

fastcgi_cache_key "$scheme|$host|$request_uri$cache_suffix";
```

Because `$request_uri` includes the query string, exact allowed query strings remain distinct. Unknown query strings bypass cache.

If tracking parameters must be normalized rather than bypassed, perform normalization at Cloudflare Worker, application middleware, or a tested Nginx njs/Lua layer. Avoid an untested regex-based query-string deletion scheme in production.

---

## 3.4