/**
 * Cột bảng "Dự án" trong Dashboard tổng công ty — theo đúng pattern
 * ACTIVITY_COLUMNS (mẫu vàng Modules/Identity/resources/js/constants/activity.js),
 * tái dùng cho kéo cột + ẩn/hiện cột + fit theo nội dung trong ProjectDataTable.vue.
 * Helper localStorage dùng chung: xem ../utils/tableStorage.js.
 */
export const PROJECT_TABLE_COLUMNS = [
  { key: 'code', label: 'Mã', defaultOn: true },
  { key: 'name', label: 'Dự án', defaultOn: true },
  { key: 'department_name', label: 'Phòng ban', defaultOn: true },
  { key: 'status_label', label: 'Trạng thái', defaultOn: true },
  { key: 'health', label: 'Sức khoẻ', defaultOn: true },
  { key: 'progress_percent', label: 'Tiến độ', defaultOn: true },
  { key: 'end_date', label: 'Hạn hoàn thành', defaultOn: true },
  { key: 'days_overdue', label: 'Trễ', defaultOn: true },
  { key: 'lead_name', label: 'Người phụ trách', defaultOn: true },
  { key: 'tasks_summary', label: 'Công việc', defaultOn: true },
  { key: 'id', label: 'Mã hệ thống', defaultOn: false },
];

export const PROJECT_TABLE_COLUMN_STORAGE_KEY = 'va-dashboard-company-projects-columns-v2';
export const PROJECT_TABLE_COLUMN_WIDTH_KEY = 'va-dashboard-company-projects-column-widths-v2';
