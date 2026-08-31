# Module `Evaluation` — Tiêu chí đánh giá

> Cập nhật: 2026-08-31. Module **đã dựng và chạy** — Tiêu chí đánh giá và
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
| Tổng hợp đánh giá | `EvaluationSummaryController` | `EvaluationSummaryService` | *(không có repository riêng — chỉ điều phối)* |

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
| `evaluation_score_kits` | `EvaluationScoreKit` | Engine chấm điểm theo phòng ban. **Cách 1** (`base_adjust`, đếm số việc): điểm gốc ± (số việc × điểm mỗi việc). Thang xếp loại do phòng tự đặt (`code` + `label` + `min_score` + `sort_order`, 2–12 mức). **Cách 2** (`weighted_task`): điểm chuẩn = cơ bản × độ khó; điểm thực = chuẩn × tiến độ × chất lượng; hiệu suất = Σ thực / Σ chuẩn × 100%. Các phương pháp khác (sự kiện/hành vi, KPI, kết hợp) dùng chung engine, chưa mở UI. |

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
  khó tạo chuẩn, tiến độ và chất lượng tạo điểm thực; case study một việc
  và thang xếp loại theo %.
- **Tổng hợp đánh giá** (`EvaluationSummary.vue`): màn hình làm việc chính khi
  chấm điểm cuối kỳ. Chọn kỳ (tháng / tuần / ngày / khoảng ngày — mặc định
  tháng) rồi xem toàn bộ nhân sự phòng ban trên **một bảng tiêu đề 2 tầng**:
  nhóm cột công việc (Tổng / Đang thực hiện / Hoàn thành, mỗi nhóm có Số
  lượng · Đúng hạn · Quá hạn), nhóm cột theo từng tiêu chí hành vi, và nhóm
  cột điểm (Khởi đầu · Công việc · Cộng · Trừ · Điểm cuối · Xếp loại).
  Route `/manager/evaluation-events` (giữ nguyên path và route name cũ để
  sidebar / phân quyền / cấu hình sidebar phòng ban không phải đổi theo).

  Mở rộng một dòng (nút mũi tên đầu dòng, hoặc double-click) hiện **từng công
  việc** kèm tên, dự án, hạn, ngày hoàn thành, tình trạng đúng hạn và điểm
  đóng góp — **ghi nhận đánh giá diễn ra ngay trên dòng công việc đó** (trước
  đây là danh sách phẳng + modal riêng, nay đã bỏ). Cuối phần chi tiết có ô
  ghi nhận không gắn công việc (`task_id = null`).

  `GET /api/evaluation/summary?from=&to=` trả `rows` (mỗi nhân sự một dòng,
  kèm `task_status_counts`, `criterion_totals`, `task_breakdown`,
  `event_breakdown`), `summary`, `criteria` và `version_no`. **Không phân
  trang** — bảng cần tổng của cả phòng ban nên lọc / sắp xếp ở client.

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
