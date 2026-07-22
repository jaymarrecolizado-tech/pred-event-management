#!/usr/bin/env bash
# Clear all attendee / registration test data.
# Keeps: admins, events, coa_signatories, report_templates
#
# Usage (from site root):
#   bash scripts/clear_attendee_data.sh
#   bash scripts/clear_attendee_data.sh --yes   # skip confirmation
#
# IMPORTANT: Unix (LF) line endings required.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ ! -f index.php || ! -f .env ]]; then
  echo "ERROR: Run this from the site root (index.php and .env must exist)."
  echo "Current: $ROOT"
  exit 1
fi

if [[ ! -f scripts/clear_attendee_data.php ]]; then
  echo "ERROR: scripts/clear_attendee_data.php is missing."
  exit 1
fi

SKIP_CONFIRM=0
if [[ "${1:-}" == "--yes" || "${1:-}" == "-y" ]]; then
  SKIP_CONFIRM=1
fi

echo "======================================================"
echo " CLEAR ATTENDEE TEST DATA"
echo " Site: $ROOT"
echo "======================================================"
echo "This will DELETE:"
echo "  - participants (registrants)"
echo "  - attendance records"
echo "  - CoA send batches/items"
echo "  - import logs + rate limits"
echo "  - files in storage/signatures, qrcodes, certificates"
echo ""
echo "This will KEEP:"
echo "  - admins / users"
echo "  - events"
echo "  - CoA signatories"
echo "  - report templates"
echo "======================================================"

if [[ "$SKIP_CONFIRM" -ne 1 ]]; then
  read -r -p "Type YES to continue: " answer
  if [[ "$answer" != "YES" ]]; then
    echo "Cancelled."
    exit 0
  fi
fi

echo "==> Clearing database tables..."
php scripts/clear_attendee_data.php

echo "==> Clearing uploaded files..."
for dir in storage/signatures storage/qrcodes storage/certificates; do
  if [[ -d "$dir" ]]; then
    # Remove files but keep directory protectors
    find "$dir" -type f ! -name 'index.php' ! -name '.gitkeep' ! -name '.htaccess' -delete 2>/dev/null || true
    echo "    cleaned $dir"
  else
    mkdir -p "$dir"
    echo "    created $dir"
  fi
done

echo "==> Done. Attendee test data cleared."
echo "    Admins and events were preserved."
