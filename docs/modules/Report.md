# Module `Report` — Báo cáo vận hành phòng ban

> Cập nhật: 2026-08-31. Module **đã dựng** — đợt đầu chỉ có loại báo cáo
> "Đánh giá nhân sự". Xem thêm `docs/modules/Evaluation.md` (nguồn cấu hình
> chấm điểm) và `docs/VA_WORKSPACE_OVERVIEW.md` §7.
>
> Chưa làm ở đợt này: xuất Excel/PDF/CSV, 5 loại báo cáo còn lại (công việc
> phòng ban, công việc cá nhân, dự án theo tháng, quản trị dự án, timesheet),
> kéo thả đổi thứ tự cột.

## 1. Vị trí trong hệ thống

- Route JSON: `Modules/Report/routes/manager.php` → đăng ký qua
  `ReportServiceProvider` với middleware `web` + prefix `/api`
  (`name('api.report.')`), giống `Evaluation` / `WorkspaceConfig` — **không**
  dùng `routes/api.php` stateless.
- Route Vue: `Modules/Report/resources/js/router.js` — 3 trang, mục
  **sidebar riêng** "Báo cáo" trong `resources/js/components/AppSidebar.vue`
  (nhóm "Quản lý", `configurableByDepartment`).

| Đường dẫn | Trang | Ai vào được |
|---|---|---|
| `/manager/reports` | `ReportList.vue` | `report.manage_department` hoặc `report.view_assigned` |
| `/manager/reports/personnel-evaluation/new` | `ReportCreatePersonnelEvaluation.vue` | `report.manage_department` |
| `/manager/reports/:id` | `ReportView.vue` | như trang danh sách, lọc thật ở backend |

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
| `reports` | `Report` | Cấu hình 1 báo cáo. `evaluation_config_version_id` chốt ngay lúc tạo và **không đổi** — đây là thứ giữ cho điểm báo cáo cũ không chạy theo khung chấm điểm mới. `status`: `draft` (còn sửa được kỳ) → `saved` (khoá kỳ, chỉ đổi được tên). |
| `report_viewers` | `ReportViewer` | Người được chia sẻ quyền xem. Người quản lý phòng ban luôn xem được, không cần có mặt ở đây. |
| `report_filters` | `ReportFilter` | Thu hẹp phạm vi; đợt đầu chỉ dùng khoá `user_id`. Không có dòng nào = tính cho toàn phòng ban. |
| `report_columns` | `ReportColumn` | Cột được bật trong bảng. `sort_order` theo thứ tự cố định do `ReportService::EVALUATION_COLUMNS` định nghĩa (đợt đầu không cho kéo thả). |
| `report_criteria` | `ReportCriterion` | Tiêu chí hành vi được hiện ở phần chi tiết nhân sự. Không chọn dòng nào = hiện toàn bộ. |
| `report_people_snapshots` | `ReportPersonSnapshot` | Danh sách nhân sự đã chụp lúc báo cáo chuyển sang `saved`. **Không** có khoá ngoại tới `users` và lưu sẵn tên: bản chụp phải sống sót cả khi tài khoản bị xoá. Báo cáo `draft` vẫn lấy nhân sự động; `saved` đọc từ bảng này. |

## 4. Quyền

| Quyền | Ai có | Ghi chú |
|---|---|---|
| `report.manage_department` | `department_director`, `deputy_department_director` (+ `admin`/`super_admin` qua `report.*`/`*`) | Tạo, sửa, xoá và xem mọi báo cáo của phòng ban mình. |
| `report.view_assigned` | `section_head`, `team_lead`, `member`, `viewer` | Chỉ thấy báo cáo có tên mình trong `report_viewers` — lọc ở `ReportService::listVisible()`, không chỉ ẩn giao diện. |
| `report.*` | `admin` | Toàn bộ báo cáo mọi phòng ban. |

Kiểm tra quyền nằm trong Controller qua `PermissionService::allows()` và
`ReportService::canView()` / `canManage()`, đúng pattern
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
`summarize()` cộng dồn cho cả phòng ban. `ReportView.vue` hiện một dòng cảnh
báo khi có — không có dòng này thì người xem tưởng mọi thứ đã tính đủ.

Kết quả chấm chất lượng (`TaskScore.rating_result`) là ô nhập tay nên engine
khớp hai vòng: đúng nguyên văn trước, không được mới khớp lỏng (bỏ dấu, bỏ
hoa thường, gom khoảng trắng). Gõ hẳn chữ khác thang thì đếm vào `missing`.

## 6. UI đáng chú ý

Cấu hình bảng dùng chung ở `Modules/Report/resources/js/constants/report.js`
(theo mẫu `Modules/Identity/resources/js/constants/activity.js`):
`loadVisibility` / `saveVisibility` cho bật-tắt cột và bộ lọc,
`loadColumnWidths` cho độ rộng đã kéo, `loadZoom` / `saveZoom` cho cỡ chữ,
`loadSort` / `saveSort` cho cách sắp xếp. Trang Ghi nhận đánh giá của module
`Evaluation` cũng import từ đây (`@modules/Report/...`) để hai trang cùng một
cách nhớ cấu hình.

- **Danh sách báo cáo** (`ReportList.vue`): theo mẫu vàng `data-table`
  (`ActivityLog.vue`) — TablePagesBar trên/dưới với đủ 2 slot `#filters` /
  `#settings`, kéo cột, ẩn thanh cuộn, panel chi tiết đẩy ngang 28rem. Click
  1 lần chỉ xem, mở báo cáo bằng double-click hoặc nút trong panel. Phân
  trang ở **máy chủ** (`GET /api/report?page=&per_page=`, trả `meta`).
- **Tạo báo cáo** (`ReportCreatePersonnelEvaluation.vue`): trang riêng 5
  bước, **không** dùng skill `form-modal` — các bước phụ thuộc dữ liệu tuần
  tự (chọn phòng ban xong mới có danh sách nhân sự / tiêu chí), không hợp
  một màn hình ngang. Rời trang giữa chừng hiện xác nhận
  (`onBeforeRouteLeave`); danh sách nhân sự có ô tìm (bỏ dấu) và nút chọn
  tất cả / bỏ chọn tất cả; bước cuối gọi
  `POST /api/report/personnel-evaluation/preview` để xem trước số liệu thật
  — endpoint này **không ghi gì** và không chốt phiên bản mới.
- **Xem báo cáo** (`ReportView.vue`): số tổng hợp + phân bổ xếp loại dạng
  chữ thường kèm chấm màu nhỏ (không dùng badge/pill), bảng theo nhân sự,
  panel chi tiết tách rõ "đóng góp từ công việc" và "ghi nhận theo hành vi".
  Bấm tiêu đề cột để đổi cách xếp (mặc định điểm cuối giảm dần, nhớ qua
  localStorage). Mỗi mức xếp loại một màu chấm riêng, gán theo **thứ tự
  trong thang** chứ không theo tên mức. Có `@media print` để in được trước
  khi có xuất file — phần ẩn vỏ ứng dụng nằm ở khối `<style>` không scoped.
