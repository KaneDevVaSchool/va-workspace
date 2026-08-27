# Module `Evaluation` — Tiêu chí đánh giá & Mẫu đánh giá

> Cập nhật: 2026-08-26. Module **đã dựng và chạy** — Giai đoạn B (Tiêu chí
> đánh giá) và Giai đoạn C (Mẫu đánh giá, PR1–PR6) đều hoàn tất. Xem
> `docs/VA_WORKSPACE_OVERVIEW.md` §7, §21 và kế hoạch chi tiết Giai đoạn C:
> `plans/2026-08-26-mau-danh-gia.md`. Giai đoạn D (phiếu đánh giá thực tế,
> hội đồng, kỳ đánh giá) chưa làm.

## 1. Vị trí trong hệ thống

- Route JSON: `Modules/Evaluation/routes/manager.php` → đăng ký qua
  `EvaluationServiceProvider` với middleware `web` + prefix `/api`
  (`name('api.evaluation.')`), giống `WorkspaceConfig` — **không** dùng
  `routes/api.php` stateless.
- Route Vue:
  - **Tiêu chí đánh giá** (Giai đoạn B) — **không** có route Vue riêng của
    module này. Được đăng ký lồng trong
    `Modules/WorkspaceConfig/resources/js/router.js`:
    `/manager/evaluation` (trang riêng, mọi thành viên có phòng ban xem
    được — `EvaluationView.vue`) và tab
    `/manager/workspace-config/evaluation` trong `WorkspaceConfigHub.vue`
    (trưởng phòng quản lý — `WorkspaceConfigEvaluation.vue`), cộng
    `/superadmin/workspace-config/departments/:id/evaluation` (chỉ xem,
    `WorkspaceConfigDepartmentEvaluationSuperadmin.vue`).
  - **Mẫu đánh giá** (Giai đoạn C) — CÓ `Modules/Evaluation/resources/js/router.js`
    riêng: `/manager/evaluation-templates`
    (`EvaluationTemplateList.vue`), mục **sidebar riêng** trong
    `resources/js/components/AppSidebar.vue` (nhóm "Quản lý"), khác hẳn
    nguyên tắc "không sidebar riêng" của Giai đoạn B — quyết định có chủ
    đích vì mẫu là entity độc lập (có thể dùng chung nhiều phòng ban).
    Route guard `meta.requiresPermission` (mới thêm vào
    `resources/js/router/index.js`) chặn truy cập URL trực tiếp ngoài
    `department_director`/`deputy_department_director`.

## 2. Kiến trúc — Controller → Service → Repository

Theo đúng pattern bắt buộc (§5 CLAUDE.md), không có ngoại lệ:

| Domain | Controller | Service | Repository interface |
|---|---|---|---|
| Loại tiêu chí | `EvaluationCriterionTypeController` | `EvaluationCriterionTypeService` | `EvaluationCriterionTypeRepositoryInterface` |
| Tiêu chí đánh giá (Giai đoạn B) | `EvaluationCriteriaController` | `EvaluationCriteriaService` | `EvaluationCriteriaRepositoryInterface` |
| Mẫu đánh giá (Giai đoạn C) | `EvaluationTemplateController` | `EvaluationTemplateService` | `EvaluationTemplateRepositoryInterface` |
| Vị trí đánh giá (Giai đoạn C, PR3) | `EvaluationPositionController` | `EvaluationPositionService` | `EvaluationPositionRepositoryInterface` |

Service phụ trợ: `EvaluationCriteriaExcelExporter`/`Importer` (xuất/nhập
tiêu chí), `EvaluationTemplateExcelExporter` (CHỈ xuất mẫu — không có
chiều nhập lại, xem §5).

Binding interface → implementation:
`Modules/Evaluation/App/Providers/EvaluationServiceProvider.php`.

## 3. Model & bảng dữ liệu (tiền tố `va_workspace_` tự động)

| Bảng | Model | Ghi chú |
|---|---|---|
| `evaluation_criterion_types` | `EvaluationCriterionType` | Loại tiêu chí, scoped theo `department_id` (VD "Thái độ", "Kỹ năng"). |
| `evaluation_criteria` | `EvaluationCriteria` | Tiêu chí, scoped theo `department_id`. 2 kiểu (`type`): `scale` (thang điểm nhiều mức) / `behavior` (cộng-trừ theo hành vi). `levels` JSON. `use_in_evaluation` (hiện trên trang ĐGNL cá nhân). |
| `evaluation_templates` | `EvaluationTemplate` | Mẫu đánh giá. `department_id` = phòng ban tạo ra mẫu (giữ nguyên kể cả khi `is_global`). `code` tự sinh `EVT-0001`. `is_global` — dùng chung toàn hệ thống, reserved `evaluation.manage_global_template`. |
| `evaluation_template_criteria` | `EvaluationTemplateCriterion` | Pivot N-N `evaluation_templates` ↔ `evaluation_criteria`, mang `weight_percent` (trọng số % riêng theo từng mẫu, 10-100 bước 10, tổng các dòng trong 1 mẫu phải = 100), `required_score`, `count_in_total`. |
| `evaluation_positions` | `EvaluationPosition` | "Vị trí đánh giá" — danh mục chức danh **dùng chung toàn hệ thống** (không scoped phòng ban). `hrm_position_uuid` chỉ đối chiếu, không phải nguồn sự thật. |
| `evaluation_template_positions` | — (pivot, không có Model riêng) | N-N `evaluation_templates` ↔ `evaluation_positions`. |
| `evaluation_template_custom_fields` | `EvaluationTemplateCustomField` | "Trường tùy biến" — chỉ định nghĩa field (`label`, `field_type` text/number/select/date, `options` JSON, `is_required`), CHƯA có UI nhập giá trị thật (chờ phiếu Giai đoạn D). |

