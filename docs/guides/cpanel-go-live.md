# NNMFB Transfer Portal — cPanel go-live guide

**Audience:** ops staff deploying via cPanel File Manager (SSH optional).  
**Target URL:** `https://<your-domain>/transfer` (example: `https://nigerianavymfb.com/transfer`)  
**Package:** `docs/packages/nnmfb-transfer-portal-package-b-public-html-transfer.zip`  
**Repo head this package tracks:** branch `cursor/nnmfb-transfer-reversal-portal-0e42`

---

## What you must supply before starting

| Item | Example / notes |
|------|-----------------|
| Domain (HTTPS) | `nigerianavymfb.com` |
| Portal path | `/transfer` → full URL `https://nigerianavymfb.com/transfer` |
| cPanel account home | e.g. `/home/nnmfbuser` |
| MySQL database name | e.g. `nnmfbuser_portal` |
| MySQL username + password | dedicated app user (not root) |
| Zoho mailbox | SMTP user + **app password** |
| From address | e.g. `noreply@nigerianavymfb.com` (must be allowed by Zoho) |
| First admin name/email/password | **real** credentials — not DEMO |
| One-time installer secret | long random string (only if using web installer) |

---

## Final server layout

```
/home/USER/
  nnmfb-portal/                    ← FULL app (outside public_html)
    app/ bootstrap/ config/ database/ resources/ routes/
    storage/ vendor/ artisan
    .env                           ← created from .env.production.example
    .env.production.example
    docs/guides/…
  public_html/
    (existing main website files)
    transfer/                      ← PUBLIC ENTRY ONLY
      index.php
      .htaccess
      css/  images/  favicon.png  robots.txt
```

Never put `vendor/`, `app/`, `storage/`, or `.env` inside `public_html/transfer/`.

---

## 1. PHP version and extensions (cPanel)

**Required: PHP 8.3+.** Locked dependencies (`composer.lock`, including `spatie/laravel-permission`) need PHP `^8.3`. PHP 8.2 will fail `composer install` / platform checks.

1. cPanel → **Select PHP Version** / **MultiPHP Manager** (or **CloudLinux Selector** / **Select PHP Version** on CloudLinux hosts).
2. Choose **PHP 8.3** (or newer 8.x) for the domain/account.
   - CloudLinux: select **`alt-php83`** (not `alt-php82`).
3. Enable extensions (names vary slightly by host; on CloudLinux enable these under `alt-php83`):

| Required | Typical cPanel / CloudLinux name |
|----------|----------------------------------|
| PDO MySQL | `pdo_mysql` / `mysqli` |
| Mbstring | `mbstring` |
| Fileinfo | `fileinfo` |
| BCMath | `bcmath` |
| Intl | `intl` |
| Zip | `zip` |
| OpenSSL | `openssl` |
| Tokenizer | `tokenizer` |
| XML | `xml` |
| Ctype | `ctype` |
| JSON | `json` (usually built-in) |
| Curl | `curl` |
| GD or Imagick | `gd` (optional; logo already PNG) |

4. Save. Confirm with cPanel → **PHP Info** (or `php -v` / `php -m` in Terminal) that the site is on **8.3.x** with the extensions above.

---

## 2. Upload and extract Package B

1. Download `nnmfb-transfer-portal-package-b-public-html-transfer.zip`.
2. cPanel → **File Manager**.
3. Upload the ZIP to `/home/USER/` (home), not into `public_html` alone.
4. Extract. You should see:

```
nnmfb-portal/
public_html-transfer/     ← rename/move contents into public_html/transfer/
README-DEPLOY.txt
```

5. Ensure app folder is exactly:

```
/home/USER/nnmfb-portal/
```

6. Create `public_html/transfer/` if missing. Move **all contents** of `public_html-transfer/` into `public_html/transfer/`:

```
/home/USER/public_html/transfer/index.php
/home/USER/public_html/transfer/.htaccess
/home/USER/public_html/transfer/css/
/home/USER/public_html/transfer/images/
```

7. Delete the empty `public_html-transfer/` folder and the ZIP from the server when done.

### `.htaccess` RewriteBase (path vs subdomain)

| Deploy style | Public files live at | `RewriteBase` |
|--------------|----------------------|---------------|
| Path under main site (default Package B) | `public_html/transfer/` | `/transfer/` |
| Subdomain as document root | subdomain `public_html/` (site root) | `/` |

