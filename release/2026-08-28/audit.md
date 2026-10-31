# Đối soát 28/08/2026

Đọc nhanh: [README.md](./README.md) · Tuần: [2026-W35](../summary/2026-W35-summary.md) · Bảng tuần: [2026-W35-audit](../summary/2026-W35-audit.md)

Việc trong ngày đánh số version **1.1**, **1.2**, … Mỗi việc một bảng Đổi gì / Vì sao / Tác dụng. Thiếu trong commit thì ghi «chưa ghi», không suy diễn.

## Việc trong ngày

### 1.1. Thêm loại dự án, import/export Excel và các picker liên quan

Dự án và công việc · Quyền và nhật ký, Công việc và dự án · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Thêm loại dự án, import/export Excel và các picker liên quan. - Thêm ProjectType (model, migration, StoreProjectTypeRequest, ProjectTypeSelect.vue) - Thêm import/export Excel cho Dự án (ProjectExcelImporter/Exporter, các Request   Import/Export/ConfirmImport/ResolveImportRow) - Thêm ProjectDepartmentPicker, ProjectUserPicker, AppDatePicker dùng chung - Cập nhật ProjectFormFields, ProjectLabelPicker, ProjectScopePicker, ProjectList,   ProjectCreate/Edit/Settings theo trường và luồng mới - Cập nhật Controller/Service/Repository/Enums/Seeder cho Dự án - Sửa nhỏ PermissionMatrix, TablePagesBar, UserAvatarTip, AppIcon, useDragScroll, theme.css - Cập nhật test ProjectSettingsTest, ProjectVisibilityTest |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 01:53 | `d032e51` | Thêm | Identity/PermissionMatrixTable.vue, Identity/PermissionMatrix.vue, Project/ProjectEnums.php, Project/ProjectController.php (+41) |

### 1.2. Menu chuột phải trên bảng dự án, nhân bản dự án, ngày thực tế

Dự án và công việc · Công việc và dự án · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Menu chuột phải trên bảng dự án, nhân bản dự án, ngày thực tế. - Thêm ProjectRowContextMenu.vue: menu chuột phải trên mỗi dòng dự án   trong ProjectList (danh mục công việc, công việc nhanh, phase,   baseline, hồ sơ ký số...). - Thêm ProjectQuickActionModals.vue: modal nhập nhanh cho các loại mục   trên, lưu tạm qua bảng project_quick_items (chờ module Task đầy đủ). - Backend: model/migration ProjectQuickItem (kind: task_category, task,   phase, baseline, signature), Repository + Service +   Controller/route mới (GET/POST /manager/projects/{id}/quick-items). - Thêm chức năng nhân bản dự án (POST /manager/projects/{id}/duplicate)   qua ProjectService::duplicate() — giữ thành viên/nhãn/phạm vi, đổi   tên + hậu tố "(bản sao)". - Thêm actual_start_date/actual_end_date (ngày bắt đầu/kết thúc thực   tế) vào bảng projects, UpdateProjectRequest, Project model. - Viết lại các picker (Department/Label/Member/User/Scope) và   ProjectFormFields/ProjectCreate cho khớp API/luồng mới. - AppIcon.vue: bổ sung icon dùng cho menu chuột phải/modal mới. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 09:44 | `062ff88` | Thêm | Project/ProjectController.php, Project/StoreProjectQuickItemRequest.php, Project/UpdateProjectRequest.php, Project/Project.php (+19) |

### 1.3. Cập nhật VA_WORKSPACE_OVERVIEW.md — Project Giai đoạn 1 + mục nhanh

Tài liệu · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Cập nhật VA_WORKSPACE_OVERVIEW.md — Project Giai đoạn 1 + mục nhanh. - Ghi nhận module Project (CRUD, nhân bản dự án, ngày thực tế, menu   chuột phải tạo nhanh danh mục/công việc/phase/baseline/hồ sơ ký số   qua project_quick_items) trong bảng "Đã có trong repo" (§ đầu),   bản đồ module §2, route map §10, lộ trình §19 (thêm Phase 1f). - Nhấn mạnh Project mới chỉ ở Giai đoạn 1 — chưa có Sprint/Task/   Worklog/Gantt thật, project_quick_items là placeholder chờ module   Task đầy đủ. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 09:45 | `8a5c5b6` | Tài liệu | docs/VA_WORKSPACE_OVERVIEW.md |

### 1.4. Validate ngày thực tế khi tạo dự án, nâng cấp modal Cập nhật thời gian

Dự án và công việc · Công việc và dự án · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Validate ngày thực tế khi tạo dự án, nâng cấp modal Cập nhật thời gian. - StoreProjectRequest: thêm rule actual_start_date/actual_end_date (đã có ở   UpdateProjectRequest nhưng thiếu ở Store, gây lỗi 422 khi tạo dự án kèm   ngày thực tế qua form ngang). - ProjectQuickActionModals (kind=dates): tách 2 khối "Thời gian dự án" /   "Thời gian thực tế", hiện số ngày rút gọn, cảnh báo khi khoảng ngày lệch,   checkbox "Thay đổi thời gian của công việc" (map shift_task_dates_with_project,   placeholder cho tới khi có Task thật), banner/chip đổi sang dot màu theo   quy tắc UI đơn giản thay vì badge nền màu. |
| **Vì sao** | vì badge nền màu |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 09:51 | `40935e8` | Sửa | Project/StoreProjectRequest.php, Project/ProjectQuickActionModals.vue |

### 1.5. PR1 - entity Task thật, migration + Model + Enum (WBS đa cấp)

Dự án và công việc · Công việc và dự án · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | PR1 - entity Task thật, migration + Model + Enum (WBS đa cấp). Project Giai đoạn 2 — bắt đầu thay thế project_quick_items bằng entity Task thật (kế hoạch: docs/VA_WORKSPACE_OVERVIEW.md §16/§19, plan chi tiết đã duyệt).  - Migration tasks: project_id bắt buộc, parent_id tự tham chiếu WBS đa cấp   không giới hạn (restrictOnDelete — chặn xoá cha còn con), type   (task/phase/category — không tách bảng phases riêng), ngày kế hoạch +   thực tế, assignee_id, progress_percent, sort_order, cùng 4 cột chừa chỗ   Task Delegation xuyên phòng ban (§6 — nullable, chưa dùng logic). - Model Task.php theo mẫu Project.php (WITH_PRESENT, fillable, casts,   quan hệ project/parent/children/assignee/creator/updater). - TaskEnums.php theo const-array pattern của ProjectEnums (không PHP enum). - Project.php: thêm quan hệ tasks() song song quickItems() cũ.  Verify: migrate + rollback sạch (kể cả FK tự tham chiếu parent_id trong cùng Schema::create), test thật qua script bootstrap Laravel — tạo Task cha/con, đọc quan hệ, xác nhận restrictOnDelete() chặn xoá cha còn con. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi bảng công việc |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 10:25 | `ed2c4da` | Thêm | Project/TaskEnums.php, Project/Project.php, Project/Task.php, Project/2026_08_29_100001_create_tasks_table.php |

