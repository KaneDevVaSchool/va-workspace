---
name: data-table
description: >-
  Trang danh sách admin theo mẫu vàng ActivityLog: filter, TablePagesBar trên/dưới,
  kéo cột, ẩn scrollbar, panel chi tiết đẩy ngang. Dùng khi làm/sửa bảng Vue
  (nhật ký, nhân sự, nhóm, danh sách), TablePagesBar, colgroup, hide-scrollbar.
---

# Bảng danh sách admin

Rule: `.cursor/rules/data-table.mdc` (`alwaysApply: true`)

Bản Cursor (đầy đủ + reference): [`.cursor/skills/data-table/SKILL.md`](../../../.cursor/skills/data-table/SKILL.md)

Mẫu: `Modules/Identity/resources/js/pages/ActivityLog.vue` + `resources/js/components/TablePagesBar.vue`

## Bắt buộc

1. Filter → **TablePagesBar trên** (Tìm, Bộ lọc, Cỡ chữ, Cột, Hiển thị, Trang, Trước/Sau, gạch chân nhẹ) → bảng → **TablePagesBar dưới** `paging-only`.
2. Cột mặc định **ít**; chi tiết trong aside **đẩy ngang** (không modal, không chia màn hình).
3. Nắm mép `<th>` kéo cột; fit theo nội dung dài nhất; `hide-scrollbar`; người + email một cột.
4. Bảng tràn ngang → kéo bằng **nắm chuột giữ + kéo** (`useDragScroll` từ
   `resources/js/composables/useDragScroll.js`, áp vào ref `tableWrap`) —
   không hiện thanh scroll.
5. Không `title`. Gạch 1 cạnh = `box-shadow`, không `border-bottom` / `border-right`.
6. Panel chi tiết: nhãn có `::after { content: ':' }`, giá trị `font-style:
   italic` (không đậm), panel rộng `28rem`.
