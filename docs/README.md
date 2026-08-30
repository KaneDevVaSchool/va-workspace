# docs/

Tài liệu kỹ thuật của dự án: kiến trúc, quy ước, hướng dẫn setup,
quyết định thiết kế, nợ kỹ thuật.

**`docs/` là nguồn sự thật cho code đã chạy** — khác với `plans/` (đề xuất/kế
hoạch viết trước khi code, có thể lệch hướng khi triển khai thực tế). Khi 1
plan triển khai xong, cập nhật doc tương ứng ở đây; chi tiết quy ước 2 chiều
xem `plans/README.md`.

## Đọc trước

| File | Dùng khi |
|---|---|
| [`VA_WORKSPACE_OVERVIEW.md`](VA_WORKSPACE_OVERVIEW.md) | **Nguồn sự thật** — vision + trạng thái code + việc làm tiếp (§21) |
| [`known-issues.md`](known-issues.md) | Nợ kỹ thuật (Laravel 10 hết hạn vá, Vite advisory) |
| [`theme.md`](theme.md) | Token CSS, quy tắc border |
| `.claude/CLAUDE.md` (root repo) | Quy tắc bắt buộc khi viết code |

## Doc theo module

- [`modules/Social.md`](modules/Social.md) — bảng tin nội bộ (đã chạy, ngoài lộ trình gốc)
- [`modules/Evaluation.md`](modules/Evaluation.md) — tiêu chí đánh giá (Giai đoạn B) + mẫu đánh giá (Giai đoạn C), đã chạy

## Cấu trúc gợi ý khi thêm doc

- `docs/modules/{TenModule}.md` — tài liệu riêng từng module khi module được dựng (`Social.md`, `Evaluation.md` đã có, tiếp theo `INITIATIVE.md`, `PROJECT.md`…)
- `docs/architecture.md` / `docs/routing.md` — tách ra khi overview §3 / §10 quá dài

Không tạo module `Auth` / `Department` / `SystemConfig` / `Audit` riêng: đã nằm trong `Identity` (xem overview §2).
