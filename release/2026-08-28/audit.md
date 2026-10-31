# Đối soát 28/08/2026

Đọc nhanh: [README.md](./README.md) · Tuần: [2026-W35](../summary/2026-W35-summary.md) · Bảng tuần: [2026-W35-audit](../summary/2026-W35-audit.md)

## Việc trong ngày

| # | Loại | Phần | Màn hình | Việc | Lần lưu | Đổi dữ liệu |
| --- | --- | --- | --- | --- | ---: | --- |
| 1 | Thêm | Dự án và công việc | Quyền và nhật ký, Công việc và dự án | Thêm loại dự án, import/export Excel và các picker liên quan | 1 | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| 2 | Thêm | Dự án và công việc | Công việc và dự án | Menu chuột phải trên bảng dự án, nhân bản dự án, ngày thực tế | 1 | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| 3 | Tài liệu | Tài liệu | Không gắn màn hình | Cập nhật VA_WORKSPACE_OVERVIEW.md — Project Giai đoạn 1 + mục nhanh | 1 | Không |
| 4 | Sửa | Dự án và công việc | Công việc và dự án | Validate ngày thực tế khi tạo dự án, nâng cấp modal Cập nhật thời gian | 1 | Không |
| 5 | Thêm | Dự án và công việc | Công việc và dự án | PR1 - entity Task thật, migration + Model + Enum (WBS đa cấp) | 1 | Đổi bảng công việc |
| 6 | Thêm | Dự án và công việc | Công việc và dự án | PR2 - Repository/Service/Controller/routes cho Task | 1 | Không |
| 7 | Thêm | Giao diện gốc | Cấu hình menu | PR3 - kích hoạt permission task.*, thêm mục sidebar Công việc | 1 | Không |
| 8 | Thêm | Dự án và công việc | Công việc và dự án | PR4 - trang "Tất cả công việc" (TaskList.vue) | 1 | Không |
| 9 | Thêm | Dự án và công việc | Công việc và dự án | PR5 - ProjectQuickActionModals dùng API Task thật | 1 | Không |
| 10 | Tài liệu | Dự án và công việc | Công việc và dự án | Cập nhật overview + README — Task thật (Giai đoạn 2) | 1 | Không |
| 11 | Thêm | Dự án và công việc | Công việc và dự án, Cấu hình đơn từ, Cấu hình menu | Tab lọc nhanh, Kanban và nhóm theo dự án trên TaskList | 1 | Không |
| 12 | Thêm | Dự án và công việc | Công việc và dự án | TaskSeeder + hoàn thiện Kanban/quick-actions cho TaskList | 1 | Không |
| 13 | Thêm | Dự án và công việc | Công việc và dự án | PR1 - migration + model nền tảng mở rộng Task | 1 | Đổi bảng công việc; Đổi bảng công việc; Đổi bảng công việc; Đổi bảng công việc; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| 14 | Thêm | Dự án và công việc | Công việc và dự án | PR2 - present() + validate + tính toán runtime cho Task | 1 | Không |
| 15 | Thêm | Dự án và công việc | Công việc và dự án | PR3 - hiển thị/sửa field mới trên TaskList (list/panel/Kanban) | 1 | Không |
| 16 | Thêm | Dự án và công việc | Công việc và dự án | PR4 - task_attachments full stack (Đính kèm công việc) | 1 | Không |
| 17 | Thêm | Dự án và công việc | Công việc và dự án | PR5 - task_worklogs full stack (Nhật ký giờ làm) + notification | 1 | Không |
| 18 | Thêm | Dự án và công việc | Công việc và dự án | PR6 - task_scores full stack (Đánh giá tối thiểu) + notification | 1 | Không |
| 19 | Tài liệu | Tài liệu | Không gắn màn hình | Lưu đề xuất Notification/Reminder Engine làm roadmap tương lai | 1 | Không |
| 20 | Thêm | Dự án và công việc | Công việc và dự án | PR7 - Filter/Sort/Bulk actions cho TaskList | 1 | Không |
| 21 | Tài liệu | Tài liệu | Không gắn màn hình | Lưu đề xuất UI Chuyển giao (Task Delegation §6) làm roadmap tương lai | 1 | Không |
| 22 | Thêm | Dự án và công việc | Công việc và dự án | PR8 - Export/Import Excel cho Task (Modal "Dữ liệu") | 1 | Không |
| 23 | Cập nhật | Khác | Không gắn màn hình | Merge: mở rộng entity Task — PR1-PR8 (migration, backend, UI, attachments, worklogs, scores, filter/sort/bulk, Excel) | 1 | Không |
| 24 | Sửa | Cấu hình workspace | Cấu hình đơn từ, Cấu hình menu | Menu toàn hệ thống áp dụng đúng cho super_admin + fix thứ tự sidebar | 1 | Không |
| 25 | Cập nhật | Khác | Không gắn màn hình | Merge: fix menu toàn hệ thống áp dụng cho super_admin + thứ tự sidebar | 1 | Không |

