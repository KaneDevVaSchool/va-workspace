# Module `Social` — Bảng tin nội bộ

> Cập nhật: 2026-08-26. Module **đã dựng và chạy** trong repo nhưng **chưa từng
> được ghi vào `VA_WORKSPACE_OVERVIEW.md`** (không nằm trong bản đồ module §2
> gốc) — file này bù lại, mô tả đúng trạng thái code thật tại thời điểm viết.
> Đã đồng bộ vào overview §2/§19, xem ghi chú ở đó.

Mạng nội bộ dạng "bảng tin" (feed) cho toàn công ty: đăng bài, thả cảm xúc,
bình luận, bình chọn (poll), nhóm (group) riêng tư/công khai, sticker động,
@mention, ghim bài, và tường (wall) theo phòng ban/cá nhân/nhóm.

## 1. Vị trí trong hệ thống

- Route JSON: `Modules/Social/routes/api.php` → prefix `/api/social`, `name('social.')`,
  middleware `auth` (session `web`, giống Identity/WorkspaceConfig — **không** Bearer stateless).
- Route Vue: `Modules/Social/resources/js/router.js` → `/social` (bảng tin),
  `/social/groups`, `/social/groups/:id` (tường nhóm).
- Không có route `manager`/`superadmin` riêng — quyền kiểm duyệt (ghim hệ thống,
  xoá bài người khác) dùng `PermissionService` chung của Identity, không phải
  route theo scope như WorkspaceConfig.

## 2. Kiến trúc — Controller → Service → Repository

Theo đúng pattern bắt buộc (§5 CLAUDE.md):

| Domain | Controller | Service | Repository interface |
|---|---|---|---|
| Bài đăng, cảm xúc, ghim, lượt xem, chia sẻ | `SocialPostController` | `SocialPostService` | `SocialPostRepositoryInterface` |
| Bình luận (đa cấp, cảm xúc riêng) | `SocialCommentController` | `SocialCommentService` | `SocialCommentRepositoryInterface` |
| Bình chọn (poll) | `SocialPollController` | `SocialPollService` | `SocialPollRepositoryInterface` |
| Nhóm (group), thành viên, lời mời, yêu cầu tham gia | `SocialGroupController` | `SocialGroupService` | `SocialGroupRepositoryInterface` |

Service phụ trợ dùng chung trong module: `SocialContentSanitizer` (lọc HTML nội
dung bài/bình luận), `SocialMentionService` (parse `@mention` → thông báo/liên kết).

Binding interface → implementation: `Modules/Social/App/Providers/SocialServiceProvider.php`.

## 3. Model & bảng dữ liệu (tiền tố `va_workspace_` tự động)

| Bảng | Model | Ghi chú |
|---|---|---|
| `social_posts` | `SocialPost` | Cột định tuyến tường: `department_id`, `wall_user_id`, `group_id` (đúng 1 trong 3 hoặc cả 3 null = bảng tin chung). `pin_scope` (`company`/`system`), `is_pinned`. |
| `social_post_department_visibility` | `SocialPostDepartmentVisibility` | **Mới** — danh sách phòng ban cho `department_visibility_mode = include/exclude`, chỉ áp dụng bài trên bảng tin chung. |
| `social_post_views` | `SocialPostView` | **Mới** — lượt xem duy nhất (`unique(post_id, user_id)`), không tính view của chính tác giả. |
| `social_post_likes` | `SocialPostLike` | Cảm xúc theo `reaction_type` (không chỉ like/dislike nhị phân). |
| `social_post_comments` | `SocialPostComment` | Đa cấp qua `parent_comment_id`, có `attachments`, `mentioned_user_id`. |
| `social_comment_likes` | `SocialCommentLike` | Cảm xúc riêng cho bình luận. |
| `social_post_revisions` | `SocialPostRevision` | Lịch sử sửa bài (endpoint `GET /posts/{id}/revisions`). |
| `social_polls`, `social_poll_options`, `social_poll_votes` | `SocialPoll`, `SocialPollOption`, `SocialPollVote` | Poll gắn 1-1 với 1 bài đăng, có `title`/`content`/`image` riêng, `allow_multiple`, `ends_at`, `show_results`. |
| `social_groups`, `social_group_members`, `social_group_join_requests` | `SocialGroup`, `SocialGroupMember`, `SocialGroupJoinRequest` | Nhóm có `avatar`, `invite_kind`; yêu cầu tham gia cần duyệt (owner/admin nhóm). |

