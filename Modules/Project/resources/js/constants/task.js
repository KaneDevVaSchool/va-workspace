export const TASK_STATUSES = [
  { value: '', label: 'Tất cả trạng thái' },
  { value: 'not_started', label: 'Chưa bắt đầu' },
  { value: 'in_progress', label: 'Đang thực hiện' },
  { value: 'under_review', label: 'Đang đánh giá' },
  { value: 'on_hold', label: 'Tạm dừng' },
  { value: 'completed', label: 'Hoàn thành' },
  { value: 'cancelled', label: 'Đã huỷ' },
];

export const TASK_STATUS_LABELS = {
  not_started: 'Chưa bắt đầu',
  in_progress: 'Đang thực hiện',
  under_review: 'Đang đánh giá',
  on_hold: 'Tạm dừng',
  completed: 'Hoàn thành',
  cancelled: 'Đã huỷ',
};

/** Tone pill/tab theo trạng thái — khớp bảng dự án (primary / gold / success / umber). */
export const TASK_STATUS_TONES = {
  not_started: 'tertiary',
  in_progress: 'primary',
  under_review: 'tertiary',
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
  RK: 'Rất khó',
  KH: 'Khó',
  TB: 'Trung bình',
  DE: 'Dễ',
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
  RK: 'danger',
  KH: 'gold',
  TB: 'tertiary',
  DE: 'neutral',
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
  { key: 'under_review', label: 'Đang đánh giá', tone: 'tertiary' },
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

export const TASK_STATUS_TAB_KEYS = ['not_started', 'in_progress', 'under_review', 'on_hold', 'completed', 'cancelled'];

export const TASK_COLUMNS = [
  { key: 'code', label: 'Mã công việc', defaultOn: true },
  { key: 'title', label: 'Tên công việc', defaultOn: true, always: true },
  { key: 'project', label: 'Dự án', defaultOn: true },
  { key: 'assignee', label: 'Người thực hiện', defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: true },
  { key: 'priority', label: 'Độ khó', defaultOn: true },
  { key: 'start_date', label: 'Ngày bắt đầu', defaultOn: true },
  { key: 'end_date', label: 'Ngày kết thúc', defaultOn: true },
  { key: 'progress_percent', label: 'Tiến độ', defaultOn: true },
  { key: 'quality', label: 'Chất lượng', defaultOn: true },
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

export const COLUMN_STORAGE_KEY = 'va-task-columns-v3';
export const COLUMN_WIDTH_KEY = 'va-task-column-widths-v2';
export const ZOOM_STORAGE_KEY = 'va-task-zoom-v1';
export const VIEW_MODE_KEY = 'va-task-view-mode';
export const PROJECT_TASK_VIEW_KEY = 'va-project-task-view';
export const PROJECT_TASK_COL_KEY = 'va-project-task-columns-v3';
export const PROJECT_TASK_WIDTH_KEY = 'va-project-task-column-widths-v2';
export const PROJECT_TASK_ZOOM_KEY = 'va-project-task-zoom-v1';
export const PROJECT_TASK_KANBAN_GROUP_KEY = 'va-project-task-kanban-group';
export const PROJECT_TASK_VIEWS = [
  { key: 'all', label: 'Tất cả công việc', icon: 'layoutList' },
  { key: 'parents', label: 'Công việc cha', icon: 'listNumbered' },
  { key: 'phase', label: 'Theo giai đoạn', icon: 'flag' },
  { key: 'sprint', label: 'Theo đợt làm việc', icon: 'layoutGrid' },
  { key: 'kanban', label: 'Kanban', icon: 'layoutGrid' },
  { key: 'gantt', label: 'Gantt', icon: 'gantt' },
  { key: 'plan', label: 'Kế hoạch', icon: 'calendar' },
];
export const PROJECT_TASK_COLUMNS = TASK_COLUMNS.filter((col) => col.key !== 'project');
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

/** Lọc nhanh từ thẻ thống kê trên chi tiết dự án — khớp matchesGanttFilter. */
export function matchesProjectTaskFilter(task, filter) {
  if (!filter || filter === 'all') return true;
  if (filter === 'overdue') return Boolean(task.is_overdue);
  return task.status === filter;
}

/** Trung bình tiến độ: việc chưa nhập % tính 0 — không bỏ qua nên nhóm không bị 100% khi còn việc dở. */
export function averageTaskProgress(tasks) {
  const list = tasks || [];
  if (!list.length) return null;
  const sum = list.reduce((acc, task) => acc + Number(task?.progress_percent || 0), 0);
  return Math.round(sum / list.length);
}

/** Mọi node type=phase trong cây WBS, theo thứ tự duyệt. */
export function collectPhaseNodes(nodes) {
  const out = [];
  const walk = (list) => {
    for (const node of list || []) {
      if (node.type === 'phase') out.push(node);
      if (node.children?.length) walk(node.children);
    }
  };
  walk(nodes);
  return out;
}

/**
 * Phẳng cây WBS thành danh sách công việc (type=task).
 * parentsOnly: chỉ việc không nằm dưới một công việc khác (việc cha / gốc).
 */
export function flattenProjectTasks(nodes, { parentsOnly = false, filter = 'all', query = '' } = {}) {
  const q = String(query || '').trim().toLowerCase();
  const out = [];

  const walk = (list, depth, parent) => {
    for (const node of list || []) {
      const kids = node.children || [];
      const parentIsTask = parent?.type === 'task';
      if (node.type === 'task') {
        const include = !parentsOnly || !parentIsTask;
        if (include && matchesProjectTaskFilter(node, filter)) {
          const hay = `${node.title || ''} ${node.code || ''}`.toLowerCase();
          if (!q || hay.includes(q)) {
            out.push({
              ...node,
              depth: parentsOnly ? 0 : depth,
              hasChildren: kids.some((child) => child.type === 'task'),
            });
          }
        }
      }
      const nextDepth = node.type === 'task' && !parentsOnly ? depth + 1 : depth;
      if (!parentsOnly || node.type !== 'task') {
        walk(kids, nextDepth, node);
      }
    }
  };

  walk(nodes, 0, null);
  return out;
}

/**
 * Nhóm công việc (type=task) theo giai đoạn (node type=phase) gần nhất
 * chứa nó trong cây WBS — task dưới category lồng trong phase vẫn tính
 * vào phase đó. Phase không có việc vẫn hiện (0 công việc). Task không nằm
 * dưới phase nào gom vào nhóm cuối "Chưa thuộc giai đoạn nào" — nhóm này
 * ẩn khi trống.
 *
 * Công việc con (subtask) đi theo phase của việc cha và giữ nguyên
 * depth/hasChildren như bảng phẳng (flattenProjectTasks) để hiện thụt lề +
 * nút thu gọn ngay trong nhóm giai đoạn — tránh việc con bị liệt kê rời
 * rạc ngang hàng với việc cha, khó biết đang thuộc việc nào.
 */
export function groupProjectTasksByPhase(nodes, { filter = 'all', query = '' } = {}) {
  const q = String(query || '').trim().toLowerCase();
  const groups = new Map();
  const noPhaseKey = '__no_phase__';

  const ensureGroup = (key, phaseNode) => {
    if (!groups.has(key)) {
      groups.set(key, {
        key,
        id: phaseNode?.id ?? null,
        title: phaseNode?.title || 'Chưa thuộc giai đoạn nào',
        code: phaseNode?.code || null,
        tasks: [],
      });
    }
    return groups.get(key);
  };

  for (const phase of collectPhaseNodes(nodes)) {
    ensureGroup(`phase-${phase.id}`, phase);
  }

  const walk = (list, phaseNode, depth) => {
    for (const node of list || []) {
      const nextPhaseNode = node.type === 'phase' ? node : phaseNode;
      const kids = node.children || [];
      if (node.type === 'task') {
        if (matchesProjectTaskFilter(node, filter)) {
          const hay = `${node.title || ''} ${node.code || ''}`.toLowerCase();
          if (!q || hay.includes(q)) {
            const key = nextPhaseNode ? `phase-${nextPhaseNode.id}` : noPhaseKey;
            ensureGroup(key, nextPhaseNode).tasks.push({
              ...node,
              depth,
              hasChildren: kids.some((child) => child.type === 'task'),
            });
          }
        }
      }
      const nextDepth = node.type === 'task' ? depth + 1 : depth;
      walk(kids, nextPhaseNode, nextDepth);
    }
  };

  walk(nodes, null, 0);

  const filtering = Boolean(q) || (filter && filter !== 'all');
  const out = Array.from(groups.values()).filter((group) => {
    if (group.key === noPhaseKey) return group.tasks.length > 0;
    if (filtering) return group.tasks.length > 0;
    return true;
  });
  out.sort((a, b) => {
    if (a.key === noPhaseKey) return 1;
    if (b.key === noPhaseKey) return -1;
    return 0;
  });

  return out.map((group) => ({ ...group, avgProgress: averageTaskProgress(group.tasks) }));
}

/** Nhãn/tone hiển thị 1 công việc — dùng chung giữa bảng phẳng, bảng nhóm
 *  theo giai đoạn (TaskTableRow.vue) và export (ProjectTasksTab.vue). */
export function taskStatusLabel(value) {
  return TASK_STATUS_LABELS[value] || value || '—';
}
export function taskStatusTone(value) {
  return TASK_STATUS_TONES[value] || 'neutral';
}
export function taskTypeLabel(value) {
  return TASK_TYPE_LABELS[value] || value || '—';
}
export function taskTypeTone(value) {
  return TASK_TYPE_TONES[value] || 'neutral';
}
export function taskPriorityLabel(value, code = '') {
  const label = value ? (TASK_PRIORITY_LABELS[value] || value) : '—';
  if (!code || label === '—' || label.startsWith(`${code}-`) || label.startsWith(`${code} `)) return label;
  return `${code}-${label}`;
}

/** Tên mức độ khó đọc được. Ưu tiên nhãn API (mã phòng ban → tên tiếng Việt). */
export function taskPriorityDisplay(task) {
  const value = String(task?.priority || '').trim();
  const fromApi = String(task?.priority_label || '').trim();
  if (fromApi && fromApi.toLowerCase() !== value.toLowerCase()) return fromApi;
  return value ? taskPriorityLabel(value) : '—';
}
export function taskPriorityTone(value) {
  return TASK_PRIORITY_TONES[value] || 'neutral';
}
export function formatTaskDate(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleDateString('vi-VN');
}
export function formatTaskDateTime(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleString('vi-VN');
}
export function formatTaskVarianceDays(value) {
  if (value == null) return '—';
  if (value > 0) return `Trễ ${value} ngày`;
  if (value < 0) return `Sớm ${Math.abs(value)} ngày`;
  return 'Đúng hạn';
}
export function taskQualityLabel(task) {
  const label = task?.task_score?.rating_result;
  return label ? String(label) : '—';
}
export function taskQualityTone(task) {
  if (task?.task_score?.is_passed === false) return 'danger';
  if (task?.task_score?.is_passed === true) return 'success';
  return task?.task_score?.rating_result ? 'gold' : 'neutral';
}
export function taskCellText(task, key) {
  if (key === 'code') return task.code || '—';
  if (key === 'title') return task.title || '—';
  if (key === 'start_date' || key === 'end_date' || key === 'actual_start_date' || key === 'actual_end_date') {
    return formatTaskDate(task[key]);
  }
  if (key === 'progress_percent') return task.progress_percent == null ? '—' : `${task.progress_percent}%`;
  if (key === 'type') return taskTypeLabel(task.type);
  if (key === 'priority') return taskPriorityDisplay(task);
  if (key === 'quality') return task.task_score?.rating_result || '—';
  if (key === 'assignee') return task.assignee?.name || '—';
  if (key === 'status') return taskStatusLabel(task.status);
  if (key === 'creator') return task.creator?.name || '—';
  if (key === 'created_at' || key === 'updated_at') return formatTaskDateTime(task[key]);
  if (key === 'parent') return task.parent?.title || '—';
  if (key === 'attachments_count') return String(task.attachments_count || 0);
  if (key === 'estimated_hours') return task.estimated_hours ?? '—';
  if (key === 'worklog_hours') return String(task.worklog_hours || 0);
  if (key === 'manager') return task.manager?.name || '—';
  if (key === 'accepted_by') return task.accepted_by_user?.name || '—';
  if (key === 'weight') return task.weight != null ? `${task.weight}%` : '—';
  if (key === 'is_overdue') return task.is_overdue ? 'Quá hạn' : 'Đúng hạn';
  if (key === 'variance_days') return formatTaskVarianceDays(task.variance_days);
  return '—';
}

/**
 * Phẳng toàn bộ cây WBS (task, phase, category) — dùng riêng cho Kanban
 * "Theo loại" vì flattenProjectTasks() chỉ trả node type=task.
 */
export function flattenAllProjectNodes(nodes, { filter = 'all', query = '' } = {}) {
  const q = String(query || '').trim().toLowerCase();
  const out = [];

  const walk = (list, depth) => {
    for (const node of list || []) {
      if (matchesProjectTaskFilter(node, filter)) {
        const hay = `${node.title || ''} ${node.code || ''}`.toLowerCase();
        if (!q || hay.includes(q)) {
          out.push({ ...node, depth, hasChildren: (node.children || []).length > 0 });
        }
      }
      walk(node.children, depth + 1);
    }
  };

  walk(nodes, 0);
  return out;
}