Package B ships `RewriteBase /transfer/`. If you put the portal at a **subdomain document root** (e.g. `https://transfer.example.com/`), replace `.htaccess` with `deploy/subdomain-docroot/.htaccess` from the repo (or change the line to `RewriteBase /`). Leaving `/transfer/` on a subdomain root breaks routing.

Also set `APP_URL` to match: path deploy → `https://<domain>/transfer`; subdomain root → `https://transfer.<domain>` (no `/transfer` suffix).

---

## 3. Edit `index.php` path (if needed)

Open `public_html/transfer/index.php`.

Default resolution:

```php
$appRoot = getenv('NNMFB_APP_ROOT') ?: dirname(__DIR__, 2).'/nnmfb-portal';
```

That means: from `…/public_html/transfer`, go up two levels to `/home/USER`, then `nnmfb-portal`.

**If your app folder name or location differs**, set an absolute path, for example:

```php
$appRoot = '/home/YOUR_CPANEL_USER/nnmfb-portal';
```

Save. Do not leave a wrong path — blank/error pages usually mean `$appRoot` is wrong or `vendor/` is missing.

---

## 4. MySQL database, user, privileges

1. cPanel → **MySQL® Databases**.
2. Create database: e.g. `USER_portal`.
3. Create user with a strong password.
4. **Add User to Database** → privileges: **ALL PRIVILEGES** for install/migrate.
5. Note host (usually `localhost`), full DB name, username, password.

### Optional: lock down `audit_logs` after install

After migrations succeed, as a privileged MySQL user (phpMyAdmin SQL):

```sql
-- Replace DB/user names with yours
REVOKE UPDATE, DELETE ON USER_portal.audit_logs FROM 'USER_portaluser'@'localhost';
GRANT SELECT, INSERT ON USER_portal.audit_logs TO 'USER_portaluser'@'localhost';
FLUSH PRIVILEGES;
```

The app already refuses update/delete on audit logs in PHP; DB revoke is defense-in-depth.

---

## 5. Create `.env` from production template

In File Manager → `nnmfb-portal/`:

1. Copy `.env.production.example` → `.env` (File Manager: Copy, then Rename).
2. **Do not** copy `.env.example` (local/dev defaults).
3. Edit `.env` (File Manager → Edit).

### Every variable explained

| Variable | Production value / guidance |
|----------|-----------------------------|
| `APP_NAME` | `"NNMFB Transfer Portal"` — shown in mail from-name fallback |
| `APP_ENV` | `production` — required |
| `APP_KEY` | `base64:…` — **required**; see §5.1 |
| `APP_DEBUG` | `false` — never `true` on the public internet |
| `APP_URL` | `https://<domain>/transfer` e.g. `https://nigerianavymfb.com/transfer` — no trailing slash issues; include `/transfer` |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `en` |
| `LOG_CHANNEL` | `stack` |
| `LOG_LEVEL` | `error` (or `warning`) |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | `localhost` (or host shown in cPanel) |
| `DB_PORT` | `3306` |
| `DB_DATABASE` | full cPanel DB name |
| `DB_USERNAME` | full cPanel DB user |
| `DB_PASSWORD` | DB password (quote if special chars: `"p@ss"` ) |
| `SESSION_DRIVER` | `database` |
| `SESSION_LIFETIME` | `120` (minutes) |
| `SESSION_ENCRYPT` | `true` |
| `SESSION_SECURE_COOKIE` | `true` (HTTPS only) |
| `SESSION_SAME_SITE` | `lax` |
| `BROADCAST_CONNECTION` | `log` |
| `FILESYSTEM_DISK` | `local` |
| `QUEUE_CONNECTION` | `database` |
| `CACHE_STORE` | `database` |
| `MAIL_MAILER` | `smtp` |
| `MAIL_HOST` | `smtp.zoho.com` (or Zoho region host) |
| `MAIL_PORT` | `587` |
| `MAIL_USERNAME` | Zoho mailbox address |
| `MAIL_PASSWORD` | Zoho **app password** |
| `MAIL_ENCRYPTION` | `tls` |
| `MAIL_FROM_ADDRESS` | allowed sender, e.g. `noreply@nigerianavymfb.com` |
| `MAIL_FROM_NAME` | `"NNMFB Transfer Portal"` |
| `INSTALLER_ENABLED` | `false` after go-live; `true` only during one-time web install |
| `INSTALLER_SETUP_SECRET` | empty after go-live; long random during web install |
| `SEED_DEMO_DATA` | `false` — never seed DEMO users in production |

