# Đối soát 01/09/2026

Đọc nhanh: [README.md](./README.md) · Tuần: [2026-W36](../summary/2026-W36-summary.md) · Bảng tuần: [2026-W36-audit](../summary/2026-W36-audit.md)

Việc trong ngày đánh số **1.1**, **1.2**, … Mỗi lần lưu (commit) là **1.1.1**, **1.1.2**, … Bảng có thêm dòng **Giải thích đơn giản** cho người không rành kỹ thuật. Thiếu trong commit thì ghi «chưa ghi», không suy diễn.

## Việc trong ngày

### 1.1. Báo cáo đánh giá nhân sự + tổng hợp đánh giá theo kỳ

Đánh giá · Quyền và nhật ký, Tiêu chí đánh giá, Báo cáo, Công việc và dự án, Cấu hình menu · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Báo cáo đánh giá nhân sự + tổng hợp đánh giá theo kỳ. Module Report mới và luồng ghi nhận / tổng hợp điểm đánh giá phía Evaluation.  Evaluation: - Ghi nhận đánh giá nhân sự (EvaluationEvent): tạo/sửa/duyệt/từ chối/xoá,   áp mức tiêu chí hành vi làm nguồn điểm thật cho báo cáo. - Phiên bản cấu hình đánh giá (EvaluationConfigVersion): bản chụp bất biến   của khung chấm điểm + tiêu chí, để báo cáo cũ giữ đúng điểm khi cấu hình   được sửa về sau. - Trang Tổng hợp đánh giá: bảng cả phòng ban theo kỳ kèm số liệu công việc   và điểm từng tiêu chí, chấm điểm ngay trên bảng (thay danh sách phẳng +   modal cũ), giữ nguyên path/name route. - Khung chấm điểm mở rộng: cách tính điểm, công thức, hệ số hiệu suất,   điểm thưởng chất lượng, tiêu chí theo thang.  Report: - Báo cáo đánh giá nhân sự: chọn kỳ, phạm vi nhân sự, cột hiển thị và   người được xem; xem trước không tạo bản ghi. - Chụp danh sách nhân sự khi lưu (report_people_snapshots) để báo cáo đã   lưu không đổi theo biến động nhân sự sau đó. - Phân quyền: report.manage_department (trưởng phòng) và   report.view_assigned (người được chia sẻ), lọc thật ở backend.  Khác: - TaskRepository::forEvaluationPeriod() lấy công việc theo kỳ, giới hạn   đúng phòng ban để người làm liên phòng không kéo điểm phòng khác. - Nhật ký hoạt động: nhãn tiếng Việt cho khung chấm điểm, ghi nhận đánh   giá, phiên bản cấu hình và báo cáo. - Seeder: chuyển superadmin sang phòng HCNS, thêm demo project nghiệp vụ;   bỏ EvaluationTemplateSeeder đã xoá.  Tests: 84 test mới (Evaluation + Report) đều pass.  Co-Authored-By: Claude Opus 5 <noreply@anthropic.com> |
| **Vì sao** | để báo cáo cũ giữ đúng điểm khi cấu hình được sửa về sau để báo cáo đã lưu không đổi theo biến động nhân sự sau đó để người làm liên phòng không kéo điểm phòng khác |
| **Tác dụng** | báo cáo cũ giữ đúng điểm khi cấu hình được sửa về sau; báo cáo đã lưu không đổi theo biến động nhân sự sau đó; người làm liên phòng không kéo điểm phòng khác; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Báo cáo đánh giá nhân sự + tổng hợp đánh giá theo kỳ. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu). File chính: Evaluation/EvaluationConfigVersionController.php, Evaluation/EvaluationEventController.php, Evaluation/EvaluationScoreKitController.php, Evaluation/EvaluationSummaryController.php (+77). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 00:28 | `e823038` | Thêm | Evaluation/EvaluationConfigVersionController.php, Evaluation/EvaluationEventController.php, Evaluation/EvaluationScoreKitController.php, Evaluation/EvaluationSummaryController.php (+77) |

#### Chi tiết từng lần lưu (version)

##### Version 1.1.1 — mã `e823038` · 00:28

