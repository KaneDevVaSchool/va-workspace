# Known issues / Nợ kỹ thuật

## 6 cách tính tiến độ Task đã ẩn khỏi UI vì chưa có công thức (2026-09-30)

`TaskEnums::PROGRESS_TYPES` khai 8 giá trị nhưng `TaskService` chỉ tự tính cho
`quantity` (và `percent` là người dùng tự nhập). 6 giá trị còn lại —
`checklist`, `child_weight`, `timeline`, `average`, `duration_weighted`,
`task_weighted` — có nhãn + validate nhưng **không có logic tính**: chọn vào thì
`progress_percent` đứng yên.

Nghiêm trọng hơn: `TaskCreate.vue` và `ProjectQuickActionModals.vue` từng mặc
định `progress_type: 'average'`, nên **phần lớn task tạo mới rơi vào cách tính
không hoạt động**.

Đã xử lý: thêm `TaskEnums::SELECTABLE_PROGRESS_TYPES = ['percent', 'quantity']`
cho UI chọn, đổi mặc định frontend sang `percent`. `PROGRESS_TYPES` giữ đủ 8 giá
trị để validate dữ liệu cũ. 3 giá trị `average`/`duration_weighted`/
`task_weighted` vốn là cách gộp ở **cấp dự án** (`ProjectEnums::PROGRESS_METHODS`),
không hợp nghĩa cho 1 task đơn lẻ.

**Cần làm**: chốt công thức cho `checklist`/`child_weight`/`timeline` rồi cài
`applyQuantityProgress()` tương ứng, hoặc bỏ hẳn khỏi enum.

## Chuyển giao công việc chưa siết phạm vi phòng ban (2026-09-30)

`BulkDelegateTaskRequest` giờ đã chặn giao cho người đã nghỉ việc (thêm
`Rule::exists(...)->where('status', 'active')`), nhưng **vẫn chưa siết phạm vi**:
ai có quyền `task.delegate` vẫn giao được cho bất kỳ ai đang hoạt động trong
toàn hệ thống, không giới hạn theo phòng ban/dự án liên quan tới task.

Chưa siết được vì cần chốt trước: phạm vi hợp lệ là cùng phòng ban, cùng dự án,
hay cùng công ty? Xem `plans/2026-09-30-master-plan-trien-khai.md` §6.5.

## `ReportPersonSnapshot` là code chết (2026-09-30)

Commit `1966255` bỏ luồng "sửa/xem chi tiết báo cáo đã lưu" (xoá route
`GET /report/{id}`, `PUT /report/{id}`, `GET /report/{id}/employees/{userId}`
và `ReportView.vue`). Sau đó **không còn chỗ nào ghi** vào
`ReportPersonSnapshot` — rà toàn repo chỉ còn 2 chỗ nhắc tới model này:
chính file model và quan hệ `Report::personSnapshots()`.

Nghĩa là model + bảng `report_person_snapshots` hiện là code chết. Cùng commit
đó cũng **bỏ sót** `tests/Feature/Report/ReportPeopleSnapshotTest.php` (5 test
gọi endpoint đã xoá nên nhận HTML của SPA thay vì JSON) — đã xoá file test
ngày 2026-09-30.

**Cần quyết định**: nếu tính năng "báo cáo đã lưu chụp danh sách nhân sự" vẫn
nằm trong kế hoạch thì phải làm lại cả ghi snapshot + endpoint xem; nếu bỏ hẳn
thì nên xoá model, quan hệ và tạo migration drop bảng. Đừng để nguyên trạng —
người đọc code sau sẽ tưởng tính năng này đang chạy.

## Test HRM cần biến môi trường `OPENSSL_CONF` (2026-09-30)

13 test HRM (`tests/Unit/Identity/HrmJwtVerifierTest.php`,
`tests/Feature/Identity/HrmSsoTest.php`) tự sinh cặp khoá RSA để ký JWT giả.
Trên máy dev mà `OPENSSL_CONF` không trỏ tới `openssl.cnf`, `openssl_pkey_new()`
trả `false` và toàn bộ nhóm test đổ với `Cannot get key from parameter 1` —
**lỗi môi trường, không phải lỗi code**.

Cách chạy:

```powershell
$env:OPENSSL_CONF = "C:\ServBay\packages\php\8.2\extras\ssl\openssl.cnf"
php artisan test --filter=Hrm
```

Không hardcode đường dẫn này vào `phpunit.xml` vì mỗi máy một chỗ khác nhau.
Hai test đã thêm `markTestSkipped()` kèm hướng dẫn, nên khi thiếu cấu hình thì
test **skip có thông báo rõ** thay vì đổ lỗi khó hiểu.

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

## ~~User/Department vẫn stub, chưa HRM~~ — ĐÃ GIẢI QUYẾT (2026-09-30)

Không còn đúng. Tích hợp VA-HRM đã dựng đầy đủ tại
`Modules/Identity/App/Hrm/`: SSO qua JWKS (`HrmSsoService`, `HrmJwtVerifier`),
webhook HMAC (`HrmWebhookController` + `VerifyHrmWebhookSignature`, bảng
`hrm_webhook_deliveries`), 3 service đồng bộ (`HrmEmployeeSyncService`,
`HrmEmployeeBulkSyncService`, `HrmDepartmentSyncService`), bảng `companies` +
`user_concurrent_positions` (kiêm nhiệm).

Việc **còn lại**: tách entity `Employee` (SSOT HRM) khỏi `User`/system account —
`app/Models/User.php` vẫn gộp 2 vai trò. Các seeder nhân sự local
(`KinhDoanhTeamSeeder`, `HcnsTeamSeeder`, `CnttSoftwareTeamSeeder`) vẫn còn
trong repo, cần rà xem còn dùng cho dev/test hay nên bỏ.

