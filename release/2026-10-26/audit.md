# Đối soát 26/10/2026

Đọc nhanh: [README.md](./README.md) · Tuần: [2026-W44](../summary/2026-W44-summary.md) · Bảng tuần: [2026-W44-audit](../summary/2026-W44-audit.md)

Việc trong ngày đánh số **1.1**, **1.2**, … Mỗi lần lưu (commit) là **1.1.1**, **1.1.2**, … Bảng có thêm dòng **Giải thích đơn giản** cho người không rành kỹ thuật. Thiếu trong commit thì ghi «chưa ghi», không suy diễn.

## Việc trong ngày

### 1.1. Nới giao diện và bỏ safe-area iOS

Giao diện gốc · Chấm công nhân viên, Lịch sử, Nghỉ phép, Chat, Bảng tin, Đăng nhập, Hồ sơ cá nhân, Tổng quan của tôi · 3 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Nới giao diện và bỏ safe-area iOS |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Gom 3 lần lưu cùng một việc; xem bảng từng version bên dưới. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 09:23 | `3c96197` | Sửa | Attendance/EmployeeMobileTabbar.vue, Attendance/EmployeeMobileWorkspaceIntro.vue, Attendance/EmployeeAttendance.vue, Attendance/EmployeeFeed.vue (+22) |
| 09:30 | `08b96d9` | Sửa tiếp | Attendance/EmployeeMobileTabbar.vue, Attendance/EmployeeMobileLayout.vue, Dashboard/DashboardEmployeeShellPreview.vue, Dashboard/employeePwaPreview.js (+4) |
| 09:41 | `cee07d0` | Sửa tiếp | Attendance/useEmployeeHomeData.js, Attendance/EmployeeHome.vue, Chat/ChatConversationView.vue, Dashboard/DashboardEmployeeHomePreview.vue (+5) |

#### Chi tiết từng lần lưu (version)

##### Version 1.1.1 — mã `3c96197` · 09:23

| | |
| --- | --- |
| **Đổi gì** | Nới giao diện và bỏ safe-area iOS |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Nới giao diện và bỏ safe-area iOS. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeMobileTabbar.vue, Attendance/EmployeeMobileWorkspaceIntro.vue, Attendance/EmployeeAttendance.vue, Attendance/EmployeeFeed.vue (+22). |

##### Version 1.1.2 — mã `08b96d9` · 09:30

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Tổng quan của tôi. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Giao diện gốc trên màn Tổng quan của tôi. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeMobileTabbar.vue, Attendance/EmployeeMobileLayout.vue, Dashboard/DashboardEmployeeShellPreview.vue, Dashboard/employeePwaPreview.js (+4). |

##### Version 1.1.3 — mã `cee07d0` · 09:41

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat, Tổng quan của tôi. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Giao diện gốc trên màn Chat, Tổng quan của tôi. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/useEmployeeHomeData.js, Attendance/EmployeeHome.vue, Chat/ChatConversationView.vue, Dashboard/DashboardEmployeeHomePreview.vue (+5). |

### 1.2. Onboarding và splash boot theo mẫu welcome VA

Chấm công · Chấm công nhân viên, Lịch sử, Chat, Bảng tin, Tổng quan của tôi · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Onboarding và splash boot theo mẫu welcome VA. Màn chào cream + mascot cho boot SPA và onboarding PWA; tinh chỉnh shell mobile, chat và dashboard preview. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Onboarding và splash boot theo mẫu welcome VA. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeAttendanceHistory.vue, Attendance/EmployeePwaLogoClip.vue, Attendance/EmployeeMobileHeader.vue, Attendance/EmployeeMobileTabbar.vue (+36). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 10:04 | `13c294e` | Thêm | Attendance/EmployeeAttendanceHistory.vue, Attendance/EmployeePwaLogoClip.vue, Attendance/EmployeeMobileHeader.vue, Attendance/EmployeeMobileTabbar.vue (+36) |

#### Chi tiết từng lần lưu (version)

##### Version 1.2.1 — mã `13c294e` · 10:04

