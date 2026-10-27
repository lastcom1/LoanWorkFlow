# NNMFB Transfer & Reversal Complaint Management Portal

Production-oriented Laravel portal for Nigerian Navy Microfinance Bank (NNMFB) staff to submit fund-transfer instructions and reversal complaints, for Fund Transfer Officers to claim and resolve them exclusively, and for supervisors / MD-CEO / auditors to oversee accountability.

## Stack

- Laravel 12 (PHP 8.2+)
- MySQL/MariaDB or SQLite (local)
- Spatie Laravel Permission (RBAC)
- Zoho SMTP via `.env`
- Blade UI (navy / gold branding)

## Quick local start

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan installer:lock
php artisan serve
```

Open http://127.0.0.1:8000 — DEMO logins (local only) are in `docs/guides/windows-xampp-local-install.md`.

Production servers: copy **`.env.production.example`** → `.env` (not `.env.example`), seed with `ProductionSeeder`, then `php artisan installer:lock`.

## Core invariants

1. Exclusive ticket claim with DB row locks
2. Duplicate detection: transfers = normalized account + amount; reversals = original reference (primary)
3. Immutable audit trail via application UI
4. Masked accounts in lists/emails; no PIN/CVV/OTP collection
5. Email failures never undo a successful claim

## Packages

See store docs `docs/packages/` for Package A (dev source) and Package B (shared hosting).
