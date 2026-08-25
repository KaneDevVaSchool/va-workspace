# Mạng nội bộ — Bảng tin công ty (module `Social`)

> **Trạng thái (2026-08-25):** Đã triển khai giai đoạn 1 (đăng bài, thích 1
> loại, bình luận phẳng, chia sẻ, ghim). Giai đoạn 2 (mục 8 bên dưới) mở
> rộng theo yêu cầu UI/UX tham khảo 1Office: reaction 6 loại, emoji picker,
> reply lồng 1 cấp — đang triển khai.

## 1. Bối cảnh / mục tiêu

Người yêu cầu muốn có 1 trang "mạng nội bộ" kiểu Bảng tin — tham khảo từ
1Office (mẫu HTML đính kèm khi lập plan): đăng bài (text/ảnh/file/bình
chọn), thích/bình luận/chia sẻ, panel "Sinh nhật"/"Thông báo công ty" bên
cạnh. Hiện repo **chưa có gì** thuộc phạm vi này — không bảng, không
module, không route.

Mục tiêu: dựng module mới `Modules/Social` theo đúng pattern
Controller→Service→Repository của dự án, gồm:
- Bảng tin — feed bài viết toàn công ty, mọi nhân viên đăng được.
- Thích / bình luận / chia sẻ (chia sẻ = đăng lại bài của người khác lên
  bảng tin của mình, kèm ghi nguồn).
- Panel "Thông báo công ty" — không phải feature riêng, mà là các bài viết
  được **ghim** bởi quản lý (dùng field `is_pinned` trên chính bài viết,
  không tạo bảng thông báo riêng).
- Panel "Sinh nhật" — **chỉ dựng UI + query rỗng an toàn**, chưa có cột
  ngày sinh trong `users` (xem quyết định ở mục 2).

## 2. Phạm vi

**Trong phạm vi:**
- Migration + Model + Repository + Service + Controller cho: `social_posts`,
  `social_post_likes`, `social_post_comments`.
- Đăng bài (text, đính kèm ảnh/file), sửa/xoá bài **của chính mình**.
- Ghim/bỏ ghim bài (chỉ `department_director` trở lên hoặc `super_admin` —
  xem permission key mục 3.5) → hiện ở panel "Thông báo công ty".
- Thích / bỏ thích bài viết (1 user chỉ thích được 1 lần / bài — unique).
- Bình luận (1 cấp, không reply lồng nhau — giữ đơn giản đúng CLAUDE.md
  mục 14).
- Chia sẻ bài viết (tạo bài mới tham chiếu `shared_from_post_id`, kèm caption
  riêng tuỳ chọn).
- Trang Vue `SocialFeed.vue` — feed 1 cột, panel phải "Thông báo công ty".
- Mục sidebar "Bảng tin" — `configurableByDepartment: true` (trưởng phòng ẩn
  được cho phòng mình, theo yêu cầu người dùng).
- Kiểm duyệt: `department_director`/`admin`/`super_admin` xoá được bài của
  người khác trong phạm vi phòng ban mình quản lý (dùng
  `PermissionService::allows()` + scope `department`, đúng engine RBAC có
  sẵn — không tự chế cơ chế quyền riêng).

**Ngoài phạm vi (không làm ở lượt này):**
- Panel "Sinh nhật" hiển thị dữ liệu thật — chỉ dựng khung UI rỗng có ghi
  chú rõ "chờ dữ liệu HRM" (giống comment đã có sẵn ở
  `Modules/Identity/App/Models/Department.php`), **không** thêm cột ngày
  sinh vào `users` ở lượt này (đã xác nhận với người yêu cầu).
- Bình chọn (poll) trong bài viết — có trong mẫu 1Office nhưng **đã xác
  nhận không làm** ở lượt này.
- Nhóm/thảo luận riêng (groups), Sơ đồ tổ chức, Lộ trình thăng tiến, Bài viết
  hẹn giờ — các mục khác trong sidebar 1Office nhưng ngoài phạm vi lần này.
- Text-to-speech, rich text editor nâng cao (bold/italic/color) — bài viết
  chỉ hỗ trợ text thường + emoji + ảnh/file đính kèm, không WYSIWYG editor.
- Thông báo đẩy (push notification) khi có người thích/bình luận — chỉ hiện
  trong feed, không tích hợp hệ thống thông báo riêng.

## 3. Backend (`Modules/Social`)

### 3.0 Cấu trúc module

Theo mẫu `Modules/Example` + `Modules/Evaluation` (module gần nhất):

