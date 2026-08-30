# plans/

Kế hoạch triển khai (implementation plans) cho từng tính năng/module,
viết **trước khi code**. Đặt tên file theo dạng `YYYY-MM-DD-ten-tinh-nang.md`.

Mỗi plan nên có:
- Bối cảnh / mục tiêu
- Phạm vi (trong/ngoài phạm vi)
- Các bước triển khai (backend, frontend, migration, route)
- Rủi ro / điểm cần xác nhận với người yêu cầu

## Quan hệ với `docs/`

`plans/` và `docs/` phản ánh 2 giai đoạn khác nhau của cùng 1 tính năng —
không trùng nội dung, không thay thế nhau:

| | `plans/` | `docs/` |
|---|---|---|
| Thời điểm | **Trước khi code** — đề xuất/kế hoạch | **Sau khi code chạy được** — phản ánh thực tế |
| Trạng thái | Có thể là draft/proposal, có thể bị đổi hướng hoặc bỏ khi triển khai thực tế khác đi | Nguồn sự thật hiện tại — nếu sai lệch với code là bug tài liệu cần sửa ngay |
| Vòng đời | Giữ nguyên làm lịch sử quyết định, **không xoá** dù đã triển khai xong | Cập nhật liên tục theo code |

**Khi 1 plan hoàn tất triển khai:**

1. Cập nhật `docs/VA_WORKSPACE_OVERVIEW.md` (mục lộ trình §19 + mục nghiệp vụ
   liên quan) và `docs/modules/{TenModule}.md` (nếu module đủ lớn để có doc
   riêng) phản ánh đúng trạng thái code mới.
2. **Không xoá** file plan gốc — thêm 1 dòng trạng thái ở đầu file, dạng:
   `> **Trạng thái: ĐÃ TRIỂN KHAI (YYYY-MM-DD)** — xem docs/VA_WORKSPACE_OVERVIEW.md §...`
   Nếu chỉ triển khai một phần, ghi rõ phần nào xong/phần nào chưa thay vì
   đánh dấu toàn bộ "đã xong".
3. Nếu triển khai thực tế lệch hướng so với đề xuất ban đầu trong plan (ví
   dụ đơn giản hoá phạm vi), ghi chú lệch đó ngay trong dòng trạng thái —
   không sửa lại nội dung gốc của plan để "khớp" với code sau này.

Nói cách khác: `plans/` trả lời "định làm gì", `docs/` trả lời "hệ thống
đang làm gì thật". Khi cả 2 có vẻ mâu thuẫn, `docs/` luôn thắng.
