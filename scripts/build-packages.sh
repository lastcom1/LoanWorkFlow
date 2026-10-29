#!/usr/bin/env bash
# Package A = source. Package B = nnmfb-portal + public_html-transfer (File Manager deploy).
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

echo "==> Package B (nnmfb-portal + public_html-transfer)"
APP="$STAGE/nnmfb-portal"
WEB="$STAGE/public_html-transfer"
mkdir -p "$APP" "$WEB"

rsync -a \
  --exclude='.git' --exclude='node_modules' --exclude='.env' \
  --exclude='database/database.sqlite' --exclude='storage/logs/' \
  --exclude='storage/framework/cache/data/' --exclude='storage/framework/sessions/' \
  --exclude='storage/framework/views/' --exclude='.phpunit.result.cache' \
  --exclude='storage/app/installed.lock' --exclude='tests' --exclude='phpunit.xml' \
  --exclude='database/factories' \
  "$ROOT"/ "$APP"/

# Remove demo-oriented seeders from production package? Keep code but SEED_DEMO_DATA=false.
# Strip local .env if rsynced
rm -f "$APP/.env"

(
  cd "$APP"
  composer install --no-dev --optimize-autoloader --no-interaction
)

# Built frontend assets already live in public/css (no Vite required for this app)
test -f "$APP/public/css/app.css"
test -f "$APP/public/images/nnmfb-logo.png"

# Public entry only
cp -f "$ROOT/deploy/transfer-public-entry/index.php" "$WEB/index.php"
cp -f "$ROOT/deploy/transfer-public-entry/.htaccess" "$WEB/.htaccess"
cp -a "$APP/public/css" "$WEB/css"
cp -a "$APP/public/images" "$WEB/images"
cp -f "$APP/public/favicon.png" "$WEB/favicon.png" 2>/dev/null || true
cp -f "$APP/public/favicon.ico" "$WEB/favicon.ico" 2>/dev/null || true
cp -f "$APP/public/robots.txt" "$WEB/robots.txt" 2>/dev/null || true

# PATH HINT file for File Manager users
cat > "$WEB/README-PUT-IN-public_html-transfer.txt" <<'EOF'
Move the CONTENTS of this folder into:

  /home/USER/public_html/transfer/

So that index.php lives at public_html/transfer/index.php
EOF

mkdir -p "$APP/storage/framework/"{cache,sessions,views} "$APP/storage/logs" "$APP/storage/app/public" "$APP/bootstrap/cache"
# Placeholder lock — delete before web installer; SSH path uses installer:lock after seed
printf '%s\n' '{"installed_at":null,"source":"package_shipped_locked","note":"Delete this file only if running the gated web installer; otherwise keep and/or run php artisan installer:lock after deploy."}' > "$APP/storage/app/installed.lock"

mkdir -p "$APP/docs/guides"
cp -f "$ROOT/docs/guides/"*.md "$APP/docs/guides/" 2>/dev/null || true
# Prefer store go-live guide if present (authoritative for ops)
if [[ -f /cursor/stores/self/docs/guides/cpanel-go-live.md ]]; then
  cp -f /cursor/stores/self/docs/guides/cpanel-go-live.md "$APP/docs/guides/cpanel-go-live.md"
fi
cp -f "$ROOT/.env.production.example" "$APP/.env.production.example"

cat > "$STAGE/README-DEPLOY.txt" <<'EOF'
NNMFB Transfer Portal — Package B (File Manager deploy)

ZIP contents:
  nnmfb-portal/           → extract/move to /home/USER/nnmfb-portal/
  public_html-transfer/   → move CONTENTS to /home/USER/public_html/transfer/
  README-DEPLOY.txt

Full go-live steps (PHP, MySQL, .env, Zoho, installer, cron, SSL, checklist):
  nnmfb-portal/docs/guides/cpanel-go-live.md

Never place vendor/app/.env under public_html.
Never seed DEMO users on production (use ProductionSeeder + real admin).
EOF

PKG_B="$OUT/nnmfb-transfer-portal-package-b-public-html-transfer.zip"
rm -f "$PKG_B"
(
  cd "$STAGE"
  zip -rq "$PKG_B" nnmfb-portal public_html-transfer README-DEPLOY.txt
)
cp -f "$PKG_B" "$OUT/nnmfb-transfer-portal-package-b-shared-hosting.zip"

# Also publish a clearly dated go-live alias
cp -f "$PKG_B" "$OUT/nnmfb-transfer-portal-golive-package-b.zip"

echo "Built:"
ls -lh "$PKG_A" "$PKG_B" "$OUT/nnmfb-transfer-portal-golive-package-b.zip"
echo "--- top ---"
unzip -l "$PKG_B" | head -20
echo "--- public entry ---"
unzip -l "$PKG_B" | rg "public_html-transfer/(index\.php|\.htaccess|css/app|images/nnmfb)|nnmfb-portal/(artisan|vendor/autoload|\.env\.production)" | head -20
if unzip -l "$PKG_B" | rg -q "public_html-transfer/(app|vendor|bootstrap)/"; then
  echo "ERROR: sensitive paths in public entry" >&2
  exit 1
fi
if unzip -l "$PKG_B" | rg -q "(^|/)\\.env$"; then
  echo "ERROR: .env leaked into package" >&2
  exit 1
fi
echo "OK Package B"
