# Kiến trúc

Bản trạng thái dài: [VA_WORKSPACE_OVERVIEW.md](../VA_WORKSPACE_OVERVIEW.md).

| Việc | Đọc |
| --- | --- |
| Module đã có tài liệu riêng | [modules/](./modules/README.md) |
| Route, quyền, module còn lại | overview §2, §10 |
| Token giao diện | [theme.md](../theme.md) |
| Chấm công gọi HRM | [PRODUCTION-WORKSPACE-HRM.md](../PRODUCTION-WORKSPACE-HRM.md) |

Workspace là app nhân viên (vệ tinh). SSOT nhân sự / chấm công / nghỉ phép: **VA-HRM** — contract `va-hrm/docs/api-contracts/index.md`, webhook `va-hrm/docs/architecture/modules/integration.md`. Workspace: Sanctum M2M + SSO; `employee uuid` từ `User.hrm_employee_uuid`, không từ trình duyệt. Deploy: [PRODUCTION-WORKSPACE-HRM.md](../PRODUCTION-WORKSPACE-HRM.md).
