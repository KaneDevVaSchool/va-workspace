const STORAGE_KEY = 'social:recent-picks:v1';
const MAX_ITEMS = 24;

function readAll() {
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    const parsed = raw ? JSON.parse(raw) : {};
    return parsed && typeof parsed === 'object' ? parsed : {};
  } catch {
    return {};
  }
}

function writeAll(data) {
  try {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
  } catch {
    // localStorage có thể bị chặn (chế độ ẩn danh) — bỏ qua, không chặn thao tác chính.
  }
}

/** @param {'emoji'|'sticker'} kind */
export function getRecentPicks(kind) {
  const all = readAll();
  return Array.isArray(all[kind]) ? all[kind] : [];
}

/**
 * Thêm 1 mục vào đầu danh sách "hay dùng", bỏ trùng, giới hạn MAX_ITEMS.
 * @param {'emoji'|'sticker'} kind
 * @param {unknown} item — với sticker là {id, emoji}; với emoji là chuỗi.
 * @param {(a: unknown, b: unknown) => boolean} [isSame]
 */
export function pushRecentPick(kind, item, isSame = (a, b) => a === b) {
  const all = readAll();
  const list = Array.isArray(all[kind]) ? all[kind] : [];
  const next = [item, ...list.filter((existing) => !isSame(existing, item))].slice(0, MAX_ITEMS);
  all[kind] = next;
  writeAll(all);
  return next;
}
