---
name: form-modal
description: >-
  Modal form tạo/sửa admin: lưới ngang 2–3 cột, nằm trong viewport, được phép
  gần full screen. Dùng khi làm/sửa dialog form Vue (tiêu chí, nhóm, vai trò,
  bản ghi), Teleport dialog, không dùng cho confirm xoá hay panel chi tiết bảng.
---

# Form modal admin

Rule: `.cursor/rules/form-modal.mdc` (`alwaysApply: true`)

Chi tiết CSS / class: [reference.md](reference.md)

## Mẫu vàng (bắt buộc đọc trước khi copy)

| Layer | File |
|-------|------|
| Modal tạo/sửa | `Modules/Evaluation/resources/js/pages/WorkspaceConfigEvaluation.vue` — dialog **Thêm tiêu chí** |

**Luôn tái dùng cấu trúc trên.** Port sang dialog khác: đổi field; giữ overlay, panel gần viewport, lưới 2–3 cột, head/tab/actions cố định.

Không áp dụng cho: confirm xoá (`role="alertdialog"`), panel chi tiết bảng (aside đẩy ngang — skill `data-table`).

---

## Cấu trúc

```
Teleport + overlay (inset 0, padding, căn giữa)
└── panel (flex column, overflow hidden, gần full viewport)
    ├── head (icon + title + đóng)     flex-shrink: 0
    ├── tabs (nếu có Thêm/Sửa)         flex-shrink: 0
    ├── body (flex 1, min-height 0, hide-scrollbar)
    │   └── form grid 2–3 cột
    └── actions (Huỷ + primary)        flex-shrink: 0
```

---

## 1. Nằm trong viewport, được phép gần full screen

- Overlay: `position: fixed; inset: 0; padding: var(--space-5)`.
- Panel: `width: min(90rem, calc(100vw - 2.5rem))`; `height: calc(100vh - 2.5rem)`; `max-height: calc(100vh - 2.5rem)`; `overflow: hidden`.
- Không `overflow: auto` trên **cả panel** — cuộn (nếu bắt buộc) chỉ ở **body**, class `hide-scrollbar`.
- Màn hẹp: vẫn trong viewport; form xếp 1 cột; không tràn khỏi đáy/cạnh màn.

---

## 2. Form ngang 2–3 cột

Desktop: khối thông tin `grid-template-areas` 3 cột (`name type scoring` / `desc desc status`).
Tab Sửa hoặc tablet: 2 cột. Mobile: 1 cột.

Danh sách con (mức điểm) nằm trong card riêng, `flex: 1`, không xếp dọc một hàng/dòng trên desktop.

---

## 3. Visual

- Token light (`var(--color-*)`). Overlay: `var(--color-sidebar-overlay)`.
- Gạch 1 cạnh (tab active, tách head): `box-shadow`, không `border-bottom` / `border-left`.
- Không `title`. Không hint/tooltip dưới field. `aria-label` cho nút icon-only.

---

## Anti-patterns

- Dialog hẹp 1 cột, cuộn dài hết field.
- Panel `max-height` lớn hơn viewport, hoặc `overflow: auto` trên panel khiến head/nút trôi mất.
- Confirm xoá làm gần full screen / 3 cột.
- Hint chữ mờ dưới input giải thích khoá/tự cấp mã.

## Checklist

- [ ] Overlay + panel ≤ viewport; panel `overflow: hidden`
- [ ] Có thể rộng/cao gần full screen
- [ ] Form grid 2–3 cột; field dài mới span full
- [ ] Head / actions không cuộn mất
- [ ] Không hint/`title`; confirm nhỏ tách riêng
