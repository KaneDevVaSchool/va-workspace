# Đối soát 30/10/2026

Đọc nhanh: [README.md](./README.md) · Tuần: [2026-W44](../summary/2026-W44-summary.md) · Bảng tuần: [2026-W44-audit](../summary/2026-W44-audit.md)

## Việc trong ngày

| # | Loại | Phần | Màn hình | Việc | Lần lưu | Đổi dữ liệu |
| --- | --- | --- | --- | --- | ---: | --- |
| 1 | Thêm | Chấm công | Lịch sử | Xuất PDF đơn nghỉ phép đã duyệt theo mẫu BM.01 | 1 | Không |
| 2 | Thêm | Chấm công | Chấm công nhân viên | Giữ cơ sở hồ sơ khi chấm công, chỉ gửi chi nhánh khi nhân viên chọn cơ sở khác | 1 | Không |
| 3 | Thêm | Chấm công | Lịch sử | Chỉnh mẫu PDF đơn nghỉ phép có quốc hiệu, chỗ ký và watermark | 1 | Không |
| 4 | Sửa | Chat | Chat | Ô soạn không render lại danh sách tin, kéo tin sát bàn phím | 1 | Không |
| 5 | Sửa | Bảng tin | Bảng tin | Khung gợi ý nhắc tên neo theo ô soạn, không bị thẻ bài cắt | 1 | Không |
| 6 | Sửa | Bảng tin | Bảng tin | Nhận link YouTube dạng shorts, live và nocookie, gắn origin lúc phát để khỏi lỗi 153 | 1 | Không |
| 7 | Tài liệu | Tài liệu | Không gắn màn hình | Chuyển tài liệu module sang bố cục BMAD và thêm rule đối chiếu phiếu | 1 | Không |
| 8 | Tài liệu | Tài liệu | Không gắn màn hình | Phiếu release và audit ngày 30/10 | 1 | Không |
| 9 | Thêm | Bảng tin | Bảng tin | Implement colleague tagging feature with mentions support | 1 | Không |
| 10 | Thêm | Bảng tin | Nhóm, Bảng tin | Nhóm bảng tin theo pháp nhân VM, Viễn Đông, Hoàng Việt và CIT | 1 | Đổi cấu trúc dữ liệu |
| 11 | Thêm | Tổng quan | Hồ sơ cá nhân, Tổng quan của tôi | Đẩy yêu cầu sửa hồ sơ sang hàng đợi HCNS | 1 | Đổi cấu trúc dữ liệu |

## Từng lần lưu

| Giờ | Mã | Loại | Phần | Màn hình | Đã ghi | Chỗ sửa |
| --- | --- | --- | --- | --- | --- | --- |
| 11:00 | `1dad47f` | Thêm | Chấm công | Lịch sử | Xuất PDF đơn nghỉ phép đã duyệt theo mẫu BM.01 | Attendance/EmployeeLeaveRequestController.php, Attendance/AttendanceServiceProvider.php, Attendance/EmployeeLeaveFormService.php, Attendance/va-form-logo.png (+9) |
| 11:35 | `6bf5736` | Thêm | Chấm công | Chấm công nhân viên | Giữ cơ sở hồ sơ khi chấm công, chỉ gửi chi nhánh khi nhân viên chọn cơ sở khác | Attendance/EmployeeAttendancePunchService.php, Attendance/AttendancePunchConfirmDialog.vue, Attendance/employeeAttendanceGeo.js, Attendance/EmployeeAttendance.vue (+2) |
| 11:35 | `53ffb5e` | Thêm | Chấm công | Lịch sử | Chỉnh mẫu PDF đơn nghỉ phép có quốc hiệu, chỗ ký và watermark | Attendance/EmployeeLeaveFormService.php, Attendance/va-form-watermark.png, Attendance/leave-form.blade.php, modules/Attendance.md (+1) |
| 11:35 | `aa832b1` | Sửa | Chat | Chat | Ô soạn không render lại danh sách tin, kéo tin sát bàn phím | Chat/ChatConversationView.vue, Chat/chatPlay.js, lib/pwaVisualViewport.js |
| 11:35 | `894b9d6` | Sửa | Bảng tin | Bảng tin | Khung gợi ý nhắc tên neo theo ô soạn, không bị thẻ bài cắt | Social/SocialMentionPicker.vue, Social/SocialPostEditor.vue |
| 11:35 | `33291f2` | Sửa | Bảng tin | Bảng tin | Nhận link YouTube dạng shorts, live và nocookie, gắn origin lúc phát để khỏi lỗi 153 | Social/SocialLinkPreviewService.php, Social/SocialLinkPreviewCard.vue, Social/linkPreviewUtils.js, docs/known-issues.md (+1) |
| 11:35 | `71f0205` | Tài liệu | Tài liệu | Không gắn màn hình | Chuyển tài liệu module sang bố cục BMAD và thêm rule đối chiếu phiếu | rules/docs-bmad.mdc, rules/docs-crosscheck.mdc, rules/release-notes.mdc, docs-bmad/SKILL.md (+15) |
| 11:35 | `9869398` | Tài liệu | Tài liệu | Không gắn màn hình | Phiếu release và audit ngày 30/10 | 2026-08-24/README.md, 2026-08-24/audit.md, 2026-08-25/README.md, 2026-08-25/audit.md (+81) |
| 14:53 | `5c46bc8` | Thêm | Bảng tin | Bảng tin | Implement colleague tagging feature with mentions support | Social/SocialCommentController.php, Social/SocialCommentService.php, Social/SocialCommentComposer.vue, Social/SocialCommentItem.vue (+7) |
| 15:52 | `40e219f` | Thêm | Bảng tin | Nhóm, Bảng tin | Nhóm bảng tin theo pháp nhân VM, Viễn Đông, Hoàng Việt và CIT | Social/SocialGroupController.php, Social/StoreSocialGroupRequest.php, Social/UpdateSocialGroupRequest.php, Social/SocialGroup.php (+11) |
| 15:52 | `a5f506e` | Thêm | Tổng quan | Hồ sơ cá nhân, Tổng quan của tôi | Đẩy yêu cầu sửa hồ sơ sang hàng đợi HCNS | Attendance/EmployeeProfile.vue, Dashboard/DashboardProfileChangeRequest.php, Dashboard/MyDashboardProfileService.php, Dashboard/2026_10_30_160100_add_hrm_synced_at_to_dashboard_profile_change_requests.php (+4) |

