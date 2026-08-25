---
name: form-modal
description: >-
  Modal form tạo/sửa admin: lưới ngang 2–3 cột, nằm trong viewport, được phép
  gần full screen. Dùng khi làm/sửa dialog form Vue (tiêu chí, nhóm, vai trò,
  bản ghi), Teleport dialog, không dùng cho confirm xoá hay panel chi tiết bảng.
---

# Form modal admin

Rule: `.cursor/rules/form-modal.mdc` (`alwaysApply: true`)

Bản Cursor (đầy đủ + reference): [`.cursor/skills/form-modal/SKILL.md`](../../../.cursor/skills/form-modal/SKILL.md)

Mẫu: `Modules/Evaluation/resources/js/pages/WorkspaceConfigEvaluation.vue` — modal **Thêm tiêu chí**.

## Bắt buộc

1. Form **ngang 2–3 cột**. Field dài (mô tả, list con) mới span full.
2. Dialog **nằm trong viewport**; được phép gần full screen. Panel `overflow: hidden`; cuộn chỉ ở body (`hide-scrollbar`).
3. Confirm xoá = alertdialog nhỏ — không dùng mẫu này.
4. Không `title`, không hint dưới field.
