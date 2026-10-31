# Đối soát 26/08/2026

Đọc nhanh: [README.md](./README.md) · Tuần: [2026-W35](../summary/2026-W35-summary.md) · Bảng tuần: [2026-W35-audit](../summary/2026-W35-audit.md)

Việc trong ngày đánh số **1.1**, **1.2**, … Mỗi lần lưu (commit) là **1.1.1**, **1.1.2**, … Bảng có thêm dòng **Giải thích đơn giản** cho người không rành kỹ thuật. Thiếu trong commit thì ghi «chưa ghi», không suy diễn.

## Việc trong ngày

### 1.1. Định dạng lại modal tiêu chí đánh giá

Đánh giá · Tiêu chí đánh giá · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Định dạng lại modal tiêu chí đánh giá. Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Định dạng lại modal tiêu chí đánh giá. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Evaluation/WorkspaceConfigEvaluation.vue. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 08:30 | `a32efde` | Cập nhật | Evaluation/WorkspaceConfigEvaluation.vue |

#### Chi tiết từng lần lưu (version)

##### Version 1.1.1 — mã `a32efde` · 08:30

| | |
| --- | --- |
| **Đổi gì** | Định dạng lại modal tiêu chí đánh giá. Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Định dạng lại modal tiêu chí đánh giá. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Evaluation/WorkspaceConfigEvaluation.vue. |

### 1.2. Lượt xem bài viết và giới hạn hiển thị theo phòng ban

Bảng tin · Bảng tin · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Lượt xem bài viết và giới hạn hiển thị theo phòng ban. - Thêm lượt xem duy nhất trên bài đăng (social_post_views), tự ghi nhận   khi bài cuộn vào viewport trên bảng tin, không tính lượt xem của tác giả. - Thêm giới hạn hiển thị bài trên bảng tin chung theo phòng ban   (department_visibility_mode: all/include/exclude), chọn qua modal   SocialDepartmentVisibilityPicker khi đăng bài. - Sửa bug: response tạo bài thiếu department_visibility do relation chưa   reload sau khi ghi danh sách phòng ban (SocialPostService::create). - Sửa bug: người dùng chưa thuộc phòng ban nào bị ẩn nhầm bài kiểu 'trừ   phòng ban X', dù không nằm trong danh sách bị trừ nên phải thấy được   (SocialPostRepository::applyDepartmentVisibility). - Thêm test SocialPostViewTest, SocialPostDepartmentVisibilityTest — đã   chạy pass trên DB test riêng (va_workspace_db_test), cùng toàn bộ suite   119 test không hồi quy. - Bổ sung docs/modules/Social.md — module Social trước đó chưa có tài   liệu dù đã chạy đầy đủ trong repo; cập nhật VA_WORKSPACE_OVERVIEW.md   và docs/README.md để phản ánh đúng trạng thái.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | do relation chưa reload sau khi ghi danh sách phòng ban (SocialPostService::create) để phản ánh đúng trạng thái |
| **Tác dụng** | phản ánh đúng trạng thái; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Lượt xem bài viết và giới hạn hiển thị theo phòng ban. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu). File chính: Social/SocialPostController.php, Social/StoreSocialPostRequest.php, Social/SocialPost.php, Social/SocialPostDepartmentVisibility.php (+16). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 08:31 | `73b45a3` | Thêm | Social/SocialPostController.php, Social/StoreSocialPostRequest.php, Social/SocialPost.php, Social/SocialPostDepartmentVisibility.php (+16) |

#### Chi tiết từng lần lưu (version)

##### Version 1.2.1 — mã `73b45a3` · 08:31

| | |
| --- | --- |
| **Đổi gì** | Lượt xem bài viết và giới hạn hiển thị theo phòng ban. - Thêm lượt xem duy nhất trên bài đăng (social_post_views), tự ghi nhận   khi bài cuộn vào viewport trên bảng tin, không tính lượt xem của tác giả. - Thêm giới hạn hiển thị bài trên bảng tin chung theo phòng ban   (department_visibility_mode: all/include/exclude), chọn qua modal   SocialDepartmentVisibilityPicker khi đăng bài. - Sửa bug: response tạo bài thiếu department_visibility do relation chưa   reload sau khi ghi danh sách phòng ban (SocialPostService::create). - Sửa bug: người dùng chưa thuộc phòng ban nào bị ẩn nhầm bài kiểu 'trừ   phòng ban X', dù không nằm trong danh sách bị trừ nên phải thấy được   (SocialPostRepository::applyDepartmentVisibility). - Thêm test SocialPostViewTest, SocialPostDepartmentVisibilityTest — đã   chạy pass trên DB test riêng (va_workspace_db_test), cùng toàn bộ suite   119 test không hồi quy. - Bổ sung docs/modules/Social.md — module Social trước đó chưa có tài   liệu dù đã chạy đầy đủ trong repo; cập nhật VA_WORKSPACE_OVERVIEW.md   và docs/README.md để phản ánh đúng trạng thái.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | do relation chưa reload sau khi ghi danh sách phòng ban (SocialPostService::create) để phản ánh đúng trạng thái |
| **Tác dụng** | phản ánh đúng trạng thái; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Lượt xem bài viết và giới hạn hiển thị theo phòng ban. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu). File chính: Social/SocialPostController.php, Social/StoreSocialPostRequest.php, Social/SocialPost.php, Social/SocialPostDepartmentVisibility.php (+16). |

### 1.3. Tiêu chí ĐGNL, lịch sử và hub phòng ban

Đánh giá · Quyền và nhật ký, Tiêu chí đánh giá, Cấu hình menu · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Tiêu chí ĐGNL, lịch sử và hub phòng ban. Thêm use_in_evaluation, updated_by, API lịch sử tiêu chí; tách hub/members/sidebar/evaluation superadmin; UserAvatarTip và test lịch sử. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Tiêu chí ĐGNL, lịch sử và hub phòng ban. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu). File chính: Evaluation/EvaluationCriteriaController.php, Evaluation/StoreEvaluationCriteriaRequest.php, Evaluation/UpdateEvaluationCriteriaRequest.php, Evaluation/EvaluationCriteria.php (+24). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 11:32 | `dda67ff` | Thêm | Evaluation/EvaluationCriteriaController.php, Evaluation/StoreEvaluationCriteriaRequest.php, Evaluation/UpdateEvaluationCriteriaRequest.php, Evaluation/EvaluationCriteria.php (+24) |

#### Chi tiết từng lần lưu (version)

##### Version 1.3.1 — mã `dda67ff` · 11:32

| | |
| --- | --- |
| **Đổi gì** | Tiêu chí ĐGNL, lịch sử và hub phòng ban. Thêm use_in_evaluation, updated_by, API lịch sử tiêu chí; tách hub/members/sidebar/evaluation superadmin; UserAvatarTip và test lịch sử. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Tiêu chí ĐGNL, lịch sử và hub phòng ban. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu). File chính: Evaluation/EvaluationCriteriaController.php, Evaluation/StoreEvaluationCriteriaRequest.php, Evaluation/UpdateEvaluationCriteriaRequest.php, Evaluation/EvaluationCriteria.php (+24). |

