## Current conclusion

**Local catalog work is complete and verified. It is not yet fully live or synchronized on the remote server.**

The remote evidence confirms that deployment is incomplete:

- Remote files still contain **1,914 instances of `is delivers`**.
- Remote WordPress contains only **1,933 helmet posts and 15 motorcycle posts**, which does not correspond to the complete local catalog of:
  - 3,247 motorcycles
  - 2,219 helmets
  - 27 accessories

Therefore, the correct sequence is:

1. Back up the remote state.
2. Transfer the verified local data.
3. Run an idempotent WordPress ingestion/synchronization.
4. Clear all cache layers.
5. Verify both files and live URLs.

Do not mark the deployment complete until the final verification passes.

---

# 1. Define deployment variables

Run these from the Mac, using the actual local repository path and SSH user.

```bash
export LOCAL_ROOT="$HOME/path/to/HelmetsanWeb"
export REMOTE_USER="YOUR_SSH_USER"
export REMOTE_HOST="31.70.136.154"
export REMOTE_ROOT="/var/www/helmetsan.com/public"
export SSH_TARGET="${REMOTE_USER}@${REMOTE_HOST}"

export REMOTE_DATA_ROOT="$REMOTE_ROOT/wp-content/uploads/helmetsan-data"
export REMOTE_PUBLIC_DATA="$REMOTE_ROOT/data"
export TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
```

Confirm the local source directories before transferring:

```bash
find "$LOCAL_ROOT/data" -maxdepth 2 -type f | head
find "$LOCAL_ROOT" -path '*helmetsan-data*' -type f | head
```

The source directories must be explicitly mapped to:

```text
Remote:
/var/www/helmetsan.com/public/wp-content/uploads/helmetsan-data/
/var/www/helmetsan.com/public/data/
```

Do not assume that the local `data` directory and local `wp-content/uploads/helmetsan-data` directory are interchangeable. Verify the actual local layout first.

---

# 2. Pre-deployment remote backup

Create a dated backup of the remote catalog and WordPress database before changing anything.

```bash
ssh "$SSH_TARGET" "
  set -e
  sudo mkdir -p /var/backups/helmetsan/$TIMESTAMP

  sudo tar -czf \
    /var/backups/helmetsan/$TIMESTAMP/helmetsan-data.tar.gz \
    -C '$REMOTE_ROOT/wp-content/uploads' helmetsan-data

  sudo tar -czf \
    /var/backups/helmetsan/$TIMESTAMP/public-data.tar.gz \
    -C '$REMOTE_ROOT' data
"
```

Back up WordPress using WP-CLI if available:

```bash
ssh "$SSH_TARGET" "
  cd '$REMOTE_ROOT'
  if command -v wp >/dev/null 2>&1; then
    sudo -u www-data wp db export \
      /var/backups/helmetsan/$TIMESTAMP/wordpress.sql \
      --add-drop-table
  else
    echo 'WP-CLI not found; perform a database backup through the configured MySQL/MariaDB backup process.'
    exit 1
  fi
"
```

Check disk space before copying the 82.92 MB catalog and related files:

```bash
ssh "$SSH_TARGET" "df -h '$REMOTE_ROOT' /var/backups/helmetsan"
```

---

# 3. Verify the local release before transfer

Confirm the local commit and tag:

```bash
cd "$LOCAL_ROOT"

git rev-parse HEAD
git describe --tags --exact-match HEAD 2>/dev/null || true
git show --stat --oneline 4ef399e
```

The expected commit is:

```text
4ef399e
catalog-verified-2026-09-15T20-06-35
```

Run the final local defect check:

```bash
grep -RIn --include='*.json' --include='*.html' --include='*.txt' \
  -F 'is delivers' "$LOCAL_ROOT/data" "$LOCAL_ROOT/wp-content/uploads/helmetsan-data" \
  || true
```

Expected result: no matches.

If the catalog database is being deployed, verify it locally:

```bash
sha256sum "$LOCAL_ROOT"/**/catalog.db 2>/dev/null || true
```