### 1.6. PR2 - Repository/Service/Controller/routes cho Task

Dự án và công việc · Công việc và dự án · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | PR2 - Repository/Service/Controller/routes cho Task. Chuỗi Controller → Service → Repository interface → implementation đầy đủ cho Task, theo đúng pattern module Project hiện có.  - TaskRepositoryInterface/TaskRepository: paginate xuyên project,   flatForProject (build cây WBS), hasChildren/descendantIds (chặn vòng   lặp khi đổi parent_id), countByProject (baseline). - TaskService: RBAC Task = RBAC Project chứa nó (tái dùng   ProjectRepositoryInterface::forViewer(), không viết lại logic   owner/executing/member/follower riêng); role chỉ có task.view_assigned   luôn bị ép lọc theo assignee_id; wbs_code tính runtime (không lưu cột);   chặn đổi parent_id thành chính nó/con cháu; chặn xoá khi còn con. - TaskController + StoreTaskRequest/UpdateTaskRequest (hỗ trợ bulk   titles[] giống StoreProjectQuickItemRequest). - Route mới trong routes/manager.php — khối /tasks* đặt TRƯỚC route   GET /{project} hiện có để tránh bị nuốt path. - Bind TaskRepositoryInterface trong ProjectServiceProvider::register().  Verify: php artisan route:list xác nhận đủ 7 route Task đúng thứ tự; test thật qua script bootstrap Laravel cho toàn bộ luồng (tạo Task cha/con, sửa, đổi parent_id hợp lệ, chặn đổi thành chính nó/con cháu, chặn xoá khi còn con, xoá thành công khi hết con, wbs_code sinh đúng thứ tự 1/1.1/1.2/2, paginate + filter assignee_id đúng RBAC).  Fix trong lúc verify: TaskRepository::create() dùng mass assignment nên origin_department_id (cố ý không có trong Task::$fillable, chừa chỗ Task Delegation) bị Eloquent âm thầm bỏ qua — sửa bằng forceFill() riêng cho cột này sau khi tạo. |
| **Vì sao** | để tránh bị nuốt path |
| **Tác dụng** | tránh bị nuốt path |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 10:26 | `1fa0a65` | Thêm | Project/TaskController.php, Project/StoreTaskRequest.php, Project/UpdateTaskRequest.php, Project/ProjectServiceProvider.php (+4) |

### 1.7. PR3 - kích hoạt permission task.*, thêm mục sidebar Công việc

Giao diện gốc · Cấu hình menu · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | PR3 - kích hoạt permission task.*, thêm mục sidebar Công việc. - config/permissions.php: bật active => true cho 7 key task.* đã có sẵn   trong catalog + ma trận role từ trước (không sửa ma trận). - AppSidebar.vue: thêm mục "Công việc" cạnh "Dự án". - DepartmentSidebarConfigService.php: thêm manager.project.tasks vào   CONFIGURABLE_MENUS + MENU_DEFAULT_SECTIONS để trưởng phòng cấu hình   ẩn/hiện được, đồng bộ với AppSidebar.vue.  Fix phát sinh: AppSidebar.vue/router/index.js chỉ hỗ trợ 1 permission key/route (requiresPermission), nhưng role member chỉ có task.view_assigned (không có task.view) — thêm requiresAnyPermission (mảng, OR logic) vào itemPasses() và route guard để mục sidebar/route vẫn hiện đúng với người chỉ được xem task của chính mình.  Verify: PHP config load được, xác nhận 7 key task.* active=true và ma trận role task.* của admin/department_director/member không đổi qua script bootstrap Laravel; node --check router/index.js và Modules/Project/resources/js/router.js sạch cú pháp. |
| **Vì sao** | để trưởng phòng cấu hình ẩn/hiện được, đồng bộ với AppSidebar để mục sidebar/route vẫn hiện đúng với người chỉ được xem task của chính mình |
| **Tác dụng** | trưởng phòng cấu hình ẩn/hiện được, đồng bộ với AppSidebar.vue; mục sidebar/route vẫn hiện đúng với người chỉ được xem task của chính mình |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 10:40 | `17d0508` | Thêm | WorkspaceConfig/DepartmentSidebarConfigService.php, config/permissions.php, components/AppSidebar.vue, router/index.js |

### 1.8. PR4 - trang "Tất cả công việc" (TaskList.vue)

Dự án và công việc · Công việc và dự án · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | PR4 - trang "Tất cả công việc" (TaskList.vue). Trang xem/sửa/xoá Task xuyên nhiều dự án, theo mẫu vàng data-table (khung ActivityLog.vue — không copy kanban/group/import-export của ProjectList.vue).  - constants/task.js: cột/filter/nhãn trạng thái + tone dot màu (không   badge/pill theo §14 CLAUDE.md), storage key riêng namespace va-task-*. - TaskList.vue: PageHeader → filter (tìm, dự án, người thực hiện, trạng   thái, khoảng ngày) → TablePagesBar top → bảng hide-scrollbar + kéo cột +   useDragScroll → TablePagesBar bottom paging-only → panel chi tiết 28rem   (field ngay hàng, sửa nhanh tại chỗ qua double-click/nút, xoá có   ConfirmDialog). KHÔNG có nút tạo Task mới ở trang này — tạo vẫn tập   trung ở menu chuột phải trong ProjectList (theo phạm vi đã chốt). - router.js: thêm route manager.project.tasks, dùng   requiresAnyPermission (task.view HOẶC task.view_assigned).  Verify: vite build sạch (không lỗi cú pháp Vue/JS); skill theme-check không phát hiện vi phạm (border theo hướng, hex hard-code, title/tooltip, badge/pill); đối chiếu từng prop/slot component dùng chung (PageHeader/TablePagesBar/ConfirmDialog/UserAvatarTip) đúng API thật; xác nhận response TaskService::present() qua script bootstrap Laravel khớp đúng cấu trúc field mà template dùng (project/assignee/creator nested, progress_percent, status).  Fix phát sinh: GET /api/project (dùng để nạp dropdown lọc "Dự án") yêu cầu quyền project.view mà role chỉ có task.view_assigned không có — tách loadOptions() thành 2 lời gọi độc lập (không Promise.all) để 1 API 403 không kéo API assignable-users theo; response assignable-users là {users: [...]} chứ không phải mảng thô, đã sửa đúng theo Controller thật. |
| **Vì sao** | để nạp dropdown lọc "Dự án") yêu cầu quyền project |
| **Tác dụng** | nạp dropdown lọc "Dự án") yêu cầu quyền project.view mà role chỉ có task.view_assigned không có — tách loadOptions() thành 2 lời gọi độc lập (không Promise.all) để 1 API 403 không kéo API assignable-users theo; response assignable-users là {users: [...]} chứ không phải mảng thô, đã sửa đúng theo Controller thật |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 10:43 | `88a253f` | Thêm | Project/task.js, Project/TaskList.vue, Project/router.js |

