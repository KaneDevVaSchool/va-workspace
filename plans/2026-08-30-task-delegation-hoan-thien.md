# Hoàn thiện Task Delegation (Phase 3 §6) — phần còn lại

> Kế hoạch tiếp theo cho `plans/2026-08-28-task-delegation-ui-proposal.md`
> sau khi bulk delegate 1 người nhận đã chạy (2026-08-30, xem
> `docs/VA_WORKSPACE_OVERVIEW.md` §6.2). Viết trước khi code — checklist PR
> nhỏ, chưa triển khai.

## Bối cảnh

`TaskService::bulkDelegate()` hiện chỉ làm 1 việc: chọn nhiều task, chuyển
`assignee_id` cho 1 người nhận, set `delegation_status='pending'` và bắn 1
thông báo. Đây là bản rút gọn tối thiểu để có delegation hoạt động được —
còn thiếu 3 phần theo đúng mô tả gốc ở §6 overview và UI hệ cũ user tham
khảo (`plans/2026-08-28-task-delegation-ui-proposal.md`):

1. Người nhận không có cách phản hồi (accept/reject) — `delegation_status`
   đứng yên ở `pending` vĩnh viễn.
2. Không có đa vai trò (chỉ đổi `assignee_id`, không có "Người quản trị" /
   "Người theo dõi" tách riêng).
3. Không thông báo watcher phòng ban nguồn khi trạng thái đổi (vì trạng
   thái chưa từng đổi).

Rà soát code (2026-08-30) cũng phát hiện 2 vấn đề cần xử lý cùng đợt:
- Vòng lặp `bulkDelegate()`/`bulkUpdate()` không bọc `DB::transaction()` —
  xem `docs/known-issues.md`.
- `BulkDelegateTaskRequest` không giới hạn người nhận theo phòng ban liên
  quan — bất kỳ ai có `task.delegate` chọn được bất kỳ user nào toàn hệ
  thống.

## Phạm vi

**Trong phạm vi:**
- Luồng người nhận accept/reject task được chuyển giao.
- Thông báo 2 chiều: người nhận (đã có) + watcher/người tạo ở phòng ban
  nguồn khi `delegation_status` đổi.
- Bọc transaction cho `bulkDelegate()`/`bulkUpdate()`.
- Đánh giá lại phạm vi người nhận hợp lệ (giới hạn theo phòng ban liên quan
  thay vì toàn hệ thống, hoặc giữ nguyên có chủ đích — cần quyết định rõ,
  xem mục Rủi ro).

**Ngoài phạm vi (đợt sau hoặc chưa quyết định):**
- Đa vai trò đầy đủ theo UI hệ cũ (Người quản trị / Người thực hiện / Người
  theo dõi, watcher many-to-many) — cần thiết kế schema riêng trước (bảng
  `task_watchers` hoặc tương tự), không làm trong PR này.
- Tích hợp sâu với Notification/Reminder Engine
  (`plans/2026-08-28-notification-reminder-engine-proposal.md`) — chỉ dùng
  `NotificationService` hiện có, không mở rộng engine.

## Các bước triển khai

### PR1 — Transaction cho bulk actions (nhỏ, làm trước, không phụ thuộc gì)

- `TaskService::bulkUpdate()` và `bulkDelegate()`: bọc toàn bộ vòng lặp
  trong `DB::transaction()`. Nếu 1 task lỗi giữa chừng, rollback toàn bộ,
  trả lỗi rõ ràng cho FE thay vì kết quả nửa vời.
- Test: thêm case giả lập lỗi giữa vòng lặp (mock Repository throw ở task
  thứ N) xác nhận rollback đúng.

### PR2 — Luồng accept/reject của người nhận

- Route mới `PATCH /api/project/tasks/{task}/delegation-response`
  (`permission` — chỉ chính người `delegated_to_employee_id` mới gọi được,
  kiểm tra ownership trong Service, không dùng permission matrix chung).