The expected SHA-256 is:

```text
8aab8ed6431cc6a2f2a48c948b314d9202d1ed56ba4157cf33346cc5c15f286a
```

---

# 4. High-speed rsync delta transfer

## 4.1 Create the remote directories

```bash
ssh "$SSH_TARGET" "
  sudo mkdir -p '$REMOTE_DATA_ROOT' '$REMOTE_PUBLIC_DATA'
  sudo chown -R $REMOTE_USER:$REMOTE_USER '$REMOTE_DATA_ROOT' '$REMOTE_PUBLIC_DATA' 2>/dev/null || true
"
```

If the web server requires `www-data`, restore ownership after transfer rather than forcing ownership prematurely.

## 4.2 Perform a dry run first

For the uploaded catalog:

```bash
rsync -azP \
  --itemize-changes \
  --checksum \
  --human-readable \
  "$LOCAL_ROOT/wp-content/uploads/helmetsan-data/" \
  "$SSH_TARGET:$REMOTE_DATA_ROOT/"
```

For the public data directory:

```bash
rsync -azP \
  --itemize-changes \
  --checksum \
  --human-readable \
  "$LOCAL_ROOT/data/" \
  "$SSH_TARGET:$REMOTE_PUBLIC_DATA/"
```

For a dry run, add `--dry-run` to both commands:

```bash
rsync -azP --dry-run --itemize-changes --checksum \
  "$LOCAL_ROOT/wp-content/uploads/helmetsan-data/" \
  "$SSH_TARGET:$REMOTE_DATA_ROOT/"
```

Review every deletion or replacement. Do **not** use `--delete` on the first deployment unless the local directory is confirmed to be the complete canonical directory.

## 4.3 Execute the actual delta transfer

Once the dry run is correct:

```bash
rsync -azP \
  --itemize-changes \
  --checksum \
  --partial \
  --human-readable \
  "$LOCAL_ROOT/wp-content/uploads/helmetsan-data/" \
  "$SSH_TARGET:$REMOTE_DATA_ROOT/"
```

```bash
rsync -azP \
  --itemize-changes \
  --checksum \
  --partial \
  --human-readable \
  "$LOCAL_ROOT/data/" \
  "$SSH_TARGET:$REMOTE_PUBLIC_DATA/"
```

For a complete mirror, and only after reviewing the dry-run output, use:

```bash
rsync -azP \
  --itemize-changes \
  --checksum \
  --delete-delay \
  --partial \
  "$LOCAL_ROOT/wp-content/uploads/helmetsan-data/" \
  "$SSH_TARGET:$REMOTE_DATA_ROOT/"
```

and:

```bash
rsync -azP \
  --itemize-changes \
  --checksum \
  --delete-delay \
  --partial \
  "$LOCAL_ROOT/data/" \
  "$SSH_TARGET:$REMOTE_PUBLIC_DATA/"
```

## 4.4 Restore web-server ownership and permissions

```bash
ssh "$SSH_TARGET" "
  sudo chown -R www-data:www-data '$REMOTE_DATA_ROOT' '$REMOTE_PUBLIC_DATA'
  sudo find '$REMOTE_DATA_ROOT' '$REMOTE_PUBLIC_DATA' -type d -exec chmod 755 {} \;
  sudo find '$REMOTE_DATA_ROOT' '$REMOTE_PUBLIC_DATA' -type f -exec chmod 644 {} \;
"
```

Use the actual PHP/Nginx service account if it is not `www-data`.

---

# 5. Verify the remote files before WordPress ingestion

Check for the known defect:

```bash
ssh "$SSH_TARGET" "
  set -o pipefail
  COUNT=\$(grep -RIl --include='*.json' --include='*.html' --include='*.txt' \
    -F 'is delivers' \
    '$REMOTE_DATA_ROOT' '$REMOTE_PUBLIC_DATA' 2>/dev/null | wc -l)
  echo \"Files containing 'is delivers': \$COUNT\"

  MATCHES=\$(grep -Roh --include='*.json' --include='*.html' --include='*.txt' \
    -F 'is delivers' \
    '$REMOTE_DATA_ROOT' '$REMOTE_PUBLIC_DATA' 2>/dev/null | wc -l)
  echo \"Total 'is delivers' matches: \$MATCHES\"

  test \"\$MATCHES\" -eq 0
"
```

