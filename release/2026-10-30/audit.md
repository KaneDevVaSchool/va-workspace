# Đối soát 30/10/2026

Đọc nhanh: [README.md](./README.md) · Tuần: [2026-W44](../summary/2026-W44-summary.md) · Bảng tuần: [2026-W44-audit](../summary/2026-W44-audit.md)

Việc trong ngày đánh số version **1.1**, **1.2**, … Mỗi việc một bảng Đổi gì / Vì sao / Tác dụng. Thiếu trong commit thì ghi «chưa ghi», không suy diễn.

## Việc trong ngày

### 1.1. Xuất PDF đơn nghỉ phép đã duyệt theo mẫu BM.01

Chấm công · Lịch sử · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Xuất PDF đơn nghỉ phép đã duyệt theo mẫu BM.01. Nhân viên tải đơn từ danh sách khi trạng thái đã duyệt. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 11:00 | `1dad47f` | Thêm | Attendance/EmployeeLeaveRequestController.php, Attendance/AttendanceServiceProvider.php, Attendance/EmployeeLeaveFormService.php, Attendance/va-form-logo.png (+9) |

### 1.2. Giữ cơ sở hồ sơ khi chấm công, chỉ gửi chi nhánh khi nhân viên chọn cơ sở khác

Chấm công · Chấm công nhân viên · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Giữ cơ sở hồ sơ khi chấm công, chỉ gửi chi nhánh khi nhân viên chọn cơ sở khác. Hồ sơ không khớp thì bắt chọn cơ sở GPS trước khi chấm. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 11:35 | `6bf5736` | Thêm | Attendance/EmployeeAttendancePunchService.php, Attendance/AttendancePunchConfirmDialog.vue, Attendance/employeeAttendanceGeo.js, Attendance/EmployeeAttendance.vue (+2) |

### 1.3. Chỉnh mẫu PDF đơn nghỉ phép có quốc hiệu, chỗ ký và watermark

Chấm công · Lịch sử · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Chỉnh mẫu PDF đơn nghỉ phép có quốc hiệu, chỗ ký và watermark. HCNS và Ban TGĐ sang trang 2. Nút tải nằm ở trang Lịch sử. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 11:35 | `53ffb5e` | Thêm | Attendance/EmployeeLeaveFormService.php, Attendance/va-form-watermark.png, Attendance/leave-form.blade.php, modules/Attendance.md (+1) |

### 1.4. Ô soạn không render lại danh sách tin, kéo tin sát bàn phím

Chat · Chat · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Ô soạn không render lại danh sách tin, kéo tin sát bàn phím. Bỏ hàng trả lời nhanh. iOS không đẩy cả khung chat lên khi mở bàn phím. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 11:35 | `aa832b1` | Sửa | Chat/ChatConversationView.vue, Chat/chatPlay.js, lib/pwaVisualViewport.js |

### 1.5. Khung gợi ý nhắc tên neo theo ô soạn, không bị thẻ bài cắt

Bảng tin · Bảng tin · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Khung gợi ý nhắc tên neo theo ô soạn, không bị thẻ bài cắt |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 11:35 | `894b9d6` | Sửa | Social/SocialMentionPicker.vue, Social/SocialPostEditor.vue |

### 1.6. Nhận link YouTube dạng shorts, live và nocookie, gắn origin lúc phát để khỏi lỗi 153

Bảng tin · Bảng tin · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Nhận link YouTube dạng shorts, live và nocookie, gắn origin lúc phát để khỏi lỗi 153 |
| **Vì sao** | để khỏi lỗi 153 |
| **Tác dụng** | khỏi lỗi 153 |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 11:35 | `33291f2` | Sửa | Social/SocialLinkPreviewService.php, Social/SocialLinkPreviewCard.vue, Social/linkPreviewUtils.js, docs/known-issues.md (+1) |

### 1.7. Chuyển tài liệu module sang bố cục BMAD và thêm rule đối chiếu phiếu

Tài liệu · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Chuyển tài liệu module sang bố cục BMAD và thêm rule đối chiếu phiếu. Mục lục ở docs/index.md. Evaluation, Report, Social nằm ở architecture/modules. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 11:35 | `71f0205` | Tài liệu | rules/docs-bmad.mdc, rules/docs-crosscheck.mdc, rules/release-notes.mdc, docs-bmad/SKILL.md (+15) |

### 1.8. Phiếu release và audit ngày 30/10

Tài liệu · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Phiếu release và audit ngày 30/10. Bảy việc trong ngày, mỗi phần một commit. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 11:35 | `9869398` | Tài liệu | 2026-08-24/README.md, 2026-08-24/audit.md, 2026-08-25/README.md, 2026-08-25/audit.md (+81) |

### 1.9. Implement colleague tagging feature with mentions support

Bảng tin · Bảng tin · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Implement colleague tagging feature with mentions support. Added functionality for tagging colleagues in posts and comments using the "@" symbol. Enhanced the mention search API to support pagination with limit and offset parameters. Updated the UI components to include a mention button and improved styling for mentions. Added tests to ensure mentions can be searched beyond the first page. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 14:53 | `5c46bc8` | Thêm | Social/SocialCommentController.php, Social/SocialCommentService.php, Social/SocialCommentComposer.vue, Social/SocialCommentItem.vue (+7) |

### 1.10. Nhóm bảng tin theo pháp nhân VM, Viễn Đông, Hoàng Việt và CIT

Bảng tin · Nhóm, Bảng tin · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Nhóm bảng tin theo pháp nhân VM, Viễn Đông, Hoàng Việt và CIT |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 15:52 | `40e219f` | Thêm | Social/SocialGroupController.php, Social/StoreSocialGroupRequest.php, Social/UpdateSocialGroupRequest.php, Social/SocialGroup.php (+11) |

### 1.11. Đẩy yêu cầu sửa hồ sơ sang hàng đợi HCNS

Tổng quan · Hồ sơ cá nhân, Tổng quan của tôi · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Đẩy yêu cầu sửa hồ sơ sang hàng đợi HCNS |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 15:52 | `a5f506e` | Thêm | Attendance/EmployeeProfile.vue, Dashboard/DashboardProfileChangeRequest.php, Dashboard/MyDashboardProfileService.php, Dashboard/2026_10_30_160100_add_hrm_synced_at_to_dashboard_profile_change_requests.php (+4) |

### 1.12. Phiếu release và audit ngày 30/10

Tài liệu · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Phiếu release và audit ngày 30/10 |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 15:56 | `0933b39` | Tài liệu | scripts/build-release-notes.mjs, 2026-08-25/README.md, 2026-08-25/audit.md, 2026-08-26/README.md (+25) |

### 1.13. Unify 'hired_at' and 'actual_start_date' fields in employee profile

Chấm công · Hồ sơ cá nhân, Tổng quan của tôi · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Unify 'hired_at' and 'actual_start_date' fields in employee profile |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 16:57 | `130f9a4` | Thêm | Attendance/EmployeeProfile.vue, Dashboard/MyDashboardProfileService.php, Identity/HrmEmployeeDirectory.php, Identity/HrmEmployeeDirectoryTest.php |

