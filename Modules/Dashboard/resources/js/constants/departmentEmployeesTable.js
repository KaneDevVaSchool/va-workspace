/**
 * Cột bảng "Nhân viên" trong Dashboard phòng ban — cùng pattern
 * companyProjectsTable.js (mẫu vàng ACTIVITY_COLUMNS).
 */
export const EMPLOYEE_TABLE_COLUMNS = [
  { key: 'name', label: 'Nhân viên', defaultOn: true },
  { key: 'team_name', label: 'Nhóm', defaultOn: true },
  { key: 'projects_leading', label: 'Dự án đang phụ trách', defaultOn: true },
  { key: 'projects_collaborating', label: 'Dự án đang phối hợp', defaultOn: true },
  { key: 'tasks_total', label: 'Tổng việc', defaultOn: true },
  { key: 'tasks_status', label: 'Việc theo trạng thái', defaultOn: true },
  { key: 'average_progress_percent', label: 'Tiến độ TB', defaultOn: true },
  { key: 'user_id', label: 'Mã hệ thống', defaultOn: false },
];

export const EMPLOYEE_TABLE_COLUMN_STORAGE_KEY = 'va-dashboard-department-employees-columns-v1';
export const EMPLOYEE_TABLE_COLUMN_WIDTH_KEY = 'va-dashboard-department-employees-column-widths-v1';
