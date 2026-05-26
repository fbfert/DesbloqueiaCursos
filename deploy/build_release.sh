#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RELEASE_DIR="$ROOT_DIR/deploy/releases"
STAMP="$(date +%Y%m%d-%H%M%S)"
ZIP_FILE="$RELEASE_DIR/portal-cursos-release-$STAMP.zip"
FILE_LIST="$(mktemp)"
native_path() {
  if command -v cygpath >/dev/null 2>&1; then
    cygpath -w "$1"
  else
    printf '%s' "$1"
  fi
}
FILE_LIST_NATIVE="$(native_path "$FILE_LIST")"
ZIP_FILE_NATIVE="$(native_path "$ZIP_FILE")"

cleanup() {
  rm -f "$FILE_LIST"
}
trap cleanup EXIT

mkdir -p "$RELEASE_DIR"

cd "$ROOT_DIR"

include_sources=(
  "index.php"
  ".htaccess"
  ".env.example"
  "app"
  "config"
  "public_html"
  "resources"
  "routes"
  "sql"
)

for source in "${include_sources[@]}"; do
  if [[ -f "$source" ]]; then
    printf '%s\n' "$source" >> "$FILE_LIST"
    continue
  fi

  if [[ -d "$source" ]]; then
    find "$source" -type f \
      ! -name '.gitkeep' \
      ! -name '.env' \
      ! -name '.env.*' \
      ! -name '*.log' \
      ! -name '*.tmp' \
      ! -name '*.temp' \
      ! -name '*.bak' \
      ! -name '*.old' \
      ! -name '*.zip' \
      ! -name '*.tar' \
      ! -name '*.tar.gz' \
      ! -name '*.gz' \
      ! -name '*.cookies' \
      ! -name '*Cópia em conflito*' \
      ! -name '*cópia em conflito*' \
      ! -name '*C#U00f3pia em conflito*' \
      ! -name '*conflicted copy*' \
      -print | sed 's#^\./##' >> "$FILE_LIST"
  fi
done

sort -u "$FILE_LIST" -o "$FILE_LIST"

if command -v zip >/dev/null 2>&1; then
  zip -q -@ "$ZIP_FILE" < "$FILE_LIST"
elif command -v powershell.exe >/dev/null 2>&1; then
  powershell.exe -ExecutionPolicy Bypass -NoProfile -Command "Import-Module Microsoft.PowerShell.Archive; Compress-Archive -LiteralPath @(Get-Content -LiteralPath '$FILE_LIST_NATIVE') -DestinationPath '$ZIP_FILE_NATIVE' -Force"
elif command -v pwsh >/dev/null 2>&1; then
  pwsh -NoProfile -Command "Import-Module Microsoft.PowerShell.Archive; Compress-Archive -LiteralPath @(Get-Content -LiteralPath '$FILE_LIST_NATIVE') -DestinationPath '$ZIP_FILE_NATIVE' -Force"
else
  echo "Erro: não foi encontrado 'zip', 'powershell.exe' ou 'pwsh' no ambiente." >&2
  exit 1
fi

echo "$ZIP_FILE"