| | |
| --- | --- |
| **Đổi gì** | Báo cáo đánh giá nhân sự + tổng hợp đánh giá theo kỳ. Module Report mới và luồng ghi nhận / tổng hợp điểm đánh giá phía Evaluation.  Evaluation: - Ghi nhận đánh giá nhân sự (EvaluationEvent): tạo/sửa/duyệt/từ chối/xoá,   áp mức tiêu chí hành vi làm nguồn điểm thật cho báo cáo. - Phiên bản cấu hình đánh giá (EvaluationConfigVersion): bản chụp bất biến   của khung chấm điểm + tiêu chí, để báo cáo cũ giữ đúng điểm khi cấu hình   được sửa về sau. - Trang Tổng hợp đánh giá: bảng cả phòng ban theo kỳ kèm số liệu công việc   và điểm từng tiêu chí, chấm điểm ngay trên bảng (thay danh sách phẳng +   modal cũ), giữ nguyên path/name route. - Khung chấm điểm mở rộng: cách tính điểm, công thức, hệ số hiệu suất,   điểm thưởng chất lượng, tiêu chí theo thang.  Report: - Báo cáo đánh giá nhân sự: chọn kỳ, phạm vi nhân sự, cột hiển thị và   người được xem; xem trước không tạo bản ghi. - Chụp danh sách nhân sự khi lưu (report_people_snapshots) để báo cáo đã   lưu không đổi theo biến động nhân sự sau đó. - Phân quyền: report.manage_department (trưởng phòng) và   report.view_assigned (người được chia sẻ), lọc thật ở backend.  Khác: - TaskRepository::forEvaluationPeriod() lấy công việc theo kỳ, giới hạn   đúng phòng ban để người làm liên phòng không kéo điểm phòng khác. - Nhật ký hoạt động: nhãn tiếng Việt cho khung chấm điểm, ghi nhận đánh   giá, phiên bản cấu hình và báo cáo. - Seeder: chuyển superadmin sang phòng HCNS, thêm demo project nghiệp vụ;   bỏ EvaluationTemplateSeeder đã xoá.  Tests: 84 test mới (Evaluation + Report) đều pass.  Co-Authored-By: Claude Opus 5 <noreply@anthropic.com> |
| **Vì sao** | để báo cáo cũ giữ đúng điểm khi cấu hình được sửa về sau để báo cáo đã lưu không đổi theo biến động nhân sự sau đó để người làm liên phòng không kéo điểm phòng khác |
| **Tác dụng** | báo cáo cũ giữ đúng điểm khi cấu hình được sửa về sau; báo cáo đã lưu không đổi theo biến động nhân sự sau đó; người làm liên phòng không kéo điểm phòng khác; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Báo cáo đánh giá nhân sự + tổng hợp đánh giá theo kỳ. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu). File chính: Evaluation/EvaluationConfigVersionController.php, Evaluation/EvaluationEventController.php, Evaluation/EvaluationScoreKitController.php, Evaluation/EvaluationSummaryController.php (+77). |

### 1.2. 6 loại báo cáo + thiết kế lại màn Tổng hợp đánh giá

Đánh giá · Báo cáo · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | 6 loại báo cáo + thiết kế lại màn Tổng hợp đánh giá. Báo cáo - Thêm 5 loại báo cáo vào danh mục (công việc phòng ban, công việc cá nhân,   chi tiết dự án theo tháng, quản trị dự án theo tháng, chi tiết timesheet).   Report::TYPES vẫn chỉ chứa loại tạo được nên Form Request tự chặn 5 loại   mới; Report::TYPES_COMING_SOON chỉ để hiển thị lộ trình. - Nút "Tạo báo cáo" mở hộp thoại lưới 3 cột liệt kê đủ 6 loại: loại tạo   được xếp trước, 5 loại kia ghi "Sắp ra mắt" và không bấm được.  Quyền - Cấp report.* cho director_officer. Trước đó Giám đốc điều hành KHÔNG có   quyền report nào nên bị router chặn ngay ở /manager/reports. Kèm theo đó   giám đốc cũng sửa/xoá được báo cáo mọi phòng ban vì canManage() chỉ xét   report.* — đây là chủ ý, đã ghi rõ trong docs/modules/Report.md §4.  Tổng hợp đánh giá - Bố cục hai vùng "chọn nhân sự mới hiện": cột trái 22rem chỉ để chọn   người, toàn bộ chỗ còn lại là khu chấm điểm của đúng người đang chọn.   Chưa chọn ai thì khu đó hiện số tổng quan phòng ban thay vì bỏ trống.   Không theo mẫu data-table: đây là bàn làm việc từng người, không phải   danh sách tra cứu. - Chênh lệch so với trung bình phòng và % hoàn thành việc chuyển từ mỗi   dòng vào thẻ phân rã điểm — dòng gọn lại mà không mất thông tin. - delete() bỏ chặn "chỉ xoá bản chờ duyệt": trưởng phòng ghi nhận là duyệt   luôn nên ràng buộc cũ làm nút xoá không bao giờ chạy được. - event_breakdown trả thêm task_id để thẻ Ghi nhận hiện được việc liên quan.  Kiểm chứng: vite build pass; php artisan test --filter="Evaluation\|Report" 84 passed. Toàn suite 237 passed / 17 failed — 17 lỗi này có sẵn từ trước ở Social, WorkspaceConfig sidebar và PermissionMatrix (đối chứng bằng stash: trước 18 fail, sau 17 fail, chênh 1 là test mới thêm).  Co-Authored-By: Claude Opus 5 <noreply@anthropic.com> |
| **Vì sao** | vì canManage() chỉ xét report vì bỏ trống để thẻ Ghi nhận hiện được việc liên quan |
| **Tác dụng** | thẻ Ghi nhận hiện được việc liên quan |
| **Giải thích đơn giản** | 6 loại báo cáo + thiết kế lại màn Tổng hợp đánh giá. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Evaluation/EvaluationEventService.php, Evaluation/EvaluationScoreComputeService.php, Evaluation/EvaluationSummary.vue, Report/Report.php (+6). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 01:21 | `b0d204b` | Thêm | Evaluation/EvaluationEventService.php, Evaluation/EvaluationScoreComputeService.php, Evaluation/EvaluationSummary.vue, Report/Report.php (+6) |

