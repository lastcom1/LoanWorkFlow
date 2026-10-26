# cPanel shared hosting deploy (overview)

**Preferred for nigerianavymfb.com:** deploy under **`public_html/transfer`**  
→ follow **[cpanel-public-html-transfer-deploy.md](./cpanel-public-html-transfer-deploy.md)**  
→ ZIP: `nnmfb-transfer-portal-package-b-public-html-transfer.zip`

Portal URL: **https://nigerianavymfb.com/transfer**

---

## Alternate: app outside `public_html` (when you can place files above the docroot)

Use only if your host lets you store code outside `public_html` and you still want `/transfer` as the public entry.

```
/home/USER/
  nnmfb-portal/                 ← full Laravel app (not web-reachable)
  public_html/
    transfer/                   ← ONLY public entry files
      index.php                 ← point autoload/bootstrap to ../../nnmfb-portal
      .htaccess
      css/ images/ favicon.png
```

`public_html/transfer/index.php` should require the app root, for example:

```php
require __DIR__.'/../../nnmfb-portal/vendor/autoload.php';
$app = require_once __DIR__.'/../../nnmfb-portal/bootstrap/app.php';
```

Set:

```env
APP_URL=https://nigerianavymfb.com/transfer
APP_ENV=production
APP_DEBUG=false
```

Then migrate/seed or use `/transfer/install`, set storage permissions, and add the main-site menu link (`main-site-portal-link.md`).

## Not recommended: site-root install

Installing the portal as the **entire** `public_html` root would replace the main NNMFB website. Prefer the `/transfer` subdirectory layout above.

## Zoho SMTP & queues

Same as the transfer subdirectory guide: configure SMTP in `.env` only; run `queue:work` via cPanel cron.

## Security

- `APP_DEBUG=false`
- HTTPS + `SESSION_SECURE_COOKIE=true`
- Change/delete DEMO users before production
- Confirm sensitive paths are not downloadable
