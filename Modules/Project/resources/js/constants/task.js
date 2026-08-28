export const TASK_STATUSES = [
  { value: '', label: 'Tất cả trạng thái' },
  { value: 'not_started', label: 'Chưa bắt đầu' },
  { value: 'in_progress', label: 'Đang thực hiện' },
  { value: 'on_hold', label: 'Tạm dừng' },
  { value: 'completed', label: 'Hoàn thành' },
  { value: 'cancelled', label: 'Đã huỷ' },
];

export const TASK_STATUS_LABELS = {
  not_started: 'Chưa bắt đầu',
  in_progress: 'Đang thực hiện',
  on_hold: 'Tạm dừng',
  completed: 'Hoàn thành',
  cancelled: 'Đã huỷ',
};

/** Dot màu theo trạng thái — quy tắc §14 CLAUDE.md, không dùng badge/pill. */
export const TASK_STATUS_TONES = {
  not_started: 'neutral',
  in_progress: 'info',
  on_hold: 'warning',
  completed: 'success',
  cancelled: 'danger',
};

export const TASK_TYPE_LABELS = {
  task: 'Công việc',
  phase: 'Giai đoạn',
  category: 'Danh mục',
};

export const TASK_PRIORITY_LABELS = {
  low: 'Thấp',
  medium: 'Trung bình',
  high: 'Cao',
  urgent: 'Khẩn cấp',
};

export const TASK_COLUMNS = [
  { key: 'title', label: 'Tên công việc', defaultOn: true, always: true },
  { key: 'project', label: 'Dự án', defaultOn: true },
  { key: 'assignee', label: 'Người thực hiện', defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: true },
  { key: 'start_date', label: 'Ngày bắt đầu', defaultOn: true },
  { key: 'end_date', label: 'Ngày kết thúc', defaultOn: true },
  { key: 'progress_percent', label: 'Tiến độ', defaultOn: true },
  { key: 'actual_start_date', label: 'Bắt đầu thực tế', defaultOn: false },
  { key: 'actual_end_date', label: 'Kết thúc thực tế', defaultOn: false },
  { key: 'type', label: 'Loại', defaultOn: false },
  { key: 'priority', label: 'Mức độ ưu tiên', defaultOn: false },
  { key: 'creator', label: 'Người tạo', defaultOn: false },
  { key: 'id', label: 'Mã công việc', defaultOn: false },
];

export const TASK_FILTERS = [
  { key: 'q', label: 'Tìm kiếm', defaultOn: true },
  { key: 'project_id', label: 'Dự án', defaultOn: true },
  { key: 'assignee_id', label: 'Người thực hiện', defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: true },
  { key: 'date_from', label: 'Từ ngày', defaultOn: true },
  { key: 'date_to', label: 'Đến ngày', defaultOn: true },
];

export const COLUMN_STORAGE_KEY = 'va-task-columns-v1';
export const FILTER_STORAGE_KEY = 'va-task-filters-v1';
export const COLUMN_WIDTH_KEY = 'va-task-column-widths-v1';
export const ZOOM_STORAGE_KEY = 'va-task-zoom-v1';

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