**Không có Repository nào query trực tiếp bảng `tasks`/module khác** — Social
độc lập hoàn toàn, chỉ phụ thuộc `Identity` (User/Department) qua model dùng chung.

## 4. Tính năng đã chạy

- Đăng bài trên 4 loại tường: **bảng tin chung** (company), **phòng ban**
  (`department_id`), **cá nhân** (`wall_user_id` — tường của mình hoặc người khác),
  **nhóm** (`group_id`). Đính kèm ảnh/file (`attachments`, tối đa 10), sticker
  động (Lottie), nội dung rich text (Tiptap — `SocialPostEditor.vue`).
- Đăng "thông báo quan trọng" (`pin_scope = system`) — chỉ `super_admin` (theo
  `auth.showSuperAdminNav` phía FE, kiểm tra quyền thật ở Service).
- Sửa bài (lưu revision), xoá bài (tác giả hoặc người có quyền kiểm duyệt theo
  phòng ban), ghim/bỏ ghim, chia sẻ bài (`SocialShareDialog.vue` → `sharedFrom`).
- Cảm xúc (reaction) nhiều loại cho cả bài và bình luận, có hiệu ứng "burst".
- Bình luận đa cấp (reply lồng), cảm xúc riêng, đính kèm, `@mention` → tab
  "Được nhắc đến" (`GET /mentions`).
- Bình chọn (poll): tạo cùng lúc với bài, vote (đơn/nhiều lựa chọn), xem ai đã
  vote, đóng poll sớm, đếm ngược hạn.
- Nhóm: tạo/sửa/xoá, tham gia công khai hoặc gửi yêu cầu, mời thành viên, duyệt/
  từ chối yêu cầu, đổi vai trò thành viên, chuyển quyền sở hữu nhóm, rời nhóm.
- Hồ sơ cá nhân trên bảng tin (`SocialProfilePanel.vue`): thống kê `posts_count`,
  `reactions_received`, `comments_count` (`GET /me/stats`).
- Panel sinh nhật (`SocialBirthdayPanel.vue`), panel bài ghim (`SocialPinnedPanel.vue`).

## 5. Đang làm dở tại thời điểm viết (working tree, chưa commit)

Hai tính năng mới, **đã có migration + Model + Repository + Service + Controller +
route + FE + test**, nhưng **chưa chạy migration trên môi trường thật và chưa commit**:

1. **Lượt xem bài viết (`views_count`)**
   - Bảng `social_post_views`, unique `(post_id, user_id)`; tác giả xem bài của
     chính mình không tính.
   - Ghi nhận qua `POST /api/social/posts/{id}/view`; FE tự gọi khi bài cuộn
     vào viewport ≥40% trong 600ms (`IntersectionObserver` trong `SocialPostCard.vue`),
     không cần người dùng bấm gì.
   - Hiển thị "N lượt xem" cạnh số bình luận, chỉ hiện khi > 0 (đúng §14 CLAUDE.md
     — không giữ chỗ khi chưa có dữ liệu).
   - Test: `tests/Feature/Social/SocialPostViewTest.php` (4 test: đếm lượt xem,
     unique theo viewer, tác giả không tự tính, nhiều viewer cộng dồn).

2. **Giới hạn hiển thị bài theo phòng ban (`department_visibility_mode`)**
   - Chỉ áp dụng cho bài đăng trên **bảng tin chung** (department/wall/group đều
     null) — các tường khác luôn `all`.
   - 3 chế độ: `all` (mặc định, ai cũng thấy), `include` (chỉ phòng ban được
     chọn), `exclude` (mọi phòng ban trừ phòng ban được chọn) — danh sách phòng
     ban lưu ở bảng `social_post_department_visibility`.
   - Validate: chọn `include`/`exclude` bắt buộc chọn ≥1 phòng ban
     (`StoreSocialPostRequest`, cả FE lẫn `SocialPostService::resolveDepartmentVisibility()`).
   - Lọc ở tầng Repository (`applyDepartmentVisibility()` trong `SocialPostRepository`),
     áp dụng cho cả `paginate()` (feed) và `paginatePinned()` — người xem chưa
     có `department_id` chỉ thấy bài `all`.
   - FE: `SocialDepartmentVisibilityPicker.vue` (modal chọn chế độ + phòng ban,
     nút "Ai được thấy?" trong Composer khi đăng lên bảng tin chung), hiển thị
     lại trên `SocialPostCard.vue` bằng 1 dòng chữ + chấm màu (không badge, đúng §14).
   - **Chưa có test riêng** cho lọc theo phòng ban (chỉ có test cho lượt xem).

