# Đối soát 26/10/2026

Đọc nhanh: [README.md](./README.md) · Tuần: [2026-W44](../summary/2026-W44-summary.md) · Bảng tuần: [2026-W44-audit](../summary/2026-W44-audit.md)

## Việc trong ngày

| # | Loại | Phần | Màn hình | Việc | Lần lưu | Đổi dữ liệu |
| --- | --- | --- | --- | --- | ---: | --- |
| 1 | Sửa | Giao diện gốc | Chấm công nhân viên, Lịch sử, Nghỉ phép, Chat, Bảng tin, Đăng nhập, Hồ sơ cá nhân, Tổng quan của tôi | Nới giao diện và bỏ safe-area iOS | 3 | Không |
| 2 | Thêm | Chấm công | Chấm công nhân viên, Lịch sử, Chat, Bảng tin, Tổng quan của tôi | Onboarding và splash boot theo mẫu welcome VA | 1 | Không |
| 3 | Sửa tiếp | Giao diện gốc | Tổng quan của tôi | Sửa tiếp phần Giao diện gốc (chưa ghi câu mô tả) | 1 | Không |
| 4 | Sửa tiếp | Chấm công | Tổng quan của tôi | Sửa tiếp phần Chấm công (chưa ghi câu mô tả) | 1 | Không |
| 5 | Sửa tiếp | Giao diện gốc | Chat, Bảng tin | Sửa tiếp phần Giao diện gốc (chưa ghi câu mô tả) | 3 | Không |
| 6 | Sửa | Giao diện gốc | Bảng tin, Tổng quan của tôi | Tab bar PWA sát đáy; overlay sinh nhật đồng nghiệp | 1 | Không |
| 7 | Cập nhật | Khác | Không gắn màn hình | Script prod-sync-code khi git pull bi chan boi sua local | 2 | Không |
| 8 | Cập nhật | Đơn từ | Đơn từ, Tổng quan của tôi, Cấu hình menu | Thêm hub xem trước Đơn từ và kế hoạch thiết kế chi tiết | 5 | Đổi cấu trúc dữ liệu |

## Từng lần lưu

