# Tuần 35 — 24–30/08/2026

| | |
| --- | --- |
| **Cửa sổ** | Thứ 2 24/08/2026 → Chủ nhật 30/08/2026 |
| **Commit** | **98** |
| **Ngày có commit** | 6 |
| **Nhánh đối soát** | `main` |
| **Bảng audit** | [2026-W35-audit](./2026-W35-audit.md) |

## Theo ngày

| Ngày | Thứ | Commit | Nhóm việc | Phiếu |
| --- | --- | ---: | ---: | --- |
| **24/08/2026** | Thứ 2 | 20 | 20 | [`2026-08-24`](../2026-08-24/README.md) |
| **25/08/2026** | Thứ 3 | 18 | 18 | [`2026-08-25`](../2026-08-25/README.md) |
| **26/08/2026** | Thứ 4 | 22 | 22 | [`2026-08-26`](../2026-08-26/README.md) |
| **27/08/2026** | Thứ 5 | 4 | 4 | [`2026-08-27`](../2026-08-27/README.md) |
| **28/08/2026** | Thứ 6 | 25 | 25 | [`2026-08-28`](../2026-08-28/README.md) |
| **29/08/2026** | Thứ 7 | 0 | 0 | — |
| **30/08/2026** | Chủ nhật | 9 | 9 | [`2026-08-30`](../2026-08-30/README.md) |

## Module

| Module | Commit |
| --- | ---: |
| Bảng tin | 12 |
| Định danh và phân quyền | 15 |
| Đánh giá | 11 |
| Dự án và công việc | 23 |
| Cấu hình workspace | 8 |
| Nền tảng | 1 |
| Giao diện gốc | 12 |
| Tài liệu | 10 |
| Khác | 5 |
| Khác | 1 |

## Tính năng lớn