### Việc còn thiếu để coi là xong (checklist ngắn)

- [x] Migration đã chạy trên DB test riêng (`va_workspace_db_test`, ServBay,
      cấu hình sẵn trong `phpunit.xml`):
      `2026_08_25_260001_create_social_post_views_table.php`,
      `2026_08_25_270001_add_department_visibility_to_social_posts.php`.
- [x] Viết + **chạy pass** test cho `department_visibility_mode` —
      `tests/Feature/Social/SocialPostDepartmentVisibilityTest.php` (6 test:
      mặc định `all`, `include`, `exclude`, thiếu `department_visibility_ids`
      bị validate chặn, chế độ bị bỏ qua ngoài bảng tin chung, tác giả tự loại
      trừ phòng ban mình vẫn mở được bài qua link trực tiếp).
- [x] Xác nhận nghiệp vụ: `GET /posts/{id}` (xem chi tiết) **không** tự ghi lượt
      xem — chỉ bảng tin/tường (card cuộn vào viewport) mới tính. Đã xác nhận
      với người dùng, không sửa `SocialPostController::show()`.
- [x] `theme-check` cho `SocialDepartmentVisibilityPicker.vue`, `SocialPostCard.vue`,
      `SocialPostComposer.vue` — không phát hiện vi phạm mới. Có 1 vi phạm
      hex-color **cũ, có sẵn từ trước** trong `SocialPostCard.vue` (màu icon
      cảm xúc, dòng ~1196-1210), nằm ngoài phần đang hoàn thiện, chưa sửa.
- [x] **2 bug phát hiện qua chạy test thật, đã sửa:**
      1. `SocialPostService::create()` — khi bài có `department_visibility_ids`
         nhưng không có `poll`/`attachments`, response trả `department_visibility`
         rỗng dù đã ghi DB đúng (relation `departmentVisibilities` chưa được
         reload trước khi `present()`). Sửa: reload `$post` qua `find()` ngay
         sau `syncDepartmentVisibility()`, giống cách làm với `poll`.
      2. `SocialPostRepository::applyDepartmentVisibility()` — người dùng
         **chưa thuộc phòng ban nào** bị ẩn luôn cả bài `exclude` (đáng lẽ họ
         không nằm trong danh sách bị trừ nên phải thấy được). Xác nhận với
         người dùng: đúng ngữ nghĩa "trừ phòng ban X" là ai không thuộc X đều
         thấy, kể cả chưa có phòng ban — đã sửa nhánh `exclude` để không phụ
         thuộc `viewerDepartmentId !== null` nữa (chỉ nhánh `include` mới cần).
- [x] Chạy toàn bộ `php artisan test` (119 test / 542 assertion) — **pass**,
      không hồi quy module khác.
- [ ] Commit các file đang đổi + file mới trong `Modules/Social/` và `tests/`.
- [ ] Cập nhật doc này nếu nghiệp vụ đổi sau khi merge.

## 6. Chưa làm / ngoài phạm vi hiện tại

- Không có Repository/Service cho thống kê bảng tin theo phòng ban (VD "phòng
  ban nào tương tác nhiều nhất") — chưa có nhu cầu.
- Không có kiểm duyệt nội dung tự động (từ khoá cấm, AI) — `SocialContentSanitizer`
  chỉ lọc HTML không an toàn, không kiểm duyệt ngữ nghĩa.
- Không tách `notification` riêng khi có `@mention`/lượt thích — kiểm tra lại
  nếu module `Notification` (kế hoạch, §2 overview) được dựng, có thể cần nối
  `SocialMentionService` vào đó thay vì tự xử lý.
- Import/Export không áp dụng cho module này (không phải dữ liệu cấu hình dạng bảng).

## 7. Liên quan

- `Modules/Identity` — User/Department dùng chung, `PermissionService` cho
  quyền kiểm duyệt bài.
- `.claude/CLAUDE.md` §14 — quy tắc UI đơn giản (chấm màu thay badge, câu tiếng
  Việt phổ thông) đã áp dụng cho phần "Ai được thấy bài viết này?" và số lượt xem.
