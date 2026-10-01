# Rà soát & sửa feedback người dùng (đợt 14/09 – 29/09/2026)

> **Trạng thái: KẾ HOẠCH** — chưa triển khai. Nguồn: bảng ghi nhận yêu cầu
> tính năng (26 bản ghi) từ `/superadmin/feature-requests`.

## 1. Bối cảnh

26 feedback từ Phòng Kinh doanh (chị Le Thi Thanh Giau, anh Do Minh Duc) và
Phòng CNTT. Sau khi rà soát code thực tế, **10 mục đã có trong code** nhưng
trạng thái trong bảng ghi nhận chưa cập nhật; **6 mục là lỗi/thiếu thật**;
**7 mục là đề xuất tính năng lớn** cần plan riêng.

Plan này chỉ bao phủ nhóm **lỗi/thiếu thật** (phần 3) + đồng bộ lại trạng thái
bảng ghi nhận (phần 5). Nhóm tính năng lớn tách plan riêng (phần 6).

## 2. Đã có trong code — chỉ cần đóng bản ghi, KHÔNG code lại

| STT | Yêu cầu | Bằng chứng |
|---|---|---|
| 20 | Text hướng dẫn khi chưa có tiêu chí | `Modules/Evaluation/resources/js/pages/EvaluationView.vue:194` — đúng nguyên văn yêu cầu |
| 19 | Nút "Thêm tiêu chí" có chữ, không chỉ dấu `+` | `Modules/Evaluation/resources/js/pages/WorkspaceConfigEvaluation.vue:1299,1636` |
| 17 | Chỗ thêm tiêu chí cho trưởng phòng | `EvaluationView.vue:193-196` — có nút "Cấu hình phòng ban" dẫn sang trang cấu hình |
| 21 | Scroll từ "Sức khoẻ dự án" → "Danh sách dự án" | `Modules/Dashboard/resources/js/pages/DashboardCompany.vue:315` (`scrollToProjectList`) |
| 13 | Xoá công việc → về đúng tab dự án | `Modules/Project/resources/js/pages/TaskDetail.vue:429-438` (`goBack()`) |
| 6 | Xoá công việc con → về trang việc cha | `TaskDetail.vue:581` (`onChildTaskDeleted` → `loadTask()`, ở lại trang cha) |
| 15 | Tách `RKRất khó` → `RK-Rất khó`; cho chọn lại phân loại | `TaskFormFields.vue:229` + `:224` (`importanceLocked = false`) |
| 14 | Set độ khó cho công việc con | `Modules/Project/resources/js/pages/TaskCreate.vue:476-478` |
| 12 | Mũi tên bung công việc con | `ProjectTasksTab.vue:200-217` — đã lọc ẩn hẳn con thay vì `v-show` |
| 18 | Thông báo yêu cầu tính năng chỉ superadmin + người gửi | `FeatureRequestService.php:236` (`allActiveSuperAdmins()->first()`) + `:379` (`notifyCreator`) |

**Việc cần làm:** đăng nhập superadmin, chuyển 10 bản ghi trên sang *Đã duyệt /
Hoàn thành*, ghi chú số dòng code làm bằng chứng. Không sửa code.

## 3. Lỗi thật — cần sửa

### 3.1. STT 10 — Panel thông báo / chat / ghi nhận chồng lên nhau (ƯU TIÊN CAO)

**Nguyên nhân (đã xác minh):** cả 6 panel header dùng **cùng** `z-index: 50`:

- `resources/js/components/HeaderFeatureRequestButton.vue:388`
- `resources/js/components/HeaderNotifications.vue:310`
- `resources/js/components/HeaderActivityLog.vue:170`
- `resources/js/components/HeaderShortcuts.vue:382`
- `resources/js/components/HeaderAccountMenu.vue:195`
- `Modules/Chat/resources/js/components/ChatFloatingPanel.vue:46`

Khi `z-index` bằng nhau, thứ tự vẽ rơi về **thứ tự DOM** trong
`resources/js/components/AppHeader.vue:48-53`:
`Ghi nhận → Chat → Thông báo`. Phần tử sau đè phần tử trước, nên Chat **luôn**
đè Ghi nhận. Khớp chính xác mô tả: "thông báo → chat thì oke, chat → ghi nhận
thì ghi nhận bị ẩn dưới chat".

