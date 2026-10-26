# Windows + XAMPP local install (beginner)

**DEMO prototype** — change all DEMO passwords before any production use.

## What you need

- Windows 10/11
- [XAMPP](https://www.apachefriends.org/) with PHP 8.2+ and MySQL/MariaDB
- [Composer](https://getcomposer.org/download/) for Windows
- Package A ZIP (`nnmfb-transfer-portal-package-a-source.zip`)

## Steps (copy-paste friendly)

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.

2. Create database in phpMyAdmin named `nnmfb_portal`.

3. Unzip Package A to a folder, for example:
   `C:\xampp\htdocs\nnmfb-portal`

4. Open **Command Prompt** or PowerShell in that folder:

```bat
cd C:\xampp\htdocs\nnmfb-portal
composer install
copy .env.example .env
php artisan key:generate
```

5. Edit `.env` (Notepad):

```env
APP_NAME="NNMFB Transfer Portal"
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nnmfb_portal
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=log
MAIL_HOST=smtp.zoho.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@example.com"
MAIL_FROM_NAME="NNMFB Transfer Portal"
```

For Zoho SMTP later, set `MAIL_MAILER=smtp` and your Zoho mailbox credentials. Never commit real passwords.

6. Migrate and seed:

```bat
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

7. Open http://127.0.0.1:8000 and sign in with a **DEMO** account below.

### Optional: SQLite instead of MySQL

```bat
copy nul database\database.sqlite
```

Set `DB_CONNECTION=sqlite` in `.env` (comment out MySQL lines), then `php artisan migrate --seed`.

## DEMO logins (labelled DEMO — change before production)

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@nnmfb.demo | DemoAdmin@123 |
| MD/CEO | mdceo@nnmfb.demo | DemoMdCeo@123 |
| Supervisor | supervisor@nnmfb.demo | DemoSupervisor@123 |
| FT Officer 1 | ft1@nnmfb.demo | DemoFtOfficer1@123 |
| FT Officer 2 | ft2@nnmfb.demo | DemoFtOfficer2@123 |
| FT Officer 3 | ft3@nnmfb.demo | DemoFtOfficer3@123 |
| FT Officer 4 | ft4@nnmfb.demo | DemoFtOfficer4@123 |
| Submitter | submitter@nnmfb.demo | DemoSubmitter@123 |
| Auditor | auditor@nnmfb.demo | DemoAuditor@123 |

## Queue worker (email delivery records)

```bat
php artisan queue:work
```

With `MAIL_MAILER=log`, messages write to `storage/logs` instead of SMTP.

## Smoke checklist

1. Login as submitter → create Fund Transfer ticket  
2. Login as FT Officer 1 → Pick Up  
3. Login as FT Officer 2 → same ticket claim fails with assignment message  
4. Submit duplicate account+amount → warning badge  
5. Reversal with same original reference → warning  
6. Reject with reason → ticket retained and searchable  
