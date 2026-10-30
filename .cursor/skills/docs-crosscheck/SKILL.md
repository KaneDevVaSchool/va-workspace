---
name: docs-crosscheck
description: >-
  Đọc đúng file docs trước khi sửa code VA Workspace và ghi ngược thay đổi
  vào file đó. Dùng khi bắt đầu task, đối chiếu thiết kế, cập nhật docs,
  hoặc khi người dùng nói đọc docs trước, ghi ngược docs, BMAD.
---

# Đối chiếu docs

Rule: `.cursor/rules/docs-crosscheck.mdc`. Bố cục BMAD: `.cursor/skills/docs-bmad/SKILL.md`.

## Đọc file nào

| Việc | File |
| --- | --- |
| Chưa rõ hệ thống | `docs/project-overview.md`, rồi bản dài `docs/VA_WORKSPACE_OVERVIEW.md` |
| Sửa một module | `docs/architecture/modules/<Tên>.md` — bản đồ `docs/architecture/modules/README.md` |
| Route, quyền chung | `docs/architecture/index.md` |
| Token, viền | `docs/theme.md` |
| Bug | file module + `docs/known-issues.md` |
| Deploy | `docs/deployment-guide.md` và `docs/PRODUCTION-WORKSPACE-HRM.md` |
| Máy mới | `docs/development-guide.md` |

## Trước khi sửa

1. Đọc file module.
2. Mở class hoặc route sắp đụng. So với câu trong docs.
3. Docs lệch code: làm theo code đang chạy, rồi sửa docs cho khớp. Không bịa nghiệp vụ cho khớp docs cũ.

## Sau khi sửa

Ghi vào cùng file module: trách nhiệm, route, hoặc bảng vừa đổi. Một ý một câu. Ghi cả trang Vue (`path`, `meta.title`). Bẫy thì thêm vào `docs/known-issues.md`.

Chưa có `docs/architecture/modules/<Tên>.md`: tạo file chỉ với việc vừa làm, thêm một dòng trong `modules/README.md` và `docs/index.md`. Không viết lại cả module.

Phiếu release của ngày đó phải gọi đúng tên màn trong file module. Phiếu sai màn: sửa `SCREEN_RULES` của skill release-notes rồi chạy lại script. Không sửa tay phiếu, không viết vào `docs/modules/`.

## Không để file tạm

Không commit `tmp/`, script dò một lần, file inspect. Những thứ đó không phải tài liệu.
