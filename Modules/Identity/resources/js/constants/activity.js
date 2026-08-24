export const ACTIVITY_ACTIONS = [
  { value: '', label: 'Tất cả thao tác' },
  { value: 'auth.login', label: 'Đăng nhập' },
  { value: 'auth.logout', label: 'Đăng xuất' },
  { value: 'view_as.activate', label: 'Xem thử vai trò' },
  { value: 'view_as.deactivate', label: 'Thoát xem thử' },
  { value: 'permission.grant', label: 'Cấp quyền' },
  { value: 'permission.deny', label: 'Tắt quyền' },
  { value: 'permission.revoke', label: 'Khôi phục quyền' },
  { value: 'team.create', label: 'Tạo nhóm' },
  { value: 'team.update', label: 'Cập nhật nhóm' },
  { value: 'team.delete', label: 'Xoá nhóm' },
  { value: 'shortcut.create', label: 'Tạo lối tắt' },
  { value: 'shortcut.update', label: 'Cập nhật lối tắt' },
  { value: 'shortcut.delete', label: 'Xoá lối tắt' },
];

export const ACTIVITY_COLUMNS = [
  { key: 'created_at', label: 'Thời gian', defaultOn: true },
  { key: 'actor_name', label: 'Người thực hiện', defaultOn: true },
  { key: 'actor_email', label: 'Email', defaultOn: false },
  { key: 'action', label: 'Loại thao tác', defaultOn: true },
  { key: 'description', label: 'Việc đã làm', defaultOn: true },
  { key: 'subject', label: 'Đối tượng', defaultOn: false },
  { key: 'properties', label: 'Chi tiết thêm', defaultOn: false },
  { key: 'ip_address', label: 'Địa chỉ mạng', defaultOn: true },
  { key: 'browser', label: 'Trình duyệt', defaultOn: false },
  { key: 'id', label: 'Mã bản ghi', defaultOn: false },
];

export const ACTIVITY_FILTERS = [
  { key: 'q', label: 'Tìm kiếm', defaultOn: true },
  { key: 'action', label: 'Loại thao tác', defaultOn: true },
  { key: 'actor_id', label: 'Người dùng', defaultOn: false },
  { key: 'date_range', label: 'Khoảng ngày', defaultOn: true },
  { key: 'ip', label: 'Địa chỉ mạng', defaultOn: false },
  { key: 'subject_type', label: 'Đối tượng', defaultOn: false },
];

export const COLUMN_STORAGE_KEY = 'va-activity-columns';
export const FILTER_STORAGE_KEY = 'va-activity-filters';

export function activityActionLabel(action) {
  return ACTIVITY_ACTIONS.find((item) => item.value === action)?.label ?? action ?? '';
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
