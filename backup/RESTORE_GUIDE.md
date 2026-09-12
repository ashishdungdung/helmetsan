# Helmetsan Complete System & Server Restoration Guide 🚀

This document is a comprehensive, production-grade guide for performing a **100% full site and server infrastructure restoration** from this local backup onto any clean Linux server.

---

## 📂 Backup Directory Structure (`backup/`)

```
backup/
├── helmetsan_db_backup.sql.gz  (21.1 MB - Full compressed MySQL database export)
├── uploads/                    (184 MB - Full WordPress media uploads, WebP images, runtime datasets)
├── server_config/              (Production Nginx, Redis, Crontab & wp-config settings)
│   ├── nginx/
│   │   ├── nginx.conf
│   │   ├── sites-available/helmetsan.com.conf
│   │   └── snippets/
│   ├── redis.conf
│   ├── crontab_root.txt
│   └── wp-config-production.php
└── RESTORE_GUIDE.md            (This restoration playbook)
```

---

## 🛠️ Step-by-Step Restoration (For Humans and AI Agents)

### Phase 1: Server Provisioning & Dependencies
On the new Ubuntu target server (`<new_server_ip>`):

```bash
# 1. Update system & install required packages:
sudo apt update && sudo apt install -y nginx redis-server php8.3-fpm php8.3-mysql php8.3-curl php8.3-gd php8.3-mbstring php8.3-xml php8.3-zip php8.3-redis mysql-server git curl unzip tar rsync

# 2. Install WP-CLI globally:
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
sudo mv wp-cli.phar /usr/local/bin/wp

# 3. Create Web Directory & System Logs Path:
sudo mkdir -p /var/www/helmetsan.com/public /var/www/helmetsan.com/logs
sudo chown -R www-data:www-data /var/www/helmetsan.com
```

---

### Phase 2: System Configurations (Nginx, Redis & Cron)

#### A. Nginx Virtual Host Setup
Copy Nginx configuration from local backup:
```bash
# Upload Nginx config to server:
scp backup/server_config/nginx/sites-available/helmetsan.com.conf root@<new_server_ip>:/etc/nginx/sites-available/helmetsan.com.conf

# Enable site and test configuration:
ssh root@<new_server_ip> "ln -sf /etc/nginx/sites-available/helmetsan.com.conf /etc/nginx/sites-enabled/ && nginx -t && systemctl reload nginx"
```

#### B. Redis Configuration
```bash
# Upload Redis configuration:
scp backup/server_config/redis.conf root@<new_server_ip>:/etc/redis/redis.conf
ssh root@<new_server_ip> "systemctl restart redis-server"
```

#### C. System Cron Jobs
```bash
# Upload crontab:
scp backup/server_config/crontab_root.txt root@<new_server_ip>:/tmp/crontab_root.txt
ssh root@<new_server_ip> "crontab /tmp/crontab_root.txt && rm /tmp/crontab_root.txt"
```

---

### Phase 3: Restore Database & Uploads

#### A. Import Production Database
```bash
# Upload dump to server:
scp backup/helmetsan_db_backup.sql.gz root@<new_server_ip>:/tmp/

# Import using WP-CLI:
ssh root@<new_server_ip> "cd /var/www/helmetsan.com/public && zcat /tmp/helmetsan_db_backup.sql.gz | wp db import - --allow-root && rm /tmp/helmetsan_db_backup.sql.gz"
```

#### B. Sync Media & Runtime Assets
```bash
# Sync local backup/uploads to server wp-content/uploads/
rsync -avz backup/uploads/ root@<new_server_ip>:/var/www/helmetsan.com/public/wp-content/uploads/
```

---

### Phase 4: Deploy Codebase & Active Plugins

#### A. Deploy Plugin & Theme
From local repository root:
```bash
# Deploy latest source build:
bash deploy.sh
```

#### B. Activate Plugins & Enable Redis Object Cache
On the server:
```bash
ssh root@<new_server_ip> "cd /var/www/helmetsan.com/public && wp plugin activate woocommerce polylang wordpress-seo redis-cache helmetsan-core --allow-root && wp theme activate helmetsan-theme --allow-root && wp redis enable --allow-root"
```

---

### Phase 5: Cache Flush & Health Diagnostics

```bash
ssh root@<new_server_ip> "cd /var/www/helmetsan.com/public && wp cache flush --allow-root && wp transient delete --all --allow-root && nginx -s reload && wp helmetsan health --allow-root"
```

---

## 🤖 1-Click Automated AI Agent Restoration Prompt

If you ask the AI agent to restore the site onto a new server in a future session, provide this command:

```text
"Agent, please restore the entire Helmetsan site, database, Nginx/Redis system configs, and media uploads onto <new_server_ip> using the backup in backup/ and RESTORE_GUIDE.md."
```

The agent will execute the automated 5-phase migration script end-to-end.
