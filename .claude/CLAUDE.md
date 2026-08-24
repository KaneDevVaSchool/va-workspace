# VA Workspace — Quy tắc dự án

Stack: **Laravel 10 + Vue 3** (Modular Monolith). Đọc file này trước khi
sửa code trong repo. Chi tiết cấu trúc từng phần xem thêm `docs/`.

## 1. Theme — Chỉ light, không dark mode

Toàn app dùng token light trong `resources/css/theme.css`. **Cấm** dark theme
(`prefers-color-scheme: dark`, `data-theme="dark"`, toggle dark/light). Layout SPA:
`data-theme="light"` + `color-scheme: light`. Chi tiết: `.cursor/rules/no-dark-mode.mdc`.

## 2. CSS — Không dùng border/color theo từng hướng

**Cấm tuyệt đối**: `border-left`, `border-right`, `border-top`, `border-bottom`
và các biến thể `*-color` tương ứng (`border-left-color`, v.v.) ở BẤT KỲ đâu
(CSS thuần, `<style>` trong `.vue`, Tailwind class nếu dùng `border-l/r/t/b`).

Thay thế:
- Viền đủ 4 cạnh → `border: <width> <style> var(--color-...)`.
- Viền 1 cạnh (ví dụ gạch chân) → `box-shadow: 0 2px 0 var(--color-...)`
  hoặc `outline` (không chiếm layout).
- Nếu bắt buộc độ dày khác nhau từng cạnh → `border-width: <top> <right> <bottom> <left>`
  dạng shorthand 4 giá trị, KHÔNG tách thành 4 thuộc tính riêng.

## 3. Routing — 4 file cố định

Route đăng ký tại cấp global, trong `routes/`:
- `routes/web.php` — public, guest, không cần quyền quản trị.
- `routes/manager.php` — khu vực quản lý (prefix `manager`, name prefix `manager.`).
- `routes/superadmin.php` — khu vực superadmin (prefix `superadmin`, name prefix `superadmin.`).
- `routes/api.php` — API JSON (middleware `api`, có thể versioning `prefix('v1')`).

Cả 4 đã được đăng ký trong `app/Providers/RouteServiceProvider.php`. Module
CÓ THỂ có route riêng cùng tên (`Modules/{Ten}/routes/{web,api,manager,superadmin}.php`)
nếu cần tách biệt — nhưng mặc định ưu tiên đăng ký ở cấp global trước.

Không tạo file route mới ngoài 4 loại này trừ khi được yêu cầu rõ ràng.

## 4. Cấu trúc thư mục

- `app/` — code Laravel core dùng chung (không thuộc module cụ thể): base Controller,
  Middleware, Providers, Models dùng chung, Console commands.
- `Modules/{TenModule}/` — mỗi tính năng lớn là 1 module (`nwidart/laravel-modules`),
  tự chứa backend (Controllers/Services/Repositories/Models/routes/migrations)
  VÀ frontend Vue riêng (`Modules/{Ten}/resources/js`). Xem `Modules/Example/` làm mẫu.
- `resources/js/` — Vue app gốc (bootstrap, router, App.vue, layout, store Pinia
  dùng chung, pages không thuộc module cụ thể).
- `resources/css/theme.css` — CSS variables (design tokens), nguồn sự thật cho màu/spacing/font.

## 5. Design pattern — Controller → Service → Repository

Bắt buộc theo mọi module mới:

```
Controller (mỏng, chỉ nhận request + gọi Service + trả response)
    → Service (business logic)
        → Repository interface (Repositories/Contracts/*Interface.php)
            → Repository implementation (duy nhất được gọi Eloquent trực tiếp)
                → Model
```

- Bind interface → implementation trong `{Module}ServiceProvider::register()`.
- Controller/Service KHÔNG BAO GIỜ gọi Eloquent Model trực tiếp.
- Form Request riêng cho validate input, không validate trong Controller.

## 6. Laravel + Vue

- Vue 3 (`<script setup>`), Vue Router, Pinia cho state dùng chung.
- Alias import: `@` → `resources/js`, `@modules` → `resources/js/Modules`,
  `@theme` → `resources/css` (khai báo trong `vite.config.js`).
- Blade chỉ dùng làm shell mount SPA (`resources/views/app.blade.php`) hoặc
  cho các trang không cần tương tác (nếu có), không viết logic UI phức tạp trong Blade.

## 7. Responsive bắt buộc

Mọi UI mới phải hoạt động tốt ở 3 breakpoint: mobile (≤480px), tablet (≤768px),
desktop (≥1280px). Dùng flexbox/grid, đơn vị tương đối (`rem`, `%`, `fr`), tránh
width/height cố định bằng px cho container lớn.

## 8. Hạn chế scroll

- Ưu tiên bố cục vừa màn hình (flex column + `overflow: hidden` ở shell,
  `overflow-y: auto` chỉ ở vùng nội dung cần cuộn — xem `.app-shell` trong `app.css`).
- Khi phải có thanh scroll, dùng style mỏng đã định nghĩa sẵn (`--scrollbar-size`,
  `--scrollbar-thumb`) — không dùng scrollbar mặc định to của trình duyệt.

## 9. Font

`Gabarito` (Google Font) là font mặc định toàn dự án, khai báo qua biến
`--font-family-base` trong `theme.css`, nạp bằng `@import` Google Fonts trong
`resources/css/app.css`. Không đổi font per-component trừ khi được yêu cầu.

## 10. Tài liệu

- `docs/` — tài liệu kỹ thuật (kiến trúc, quy ước, theme, API).
- `plans/` — kế hoạch triển khai từng tính năng, viết trước khi code tính năng lớn.

## 11. Database

Mọi bảng dùng tiền tố **`va_workspace_`**, cấu hình qua `DB_PREFIX` trong `.env`
và `config/database.php` (`'prefix' => env('DB_PREFIX', 'va_workspace_')`).
KHÔNG tự gõ tiền tố vào tên bảng trong migration — Laravel tự thêm prefix từ config.

## 12. Theme màu

Màu chính: `#9a0036` (token `--color-primary` / `--color-primary-900`), thang đầy đủ
50–900 trong `resources/css/theme.css`. Không hard-code mã hex trong component —
luôn dùng `var(--color-primary-*)`. Chi tiết: `docs/theme.md`.

## 13. Không dùng hint/tooltip

**Cấm tuyệt đối**: thuộc tính `title="..."` (tooltip mặc định của trình duyệt)
và mọi dạng tooltip-on-hover tương tự trên nút, icon, link — ở BẤT KỲ đâu
(Blade, `.vue`, component dùng chung). Ý nghĩa của một nút/icon phải luôn
hiển thị sẵn (label chữ thật), không được giấu vào hint chỉ hiện khi hover.

- Nút chỉ có icon (ví dụ icon-only trên desktop khi sidebar thu gọn) → vẫn
  phải có `aria-label` cho accessibility, nhưng KHÔNG dùng `title` để hiện
  tooltip trực quan. Nếu cần giải thích cho người dùng thấy được, hiện label
  chữ thật cạnh icon hoặc trong dropdown/menu, không phụ thuộc hover.
- Input/textarea: không dùng `placeholder` để truyền ý nghĩa chính của field
  (ý nghĩa phải nằm ở `<label>` hiển thị sẵn); placeholder chỉ được dùng cho
  ví dụ định dạng ngắn nếu thực sự cần, không thay thế label.

---

Skill riêng cho dự án (quy trình tạo module mới, checklist route, v.v.):
xem `.claude/skills/`.
