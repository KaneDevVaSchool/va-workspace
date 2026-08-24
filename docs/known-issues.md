# Known issues / Nợ kỹ thuật

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
phép, để nhận bản vá bảo mật chính thức. **Không chặn** việc làm
`Evaluation` / WorkspaceConfig tiếp theo (xem `docs/VA_WORKSPACE_OVERVIEW.md` §21).

## esbuild/vite dev-server advisory (2026-08-24)

`npm audit` báo 1 moderate (esbuild ≤0.24.2, qua `vite` ≤6.4.2): dev
server có thể nhận request từ website bất kỳ và đọc response
(https://github.com/advisories/GHSA-67mh-4wv8-2f99). Chỉ ảnh hưởng khi
chạy `npm run dev` (không ảnh hưởng bundle production `npm run build`).

Fix đề xuất của `npm audit fix --force` nâng lên `vite@8` — breaking
change, chưa chắc tương thích `laravel-vite-plugin@^1.0.0` hiện tại. Chưa
áp dụng để tránh phá vỡ setup. Cần đánh giá lại khi nâng cấp Vite có kế
hoạch rõ ràng, không chạy `--force` một cách bị động.

## User/Department vẫn stub, chưa HRM (2026-08-24)

`UserRepository` / `DepartmentRepository` trong `Identity` đọc bảng local.
Khi HRM cung cấp API: đổi binding trong `IdentityServiceProvider`, không
đổi Controller/Service. Team **không** nằm trong đợt thay này (`team_lead_id`
là sở hữu Workspace).
