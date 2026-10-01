# Master plan triển khai + checklist toàn bộ (30/09/2026)

> **Trạng thái: ĐANG TRIỂN KHAI (cập nhật 30/09/2026)**
> Gộp 2 rà soát: `2026-09-30-ra-soat-feedback-nguoi-dung.md` (26 feedback) và
> `2026-09-30-ra-soat-hien-trang-phan-mem.md` (hiện trạng module).

## 0. Cách dùng file này

Checklist dùng `- [ ]` để tick trực tiếp khi làm. Mỗi hạng mục có **tiêu chí
hoàn thành (DoD)** rõ ràng — không tick khi chưa đạt DoD.

Thứ tự đợt: **Đợt 1 → 5**. Trong cùng đợt, các hạng mục độc lập nhau.

### Tình hình hiện tại

**Đã xong:** Đợt 0, 1 (trừ kiểm thủ công + 1.3), 2.1, 2.2, 3.1, 3.2, 5.1, 5.2,
và **toàn bộ 37 test đang fail từ trước** (xem dưới).

**Chờ người dùng:**

- **1.3** (dropdown trạng thái) — chờ chốt §6.2: double-click hay nút mũi tên.
- **1.1 / 1.2** — code xong, cần **kiểm thủ công trên trình duyệt** (15 cặp
  panel × 2 chiều, 3 breakpoint) vì không tự kiểm được.
- **4.1** (lan trạng thái lên danh mục) — chờ chốt §6.1.
- **4.2** (hashtag Social) — chờ chốt §6.3.
- **3.3** (siết phạm vi delegation) — đã chặn giao cho người đã nghỉ việc; phần
  phạm vi phòng ban chờ chốt §6.5.
- **2.2** — đã ẩn 6 cách tính chưa có logic khỏi UI (Phương án A). Nếu muốn
  Phương án B (cài logic thật) thì cần chốt công thức, §6.6.

**Chưa làm:** 3.4 (rà enforcement 10 cờ creation settings), 4.3 (rà bảng phân
quyền), 4.4 (tách bản ghi STT 23), 5.3 (đóng bản ghi trên bảng ghi nhận — việc
này làm trên UI, không phải code).

### 37 test fail từ trước — đã xử lý xong

Không phải lỗi mới; có sẵn trước khi bắt đầu plan này. Nguyên nhân:

| Nhóm | Số test | Nguyên nhân thật |
|---|---|---|
| HRM (`HrmJwtVerifierTest`, `HrmSsoTest`) | 12 | **Lỗi môi trường**, không phải lỗi code: thiếu `OPENSSL_CONF` → `openssl_pkey_new()` trả false. Đã thêm `markTestSkipped()` kèm hướng dẫn + ghi vào `docs/known-issues.md` |
| `HrmJwtVerifierTest` fallback | 1 | **Test sai**: `setUp()` fake JWKS 200 trước, `Http::fake()` chỉ gộp stub nên 404 của test không ghi đè được → nhánh fallback chưa từng được kiểm. Đã tách stub ra `fakeJwksAvailable()` |
| `ReportPeopleSnapshotTest` | 5 | **Test lỗi thời**: gọi `GET /report/{id}` đã bị xoá ở commit `1966255` (bỏ luồng xem/sửa báo cáo đã lưu) → nhận HTML của SPA. Đã xoá file test; phát hiện thêm `ReportPersonSnapshot` giờ là **code chết** |
| `TaskPriorityValidationTest`, `EvaluationScoreKitTest` | 2 | **Test lỗi thời**: kỳ vọng nhãn `'Rất khó'` nhưng code đã đổi thành `'RK-Rất khó'` theo đúng yêu cầu người dùng **STT 15** |
| `ProjectVisibilityTest` | 2 | **Test lỗi thời**: kỳ vọng thành viên/người theo dõi xem được dự án phòng khác, trong khi commit `dff1c55` đã siết phạm vi xem về **chỉ theo phòng ban** (có chủ ý, docblock ghi rõ). Đã đảo lại cho khớp + thêm 2 test đường đi hợp lệ |
| `ProjectSettingsTest` | 1 | **Test lỗi thời**: `task_code_pattern`/`task_code_counter` thành `required` sau khi test được viết → PUT trả 422 |
| Social, các nhóm khác | còn lại | Xem kết quả chạy cuối |

