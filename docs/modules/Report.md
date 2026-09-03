# Module `Report` — Báo cáo vận hành phòng ban

> Cập nhật: 2026-09-03. Module **đã dựng** — đợt đầu chỉ có loại báo cáo
> "Đánh giá nhân sự". Xem thêm `docs/modules/Evaluation.md` (nguồn cấu hình
> chấm điểm) và `docs/VA_WORKSPACE_OVERVIEW.md` §7.
>
> Xuất PDF bảng đánh giá nhân sự từ danh sách báo cáo đã lưu (nút **Xuất PDF**
> trên `ReportList.vue`) và trên trang chi tiết/chấm điểm
> (`/manager/evaluation-events`, `GET /api/evaluation/summary/export-pdf`) —
> form ngang A3, dấu chìm VA, phiếu chi tiết từng người. CSV vẫn xuất từ
> trình duyệt trên trang ma trận. Chưa làm: xuất Excel từ danh sách báo cáo, kéo thả đổi thứ tự
> cột, và phần tính số liệu của 5 loại báo cáo còn lại — 5 loại đó đã có
> tên/mô tả trong danh mục (`Report::TYPES_COMING_SOON` + `REPORT_TYPES` ở
> frontend) và hiện trong hộp thoại chọn loại dưới dạng "Sắp ra mắt", chưa
> bấm tạo được.

## 1. Vị trí trong hệ thống

- Route JSON: `Modules/Report/routes/manager.php` → đăng ký qua
  `ReportServiceProvider` với middleware `web` + prefix `/api`
  (`name('api.report.')`), giống `Evaluation` / `WorkspaceConfig` — **không**
  dùng `routes/api.php` stateless.
- Route Vue: `Modules/Report/resources/js/router.js` — 3 route, mục
  **sidebar riêng** "Báo cáo" trong `resources/js/components/AppSidebar.vue`
  (nhóm "Quản lý", `configurableByDepartment`). `/manager/reports` và
  `/manager/reports/list` cùng render `ReportList.vue` (route đầu là lối vào
  từ sidebar, route sau là đích điều hướng nội bộ sau khi tạo/huỷ báo cáo).

| Đường dẫn | Trang | Ai vào được |
|---|---|---|
| `/manager/reports` | `ReportList.vue` (danh sách báo cáo đã lưu) | `report.manage_department` hoặc `report.view_assigned` |
| `/manager/reports/list` | `ReportList.vue` (danh sách báo cáo đã lưu) | `report.manage_department` hoặc `report.view_assigned` |
| `/manager/reports/personnel-evaluation/new` | `ReportCreatePersonnelEvaluation.vue` | `report.manage_department` |

> Đã bỏ trang xem chi tiết báo cáo (`ReportView.vue`, route
> `manager.reports.show`) cùng các endpoint chỉ phục vụ nó (`GET
> /api/report/{id}`, `GET /api/report/{id}/employees/{userId}`, `PUT
> /api/report/{id}`). **Chốt lưu** (`PATCH /api/report/{id}/save`) làm trên
> bảng **Đánh giá nhân sự** — chuyển `draft` → `saved`, khoá ghi nhận trong
> kỳ, chụp phạm vi nhân sự. Đổi cột tiêu chí (chỉ khi còn nháp) dùng
> `PATCH /api/report/{id}/display` (phụ lục 1.1+).

## 2. Kiến trúc — Controller → Service → Repository

| Domain | Controller | Service | Repository interface |
|---|---|---|---|
| Báo cáo | `ReportController` | `ReportService` | `ReportRepositoryInterface` |

Binding interface → implementation:
`Modules/Report/App/Providers/ReportServiceProvider.php`.

`ReportService` gọi sang `Modules\Evaluation`:
`EvaluationConfigVersionService` (chốt / đọc phiên bản cấu hình) và
`EvaluationScoreComputeService` (tính điểm) — gọi qua Service, **không**
query Eloquent chéo module. Danh sách nhân sự lấy qua
`UserRepositoryInterface::allActiveByDepartment()` của `Identity`.

Các bảng phụ (`report_viewers`, `report_filters`, `report_columns`,
`report_criteria`) không có Repository riêng — luôn thao tác cùng báo cáo
cha nên gộp vào `ReportRepository` (`syncViewers`, `syncUserFilters`,
`syncColumns`, `syncCriteria`).