| Ngày | Module | Tính năng | Commit |
| --- | --- | --- | ---: |
| 24/08/2026 | Khác | Khởi tạo tooling và config gốc dự án | 1 |
| 24/08/2026 | Nền tảng | Nền tảng Laravel core (app, config, database, routes, tests) | 1 |
| 24/08/2026 | Giao diện gốc | Frontend gốc Vue 3 + shell Blade | 1 |
| 24/08/2026 | Khác | Thêm module mẫu Example theo pattern Controller-Service-Repository | 1 |
| 24/08/2026 | Định danh và phân quyền | Thêm module Identity — đăng nhập Google OAuth và quản lý user/department | 1 |
| 24/08/2026 | Định danh và phân quyền | Thêm AppLayout, RBAC tối giản và view-as cho super_admin | 1 |
| 24/08/2026 | Định danh và phân quyền | SPA session, CSRF, view-as và bootstrap super admin | 1 |
| 24/08/2026 | Định danh và phân quyền | Ma trận phân quyền superadmin + module Team | 1 |
| 24/08/2026 | Định danh và phân quyền | Hoàn thiện ma trận phân quyền và quản lý nhóm | 1 |
| 24/08/2026 | Định danh và phân quyền | Header mới, shortcut cá nhân và nhật ký hoạt động | 1 |
| 24/08/2026 | Định danh và phân quyền | Export excel nhật ký hoạt động + lọc nâng cao | 1 |
| 24/08/2026 | Giao diện gốc | Trang Tổng quan (Home) mới với widget dòng hoạt động | 1 |
| 24/08/2026 | Định danh và phân quyền | Nâng cấp trang Ma trận phân quyền theo pattern data-table | 1 |
| 24/08/2026 | Cấu hình workspace | Hub cấu hình workspace theo phòng ban | 1 |
| 24/08/2026 | Giao diện gốc | Cập nhật widget dòng hoạt động trên trang Tổng quan | 1 |
| 24/08/2026 | Cấu hình workspace | Gộp quản lý nhóm vào trang thành viên phòng ban | 1 |
| 24/08/2026 | Đánh giá | Thêm module Tiêu chí đánh giá theo phòng ban | 1 |
| 25/08/2026 | Đánh giá | Thêm loại tiêu chí đánh giá + cho phép nửa điểm | 1 |
| 25/08/2026 | Giao diện gốc | Sidebar phòng ban chỉ cấu hình menu thật, không phải tab hub | 1 |
| 25/08/2026 | Bảng tin | Thêm module Mạng nội bộ (bảng tin, bình luận, cảm xúc) | 1 |
| 25/08/2026 | Khác | Thêm cấu hình DB test riêng va_workspace_db_test cho PHPUnit | 1 |
| 25/08/2026 | Cấu hình workspace | Bỏ dạng pill/badge bo tròn khỏi nhãn trạng thái | 1 |
| 25/08/2026 | Bảng tin | Thêm tường cá nhân/phòng ban, lịch sử sửa bài, bình chọn | 1 |
| 25/08/2026 | Bảng tin | Bình luận lồng 2 cấp + xem danh sách người thả cảm xúc | 1 |
| 25/08/2026 | Bảng tin | Giao diện tường cá nhân/phòng ban, bình chọn, chia sẻ, đính kèm ảnh | 1 |
| 25/08/2026 | Định danh và phân quyền | Thêm thông báo đẩy (web push) và trung tâm thông báo | 1 |
| 25/08/2026 | Bảng tin | Nhắc tên người dùng trong bài viết/bình luận | 1 |
| 25/08/2026 | Bảng tin | Thêm nhóm/phòng ban, tường nhóm và sửa thông báo đẩy | 1 |
| 25/08/2026 | Bảng tin | Mời thành viên vào nhóm, ảnh đại diện nhóm, chia sẻ bài viết vào nhóm | 1 |
| 25/08/2026 | Bảng tin | Sticker động (Lottie) trong bài viết/bình luận | 1 |
| 25/08/2026 | Định danh và phân quyền | Mô tả nhật ký hoạt động không còn lẫn thẻ HTML | 1 |
| 25/08/2026 | Bảng tin | Cải thiện bảng tin, bình chọn, panel hồ sơ | 1 |
| 25/08/2026 | Giao diện gốc | Cập nhật trang chủ và DataStreamWidget | 1 |
| 25/08/2026 | Định danh và phân quyền | Định dạng lại Login.vue/AuthCallback.vue (thụt lề 4 khoảng trắng) | 1 |
| 26/08/2026 | Đánh giá | Định dạng lại modal tiêu chí đánh giá | 1 |
| 26/08/2026 | Bảng tin | Lượt xem bài viết và giới hạn hiển thị theo phòng ban | 1 |
| 26/08/2026 | Đánh giá | Tiêu chí ĐGNL, lịch sử và hub phòng ban | 1 |
| 26/08/2026 | Đánh giá | Mẫu đánh giá, vị trí đánh giá và import/export Excel | 1 |
| 26/08/2026 | Bảng tin | Hashtag trong bài viết | 1 |
| 26/08/2026 | Định danh và phân quyền | Hiện avatar + phòng ban trong nhật ký hoạt động | 1 |
| 26/08/2026 | Giao diện gốc | Thêm màu phụ "Nhân Ái" #009082 | 1 |
| 26/08/2026 | Cấu hình workspace | Tuỳ chỉnh menu sidebar theo phòng ban | 1 |
| 26/08/2026 | Cấu hình workspace | Áp width 28rem cho panel chi tiết theo mẫu vàng mới | 1 |
| 26/08/2026 | Đánh giá | Chặn trùng tên tiêu chí đánh giá trong cùng phòng ban | 1 |
| 26/08/2026 | Đánh giá | Gộp thao tác theo dòng vào 1 dropdown (mẫu vàng data-table) | 1 |
| 26/08/2026 | Bảng tin | Đăng ẩn danh + kiểm duyệt bài viết trước khi hiển thị công khai | 1 |
| 26/08/2026 | Cấu hình workspace | Sắp xếp lại thứ tự và nhóm menu sidebar theo phòng ban | 1 |
| 26/08/2026 | Đánh giá | Áp width 28rem cho panel chi tiết ở trang xem đánh giá | 1 |
| 26/08/2026 | Giao diện gốc | Nút hiện lại header căn giữa tuyệt đối, không lệch theo flex | 1 |
| 26/08/2026 | Giao diện gốc | Thêm trang 404 khi truy cập route không tồn tại | 1 |
| 26/08/2026 | Định danh và phân quyền | Ẩn/hiện menu sidebar toàn hệ thống (superadmin) | 1 |
| 26/08/2026 | Đánh giá | Cho phép super_admin tạo mẫu đánh giá dùng chung toàn hệ thống | 1 |
| 26/08/2026 | Định danh và phân quyền | Ẩn/hiện menu toàn hệ thống áp dụng cho cả super_admin, nâng cấp trang quản lý | 1 |
| 26/08/2026 | Định danh và phân quyền | Đổi tên, sắp xếp và cấu hình nhóm menu sidebar toàn hệ thống | 1 |
| 27/08/2026 | Giao diện gốc | Thêm màu tertiary và gold vào theme.css | 1 |
| 27/08/2026 | Cấu hình workspace | Sửa toggle ẩn/hiện menu toàn hệ thống và bỏ hint Brave không dùng | 1 |
| 27/08/2026 | Đánh giá | Bỏ CRUD tay Vị trí đánh giá, đổi trọng số sang %, gộp field tùy biến | 1 |
| 27/08/2026 | Dự án và công việc | Thêm module Dự án (giai đoạn 1 — CRUD) | 1 |
| 28/08/2026 | Dự án và công việc | Thêm loại dự án, import/export Excel và các picker liên quan | 1 |
| 28/08/2026 | Dự án và công việc | Menu chuột phải trên bảng dự án, nhân bản dự án, ngày thực tế | 1 |
| 28/08/2026 | Dự án và công việc | Validate ngày thực tế khi tạo dự án, nâng cấp modal Cập nhật thời gian | 1 |
| 28/08/2026 | Dự án và công việc | PR1 - entity Task thật, migration + Model + Enum (WBS đa cấp) | 1 |
| 28/08/2026 | Dự án và công việc | PR2 - Repository/Service/Controller/routes cho Task | 1 |
| 28/08/2026 | Giao diện gốc | PR3 - kích hoạt permission task.*, thêm mục sidebar Công việc | 1 |
| 28/08/2026 | Dự án và công việc | PR4 - trang "Tất cả công việc" (TaskList.vue) | 1 |
| 28/08/2026 | Dự án và công việc | PR5 - ProjectQuickActionModals dùng API Task thật | 1 |
| 28/08/2026 | Dự án và công việc | Cập nhật overview + README — Task thật (Giai đoạn 2) | 1 |
| 28/08/2026 | Dự án và công việc | Tab lọc nhanh, Kanban và nhóm theo dự án trên TaskList | 1 |
| 28/08/2026 | Dự án và công việc | TaskSeeder + hoàn thiện Kanban/quick-actions cho TaskList | 1 |
| 28/08/2026 | Dự án và công việc | PR1 - migration + model nền tảng mở rộng Task | 1 |
| 28/08/2026 | Dự án và công việc | PR2 - present() + validate + tính toán runtime cho Task | 1 |
| 28/08/2026 | Dự án và công việc | PR3 - hiển thị/sửa field mới trên TaskList (list/panel/Kanban) | 1 |
| 28/08/2026 | Dự án và công việc | PR4 - task_attachments full stack (Đính kèm công việc) | 1 |
| 28/08/2026 | Dự án và công việc | PR5 - task_worklogs full stack (Nhật ký giờ làm) + notification | 1 |
| 28/08/2026 | Dự án và công việc | PR6 - task_scores full stack (Đánh giá tối thiểu) + notification | 1 |
| 28/08/2026 | Dự án và công việc | PR7 - Filter/Sort/Bulk actions cho TaskList | 1 |
| 28/08/2026 | Dự án và công việc | PR8 - Export/Import Excel cho Task (Modal "Dữ liệu") | 1 |
| 28/08/2026 | Khác | Merge: mở rộng entity Task — PR1-PR8 (migration, backend, UI, attachments, worklogs, scores, filter/sort/bulk, Excel) | 1 |
| 28/08/2026 | Cấu hình workspace | Menu toàn hệ thống áp dụng đúng cho super_admin + fix thứ tự sidebar | 1 |
| 28/08/2026 | Khác | Merge: fix menu toàn hệ thống áp dụng cho super_admin + thứ tự sidebar | 1 |
| 30/08/2026 | Dự án và công việc | Chuyển giao công việc hàng loạt (Task Delegation Phase 3 §6) | 1 |
| 30/08/2026 | Dự án và công việc | Chế độ xem Lịch cho "Tất cả công việc" | 1 |
| 30/08/2026 | Giao diện gốc | TablePagesBar hỗ trợ đóng menu từ slot (theo sau Calendar View) | 1 |
| 30/08/2026 | Giao diện gốc | Thanh tiến độ 2 lớp (Dual Progress Bar) dùng chung, áp dụng cho Project | 1 |
| 30/08/2026 | Dự án và công việc | Dữ liệu mẫu cho Lịch công việc (theo sau Calendar View) | 1 |
| 30/08/2026 | Khác | Hook tự động cảnh báo vi phạm CLAUDE.md mục 2/12 sau mỗi Edit/Write .vue\|.css | 1 |
| 30/08/2026 | Dự án và công việc | Task đứng độc lập + watchers/collaborators + creation settings | 1 |
| 30/08/2026 | Đánh giá | Bộ điểm tiêu chí đánh giá + mức độ ưu tiên task nâng cao | 1 |

