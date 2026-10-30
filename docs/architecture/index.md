# Kiến trúc

Bản trạng thái dài: [VA_WORKSPACE_OVERVIEW.md](../VA_WORKSPACE_OVERVIEW.md).

| Việc | Đọc |
| --- | --- |
| Module đã có tài liệu riêng | [modules/](./modules/README.md) |
| Route, quyền, module còn lại | overview §2, §10 |
| Token giao diện | [theme.md](../theme.md) |
| Chấm công gọi HRM | [PRODUCTION-WORKSPACE-HRM.md](../PRODUCTION-WORKSPACE-HRM.md) |

Workspace là app nhân viên. Hồ sơ nhân sự, bảng công, cộng phép nằm ở VA-HRM. Workspace gọi HRM bằng Sanctum (`employee uuid` lấy từ `User.hrm_employee_uuid`, không lấy từ trình duyệt).
