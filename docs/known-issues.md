# Known issues / Nợ kỹ thuật

## docs/VA_WORKSPACE_OVERVIEW.md mô tả kiến trúc khác với setup hiện tại (2026-08-24)

Repo có sẵn `docs/VA_WORKSPACE_OVERVIEW.md` (không do phiên setup này tạo)
mô tả một kiến trúc khác với những gì đã setup ở đây:

| | VA_WORKSPACE_OVERVIEW.md | Setup hiện tại (theo yêu cầu trực tiếp) |
|---|---|---|
| Frontend | Vue 3 + **Inertia.js** (server-driven SPA, không REST riêng) | Vue 3 SPA thuần + Vue Router + Pinia |
| Kiến trúc | Hỗn hợp: **Application Use Case** (`app/Application/{Module}`) cho module có state machine, **MVC thuần** cho module còn lại | **Repository + Service** đồng nhất cho mọi module, qua `nwidart/laravel-modules` |
| Module | `app/Application/{TenModule}/`, không có `Modules/` riêng | `Modules/{TenModule}/` qua `nwidart/laravel-modules` |
| DB prefix | `va_prd_` | `va_workspace_` |
| CSS | Tailwind CSS | CSS variables tự viết (`resources/css/theme.css`) |

**Quyết định (đã hỏi người yêu cầu trực tiếp)**: giữ nguyên setup theo 11
quy tắc đưa ra trong phiên này (Modules + Repository/Service + Vue SPA +
`va_workspace_`), KHÔNG theo `VA_WORKSPACE_OVERVIEW.md`. File đó được giữ
nguyên, không chỉnh sửa.

**Rủi ro cần lưu ý**: nếu `VA_WORKSPACE_OVERVIEW.md` là tài liệu kiến
trúc chính thức cho cùng một dự án production (không phải bản nháp cũ),
hai nguồn sự thật này sẽ mâu thuẫn khi triển khai thật — cần người phụ
trách dự án xác nhận lại file nào là chuẩn trước khi code nghiệp vụ thật.

## Laravel 10 đã hết hạn vá bảo mật (2026-08-24)

Toàn bộ dòng `laravel/framework` 10.x (kể cả bản mới nhất `10.50.3`) bị
Composer 2 chặn cài mặc định vì dính các security advisory đã công bố
(`PKSA-m5cs-t1y6-qpcs`, `PKSA-3r5d-mb8f-1qw9`, `PKSA-mdq4-51ck-6kdq`,
`PKSA-8qx3-n5y5-vvnd`, `PKSA-w7xr-vk7n-rstm`). Xem chi tiết:
https://packagist.org/security-advisories/

**Quyết định**: giữ Laravel 10 theo yêu cầu dự án, tắt chặn cài đặt qua
`composer.json` → `config.audit.block-insecure = false`. Đây KHÔNG phải
đã vá lỗ hổng — chỉ là cho phép cài đặt tiếp.

**Cần làm sau**: đánh giá nâng cấp lên Laravel 11 hoặc 12 khi dự án cho
phép, để nhận bản vá bảo mật chính thức. Tham khảo advisory ở packagist
để biết mức độ nghiêm trọng cụ thể trước khi lên production.

## esbuild/vite dev-server advisory (2026-08-24)

`npm audit` báo 1 moderate (esbuild ≤0.24.2, qua `vite` ≤6.4.2): dev
server có thể nhận request từ website bất kỳ và đọc response
(https://github.com/advisories/GHSA-67mh-4wv8-2f99). Chỉ ảnh hưởng khi
chạy `npm run dev` (không ảnh hưởng bundle production `npm run build`).

Fix đề xuất của `npm audit fix --force` nâng lên `vite@8` — breaking
change, chưa chắc tương thích `laravel-vite-plugin@^1.0.0` hiện tại. Chưa
áp dụng để tránh phá vỡ setup. Cần đánh giá lại khi nâng cấp Vite có kế
hoạch rõ ràng, không chạy `--force` một cách bị động.