## ~~Bulk actions Task không bọc transaction~~ — ĐÃ GIẢI QUYẾT (2026-09-30)

`TaskService::bulkUpdate()` và `bulkDelegate()` đã bọc `DB::transaction()`
quanh vòng lặp, nên lỗi giữa chừng rollback sạch thay vì để dữ liệu nửa vời.
Thông báo chuyển giao gửi **sau** khi commit (nếu gửi trong transaction rồi
rollback thì người nhận đã nhận thông báo về việc không hề xảy ra).

Còn lại (không chặn): vẫn là N query `find()` + `update()` cho N task. Cân nhắc
gộp thành 1 câu update hàng loạt nếu có lúc chọn hàng nghìn task.

## Filter Lịch (`overlap_from`/`overlap_to`) không có index phù hợp (2026-08-30)

`TaskRepository::applyFilters()` dùng
`whereRaw('DATE(COALESCE(start_date, end_date)) <= ?', ...)` để lọc task
chồng khoảng ngày cho chế độ xem Lịch — biểu thức tính toán trên cột không
tận dụng được index thường trên `start_date`/`end_date`. Bảng `tasks` còn
nhỏ nên chưa ảnh hưởng hiệu năng thực tế; cần đánh giá lại (functional
index hoặc đổi cách lọc) khi dữ liệu lớn hơn.

## Task Delegation — chưa siết phạm vi người nhận theo phòng ban (2026-08-30, cập nhật 30/09)

`BulkDelegateTaskRequest` chỉ validate `exists:users,id`; kết hợp với
`ProjectService::assignableUsers(unrestricted: true)` (dùng khi FE mở dropdown
chọn người nhận), bất kỳ ai có quyền `task.delegate` có thể chuyển giao task
cho **bất kỳ user nào trong toàn hệ thống**, không giới hạn theo phòng ban
liên quan tới task/project. Cần siết lại phạm vi hợp lý khi làm tiếp Phase 3
đầy đủ (`plans/2026-08-30-task-delegation-hoan-thien.md`).

**Cập nhật 30/09:** đã chặn được một nửa — `BulkDelegateTaskRequest` giờ dùng
`Rule::exists('users','id')->where('status','active')` nên không giao được cho
người đã nghỉ việc. Phần phạm vi phòng ban/dự án **vẫn chưa siết** vì cần chốt
phạm vi hợp lệ trước (§6.5 của `plans/2026-09-30-master-plan-trien-khai.md`).

## Vi phạm nhẹ pattern Controller/Service gọi Eloquent trực tiếp (2026-08-30)

Rà soát phát hiện vài chỗ Service/Controller gọi thẳng Eloquent Model thay
vì qua Repository (vi phạm §5 CLAUDE.md):

**Đã dọn 30/09:** `ProjectProgressCalculator` (trước ở module Dashboard) gọi
`DB::table('tasks')` trực tiếp — khi chuyển sang module Project đã đổi sang đi
qua `TaskRepository::progressAggregatesByProject()`.

Còn lại:

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

## ~~`progress_type` mới chỉ khai enum, chưa có logic tính~~ — ĐÃ XỬ LÝ TẠM (2026-09-30)

`TaskEnums::PROGRESS_TYPES` đã thêm 3 giá trị mới cùng nhãn hiển thị, và
`StoreTaskRequest`/`UpdateTaskRequest` chấp nhận chúng khi validate, nhưng
`TaskService::applyQuantityProgress()` (nơi tự tính `progress_percent`) mới
xử lý `percent`/`quantity` như trước — chọn `checklist`/`child_weight`/
`timeline` hiện không tự tính gì, `progress_percent` sẽ đứng yên theo giá
trị nhập tay hoặc null. Cần cài đặt logic tương ứng trước khi cho phép chọn
3 phương pháp này trên UI thật (hiện `TaskCreate.vue`/`TaskList.vue` đã có
sẵn trong danh sách lựa chọn qua `TaskEnums::options()`).

**Cập nhật 30/09:** đã ẩn khỏi UI bằng `TaskEnums::SELECTABLE_PROGRESS_TYPES`
(chỉ còn `percent` + `quantity`), và phát hiện thêm 3 giá trị cấp dự án
(`average`/`duration_weighted`/`task_weighted`) cũng nằm trong danh sách chọn —
xem mục đầu file để biết chi tiết. Logic tính cho 3 phương pháp này vẫn **chưa
có**, chỉ là không còn phơi ra UI nữa.

## Creation settings mới trên Task — cột + validate đã có, enforcement runtime chưa rà soát (2026-08-30)

Migration `2026_08_30_100008_add_creation_settings_to_tasks_table` thêm 10
cột cấu hình (ẩn/hiện chéo cha-con-người theo dõi, tự động hoàn thành theo
báo cáo, chính sách tương tác sau hoàn thành, yêu cầu mô tả/đính kèm báo
cáo). `StoreTaskRequest`/`UpdateTaskRequest`/`TaskService::present()` đã
đọc/ghi/trả các cột này, nhưng chưa rà soát toàn bộ nơi các cờ này cần được
**thực thi** (ví dụ: `hide_from_parent_followers` có thực sự lọc bớt dữ
liệu trả về ở `TaskRepository`/`TaskList.vue` chưa). Cần kiểm tra từng cờ
trước khi công bố tính năng "hoàn chỉnh" cho người dùng cuối.

`auto_complete_on_report` (boolean) đã được thay bằng `report_complete_action`
(none/completed/under_review) và **đã enforce runtime** qua nút "Báo cáo
hoàn thành" (assignee-only) → `TaskService::reportComplete()` — xem
migration `2026_09_04_100001_add_report_review_fields_to_tasks_table`.
