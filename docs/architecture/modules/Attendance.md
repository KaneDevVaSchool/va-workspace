# Module Attendance — PWA nhân viên & nghỉ phép

Chấm công, nghỉ phép, lịch sử đơn, duyệt bảng công (manager). Dữ liệu nghỉ/bảng công nằm trên VA-HRM; Workspace gọi API Sanctum và (tuỳ chọn) đọc DB `hrm`.

## Trang Vue (nhân viên)

| Route | File | `meta.title` |
| --- | --- | --- |
| `/dashboard/me/leave` | `EmployeeLeave.vue` | Nghỉ phép — số dư, tạo đơn |
| `/dashboard/me/requests` | `EmployeeRequests.vue` | Lịch sử — hub tiện ích |
| `/dashboard/me/requests?view=leave` | cùng file | Danh sách đơn nghỉ (chip lọc trạng thái) |
| `/dashboard/me/requests?view=attendance` | cùng file | Lịch sử chấm công local |
| `/dashboard/me/profile` | `EmployeeProfile.vue` | Hồ sơ (module Dashboard doc) |
| `/attendance/timesheet-approvals` | manager | Duyệt bảng công HRM |

Hub **Lịch sử** (`/dashboard/me/requests` không có query) chỉ hiện 2 ô; API `GET /api/attendance/leave-requests?limit=50` vẫn chạy để đếm trên ô «Lịch sử nghỉ phép». Danh sách chi tiết và nút xuất PDF chỉ khi `?view=leave`.

## API đơn nghỉ

- `GET /api/attendance/leave-requests` — `EmployeeLeaveRequestController::index` → `EmployeeLeaveSubmissionService::recentForUser` → `HrmApiClient::listLeaveRequests` (uuid từ `resolveLeaveCatalogEmployeeUuid`).
- `POST /api/attendance/leave-requests` — tạo đơn trên HRM.
- `GET /api/attendance/leave-requests/{uuid}/form` — PDF BM.01 (`EmployeeLeaveFormService::downloadForUser`).

Uuid nhân viên: ưu tiên `users.hrm_employee_uuid` nếu HRM catalog biết uuid đó, không thì tra theo email (`company_email`). Chi tiết mirror: [Identity.md](./Identity.md).

## PDF đơn đã duyệt

Mẫu **BM.01/HN.QT.02**, hiệu lực 01/07/2025, revision 01. Mã phiếu `NP-YYMM-id`.

- Chỉ đơn **của chính người đăng nhập**, trạng thái **`approved`**. Khác trạng thái: 422 «Chỉ xuất được đơn đã được duyệt.» Không thấy đơn: 404.
- HRM không kết nối: 503 «Không xuất được đơn nghỉ phép từ HRM.»
- `EmployeeLeaveFormService` render `attendance::pdf.leave-form` (DomPDF A4). Logo/watermark: `Modules/Attendance/resources/images/va-form-*.png`.
- UI (`EmployeeRequests.vue`, `/dashboard/me/requests?view=leave`, `meta.title` Lịch sử): nút «Xuất đơn nghỉ phép» luôn hiện; chỉ bấm được khi `approved`. Đơn chờ / từ chối / huỷ thì nút disabled và dòng chữ nghiêng giải thích (chỉ xuất PDF khi đã duyệt). **Không** đặt trên trang Nghỉ phép (`/dashboard/me/leave`).

## Việc in (layout)

- View nạp trong `AttendanceServiceProvider` từ `Modules/Attendance/resources/views`.
- Đầu đơn: trái «HỆ THỐNG TRƯỜNG VIỆT MỸ» + đơn vị + số phiếu; phải quốc hiệu.
- HCNS và TGĐ ký ở trang 2; chỗ ký chừa trống ký tay.
