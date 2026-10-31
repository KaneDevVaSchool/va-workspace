# Đối soát 15/10/2026

Đọc nhanh: [README.md](./README.md) · Tuần: [2026-W42](../summary/2026-W42-summary.md) · Bảng tuần: [2026-W42-audit](../summary/2026-W42-audit.md)

Việc trong ngày đánh số **1.1**, **1.2**, … Mỗi lần lưu (commit) là **1.1.1**, **1.1.2**, … Bảng có thêm dòng **Giải thích đơn giản** cho người không rành kỹ thuật. Thiếu trong commit thì ghi «chưa ghi», không suy diễn.

## Việc trong ngày

### 1.1. Thêm duyệt bảng chấm công HRM cho Trưởng đơn vị

Chấm công · Cấu hình menu · 2 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Thêm duyệt bảng chấm công HRM cho Trưởng đơn vị. Proxy API attendance/timesheet-approvals, trang SPA, webhook thông báo/chuông, badge sidebar và token HRM_TIMESHEET_API_TOKEN tùy chọn. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Gom 2 lần lưu cùng một việc; xem bảng từng version bên dưới. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 08:21 | `a9016e5` | Cập nhật | .env.example, Attendance/EmployeeTimesheetApprovalController.php, Attendance/ProcessTimesheetStatusChangedJob.php, Attendance/EmployeeTimesheetApprovalService.php (+15) |
| 08:27 | `c28c2c6` | Sửa tiếp | Attendance/EmployeeMobileLayout.vue, css/employee-mobile-shell.css |

#### Chi tiết từng lần lưu (version)

##### Version 1.1.1 — mã `a9016e5` · 08:21

| | |
| --- | --- |
| **Đổi gì** | Thêm duyệt bảng chấm công HRM cho Trưởng đơn vị. Proxy API attendance/timesheet-approvals, trang SPA, webhook thông báo/chuông, badge sidebar và token HRM_TIMESHEET_API_TOKEN tùy chọn. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Thêm duyệt bảng chấm công HRM cho Trưởng đơn vị. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: .env.example, Attendance/EmployeeTimesheetApprovalController.php, Attendance/ProcessTimesheetStatusChangedJob.php, Attendance/EmployeeTimesheetApprovalService.php (+15). |

##### Version 1.1.2 — mã `c28c2c6` · 08:27

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Chấm công. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeMobileLayout.vue, css/employee-mobile-shell.css. |

