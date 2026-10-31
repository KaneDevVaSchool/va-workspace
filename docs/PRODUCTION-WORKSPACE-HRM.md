# Production — VA Workspace ↔ VA-HRM

> **VA-HRM là SSOT** nhân sự, tổ chức, chấm công, nghỉ phép, duyệt bảng công.
> Workspace **không lưu trùng** hồ sơ — tham chiếu `users.hrm_employee_uuid`, gọi
> Public API v1 + SSO JWT + webhook HMAC. Đối chiếu HRM trước khi sửa tích hợp:
> repo **va-hrm** → `docs/index.md`, `docs/api-contracts/index.md`,
> `docs/architecture/modules/integration.md`, `docs/deployment-guide.md` §4.1.

Cập nhật: 2026-10-31.

## 0. Ba vhost production (cùng máy)

| Vhost | Thư mục deploy (site user) | Vai trò |
| --- | --- | --- |
| `https://hrm.vaschools.edu.vn` | `/home/vaschools-hrm/htdocs/hrm.vaschools.edu.vn` (`vaschools-hrm`) | **HRM chính** — SSOT, Public API v1, mint token |
| `https://portal.hrm.vaschools.edu.vn` | `/home/vaschools-portal-hrm/htdocs/portal.hrm.vaschools.edu.vn` (`vaschools-portal-hrm`) | Cùng codebase/DB HRM (gateway); **không** dùng làm `HRM_API_BASE_URL` cho Workspace |
| `https://workspace.vaschools.edu.vn` | `/home/vaschools-workspace/htdocs/workspace.vaschools.edu.vn` (`vaschools-workspace`) | Vệ tinh va-workspace |

Portal gateway có thể tạm ngưng trên HRM-only (xem `va-hrm/docs/deployment-guide.md` §1) — Workspace **luôn** trỏ API/SSO về host **hrm.vaschools.edu.vn**, không set `HRM_LEAVE_API_*` trỏ portal.

## 1. Trên server HRM

```bash
cd /home/vaschools-hrm/htdocs/hrm.vaschools.edu.vn
CI=true HUSKY=0 NODE_ENV=production DEPLOY_SITE_USER=vaschools-hrm \
  bash ./scripts/deploy-production.sh
systemctl restart HRIS-queue
```

Sau deploy có đổi ability / ApiClient:

```bash
sudo -u vaschools-hrm php artisan db:seed --class=ApiClientSeeder --force
sudo -u vaschools-hrm php artisan integration:setup-va-workspace --mint
```

Lệnh `integration:setup-va-workspace` (va-hrm) gộp abilities tối thiểu cho client `va-workspace`:

`employees:read`, `org:read`, `sso:verify`, `leave:read`, `leave:write`,
`attendance:approve`, `attendance:punch`, `employees:profile-change`

và bổ sung webhook `attendance.timesheet.status_changed` nếu thiếu.

`--mint` in **một lần** (copy ngay, không commit):

- `HRM_API_TOKEN`
- `HRM_TIMESHEET_API_TOKEN` — có thể **cùng** chuỗi token; Workspace dùng cho chấm công / duyệt bảng công

Token Sanctum **cố định ability lúc mint** — sau khi HRM thêm ability mới (vd. `employees:profile-change`), phải `--mint` lại và thay cả hai biến trên Workspace. Token cũ: thu hồi tại HRM `/admin/api-clients` → client **va-workspace**.

`.env` HRM (tóm tắt):

```env
APP_URL=https://hrm.vaschools.edu.vn
APP_TIMEZONE=Asia/Ho_Chi_Minh
HRM_URL=https://hrm.vaschools.edu.vn
```

Chi tiết deploy HRM: `va-hrm/docs/deployment-guide.md`.

## 2. Trên server Workspace

`.env` production:

```env
APP_URL=https://workspace.vaschools.edu.vn
APP_TIMEZONE=Asia/Ho_Chi_Minh
HRM_API_BASE_URL=https://hrm.vaschools.edu.vn
HRM_SSO_ISSUER=https://hrm.vaschools.edu.vn
HRM_SSO_CALLBACK_URL=https://workspace.vaschools.edu.vn/auth/hrm/callback
HRM_API_TOKEN=<từ bước mint HRM>
HRM_TIMESHEET_API_TOKEN=<cùng token mint>
# Không set HRM_LEAVE_API_BASE_URL / HRM_LEAVE_API_TOKEN
```

Tuỳ chọn đọc roster / PDF đơn / mirror trực tiếp MySQL HRM (`HRM_DB_*`) — **không** commit mật khẩu.

Deploy code (user `vaschools-workspace`; script **không** migrate):

```bash
cd /home/vaschools-workspace/htdocs/workspace.vaschools.edu.vn
sudo -u vaschools-workspace bash deploy.sh
# Migration (khi có): sudo -u vaschools-workspace php artisan migrate --force
```

Sau khi sửa `.env` hoặc deploy:

```bash
sudo -u vaschools-workspace php artisan config:clear
sudo -u vaschools-workspace php artisan config:cache
sudo -u vaschools-workspace php artisan identity:hrm-sso-diagnose
sudo -u vaschools-workspace php artisan identity:hrm-leave-diagnose --employee-uuid=<uuid thử>
sudo -u vaschools-workspace php artisan identity:hrm-timesheet-diagnose --employee-uuid=<uuid thử>
```

Kỳ vọng: leave + punches **HTTP 200** (punches có thể rỗng nếu chưa chấm).

Contract endpoint Workspace hay gọi: `va-hrm/docs/api-contracts/index.md` (§12.2).

## 3. Mirror DB HRM (tuỳ chọn)

`php artisan identity:hrm-db-diagnose` — connection `hrm` tới DB `va_hrm`.

`php artisan identity:hrm-mirror-employees --force` cập nhật tên/avatar/`hrm_employee_uuid`.
Trùng `company_email` nhiều employee HRM có thể làm uuid nhảy — xem
`docs/known-issues.md` và `docs/architecture/modules/Identity.md`.

## 4. Khớp `hrm_employee_uuid`

SSOT uuid = bản ghi `employees` trên HRM (email `company_email`). Lệch uuid → chấm công / list đơn nghỉ / duyệt bảng công **422** hoặc danh sách rỗng.

```bash
sudo -u vaschools-workspace php artisan identity:reconcile-hrm-employee-uuids --dry-run
sudo -u vaschools-workspace php artisan identity:reconcile-hrm-employee-uuids
```

## 5. Kiểm tra nhanh

1. SSO HRM → Workspace (`/auth/hrm/...`).
2. PWA chấm công — không 422 trên `/api/attendance/punches/today`.
3. Nghỉ phép — loại nghỉ / số dư; lịch sử `/dashboard/me/requests?view=leave`.
4. PDF đơn BM.01 — chỉ đơn **`approved`** (`docs/architecture/modules/Attendance.md`).
5. Yêu cầu sửa hồ sơ — token có `employees:profile-change`; HCNS duyệt trên HRM `/employees/profile-changes`.

Checklist duyệt bảng công (HRM + Workspace): `va-hrm/docs/qa/checklists/timesheet-approval.md`.
