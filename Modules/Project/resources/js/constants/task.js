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

/** Tone pill/tab theo trạng thái — khớp bảng dự án (primary / gold / success / umber). */
export const TASK_STATUS_TONES = {
  not_started: 'tertiary',
  in_progress: 'primary',
  on_hold: 'gold',
  completed: 'success',
  cancelled: 'umber',
};

export const TASK_TYPE_LABELS = {
  task: 'Công việc',
  phase: 'Giai đoạn',
  category: 'Danh mục',
};

export const TASK_TYPE_TONES = {
  task: 'info',
  phase: 'gold',
  category: 'violet',
};

export const TASK_PRIORITY_LABELS = {
  low: 'Thấp',
  medium: 'Trung bình',
  high: 'Cao',
  urgent: 'Khẩn cấp',
};

export const TASK_PRIORITY_TONES = {
  low: 'neutral',
  medium: 'info',
  high: 'gold',
  urgent: 'danger',
};

export const TASK_TABS = [
  { key: 'all', label: 'Tất cả', tone: 'primary' },
  { key: 'in_progress', label: 'Đang thực hiện', tone: 'info' },
  { key: 'completed', label: 'Hoàn thành', tone: 'success' },
  { key: 'on_hold', label: 'Tạm dừng', tone: 'gold' },
  { key: 'not_started', label: 'Chưa bắt đầu', tone: 'warning' },
  { key: 'cancelled', label: 'Đã huỷ', tone: 'umber' },
  { key: 'my_tasks', label: 'Bạn thực hiện', tone: 'tertiary' },
  { key: 'overdue', label: 'Quá hạn', tone: 'danger' },
];

export const TASK_PROGRESS_TYPE_LABELS = {
  percent: 'Theo phần trăm',
  quantity: 'Theo khối lượng',
};

/** Gợi ý datalist cho "Kết quả đánh giá" — tự do, KHÔNG ràng buộc enum. */
export const TASK_SCORE_RESULT_SUGGESTIONS = ['Đạt', 'Không đạt', 'Xuất sắc', 'Cần cải thiện'];

export const TASK_STATUS_TAB_KEYS = ['not_started', 'in_progress', 'on_hold', 'completed', 'cancelled'];

export const TASK_COLUMNS = [
  { key: 'code', label: 'Mã công việc', defaultOn: true },
  { key: 'title', label: 'Tên công việc', defaultOn: true, always: true },
  { key: 'project', label: 'Dự án', defaultOn: true },
  { key: 'assignee', label: 'Người thực hiện', defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: true },
  { key: 'priority', label: 'Mức độ ưu tiên', defaultOn: true },
  { key: 'start_date', label: 'Ngày bắt đầu', defaultOn: true },
  { key: 'end_date', label: 'Ngày kết thúc', defaultOn: true },
  { key: 'progress_percent', label: 'Tiến độ', defaultOn: true },
  { key: 'type', label: 'Loại', defaultOn: false },
  { key: 'actual_start_date', label: 'Bắt đầu thực tế', defaultOn: false },
  { key: 'actual_end_date', label: 'Kết thúc thực tế', defaultOn: false },
  { key: 'creator', label: 'Người tạo', defaultOn: false },
  { key: 'created_at', label: 'Ngày tạo', defaultOn: false },
  { key: 'updated_at', label: 'Ngày cập nhật', defaultOn: false },
  { key: 'parent', label: 'Công việc cha', defaultOn: false },
  { key: 'attachments_count', label: 'Số file đính kèm', defaultOn: false },
  { key: 'estimated_hours', label: 'Thời gian dự kiến (giờ)', defaultOn: false },
  { key: 'worklog_hours', label: 'Thời gian thực hiện (giờ)', defaultOn: false },
  { key: 'manager', label: 'Người quản lý', defaultOn: false },
  { key: 'accepted_by', label: 'Người đã nhận', defaultOn: false },
  { key: 'weight', label: 'Tỷ trọng (%)', defaultOn: false },
  { key: 'is_overdue', label: 'Quá hạn', defaultOn: false },
  { key: 'variance_days', label: 'Chênh lệch (ngày)', defaultOn: false },
];

export const TASK_FILTERS = [
  { key: 'project_id', label: 'Dự án', defaultOn: false },
  { key: 'assignee_id', label: 'Người thực hiện', defaultOn: false },
  { key: 'date_from', label: 'Từ ngày', defaultOn: false },
  { key: 'date_to', label: 'Đến ngày', defaultOn: false },
];

export const COLUMN_STORAGE_KEY = 'va-task-columns-v2';
export const FILTER_STORAGE_KEY = 'va-task-filters-v2';
export const COLUMN_WIDTH_KEY = 'va-task-column-widths-v2';
export const ZOOM_STORAGE_KEY = 'va-task-zoom-v1';
export const VIEW_MODE_KEY = 'va-task-view-mode';
export const KANBAN_GROUP_KEY = 'va-task-kanban-group';
export const KANBAN_ASSIGNEES_KEY = 'va-task-kanban-assignees';
export const COLLAPSED_GROUPS_KEY = 'va-task-collapsed-groups';

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
