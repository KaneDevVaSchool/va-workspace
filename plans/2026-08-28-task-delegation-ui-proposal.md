# Đề xuất: UI "Chuyển giao" hàng loạt cho Task (Task Delegation §6)

> **Trạng thái: ĐỀ XUẤT — chưa triển khai.** Ghi lại tham chiếu UI hệ cũ
> user gửi (2026-08-28) trong lúc làm PR7 (Filter/Sort/Bulk actions) của
> plan mở rộng Task. Không code gì cho tài liệu này.

## Bối cảnh

Trong lúc làm bulk actions ở PR7 (`plans/imperative-knitting-avalanche.md`
— chỉ gán `manager_id`/`weight` hàng loạt), user gửi ảnh UI hệ cũ: nút
"Chuyển giao" cạnh Tìm/Bộ lọc/Cỡ chữ/Cột trên thanh công cụ, bấm mở modal
"Chuyển giao N dự án" với 3 cột: **Vai trò chuyển giao** (Người quản trị /
Người thực hiện / Người theo dõi), **Người chuyển giao**, **Người tiếp
nhận** — có nút "+" thêm nhiều dòng vai trò cùng lúc.

User xác nhận: đây đúng là ý nghĩa **Task Delegation** (đổi chủ sở hữu vai
trò từ người này sang người khác), không phải chỉ gán quản lý/tỷ trọng
đơn thuần. Đây chính là module đã mô tả ở
`docs/VA_WORKSPACE_OVERVIEW.md` §6 (Cross-department Task Delegation) —
Phase 3, **chưa dựng**, cần Project/Task ổn định trước. Plan mở rộng Task
hiện tại (PR1–PR8) đã ghi rõ trong mục "KHÔNG LÀM": *"Initiative, Task
Delegation logic thật (4 cột delegation giữ nguyên 'chưa dùng')"*.

Quyết định: **không** mở rộng PR7 thành Task Delegation — giữ PR7 đúng
phạm vi đã duyệt (bulk gán `manager_id`/`weight`, đã code + verify xong),
lưu ý tưởng UI "Chuyển giao" này làm tham chiếu roadmap cho khi Phase 3
(Task Delegation) tới lượt làm thật.

## Tham chiếu UI hệ cũ (ảnh gửi kèm)

- Nút **"Chuyển giao"** đặt cạnh nhóm Tìm / Bộ lọc / Cỡ chữ / Cột trên
  thanh công cụ bảng danh sách (cùng hàng `TablePagesBar`).
- Bấm mở **modal Teleport** tiêu đề "Chuyển giao N [đối tượng]" (N = số
  dòng đã chọn).
- Modal có form nhiều dòng, mỗi dòng 3 cột ngang:
  1. **Vai trò chuyển giao** — select, ít nhất 3 giá trị thấy trong ảnh:
     "Người quản trị", "Người thực hiện", "Người theo dõi".
  2. **Người chuyển giao** — select người hiện đang giữ vai trò đó.
  3. **Người tiếp nhận** — search/select người nhận vai trò mới.
- Nút **"+"** ở góc dưới trái để thêm dòng vai trò khác (chuyển nhiều vai
  trò cùng lúc trong 1 lần submit).
- Nút hành động: "Huỷ bỏ" (ghost) / "Cập nhật" (primary, cam trong ảnh —
  lưu ý dự án dùng token `--color-primary` đỏ `#9a0036`, không phải cam,
  chỉ tham khảo bố cục không tham khảo màu từ ảnh hệ cũ).

## Vì sao đây là Task Delegation, không phải bulk gán field đơn thuần

- "Vai trò chuyển giao" ánh xạ tới khái niệm nhiều vai trò trên 1 Task
  (không chỉ `assignee`/`manager` — còn "Người theo dõi" là watcher, đã
  ghi là **Open Question chưa triển khai** trong plan mở rộng Task hiện
  tại, mục "Theo dõi/phối hợp thực hiện").
- "Người chuyển giao" → "Người tiếp nhận" là hành động **đổi chủ sở hữu**
  một vai trò cụ thể, có thể xuyên phòng ban — đúng mô tả §6: "task tạo ở
  phòng ban A có thể giao trực tiếp cho người ở phòng ban B, trạng thái
  đồng bộ hai chiều".
- Cần trạng thái `delegation_status` (pending/accepted/in_progress/done/
  rejected) — cột đã có sẵn trong migration `tasks` từ PR1 nhưng chưa có
  logic — và cần thông báo 2 chiều (nguồn ↔ đích), khác hẳn 1 lần PATCH
  im lặng của bulk `manager_id`/`weight`.

## Việc cần làm khi tới lượt (Phase 3, không phải bây giờ)

1. `EnterPlanMode` riêng cho Task Delegation — không lồng vào PR bulk
   actions hiện tại.
2. Xác nhận "vai trò chuyển giao" đầy đủ: watcher/theo dõi cần thiết kế
   trước (many-to-many) — đang là Open Question chưa có schema.
3. Thiết kế modal theo skill `form-modal` của dự án (lưới ngang 2–3 cột,
   trong viewport) — đúng UI hệ cũ đã tham khảo nhưng theo đúng theme
   `--color-primary` của VA Workspace, không copy màu cam.
4. Logic đồng bộ trạng thái 2 chiều + `NotificationService` báo watcher ở
   phòng ban nguồn khi `delegation_status` đổi (đã mô tả sẵn ở §6.2).
5. Đối chiếu với đề xuất Notification/Reminder Engine
   (`plans/2026-08-28-notification-reminder-engine-proposal.md`) — 2 việc
   này có khả năng cần làm cùng lúc vì Task Delegation phụ thuộc nặng vào
   thông báo 2 chiều.

## Không làm cho tới khi có quyết định rõ

- Không thêm nút "Chuyển giao" hay modal delegation vào `TaskList.vue`
  cho tới khi Phase 3 được lên kế hoạch riêng.
- Không động vào 4 cột `origin_department_id`/`delegated_to_department_id`/
  `delegated_to_employee_id`/`delegation_status` đã chừa sẵn trong `tasks`.
- Bulk actions PR7 hiện tại (gán `manager_id`/`weight`) giữ nguyên như đã
  code — không thay bằng "Chuyển giao" cho tới khi Task Delegation thật
  được thiết kế xong.
