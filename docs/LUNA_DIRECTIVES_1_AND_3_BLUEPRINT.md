# Helmetsan Operational Execution Blueprint

This blueprint assumes:

- Canonical host: `https://helmetsan.com`
- WordPress and custom `helmetsan-core` plugin
- Nginx + PHP-FPM
- Redis Object Cache
- Cloudflare proxy
- MariaDB available for durable telemetry
- Python 3.11+ available for workers

Several important constraints apply:

1. **Google’s Indexing API is not a general-purpose page-indexing API.** It is officially limited to `JobPosting` and livestream pages. Use XML sitemaps and Search Console for helmet/product pages.
2. **IndexNow does not guarantee indexing.** It submits change notifications to participating engines.
3. **Cloudflare cache cannot be guaranteed to be 100% warm.** Cache keys, POP selection, cache bypass rules, cookies, query strings, bot policies, and purge events affect the result.
4. **Do not manually forge `CF-IPCountry` as if it were a genuine visitor country.** Use controlled internal prewarming headers and, if country-specific cache priming is required, Cloudflare Workers or real country-originating traffic.
5. **Amazon affiliate behavior must comply with the current Associates Operating Agreement.** Do not cache Amazon pricing or availability beyond permitted periods, and do not conceal the commercial nature of links.

---

# 1. IndexNow Surge System

## 1.1 Key verification

Create the verification file:

```bash
sudo install -d -m 0755 /var/www/helmetsan/public

sudo tee /var/www/helmetsan/public/c9a72e8140db4e5fb3d6812975ef83a0.txt >/dev/null <<'EOF'
c9a72e8140db4e5fb3d6812975ef83a0
EOF

sudo chown www-data:www-data \
  /var/www/helmetsan/public/c9a72e8140db4e5fb3d6812975ef83a0.txt
```

Verify:

```bash
curl -fsS https://helmetsan.com/c9a72e8140db4e5fb3d6812975ef83a0.txt
```

The response must be exactly:

```text
c9a72e8140db4e5fb3d6812975ef83a0
```

Nginx should serve this as a static file without WordPress involvement:

```nginx
location = /c9a72e8140db4e5fb3d6812975ef83a0.txt {
    default_type text/plain;
    try_files $uri =404;
    access_log off;
    add_header Cache-Control "public, max-age=86400, immutable";
}
```

---

## 1.2 IndexNow request format

IndexNow supports one or more URLs in a JSON POST. Use no more than 10,000 URLs per request.

```http
POST https://api.indexnow.org/indexnow
Content-Type: application/json

{
  "host": "helmetsan.com",
  "key": "c9a72e8140db4e5fb3d6812975ef83a0",
  "keyLocation": "https://helmetsan.com/c9a72e8140db4e5fb3d6812975ef83a0.txt",
  "urlList": [
    "https://helmetsan.com/en/helmets/shoei-x-spirit-3/",
    "https://helmetsan.com/de/helme/shoei-x-spirit-3/"
  ]
}
```

Use absolute canonical URLs only. Do not submit:

- `localhost`
- staging domains
- URLs with session parameters
- duplicate language variants
- noindex pages
- canonicalized pages that point elsewhere
- URLs blocked by `robots.txt`

A successful submission is generally `200`. Treat `202` as accepted where returned. Handle:

- `400`: malformed request
- `403`: invalid key or key location
- `422`: URLs do not belong to the host or are invalid
- `429`: throttled; back off
- `5xx`: retry with exponential backoff

---

## 1.3 Production IndexNow worker

Create `/opt/helmetsan/bin/indexnow_submit.py`:

