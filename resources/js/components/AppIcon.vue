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
  shield: 'M12 2 4 5v6c0 5.25 3.4 9.74 8 11 4.6-1.26 8-5.75 8-11V5l-8-3Z',
  info: 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 5a1.15 1.15 0 1 1 0 2.3A1.15 1.15 0 0 1 12 7Zm1.25 10.5h-2.5v-1h.6v-4.5h-.6v-1h1.9v5.5h.6v1Z',
  refresh: 'M12 5V2L8 6l4 4V7a5 5 0 1 1-5 5H5a7 7 0 1 0 7-7Z',
  search: 'M10 4a6 6 0 1 0 3.76 10.66l4.79 4.8 1.41-1.42-4.79-4.79A6 6 0 0 0 10 4Zm-4 6a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z',
  lock: 'M12 2a4 4 0 0 0-4 4v3H7a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2h-1V6a4 4 0 0 0-4-4Zm-2 7V6a2 2 0 1 1 4 0v3h-4Z',
  unlock: 'M7 11V7a5 5 0 0 1 9.9-1M5 11h14a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2z',
  home: 'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zM9 22V12h6v10',
  plus: 'M12 5v14M5 12h14',
  layoutList: 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
  layoutGrid: 'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z',
  fileDown: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M12 18v-6M9 15l3 3 3-3',
  fileSpreadsheet: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h2M8 17h2M14 13h2M14 17h2',
  fileText: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M16 13H8M16 17H8M10 9H8',
  fileUp: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M12 12v6M9 15l3-3 3 3',
  download: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3',
  calendar: 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z',
  chevronsUp: 'M17 11l-5-5-5 5M17 18l-5-5-5 5',
  chevronsDown: 'M7 13l5 5 5-5M7 6l5 5 5-5',
  layers: 'M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5',
  fullscreen: 'M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M8 21H5a2 2 0 0 1-2-2v-3M16 21h3a2 2 0 0 0 2-2v-3',
  minimize: 'M8 3v3a2 2 0 0 1-2 2H3M16 3v3a2 2 0 0 0 2 2h3M8 21v-3a2 2 0 0 0-2-2H3M16 21v-3a2 2 0 0 1 2-2h3',
  star: 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z',
  starFilled: 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z',
  bookmark: 'M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z',
  pencil: 'M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z',
  trash: 'M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2',
  clock: 'M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10zM12 6v6l4 2',
  link: 'M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71',
  plusCircle: 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 9V7h-2v4H7v2h4v4h2v-4h4v-2h-4Z',
  check: 'M5 12l5 5L20 7',
  minus: 'M5 12h14',
  columns: 'M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7m0-18H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h7m0-18v18',
  sliders: 'M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6',
  pieChart: 'M21.21 15.89A10 10 0 1 1 8 2.83M22 12A10 10 0 0 0 12 2v10z',
  trendingUp: 'M23 6l-9.5 9.5-5-5L1 18',
  clipboardCheck: 'M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11',
  percent: 'M19 5L5 19M6.5 3.5a3 3 0 1 0 0 6 3 3 0 0 0 0-6zM17.5 14.5a3 3 0 1 0 0 6 3 3 0 0 0 0-6z',
  messageCircle: 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z',
  paperclip: 'M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48',
  user: 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
  userPlus: 'M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M8.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM20 8v6M23 11h-6',
  barChart: 'M12 20V10M18 20V4M6 20v-4',
  dollarSign: 'M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6',
  zoomIn: 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.35-4.35M11 8v6M8 11h6',
  zoomOut: 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.35-4.35M8 11h6',
  bell: 'M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0',
  gitBranch: 'M6 3v12M18 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM6 21a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM18 9a9 9 0 0 1-9 9',
  activity: 'M22 12h-4l-3 9L9 3l-3 9H2',
  target: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM12 18a6 6 0 1 0 0-12 6 6 0 0 0 0 12zM12 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
  move: 'M12 2v20M2 12h20M5 9l-3 3 3 3M19 9l3 3-3 3M9 5l3-3 3 3M9 19l3 3 3-3',
  megaphone: 'M3 11v2a2 2 0 0 0 2 2h1l2 6h2l-1.5-6H12l7 4V5l-7 4H6a2 2 0 0 0-2 2Zm5 4v5a2 2 0 0 0 4 0v-5',
  share: 'M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8M16 6l-4-4-4 4M12 2v13',
  bold: 'M6 4h8a4 4 0 0 1 0 8H6zM6 12h9a4 4 0 0 1 0 8H6z',
  italic: 'M19 4h-9M14 20H5M15 4L9 20',
  heading: 'M6 4v16M18 4v16M6 12h12',
  listBullet: 'M9 6h12M9 12h12M9 18h12M4 6h.01M4 12h.01M4 18h.01',
  listNumbered: 'M11 6h10M11 12h10M11 18h10M4 6h1v4M4 10h2M6 14a2 2 0 1 0-2 2h2a2 2 0 1 1-2 2H4',
  alignLeft: 'M4 6h16M4 12h10M4 18h13',
  alignCenter: 'M4 6h16M7 12h10M5 18h14',
  alignRight: 'M4 6h16M10 12h10M7 18h13',
  textColor: 'M6 15l4-10 4 10M7.5 11h5M4 20h16',
  unlink: 'M18.84 12.25l1.72-1.71a5 5 0 0 0-7.07-7.07l-1.72 1.71M5.17 11.75 3.46 13.46a5 5 0 0 0 7.07 7.07l1.71-1.71M8 8l8 8M2 2l20 20',
  heart: 'M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z',
  mail: 'M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zM22 6l-10 7L2 6',
  listChecks: 'M11 6h11M11 12h11M11 18h11M3 6l1.5 1.5L7 5M3 12l1.5 1.5L7 11M3 18l1.5 1.5L7 17',
};

const STROKE_ICONS = [
  'chevronLeft',
  'chevronRight',
  'chevronDown',
  'chevronsUp',
  'chevronsDown',
  'menu',
  'close',
  'refresh',
  'search',
  'unlock',
  'home',
  'plus',
  'layoutList',
  'layoutGrid',
  'fileDown',
  'fileSpreadsheet',
  'fileText',
  'fileUp',
  'download',
  'calendar',
  'layers',
  'fullscreen',
  'minimize',
  'star',
  'bookmark',
  'pencil',
  'trash',
  'clock',
  'link',
  'check',
  'minus',
  'columns',
  'sliders',
  'pieChart',
  'trendingUp',
  'clipboardCheck',
  'percent',
  'messageCircle',
  'paperclip',
  'user',
  'userPlus',
  'barChart',
  'dollarSign',
  'zoomIn',
  'zoomOut',
  'bell',
  'gitBranch',
  'activity',
  'target',
  'move',
  'megaphone',
  'share',
  'bold',
  'italic',
  'heading',
  'listBullet',
  'listNumbered',
  'alignLeft',
  'alignCenter',
  'alignRight',
  'textColor',
  'unlink',
  'heart',
  'mail',
  'listChecks',
];

defineProps({
  name: { type: String, required: true },
  size: { type: [Number, String], default: 20 },
  strokeWidth: { type: [Number, String], default: 2 },
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
      :stroke="STROKE_ICONS.includes(name) ? 'currentColor' : 'none'"
      :fill="STROKE_ICONS.includes(name) ? 'none' : 'currentColor'"
      :stroke-width="strokeWidth"
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
