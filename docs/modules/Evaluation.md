# Module `Evaluation` — Tiêu chí đánh giá

> Cập nhật: 2026-09-03. Module **đã dựng và chạy** — Tiêu chí đánh giá và
> Khung chấm điểm. Xem `docs/VA_WORKSPACE_OVERVIEW.md` §7, §21.
>
> Tính năng "Mẫu đánh giá" (gộp nhiều tiêu chí thành 1 bộ có trọng số, mục
> sidebar riêng `/manager/evaluation-templates`) đã bị **xoá khỏi phạm vi dự
> án** (2026-08-31) — chưa từng có phiếu đánh giá thực tế dùng đến. Kế hoạch
> cũ: `plans/2026-08-26-mau-danh-gia.md` (chỉ còn giá trị lịch sử).

## 1. Vị trí trong hệ thống

- Route JSON: `Modules/Evaluation/routes/manager.php` → đăng ký qua
  `EvaluationServiceProvider` với middleware `web` + prefix `/api`
  (`name('api.evaluation.')`), giống `WorkspaceConfig` — **không** dùng
  `routes/api.php` stateless.
- Route Vue:
  - **Tiêu chí đánh giá** — **không** có route Vue riêng của module này.
    Được đăng ký lồng trong `Modules/WorkspaceConfig/resources/js/router.js`:
    `/manager/evaluation` (trang riêng, mọi thành viên có phòng ban xem
    được — `EvaluationView.vue`) và tab
    `/manager/workspace-config/evaluation` trong `WorkspaceConfigHub.vue`
    (trưởng phòng quản lý — `WorkspaceConfigEvaluation.vue`), cộng
    `/superadmin/workspace-config/departments/:id/evaluation` (chỉ xem,
    `WorkspaceConfigDepartmentEvaluationSuperadmin.vue`).
  - **Khung chấm điểm** — CÓ `Modules/Evaluation/resources/js/router.js`
    riêng: `/manager/evaluation-score-kit` (`EvaluationScoreKit.vue`), mục
    **sidebar riêng** trong `resources/js/components/AppSidebar.vue` (nhóm
    "Quản lý").

## 2. Kiến trúc — Controller → Service → Repository

Theo đúng pattern bắt buộc (§5 CLAUDE.md), không có ngoại lệ:

| Domain | Controller | Service | Repository interface |
|---|---|---|---|
| Loại tiêu chí | `EvaluationCriterionTypeController` | `EvaluationCriterionTypeService` | `EvaluationCriterionTypeRepositoryInterface` |
| Tiêu chí đánh giá | `EvaluationCriteriaController` | `EvaluationCriteriaService` | `EvaluationCriteriaRepositoryInterface` |
| Khung chấm điểm | `EvaluationScoreKitController` | `EvaluationScoreKitService` | `EvaluationScoreKitRepositoryInterface` |
| Vị trí đánh giá | `EvaluationPositionController` | `EvaluationPositionService` | `EvaluationPositionRepositoryInterface` |
| Phiên bản cấu hình | `EvaluationConfigVersionController` | `EvaluationConfigVersionService` | `EvaluationConfigVersionRepositoryInterface` |
| Ghi nhận đánh giá | `EvaluationEventController` | `EvaluationEventService` | `EvaluationEventRepositoryInterface` |
| Đánh giá nhân sự | `EvaluationSummaryController` | `EvaluationSummaryService` | *(không có repository riêng — chỉ điều phối)* |

Service không có Controller riêng: `EvaluationScoreComputeService` — chạy
công thức khung chấm điểm trên dữ liệu công việc thật để ra điểm nhân sự,
được `Modules/Report` gọi sang (xem `docs/modules/Report.md`).

Service phụ trợ: `EvaluationCriteriaExcelExporter`/`Importer` (xuất/nhập
tiêu chí).

Binding interface → implementation:
`Modules/Evaluation/App/Providers/EvaluationServiceProvider.php`.

## 3. Model & bảng dữ liệu (tiền tố `va_workspace_` tự động)

| Bảng | Model | Ghi chú |
|---|---|---|
| `evaluation_criterion_types` | `EvaluationCriterionType` | Loại tiêu chí, scoped theo `department_id` (VD "Thái độ", "Kỹ năng"). |
| `evaluation_criteria` | `EvaluationCriteria` | Tiêu chí, scoped theo `department_id`. 2 kiểu (`type`): `scale` (thang điểm nhiều mức) / `behavior` (cộng-trừ theo hành vi). `levels` JSON. `use_in_evaluation` (hiện trên trang ĐGNL cá nhân). |
| `evaluation_positions` | `EvaluationPosition` | "Vị trí đánh giá" — danh mục chức danh **dùng chung toàn hệ thống** (không scoped phòng ban). `hrm_position_uuid` chỉ đối chiếu, không phải nguồn sự thật. CHỈ ĐỌC — chờ nối API VA-HRM. |
| `evaluation_score_kits` | `EvaluationScoreKit` | Engine chấm điểm theo phòng ban. **Cách 1** (`base_adjust`, đếm số việc): điểm gốc ± (số việc × điểm mỗi việc). Thang xếp loại do phòng tự đặt (`code` + `label` + `min_score` + `sort_order`, 2–12 mức). **Cách 2** (`weighted_task`, `kit_schema_version` 2): điểm chuẩn = cơ bản × độ khó; việc chưa xong hoặc thiếu dữ liệu bắt buộc = 0 điểm thực (vẫn nằm trong mẫu số); hiệu suất = Σ thực / Σ chuẩn × 100%; mỗi điểm hành vi = 1 điểm phần trăm. Snapshot schema 1 giữ công thức cũ để báo cáo đã chốt không đổi số. Không tự khớp thang theo tên tiêu chí — chỉ khi phòng chọn ID. Các phương pháp khác (sự kiện/hành vi, KPI, kết hợp) dùng chung engine, chưa mở UI. |

