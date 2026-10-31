# Đối soát 03/09/2026

Đọc nhanh: [README.md](./README.md) · Tuần: [2026-W36](../summary/2026-W36-summary.md) · Bảng tuần: [2026-W36-audit](../summary/2026-W36-audit.md)

Việc trong ngày đánh số **1.1**, **1.2**, … Mỗi lần lưu (commit) là **1.1.1**, **1.1.2**, … Bảng có thêm dòng **Giải thích đơn giản** cho người không rành kỹ thuật. Thiếu trong commit thì ghi «chưa ghi», không suy diễn.

## Việc trong ngày

### 1.1. Kit schema v2 cho Cách 2 + khoá kỳ báo cáo đã lưu

Đánh giá · Tiêu chí đánh giá, Báo cáo, Công việc và dự án · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Kit schema v2 cho Cách 2 + khoá kỳ báo cáo đã lưu. - Thêm kit_schema_version cho bộ điểm: công thức Cách 2 mới tính việc   chưa xong/thiếu dữ liệu = 0 điểm thực, kit cũ giữ nguyên công thức   legacy để báo cáo đã lưu không đổi số; kèm command repair dữ liệu cũ. - Report: bỏ luồng sửa/xem chi tiết báo cáo đã lưu, thay bằng khoá ghi   nhận/xoá dữ liệu rơi vào kỳ đã có báo cáo nhân sự đã lưu.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | để báo cáo đã lưu không đổi số |
| **Tác dụng** | báo cáo đã lưu không đổi số; kèm command repair dữ liệu cũ; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Kit schema v2 cho Cách 2 + khoá kỳ báo cáo đã lưu. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu). File chính: rules/data-table.mdc, data-table/SKILL.md, Evaluation/RepairCach2Command.php, Evaluation/EvaluationScoreKitController.php (+32). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 13:20 | `1966255` | Thêm | rules/data-table.mdc, data-table/SKILL.md, Evaluation/RepairCach2Command.php, Evaluation/EvaluationScoreKitController.php (+32) |

#### Chi tiết từng lần lưu (version)

##### Version 1.1.1 — mã `1966255` · 13:20

| | |
| --- | --- |
| **Đổi gì** | Kit schema v2 cho Cách 2 + khoá kỳ báo cáo đã lưu. - Thêm kit_schema_version cho bộ điểm: công thức Cách 2 mới tính việc   chưa xong/thiếu dữ liệu = 0 điểm thực, kit cũ giữ nguyên công thức   legacy để báo cáo đã lưu không đổi số; kèm command repair dữ liệu cũ. - Report: bỏ luồng sửa/xem chi tiết báo cáo đã lưu, thay bằng khoá ghi   nhận/xoá dữ liệu rơi vào kỳ đã có báo cáo nhân sự đã lưu.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | để báo cáo đã lưu không đổi số |
| **Tác dụng** | báo cáo đã lưu không đổi số; kèm command repair dữ liệu cũ; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Kit schema v2 cho Cách 2 + khoá kỳ báo cáo đã lưu. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu). File chính: rules/data-table.mdc, data-table/SKILL.md, Evaluation/RepairCach2Command.php, Evaluation/EvaluationScoreKitController.php (+32). |

### 1.2. Cải tiến UI trang Tiêu chí đánh giá

Đánh giá · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Cải tiến UI trang Tiêu chí đánh giá. Thêm insight stats, empty state, điều hướng bàn phím, badge nhóm cho trang quản lý Tiêu chí đánh giá. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Cải tiến UI trang Tiêu chí đánh giá. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Evaluation/EvaluationView.vue. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 23:27 | `2d16bec` | Cấu trúc lại | Evaluation/EvaluationView.vue |

#### Chi tiết từng lần lưu (version)

##### Version 1.2.1 — mã `2d16bec` · 23:27

| | |
| --- | --- |
| **Đổi gì** | Cải tiến UI trang Tiêu chí đánh giá. Thêm insight stats, empty state, điều hướng bàn phím, badge nhóm cho trang quản lý Tiêu chí đánh giá. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Cải tiến UI trang Tiêu chí đánh giá. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Evaluation/EvaluationView.vue. |

### 1.3. PDF chung + xuất PDF tổng hợp đánh giá

Đánh giá · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | PDF chung + xuất PDF tổng hợp đánh giá. Tách watermark PDF thành class dùng chung (PdfWatermark), thêm endpoint xuất PDF form ngang cho bảng tổng hợp đánh giá, bổ sung email/avatar cho danh sách nhân sự phòng ban và recorded_by cho phiếu ghi nhận để phục vụ nội dung PDF. |
| **Vì sao** | để phục vụ nội dung PDF |
| **Tác dụng** | phục vụ nội dung PDF |
| **Giải thích đơn giản** | PDF chung + xuất PDF tổng hợp đánh giá. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Evaluation/EvaluationSummaryController.php, Evaluation/EvaluationSummaryRequest.php, Evaluation/EvaluationCriteriaService.php, Evaluation/EvaluationEventService.php (+7). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 23:27 | `d2cde9e` | Thêm | Evaluation/EvaluationSummaryController.php, Evaluation/EvaluationSummaryRequest.php, Evaluation/EvaluationCriteriaService.php, Evaluation/EvaluationEventService.php (+7) |

