# Module Project

Quản lý dự án (Giai đoạn 1: CRUD dự án) + công việc (Giai đoạn 2: entity
`Task` thật, WBS đa cấp). Cột `evaluation_score` và `progress_method` trên
bảng `projects` đã chuẩn bị sẵn chỗ cho roll-up tự động từ Task nhưng
**chưa** tính toán gì ở bước này (Giai đoạn 7 — xem overview §19).

`Task` (bảng `tasks`) luôn thuộc 1 `project_id` bắt buộc — không tách
module riêng. Tự đóng vai "phase"/"danh mục" qua cột `type ∈ {task, phase,
category}` + `parent_id` tự tham chiếu (không tách bảng `phases`/`task_categories`
riêng — 1 cây WBS duy nhất). 4 cột chừa chỗ Task Delegation xuyên phòng ban
(`origin_department_id`, `delegated_to_department_id`,
`delegated_to_employee_id`, `delegation_status`) đã có trong migration
nhưng **chưa** cài đặt logic (overview §6, Phase 3).

`project_quick_items` (Giai đoạn 1) giờ chỉ còn dùng cho `kind ∈ {baseline,
signature}` — `kind ∈ {task, task_category, phase}` đã chuyển hẳn sang bảng
`tasks` thật; dữ liệu cũ giữ nguyên làm lịch sử, không migrate.

## Cấu trúc

```
Modules/Project/
├── App/
│   ├── Http/Controllers/     # ProjectController, TaskController — mỏng, chỉ gọi Service
│   ├── Http/Requests/        # StoreProjectRequest, UpdateProjectRequest, StoreTaskRequest, UpdateTaskRequest, ...
│   ├── Services/             # ProjectService, TaskService — business logic
│   ├── Repositories/         # ProjectRepository, TaskRepository (implements Contracts/)
│   │   └── Contracts/        # ProjectRepositoryInterface, TaskRepositoryInterface
│   ├── Models/                # Project, Task, ProjectScope, ProjectMember, ProjectAttachment, ProjectQuickItem
│   ├── Enums/                 # ProjectEnums, TaskEnums — danh mục giá trị cố định
│   └── Providers/             # ProjectServiceProvider (bind cả 2 Repository)
├── routes/
│   ├── api.php                # không dùng — API JSON đăng ký qua manager.php + prefix('api') trong Provider
│   └── manager.php            # /api/project/* (đăng ký với prefix api, name api.project. — giống Evaluation).
│                               # Route Task tĩnh (/tasks*) đăng ký TRƯỚC GET /{project} để tránh bị nuốt path.
├── resources/js/
│   ├── pages/ProjectList.vue, TaskList.vue
│   ├── components/            # ProjectMemberPicker.vue, ProjectScopePicker.vue, ProjectQuickActionModals.vue
│   ├── constants/task.js
│   └── router.js
├── Database/migrations/
└── module.json
```

## Design pattern

**Controller → Service → Repository (interface) → Eloquent Model.**

Route trang quản lý Vue (`manager.project.index`) là route Vue Router thuần
(SPA), được Laravel phục vụ qua fallback catch-all trong `routes/web.php`
gốc — KHÔNG có route Laravel `manager.project.index` thật, giống hệt cách
Evaluation làm với `manager.evaluation-templates.index`.

API JSON thật đăng ký trong `Modules/Project/routes/manager.php`, được
`ProjectServiceProvider::registerRoutes()` nạp với `prefix('api')->name('api.project.')`
— cùng pattern `EvaluationServiceProvider`.

## Quy tắc bắt buộc

Xem `.claude/CLAUDE.md` ở root.
