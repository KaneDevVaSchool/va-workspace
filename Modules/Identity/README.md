# Module Identity

Đăng nhập Google Workspace (SSO) — chỉ tài khoản thuộc domain trong
`GOOGLE_ALLOWED_DOMAINS` mới đăng nhập được. Đồng thời sở hữu vòng đời
**User / Department / Role / Team / RBAC / nhật ký hoạt động**.

Không tách module `Auth`, `Department`, `SystemConfig` hay `Audit` riêng
(xem `docs/VA_WORKSPACE_OVERVIEW.md` §2).

## ⚠️ Dữ liệu User/Department TẠM THỜI giả lập

HRM chưa cung cấp API, nên module tự có bảng `users` (mở rộng bảng gốc
Laravel) + `departments` (bảng phẳng):

- `Database/migrations/*_create_departments_table.php`
- `Database/migrations/*_add_identity_fields_to_users_table.php`
- `Database/Seeders/DepartmentSeeder.php` — phòng ban mẫu
- `Database/Seeders/DemoUserSeeder.php` — user giả, chỉ `local`/`testing`
- `Database/Seeders/RoleSeeder.php` — **9 role hệ thống**, chạy mọi environment

**Khi HRM cung cấp API thật**, chỉ cần:

1. Implementation mới cho `UserRepositoryInterface` /
   `DepartmentRepositoryInterface` (vd. `HrmApiUserRepository`).
2. Đổi binding trong `IdentityServiceProvider::register()`.
3. Xoá seeder/migration giả lập User/Department (cân nhắc giữ
   `google_id` / `avatar_url` / `status` trên `users` cho session).

**Không đổi:** `Team` / `team_lead_id` (sở hữu lâu dài của Workspace,
không sync HRM), `GoogleAuthenticator`, `GoogleAuthController`.

## Đã có (không chỉ SSO)

- 9 role: `super_admin`, `admin`, `director_officer`, `department_director`,
  `deputy_department_director`, `section_head`, `team_lead`, `member`, `viewer`
- RBAC: `config/permissions.php` + `permission_grants` + `PermissionService`
- UI ma trận: `/superadmin/permissions`
- View-as (super_admin giả lập role)
- Team (migration + `TeamService`; CRUD UI nằm ở WorkspaceConfig tab Thành viên)
- Nhật ký hoạt động: `/superadmin/activity`
- Shortcut header

## Cấu trúc (rút gọn)

```
Modules/Identity/
├── App/
│   ├── Http/Controllers/     # Google, Me, Permission*, ViewAs, ActivityLog,
│   │                         # Shortcut, Team, Department
│   ├── Services/             # GoogleAuthenticator, PermissionService, TeamService,
│   │                         # ActivityLogService, ViewAsService, …
│   ├── Repositories/         # interface + Eloquent (User/Department = TẠM THỜI)
│   ├── Models/               # Department, Role, Team, PermissionGrant, ActivityLog, …
│   └── Providers/IdentityServiceProvider.php
├── routes/web.php            # SSO + JSON session /api/me, /api/permissions, …
├── Database/migrations|Seeders
└── resources/js/             # Login, PermissionMatrix, ActivityLog, store auth
```

`App\Models\User` vẫn ở `app/Models/User.php` — Sanctum/session trỏ vào đây.
JSON SPA dùng middleware `web` + session/CSRF, không Bearer trên `routes/api.php`.

## Auth flow (Sanctum SPA — session/cookie)

1. Vue (`Login.vue`) điều hướng full-page tới `GET /auth/google`.
2. Google consent → `GET /auth/google/callback` → `Auth::login()` + cookie.
3. Redirect `APP_URL + /auth/callback?status=ok`.
4. `AuthCallback.vue` gọi CSRF rồi `GET /api/me`, lưu Pinia `auth`.
5. `POST /logout` xoá session.

## Quy tắc dự án

`.claude/CLAUDE.md` (border shorthand, 4 loại route, CSS var, prefix
`va_workspace_` qua `DB_PREFIX`). Việc tiếp theo của repo: module
`Evaluation` — xem overview §21, không mở rộng Identity trừ khi sửa bug.
