#!/usr/bin/env bash
# Run on the VPS after files are uploaded (SSH).
# Adjust APP_ROOT if your document root differs.
# IMPORTANT: This file must use Unix (LF) line endings.

set -euo pipefail

# Detect site root: prefer current directory when index.php is here.
if [[ -f ./index.php ]]; then
  APP_ROOT="$(pwd)"
elif [[ -n "${APP_ROOT:-}" ]]; then
  :
else
  # Common Hostinger / CloudPanel paths
  for candidate in \
    "$HOME/htdocs/digitalhero.dictr2.cloud" \
    "$HOME/domains/digitalhero.dictr2.cloud/public_html" \
    "$HOME/public_html" \
    "/home/dghero111/htdocs/digitalhero.dictr2.cloud"
  do
    if [[ -f "$candidate/index.php" ]]; then
      APP_ROOT="$candidate"
      break
    fi
  done
fi

APP_ROOT="${APP_ROOT:-$(pwd)}"
cd "$APP_ROOT"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "==> Site root: $APP_ROOT"

echo "==> Permissions"
if [[ -f "$SCRIPT_DIR/set_permissions.sh" ]]; then
  # Strip Windows CRLF if present, then run
  sed -i 's/\r$//' "$SCRIPT_DIR/set_permissions.sh" 2>/dev/null || true
  APP_ROOT="$APP_ROOT" bash "$SCRIPT_DIR/set_permissions.sh"
elif [[ -f ./set_permissions.sh ]]; then
  sed -i 's/\r$//' ./set_permissions.sh 2>/dev/null || true
  bash ./set_permissions.sh
else
  find . -type d -exec chmod 755 {} +
  find . -type f -exec chmod 644 {} +
  find . -type f -name '*.sh' -exec chmod 755 {} + 2>/dev/null || true
  [[ -f .env ]] && chmod 600 .env
  mkdir -p storage/qrcodes storage/signatures storage/imports storage/reports storage/runtime
  find storage -type d -exec chmod 755 {} +
  chmod -R o-w . 2>/dev/null || true
  [[ -f .env ]] && chmod 600 .env
  echo "    (inline permission pass complete)"
fi

echo "==> Migrations"
php scripts/run_migrations.php

echo "==> Create / reset admin (run this next):"
echo "    php scripts/seed_admin.php admin 'YourStrongPasswordHere'"

echo "==> Remove dangerous scripts if present"
rm -f diagnose.php create_admin.php run_migrations_once.php

echo "Done. Open https://digitalhero.dictr2.cloud/?r=admin_login"