**Cách sửa (2 lớp, làm cả hai):**

1. **Chỉ cho phép 1 panel mở tại 1 thời điểm.** Đây là cách sửa gốc — 2 panel
   không bao giờ cùng hiện thì không còn chuyện đè nhau. Tạo composable mới
   `resources/js/composables/useExclusivePanel.js`: giữ 1 `ref` module-scope
   `openPanelId`; mỗi panel đăng ký 1 id, khi mở thì set `openPanelId = id`,
   các panel khác `watch` thấy khác id của mình thì tự đóng. Áp dụng cho cả 6
   component trên.
2. **Nâng z-index của panel đang mở.** Phòng trường hợp còn panel nào mở song
   song (vd. panel neo trong nội dung): thêm class `--active` khi mở, đặt
   `z-index: 60`. Giữ `50` làm mức nền.

**Kiểm thử thủ công:** lần lượt mở từng cặp trong 6 panel theo cả 2 chiều
(15 cặp × 2), xác nhận panel mở sau luôn hiện trên cùng và panel trước đã đóng.
Kiểm ở cả 3 breakpoint — mobile ≤480px các panel này chuyển `position: fixed`
(`HeaderNotifications.vue:509`, `HeaderFeatureRequestButton.vue:770`,
`HeaderActivityLog.vue:402`, `HeaderShortcuts.vue:653`) nên cần soát riêng.

### 3.2. STT 11 + 7 — Đổi trạng thái ngay tại bảng, không cần vào chi tiết

**Hiện trạng:** `Modules/Project/resources/js/components/TaskTableRow.vue:69-71`
render trạng thái dạng **chữ tĩnh**, không đổi được. Muốn đổi phải mở chi tiết.

**Kèm vi phạm quy tắc:** chỗ này dùng class `ptasks__pill` — vi phạm
CLAUDE.md §14 (cấm badge/pill bo tròn nền màu cho trạng thái). Sửa luôn 1 lượt.

**Cách sửa:**

- Đổi `ptasks__pill` → chấm màu `0.5rem` + `border-radius: var(--radius-full)`
  đặt trước chữ thường, theo mẫu `PermissionMatrix.vue` (`.perm-side__dot`).
- Bọc thành nút mở dropdown chọn trạng thái. **Theo CLAUDE.md §14: click 1 lần
  = xem, double-click mới đổi** — vì ô này vừa hiển thị vừa sửa được. Hoặc
  dùng 1 nút mũi tên nhỏ riêng cạnh chữ để mở dropdown, tránh đổi nhầm.
- Gọi `PUT /api/project/tasks/{id}` với `{ status }`, rồi **patch trực tiếp
  vào state từ response** (CLAUDE.md §14), không reload cả bảng.
- Backend đã sẵn: `TaskService::applyCompletionTracking()`
  (`Modules/Project/App/Services/TaskService.php:877-899`) tự set
  `actual_end_date` + `progress_percent`. Không cần sửa backend.
- Áp dụng ở cả 2 nơi dùng `TaskTableRow`: `ProjectTasksTab.vue` (STT 11) và
  danh sách công việc con trong `TaskDetail.vue` (STT 7).

### 3.3. STT 3 — Nút "Lịch" nhảy vị trí

**Nguyên nhân:** `TaskViewModeMenu` được mount ở **2 chỗ khác nhau** trong
`Modules/Project/resources/js/pages/TaskList.vue`:

- dòng `1983` — trên hàng tab (chế độ Danh sách / Kanban)
- dòng `2450` — trên thanh toolbar lịch (chế độ Lịch)

Nên khi chuyển sang Lịch, nút đổi chỗ từ trái sang phải màn hình.

**Cách sửa:** giữ **1 instance duy nhất** ở hàng tab cho mọi chế độ. Bỏ
instance ở toolbar lịch (dòng 2450); các control riêng của lịch (chọn
tháng/tuần) vẫn ở toolbar. Comment đầu `TaskViewModeMenu.vue:3-5` đã ghi
"chỉ một instance được mount tại một thời điểm" — sửa như trên mới đúng ý đó.

