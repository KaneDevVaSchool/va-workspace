# docs/

Tài liệu kỹ thuật của dự án: kiến trúc, quy ước, hướng dẫn setup,
quyết định thiết kế, nợ kỹ thuật.

## Đọc trước

| File | Dùng khi |
|---|---|
| [`VA_WORKSPACE_OVERVIEW.md`](VA_WORKSPACE_OVERVIEW.md) | **Nguồn sự thật** — vision + trạng thái code + việc làm tiếp (§21) |
| [`known-issues.md`](known-issues.md) | Nợ kỹ thuật (Laravel 10 hết hạn vá, Vite advisory) |
| [`theme.md`](theme.md) | Token CSS, quy tắc border |
| `.claude/CLAUDE.md` (root repo) | Quy tắc bắt buộc khi viết code |

## Cấu trúc gợi ý khi thêm doc

- `docs/modules/{TenModule}.md` — tài liệu riêng từng module khi module được dựng (`EVALUATION.md` trước, rồi `INITIATIVE.md`, `PROJECT.md`…)
- `docs/architecture.md` / `docs/routing.md` — tách ra khi overview §3 / §10 quá dài

Không tạo module `Auth` / `Department` / `SystemConfig` / `Audit` riêng: đã nằm trong `Identity` (xem overview §2).