## Việc trong tuần

### 24/08/2026

- **1.1.** Khởi tạo tooling và config gốc dự án (1 lần lưu)
- **1.2.** Nền tảng Laravel core (app, config, database, routes, tests) (1 lần lưu)
- **1.3.** Frontend gốc Vue 3 + shell Blade (1 lần lưu)
- **1.4.** Thêm module mẫu Example theo pattern Controller-Service-Repository (1 lần lưu)
- **1.5.** Thêm module Identity — đăng nhập Google OAuth và quản lý user/department (1 lần lưu)
- **1.6.** Tài liệu dự án, kế hoạch triển khai và skill Claude Code (1 lần lưu)
- **1.7.** Thêm AppLayout, RBAC tối giản và view-as cho super_admin (1 lần lưu)
- **1.8.** SPA session, CSRF, view-as và bootstrap super admin (1 lần lưu)
- **1.9.** Commit Rules (1 lần lưu)
- **1.10.** Ma trận phân quyền superadmin + module Team (1 lần lưu)
- **1.11.** Hoàn thiện ma trận phân quyền và quản lý nhóm (1 lần lưu)
- **1.12.** Header mới, shortcut cá nhân và nhật ký hoạt động (1 lần lưu)
- **1.13.** Export excel nhật ký hoạt động + lọc nâng cao (1 lần lưu)
- **1.14.** Chuẩn hoá bảng dữ liệu theo pattern data-table + export Excel (1 lần lưu)
- **1.15.** Trang Tổng quan (Home) mới với widget dòng hoạt động (1 lần lưu)
- **1.16.** Nâng cấp trang Ma trận phân quyền theo pattern data-table (1 lần lưu)
- **1.17.** Hub cấu hình workspace theo phòng ban (1 lần lưu)
- **1.18.** Cập nhật widget dòng hoạt động trên trang Tổng quan (1 lần lưu)
- **1.19.** Gộp quản lý nhóm vào trang thành viên phòng ban (1 lần lưu)
- **1.20.** Thêm module Tiêu chí đánh giá theo phòng ban (1 lần lưu)