```
Modules/Social/
  App/Http/Controllers/SocialPostController.php
  App/Http/Controllers/SocialCommentController.php
  App/Http/Requests/StoreSocialPostRequest.php
  App/Http/Requests/UpdateSocialPostRequest.php
  App/Http/Requests/StoreSocialCommentRequest.php
  App/Models/SocialPost.php
  App/Models/SocialPostLike.php
  App/Models/SocialPostComment.php
  App/Providers/SocialServiceProvider.php
  App/Repositories/Contracts/SocialPostRepositoryInterface.php
  App/Repositories/Contracts/SocialCommentRepositoryInterface.php
  App/Repositories/SocialPostRepository.php
  App/Repositories/SocialCommentRepository.php
  App/Services/SocialPostService.php
  App/Services/SocialCommentService.php
  Database/migrations/2026_08_25_100001_create_social_posts_table.php
  Database/migrations/2026_08_25_100002_create_social_post_likes_table.php
  Database/migrations/2026_08_25_100003_create_social_post_comments_table.php
  module.json
  routes/api.php
  resources/js/pages/SocialFeed.vue
  resources/js/components/SocialPostComposer.vue
  resources/js/components/SocialPostCard.vue
  resources/js/components/SocialCommentList.vue
  resources/js/components/SocialPinnedPanel.vue
  resources/js/components/SocialBirthdayPanel.vue
```

Chỉ cần `routes/api.php` (SPA gọi JSON qua fetch/axios) — **không** cần
`web.php`/`manager.php`/`superadmin.php` riêng, vì trang Bảng tin dùng
chung cho mọi role đã đăng nhập, phân quyền xử lý trong Controller/Service
qua `PermissionService`, không phân theo khu vực route (đúng tinh thần
CLAUDE.md mục 3 — chỉ tạo route ngoài 4 loại chuẩn khi thực sự cần tách
biệt; ở đây API dùng chung `routes/api.php` sẵn có là đủ, đăng ký qua
`SocialServiceProvider::registerRoutes()` theo đúng cách `EvaluationServiceProvider`
đã làm, không đụng 4 file gốc).

### 3.1 Migration

```php
// create_social_posts_table
Schema::create('social_posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->text('content')->nullable(); // nullable: cho phép chia sẻ chỉ kèm ảnh, không caption
    $table->json('attachments')->nullable(); // [{type, url, name, size}]
    $table->boolean('is_pinned')->default(false);
    $table->foreignId('pinned_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('pinned_at')->nullable();
    $table->foreignId('shared_from_post_id')->nullable()
        ->constrained('social_posts')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes(); // xoá mềm — giữ vết cho ActivityLogService/audit
});

// create_social_post_likes_table
Schema::create('social_post_likes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id')->constrained('social_posts')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->timestamps();
    $table->unique(['post_id', 'user_id']); // 1 user chỉ thích 1 lần/bài
});

// create_social_post_comments_table
Schema::create('social_post_comments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id')->constrained('social_posts')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->text('content');
    $table->timestamps();
    $table->softDeletes();
});
```

Bảng dùng tiền tố `va_workspace_` tự động qua `DB_PREFIX` (CLAUDE.md mục
11) — không tự gõ tiền tố trong migration.

`attachments` lưu JSON thay vì bảng riêng vì chỉ cần hiển thị lại (không
query/filter theo file) — nhất quán mức đơn giản của module, tránh
over-engineer 1 bảng `social_post_attachments` khi chưa có nhu cầu truy vấn
riêng theo file.

### 3.2 Model

- `SocialPost`: `$fillable` (`user_id`, `content`, `attachments`,
  `is_pinned`, `pinned_by`, `pinned_at`, `shared_from_post_id`), `$casts`
  (`attachments` → `array`, `is_pinned` → `boolean`, `pinned_at` →
  `datetime`), `SoftDeletes`. Quan hệ: `user()` belongsTo `App\Models\User`,
  `likes()` hasMany `SocialPostLike`, `comments()` hasMany
  `SocialPostComment`, `sharedFrom()` belongsTo `SocialPost`
  (`shared_from_post_id`), `pinnedBy()` belongsTo `User`. Accessor
  `getLikesCountAttribute()`/`getCommentsCountAttribute()` nếu không dùng
  `withCount()` ở repository (ưu tiên `withCount()` ở Repository, không
  N+1 query qua accessor).
- `SocialPostLike`, `SocialPostComment`: model mỏng, quan hệ `belongsTo`
  tương ứng.

### 3.3 Repository

`SocialPostRepositoryInterface`:
- `paginate(int $perPage, ?int $cursor): LengthAwarePaginator` — feed chính,
  `orderByDesc('is_pinned')->orderByDesc('created_at')` (bài ghim luôn lên
  đầu — quyết định: ghim hiện ở CẢ panel phải LẪN đầu feed chính, không chỉ
  ở panel, để không "giấu" thông báo quan trọng khỏi luồng đọc chính).
- `pinned(int $limit): Collection` — cho panel "Thông báo công ty".
- `find(int $id): ?SocialPost`.
- `create(array $data): SocialPost`.
- `update(SocialPost $post, array $data): SocialPost`.
- `delete(SocialPost $post): void` (soft delete).
- `toggleLike(SocialPost $post, int $userId): bool` — trả `true` nếu vừa
  like, `false` nếu vừa unlike (toggle 1 lệnh, tránh Service phải tự
  `exists()` rồi `create/delete` — atomic hơn nếu dùng
  `firstOrCreate`/`delete` trong 1 transaction).

`SocialCommentRepositoryInterface`:
- `forPost(int $postId): Collection`.
- `create(array $data): SocialPostComment`.
- `delete(SocialPostComment $comment): void`.

