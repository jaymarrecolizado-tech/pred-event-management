#!/usr/bin/env bash
# Enforce production file permissions for attendance_digital on Hostinger.
# Run from the site document root, or set APP_ROOT.
#
# Usage:
#   cd /path/to/public_html
#   bash set_permissions.sh
#   # or:
#   APP_ROOT=/path/to/public_html bash set_permissions.sh

set -euo pipefail

APP_ROOT="${APP_ROOT:-$(pwd)}"
cd "$APP_ROOT"

if [[ ! -f index.php ]]; then
  echo "ERROR: index.php not found in $APP_ROOT — wrong directory?"
  exit 1
fi

echo "==> Site root: $APP_ROOT"
echo "==> Enforcing permissions (dirs 755, files 644, secrets 600, storage writable)"

# Baseline: no world-writable junk
# Directories
find . -type d -exec chmod 755 {} +

# Regular files
find . -type f -exec chmod 644 {} +

# Shell helpers (if present)
find . -type f -name '*.sh' -exec chmod 755 {} + 2>/dev/null || true

# Secrets — never world-readable
if [[ -f .env ]]; then
  chmod 600 .env
  echo "    .env -> 600"
fi
if [[ -f .env.production ]]; then
  chmod 600 .env.production
fi

# Web entry / rewrite
[[ -f .htaccess ]] && chmod 644 .htaccess
[[ -f index.php ]] && chmod 644 index.php
[[ -f qrcode.php ]] && chmod 644 qrcode.php
[[ -f signature.php ]] && chmod 644 signature.php

# Ensure storage tree exists and is owner-writable (755 is enough when PHP runs as site user)
mkdir -p \
  storage/qrcodes \
  storage/signatures \
  storage/imports \
  storage/reports \
  storage/runtime \
  storage/logs 2>/dev/null || true

find storage -type d -exec chmod 755 {} +
find storage -type f -exec chmod 644 {} +

# Protect storage from direct listing via .htaccess if present
find storage -name '.htaccess' -exec chmod 644 {} + 2>/dev/null || true

# Config / source / views — read-only for web (644/755 already applied)
# Double-lock sensitive paths if they exist
[[ -d config ]] && find config -type f -exec chmod 644 {} +
[[ -d migrations ]] && find migrations -type f -exec chmod 644 {} +
[[ -d scripts ]] && find scripts -type f -name '*.php' -exec chmod 644 {} +

# Never leave these executable or world-writable
for danger in diagnose.php create_admin.php run_migrations_once.php; do
  if [[ -f "$danger" ]]; then
    chmod 600 "$danger"
    echo "    WARN: $danger is present — delete after use (chmod 600 for now)"
  fi
done

# Strip write bits for "other" everywhere (defense in depth)
chmod -R o-w . 2>/dev/null || true
# Restore .env to 600 after o-w pass
[[ -f .env ]] && chmod 600 .env

echo "==> Sample modes:"
ls -la .env .htaccess index.php 2>/dev/null || true
ls -ld storage storage/qrcodes storage/signatures 2>/dev/null || true

echo "==> Permissions applied."
echo "    dirs=755  files=644  .env=600  storage writable by owner"
echo "    Avoid 777. If uploads fail, confirm PHP runs as site user: digitalhero"
