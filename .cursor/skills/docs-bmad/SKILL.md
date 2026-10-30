---
name: docs-bmad
description: >-
  Giữ bố cục tài liệu VA Workspace theo BMAD brownfield: file mở đầu ở gốc
  docs/, thư mục chỉ khi một chủ đề có nhiều file. Dùng khi thêm, đổi tên,
  hoặc chuyển file trong docs/, khi người dùng nói BMAD, đặt tên docs, hoặc
  bỏ docs vào thư mục.
---

# Bố cục docs BMAD

Rule: `.cursor/rules/docs-bmad.mdc`. Mục lục người đọc: `docs/index.md`.

Mẫu VA-HRM: `va-hrm/.cursor/skills/docs-bmad/SKILL.md`. Workspace không đổi tên file mà code đang trỏ.

## Giữ ở gốc

| File | Vai trò |
| --- | --- |
| `docs/README.md` | Chỉ trỏ `index.md` |
| `docs/index.md` | Mục lục. Thêm dòng khi có file mới |
| `docs/project-overview.md` | Cửa vào. Bản trạng thái dài là `VA_WORKSPACE_OVERVIEW.md` |
| `docs/development-guide.md` | Máy dev mới |
| `docs/deployment-guide.md` | Cửa deploy. Việc máy chủ nằm ở `PRODUCTION-WORKSPACE-HRM.md` |
| `docs/theme.md` | Token màu |
| `docs/known-issues.md` | Bẫy đang mở |

## Thư mục

| Thư mục | Chứa |
| --- | --- |
| `docs/architecture/` | `index.md`, `modules/<Module>.md` |
| `docs/modules/` | Lối cũ. Mỗi file một dòng trỏ sang `architecture/modules/` |

Module mới viết vào `docs/architecture/modules/<Tên>.md` và thêm một dòng trong `modules/README.md` cùng `docs/index.md`. File mới chỉ ghi việc vừa có trong code (route, trang Vue, câu lỗi). Không mở file thiết kế mới trong `docs/modules/`.

## Khi thêm file

1. Đã có file cùng chủ đề → ghi vào file đó.
2. Chủ đề đã có thư mục → thêm file trong thư mục, cập nhật `index.md` của thư mục và `docs/index.md`.
3. Một file mở đầu → gốc `docs/`, tên chữ thường có gạch ngang.
4. Chủ đề mới cần từ hai file → tạo thư mục + `index.md`, rồi thêm vào `docs/index.md`.

## Không làm

- Đổi tên `VA_WORKSPACE_OVERVIEW.md`, `known-issues.md`, `theme.md`, `PRODUCTION-WORKSPACE-HRM.md`.
- Tạo `docs/architecture.md` hoặc `docs/detailed-design.md` ở gốc.
- Tạo thư mục chỉ để bọc một file.
