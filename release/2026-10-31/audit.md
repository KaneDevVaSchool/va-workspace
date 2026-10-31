# Đối soát 31/10/2026

Đọc nhanh: [README.md](./README.md) · Tuần: [2026-W44](../summary/2026-W44-summary.md) · Bảng tuần: [2026-W44-audit](../summary/2026-W44-audit.md)

Việc trong ngày đánh số **1.1**, **1.2**, … Mỗi lần lưu (commit) là **1.1.1**, **1.1.2**, … Bảng có thêm dòng **Giải thích đơn giản** cho người không rành kỹ thuật. Thiếu trong commit thì ghi «chưa ghi», không suy diễn.

## Việc trong ngày

### 1.1. Giữ UUID khi trùng email, ẩn menu đơn từ chưa mở và giải thích xuất PDF

Đơn từ · Lịch sử, Cấu hình đơn từ, Đơn từ · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Giữ UUID khi trùng email, ẩn menu đơn từ chưa mở và giải thích xuất PDF. Giữ mã nhân sự khi nhiều hồ sơ HRM cùng email, chỉ để menu Cấu hình đơn từ, và ghi rõ chỉ xuất PDF khi đơn đã duyệt. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Giữ UUID khi trùng email, ẩn menu đơn từ chưa mở và giải thích xuất PDF. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeRequests.vue, DonTu/DonTuSidebarMenuCatalog.php, DonTu/nav.js, DonTu/DonTuLayout.vue (+21). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 09:12 | `fe9e4a3` | Thêm | Attendance/EmployeeRequests.vue, DonTu/DonTuSidebarMenuCatalog.php, DonTu/nav.js, DonTu/DonTuLayout.vue (+21) |

#### Chi tiết từng lần lưu (version)

##### Version 1.1.1 — mã `fe9e4a3` · 09:12

| | |
| --- | --- |
| **Đổi gì** | Giữ UUID khi trùng email, ẩn menu đơn từ chưa mở và giải thích xuất PDF. Giữ mã nhân sự khi nhiều hồ sơ HRM cùng email, chỉ để menu Cấu hình đơn từ, và ghi rõ chỉ xuất PDF khi đơn đã duyệt. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Giữ UUID khi trùng email, ẩn menu đơn từ chưa mở và giải thích xuất PDF. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Attendance/EmployeeRequests.vue, DonTu/DonTuSidebarMenuCatalog.php, DonTu/nav.js, DonTu/DonTuLayout.vue (+21). |

### 1.2. Phieu release va audit ngay 31/10

Tài liệu · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Phieu release va audit ngay 31/10 |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Phieu release va audit ngay 31/10. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: scripts/build-release-notes.mjs, 2026-08-26/README.md, 2026-08-26/audit.md, 2026-08-27/README.md (+28). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 09:14 | `ea2200e` | Tài liệu | scripts/build-release-notes.mjs, 2026-08-26/README.md, 2026-08-26/audit.md, 2026-08-27/README.md (+28) |

#### Chi tiết từng lần lưu (version)

##### Version 1.2.1 — mã `ea2200e` · 09:14

| | |
| --- | --- |
| **Đổi gì** | Phieu release va audit ngay 31/10 |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Phieu release va audit ngay 31/10. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: scripts/build-release-notes.mjs, 2026-08-26/README.md, 2026-08-26/audit.md, 2026-08-27/README.md (+28). |

### 1.3. Audit BMAD theo version 1.1 và 1.2

Tài liệu · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Audit BMAD theo version 1.1 và 1.2. Phiếu đối soát mỗi việc một bảng đổi gì, vì sao, tác dụng, đánh số 1.1, 1.2 trong ngày. |
| **Vì sao** | vì sao, tác dụng, đánh số 1 |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Audit BMAD theo version 1.1 và 1.2. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: rules/release-notes.mdc, release-notes/SKILL.md, scripts/build-release-notes.mjs, 2026-08-24/README.md (+86). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 09:16 | `427206a` | Tài liệu | rules/release-notes.mdc, release-notes/SKILL.md, scripts/build-release-notes.mjs, 2026-08-24/README.md (+86) |

#### Chi tiết từng lần lưu (version)

##### Version 1.3.1 — mã `427206a` · 09:16

| | |
| --- | --- |
| **Đổi gì** | Audit BMAD theo version 1.1 và 1.2. Phiếu đối soát mỗi việc một bảng đổi gì, vì sao, tác dụng, đánh số 1.1, 1.2 trong ngày. |
| **Vì sao** | vì sao, tác dụng, đánh số 1 |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Audit BMAD theo version 1.1 và 1.2. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: rules/release-notes.mdc, release-notes/SKILL.md, scripts/build-release-notes.mjs, 2026-08-24/README.md (+86). |

### 1.4. Đồng bộ audit BMAD với VA-HRM, version 1.1

Tài liệu · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Đồng bộ audit BMAD với VA-HRM, version 1.1. Phiếu lấy phần theo module code, không nhận nhầm chữ để trong chỉ để thành lý do. |
| **Vì sao** | để trong chỉ để thành lý do |
| **Tác dụng** | trong chỉ để thành lý do |
| **Giải thích đơn giản** | Đồng bộ audit BMAD với VA-HRM, version 1.1. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: release-notes/SKILL.md, scripts/build-release-notes.mjs, 2026-08-24/README.md, 2026-08-24/audit.md (+13). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 09:19 | `1542b90` | Tài liệu | release-notes/SKILL.md, scripts/build-release-notes.mjs, 2026-08-24/README.md, 2026-08-24/audit.md (+13) |

#### Chi tiết từng lần lưu (version)

##### Version 1.4.1 — mã `1542b90` · 09:19

| | |
| --- | --- |
| **Đổi gì** | Đồng bộ audit BMAD với VA-HRM, version 1.1. Phiếu lấy phần theo module code, không nhận nhầm chữ để trong chỉ để thành lý do. |
| **Vì sao** | để trong chỉ để thành lý do |
| **Tác dụng** | trong chỉ để thành lý do |
| **Giải thích đơn giản** | Đồng bộ audit BMAD với VA-HRM, version 1.1. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: release-notes/SKILL.md, scripts/build-release-notes.mjs, 2026-08-24/README.md, 2026-08-24/audit.md (+13). |

