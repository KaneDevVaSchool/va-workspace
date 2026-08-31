/**
 * Cấu hình bảng cho các trang Báo cáo & Đánh giá, theo đúng mẫu vàng
 * `Modules/Identity/resources/js/constants/activity.js`.
 *
 * Mỗi trang có bộ cột / bộ lọc riêng nhưng dùng chung `loadVisibility` /
 * `saveVisibility` để nhớ cấu hình qua localStorage — không có phần này thì
 * kéo cột xong tải lại trang là mất hết.
 */

/**
 * Sáu loại báo cáo của hệ thống.
 *
 * `available: false` = đã có tên và mô tả để người dùng biết sắp có gì, nhưng
 * chưa dựng phần tính số liệu — trang danh sách vẫn liệt kê, chỉ không cho
 * bấm tạo. Đồng bộ thủ công với `Report::TYPES` / `Report::TYPES_COMING_SOON`
 * ở backend; backend mới là nơi chặn thật, đây chỉ là phần hiển thị.
 */
export const REPORT_TYPES = [
  {
    key: 'personnel_evaluation',
    label: 'Đánh giá nhân sự',
    description:
      'Điểm của từng nhân sự trong kỳ: điểm khởi đầu, điểm từ công việc, điểm cộng trừ và xếp loại.',
    icon: 'clipboardCheck',
    routeName: 'manager.reports.personnel-evaluation.create',
    available: true,
  },
  {
    key: 'department_work',
    label: 'Báo cáo công việc phòng ban',
    description:
      'Toàn bộ công việc của phòng ban trong kỳ: số lượng, tiến độ, đúng hạn và quá hạn.',
    icon: 'listChecks',
    available: false,
  },
  {
    key: 'personal_work',
    label: 'Báo cáo công việc cá nhân',
    description: 'Công việc của một nhân sự trong kỳ, kèm tiến độ và thời gian hoàn thành.',
    icon: 'user',
    available: false,
  },
  {
    key: 'project_monthly',
    label: 'Chi tiết các dự án theo tháng',
    description: 'Từng dự án trong tháng: công việc, người thực hiện và tiến độ thực tế.',
    icon: 'layoutGrid',
    available: false,
  },
  {
    key: 'project_governance',
    label: 'Quản trị dự án theo tháng',
    description: 'Nhìn tổng thể nhiều dự án trong tháng để thấy dự án nào đang chậm.',
    icon: 'layers',
    available: false,
  },
  {
    key: 'timesheet_detail',
    label: 'Báo cáo chi tiết timesheet',
    description: 'Giờ làm đã ghi nhận theo từng người, từng ngày và từng công việc.',
    icon: 'clock',
    available: false,
  },
];

export const REPORT_TYPE_LABELS = Object.fromEntries(
  REPORT_TYPES.map((item) => [item.key, item.label]),
);

export const REPORT_STATUS_LABELS = {
  draft: 'Bản nháp',
  saved: 'Đã lưu',
};

export const EVENT_STATUS_LABELS = {
  pending: 'Chờ duyệt',
  approved: 'Đã duyệt',
  rejected: 'Đã từ chối',
};

/* ---------- Trang Danh sách báo cáo ---------- */

export const REPORT_LIST_COLUMNS = [
  { key: 'title', label: 'Tên báo cáo', defaultOn: true },
  { key: 'report_type', label: 'Loại báo cáo', defaultOn: true },
  { key: 'period', label: 'Kỳ báo cáo', defaultOn: true },
  { key: 'department_name', label: 'Phòng ban', defaultOn: false },
  { key: 'viewer_count', label: 'Người được xem', defaultOn: false },
  { key: 'status', label: 'Tình trạng', defaultOn: true },
  { key: 'created_by_name', label: 'Người tạo', defaultOn: true },
  { key: 'created_at', label: 'Tạo lúc', defaultOn: false },
];

export const REPORT_LIST_FILTERS = [
  { key: 'q', label: 'Tìm kiếm', defaultOn: true },
  { key: 'report_type', label: 'Loại báo cáo', defaultOn: true },
  { key: 'status', label: 'Tình trạng', defaultOn: true },
];

