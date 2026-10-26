#!/usr/bin/env bash
#
# Đồng bộ code production với origin (bỏ qua sửa local trên file tracked).
# Dùng khi `git pull` báo "would be overwritten by merge".
#
#   cd /path/to/project
#   bash scripts/prod-sync-code.sh
#
# Tuỳ chọn:
#   BRANCH=main SKIP_NPM=1 bash scripts/prod-sync-code.sh

set -euo pipefail

BRANCH="${BRANCH:-main}"

if [ ! -f artisan ]; then
  echo "Chạy script trong thư mục gốc Laravel (có file artisan)." >&2
  exit 1
fi

echo "==> git fetch origin $BRANCH"
git fetch origin "$BRANCH"

echo "==> git reset --hard origin/$BRANCH (bỏ sửa local trên file tracked)"
git reset --hard "origin/$BRANCH"

if [ "${SKIP_NPM:-0}" != "1" ]; then
  echo "==> npm run build"
  npm run build
else
  echo "SKIP_NPM=1 — bỏ build"
fi

echo "==> Xong. Commit hiện tại: $(git rev-parse --short HEAD)"
