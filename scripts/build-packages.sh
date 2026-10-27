#!/usr/bin/env bash
# Package A = source. Package B = app outside public_html + public_html/transfer entry.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-/cursor/stores/self/docs/packages}"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

mkdir -p "$OUT"

echo "==> Package A (dev source)"
PKG_A="$OUT/nnmfb-transfer-portal-package-a-source.zip"
rm -f "$PKG_A"
(
  cd "$ROOT"
  zip -rq "$PKG_A" . \
    -x "./.git/*" "./vendor/*" "./node_modules/*" "./.env" "./database/database.sqlite" \
       "./storage/logs/*" "./storage/framework/cache/data/*" "./storage/framework/sessions/*" \
       "./storage/framework/views/*" "./.phpunit.result.cache" "./storage/app/installed.lock" \
       "./storage/app/public/*"
)

echo "==> Package B (nnmfb-portal + public_html/transfer entry)"
APP="$STAGE/nnmfb-portal"
WEB="$STAGE/public_html/transfer"
mkdir -p "$APP" "$WEB"

rsync -a \
  --exclude='.git' --exclude='node_modules' --exclude='.env' \
  --exclude='database/database.sqlite' --exclude='storage/logs/' \
  --exclude='storage/framework/cache/data/' --exclude='storage/framework/sessions/' \
  --exclude='storage/framework/views/' --exclude='.phpunit.result.cache' \
  --exclude='storage/app/installed.lock' --exclude='tests' --exclude='phpunit.xml' \
  "$ROOT"/ "$APP"/

(
  cd "$APP"
  composer install --no-dev --optimize-autoloader --no-interaction
)

# Public entry only: front controller + static assets (no app/vendor)
cp -f "$ROOT/deploy/transfer-public-entry/index.php" "$WEB/index.php"
cp -f "$ROOT/deploy/transfer-public-entry/.htaccess" "$WEB/.htaccess"
cp -a "$APP/public/css" "$WEB/css"
cp -a "$APP/public/images" "$WEB/images"
cp -f "$APP/public/favicon.png" "$WEB/favicon.png" 2>/dev/null || true
cp -f "$APP/public/favicon.ico" "$WEB/favicon.ico" 2>/dev/null || true
cp -f "$APP/public/robots.txt" "$WEB/robots.txt" 2>/dev/null || true

# Point entry at sibling ../../nnmfb-portal from public_html/transfer
# (dirname(__DIR__, 2)/nnmfb-portal == /home/USER/nnmfb-portal when transfer is under public_html)

mkdir -p "$APP/storage/framework/"{cache,sessions,views} "$APP/storage/logs" "$APP/storage/app/public" "$APP/bootstrap/cache"
# Lock installer by default in packaged production build
echo '{"installed_at":null,"source":"package_default_locked","note":"Replace after real install or run installer:lock"}' > "$APP/storage/app/installed.lock"

mkdir -p "$APP/docs/guides"
cp -f "$ROOT/docs/guides/"*.md "$APP/docs/guides/" 2>/dev/null || true

cat > "$STAGE/README-DEPLOY.txt" <<'EOF'
NNMFB Transfer Portal — Package B layout

1) Upload/extract so you have:
     /home/USER/nnmfb-portal/          ← full Laravel app (NOT web-accessible)
     /home/USER/public_html/transfer/  ← index.php, .htaccess, css/, images/ only

2) Edit public_html/transfer/index.php if your home path differs (default expects
   dirname(public_html)/nnmfb-portal i.e. ~/nnmfb-portal).

3) Copy nnmfb-portal/.env.production.example → .env (NOT .env.example), set DB_* and Zoho SMTP.
     Safe defaults already include production/debug/installer/demo flags.

4) SSH (recommended):
     cd ~/nnmfb-portal
     php artisan key:generate --force
     php artisan migrate --force
     php artisan db:seed --class=ProductionSeeder --force
     php artisan installer:lock --email=you@example.com
     # Create the real admin via tinker or a one-time INSTALLER_ENABLED setup.

5) Never run DefaultAdminSeeder / DemoDatabaseSeeder on production.

Guide: nnmfb-portal/docs/guides/cpanel-public-html-transfer-deploy.md
EOF

PKG_B="$OUT/nnmfb-transfer-portal-package-b-public-html-transfer.zip"
rm -f "$PKG_B"
(
  cd "$STAGE"
  zip -rq "$PKG_B" nnmfb-portal public_html README-DEPLOY.txt
)
cp -f "$PKG_B" "$OUT/nnmfb-transfer-portal-package-b-shared-hosting.zip"

echo "Built:"
ls -lh "$PKG_A" "$PKG_B"
echo "--- Package B top entries ---"
unzip -l "$PKG_B" | head -25
echo "--- Must NOT contain app under public_html/transfer ---"
if unzip -l "$PKG_B" | rg -q "public_html/transfer/(app|vendor|bootstrap|\.env)/"; then
  echo "ERROR: sensitive paths inside public_html/transfer" >&2
  exit 1
fi
unzip -l "$PKG_B" | rg "public_html/transfer/(index\.php|\.htaccess|css/|images/)|nnmfb-portal/artisan|nnmfb-portal/vendor/autoload" | head -20
echo "OK layout"
