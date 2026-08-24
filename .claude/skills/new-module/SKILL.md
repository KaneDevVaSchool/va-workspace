---
name: new-module
description: Tạo một module Laravel + Vue mới trong Modules/ theo đúng cấu trúc và design pattern chuẩn của dự án (Controller → Service → Repository, 4 loại route, resources/js riêng). Dùng khi người dùng yêu cầu "tạo module mới", "thêm module", "scaffold module <tên>".
---

# New Module

Tạo module mới bằng cách **copy `Modules/Example/`** và đổi tên, KHÔNG viết
lại từ đầu — giữ đúng cấu trúc mẫu đã được duyệt.

## Các bước

1. Hỏi người dùng tên module (PascalCase, ví dụ `Student`, `Timetable`) nếu
   chưa rõ, và xác nhận: module này có cần route riêng cho `manager`/`superadmin`
   hay chỉ dùng route global (`routes/manager.php`, `routes/superadmin.php`)?

2. Copy toàn bộ cây thư mục `Modules/Example/` sang `Modules/{TenModule}/`.

3. Đổi tên trong TẤT CẢ file đã copy:
   - Namespace `Modules\Example\...` → `Modules\{TenModule}\...` (giữ nguyên
     casing `App/`, `Database/Seeders`, `Database/migrations` — xem
     `config/modules.php` → `paths.generator`, KHÔNG đổi thành lowercase `app/`).
   - Class `Example*` → `{TenModule}*` (ví dụ `ExampleService` → `StudentService`)
   - `module.json`: `name`, `alias` (kebab/lowercase), `providers` (đường dẫn provider mới)
   - Route prefix/name trong `routes/*.php` của module (`example` → tên module dạng kebab-case)
   - `README.md` của module: cập nhật tên, mô tả

4. **Giữ nguyên khối `registerRoutes()` trong `{Module}ServiceProvider::boot()`**
   (copy y nguyên từ `ExampleServiceProvider`, chỉ đổi namespace). Route của
   module KHÔNG tự động được Laravel nạp — nếu thiếu đoạn này, mọi route
   trong `Modules/{Ten}/routes/*.php` sẽ im lặng không hoạt động (đã xác
   minh thực tế bằng `route:list` khi dựng `Modules/Example`).

5. Thêm entry vào `modules_statuses.json` ở root: `"{TenModule}": true`.

6. KHÔNG xoá `Modules/Example` — đây là bản mẫu tham chiếu dùng chung, giữ
   nguyên trừ khi người dùng yêu cầu rõ ràng xoá nó.

7. Chạy `composer dump-autoload` để nạp namespace PSR-4 mới, sau đó xác
   minh bằng `php artisan route:list` rằng route module mới thực sự xuất
   hiện (đừng chỉ giả định — bước 4 dễ bị bỏ sót và route sẽ âm thầm không chạy).

## Checklist tuân thủ (đối chiếu trước khi báo hoàn thành)

- [ ] Controller không chứa business logic, chỉ gọi Service.
- [ ] Service không gọi Eloquent trực tiếp, chỉ gọi Repository interface.
- [ ] Repository implementation là nơi DUY NHẤT query Eloquent.
- [ ] Binding interface→implementation khai báo trong `{Module}ServiceProvider::register()`.
- [ ] Route đặt đúng file trong 4 loại (`web`/`manager`/`superadmin`/`api`), không tạo loại route mới.
- [ ] CSS/Vue trong module không dùng `border-left/right/top/bottom` hay `*-color` theo hướng (dùng `border` shorthand / `box-shadow` / `outline`).
- [ ] Component Vue dùng biến theme (`var(--color-*)`, `var(--font-family-base)`, `var(--space-*)`), không hard-code màu/font.
- [ ] UI mới responsive ở mobile/tablet/desktop, tránh scroll toàn trang (scroll trong container nội bộ, thanh scroll mỏng).
- [ ] Migration không tự thêm tiền tố bảng (`va_workspace_` được Laravel tự thêm qua `DB_PREFIX`).

Tham chiếu đầy đủ quy tắc: `.claude/CLAUDE.md` ở root repo.