### 25/08/2026

- **1.1.** Thêm skill/rule form-modal (1 lần lưu)
- **1.2.** Thêm loại tiêu chí đánh giá + cho phép nửa điểm (1 lần lưu)
- **1.3.** Sidebar phòng ban chỉ cấu hình menu thật, không phải tab hub (1 lần lưu)
- **1.4.** Thêm module Mạng nội bộ (bảng tin, bình luận, cảm xúc) (1 lần lưu)
- **1.5.** Thêm cấu hình DB test riêng va_workspace_db_test cho PHPUnit (1 lần lưu)
- **1.6.** Bỏ dạng pill/badge bo tròn khỏi nhãn trạng thái (1 lần lưu)
- **1.7.** Thêm tường cá nhân/phòng ban, lịch sử sửa bài, bình chọn (1 lần lưu)
- **1.8.** Bình luận lồng 2 cấp + xem danh sách người thả cảm xúc (1 lần lưu)
- **1.9.** Giao diện tường cá nhân/phòng ban, bình chọn, chia sẻ, đính kèm ảnh (1 lần lưu)
- **1.10.** Thêm thông báo đẩy (web push) và trung tâm thông báo (1 lần lưu)
- **1.11.** Nhắc tên người dùng trong bài viết/bình luận (1 lần lưu)
- **1.12.** Thêm nhóm/phòng ban, tường nhóm và sửa thông báo đẩy (1 lần lưu)
- **1.13.** Mời thành viên vào nhóm, ảnh đại diện nhóm, chia sẻ bài viết vào nhóm (1 lần lưu)
- **1.14.** Sticker động (Lottie) trong bài viết/bình luận (1 lần lưu)
- **1.15.** Mô tả nhật ký hoạt động không còn lẫn thẻ HTML (1 lần lưu)
- **1.16.** Cải thiện bảng tin, bình chọn, panel hồ sơ (1 lần lưu)
- **1.17.** Cập nhật trang chủ và DataStreamWidget (1 lần lưu)
- **1.18.** Định dạng lại Login.vue/AuthCallback.vue (thụt lề 4 khoảng trắng) (1 lần lưu)

