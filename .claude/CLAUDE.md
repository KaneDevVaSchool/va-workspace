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
- Khi phải có thanh scroll **ở shell/panel phụ**, dùng style mỏng đã định nghĩa sẵn (`--scrollbar-size`,
  `--scrollbar-thumb`) — không dùng scrollbar mặc định to của trình duyệt.
- **Bảng dữ liệu**: cột nắm mép phải để kéo đổi độ rộng; **không hiện thanh scroll**
  (class `.hide-scrollbar`). Mẫu vàng: trang Nhật ký hoạt động
  (`Modules/Identity/resources/js/pages/ActivityLog.vue`). Chi tiết:
  `.cursor/rules/data-table.mdc` + skill `data-table`.

## 9. Font

`Be Vietnam Pro` (Google Font, có đủ dấu tiếng Việt) là font mặc định toàn dự án, khai báo qua biến
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

## 14. UI đơn giản, dễ hiểu — không badge/pill, chữ phổ thông, field ngay hàng

Người dùng cuối không rành công nghệ. Mọi màn hình quản trị (đặc biệt bảng
dữ liệu + panel chi tiết) phải ưu tiên rõ ràng, dễ đọc hơn là "đẹp kỹ thuật".

- **Không dùng badge/pill/tag bo tròn nền màu** để hiển thị trạng thái
  (kiểu `<span class="...badge">Được cấp</span>` nền xanh bo tròn). Thay bằng
  chữ thường + 1 chấm màu nhỏ (`width/height: 0.5rem`, `border-radius:
  var(--radius-full)`) đặt trước chữ — xem `PermissionMatrix.vue`
  (`.perm-side__dot` + `.perm-side__row-value`) làm ví dụ.
- **Ngôn ngữ dùng câu tiếng Việt phổ thông**, không thuật ngữ kỹ thuật/tiếng Anh
  lẫn vào UI (tránh "Global override", "Scope override", "effective_source",
  "reserved"...). Nếu cần giải thích "vì sao có giá trị này", viết thành 1 câu
  đầy đủ (vd. "Do có thiết lập riêng cho Team Backend") thay vì nhãn viết tắt.
- **Field trong panel/form chi tiết phải ngay hàng, đều nhau**: mỗi field là
  1 dòng ngang `display: flex; justify-content: space-between` — nhãn bên
  trái (`color: var(--color-text-muted)`), giá trị bên phải, cách dòng bằng
  `box-shadow: 0 1px 0 var(--color-border)` (không dùng `border-bottom`, xem
  mục 2). Không dùng bố cục `<dl>` 2 cột lệch trái/phải kiểu bảng biểu kỹ thuật.
- **Panel/khối phụ chỉ hiện khi có dữ liệu thật để hiện** — không giữ chỗ
  (placeholder rỗng) chiếm layout khi chưa có gì để xem; dùng `v-if` trên
  chính phần tử đó thay vì `v-if` bên trong + trạng thái rỗng.
- **Thao tác đổi dữ liệu (ghi/xoá) nên cập nhật ngay tại chỗ bằng response
  API**, không gọi lại toàn bộ danh sách/ma trận chỉ để phản ánh 1 thay đổi
  nhỏ — endpoint ghi/xoá nên trả về bản ghi vừa đổi để frontend patch trực
  tiếp vào state hiện có (xem `PermissionGrantController::upsert/destroy`
  trả `cell`, `PermissionMatrix.vue::applyCellUpdate()`).
- **Click 1 lần = xem, không đổi gì** khi 1 ô/dòng vừa mang tính hiển thị vừa
  có thể sửa — dùng double-click (hoặc 1 nút hành động rõ ràng trong panel
  chi tiết) cho thao tác thực sự đổi dữ liệu, tránh đổi nhầm khi người dùng
  chỉ muốn xem thông tin.

## 15. Form modal — lưới ngang, trong viewport

Dialog **tạo/sửa** (không phải confirm xoá): form **ngang 2–3 cột**, toàn bộ
nằm trong viewport, được phép gần full screen. Mẫu vàng: modal Thêm tiêu chí
trong `Modules/Evaluation/resources/js/pages/WorkspaceConfigEvaluation.vue`.
Chi tiết: `.cursor/rules/form-modal.mdc` + skill `form-modal`.

---

Skill riêng cho dự án (quy trình tạo module mới, checklist route, v.v.):
xem `.claude/skills/`.