### 3.4. STT 8 — Công việc bắt đầu thì danh mục bắt đầu theo

**Hiện trạng:** `TaskService::applyCompletionTracking()`
(`Modules/Project/App/Services/TaskService.php:877`) chỉ xử lý trạng thái của
**chính** task, không lan lên cha (`phase` / `category`).

**Cách sửa:** thêm bước "lan trạng thái lên cha" sau khi cập nhật task:

- Khi task chuyển từ `not_started` → khác `not_started`, tìm cha gần nhất có
  `type` thuộc `phase`/`category`; nếu cha đang `not_started` thì set cha sang
  `in_progress`. Đệ quy lên trên.
- Chỉ lan **một chiều** (bắt đầu). Không tự hoàn thành cha khi con xong —
  cần xác nhận với người yêu cầu trước (phần 4).
- Đặt trong cùng transaction với update task.
- Test: `tests/Feature/Project/` — thêm case task con bắt đầu → phase cha
  `in_progress`; case cha đã `in_progress` thì không đổi.

### 3.5. STT 9 — Hiện thêm danh mục ngoài dashboard dự án

**Hiện trạng:** `ProjectTasksTab.vue` **đã có** chế độ nhóm theo danh mục
(`isPhaseGroup`, `phaseGroups` — dòng `161`, `198`, `1280`), nhưng phải chọn
chế độ xem mới thấy.

**Cách sửa:** thêm cột "Danh mục" vào bảng ở chế độ danh sách phẳng, bật mặc
định trong `columns`. Dữ liệu đã có sẵn trong task (`phase`/`category` của
cha) — chỉ cần render, không cần đổi API.

### 3.6. STT 25 — Hashtag & ảnh bìa khi chia sẻ từ Social

**Lưu ý quan trọng:** bản ghi này đang ghi **"Đã duyệt" + có người xử lý +
ngày hoàn thành 22/09/2026**, nhưng rà soát code **không thấy** xử lý hashtag
trong `Modules/Social/resources/js/components/SocialShareDialog.vue`.
→ **Cần xác nhận lại**: hoặc đã làm ở chỗ khác mà rà soát chưa thấy, hoặc bản
ghi bị đóng sớm. Chưa nên code tới khi rõ.

Nội dung yêu cầu gồm 2 phần:
1. Copy từ social sang tự chèn link theo từng hashtag → người đăng phải gỡ
   thủ công từng cái. Và hashtag đang hiển thị 2 kiểu thiết kế, cần gom về 1.
2. Chia sẻ bài dạng ảnh/video không hiện ảnh bìa.

## 4. Điểm cần xác nhận với người yêu cầu

1. **STT 8** — công việc bắt đầu thì danh mục bắt đầu theo: vậy khi **tất cả**
   công việc trong danh mục hoàn thành thì danh mục có tự hoàn thành không?
   Plan này mặc định **không** (chỉ lan chiều bắt đầu) để tránh tự đổi dữ liệu
   ngoài ý muốn.
2. **STT 11/7** — dropdown trạng thái tại bảng: theo CLAUDE.md §14 nên dùng
   **double-click** hoặc nút mũi tên riêng, không phải click 1 lần, để tránh
   đổi nhầm khi chỉ muốn xem. Cần anh Đức xác nhận thao tác nào tiện hơn.
3. **STT 25** — xác nhận lại đã làm chưa (xem 3.6).
4. **STT 23** — yêu cầu gộp 2 việc khác nhau: (a) đổi trang "Quy trình" thành
   hướng dẫn sử dụng + giải thích khái niệm, (b) tour tự động theo quyền cho
   người mới. Bản ghi đang "Đã duyệt" — cần tách 2 bản ghi riêng.

## 5. Đồng bộ lại bảng ghi nhận

Bảng ghi nhận hiện lệch với code ở cả 2 chiều:

- **10 mục đã làm nhưng còn treo** (phần 2) — đang ở *Chờ ghi nhận* / *Đang
  xem xét*, cần chuyển sang *Đã duyệt/Hoàn thành*.
