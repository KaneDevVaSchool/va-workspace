<script setup>
//
// Icon set nội bộ, dạng SVG inline chọn theo tên (không phụ thuộc thư viện
// icon ngoài). Thêm icon mới bằng cách thêm key vào PATHS bên dưới.
//
const PATHS = {
  dashboard: 'M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6V11h-6v9Zm0-16v5h6V4h-6Z',
  users: 'M16 14c2.67 0 8 1.34 8 4v2H0v-2c0-2.66 5.33-4 8-4Zm0-2a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-8 2c.29 0 .62.02.97.05A5.98 5.98 0 0 0 6 19v1H0v-2c0-2.24 3.5-3.5 6-3.87A9.3 9.3 0 0 1 8 14Zm0-2a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
  building: 'M4 21V3h10v6h6v12h-6v-4h-4v4H4Zm2-2h2v-2H6v2Zm0-4h2v-2H6v2Zm0-4h2V9H6v2Zm0-4h2V5H6v2Zm4 8h2v-2h-2v2Zm0-4h2V9h-2v2Zm0-4h2V5h-2v2Zm6 12h2v-2h-2v2Zm0-4h2v-2h-2v2Z',
  settings:
    'M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm7.4-3.5c0 .5-.04 1-.12 1.46l2.02 1.58a.5.5 0 0 1 .12.64l-1.92 3.32a.5.5 0 0 1-.6.22l-2.38-.96c-.5.38-1.04.7-1.62.94l-.36 2.52a.5.5 0 0 1-.5.42h-3.84a.5.5 0 0 1-.5-.42l-.36-2.52c-.58-.24-1.12-.56-1.62-.94l-2.38.96a.5.5 0 0 1-.6-.22L2.58 15.7a.5.5 0 0 1 .12-.64l2.02-1.58C4.64 13 4.6 12.5 4.6 12s.04-1 .12-1.46L2.7 8.96a.5.5 0 0 1-.12-.64l1.92-3.32a.5.5 0 0 1 .6-.22l2.38.96c.5-.38 1.04-.7 1.62-.94l.36-2.52a.5.5 0 0 1 .5-.42h3.84a.5.5 0 0 1 .5.42l.36 2.52c.58.24 1.12.56 1.62.94l2.38-.96a.5.5 0 0 1 .6.22l1.92 3.32a.5.5 0 0 1-.12.64l-2.02 1.58c.08.46.12.96.12 1.46Z',
  logout:
    'M10 17v-2H4V9h6V7l5 5-5 5Zm-1 3H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h4v2H5v12h4v2Z',
  chevronLeft: 'M15 6l-6 6 6 6',
  chevronRight: 'M9 6l6 6-6 6',
  chevronDown: 'M6 9l6 6 6-6',
  menu: 'M4 6h16M4 12h16M4 18h16',
  close: 'M6 6l12 12M18 6L6 18',
};

defineProps({
  name: { type: String, required: true },
  size: { type: [Number, String], default: 20 },
});
</script>

<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    aria-hidden="true"
    class="app-icon"
  >
    <path
      :d="PATHS[name] ?? PATHS.dashboard"
      :stroke="['chevronLeft', 'chevronRight', 'chevronDown', 'menu', 'close'].includes(name) ? 'currentColor' : 'none'"
      :fill="['chevronLeft', 'chevronRight', 'chevronDown', 'menu', 'close'].includes(name) ? 'none' : 'currentColor'"
      stroke-width="2"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
  </svg>
</template>

<style scoped>
.app-icon {
  flex-shrink: 0;
}
</style>