### 1.4. Mẫu đánh giá, vị trí đánh giá và import/export Excel

Đánh giá · Tiêu chí đánh giá, Cấu hình menu · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Mẫu đánh giá, vị trí đánh giá và import/export Excel. Giai đoạn C của module Evaluation: - Mẫu đánh giá (EvaluationTemplate) gộp nhiều tiêu chí có trọng số, gán   cho Vị trí đánh giá (EvaluationPosition), có thể đánh dấu dùng chung   toàn hệ thống (quyền evaluation.manage_global_template). - Trường tùy biến cho mẫu đánh giá (EvaluationTemplateCustomField). - Import/Export Excel cho tiêu chí đánh giá, Export Excel/PDF cho mẫu   đánh giá (barryvdh/laravel-dompdf). - Mục sidebar riêng "Mẫu đánh giá" (khác tab Tiêu chí đánh giá trong Hub),   route/permission riêng theo evaluation.manage_department. - Nút xuất dữ liệu trong header WorkspaceConfigHub (export-options). - Cập nhật docs/VA_WORKSPACE_OVERVIEW.md, docs/modules/Evaluation.md,   kế hoạch plans/2026-08-26-mau-danh-gia.md.  🤖 Generated with [Claude Code](https://claude.com/claude-code)  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi bảng mẫu đánh giá; Đổi cấu trúc dữ liệu; Đổi bảng mẫu đánh giá; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Mẫu đánh giá, vị trí đánh giá và import/export Excel. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi bảng mẫu đánh giá; Đổi cấu trúc dữ liệu; Đổi bảng mẫu đánh giá; Đổi cấu trúc dữ liệu). File chính: Evaluation/EvaluationCriteriaController.php, Evaluation/EvaluationPositionController.php, Evaluation/EvaluationTemplateController.php, Evaluation/ConfirmImportEvaluationCriteriaRequest.php (+57). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 16:55 | `2a11d10` | Thêm | Evaluation/EvaluationCriteriaController.php, Evaluation/EvaluationPositionController.php, Evaluation/EvaluationTemplateController.php, Evaluation/ConfirmImportEvaluationCriteriaRequest.php (+57) |

#### Chi tiết từng lần lưu (version)

##### Version 1.4.1 — mã `2a11d10` · 16:55

| | |
| --- | --- |
| **Đổi gì** | Mẫu đánh giá, vị trí đánh giá và import/export Excel. Giai đoạn C của module Evaluation: - Mẫu đánh giá (EvaluationTemplate) gộp nhiều tiêu chí có trọng số, gán   cho Vị trí đánh giá (EvaluationPosition), có thể đánh dấu dùng chung   toàn hệ thống (quyền evaluation.manage_global_template). - Trường tùy biến cho mẫu đánh giá (EvaluationTemplateCustomField). - Import/Export Excel cho tiêu chí đánh giá, Export Excel/PDF cho mẫu   đánh giá (barryvdh/laravel-dompdf). - Mục sidebar riêng "Mẫu đánh giá" (khác tab Tiêu chí đánh giá trong Hub),   route/permission riêng theo evaluation.manage_department. - Nút xuất dữ liệu trong header WorkspaceConfigHub (export-options). - Cập nhật docs/VA_WORKSPACE_OVERVIEW.md, docs/modules/Evaluation.md,   kế hoạch plans/2026-08-26-mau-danh-gia.md.  🤖 Generated with [Claude Code](https://claude.com/claude-code)  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi bảng mẫu đánh giá; Đổi cấu trúc dữ liệu; Đổi bảng mẫu đánh giá; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Mẫu đánh giá, vị trí đánh giá và import/export Excel. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi bảng mẫu đánh giá; Đổi cấu trúc dữ liệu; Đổi bảng mẫu đánh giá; Đổi cấu trúc dữ liệu). File chính: Evaluation/EvaluationCriteriaController.php, Evaluation/EvaluationPositionController.php, Evaluation/EvaluationTemplateController.php, Evaluation/ConfirmImportEvaluationCriteriaRequest.php (+57). |

### 1.5. Hashtag trong bài viết

Bảng tin · Nhóm, Bảng tin · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Hashtag trong bài viết. - Tự nhận diện #hashtag khi soạn bài (linkify, click lọc theo hashtag). - Model/Service/Controller SocialHashtag, migration bảng hashtag. - SocialHashtagPanel (danh sách hashtag nổi bật) và SocialHashtagPicker   (gợi ý khi gõ #). - Icon "hash" mới trong AppIcon. - Sửa nhỏ SocialEmojiPicker/socialStickers đi kèm. - Test Feature/Unit cho hashtag.  🤖 Generated with [Claude Code](https://claude.com/claude-code)  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Hashtag trong bài viết. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu). File chính: Social/SocialHashtagController.php, Social/SocialPostController.php, Social/SocialHashtag.php, Social/SocialPost.php (+25). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 16:55 | `90842d3` | Thêm | Social/SocialHashtagController.php, Social/SocialPostController.php, Social/SocialHashtag.php, Social/SocialPost.php (+25) |

#### Chi tiết từng lần lưu (version)

##### Version 1.5.1 — mã `90842d3` · 16:55

| | |
| --- | --- |
| **Đổi gì** | Hashtag trong bài viết. - Tự nhận diện #hashtag khi soạn bài (linkify, click lọc theo hashtag). - Model/Service/Controller SocialHashtag, migration bảng hashtag. - SocialHashtagPanel (danh sách hashtag nổi bật) và SocialHashtagPicker   (gợi ý khi gõ #). - Icon "hash" mới trong AppIcon. - Sửa nhỏ SocialEmojiPicker/socialStickers đi kèm. - Test Feature/Unit cho hashtag.  🤖 Generated with [Claude Code](https://claude.com/claude-code)  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Hashtag trong bài viết. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu). File chính: Social/SocialHashtagController.php, Social/SocialPostController.php, Social/SocialHashtag.php, Social/SocialPost.php (+25). |

### 1.6. Hiện avatar + phòng ban trong nhật ký hoạt động

Định danh và phân quyền · Quyền và nhật ký · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Hiện avatar + phòng ban trong nhật ký hoạt động. - ActivityLogRepository eager-load actor.department, tránh N+1. - Hiện avatar (ảnh hoặc chữ cái đầu tên) cho từng dòng hoạt động ở   ActivityLog.vue và dropdown HeaderActivityLog.vue.  🤖 Generated with [Claude Code](https://claude.com/claude-code)  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Hiện avatar + phòng ban trong nhật ký hoạt động. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Identity/ActivityLogRepository.php, Identity/ActivityLogService.php, Identity/ActivityLog.vue, components/HeaderActivityLog.vue. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 16:55 | `e324868` | Thêm | Identity/ActivityLogRepository.php, Identity/ActivityLogService.php, Identity/ActivityLog.vue, components/HeaderActivityLog.vue |

#### Chi tiết từng lần lưu (version)

##### Version 1.6.1 — mã `e324868` · 16:55

| | |
| --- | --- |
| **Đổi gì** | Hiện avatar + phòng ban trong nhật ký hoạt động. - ActivityLogRepository eager-load actor.department, tránh N+1. - Hiện avatar (ảnh hoặc chữ cái đầu tên) cho từng dòng hoạt động ở   ActivityLog.vue và dropdown HeaderActivityLog.vue.  🤖 Generated with [Claude Code](https://claude.com/claude-code)  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Hiện avatar + phòng ban trong nhật ký hoạt động. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Identity/ActivityLogRepository.php, Identity/ActivityLogService.php, Identity/ActivityLog.vue, components/HeaderActivityLog.vue. |

### 1.7. Thêm màu phụ "Nhân Ái" #009082

Giao diện gốc · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Thêm màu phụ "Nhân Ái" #009082. Token --color-secondary-* (50–900) trong theme.css, phối cùng primary đỏ. Chưa gán dùng ở component nào, chuẩn bị cho các UI cần màu phụ.  🤖 Generated with [Claude Code](https://claude.com/claude-code)  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Thêm màu phụ "Nhân Ái" #009082. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: css/theme.css. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 16:56 | `4d2525b` | Cập nhật | css/theme.css |

#### Chi tiết từng lần lưu (version)

##### Version 1.7.1 — mã `4d2525b` · 16:56

| | |
| --- | --- |
| **Đổi gì** | Thêm màu phụ "Nhân Ái" #009082. Token --color-secondary-* (50–900) trong theme.css, phối cùng primary đỏ. Chưa gán dùng ở component nào, chuẩn bị cho các UI cần màu phụ.  🤖 Generated with [Claude Code](https://claude.com/claude-code)  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Thêm màu phụ "Nhân Ái" #009082. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: css/theme.css. |

### 1.8. Tuỳ chỉnh menu sidebar theo phòng ban

Cấu hình workspace · Bảng tin, Quyền và nhật ký, Tiêu chí đánh giá, Cấu hình đơn từ, Cấu hình menu · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Tuỳ chỉnh menu sidebar theo phòng ban. - Thêm SidebarMenuConfigPanel + sidebarMenus.js để cấu hình hiển thị/nhãn menu theo phòng ban - Thêm cột custom_label cho department_sidebar_configs (migration + model + repository) - Cập nhật WorkspaceConfigSidebarController/Service, UpdateSidebarVisibilityRequest cho tính năng mới - Thêm SocialHistoryDialog, useDragScroll composable dùng chung cho kéo cuộn ngang - Điều chỉnh AppHeader/AppLayout/AppSidebar, các trang WorkspaceConfig*, PermissionMatrix, ActivityLog theo data-table/form-modal mẫu vàng - Cập nhật skill data-table (SKILL.md, reference.md, rule .mdc) - Thêm test WorkspaceConfigSidebarTest  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | để cấu hình hiển thị/nhãn menu theo phòng ban - Thêm cột custom_label cho department_sidebar_configs (migration + model + repository) - Cập nhật WorkspaceConfigSidebarController/Service, UpdateSidebarVisibilityRequest cho tính năng mới - Thêm SocialHistoryDialog, useDragScroll composable dùng chung cho kéo cuộn ngang - Điều chỉnh AppHeader/AppLayout/AppSidebar, các trang WorkspaceConfig*, PermissionMatrix, ActivityLog theo data-table/form-modal mẫu vàng - Cập nhật skill data-table (SKILL |
| **Tác dụng** | cấu hình hiển thị/nhãn menu theo phòng ban - Thêm cột custom_label cho department_sidebar_configs (migration + model + repository) - Cập nhật WorkspaceConfigSidebarController/Service, UpdateSidebarVisibilityRequest cho tính năng mới - Thêm SocialHistoryDialog, useDragScroll composable dùng chung cho kéo cuộn ngang - Điều chỉnh AppHeader/AppLayout/AppSidebar, các trang WorkspaceConfig*, PermissionMatrix, ActivityLog theo data-table/form-modal mẫu vàng - Cập nhật skill data-table (SKILL.md, reference.md, rule .mdc) - Thêm test WorkspaceConfigSidebarTest Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Tuỳ chỉnh menu sidebar theo phòng ban. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu). File chính: data-table/SKILL.md, rules/data-table.mdc, data-table/SKILL.md, data-table/reference.md (+33). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 19:18 | `440e799` | Thêm | data-table/SKILL.md, rules/data-table.mdc, data-table/SKILL.md, data-table/reference.md (+33) |

#### Chi tiết từng lần lưu (version)

##### Version 1.8.1 — mã `440e799` · 19:18

| | |
| --- | --- |
| **Đổi gì** | Tuỳ chỉnh menu sidebar theo phòng ban. - Thêm SidebarMenuConfigPanel + sidebarMenus.js để cấu hình hiển thị/nhãn menu theo phòng ban - Thêm cột custom_label cho department_sidebar_configs (migration + model + repository) - Cập nhật WorkspaceConfigSidebarController/Service, UpdateSidebarVisibilityRequest cho tính năng mới - Thêm SocialHistoryDialog, useDragScroll composable dùng chung cho kéo cuộn ngang - Điều chỉnh AppHeader/AppLayout/AppSidebar, các trang WorkspaceConfig*, PermissionMatrix, ActivityLog theo data-table/form-modal mẫu vàng - Cập nhật skill data-table (SKILL.md, reference.md, rule .mdc) - Thêm test WorkspaceConfigSidebarTest  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | để cấu hình hiển thị/nhãn menu theo phòng ban - Thêm cột custom_label cho department_sidebar_configs (migration + model + repository) - Cập nhật WorkspaceConfigSidebarController/Service, UpdateSidebarVisibilityRequest cho tính năng mới - Thêm SocialHistoryDialog, useDragScroll composable dùng chung cho kéo cuộn ngang - Điều chỉnh AppHeader/AppLayout/AppSidebar, các trang WorkspaceConfig*, PermissionMatrix, ActivityLog theo data-table/form-modal mẫu vàng - Cập nhật skill data-table (SKILL |
| **Tác dụng** | cấu hình hiển thị/nhãn menu theo phòng ban - Thêm cột custom_label cho department_sidebar_configs (migration + model + repository) - Cập nhật WorkspaceConfigSidebarController/Service, UpdateSidebarVisibilityRequest cho tính năng mới - Thêm SocialHistoryDialog, useDragScroll composable dùng chung cho kéo cuộn ngang - Điều chỉnh AppHeader/AppLayout/AppSidebar, các trang WorkspaceConfig*, PermissionMatrix, ActivityLog theo data-table/form-modal mẫu vàng - Cập nhật skill data-table (SKILL.md, reference.md, rule .mdc) - Thêm test WorkspaceConfigSidebarTest Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Tuỳ chỉnh menu sidebar theo phòng ban. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu). File chính: data-table/SKILL.md, rules/data-table.mdc, data-table/SKILL.md, data-table/reference.md (+33). |

### 1.9. Panel chi tiết 28rem + mẫu thao tác dòng gộp 1 dropdown

Tài liệu · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Panel chi tiết 28rem + mẫu thao tác dòng gộp 1 dropdown. - Chuẩn hoá width panel chi tiết đẩy ngang: 22-25rem -> 28rem - Thêm quy tắc mới: cột thao tác cuối dòng gộp vào 1 nút dropdown (icon   more_vert) thay vì nhiều icon-button rời, kể cả khi chỉ có 1 hành động  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | vì nhiều icon-button rời, kể cả khi chỉ có 1 hành động Co-Authored-By: Claude Sonnet 5 <noreply@anthropic |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Panel chi tiết 28rem + mẫu thao tác dòng gộp 1 dropdown. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: data-table/SKILL.md, rules/data-table.mdc, data-table/SKILL.md, data-table/reference.md. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 20:25 | `45c9633` | Tài liệu | data-table/SKILL.md, rules/data-table.mdc, data-table/SKILL.md, data-table/reference.md |

#### Chi tiết từng lần lưu (version)

##### Version 1.9.1 — mã `45c9633` · 20:25

| | |
| --- | --- |
| **Đổi gì** | Panel chi tiết 28rem + mẫu thao tác dòng gộp 1 dropdown. - Chuẩn hoá width panel chi tiết đẩy ngang: 22-25rem -> 28rem - Thêm quy tắc mới: cột thao tác cuối dòng gộp vào 1 nút dropdown (icon   more_vert) thay vì nhiều icon-button rời, kể cả khi chỉ có 1 hành động  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | vì nhiều icon-button rời, kể cả khi chỉ có 1 hành động Co-Authored-By: Claude Sonnet 5 <noreply@anthropic |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Panel chi tiết 28rem + mẫu thao tác dòng gộp 1 dropdown. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: data-table/SKILL.md, rules/data-table.mdc, data-table/SKILL.md, data-table/reference.md. |

### 1.10. Áp width 28rem cho panel chi tiết theo mẫu vàng mới

Cấu hình workspace · Quyền và nhật ký, Cấu hình menu · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Áp width 28rem cho panel chi tiết theo mẫu vàng mới. Đồng bộ width panel chi tiết đẩy ngang ở các trang: Nhật ký hoạt động, Ma trận quyền, tiêu chí đánh giá theo phòng ban, thành viên/tổng quan phòng ban — theo chuẩn 28rem cập nhật trong skill data-table.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Áp width 28rem cho panel chi tiết theo mẫu vàng mới. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Evaluation/WorkspaceConfigDepartmentEvaluationSuperadmin.vue, Identity/ActivityLog.vue, Identity/PermissionMatrix.vue, WorkspaceConfig/WorkspaceConfigDepartmentMembersSuperadmin.vue (+2). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 20:25 | `2392bb5` | Cập nhật | Evaluation/WorkspaceConfigDepartmentEvaluationSuperadmin.vue, Identity/ActivityLog.vue, Identity/PermissionMatrix.vue, WorkspaceConfig/WorkspaceConfigDepartmentMembersSuperadmin.vue (+2) |

#### Chi tiết từng lần lưu (version)

##### Version 1.10.1 — mã `2392bb5` · 20:25

| | |
| --- | --- |
| **Đổi gì** | Áp width 28rem cho panel chi tiết theo mẫu vàng mới. Đồng bộ width panel chi tiết đẩy ngang ở các trang: Nhật ký hoạt động, Ma trận quyền, tiêu chí đánh giá theo phòng ban, thành viên/tổng quan phòng ban — theo chuẩn 28rem cập nhật trong skill data-table.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Áp width 28rem cho panel chi tiết theo mẫu vàng mới. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Evaluation/WorkspaceConfigDepartmentEvaluationSuperadmin.vue, Identity/ActivityLog.vue, Identity/PermissionMatrix.vue, WorkspaceConfig/WorkspaceConfigDepartmentMembersSuperadmin.vue (+2). |

### 1.11. Chặn trùng tên tiêu chí đánh giá trong cùng phòng ban

Đánh giá · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Chặn trùng tên tiêu chí đánh giá trong cùng phòng ban. - Repository: existsNameInDepartment() so khớp không phân biệt hoa/thường   và khoảng trắng thừa (LOWER+TRIM), loại trừ chính bản ghi đang sửa - StoreEvaluationCriteriaRequest / UpdateEvaluationCriteriaRequest: validate   name qua rule closure, báo lỗi tiếng Việt khi trùng - Migration: unique constraint theo (department_id, name) ở tầng DB  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Chặn trùng tên tiêu chí đánh giá trong cùng phòng ban. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu). File chính: Evaluation/StoreEvaluationCriteriaRequest.php, Evaluation/UpdateEvaluationCriteriaRequest.php, Evaluation/EvaluationCriteriaRepositoryInterface.php, Evaluation/EvaluationCriteriaRepository.php (+1). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 20:26 | `8bbd73f` | Thêm | Evaluation/StoreEvaluationCriteriaRequest.php, Evaluation/UpdateEvaluationCriteriaRequest.php, Evaluation/EvaluationCriteriaRepositoryInterface.php, Evaluation/EvaluationCriteriaRepository.php (+1) |

#### Chi tiết từng lần lưu (version)

##### Version 1.11.1 — mã `8bbd73f` · 20:26

| | |
| --- | --- |
| **Đổi gì** | Chặn trùng tên tiêu chí đánh giá trong cùng phòng ban. - Repository: existsNameInDepartment() so khớp không phân biệt hoa/thường   và khoảng trắng thừa (LOWER+TRIM), loại trừ chính bản ghi đang sửa - StoreEvaluationCriteriaRequest / UpdateEvaluationCriteriaRequest: validate   name qua rule closure, báo lỗi tiếng Việt khi trùng - Migration: unique constraint theo (department_id, name) ở tầng DB  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Chặn trùng tên tiêu chí đánh giá trong cùng phòng ban. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu). File chính: Evaluation/StoreEvaluationCriteriaRequest.php, Evaluation/UpdateEvaluationCriteriaRequest.php, Evaluation/EvaluationCriteriaRepositoryInterface.php, Evaluation/EvaluationCriteriaRepository.php (+1). |

### 1.12. Gộp thao tác theo dòng vào 1 dropdown (mẫu vàng data-table)

Đánh giá · Tiêu chí đánh giá · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Gộp thao tác theo dòng vào 1 dropdown (mẫu vàng data-table). Áp mẫu 'thao tác theo dòng = 1 nút dropdown' (data-table.mdc mục 6) cho 2 trang: Mẫu đánh giá (EvaluationTemplateList) và Tiêu chí đánh giá (WorkspaceConfigEvaluation) — thay các icon-button rời (sửa/nhân bản/ ẩn-hiện/xoá) bằng 1 nút ⋮ mở menu, Teleport ra body để không bị cắt khi bảng cuộn.  Thêm icon dùng chung: moreVertical (trigger dropdown), gripVertical, userX (dùng ở các commit tiếp theo).  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | để không bị cắt khi bảng cuộn |
| **Tác dụng** | không bị cắt khi bảng cuộn |
| **Giải thích đơn giản** | Gộp thao tác theo dòng vào 1 dropdown (mẫu vàng data-table). Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Evaluation/EvaluationTemplateList.vue, Evaluation/WorkspaceConfigEvaluation.vue, components/AppIcon.vue. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 20:26 | `3541f0b` | Thêm | Evaluation/EvaluationTemplateList.vue, Evaluation/WorkspaceConfigEvaluation.vue, components/AppIcon.vue |

#### Chi tiết từng lần lưu (version)

##### Version 1.12.1 — mã `3541f0b` · 20:26

| | |
| --- | --- |
| **Đổi gì** | Gộp thao tác theo dòng vào 1 dropdown (mẫu vàng data-table). Áp mẫu 'thao tác theo dòng = 1 nút dropdown' (data-table.mdc mục 6) cho 2 trang: Mẫu đánh giá (EvaluationTemplateList) và Tiêu chí đánh giá (WorkspaceConfigEvaluation) — thay các icon-button rời (sửa/nhân bản/ ẩn-hiện/xoá) bằng 1 nút ⋮ mở menu, Teleport ra body để không bị cắt khi bảng cuộn.  Thêm icon dùng chung: moreVertical (trigger dropdown), gripVertical, userX (dùng ở các commit tiếp theo).  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | để không bị cắt khi bảng cuộn |
| **Tác dụng** | không bị cắt khi bảng cuộn |
| **Giải thích đơn giản** | Gộp thao tác theo dòng vào 1 dropdown (mẫu vàng data-table). Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Evaluation/EvaluationTemplateList.vue, Evaluation/WorkspaceConfigEvaluation.vue, components/AppIcon.vue. |

### 1.13. Đăng ẩn danh + kiểm duyệt bài viết trước khi hiển thị công khai

Bảng tin · Bảng tin, Quyền và nhật ký · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Đăng ẩn danh + kiểm duyệt bài viết trước khi hiển thị công khai. - SocialPost: thêm is_anonymous/anonymous_name, review_status (pending/   approved/rejected) + reviewed_by/reviewed_at/review_reject_reason - Bài đăng công khai toàn trường mặc định 'pending', chỉ hiện trên bảng tin   khi được duyệt 'approved'; thông báo do quản trị đăng (system) tự duyệt - SocialPostModerationController + trang SocialModeration.vue: danh sách   chờ duyệt, duyệt/từ chối (kèm lý do từ chối) - Bài ẩn danh: ẩn author thật, hiện anonymous_name; permission mới   'social.review' cho quyền duyệt bài - PermissionService::resolveGrantedKeys(): duyệt qua allows() theo từng   permission key trong catalog thay vì gộp thẳng config theo role, để khớp   override DB (cần thiết để 'social.review' phản ánh đúng khi cấp/thu hồi   qua ma trận phân quyền) — kèm test - ActivityLogService: thêm nhãn nhật ký social_post.approve/reject  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | do quản trị đăng (system) tự duyệt - SocialPostModerationController + trang SocialModeration |
| **Tác dụng** | khớp override DB (cần thiết để 'social.review' phản ánh đúng khi cấp/thu hồi qua ma trận phân quyền) — kèm test - ActivityLogService: thêm nhãn nhật ký social_post.approve/reject Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Đăng ẩn danh + kiểm duyệt bài viết trước khi hiển thị công khai. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu). File chính: Identity/ActivityLogService.php, Identity/PermissionService.php, Social/SocialPostController.php, Social/SocialPostModerationController.php (+19). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 20:27 | `dab2dca` | Thêm | Identity/ActivityLogService.php, Identity/PermissionService.php, Social/SocialPostController.php, Social/SocialPostModerationController.php (+19) |

#### Chi tiết từng lần lưu (version)

##### Version 1.13.1 — mã `dab2dca` · 20:27

| | |
| --- | --- |
| **Đổi gì** | Đăng ẩn danh + kiểm duyệt bài viết trước khi hiển thị công khai. - SocialPost: thêm is_anonymous/anonymous_name, review_status (pending/   approved/rejected) + reviewed_by/reviewed_at/review_reject_reason - Bài đăng công khai toàn trường mặc định 'pending', chỉ hiện trên bảng tin   khi được duyệt 'approved'; thông báo do quản trị đăng (system) tự duyệt - SocialPostModerationController + trang SocialModeration.vue: danh sách   chờ duyệt, duyệt/từ chối (kèm lý do từ chối) - Bài ẩn danh: ẩn author thật, hiện anonymous_name; permission mới   'social.review' cho quyền duyệt bài - PermissionService::resolveGrantedKeys(): duyệt qua allows() theo từng   permission key trong catalog thay vì gộp thẳng config theo role, để khớp   override DB (cần thiết để 'social.review' phản ánh đúng khi cấp/thu hồi   qua ma trận phân quyền) — kèm test - ActivityLogService: thêm nhãn nhật ký social_post.approve/reject  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | do quản trị đăng (system) tự duyệt - SocialPostModerationController + trang SocialModeration |
| **Tác dụng** | khớp override DB (cần thiết để 'social.review' phản ánh đúng khi cấp/thu hồi qua ma trận phân quyền) — kèm test - ActivityLogService: thêm nhãn nhật ký social_post.approve/reject Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Đăng ẩn danh + kiểm duyệt bài viết trước khi hiển thị công khai. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu). File chính: Identity/ActivityLogService.php, Identity/PermissionService.php, Social/SocialPostController.php, Social/SocialPostModerationController.php (+19). |

