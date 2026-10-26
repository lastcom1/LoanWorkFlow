# cPanel shared hosting deploy (no Composer on server)

Use **Package B** (`nnmfb-transfer-portal-package-b-shared-hosting.zip`).

## Layout recommendation

```
/home/USER/
  nnmfb-portal/          ← Laravel app root (outside public_html)
    app/, bootstrap/, config/, database/, resources/, routes/, storage/, vendor/, artisan, .env
  public_html/           ← document root
    index.php, .htaccess, css/, images/, favicon.png, ...
```

If your host forces everything under `public_html`, place the app in `public_html/../nnmfb-portal` (or `public_html/app-root`) and point the domain document root to the Laravel `public` folder when the host allows it.

## Upload steps

1. Create a MySQL database + user in cPanel; note host/name/user/password.
2. Upload and unzip Package B on the server (File Manager or FTP).
3. Copy Laravel `public/` contents into `public_html/`.
4. Edit `public_html/index.php` so paths point to the app root, e.g.:

```php
require __DIR__.'/../nnmfb-portal/vendor/autoload.php';
$app = require_once __DIR__.'/../nnmfb-portal/bootstrap/app.php';
```

(Package B ships a ready `public/index.php`; adjust relative paths to match your folder names.)

5. Place `shared-hosting.htaccess` **above** the public docroot if needed to block `.env` / `artisan` exposure.
6. Create `.env` in the app root from `.env.example`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

MAIL_MAILER=smtp
MAIL_HOST=smtp.zoho.com
MAIL_PORT=587
MAIL_USERNAME=your-zoho-user
MAIL_PASSWORD=your-zoho-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.example
MAIL_FROM_NAME="NNMFB Transfer Portal"

SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
```

7. Set permissions: `storage/` and `bootstrap/cache/` writable (0755/0775 as host requires).
8. **Web installer:** visit `https://your-domain.example/install` if migrations were not run yet. Complete requirements → DB/SMTP → migrate → admin → lock.
9. Or run via SSH if available: `php artisan migrate --seed --force` then create `storage/app/installed.lock`.
10. After install succeeds, confirm installer is locked; keep `APP_DEBUG=false`.

## Cron for queues (cPanel)

Add a cron every minute:

```bash
cd /home/USER/nnmfb-portal && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
```

## Security checklist

- Document root = public entry only  
- `APP_DEBUG=false`  
- HTTPS + secure cookies  
- Change all DEMO passwords or delete DEMO users  
- Validate Zoho SMTP with a test ticket  
- Backups: include database + `storage/app` attachments  

## CBN / NDPA note

This portal supports operational controls (access control, audit, masking) relevant to banking process accountability. It does **not** claim CBN certification or NDPA compliance certification. Conduct your own legal/compliance review before production.
