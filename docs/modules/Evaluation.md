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
| `evaluation_score_kits` | `EvaluationScoreKit` | Khung chấm điểm theo phòng ban — cách tính điểm gốc ± theo việc, hoặc theo trọng số khó/dễ và mức dự án. |

**Không có Repository nào query trực tiếp bảng của module khác** — Evaluation
chỉ phụ thuộc `Identity` (User/Department) qua model dùng chung.

## 4. Quyền

| Quyền | Ai có | Ghi chú |
|---|---|---|
| `evaluation.manage_department` | `department_director`, `deputy_department_director` (+ `admin`/`super_admin` qua `evaluation.*`/`*`) | CRUD tiêu chí, vị trí đánh giá, khung chấm điểm trong phạm vi phòng ban mình. |
| `workspace.evaluation.*` | Reserved — chỉ `super_admin` | Catalog hệ thống, chưa dùng hiện tại. |
| `workspace_config.view_all` | Reserved — chỉ `super_admin` | Xem tổng hợp tiêu chí mọi phòng ban (chỉ xem, không sửa thay). |

## 5. UI đáng chú ý

- **Tiêu chí đánh giá** (`WorkspaceConfigEvaluation.vue`): mẫu vàng cho
  skill `form-modal` (dialog Thêm tiêu chí — lưới ngang 2-3 cột, gần
  full-screen).