```python
#!/usr/bin/env python3
from __future__ import annotations

import json
import random
import sys
import time
from pathlib import Path
from typing import Iterable

import requests

API = "https://api.indexnow.org/indexnow"
HOST = "helmetsan.com"
KEY = "c9a72e8140db4e5fb3d6812975ef83a0"
KEY_LOCATION = f"https://{HOST}/{KEY}.txt"

MAX_BATCH = 10_000
MAX_ATTEMPTS = 7
TIMEOUT = 30

session = requests.Session()
session.headers.update({
    "User-Agent": "Helmetsan-IndexNow/1.0 (+https://helmetsan.com)"
})


def chunks(items: list[str], size: int) -> Iterable[list[str]]:
    for i in range(0, len(items), size):
        yield items[i:i + size]


def normalise_urls(values: Iterable[str]) -> list[str]:
    output = []
    seen = set()

    for value in values:
        url = value.strip()
        if not url:
            continue

        if not url.startswith(f"https://{HOST}/"):
            raise ValueError(f"URL is not an HTTPS Helmetsan URL: {url}")

        if url not in seen:
            seen.add(url)
            output.append(url)

    return output


def submit_batch(urls: list[str]) -> None:
    payload = {
        "host": HOST,
        "key": KEY,
        "keyLocation": KEY_LOCATION,
        "urlList": urls,
    }

    for attempt in range(MAX_ATTEMPTS):
        try:
            response = session.post(
                API,
                json=payload,
                timeout=TIMEOUT,
            )
        except requests.RequestException as exc:
            if attempt == MAX_ATTEMPTS - 1:
                raise RuntimeError(f"Network failure: {exc}") from exc

            delay = min(300, 2 ** attempt * 5) + random.uniform(0, 2)
            time.sleep(delay)
            continue

        if response.status_code in (200, 202):
            print(f"submitted={len(urls)} status={response.status_code}")
            return

        if response.status_code == 429 or response.status_code >= 500:
            retry_after = response.headers.get("Retry-After")

            if retry_after and retry_after.isdigit():
                delay = min(900, int(retry_after))
            else:
                delay = min(900, 2 ** attempt * 10) + random.uniform(0, 5)

            if attempt == MAX_ATTEMPTS - 1:
                raise RuntimeError(
                    f"IndexNow exhausted retries: {response.status_code} "
                    f"{response.text[:500]}"
                )

            time.sleep(delay)
            continue

        raise RuntimeError(
            f"IndexNow permanent failure: {response.status_code} "
            f"{response.text[:1000]}"
        )


def main() -> int:
    if len(sys.argv) != 2:
        print(f"usage: {sys.argv[0]} urls.txt", file=sys.stderr)
        return 2

    path = Path(sys.argv[1])
    urls = normalise_urls(path.read_text(encoding="utf-8").splitlines())

    for batch in chunks(urls, MAX_BATCH):
        submit_batch(batch)
        # Keep a small inter-batch pause even when the service accepts quickly.
        time.sleep(2)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
```

Install:

```bash
sudo install -m 0755 /opt/helmetsan/bin/indexnow_submit.py \
  /opt/helmetsan/bin/indexnow_submit.py

python3 -m venv /opt/helmetsan/venv
/opt/helmetsan/venv/bin/pip install requests
```

Run:

```bash
/opt/helmetsan/venv/bin/python \
  /opt/helmetsan/bin/indexnow_submit.py \
  /var/lib/helmetsan/indexnow/priority-urls.txt
```

### Prioritization

Maintain separate queues:

| Queue | Examples | Priority |
|---|---|---:|
| P0 | Top helmets, high-volume brand pages, newly published commercial pages | 100 |
| P1 | Updated product specifications, price/dealer changes | 80 |
| P2 | Category and comparison pages | 60 |
| P3 | Long-tail localized product records | 30 |
| P4 | Low-value or stale pages | 0 |

Do not repeatedly resubmit all 98,115 records. Submit when:

- a page is newly published;
- the canonical URL changes;
- material content changes;
- stock/dealer availability changes materially;
- structured data changes;
- a previously unavailable product becomes active.

A practical first surge:

```text
P0: 5,000 URLs
P1: 15,000 URLs
P2: 20,000 URLs
P3: remaining changed URLs
```

Submit P0 immediately, then drain lower queues with rate-limited workers.

---

# 2. Sitemap and Google Search Console Protocol

## 2.1 Expected Yoast structure

Typical Yoast output:

```text
https://helmetsan.com/sitemap_index.xml
https://helmetsan.com/helmet-sitemap.xml
https://helmetsan.com/helmet-sitemap2.xml
https://helmetsan.com/category-sitemap.xml
```

The exact names depend on the post type and Yoast configuration. Confirm them:

```bash
curl -fsS https://helmetsan.com/sitemap_index.xml
```

For 98,115 localized records, use sensible shard sizes. For example:

```text
helmet-sitemap.xml       5,000 URLs
helmet-sitemap2.xml      5,000 URLs
...
```

Ensure all sitemap URLs are:

- canonical;
- HTTP 200;
- indexable;
- self-canonical;
- language-consistent;
- present in `hreflang` alternates where applicable.

`robots.txt`:

```text
User-agent: *
Disallow: /wp-admin/
Disallow: /wp-login.php

Sitemap: https://helmetsan.com/sitemap_index.xml
```

---

## 2.2 Sitemap submission

Google’s old sitemap ping endpoint should not be treated as the primary mechanism. Use Search Console API submission.

The REST method is:

```http
PUT https://www.googleapis.com/webmasters/v3/sites/{siteUrl}/sitemaps/{feedpath}
Authorization: Bearer ACCESS_TOKEN
```

For a URL-encoded site property:

```text
siteUrl = sc-domain%3Ahelmetsan.com
feedpath = https%3A%2F%2Fhelmetsan.com%2Fsitemap_index.xml
```

Example:

```bash
curl -X PUT \
  -H "Authorization: Bearer ${GOOGLE_ACCESS_TOKEN}" \
  "https://www.googleapis.com/webmasters/v3/sites/sc-domain%3Ahelmetsan.com/sitemaps/https%3A%2F%2Fhelmetsan.com%2Fsitemap_index.xml"
```

