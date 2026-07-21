#!/usr/bin/env bash
# Run on the VPS after files are uploaded (SSH).
# Adjust APP_ROOT if your document root differs.

set -euo pipefail

APP_ROOT="${APP_ROOT:-$HOME/domains/digitalhero.dictr2.cloud/public_html}"
# Common Hostinger VPS / cloud paths — edit if needed:
# APP_ROOT="$HOME/public_html"
# APP_ROOT="/var/www/digitalhero"

cd "$APP_ROOT"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "==> Permissions"
if [[ -f "$SCRIPT_DIR/set_permissions.sh" ]]; then
  APP_ROOT="$APP_ROOT" bash "$SCRIPT_DIR/set_permissions.sh"
elif [[ -f ./set_permissions.sh ]]; then
  bash ./set_permissions.sh
else
  # Inline fallback if helper was not uploaded
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

echo "==> Create admin (edit username/password first!)"
echo "    php scripts/seed_admin.php admin 'YourStrongPasswordHere'"
echo "==> Optional role users"
echo "    php scripts/seed_role_users.php"

echo "==> Remove dangerous scripts if present"
rm -f diagnose.php create_admin.php run_migrations_once.php

echo "Done. Open https://digitalhero.dictr2.cloud/?r=admin_login"
