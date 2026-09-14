/**
 * Helper localStorage dùng chung cho mọi data-table trong module Dashboard —
 * cùng pattern loadVisibility/saveVisibility trong
 * Modules/Identity/resources/js/constants/activity.js (mẫu vàng).
 */
export function loadVisibility(storageKey, items) {
  const defaults = {};
  for (const item of items) defaults[item.key] = item.defaultOn;

  try {
    const raw = localStorage.getItem(storageKey);
    if (!raw) return defaults;
    const parsed = JSON.parse(raw);
    if (!parsed || typeof parsed !== 'object') return defaults;
    const next = { ...defaults };
    for (const item of items) {
      if (typeof parsed[item.key] === 'boolean') next[item.key] = parsed[item.key];
    }
    return next;
  } catch {
    return defaults;
  }
}

export function saveVisibility(storageKey, value) {
  try {
    localStorage.setItem(storageKey, JSON.stringify(value));
  } catch {
    // Bỏ qua nếu trình duyệt chặn localStorage.
  }
}

export function loadColumnWidths(storageKey) {
  try {
    const raw = localStorage.getItem(storageKey);
    const parsed = raw ? JSON.parse(raw) : {};
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) return parsed;
  } catch {
    // Bỏ qua nếu trình duyệt chặn localStorage.
  }
  return {};
}

export function saveColumnWidths(storageKey, value) {
  try {
    localStorage.setItem(storageKey, JSON.stringify(value));
  } catch {
    // Bỏ qua.
  }
}