### 5.1 Generate `APP_KEY` without SSH

Pick one:

**A. cPanel Terminal / PHP** (if available):

```bash
cd ~/nnmfb-portal
php -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"
```

Paste the output into `.env` as `APP_KEY=base64:...`

**B. On any trusted local machine with PHP:**

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"
```

**C. OpenSSL (local):**

```bash
openssl rand -base64 32
```

Then set `APP_KEY=base64:<that-output>` (Laravel expects the `base64:` prefix).

Never reuse a key from another environment. Never commit `.env`.

---

## 6. Permissions

In File Manager, for `nnmfb-portal/storage` and `nnmfb-portal/bootstrap/cache`:

- Folders: **0755** (or **0775** if your host requires group write)
- Ensure the web user can write logs, cache, sessions, and uploads under `storage/`

Create empty folders if missing:

```
nnmfb-portal/storage/framework/cache
nnmfb-portal/storage/framework/sessions
nnmfb-portal/storage/framework/views
nnmfb-portal/storage/logs
nnmfb-portal/storage/app/public
nnmfb-portal/bootstrap/cache
```

`php artisan storage:link` is **not required** for this portal’s attachment downloads (served via authenticated controller). Skip if you have no SSH.

---

## 7. Migrations + ProductionSeeder + lock

### Option A — SSH / Terminal (recommended)

```bash
cd ~/nnmfb-portal
php artisan key:generate --force   # skip if APP_KEY already set
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder --force
php artisan installer:lock --email=you@your-domain.com
```

`ProductionSeeder` creates **roles + branches only** — **no DEMO users**.

Create the first admin:

```bash
php artisan tinker
```

```php
$u = \App\Models\User::create([
  'name' => 'Portal Admin',
  'email' => 'admin@your-domain.com',
  'password' => bcrypt('Choose-A-Strong-Password-Here'),
  'is_active' => true,
  'is_demo' => false,
]);
$u->assignRole('admin');
exit
```

### Option B — No SSH (web installer)

1. In `.env` temporarily set:

```env
INSTALLER_ENABLED=true
INSTALLER_SETUP_SECRET=long-random-secret-you-choose
```

2. Delete `nnmfb-portal/storage/app/installed.lock` if present (package ships a placeholder lock).
3. Visit:

```
https://<domain>/transfer/install?setup_secret=long-random-secret-you-choose
```

4. Complete requirements → env (if needed; skip rewriting a keyed `.env`) → migrate/seed (ProductionSeeder) → **create a new admin** (will not modify existing users).
5. Confirm installer finishes and lock file exists.
6. Set in `.env`:

```env
INSTALLER_ENABLED=false
INSTALLER_SETUP_SECRET=
```

7. Confirm `https://<domain>/transfer/install` returns **403**.

---

## 8. Cron (scheduler / queue)

cPanel → **Cron Jobs** → add every minute:

```bash
cd /home/USER/nnmfb-portal && php artisan schedule:run >> /dev/null 2>&1
```

And/or queue worker (emails):

```bash
cd /home/USER/nnmfb-portal && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
```

Replace `USER` with your cPanel username. Without a queue cron, mail stays in the `jobs` table.

---

## 9. SSL

1. cPanel → **SSL/TLS Status** or AutoSSL — force HTTPS for the domain.
2. Keep `APP_URL` as `https://…` and `SESSION_SECURE_COOKIE=true`.
3. Force HTTPS redirect at domain level if available.

---

## 10. Verify sensitive paths are not web-accessible

From a browser (logged out):

| URL | Expected |
|-----|----------|
| `https://<domain>/transfer/login` | Branded login |
| `https://<domain>/transfer/../nnmfb-portal/.env` | Not found / forbidden |
| `https://<domain>/nnmfb-portal/.env` | Not found / forbidden |
| `https://<domain>/transfer/install` | 403 after lock |
| Direct request for any path under app `vendor/` via web | Must fail |

If `.env` is ever downloadable, rotate `APP_KEY`, DB password, and Zoho app password immediately.

---

## 11. Smoke-test checklist

