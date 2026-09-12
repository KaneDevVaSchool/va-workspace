#!/usr/bin/env bash
#
# Deploy script — VA Workspace (Laravel 10 + Vue 3 Modular Monolith)
# Chạy trực tiếp trên server, trong thư mục project (public_html).
#
# KHÔNG chứa bước migrate DB — chạy `php artisan migrate --force` riêng,
# thủ công, sau khi đã kiểm tra kỹ.
#
# Cách dùng:
#   cd /home/workspace.vaschools.edu.vn/public_html
#   bash deploy.sh
#
# Tuỳ chọn:
#   SKIP_NPM=1 bash deploy.sh     # bỏ qua npm ci + build (VD chỉ đổi backend)
#   BRANCH=main bash deploy.sh    # đổi nhánh deploy (mặc định: main)

set -euo pipefail

BRANCH="${BRANCH:-main}"
APP_DIR="$(pwd)"

log()  { printf '\n\033[1;36m==> %s\033[0m\n' "$1"; }
warn() { printf '\033[1;33m!! %s\033[0m\n' "$1"; }
err()  { printf '\033[1;31mXX %s\033[0m\n' "$1"; }

# --- 0. Kiểm tra sơ bộ -------------------------------------------------
if [ ! -f artisan ]; then
  err "Không thấy file 'artisan' — hãy cd vào đúng thư mục project (public_html) trước khi chạy."
  exit 1
fi

if [ ! -f .env ]; then
  err "Không thấy .env — dừng lại, kiểm tra thủ công trước khi deploy."
  exit 1
fi

log "Thư mục project: $APP_DIR"
log "Nhánh deploy: $BRANCH"

# --- 1. Bật maintenance mode -------------------------------------------
log "Bật maintenance mode"
php artisan down --retry=15 || warn "Không bật được maintenance mode (bỏ qua, tiếp tục)"

# Đảm bảo tắt maintenance mode dù script fail ở bước nào
trap 'php artisan up >/dev/null 2>&1 || true' EXIT

# --- 2. Cập nhật code ----------------------------------------------------
log "Lưu commit hiện tại để rollback nếu cần"
PREV_COMMIT="$(git rev-parse HEAD)"
echo "Commit trước deploy: $PREV_COMMIT"

log "git fetch + checkout $BRANCH"
git fetch origin "$BRANCH"
git checkout "$BRANCH"

log "git reset --hard origin/$BRANCH"
git reset --hard "origin/$BRANCH"

NEW_COMMIT="$(git rev-parse HEAD)"
echo "Commit sau deploy: $NEW_COMMIT"

if [ "$PREV_COMMIT" = "$NEW_COMMIT" ]; then
  warn "Không có commit mới — code không đổi."
fi

# --- 3. Composer (PHP dependencies) --------------------------------------
log "composer install --no-dev --optimize-autoloader"
composer install --no-dev --optimize-autoloader --no-interaction

# --- 4. NPM (frontend build) ---------------------------------------------
if [ "${SKIP_NPM:-0}" != "1" ]; then
  log "npm ci"
  npm ci

  log "npm run build"
  npm run build
else
  warn "SKIP_NPM=1 — bỏ qua bước build frontend"
fi

# --- 5. Laravel Modules ----------------------------------------------------
if [ -f artisan ] && php artisan list module 2>/dev/null | grep -q 'module:'; then
  log "Đồng bộ module (module:migrate --force chỉ khi bạn tự chạy riêng)"
  # Không tự enable/disable module ở đây để tránh bật nhầm module chưa sẵn sàng
  # trên production. Kiểm tra modules_statuses.json thủ công nếu có module mới.
  php artisan module:list || true
fi

# --- 6. Cache config/route/view -------------------------------------------
log "Xoá cache cũ"
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

log "Build cache mới (config, route, view)"
php artisan config:cache
php artisan route:cache
php artisan view:cache

# --- 7. Storage link (an toàn nếu đã tồn tại) ------------------------------
php artisan storage:link || true

# --- 8. Quyền thư mục ghi được ----------------------------------------------
log "Đảm bảo quyền ghi cho storage/ và bootstrap/cache/"
chmod -R ug+rwX storage bootstrap/cache || warn "Không chỉnh được quyền — kiểm tra thủ công"

# --- 9. Restart queue worker (nếu dùng supervisor) --------------------------
if command -v supervisorctl >/dev/null 2>&1; then
  log "Restart queue worker qua supervisor"
  supervisorctl restart all || warn "Không restart được qua supervisorctl — kiểm tra thủ công"
else
  warn "Không thấy supervisorctl — nếu có queue worker chạy riêng, restart thủ công."
fi

# --- 10. Restart LiteSpeed (CyberPanel) -------------------------------------
log "Restart LiteSpeed (CyberPanel)"
if command -v systemctl >/dev/null 2>&1 && systemctl list-units --full -all | grep -q 'lsws'; then
  systemctl restart lsws || warn "systemctl restart lsws thất bại — kiểm tra thủ công (có thể cần sudo)"
elif [ -x /usr/local/lsws/bin/lswsctrl ]; then
  /usr/local/lsws/bin/lswsctrl restart || warn "lswsctrl restart thất bại — kiểm tra thủ công"
else
  warn "Không tìm thấy cách restart LiteSpeed tự động — restart thủ công qua CyberPanel UI nếu cần."
fi

# --- 11. Tắt maintenance mode ------------------------------------------------
log "Tắt maintenance mode"
php artisan up
trap - EXIT

log "DEPLOY HOÀN TẤT"
echo "  Commit trước:  $PREV_COMMIT"
echo "  Commit sau:    $NEW_COMMIT"
echo ""
echo "Lưu ý:"
echo "  - Script này KHÔNG chạy 'php artisan migrate'. Nếu có migration mới,"
echo "    kiểm tra kỹ rồi tự chạy: php artisan migrate --force"
echo "  - Nếu có module mới trong modules_statuses.json (vd. Credential) chưa"
echo "    từng chạy trên server này, cần enable + migrate riêng:"
echo "      php artisan module:enable <TenModule>"
echo "      php artisan module:migrate <TenModule> --force"
