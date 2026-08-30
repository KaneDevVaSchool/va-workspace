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

## Bulk actions Task không bọc transaction (2026-08-30)

`TaskService::bulkUpdate()` và `bulkDelegate()`
(`Modules/Project/App/Services/TaskService.php`) lặp qua từng task, gọi
`find()` + `update()` riêng cho mỗi task, không bọc `DB::transaction()`. Nếu
lỗi giữa chừng (mất kết nối DB, timeout khi chọn hàng trăm task), một phần
task đã đổi dữ liệu, phần còn lại giữ nguyên — không có cách rollback, và
response không phản ánh rõ ràng phần nào thất bại. **Cần làm**: bọc
`DB::transaction()` quanh vòng lặp, cân nhắc chuyển sang update hàng loạt
bằng 1 câu query thay vì N query riêng lẻ khi có thời gian.

## Filter Lịch (`overlap_from`/`overlap_to`) không có index phù hợp (2026-08-30)

`TaskRepository::applyFilters()` dùng
`whereRaw('DATE(COALESCE(start_date, end_date)) <= ?', ...)` để lọc task
chồng khoảng ngày cho chế độ xem Lịch — biểu thức tính toán trên cột không
tận dụng được index thường trên `start_date`/`end_date`. Bảng `tasks` còn
nhỏ nên chưa ảnh hưởng hiệu năng thực tế; cần đánh giá lại (functional
index hoặc đổi cách lọc) khi dữ liệu lớn hơn.

## Task Delegation — chưa siết phạm vi người nhận theo phòng ban (2026-08-30)

`BulkDelegateTaskRequest` chỉ validate `exists:users,id`; kết hợp với
`ProjectService::assignableUsers(unrestricted: true)` (dùng khi FE mở dropdown
chọn người nhận), bất kỳ ai có quyền `task.delegate` có thể chuyển giao task
cho **bất kỳ user nào trong toàn hệ thống**, không giới hạn theo phòng ban
liên quan tới task/project. Cần siết lại phạm vi hợp lý khi làm tiếp Phase 3
đầy đủ (`plans/2026-08-30-task-delegation-hoan-thien.md`).

## Vi phạm nhẹ pattern Controller/Service gọi Eloquent trực tiếp (2026-08-30)

Rà soát phát hiện vài chỗ Service/Controller gọi thẳng Eloquent Model thay
vì qua Repository (vi phạm §5 CLAUDE.md):

- `TaskService` gọi `Project::query()` trực tiếp ở 5 chỗ (để truyền vào
  `ProjectRepositoryInterface::forViewer(Builder $query, ...)` — chữ ký
  interface hiện bắt buộc nhận `Builder` từ ngoài).
- `Modules/Identity/App/Http/Controllers/PermissionMatrixController.php`,
  `ViewAsController.php`, `SuperAdminBootstrap.php` gọi `Role::query()`
  trực tiếp trong Controller.
- `Modules/Social/App/Services/SocialGroupService.php`,
  `SocialHashtagService.php`, `SocialPollService.php` gọi thẳng
  `User::query()`/`SocialHashtag::query()`/`SocialPoll::query()`/
  `DB::table(...)`, bỏ qua Repository hoàn toàn.

Không chặn tính năng hiện tại (code chạy đúng) — ghi nhận làm nợ kỹ thuật,
cân nhắc dọn khi có đợt refactor Identity/Social hoặc khi sửa
`ProjectRepositoryInterface::forViewer()` để tự khởi tạo query bên trong
thay vì nhận từ ngoài.

## `progress_type` mới (`checklist`/`child_weight`/`timeline`) chỉ khai enum, chưa có logic tính (2026-08-30)

`TaskEnums::PROGRESS_TYPES` đã thêm 3 giá trị mới cùng nhãn hiển thị, và
`StoreTaskRequest`/`UpdateTaskRequest` chấp nhận chúng khi validate, nhưng
`TaskService::applyQuantityProgress()` (nơi tự tính `progress_percent`) mới
xử lý `percent`/`quantity` như trước — chọn `checklist`/`child_weight`/
`timeline` hiện không tự tính gì, `progress_percent` sẽ đứng yên theo giá
trị nhập tay hoặc null. Cần cài đặt logic tương ứng trước khi cho phép chọn
3 phương pháp này trên UI thật (hiện `TaskCreate.vue`/`TaskList.vue` đã có
sẵn trong danh sách lựa chọn qua `TaskEnums::options()`).

## Creation settings mới trên Task — cột + validate đã có, enforcement runtime chưa rà soát (2026-08-30)

Migration `2026_08_30_100008_add_creation_settings_to_tasks_table` thêm 10
cột cấu hình (ẩn/hiện chéo cha-con-người theo dõi, tự động hoàn thành theo
báo cáo, chính sách tương tác sau hoàn thành, yêu cầu mô tả/đính kèm báo
cáo). `StoreTaskRequest`/`UpdateTaskRequest`/`TaskService::present()` đã
đọc/ghi/trả các cột này, nhưng chưa rà soát toàn bộ nơi các cờ này cần được
**thực thi** (ví dụ: `hide_from_parent_followers` có thực sự lọc bớt dữ
liệu trả về ở `TaskRepository`/`TaskList.vue` chưa; `auto_complete_on_report`
có tự đổi `status='completed'` khi tạo báo cáo chưa). Cần kiểm tra từng cờ
trước khi công bố tính năng "hoàn chỉnh" cho người dùng cuối.