### 1.9. PR5 - ProjectQuickActionModals dùng API Task thật

Dự án và công việc · Công việc và dự án · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | PR5 - ProjectQuickActionModals dùng API Task thật. Chuyển luồng tạo "công việc/danh mục/phase" từ menu chuột phải sang bảng tasks thật, thay vì project_quick_items — giữ nguyên UX 5 biến thể đã có (normal/bulk/by_category/process/by_phase).  - ProjectQuickActionModals.vue: thêm loadTaskTree()/postTask() gọi   GET\|POST /api/project/{project}/tasks. kind='category'/'phase'/'task'   chuyển hẳn sang postTask() (type='category'/'phase'/'task');   by_category/by_phase giờ set parent_id = id của Task cha đã chọn (WBS   thật) thay vì payload.category_id/phase_id cũ. kind='baseline'/   'signature' GIỮ NGUYÊN gọi project_quick_items — bản chất khác (snapshot   immutable log / hồ sơ ký số, không phải Task). - ProjectRepository::countQuickItemsByKind(): bỏ tính work_items từ   project_quick_items (chỉ còn đếm theo kind cho baseline/signature). - ProjectService: thêm TaskRepositoryInterface, method quickItemCounts()   tổng hợp counts từ ProjectRepository + work_items thật từ   TaskRepositoryInterface::countByProject() (QD8) — dùng cho cả hiển thị   số liệu và snapshot item_count khi chốt baseline.  Verify: vite build sạch; script bootstrap Laravel xác nhận work_items đếm đúng theo Task thật (0 → +2 sau khi tạo 2 Task, baseline snapshot item_count khớp); mô phỏng đầy đủ luồng UI (category → task by_category → phase → task by_phase → bulk 3 task) qua TaskService, xác nhận parent_id đúng và wbs_code cây tính đúng thứ tự (1, 1.1, 2, 2.1, 3, 4, 5).  Dữ liệu project_quick_items cũ (kind task/task_category/phase từ Giai đoạn 1) giữ nguyên làm lịch sử, không migrate — theo đúng QD8. |
| **Vì sao** | vì project_quick_items — giữ nguyên UX 5 biến thể đã có (normal/bulk/by_category/process/by_phase) vì payload |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 10:51 | `afab973` | Thêm | Project/ProjectRepository.php, Project/ProjectService.php, Project/ProjectQuickActionModals.vue |

### 1.10. Cập nhật overview + README — Task thật (Giai đoạn 2)

Dự án và công việc · Công việc và dự án · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Cập nhật overview + README — Task thật (Giai đoạn 2). - VA_WORKSPACE_OVERVIEW.md: thêm mục cập nhật 2026-08-29, đổi trạng thái   Project trong bảng "Đã có trong repo", §2 (bản đồ module), §10 (route   tasks), §19 (thêm Phase 1g, sửa Phase 7 chỉ còn roll-up+Gantt), §21   (Bước 3 mở rộng Initiative/Delegation/roll-up), dòng tổng kết cuối file. - Modules/Project/README.md: mô tả entity Task, quyết định không tách   bảng phases/task_categories, cột chừa chỗ Task Delegation, cấu trúc   thư mục cập nhật đủ file mới (TaskController/TaskService/TaskRepository/   TaskEnums/TaskList.vue/constants/task.js). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 10:55 | `a2854e9` | Tài liệu | Project/README.md, docs/VA_WORKSPACE_OVERVIEW.md |

### 1.11. Tab lọc nhanh, Kanban và nhóm theo dự án trên TaskList

Dự án và công việc · Công việc và dự án, Cấu hình đơn từ, Cấu hình menu · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Tab lọc nhanh, Kanban và nhóm theo dự án trên TaskList |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 11:41 | `045718b` | Thêm | Project/TaskController.php, Project/TaskRepositoryInterface.php, Project/TaskRepository.php, Project/TaskService.php (+6) |

### 1.12. TaskSeeder + hoàn thiện Kanban/quick-actions cho TaskList

Dự án và công việc · Công việc và dự án · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | TaskSeeder + hoàn thiện Kanban/quick-actions cho TaskList. - Thêm Modules/Project/Database/Seeders/TaskSeeder.php (dữ liệu mẫu WBS đa cấp) - Đăng ký TaskSeeder trong DatabaseSeeder.php - Hoàn thiện ProjectQuickActionModals.vue (tạo Task qua API thật) - Hoàn thiện TaskList.vue (tab lọc nhanh, Kanban, nhóm theo dự án)  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 19:48 | `16c1f5f` | Thêm | Project/TaskSeeder.php, Project/ProjectQuickActionModals.vue, Project/TaskList.vue, seeders/DatabaseSeeder.php |

### 1.13. PR1 - migration + model nền tảng mở rộng Task

