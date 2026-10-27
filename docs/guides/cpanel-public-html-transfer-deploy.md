# Deploy to `public_html/transfer` (app outside web root)

Portal URL: **https://nigerianavymfb.com/transfer**

ZIP: `docs/packages/nnmfb-transfer-portal-package-b-public-html-transfer.zip`

## Required layout

```
/home/USER/
  nnmfb-portal/                 ← FULL Laravel app (NOT under public_html)
    app/ bootstrap/ config/ database/ resources/ routes/
    storage/ vendor/ artisan .env .env.example
  public_html/
    (existing main website…)
    transfer/                   ← PUBLIC ENTRY ONLY
      index.php                 ← points at ~/nnmfb-portal
      .htaccess                 ← RewriteBase /transfer/
      css/  images/  favicon.png
```

Do **not** place `vendor/`, `app/`, or `.env` inside `public_html/transfer/`.

## Steps

### 1. Database

Create MySQL DB + user in cPanel.

### 2. Extract Package B

Upload the ZIP and extract so you get both folders:

- `nnmfb-portal/` → move to `/home/USER/nnmfb-portal/`
- `public_html/transfer/` → `/home/USER/public_html/transfer/`

Confirm `public_html/transfer/index.php` resolves `dirname(__DIR__, 2).'/nnmfb-portal'` (i.e. sibling of `public_html`). If your account layout differs, edit `$appRoot` in that file or set env `NNMFB_APP_ROOT`.

### 3. Configure `.env` in `~/nnmfb-portal`

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://nigerianavymfb.com/transfer
INSTALLER_ENABLED=false
INSTALLER_SETUP_SECRET=
SEED_DEMO_DATA=false

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

MAIL_MAILER=smtp
MAIL_HOST=smtp.zoho.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@nigerianavymfb.com
MAIL_FROM_NAME="NNMFB Transfer Portal"
SESSION_SECURE_COOKIE=true
```

### 4. Permissions

`storage/` and `bootstrap/cache/` writable under `~/nnmfb-portal`.

### 5. SSH deploy (recommended)

```bash
cd ~/nnmfb-portal
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder --force
php artisan installer:lock --email=you@your-domain.com
```

`ProductionSeeder` loads **roles + branches only** — **no DEMO users**.

Create the real administrator with tinker (example):

```bash
php artisan tinker
```

```php
$u = \App\Models\User::create([
  'name' => 'Portal Admin',
  'email' => 'admin@your-domain.com',
  'password' => bcrypt('choose-a-strong-password'),
  'is_active' => true,
  'is_demo' => false,
]);
$u->assignRole('admin');
```

### 6. Optional web installer (gated)

Only if SSH is unavailable:

1. Set `INSTALLER_ENABLED=true` and a long random `INSTALLER_SETUP_SECRET` in `.env`
2. Visit `https://nigerianavymfb.com/transfer/install?setup_secret=YOUR_SECRET`
3. Complete setup (creates a **new** admin only; will not modify existing users)
4. Confirm lock file exists; set `INSTALLER_ENABLED=false`
5. Or run `php artisan installer:lock`

Installer is **off by default** and refuses to run when users already exist (and auto-locks).

### 7. Health check

1. https://nigerianavymfb.com/transfer/login loads  
2. Sign in with your real admin  
3. https://nigerianavymfb.com/transfer/install returns 403  
4. Confirm `~/nnmfb-portal/.env` is not web-reachable  

### 8. Queue cron

```bash
cd /home/USER/nnmfb-portal && php artisan queue:work --stop-when-empty >/dev/null 2>&1
```

### 9. Main site link

See `main-site-portal-link.md` → `https://nigerianavymfb.com/transfer`

## DEMO data

Never seed DEMO accounts on production. Local-only:

```bash
# local / non-production only
php artisan db:seed --class=DemoDatabaseSeeder
```

DEMO passwords are documented only in the **Windows local install** guide — change them before any shared use.
