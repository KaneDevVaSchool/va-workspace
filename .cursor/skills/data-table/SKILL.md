---
name: data-table
description: >-
  Trang danh sách admin theo mẫu vàng ActivityLog: filter, TablePagesBar trên/dưới,
  kéo cột, ẩn scrollbar, panel chi tiết đẩy ngang. Dùng khi làm/sửa bảng Vue
  (nhật ký, nhân sự, nhóm, danh sách), TablePagesBar, colgroup, hide-scrollbar.
---

# Bảng danh sách admin

Rule: `.cursor/rules/data-table.mdc` (`alwaysApply: true`)

Chi tiết CSS / props / đo cột: [reference.md](reference.md)

## Mẫu vàng (bắt buộc đọc trước khi copy)

| Layer | File |
|-------|------|
| Trang | `Modules/Identity/resources/js/pages/ActivityLog.vue` |
| Thanh công cụ / phân trang | `resources/js/components/TablePagesBar.vue` |
| Cột / bộ lọc / localStorage | `Modules/Identity/resources/js/constants/activity.js` |
| Header xuất dữ liệu | `resources/js/components/PageHeader.vue` |
| Ẩn scrollbar | `resources/css/app.css` (`.hide-scrollbar`) |
| Kéo ngang bằng chuột | `resources/js/composables/useDragScroll.js` |

**Luôn tái dùng cấu trúc trên.** Port sang trang khác: đổi API, key cột, nhãn; giữ layout, `TablePagesBar`, kéo cột, panel đẩy ngang.

---

## Cấu trúc trang

```
PageHeader (tiêu đề + Làm mới + xuất nếu cần)
div.__body (flex row, overflow hidden)
├── div.__main (flex column, min-width 0)
│   ├── filter fields (grid, v-if còn field đang bật)
│   ├── TablePagesBar placement="top"  ← đủ nút, gạch chân nhẹ
│   ├── table-wrap.hide-scrollbar (flex 1)
│   └── TablePagesBar placement="bottom" paging-only
└── aside.__side (v-if selected) — Chi tiết, width 28rem, đẩy bảng
```

Thứ tự **không** đảo: filter → thanh đầy đủ → bảng → thanh phân trang.

---

## 1. Hai thanh `TablePagesBar`

**Trên** (`placement="top"`): Tìm, Bộ lọc (slot `#filters`), Cỡ chữ, Cột (slot `#settings`), Hiển thị a–b / n, Trang 01 / 05, Trước, Sau.  
`show-search`, `show-clear-filters` khi đang lọc. Gạch chân nhẹ: `box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 75%, transparent)`. Menu mở **xuống**.

**Dưới** (`placement="bottom"` + `paging-only`): chỉ Hiển thị, Trang, Trước, Sau. Menu mở **lên**.

Cấm: một thanh duy nhất; nút **Chia màn hình**; `title` tooltip.

---

## 2. Cột trên bảng — ít, đủ quét

Mặc định chỉ cột quét nhanh (mẫu: Thời gian, Người thực hiện, Việc đã làm).  
Loại thao tác, IP, trình duyệt, mã bản ghi… **tắt mặc định** — đủ trong panel chi tiết. Người dùng bật thêm qua **Cột**. Cần ≥ 1 cột. Đổi default → bump key localStorage (`…-columns-v2`).

Người + email: **một cột**, tên một dòng, email dòng dưới `color: var(--color-text-muted)`.

---

## 3. Kéo cột + độ rộng theo nội dung

- `table-layout: fixed`; `colgroup` + width px; `min-width: 100%` trên `<table>`.
- Mỗi `<th>`: tay nắm mép phải `cursor: col-resize`, `aria-label="Kéo để đổi độ rộng cột"`, **không** `title`.
- Kéo: cột hiện tại và cột kề (phải, hoặc trái nếu cột cuối) co/giãn bù; `min ≈ 72px`.
- Tay nắm: `position: absolute; right: 0; width: 0.5rem`. Vạch = `::after` `background: var(--color-border)` — **cấm** `border-right`.
- Đo canvas theo chữ dài nhất (header + ô, gồm email), cộng padding; nếu tổng < khung thì giãn tỉ lệ cho kín. Persist localStorage.
- Ô: `white-space: nowrap` (không ellipsis khi đã fit). Wrap bảng: `hide-scrollbar`.

### Kéo ngang toàn bảng bằng chuột

Khi tổng độ rộng cột (đã fit nội dung) vượt khung nhìn — bảng nhiều cột hơn
màn hình xem hết — **không chỉ dựa vào cuộn bánh xe/trackpad**: máy bàn dùng
chuột thường không vuốt ngang được. Bảng phải kéo được bằng cách **nắm chuột
giữ (mousedown) rồi kéo** ngay trên vùng bảng, giống pan trên canvas.

