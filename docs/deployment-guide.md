# Deploy

Máy chủ workspace ↔ HRM: [PRODUCTION-WORKSPACE-HRM.md](./PRODUCTION-WORKSPACE-HRM.md).

Tóm tắt: một host HRM `https://hrm.vaschools.edu.vn`. Production không dùng `HRM_LEAVE_API_*` trỏ portal. Token mint từ HRM (`integration:setup-va-workspace --mint`) rồi ghi vào `.env` workspace.
