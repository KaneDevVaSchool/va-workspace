export const ACCOUNT_TYPES = [
  { value: 'admin', label: 'Quản trị viên' },
  { value: 'superadmin', label: 'Quản trị viên cấp cao' },
  { value: 'user', label: 'Người dùng' },
  { value: 'demo', label: 'Dùng thử' },
  { value: 'testing', label: 'Kiểm thử' },
];

export const STATUS_OPTIONS = [
  { value: '', label: 'Tất cả trạng thái' },
  { value: 'active', label: 'Đang sử dụng' },
  { value: 'renewing_soon', label: 'Chuẩn bị gia hạn' },
  { value: 'expiring_soon', label: 'Sắp hết hạn' },
  { value: 'expired', label: 'Đã hết hạn' },
];

export const STATUS_DOT_TONE = {
  active: 'success',
  renewing_soon: 'info',
  expiring_soon: 'warning',
  expired: 'danger',
};

export const CREDENTIAL_COLUMNS = [
  { key: 'name', label: 'Tên tài khoản', defaultOn: true },
  { key: 'provider', label: 'Nhà cung cấp', defaultOn: true },
  { key: 'account_type', label: 'Loại tài khoản', defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: true },
  { key: 'expires_at', label: 'Ngày hết hạn', defaultOn: true },
  { key: 'monthly_cost', label: 'Chi phí/tháng', defaultOn: false },
  { key: 'server_name', label: 'Server', defaultOn: false },
  { key: 'domain', label: 'Domain', defaultOn: false },
  { key: 'creator_name', label: 'Người tạo', defaultOn: false },
];

export const CREDENTIAL_FILTERS = [
  { key: 'q', label: 'Tìm kiếm', defaultOn: true },
  { key: 'provider_id', label: 'Nhà cung cấp', defaultOn: true },
  { key: 'account_type', label: 'Loại tài khoản', defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: true },
];

export const COLUMN_STORAGE_KEY = 'va-credential-columns-v1';
export const FILTER_STORAGE_KEY = 'va-credential-filters';
export const COLUMN_WIDTH_KEY = 'va-credential-column-widths';
export const ZOOM_STORAGE_KEY = 'va-credential-zoom';

export function accountTypeLabel(value) {
  return ACCOUNT_TYPES.find((item) => item.value === value)?.label ?? value ?? '';
}

export function statusLabel(value) {
  return STATUS_OPTIONS.find((item) => item.value === value)?.label ?? value ?? '';
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
