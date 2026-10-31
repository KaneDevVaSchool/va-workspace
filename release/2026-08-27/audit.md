# Đối soát 27/08/2026

Đọc nhanh: [README.md](./README.md) · Tuần: [2026-W35](../summary/2026-W35-summary.md) · Bảng tuần: [2026-W35-audit](../summary/2026-W35-audit.md)

Việc trong ngày đánh số version **1.1**, **1.2**, … Mỗi việc một bảng Đổi gì / Vì sao / Tác dụng. Thiếu trong commit thì ghi «chưa ghi», không suy diễn.

## Việc trong ngày

### 1.1. Thêm màu tertiary và gold vào theme.css

Giao diện gốc · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Thêm màu tertiary và gold vào theme.css. Bổ sung 2 thang màu mới (--color-tertiary-*, xanh dương #1750b5 và --color-gold-*, vàng đồng #c49c57) để phối cùng primary/secondary, chưa gán dùng ở component nào.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | để phối cùng primary/secondary, chưa gán dùng ở component nào |
| **Tác dụng** | phối cùng primary/secondary, chưa gán dùng ở component nào |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 13:11 | `6aab04a` | Thêm | css/theme.css |

### 1.2. Sửa toggle ẩn/hiện menu toàn hệ thống và bỏ hint Brave không dùng

Cấu hình workspace · Cấu hình menu · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa toggle ẩn/hiện menu toàn hệ thống và bỏ hint Brave không dùng. - WorkspaceConfigGlobalMenuVisibilitySuperadmin.vue: nextHidden đang lấy   !is_visible sai chiều, sửa lại dùng thẳng is_visible (is_hidden ngược   nghĩa với is_visible). Thêm test hide/show menu 'home' để chặn regression. - HeaderNotifications.vue: bỏ isBraveBrowser không còn dùng tới trong   pushHint, tránh nhánh cảnh báo Brave lỗi thời.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | để chặn regression |
| **Tác dụng** | chặn regression |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 13:15 | `cc4bcab` | Sửa | WorkspaceConfig/WorkspaceConfigGlobalMenuVisibilitySuperadmin.vue, components/HeaderNotifications.vue, WorkspaceConfig/WorkspaceConfigGlobalMenuVisibilityTest.php |

### 1.3. Bỏ CRUD tay Vị trí đánh giá, đổi trọng số sang %, gộp field tùy biến

Đánh giá · Tiêu chí đánh giá · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Bỏ CRUD tay Vị trí đánh giá, đổi trọng số sang %, gộp field tùy biến. - EvaluationPosition: bỏ store/update/destroy (StoreEvaluationPositionRequest,   UpdateEvaluationPositionRequest, Repository/Service tương ứng) — danh mục   vị trí đánh giá chuyển sang chỉ đọc, chờ nối API VA-HRM sau này   (xem [[hrm-employee-sync-future]]). - evaluation_template_criteria: đổi weight_label (nhãn tiếng Việt) +   weight_value (số ẩn 1-4) sang weight_percent (10-100, bước 10, tổng các   dòng tính điểm trong 1 mẫu phải = 100) — migration   change_weight_to_percent_on_evaluation_template_criteria_table. - evaluation_template_custom_fields: field_type rút còn 2 loại   text (chữ) / bonus (điểm phụ thêm), map dữ liệu cũ number→bonus,   select\|date→text — migration change_custom_field_types_to_text_and_bonus. - Cập nhật toàn bộ Controller/Service/Repository/Request/Seeder liên quan   và các component Vue dựng mẫu đánh giá (WorkspaceConfigEvaluation,   EvaluationTemplateCreateDialog/EditDialog, EvaluationCriteriaPicker,   EvaluationCustomFieldsEditor, EvaluationPositionPicker, EvaluationView,   EvaluationTemplateList) theo field mới. - Cập nhật docs/modules/Evaluation.md và test liên quan.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi bảng mẫu đánh giá; Đổi cấu trúc dữ liệu |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 13:15 | `248f68d` | Cấu trúc lại | Evaluation/EvaluationCriteriaController.php, Evaluation/EvaluationPositionController.php, Evaluation/StoreEvaluationPositionRequest.php, Evaluation/StoreEvaluationTemplateRequest.php (+24) |

### 1.4. Thêm module Dự án (giai đoạn 1 — CRUD)

Dự án và công việc · Công việc và dự án, Cấu hình menu · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Thêm module Dự án (giai đoạn 1 — CRUD). Module mới Modules/Project theo đúng Controller → Service → Repository: - Project, ProjectScope, ProjectFollower, ProjectLabel (+ gán nhãn),   ProjectAttachment, ProjectSetting, ProjectCreatorAllowlist. - ProjectController: CRUD dự án, quản lý nhãn/thành viên/tệp đính kèm/avatar,   cấu hình dự án (mã, quy tắc hoạt động, danh sách nhân sự được phép tạo). - AutoStartProjectsCommand: job tự chuyển trạng thái dự án theo lịch. - Quyền mới project.manage_settings (config/permissions.php) — cấu hình   dự án toàn hệ thống, chỉ admin/super_admin. - Nối vào hệ thống: sidebar (AppSidebar.vue, mục "Dự án" nhóm Quản lý,   configurableByDepartment), router SPA (router/index.js), menu theo   phòng ban (DepartmentSidebarConfigService), DatabaseSeeder,   modules_statuses.json. - Test: ProjectVisibilityTest, ProjectSettingsTest.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 13:15 | `8e29655` | Thêm | Project/AutoStartProjectsCommand.php, Project/ProjectEnums.php, Project/ProjectOwnerDepartmentMissing.php, Project/ProjectController.php (+49) |