### 1.14. Sắp xếp lại thứ tự và nhóm menu sidebar theo phòng ban

Cấu hình workspace · Cấu hình đơn từ, Cấu hình menu · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sắp xếp lại thứ tự và nhóm menu sidebar theo phòng ban. - DepartmentSidebarConfig: thêm sort_order + section_key (mục thuộc nhóm   menu trái nào, thứ tự trong nhóm) - Repository: sortOrdersForDepartment/itemSectionsForDepartment/   sectionLabelsForDepartment; upsert() nhận thêm updateLayout/sortOrder/   sectionKey - WorkspaceConfigSidebarController: 2 endpoint mới PUT layout (kéo thả đổi   thứ tự) và PUT section (đổi tên nhóm), qua ReorderSidebarLayoutRequest/   UpdateSidebarSectionRequest - SidebarMenuConfigPanel: kéo thả sắp xếp mục + đổi tên nhóm ngay trong   panel cấu hình (superadmin/manager) - AuthenticatedUserPresenter trả thêm menu_order/menu_item_sections/   menu_section_labels trong /api/me; auth.js cache + patch trực tiếp   (setMenuLayout/setSectionLabel) không cần gọi lại toàn bộ; AppSidebar   render theo thứ tự/nhóm tuỳ chỉnh của phòng ban  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Sắp xếp lại thứ tự và nhóm menu sidebar theo phòng ban. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu). File chính: Identity/DepartmentSidebarConfig.php, Identity/DepartmentSidebarConfigRepositoryInterface.php, Identity/DepartmentSidebarConfigRepository.php, Identity/AuthenticatedUserPresenter.php (+15). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 20:28 | `33c9c80` | Thêm | Identity/DepartmentSidebarConfig.php, Identity/DepartmentSidebarConfigRepositoryInterface.php, Identity/DepartmentSidebarConfigRepository.php, Identity/AuthenticatedUserPresenter.php (+15) |

