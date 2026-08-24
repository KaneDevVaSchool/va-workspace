export const MEMBER_COLUMNS = [
  { key: 'person', label: 'Họ tên', defaultOn: true },
  { key: 'team', label: 'Nhóm', defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: true },
  { key: 'roles', label: 'Vai trò', defaultOn: false },
  { key: 'id', label: 'Mã thành viên', defaultOn: false },
];

export const MEMBER_FILTERS = [
  { key: 'q', label: 'Tìm kiếm', defaultOn: true },
  { key: 'team_id', label: 'Nhóm', defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: true },
  { key: 'role', label: 'Vai trò', defaultOn: false },
];

export const MEMBER_STATUS_OPTIONS = [
  { value: '', label: 'Tất cả trạng thái' },
  { value: 'active', label: 'Đang hoạt động' },
  { value: 'inactive', label: 'Ngừng hoạt động' },
];

export const COLUMN_STORAGE_KEY = 'va-wc-members-columns-v1';
export const FILTER_STORAGE_KEY = 'va-wc-members-filters';
export const COLUMN_WIDTH_KEY = 'va-wc-members-column-widths';
export const ZOOM_STORAGE_KEY = 'va-wc-members-zoom';

export function memberStatusLabel(status) {
  return status === 'active' ? 'Đang hoạt động' : 'Ngừng hoạt động';
}

export function memberRoles(member) {
  if (!Array.isArray(member?.roles) || member.roles.length === 0) {
    return [];
  }
  return member.roles.map((role) =>
    typeof role === 'string' ? { code: role, name: role } : { code: role.code, name: role.name },
  );
}

export function memberRolesText(member) {
  const roles = memberRoles(member);
  return roles.length ? roles.map((role) => role.name).join(', ') : '—';
}

export const FALLBACK_AVATAR_SRC = '/images/congnghe/brand/vas-white-mark.png';
export const FALLBACK_AVATAR_SRCSET =
  '/images/congnghe/brand/vas-white-mark.png 1x, /images/congnghe/brand/vas-white-mark@2x.png 2x';

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