| | |
| --- | --- |
| **Đổi gì** | Onboarding và splash boot theo mẫu welcome VA. Màn chào cream + mascot cho boot SPA và onboarding PWA; tinh chỉnh shell mobile, chat và dashboard preview. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Onboarding và splash boot theo mẫu welcome VA. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeAttendanceHistory.vue, Attendance/EmployeePwaLogoClip.vue, Attendance/EmployeeMobileHeader.vue, Attendance/EmployeeMobileTabbar.vue (+36). |

### 1.3. Sửa tiếp phần Giao diện gốc (chưa ghi câu mô tả)

Giao diện gốc · Tổng quan của tôi · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc trên màn Tổng quan của tôi, đụng Attendance/EmployeeMobileHeader.vue, Attendance/EmployeeMobileWorkspaceIntro.vue, Dashboard/DashboardEmployeeShellPreview.vue, employee-mobile-shell/base.css (+2). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Giao diện gốc trên màn Tổng quan của tôi. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeMobileHeader.vue, Attendance/EmployeeMobileWorkspaceIntro.vue, Dashboard/DashboardEmployeeShellPreview.vue, employee-mobile-shell/base.css (+2). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 10:14 | `ceb149b` | Sửa tiếp | Attendance/EmployeeMobileHeader.vue, Attendance/EmployeeMobileWorkspaceIntro.vue, Dashboard/DashboardEmployeeShellPreview.vue, employee-mobile-shell/base.css (+2) |

#### Chi tiết từng lần lưu (version)

##### Version 1.3.1 — mã `ceb149b` · 10:14

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Tổng quan của tôi. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Giao diện gốc trên màn Tổng quan của tôi. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeMobileHeader.vue, Attendance/EmployeeMobileWorkspaceIntro.vue, Dashboard/DashboardEmployeeShellPreview.vue, employee-mobile-shell/base.css (+2). |

### 1.4. Sửa tiếp phần Chấm công (chưa ghi câu mô tả)

Chấm công · Tổng quan của tôi · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc trên màn Tổng quan của tôi, đụng Attendance/EmployeeMobileBirthdayGreeting.vue, Attendance/EmployeeMobileHeader.vue, Attendance/employeeBirthdayGreetings.js, Attendance/EmployeeMobileLayout.vue (+15). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Chấm công trên màn Tổng quan của tôi. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeMobileBirthdayGreeting.vue, Attendance/EmployeeMobileHeader.vue, Attendance/employeeBirthdayGreetings.js, Attendance/EmployeeMobileLayout.vue (+15). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 10:21 | `0333660` | Sửa tiếp | Attendance/EmployeeMobileBirthdayGreeting.vue, Attendance/EmployeeMobileHeader.vue, Attendance/employeeBirthdayGreetings.js, Attendance/EmployeeMobileLayout.vue (+15) |

#### Chi tiết từng lần lưu (version)

##### Version 1.4.1 — mã `0333660` · 10:21

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Tổng quan của tôi. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Chấm công trên màn Tổng quan của tôi. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeMobileBirthdayGreeting.vue, Attendance/EmployeeMobileHeader.vue, Attendance/employeeBirthdayGreetings.js, Attendance/EmployeeMobileLayout.vue (+15). |

### 1.5. Sửa tiếp phần Giao diện gốc (chưa ghi câu mô tả)

Giao diện gốc · Chat, Bảng tin · 3 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc trên màn Chat, Bảng tin, đụng config/pwa_ios.php, employee-mobile-shell/pwa-ios.css, partials/pwa-ios-meta.blade.php, Chat/ChatConversationList.vue (+11). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Gom 3 lần lưu cùng một việc; xem bảng từng version bên dưới. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 10:26 | `55962a2` | Sửa tiếp | config/pwa_ios.php, employee-mobile-shell/pwa-ios.css, partials/pwa-ios-meta.blade.php |
| 10:28 | `63c03b3` | Sửa tiếp | Chat/ChatConversationList.vue, config/pwa_ios.php, employee-mobile-shell/pwa-ios.css, employee-mobile-shell/pwa.css (+1) |
| 10:29 | `14bc1c3` | Sửa tiếp | Attendance/EmployeeMobileLayout.vue, Social/SocialPostController.php, Social/api.php, css/welcome-screen.css (+3) |

