# Đối soát 28/10/2026

Đọc nhanh: [README.md](./README.md) · Tuần: [2026-W44](../summary/2026-W44-summary.md) · Bảng tuần: [2026-W44-audit](../summary/2026-W44-audit.md)

Việc trong ngày đánh số **1.1**, **1.2**, … Mỗi lần lưu (commit) là **1.1.1**, **1.1.2**, … Bảng có thêm dòng **Giải thích đơn giản** cho người không rành kỹ thuật. Thiếu trong commit thì ghi «chưa ghi», không suy diễn.

## Việc trong ngày

### 1.1. Mở rộng chat nhân viên (nhóm, poll, đính kèm, read receipt) và story social với sticker VA

Chat · Chấm công nhân viên, Chat, Bảng tin, Hồ sơ cá nhân, Tổng quan của tôi · 3 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Mở rộng chat nhân viên (nhóm, poll, đính kèm, read receipt) và story social với sticker VA. Cải thiện shell mobile (hồ sơ, chấm công), web push, hồ sơ dashboard và bổ sung migration, test tương ứng. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Gom 3 lần lưu cùng một việc; xem bảng từng version bên dưới. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 09:17 | `67aa6f6` | Cập nhật | rules/no-dev-server.mdc, Attendance/EmployeeAttendancePunchService.php, Attendance/EmployeeMobileHeader.vue, Attendance/EmployeeMobileLayout.vue (+96) |
| 09:20 | `2eaac86` | Sửa tiếp | Chat/2026_10_28_120000_ensure_chat_core_tables.php, Chat/2026_10_28_130001_upgrade_chat_rooms.php |
| 09:21 | `ceeb196` | Sửa tiếp | Chat/2026_10_28_130001_upgrade_chat_rooms.php |

#### Chi tiết từng lần lưu (version)

##### Version 1.1.1 — mã `67aa6f6` · 09:17

| | |
| --- | --- |
| **Đổi gì** | Mở rộng chat nhân viên (nhóm, poll, đính kèm, read receipt) và story social với sticker VA. Cải thiện shell mobile (hồ sơ, chấm công), web push, hồ sơ dashboard và bổ sung migration, test tương ứng. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Mở rộng chat nhân viên (nhóm, poll, đính kèm, read receipt) và story social với sticker VA. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu). File chính: rules/no-dev-server.mdc, Attendance/EmployeeAttendancePunchService.php, Attendance/EmployeeMobileHeader.vue, Attendance/EmployeeMobileLayout.vue (+96). |

##### Version 1.1.2 — mã `2eaac86` · 09:20

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Sửa nốt phần Chat trên màn Chat. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu). File chính: Chat/2026_10_28_120000_ensure_chat_core_tables.php, Chat/2026_10_28_130001_upgrade_chat_rooms.php. |

##### Version 1.1.3 — mã `ceeb196` · 09:21

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Sửa nốt phần Chat trên màn Chat. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu). File chính: Chat/2026_10_28_130001_upgrade_chat_rooms.php. |

### 1.2. Sửa tiếp phần Chấm công (chưa ghi câu mô tả)

Chấm công · Bảng tin · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc trên màn Bảng tin, đụng Attendance/EmployeeMobileHeader.vue, Attendance/useEmployeeSocialFeed.js, Attendance/EmployeeMobileLayout.vue, Attendance/EmployeeFeed.vue (+4). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Chấm công trên màn Bảng tin. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeMobileHeader.vue, Attendance/useEmployeeSocialFeed.js, Attendance/EmployeeMobileLayout.vue, Attendance/EmployeeFeed.vue (+4). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 09:25 | `889666c` | Sửa tiếp | Attendance/EmployeeMobileHeader.vue, Attendance/useEmployeeSocialFeed.js, Attendance/EmployeeMobileLayout.vue, Attendance/EmployeeFeed.vue (+4) |

#### Chi tiết từng lần lưu (version)

##### Version 1.2.1 — mã `889666c` · 09:25

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Bảng tin. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Chấm công trên màn Bảng tin. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeMobileHeader.vue, Attendance/useEmployeeSocialFeed.js, Attendance/EmployeeMobileLayout.vue, Attendance/EmployeeFeed.vue (+4). |

### 1.3. Sửa tiếp phần Chat (chưa ghi câu mô tả)

