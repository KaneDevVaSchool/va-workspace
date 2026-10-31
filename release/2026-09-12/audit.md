# Đối soát 12/09/2026

Đọc nhanh: [README.md](./README.md) · Tuần: [2026-W37](../summary/2026-W37-summary.md) · Bảng tuần: [2026-W37-audit](../summary/2026-W37-audit.md)

Việc trong ngày đánh số **1.1**, **1.2**, … Mỗi lần lưu (commit) là **1.1.1**, **1.1.2**, … Bảng có thêm dòng **Giải thích đơn giản** cho người không rành kỹ thuật. Thiếu trong commit thì ghi «chưa ghi», không suy diễn.

## Việc trong ngày

### 1.1. Tab Feedback/Testcase/Report, module Credential, làm lại Permission Matrix

Dự án và công việc · Quyền và nhật ký, Công việc và dự án, Hồ sơ đăng nhập, Cấu hình menu · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Tab Feedback/Testcase/Report, module Credential, làm lại Permission Matrix. - Project: thêm tab Phản hồi (feedback), Testcase (kèm check stages), Báo cáo   hoàn thành cho trang Chi tiết dự án; hỗ trợ ẩn/hiện tab qua cấu hình   disabled_tabs; bulk task, context menu dòng công việc, modal thao tác nhanh   cho task. - Credential: module mới quản lý thông tin đăng nhập dịch vụ (provider,   credential, viewer) theo đúng pattern Controller → Service → Repository. - Identity: làm lại PermissionMatrix (bảng, ô quyền, filter theo scope) theo   hướng dễ đọc hơn, đồng bộ permissions.js với config/permissions.php và   middleware EnsureHasPermission. - Cập nhật routes/manager.php (Project, Credential), sidebar/header, PageHeader,   router, deploy.sh.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Tab Feedback/Testcase/Report, module Credential, làm lại Permission Matrix. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu). File chính: .claude/CLAUDE.md, Credential/CredentialEnums.php, Credential/CredentialController.php, Credential/CredentialProviderController.php (+82). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 10:00 | `c9e5e6f` | Thêm | .claude/CLAUDE.md, Credential/CredentialEnums.php, Credential/CredentialController.php, Credential/CredentialProviderController.php (+82) |

#### Chi tiết từng lần lưu (version)

##### Version 1.1.1 — mã `c9e5e6f` · 10:00

| | |
| --- | --- |
| **Đổi gì** | Tab Feedback/Testcase/Report, module Credential, làm lại Permission Matrix. - Project: thêm tab Phản hồi (feedback), Testcase (kèm check stages), Báo cáo   hoàn thành cho trang Chi tiết dự án; hỗ trợ ẩn/hiện tab qua cấu hình   disabled_tabs; bulk task, context menu dòng công việc, modal thao tác nhanh   cho task. - Credential: module mới quản lý thông tin đăng nhập dịch vụ (provider,   credential, viewer) theo đúng pattern Controller → Service → Repository. - Identity: làm lại PermissionMatrix (bảng, ô quyền, filter theo scope) theo   hướng dễ đọc hơn, đồng bộ permissions.js với config/permissions.php và   middleware EnsureHasPermission. - Cập nhật routes/manager.php (Project, Credential), sidebar/header, PageHeader,   router, deploy.sh.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Tab Feedback/Testcase/Report, module Credential, làm lại Permission Matrix. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu). File chính: .claude/CLAUDE.md, Credential/CredentialEnums.php, Credential/CredentialController.php, Credential/CredentialProviderController.php (+82). |

