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
2.  **Execute**:
    ```bash
    ssh root@helmetsan.com
    cd /var/www/helmetsan.com/public
    wp eval-file seed_brands.php --allow-root
    ```
3.  **Pull Data**: Run `./scripts/pull-data.sh` locally.