Dự án và công việc · Công việc và dự án · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | PR1 - migration + model nền tảng mở rộng Task. 7 migration additive (nullable/có default, không breaking) theo plan mở rộng Task với các trường còn thiếu so với hệ cũ:  - Nhóm H: progress_type/progress_number/progress_total/unit (khối lượng) - Nhóm E: estimated_hours/start_time/due_time (giao việc theo giờ) - Nhóm F: manager_id (nhập tay)/accepted_by+accepted_at (derived) - Nhóm I: weight (tỷ trọng, phạm vi Project) - Bảng mới: task_attachments, task_worklogs, task_scores  Cập nhật Task.php (fillable/casts/relations: manager, acceptedBy, taskScore, worklogs, attachments), model TaskAttachment/TaskWorklog/TaskScore mới, TaskEnums::PROGRESS_TYPES.  Verify: migrate + rollback + re-migrate sạch, TaskSeeder chạy không lỗi, tinker xác nhận relations/casts hoạt động đúng.  Xem plan: mở rộng entity Task (PR1/8).  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi bảng công việc; Đổi bảng công việc; Đổi bảng công việc; Đổi bảng công việc; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 19:52 | `5cad065` | Thêm | Project/TaskEnums.php, Project/Task.php, Project/TaskAttachment.php, Project/TaskScore.php (+8) |

### 1.14. PR2 - present() + validate + tính toán runtime cho Task

Dự án và công việc · Công việc và dự án · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | PR2 - present() + validate + tính toán runtime cho Task. TaskService::present() mở rộng field mới (parent object, progress_type/ number/total/unit, estimated_hours/start_time/due_time/worklog_hours, manager/accepted_by/accepted_at, weight, attachments_count, task_score, is_overdue/overdue_days/variance_days qua computeOverdue() — 1 nguồn công thức duy nhất, tránh trùng lặp).  TaskService::applyQuantityProgress() tự tính progress_percent khi progress_type=quantity, applyAcceptedTracking() tự set accepted_by/ accepted_at khi status rời not_started lần đầu (derived, không input UI).  TaskRepository: eager-load parent/manager/acceptedBy/taskScore + withCount('attachments') + withSum('worklogs','hours'); filter mới manager_id/progress_type/is_overdue; tab 'overdue' dùng chung scopeOverdue() với filter is_overdue (không lặp công thức); create()/update() tách guarded keys (accepted_by/accepted_at derived, cùng cơ chế origin_department_id).  StoreTaskRequest/UpdateTaskRequest: rule đầy đủ cho field mới, ràng buộc progress_type/progress_number/progress_total/unit lẫn nhau (required_if/ prohibited_if), progress_number lte progress_total, progress_total gt:0.  TaskController::update() + route PUT /tasks/{task} đổi sang implicit model binding (Task , khác int  ở show/destroy) — UpdateTaskRequest cần đọc progress_type hiện có trên record để validate đúng khi client chỉ PUT một phần field.  Verify: php -l tất cả file sạch; tinker xác nhận present() trả đúng field mới; update() tự tính progress_percent=75 từ 15/20, tự set accepted_by khi đổi status, không ghi đè lại ở lần update sau; filter/tab overdue hoạt động đúng; 5 validate case (prohibited/required_if/lte/gt) đều đúng kỳ vọng. DB dev đã reset về trạng thái sạch sau test.  Xem plan: mở rộng entity Task (PR2/8).  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | để validate đúng khi client chỉ PUT một phần field |
| **Tác dụng** | validate đúng khi client chỉ PUT một phần field |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 20:04 | `2af2261` | Thêm | Project/TaskController.php, Project/StoreTaskRequest.php, Project/UpdateTaskRequest.php, Project/TaskRepository.php (+2) |

### 1.15. PR3 - hiển thị/sửa field mới trên TaskList (list/panel/Kanban)

Dự án và công việc · Công việc và dự án · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | PR3 - hiển thị/sửa field mới trên TaskList (list/panel/Kanban). constants/task.js: TASK_COLUMNS +10 cột mới (ẩn mặc định) - created_at, updated_at, parent, attachments_count, estimated_hours, worklog_hours, manager, accepted_by, weight, is_overdue, variance_days. TASK_TABS thêm tab "Quá hạn". Thêm TASK_PROGRESS_TYPE_LABELS, TASK_SCORE_RESULT_SUGGESTIONS.  TaskList.vue: - Bảng list: nhánh <td> mới cho 10 cột trên, formatDateTime()/   formatVarianceDays() helper (dương=trễ, âm=sớm). - Panel chi tiết (view): field mới theo pattern task-page__row có sẵn -   Công việc cha, Tình trạng hạn, Chênh lệch, Thời gian dự kiến/thực hiện,   Người quản lý/đã nhận, Khối lượng (khi quantity), Tỷ trọng, điểm đánh giá   read-only (ẩn khi chưa có dữ liệu - CLAUDE.md muc 14), Ngày tạo/cập nhật. - editForm mở rộng 10 field mới; form sửa: input mới cho progress_type   (ẩn/hiện progress_percent vs number/total/unit), start_time/due_time,   manager_id, estimated_hours, weight - ước tính % client-side trước khi lưu. - Cây WBS thu gọn/mở rộng theo parent_id: buildTaskTree() trong   groupedTasks, tái dùng nguyên collapsedGroups (Set + localStorage) với   key dạng task-{id}, chỉ áp dụng List view. - Kanban card: chấm đỏ "Quá hạn" góc card, Người quản lý + Thời gian thực   hiện trong card-facts, Tỷ trọng ở header card. - CSS mới: .task-page__hint, .task-page__name-title-row/tree-toggle/   tree-chevron, .task-kanban__overdue-dot/card-weight - theo đúng token   --color-danger/--color-text-muted có sẵn, không border theo hướng.  Verify: php -l sạch (backend liên đới); npm run build (Vite) thành công không lỗi compile; rà soát thủ công toàn bộ optional-chaining/v-if guard cho field mới (manager/parent/accepted_by_user/task_score) - không có undefined access ngoài guard; theme-check sạch (không border theo hướng, không title/tooltip mới, không badge/pill bo tròn, field ngay hàng đúng pattern, trạng thái rỗng ẩn hẳn qua v-if). CHƯA verify qua browser E2E thật (app dùng Google SSO thật, không có mock login/skill chạy app sẵn có trong repo - để lại cho người review chạy thật hoặc /run-skill-generator).  Xem plan: mở rộng entity Task (PR3/8).  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | để lại cho người review chạy thật hoặc /run-skill-generator) |
| **Tác dụng** | lại cho người review chạy thật hoặc /run-skill-generator) |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 20:13 | `cc8188b` | Thêm | Project/task.js, Project/TaskList.vue |

### 1.16. PR4 - task_attachments full stack (Đính kèm công việc)