- **1 mục đã đóng nhưng chưa có code** — STT 25 (xem 3.6).

Sau khi sửa xong phần 3, cập nhật tiếp các bản ghi tương ứng. Cột "Số ngày chờ
xử lý" đang lên tới 10 ngày ở nhiều mục chỉ vì chưa ai đóng bản ghi, không phải
vì chưa làm — việc đồng bộ này làm số liệu phản ánh đúng.

## 6. Ngoài phạm vi — đề xuất tính năng lớn, cần plan riêng

| STT | Nội dung | Ghi chú |
|---|---|---|
| 5 | Dashboard Tổng Giám đốc: doanh thu, dòng tiền, OKR, turnover, công nợ, chi phí vận hành, chỉ số phụ huynh, khung cảnh báo | Rất lớn. Phần lớn chỉ số **chưa có nguồn dữ liệu** trong workspace — cần VA-HRM + hệ tài chính. Phải chốt nguồn số trước khi thiết kế UI |
| 2 | Đồng bộ 2 chiều Google Calendar + tuỳ chọn phạm vi đồng bộ | Cần OAuth Google, job đồng bộ, xử lý xung đột 2 chiều. Plan riêng |
| 4 | Dự án: gán nhãn mức độ, cảnh báo rủi ro/chậm tiến độ, lọc theo sức khoẻ/tiến trình/cấp quản lý/khu vực | Có thể tách: phần lọc & nhãn làm được sớm; phần cảnh báo rủi ro cần định nghĩa công thức tính sức khoẻ |
| 1 | Công việc: lọc theo vai trò JD, lọc sức khoẻ công việc, gán nhãn, lọc cấp quản lý | Cần định nghĩa "vai trò của tôi" (phê duyệt / giao việc / được tag) theo dữ liệu hiện có |
| 22 | Đặt lịch đăng bài trên bảng tin | Nhỏ hơn nhóm trên nhưng vẫn cần migration: `SocialPost` **chưa có** `scheduled_at` (`Modules/Social/App/Models/SocialPost.php:54-74`), cần thêm cột + job đăng theo giờ |
| 23 | Trang hướng dẫn sử dụng + tour onboarding theo quyền | Xem mục 4.4 — cần tách bản ghi |
| 24 | Rà soát lại bảng phân quyền cho khớp sidebar thật | Đang "Đã duyệt". Liên quan CLAUDE.md §16 (đồng bộ sidebar ↔ `GlobalMenuVisibilityService::CATALOG`) |

## 7. Thứ tự triển khai đề xuất

1. **STT 10** (z-index panel header) — lỗi rõ, ảnh hưởng mọi trang, sửa gọn.
2. **STT 3** (nút Lịch) — xoá 1 instance trùng, rất gọn.
3. **STT 11 + 7** (dropdown trạng thái) — kèm dọn vi phạm pill §14.
4. **STT 9** (cột danh mục) — chỉ thêm cột, dữ liệu đã có.
5. **STT 8** (lan trạng thái lên danh mục) — cần chốt câu hỏi 4.1 trước.
6. **Đồng bộ bảng ghi nhận** (phần 5).
7. **STT 25** — sau khi xác nhận 3.6.

Bước 1–4 độc lập nhau, làm song song được. Bước 5 chờ xác nhận.

## 8. Rủi ro

- **STT 10**: sửa `z-index` + đóng panel chéo nhau chạm vào 6 component dùng ở
  mọi trang. Nếu composable đóng panel sai, có thể panel tự đóng khi người dùng
  bấm vào trong chính nó. Cần soát kỹ `pointerdown` hiện có ở từng component.
- **STT 8**: lan trạng thái lên cha là thay đổi **ghi dữ liệu tự động** —
  nếu lan sai sẽ đổi trạng thái nhiều phase ngoài ý muốn. Bắt buộc có test
  trước khi lên production.
- **STT 11/7**: đổi trạng thái ngay tại bảng làm tăng rủi ro đổi nhầm. Đó là lý
  do CLAUDE.md §14 yêu cầu double-click — đừng bỏ qua để "cho nhanh".
