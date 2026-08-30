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
  support: 'Phụ trợ',
  assist: 'Hỗ trợ',
  important: 'Quan trọng',
  high_priority: 'Ưu tiên cao',
  strategic: 'Chiến lược / Sống còn',
  low: 'Thấp',
  medium: 'Trung bình',
  high: 'Cao',
  urgent: 'Khẩn cấp',
};

export const TASK_PRIORITY_TONES = {
  support: 'neutral',
  assist: 'info',
  important: 'tertiary',
  high_priority: 'gold',
  strategic: 'danger',
  low: 'neutral',
  medium: 'info',
  high: 'gold',
  urgent: 'danger',
};

export const TASK_DELEGATION_STATUS_LABELS = {
  pending: 'Chờ tiếp nhận',
  accepted: 'Đã tiếp nhận',
  in_progress: 'Đang thực hiện',
  done: 'Hoàn thành',
  rejected: 'Từ chối',
};

export const TASK_DELEGATION_STATUS_TONES = {
  pending: 'gold',
  accepted: 'info',
  in_progress: 'primary',
  done: 'success',
  rejected: 'umber',
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
  percent: 'Theo % người dùng tự cập nhật',
  quantity: 'Theo tỷ lệ hoàn thành khối lượng công việc',
  checklist: 'Theo tỷ lệ hoàn thành đầu việc',
  child_weight: 'Theo tỷ trọng công việc con',
  timeline: 'Tự động theo thời gian hoàn thành công việc',
  average: 'Theo bình quân % tiến độ các công việc thuộc dự án',
  duration_weighted: 'Theo tỷ trọng ngày thực hiện',
  task_weighted: 'Theo tỷ trọng công việc',
};

/** 3 cách tính tiến độ khi tạo việc / tạo dự án — khớp form hệ cũ. */
export const TASK_PROGRESS_METHOD_OPTIONS = [
  {
    value: 'average',
    label: 'Theo bình quân % tiến độ các công việc thuộc dự án',
    description:
      'Ví dụ dự án gồm 2 công việc A và B. Công việc A tiến độ 40%, công việc B tiến độ 60%. Tiến độ dự án là (60+40)/2 = 50%',
  },
  {
    value: 'duration_weighted',
    label: 'Theo tỷ trọng ngày thực hiện',
    description:
      'Ví dụ dự án gồm 2 công việc A và B. Công việc A yêu cầu thực hiện trong 4 ngày, tiến độ 40% Công việc B yêu cầu thực hiện trong 6 ngày, tiến độ 50% Tiến độ dự án là ((4*40 + 6*50)/(4*100 + 6*100)) * 100 = 46%',
  },
  {
    value: 'task_weighted',
    label: 'Theo tỷ trọng công việc',
    description:
      'Ví dụ Dự án gồm 2 công việc A và B Công việc A có Tỷ trọng là 40, tiến độ là 50% Công việc B có Tỷ trọng là 30, tiến độ là 40% Tiến độ của dự án là [(40x50)+(30x40)]/(40+30)=45.71%',
  },
];

/** Gợi ý datalist cho "Kết quả đánh giá" — tự do, KHÔNG ràng buộc enum. */
export const TASK_SCORE_RESULT_SUGGESTIONS = ['Đạt', 'Không đạt', 'Xuất sắc', 'Cần cải thiện'];

export const TASK_STATUS_TAB_KEYS = ['not_started', 'in_progress', 'on_hold', 'completed', 'cancelled'];

export const TASK_COLUMNS = [
  { key: 'code', label: 'Mã công việc', defaultOn: true },
  { key: 'title', label: 'Tên công việc', defaultOn: true, always: true },
  { key: 'project', label: 'Dự án', defaultOn: true },
  { key: 'assignee', label: 'Người thực hiện', defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: true },
  { key: 'priority', label: 'Mức độ quan trọng', defaultOn: true },
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

export const COLUMN_STORAGE_KEY = 'va-task-columns-v2';
export const COLUMN_WIDTH_KEY = 'va-task-column-widths-v2';
export const ZOOM_STORAGE_KEY = 'va-task-zoom-v1';
export const VIEW_MODE_KEY = 'va-task-view-mode';
export const KANBAN_GROUP_KEY = 'va-task-kanban-group';
export const KANBAN_ASSIGNEES_KEY = 'va-task-kanban-assignees';
export const COLLAPSED_GROUPS_KEY = 'va-task-collapsed-groups';
export const LIST_GROUP_KEY = 'va-task-list-group';
export const CALENDAR_MODE_KEY = 'va-task-calendar-mode';

export const CALENDAR_MODES = [
  { value: 'month', label: 'Xem theo tháng', short: 'Tháng' },
  { value: 'week', label: 'Xem theo tuần', short: 'Tuần' },
];

export const CALENDAR_MODE_VALUES = CALENDAR_MODES.map((item) => item.value);

export const WEEKDAY_SHORT = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];

export function toYmd(date) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

export function parseYmd(value) {
  if (!value) return null;
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value));
  if (!match) return null;
  return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
}

export function startOfWeekMonday(date) {
  const next = new Date(date.getFullYear(), date.getMonth(), date.getDate());
  const dow = (next.getDay() + 6) % 7;
  next.setDate(next.getDate() - dow);
  return next;
}

export function addDays(date, days) {
  const next = new Date(date.getFullYear(), date.getMonth(), date.getDate());
  next.setDate(next.getDate() + days);
  return next;
}

export function calendarOverlapRange(mode, cursor) {
  const c = new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate());
  if (mode === 'week') {
    const start = startOfWeekMonday(c);
    return { from: toYmd(start), to: toYmd(addDays(start, 6)) };
  }
  const first = new Date(c.getFullYear(), c.getMonth(), 1);
  const start = startOfWeekMonday(first);
  const last = new Date(c.getFullYear(), c.getMonth() + 1, 0);
  return { from: toYmd(start), to: toYmd(addDays(startOfWeekMonday(last), 6)) };
}

export const LIST_GROUP_OPTIONS = [
  { value: 'deadline', label: 'Cảnh báo đến hạn' },
  { value: 'status', label: 'Trạng thái' },
  { value: 'type', label: 'Loại công việc' },
  { value: 'priority', label: 'Mức độ quan trọng' },
  { value: 'project', label: 'Dự án' },
  { value: 'date', label: 'Theo ngày' },
];

export const LIST_GROUP_MODES = LIST_GROUP_OPTIONS.map((item) => item.value);

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