Dự án và công việc · Công việc và dự án · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | PR4 - task_attachments full stack (Đính kèm công việc). Backend (Controller -> Service -> Repository -> Model, khuôn theo ProjectAttachment/uploadAttachment sẵn có, rút gọn chỉ file upload):  - TaskAttachmentRepositoryInterface + TaskAttachmentRepository: CRUD đơn   giản (listForTask/find/create/delete) qua Eloquent. - TaskAttachmentService: upload() lưu file qua Storage::disk('public'),   delete() xoá cả file vật lý lẫn record, present() trả file_url sinh từ   Storage::url(). - StoreTaskAttachmentRequest: validate file bắt buộc, max 20MB, whitelist   mimes giống ProjectAttachment. - TaskAttachmentController: 3 action mỏng (index/store/destroy). - Đăng ký binding TaskAttachmentRepositoryInterface trong   ProjectServiceProvider::register().  Route mới trong manager.php:   GET    /tasks/{task}/attachments        permission:task.view   POST   /tasks/{task}/attachments        permission:task.create   DELETE /tasks/attachments/{attachment}  permission:task.create  Phát hiện và sửa 1 lỗi thứ tự route quan trọng trước khi merge: DELETE /tasks/attachments/{attachment} phải đăng ký TRƯỚC DELETE /tasks/{task} (wildcard) - nếu không "attachments" bị Laravel hiểu nhầm là giá trị {task} và route attachments không bao giờ match tới. Đã verify lại bằng route:list.  Frontend TaskList.vue: section "Tệp đính kèm" mới trong panel chi tiết (dưới task-page__rows) - list file (tên/kích thước/nút xoá), input file ẩn + nút "Tải file lên" (chỉ hiện khi canEdit). loadAttachments() gọi khi mở panel (inspect()), upload/xoá patch attachments_count tại chỗ trên selected + dòng trong bảng list (CLAUDE.md muc 14, không gọi lại toàn bộ danh sách). CSS mới: .task-page__subsection/-title, .task-page__attachment*, .task-page__hidden-input - khuôn theo .proj-page__attachment* sẵn có trong ProjectList.vue, cùng token (box-shadow 0 1px 0 var(--color-border), không border theo hướng).  Verify: php -l sạch tất cả file backend mới; tinker test thực tế qua TaskAttachmentService (upload file giả -> file_name/file_size đúng, list count đúng, xoá lần 1 thành công + file vật lý bị xoá khỏi storage, xoá lần 2 đúng trả lỗi "không tìm thấy"); route:list xác nhận thứ tự route đúng sau khi sửa; npm run build (Vite) thành công không lỗi compile; diff không có border theo hướng/hex hard-code/title tooltip mới. DB dev đã dọn sạch record test sau khi verify.  Xem plan: mở rộng entity Task (PR4/8).  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 20:21 | `d48285e` | Thêm | Project/TaskAttachmentController.php, Project/StoreTaskAttachmentRequest.php, Project/ProjectServiceProvider.php, Project/TaskAttachmentRepositoryInterface.php (+4) |

### 1.17. PR5 - task_worklogs full stack (Nhật ký giờ làm) + notification

Dự án và công việc · Công việc và dự án · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | PR5 - task_worklogs full stack (Nhật ký giờ làm) + notification. Backend (Controller -> Service -> Repository -> Model):  - TaskWorklogRepositoryInterface + TaskWorklogRepository: CRUD đơn giản   (listForTask/find/create/update/delete) qua Eloquent. - TaskWorklogService: user_id LUÔN = người đăng nhập (mỗi người chỉ tự   ghi giờ của mình, không nhận từ client, không cho chấm hộ - quyết định   đã chốt); canManage() chỉ cho phép chủ log (created_by) hoặc người có   task.approve sửa/xoá; sau khi tạo worklog thành công, gọi   NotificationService::notifyUsers() báo cho manager (nếu có) hoặc creator   (fallback) của task - actor không tự nhận thông báo (service tự loại trừ). - StoreTaskWorklogRequest/UpdateTaskWorklogRequest: work_date required   date, hours required numeric min:0.25 max:24, note nullable max 2000. - TaskWorklogController: 4 action mỏng (index/store/update/destroy). - NotificationService (Identity, dùng chung) thêm 2 type mới:   TYPE_TASK_WORKLOG_ADDED, TYPE_TASK_SCORED (type sau dùng ở PR6) - tái   dùng service tổng quát đã có (notify/notifyUsers), KHÔNG tạo module   Notification riêng, KHÔNG tạo bảng/queue mới. - Đăng ký binding TaskWorklogRepositoryInterface trong   ProjectServiceProvider::register().  Route mới trong manager.php (đã học từ lỗi thứ tự route ở PR4 - áp dụng đúng ngay từ đầu):   GET    /tasks/{task}/worklogs        permission:task.view   POST   /tasks/{task}/worklogs        permission:task.create   PUT    /tasks/worklogs/{worklog}     permission:task.create (đặt TRƯỚC   DELETE /tasks/worklogs/{worklog}     permission:task.create  PUT/DELETE                                         /tasks/{task} wildcard bên dưới) Đã verify route:list xác nhận đúng thứ tự.  Frontend TaskList.vue: section "Nhật ký giờ làm" mới trong panel chi tiết (dưới section Tệp đính kèm) - list log (người/ngày/giờ/ghi chú), form inline "+ Thêm giờ làm" (không modal, nhất quán cách sửa task hiện tại), xoá qua ConfirmDialog thứ 2 độc lập (confirmingDeleteWorklog), nút xoá chỉ hiện với chủ log hoặc người có task.approve (canEditWorklog()). loadWorklogs() gọi khi mở panel (inspect()). Thêm/xoá patch worklog_hours tại chỗ trên selected + dòng bảng list (CLAUDE.md muc 14). CSS mới: .task-page__worklog-list/-item/-main/-user/-date/-hours/-note, .task-page__form--compact - kế thừa token/pattern box-shadow 0 1px 0 sẵn có.  Verify: php -l sạch tất cả 9 file backend thay đổi/mới; tinker test thực tế qua TaskWorklogService (tạo worklog -> user_id = actor.id đúng, update bởi chủ log OK, update bởi người khác không co task.approve bị chặn đúng lỗi, xoá bởi chủ log OK); test notification riêng (set manager_id -> manager nhận đúng notification type=task_worklog_added, actor không tự nhận); route:list xác nhận thứ tự đúng; npm run build (Vite) thành công; diff không co border theo hướng/hex hard-code/title-tooltip mới (title= duy nhất la prop <ConfirmDialog title="..."> hiển thị sẵn dạng <h2>, không phải tooltip). DB dev đã dọn sạch record test sau khi verify.  Xem plan: mở rộng entity Task (PR5/8).  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 20:33 | `7765324` | Thêm | Identity/NotificationService.php, Project/TaskWorklogController.php, Project/StoreTaskWorklogRequest.php, Project/UpdateTaskWorklogRequest.php (+6) |

