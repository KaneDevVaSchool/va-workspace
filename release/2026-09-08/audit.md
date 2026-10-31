# Đối soát 08/09/2026

Đọc nhanh: [README.md](./README.md) · Tuần: [2026-W37](../summary/2026-W37-summary.md) · Bảng tuần: [2026-W37-audit](../summary/2026-W37-audit.md)

Việc trong ngày đánh số version **1.1**, **1.2**, … Mỗi việc một bảng Đổi gì / Vì sao / Tác dụng. Thiếu trong commit thì ghi «chưa ghi», không suy diễn.

## Việc trong ngày

### 1.1. Liên kết TaskScore.is_passed vào tính điểm chất lượng

Đánh giá · Tiêu chí đánh giá · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Liên kết TaskScore.is_passed vào tính điểm chất lượng. is_passed (Đạt/Không đạt tường minh, chấm ở TaskDetail) nay là nguồn sự thật ưu tiên trong qualityFactor()/isExcellentQuality() thay vì chỉ so khớp text rating_result. Thêm endpoint GET score-kit/quality-levels (chỉ đọc, không cần evaluation.manage_department) để form chấm điểm việc ở Project chọn đúng theo thang đã cấu hình.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | vì chỉ so khớp text rating_result để form chấm điểm việc ở Project chọn đúng theo thang đã cấu hình |
| **Tác dụng** | form chấm điểm việc ở Project chọn đúng theo thang đã cấu hình |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 13:37 | `e905aea` | Thêm | Evaluation/EvaluationScoreKitController.php, Evaluation/EvaluationScoreComputeService.php, Evaluation/EvaluationSummary.vue, Evaluation/manager.php |

### 1.2. Backend cho thảo luận, folder tài liệu, bulk task, báo cáo hoàn thành

Dự án và công việc · Công việc và dự án · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Backend cho thảo luận, folder tài liệu, bulk task, báo cáo hoàn thành. - Comment (Task/Project, polymorphic): model + repository + service +   controller + request cho tạo/xoá/reaction/ghim/đánh dấu đã đọc, có   sanitize nội dung (CommentSanitizer). - ProjectFolder: cấu trúc thư mục tài liệu dự án, gắn vào ProjectAttachment   (folder_id), sửa/thay thế file đính kèm. - Bulk tạo task + đồng bộ cấu trúc category/phase (StoreBulkTasksRequest,   SyncProjectStructureRequest, TaskController::storeBulk/syncStructure). - Báo cáo hoàn thành (assignee-only): report_complete_action thay cho   auto_complete_on_report, enforce qua TaskService::reportComplete(). - TaskScore.is_passed (Đạt/Không đạt tường minh) — cột mới dùng chung với   Evaluation, chấm ở TaskDetail. - Migrations tương ứng + test Feature cho cấu trúc dự án và bulk task.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi bảng công việc; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 13:37 | `649d55e` | Thêm | Project/TaskEnums.php, Project/CommentController.php, Project/ProjectController.php, Project/TaskAttachmentController.php (+47) |

### 1.3. Trang Chi tiết dự án với tab Thảo luận, Tài liệu, Gantt, Công việc

Dự án và công việc · Công việc và dự án · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Trang Chi tiết dự án với tab Thảo luận, Tài liệu, Gantt, Công việc. - ProjectDetail.vue: trang độc lập thay panel trượt cũ trong ProjectList,   gồm 4 tab con — ProjectDiscussionTab (bình luận + reaction + ghim +   mention), ProjectDocumentsTab (folder + xem trước PDF/ảnh), ProjectGanttTab,   ProjectTasksTab (+ ProjectTaskViewModeMenu đổi chế độ xem). - Bộ component Comment (Composer/Editor/Item/List/EmojiPicker/   MentionPicker/ReactionPicker/ReactionList/ReactionBursts/ImageGrid/   ImageLightbox/UploadCards) dùng chung cho Task và Project, kèm tiện ích   trong resources/js/lib (sanitize, mention click, tiptap extension). - TaskDetail.vue: bổ sung khối chấm điểm liên kết Evaluation (is_passed,   thang chất lượng theo phòng ban), báo cáo hoàn thành, thảo luận việc. - TaskEdit.vue: trang sửa công việc độc lập (route mới   manager.project.tasks.edit). - FilePreviewDialog dùng chung để xem trước file đính kèm.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | để xem trước file đính kèm |
| **Tác dụng** | xem trước file đính kèm |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 13:37 | `ff7ad64` | Thêm | Project/CommentComposer.vue, Project/CommentEditor.vue, Project/CommentEmojiPicker.vue, Project/CommentImageGrid.vue (+36) |

### 1.4. Hạ tầng xem trước PDF + icon mới + fix overflow TaskDetail

Khác · Công việc và dự án · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Hạ tầng xem trước PDF + icon mới + fix overflow TaskDetail. - pdfjs-dist: route /pdfjs-worker.mjs (routes/web.php), copy worker qua   vite plugin, mime .mjs trong .htaccess — phục vụ FilePreviewDialog xem   trước PDF ở tab Tài liệu/Thảo luận (Project). - AppIcon: thêm icon folder/folderPlus/pin/gantt cho UI Project mới. - known-issues.md: cập nhật ghi chú report_complete_action đã enforce. - Sửa overflow cột thảo luận trong TaskDetail.vue (z-index/overflow:   visible để menu reaction/mention không bị cắt).  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | để menu reaction/mention không bị cắt) |
| **Tác dụng** | menu reaction/mention không bị cắt) |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 13:38 | `04fc58d` | Cập nhật | .claude/settings.json, .gitignore, Project/TaskDetail.vue, docs/known-issues.md (+6) |