- `TaskService::respondToDelegation(Task $task, string $action, User $user)`
  — `$action ∈ {accept, reject}`; `accept` → `delegation_status='accepted'`;
  `reject` → `delegation_status='rejected'` (cân nhắc: có revert
  `assignee_id` về người cũ không? — cần xác nhận, xem Rủi ro).
- Khi người nhận cập nhật tiến độ/trạng thái task sau khi accept, cân nhắc
  tự chuyển `delegation_status` sang `in_progress`/`done` theo `status` của
  task (đối chiếu §6.2 đề xuất gốc "cập nhật như task thường") — hoặc giữ
  2 trường độc lập, cần quyết định rõ trước khi code (xem Rủi ro).
- FE: nút "Chấp nhận"/"Từ chối" trong panel chi tiết Task khi
  `delegation_status='pending'` và user hiện tại = người nhận.

### PR3 — Thông báo watcher phòng ban nguồn

- `TaskService::respondToDelegation()` sau khi đổi `delegation_status` →
  gọi `NotificationService` bắn thông báo cho **người tạo task**
  (`created_by`) hoặc **trưởng phòng ban nguồn** (`origin_department_id`) —
  cần xác nhận đối tượng nhận chính xác là ai (xem Rủi ro — "watcher" chưa
  có bảng riêng, hiện chưa có khái niệm watcher thật).
- Thêm `NotificationService::TYPE_TASK_DELEGATION_RESPONDED` (hoặc tái dùng
  `TYPE_TASK_DELEGATED` với payload khác — quyết định khi code).

### PR4 — Siết phạm vi người nhận hợp lệ

- Đánh giá lại `ProjectService::assignableUsers(unrestricted: true)` +
  `BulkDelegateTaskRequest` — quyết định: giữ nguyên "toàn hệ thống" (nếu
  đây là chủ đích — delegation xuyên phòng ban vốn cần chọn người ở PB
  khác) hay giới hạn theo 1 danh sách phòng ban được phép nhận (whitelist
  cấu hình). Không tự quyết — hỏi người yêu cầu trước khi code PR này.

## Rủi ro / điểm cần xác nhận trước khi code

| # | Câu hỏi | Ảnh hưởng |
|---|---|---|
| 1 | `reject` có tự động revert `assignee_id` về người cũ (hoặc về `null`) không, hay chỉ đổi `delegation_status='rejected'` và để người giao tự xử lý tiếp? | Ảnh hưởng trực tiếp logic PR2 |
| 2 | `delegation_status` có tự động sync theo `status` của task (in_progress/done) sau khi `accepted`, hay là 2 trường hoàn toàn độc lập người dùng tự đổi tay? | Ảnh hưởng UI + logic PR2 |
| 3 | "Watcher phòng ban nguồn" trong §6.2 đề xuất gốc là ai cụ thể — `created_by` của task, hay `department_director` của `origin_department_id`, hay cả 2? Hiện chưa có bảng watcher many-to-many | Ảnh hưởng PR3 — có thể cần thêm truy vấn `UserRepository::findDepartmentDirector()` |
| 4 | Giữ nguyên "chọn người nhận toàn hệ thống" hay giới hạn theo phòng ban? | Ảnh hưởng PR4 — quyết định business, không phải kỹ thuật thuần |
| 5 | Đa vai trò (PR ngoài phạm vi) có thật sự cần trong Phase 3 lần này, hay để riêng thành Phase 3b sau khi accept/reject ổn định? | Ảnh hưởng ưu tiên tổng thể — đề xuất: **để riêng**, PR1–PR4 đã đủ khép luồng delegation cơ bản 1 vai trò |

## Không làm trong plan này

- Đa vai trò (quản trị/thực hiện/theo dõi) — để `plans/2026-08-28-task-delegation-ui-proposal.md`
  làm tài liệu tham khảo cho 1 plan Phase 3b riêng sau này.
- Đổi cấu trúc bảng `tasks` (4 cột delegation hiện tại đủ dùng cho PR1–PR4).
- Tích hợp Notification/Reminder Engine đề xuất
  (`plans/2026-08-28-notification-reminder-engine-proposal.md`) — chỉ dùng
  `NotificationService` hiện có.