| `evaluation_config_versions` | `EvaluationConfigVersion` | Bản chụp **bất biến** của toàn bộ cấu hình đánh giá 1 phòng ban tại thời điểm chốt (`kit_snapshot` + `criteria_snapshot`). Gộp chung 1 bảng thay vì tách khung chấm điểm / tiêu chí, vì công thức luôn đọc hai thứ cùng nhau. Mỗi phòng tối đa 1 phiên bản `active`; chốt phiên bản mới đẩy phiên bản cũ sang `superseded`. Báo cáo trỏ tới đúng phiên bản dùng lúc tạo nên điểm cũ không đổi khi cấu hình đổi. |
| `evaluation_events` | `EvaluationEvent` | Ghi nhận áp dụng 1 mức tiêu chí **hành vi** cho 1 nhân sự: ai, mức nào, ngày nào, lý do. `score` mang dấu (dương = cộng, âm = trừ). Tên tiêu chí / tên mức / điểm đều chụp lại lúc ghi nhận nên vẫn hiển thị đúng dù danh mục sửa hoặc xoá sau. Người có `evaluation.manage_department` ghi nhận là duyệt luôn; sự kiện đã duyệt bất biến (muốn sửa phải xoá và ghi lại) để không làm lệch báo cáo đã lưu. |

**Không có Repository nào query trực tiếp bảng của module khác** — Evaluation
chỉ phụ thuộc `Identity` (User/Department) qua model dùng chung.
Ngoại lệ có kiểm soát: `EvaluationScoreComputeService` đọc công việc qua
`TaskRepositoryInterface::forEvaluationPeriod()` của `Modules/Project` (qua
interface, không gọi Eloquent trực tiếp).

## 4. Quyền

| Quyền | Ai có | Ghi chú |
|---|---|---|
| `evaluation.manage_department` | `department_director`, `deputy_department_director` (+ `admin`/`super_admin` qua `evaluation.*`/`*`) | CRUD tiêu chí, vị trí đánh giá, khung chấm điểm trong phạm vi phòng ban mình; chốt phiên bản cấu hình; ghi nhận / duyệt đánh giá nhân sự. |
| `workspace.evaluation.*` | Reserved — chỉ `super_admin` | Catalog hệ thống, chưa dùng hiện tại. |
| `workspace_config.view_all` | Reserved — chỉ `super_admin` | Xem tổng hợp tiêu chí mọi phòng ban (chỉ xem, không sửa thay). |

## 5. UI đáng chú ý

- **Tiêu chí đánh giá** (`WorkspaceConfigEvaluation.vue`): mẫu vàng cho
  skill `form-modal` (dialog Thêm tiêu chí — lưới ngang 2-3 cột, gần
  full-screen).
- **Khung chấm điểm** (`EvaluationScoreKit.vue`): engine nhiều phương pháp.
  Cách 1 đếm số việc (mọi việc tính giống nhau); thang xếp loại thêm/bớt/sắp
  xếp, không khóa 5 mức. Cách 2 hiệu suất việc = điểm thực / điểm chuẩn; độ
  khó tạo chuẩn, tiến độ và chất lượng tạo điểm thực. Preview cảnh báo việc
  chưa xong / thiếu dữ liệu = 0 điểm thực; thang 1–5 được chuẩn hoá thành
  hệ số 0.5–1.1 (tiến độ) / 0.5–1.0 (chất lượng) trước khi lưu.