## Từng lần lưu

| Giờ | Mã | Loại | Phần | Màn hình | Đã ghi | Chỗ sửa |
| --- | --- | --- | --- | --- | --- | --- |
| 01:53 | `d032e51` | Thêm | Dự án và công việc | Quyền và nhật ký, Công việc và dự án | Thêm loại dự án, import/export Excel và các picker liên quan | Identity/PermissionMatrixTable.vue, Identity/PermissionMatrix.vue, Project/ProjectEnums.php, Project/ProjectController.php (+41) |
| 09:44 | `062ff88` | Thêm | Dự án và công việc | Công việc và dự án | Menu chuột phải trên bảng dự án, nhân bản dự án, ngày thực tế | Project/ProjectController.php, Project/StoreProjectQuickItemRequest.php, Project/UpdateProjectRequest.php, Project/Project.php (+19) |
| 09:45 | `8a5c5b6` | Tài liệu | Tài liệu | Không gắn màn hình | Cập nhật VA_WORKSPACE_OVERVIEW.md — Project Giai đoạn 1 + mục nhanh | docs/VA_WORKSPACE_OVERVIEW.md |
| 09:51 | `40935e8` | Sửa | Dự án và công việc | Công việc và dự án | Validate ngày thực tế khi tạo dự án, nâng cấp modal Cập nhật thời gian | Project/StoreProjectRequest.php, Project/ProjectQuickActionModals.vue |
| 10:25 | `ed2c4da` | Thêm | Dự án và công việc | Công việc và dự án | PR1 - entity Task thật, migration + Model + Enum (WBS đa cấp) | Project/TaskEnums.php, Project/Project.php, Project/Task.php, Project/2026_08_29_100001_create_tasks_table.php |
| 10:26 | `1fa0a65` | Thêm | Dự án và công việc | Công việc và dự án | PR2 - Repository/Service/Controller/routes cho Task | Project/TaskController.php, Project/StoreTaskRequest.php, Project/UpdateTaskRequest.php, Project/ProjectServiceProvider.php (+4) |
| 10:40 | `17d0508` | Thêm | Giao diện gốc | Cấu hình menu | PR3 - kích hoạt permission task.*, thêm mục sidebar Công việc | WorkspaceConfig/DepartmentSidebarConfigService.php, config/permissions.php, components/AppSidebar.vue, router/index.js |
| 10:43 | `88a253f` | Thêm | Dự án và công việc | Công việc và dự án | PR4 - trang "Tất cả công việc" (TaskList.vue) | Project/task.js, Project/TaskList.vue, Project/router.js |
| 10:51 | `afab973` | Thêm | Dự án và công việc | Công việc và dự án | PR5 - ProjectQuickActionModals dùng API Task thật | Project/ProjectRepository.php, Project/ProjectService.php, Project/ProjectQuickActionModals.vue |
| 10:55 | `a2854e9` | Tài liệu | Dự án và công việc | Công việc và dự án | Cập nhật overview + README — Task thật (Giai đoạn 2) | Project/README.md, docs/VA_WORKSPACE_OVERVIEW.md |
| 11:41 | `045718b` | Thêm | Dự án và công việc | Công việc và dự án, Cấu hình đơn từ, Cấu hình menu | Tab lọc nhanh, Kanban và nhóm theo dự án trên TaskList | Project/TaskController.php, Project/TaskRepositoryInterface.php, Project/TaskRepository.php, Project/TaskService.php (+6) |
| 19:48 | `16c1f5f` | Thêm | Dự án và công việc | Công việc và dự án | TaskSeeder + hoàn thiện Kanban/quick-actions cho TaskList | Project/TaskSeeder.php, Project/ProjectQuickActionModals.vue, Project/TaskList.vue, seeders/DatabaseSeeder.php |
| 19:52 | `5cad065` | Thêm | Dự án và công việc | Công việc và dự án | PR1 - migration + model nền tảng mở rộng Task | Project/TaskEnums.php, Project/Task.php, Project/TaskAttachment.php, Project/TaskScore.php (+8) |
| 20:04 | `2af2261` | Thêm | Dự án và công việc | Công việc và dự án | PR2 - present() + validate + tính toán runtime cho Task | Project/TaskController.php, Project/StoreTaskRequest.php, Project/UpdateTaskRequest.php, Project/TaskRepository.php (+2) |
| 20:13 | `cc8188b` | Thêm | Dự án và công việc | Công việc và dự án | PR3 - hiển thị/sửa field mới trên TaskList (list/panel/Kanban) | Project/task.js, Project/TaskList.vue |
| 20:21 | `d48285e` | Thêm | Dự án và công việc | Công việc và dự án | PR4 - task_attachments full stack (Đính kèm công việc) | Project/TaskAttachmentController.php, Project/StoreTaskAttachmentRequest.php, Project/ProjectServiceProvider.php, Project/TaskAttachmentRepositoryInterface.php (+4) |
| 20:33 | `7765324` | Thêm | Dự án và công việc | Công việc và dự án | PR5 - task_worklogs full stack (Nhật ký giờ làm) + notification | Identity/NotificationService.php, Project/TaskWorklogController.php, Project/StoreTaskWorklogRequest.php, Project/UpdateTaskWorklogRequest.php (+6) |
| 20:42 | `a7c87c7` | Thêm | Dự án và công việc | Công việc và dự án | PR6 - task_scores full stack (Đánh giá tối thiểu) + notification | Project/TaskScoreController.php, Project/UpsertTaskScoreRequest.php, Project/ProjectServiceProvider.php, Project/TaskScoreRepositoryInterface.php (+4) |
| 20:44 | `09afa76` | Tài liệu | Tài liệu | Không gắn màn hình | Lưu đề xuất Notification/Reminder Engine làm roadmap tương lai | plans/2026-08-28-notification-reminder-engine-proposal.md |
| 20:56 | `8919f4c` | Thêm | Dự án và công việc | Công việc và dự án | PR7 - Filter/Sort/Bulk actions cho TaskList | Project/TaskController.php, Project/BulkUpdateTaskRequest.php, Project/TaskRepository.php, Project/TaskService.php (+3) |
| 21:23 | `776c862` | Tài liệu | Tài liệu | Không gắn màn hình | Lưu đề xuất UI Chuyển giao (Task Delegation §6) làm roadmap tương lai | plans/2026-08-28-task-delegation-ui-proposal.md |
| 21:38 | `b9640af` | Thêm | Dự án và công việc | Công việc và dự án | PR8 - Export/Import Excel cho Task (Modal "Dữ liệu") | Project/TaskEnums.php, Project/TaskController.php, Project/ConfirmImportTaskRequest.php, Project/ExportTaskRequest.php (+9) |
| 21:40 | `c537a8d` | Cập nhật | Khác | Không gắn màn hình | Merge: mở rộng entity Task — PR1-PR8 (migration, backend, UI, attachments, worklogs, scores, filter/sort/bulk, Excel) | — |
| 21:44 | `4baacea` | Sửa | Cấu hình workspace | Cấu hình đơn từ, Cấu hình menu | Menu toàn hệ thống áp dụng đúng cho super_admin + fix thứ tự sidebar | Identity/GlobalMenuVisibilityRepositoryInterface.php, Identity/GlobalMenuVisibilityRepository.php, Identity/AuthenticatedUserPresenter.php, WorkspaceConfig/WorkspaceConfigGlobalMenuController.php (+6) |
| 21:44 | `4676390` | Cập nhật | Khác | Không gắn màn hình | Merge: fix menu toàn hệ thống áp dụng cho super_admin + thứ tự sidebar | — |