Chat · Chat · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc trên màn Chat, đụng Attendance/EmployeeMobileHeader.vue, Attendance/EmployeeMobileLayout.vue, Chat/ChatConversationList.vue, Chat/ChatConversationView.vue (+4). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Chat trên màn Chat. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeMobileHeader.vue, Attendance/EmployeeMobileLayout.vue, Chat/ChatConversationList.vue, Chat/ChatConversationView.vue (+4). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 09:30 | `2f08f99` | Sửa tiếp | Attendance/EmployeeMobileHeader.vue, Attendance/EmployeeMobileLayout.vue, Chat/ChatConversationList.vue, Chat/ChatConversationView.vue (+4) |

#### Chi tiết từng lần lưu (version)

##### Version 1.3.1 — mã `2f08f99` · 09:30

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Chat trên màn Chat. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeMobileHeader.vue, Attendance/EmployeeMobileLayout.vue, Chat/ChatConversationList.vue, Chat/ChatConversationView.vue (+4). |

### 1.4. Sửa tiếp phần Bảng tin (chưa ghi câu mô tả)

Bảng tin · Chat, Bảng tin · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc trên màn Chat, Bảng tin, đụng Chat/ChatConversationView.vue, Chat/ChatPicker.vue, Social/SocialPostComposer.vue, Social/SocialStoryRail.vue (+2). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Bảng tin trên màn Chat, Bảng tin. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Chat/ChatConversationView.vue, Chat/ChatPicker.vue, Social/SocialPostComposer.vue, Social/SocialStoryRail.vue (+2). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 09:37 | `0b53486` | Sửa tiếp | Chat/ChatConversationView.vue, Chat/ChatPicker.vue, Social/SocialPostComposer.vue, Social/SocialStoryRail.vue (+2) |

#### Chi tiết từng lần lưu (version)

##### Version 1.4.1 — mã `0b53486` · 09:37

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat, Bảng tin. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Bảng tin trên màn Chat, Bảng tin. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Chat/ChatConversationView.vue, Chat/ChatPicker.vue, Social/SocialPostComposer.vue, Social/SocialStoryRail.vue (+2). |

### 1.5. Sửa tiếp phần Chat (chưa ghi câu mô tả)

Chat · Chat, Bảng tin, Hồ sơ cá nhân, Tổng quan của tôi · 2 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc trên màn Chat, Bảng tin, Hồ sơ cá nhân, Tổng quan của tôi, đụng Chat/MessageRead.php, Chat/ChatConversationController.php, Chat/Conversation.php, Chat/ChatService.php (+32). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Gom 2 lần lưu cùng một việc; xem bảng từng version bên dưới. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 09:49 | `1f24332` | Sửa tiếp | Chat/MessageRead.php, Chat/ChatConversationController.php, Chat/Conversation.php, Chat/ChatService.php (+21) |
| 09:56 | `025f950` | Sửa tiếp | Attendance/EmployeeMobileHeader.vue, Attendance/EmployeeProfile.vue, Chat/HandlesChatFeatures.php, Chat/ChatConversationView.vue (+7) |

#### Chi tiết từng lần lưu (version)

##### Version 1.5.1 — mã `1f24332` · 09:49

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat, Bảng tin. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Sửa nốt phần Chat trên màn Chat, Bảng tin. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu). File chính: Chat/MessageRead.php, Chat/ChatConversationController.php, Chat/Conversation.php, Chat/ChatService.php (+21). |

##### Version 1.5.2 — mã `025f950` · 09:56

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat, Hồ sơ cá nhân, Tổng quan của tôi. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Chat trên màn Chat, Hồ sơ cá nhân, Tổng quan của tôi. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeMobileHeader.vue, Attendance/EmployeeProfile.vue, Chat/HandlesChatFeatures.php, Chat/ChatConversationView.vue (+7). |

### 1.6. Sửa tiếp phần Giao diện gốc (chưa ghi câu mô tả)

Giao diện gốc · Hồ sơ cá nhân · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc trên màn Hồ sơ cá nhân, đụng Attendance/EmployeeProfile.vue, employee-mobile-shell/base.css, employee-mobile-shell/theme.css, Dashboard/MyDashboardProfileTest.php. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Giao diện gốc trên màn Hồ sơ cá nhân. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeProfile.vue, employee-mobile-shell/base.css, employee-mobile-shell/theme.css, Dashboard/MyDashboardProfileTest.php. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 09:56 | `71e98f8` | Sửa tiếp | Attendance/EmployeeProfile.vue, employee-mobile-shell/base.css, employee-mobile-shell/theme.css, Dashboard/MyDashboardProfileTest.php |

#### Chi tiết từng lần lưu (version)

