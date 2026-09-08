import {
  addDays,
  parseYmd,
  toYmd,
  WEEKDAY_SHORT,
} from './task.js';

export const GANTT_ROW_HEIGHT = 46;
export const GANTT_HEAD_GROUP_H = 30;
export const GANTT_HEAD_UNIT_H = 35;
export const GANTT_BAR_H = 16;
export const GANTT_BAR_TOP = 15;
export const GANTT_DEFAULT_LEFT_WIDTH = 560;
export const GANTT_MIN_LEFT_WIDTH = 280;
export const GANTT_DEFAULT_DAY_WIDTH = 40;
export const GANTT_ZOOM_STEPS = [24, 32, 40, 52, 64];
export const GANTT_PAD_BEFORE = 7;
export const GANTT_PAD_AFTER = 14;
export const GANTT_EXTEND_DAYS = 14;

export const GANTT_COL_STORAGE = 'va-project-gantt-cols-v1';
export const GANTT_WIDTH_STORAGE = 'va-project-gantt-col-widths-v1';
export const GANTT_LEFT_STORAGE = 'va-project-gantt-left-width-v1';
export const GANTT_ZOOM_STORAGE = 'va-project-gantt-zoom-v1';
export const GANTT_SCALE_STORAGE = 'va-project-gantt-scale-v1';
export const GANTT_SORT_STORAGE = 'va-project-gantt-sort-v1';
export const GANTT_COLLAPSE_STORAGE = 'va-project-gantt-collapsed-v1';

export const GANTT_COLUMNS = [
  { key: 'deadline', label: 'Hạn', defaultOn: true, width: 132, align: 'center', sortBy: 'deadline' },
  { key: 'outline', label: 'Thứ tự', defaultOn: true, width: 72, align: 'center', sortBy: 'outline' },
  { key: 'title', label: 'Công việc', defaultOn: true, always: true, width: 220, sortBy: 'title' },
  { key: 'assignee', label: 'Thực hiện', defaultOn: true, width: 112 },
  { key: 'status', label: 'Trạng thái', defaultOn: true, width: 128, align: 'center', sortBy: 'status' },
  { key: 'percent', label: 'Tiến độ', defaultOn: true, width: 148, align: 'center', sortBy: 'progress_percent' },
  { key: 'start_date', label: 'Bắt đầu', defaultOn: true, width: 104, sortBy: 'start_date' },
  { key: 'end_date', label: 'Kết thúc', defaultOn: true, width: 104, sortBy: 'end_date' },
  { key: 'actual_start_date', label: 'BĐ thực tế', defaultOn: false, width: 110, sortBy: 'actual_start_date' },
  { key: 'actual_end_date', label: 'KT thực tế', defaultOn: false, width: 110, sortBy: 'actual_end_date' },
  { key: 'duration', label: 'Thời gian', defaultOn: false, width: 96, align: 'center' },
  { key: 'weight', label: 'Tỷ trọng', defaultOn: false, width: 88, align: 'center', sortBy: 'weight' },
  { key: 'code', label: 'Mã công việc', defaultOn: false, width: 120, sortBy: 'code' },
  { key: 'type', label: 'Loại công việc', defaultOn: false, width: 128, sortBy: 'type' },
  { key: 'priority', label: 'Ưu tiên', defaultOn: false, width: 120, sortBy: 'priority' },
  { key: 'parent', label: 'Công việc cha', defaultOn: false, width: 160 },
  { key: 'manager', label: 'Người quản lý', defaultOn: false, width: 140 },
  { key: 'watchers', label: 'Theo dõi/phối hợp', defaultOn: false, width: 148 },
  { key: 'estimated_hours', label: 'Thời gian dự kiến', defaultOn: false, width: 140 },
  { key: 'worklog_hours', label: 'Thời gian thực hiện', defaultOn: false, width: 148 },
  { key: 'attachments_count', label: 'Số file đính kèm', defaultOn: false, width: 128, align: 'center' },
  { key: 'variance_days', label: 'Chênh lệch', defaultOn: false, width: 104, align: 'center' },
  { key: 'created_at', label: 'Ngày tạo', defaultOn: false, width: 110, sortBy: 'created_at' },
  { key: 'updated_at', label: 'Ngày cập nhật', defaultOn: false, width: 120, sortBy: 'updated_at' },
  { key: 'description', label: 'Mô tả', defaultOn: false, width: 200 },
];

