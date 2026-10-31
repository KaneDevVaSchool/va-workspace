# Deploy

Máy chủ workspace ↔ HRM: [PRODUCTION-WORKSPACE-HRM.md](./PRODUCTION-WORKSPACE-HRM.md) (đối chiếu va-hrm [deployment-guide.md](../va-hrm/docs/deployment-guide.md) §4.1).

Tóm tắt: HRM SSOT tại `https://hrm.vaschools.edu.vn`; Workspace không dùng `HRM_LEAVE_API_*` trỏ portal. Mint token trên HRM (`integration:setup-va-workspace --mint`), abilities theo va-hrm `docs/api-contracts/index.md`.

Upload Social lên S3: bucket production và policy mẫu [s3-bucket-policy.json](./s3-bucket-policy.json) (prefix `social/` public-read theo thiết kế uploader).