##### Version 1.6.1 — mã `71e98f8` · 09:56

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Hồ sơ cá nhân. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Giao diện gốc trên màn Hồ sơ cá nhân. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeProfile.vue, employee-mobile-shell/base.css, employee-mobile-shell/theme.css, Dashboard/MyDashboardProfileTest.php. |

### 1.7. Sửa tiếp phần Bảng tin (chưa ghi câu mô tả)

Bảng tin · Chat, Bảng tin, Tổng quan của tôi · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc trên màn Chat, Bảng tin, Tổng quan của tôi, đụng Attendance/EmployeeMobileHeader.vue, Attendance/EmployeeMobileLayout.vue, Attendance/EmployeeFeed.vue, Chat/ChatConversationView.vue (+20). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Sửa nốt phần Bảng tin trên màn Chat, Bảng tin, Tổng quan của tôi. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu). File chính: Attendance/EmployeeMobileHeader.vue, Attendance/EmployeeMobileLayout.vue, Attendance/EmployeeFeed.vue, Chat/ChatConversationView.vue (+20). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 10:03 | `669424e` | Sửa tiếp | Attendance/EmployeeMobileHeader.vue, Attendance/EmployeeMobileLayout.vue, Attendance/EmployeeFeed.vue, Chat/ChatConversationView.vue (+20) |

#### Chi tiết từng lần lưu (version)

##### Version 1.7.1 — mã `669424e` · 10:03

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat, Bảng tin, Tổng quan của tôi. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Sửa nốt phần Bảng tin trên màn Chat, Bảng tin, Tổng quan của tôi. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu). File chính: Attendance/EmployeeMobileHeader.vue, Attendance/EmployeeMobileLayout.vue, Attendance/EmployeeFeed.vue, Chat/ChatConversationView.vue (+20). |

### 1.8. Sửa tiếp phần Giao diện gốc (chưa ghi câu mô tả)

Giao diện gốc · Chat, Hồ sơ cá nhân · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc trên màn Chat, Hồ sơ cá nhân, đụng Attendance/EmployeeProfile.vue, Chat/ChatConversationView.vue, employee-mobile-shell/browser.css, composables/useEmployeeMobileViewport.js (+1). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Giao diện gốc trên màn Chat, Hồ sơ cá nhân. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeProfile.vue, Chat/ChatConversationView.vue, employee-mobile-shell/browser.css, composables/useEmployeeMobileViewport.js (+1). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 10:09 | `5a756a5` | Sửa tiếp | Attendance/EmployeeProfile.vue, Chat/ChatConversationView.vue, employee-mobile-shell/browser.css, composables/useEmployeeMobileViewport.js (+1) |

#### Chi tiết từng lần lưu (version)

##### Version 1.8.1 — mã `5a756a5` · 10:09

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat, Hồ sơ cá nhân. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Giao diện gốc trên màn Chat, Hồ sơ cá nhân. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeProfile.vue, Chat/ChatConversationView.vue, employee-mobile-shell/browser.css, composables/useEmployeeMobileViewport.js (+1). |

### 1.9. Sửa tiếp phần Chat (chưa ghi câu mô tả)

Chat · Chat, Bảng tin · 7 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc trên màn Chat, Bảng tin, đụng Chat/ChatMemberDialog.vue, Chat/ChatConversationController.php, Chat/chatApi.js, Chat/ChatGroupDialog.vue (+52). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Gom 7 lần lưu cùng một việc; xem bảng từng version bên dưới. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 10:14 | `6997376` | Sửa tiếp | Chat/ChatMemberDialog.vue |
| 10:31 | `109eda6` | Sửa tiếp | Chat/ChatConversationController.php, Chat/chatApi.js, Chat/ChatGroupDialog.vue, Chat/ChatMemberDialog.vue (+9) |
| 10:37 | `5da24ab` | Sửa tiếp | Chat/ChatService.php, Chat/ChatGroupDialog.vue, Chat/ChatUserSearch.vue, Chat/EmployeeChatHeaderToolbar.vue (+4) |
| 10:42 | `99fb176` | Sửa tiếp | Chat/ChatConversationList.vue, Chat/ChatUserSearch.vue, Chat/EmployeeChatHeaderToolbar.vue |
| 10:57 | `7ab689c` | Sửa tiếp | Chat/ChatAvatar.vue, Chat/ChatConversationView.vue, Chat/ChatMemberDialog.vue, employee-mobile-shell/chat.css |
| 11:48 | `4710680` | Sửa tiếp | Chat/ChatMessageController.php, Chat/ReactChatMessageRequest.php, Chat/Message.php, Chat/MessageReaction.php (+20) |
| 11:55 | `36b74df` | Sửa tiếp | Chat/ChatConversationView.vue, Social/SocialStoryRail.vue, lib/pwaVisualViewport.js |