Submit only the index sitemap unless individual child sitemaps are separately useful for diagnostics.

### Search Console API notes

Create a Google Cloud project, enable the Search Console API, create OAuth credentials, and grant the account access to the Search Console property. Store refresh tokens outside the web root, for example:

```text
/etc/helmetsan/gsc.env
```

Permissions:

```bash
sudo chmod 0600 /etc/helmetsan/gsc.env
```

Do not expose the access token through a WordPress endpoint.

---

## 2.3 Google indexing API restriction

Do not use the Google Indexing API for helmet product pages. It is not an approved general indexing submission mechanism.

Use:

1. XML sitemap inclusion;
2. Search Console sitemap submission;
3. strong internal linking;
4. accurate `lastmod`;
5. valid canonical and `hreflang`;
6. server-side rendered content;
7. IndexNow for participating engines;
8. normal crawl discovery.

---

# 3. Edge Pre-Warming Crawler

## 3.1 Correct cache-warming model

A request to the origin can warm the origin’s FastCGI cache but does not necessarily warm every Cloudflare POP.

Recommended model:

```text
Pre-warmer
   |
   | HTTPS request to public hostname
   v
Cloudflare POP
   |
   | MISS
   v
Nginx FastCGI microcache
   |
   | MISS
   v
PHP-FPM / WordPress
```

The pre-warmer must request the public Cloudflare hostname, not the origin IP, if the objective is to warm Cloudflare.

Do not set `CF-IPCountry` manually and assume Cloudflare will accept it. Cloudflare controls that header at the edge. For country-specific cache variants:

- use a Cloudflare Worker to normalize country into the cache key; or
- use actual requests from geographically appropriate egress locations; or
- avoid country in the cache key unless content truly differs by country.

If content differs only at affiliate redirect time, keep product pages country-neutral and route `/go/{slug}` dynamically.

---

## 3.2 Internal prewarm authentication

Generate a secret:

```bash
openssl rand -hex 32
```

Store it in:

```text
/etc/helmetsan/prewarm.env
```

```dotenv
PREWARM_TOKEN=replace-with-64-hex-character-secret
```

Protect the route at Nginx:

```nginx
map $http_x_helmetsan_prewarm $is_prewarm {
    default 0;
    "replace-with-64-hex-character-secret" 1;
}

server {
    ...

    location / {
        # Only use this if the application needs to know it is a prewarm request.
        # Do not bypass all security controls globally.
        proxy_set_header X-Helmetsan-Prewarm $http_x_helmetsan_prewarm;
    }
}
```

For PHP-FPM, pass the header:

```nginx
fastcgi_param HTTP_X_HELMETSAN_PREWARM $http_x_helmetsan_prewarm;
```

Prefer an allowlisted source IP in addition to the token. Never place the token in public HTML, JavaScript, or logs.

---

## 3.3 Async Python pre-warmer

Create `/opt/helmetsan/bin/prewarm.py`:

```python
#!/usr/bin/env python3
from __future__ import annotations

import asyncio
import os
import sys
from collections import Counter
from pathlib import Path
from urllib.parse import urlsplit

import aiohttp
from aiohttp import ClientTimeout


TOKEN = os.environ["PREWARM_TOKEN"]
CONCURRENCY = int(os.getenv("PREWARM_CONCURRENCY", "64"))
TIMEOUT = ClientTimeout(total=20, connect=5, sock_read=15)

# These are representative countries. Use only if your edge/application
# deliberately varies content by country.
COUNTRIES = ["US", "CA", "DE", "FR", "IT", "ES", "IN", "GB", "JP", "NL"]


def load_urls(path: str) -> list[str]:
    seen = set()
    output = []

    for line in Path(path).read_text(encoding="utf-8").splitlines():
        url = line.strip()

        if not url or url.startswith("#"):
            continue

        parts = urlsplit(url)
        if parts.scheme != "https" or parts.netloc != "helmetsan.com":
            raise ValueError(f"Invalid URL: {url}")

        if url not in seen:
            seen.add(url)
            output.append(url)

    return output


async def fetch(
    session: aiohttp.ClientSession,
    semaphore: asyncio.Semaphore,
    url: str,
    country: str,
) -> tuple[str, str, int | str]:
    headers = {
        "User-Agent": (
            "Helmetsan-Cache-Prewarmer/1.0 "
            "(authorized internal crawler; https://helmetsan.com)"
        ),
        "X-Helmetsan-Prewarm": TOKEN,
        # This is an application hint only. It is not a replacement for the
        # Cloudflare-generated CF-IPCountry header.
        "X-Helmetsan-Target-Country": country,
        "Accept": "text/html,application/xhtml+xml",
    }

    async with semaphore:
        for attempt in range(3):
           