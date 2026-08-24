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

## Panel chi tiết

Không `Teleport` overlay. Sibling của `__main` trong `__body` (`display: flex; gap: var(--space-4)`).

```html
<aside v-if="selected" class="…side" aria-label="Chi tiết thao tác">
  <div class="…side-head">
    <h2>Chi tiết thao tác</h2>
    <button type="button" aria-label="Đóng" @click="selected = null">…</button>
  </div>
  <!-- hàng: nhãn muted trái / giá trị phải -->
</aside>
```

```css
.side {
  flex-shrink: 0;
  width: 20rem;
  overflow-y: auto;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
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
