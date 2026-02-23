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
- **Syncs**:
  - `helmetsan-theme` -> `.../wp-content/themes/`
  - `helmetsan-core` -> `.../wp-content/plugins/`

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
