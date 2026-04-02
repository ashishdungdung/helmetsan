# Exposing LM Studio to the Internet (Cloudflare Tunnel)

To allow your production server (or any external site) to securely reach your local LM Studio instance on your Mac, use a **Cloudflare Tunnel**. This avoids port-forwarding and handles SSL automatically.

## Prerequisites

1.  **LM Studio** running on your Mac with the Local Server enabled on port `1234`.
2.  **Cloudflare Account** and a domain already pointed to Cloudflare (e.g., `helmetsan.com`).
3.  **`cloudflared`** installed on your Mac (`brew install cloudflared`).

---

## Setup Steps

### 1. Authenticate Cloudflare CLI
On your Mac, run:
```bash
cloudflared tunnel login
```
This opens a browser. Authenticate and select your domain.

### 2. Create the Tunnel
Replace `lm-studio-tunnel` with your preferred name:
```bash
cloudflared tunnel create lm-studio-tunnel
```
This creates a credentials file (`~/.cloudflared/<TUNNEL_ID>.json`). **Note the Tunnel ID.**

### 3. Configure the Tunnel
Create or edit `~/.cloudflared/config.yml` on your Mac:
```yaml
tunnel: <TUNNEL_ID>
credentials-file: /Users/<YOUR_USER>/.cloudflared/<TUNNEL_ID>.json

ingress:
  - hostname: ai.helmetsan.com  # Replace with your subdomain
    service: http://localhost:1234
  - service: http_status:404
```

### 4. Route the Subdomain
Link your tunnel to your Cloudflare DNS:
```bash
cloudflared tunnel route dns lm-studio-tunnel ai.helmetsan.com
```

### 5. Run the Tunnel
Start the tunnel locally:
```bash
cloudflared tunnel run lm-studio-tunnel
```

---

## Project Configuration

Once the tunnel is running and `ai.helmetsan.com` is active, update your Helmetsan project to use it.

### Via `wp-config.php` (Recommended for Prod)
Add this to your production `wp-config.php`:
```php
define('HELMETSAN_LMSTUDIO_BASE_URL', 'https://ai.helmetsan.com/v1');
```

### Via WP Admin
1.  Go to **Helmetsan → AI**.
2.  Under **LM Studio (local)**, set **Base URL** to `https://ai.helmetsan.com/v1`.
3.  Save and Test.

---

## Security (Optional)

To prevent unauthorized access to your local LLM, add **Cloudflare Access** (Zero Trust) to the `ai.helmetsan.com` subdomain. If you do this:
1.  Create a Service Token in Cloudflare Zero Trust.
2.  The plugin's `LMStudioProvider` can be updated to send the `CF-Access-Client-Id` and `CF-Access-Client-Secret` headers if needed (ask the agent to add this support).