Chỉ Repository thao tác Eloquent trực tiếp (CLAUDE.md mục 5).

### 3.4 Service

`SocialPostService`:
- `present(SocialPost $post, User $viewer): array` — format response dùng
  chung cho mọi action (list/store/update/pin), tránh lặp code định dạng ở
  Controller (đúng pattern `EvaluationCriteriaService::present()`):

  ```php
  [
      'id' => ..., 'content' => ..., 'attachments' => [...],
      'author' => ['id', 'name', 'avatar_url', 'department'],
      'is_pinned' => bool,
      'shared_from' => null|[...post rút gọn...],
      'likes_count' => int,
      'comments_count' => int,
      'liked_by_me' => bool, // so với $viewer->id
      'can_edit' => bool,   // $post->user_id === $viewer->id
      'can_delete' => bool, // chính chủ HOẶC allows($viewer, 'social.moderate', 'department', $post->user->department_id)
      'can_pin' => bool,    // allows($viewer, 'social.pin', 'department', ...)
      'created_at' => ...,
  ]
  ```
- `listFeed(User $viewer, int $perPage): array`.
- `create(User $author, array $data): SocialPost`.
- `update(SocialPost $post, User $editor, array $data): SocialPost` —
  chặn nếu `$editor->id !== $post->user_id` (ném 403 — sửa bài luôn chỉ
  dành chính chủ, không có quyền "sửa hộ" như xoá).
- `delete(SocialPost $post, User $actor): void` — cho phép nếu chính chủ
  HOẶC `PermissionService::allows($actor, 'social.moderate', 'department',
  $post->user->department_id)`; ghi `ActivityLogService::record()` khi
  actor khác chính chủ (kiểm duyệt — cần vết audit).
- `share(User $sharer, SocialPost $original, ?string $caption): SocialPost`
  — tạo `SocialPost` mới với `shared_from_post_id = $original->id`.
- `toggleLike(SocialPost $post, User $user): array` — trả lại
  `['liked' => bool, 'likes_count' => int]` để frontend patch state tại
  chỗ (CLAUDE.md mục 14 — không load lại cả feed).
- `pin(SocialPost $post, User $actor): SocialPost` /
  `unpin(...)` — check `allows($actor, 'social.pin', ...)` trước.

`SocialCommentService`:
- `create(User $author, SocialPost $post, string $content): array` — trả
  comment vừa tạo + `comments_count` mới, cùng lý do patch-tại-chỗ.
- `delete(SocialPostComment $comment, User $actor): void` — chính chủ HOẶC
  `social.moderate`.

### 3.5 Permission keys mới (`config/permissions.php`)

Thêm vào `matrix` (không sửa `PermissionService`, dùng engine RBAC có sẵn):

```php
'social.moderate' => [...role có ở department_director trở lên, admin, super_admin (đã có '*')],
'social.pin'       => [...cùng nhóm role với social.moderate],
```