Bài học: phần lớn "test fail" ở đây là **test chưa cập nhật theo thay đổi có
chủ ý**, không phải code sai. Khi sửa hành vi theo yêu cầu người dùng, phải rà
test cùng lúc — nếu không, lần sau không ai biết fail nào là thật.

---

## ĐỢT 0 — Đính chính rà soát trước (làm đầu tiên, rất nhanh)

Rà soát ngày 30/09 có **1 kết luận sai** cần đính chính trước khi ai đó dựa vào
nó để code:

> Bản rà soát trước ghi *"Roll-up tiến độ Task → Project chưa có"*. **Không
> chính xác.** `Modules/Dashboard/App/Services/ProjectProgressCalculator.php`
> đã cài **đủ 3 phương pháp** (`average`, `duration_weighted`,
> `task_weighted`) khớp `ProjectEnums::PROGRESS_METHODS`, có fallback hợp lý.

**Vấn đề thật (hẹp hơn nhiều):** calculator nằm **trong module `Dashboard`** và
**chỉ có 1 nơi dùng** — `CompanyDashboardService`. Còn
`Modules/Project/App/Services/ProjectService.php:988` (hàm `present()`, tức
trang chi tiết dự án + danh sách dự án của module Project) vẫn trả cứng:

```php
'progress_percent' => null, // Chưa có Task — luôn null ở giai đoạn 1.
```

→ Hệ quả: **Dashboard tổng công ty hiện đúng % tiến độ, nhưng trang Dự án
(`/manager/project`) và chi tiết dự án luôn hiện `—`.** Cột "Tiến độ" trong
`ProjectList.vue:1125` đọc `project.progress_percent` nên luôn rỗng.

Đây là **lỗi nhất quán dữ liệu giữa 2 trang**, không phải thiếu tính năng.

