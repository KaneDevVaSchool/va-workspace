# Module Attendance — Đơn nghỉ phép (mẫu in)

Nhân viên tải PDF đơn đã duyệt từ danh sách đơn. Mẫu **BM.01/HN.QT.02**, hiệu lực 01/07/2025, revision 01. Mã phiếu `NP-YYMM-id`.

## Route

- `GET /api/attendance/leave-requests/{uuid}/form` — `EmployeeLeaveRequestController::form`, tên `leave-requests.form`.
- Chỉ đơn của chính người đăng nhập, trạng thái `approved`. Đơn khác trả 422 «Chỉ xuất được đơn đã được duyệt.» Không thấy đơn: 404.
- HRM không kết nối được: 503 «Không xuất được đơn nghỉ phép từ HRM.»

## Việc in

- `EmployeeLeaveFormService` dựng dữ liệu và render view `attendance::pdf.leave-form` (A4 dọc, DomPDF).
- View nạp trong `AttendanceServiceProvider` từ `Modules/Attendance/resources/views`.
- Logo: `Modules/Attendance/resources/images/va-form-logo.png`. Watermark giữa trang, nhỏ hơn họa tiết login (`va-form-watermark.png`, nguồn `public/images/background/background-logo.png`).
- Đầu đơn: bên trái «HỆ THỐNG TRƯỜNG VIỆT MỸ», đơn vị, số phiếu; bên phải quốc hiệu giãn chữ «CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM» và «Độc lập - Tự do - Hạnh phúc».
- Mỗi trường trên một hàng (họ tên, mã, chức danh, phòng, ngày, chi tiết, buổi, số ngày, loại nghỉ, lý do) có đường chấm riêng.
- Chỗ ký (người làm đơn, trưởng đơn vị, Phòng HCNS, Ban TGĐ) chừa khoảng trống để ký tay. HCNS và TGĐ sang trang 2.
- Nút tải nằm trên trang **Lịch sử** (`/dashboard/me/requests`, `EmployeeRequests.vue`). Trang **Nghỉ phép** (`/dashboard/me/leave`) là số dư, không phải chỗ tải đơn.
