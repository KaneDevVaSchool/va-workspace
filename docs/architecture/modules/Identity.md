# Module Identity — đăng nhập & tích hợp HRM

Google SSO, HRM SSO (JWKS), webhook HMAC, RBAC, nhật ký, thông báo. Tích hợp VA-HRM nằm tại `Modules/Identity/App/Hrm/`.

## API client (`HrmApiClient`)

Token Sanctum từ `.env` (`HRM_API_TOKEN`, tuỳ chọn `HRM_TIMESHEET_API_TOKEN`). Mint sau khi cấp ability mới trên HRM (ví dụ `employees:profile-change`) — token cũ không tự có scope mới.

- `resolveLeaveCatalogEmployeeUuid(User)`: nếu `hrm_employee_uuid` đã lưu và HRM biết employee đó → dùng; không thì lookup theo email workspace (khớp `company_email` HRM). Mọi list/tạo đơn nghỉ Attendance đi qua hàm này.
- Profile change: `submitProfileChangeRequest` → HRM queue HCNS.

## Mirror nhân sự DB (`HrmEmployeeDatabaseMirrorService`)

Đọc roster qua `HrmEmployeeDirectory` (connection `hrm`), cập nhật `users` (tên, avatar, phòng, `hrm_employee_uuid`). Artisan: `identity:hrm-mirror-employees`.

`mirrorOne`: khi đúng **một** hồ sơ HRM khớp email và uuid khác uuid đang lưu, service ghi đè uuid (log `hrm.employee_db_mirror.uuid_reconciled`). Khi **nhiều** dòng `employees` cùng `company_email`, service **giữ** uuid đã lưu và bỏ qua bản ghi kia (log `hrm.employee_db_mirror.uuid_kept_duplicate_email`) — không cập nhật tên/mã từ hồ sơ trùng. Dữ liệu trùng email trên HRM vẫn cần dọn; khớp tay: [PRODUCTION-WORKSPACE-HRM.md](../../PRODUCTION-WORKSPACE-HRM.md) §4.

Khớp uuid thủ công production: [PRODUCTION-WORKSPACE-HRM.md](../../PRODUCTION-WORKSPACE-HRM.md) §4.

## Thư mục chính

| Thành phần | Vai trò |
| --- | --- |
| `HrmSsoService` / `HrmJwtVerifier` | SSO session |
| `HrmWebhookController` | Webhook nhân sự, chấm công |
| `HrmEmployeeSyncService`, bulk/department sync | HTTP sync |
| `HrmEmployeeDirectory` | Đọc MySQL HRM (không qua HTTP) cho roster |

Trang quản trị: ma trận quyền `/superadmin/permissions`, nhật ký `ActivityLog.vue`, v.v. — overview §2.