- [ ] Login page shows NNMFB logo / navy–gold branding  
- [ ] First admin can sign in  
- [ ] Create a Fund Transfer ticket (submitter or admin)  
- [ ] Duplicate warning appears for same account+amount (acknowledge to proceed)  
- [ ] Reversal requires reference; duplicate matches on reference  
- [ ] FT officer can Pick Up; second officer cannot claim same ticket  
- [ ] Start → Complete works; Complete from Claimed without Start fails  
- [ ] Reject requires reason; ticket remains searchable  
- [ ] Forgot password returns generic message; Zoho delivers reset mail (or queue processes)  
- [ ] Audit trail visible to admin/auditor; no edit/delete UI  
- [ ] Main site menu links to `https://<domain>/transfer` (see `main-site-portal-link.md`)

---

## 12. Backups

Before go-live and on a schedule:

1. cPanel → **Backup** / **JetBackup**: full account or at least home + MySQL.
2. Explicitly include:
   - `nnmfb-portal/` (especially `.env` stored securely offline)
   - `nnmfb-portal/storage/app` (attachments)
   - MySQL database dump
3. Test restore on a staging subdomain before relying on it.

---

## 13. Future updates (this production host)

**Live portal:** `https://transfer.nigerianavymfb.com`  
**App root:** `/home2/nigeri45/nnmfb-portal`  
**PHP binary (CloudLinux):** `/opt/alt/php83/usr/bin/php`  
**Mail:** `MAIL_MAILER=sendmail` (SMTP outbound blocked on host)  
**Cron:** `schedule:run` every minute + `queue:work --stop-when-empty` (queued mail)

1. Backup DB + `nnmfb-portal` + public entry under the subdomain document root.
2. Upload new Package B / `git pull` on SSH into the app root (keep production `.env`).
3. Refresh public entry assets if the package changed (`css/`, `images/`, `index.php`, `.htaccess` — use `RewriteBase /` for subdomain docroot).
4. Run **exactly**:

```bash
cd /home2/nigeri45/nnmfb-portal
/opt/alt/php83/usr/bin/php artisan migrate --force
/opt/alt/php83/usr/bin/php artisan installer:lock
/opt/alt/php83/usr/bin/php artisan config:clear
/opt/alt/php83/usr/bin/php artisan route:clear
/opt/alt/php83/usr/bin/php artisan view:clear
/opt/alt/php83/usr/bin/php artisan config:cache
/opt/alt/php83/usr/bin/php artisan route:cache
/opt/alt/php83/usr/bin/php artisan view:cache
```

5. Smoke-test: login, forced password change for a new user, Pick Up SLA badge, submitter “picked up” mail (queue worker). Never re-run DEMO seeders on production.

### Pick-up SLA default

- **Default target: 240 minutes (4 hours)** from claim/pick (`claim_sla_minutes`).
- Optional overrides: Fund Transfer / Reversal minutes in **Admin → Settings** (blank = use default).
- Clock starts on Pick Up; stops on Complete/Reject; duration stored on the ticket.
- UI flags **Near SLA** (≥85% elapsed) and **Overdue**.

### Onboarding

- Admin **Add user** emails login URL + email + temporary password (failures never block create).
- First login forces **Change password**.
- **Resend welcome** resets a new temporary password and emails it again.

---

## Quick reference — artisan commands (this host)

| Command | Purpose |
|---------|---------|
| `/opt/alt/php83/usr/bin/php artisan migrate --force` | Apply schema |
| `/opt/alt/php83/usr/bin/php artisan db:seed --class=ProductionSeeder --force` | Roles + branches only |
| `/opt/alt/php83/usr/bin/php artisan installer:lock` | Disable web installer |
| `/opt/alt/php83/usr/bin/php artisan queue:work --stop-when-empty` | Send queued mail |
| `/opt/alt/php83/usr/bin/php artisan schedule:run` | Scheduler tick |

---

## Related docs

- `cpanel-public-html-transfer-deploy.md` — shorter deploy overview  
- `cpanel-shared-hosting-deploy.md` — layout summary  
- `main-site-portal-link.md` — HTML/CMS snippet for the public website  
- `security-and-compliance.md` — audit DB grants, controls  
- `drafts-policy.md` — drafts removed; submit only  
- `windows-xampp-local-install.md` — local DEMO only (not production)