### 1.18. PR6 - task_scores full stack (Đánh giá tối thiểu) + notification

Dự án và công việc · Công việc và dự án · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | PR6 - task_scores full stack (Đánh giá tối thiểu) + notification. Backend (Controller -> Service -> Repository -> Model):  - TaskScoreRepositoryInterface + TaskScoreRepository: findForTask() +   upsertForTask() dùng updateOrCreate(['task_id' => ...], $data) - đúng   thiết kế "1 task = 1 bản ghi hiện hành" (unique task_id đã tạo ở PR1),   gọi lại ghi đè thay vì tạo dòng lịch sử mới. - TaskScoreService: upsert() tự set scored_by/scored_at, sau đó gọi   NotificationService::notifyUsers() báo cho assignee của task (người   được chấm điểm cần biết kết quả) - dùng type TYPE_TASK_SCORED đã thêm   ở PR5. - UpsertTaskScoreRequest: rating_score nullable numeric min:0,   rating_result nullable string max:100 (text tự do, KHÔNG enum DB cứng -   kết quả đánh giá tuỳ evaluation configuration tương lai, chưa dựng),   rating_desc nullable max:5000. - TaskScoreController: show()/upsert() mỏng. - Đăng ký binding TaskScoreRepositoryInterface trong   ProjectServiceProvider::register().  Route mới trong manager.php (đúng quyết định đã chốt: ai có task.view cũng XEM được điểm, chỉ task.approve mới CHẤM/sửa):   GET /tasks/{task}/score   permission:task.view   PUT /tasks/{task}/score   permission:task.approve Cấu trúc /tasks/{task}/score không xung đột route wildcard /tasks/{task} (khác số path segment) - không cần lưu ý thứ tự đặc biệt như attachments/ worklogs ở PR4/PR5.  Frontend TaskList.vue: section "Đánh giá công việc" mới trong panel chi tiết (dưới section Nhật ký giờ làm) - CHỈ hiện với auth.can('task.approve') (canApprove computed mới). Form có Điểm số, Kết quả (input text + datalist gợi ý TASK_SCORE_RESULT_SUGGESTIONS - tự do, không ép chọn), Ý kiến đánh giá. hydrateScoreForm() pre-fill từ selected.task_score khi mở panel (data đã có sẵn trong response present() từ PR2, không cần gọi API riêng để hiển thị). saveScore() PUT lên server, patch selected.task_score + dòng trong bảng list tại chỗ (CLAUDE.md muc 14). Người chỉ có task.view thấy 3 dòng read-only đã làm từ PR3 (ẩn hẳn khi chưa có dữ liệu).  Verify: php -l sạch tất cả file backend mới/thay đổi; tinker test thực tế qua TaskScoreService (chưa có điểm -> findForTask null; upsert lần 1 tạo mới + assignee nhận đúng notification type=task_scored; upsert lần 2 CÙNG id - ghi đè, tổng vẫn 1 dòng cho task, xác nhận đúng thiết kế unique); validate rule riêng xác nhận: điểm âm bị chặn, text tự do cho rating_result được chấp nhận, payload rỗng hợp lệ; route:list xác nhận route đăng ký đúng; npm run build (Vite) thành công không lỗi compile; diff không có border theo hướng/hex hard-code/title-tooltip mới. DB dev đã dọn sạch record test sau khi verify.  Xem plan: mở rộng entity Task (PR6/8) - hoàn thành nhóm PR core (PR1-PR6).  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | vì tạo dòng lịch sử mới để hiển thị) do cho rating_result được chấp nhận, payload rỗng hợp lệ |
| **Tác dụng** | hiển thị) |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 20:42 | `a7c87c7` | Thêm | Project/TaskScoreController.php, Project/UpsertTaskScoreRequest.php, Project/ProjectServiceProvider.php, Project/TaskScoreRepositoryInterface.php (+4) |

### 1.19. Lưu đề xuất Notification/Reminder Engine làm roadmap tương lai

Tài liệu · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Lưu đề xuất Notification/Reminder Engine làm roadmap tương lai. Ghi lại nguyên văn đề xuất kiến trúc Notification Engine dùng chung (module riêng, đa kênh, policy chống spam, Activity timeline, permission thúc việc theo vai trò) — KHÔNG triển khai, chỉ lưu tham chiếu cho lần EnterPlanMode riêng sau này. NotificationService hiện có (Identity) + 2 type mới từ PR5/PR6 (task_worklog_added/task_scored) giữ nguyên dạng tạm cho tới khi module Notification thật thay thế.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 20:44 | `09afa76` | Tài liệu | plans/2026-08-28-notification-reminder-engine-proposal.md |

### 1.20. PR7 - Filter/Sort/Bulk actions cho TaskList

