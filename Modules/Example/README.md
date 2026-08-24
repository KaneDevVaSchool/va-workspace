# Module Example

Module mẫu — copy thư mục này để tạo module mới, đổi tên `Example` →
tên module thật (namespace, thư mục, `module.json`, provider).

## Cấu trúc

```
Modules/Example/
├── App/
│   ├── Http/Controllers/     # Controller mỏng, chỉ gọi Service
│   ├── Services/             # Business logic
│   ├── Repositories/         # Truy vấn Eloquent (implements Contracts/)
│   │   └── Contracts/        # Interface cho Repository
│   ├── Models/                # Eloquent models
│   └── Providers/             # ExampleServiceProvider (bind interface, load routes/views/migrations)
├── routes/
│   ├── web.php                # public
│   ├── api.php                # JSON API
│   ├── manager.php            # khu vực quản lý (chỉ nếu module cần tách riêng khỏi routes/manager.php gốc)
│   └── superadmin.php         # khu vực superadmin (tương tự)
├── resources/
│   ├── js/                    # Vue components/pages riêng của module
│   └── views/                 # Blade views riêng của module (nếu cần)
├── Database/
│   ├── migrations/
│   ├── Seeders/
│   └── Factories/
├── config/
├── tests/
│   ├── Feature/
│   └── Unit/
└── module.json
```

> Casing thư mục (`App/`, `Database/Seeders`, `migrations/` viết thường)
> theo đúng mặc định của `nwidart/laravel-modules` v10 — xem `config/modules.php`
> khoá `paths.generator`. Không tự đổi casing khi tạo module mới.

## Design pattern

**Controller → Service → Repository (interface) → Eloquent Model.**

- Controller: nhận request, validate input (Form Request), gọi Service, trả response. Không có logic nghiệp vụ.
- Service: chứa business logic, điều phối 1 hoặc nhiều Repository.
- Repository: nơi DUY NHẤT được gọi Eloquent trực tiếp, implement 1 interface trong `Repositories/Contracts`.
- Binding interface → implementation khai báo trong `ExampleServiceProvider::register()`.

## Quy tắc bắt buộc của dự án (áp dụng cho mọi module)

Xem `.claude/CLAUDE.md` ở root — đặc biệt:
1. Không dùng `border-left/right/top/bottom` hay `*-color` theo từng hướng — luôn dùng shorthand.
2. Route đăng ký đúng theo 4 loại: `manager`, `web`, `superadmin`, `api`.
3. Luôn responsive (mobile/tablet/desktop), hạn chế scroll toàn trang.
4. Font `Gabarito` dùng qua biến `--font-family-base`, màu dùng biến `--color-*` trong `resources/css/theme.css`, không hard-code mã màu.
5. Bảng DB của module có tiền tố `va_workspace_` (tự động qua `DB_PREFIX` trong `.env`, không tự thêm tiền tố trong tên migration).