### 26/08/2026

- **1.1.** Định dạng lại modal tiêu chí đánh giá (1 lần lưu)
- **1.2.** Lượt xem bài viết và giới hạn hiển thị theo phòng ban (1 lần lưu)
- **1.3.** Tiêu chí ĐGNL, lịch sử và hub phòng ban (1 lần lưu)
- **1.4.** Mẫu đánh giá, vị trí đánh giá và import/export Excel (1 lần lưu)
- **1.5.** Hashtag trong bài viết (1 lần lưu)
- **1.6.** Hiện avatar + phòng ban trong nhật ký hoạt động (1 lần lưu)
- **1.7.** Thêm màu phụ "Nhân Ái" #009082 (1 lần lưu)
- **1.8.** Tuỳ chỉnh menu sidebar theo phòng ban (1 lần lưu)
- **1.9.** Panel chi tiết 28rem + mẫu thao tác dòng gộp 1 dropdown (1 lần lưu)
- **1.10.** Áp width 28rem cho panel chi tiết theo mẫu vàng mới (1 lần lưu)
- **1.11.** Chặn trùng tên tiêu chí đánh giá trong cùng phòng ban (1 lần lưu)
- **1.12.** Gộp thao tác theo dòng vào 1 dropdown (mẫu vàng data-table) (1 lần lưu)
- **1.13.** Đăng ẩn danh + kiểm duyệt bài viết trước khi hiển thị công khai (1 lần lưu)
- **1.14.** Sắp xếp lại thứ tự và nhóm menu sidebar theo phòng ban (1 lần lưu)
- **1.15.** Áp width 28rem cho panel chi tiết ở trang xem đánh giá (1 lần lưu)
- **1.16.** Nút hiện lại header căn giữa tuyệt đối, không lệch theo flex (1 lần lưu)
- **1.17.** Thêm trang 404 khi truy cập route không tồn tại (1 lần lưu)
- **1.18.** Ẩn/hiện menu sidebar toàn hệ thống (superadmin) (1 lần lưu)
- **1.19.** Cho phép super_admin tạo mẫu đánh giá dùng chung toàn hệ thống (1 lần lưu)
- **1.20.** Ẩn/hiện menu toàn hệ thống áp dụng cho cả super_admin, nâng cấp trang quản lý (1 lần lưu)
- **1.21.** Cập nhật VA_WORKSPACE_OVERVIEW.md — sửa ẩn/hiện menu toàn hệ thống (1 lần lưu)
- **1.22.** Đổi tên, sắp xếp và cấu hình nhóm menu sidebar toàn hệ thống (1 lần lưu)

### 27/08/2026

- **1.1.** Thêm màu tertiary và gold vào theme.css (1 lần lưu)
- **1.2.** Sửa toggle ẩn/hiện menu toàn hệ thống và bỏ hint Brave không dùng (1 lần lưu)
- **1.3.** Bỏ CRUD tay Vị trí đánh giá, đổi trọng số sang %, gộp field tùy biến (1 lần lưu)
- **1.4.** Thêm module Dự án (giai đoạn 1 — CRUD) (1 lần lưu)

### 28/08/2026