## 3. Model & bảng dữ liệu (tiền tố `va_workspace_` tự động)

| Bảng | Model | Ghi chú |
|---|---|---|
| `reports` | `Report` | Cấu hình 1 báo cáo. `evaluation_config_version_id` chốt ngay lúc tạo và **không đổi** — đây là thứ giữ cho điểm báo cáo cũ không chạy theo khung chấm điểm mới. `status`: `draft` khi mới tạo; chuyển `saved` qua `PATCH /api/report/{id}/save` trên bảng Đánh giá nhân sự (khoá ghi nhận trong kỳ). |
| `report_viewers` | `ReportViewer` | Người được chia sẻ quyền xem. Người quản lý phòng ban luôn xem được, không cần có mặt ở đây. |
| `report_filters` | `ReportFilter` | Thu hẹp phạm vi; đợt đầu chỉ dùng khoá `user_id`. Không có dòng nào = tính cho toàn phòng ban. |
| `report_columns` | `ReportColumn` | Cột được bật trong bảng. `sort_order` theo thứ tự cố định do `ReportService::EVALUATION_COLUMNS` định nghĩa (đợt đầu không cho kéo thả). |
| `report_criteria` | `ReportCriterion` | Tiêu chí hành vi được hiện trên bảng chấm điểm. Không chọn dòng nào lúc tạo = hiện toàn bộ. |
| `report_display_revisions` | `ReportDisplayRevision` | Lịch sử cột điểm / cột tiêu chí. Bản **1.0** ghi lúc tạo; mỗi lần đổi cột trên bảng chấm điểm lưu **phụ lục 1.1+**. `reports.display_revision` trỏ bản đang áp dụng. |
| `report_people_snapshots` | `ReportPersonSnapshot` | Chụp danh sách nhân sự lúc `PATCH /api/report/{id}/save` — báo cáo đã lưu giữ đúng phạm vi dù nhân sự thay đổi sau đó. |

## 4. Quyền

| Quyền | Ai có | Ghi chú |
|---|---|---|
| `report.manage_department` | `department_director`, `deputy_department_director` (+ `admin`/`super_admin` qua `report.*`/`*`) | Tạo và xoá **bản nháp** của phòng ban mình. Báo cáo đã lưu không xoá được (`ReportService::delete()` trả 422). |
| `report.view_assigned` | `section_head`, `team_lead`, `member`, `viewer` | Chỉ thấy báo cáo có tên mình trong `report_viewers` — lọc ở `ReportService::listVisible()`, không chỉ ẩn giao diện. |
| `report.*` | `admin`, `director_officer` | Toàn bộ báo cáo mọi phòng ban. Giám đốc điều hành giám sát toàn hệ thống nên không giới hạn theo `department_id`; kèm theo đó là **xoá được bản nháp** của mọi phòng ban (`canManage()` chỉ xét `report.*`). Báo cáo đã lưu vẫn không xoá được. Hồi quy: `PersonnelEvaluationReportTest::test_director_officer_sees_reports_of_every_department`. |

Kiểm tra quyền nằm trong Controller qua `PermissionService::allows()` và
`ReportService::canManage()`, đúng pattern
`EvaluationScoreKitController`.

## 5. Cách tính điểm báo cáo đánh giá nhân sự

Điểm gộp hai nguồn, theo đúng phiên bản cấu hình đã chốt:

```
điểm cuối = điểm khởi đầu + điều chỉnh từ công việc + điểm cộng − điểm trừ
```

- **Công việc** — `EvaluationScoreComputeService` chạy công thức khung chấm
  điểm của phòng ban trên `Task` / `TaskScore` trong kỳ (theo
  `actual_end_date`, hoặc `end_date` nếu chưa có ngày thực tế). Cách 1
  (`base_adjust`) cộng trừ theo số việc; cách 2 (`weighted_task`) tính hiệu
  suất theo độ khó × tiến độ × chất lượng.
- **Hành vi** — các `evaluation_events` đã duyệt phát sinh trong kỳ; điểm
  dương vào "điểm cộng", điểm âm vào "điểm trừ".

