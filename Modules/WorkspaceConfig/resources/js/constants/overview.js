export const OVERVIEW_COLUMNS = [
  { key: 'name', label: 'Phòng ban', defaultOn: true },
  { key: 'is_active', label: 'Trạng thái', defaultOn: true },
  { key: 'has_config', label: 'Cấu hình', defaultOn: true },
  { key: 'director', label: 'Quản lý phòng ban', defaultOn: true },
  { key: 'member_count', label: 'Số thành viên', defaultOn: false },
  { key: 'code', label: 'Mã phòng ban', defaultOn: false },
  { key: 'criteria_count', label: 'Số tiêu chí đánh giá', defaultOn: false },
  { key: 'id', label: 'Mã bản ghi', defaultOn: false },
];

export const OVERVIEW_FILTERS = [
  { key: 'q', label: 'Tìm kiếm', defaultOn: true },
  { key: 'is_active', label: 'Trạng thái', defaultOn: true },
  { key: 'has_config', label: 'Cấu hình', defaultOn: true },
  { key: 'has_director', label: 'Quản lý phòng ban', defaultOn: true },
];

export const DIRECTOR_FILTER_OPTIONS = [
  { value: '', label: 'Tất cả phòng ban' },
  { value: 'yes', label: 'Đã có trưởng đơn vị' },
  { value: 'no', label: 'Chưa gán trưởng đơn vị' },
];

export const STATUS_FILTER_OPTIONS = [
  { value: '', label: 'Tất cả trạng thái' },
  { value: 'yes', label: 'Đang hoạt động' },
  { value: 'no', label: 'Ngừng hoạt động' },
];

export const CONFIG_FILTER_OPTIONS = [
  { value: '', label: 'Tất cả cấu hình' },
  { value: 'yes', label: 'Đã có cấu hình' },
  { value: 'no', label: 'Chưa có cấu hình' },
];

export const COLUMN_STORAGE_KEY = 'va-wc-overview-columns-v3';
export const FILTER_STORAGE_KEY = 'va-wc-overview-filters';
export const COLUMN_WIDTH_KEY = 'va-wc-overview-column-widths';
export const ZOOM_STORAGE_KEY = 'va-wc-overview-zoom';

export function directorName(department) {
  return department?.director?.name || '';
}

export function directorEmail(department) {
  return department?.director?.email || '';
}

export function departmentStatusLabel(isActive) {
  return isActive ? 'Đang hoạt động' : 'Ngừng hoạt động';
}

export function departmentConfigLabel(hasConfig) {
  return hasConfig ? 'Đã có cấu hình' : 'Chưa có cấu hình';
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