- **1.1.** Thêm loại dự án, import/export Excel và các picker liên quan (1 lần lưu)
- **1.2.** Menu chuột phải trên bảng dự án, nhân bản dự án, ngày thực tế (1 lần lưu)
- **1.3.** Cập nhật VA_WORKSPACE_OVERVIEW.md — Project Giai đoạn 1 + mục nhanh (1 lần lưu)
- **1.4.** Validate ngày thực tế khi tạo dự án, nâng cấp modal Cập nhật thời gian (1 lần lưu)
- **1.5.** PR1 - entity Task thật, migration + Model + Enum (WBS đa cấp) (1 lần lưu)
- **1.6.** PR2 - Repository/Service/Controller/routes cho Task (1 lần lưu)
- **1.7.** PR3 - kích hoạt permission task.*, thêm mục sidebar Công việc (1 lần lưu)
- **1.8.** PR4 - trang "Tất cả công việc" (TaskList.vue) (1 lần lưu)
- **1.9.** PR5 - ProjectQuickActionModals dùng API Task thật (1 lần lưu)
- **1.10.** Cập nhật overview + README — Task thật (Giai đoạn 2) (1 lần lưu)
- **1.11.** Tab lọc nhanh, Kanban và nhóm theo dự án trên TaskList (1 lần lưu)
- **1.12.** TaskSeeder + hoàn thiện Kanban/quick-actions cho TaskList (1 lần lưu)
- **1.13.** PR1 - migration + model nền tảng mở rộng Task (1 lần lưu)
- **1.14.** PR2 - present() + validate + tính toán runtime cho Task (1 lần lưu)
- **1.15.** PR3 - hiển thị/sửa field mới trên TaskList (list/panel/Kanban) (1 lần lưu)
- **1.16.** PR4 - task_attachments full stack (Đính kèm công việc) (1 lần lưu)
- **1.17.** PR5 - task_worklogs full stack (Nhật ký giờ làm) + notification (1 lần lưu)
- **1.18.** PR6 - task_scores full stack (Đánh giá tối thiểu) + notification (1 lần lưu)
- **1.19.** Lưu đề xuất Notification/Reminder Engine làm roadmap tương lai (1 lần lưu)
- **1.20.** PR7 - Filter/Sort/Bulk actions cho TaskList (1 lần lưu)
- **1.21.** Lưu đề xuất UI Chuyển giao (Task Delegation §6) làm roadmap tương lai (1 lần lưu)
- **1.22.** PR8 - Export/Import Excel cho Task (Modal "Dữ liệu") (1 lần lưu)
- **1.23.** Merge: mở rộng entity Task — PR1-PR8 (migration, backend, UI, attachments, worklogs, scores, filter/sort/bulk, Excel) (1 lần lưu)
- **1.24.** Menu toàn hệ thống áp dụng đúng cho super_admin + fix thứ tự sidebar (1 lần lưu)
- **1.25.** Merge: fix menu toàn hệ thống áp dụng cho super_admin + thứ tự sidebar (1 lần lưu)

### 30/08/2026

- **1.1.** Chuyển giao công việc hàng loạt (Task Delegation Phase 3 §6) (1 lần lưu)
- **1.2.** Chế độ xem Lịch cho "Tất cả công việc" (1 lần lưu)
- **1.3.** TablePagesBar hỗ trợ đóng menu từ slot (theo sau Calendar View) (1 lần lưu)
- **1.4.** Thanh tiến độ 2 lớp (Dual Progress Bar) dùng chung, áp dụng cho Project (1 lần lưu)
- **1.5.** Dữ liệu mẫu cho Lịch công việc (theo sau Calendar View) (1 lần lưu)
- **1.6.** Cập nhật overview theo Task Delegation/Calendar/Dual Progress + rà soát repo (1 lần lưu)
- **1.7.** Hook tự động cảnh báo vi phạm CLAUDE.md mục 2/12 sau mỗi Edit/Write .vue|.css (1 lần lưu)
- **1.8.** Task đứng độc lập + watchers/collaborators + creation settings (1 lần lưu)
- **1.9.** Bộ điểm tiêu chí đánh giá + mức độ ưu tiên task nâng cao (1 lần lưu)

[← Mục lục](../README.md)