#### Chi tiết từng lần lưu (version)

##### Version 1.14.1 — mã `33c9c80` · 20:28

| | |
| --- | --- |
| **Đổi gì** | Sắp xếp lại thứ tự và nhóm menu sidebar theo phòng ban. - DepartmentSidebarConfig: thêm sort_order + section_key (mục thuộc nhóm   menu trái nào, thứ tự trong nhóm) - Repository: sortOrdersForDepartment/itemSectionsForDepartment/   sectionLabelsForDepartment; upsert() nhận thêm updateLayout/sortOrder/   sectionKey - WorkspaceConfigSidebarController: 2 endpoint mới PUT layout (kéo thả đổi   thứ tự) và PUT section (đổi tên nhóm), qua ReorderSidebarLayoutRequest/   UpdateSidebarSectionRequest - SidebarMenuConfigPanel: kéo thả sắp xếp mục + đổi tên nhóm ngay trong   panel cấu hình (superadmin/manager) - AuthenticatedUserPresenter trả thêm menu_order/menu_item_sections/   menu_section_labels trong /api/me; auth.js cache + patch trực tiếp   (setMenuLayout/setSectionLabel) không cần gọi lại toàn bộ; AppSidebar   render theo thứ tự/nhóm tuỳ chỉnh của phòng ban  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Sắp xếp lại thứ tự và nhóm menu sidebar theo phòng ban. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu). File chính: Identity/DepartmentSidebarConfig.php, Identity/DepartmentSidebarConfigRepositoryInterface.php, Identity/DepartmentSidebarConfigRepository.php, Identity/AuthenticatedUserPresenter.php (+15). |

