# Module Identity

Đăng nhập Google Workspace (SSO) — chỉ tài khoản thuộc domain trong
`GOOGLE_ALLOWED_DOMAINS` mới đăng nhập được. Bản rút gọn từ `va-hrm`
(không mobile deep-link, không tunnel host, không break-glass fallback —
chưa cần ở giai đoạn này).

## ⚠️ Dữ liệu User/Department TẠM THỜI giả lập

HRM chưa cung cấp API cho dự án này dùng, nên `Modules/Identity` tự có
bảng `users` (mở rộng bảng gốc của Laravel) + `departments` (bảng phẳng)
làm nguồn dữ liệu giả lập:

- `Modules/Identity/Database/migrations/*_create_departments_table.php`
- `Modules/Identity/Database/migrations/*_add_identity_fields_to_users_table.php`
- `Modules/Identity/Database/Seeders/DepartmentSeeder.php` — vài phòng ban mẫu.
- `Modules/Identity/Database/Seeders/DemoUserSeeder.php` — user giả (factory,
  không có email thật) chỉ chạy ở môi trường `local`/`testing`.

**Khi HRM cung cấp API thật**, chỉ cần:

1. Tạo implementation mới cho `UserRepositoryInterface` /
   `DepartmentRepositoryInterface` (vd. `HrmApiUserRepository`) gọi API HRM.
2. Đổi binding trong `IdentityServiceProvider::register()`.
3. Xoá `DepartmentSeeder`, `DemoUserSeeder` và migration giả lập (cân nhắc
   giữ cột `google_id`/`avatar_url`/`status` trên `users` nếu vẫn cần cache
   local cho session/token).

`GoogleAuthenticator` (Service) và `GoogleAuthController` **không cần sửa**
vì chỉ phụ thuộc `UserRepositoryInterface`.

## Cấu trúc

```
Modules/Identity/
├── App/
│   ├── Http/Controllers/
│   │   ├── GoogleAuthController.php   # redirect/callback/logout
│   │   └── MeController.php           # GET /api/me
│   ├── Services/
│   │   └── GoogleAuthenticator.php    # check domain, find-or-create user
│   ├── Repositories/
│   │   ├── Contracts/                 # UserRepositoryInterface, DepartmentRepositoryInterface
│   │   ├── UserRepository.php         # Eloquent — TẠM THỜI
│   │   └── DepartmentRepository.php   # Eloquent — TẠM THỜI
│   ├── Models/
│   │   └── Department.php
│   ├── Exceptions/
│   │   ├── EmailDomainNotAllowed.php
│   │   └── AccountNotUsable.php
│   └── Providers/
│       └── IdentityServiceProvider.php
├── routes/
│   ├── web.php   # /auth/google, /auth/google/callback, /logout
│   └── api.php   # /api/me (auth:sanctum)
├── Database/
│   ├── migrations/
│   └── Seeders/
└── module.json
```

`App\Models\User` (ở `app/Models/User.php`, không phải trong module) vẫn
là model auth thật — `config/auth.php`, Sanctum, session guard đều trỏ vào
đây theo convention Laravel chuẩn. Module Identity chỉ mở rộng nó qua
migration + wrap bằng `UserRepositoryInterface`.

## Auth flow (Sanctum SPA — session/cookie)

1. Vue (`Login.vue`) điều hướng full-page tới `GET /auth/google`.
2. Google consent → callback `GET /auth/google/callback` → tạo/login user,
   `Auth::login()` + session cookie (HttpOnly).
3. Redirect về `APP_URL + /auth/callback?status=ok` (route Vue).
4. `AuthCallback.vue` gọi `GET /sanctum/csrf-cookie` rồi `GET /api/me`
   (axios `withCredentials: true`) để lấy user, lưu vào Pinia store `auth`.
5. `POST /logout` xoá session.

## Quy tắc bắt buộc của dự án

Xem `.claude/CLAUDE.md` ở root (border shorthand, 4 loại route, responsive,
font/màu qua CSS var, tiền tố bảng `va_workspace_` tự động qua `DB_PREFIX`).
