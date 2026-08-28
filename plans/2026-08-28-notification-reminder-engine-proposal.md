# Đề xuất: Notification + Reminder Engine dùng chung toàn hệ thống

> **Trạng thái: ĐỀ XUẤT — chưa triển khai.** Ghi lại nguyên văn ý tưởng của
> user (2026-08-28) để làm roadmap tương lai. Không code gì cho tài liệu
> này — chỉ lưu lại thiết kế đã bàn để tham chiếu khi bắt tay làm thật.
> Việc này **lớn hơn nhiều** phạm vi "mở rộng field Task" đang làm (xem
> `plans/imperative-knitting-avalanche.md`), cần `EnterPlanMode` riêng,
> review kiến trúc riêng trước khi động vào code.

## Bối cảnh

Trong lúc làm PR5 (`task_worklogs`) và PR6 (`task_scores`) của plan mở
rộng Task, đã tích hợp tạm 2 notification type mới
(`TYPE_TASK_WORKLOG_ADDED`, `TYPE_TASK_SCORED`) vào
`NotificationService` hiện có (`Modules/Identity/App/Services/
NotificationService.php`) — service tổng quát đang dùng cho Social
(mention, group join...). Đây là giải pháp **tạm, tối thiểu**, đúng đủ
cho nhu cầu PR5/PR6, KHÔNG phải kiến trúc Notification Engine đầy đủ.

User đề xuất một kiến trúc lớn hơn hẳn: tách hẳn thành module
`Notification` riêng với Reminder Engine, đa kênh (channel), policy
chống spam, Activity timeline, quyền "thúc việc" phân theo vai trò.

## Nguyên văn đề xuất

### 1. Ba loại "thúc việc"

- **Nhắc thủ công**: người giao việc bấm "🔔 Thúc việc" → chọn mức độ
  (Bình thường / Quan trọng / Gấp) + tin nhắn tự do → gửi notification
  cho người thực hiện.
- **Nhắc tự động theo deadline**: hệ thống tự theo dõi trạng thái task
  (Chưa bắt đầu / Đang thực hiện / Sắp deadline → Reminder / Quá deadline
  → Overdue Alert / Không có activity → Nudge).
  - Trước deadline 24h: "⏰ Task còn 1 ngày đến deadline."
  - Quá deadline: "🔴 Task đã quá deadline N giờ."
  - Không hoạt động 2 ngày: "⚠️ Task chưa có cập nhật trong 2 ngày."
- **Notification Policy chống spam**: auto reminder theo lịch cố định
  (24h trước → 2h trước → đúng hạn → quá hạn 1 ngày), có **cooldown**
  (không gửi cùng loại notification trong vòng 4 giờ) — kể cả khi người
  giao bấm "Thúc việc" liên tục.

### 2. Module `Notification` riêng (không nhét vào Task)

```
Modules/
├── Task/
├── Project/
├── Evaluation/
├── Social/
└── Notification/
      ├── Notification
      ├── NotificationPreference
      ├── NotificationChannel
      ├── NotificationTemplate
      └── NotificationRule
```

Schema đề xuất:

```
notifications
  id, recipient_id, actor_id, type, title, message,
  entity_type, entity_id, channel, priority,
  read_at, sent_at, created_at
```

### 3. Channel abstraction (không hard-code `sendPushNotification()`)

```
Notification
  ├── In-App
  ├── Web Push
  ├── Mobile Push
  ├── Email
  └── Google Calendar (tương lai: Zalo/Teams/Slack)
```

### 4. Activity timeline trên Task

Không chỉ notification — task nên có timeline đầy đủ (tạo, bắt đầu, đổi
deadline, thúc việc, xem notification, cập nhật progress...) để người
giao biết "mình đã thúc, người nhận đã xem hay chưa".

### 5. Trạng thái notification lifecycle

```
sent → delivered → read → actioned
```

Hữu ích cho KPI/Performance sau này (đo tốc độ phản hồi).

### 6. Kiến trúc tổng thể