export const REPORT_LIST_COLUMN_KEY = 'va-report-list-columns';
export const REPORT_LIST_FILTER_KEY = 'va-report-list-filters';
export const REPORT_LIST_WIDTH_KEY = 'va-report-list-column-widths';
export const REPORT_LIST_ZOOM_KEY = 'va-report-list-zoom';

/* ---------- Trang Ghi nhận đánh giá ---------- */

export const EVENT_COLUMNS = [
  { key: 'occurred_at', label: 'Ngày ghi nhận', defaultOn: true },
  { key: 'user_name', label: 'Nhân sự', defaultOn: true },
  { key: 'criterion_name', label: 'Tiêu chí', defaultOn: true },
  { key: 'level_label', label: 'Mức', defaultOn: true },
  { key: 'score', label: 'Điểm', defaultOn: true },
  { key: 'status', label: 'Tình trạng', defaultOn: true },
  { key: 'reason', label: 'Lý do', defaultOn: false },
  { key: 'criterion_type_name', label: 'Nhóm tiêu chí', defaultOn: false },
  { key: 'task_title', label: 'Công việc liên quan', defaultOn: false },
  { key: 'recorded_by_name', label: 'Người ghi nhận', defaultOn: false },
  { key: 'approved_by_name', label: 'Người duyệt', defaultOn: false },
];

export const EVENT_FILTERS = [
  { key: 'q', label: 'Tìm kiếm', defaultOn: true },
  { key: 'user_id', label: 'Nhân sự', defaultOn: true },
  { key: 'status', label: 'Tình trạng', defaultOn: true },
  { key: 'from', label: 'Từ ngày', defaultOn: true },
  { key: 'to', label: 'Đến ngày', defaultOn: true },
];

export const EVENT_COLUMN_KEY = 'va-evaluation-event-columns';
export const EVENT_FILTER_KEY = 'va-evaluation-event-filters';
export const EVENT_WIDTH_KEY = 'va-evaluation-event-column-widths';
export const EVENT_ZOOM_KEY = 'va-evaluation-event-zoom';

/* ---------- Trang Tổng hợp đánh giá ---------- */

/**
 * Nhóm cột của bảng tổng hợp — mỗi nhóm là một ô ở hàng tiêu đề trên, các
 * `cols` bên trong là ô ở hàng dưới.
 *
 * Lưu ý về ý nghĩa: nhóm theo trạng thái và nhóm "Quá hạn" ĐỘC LẬP với nhau.
 * Một việc hoàn thành muộn được đếm ở cả "Hoàn thành" lẫn "Quá hạn" — đây là
 * chủ ý, không phải lỗi đếm trùng.
 */
export const SUMMARY_GROUPS = [
  {
    key: 'tasks_total',
    label: 'Tổng công việc',
    cols: [
      { key: 'tasks_total__count', label: 'Số lượng' },
      { key: 'tasks_total__on_time', label: 'Đúng hạn' },
      { key: 'tasks_total__overdue', label: 'Quá hạn' },
    ],
  },
  {
    key: 'tasks_in_progress',
    label: 'Đang thực hiện',
    cols: [
      { key: 'tasks_in_progress__count', label: 'Số lượng' },
      { key: 'tasks_in_progress__on_time', label: 'Trong hạn' },
      { key: 'tasks_in_progress__overdue', label: 'Quá hạn' },
    ],
  },
  {
    key: 'tasks_completed',
    label: 'Hoàn thành',
    cols: [
      { key: 'tasks_completed__count', label: 'Số lượng' },
      { key: 'tasks_completed__on_time', label: 'Đúng hạn' },
      { key: 'tasks_completed__overdue', label: 'Trễ hạn' },
    ],
  },
  {
    key: 'scores',
    label: 'Điểm đánh giá',
    cols: [
      { key: 'start_score', label: 'Khởi đầu' },
      { key: 'task_adjustment', label: 'Công việc' },
      { key: 'bonus', label: 'Cộng' },
      { key: 'penalty', label: 'Trừ' },
      { key: 'final_score', label: 'Điểm cuối' },
      { key: 'classification', label: 'Xếp loại' },
    ],
  },
];

