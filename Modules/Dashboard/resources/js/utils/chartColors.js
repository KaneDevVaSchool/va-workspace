/**
 * Đọc màu thật từ CSS variable lúc runtime (không hard-code hex trong file
 * JS options — CLAUDE.md mục 12). Dùng cho mọi biểu đồ ApexCharts trong
 * module Dashboard.
 */
export function cssVar(name) {
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

/** Màu theo trạng thái dự án/công việc — dùng chung donut/bar. */
export function statusColor(status) {
  const map = {
    planning: '--color-border-strong',
    not_started: '--color-border-strong',
    in_progress: '--color-tertiary',
    under_review: '--color-gold',
    on_hold: '--color-warning',
    completed: '--color-success',
    cancelled: '--color-umber',
  };
  return cssVar(map[status] ?? '--color-border-strong');
}

/** Màu theo phân loại sức khoẻ dự án (Project Health). */
export function healthColor(health) {
  const map = {
    good: '--color-success',
    warning: '--color-warning',
    risk: '--color-danger',
  };
  return cssVar(map[health] ?? '--color-border-strong');
}