#### Chi tiết từng lần lưu (version)

##### Version 1.5.1 — mã `55962a2` · 10:26

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Giao diện gốc. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: config/pwa_ios.php, employee-mobile-shell/pwa-ios.css, partials/pwa-ios-meta.blade.php. |

##### Version 1.5.2 — mã `63c03b3` · 10:28

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Giao diện gốc trên màn Chat. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Chat/ChatConversationList.vue, config/pwa_ios.php, employee-mobile-shell/pwa-ios.css, employee-mobile-shell/pwa.css (+1). |

##### Version 1.5.3 — mã `14bc1c3` · 10:29

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Bảng tin. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Giao diện gốc trên màn Bảng tin. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeMobileLayout.vue, Social/SocialPostController.php, Social/api.php, css/welcome-screen.css (+3). |

### 1.6. Tab bar PWA sát đáy; overlay sinh nhật đồng nghiệp

Giao diện gốc · Bảng tin, Tổng quan của tôi · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Tab bar PWA sát đáy; overlay sinh nhật đồng nghiệp. Khôi phục layout PWA iOS (tab bar fixed bottom, status bar default, không viewport-fit=cover) và padding tab bar theo 3c96197, với fallback chiều cao tab bar 4.5rem.  Mở rộng chúc sinh nhật: API trả danh sách đồng nghiệp sinh nhật hôm nay, composable dùng chung trên shell mobile và AppLayout desktop. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Tab bar PWA sát đáy; overlay sinh nhật đồng nghiệp. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeMobileBirthdayGreeting.vue, Attendance/useEmployeeBirthdayGreeting.js, Attendance/EmployeeMobileLayout.vue, Attendance/employeeBirthdayGreeting.js (+14). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 10:37 | `1d08502` | Sửa | Attendance/EmployeeMobileBirthdayGreeting.vue, Attendance/useEmployeeBirthdayGreeting.js, Attendance/EmployeeMobileLayout.vue, Attendance/employeeBirthdayGreeting.js (+14) |

#### Chi tiết từng lần lưu (version)

##### Version 1.6.1 — mã `1d08502` · 10:37

| | |
| --- | --- |
| **Đổi gì** | Tab bar PWA sát đáy; overlay sinh nhật đồng nghiệp. Khôi phục layout PWA iOS (tab bar fixed bottom, status bar default, không viewport-fit=cover) và padding tab bar theo 3c96197, với fallback chiều cao tab bar 4.5rem.  Mở rộng chúc sinh nhật: API trả danh sách đồng nghiệp sinh nhật hôm nay, composable dùng chung trên shell mobile và AppLayout desktop. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Tab bar PWA sát đáy; overlay sinh nhật đồng nghiệp. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeMobileBirthdayGreeting.vue, Attendance/useEmployeeBirthdayGreeting.js, Attendance/EmployeeMobileLayout.vue, Attendance/employeeBirthdayGreeting.js (+14). |

### 1.7. Script prod-sync-code khi git pull bi chan boi sua local

Khác · Không gắn màn hình · 2 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Script prod-sync-code khi git pull bi chan boi sua local. Fetch + reset --hard origin/main thay cho pull merge; ghi chu trong deploy.sh. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Gom 2 lần lưu cùng một việc; xem bảng từng version bên dưới. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 10:40 | `db65215` | Cập nhật | deploy.sh, scripts/prod-sync-code.sh |
| 11:03 | `7bec5b7` | Sửa tiếp | Attendance/EmployeeMobileBirthdayGreeting.vue, birthday/greeting-1.png, birthday/greeting-2.png, birthday/greeting-3.png (+2) |

#### Chi tiết từng lần lưu (version)

##### Version 1.7.1 — mã `db65215` · 10:40

| | |
| --- | --- |
| **Đổi gì** | Script prod-sync-code khi git pull bi chan boi sua local. Fetch + reset --hard origin/main thay cho pull merge; ghi chu trong deploy.sh. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Script prod-sync-code khi git pull bi chan boi sua local. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: deploy.sh, scripts/prod-sync-code.sh. |