- **Đánh giá nhân sự** (`EvaluationSummary.vue`): màn hình làm việc chính khi
  chấm điểm cuối kỳ, **chỉ trưởng phòng thấy nhân sự phòng mình** —
  `EvaluationEventController` / `EvaluationSummaryController` lấy
  `department_id` từ chính tài khoản đăng nhập, không nhận tham số từ trình
  duyệt, nên không có cách nào xem phòng khác. Route
  `/manager/evaluation-events` (giữ nguyên path và route name cũ). **Không
  phải mục sidebar riêng** — chỉ vào được từ luồng báo cáo module Report:
  tạo báo cáo "Đánh giá nhân sự" (`ReportCreatePersonnelEvaluation.vue`) xong
  chuyển thẳng tới đây, hoặc bấm **Chấm điểm** (bản nháp) / **Chi tiết**
  (đã lưu) trên `ReportList.vue` — cả hai đều kèm `?from=&to=&report=` đúng kỳ
  và đúng báo cáo. Kỳ **không** chọn lại trên trang này (đã chọn lúc tạo báo cáo).
  Cột điểm và cột tiêu chí lấy từ báo cáo (bản 1.0). Đổi cột tiêu chí rồi đóng
  hộp thoại **Cột tiêu chí** thì xác nhận lưu **phụ lục 1.1**. Tiêu đề trang là
  **Đánh giá nhân sự**; báo cáo đã lưu đổi thành **Chi tiết đánh giá nhân sự**.

  Bố cục **ma trận kín khung nhìn**: mỗi nhân sự một hàng, tiêu chí là cột.
  Thead hai hàng gộp nhóm (`Điểm việc` / từng loại tiêu chí / `Kết quả`).
  Nút **Tiêu chí** chọn cột nào hiện (ẩn khi báo cáo đã lưu). Bấm tên mở dialog
  chi tiết nhân sự (Công việc / Ghi nhận). Bấm ô tiêu chí: bản nháp mở modal
  chấm điểm; đã lưu mở chi tiết nhân sự. Tab Chấm điểm chọn
  tiêu chí bằng `OptionPicker` (tên + nhóm), rồi chọn mức, ngày, việc, lý do.
  Hàng chân bảng là trung bình phòng.

  Ghi nhận gắn việc (`task_id`) hoặc không gắn việc (`task_id = null`).
  Mũi tên lên/xuống khi đang mở chi tiết, `Esc` đóng.

  `GET /api/evaluation/summary?from=&to=&report=` trả `rows` (mỗi nhân sự một dòng,
  kèm `task_status_counts`, `criterion_totals`, `task_breakdown`,
  `event_breakdown`, `missing_total`, `has_task_basis`), `summary`,
  `criteria`, `version_no`, `period_lock` và `report` (cột điểm / cột tiêu chí
  đang áp dụng, kèm lịch sử 1.0 / phụ lục 1.1). Máy chủ trả cả phòng.

  `GET /api/evaluation/summary/export-pdf?from=&to=&report=&criterion_ids[]=`
  xuất **PDF ngang A3** cùng số liệu: form tổng hợp (meta 2 hàng × 6 cột, ma trận
  nhóm tiêu chí, chỗ ký duyệt) rồi **phiếu chi tiết từng nhân sự**
  (KPI ngang, điểm theo tiêu chí, việc, ghi nhận). Dấu chìm họa tiết
  `background-logo.png` trên mọi trang — cùng `PdfWatermark` với xuất PDF tiêu chí.
  Nút **Xuất bảng → Xuất PDF** trên `EvaluationSummary.vue`; báo cáo đã lưu
  thêm nút **Xuất PDF** trên header và trên danh sách báo cáo. CSV vẫn còn. **Không**
  dùng mẫu danh sách ActivityLog / `TablePagesBar` — đây là ma trận kín khung,
  không phân trang, không thanh tìm/lọc phía trên bảng. Cách 2 hiện hiệu suất
  bằng `%` (không dấu `+`); việc thiếu dữ liệu gắn badge trên dòng và ghi chú
  «điểm thực 0» trong modal.

  `period_lock.locked` = cả khoảng đang xem nằm trong một báo cáo đánh giá
  nhân sự **đã lưu**. `period_lock.reports` liệt kê báo cáo giao với kỳ
  (kể cả khi chỉ khoá một phần). Ghi nhận / xoá / sửa sự kiện có
  `occurred_at` thuộc kỳ đã lưu bị máy chủ từ chối (422). Báo cáo còn nháp
  không khoá.

  **Không tự cộng điểm ở trình duyệt**: khi ghi nhận / xoá, request gửi kèm
  `period_from` / `period_to`, máy chủ tính lại đúng dòng của nhân sự đó và
  trả về trong khoá `row` để giao diện thay nguyên dòng. Điểm cuối phụ thuộc
  khung chấm điểm, công việc và xếp loại nên cộng trừ ở client sẽ sớm lệch;
  gọi lại cả bảng thì chậm. Trong lúc request bay, các ô chọn bị khoá để bấm
  nhanh hai lần không tạo hai ghi nhận ngoài ý muốn — nếu vẫn trùng nội dung
  với một ghi nhận cùng ngày, phản hồi kèm `duplicate_warning` để cảnh báo
  (không chặn: một hành vi lặp lại trong ngày là chuyện có thật).

## 6. Quan hệ với module Báo cáo

Điểm đánh giá nhân sự **không** tính sẵn và lưu vào bảng nào — mỗi lần mở
báo cáo, `EvaluationScoreComputeService` tính lại từ công việc và các ghi
nhận hành vi trong kỳ, nhưng dùng đúng phiên bản cấu hình mà báo cáo đã chốt
lúc tạo. Chi tiết công thức: `docs/modules/Report.md` §5.

Khi báo cáo đánh giá nhân sự chuyển sang `saved`, kỳ đó khoá ghi nhận trên
bảng tổng hợp (`ReportService::periodLock` / `assertDateWritable`) để số
liệu không lệch với báo cáo đã chốt. Evaluation gọi `ReportService`, không
query bảng `reports` trực tiếp.