Xếp loại lấy mức đầu tiên (từ cao xuống) mà điểm cuối đạt tới, theo thang
trong bản chụp phiên bản.

### 5.1 Phạm vi phòng ban của công việc

`TaskRepository::forEvaluationPeriod()` nhận thêm `$departmentId` và **bắt
buộc** truyền khi chấm điểm. Không có nó, truy vấn chỉ lọc theo người thực
hiện: nhân viên làm việc cho dự án phòng khác sẽ kéo việc đó vào điểm phòng
mình. Phòng ban của một công việc xác định theo thứ tự:

1. Đã chuyển giao và người nhận chưa từ chối → phòng **nhận**
   (`delegated_to_department_id`); phòng giao không đếm lại việc đó nữa.
2. Việc thuộc dự án → `project.owner_department_id` hoặc
   `executing_department_id`.
3. Việc đứng riêng → `origin_department_id`, không có thì theo phòng ban
   hiện tại của người thực hiện.

Test hồi quy: `tests/Feature/Evaluation/EvaluationScoreDepartmentScopeTest.php`.

### 5.2 Bảng tra độ khó trong bản chụp

Thang độ khó trong khung chấm điểm lưu mã / tên mức (`TB`, `Trung bình`),
còn `task.priority` lưu giá trị đã quy chuẩn của tiêu chí loại công việc
(`important`, `high_priority` — xem `TaskImportanceOptions::mapLevels()`).
Hai bên không nói cùng ngôn ngữ nên `EvaluationConfigVersionService::publish()`
chụp thêm `kit_snapshot['difficulty_lookup']`: bảng gộp **mọi dạng viết** của
cùng một mức → hệ số. Engine tra thẳng bảng đó; bản chụp cũ (chưa có bảng)
rơi về thang độ khó như trước để báo cáo cũ vẫn mở được.

### 5.3 Phần không tính được

Việc thiếu dữ liệu không còn âm thầm nhận hệ số 1.0. `computeForUser()` trả
thêm `missing` (`difficulty` / `progress` / `quality`) và `missing_total`;
`summarize()` cộng dồn cho cả phòng ban.

Trên **Cách 2 schema 2**, việc chưa hoàn thành hoặc thiếu trường bắt buộc
có `actual_score = 0` (vẫn cộng điểm chuẩn vào mẫu số). Snapshot schema 1
giữ công thức cũ nên báo cáo đã lưu không đổi số. Màn tạo báo cáo cảnh báo
số việc bị zero và yêu cầu xác nhận trước khi tạo; vẫn cho lưu sau khi
xác nhận.

Kết quả chấm chất lượng (`TaskScore.rating_result`) là ô nhập tay nên engine
khớp hai vòng: đúng nguyên văn trước, không được mới khớp lỏng (bỏ dấu, bỏ
hoa thường, gom khoảng trắng). Gõ hẳn chữ khác thang thì đếm vào `missing`.

## 6. UI đáng chú ý

Cấu hình bảng dùng chung ở `Modules/Report/resources/js/constants/report.js`
(theo mẫu `Modules/Identity/resources/js/constants/activity.js`):
`loadVisibility` / `saveVisibility` cho bật-tắt cột và bộ lọc,
`loadColumnWidths` cho độ rộng đã kéo, `loadZoom` / `saveZoom` cho cỡ chữ.
Trang Ghi nhận đánh giá của module `Evaluation` cũng import từ đây
(`@modules/Report/...`) để hai trang cùng một cách nhớ cấu hình.

