export const PERMISSION_META_COLUMNS = [
  { key: 'permission', label: 'Tên quyền', defaultOn: true },
  { key: 'module', label: 'Module', defaultOn: false },
  { key: 'key', label: 'Mã quyền', defaultOn: false },
];

export const PERMISSION_FILTERS = [
  { key: 'scope', label: 'Phạm vi', defaultOn: true },
  { key: 'q', label: 'Tìm quyền', defaultOn: true },
  { key: 'module', label: 'Module', defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: true },
];

export const PERMISSION_STATUS_OPTIONS = [
  { value: '', label: 'Tất cả trạng thái' },
  { value: 'granted', label: 'Được cấp' },
  { value: 'denied', label: 'Không được cấp' },
  { value: 'override', label: 'Có sửa riêng' },
  { value: 'reserved', label: 'Quyền hệ thống' },
];

export const COLUMN_STORAGE_KEY = 'va-permissions-columns-v2';
export const FILTER_STORAGE_KEY = 'va-permissions-filters';
export const COLUMN_WIDTH_KEY = 'va-permissions-column-widths';
export const ZOOM_STORAGE_KEY = 'va-permissions-zoom';

export function roleColumnKey(code) {
  return `role:${code}`;
}

export function roleCodeFromColumn(key) {
  return key.startsWith('role:') ? key.slice(5) : null;
}

export function permissionColumns(roles = []) {
  return [
    ...PERMISSION_META_COLUMNS,
    ...roles.map((role) => ({
      key: roleColumnKey(role.code),
      label: role.label,
      defaultOn: true,
      roleCode: role.code,
    })),
  ];
}

export function loadVisibility(storageKey, items) {
  const defaults = {};
  for (const item of items) {
    defaults[item.key] = item.defaultOn;
  }

  try {
    const raw = localStorage.getItem(storageKey);
    if (!raw) {
      return defaults;
    }
    const parsed = JSON.parse(raw);
    if (!parsed || typeof parsed !== 'object') {
      return defaults;
    }
    const next = { ...defaults };
    for (const item of items) {
      if (typeof parsed[item.key] === 'boolean') {
        next[item.key] = parsed[item.key];
      }
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