```js
import { useDragScroll } from '@/composables/useDragScroll';

const tableWrap = ref(null);
const resizing = ref(false); // đã có sẵn cho kéo cột

useDragScroll(tableWrap, { isBlocked: () => resizing.value });
```

`useDragScroll` tự bỏ qua khi bắt đầu từ nắm kéo cột / button / input / a /
select (không phá thao tác click chọn dòng — chỉ kích hoạt kéo khi di
chuyển ≥ 4px). Vẫn **tuyệt đối không hiện thanh scroll**; chỉ đổi con trỏ
`grab` → `grabbing` (định nghĩa sẵn trong `.hide-scrollbar` ở `app.css`,
không cần thêm CSS ở trang).

---

## 4. Chi tiết thao tác — đẩy ngang

Click dòng → `selected = log` → `<aside>` bên phải, bảng co lại. Không modal giữa màn, không overlay. Đóng: nút X hoặc Escape. Màn hẹp: panel dưới bảng, `max-height: 42%`.

Panel rộng `28rem` (không dùng `20rem` hay `22–24rem`). Mỗi dòng: nhãn trái chữ mờ có
dấu `:` cuối (`::after { content: ':' }` trong CSS, không gõ `:` vào text),
giá trị bên phải **chữ thường nghiêng** (`font-style: italic`, không
`font-weight: 600` — quét toàn bộ, không in đậm), cách dòng `box-shadow: 0
1px 0 var(--color-border)`.

```css
.page__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}
.page__row-label::after {
  content: ':';
}
.page__row-value {
  color: var(--color-text);
  font-style: italic;
  text-align: right;
  overflow-wrap: anywhere;
}
```

---

## 5. Filter

Ô filter **trên** thanh công cụ. **Bộ lọc** chỉ chọn field nào hiện. **Tìm** gọi load trang 1. Ẩn hết field thì ẩn hàng filter.

---

## 6. Thao tác theo dòng — 1 nút dropdown

Cột/khu vực thao tác cuối mỗi dòng **không** hiện nhiều icon-button rời
(sửa, xoá, khoá, ghim, duyệt… mỗi cái 1 nút). Gộp tất cả vào **1 nút** mở
menu collapse — kể cả khi dòng đó chỉ có đúng 1 hành động khả dụng (vẫn qua
dropdown, không hiện thẳng nút đơn, để cột luôn đồng nhất).

- Nút trigger: icon 3 chấm dọc (`more_vert`), `aria-label="Thao tác"`
  (không `title` — mục cấm hint áp dụng cả đây).
- Menu: `role="menu"`, mỗi hành động `role="menuitem"` với **chữ tiếng Việt
  phổ thông** đứng cạnh icon (không chỉ icon trần) — đúng tinh thần "ý nghĩa
  nút phải hiển thị sẵn".
- Đóng khi click ngoài hoặc phím Escape; mở **xuống** nếu đủ chỗ, tự lật
  **lên** khi gần đáy khung nhìn/bảng.
- Hành động phá huỷ (xoá, thu hồi quyền…) trong menu vẫn phải qua
  `ConfirmDialog`, không xoá ngay khi click item.
- Mẫu cấu trúc dropdown tham khảo: `resources/js/components/HeaderAccountMenu.vue`
  (trigger button + `<div role="menu">` tuyệt đối định vị, đóng bằng click-outside/Escape).

---

## Anti-patterns

- Chia màn hình / split view / chi tiết modal.
- Thanh công cụ chỉ dưới bảng, hoặc thiếu thanh phân trang dưới.
- Hiện hết cột trên bảng vì “đủ thông tin”.
- Scrollbar hiện trên khung bảng; `title="..."`.
- `border-bottom` / `border-right` cho gạch — dùng `box-shadow`.
- Bảng tràn ngang mà không kéo được bằng chuột (chỉ trông chờ wheel/trackpad).
- Giá trị trong panel chi tiết in đậm (`font-weight: 600`) hoặc nhãn thiếu dấu `:`.
- Nhiều icon-button thao tác rời nhau trên 1 dòng thay vì gộp 1 dropdown.

## Checklist

- [ ] Filter → TablePagesBar trên → bảng → TablePagesBar dưới (`paging-only`)
- [ ] Thanh trên có gạch chân nhẹ; không chia màn hình
- [ ] Cột mặc định ít; người + email một cột
- [ ] Mọi cột kéo được; width persist; fit theo nội dung dài nhất
- [ ] `hide-scrollbar`; không `title`
- [ ] Bảng tràn ngang kéo được bằng nắm chuột (`useDragScroll`), không hiện thanh scroll
- [ ] Chi tiết = aside đẩy ngang (rộng 28rem), không modal
- [ ] Panel chi tiết: nhãn có `:` (CSS `::after`), giá trị chữ nghiêng không đậm
- [ ] Thao tác theo dòng gộp 1 nút dropdown (⋮ + "Thao tác"), kể cả chỉ 1 hành động
