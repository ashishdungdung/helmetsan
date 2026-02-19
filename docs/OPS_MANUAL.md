# Operations Manual 🛠️

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
