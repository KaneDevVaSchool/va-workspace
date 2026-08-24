# Màn hình Quản lý phân quyền (superadmin/permissions)

## 1. Bối cảnh / mục tiêu

Backend RBAC granular đã hoàn thiện trong `Modules/Identity`:
- `config/permissions.php` — ma trận mặc định theo role (`matrix`), danh sách
  `reserved` keys (chỉ super_admin) và `reserved_exceptions`.
- `PermissionGrant` model + migration `permission_grants` — override
  cấp/thu hồi quyền theo `role_code` + `permission_key` + `scope_type/scope_id`.
- `PermissionGrantRepository` (CRUD override) đã có sẵn `upsert()` / `remove()` /
  `getGrantsForRole()`.
- `PermissionService::allows()` / `isReserved()` / `resolveGrantedKeys()` — engine
  kiểm tra, đã dùng trong `EnsureHasPermission` middleware và `/api/me`
  (`AuthenticatedUserPresenter::forUser()` trả `granted_permissions`).
- Frontend đã cache quyền trong `authStore.can(key)` (Modules/Identity/resources/js/stores/auth.js).

**Chưa có gì ở lớp trình bày**: không Controller, không route
`superadmin/permissions`, không trang Vue nào. Đây là khoảng trống duy nhất
còn lại để super_admin thực sự thao tác được ma trận phân quyền qua UI thay
vì sửa tay `config/permissions.php`.

Mục tiêu: thiết kế + build 1 màn hình (khu vực superadmin) cho phép super_admin
xem ma trận quyền hiệu lực theo role, override (cấp thêm/thu hồi) theo scope
global/department/team, và hoàn tác override để quay về config default.

## 2. Phạm vi

**Trong phạm vi:**
- 1 trang chính: **Ma trận phân quyền** — bảng role × permission key.
- Bộ lọc scope (global / department / team + chọn scope cụ thể).
- Toggle override từng ô (grant/revoke) + nút "khôi phục mặc định" (xoá override).
- Hiển thị khoá các `reserved` keys (trừ theo `reserved_exceptions`).
- API JSON phục vụ trang này (danh sách permission keys theo module, trạng thái
  hiệu lực hiện tại, CRUD override).

**Ngoài phạm vi (không làm ở lượt này):**
- Bảng audit log riêng biệt (chỉ dựa vào `created_by`/`created_at` sẵn có trên
  `permission_grants`, không thêm bảng lịch sử mới).
- Sửa `config/permissions.php` qua UI (chỉ override qua DB, đúng thiết kế hiện tại).
- Gán role cho user nói chung (đã có/sẽ có ở màn hình khác) — **trừ** gán
  `team_lead_id` cho 1 team, việc đó nằm trong phạm vi CRUD team tối thiểu
  ở mục 3.0 vì team không có ý nghĩa nếu không có người phụ trách.

**Quyết định phạm vi scope — đã xác nhận với người yêu cầu:**
`docs/VA_WORKSPACE_OVERVIEW.md §8` quy định bảng `teams` do chính Workspace
tự quản lý ngay từ đầu (không chờ HRM org-chart), có sẵn cột `hrm_team_uuid`
(nullable) để đồng bộ về sau nếu cần. Người yêu cầu xác nhận thêm: **HRM sẽ
không bao giờ bắn cấp trưởng nhóm (`team_lead_id`)** — khác với `Department`/
`User` vốn là dữ liệu tạm chờ thay bằng API HRM, dữ liệu `team` (đặc biệt
`team_lead_id`) là **sở hữu lâu dài của Workspace**, không phải tạm thời.
→ Plan này **mở rộng phạm vi**: dựng module Team tối thiểu trước (mục 3.0),
để scope `team` hoạt động đầy đủ trong permission matrix ngay từ đầu, thay
vì disable như dự tính ban đầu.

**Làm rõ thêm — mô hình 2 tầng "lead" (đã xác nhận với người yêu cầu, ví dụ
thực tế: phòng Công nghệ có team "Phần mềm" và team "Phần cứng"; Khoa là
`team_lead` của team Phần mềm, quản lý toàn bộ dự án của team; dự án HRM do
3 người trong team Phần mềm làm nhưng chỉ 1 người được giao làm "leader con"
riêng cho dự án đó):**