- [x] Sửa `plans/2026-09-30-ra-soat-hien-trang-phan-mem.md` §3.2 + §6 theo đúng
      phát hiện trên (đổi "chưa có roll-up" → "có calculator nhưng chưa dùng ở
      module Project").
- [x] **DoD:** file rà soát không còn câu nào nói roll-up chưa tồn tại.

---

## ĐỢT 1 — Lỗi UI rõ ràng, sửa được ngay

### 1.1. STT 10 — Panel thông báo / chat / ghi nhận chồng nhau ⭐ ƯU TIÊN CAO

Nguyên nhân đã xác minh: 6 panel cùng `z-index: 50` → thứ tự vẽ rơi về thứ tự
DOM (`AppHeader.vue:48-53`: Ghi nhận → Chat → Thông báo), nên Chat **luôn** đè
Ghi nhận.

> **Đính chính khi triển khai (30/09):** cơ chế "1 panel mở tại 1 thời điểm"
> **đã có sẵn** — `resources/js/composables/useHeaderPopover.js`. Không cần viết
> composable mới. Nguyên nhân thật hẹp hơn: **5/6 panel đã dùng composable đó,
> chỉ Chat thì không** — Chat giữ trạng thái mở riêng trong Pinia store
> (`chatStore.panelOpen`), nên nó hiện song song với panel khác. Đúng là lý do
> mọi triệu chứng người dùng báo đều liên quan tới Chat.
>
> Cách sửa thực tế: thêm `registerHeaderPopover()` /
> `notifyHeaderPopoverOpened()` vào composable sẵn có để panel giữ state bên
> ngoài cũng vào được cùng nhóm loại trừ, rồi đăng ký Chat trong
> `ChatWidget.vue` (2 chiều: Chat mở → đóng panel khác; panel khác mở → đóng
> Chat).
>
> Không cần lớp z-index thứ 2: 5 panel dùng `v-if` nên panel đóng không có
> trong DOM, `HeaderAccountMenu` dùng `v-show` + `display: none`. Đã loại trừ
> nhau thì không còn chồng nhau.

- [x] ~~Tạo `resources/js/composables/useExclusivePanel.js`~~ — không cần, đã có
      `useHeaderPopover.js`; thay vào đó mở rộng nó cho panel giữ state ngoài.
- [x] `HeaderFeatureRequestButton.vue` — đã dùng `useHeaderPopover('feature-request')`
- [x] `HeaderNotifications.vue` — đã dùng `useHeaderPopover('notifications')`
- [x] `HeaderActivityLog.vue` — đã dùng `useHeaderPopover('activity')`
- [x] `HeaderShortcuts.vue` — đã dùng `useHeaderPopover('shortcuts')`
- [x] `HeaderAccountMenu.vue` — đã dùng `useHeaderPopover('account')`
- [x] **Chat** — thủ phạm thật: đăng ký vào nhóm loại trừ trong `ChatWidget.vue`
- [x] ~~Thêm class `--active` → `z-index: 60`~~ — không cần, xem đính chính trên
- [x] Rà `pointerdown`/`click-outside` sẵn có ở từng component — `ChatFloatingPanel`
      không có handler click-outside nên không có nguy cơ tự đóng khi bấm bên trong
- [ ] **Người dùng kiểm thủ công:** 15 cặp panel × 2 chiều ở desktop
- [ ] **Người dùng kiểm thủ công:** mobile ≤480px (các panel chuyển
      `position: fixed`: `HeaderNotifications.vue:509`,
      `HeaderFeatureRequestButton.vue:770`, `HeaderActivityLog.vue:402`,
      `HeaderShortcuts.vue:653`)
- [ ] **DoD:** mở panel nào cũng hiện trên cùng, panel trước tự đóng, đúng ở cả
      3 breakpoint. Không panel nào tự đóng khi bấm bên trong.

### 1.2. STT 3 — Nút "Lịch" nhảy vị trí

`TaskViewModeMenu` mount ở **2 chỗ**: `TaskList.vue:1983` (hàng tab) và
`:2450` (toolbar lịch).

- [x] Bỏ instance ở toolbar lịch (`TaskList.vue:2450`), giữ duy nhất ở hàng tab
- [x] Giữ nguyên control riêng của lịch (chọn tháng/tuần) trên toolbar
- [x] **DoD:** chuyển Danh sách ↔ Kanban ↔ Lịch, nút chế độ xem **không đổi vị
      trí**. Khớp comment `TaskViewModeMenu.vue:3-5` ("chỉ một instance").

### 1.3. STT 11 + 7 — Đổi trạng thái ngay tại bảng

`TaskTableRow.vue:69-71` render trạng thái là **chữ tĩnh** + dùng class
`ptasks__pill` (**vi phạm CLAUDE.md §14** cấm badge/pill).

- [ ] Đổi `ptasks__pill` → chấm màu `0.5rem` + `border-radius: var(--radius-full)`
      trước chữ thường (mẫu `PermissionMatrix.vue` → `.perm-side__dot`)
- [ ] Thêm dropdown chọn trạng thái. **Thao tác: chờ chốt câu hỏi §6.2** —
      double-click, hay nút mũi tên riêng cạnh chữ
- [ ] Gọi `PUT /api/project/tasks/{id}` `{ status }`
- [ ] **Patch state từ response**, không reload cả bảng (CLAUDE.md §14)
- [ ] Áp dụng ở `ProjectTasksTab.vue` (STT 11)
- [ ] Áp dụng ở danh sách việc con trong `TaskDetail.vue` (STT 7)
- [ ] Không cần sửa backend — `TaskService::applyCompletionTracking()`
      (`:877-899`) đã tự set `actual_end_date` + `progress_percent`
- [ ] **DoD:** đổi được trạng thái từ bảng, không còn pill bo tròn nền màu,
      không reload toàn bảng sau khi đổi.

### 1.4. STT 9 — Hiện cột danh mục ngoài bảng dự án

`ProjectTasksTab.vue` đã có chế độ nhóm theo danh mục (`isPhaseGroup` `:161`,
`phaseGroups` `:198`) nhưng phải đổi chế độ xem mới thấy.

- [x] Thêm cột "Danh mục" vào bảng chế độ danh sách phẳng, bật mặc định
- [x] Không cần đổi API — dữ liệu `phase`/`category` của cha đã có trong task
- [x] **DoD:** vào tab Công việc của dự án thấy ngay danh mục, không cần đổi
      chế độ xem.

---

## ĐỢT 2 — Nhất quán dữ liệu tiến độ (mở đường cho yêu cầu cấp điều hành)

### 2.1. Dùng `ProjectProgressCalculator` trong module `Project`

Xem Đợt 0 để hiểu đúng vấn đề: calculator đã có, chỉ chưa dùng ở `Project`.

- [x] Quyết định vị trí calculator: **chuyển** `ProjectProgressCalculator` từ
      `Modules/Dashboard/App/Services/` sang `Modules/Project/App/Services/`
      (đúng chủ sở hữu nghiệp vụ — tiến độ dự án thuộc Project, Dashboard chỉ
      là bên tiêu thụ), hoặc giữ chỗ cũ và cho Project gọi sang. **Đề xuất:
      chuyển**, rồi `CompanyDashboardService` import từ Project.
- [x] Sửa `ProjectService.php:988` — thay `null` bằng giá trị calculator tính,
      theo `progress_method` của chính project
- [x] Xoá comment sai *"Chưa có Task — luôn null ở giai đoạn 1"*
- [x] Tránh N+1: `present()` gọi cho 1 project thì query 1 lần; danh sách dự án
      dùng `resolveMany()` hàng loạt (đã có sẵn, xem `CompanyDashboardService.php:108`)
- [x] Cập nhật `CompanyDashboardService` theo vị trí mới, không đổi hành vi
- [x] Test: cùng 1 project, `%` ở `/dashboard` và ở `/manager/project` **bằng nhau**
- [x] Test 3 phương pháp `average` / `duration_weighted` / `task_weighted`
- [x] Test fallback: task không có `weight` hợp lệ → về `average`
- [x] **DoD:** cột "Tiến độ" ở `/manager/project` không còn luôn `—`; số khớp
      Dashboard; có test cho cả 3 phương pháp.

### 2.2. `progress_type` 3 kiểu chưa có logic — đang phơi ra UI

`checklist` / `child_weight` / `timeline` đã khai enum + validate + **hiện trên
UI cho người dùng chọn**, nhưng `TaskService::applyQuantityProgress()` (`:811`)
chỉ xử lý `percent`/`quantity`. Chọn 3 kiểu này → `progress_percent` đứng yên.

Chọn **một** trong hai, không để nguyên trạng:

- [x] **Phương án A (nhanh, an toàn):** tạm ẩn 3 lựa chọn khỏi UI
      (`TaskEnums::options()`) tới khi có logic. Ghi rõ lý do trong code.
- [ ] **Phương án B (đầy đủ):** cài logic tính cho cả 3 —
      `checklist` (theo % subtask done), `child_weight` (theo weight việc con),
      `timeline` (theo thời gian đã trôi). Cần chốt công thức từng kiểu trước.
- [x] **DoD:** không còn lựa chọn nào trên UI mà chọn vào thì không có tác dụng.

---

## ĐỢT 3 — Chất lượng & an toàn (nợ kỹ thuật chặn mở rộng)

### 3.1. Test cho module `Dashboard` (hiện **0 test**)

So sánh: Social 20, Project 9, Identity 8, Evaluation 7, WorkspaceConfig 4,
Chat 3, Report 3, FeatureRequest 2, Credential 1, **Dashboard 0**.

- [ ] Test `CompanyDashboardService`: health classify, aging, phân trang tự cắt
- [x] Test `ProjectProgressCalculator` — cả 3 phương pháp + fallback (gộp 2.1)
- [ ] Test `DepartmentDashboardService` + `MyDashboardService`
- [ ] Test phân quyền: `dashboard.view` / `dashboard.view_company` /
      `dashboard.view_department` — ai **không** được xem gì
- [ ] **DoD:** module số liệu điều hành có test cho cả tính toán và phân quyền.

### 3.2. Bọc transaction cho bulk actions Task

`TaskService::bulkUpdate()` / `bulkDelegate()` lặp N query, không
`DB::transaction()` → lỗi giữa chừng để dữ liệu nửa vời, không rollback.

- [x] Bọc `DB::transaction()` quanh vòng lặp
- [ ] Cân nhắc đổi sang update hàng loạt 1 query thay vì N query
- [x] Response nói rõ phần nào thất bại
- [ ] Test: mô phỏng lỗi giữa chừng → rollback sạch
- [x] **DoD:** chọn hàng trăm task, lỗi giữa chừng không để lại dữ liệu nửa vời.

### 3.3. Siết phạm vi người nhận delegation

`BulkDelegateTaskRequest` chỉ validate `exists:users,id` + kết hợp
`assignableUsers(unrestricted: true)` → ai có `task.delegate` giao được cho
**bất kỳ user toàn hệ thống**.

- [ ] Chốt phạm vi hợp lệ (§6.5) rồi mới code
- [ ] Validate phạm vi ở Form Request, không chỉ ở dropdown FE
- [ ] Test: giao cho user ngoài phạm vi → bị từ chối
- [ ] **DoD:** không giao chéo được ra ngoài phạm vi cho phép, chặn ở backend.

### 3.4. Rà soát enforcement của creation settings

10 cột cấu hình ẩn/hiện chéo đã có cột + validate + `present()`, nhưng chưa rà
hết nơi **thực thi**.

- [ ] Lập bảng 10 cờ × "đã enforce ở đâu" (`TaskRepository`? `TaskList.vue`?)
- [ ] Với cờ chưa enforce: cài, hoặc ẩn khỏi UI
- [ ] Test riêng cho `hide_cross_tasks_from_assignees`,
      `hide_from_parent_assignees`, `hide_from_parent_followers`
- [ ] **DoD:** mỗi cờ bật lên đều có tác dụng thật, có test chứng minh.

---

## ĐỢT 4 — Feedback cần xác nhận / phụ thuộc

### 4.1. STT 8 — Công việc bắt đầu thì danh mục bắt đầu theo

**Chờ chốt §6.1 trước khi code.**

- [ ] Chốt: con xong hết thì cha có tự hoàn thành không?
- [ ] Thêm bước lan trạng thái lên cha trong `TaskService`: task từ
      `not_started` → khác, tìm cha gần nhất `type ∈ {phase, category}`, nếu cha
      đang `not_started` thì set `in_progress`, đệ quy lên
- [ ] Chỉ lan **một chiều** (bắt đầu), trừ khi §6.1 chốt khác
- [ ] Trong cùng transaction với update task
- [ ] Test: con bắt đầu → phase cha `in_progress`
- [ ] Test: cha đã `in_progress` → không đổi
- [ ] Test: WBS nhiều tầng → lan đúng tới gốc
- [ ] **DoD:** có test cho cả 3 case. **Bắt buộc** vì đây là ghi dữ liệu tự động.

### 4.2. STT 25 — Hashtag & ảnh bìa khi share Social

Bản ghi ghi "Đã duyệt" + hoàn thành 22/09 nhưng **không thấy code**.

- [ ] Xác nhận với anh Khoa: đã làm ở đâu, hay bản ghi đóng sớm (§6.3)
- [ ] Nếu chưa làm: gỡ link tự chèn theo từng hashtag khi copy từ social
- [ ] Gom hashtag về **1 kiểu thiết kế** (đang 2 kiểu)
- [ ] Hiện ảnh bìa khi share bài dạng ảnh/video
- [ ] **DoD:** copy bài không phải gỡ link thủ công; share có ảnh bìa.

### 4.3. STT 24 — Rà soát bảng phân quyền khớp sidebar thật

Không bị chặn gì, làm được ngay.

- [ ] Đối chiếu `MENU_SECTIONS` (`AppSidebar.vue`) ↔
      `GlobalMenuVisibilityService::CATALOG` ↔ trang `/superadmin/permissions`
- [ ] Bổ sung mục thiếu theo **CLAUDE.md §16** (đồng bộ đôi bên)
- [ ] Rà `WorkspaceConfigGlobalMenuVisibilityTest` +
      `WorkspaceConfigSidebarTest` — có assertion cứng theo **số lượng**
      (`assertJsonCount`) và **vị trí** (`menus.0`, `$before[N]`), thêm/bớt mục
      làm lệch index → phải cập nhật con số
- [ ] Dùng chữ tiếng Việt phổ thông, không thuật ngữ kỹ thuật (CLAUDE.md §14)
- [ ] **DoD:** mọi mục trên sidebar thật đều xuất hiện ở trang phân quyền và
      trang menu hệ thống; test xanh.

### 4.4. STT 23 — Tách bản ghi

- [ ] Tách thành 2 bản ghi: (a) trang hướng dẫn sử dụng + giải thích khái niệm,
      (b) tour onboarding theo quyền
- [ ] (b) phụ thuộc module `Onboarding` (Phase 6, chưa dựng) → không hứa sớm
- [ ] **DoD:** 2 bản ghi riêng, mỗi cái có phạm vi rõ.

---

## ĐỢT 5 — Đồng bộ tài liệu & bảng ghi nhận

### 5.1. Cập nhật `docs/VA_WORKSPACE_OVERVIEW.md`

Theo `plans/README.md`: `docs/` là nguồn sự thật hiện tại, lệch code là **bug
tài liệu cần sửa ngay**.

- [x] Bảng "Đã có trong repo" — thêm 5 module thiếu: `Chat`, `Credential`,
      `Dashboard`, `Report`, `FeatureRequest`
- [x] Sửa câu *"Các module nghiệp vụ (Project, Initiative, DailyReport…) chưa
      dựng"* — Project đã rất đầy đủ
- [x] §19 Phase 1g — xoá *"chưa có Sprint/Worklog/Gantt UI thật"*; cả 3 đã có
      (`ProjectGanttTab.vue` 1.646 dòng, `ProjectSprintBoard.vue` 1.261 dòng,
      worklog trong `TaskDetail.vue`)
- [x] §19 Phase 7 — tách trạng thái: Gantt **xong**; roll-up có calculator
      nhưng chưa dùng ở module Project (Đợt 2.1)
- [x] Dòng `Identity` — bỏ *"stub chờ HRM"*; HRM SSO + webhook + sync đã dựng
      đầy đủ (`Modules/Identity/App/Hrm/`)
- [x] §20.10 — Pusher đã cấu hình, không còn "chưa có lib realtime"
- [x] **DoD:** không còn mục nào trong overview trái với code.

### 5.2. Cập nhật `docs/known-issues.md`

- [x] Thêm: `ProjectService::present()` chưa dùng calculator → tiến độ lệch
      giữa Dashboard và trang Dự án (xoá khi làm xong Đợt 2.1)
- [ ] Thêm: module `Dashboard` 0 test (xoá khi làm xong Đợt 3.1)
- [x] **DoD:** 2 lỗ hổng này được ghi nhận thay vì chỉ nằm trong plan.

### 5.3. Đồng bộ bảng ghi nhận yêu cầu tính năng

Bảng lệch code **cả hai chiều**.

- [ ] Đóng 10 bản ghi đã làm xong nhưng còn treo: STT **20, 19, 17, 21, 13, 6,
      15, 14, 12, 18** (bằng chứng số dòng trong
      `2026-09-30-ra-soat-feedback-nguoi-dung.md` §2)
- [ ] Mở lại / xác minh STT **25** (đã đóng nhưng chưa có code)
- [ ] Đóng tiếp các mục làm xong ở Đợt 1–2
- [ ] **DoD:** trạng thái bảng khớp code. Cột "Số ngày chờ xử lý" (đang tới 10
      ngày) phản ánh đúng thực tế, không phải vì chưa ai đóng bản ghi.

---

## 6. Câu hỏi phải chốt (chặn code)

| # | Câu hỏi | Chặn | Hỏi ai |
|---|---|---|---|
| 6.1 | STT 8 — mọi việc trong danh mục xong thì danh mục **tự hoàn thành** không? Plan mặc định **không** (chỉ lan chiều bắt đầu) | Đợt 4.1 | anh Đức |
| 6.2 | STT 11/7 — dropdown trạng thái: **double-click** (theo CLAUDE.md §14, tránh đổi nhầm) hay **nút mũi tên riêng** cạnh chữ? | Đợt 1.3 | anh Đức |
| 6.3 | STT 25 — đã làm chưa, hay bản ghi đóng sớm? | Đợt 4.2 | anh Khoa |
| 6.4 | §20.9 — WBS giới hạn mấy tầng? (đề xuất cảnh báo mềm ở tầng 6+) | Đợt 4.1 (lan đệ quy) | — |
| 6.5 | Phạm vi hợp lệ của người nhận delegation (cùng phòng? cùng dự án? cùng công ty?) | Đợt 3.3 | — |
| 6.6 | `progress_type` 3 kiểu — chọn Phương án A (tạm ẩn) hay B (cài logic)? Nếu B: công thức từng kiểu | Đợt 2.2 | — |

---

## 7. Ngoài phạm vi plan này

### 7.1. Tính năng lớn từ feedback — cần plan riêng

| STT | Nội dung | Vướng gì |
|---|---|---|
| 5 | Dashboard Tổng Giám đốc (doanh thu, dòng tiền, công nợ, chi phí, chỉ số phụ huynh, khung cảnh báo) | `ProjectFinance` (Phase 9) chưa có + **chưa có nguồn dữ liệu tài chính**. Phần nhân sự/turnover **giờ làm được** nhờ HRM đã tích hợp. Phải chốt nguồn số trước khi thiết kế UI |
| 2 | Đồng bộ 2 chiều Google Calendar | OAuth Google + job đồng bộ + xử lý xung đột 2 chiều |
| 4 | Dự án: gán nhãn mức độ, cảnh báo rủi ro/chậm tiến độ, lọc sức khoẻ/tiến trình/cấp quản lý/khu vực | Tách được: lọc & nhãn làm sớm; cảnh báo rủi ro cần công thức sức khoẻ (đã có `healthClassifier` trong Dashboard — tái dùng được) |
| 1 | Công việc: lọc theo vai trò JD, sức khoẻ công việc, gán nhãn, lọc cấp quản lý | Cần định nghĩa "vai trò của tôi" (phê duyệt / giao việc / được tag) theo dữ liệu hiện có |
| 22 | Đặt lịch đăng bài bảng tin | `SocialPost` chưa có `scheduled_at` → cần migration + job đăng theo giờ |

### 7.2. Module roadmap chưa dựng (14)

`Initiative` (Phase 2), `TaskScoringConfig` + `Kpi` (4), `DailyReport`,
`WeeklyReport`, `Blocker`, `Contract`, `KnowledgeBase`, `AiAccount`,
Evaluation Giai đoạn D (5), `Onboarding` (6), `DocumentManager` (8),
`ProjectFinance` (9), `MaterialPlanning` (11), `ProcessEngine` (12).

Trước khi mở Phase 2/4 cần chốt §20.1 (`director_officer` có phòng ban ảo?),
§20.3 (roll-up Hạng mục có phạt điểm khi trễ?), §20.4 (task delegated tính KPI
phòng nguồn hay phòng nhận?), §20.5 (`viewer` scope theo Hạng mục?).

### 7.3. Nợ kỹ thuật không chặn

- **Laravel 10 hết hạn vá bảo mật** — đã tắt `block-insecure`; **chưa vá**.
  Cần đánh giá nâng 11/12.
- **esbuild/vite advisory** — chỉ ảnh hưởng `npm run dev`.
- **Vi phạm §5 CLAUDE.md** — Service/Controller gọi Eloquent trực tiếp ở
  `TaskService`, `PermissionMatrixController`, `ViewAsController`,
  `SuperAdminBootstrap`, 3 service Social.
- **Filter Lịch không dùng được index** — `whereRaw('DATE(COALESCE(...)))`.

---

## 8. Rủi ro

| Rủi ro | Mức | Giảm thiểu |
|---|---|---|
| Đợt 1.1 chạm 6 component dùng ở **mọi trang** — composable đóng panel sai làm panel tự đóng khi bấm bên trong | Cao | Rà `pointerdown` từng component trước khi sửa; kiểm đủ 15 cặp × 2 chiều |
| Đợt 4.1 lan trạng thái lên cha là **ghi dữ liệu tự động** — lan sai đổi trạng thái nhiều phase ngoài ý muốn | Cao | Bắt buộc có test 3 case trước khi lên production; chốt §6.1 trước |
| Đợt 1.3 đổi trạng thái tại bảng làm tăng rủi ro đổi nhầm | Trung bình | Double-click hoặc nút riêng theo CLAUDE.md §14 — **đừng bỏ qua để "cho nhanh"** |
| Đợt 2.1 chuyển calculator giữa 2 module có thể vỡ Dashboard đang chạy đúng | Trung bình | Viết test so khớp số Dashboard **trước** khi chuyển, chạy lại sau |
| Đợt 2.1 tính tiến độ cho danh sách dự án dễ gây N+1 | Trung bình | Dùng `resolveMany()` hàng loạt, không gọi `resolve()` trong vòng lặp |
| Đợt 4.3 test phân quyền có assertion cứng theo số lượng/vị trí | Thấp | Cập nhật con số trong test cùng lúc, đúng CLAUDE.md §16 |