#### Chi tiết từng lần lưu (version)

##### Version 1.3.1 — mã `d2cde9e` · 23:27

| | |
| --- | --- |
| **Đổi gì** | PDF chung + xuất PDF tổng hợp đánh giá. Tách watermark PDF thành class dùng chung (PdfWatermark), thêm endpoint xuất PDF form ngang cho bảng tổng hợp đánh giá, bổ sung email/avatar cho danh sách nhân sự phòng ban và recorded_by cho phiếu ghi nhận để phục vụ nội dung PDF. |
| **Vì sao** | để phục vụ nội dung PDF |
| **Tác dụng** | phục vụ nội dung PDF |
| **Giải thích đơn giản** | PDF chung + xuất PDF tổng hợp đánh giá. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Evaluation/EvaluationSummaryController.php, Evaluation/EvaluationSummaryRequest.php, Evaluation/EvaluationCriteriaService.php, Evaluation/EvaluationEventService.php (+7). |

### 1.4. Khoá kỳ báo cáo đã lưu + phụ lục cột tiêu chí

Báo cáo · Quyền và nhật ký, Báo cáo · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Khoá kỳ báo cáo đã lưu + phụ lục cột tiêu chí. Thêm bảng report_display_revisions để lưu phụ lục hiển thị khi cột tiêu chí đổi sau khi báo cáo đã chốt lưu, thay vì ghi đè bản gốc. Bổ sung endpoint cập nhật hiển thị báo cáo đánh giá nhân sự và ghi activity log tương ứng. |
| **Vì sao** | để lưu phụ lục hiển thị khi cột tiêu chí đổi sau khi báo cáo đã chốt lưu, thay vì ghi đè bản gốc |
| **Tác dụng** | lưu phụ lục hiển thị khi cột tiêu chí đổi sau khi báo cáo đã chốt lưu, thay vì ghi đè bản gốc; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Khoá kỳ báo cáo đã lưu + phụ lục cột tiêu chí. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu). File chính: Identity/ActivityLogService.php, Report/ReportController.php, Report/UpdatePersonnelEvaluationDisplayRequest.php, Report/Report.php (+7). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 23:28 | `3223734` | Thêm | Identity/ActivityLogService.php, Report/ReportController.php, Report/UpdatePersonnelEvaluationDisplayRequest.php, Report/Report.php (+7) |

#### Chi tiết từng lần lưu (version)

##### Version 1.4.1 — mã `3223734` · 23:28

| | |
| --- | --- |
| **Đổi gì** | Khoá kỳ báo cáo đã lưu + phụ lục cột tiêu chí. Thêm bảng report_display_revisions để lưu phụ lục hiển thị khi cột tiêu chí đổi sau khi báo cáo đã chốt lưu, thay vì ghi đè bản gốc. Bổ sung endpoint cập nhật hiển thị báo cáo đánh giá nhân sự và ghi activity log tương ứng. |
| **Vì sao** | để lưu phụ lục hiển thị khi cột tiêu chí đổi sau khi báo cáo đã chốt lưu, thay vì ghi đè bản gốc |
| **Tác dụng** | lưu phụ lục hiển thị khi cột tiêu chí đổi sau khi báo cáo đã chốt lưu, thay vì ghi đè bản gốc; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Khoá kỳ báo cáo đã lưu + phụ lục cột tiêu chí. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu). File chính: Identity/ActivityLogService.php, Report/ReportController.php, Report/UpdatePersonnelEvaluationDisplayRequest.php, Report/Report.php (+7). |

### 1.5. Kết nối bảng Đánh giá nhân sự với báo cáo

Báo cáo · Báo cáo · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Kết nối bảng Đánh giá nhân sự với báo cáo. Đổi "Tổng hợp đánh giá" thành "Đánh giá nhân sự" gắn với báo cáo cụ thể qua query ?report=, redesign wizard tạo báo cáo và danh sách báo cáo theo mẫu vàng data-table (kéo cột, ẩn scrollbar). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Kết nối bảng Đánh giá nhân sự với báo cáo. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: rules/data-table.mdc, data-table/SKILL.md, Evaluation/EvaluationSummary.vue, Evaluation/router.js (+4). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 23:28 | `67ea656` | Thêm | rules/data-table.mdc, data-table/SKILL.md, Evaluation/EvaluationSummary.vue, Evaluation/router.js (+4) |