/** Nhóm cột bật/tắt được — nhân sự và điểm cuối luôn hiện nên không nằm đây. */
export const SUMMARY_GROUP_TOGGLES = [
  { key: 'tasks_total', label: 'Tổng công việc', defaultOn: true },
  { key: 'tasks_in_progress', label: 'Đang thực hiện', defaultOn: true },
  { key: 'tasks_completed', label: 'Hoàn thành', defaultOn: true },
  { key: 'criteria', label: 'Theo tiêu chí', defaultOn: true },
  { key: 'scores', label: 'Điểm đánh giá', defaultOn: true },
];

export const SUMMARY_PERIOD_TYPES = [
  { value: 'month', label: 'Tháng' },
  { value: 'week', label: 'Tuần' },
  { value: 'day', label: 'Ngày' },
  { value: 'range', label: 'Khoảng ngày' },
];

/** Trạng thái đúng hạn — chữ hiển thị và tông màu chấm đứng trước. */
export const TIMELINESS_LABELS = {
  on_time: 'Đúng hạn',
  overdue: 'Quá hạn',
  unknown: 'Chưa xác định',
};

export const TIMELINESS_TONES = {
  on_time: 'ok',
  overdue: 'danger',
  unknown: 'muted',
};

export const TASK_STATUS_LABELS = {
  not_started: 'Chưa bắt đầu',
  in_progress: 'Đang thực hiện',
  on_hold: 'Tạm dừng',
  completed: 'Hoàn thành',
  cancelled: 'Đã huỷ',
};

export const SUMMARY_GROUP_KEY = 'va-evaluation-summary-groups';
export const SUMMARY_WIDTH_KEY = 'va-evaluation-summary-column-widths';
export const SUMMARY_ZOOM_KEY = 'va-evaluation-summary-zoom';
export const SUMMARY_PERIOD_KEY = 'va-evaluation-summary-period-type';

/* ---------- Trang Xem báo cáo ---------- */

export const REPORT_VIEW_WIDTH_KEY = 'va-report-view-column-widths';
export const REPORT_VIEW_ZOOM_KEY = 'va-report-view-zoom';
export const REPORT_VIEW_SORT_KEY = 'va-report-view-sort';

/* ---------- Dùng chung ---------- */

/**
 * Đọc cấu hình bật/tắt đã lưu, thiếu khoá nào thì lấy mặc định của khoá đó.
 * Trình duyệt chặn localStorage cũng không được làm hỏng trang.
 */
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

/** Độ rộng cột đã kéo — để lần sau vào không phải kéo lại. */
export function loadColumnWidths(storageKey) {
  try {
    const raw = localStorage.getItem(storageKey);
    const parsed = raw ? JSON.parse(raw) : {};
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
      return parsed;
    }
  } catch {
    // Bỏ qua nếu trình duyệt chặn localStorage.
  }
  return {};
}

/** Cỡ chữ bảng — chỉ nhận đúng 3 mức TablePagesBar hỗ trợ. */
export function loadZoom(storageKey) {
  try {
    const raw = Number(localStorage.getItem(storageKey));
    if (raw === 0.9 || raw === 1 || raw === 1.15) {
      return raw;
    }
  } catch {
    // Bỏ qua.
  }
  return 1;
}

export function saveZoom(storageKey, value) {
  try {
    localStorage.setItem(storageKey, String(value));
  } catch {
    // Bỏ qua.
  }
}

/** Cách sắp xếp bảng xem báo cáo — nhớ để lần sau mở ra vẫn xếp như cũ. */
export function loadSort(storageKey, fallback) {
  try {
    const raw = localStorage.getItem(storageKey);
    const parsed = raw ? JSON.parse(raw) : null;
    if (parsed && typeof parsed === 'object' && typeof parsed.key === 'string') {
      return { key: parsed.key, dir: parsed.dir === 'asc' ? 'asc' : 'desc' };
    }
  } catch {
    // Bỏ qua.
  }
  return { ...fallback };
}

export function saveSort(storageKey, value) {
  try {
    localStorage.setItem(storageKey, JSON.stringify(value));
  } catch {
    // Bỏ qua.
  }
}
