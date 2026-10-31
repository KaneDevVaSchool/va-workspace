# Đối soát 03/09/2026

Đọc nhanh: [README.md](./README.md) · Tuần: [2026-W36](../summary/2026-W36-summary.md) · Bảng tuần: [2026-W36-audit](../summary/2026-W36-audit.md)

## Việc trong ngày

| # | Loại | Phần | Màn hình | Việc | Lần lưu | Đổi dữ liệu |
| --- | --- | --- | --- | --- | ---: | --- |
| 1 | Thêm | Đánh giá | Tiêu chí đánh giá, Báo cáo, Công việc và dự án | Kit schema v2 cho Cách 2 + khoá kỳ báo cáo đã lưu | 1 | Đổi cấu trúc dữ liệu |
| 2 | Cấu trúc lại | Đánh giá | Không gắn màn hình | Cải tiến UI trang Tiêu chí đánh giá | 1 | Không |
| 3 | Thêm | Đánh giá | Không gắn màn hình | PDF chung + xuất PDF tổng hợp đánh giá | 1 | Không |
| 4 | Thêm | Báo cáo | Quyền và nhật ký, Báo cáo | Khoá kỳ báo cáo đã lưu + phụ lục cột tiêu chí | 1 | Đổi cấu trúc dữ liệu |
| 5 | Thêm | Báo cáo | Báo cáo | Kết nối bảng Đánh giá nhân sự với báo cáo | 1 | Không |
| 6 | Thêm | Cấu hình workspace | Cấu hình đơn từ, Cấu hình menu | Gỡ/thêm mục sidebar tương ứng | 1 | Không |
| 7 | Tài liệu | Tài liệu | Không gắn màn hình | Cập nhật tài liệu | 1 | Không |
| 8 | Thêm | Dự án và công việc | Công việc và dự án, Cấu hình menu | Trang Chi tiết công việc độc lập | 1 | Không |

## Từng lần lưu

| Giờ | Mã | Loại | Phần | Màn hình | Đã ghi | Chỗ sửa |
| --- | --- | --- | --- | --- | --- | --- |
| 13:20 | `1966255` | Thêm | Đánh giá | Tiêu chí đánh giá, Báo cáo, Công việc và dự án | Kit schema v2 cho Cách 2 + khoá kỳ báo cáo đã lưu | rules/data-table.mdc, data-table/SKILL.md, Evaluation/RepairCach2Command.php, Evaluation/EvaluationScoreKitController.php (+32) |
| 23:27 | `2d16bec` | Cấu trúc lại | Đánh giá | Không gắn màn hình | Cải tiến UI trang Tiêu chí đánh giá | Evaluation/EvaluationView.vue |
| 23:27 | `d2cde9e` | Thêm | Đánh giá | Không gắn màn hình | PDF chung + xuất PDF tổng hợp đánh giá | Evaluation/EvaluationSummaryController.php, Evaluation/EvaluationSummaryRequest.php, Evaluation/EvaluationCriteriaService.php, Evaluation/EvaluationEventService.php (+7) |
| 23:28 | `3223734` | Thêm | Báo cáo | Quyền và nhật ký, Báo cáo | Khoá kỳ báo cáo đã lưu + phụ lục cột tiêu chí | Identity/ActivityLogService.php, Report/ReportController.php, Report/UpdatePersonnelEvaluationDisplayRequest.php, Report/Report.php (+7) |
| 23:28 | `67ea656` | Thêm | Báo cáo | Báo cáo | Kết nối bảng Đánh giá nhân sự với báo cáo | rules/data-table.mdc, data-table/SKILL.md, Evaluation/EvaluationSummary.vue, Evaluation/router.js (+4) |
| 23:29 | `113b9d2` | Thêm | Cấu hình workspace | Cấu hình đơn từ, Cấu hình menu | Gỡ/thêm mục sidebar tương ứng | WorkspaceConfig/DepartmentSidebarConfigService.php, WorkspaceConfig/GlobalMenuVisibilityService.php, WorkspaceConfig/sidebarMenus.js, components/AppSidebar.vue (+2) |
| 23:38 | `332896e` | Tài liệu | Tài liệu | Không gắn màn hình | Cập nhật tài liệu | modules/Evaluation.md, modules/Report.md |
| 23:39 | `91eea44` | Thêm | Dự án và công việc | Công việc và dự án, Cấu hình menu | Trang Chi tiết công việc độc lập | Project/UpdateTaskRequest.php, Project/Task.php, Project/TaskScoreService.php, Project/TaskService.php (+5) |

