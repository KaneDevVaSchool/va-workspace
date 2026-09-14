export const ACCOUNT_TYPES = [
  { value: 'admin', label: 'Quản trị viên' },
  { value: 'superadmin', label: 'Quản trị viên cấp cao' },
  { value: 'user', label: 'Người dùng' },
  { value: 'demo', label: 'Dùng thử' },
  { value: 'testing', label: 'Kiểm thử' },
  { value: 'phu_huynh', label: 'Phụ huynh' },
  { value: 'giao_vien', label: 'Giáo viên' },
  { value: 'hoc_sinh', label: 'Học sinh' },
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
  { key: 'department', label: 'Phòng ban', defaultOn: true },
  { key: 'provider', label: 'Nhà cung cấp', defaultOn: true },
  { key: 'account_type', label: 'Loại tài khoản', defaultOn: true },
  { key: 'email', label: 'Email đăng nhập', defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: true },
  { key: 'expires_at', label: 'Ngày hết hạn', defaultOn: true },
  { key: 'access_url', label: 'Link truy cập', defaultOn: false },
  { key: 'monthly_cost', label: 'Chi phí/tháng', defaultOn: false },
  { key: 'server_name', label: 'Server', defaultOn: false },
  { key: 'domain', label: 'Domain', defaultOn: false },
  { key: 'creator_name', label: 'Người tạo', defaultOn: true },
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

/**
 * Số ngày còn lại đến hạn từ hôm nay (số nguyên, âm nếu đã quá hạn).
 * Trả về null nếu không có ngày hết hạn (tài khoản không có hạn dùng).
 */
export function daysUntil(dateStr) {
  if (!dateStr) return null;
  const target = new Date(dateStr);
  if (Number.isNaN(target.getTime())) return null;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  target.setHours(0, 0, 0, 0);
  return Math.round((target.getTime() - today.getTime()) / 86400000);
}

/** Nhãn "Còn X ngày" / "Đã quá hạn X ngày" dùng chung List + Detail. */
export function countdownLabel(dateStr) {
  const days = daysUntil(dateStr);
  if (days === null) return '';
  if (days < 0) return `Đã quá hạn ${Math.abs(days)} ngày`;
  if (days === 0) return 'Hết hạn hôm nay';
  return `Còn ${days} ngày`;
}

/** Tone màu cho countdown — dùng token đã có, khớp ngưỡng backend (EXPIRING_SOON_DAYS/RENEWING_SOON_DAYS). */
export function countdownTone(dateStr) {
  const days = daysUntil(dateStr);
  if (days === null) return null;
  if (days <= 7) return 'danger';
  if (days <= 30) return 'warning';
  return null;
}

/** true nếu còn ≤7 ngày (kể cả đã quá hạn) — làm nổi bật countdown (đậm + pulse + icon). */
export function isCountdownUrgent(dateStr) {
  const days = daysUntil(dateStr);
  return days !== null && days <= 7;
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
