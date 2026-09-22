export const STATUS_OPTIONS = [
  { value: '', label: 'Tất cả trạng thái' },
  { value: 'pending', label: 'Chờ ghi nhận' },
  { value: 'reviewing', label: 'Đang xem xét' },
  { value: 'approved', label: 'Đã duyệt' },
  { value: 'rejected', label: 'Từ chối' },
  { value: 'done', label: 'Đã hoàn thành' },
];

export const STATUS_LABEL = {
  pending: 'Chờ ghi nhận',
  reviewing: 'Đang xem xét',
  approved: 'Đã duyệt',
  rejected: 'Từ chối',
  done: 'Đã hoàn thành',
};

export const STATUS_HINT = {
  pending: 'Chưa ai xem ghi nhận này.',
  reviewing: 'Superadmin đang xem xét yêu cầu.',
  approved: 'Yêu cầu đã được duyệt, đang chờ triển khai.',
  rejected: 'Yêu cầu không được thực hiện.',
  done: 'Tính năng đã hoàn thành.',
};

export const STATUS_TONE = {
  pending: 'neutral',
  reviewing: 'warning',
  approved: 'info',
  rejected: 'danger',
  done: 'success',
};

export const ADMIN_COLUMNS = [
  { key: 'created_at', label: 'Thời gian', defaultOn: true },
  { key: 'sender', label: 'Người gửi', defaultOn: true },
  { key: 'description', label: 'Yêu cầu', defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: true },
  { key: 'department', label: 'Phòng ban', defaultOn: false },
  { key: 'page', label: 'Trang đính kèm', defaultOn: false },
  { key: 'reviewer', label: 'Người xử lý', defaultOn: false },
  { key: 'expected_done_at', label: 'Ngày dự kiến', defaultOn: false },
  { key: 'id', label: 'Mã bản ghi', defaultOn: false },
];

export const MINE_COLUMNS = [
  { key: 'created_at', label: 'Thời gian', defaultOn: true },
  { key: 'description', label: 'Yêu cầu', defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: true },
  { key: 'page', label: 'Trang đính kèm', defaultOn: false },
  { key: 'reviewer', label: 'Người xử lý', defaultOn: false },
  { key: 'expected_done_at', label: 'Ngày dự kiến', defaultOn: false },
  { key: 'id', label: 'Mã bản ghi', defaultOn: false },
];

export const ADMIN_FILTERS = [
  { key: 'q', label: 'Tìm kiếm', defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: true },
  { key: 'department', label: 'Phòng ban', defaultOn: true },
  { key: 'date_from', label: 'Từ ngày', defaultOn: true },
  { key: 'date_to', label: 'Đến ngày', defaultOn: true },
];

export const MINE_FILTERS = [
  { key: 'q', label: 'Tìm kiếm', defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: true },
  { key: 'date_from', label: 'Từ ngày', defaultOn: true },
  { key: 'date_to', label: 'Đến ngày', defaultOn: true },
];

export const ADMIN_COLUMN_KEY = 'va-feature-request-admin-columns-v1';
export const ADMIN_FILTER_KEY = 'va-feature-request-admin-filters-v1';
export const ADMIN_WIDTH_KEY = 'va-feature-request-admin-widths-v1';
export const ADMIN_ZOOM_KEY = 'va-feature-request-admin-zoom-v1';

export const MINE_COLUMN_KEY = 'va-feature-request-mine-columns-v1';
export const MINE_FILTER_KEY = 'va-feature-request-mine-filters-v1';
export const MINE_WIDTH_KEY = 'va-feature-request-mine-widths-v1';
export const MINE_ZOOM_KEY = 'va-feature-request-mine-zoom-v1';

export const DESCRIPTION_MAX = 2000;
export const PROGRESS_NOTE_MAX = 500;
export const REJECT_REASON_MAX = 500;

export function loadVisibility(storageKey, items) {
  const defaults = {};
  for (const item of items) {
    defaults[item.key] = item.defaultOn;
  }

  try {
    const raw = localStorage.getItem(storageKey);
    if (!raw) return defaults;
    const parsed = JSON.parse(raw);
    if (!parsed || typeof parsed !== 'object') return defaults;
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

export function previewText(text, max = 96) {
  const value = String(text ?? '').replace(/\s+/g, ' ').trim();
  if (!value) return '—';
  if (value.length <= max) return value;
  return `${value.slice(0, max).trim()}…`;
}

export function localDateKey(iso) {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${date.getFullYear()}-${month}-${day}`;
}