export const GANTT_SORTS = [
  { key: 'outline', label: 'Thứ tự' },
  { key: 'start_date', label: 'Bắt đầu' },
  { key: 'end_date', label: 'Kết thúc' },
  { key: 'title', label: 'Công việc' },
  { key: 'status', label: 'Trạng thái' },
  { key: 'progress_percent', label: 'Tiến độ' },
  { key: 'deadline', label: 'Hạn' },
];

export const GANTT_SCALES = [
  { value: 'day', label: 'Theo ngày' },
  { value: 'week', label: 'Theo tuần' },
  { value: 'month', label: 'Theo tháng' },
];

export function startOfToday() {
  const now = new Date();
  return new Date(now.getFullYear(), now.getMonth(), now.getDate());
}

export function daysBetween(a, b) {
  return Math.round((b.getTime() - a.getTime()) / 86400000);
}

export function formatViDate(value) {
  const date = value instanceof Date ? value : parseYmd(value);
  if (!date) return '—';
  const dd = String(date.getDate()).padStart(2, '0');
  const mm = String(date.getMonth() + 1).padStart(2, '0');
  return `${dd}/${mm}/${date.getFullYear()}`;
}

export function monthGroupLabel(date) {
  const mm = String(date.getMonth() + 1).padStart(2, '0');
  return `Tháng ${mm}, ${date.getFullYear()}`;
}

export function weekdayIndex(date) {
  return (date.getDay() + 6) % 7;
}

export function isWeekend(date) {
  const day = date.getDay();
  return day === 0 || day === 6;
}

export function durationDays(start, end) {
  const a = parseYmd(start);
  const b = parseYmd(end);
  if (!a || !b || b < a) return 0;
  return daysBetween(a, b) + 1;
}

export function openOverdueDays(task, today = startOfToday()) {
  if (!task?.is_overdue || !task.end_date) return null;
  const end = parseYmd(task.end_date);
  if (!end || end >= today) return null;
  return daysBetween(end, today);
}

/** Số ngày còn lại đến hạn: dương = còn hạn, 0 = hôm nay, âm = quá hạn. */
export function remainingDeadlineDays(task, today = startOfToday()) {
  const end = parseYmd(task?.end_date);
  if (!end) return null;
  return daysBetween(today, end);
}

/**
 * Nhãn cột Hạn — chữ thường, không pill.
 * Việc đã đóng: Đúng hạn / Trễ X ngày. Việc mở: Còn X ngày / Hôm nay / Quá hạn X ngày.
 */
export function deadlineDisplay(task, today = startOfToday()) {
  if (task?.status === 'cancelled') return { text: '—', tone: 'muted' };
  if (task?.status === 'completed') {
    const late = Number(task.overdue_days) || 0;
    if (late > 0) return { text: `Trễ ${late} ngày`, tone: 'danger' };
    return { text: task.end_date ? 'Đúng hạn' : '—', tone: task.end_date ? 'success' : 'muted' };
  }
  const days = remainingDeadlineDays(task, today);
  if (days == null) return { text: '—', tone: 'muted' };
  if (days < 0) return { text: `Quá hạn ${-days} ngày`, tone: 'danger' };
  if (days === 0) return { text: 'Hôm nay', tone: 'warning' };
  return { text: `Còn ${days} ngày`, tone: days <= 7 ? 'warning' : 'success' };
}

function collectDates(node, acc) {
  const start = parseYmd(node.start_date);
  const end = parseYmd(node.end_date);
  if (start) acc.starts.push(start);
  if (end) acc.ends.push(end);
  for (const child of node.children || []) collectDates(child, acc);
  return acc;
}

