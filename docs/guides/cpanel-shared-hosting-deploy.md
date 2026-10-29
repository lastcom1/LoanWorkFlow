# cPanel shared hosting deploy (overview)

**Required layout for nigerianavymfb.com:** app outside the web root + public entry under `/transfer`.

**PHP:** **8.3+** required (`composer.lock` / Spatie). On CloudLinux use **`alt-php83`** with `pdo_mysql`, `mbstring`, `fileinfo`, `bcmath`, `intl`, `zip`.

**RewriteBase:** path deploy `public_html/transfer/` → `/transfer/`; subdomain at document root → `/` (see `deploy/subdomain-docroot/.htaccess`).

→ Full steps: **[cpanel-go-live.md](./cpanel-go-live.md)** / **[cpanel-public-html-transfer-deploy.md](./cpanel-public-html-transfer-deploy.md)**  
→ ZIP: `nnmfb-transfer-portal-package-b-public-html-transfer.zip`

```
~/nnmfb-portal/              ← Laravel application
~/public_html/transfer/      ← index.php + assets only
```

Portal URL: **https://nigerianavymfb.com/transfer**

## Production `.env`

Copy **`.env.production.example`** → `.env` on the server. Do not use local `.env.example` (dev defaults).

## Production seeding (no DEMO users)

```bash
cd ~/nnmfb-portal
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder --force
php artisan installer:lock --email=you@your-domain.com
```

Do **not** run `php artisan db:seed --force` with demo seeders on production.  
Do **not** run `DemoDatabaseSeeder` / `DefaultAdminSeeder` when `APP_ENV=production`.

## Installer

Off by default (`INSTALLER_ENABLED=false`). If you must use it, gate with `INSTALLER_SETUP_SECRET`, then lock.

## Main website menu

See `main-site-portal-link.md`.
