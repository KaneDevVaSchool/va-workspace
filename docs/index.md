# VA Workspace — Tài liệu

Mọi việc code bắt đầu bằng một file ở đây, không bắt đầu bằng trí nhớ.

1. Chưa rõ hệ thống → [project-overview.md](./project-overview.md), rồi bản dài [VA_WORKSPACE_OVERVIEW.md](./VA_WORKSPACE_OVERVIEW.md).
2. Sửa một module đã có file → [architecture/modules/](./architecture/modules/README.md). Đọc class hoặc route sắp đụng, đối chiếu, rồi mới sửa.
3. Sửa xong → ghi ngược vào cùng file. Bẫy thì ghi [known-issues.md](./known-issues.md).
4. Sửa khung chạy → [architecture/index.md](./architecture/index.md).

## Cấu trúc thư mục (BMAD)

| Vị trí | Nội dung |
| --- | --- |
| `docs/` (gốc) | Cửa vào: `index.md`, `project-overview.md`, `VA_WORKSPACE_OVERVIEW.md`, `theme.md`, `known-issues.md`, `development-guide.md`, `deployment-guide.md`, `PRODUCTION-WORKSPACE-HRM.md` |
| `docs/architecture/` | [index.md](./architecture/index.md) — runtime chung; [modules/](./architecture/modules/README.md) — tài liệu từng module đã rà soát |
| `docs/modules/` | **Lối cũ** — mỗi file một dòng trỏ sang `architecture/modules/` (giữ link bookmark cũ) |
| `docs/s3-bucket-policy.json` | Mẫu policy S3 cho upload Social (tham chiếu từ `.env.example`, `SocialAttachmentUploader`) — không phải prose |
| `plans/` (gốc repo) | Kế hoạch **trước khi code** — [../plans/README.md](../plans/README.md). Không thay `docs/` |
| `release/` (gốc repo) | Phiếu release theo ngày — [../release/README.md](../release/README.md) |

File ở gốc `docs/` giữ nguyên tên vì code và overview đang trỏ: `VA_WORKSPACE_OVERVIEW.md`, `known-issues.md`, `theme.md`, `PRODUCTION-WORKSPACE-HRM.md`.

## Tra cứu nhanh

| Ngăn | File | Đọc khi |
| --- | --- | --- |
| Tổng quan | [project-overview.md](./project-overview.md), [VA_WORKSPACE_OVERVIEW.md](./VA_WORKSPACE_OVERVIEW.md) | Chưa biết hệ thống, trạng thái code |
| Kiến trúc | [architecture/index.md](./architecture/index.md) | Runtime, route |
| Module | [architecture/modules/](./architecture/modules/README.md) | Sửa Attendance, Dashboard, DonTu, Evaluation, Identity, Report, Social |
| Giao diện | [theme.md](./theme.md) | Token, viền |
| Bẫy | [known-issues.md](./known-issues.md) | Trước khi fix bug |
| Máy mới | [development-guide.md](./development-guide.md) | Cài repo |
| Deploy | [deployment-guide.md](./deployment-guide.md), [PRODUCTION-WORKSPACE-HRM.md](./PRODUCTION-WORKSPACE-HRM.md) | Lên máy chủ; đối chiếu va-hrm `docs/deployment-guide.md` §4.1 + `docs/api-contracts/index.md` |