##### Version 1.7.2 — mã `7bec5b7` · 11:03

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Khác. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeMobileBirthdayGreeting.vue, birthday/greeting-1.png, birthday/greeting-2.png, birthday/greeting-3.png (+2). |

### 1.8. Thêm hub xem trước Đơn từ và kế hoạch thiết kế chi tiết

Đơn từ · Cấu hình đơn từ, Đơn từ, Tổng quan của tôi, Cấu hình menu · 5 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Thêm hub xem trước Đơn từ và kế hoạch thiết kế chi tiết |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Gom 5 lần lưu cùng một việc; xem bảng từng version bên dưới. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 11:43 | `7b14927` | Cập nhật | DonTu/nav.js, DonTu/DonTuLayout.vue, DonTu/DonTuPlaceholder.vue, DonTu/router.js (+6) |
| 11:49 | `07f2a5c` | Sửa tiếp | Dashboard/DashboardMeContent.vue, DonTu/DonTuSidebarMenuCatalog.php, DonTu/nav.js, DonTu/DonTuLayout.vue (+5) |
| 11:54 | `0402962` | Sửa tiếp | DonTu/DonTuSidebarMenuCatalog.php, DonTu/nav.js, DonTu/DonTuLayout.vue, DonTu/router.js (+2) |
| 11:57 | `1e0b4b7` | Sửa tiếp | DonTu/DonTuSidebarMenuCatalog.php, DonTu/nav.js, DonTu/DonTuLayout.vue, DonTu/DonTuPlaceholder.vue (+4) |
| 12:04 | `c790213` | Sửa tiếp | DonTu/DonTuFormTemplateController.php, DonTu/DonTuStatusSetController.php, DonTu/DonTuWorkflowController.php, DonTu/DonTuFormTemplate.php (+29) |

#### Chi tiết từng lần lưu (version)

##### Version 1.8.1 — mã `7b14927` · 11:43

| | |
| --- | --- |
| **Đổi gì** | Thêm hub xem trước Đơn từ và kế hoạch thiết kế chi tiết |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Thêm hub xem trước Đơn từ và kế hoạch thiết kế chi tiết. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: DonTu/nav.js, DonTu/DonTuLayout.vue, DonTu/DonTuPlaceholder.vue, DonTu/router.js (+6). |

##### Version 1.8.2 — mã `07f2a5c` · 11:49

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Cấu hình đơn từ, Đơn từ, Tổng quan của tôi, Cấu hình menu. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Đơn từ trên màn Cấu hình đơn từ, Đơn từ, Tổng quan của tôi, Cấu hình menu. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Dashboard/DashboardMeContent.vue, DonTu/DonTuSidebarMenuCatalog.php, DonTu/nav.js, DonTu/DonTuLayout.vue (+5). |

##### Version 1.8.3 — mã `0402962` · 11:54

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Cấu hình đơn từ, Đơn từ, Cấu hình menu. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Đơn từ trên màn Cấu hình đơn từ, Đơn từ, Cấu hình menu. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: DonTu/DonTuSidebarMenuCatalog.php, DonTu/nav.js, DonTu/DonTuLayout.vue, DonTu/router.js (+2). |

##### Version 1.8.4 — mã `1e0b4b7` · 11:57

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Cấu hình đơn từ, Đơn từ, Cấu hình menu. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Đơn từ trên màn Cấu hình đơn từ, Đơn từ, Cấu hình menu. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: DonTu/DonTuSidebarMenuCatalog.php, DonTu/nav.js, DonTu/DonTuLayout.vue, DonTu/DonTuPlaceholder.vue (+4). |

##### Version 1.8.5 — mã `c790213` · 12:04

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Cấu hình đơn từ, Đơn từ. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Sửa nốt phần Đơn từ trên màn Cấu hình đơn từ, Đơn từ. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu). File chính: DonTu/DonTuFormTemplateController.php, DonTu/DonTuStatusSetController.php, DonTu/DonTuWorkflowController.php, DonTu/DonTuFormTemplate.php (+29). |

