# Timeouts for AI enrichment (504 Gateway Time-out)

Catalog and Brands AI enrichment can run for a while (API calls + 1s rate limit per call). If you see **504 Gateway Time-out** from nginx, increase timeouts as below.

## 1. PHP: max execution time

The plugin calls `set_time_limit(600)` for Catalog and Brand AI actions (10 minutes). If your host disables `set_time_limit`, set it in PHP config:

**Option A – `php.ini`** (server-wide or PHP-FPM pool):

```ini
max_execution_time = 600
```

**Option B – `.user.ini`** in the site’s document root (e.g. `/var/www/helmetsan.com/public/.user.ini`) if your host allows it:

```ini
max_execution_time = 600
```

Then reload PHP-FPM (e.g. `sudo systemctl reload php8.2-fpm` or your version).

## 2. Nginx: fastcgi read timeout

504 often comes from **nginx** closing the connection before PHP finishes. Raise the FastCGI read timeout.

**In your nginx server (or location) block** that handles PHP (e.g. `location ~ \.php$` or `location /` with `try_files`), add or adjust:

```nginx
location ~ \.php$ {
    # ... existing fastcgi params ...
    fastcgi_read_timeout 600;
    # optional: if you use proxy to PHP-FPM
    # proxy_read_timeout 600;
}
```

Or in the main `server` block if it applies to all PHP:

```nginx
server {
    # ...
    fastcgi_read_timeout 600;
    # ...
}
```

Then reload nginx:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

Use the same value as PHP (e.g. **600** seconds = 10 minutes). For very long runs (100+ helmets), use CLI instead: `wp helmetsan ai fill-missing --post-type=helmet --limit=100`.

## Summary

| Layer   | Setting                  | Recommended |
|--------|---------------------------|---------------|
| PHP    | `max_execution_time`      | `600` (10 min) |
| Nginx  | `fastcgi_read_timeout`    | `600` (10 min) |

The plugin sets PHP’s limit to 600s for AI actions; set nginx to 600s (or higher) on the server so requests can complete.