export function effectiveSpan(node) {
  const ownStart = parseYmd(node.start_date);
  const ownEnd = parseYmd(node.end_date);
  if (ownStart && ownEnd) {
    return { start: ownStart, end: ownEnd, own: true };
  }
  const acc = collectDates(node, { starts: [], ends: [] });
  if (!acc.starts.length || !acc.ends.length) return null;
  const start = new Date(Math.min(...acc.starts.map((d) => d.getTime())));
  const end = new Date(Math.max(...acc.ends.map((d) => d.getTime())));
  return { start, end, own: Boolean(ownStart || ownEnd) };
}

export function peopleOf(task) {
  const list = [];
  const seen = new Set();
  const add = (user) => {
    if (!user?.id || seen.has(user.id)) return;
    seen.add(user.id);
    list.push(user);
  };
  add(task.assignee);
  for (const user of task.collaborators || []) add(user);
  return list;
}

function hasMatchingDescendant(node, matches) {
  for (const child of node.children || []) {
    if (matches(child) || hasMatchingDescendant(child, matches)) return true;
  }
  return false;
}

export function matchesGanttFilter(task, filter) {
  if (!filter || filter === 'all') return true;
  if (filter === 'overdue') return Boolean(task.is_overdue);
  return task.status === filter;
}

export function flattenGanttRows(nodes, collapsedIds, filter) {
  const rows = [];
  const matches = (node) => matchesGanttFilter(node, filter);

  const walk = (list, depth) => {
    for (const node of list || []) {
      const kids = node.children || [];
      const selfMatch = matches(node);
      const childMatch = hasMatchingDescendant(node, matches);
      if (!selfMatch && !childMatch) continue;
      const collapsed = collapsedIds.has(node.id);
      rows.push({
        ...node,
        depth,
        hasChildren: kids.length > 0,
        collapsed,
      });
      if (kids.length && !collapsed) walk(kids, depth + 1);
    }
  };

  walk(nodes, 0);
  return rows;
}

function compareValue(a, b, dir) {
  const mul = dir === 'desc' ? -1 : 1;
  if (a == null && b == null) return 0;
  if (a == null) return 1;
  if (b == null) return -1;
  if (typeof a === 'number' && typeof b === 'number') return (a - b) * mul;
  return String(a).localeCompare(String(b), 'vi', { numeric: true }) * mul;
}

function sortKey(node, sortBy) {
  if (sortBy === 'outline') return node.wbs_code || '';
  if (sortBy === 'deadline') return remainingDeadlineDays(node) ?? Number.POSITIVE_INFINITY;
  if (sortBy === 'progress_percent') return Number(node.progress_percent) || 0;
  if (sortBy === 'weight') return Number(node.weight) || 0;
  return node[sortBy] ?? '';
}

export function sortTaskTree(nodes, sortBy, sortDir) {
  const copy = (nodes || []).map((node) => ({
    ...node,
    children: sortTaskTree(node.children || [], sortBy, sortDir),
  }));
  if (!sortBy || sortBy === 'outline') {
    if (sortDir === 'desc') copy.reverse();
    return copy;
  }
  copy.sort((a, b) => compareValue(sortKey(a, sortBy), sortKey(b, sortBy), sortDir));
  return copy;
}

export function collectParentIds(nodes, acc = []) {
  for (const node of nodes || []) {
    if (node.children?.length) {
      acc.push(node.id);
      collectParentIds(node.children, acc);
    }
  }
  return acc;
}

export function loadJson(key, fallback) {
  try {
    const raw = localStorage.getItem(key);
    if (!raw) return fallback;
    return JSON.parse(raw);
  } catch {
    return fallback;
  }
}

export function saveJson(key, value) {
  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch {
    // Bỏ qua nếu trình duyệt chặn localStorage.
  }
}

function startOfWeekMonday(date) {
  const next = new Date(date.getFullYear(), date.getMonth(), date.getDate());
  next.setDate(next.getDate() - weekdayIndex(next));
  return next;
}

