#!/usr/bin/env bash
# Enforce production file permissions.
# IMPORTANT: This file must use Unix (LF) line endings.
#
# Usage:
#   cd /path/to/site/root
#   bash set_permissions.sh

set -euo pipefail

APP_ROOT="${APP_ROOT:-$(pwd)}"
cd "$APP_ROOT"

if [[ ! -f index.php ]]; then
  echo "ERROR: index.php not found in $APP_ROOT — wrong directory?"
  exit 1
fi

echo "==> Site root: $APP_ROOT"
echo "==> Enforcing permissions (dirs 755, files 644, secrets 600, storage writable)"

find . -type d -exec chmod 755 {} +
find . -type f -exec chmod 644 {} +
find . -type f -name '*.sh' -exec chmod 755 {} + 2>/dev/null || true

if [[ -f .env ]]; then
  chmod 600 .env
  echo "    .env -> 600"
fi
if [[ -f .env.production ]]; then
  chmod 600 .env.production
fi

[[ -f .htaccess ]] && chmod 644 .htaccess
[[ -f index.php ]] && chmod 644 index.php
[[ -f qrcode.php ]] && chmod 644 qrcode.php

mkdir -p \
  storage/qrcodes \
  storage/signatures \
  storage/imports \
  storage/reports \
  storage/runtime \
  storage/logs 2>/dev/null || true

find storage -type d -exec chmod 755 {} +
find storage -type f -exec chmod 644 {} +
find storage -name '.htaccess' -exec chmod 644 {} + 2>/dev/null || true

[[ -d config ]] && find config -type f -exec chmod 644 {} +
[[ -d migrations ]] && find migrations -type f -exec chmod 644 {} +
[[ -d scripts ]] && find scripts -type f -name '*.php' -exec chmod 644 {} +

for danger in diagnose.php create_admin.php run_migrations_once.php; do
  if [[ -f "$danger" ]]; then
    chmod 600 "$danger"
    echo "    WARN: $danger is present — delete after use"
  fi
done

chmod -R o-w . 2>/dev/null || true
[[ -f .env ]] && chmod 600 .env

echo "==> Sample modes:"
ls -la .env .htaccess index.php 2>/dev/null || true
ls -ld storage storage/qrcodes storage/signatures 2>/dev/null || true

echo "==> Permissions applied."
echo "    dirs=755  files=644  .env=600"
echo "    Avoid 777. PHP should run as the site user."
