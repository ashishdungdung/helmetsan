# Operations Manual 🛠️

## SSH key & multiplexing

Use key-based auth so deploy and ad-hoc `ssh`/`rsync` don’t need a password. Reuse one connection (multiplexing) so repeated SSH/rsync is fast.

**1. Key (not stored in repo)**  
Use your local key (e.g. `~/.ssh/id_rsa`) and add the public key to the server:

```bash
# On server (one-time)
mkdir -p ~/.ssh
echo "PASTE_CONTENTS_OF_~/.ssh/id_rsa.pub" >> ~/.ssh/authorized_keys
chmod 700 ~/.ssh
chmod 600 ~/.ssh/authorized_keys
```

**2. Store this in `~/.ssh/config` (local machine)**  
Then `ssh root@helmetsan.com` and `scripts/deploy.sh` (rsync over SSH) will reuse a single connection:

```
Host helmetsan.com
  User root
  HostName helmetsan.com
  ControlMaster auto
  ControlPath ~/.ssh/control-%r@%h:%p
  ControlPersist 10m
```

- **ControlMaster auto**: first connection opens a master; later ones reuse it.  
- **ControlPath**: socket path; `%r`=user, `%h`=host, `%p`=port.  
- **ControlPersist 10m**: keep master alive 10 minutes after last session.

Ensure `~/.ssh` is mode `700` so only you can use the control socket.

---

## Environment Paths

Remote paths are defined once in **`scripts/config`** (source of truth). Scripts source it and use `wp --path=$REMOTE_WP_PATH` so the WordPress install is always found regardless of SSH CWD.

| Environment        | Path                                               | Notes                |
| :----------------- | :------------------------------------------------- | :------------------- |
| **Local**          | `/Users/anumac/Documents/Helmetsan`                | Project Root         |
| **Remote**         | `/var/www/helmetsan.com/public`                    | Web Root (WordPress) |
| **Remote Plugins** | `/var/www/helmetsan.com/public/wp-content/plugins` |                      |
| **Remote Themes**  | `/var/www/helmetsan.com/public/wp-content/themes`  |                      |

## Deployment (`scripts/deploy.sh`)

Deploys code from Local -> Remote.

- **Command**: `./scripts/deploy.sh`
- **Syncs** (as proper subdirs, does not overwrite other themes/plugins):
  - `helmetsan-theme` -> `.../wp-content/themes/helmetsan-theme/`
  - `helmetsan-core` -> `.../wp-content/plugins/helmetsan-core/`

### Password-based deploy (optional)

To use expect scripts with a password instead of SSH key:

1. Create **`scripts/.env.deploy`** (file is gitignored; do not commit it):
   ```bash
   DEPLOY_PASSWORD=your_ssh_or_sudo_password
   ```
2. Ensure **`scripts/deploy-rsync.expect`** and **`scripts/deploy-rsync-file.expect`** exist and are executable (templates are not in repo; create from your own expect templates if you use password auth).
3. Run `./scripts/deploy.sh`; it will source `.env.deploy` and use expect when `DEPLOY_PASSWORD` is set.

### Recovery after a bad deploy (no backup, VPS)

Clean slate: only **GeneratePress + Helmetsan child theme** and **Helmetsan Core + WooCommerce + Yoast SEO**. All other themes and plugins are removed.

1. **From your machine:** Deploy theme and plugin so they exist on the server:
   ```bash
   ./scripts/deploy.sh
   ```
2. **On the VPS:** Copy and run the recovery script (it removes every other theme/plugin, then configures theme and plugins together):
   ```bash
   scp scripts/vps-recover-themes-plugins.sh root@helmetsan.com:/root/
   ssh root@helmetsan.com 'bash /root/vps-recover-themes-plugins.sh /var/www/helmetsan.com/public'
   ```
   Or SSH in and run:
   ```bash
   bash /path/to/vps-recover-themes-plugins.sh /var/www/helmetsan.com/public
   ```
   The script: activates GeneratePress, deletes all other themes, activates `helmetsan-theme`; deactivates all plugins, deletes all except `helmetsan-core`, installs `woocommerce` and `wordpress-seo`, then activates theme and all three plugins together. Database (posts, settings) is unchanged.

### Duplicate “Helmetsan Core” or “Could not fully remove” on Plugins page

If the Plugins page shows two Helmetsan Core entries or deletion fails, run on the VPS:

```bash
# Copy and run the fix script (default path)
scp scripts/vps-fix-duplicate-helmetsan-core.sh root@helmetsan.com:/root/
ssh root@helmetsan.com 'bash /root/vps-fix-duplicate-helmetsan-core.sh'
```

The script removes any stray `helmetsan-core.php` at `wp-content/plugins/` (leftover from a bad deploy), deactivates the plugin, dedupes `active_plugins` in the database, then reactivates once. After that, refresh the Plugins page.

### “Could not create directory” when installing themes or plugins

The web server user (e.g. `www-data`, `nginx`) must be able to create directories under `wp-content/themes` and `wp-content/plugins`. On the VPS, fix ownership and permissions (replace `WP_PATH` and `WEB_USER` with your values):

