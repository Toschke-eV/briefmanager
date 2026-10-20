#!/usr/bin/env bash
# Baut ein öffentliches Source-/Install-ZIP ohne private Logos und ohne Secrets.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT_DIR="$ROOT/dist"
OUT="$OUT_DIR/briefmanager-public-source.zip"
STAGING="$(mktemp -d)"
trap 'rm -rf "$STAGING"' EXIT

mkdir -p "$OUT_DIR" "$STAGING/briefmanager"

if [[ ! -d "$ROOT/vendor/tecnickcom/tcpdf" ]]; then
  (cd "$ROOT" && composer install --no-dev --optimize-autoloader --no-interaction)
fi

rsync -a \
  --exclude '.DS_Store' \
  --exclude '._*' \
  --exclude '.git' \
  --exclude 'dist' \
  --exclude 'tests' \
  --exclude '.gitignore' \
  --exclude 'scripts' \
  --exclude 'vendor/tecnickcom/tcpdf/fonts/freefont*' \
  --exclude 'vendor/tecnickcom/tcpdf/fonts/ae_*' \
  --exclude 'vendor/tecnickcom/tcpdf/fonts/cid*' \
  --exclude 'vendor/tecnickcom/tcpdf/fonts/koz*' \
  --exclude 'vendor/tecnickcom/tcpdf/fonts/msung*' \
  --exclude 'vendor/tecnickcom/tcpdf/fonts/hysmyeongjo*' \
  --exclude 'vendor/tecnickcom/tcpdf/fonts/stsong*' \
  --exclude 'vendor/tecnickcom/tcpdf/fonts/uni*' \
  --exclude 'vendor/tecnickcom/tcpdf/tools' \
  --exclude 'vendor/tecnickcom/tcpdf/examples' \
  "$ROOT/" "$STAGING/briefmanager/"

find "$STAGING" \( -name '*.key' -o -name '*.csr' -o -name '*.crt' -o -name '*.pem' \) -delete
find "$STAGING/briefmanager/img/designs" -type f ! -name 'muster.png' -delete 2>/dev/null || true

rm -f "$OUT"
(cd "$STAGING" && zip -qr "$OUT" briefmanager)
echo "OK: $OUT ($(du -h "$OUT" | awk '{print $1}'))"