### 1.15. Áp width 28rem cho panel chi tiết ở trang xem đánh giá

Đánh giá · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Áp width 28rem cho panel chi tiết ở trang xem đánh giá. Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Áp width 28rem cho panel chi tiết ở trang xem đánh giá. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Evaluation/EvaluationView.vue. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 20:28 | `8353001` | Cập nhật | Evaluation/EvaluationView.vue |

#### Chi tiết từng lần lưu (version)

##### Version 1.15.1 — mã `8353001` · 20:28

| | |
| --- | --- |
| **Đổi gì** | Áp width 28rem cho panel chi tiết ở trang xem đánh giá. Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Áp width 28rem cho panel chi tiết ở trang xem đánh giá. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Evaluation/EvaluationView.vue. |

### 1.16. Nút hiện lại header căn giữa tuyệt đối, không lệch theo flex

Giao diện gốc · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Nút hiện lại header căn giữa tuyệt đối, không lệch theo flex. Đổi position: relative -> absolute + translateX(-50%) cho .app-layout__header-reveal để nút luôn căn giữa theo chiều ngang của khu vực nội dung, không bị ảnh hưởng bởi flex layout xung quanh.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | để nút luôn căn giữa theo chiều ngang của khu vực nội dung, không bị ảnh hưởng bởi flex layout xung quanh |
| **Tác dụng** | nút luôn căn giữa theo chiều ngang của khu vực nội dung, không bị ảnh hưởng bởi flex layout xung quanh |
| **Giải thích đơn giản** | Nút hiện lại header căn giữa tuyệt đối, không lệch theo flex. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: components/AppLayout.vue. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 20:28 | `f585899` | Sửa | components/AppLayout.vue |