```bash
# Typical VPS: WordPress under /var/www/helmetsan.com/public, web user www-data
WP_PATH="/var/www/helmetsan.com/public"
WEB_USER="www-data"

# Option A: Let the web server own content (recommended)
sudo chown -R "$WEB_USER:$WEB_USER" "$WP_PATH/wp-content"
sudo find "$WP_PATH/wp-content" -type d -exec chmod 755 {} \;
sudo find "$WP_PATH/wp-content" -type f -exec chmod 644 {} \;
```

To find the web user if unsure:

```bash
# Nginx
ps aux | grep nginx | grep -v grep | head -1

# Apache
ps aux | grep apache | grep -v grep | head -1
```

Then install GeneratePress via WP-CLI (runs as root, so it doesn’t depend on web permissions):

```bash
sudo -u "$WEB_USER" wp theme install generatepress --path="$WP_PATH"
# or as root if wp is in PATH:
wp theme install generatepress --path="$WP_PATH"
```

After that, the Add Themes UI should work for future installs.

## Release / tagging

When cutting a new version (e.g. 0.3.0):

1. **Version and changelog** (already done when preparing a release):
   - Set `Version:` and `HELMETSAN_CORE_VERSION` in `helmetsan-core/helmetsan-core.php`.
   - In `CHANGELOG.md`, move Unreleased bullets into a new `## X.Y.Z - YYYY-MM-DD` section; leave Unreleased with a placeholder.
2. **Smoke test** (optional but recommended): On remote or local WP, run `wp helmetsan` (list commands), `wp helmetsan health`, and open Helmetsan → Health in admin. Confirm no fatal errors.
3. **Tag and push:**
   ```bash
   git add CHANGELOG.md helmetsan-core/helmetsan-core.php
   git commit -m "Release X.Y.Z"
   git tag -a vX.Y.Z -m "Release X.Y.Z"
   git push origin main && git push origin vX.Y.Z
   ```
4. **Deploy:** Run `./scripts/deploy.sh` to ship the new version to the server (or use your normal deploy process).

---

## Data Sync (`scripts/pull-data.sh`)

Syncs generated data (JSON) from Remote -> Local.

- **Command**: `./scripts/pull-data.sh`
- **Syncs**:
  - `.../helmetsan-core/data/` -> `helmetsan-core/data/`

## Brand Seeding (Remote Execution)

To seed the database with brands and generate the JSON source of truth:

1.  **Upload Script**: (done via deploy, or manually)
2.  **Execute** (use `--path` so CWD does not matter):
    ```bash
    ssh root@helmetsan.com "wp --path=/var/www/helmetsan.com/public eval-file seed_brands.php --allow-root"
    ```
3.  **Pull Data**: Run `./scripts/pull-data.sh` locally.

---

## Common errors & quick fixes

Use this first when something fails — avoids repeated debugging and AI queries.

| Symptom | Cause | Fix |
| --------| ----- | --- |
| **“Error: This does not appear to be a WordPress installation.”** or **wp** can’t find the install | Remote shell CWD is wrong (e.g. `~` or cron). | All scripts now use `wp --path=$REMOTE_WP_PATH`. For one-off commands always use: `wp --path=/var/www/helmetsan.com/public ...` (or source `scripts/config` and use `$REMOTE_WP_PATH`). |
| **Script fails with “REMOTE_WP_PATH: unbound variable”** (or config not found) | Run from wrong directory or config missing. | Run deploy/reseed from **repo root**; ensure `scripts/config` exists. Scripts source it via `SCRIPT_DIR`. |
| **Permission denied (publickey)** or password prompt every time | SSH key not on server or multiplexing not used. | Add your `~/.ssh/id_rsa.pub` to server `~/.ssh/authorized_keys`. Add the `Host helmetsan.com` block to `~/.ssh/config` (see above) for multiplexing. |
| **rsync / deploy hangs or very slow** | New SSH connection every time (no multiplexing). | Use `~/.ssh/config` with `ControlMaster auto` and `ControlPersist 10m` for `helmetsan.com`. |
| **“Seed file not found”** on server | Ingest runs before deploy or path wrong. | Run full pipeline: `./scripts/reseed.sh` (deploy copies seed to server). Or `--skip-deploy` only if seed is already at `wp-content/plugins/helmetsan-core/seed-data/helmets_seed.json`. |
| **PHP script “ABSPATH not defined”** | Run with `php script.php` instead of via WordPress. | Run via WP-CLI from site root: `wp --path=/var/www/helmetsan.com/public eval-file path/to/script.php --allow-root`. |

Changing the server path in one place: edit **`scripts/config`** and set `REMOTE_WP_PATH`; all scripts that source it will use the new path.

---

## Health & Alerts

**Health (admin):** Helmetsan → **Health** shows repository, ingestion logs, sync, GitHub, AI config, validation/enrichment flags, and scheduler. Use it to confirm data root, failed ingests, and that at least one AI provider is configured before running fill-missing or generate-seed.

**Health (CLI):** From the server (or SSH):

```bash
wp --path=/var/www/helmetsan.com/public helmetsan health --format=json --allow-root
```

**Alerts:** Configure email and/or Slack in Helmetsan → Settings → Alert System. Test without sending to production:

```bash
wp --path=/var/www/helmetsan.com/public helmetsan alerts test --title="Ping" --message="Test" --allow-root
```

Alerts fire on sync errors, ingestion failures, and (if enabled) health warnings. Slack is retried once on 5xx/429.