| Giờ | Mã | Loại | Phần | Màn hình | Đã ghi | Chỗ sửa |
| --- | --- | --- | --- | --- | --- | --- |
| 09:23 | `3c96197` | Sửa | Giao diện gốc | Chấm công nhân viên, Lịch sử, Nghỉ phép, Bảng tin, Đăng nhập, Hồ sơ cá nhân | Nới giao diện và bỏ safe-area iOS | Attendance/EmployeeMobileTabbar.vue, Attendance/EmployeeMobileWorkspaceIntro.vue, Attendance/EmployeeAttendance.vue, Attendance/EmployeeFeed.vue (+22) |
| 09:30 | `08b96d9` | Sửa tiếp | Giao diện gốc | Tổng quan của tôi | Sửa tiếp cùng việc. Màn: Tổng quan của tôi. | Attendance/EmployeeMobileTabbar.vue, Attendance/EmployeeMobileLayout.vue, Dashboard/DashboardEmployeeShellPreview.vue, Dashboard/employeePwaPreview.js (+4) |
| 09:41 | `cee07d0` | Sửa tiếp | Giao diện gốc | Chat, Tổng quan của tôi | Sửa tiếp cùng việc. Màn: Chat, Tổng quan của tôi. | Attendance/useEmployeeHomeData.js, Attendance/EmployeeHome.vue, Chat/ChatConversationView.vue, Dashboard/DashboardEmployeeHomePreview.vue (+5) |
| 10:04 | `13c294e` | Thêm | Chấm công | Chấm công nhân viên, Lịch sử, Chat, Bảng tin, Tổng quan của tôi | Onboarding và splash boot theo mẫu welcome VA | Attendance/EmployeeAttendanceHistory.vue, Attendance/EmployeePwaLogoClip.vue, Attendance/EmployeeMobileHeader.vue, Attendance/EmployeeMobileTabbar.vue (+36) |
| 10:14 | `ceb149b` | Sửa tiếp | Giao diện gốc | Tổng quan của tôi | Sửa tiếp cùng việc. Màn: Tổng quan của tôi. | Attendance/EmployeeMobileHeader.vue, Attendance/EmployeeMobileWorkspaceIntro.vue, Dashboard/DashboardEmployeeShellPreview.vue, employee-mobile-shell/base.css (+2) |
| 10:21 | `0333660` | Sửa tiếp | Chấm công | Tổng quan của tôi | Sửa tiếp cùng việc. Màn: Tổng quan của tôi. | Attendance/EmployeeMobileBirthdayGreeting.vue, Attendance/EmployeeMobileHeader.vue, Attendance/employeeBirthdayGreetings.js, Attendance/EmployeeMobileLayout.vue (+15) |
| 10:26 | `55962a2` | Sửa tiếp | Giao diện gốc | Không gắn màn hình | Sửa tiếp cùng việc. | config/pwa_ios.php, employee-mobile-shell/pwa-ios.css, partials/pwa-ios-meta.blade.php |
| 10:28 | `63c03b3` | Sửa tiếp | Giao diện gốc | Chat | Sửa tiếp cùng việc. Màn: Chat. | Chat/ChatConversationList.vue, config/pwa_ios.php, employee-mobile-shell/pwa-ios.css, employee-mobile-shell/pwa.css (+1) |
| 10:29 | `14bc1c3` | Sửa tiếp | Giao diện gốc | Bảng tin | Sửa tiếp cùng việc. Màn: Bảng tin. | Attendance/EmployeeMobileLayout.vue, Social/SocialPostController.php, Social/api.php, css/welcome-screen.css (+3) |
| 10:37 | `1d08502` | Sửa | Giao diện gốc | Bảng tin, Tổng quan của tôi | Tab bar PWA sát đáy; overlay sinh nhật đồng nghiệp | Attendance/EmployeeMobileBirthdayGreeting.vue, Attendance/useEmployeeBirthdayGreeting.js, Attendance/EmployeeMobileLayout.vue, Attendance/employeeBirthdayGreeting.js (+14) |
| 10:40 | `db65215` | Cập nhật | Khác | Không gắn màn hình | Script prod-sync-code khi git pull bi chan boi sua local | deploy.sh, scripts/prod-sync-code.sh |
| 11:03 | `7bec5b7` | Sửa tiếp | Khác | Không gắn màn hình | Sửa tiếp cùng việc. | Attendance/EmployeeMobileBirthdayGreeting.vue, birthday/greeting-1.png, birthday/greeting-2.png, birthday/greeting-3.png (+2) |
| 11:43 | `7b14927` | Cập nhật | Đơn từ | Đơn từ, Cấu hình menu | Thêm hub xem trước Đơn từ và kế hoạch thiết kế chi tiết | DonTu/nav.js, DonTu/DonTuLayout.vue, DonTu/DonTuPlaceholder.vue, DonTu/router.js (+6) |
| 11:49 | `07f2a5c` | Sửa tiếp | Đơn từ | Đơn từ, Tổng quan của tôi, Cấu hình menu | Sửa tiếp cùng việc. Màn: Đơn từ, Tổng quan của tôi, Cấu hình menu. | Dashboard/DashboardMeContent.vue, DonTu/DonTuSidebarMenuCatalog.php, DonTu/nav.js, DonTu/DonTuLayout.vue (+5) |
| 11:54 | `0402962` | Sửa tiếp | Đơn từ | Đơn từ, Cấu hình menu | Sửa tiếp cùng việc. Màn: Đơn từ, Cấu hình menu. | DonTu/DonTuSidebarMenuCatalog.php, DonTu/nav.js, DonTu/DonTuLayout.vue, DonTu/router.js (+2) |
| 11:57 | `1e0b4b7` | Sửa tiếp | Đơn từ | Đơn từ, Cấu hình menu | Sửa tiếp cùng việc. Màn: Đơn từ, Cấu hình menu. | DonTu/DonTuSidebarMenuCatalog.php, DonTu/nav.js, DonTu/DonTuLayout.vue, DonTu/DonTuPlaceholder.vue (+4) |
| 12:04 | `c790213` | Sửa tiếp | Đơn từ | Đơn từ | Sửa tiếp cùng việc. Màn: Đơn từ. | DonTu/DonTuFormTemplateController.php, DonTu/DonTuStatusSetController.php, DonTu/DonTuWorkflowController.php, DonTu/DonTuFormTemplate.php (+29) |