- **Danh sách báo cáo đã lưu** (`ReportList.vue`, `/manager/reports` và
  `/manager/reports/list`): theo mẫu vàng `data-table` (`ActivityLog.vue`) —
  TablePagesBar trên/dưới với đủ 2 slot `#filters` / `#settings`, kéo cột,
  ẩn thanh cuộn, panel chi tiết đẩy ngang 28rem. Phân trang ở **máy chủ**
  (`GET /api/report?page=&per_page=`, trả `meta`). Bảng **nhóm theo ngày tạo**
  (Hôm nay / Hôm qua / thứ + ngày), bấm hàng nhóm để thu gọn. Cột mặc định
  gồm tên, loại, kiểu kỳ, kỳ, phòng ban, phạm vi, người được xem, tình trạng,
  người tạo, lúc tạo — các cột còn lại bật trong **Cột**. Panel chi tiết hiện
  khối tóm tắt + đủ trường bản ghi (mã, kỳ, phạm vi, người xem, cột, tiêu chí,
  người tạo/cập nhật). Báo cáo **Đánh giá nhân sự** còn nháp có **Chấm điểm**
  (dropdown thao tác dòng và panel) mở `/manager/evaluation-events?from=&to=&report=`
  đúng kỳ. Báo cáo **đã lưu** đổi nút đó thành **Chi tiết** (cùng trang, chỉ
  xem), thêm **Xuất PDF**, và **không cho xoá**. Nút **"Tạo báo cáo"** (dấu +) ở header mở modal `report-picker` — lưới 3
  cột liệt kê đủ **6 loại**, loại tạo được xếp trước, loại chưa dựng xếp sau
  và ghi "Sắp ra mắt" (chữ thường nghiêng, không phải badge). Modal **không**
  theo mẫu `data-table`/`form-modal` đầy đủ vì là picker chọn 1 thẻ, không
  phải form nhập liệu hay bảng — overlay + panel gần full viewport
  (`overflow: hidden`, cuộn ở `.report-picker__body`), giống khung
  `EvaluationSummary.vue` — xem `.cursor/rules/data-table.mdc`. Danh mục ở
  `REPORT_TYPES` (`constants/report.js`), đồng bộ thủ công với
  `Report::TYPES` / `Report::TYPES_COMING_SOON`. Backend mới là nơi chặn
  thật: loại chưa có trong `Report::TYPES` thì không có route tạo tương ứng.
- **Tạo báo cáo** (`ReportCreatePersonnelEvaluation.vue`,
  `/manager/reports/personnel-evaluation/new`): wizard 5 bước **cùng bố cục
  trang Thêm dự án** (`ProjectCreate.vue`) — watermark nền, tiêu đề gradient,
  rail timeline dọc bên trái (thanh tiến độ + chấm bước màu theo tông), form
  từng bước trong card, banner “đã đủ bước 1”, thanh Huỷ / Quay lại / Tiếp
  tục / Tạo báo cáo dưới cùng. **Không** dùng skill `form-modal`. Rời trang
  giữa chừng hiện xác nhận (`onBeforeRouteLeave`); danh sách nhân sự ở bước
  **Phạm vi nhân sự** và **Người được xem** là bảng một cột (checkbox +
  người/email), tìm theo tên hoặc email, tick cả dòng, chọn tất cả / bỏ chọn,
  cuộn trong khung (`hide-scrollbar`). Không dùng mẫu vàng `data-table` đầy
  đủ (không `TablePagesBar`, không panel chi tiết) vì đây là picker trong
  wizard, không phải trang danh sách bản ghi.

  Bước **Cột và tiêu chí** cấu hình đúng ma trận
  `EvaluationSummary.vue` (`/manager/evaluation-events`) — đây là màn hình
  báo cáo đánh giá nhân sự, không còn trang xem riêng. Cột điểm trùng hàng
  tiêu đề bảng (Việc, Khởi đầu, Từ việc, Cộng, Trừ, Cuối, Xếp loại; Nhân sự
  luôn hiện). Tiêu chí hành vi nhóm theo loại, tick + tìm như hộp thoại
  “Cột tiêu chí” trên bảng Đánh giá nhân sự; để trống `criterion_ids` = hiện tất cả.
  Bước cuối gọi `POST /api/report/personnel-evaluation/preview` để xem trước
  số liệu thật — endpoint này **không ghi gì** và không chốt phiên bản mới.
  Tạo xong **mở bảng Đánh giá nhân sự** đúng kỳ vừa chọn (`?from=&to=&report=`),
  không về danh sách; trang này không còn bộ chọn khoảng ngày. Cột điểm và cột
  tiêu chí **mapping đúng lựa chọn lúc tạo** (bản **1.0**). Đổi cột tiêu chí trên
  bảng thì lưu **phụ lục 1.1 / 1.2…** — bản gốc không bị ghi đè
  (`PATCH /api/report/{id}/display`, bảng `report_display_revisions`).
