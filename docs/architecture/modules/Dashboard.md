# Module Dashboard — hồ sơ của tôi

Trang hồ sơ nhân viên: `Modules/Attendance/resources/js/pages/EmployeeProfile.vue`, route `/dashboard/me/profile`, `meta.title` Hồ sơ cá nhân.

## Yêu cầu sửa hồ sơ

Form gửi họ tên, điện thoại, email, ngày sinh, nơi làm việc, mã chấm công, chức danh, phòng, bộ phận, ngày vào làm và các trường còn lại trong `MyDashboardProfileService::EDITABLE_FIELDS`.

Bản ghi nằm ở `dashboard_profile_change_requests`, trạng thái `pending`. Workspace không ghi đè hồ sơ HRM. `HrmApiClient::submitProfileChangeRequest` đẩy sang `POST /api/v1/employees/profile-change-requests` khi đã cấu hình token HRM. Lần mở hồ sơ sau kéo trạng thái `approved` / `rejected`.

HCNS duyệt trên HRM, màn «Yêu cầu sửa hồ sơ» (`/employees/profile-changes`).