export function buildTimeline(rangeStart, rangeEnd, scale, dayWidth) {
  const units = [];
  const groups = [];
  if (!rangeStart || !rangeEnd || rangeEnd < rangeStart) {
    return { units, groups, totalWidth: 0, pxPerDay: dayWidth, origin: rangeStart };
  }

  if (scale === 'week') {
    const pxPerDay = dayWidth / 7;
    let cursor = startOfWeekMonday(rangeStart);
    const origin = new Date(cursor);
    const last = startOfWeekMonday(rangeEnd);
    let left = 0;
    let group = null;
    while (cursor <= last) {
      const width = dayWidth;
      const monthKey = `${cursor.getFullYear()}-${cursor.getMonth()}`;
      if (!group || group.key !== monthKey) {
        group = {
          key: monthKey,
          label: monthGroupLabel(cursor),
          left,
          width: 0,
        };
        groups.push(group);
      }
      group.width += width;
      units.push({
        key: toYmd(cursor),
        date: new Date(cursor),
        label: String(cursor.getDate()),
        sub: 'Tuần',
        left,
        width,
        weekend: false,
      });
      left += width;
      cursor = addDays(cursor, 7);
    }
    return { units, groups, totalWidth: left, pxPerDay, origin };
  }

  if (scale === 'month') {
    const pxPerDay = dayWidth / 10;
    let cursor = new Date(rangeStart.getFullYear(), rangeStart.getMonth(), 1);
    const origin = new Date(cursor);
    const last = new Date(rangeEnd.getFullYear(), rangeEnd.getMonth(), 1);
    let left = 0;
    let group = null;
    while (cursor <= last) {
      const days = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0).getDate();
      const width = days * pxPerDay;
      const yearKey = String(cursor.getFullYear());
      if (!group || group.key !== yearKey) {
        group = { key: yearKey, label: String(cursor.getFullYear()), left, width: 0 };
        groups.push(group);
      }
      group.width += width;
      units.push({
        key: `${cursor.getFullYear()}-${cursor.getMonth()}`,
        date: new Date(cursor),
        label: `Thg ${cursor.getMonth() + 1}`,
        sub: '',
        left,
        width,
        weekend: false,
      });
      left += width;
      cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1);
    }
    return { units, groups, totalWidth: left, pxPerDay, origin };
  }

  const pxPerDay = dayWidth;
  const origin = new Date(rangeStart);
  let cursor = new Date(rangeStart);
  let left = 0;
  let group = null;
  while (cursor <= rangeEnd) {
    const monthKey = `${cursor.getFullYear()}-${cursor.getMonth()}`;
    if (!group || group.key !== monthKey) {
      group = {
        key: monthKey,
        label: monthGroupLabel(cursor),
        left,
        width: 0,
      };
      groups.push(group);
    }
    group.width += dayWidth;
    units.push({
      key: toYmd(cursor),
      date: new Date(cursor),
      label: String(cursor.getDate()),
      sub: WEEKDAY_SHORT[weekdayIndex(cursor)],
      left,
      width: dayWidth,
      weekend: isWeekend(cursor),
    });
    left += dayWidth;
    cursor = addDays(cursor, 1);
  }
  return { units, groups, totalWidth: left, pxPerDay, origin };
}

export function dateToX(date, rangeStart, pxPerDay) {
  if (!date || !rangeStart) return 0;
  return daysBetween(rangeStart, date) * pxPerDay;
}

export function barLayout(span, rangeStart, pxPerDay) {
  if (!span?.start || !span?.end || !rangeStart || !pxPerDay) return null;
  const left = dateToX(span.start, rangeStart, pxPerDay);
  const width = Math.max(pxPerDay, (daysBetween(span.start, span.end) + 1) * pxPerDay);
  return { left, width };
}

export function tailLayout(task, span, rangeStart, pxPerDay, today = startOfToday()) {
  if (!span?.end || !pxPerDay) return null;
  let tailEnd = null;
  const actualEnd = parseYmd(task.actual_end_date);
  if (actualEnd && actualEnd > span.end) tailEnd = actualEnd;
  else if (task.is_overdue && today > span.end) tailEnd = today;
  if (!tailEnd) return null;
  const left = dateToX(addDays(span.end, 1), rangeStart, pxPerDay);
  const width = Math.max(0, (daysBetween(span.end, tailEnd)) * pxPerDay);
  if (width <= 0) return null;
  return { left, width };
}