```
                    ┌─────────────────┐
                    │      TASK       │
                    └────────┬────────┘
                             │
              ┌──────────────┼──────────────┐
              ↓              ↓              ↓
        Deadline Rule   Activity Rule   Manual Nudge
              │              │              │
              └──────────────┼──────────────┘
                             ↓
                    ┌─────────────────┐
                    │ Notification    │
                    │ Engine           │
                    └────────┬────────┘
                             │
             ┌───────────────┼───────────────┐
             ↓               ↓               ↓
          In-App           Push            Email
             │               │
             └───────┬───────┘
                     ↓
              Notification Center
                     │
                     ↓
                User / Mobile
```

Nguyên tắc cốt lõi: **Task/Project/Leave/Social/Evaluation... chỉ phát
sinh event, Notification Engine quyết định ai nhận, lúc nào, qua kênh
nào, có gửi hay không.** Đây là cách về sau thêm deadline reminder,
overdue, approval, mention `@user`, comment, assignment, KPI warning,
sinh nhật, leave approval... mà không phải sửa kiến trúc liên tục.

### 7. Permission mới đề xuất

```
task.remind
task.manage_reminder
notification.view
notification.manage
notification.preference
```

Scope theo vai trò:
- Người giao việc → thúc người thực hiện.
- Team Lead → thúc member trong team.
- Department Director → thúc trong department.
- Super Admin → toàn hệ thống.

## Quan hệ với `NotificationService` hiện có

`Modules/Identity/App/Services/NotificationService.php` hiện tại (dùng
cho Social + 2 type mới `task_worklog_added`/`task_scored` từ PR5/PR6)
là một **phần con** rất nhỏ của thiết kế này — chỉ có `notify()`/
`notifyUsers()` gửi thẳng, không có: policy/cooldown, channel
abstraction (chỉ có in-app + web push cứng), rule engine theo deadline,
Activity timeline, hay lifecycle `sent→delivered→read→actioned` đầy đủ
(hiện chỉ có `read_at`).

Khi triển khai module `Notification` thật, cần quyết định: viết mới hoàn
toàn hay refactor dần từ `NotificationService`/`UserNotification` hiện
có (bảng `user_notifications` đã tồn tại, đang phục vụ Social) — đây là
1 trong những câu hỏi kiến trúc đầu tiên cần trả lời khi bắt đầu
`EnterPlanMode` cho việc này.

## Việc cần làm trước khi bắt tay code (khi tới lượt)

1. `EnterPlanMode` riêng — không làm chung với bất kỳ plan feature nào
   khác, vì đây là thay đổi kiến trúc nền tảng ảnh hưởng nhiều module.
2. Quyết định: module `Notification` mới hoàn toàn, hay nâng cấp
   `Identity`'s `NotificationService`/`UserNotification` hiện có.
3. Thiết kế schema `notifications` mới (nếu tách bảng) — đối chiếu với
   `user_notifications` đang dùng, quyết định migrate hay chạy song song.
4. Thiết kế `NotificationRule`/policy engine (deadline reminder, cooldown)
   — cần cron/schedule mới (project đã có tiền lệ `Schedule::command()`
   trong `ProjectServiceProvider::boot()` cho `project:auto-start`).
5. Permission mới (`task.remind`, `notification.manage`...) — thêm vào
   `config/permissions.php`, gán role theo đúng bảng RBAC hiện có (§4
   overview).
6. Xác nhận phạm vi Phase — đối chiếu `docs/VA_WORKSPACE_OVERVIEW.md` §2
   liệt kê module `Notification` là "chưa dựng" (Phase 3, cùng Task
   Delegation) — việc này có thể là dịp dựng đúng module đó thay vì để
   tiếp tục ở dạng service tạm trong Identity.

## Không làm cho tới khi có quyết định rõ

- Không tự tạo bảng `notifications` mới, `NotificationRule`, channel
  abstraction khi chưa `EnterPlanMode` riêng.
- Không thêm permission `task.remind`/`notification.*` mới vào
  `config/permissions.php` cho tới khi thiết kế đã chốt.
- Không đổi `NotificationService` hiện có sang kiến trúc mới giữa chừng
  plan "mở rộng field Task" — 2 type đã thêm ở PR5/PR6 giữ nguyên dạng
  tạm cho tới khi module `Notification` thật thay thế.