1. **Tầng Team (thuộc Department)** — cố định, tổ chức. Đây chính là bảng
   `teams` ở mục 3.0 (`department_id`, `team_lead_id`). `team_lead_id` do
   Workspace tự gán tay, **không** đọc/đồng bộ từ HRM (HRM có thể có "team
   lead" riêng ngoài org-chart chính thức mà Workspace không quan tâm/không
   phụ thuộc — đúng lý do người yêu cầu nêu). Đây là tầng mà permission
   scope `team` trong `permission_grants` sử dụng.
2. **Tầng Project lead** — linh hoạt, gắn 1 dự án/dự án con cụ thể, không
   gắn cứng vào team/department. Ví dụ "leader con" phụ trách riêng dự án
   HRM trong số 3 người team Phần mềm. Đây là field thuộc **module Project**
   (`projects.lead_id` hoặc tương tự — module Project **chưa tồn tại trong
   code**, ngoài phạm vi Identity) — **không** thêm scope_type mới vào
   `permission_grants` cho tầng này. Khi module Project được dựng, phân
   quyền theo project-lead sẽ là kiểu "ownership/entity-grant" riêng (đã có
   tiền lệ trong `docs/VA_WORKSPACE_OVERVIEW.md` dòng 186: "Quyền cuối cùng =
   (matrix-grant) OR (ownership/entity-grant)"), không phải mở rộng
   `scope_type` của permission matrix này.

→ Plan permission này (Identity module) **chỉ cần hoàn thiện tầng 1** (Team
thuộc Department) để scope `team` trong ma trận phân quyền hoạt động đúng.
Tầng 2 (project lead) ghi nhận lại đây để module Project sau này tham chiếu,
không triển khai trong plan này.

## 3. Backend

### 3.0 Tiền đề — Module Team tối thiểu (mới, theo docs §8)

Chưa tồn tại trong code — cần dựng trước khi scope `team` trong permission
matrix có ý nghĩa. Theo đúng cấu trúc `docs/VA_WORKSPACE_OVERVIEW.md §8`,
đặt trong module `Identity` (cạnh `Department`, chưa cần tách module riêng
— nhất quán với việc `Department`/`Role` cũng đang nằm trong Identity).

- **Migration** `create_teams_table`:

  ```php
  Schema::create('teams', function (Blueprint $table) {
      $table->id();
      $table->foreignId('department_id')->constrained()->cascadeOnDelete();
      $table->string('name');
      $table->foreignId('team_lead_id')->nullable()
          ->constrained('users')->nullOnDelete();
      // Tham chiếu đối chiếu, KHÔNG phải nguồn sự thật — team_lead_id luôn
      // do Workspace tự gán tay, không đọc/sync từ HRM (xác nhận với người
      // yêu cầu: HRM có thể có "team lead" riêng ngoài org-chart chính
      // thức mà Workspace không phụ thuộc vào).
      $table->string('hrm_team_uuid')->nullable()->unique();
      $table->timestamps();
  });
  ```

- **Migration** `add_team_id_to_users_table`: thêm
  `$table->foreignId('team_id')->nullable()->after('department_id')
  ->constrained('teams')->nullOnDelete();` — cột này **chưa tồn tại** dù
  `PermissionService::userMatchesScope()` và `User::allowsScoped()` đã tham
  chiếu `$user->team_id` (hiện đang đọc thuộc tính không có trong DB, chỉ
  chưa lộ lỗi vì scope `team` chưa được dùng thực tế ở đâu).
- **Model** `Modules/Identity/App/Models/Team.php`: `belongsTo(Department)`,
  `belongsTo(User::class, 'team_lead_id')`, `hasMany(User::class)`.
- **Repository**: `TeamRepositoryInterface` + `TeamRepository`
  (`allByDepartment(int $departmentId)`, `find(int $id)`, `create()`,
  `update()`, `assignLead()`) — bind trong `IdentityServiceProvider`. Đây
  là dữ liệu **sở hữu lâu dài của Workspace** (không có comment "TẠM THỜI
  chờ HRM" như `DepartmentRepository`/`UserRepository`, vì `team_lead_id`
  không do HRM cấp).
- **Service** `TeamService` (mỏng): CRUD team + gán/đổi `team_lead_id` —
  validate:
  - `team_lead_id` (nếu có) phải là user thuộc cùng `department_id` với team
    và đang `status = active`.
  - **Không giới hạn 1 user chỉ lead 1 team** — đã xác nhận với người yêu
    cầu: 1 user có thể là `team_lead_id` của nhiều team cùng lúc (vd. Khoa
    vừa lead team Phần mềm vừa lead team khác nếu cần). Không thêm unique
    constraint hay check nghiệp vụ nào chặn việc này.
- **Đã xác nhận với người yêu cầu**: Team là **domain data chính thức**
  (không chỉ tiền đề kỹ thuật) — làm **UI CRUD Team đầy đủ ngay trong plan
  này**, không phải placeholder.
- **Controller + route CRUD đầy đủ** — đặt ở `manager.php` (đúng CLAUDE.md
  mục 3: khu vực quản lý), theo mẫu `department_director` quản lý team của
  phòng mình: `manager/teams` — list, tạo, sửa (đổi tên), gán/đổi
  `team_lead_id`, xoá team. Middleware `permission:team.manage,department`
  (đã có key `team.manage` trong `config/permissions.php` matrix của
  `department_director`/`team_lead`).
- **Frontend Team**: trang `pages/TeamManagement.vue` trong
  `Modules/Identity/resources/js` (hoặc tách sang module `Department` nếu
  sau này Department được tách riêng — hiện tại `Department`/`Role` đều ở
  Identity nên đặt cùng chỗ) — bảng danh sách team theo phòng ban, form
  tạo/sửa, dropdown chọn `team_lead_id` (chỉ liệt kê user cùng phòng ban).
  Theo CLAUDE.md mục 5: Controller → `TeamService` → `TeamRepositoryInterface`
  → `TeamRepository`, Form Request riêng cho tạo/sửa team.
- Cập nhật `User::team()` relation (`belongsTo(Team::class)`), giữ nguyên
  `allowsScoped()` hiện tại (đã đúng chữ ký).

### 3.1 Route

Thêm `Modules/Identity/routes/superadmin.php` (module chưa có file này —
tạo mới, đăng ký theo `IdentityServiceProvider::registerRoutes()` giống cách
đã làm với `web.php`/`api.php`).

```php
// Modules/Identity/routes/superadmin.php
Route::middleware(['auth', 'role:super_admin'])
    ->prefix('permissions')->name('permissions.')
    ->group(function () {
        Route::get('/', [PermissionMatrixController::class, 'index'])->name('index');
    });
```

SPA nên route Vue-side đọc dữ liệu qua API riêng (`routes/api.php` module,
prefix sẵn `api/`), không cần load lại toàn trang:

```php
// Modules/Identity/routes/api.php (bổ sung, bọc auth + role:super_admin)
Route::prefix('permissions')->name('permissions.')->group(function () {
    Route::get('/matrix', [PermissionMatrixController::class, 'matrix']);
    Route::put('/grants', [PermissionGrantController::class, 'upsert']);
    Route::delete('/grants', [PermissionGrantController::class, 'destroy']);
});
```

Cập nhật `IdentityServiceProvider::registerRoutes()` để nạp thêm
`superadmin.php` (theo đúng pattern `file_exists($basePath.'/superadmin.php')`
như đã làm cho web/api) — **CLAUDE.md mục 3**: route đăng ký ở 4 file cố định,
module có thể có route riêng cùng tên nếu cần tách biệt; ở đây cần tách vì
Identity tự quản lý toàn bộ route auth/superadmin của chính nó.

### 3.2 Controller (mỏng — chỉ gọi Service)

`Modules/Identity/App/Http/Controllers/PermissionMatrixController.php`
- `matrix(Request $request)`: trả về JSON toàn bộ dữ liệu cho UI, backend là
  **single source of truth** — frontend không tự suy luận danh sách
  role/module/permission, tất cả nằm sẵn trong response:

  ```json
  {
      "roles": [{"code": "admin", "label": "Admin"}, ...],   // loại super_admin — không có ý nghĩa trong ma trận
      "modules": [{"key": "task", "label": "Công việc"}, ...],
      "permissions": [
          {"key": "task.delegate", "label": "Uỷ quyền công việc", "module": "task", "reserved": false},
          ...
      ],
      "scope": {"type": "department", "id": 3},
      "matrix": { /* PermissionService::matrixFor() — xem 3.3 */ }
  }
  ```

  Build từ `config('permissions.matrix')` + `config('permissions.catalog')`
  (danh sách key/label/module) + `PermissionService::matrixFor()` (trạng
  thái hiệu lực/override theo scope hiện tại).
- Input query: `scope_type` (`global|department|team`, mặc định `global`),
  `scope_id` (nullable, required nếu scope_type khác global).

`Modules/Identity/App/Http/Controllers/PermissionGrantController.php`
- `upsert(UpsertPermissionGrantRequest $request)`: gọi
  `PermissionGrantRepository::upsert(role_code, permission_key, granted,
  scope_type, scope_id, auth()->id())`. **Chặn reserved key** ở Service
  (xem 3.3) trước khi ghi.
- `destroy(DestroyPermissionGrantRequest $request)`: gọi `remove()`.

Theo CLAUDE.md mục 5: Controller không gọi Eloquent trực tiếp — cả hai
controller trên chỉ gọi `PermissionGrantRepositoryInterface` (đã bind sẵn
trong `IdentityServiceProvider`) hoặc 1 Service mới (xem dưới) — không tự
query `PermissionGrant`.

### 3.3 Service — bổ sung vào `PermissionService` (không tạo service mới)

**Precedence — giữ đúng logic 2 bậc đã có trong `roleAllows()` hiện tại**
(đã xác nhận với người yêu cầu, KHÔNG đổi sang 3 bậc lồng nhau
team→department→global→config): khi scope là `department`/`team`, override
tại đúng scope đó được ưu tiên; nếu không có, fallback thẳng về override
`global` (bỏ qua mọi tầng trung gian); nếu vẫn không có, dùng config default.
`matrixFor()` dưới đây phải phản ánh đúng 2 bậc này, không mô phỏng 1 chuỗi
3 bậc không tồn tại trong `roleAllows()`.

Thêm 2 method:
- `matrixFor(string $scopeType, ?int $scopeId): array` — build cấu trúc chi
  tiết hơn bản nháp ban đầu, để UI phân biệt rõ "có override hay không" và
  "override đến từ đâu" (tránh nhầm giữa "effective khác config default" và
  "có row override trong DB" — 2 việc dễ trùng nhưng không tương đương, ví
  dụ override `global=false` bị override `department=true` đè lên: effective
  vẫn là `true`, tức trùng config default, nhưng thực ra có 2 override tồn
  tại):

  ```php
  [roleCode => [permissionKey => [
      'default'        => bool,           // config('permissions.matrix') gốc
      'effective'       => bool,           // kết quả roleAllows() cho scope hiện tại
      'reserved'        => bool,
      'global_override'    => bool|null,   // null = không có override global
      'scoped_override'    => bool|null,   // null = không có override tại scope hiện tại
                                            // (luôn null khi scopeType = global)
      'effective_source'   => 'config'|'global'|'scoped', // override nào quyết định effective
  ]]]
  ```

  Build bằng cách kết hợp `config('permissions.matrix')` (liệt kê keys,
  cũng chính là `default`) + tra `grants->getGrantsForRole($role, 'global',
  null)` và (nếu scopeType ≠ global) `grants->getGrantsForRole($role,
  $scopeType, $scopeId)` — dùng đúng 2 lệnh gọi mà `roleAllows()` đã dùng,
  không thêm lệnh gọi tầng department khi đang ở team.
- `setGrant(string $roleCode, string $key, bool $granted, string $scopeType,
  ?int $scopeId, int $createdBy): void` — validate `isReserved($key)` trước,
  nếu reserved và không nằm trong `reserved_exceptions[$key]` chứa
  `$roleCode` → ném exception (`PermissionKeyReserved`, tạo mới trong
  `Modules/Identity/App/Exceptions/`). Nếu `$scopeType` là `team`, validate
  thêm `$scopeId` là 1 team tồn tại (ném `ScopeNotFound` nếu không — xem
  3.4). Nếu hợp lệ → gọi `$this->grants->upsert(...)`.
- `revokeGrant(...)`: tương tự, gọi `$this->grants->remove(...)`.

Lý do sửa `PermissionService` thay vì Controller gọi thẳng Repository: giữ
đúng nguyên tắc 1 nơi duy nhất áp luật nghiệp vụ (reserved-key check), tránh
Controller phải biết logic `isReserved`.

### 3.4 Form Requests

- `Modules/Identity/App/Http/Requests/UpsertPermissionGrantRequest.php`:
  validate `role_code` (`Rule::in` danh sách role, loại `super_admin` — không
  cần override gì cho super_admin vì luôn bypass), `permission_key` (string,
  không rỗng), `granted` (boolean), `scope_type` (`in:global,department,team`),
  `scope_id`:
  - `required_unless:scope_type,global`, `integer`.
  - `exists:departments,id` khi `scope_type=department`.
  - `exists:teams,id` khi `scope_type=team` — **và thêm 1 rule tuỳ biến**
    (`Rule::exists` không đủ) kiểm tra team đó thực sự thuộc phòng ban đang
    được chọn trên UI nếu client cũng gửi `department_id` kèm theo cho mục
    đích hiển thị/audit; tối thiểu bắt buộc phải là 1 `teams.id` tồn tại,
    tránh việc set `scope_type=team, scope_id=<id không tồn tại>` tạo ra 1
    grant "treo" (permission_grants không có FK ràng buộc `scope_id`, nên
    đây là validation bắt buộc ở tầng ứng dụng, không có DB constraint
    chặn hộ).
- `DestroyPermissionGrantRequest.php`: rule giống hệt (trừ `granted`) — vì
  xoá theo composite identity `(role_code, permission_key, scope_type,
  scope_id)`, cùng đúng bộ cột với unique index đã có trong migration
  `permission_grants` (`perm_grants_unique` — đã tồn tại từ trước, không
  cần thêm).

Theo CLAUDE.md mục 5: validate qua Form Request riêng, không validate trong
Controller.

### 3.5 Danh sách permission keys có mô tả (label tiếng Việt cho UI)

Cần 1 danh sách "catalog" keys kèm nhãn hiển thị + tên module, vì
`config('permissions.matrix')` hiện chỉ có keys thô (`task.delegate`,
`project.*`...), không có label người đọc được. Đề xuất: thêm mảng mới trong
`config/permissions.php`:

```php
'catalog' => [
    // 'permission_key' => ['label' => '...', 'module' => '...', 'description' => '...'],
    'task.delegate' => [
        'label' => 'Uỷ quyền công việc',
        'module' => 'Công việc',
        'description' => 'Cho phép giao/chuyển task cho người khác thực hiện',
    ],
    // ...
],
```

`description` là nội dung phụ (tooltip-on-hover bị cấm theo CLAUDE.md mục
13) — hiển thị dưới dạng text phụ luôn hiện trong panel chi tiết/dòng phụ
dưới label, không dùng `title=""`.

**Đã xác nhận với người yêu cầu**: điền `catalog` đầy đủ ngay (toàn bộ key
xuất hiện trong `config('permissions.matrix')` phải có `label` tiếng Việt +
`module` trước khi coi bước này xong) — không dùng key thô làm label tạm.

## 4. Frontend (`Modules/Identity/resources/js`)

### 4.1 Route Vue

Thêm vào `router.js` (hoặc file `router.superadmin.js` mới nếu convention
tách theo khu vực — cần kiểm tra `resources/js/router` gốc để biết cách
gộp theo prefix `superadmin`):

```js
{
  path: '/superadmin/permissions',
  name: 'superadmin.permissions',
  component: () => import('./pages/PermissionMatrix.vue'),
  meta: { requiresSuperAdmin: true },
}
```

### 4.2 Trang chính — `pages/PermissionMatrix.vue`

Bố cục (theo CLAUDE.md mục 7/8 — vừa màn hình, scroll trong vùng nội dung):

```
┌─────────────────────────────────────────────────────────┐
│ Quản lý phân quyền                                       │
│ [Scope: Toàn hệ thống ▾] [Phòng ban: Công nghệ ▾] [Team: Backend ▾] │ ← filter bar
│  (2 dropdown scope_id chỉ hiện khi scope_type ≠ global;              │
│   Team lọc theo Phòng ban đã chọn — chọn PB trước mới chọn Team)     │
├─────────────────────────────────────────────────────────┤
│ [Tìm permission key...]                    [Module: Tất cả ▾] │ ← search/filter
├─────────────────────────────────────────────────────────┤
│                    │Admin│Giám đốc│Trưởng PB│Trưởng nhóm│NV│Xem│
│ ▸ Công việc (task)                                        │  ← group header
│   task.delegate    │ ✅  │  ✅   │   ✅    │    ✅    │ – │ – │
│   task.assign      │ ✅  │  –   │   ✅    │    ✅    │ – │ – │
│ ▸ Dự án (project)                                          │
│   ...                                                      │
│ ▸ Hệ thống (reserved)                                      │
│   permissions.manage│ 🔒  │  🔒  │   🔒    │    🔒    │🔒│🔒│  ← disabled
└─────────────────────────────────────────────────────────┘  ← overflow-y: auto
```

- Cột đầu: permission key + label, group theo module (accordion/collapsible
  theo module, để bảng không quá dài — ~25 module).
  - Header cột cố định (sticky) khi cuộn dọc — dùng vùng scroll riêng, không
    scroll cả trang.
- **Legend cố định phía trên bảng** (không phải tooltip — hiển thị sẵn theo
  CLAUDE.md mục 13), vì với ~25 module × 6 role rất dễ đọc nhầm trạng thái:

  ```text
  ● Được cấp    ○ Không được cấp    ◆ Có override    🔒 Quyền hệ thống (khoá)
  ```

- Ô đọc trực tiếp từ `matrixFor()` (mục 3.3), không tự suy luận lại ở
  frontend:
  - Nền/dấu theo `effective` (true → ● cấp, false → ○ không cấp).
  - Có dấu phụ ◆ khi `global_override !== null || scoped_override !== null`
    (tức **có tồn tại override**, phân biệt rõ với "effective khác default"
    — 2 điều kiện không tương đương: 1 override global có thể bị 1 override
    scoped đè lại về đúng giá trị default, ◆ vẫn phải hiện vì override thật
    sự tồn tại trong DB). Dấu ◆ dùng `box-shadow`/`outline`, không dùng
    border theo hướng (CLAUDE.md mục 2).
  - Click vào dấu ◆ (hoặc panel chi tiết ô) hiện rõ `effective_source`
    (`config`/`global`/`scoped`) — vd. "Đang được cấp bởi override cấp
    Team" — để admin không phải đoán quyền tới từ đâu.
  - 🔒 = `reserved: true`, ô disabled, không click được (trừ ngoại lệ).
- Click ô (không reserved) → toggle ngay (optimistic update):
  - Nếu giá trị mới **khác** `default` → gọi `PUT /api/permissions/grants`
    (tạo/cập nhật override tại scope hiện tại).
  - Nếu giá trị mới **trùng** `default` **và** không còn override nào khác
    ở scope hiện tại từng ghi đè nó → gọi `DELETE /api/permissions/grants`
    (xoá override tại đúng scope hiện tại — không đụng override ở scope
    khác, vd. xoá override `team` không xoá override `global`).
  - Không bắt user phân biệt thao tác "override" vs "revoke" — UI tự chọn
    action theo state, đúng như bản nháp ban đầu.
- Toast lỗi 422 (validate) / 403 (reserved) nếu API từ chối, revert optimistic
  update.

### 4.3 Component con

- `components/PermissionScopeFilter.vue` — dropdown scope_type + scope_id.
  - `scope_type = department`: danh sách department từ API có sẵn (cần
    kiểm tra `DepartmentRepositoryInterface`/endpoint hiện có để tái dùng,
    không tạo API mới nếu đã có `/api/departments` hay tương tự).
  - `scope_type = team`: dropdown team, **lọc theo department đã chọn**
    trước đó (chọn Phòng ban → mới hiện danh sách Team thuộc phòng đó, dùng
    `TeamRepository::allByDepartment()` mục 3.0) — vì team luôn thuộc 1
    department cố định.
- `components/PermissionMatrixTable.vue` — bảng chính, nhận props
  `matrix`, `scopeType`, `scopeId`, emit `@toggle`.
- `components/PermissionCell.vue` — 1 ô, xử lý 3 trạng thái + loading khi
  đang gọi API.

### 4.4 State

Không cần Pinia store riêng — dữ liệu matrix chỉ dùng trong trang này, giữ
local state (`ref`/`reactive`) trong `PermissionMatrix.vue`, fetch lại khi
đổi scope filter.

### 4.5 Responsive (CLAUDE.md mục 7)

- Desktop (≥1280px): bảng đầy đủ, tất cả cột role hiển thị.
- Tablet (≤768px): bảng cuộn ngang trong container riêng
  (`overflow-x: auto`), cột permission key sticky bên trái.
- Mobile (≤480px): **không ưu tiên ở giai đoạn này** (đã chốt) — chỉ cần
  cuộn ngang (`overflow-x: auto`) kế thừa từ tablet, không xây layout
  "card" riêng cho mobile.

## 5. Các bước triển khai (thứ tự đề xuất)

1. Migration/model/repository/service RBAC nền tảng (permission_grants) —
   **đã xong** (đang ở working tree).
2. Backend — module Team đầy đủ (mục 3.0): migration `teams` +
   `add_team_id_to_users_table`, `Team` model, `TeamRepositoryInterface` +
   `TeamRepository`, `TeamService` (không giới hạn 1 user lead nhiều team),
   bind trong `IdentityServiceProvider`, cập nhật `User::team()` relation.
   Controller + route CRUD đầy đủ tại `manager/teams`.
3. Frontend Team: `pages/TeamManagement.vue` — CRUD team đầy đủ (list theo
   phòng ban, tạo/sửa/xoá, gán `team_lead_id`).
4. `config/permissions.php`: thêm khoá `catalog` — label + module +
   description tiếng Việt đầy đủ cho **toàn bộ** key trong `matrix` (đã
   chốt: không dùng key thô tạm).
5. Backend permission matrix: Exception `PermissionKeyReserved` +
   `ScopeNotFound` + method mới trong `PermissionService` (`matrixFor` với
   cấu trúc chi tiết ở mục 3.3, `setGrant`, `revokeGrant`) — giữ nguyên
   precedence 2 bậc hiện có, không đổi sang 3 bậc lồng nhau.
6. Backend: 2 Form Request (validate `scope_id` chặt theo `scope_type`,
   đặc biệt `team` phải tồn tại thật) + 2 Controller (`/api/permissions/matrix`
   trả đủ metadata — roles/modules/permissions/matrix, không bắt frontend tự
   suy luận) + đăng ký route (`Modules/Identity/routes/superadmin.php` mới,
   bổ sung `Modules/Identity/routes/api.php`, cập nhật
   `IdentityServiceProvider::registerRoutes()`).
7. Frontend: `PermissionMatrix.vue` + 3 component con + route (scope filter
   3 cấp: global / department / team, team lọc theo department đã chọn) +
   legend cố định + panel giải thích `effective_source` khi click ô có ◆.
8. Test — xem mục 6a (Test Plan) bên dưới, chạy đủ trước khi coi plan này
   hoàn thành.
9. `theme-check` skill trước khi hoàn thành (CSS mới, tránh border theo
   hướng, đảm bảo dùng `var(--color-*)`, không title/tooltip).

## 6a. Test Plan

Test cho `PermissionService`/`PermissionGrantRepository` (feature hoặc unit
test tuỳ setup hiện có của dự án — kiểm tra thư mục `tests/` trước khi viết
để bám đúng convention/factory sẵn có):

- **Config default**: role không có override nào → `roleAllows()` trả đúng
  theo `config('permissions.matrix')` (cả trường hợp match key chính xác
  lẫn match qua `module.*`/`*`).
- **Global override**: grant thêm 1 key role vốn không có theo default →
  `true`; revoke 1 key role vốn có theo default → `false`.
- **Scoped override (department/team) đè global**: override `global=false`
  nhưng override tại `department`/`team` = `true` cho đúng role/key/scope
  đó → effective phải là `true` (scoped thắng global — đúng thứ tự trong
  `roleAllows()` hiện tại).
- **Scoped override đè global — chiều ngược lại**: override `global=true`,
  override `team=false` tại đúng team của user → effective `false`.
- **Không có override tại scope cụ thể → fallback global**: chỉ có override
  `global`, gọi `allows()` với `scopeType=department` → vẫn áp dụng override
  global (đúng nhánh fallback trong `roleAllows()`).
- **Scope không khớp user → từ chối trước khi xét override**: `allows($user,
  $key, 'department', $otherDeptId)` với `$otherDeptId` khác
  `$user->department_id` → luôn `false`, bất kể override gì tồn tại (đúng
  `userMatchesScope()`).
- **Reserved key**: role thường cố gắng được grant 1 reserved key → API
  `setGrant()` ném `PermissionKeyReserved`, không ghi DB. Reserved key có
  trong `reserved_exceptions` (`initiative.assign_department` cho
  `director_officer`) → grant thành công.
- **super_admin bypass**: `allows($superAdminUser, bất_kỳ_key)` luôn `true`
  kể cả reserved key, kể cả không có override nào — trừ khi đang
  `isImpersonating()`.
- **`matrixFor()` phân biệt "có override" vs "effective khác default"**: ca
  cụ thể override `global=false` + override `scoped=true` cho cùng
  role/key/department mà `config default = true` → `effective = true`
  (trùng default) nhưng `global_override` và `scoped_override` đều khác
  `null` — verify `matrixFor()` trả đúng cả 2, không chỉ so `effective !==
  default` để suy ra có override hay không.
- **CRUD grant qua API**: `PUT /api/permissions/grants` tạo override đúng
  scope; gọi lại lần 2 với giá trị khác → `updateOrCreate` cập nhật, không
  tạo row trùng (đúng unique index `perm_grants_unique` đã có). `DELETE`
  xoá đúng 1 row theo composite identity, không ảnh hưởng override ở scope
  khác.
- **Validation `scope_id`**: `scope_type=team` với `scope_id` không tồn tại
  trong bảng `teams` → 422, không tạo được override "treo".
- **Quyền truy cập API**: role khác `super_admin` gọi
  `GET/PUT/DELETE /api/permissions/*` → 403 (route bọc
  `role:super_admin`, test cả trường hợp super_admin đang view-as giả lập
  role khác — theo `ViewAsService`, quyền thật mới được tính, không phải
  role đang view-as).
- **Team**: gán `team_lead_id` khác `department_id` của team → Form
  Request/Service reject. Gán cùng 1 user làm lead 2 team khác nhau → thành
  công (đã chốt không giới hạn).

## 7. Rủi ro / điểm cần xác nhận với người yêu cầu

**Đã chốt trong quá trình lập plan (kể cả sau vòng review):**

- Catalog: điền đầy đủ `label` + `module` + `description` tiếng Việt ngay,
  không dùng key thô tạm.
- Scope "team": xây đầy đủ ngay (không disable) — team thuộc department cố
  định (`teams.department_id`), `team_lead_id` luôn do Workspace tự gán tay,
  không sync từ HRM.
- Mobile ≤480px: chưa cần thiết ở giai đoạn này — chỉ tối ưu desktop/tablet,
  bỏ qua layout "card" riêng cho mobile trong lượt này.
- Mô hình 2 tầng lead: tầng Team (department, dùng cho permission scope) và
  tầng Project-lead (linh hoạt theo dự án, thuộc module Project — ngoài
  phạm vi plan này) là 2 khái niệm tách biệt, không gộp vào `permission_grants`.
- **Precedence scope giữ nguyên 2 bậc** (scope cụ thể → global override →
  config default) đúng như `PermissionService::roleAllows()` đã có sẵn —
  KHÔNG đổi sang 3 bậc lồng nhau team→department→global→config như 1 đề xuất
  review đưa ra, vì sẽ phải sửa lại logic đang chạy (dùng trong
  `EnsureHasPermission`) mà chưa có lý do nghiệp vụ rõ ràng để đổi.
- **UI CRUD Team làm đầy đủ ngay** trong plan này (không phải chỉ API tối
  thiểu) — Team là domain data chính thức.
- **1 user được phép làm `team_lead_id` của nhiều team cùng lúc** — không
  thêm giới hạn nghiệp vụ nào.
- `matrixFor()` trả đủ `default/effective/global_override/scoped_override/
  effective_source` (không chỉ `effective`+`source` đơn giản) để UI phân
  biệt rõ "có override" và "effective khác default" là 2 điều kiện khác nhau.
- API `/api/permissions/matrix` trả đủ metadata (roles/modules/permissions)
  — backend là single source of truth, frontend không tự suy luận danh sách.
- Thêm mục 6a Test Plan bao phủ precedence, reserved key, CRUD grant, scope
  validation, quyền truy cập API, team.

**Đã chốt với người yêu cầu (vòng cuối):**

- Route quản lý Team: `manager/teams` — xác nhận đúng như đề xuất.
- Ma trận phân quyền không hiển thị role `super_admin` — chỉ 6 role
  (`admin` → `viewer`), vì `super_admin` luôn bypass ở
  `PermissionService::allows()`.

Không còn điểm nào treo — plan sẵn sàng để triển khai theo thứ tự ở mục 5.
