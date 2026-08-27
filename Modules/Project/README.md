# Module Project

Quản lý dự án (giai đoạn 1: CRUD dự án). Nền tảng cho Task/công việc con
tính điểm/tiến độ tự động sẽ bổ sung ở giai đoạn sau — cột `evaluation_score`
và `progress_method` trên bảng `projects` đã chuẩn bị sẵn chỗ nhưng chưa
tính toán gì ở bước này.

## Cấu trúc

```
Modules/Project/
├── App/
│   ├── Http/Controllers/     # ProjectController — mỏng, chỉ gọi Service
│   ├── Http/Requests/        # StoreProjectRequest, UpdateProjectRequest, UploadProjectAttachmentRequest
│   ├── Services/             # ProjectService — business logic
│   ├── Repositories/         # ProjectRepository (implements Contracts/)
│   │   └── Contracts/        # ProjectRepositoryInterface
│   ├── Models/                # Project, ProjectScope, ProjectMember, ProjectAttachment
│   ├── Enums/                 # ProjectEnums — danh mục giá trị cố định
│   └── Providers/             # ProjectServiceProvider
├── routes/
│   ├── api.php                # không dùng — API JSON đăng ký qua manager.php + prefix('api') trong Provider
│   └── manager.php            # /api/project/* (đăng ký với prefix api, name api.project. — giống Evaluation)
├── resources/js/
│   ├── pages/ProjectList.vue
│   ├── components/            # ProjectMemberPicker.vue, ProjectScopePicker.vue
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