**Không có Repository nào query trực tiếp bảng của module khác** — Evaluation
chỉ phụ thuộc `Identity` (User/Department) qua model dùng chung.

## 4. Business rule quan trọng

- **Tiêu chí cho mẫu global**: mẫu thường (`is_global = false`) chỉ được
  chọn tiêu chí `is_active = true` cùng `department_id`; mẫu global được
  chọn tiêu chí active của **bất kỳ phòng ban nào** — validate ở tầng
  Service (`EvaluationTemplateService::buildCriteriaRows()`), không chỉ
  chặn ở UI. Endpoint riêng `GET /api/evaluation/templates/global-criteria-pool`
  (reserved `evaluation.manage_global_template`) trả tiêu chí active mọi
  phòng ban kèm tên phòng ban nguồn, dùng khi build/sửa mẫu global.
- **Xoá tiêu chí/vị trí đang dùng trong mẫu**: không chặn xoá cứng ở phía
  `evaluation_criteria`/`evaluation_positions` (FK `cascadeOnDelete` trên
  bảng pivot) — tắt `is_active` một tiêu chí đang dùng trong mẫu global
  (thuộc phòng ban khác) vẫn hợp lệ, chỉ ẩn khỏi lần tính điểm tiếp theo.
- **Nhân bản mẫu** (`EvaluationTemplateService::duplicate()`): copy toàn bộ
  tiêu chí + trọng số + vị trí + trường tùy biến sang mẫu mới cùng phòng
  ban người thao tác; mẫu nhân bản luôn `is_global = false` (không tự động
  kế thừa trạng thái dùng chung).
- **Mã mẫu tự sinh**: `EVT-{4 chữ số zero-pad}`, tăng dần toàn hệ thống
  (không theo từng phòng ban), sinh trong `DB::transaction` + lock để tránh
  trùng khi ghi đồng thời.

## 5. Import/Export — khác nhau giữa 2 entity

- **Tiêu chí đánh giá**: có cả Export (Excel + PDF) và Import theo mô hình
  **preview/confirm 2 bước** (`POST /criteria/import/preview` đọc + validate
  từng dòng không ghi DB, `POST /criteria/import/confirm` ghi DB thật từ
  dữ liệu đã duyệt) — vì tiêu chí phẳng, 1 dòng Excel = 1 bản ghi.
- **Mẫu đánh giá**: **CHỈ Export** (`GET /templates/export`), quyết định có
  chủ đích 2026-08-26 (xem `plans/2026-08-26-mau-danh-gia.md` §7 PR6) —
  mẫu có cấu trúc lồng nhau (N-N tiêu chí kèm trọng số riêng, N-N vị trí,
  trường tùy biến JSON) không phẳng an toàn thành 1 dòng Excel để nhập lại;
  rủi ro sai lệch cao hơn lợi ích ở giai đoạn này. Tiêu chí/vị trí/trường
  tùy biến của mỗi mẫu gộp thành text mô tả trong cùng 1 dòng khi xuất.

Cả hai dùng chung `phpoffice/phpspreadsheet` (đã có sẵn trong
`composer.json`), cùng khuôn mẫu visual (`EvaluationCriteriaExcelExporter`
là gốc, `EvaluationTemplateExcelExporter` copy layout).

## 6. Quyền

| Quyền | Ai có | Ghi chú |
|---|---|---|
| `evaluation.manage_department` | `department_director`, `deputy_department_director` (+ `admin`/`super_admin` qua `evaluation.*`/`*`) | CRUD tiêu chí, mẫu, vị trí đánh giá trong phạm vi phòng ban mình. |
| `evaluation.manage_global_template` | `department_director`, `deputy_department_director` (+ `admin`/`super_admin`) | Đánh dấu mẫu `is_global`, xem `global-criteria-pool`. **Không** cấp `section_head`/`team_lead`. |
| `workspace.evaluation.*` | Reserved — chỉ `super_admin` | Catalog hệ thống, chưa dùng ở Giai đoạn B/C hiện tại. |
| `workspace_config.view_all` | Reserved — chỉ `super_admin` | Xem tổng hợp tiêu chí/mẫu mọi phòng ban (chỉ xem, không sửa thay). |

## 7. UI đáng chú ý

- **Tiêu chí đánh giá** (`WorkspaceConfigEvaluation.vue`): mẫu vàng cho
  skill `form-modal` (dialog Thêm tiêu chí — lưới ngang 2-3 cột, gần
  full-screen).
- **Mẫu đánh giá** (`EvaluationTemplateList.vue`): áp dụng cả 2 skill
  `data-table` (TablePagesBar trên/dưới, cột kéo được, panel chi tiết đẩy
  ngang) và `form-modal`. Tuân CLAUDE.md §14: chấm màu thay badge/pill,
  field ngay hàng ở panel chi tiết, trọng số hiển thị chữ tiếng Việt
  ("Khá quan trọng"...) không lộ số.