#### Chi tiết từng lần lưu (version)

##### Version 1.16.1 — mã `f585899` · 20:28

| | |
| --- | --- |
| **Đổi gì** | Nút hiện lại header căn giữa tuyệt đối, không lệch theo flex. Đổi position: relative -> absolute + translateX(-50%) cho .app-layout__header-reveal để nút luôn căn giữa theo chiều ngang của khu vực nội dung, không bị ảnh hưởng bởi flex layout xung quanh.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | để nút luôn căn giữa theo chiều ngang của khu vực nội dung, không bị ảnh hưởng bởi flex layout xung quanh |
| **Tác dụng** | nút luôn căn giữa theo chiều ngang của khu vực nội dung, không bị ảnh hưởng bởi flex layout xung quanh |
| **Giải thích đơn giản** | Nút hiện lại header căn giữa tuyệt đối, không lệch theo flex. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: components/AppLayout.vue. |

### 1.17. Thêm trang 404 khi truy cập route không tồn tại

Giao diện gốc · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Thêm trang 404 khi truy cập route không tồn tại. Wildcard route (/:pathMatch(.*)*) render NotFound.vue thay vì màn trắng/ lỗi router khi gõ sai URL hoặc route đã bị xoá.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | vì màn trắng/ lỗi router khi gõ sai URL hoặc route đã bị xoá |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Thêm trang 404 khi truy cập route không tồn tại. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: brand/404.png, pages/NotFound.vue, router/index.js. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 20:28 | `866fe2d` | Thêm | brand/404.png, pages/NotFound.vue, router/index.js |

#### Chi tiết từng lần lưu (version)

##### Version 1.17.1 — mã `866fe2d` · 20:28

| | |
| --- | --- |
| **Đổi gì** | Thêm trang 404 khi truy cập route không tồn tại. Wildcard route (/:pathMatch(.*)*) render NotFound.vue thay vì màn trắng/ lỗi router khi gõ sai URL hoặc route đã bị xoá.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | vì màn trắng/ lỗi router khi gõ sai URL hoặc route đã bị xoá |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Thêm trang 404 khi truy cập route không tồn tại. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: brand/404.png, pages/NotFound.vue, router/index.js. |

### 1.18. Ẩn/hiện menu sidebar toàn hệ thống (superadmin)

