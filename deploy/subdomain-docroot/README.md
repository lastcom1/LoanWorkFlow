# Subdomain document-root entry

Use this when the portal is served at the **root** of a subdomain (e.g. `https://transfer.example.com/`), not under `/transfer` on the main site.

1. Copy `index.php` from `../transfer-public-entry/` (adjust `$appRoot` — often one level up to `nnmfb-portal`).
2. Use **this** `.htaccess` with `RewriteBase /` (not `/transfer/`).
3. Set `APP_URL=https://transfer.example.com` (no `/transfer` path).

For path-based deploy under `public_html/transfer/`, keep `deploy/transfer-public-entry/.htaccess` (`RewriteBase /transfer/`).