Check the remote catalog database checksum, if deployed:

```bash
ssh "$SSH_TARGET" "
  find '$REMOTE_ROOT' -name catalog.db -type f -print -exec sha256sum {} \;
"
```

Expected checksum:

```text
8aab8ed6431cc6a2f2a48c948b314d9202d1ed56ba4157cf33346cc5c15f286a
```

Also confirm file counts and sizes:

```bash
ssh "$SSH_TARGET" "
  du -sh '$REMOTE_DATA_ROOT' '$REMOTE_PUBLIC_DATA'
  find '$REMOTE_DATA_ROOT' -type f | wc -l
  find '$REMOTE_PUBLIC_DATA' -type f | wc -l
"
```

---

# 6. WordPress synchronization

The WordPress ingestion must be **idempotent**. It must update existing records rather than creating duplicates on every run.

The importer should use a stable external identifier, for example:

```text
catalog_id
sku
source_slug
canonical_url
```

The importer must handle all three content classes:

```text
helmets
motorcycles
accessories
```

It should update, at minimum:

- Post title
- Slug, where safe
- Editorial body
- Excerpt
- Featured image or media reference
- Brand
- Model
- Category
- Product metadata
- Canonical catalog identifier
- SEO fields, if managed by WordPress
- Published/draft status

## 6.1 Discover the existing importer

On the server:

```bash
ssh "$SSH_TARGET" "
  cd '$REMOTE_ROOT'

  echo 'WP-CLI:'
  command -v wp || true

  echo 'Possible importer files:'
  find '$REMOTE_ROOT' -maxdepth 5 -type f \
    \\( -iname '*import*' -o -iname '*sync*' -o -iname '*catalog*' \\) \
    -print 2>/dev/null | head -200

  echo 'Possible cron jobs:'
  sudo crontab -l 2>/dev/null || true
  crontab -l 2>/dev/null || true
"
```

Also inspect registered post types and taxonomies:

```bash
ssh "$SSH_TARGET" "
  cd '$REMOTE_ROOT'
  wp post-type list --format=table
  wp taxonomy list --format=table
"
```

Do not guess the post type names. They may be `helmet`, `motorcycle`, `accessory`, `product`, or a custom shared type.

## 6.2 Run the official importer in dry-run mode

Use the project’s actual importer. The command should conceptually look like this:

```bash
ssh "$SSH_TARGET" "
  cd '$REMOTE_ROOT'

  sudo -u www-data wp helmetsan catalog:sync \
    --source='$REMOTE_DATA_ROOT' \
    --types=helmets,motorcycles,accessories \
    --update-existing \
    --match-by=catalog_id \
    --dry-run \
    --report='/tmp/helmetsan-sync-$TIMESTAMP.json'
"
```

If the project uses a PHP script instead:

```bash
ssh "$SSH_TARGET" "
  cd '$REMOTE_ROOT'
  sudo -u www-data php path/to/helmetsan-import.php \
    --source='$REMOTE_DATA_ROOT' \
    --types=helmets,motorcycles,accessories \
    --update-existing \
    --match-by=catalog_id \
    --dry-run
"
```

The dry-run report must show:

- Existing posts to update
- New posts to create
- Records skipped
- Records with missing identifiers
- Records with invalid taxonomies
- Records with missing media
- No unexpected mass deletion

The expected target totals are approximately:

```text
Helmets:      2,219
Motorcycles:  3,247
Accessories:     27
Total:        5,493
```

The current remote count of only **15 motorcycles** is a deployment blocker. Resolve that before production ingestion.

## 6.3 Execute the synchronization

After reviewing the dry-run report:

```bash
ssh "$