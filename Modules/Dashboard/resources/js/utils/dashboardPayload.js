/**
 * Chuẩn hoá payload dashboard: API có thể trả thẳng object, hoặc bọc
 * { data: {...} }. Computed/template không được đọc .months / Object.keys
 * trên undefined.
 */

export function unwrapOverview(payload) {
  if (isOverviewBody(payload)) return payload;
  if (isOverviewBody(payload.data)) return payload.data;
  return null;
}

export function unwrapTablePage(payload, fallback) {
  const empty = fallback ?? { data: [], total: 0 };
  if (Array.isArray(payload)) {
    return { data: payload, total: payload.length };
  }
  if (!isPlainObject(payload)) {
    return { ...empty, data: [] };
  }
  if (Array.isArray(payload.data)) return payload;
  if (isPlainObject(payload.data) && Array.isArray(payload.data.data)) {
    return payload.data;
  }
  return { ...empty, data: [] };
}

export function asRecord(value) {
  return isPlainObject(value) ? value : null;
}

export function asList(value) {
  return Array.isArray(value) ? value : [];
}

/** "2026-09" → "09/2026" — tránh ApexCharts nhận nhầm datetime rồi đọc locale.months. */
export function formatYearMonth(ym) {
  if (typeof ym !== 'string') return String(ym ?? '');
  const match = ym.match(/^(\d{4})-(\d{2})$/);
  return match ? `${match[2]}/${match[1]}` : ym;
}

function isPlainObject(value) {
  return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
}

function isOverviewBody(value) {
  return isPlainObject(value) && Boolean(value.kpis || value.timeline || value.status_breakdown || value.tasks_status_breakdown);
}