#### Chi tiết từng lần lưu (version)

##### Version 1.2.1 — mã `b0d204b` · 01:21

| | |
| --- | --- |
| **Đổi gì** | 6 loại báo cáo + thiết kế lại màn Tổng hợp đánh giá. Báo cáo - Thêm 5 loại báo cáo vào danh mục (công việc phòng ban, công việc cá nhân,   chi tiết dự án theo tháng, quản trị dự án theo tháng, chi tiết timesheet).   Report::TYPES vẫn chỉ chứa loại tạo được nên Form Request tự chặn 5 loại   mới; Report::TYPES_COMING_SOON chỉ để hiển thị lộ trình. - Nút "Tạo báo cáo" mở hộp thoại lưới 3 cột liệt kê đủ 6 loại: loại tạo   được xếp trước, 5 loại kia ghi "Sắp ra mắt" và không bấm được.  Quyền - Cấp report.* cho director_officer. Trước đó Giám đốc điều hành KHÔNG có   quyền report nào nên bị router chặn ngay ở /manager/reports. Kèm theo đó   giám đốc cũng sửa/xoá được báo cáo mọi phòng ban vì canManage() chỉ xét   report.* — đây là chủ ý, đã ghi rõ trong docs/modules/Report.md §4.  Tổng hợp đánh giá - Bố cục hai vùng "chọn nhân sự mới hiện": cột trái 22rem chỉ để chọn   người, toàn bộ chỗ còn lại là khu chấm điểm của đúng người đang chọn.   Chưa chọn ai thì khu đó hiện số tổng quan phòng ban thay vì bỏ trống.   Không theo mẫu data-table: đây là bàn làm việc từng người, không phải   danh sách tra cứu. - Chênh lệch so với trung bình phòng và % hoàn thành việc chuyển từ mỗi   dòng vào thẻ phân rã điểm — dòng gọn lại mà không mất thông tin. - delete() bỏ chặn "chỉ xoá bản chờ duyệt": trưởng phòng ghi nhận là duyệt   luôn nên ràng buộc cũ làm nút xoá không bao giờ chạy được. - event_breakdown trả thêm task_id để thẻ Ghi nhận hiện được việc liên quan.  Kiểm chứng: vite build pass; php artisan test --filter="Evaluation\|Report" 84 passed. Toàn suite 237 passed / 17 failed — 17 lỗi này có sẵn từ trước ở Social, WorkspaceConfig sidebar và PermissionMatrix (đối chứng bằng stash: trước 18 fail, sau 17 fail, chênh 1 là test mới thêm).  Co-Authored-By: Claude Opus 5 <noreply@anthropic.com> |
| **Vì sao** | vì canManage() chỉ xét report vì bỏ trống để thẻ Ghi nhận hiện được việc liên quan |
| **Tác dụng** | thẻ Ghi nhận hiện được việc liên quan |
| **Giải thích đơn giản** | 6 loại báo cáo + thiết kế lại màn Tổng hợp đánh giá. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Evaluation/EvaluationEventService.php, Evaluation/EvaluationScoreComputeService.php, Evaluation/EvaluationSummary.vue, Report/Report.php (+6). |