#### Chi tiết từng lần lưu (version)

##### Version 1.9.1 — mã `6997376` · 10:14

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Chat trên màn Chat. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Chat/ChatMemberDialog.vue. |

##### Version 1.9.2 — mã `109eda6` · 10:31

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Chat trên màn Chat. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Chat/ChatConversationController.php, Chat/chatApi.js, Chat/ChatGroupDialog.vue, Chat/ChatMemberDialog.vue (+9). |

##### Version 1.9.3 — mã `5da24ab` · 10:37

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Chat trên màn Chat. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Chat/ChatService.php, Chat/ChatGroupDialog.vue, Chat/ChatUserSearch.vue, Chat/EmployeeChatHeaderToolbar.vue (+4). |

##### Version 1.9.4 — mã `99fb176` · 10:42

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Chat trên màn Chat. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Chat/ChatConversationList.vue, Chat/ChatUserSearch.vue, Chat/EmployeeChatHeaderToolbar.vue. |

##### Version 1.9.5 — mã `7ab689c` · 10:57

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Chat trên màn Chat. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Chat/ChatAvatar.vue, Chat/ChatConversationView.vue, Chat/ChatMemberDialog.vue, employee-mobile-shell/chat.css. |

##### Version 1.9.6 — mã `4710680` · 11:48

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat, Bảng tin. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Sửa nốt phần Chat trên màn Chat, Bảng tin. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu). File chính: Chat/ChatMessageController.php, Chat/ReactChatMessageRequest.php, Chat/Message.php, Chat/MessageReaction.php (+20). |

##### Version 1.9.7 — mã `36b74df` · 11:55

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat, Bảng tin. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Chat trên màn Chat, Bảng tin. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Chat/ChatConversationView.vue, Social/SocialStoryRail.vue, lib/pwaVisualViewport.js. |

### 1.10. Sửa tiếp phần Giao diện gốc (chưa ghi câu mô tả)

Giao diện gốc · Bảng tin · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc trên màn Bảng tin, đụng Social/SocialStoryRail.vue, employee-mobile-shell/chat.css, lib/pwaVisualViewport.js. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Giao diện gốc trên màn Bảng tin. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Social/SocialStoryRail.vue, employee-mobile-shell/chat.css, lib/pwaVisualViewport.js. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 11:59 | `7d4405f` | Sửa tiếp | Social/SocialStoryRail.vue, employee-mobile-shell/chat.css, lib/pwaVisualViewport.js |

#### Chi tiết từng lần lưu (version)

##### Version 1.10.1 — mã `7d4405f` · 11:59

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Bảng tin. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Giao diện gốc trên màn Bảng tin. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Social/SocialStoryRail.vue, employee-mobile-shell/chat.css, lib/pwaVisualViewport.js. |

### 1.11. Sửa tiếp phần Chat (chưa ghi câu mô tả)

Chat · Chat · 3 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc trên màn Chat, đụng Chat/ChatConversationController.php, Chat/HandlesChatFeatures.php, Chat/chatApi.js, Chat/ChatConversationView.vue (+8). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Gom 3 lần lưu cùng một việc; xem bảng từng version bên dưới. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 12:02 | `c509322` | Sửa tiếp | Chat/ChatConversationController.php, Chat/HandlesChatFeatures.php, Chat/chatApi.js, Chat/ChatConversationView.vue (+5) |
| 12:04 | `36e70d6` | Sửa tiếp | Chat/ChatConversationView.vue |
| 12:07 | `72d729d` | Sửa tiếp | Chat/ChatConversationView.vue, employee-mobile-shell/chat.css |

#### Chi tiết từng lần lưu (version)

##### Version 1.11.1 — mã `c509322` · 12:02

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Chat trên màn Chat. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Chat/ChatConversationController.php, Chat/HandlesChatFeatures.php, Chat/chatApi.js, Chat/ChatConversationView.vue (+5). |

##### Version 1.11.2 — mã `36e70d6` · 12:04

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Chat trên màn Chat. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Chat/ChatConversationView.vue. |

##### Version 1.11.3 — mã `72d729d` · 12:07

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Chat trên màn Chat. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Chat/ChatConversationView.vue, employee-mobile-shell/chat.css. |