Dự án và công việc · Công việc và dự án · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | PR7 - Filter/Sort/Bulk actions cho TaskList. Backend:  - TaskRepository: applySort() mới - whitelist 6 cot sortable (end_date,   progress_percent, weight, estimated_hours, worklog_hours -> alias SQL   that worklogs_sum_hours tu withSum, created_at), doc filters['sort_by']/   ['sort_dir'], mac dinh created_at desc khi sort_by khong hop le/rong   (tranh SQL injection qua whitelist map, khong noi truc tiep input). - TaskService::bulkUpdate() moi - whitelist CHI manager_id/weight (dung   quyet dinh da chot: khong cho bulk sua status/title/assignee...), chi ap   dung cho task thuoc pham vi quyen editor (qua forViewer()), task ngoai   pham vi hoac khong ton tai bi bo qua am tham (khong bao loi ro ri thong   tin). BulkUpdateTaskRequest validate task_ids array + it nhat 1 trong 2   field manager_id/weight. - TaskController::bulkUpdate() moi, route PATCH /tasks/bulk   (permission:task.create). - Filter manager_id/progress_type/is_overdue da co san tu PR2 (chuan bi   truoc) - PR7 chi thieu sort + bulk + frontend UI.  Frontend TaskList.vue:  - TASK_FILTERS them manager_id/is_overdue/progress_type (constants/task.js). - Filter UI moi trong toolbar: select Nguoi quan ly, select Cach tinh tien   do, checkbox Chi hien viec qua han - dung chung allAssignableUsers da co. - Sort: bam header cot (isSortableColumn() khop dung 6 cot backend) -> 3   trang thai tang/giam/bo sort (toggleSort()), icon chevronsUp/chevronsDown. - Bulk: checkbox dau moi dong + "chon tat ca" (cot rieng ngoai shownColumns,   luon hien - khong tat duoc qua modal chon cot, dung yeu cau plan), thanh   hanh dong noi khi co dong duoc chon (task-page__bulk-bar) voi 2 truong   Gan nguoi quan ly / Ty trong + nut Ap dung goi PATCH /tasks/bulk, patch   ket qua tai cho vao tasks[] (khong load lai toan bo danh sach - CLAUDE.md   muc 14). clearSelection() tu dong goi khi loadTasks() de tranh chon nham   task khong con hien thi sau khi doi trang/filter. colSpan +1 cho cot   checkbox moi.  Verify: php -l sach tat ca file thay doi/moi; tinker test truc tiep qua TaskService (sort ca 6 cot + gia tri khong hop le/null deu chay khong loi SQL, ke ca alias worklogs_sum_hours; bulkUpdate: manager_id/weight doi dung cho ca 3 task, status KHONG bi doi du gui trong payload - whitelist dung, task id khong ton tai bi bo qua dung, payload thieu whitelist field tra rong ngay); route:list xac nhan PATCH /tasks/bulk dang ky dung; npm run build (Vite) thanh cong khong loi compile; diff khong co border theo huong/hex hard-code/title-tooltip moi. DB dev sach sau khi verify.  Xem plan: mo rong entity Task (PR7/8).  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 20:56 | `8919f4c` | Thêm | Project/TaskController.php, Project/BulkUpdateTaskRequest.php, Project/TaskRepository.php, Project/TaskService.php (+3) |

### 1.21. Lưu đề xuất UI Chuyển giao (Task Delegation §6) làm roadmap tương lai

Tài liệu · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Lưu đề xuất UI Chuyển giao (Task Delegation §6) làm roadmap tương lai. Ghi lại tham chiếu UI hệ cũ (nút Chuyển giao cạnh Tìm/Bộ lọc/Cỡ chữ/Cột, modal nhiều dòng Vai trò/Người chuyển giao/Người tiếp nhận) — xác nhận đây là Task Delegation (§6 overview), Phase 3 chưa dựng, KHÔNG lồng vào PR7 bulk actions hiện tại (giữ nguyên gán manager_id/weight đã code). Chỉ lưu tham chiếu cho lần EnterPlanMode riêng sau này.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 21:23 | `776c862` | Tài liệu | plans/2026-08-28-task-delegation-ui-proposal.md |

### 1.22. PR8 - Export/Import Excel cho Task (Modal "Dữ liệu")

