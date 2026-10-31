# Đối soát 31/08/2026

Đọc nhanh: [README.md](./README.md) · Tuần: [2026-W36](../summary/2026-W36-summary.md) · Bảng tuần: [2026-W36-audit](../summary/2026-W36-audit.md)

Việc trong ngày đánh số version **1.1**, **1.2**, … Mỗi việc một bảng Đổi gì / Vì sao / Tác dụng. Thiếu trong commit thì ghi «chưa ghi», không suy diễn.

## Việc trong ngày

### 1.1. Xóa backend + DB tính năng Mẫu đánh giá

Đánh giá · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Xóa backend + DB tính năng Mẫu đánh giá. Xoá toàn bộ Controller/Service/Repository/Model/Request/Seeder của EvaluationTemplate (Giai đoạn C) — chưa từng có phiếu đánh giá thực tế (Giai đoạn D) dùng đến.  - Xoá 5 migration tạo/sửa bảng evaluation_templates và bảng con. - Thêm migration mới drop 4 bảng (evaluation_templates,   evaluation_template_criteria, evaluation_template_custom_fields,   evaluation_template_positions) cho môi trường đã migrate rồi. - Tách evaluation_positions (danh mục Vị trí đánh giá, dùng chung, CHỈ ĐỌC)   khỏi migration cũ — bảng này VẪN GIỮ, chỉ mất quan hệ N-N với mẫu.   EvaluationPosition::templates() bị gỡ theo. - Xoá test EvaluationTemplateSuperAdminTest.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi bảng mẫu đánh giá; Đổi cấu trúc dữ liệu; Đổi bảng mẫu đánh giá; Đổi bảng mẫu đánh giá; Đổi bảng mẫu đánh giá; Đổi cấu trúc dữ liệu; Đổi bảng mẫu đánh giá |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 00:32 | `3085cb5` | Thêm | Evaluation/EvaluationPositionController.php, Evaluation/EvaluationTemplateController.php, Evaluation/ExportEvaluationTemplateRequest.php, Evaluation/StoreEvaluationTemplateRequest.php (+19) |

### 1.2. Gỡ route + Vue page tính năng Mẫu đánh giá

Đánh giá · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Gỡ route + Vue page tính năng Mẫu đánh giá. - Xoá route group /templates/* trong Evaluation manager routes. - Gỡ bind EvaluationTemplateRepositoryInterface khỏi ServiceProvider. - Xoá route Vue Router /manager/evaluation-templates. - Xoá trang EvaluationTemplateList.vue + 2 dialog tạo/sửa mẫu +   EvaluationCriteriaPicker/EvaluationPositionPicker (chỉ dùng riêng cho   2 dialog này, không dùng chỗ khác). - Cập nhật comment guard requiresPermission trong router/index.js sang   ví dụ còn tồn tại (manager.evaluation-score-kit.index).  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 00:32 | `cb87ae7` | Thêm | Evaluation/EvaluationServiceProvider.php, Evaluation/EvaluationCriteriaPicker.vue, Evaluation/EvaluationPositionPicker.vue, Evaluation/EvaluationTemplateCreateDialog.vue (+5) |

### 1.3. Gỡ mục sidebar + quyền Mẫu đánh giá

Cấu hình workspace · Cấu hình đơn từ, Cấu hình menu · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Gỡ mục sidebar + quyền Mẫu đánh giá. - Xoá mục sidebar "Mẫu đánh giá" khỏi AppSidebar.vue, SIDEBAR_MENU_CATALOG,   GlobalMenuVisibilityService::CATALOG,   DepartmentSidebarConfigService::CONFIGURABLE_MENUS/MENU_DEFAULT_SECTIONS. - Xoá quyền evaluation.manage_global_template khỏi config/permissions.php   (role_permissions department_director/deputy + catalog) — không còn   Controller nào check quyền này. Grant cũ (nếu có) trong bảng   permission_grants trở thành dữ liệu chết, không gây lỗi. - Sửa WorkspaceConfigSidebarTest: đổi menu_key ví dụ dùng trong test   reorder từ manager.evaluation-templates.index (đã xoá) sang   manager.evaluation-score-kit.index (vẫn còn, cùng tính chất   configurableByDepartment).  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 00:34 | `1f2cd0e` | Thêm | WorkspaceConfig/DepartmentSidebarConfigService.php, WorkspaceConfig/GlobalMenuVisibilityService.php, WorkspaceConfig/sidebarMenus.js, config/permissions.php (+2) |

### 1.4. Cập nhật tài liệu sau khi xoá tính năng Mẫu đánh giá

Dự án và công việc · Công việc và dự án, Cấu hình menu · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Cập nhật tài liệu sau khi xoá tính năng Mẫu đánh giá. - docs/VA_WORKSPACE_OVERVIEW.md: đổi §7.1 (hệ chấm điểm), ERD, §19 (bảng   lộ trình phase 1e), §21 (bước 2) phản ánh Mẫu đánh giá đã bị xoá khỏi   phạm vi dự án; thêm mục changelog đầu file. - docs/modules/Evaluation.md: viết lại — bỏ toàn bộ nội dung mô tả   EvaluationTemplate, chỉ còn Tiêu chí đánh giá + Vị trí đánh giá + Khung   chấm điểm (hiện trạng thật của module). - plans/2026-08-26-mau-danh-gia.md: thêm ghi chú đầu file — kế hoạch này   chỉ còn giá trị lịch sử, tính năng đã bị xoá. - Modules/WorkspaceConfig/README.md: bỏ đoạn nói Mẫu đánh giá có sidebar   riêng ngoài Hub (không còn đúng). - Modules/Project/README.md, ProjectEnums.php, router.js, migration   projects: sửa các comment ví dụ đang trỏ vào   EvaluationTemplate/EvaluationTemplateRepository/manager.evaluation-   templates.index (đã xoá) sang ví dụ còn tồn tại hoặc bỏ tham chiếu chéo.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 00:39 | `bd923c2` | Tài liệu | Project/ProjectEnums.php, Project/2026_08_27_100001_create_projects_table.php, Project/README.md, Project/router.js (+4) |