#### Chi tiết từng lần lưu (version)

##### Version 1.5.1 — mã `67ea656` · 23:28

| | |
| --- | --- |
| **Đổi gì** | Kết nối bảng Đánh giá nhân sự với báo cáo. Đổi "Tổng hợp đánh giá" thành "Đánh giá nhân sự" gắn với báo cáo cụ thể qua query ?report=, redesign wizard tạo báo cáo và danh sách báo cáo theo mẫu vàng data-table (kéo cột, ẩn scrollbar). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Kết nối bảng Đánh giá nhân sự với báo cáo. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: rules/data-table.mdc, data-table/SKILL.md, Evaluation/EvaluationSummary.vue, Evaluation/router.js (+4). |

### 1.6. Gỡ/thêm mục sidebar tương ứng

Cấu hình workspace · Cấu hình đơn từ, Cấu hình menu · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Gỡ/thêm mục sidebar tương ứng. Đồng bộ cấu hình sidebar theo phòng ban và global menu với thay đổi Đánh giá nhân sự — không còn là mục sidebar riêng, chỉ truy cập từ Báo cáo. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Gỡ/thêm mục sidebar tương ứng. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: WorkspaceConfig/DepartmentSidebarConfigService.php, WorkspaceConfig/GlobalMenuVisibilityService.php, WorkspaceConfig/sidebarMenus.js, components/AppSidebar.vue (+2). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 23:29 | `113b9d2` | Thêm | WorkspaceConfig/DepartmentSidebarConfigService.php, WorkspaceConfig/GlobalMenuVisibilityService.php, WorkspaceConfig/sidebarMenus.js, components/AppSidebar.vue (+2) |

#### Chi tiết từng lần lưu (version)

##### Version 1.6.1 — mã `113b9d2` · 23:29

| | |
| --- | --- |
| **Đổi gì** | Gỡ/thêm mục sidebar tương ứng. Đồng bộ cấu hình sidebar theo phòng ban và global menu với thay đổi Đánh giá nhân sự — không còn là mục sidebar riêng, chỉ truy cập từ Báo cáo. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Gỡ/thêm mục sidebar tương ứng. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: WorkspaceConfig/DepartmentSidebarConfigService.php, WorkspaceConfig/GlobalMenuVisibilityService.php, WorkspaceConfig/sidebarMenus.js, components/AppSidebar.vue (+2). |

### 1.7. Cập nhật tài liệu

Tài liệu · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Cập nhật tài liệu. Cập nhật tài liệu module Evaluation và Report theo các thay đổi xuất PDF, khoá kỳ báo cáo đã lưu, và kết nối Đánh giá nhân sự với báo cáo. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Cập nhật tài liệu. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: modules/Evaluation.md, modules/Report.md. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 23:38 | `332896e` | Tài liệu | modules/Evaluation.md, modules/Report.md |

#### Chi tiết từng lần lưu (version)

##### Version 1.7.1 — mã `332896e` · 23:38

| | |
| --- | --- |
| **Đổi gì** | Cập nhật tài liệu. Cập nhật tài liệu module Evaluation và Report theo các thay đổi xuất PDF, khoá kỳ báo cáo đã lưu, và kết nối Đánh giá nhân sự với báo cáo. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Cập nhật tài liệu. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: modules/Evaluation.md, modules/Report.md. |

### 1.8. Trang Chi tiết công việc độc lập

Dự án và công việc · Công việc và dự án, Cấu hình menu · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Trang Chi tiết công việc độc lập. Tách panel "Chi tiết công việc" trong TaskList.vue thành trang riêng TaskDetail.vue, điều hướng qua route /manager/project/tasks/{id}. Cho phép chỉnh sửa người theo dõi/phối hợp, hiện tên người chấm điểm, và cập nhật URL thông báo liên quan sang route mới. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Trang Chi tiết công việc độc lập. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Project/UpdateTaskRequest.php, Project/Task.php, Project/TaskScoreService.php, Project/TaskService.php (+5). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 23:39 | `91eea44` | Thêm | Project/UpdateTaskRequest.php, Project/Task.php, Project/TaskScoreService.php, Project/TaskService.php (+5) |

#### Chi tiết từng lần lưu (version)

##### Version 1.8.1 — mã `91eea44` · 23:39

| | |
| --- | --- |
| **Đổi gì** | Trang Chi tiết công việc độc lập. Tách panel "Chi tiết công việc" trong TaskList.vue thành trang riêng TaskDetail.vue, điều hướng qua route /manager/project/tasks/{id}. Cho phép chỉnh sửa người theo dõi/phối hợp, hiện tên người chấm điểm, và cập nhật URL thông báo liên quan sang route mới. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Trang Chi tiết công việc độc lập. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Project/UpdateTaskRequest.php, Project/Task.php, Project/TaskScoreService.php, Project/TaskService.php (+5). |

