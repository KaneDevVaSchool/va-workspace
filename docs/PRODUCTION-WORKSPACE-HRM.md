# Production — workspace.vaschools.edu.vn ↔ hrm.vaschools.edu.vn

Chấm công, nghỉ phép, SSO và webhook dùng **một** host HRM: `https://hrm.vaschools.edu.vn`.  
Không dùng `HRM_LEAVE_API_*` trỏ `portal.hrm.*` trên production (token portal không hợp lệ trên HRM chính).

## 1. Trên server HRM (`hrm.vaschools.edu.vn`)

Trong thư mục `public_html` của HRM:

```bash
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan db:seed --class=ApiClientSeeder
php artisan integration:setup-va-workspace --mint
```

Lệnh `--mint` in **một lần**:

- `HRM_API_TOKEN`
- `HRM_TIMESHEET_API_TOKEN` (cùng token, đủ `attendance:punch` + `leave:read` + …)

Thu hồi token cũ tại `/admin/api-clients` → client **va-workspace** nếu token cũ bị lộ hoặc 401.

`.env` HRM production (tóm tắt):

```env
APP_URL=https://hrm.vaschools.edu.vn
APP_TIMEZONE=Asia/Ho_Chi_Minh
HRM_URL=https://hrm.vaschools.edu.vn
```

Deploy: `./scripts/deploy-production.sh` (xem `va-hrm/docs/DEPLOY.md`).

## 2. Trên server Workspace (`workspace.vaschools.edu.vn`)

Trong `public_html`, cập nhật `.env`:

```env
APP_URL=https://workspace.vaschools.edu.vn
APP_TIMEZONE=Asia/Ho_Chi_Minh
HRM_API_BASE_URL=https://hrm.vaschools.edu.vn
HRM_SSO_ISSUER=https://hrm.vaschools.edu.vn
HRM_SSO_CALLBACK_URL=https://workspace.vaschools.edu.vn/auth/hrm/callback
HRM_API_TOKEN=<từ bước 1>
HRM_TIMESHEET_API_TOKEN=<cùng token bước 1>
# Không set HRM_LEAVE_API_BASE_URL / HRM_LEAVE_API_TOKEN
```

Sau khi sửa `.env`:

```bash
php artisan config:clear
php artisan identity:hrm-sso-diagnose
php artisan identity:hrm-leave-diagnose --employee-uuid=<uuid nhân sự thử>
php artisan identity:hrm-timesheet-diagnose --employee-uuid=<uuid nhân sự thử>
```

Kỳ vọng: leave + punches **HTTP 200** (punches có thể trả mảng rỗng nếu chưa chấm).

Deploy app: `bash deploy.sh` (repo va-workspace).

## 3. Roster / mirror DB (tuỳ chọn)

Nếu Workspace đọc MySQL HRM trực tiếp (`HRM_DB_*`), trỏ host/user production — **không** commit mật khẩu.  
Chẩn đoán: `php artisan identity:hrm-db-diagnose`.

## 4. Khớp `hrm_employee_uuid` (toàn bộ users)

SSOT = email nhân sự trên DB HRM (`HRM_DB_*`). Khi UUID workspace lệch (thường sau SSO/mirror cũ), chấm công và duyệt bảng công **422**.

Trên server Workspace:

```bash
php artisan identity:reconcile-hrm-employee-uuids --dry-run
php artisan identity:reconcile-hrm-employee-uuids
```

Sau đó (tuỳ chọn): `php artisan identity:hrm-mirror-employees --force` để cập nhật tên/avatar.

## 5. Kiểm tra nhanh từ trình duyệt

1. Đăng nhập SSO qua HRM → Workspace.
2. Mở chấm công — không còn 422 trên `/api/attendance/punches/today`.
3. Tab nghỉ phép — tải được loại nghỉ / số dư.