Định danh và phân quyền · Bảng tin, Quyền và nhật ký, Cấu hình đơn từ, Cấu hình menu · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Ẩn/hiện menu sidebar toàn hệ thống (superadmin). - Thêm bảng va_workspace_global_menu_visibilities + model/repository   GlobalMenuVisibility để lưu danh sách menu key bị ẩn toàn hệ thống. - Service WorkspaceConfig\GlobalMenuVisibilityService + controller/request   quản lý bật/tắt ẩn menu, quyền workspace_config.manage_global_menu. - Middleware EnsureMenuNotGloballyHidden (menu.not_hidden:<key>) chặn   backend cho các route bị ẩn (activity-logs, social moderation,   workspace-config members/sidebar...). - Router SPA + store auth: chặn điều hướng client khi menu bị ẩn toàn   hệ thống (trừ super_admin), AppSidebar ẩn mục tương ứng. - Trang superadmin WorkspaceConfigGlobalMenuVisibilitySuperadmin.vue để   bật/tắt ẩn menu. - Test WorkspaceConfigGlobalMenuVisibilityTest + cập nhật   WorkspaceConfigSidebarTest. |
| **Vì sao** | để lưu danh sách menu key bị ẩn toàn hệ thống để bật/tắt ẩn menu |
| **Tác dụng** | lưu danh sách menu key bị ẩn toàn hệ thống; bật/tắt ẩn menu; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Ẩn/hiện menu sidebar toàn hệ thống (superadmin). Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu). File chính: Identity/GlobalMenuVisibility.php, Identity/IdentityServiceProvider.php, Identity/GlobalMenuVisibilityRepositoryInterface.php, Identity/GlobalMenuVisibilityRepository.php (+22). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 21:17 | `952f5b6` | Thêm | Identity/GlobalMenuVisibility.php, Identity/IdentityServiceProvider.php, Identity/GlobalMenuVisibilityRepositoryInterface.php, Identity/GlobalMenuVisibilityRepository.php (+22) |

#### Chi tiết từng lần lưu (version)

##### Version 1.18.1 — mã `952f5b6` · 21:17

| | |
| --- | --- |
| **Đổi gì** | Ẩn/hiện menu sidebar toàn hệ thống (superadmin). - Thêm bảng va_workspace_global_menu_visibilities + model/repository   GlobalMenuVisibility để lưu danh sách menu key bị ẩn toàn hệ thống. - Service WorkspaceConfig\GlobalMenuVisibilityService + controller/request   quản lý bật/tắt ẩn menu, quyền workspace_config.manage_global_menu. - Middleware EnsureMenuNotGloballyHidden (menu.not_hidden:<key>) chặn   backend cho các route bị ẩn (activity-logs, social moderation,   workspace-config members/sidebar...). - Router SPA + store auth: chặn điều hướng client khi menu bị ẩn toàn   hệ thống (trừ super_admin), AppSidebar ẩn mục tương ứng. - Trang superadmin WorkspaceConfigGlobalMenuVisibilitySuperadmin.vue để   bật/tắt ẩn menu. - Test WorkspaceConfigGlobalMenuVisibilityTest + cập nhật   WorkspaceConfigSidebarTest. |
| **Vì sao** | để lưu danh sách menu key bị ẩn toàn hệ thống để bật/tắt ẩn menu |
| **Tác dụng** | lưu danh sách menu key bị ẩn toàn hệ thống; bật/tắt ẩn menu; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Ẩn/hiện menu sidebar toàn hệ thống (superadmin). Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu). File chính: Identity/GlobalMenuVisibility.php, Identity/IdentityServiceProvider.php, Identity/GlobalMenuVisibilityRepositoryInterface.php, Identity/GlobalMenuVisibilityRepository.php (+22). |

### 1.19. Cho phép super_admin tạo mẫu đánh giá dùng chung toàn hệ thống

Đánh giá · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Cho phép super_admin tạo mẫu đánh giá dùng chung toàn hệ thống. - department_id trong evaluation_templates chuyển nullable — mẫu do   super_admin tạo không gắn phòng ban cụ thể, áp dụng toàn hệ thống. - EvaluationTemplateController/Service/Request xử lý luồng tạo mẫu   dùng chung + phân quyền super_admin bên cạnh department_director/deputy. - Seeder HrCnttEvaluationCriteriaSeeder: tiêu chí đánh giá mẫu cho   phòng Nhân sự và CNTT. - Frontend: EvaluationTemplateCreateDialog + EvaluationTemplateList   hỗ trợ hiển thị/tạo mẫu toàn hệ thống. - Test EvaluationTemplateSuperAdminTest. |
| **Vì sao** | do super_admin tạo không gắn phòng ban cụ thể, áp dụng toàn hệ thống |
| **Tác dụng** | Đổi bảng mẫu đánh giá |
| **Giải thích đơn giản** | Cho phép super_admin tạo mẫu đánh giá dùng chung toàn hệ thống. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi bảng mẫu đánh giá). File chính: Evaluation/EvaluationTemplateController.php, Evaluation/StoreEvaluationTemplateRequest.php, Evaluation/EvaluationTemplate.php, Evaluation/EvaluationTemplateService.php (+7). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 21:17 | `de35d87` | Thêm | Evaluation/EvaluationTemplateController.php, Evaluation/StoreEvaluationTemplateRequest.php, Evaluation/EvaluationTemplate.php, Evaluation/EvaluationTemplateService.php (+7) |

#### Chi tiết từng lần lưu (version)

##### Version 1.19.1 — mã `de35d87` · 21:17

| | |
| --- | --- |
| **Đổi gì** | Cho phép super_admin tạo mẫu đánh giá dùng chung toàn hệ thống. - department_id trong evaluation_templates chuyển nullable — mẫu do   super_admin tạo không gắn phòng ban cụ thể, áp dụng toàn hệ thống. - EvaluationTemplateController/Service/Request xử lý luồng tạo mẫu   dùng chung + phân quyền super_admin bên cạnh department_director/deputy. - Seeder HrCnttEvaluationCriteriaSeeder: tiêu chí đánh giá mẫu cho   phòng Nhân sự và CNTT. - Frontend: EvaluationTemplateCreateDialog + EvaluationTemplateList   hỗ trợ hiển thị/tạo mẫu toàn hệ thống. - Test EvaluationTemplateSuperAdminTest. |
| **Vì sao** | do super_admin tạo không gắn phòng ban cụ thể, áp dụng toàn hệ thống |
| **Tác dụng** | Đổi bảng mẫu đánh giá |
| **Giải thích đơn giản** | Cho phép super_admin tạo mẫu đánh giá dùng chung toàn hệ thống. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi bảng mẫu đánh giá). File chính: Evaluation/EvaluationTemplateController.php, Evaluation/StoreEvaluationTemplateRequest.php, Evaluation/EvaluationTemplate.php, Evaluation/EvaluationTemplateService.php (+7). |

### 1.20. Ẩn/hiện menu toàn hệ thống áp dụng cho cả super_admin, nâng cấp trang quản lý

