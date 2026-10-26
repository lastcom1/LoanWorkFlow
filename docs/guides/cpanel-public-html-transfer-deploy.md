# Deploy to `public_html/transfer` (recommended for nigerianavymfb.com)

This guide installs the portal at:

**https://nigerianavymfb.com/transfer**

Use ZIP: `docs/packages/nnmfb-transfer-portal-package-b-public-html-transfer.zip`

You do **not** need to change the domain document root. Extract into `public_html/transfer`.

## Target layout after extract

```
public_html/
  (existing main website files…)
  transfer/                 ← ZIP folder `transfer/` extracts here
    index.php               ← front controller (web entry)
    .htaccess               ← RewriteBase /transfer/ + deny sensitive paths
    css/  images/  favicon.png
    app/  bootstrap/  config/  database/  resources/  routes/
    storage/  vendor/  artisan  .env.example
    docs/guides/…
```

Sensitive paths (`app`, `vendor`, `.env`, etc.) are blocked by `.htaccess` (`403`). Only `index.php`, static assets, and routed URLs should be reachable.

## Step-by-step

### 1. Create MySQL database

In cPanel → MySQL Databases: create DB + user, grant ALL, note host/name/user/password.

### 2. Upload and extract

1. Upload `nnmfb-transfer-portal-package-b-public-html-transfer.zip` via File Manager or FTP.
2. Extract so the inner folder is exactly `public_html/transfer/` (you should see `public_html/transfer/index.php`).
3. If File Manager extracted an extra nesting level (`public_html/transfer/transfer/`), move contents up one level.

### 3. Create `.env`

In `public_html/transfer/`, copy `.env.example` → `.env` and set:

```env
APP_NAME="NNMFB Transfer Portal"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://nigerianavymfb.com/transfer
APP_KEY=

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=YOUR_DB_NAME
DB_USERNAME=YOUR_DB_USER
DB_PASSWORD=YOUR_DB_PASSWORD

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_HOST=smtp.zoho.com
MAIL_PORT=587
MAIL_USERNAME=your-zoho-mailbox@domain
MAIL_PASSWORD=your-zoho-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@nigerianavymfb.com
MAIL_FROM_NAME="NNMFB Transfer Portal"
```

Generate `APP_KEY` either:

- SSH: `cd ~/public_html/transfer && php artisan key:generate --force`
- Or paste a key from a trusted local `php artisan key:generate --show`

### 4. Permissions

Make writable (typically 0755/0775 depending on host):

- `storage/` (and subfolders)
- `bootstrap/cache/`

### 5. Migrations / seed / installer

**Option A — Web installer (no SSH):** visit  
https://nigerianavymfb.com/transfer/install  

Complete requirements → DB/SMTP (if not already in `.env`) → migrate → create admin → finish. Confirm `storage/app/installed.lock` exists afterward.

**Option B — SSH:**

```bash
cd ~/public_html/transfer
php artisan migrate --force
php artisan db:seed --force
# optional: remove DEMO users or change passwords immediately
```

Then create `storage/app/installed.lock` (empty JSON is fine) so `/install` stays locked.

### 6. Health check

1. Open https://nigerianavymfb.com/transfer/login — branded NNMFB login must load.
2. Sign in with your admin (or DEMO only on non-production).
3. Submit a test ticket; confirm dashboard.
4. Request **Forgot password?** — confirm Zoho delivery (or check mail logs).
5. Confirm https://nigerianavymfb.com/transfer/app returns **403/denied** (not source code).

### 7. Disable installer

After success, installer writes `storage/app/installed.lock`. Keep it. Do not leave `APP_DEBUG=true`.

### 8. Queue (email delivery)

cPanel Cron every minute:

```bash
cd /home/USER/public_html/transfer && php artisan queue:work --stop-when-empty >/dev/null 2>&1
```

### 9. Link from the main website

Add a menu item on https://nigerianavymfb.com pointing to `/transfer` — see `main-site-portal-link.md`.

## Password reset (how-to)

1. Login page → **Forgot password?**
2. Enter work email (DEMO accounts work: e.g. `submitter@nnmfb.demo`).
3. Open the branded reset email (Zoho SMTP in production; `MAIL_MAILER=log` writes to `storage/logs` locally).
4. Choose a new password (token expires in 60 minutes; requests throttled).

## Troubleshooting

| Symptom | Check |
|---------|--------|
| 404 on /transfer/login | `.htaccess` present? `RewriteBase /transfer/`? AllowOverride On? |
| CSS/logo missing | `APP_URL` must include `/transfer`; hard-refresh |
| 500 after extract | `storage`/`bootstrap/cache` permissions; `APP_KEY` set |
| Installer loops | DB credentials; PHP 8.2+; `vendor/` present |
| Emails missing | Zoho SMTP creds; cron queue worker |

## Security reminders

- Change or delete all DEMO users before production use.
- Never commit real Zoho passwords.
- This deploy keeps Laravel files under `transfer/` but blocks them via `.htaccess` — prefer also moving the app above `public_html` if your host allows (see alternate guide).