`social.view`/`social.post`/`social.comment`/`social.like` **không cần**
thêm — mọi user đã đăng nhập được phép các hành động này mặc định (không
qua `PermissionService::allows()`, chỉ cần `auth` middleware), vì người
yêu cầu xác nhận "mọi nhân viên đăng được" — thêm permission key cho hành
động ai cũng làm được là thừa (không đúng tinh thần "field chỉ khi có dữ
liệu thật", ở đây là "permission key chỉ khi có phân biệt role thật").

Thêm `catalog` (mục `label`/`module`/`description` tiếng Việt, đồng bộ
pattern đã có, xem plan `2026-08-24-quan-ly-phan-quyen-superadmin.md` §3.5):

```php
'catalog' => [
    // ... giữ nguyên các key cũ ...
    'social.moderate' => [
        'label' => 'Kiểm duyệt bảng tin',
        'module' => 'Mạng nội bộ',
        'description' => 'Xoá bài viết/bình luận của người khác trong phòng ban quản lý',
    ],
    'social.pin' => [
        'label' => 'Ghim thông báo',
        'module' => 'Mạng nội bộ',
        'description' => 'Ghim bài viết lên panel Thông báo công ty',
    ],
],
```

### 3.6 Route (`Modules/Social/routes/api.php`)

```php
Route::middleware('auth')->prefix('social')->name('social.')->group(function () {
    Route::get('/posts', [SocialPostController::class, 'index']);
    Route::post('/posts', [SocialPostController::class, 'store']);
    Route::put('/posts/{post}', [SocialPostController::class, 'update']);
    Route::delete('/posts/{post}', [SocialPostController::class, 'destroy']);
    Route::post('/posts/{post}/like', [SocialPostController::class, 'toggleLike']);
    Route::post('/posts/{post}/share', [SocialPostController::class, 'share']);
    Route::post('/posts/{post}/pin', [SocialPostController::class, 'pin']);
    Route::delete('/posts/{post}/pin', [SocialPostController::class, 'unpin']);
    Route::get('/pinned', [SocialPostController::class, 'pinned']);

    Route::get('/posts/{post}/comments', [SocialCommentController::class, 'index']);
    Route::post('/posts/{post}/comments', [SocialCommentController::class, 'store']);
    Route::delete('/comments/{comment}', [SocialCommentController::class, 'destroy']);
});
```

`SocialServiceProvider::registerRoutes()` bọc `middleware('web')->prefix('api')`
giống hệt `EvaluationServiceProvider` (xem
`Modules/Evaluation/App/Providers/EvaluationServiceProvider.php`), để dùng
được session auth của SPA (không phải Sanctum token riêng).

### 3.7 Controller

Mỏng — chỉ decode request → gọi Service → trả JSON, theo đúng mẫu
`EvaluationCriteriaController`. `SocialPostController::destroy`/`pin` inject
`PermissionService` để pre-check trước khi gọi Service (tránh Service ném
exception cho luồng bình thường — check quyền ở Controller, business logic
ở Service, đúng phân tầng đã dùng ở Evaluation).

### 3.8 Form Requests

- `StoreSocialPostRequest`: `content` (`nullable|string|max:5000`, nhưng
  bắt buộc có **hoặc** `content` **hoặc** ít nhất 1 file trong
  `attachments` — rule `required_without:attachments`), `attachments.*`
  (`file|max:10240|mimes:jpg,jpeg,png,gif,pdf,doc,docx,xlsx`).
- `UpdateSocialPostRequest`: `content` (`required|string|max:5000` — sửa
  bài không cho xoá hết còn trống, khác lúc tạo).
- `StoreSocialCommentRequest`: `content` (`required|string|max:2000`).

### 3.9 Lưu file đính kèm

Dự án **chưa có cơ chế upload dùng chung** (đã khảo sát: không có
`Storage::` call nào trong repo, `avatar_url` chỉ là URL từ Google OAuth,
không phải file tải lên). Module Social tự dựng từ đầu, dùng đúng khung
Laravel mặc định thay vì tự chế:

- Disk `public` (`config/filesystems.php`, đã khai báo sẵn, chưa ai dùng) —
  lưu tại `storage/app/public/social/{post_id}/{filename}`, serve qua
  `APP_URL/storage/...`.
- Cần chạy `php artisan storage:link` (tạo symlink `public/storage` →
  `storage/app/public`) — bước cài đặt thủ công 1 lần trên môi trường
  deploy, ghi vào bước 2 mục 5 (không phải migration, không tự động qua
  code).
- `SocialPostService::create()`/`update()` gọi
  `$file->store('social/'.$post->id, 'public')`, lưu path trả về (không
  lưu full URL) vào từng phần tử `attachments` JSON, kèm `type`
  (`image`/`file`), `name` gốc, `size` — Model
  `getAttachmentsAttribute()`/response `present()` tự ghép
  `Storage::disk('public')->url($path)` khi trả JSON, để đổi disk sau này
  (vd. sang S3) không phải sửa dữ liệu đã lưu.
- Form Request rule cụ thể (chưa có convention sẵn trong repo, tự đặt theo
  nhu cầu thực tế bài đăng nội bộ, không phải kho tài liệu lớn):
  `attachments.*` → `file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xlsx,xls`
  (10MB/file), tối đa 5 file/bài (`attachments` → `array|max:5`).
- Không dùng S3 ở lượt này — biến `AWS_*` trong `.env` đang để trống, chưa
  có nhu cầu/hạ tầng cloud storage thật; giữ disk `local/public` cho đơn
  giản, đổi sang S3 sau chỉ cần đổi `FILESYSTEM_DISK` nhờ thiết kế ở trên
  (lưu path tương đối, không lưu full URL).

## 4. Frontend (`Modules/Social/resources/js`)

### 4.1 Route Vue

Đăng ký trực tiếp trong `resources/js/router/index.js` gốc (route dùng
chung mọi role, không thuộc khu vực `manager`/`superadmin` nào — khác pattern
Evaluation vốn nhúng vào router con của WorkspaceConfig):

```js
{
  path: '/social',
  name: 'social.feed',
  component: () => import('@modules/Social/resources/js/pages/SocialFeed.vue'),
  meta: { requiresAuth: true },
}
```

### 4.2 Mục sidebar

Thêm vào `MENU_SECTIONS` trong `resources/js/components/AppSidebar.vue`
(section `general`, cạnh `home`):

```js
{ name: 'social.feed', label: 'Bảng tin', icon: 'megaphone', configurableByDepartment: true },
```

Và thêm vào `CONFIGURABLE_MENUS` trong
`Modules/WorkspaceConfig/App/Services/DepartmentSidebarConfigService.php`:

```php
'social.feed' => 'Bảng tin',
```

(đúng yêu cầu: trưởng đơn vị ẩn được mục này cho phòng ban mình, dùng lại
cơ chế `DepartmentSidebarConfigService`/`configurableByDepartment` đã có
sẵn cho `home` — không xây cơ chế ẩn/hiện riêng.)

### 4.3 Trang chính — `pages/SocialFeed.vue`

Bố cục 2 cột (CLAUDE.md mục 7/8 — vừa màn hình, scroll trong vùng nội
dung riêng, không scroll cả trang):

```
┌───────────────────────────────────────┬─────────────────────┐
│ [avatar] [Đăng bài viết mới........]   │ 📌 Thông báo công ty│
│  [Ảnh/File]                            │ ─────────────────── │
├───────────────────────────────────────┤ · Tiêu đề thông báo 1│
│ (scroll riêng, overflow-y: auto)       │ · Tiêu đề thông báo 2│
│ ┌─────────────────────────────────┐   │                      │
│ │ [avatar] Tên người đăng · giờ    │   │ 🎂 Sinh nhật         │
│ │ Nội dung bài viết...             │   │ ─────────────────── │
│ │ [ảnh/file đính kèm nếu có]       │   │ (rỗng — chờ dữ liệu  │
│ │ 3 lượt thích · 2 bình luận       │   │  ngày sinh từ HRM,   │
│ │ [Thích] [Bình luận] [Chia sẻ]    │   │  panel ẩn nếu không  │
│ │ ─ danh sách bình luận ─          │   │  có gì hiện — CLAUDE │
│ │ [avatar][viết bình luận...]      │   │  .md mục 14)         │
│ └─────────────────────────────────┘   │                      │
│ ┌─────────────────────────────────┐   │                      │
│ │ (bài tiếp theo...)               │   │                      │
└───────────────────────────────────────┴─────────────────────┘
```

- Cột trái: `SocialPostComposer.vue` (form đăng bài, sticky trên cùng) +
  danh sách `SocialPostCard.vue` (infinite scroll hoặc "Xem thêm" — dùng
  cursor pagination từ `SocialPostRepository::paginate()`).
- Cột phải: `SocialPinnedPanel.vue` + `SocialBirthdayPanel.vue` — panel
  sinh nhật **chỉ render nếu có dữ liệu** (v-if trên chính component, đúng
  CLAUDE.md mục 14 — không giữ chỗ rỗng); vì hiện chưa có nguồn dữ liệu
  ngày sinh, component gọi API trả mảng rỗng → panel tự ẩn, không hiện
  khung "chưa có ai sinh nhật" placeholder.

### 4.4 Component con

- `SocialPostComposer.vue` — textarea (không dùng `placeholder` làm nhãn
  chính, có label ẩn-cho-screen-reader hoặc label chữ thật "Đăng bài viết
  mới" hiển thị sẵn cạnh avatar, theo CLAUDE.md mục 13) + input file ẩn +
  preview ảnh/file đã chọn trước khi đăng.
- `SocialPostCard.vue` — props `post` (theo shape `present()` ở 3.4),
  emit `@like`, `@share`, `@delete`, `@pin`. Nút Thích đổi trạng thái ngay
  (optimistic, patch tại chỗ theo response `toggleLike` — CLAUDE.md mục
  14, không refetch feed). Nút xoá chỉ hiện nếu `post.can_delete`; nút ghim
  chỉ hiện nếu `post.can_pin`.
- `SocialCommentList.vue` — danh sách bình luận + ô nhập bình luận mới,
  patch trực tiếp vào mảng comments khi POST thành công (không refetch).
- `SocialPinnedPanel.vue` — danh sách bài ghim rút gọn (tiêu đề + link
  scroll-to trong feed, hoặc mở modal xem nhanh).
- `SocialBirthdayPanel.vue` — hiện khung rỗng có ghi chú kỹ thuật trong
  code (comment, không hiện ra UI) rằng nguồn dữ liệu là ngày sinh nhân sự
  từ API HRM tương lai, cùng tinh thần với
  `Modules/Identity/App/Models/Department.php` (dữ liệu tạm chờ HRM) — xem
  `[[hrm-employee-sync-future]]` trong memory dự án.

### 4.5 State

Không cần Pinia store riêng — feed chỉ dùng trong `SocialFeed.vue`, local
`ref`/`reactive`, gọi `window.axios` trực tiếp (đúng pattern
`WorkspaceConfigEvaluation.vue`, không tạo axios instance riêng).

### 4.6 Responsive (CLAUDE.md mục 7)

- Desktop (≥1280px): 2 cột như wireframe trên, cột phải cố định chiều
  rộng (~320px).
- Tablet (≤768px): cột phải chuyển xuống dưới cột trái (stack dọc), hoặc ẩn
  sau tab/toggle nếu quá dài — quyết định cụ thể để lúc code, ưu tiên
  stack dọc đơn giản trước.
- Mobile (≤480px): 1 cột, panel "Thông báo công ty" hiện như 1 khối gấp gọn
  (collapsible) phía trên feed thay vì cột riêng.

## 5. Các bước triển khai (thứ tự đề xuất)

1. Scaffold module `Social` (dùng skill `new-module` nếu có sẵn quy trình,
   hoặc tạo tay theo cấu trúc mục 3.0).
2. Migration 3 bảng (3.1) + chạy `php artisan migrate` + chạy
   `php artisan storage:link` (một lần, tạo symlink cho disk `public` — xem
   3.9).
3. Model + Repository (interface + impl) + bind trong
   `SocialServiceProvider::register()`.
4. `config/permissions.php`: thêm `social.moderate`, `social.pin` vào
   `matrix` (role phù hợp) + `catalog` (label tiếng Việt).
5. Service (`SocialPostService`, `SocialCommentService`) + Form Requests.
6. Controller + route `routes/api.php` + đăng ký trong
   `SocialServiceProvider::registerRoutes()`.
7. Frontend: route Vue (`resources/js/router/index.js`), mục sidebar
   (`AppSidebar.vue` + `DepartmentSidebarConfigService::CONFIGURABLE_MENUS`).
8. Frontend: `SocialFeed.vue` + 5 component con (4.3–4.4), responsive 3
   breakpoint.
9. Test — xem mục 6a bên dưới.
10. `theme-check` skill trước khi hoàn thành (không border theo hướng,
    không title/tooltip, không badge/pill trạng thái, field ngay hàng nếu
    có panel chi tiết, dùng `var(--color-*)`).

## 6a. Test Plan

- **Đăng bài**: user thường đăng bài text-only → thành công; đăng bài
  không `content` và không `attachments` → 422 (`required_without`).
- **Sửa bài**: chính chủ sửa `content` → thành công; user khác cố sửa bài
  không phải của mình → 403.
- **Xoá bài**: chính chủ xoá bài của mình → thành công (soft delete);
  `department_director` xoá bài của nhân viên **cùng phòng ban mình quản
  lý** → thành công (dùng `social.moderate` + scope `department`), ghi
  `ActivityLogService`; `department_director` cố xoá bài của nhân viên
  **phòng ban khác** → 403 (`allows()` từ chối do scope không khớp); user
  thường (không có `social.moderate`) cố xoá bài người khác → 403.
- **Thích**: like lần đầu → `liked=true`, `likes_count` +1; gọi lại cùng
  endpoint (toggle) → `liked=false`, `likes_count` -1; 2 request đồng thời
  từ cùng user (race) → không tạo 2 row (unique index `post_id+user_id`
  chặn).
- **Bình luận**: tạo bình luận → xuất hiện trong `forPost()`, `comments_count`
  patch đúng; xoá bình luận không phải của mình và không có
  `social.moderate` → 403.
- **Chia sẻ**: share 1 bài → tạo `SocialPost` mới có `shared_from_post_id`
  đúng, `present()` trả `shared_from` là bản rút gọn của bài gốc; share 1
  bài đã bị xoá (soft-deleted) → 404/422 (không cho share bài không còn
  tồn tại).
- **Ghim**: user có `social.pin` ghim bài → `is_pinned=true`,
  `pinned_by`/`pinned_at` set đúng, bài xuất hiện ở đầu feed **và** trong
  `pinned()`; user không có `social.pin` cố ghim → 403.
- **Feed pagination**: bài ghim luôn xếp trước bài thường bất kể
  `created_at`; trong cùng nhóm ghim/không ghim, sắp theo `created_at` giảm
  dần.
- **Sidebar ẩn theo phòng ban**: `department_director` ẩn mục "Bảng tin"
  cho phòng ban mình (qua `DepartmentSidebarConfigService`) → thành viên
  phòng đó không thấy mục "Bảng tin" trong `AppSidebar.vue`
  (`auth.hiddenMenuKeys` chứa `social.feed`); user phòng ban khác vẫn thấy
  bình thường.

## 7. Rủi ro / điểm cần xác nhận với người yêu cầu

**Đã chốt trong quá trình lập plan:**

- Mọi nhân viên đăng bài được (không hạn chế chỉ admin) — cần cơ chế kiểm
  duyệt cho quản lý (`social.moderate`, scope department).
- Sinh nhật: để trống, chỉ dựng khung UI, chờ dữ liệu ngày sinh từ HRM
  trong tương lai — **không** thêm cột vào `users` ở lượt này.
- Mục sidebar "Bảng tin" ở section chung (không phải khu quản lý riêng),
  nhưng **configurable theo phòng ban** — trưởng đơn vị ẩn được, dùng lại
  cơ chế `DepartmentSidebarConfigService` sẵn có.
- "Thông báo công ty" = bài viết được ghim, không tạo bảng/khái niệm riêng.
- Bình luận chỉ 1 cấp (không reply lồng nhau).
- **Không làm bình chọn (poll)** ở lượt này (đã xác nhận với người yêu cầu).
- **Bảng tin dùng chung toàn công ty** (đã xác nhận) — mọi user đã đăng
  nhập xem được toàn bộ bài viết, không phân mảnh theo phòng ban/nhóm.
  Mục sidebar vẫn ẩn/hiện được theo phòng ban (mục 4.2), nhưng đó là ẩn
  *lối vào trang*, không phải ẩn *nội dung bài viết theo phòng ban* — 2
  việc tách biệt.
- **Lưu file đính kèm bằng disk `public` mặc định của Laravel** (đã khảo
  sát: dự án chưa có cơ chế upload nào sẵn có để tái dùng — không
  `Storage::` call nào trong repo, `avatar_url` chỉ là URL từ Google OAuth
  chứ không phải file tải lên). Không dùng S3 (biến `AWS_*` trong `.env`
  đang trống, chưa có hạ tầng). Chi tiết cơ chế: mục 3.9. Giới hạn tạm đặt
  `max:10240` KB (10MB)/file, tối đa 5 file/bài — mức hợp lý cho bài đăng
  nội bộ, có thể chỉnh khi thấy không phù hợp thực tế.

Không còn điểm nào treo — plan sẵn sàng để triển khai theo thứ tự ở mục 5.

## 8. Giai đoạn 2 — Reaction đa dạng, emoji picker, reply lồng 1 cấp

Sau khi giai đoạn 1 (mục 1–7) chạy được, người yêu cầu xem UI tham khảo
1Office và muốn mở rộng 3 điểm — đã xác nhận cả 3:

1. **Reaction 6 loại** (👍 Thích, ❤️ Yêu thích, 😂 Haha, 😮 Wow, 😢 Buồn,
   😡 Phẫn nộ) thay vì chỉ 1 nút "Thích" nhị phân.
2. **Emoji picker** đầy đủ khi viết bài/bình luận (bảng emoji lớn, nhiều
   nhóm), không chỉ gõ emoji bàn phím hệ điều hành.
3. **Bình luận có reply lồng 1 cấp** (không lồng vô hạn — trả lời của trả
   lời vẫn hiện phẳng dưới bình luận gốc).

### 8.1 Backend — Reaction

Đổi `social_post_likes` từ nhị phân sang có kiểu, migration mới thêm cột:

```php
Schema::table('social_post_likes', function (Blueprint $table) {
    $table->string('reaction_type', 20)->default('like')->after('user_id');
});
```

Giá trị hợp lệ: `like`, `love`, `haha`, `wow`, `sad`, `angry` (validate ở
Form Request, không cần bảng danh mục riêng — 6 loại cố định, không có nhu
cầu quản trị thêm/bớt loại reaction qua UI).

- `SocialPostRepositoryInterface::toggleLike()` đổi thành
  `setReaction(SocialPost $post, int $userId, string $type): array` — nếu
  user chưa có reaction nào trên bài → tạo mới; nếu đã có reaction khác →
  **cập nhật type** (không tạo row thứ 2, vẫn giữ unique
  `post_id+user_id`); nếu gọi lại đúng type đang có → xoá (bỏ reaction).
  Trả `['action' => 'set'|'removed', 'reaction_type' => ?string]`.
- Cần thêm `reactionSummary(SocialPost $post): array` — đếm theo từng loại
  để hiện icon tổng hợp kiểu Facebook, vd.
  `['like' => 3, 'love' => 1, 'sad' => 0, ...]`, cộng `total`.
- `present()` trả thêm `reactions: {counts: {...}, total: int, my_reaction:
  string|null}` thay cho `likes_count`/`liked_by_me` cũ (đổi tên field —
  chấp nhận breaking change vì đây vẫn giai đoạn phát triển, chưa có dữ
  liệu người dùng thật cần giữ tương thích ngược).
- Route `POST /api/social/posts/{postId}/like` đổi thành
  `POST /api/social/posts/{postId}/reactions` với body `{type: 'like'|...}`
  (giữ tên `like` cũ có thể gây hiểu nhầm là chỉ 1 loại — đặt tên route rõ
  ràng hơn từ đầu thay vì giữ tên cũ rồi giải thích thêm).

### 8.2 Backend — Reply lồng 1 cấp

Migration thêm cột vào `social_post_comments`:

```php
Schema::table('social_post_comments', function (Blueprint $table) {
    $table->foreignId('parent_comment_id')->nullable()
        ->after('post_id')->constrained('social_post_comments')->cascadeOnDelete();
});
```

- Giới hạn 1 cấp ở tầng Service: `SocialCommentService::create()` — nếu
  `parent_comment_id` được truyền, validate comment cha đó **chính nó**
  không có `parent_comment_id` (tức cha phải là comment gốc, không cho
  reply-của-reply) → nếu vi phạm, ném `ValidationException` thay vì âm thầm
  gắn phẳng, để frontend không tạo được UI reply lồng sâu hơn dự kiến.
- `SocialCommentRepositoryInterface::forPost()` trả cấu trúc phân cấp: mỗi
  comment gốc kèm mảng `replies` (không phải danh sách phẳng để frontend tự
  gom nhóm — nhất quán nguyên tắc "backend là single source of truth" đã
  dùng ở permission matrix).
- `cascadeOnDelete()` trên `parent_comment_id`: xoá comment gốc kéo theo
  xoá hết reply — chấp nhận được vì soft-delete (`SoftDeletes` đã có sẵn
  trên model) nên dữ liệu không mất vĩnh viễn.

### 8.3 Frontend — Reaction picker

`components/SocialReactionPicker.vue` mới:
- Nút hành động "Thích" trong `SocialPostCard.vue` đổi hành vi: click
  nhanh = toggle reaction mặc định (`like`, giữ hành vi quen thuộc); hover
  (desktop) hoặc long-press (mobile, dùng `@touchstart` với timer) hiện
  popup 6 icon reaction để chọn loại khác — đúng UX quen thuộc Facebook/
  1Office trong ảnh tham khảo.
- Hiện tổng hợp reaction dạng cụm icon nhỏ chồng nhau (chỉ icon loại có
  `count > 0`, sort theo count giảm dần) + tổng số — thay vì chỉ số
  "X lượt thích" như giai đoạn 1.
- Icon 6 reaction dùng emoji Unicode trực tiếp (không cần SVG riêng —
  `👍 ❤️ 😂 😮 😢 😡` render nhất quán đủ tốt trên các trình duyệt hiện đại,
  tránh phải vẽ thêm 6 icon vào `AppIcon.vue` vốn dùng path SVG tối giản
  không hợp phong cách "biểu cảm" của reaction).

### 8.4 Frontend — Emoji picker khi viết bài/bình luận

`components/SocialEmojiPicker.vue` mới — dùng danh sách emoji tĩnh nhúng
sẵn trong code (không gọi API/CDN ngoài, tuân thủ nguyên tắc self-contained,
và tránh thêm dependency ngoài không cần thiết cho nhu cầu này):
- Nhóm theo category tối thiểu (Mặt cười, Cử chỉ, Trái tim, Đồ vật/khác) —
  đủ để không phải cuộn 1 danh sách phẳng dài như ảnh tham khảo (ảnh 2 có
  tab category phía trên bảng).
- Gắn vào `SocialPostComposer.vue` (nút mặt cười cạnh nút Ảnh/File, đúng vị
  trí trong ảnh tham khảo) và `SocialCommentList.vue` (nút mặt cười trong ô
  viết bình luận) — click emoji chèn vào vị trí con trỏ hiện tại trong
  textarea/input, không thay thế toàn bộ nội dung.
- Popup đóng khi click ra ngoài (`@click.self` trên overlay hoặc
  `document.addEventListener('click', ...)` với kiểm tra
  `target.closest()`).

### 8.5 Frontend — Reply lồng 1 cấp

`SocialCommentList.vue` cập nhật:
- Mỗi comment gốc hiện nút "Trả lời" (bên cạnh nút xoá, chỉ hiện cho
  comment gốc — không hiện trên reply, đúng giới hạn 1 cấp ở 8.2).
- Click "Trả lời" mở 1 ô nhập nhỏ ngay dưới comment đó (không dùng ô nhập
  chung ở cuối danh sách) — gửi kèm `parent_comment_id`.
- Reply hiển thị thụt lề nhẹ (`padding-left`, không dùng `border-left` theo
  CLAUDE.md mục 2) dưới comment gốc tương ứng, avatar nhỏ hơn comment gốc
  (24px thay vì 28px) để phân biệt cấp bậc trực quan mà không cần đường kẻ
  dọc.

### 8.6 Việc KHÔNG làm trong giai đoạn 2 (giữ đúng phạm vi đã chốt)

- **Không làm bình chọn (poll)** — dù ảnh tham khảo 1Office có nút "Bình
  chọn" cạnh "Ảnh/File", người yêu cầu đã xác nhận trước đó không cần tính
  năng này; không tự thêm lại chỉ vì xuất hiện trong ảnh tham khảo giao
  diện.
- **Không đổi cấu trúc card trái (profile + menu Sơ đồ tổ chức/Lộ trình
  thăng tiến/...)** — đó là toàn bộ hệ thống điều hướng 1Office ngoài phạm
  vi module `Social`; sidebar hiện tại của VA Workspace (`AppSidebar.vue`)
  giữ nguyên cấu trúc chung của app, module `Social` chỉ đóng góp 1 mục
  "Bảng tin" vào đó (đã làm ở mục 4.2), không thay thế toàn bộ sidebar.
- **Không làm "Bài viết chờ duyệt"** riêng (kiểm duyệt trước khi đăng) —
  khác với `social.moderate` (xoá sau khi đăng) đã có; nếu cần workflow
  duyệt-trước-khi-hiện, đó là thay đổi lớn hơn (thêm trạng thái `pending`
  cho bài viết) cần xác nhận riêng, không tự suy diễn từ ảnh tham khảo.

## 6b. Test Plan bổ sung (giai đoạn 2)

- **Reaction**: user chưa reaction, gọi `like` → tạo reaction `like`; gọi
  lại `love` trên cùng bài → cập nhật thành `love` (không tạo row thứ 2,
  vẫn 1 row theo unique `post_id+user_id`); gọi lại đúng `love` lần nữa →
  xoá reaction (`action=removed`). `reactionSummary()` đếm đúng theo từng
  loại sau nhiều user reaction khác nhau trên cùng bài.
- **Reply lồng**: tạo comment gốc → tạo reply với `parent_comment_id` trỏ
  đúng → xuất hiện trong `replies` của comment gốc khi gọi `forPost()`; cố
  tạo reply có `parent_comment_id` trỏ tới 1 reply (không phải comment gốc)
  → 422, không tạo được lồng 2 cấp; xoá comment gốc → toàn bộ reply con bị
  xoá theo (cascade), `comments_count` trả về tính đúng tổng cả gốc + reply.