Dự án và công việc · Công việc và dự án · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | PR8 - Export/Import Excel cho Task (Modal "Dữ liệu"). Backend (Controller -> Service -> Repository -> Model, khuôn theo ProjectExcelExporter/ProjectExcelImporter/ProjectService::export\| previewImport\|resolveImportRow\|confirmImport co san):  - TaskExcelExporter: xuat .xlsx theo dung khuon ProjectExcelExporter (dong   1 tieu de, dong 2 ghi chu, dong 4 header, tu dong 5 du lieu). COLUMNS 18   cot (code, project_code luon xuat - diem doi chieu update/tao moi khi   nhap lai). - TaskExcelImporter: doc + preview (KHONG ghi DB), doi chieu theo "Ma cong   viec" -> action=update (o trong = giu nguyen), khac Project o cho LUON   can "Ma du an" de xac dinh project dich ke ca khi update. TaskEnums them   cac ham typeFromInput/statusFromInput/priorityFromInput/   progressTypeFromInput (label tieng Viet -> value, cung pattern   ProjectEnums::valueFromInput). - TaskRepositoryInterface/TaskRepository them findByCode() (doi chieu ma   cong viec) va forExport() (toan bo Task khop filter hien tai, khong   phan trang, ton trong dung bo loc dang ap dung tren trang). - TaskService them export()/previewImport()/resolveImportRow()/   confirmImport() - phat hien va sua dung 1 diem khong nhat quan quan   trong: frontend (theo dung pattern ProjectList.vue::confirmImportRows())   SPREAD row.data len top-level truoc khi gui confirm, khong gui long   trong 'data' - confirmImport()/onlyProvidedFields() da sua doc dung field   top-level, khop chinh xac ConfirmImportTaskRequest validate cung field   do (rows.*.title, khong phai rows.*.data.title). - 4 Form Request moi: ExportTaskRequest (doi xung currentFilterParams() FE),   ImportTaskRequest, ResolveImportTaskRowRequest, ConfirmImportTaskRequest. - TaskController them 4 action: export/importPreview/importResolveRow/   importConfirm.  Route moi (phat hien dung lai loi thu tu route da hoc tu PR4/PR5 - GET /tasks/export dang ky TRUOC GET /tasks/{task} wildcard cung method GET):   GET  /tasks/export               permission:task.view   POST /tasks/import/preview       permission:task.create   POST /tasks/import/resolve-row   permission:task.create   POST /tasks/import/confirm       permission:task.create  Frontend TaskList.vue: nut "Du lieu" tren PageHeader (export-label/ export-options/export-busy-key - dung dung co che dropdown san co cua PageHeader.vue, KHONG tu nghi modal 3-tab moi) mo 2 dialog Teleport rieng - Xuat Excel (chon cot, mac dinh xuat het) va Nhap Excel (3 buoc: chon   file -> xem truoc/sua loi tai cho -> ket qua), copy dung cau truc HTML/   CSS tu .proj-page__dialog* cua ProjectList.vue (doi prefix proj-page ->   task-page). Them 1 media query responsive bo sung (CLAUDE.md muc 7,   ProjectList.vue ban goc chua co) cho modal tren mobile/tablet.  Verify: php -l sach toan bo 12 file backend thay doi/moi; tinker test roundtrip day du qua TaskService (export 43 task -> doc lai file that bang PhpSpreadsheet, header + du lieu dung thu tu cot; export -> import lai chinh file -> ca 43 dong nhan dung action=update, khong loi; tao file Excel thu cong 1 dong CREATE moi (khong ma, co ma du an + status/priority label tieng Viet) -> preview parse dung status=in_progress/priority=high/ project_id dung -> confirm tao task that thanh cong; update qua ma chi gui field weight -> title KHONG bi ghi de, dung co che provided_fields); route:list xac nhan GET /tasks/export dung truoc GET /tasks/{task}; npm run build (Vite) thanh cong khong loi compile; diff khong co border theo huong/hex hard-code/title-tooltip moi (title= duy nhat la prop <ConfirmDialog>/H2 hien thi san). DB dev da don sach sau khi verify.  Phat hien va KHONG dung tay vao: workspace co thay doi tu phien lam viec khac (Modules/Identity/WorkspaceConfig/AppSidebar/tests) khong lien quan den PR8 - chi git add dung 13 file thuoc pham vi Task cua minh, khong commit nham thay doi cua nguoi/tien trinh khac.  Xem plan: mo rong entity Task (PR8/8) - HOAN THANH toan bo 8 PR.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | do (rows |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 21:38 | `b9640af` | Thêm | Project/TaskEnums.php, Project/TaskController.php, Project/ConfirmImportTaskRequest.php, Project/ExportTaskRequest.php (+9) |

### 1.23. Merge: mở rộng entity Task — PR1-PR8 (migration, backend, UI, attachments, worklogs, scores, filter/sort/bulk, Excel)

Khác · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Merge: mở rộng entity Task — PR1-PR8 (migration, backend, UI, attachments, worklogs, scores, filter/sort/bulk, Excel). Gộp toàn bộ 8 PR theo plan mở rộng field Task (đã duyệt qua EnterPlanMode): - PR1: migration + model nền tảng - PR2: present() + validate + tính toán runtime (is_overdue, auto progress) - PR3: frontend list/panel/Kanban - PR4: task_attachments (Đính kèm công việc) - PR5: task_worklogs (Nhật ký giờ làm) + notification - PR6: task_scores (Đánh giá tối thiểu) + notification - PR7: Filter/Sort/Bulk actions - PR8: Export/Import Excel (modal Dữ liệu)  Kèm 2 ghi chú roadmap (chưa triển khai): Notification/Reminder Engine, Task Delegation UI (Chuyển giao).  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 21:40 | `c537a8d` | Cập nhật | — |

### 1.24. Menu toàn hệ thống áp dụng đúng cho super_admin + fix thứ tự sidebar

Cấu hình workspace · Cấu hình đơn từ, Cấu hình menu · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Menu toàn hệ thống áp dụng đúng cho super_admin + fix thứ tự sidebar. Đúng theo quyết định đã chốt trong overview: "Ẩn/hiện menu toàn hệ thống" (WorkspaceConfig) áp dụng cho MỌI tài khoản kể cả super_admin, bỏ ngoại lệ trước đây.  - AuthenticatedUserPresenter::forUser(): super_admin thật (không đang   view-as) không mang ngữ cảnh phòng ban — sidebar đi theo menu toàn hệ   thống, không bị overlay per-department đè lên. Khi view-as vai trò khác   vẫn giữ department thật để RBAC scope=department hoạt động đúng. - GlobalMenuVisibilityRepository::sortOrders(): chỉ tính item đã thực sự   kéo-thả (có section_key) — item chỉ mới toggle ẩn/hiện hoặc đổi tên có   sort_order mặc định 0 trước đây bị AppSidebar hiểu nhầm là "đẩy lên đầu",   gây lệch thứ tự so với trang cấu hình. - GlobalMenuVisibilityService::forListing(): sort_order mặc định theo đúng   vị trí trong CATALOG (thay vì luôn 0) khi item chưa có layout riêng —   tránh trang danh sách hiện sai thứ tự trước khi user kéo-thả lần đầu.   Sửa icon "Phân quyền" trùng icon với mục khác (shield -> settings). - AppSidebar.vue: dùng Object.hasOwn() thay vì chỉ check Number.isFinite()   khi tra menuOrder — phân biệt đúng "không có entry" (dùng default) với   "có entry nhưng giá trị 0" (dùng đúng 0, vị trí đầu tiên). - sidebarMenus.js: thêm 2 section mới (admin "Quản trị",   superadmin-workspace-config "Cấu hình Workspace"); groupMenus() sort ổn   định theo đúng thứ tự catalog/API khi cùng sort_order, không còn tự sắp   xếp alphabet theo menu_key gây lệch với sidebar thật. - WorkspaceConfigGlobalMenuVisibilitySuperadmin.vue: đồng bộ auth store   (hidden/label/section) ngay khi nhận response đầy đủ (applyFullResponse),   không chỉ lúc reorder — tránh sidebar không cập nhật ngay sau khi ẩn/đổi   tên/đổi section một mục.  2 test mới xác nhận đúng hành vi: - ViewAsWebTest: super_admin thật -> department null; view-as vai trò   khác -> department thật; quay lại super_admin -> department null lại,   không đổi department_id thật trong DB. - WorkspaceConfigGlobalMenuVisibilityTest: danh sách giữ đúng thứ tự   catalog sau khi ẩn 1 mục (không polluting /api/me order); reorder di   chuyển đúng item và phản ánh đúng vào payload /api/me.  Verify: php -l sạch 7 file thay đổi; php artisan test chạy cả 2 file — 14/14 test pass (80 assertions), bao gồm 3 test mới thêm lần này.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | để RBAC scope=department hoạt động đúng vì luôn 0) khi item chưa có layout riêng — tránh trang danh sách hiện sai thứ tự trước khi user kéo-thả lần đầu vì chỉ check Number |
| **Tác dụng** | RBAC scope=department hoạt động đúng |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 21:44 | `4baacea` | Sửa | Identity/GlobalMenuVisibilityRepositoryInterface.php, Identity/GlobalMenuVisibilityRepository.php, Identity/AuthenticatedUserPresenter.php, WorkspaceConfig/WorkspaceConfigGlobalMenuController.php (+6) |

### 1.25. Merge: fix menu toàn hệ thống áp dụng cho super_admin + thứ tự sidebar

Khác · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Merge: fix menu toàn hệ thống áp dụng cho super_admin + thứ tự sidebar. Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 21:44 | `4676390` | Cập nhật | — |

