# Bảng danh sách — kỹ thuật (mẫu ActivityLog)

Copy từ `Modules/Identity/resources/js/pages/ActivityLog.vue` và `resources/js/components/TablePagesBar.vue`.

## TablePagesBar

```vue
<!-- Dưới hàng filter -->
<TablePagesBar
  placement="top"
  :from="meta.from || 0"
  :to="meta.to || 0"
  :total="meta.total || 0"
  :page="meta.current_page || 1"
  :last-page="meta.last_page || 1"
  :per-page="perPage"
  :zoom="tableZoom"
  show-search
  :show-clear-filters="hasActiveFilters"
  :filters-active="hasActiveFilters"
  @search="loadLogs(1)"
  @clear-filters="clearFilters"
  @update:page="goPage"
  @update:per-page="perPage = $event"
  @update:zoom="tableZoom = $event"
>
  <template #filters>…checkbox field…</template>
  <template #settings>…checkbox cột…</template>
</TablePagesBar>

<!-- Dưới bảng -->
<TablePagesBar
  placement="bottom"
  paging-only
  :from="meta.from || 0"
  :to="meta.to || 0"
  :total="meta.total || 0"
  :page="meta.current_page || 1"
  :last-page="meta.last_page || 1"
  :per-page="perPage"
  @update:page="goPage"
  @update:per-page="perPage = $event"
/>
```

| Prop | Ý nghĩa |
|------|---------|
| `placement` | `'top'` menu xuống + gạch chân nhẹ; `'bottom'` menu lên |
| `pagingOnly` | Ẩn Tìm / Bộ lọc / Cỡ chữ / Cột |
| `showSearch` | Nút Tìm |
| `showClearFilters` | Nút Xoá lọc |
| `filtersActive` | Tô sáng Bộ lọc khi đang lọc |

Không `title`. Menu click-outside + Escape trong chính component.

Gạch chân thanh trên (không `border-bottom`):

```css
.table-pages--top {
  box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 75%, transparent);
}
```

## Bảng + kéo cột

```html
<div ref="tableWrap" class="…table-wrap hide-scrollbar">
  <table class="…table" :style="{ width: tableWidthPx }">
    <colgroup>
      <col v-for="col in shownColumns" :key="col.key" :style="{ width: colWidthStyle(col.key) }" />
    </colgroup>
    <thead>
      <th>
        <span>{{ col.label }}</span>
        <button type="button" class="…resize" aria-label="Kéo để đổi độ rộng cột"
          @click.stop @mousedown.stop.prevent="startResize($event, col.key)" />
      </th>
    </thead>
  </table>
</div>
```

```css
.resize {
  position: absolute;
  top: 0;
  right: 0;
  z-index: 2;
  width: 0.5rem;
  height: 100%;
  padding: 0;
  border: none;
  background: transparent;
  cursor: col-resize;
}
.resize::after {
  content: '';
  position: absolute;
  top: 25%;
  right: 2px;
  width: 2px;
  height: 50%;
  border-radius: var(--radius-full);
  background: var(--color-border);
}
table { min-width: 100%; table-layout: fixed; }
th, td { white-space: nowrap; box-shadow: 0 1px 0 var(--color-border); }
```

`startResize`: neighbor = cột phải, hoặc trái nếu cột cuối; `mousemove` chia lại `pair = A + B`; `MIN_COL_PX ≈ 72`.

`fitColumnsToContent`: `canvas.measureText` header + mọi ô (+ email nếu cột người); `+ padding (~32) + extra (~24)`; nếu tổng < `wrap.clientWidth` thì phân bổ phần dư theo tỉ lệ. Gọi sau load data, đổi cột, zoom, mở/đóng panel, ResizeObserver width.

## Kéo ngang bằng chuột (`useDragScroll`)

`resources/js/composables/useDragScroll.js` — mousedown trên `tableWrap` rồi
mousemove ≥ ngưỡng 4px thì coi là kéo, set `scrollLeft`/`scrollTop` theo delta;
mouseup thì dừng, chặn `click` phát sinh ngay sau đó (tránh chọn nhầm dòng vì
mouseup rơi trên `<tr>`). Tự bỏ qua khi mousedown bắt đầu từ
`button, a, input, select, textarea` hoặc khi `isBlocked()` trả `true` (đang
kéo nắm cột).

```js
import { useDragScroll } from '@/composables/useDragScroll';

useDragScroll(tableWrap, { isBlocked: () => resizing.value });
```

Không cần thêm CSS ở trang — `grab`/`grabbing` cursor đã định nghĩa sẵn trên
`.hide-scrollbar` / `.hide-scrollbar.drag-scrolling` trong `resources/css/app.css`.

## Panel chi tiết

Không `Teleport` overlay. Sibling của `__main` trong `__body` (`display: flex; gap: var(--space-4)`).

```html
<aside v-if="selected" class="…side" aria-label="Chi tiết thao tác">
  <div class="…side-head">
    <h2>Chi tiết thao tác</h2>
    <button type="button" aria-label="Đóng" @click="selected = null">…</button>
  </div>
  <div class="…row">
    <span class="…row-label">Thời gian</span>
    <span class="…row-value">{{ ... }}</span>
  </div>
  <!-- lặp lại mỗi field một …row -->
</aside>
```

Nhãn **không** gõ dấu `:` vào text — thêm bằng CSS `::after` để đồng nhất.
Giá trị chữ thường **nghiêng**, không in đậm.

```css
.side {
  flex-shrink: 0;
  width: 28rem; /* không còn 20rem hay 22–24rem */
  overflow-y: auto;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
}
.row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
}
.row:last-child { box-shadow: none; }
.row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}
.row-label::after {
  content: ':';
}
.row-value {
  color: var(--color-text);
  font-style: italic;
  text-align: right;
  overflow-wrap: anywhere;
}
@media (max-width: 1024px) {
  .body { flex-direction: column; }
  .side { width: 100%; max-height: 42%; }
}
```

## Cột mặc định (mẫu)

Bật: `created_at`, `actor`, `description`.  
Tắt: `action`, `subject`, `properties`, `ip_address`, `browser`, `id`.

Visibility: `loadVisibility` / `saveVisibility` trong constants trang.
