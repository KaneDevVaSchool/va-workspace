const WEEKDAY_SHORT = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];

export { WEEKDAY_SHORT };

/** Thứ Hai đầu tuần (lịch làm việc VN). */
export function startOfWeekMonday(date) {
  const d = new Date(date.getFullYear(), date.getMonth(), date.getDate());
  const day = d.getDay();
  const offset = day === 0 ? -6 : 1 - day;
  d.setDate(d.getDate() + offset);
  return d;
}

export function addDays(date, days) {
  const d = new Date(date.getFullYear(), date.getMonth(), date.getDate());
  d.setDate(d.getDate() + days);
  return d;
}

export function weekKeyFromDate(date) {
  const start = startOfWeekMonday(date);
  const y = start.getFullYear();
  const m = String(start.getMonth() + 1).padStart(2, '0');
  const day = String(start.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

export function parseIsoDate(value) {
  if (!value) return null;
  const str = String(value).trim();
  const parts = /^(\d{4})-(\d{2})-(\d{2})/.exec(str);
  if (parts) {
    return new Date(Number(parts[1]), Number(parts[2]) - 1, Number(parts[3]));
  }
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return null;
  return new Date(d.getFullYear(), d.getMonth(), d.getDate());
}

export function formatWeekRangeLabel(weekStart) {
  const end = addDays(weekStart, 6);
  const fmt = (d) => d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' });
  const endFmt = end.toLocaleDateString('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: end.getFullYear() !== weekStart.getFullYear() ? 'numeric' : undefined,
  });
  return `${fmt(weekStart)} – ${endFmt}`;
}

export function isoWeekNumber(date) {
  const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
  const dayNum = d.getUTCDay() || 7;
  d.setUTCDate(d.getUTCDate() + 4 - dayNum);
  const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
  return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
}

/**
 * @template T
 * @param {T[]} items
 * @param {(item: T) => Date|null} getDate
 */
export function groupItemsByWeek(items, getDate) {
  /** @type {Map<string, { key: string, weekStart: Date, label: string, weekNum: number, items: T[] }>} */
  const map = new Map();

  for (const item of items) {
    const date = getDate(item);
    if (!date) continue;
    const weekStart = startOfWeekMonday(date);
    const key = weekKeyFromDate(weekStart);
    let group = map.get(key);
    if (!group) {
      group = {
        key,
        weekStart,
        label: formatWeekRangeLabel(weekStart),
        weekNum: isoWeekNumber(weekStart),
        items: [],
      };
      map.set(key, group);
    }
    group.items.push(item);
  }

  return [...map.values()].sort((a, b) => b.weekStart - a.weekStart);
}
