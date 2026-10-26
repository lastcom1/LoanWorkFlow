#!/usr/bin/env bash
# Build Package A (source) and Package B (public_html/transfer flat deploy)
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

echo "==> Package B (public_html/transfer flat layout)"
BUILD="$STAGE/transfer"
mkdir -p "$BUILD"

# Copy application (no .git / tests / node)
rsync_like() {
  tar -C "$ROOT" -cf - \
    --exclude='.git' --exclude='node_modules' --exclude='.env' \
    --exclude='database/database.sqlite' --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/data/*' --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' --exclude='.phpunit.result.cache' \
    --exclude='storage/app/installed.lock' --exclude='tests' --exclude='phpunit.xml' \
    --exclude='deploy' \
    . | tar -C "$BUILD" -xf -
}

if command -v rsync >/dev/null 2>&1; then
  rsync -a --delete \
    --exclude='.git' --exclude='node_modules' --exclude='.env' \
    --exclude='database/database.sqlite' --exclude='storage/logs/' \
    --exclude='storage/framework/cache/data/' --exclude='storage/framework/sessions/' \
    --exclude='storage/framework/views/' --exclude='.phpunit.result.cache' \
    --exclude='storage/app/installed.lock' --exclude='tests' --exclude='phpunit.xml' \
    --exclude='deploy' \
    "$ROOT"/ "$BUILD"/
else
  rsync_like
fi

# Ensure production vendor present
(
  cd "$BUILD"
  if [[ ! -f vendor/autoload.php ]]; then
    composer install --no-dev --optimize-autoloader --no-interaction
  else
    composer install --no-dev --optimize-autoloader --no-interaction
  fi
)

# Flatten public/ into transfer root for subdirectory hosting
cp -a "$BUILD/public/." "$BUILD/"
cp -f "$ROOT/deploy/transfer-subdir/index.php" "$BUILD/index.php"
cp -f "$ROOT/deploy/transfer-subdir/.htaccess" "$BUILD/.htaccess"
# Keep a copy of original public assets path note; remove nested public to avoid confusion
rm -rf "$BUILD/public"
mkdir -p "$BUILD/public"
# Compatibility stub if anything still references /public
echo "Document root is this folder (public_html/transfer). Do not serve from a nested public/." > "$BUILD/public/README.txt"

# Seed storage placeholders
mkdir -p "$BUILD/storage/framework/"{cache,sessions,views} "$BUILD/storage/logs" "$BUILD/storage/app/public" "$BUILD/bootstrap/cache"
touch "$BUILD/storage/logs/.gitkeep" "$BUILD/bootstrap/cache/.gitkeep"

# Deploy readme inside package
cat > "$BUILD/DEPLOY-TO-PUBLIC-HTML-TRANSFER.txt" <<'EOF'
Extract the contents of this ZIP so that index.php and .htaccess land in:

  public_html/transfer/

Expected portal URL:
  https://nigerianavymfb.com/transfer

Set in .env:
  APP_URL=https://nigerianavymfb.com/transfer
  APP_ENV=production
  APP_DEBUG=false

Full steps: see docs/guides/cpanel-public-html-transfer-deploy.md
EOF

# Copy guides into package docs
mkdir -p "$BUILD/docs/guides"
cp -f "$ROOT/docs/guides/"*.md "$BUILD/docs/guides/" 2>/dev/null || true

PKG_B="$OUT/nnmfb-transfer-portal-package-b-public-html-transfer.zip"
rm -f "$PKG_B"
(
  cd "$STAGE"
  zip -rq "$PKG_B" transfer
)

# Also keep a clearly named alias copy for older references
cp -f "$PKG_B" "$OUT/nnmfb-transfer-portal-package-b-shared-hosting.zip"

echo "Built:"
ls -lh "$PKG_A" "$PKG_B" "$OUT/nnmfb-transfer-portal-package-b-shared-hosting.zip"
unzip -l "$PKG_B" | head -35
echo "..."
unzip -l "$PKG_B" | rg -n "transfer/\.htaccess|transfer/index\.php|transfer/vendor/autoload|transfer/css/app" | head -20
unzip -l "$PKG_B" | tail -3
