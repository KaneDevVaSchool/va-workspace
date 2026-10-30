# Module Attendance — Đơn nghỉ phép (mẫu in)

Nhân viên tải PDF đơn đã duyệt từ danh sách đơn. Mẫu **BM.01/HN.QT.02**, hiệu lực 01/07/2025, revision 01. Mã phiếu `NP-YYMM-id`.

## Route

- `GET /api/attendance/leave-requests/{uuid}/form` — `EmployeeLeaveRequestController::form`, tên `leave-requests.form`.
- Chỉ đơn của chính người đăng nhập, trạng thái `approved`. Đơn khác trả 422 «Chỉ xuất được đơn đã được duyệt.» Không thấy đơn: 404.
- HRM không kết nối được: 503 «Không xuất được đơn nghỉ phép từ HRM.»

## Việc in

- `EmployeeLeaveFormService` dựng dữ liệu và render view `attendance::pdf.leave-form` (A4 dọc, DomPDF).
- View nạp trong `AttendanceServiceProvider` từ `Modules/Attendance/resources/views`.
- Logo: `Modules/Attendance/resources/images/va-form-logo.png`.
- Nút tải nằm trên `EmployeeRequests.vue`.