### 1.12. Sửa tiếp phần Giao diện gốc (chưa ghi câu mô tả)

Giao diện gốc · Chat · 2 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc trên màn Chat, đụng Chat/ChatConversationView.vue, employee-mobile-shell/chat.css, lib/pwaVisualViewport.js, Chat/ChatConversationView.vue (+3). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Gom 2 lần lưu cùng một việc; xem bảng từng version bên dưới. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 12:10 | `c6a930e` | Sửa tiếp | Chat/ChatConversationView.vue, employee-mobile-shell/chat.css, lib/pwaVisualViewport.js |
| 12:19 | `c5b7447` | Sửa tiếp | Chat/ChatConversationView.vue, employee-mobile-shell/chat.css, lib/employeeMobileViewport.js, lib/pwaVisualViewport.js |

#### Chi tiết từng lần lưu (version)

##### Version 1.12.1 — mã `c6a930e` · 12:10

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Giao diện gốc trên màn Chat. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Chat/ChatConversationView.vue, employee-mobile-shell/chat.css, lib/pwaVisualViewport.js. |

##### Version 1.12.2 — mã `c5b7447` · 12:19

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Giao diện gốc trên màn Chat. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Chat/ChatConversationView.vue, employee-mobile-shell/chat.css, lib/employeeMobileViewport.js, lib/pwaVisualViewport.js. |

### 1.13. Sửa tiếp phần Chat (chưa ghi câu mô tả)

Chat · Chat · 4 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc trên màn Chat, đụng Chat/ChatConversationView.vue, lib/pwaVisualViewport.js, Chat/ChatConversationView.vue, Chat/ChatConversationList.vue (+6). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Gom 4 lần lưu cùng một việc; xem bảng từng version bên dưới. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 12:27 | `994b982` | Sửa tiếp | Chat/ChatConversationView.vue, lib/pwaVisualViewport.js |
| 12:31 | `6f39a75` | Sửa tiếp | Chat/ChatConversationView.vue |
| 12:47 | `b5fc178` | Sửa tiếp | Chat/ChatConversationList.vue, Chat/ChatConversationView.vue, employee-mobile-shell/base.css, employee-mobile-shell/chat.css |
| 12:56 | `bbeb4ba` | Sửa tiếp | Chat/ChatConversationView.vue, Chat/chatStore.js, employee-mobile-shell/chat.css |

#### Chi tiết từng lần lưu (version)

##### Version 1.13.1 — mã `994b982` · 12:27

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Chat trên màn Chat. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Chat/ChatConversationView.vue, lib/pwaVisualViewport.js. |

##### Version 1.13.2 — mã `6f39a75` · 12:31

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Chat trên màn Chat. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Chat/ChatConversationView.vue. |

##### Version 1.13.3 — mã `b5fc178` · 12:47

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Chat trên màn Chat. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Chat/ChatConversationList.vue, Chat/ChatConversationView.vue, employee-mobile-shell/base.css, employee-mobile-shell/chat.css. |

##### Version 1.13.4 — mã `bbeb4ba` · 12:56

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Chat. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Chat trên màn Chat. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Chat/ChatConversationView.vue, Chat/chatStore.js, employee-mobile-shell/chat.css. |

### 1.14. Củng cố PWA, service worker và Web Push cho khu vực nhân viên

Giao diện gốc · Chấm công nhân viên, Lịch sử, Chat, Bảng tin, Tổng quan của tôi · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Củng cố PWA, service worker và Web Push cho khu vực nhân viên |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Củng cố PWA, service worker và Web Push cho khu vực nhân viên. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu). File chính: .env.example, Attendance/EmployeeMobileInstallBanner.vue, Attendance/EmployeeMobileOfflineStrip.vue, Attendance/EmployeeMobileTabbar.vue (+60). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 14:03 | `a8c61ac` | Cập nhật | .env.example, Attendance/EmployeeMobileInstallBanner.vue, Attendance/EmployeeMobileOfflineStrip.vue, Attendance/EmployeeMobileTabbar.vue (+60) |

#### Chi tiết từng lần lưu (version)

##### Version 1.14.1 — mã `a8c61ac` · 14:03

| | |
| --- | --- |
| **Đổi gì** | Củng cố PWA, service worker và Web Push cho khu vực nhân viên |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Củng cố PWA, service worker và Web Push cho khu vực nhân viên. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu). File chính: .env.example, Attendance/EmployeeMobileInstallBanner.vue, Attendance/EmployeeMobileOfflineStrip.vue, Attendance/EmployeeMobileTabbar.vue (+60). |