Định danh và phân quyền · Cấu hình menu · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Ẩn/hiện menu toàn hệ thống áp dụng cho cả super_admin, nâng cấp trang quản lý. - Menu bị ẩn toàn hệ thống (superadmin cấu hình) giờ áp dụng cho MỌI tài   khoản kể cả super_admin, không còn ngoại lệ ở AppSidebar.vue::itemPasses.   Trang "Ẩn/hiện menu toàn hệ thống" tự bảo vệ chính nó (PROTECTED_MENU_KEYS)   nên super_admin luôn còn đường vào lại để bật menu đã ẩn. - Bỏ 3 mục không còn dùng khỏi catalog: settings, users, departments. - Thêm ghi chú "audience" cho các mục dễ nhầm là dành riêng super_admin   (Cấu hình phòng ban, Mẫu đánh giá, Duyệt bài viết) — hiện thành 1 dòng   chú thích trên trang quản lý. - Nâng cấp WorkspaceConfigGlobalMenuVisibilitySuperadmin.vue: tìm kiếm,   lọc hiện/ẩn, bật/ẩn hàng loạt, panel chi tiết, chấm màu theo section. - Cập nhật test tương ứng. |
| **Vì sao** | để bật menu đã ẩn |
| **Tác dụng** | bật menu đã ẩn |
| **Giải thích đơn giản** | Ẩn/hiện menu toàn hệ thống áp dụng cho cả super_admin, nâng cấp trang quản lý. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Identity/AuthenticatedUserPresenter.php, Identity/auth.js, WorkspaceConfig/GlobalMenuVisibilityService.php, WorkspaceConfig/WorkspaceConfigGlobalMenuVisibilitySuperadmin.vue (+2). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 21:48 | `768dad0` | Thêm | Identity/AuthenticatedUserPresenter.php, Identity/auth.js, WorkspaceConfig/GlobalMenuVisibilityService.php, WorkspaceConfig/WorkspaceConfigGlobalMenuVisibilitySuperadmin.vue (+2) |

#### Chi tiết từng lần lưu (version)

##### Version 1.20.1 — mã `768dad0` · 21:48

| | |
| --- | --- |
| **Đổi gì** | Ẩn/hiện menu toàn hệ thống áp dụng cho cả super_admin, nâng cấp trang quản lý. - Menu bị ẩn toàn hệ thống (superadmin cấu hình) giờ áp dụng cho MỌI tài   khoản kể cả super_admin, không còn ngoại lệ ở AppSidebar.vue::itemPasses.   Trang "Ẩn/hiện menu toàn hệ thống" tự bảo vệ chính nó (PROTECTED_MENU_KEYS)   nên super_admin luôn còn đường vào lại để bật menu đã ẩn. - Bỏ 3 mục không còn dùng khỏi catalog: settings, users, departments. - Thêm ghi chú "audience" cho các mục dễ nhầm là dành riêng super_admin   (Cấu hình phòng ban, Mẫu đánh giá, Duyệt bài viết) — hiện thành 1 dòng   chú thích trên trang quản lý. - Nâng cấp WorkspaceConfigGlobalMenuVisibilitySuperadmin.vue: tìm kiếm,   lọc hiện/ẩn, bật/ẩn hàng loạt, panel chi tiết, chấm màu theo section. - Cập nhật test tương ứng. |
| **Vì sao** | để bật menu đã ẩn |
| **Tác dụng** | bật menu đã ẩn |
| **Giải thích đơn giản** | Ẩn/hiện menu toàn hệ thống áp dụng cho cả super_admin, nâng cấp trang quản lý. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Identity/AuthenticatedUserPresenter.php, Identity/auth.js, WorkspaceConfig/GlobalMenuVisibilityService.php, WorkspaceConfig/WorkspaceConfigGlobalMenuVisibilitySuperadmin.vue (+2). |

### 1.21. Cập nhật VA_WORKSPACE_OVERVIEW.md — sửa ẩn/hiện menu toàn hệ thống

Tài liệu · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Cập nhật VA_WORKSPACE_OVERVIEW.md — sửa ẩn/hiện menu toàn hệ thống. Ghi nhận thay đổi: cơ chế ẩn/hiện menu toàn hệ thống (WorkspaceConfig) nay áp dụng cho mọi tài khoản kể cả super_admin, không còn ngoại lệ; UI trang quản lý được nâng cấp (tìm kiếm, lọc, thao tác hàng loạt, panel chi tiết). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Cập nhật VA_WORKSPACE_OVERVIEW.md — sửa ẩn/hiện menu toàn hệ thống. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: docs/VA_WORKSPACE_OVERVIEW.md. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 21:49 | `c4eef56` | Tài liệu | docs/VA_WORKSPACE_OVERVIEW.md |

#### Chi tiết từng lần lưu (version)

##### Version 1.21.1 — mã `c4eef56` · 21:49

| | |
| --- | --- |
| **Đổi gì** | Cập nhật VA_WORKSPACE_OVERVIEW.md — sửa ẩn/hiện menu toàn hệ thống. Ghi nhận thay đổi: cơ chế ẩn/hiện menu toàn hệ thống (WorkspaceConfig) nay áp dụng cho mọi tài khoản kể cả super_admin, không còn ngoại lệ; UI trang quản lý được nâng cấp (tìm kiếm, lọc, thao tác hàng loạt, panel chi tiết). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Cập nhật VA_WORKSPACE_OVERVIEW.md — sửa ẩn/hiện menu toàn hệ thống. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: docs/VA_WORKSPACE_OVERVIEW.md. |

### 1.22. Đổi tên, sắp xếp và cấu hình nhóm menu sidebar toàn hệ thống

Định danh và phân quyền · Cấu hình đơn từ, Cấu hình menu · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Đổi tên, sắp xếp và cấu hình nhóm menu sidebar toàn hệ thống |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Đổi tên, sắp xếp và cấu hình nhóm menu sidebar toàn hệ thống. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu). File chính: Evaluation/EvaluationView.vue, Identity/GlobalMenuSectionConfig.php, Identity/GlobalMenuVisibility.php, Identity/IdentityServiceProvider.php (+18). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 23:15 | `a296305` | Thêm | Evaluation/EvaluationView.vue, Identity/GlobalMenuSectionConfig.php, Identity/GlobalMenuVisibility.php, Identity/IdentityServiceProvider.php (+18) |

#### Chi tiết từng lần lưu (version)

##### Version 1.22.1 — mã `a296305` · 23:15

| | |
| --- | --- |
| **Đổi gì** | Đổi tên, sắp xếp và cấu hình nhóm menu sidebar toàn hệ thống |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Đổi tên, sắp xếp và cấu hình nhóm menu sidebar toàn hệ thống. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu). File chính: Evaluation/EvaluationView.vue, Identity/GlobalMenuSectionConfig.php, Identity/GlobalMenuVisibility.php, Identity/IdentityServiceProvider.php (+18). |

