export {
  FALLBACK_AVATAR_SRC,
  FALLBACK_AVATAR_SRCSET,
  loadVisibility,
  memberRoles,
  memberRolesText,
  saveVisibility,
} from './members.js';

export const HRM_STATUS_OPTIONS = [
  { value: '', label: 'Tất cả trạng thái' },
  { value: 'active', label: 'Đang làm việc' },
  { value: 'on_leave', label: 'Nghỉ phép' },
  { value: 'processing', label: 'Đang xử lý' },
  { value: 'pending_confirmation', label: 'Chờ xác nhận' },
  { value: 'suspended', label: 'Tạm ngưng' },
  { value: 'terminated', label: 'Đã nghỉ việc' },
];

const HRM_STATUS_LABELS = Object.fromEntries(
  HRM_STATUS_OPTIONS.filter((item) => item.value).map((item) => [item.value, item.label]),
);

export function hrmStatusLabel(status) {
  if (!status) return '—';
  return HRM_STATUS_LABELS[status] || (status === 'inactive' ? 'Ngừng hoạt động' : 'Đang làm việc');
}

export const UNASSIGNED_COLUMNS = [
  { key: 'person', label: 'Họ tên', defaultOn: true },
  { key: 'job_title', label: 'Chức danh', defaultOn: true },
  { key: 'org_unit', label: 'Đơn vị HRM', defaultOn: true },
  { key: 'department', label: 'Phòng ban workspace', defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: true },
  { key: 'employee_code', label: 'Mã nhân viên', defaultOn: false },
  { key: 'company', label: 'Pháp nhân', defaultOn: false },
  { key: 'manager', label: 'Cấp trên', defaultOn: false },
  { key: 'position_level', label: 'Cấp bậc', defaultOn: false },
  { key: 'concurrent', label: 'Kiêm nhiệm', defaultOn: false },
  { key: 'team', label: 'Nhóm', defaultOn: false },
  { key: 'roles', label: 'Vai trò', defaultOn: false },
];

export const UNASSIGNED_FILTERS = [
  { key: 'q', label: 'Tìm kiếm', defaultOn: true },
  { key: 'org_unit', label: 'Đơn vị HRM', defaultOn: true },
  { key: 'department_id', label: 'Phòng ban workspace', defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: true },
];

export const COLUMN_STORAGE_KEY = 'va-wc-unassigned-columns-v4';
export const FILTER_STORAGE_KEY = 'va-wc-unassigned-filters-v3';
export const COLUMN_WIDTH_KEY = 'va-wc-unassigned-column-widths-v4';
export const ZOOM_STORAGE_KEY = 'va-wc-unassigned-zoom';

export function departmentName(member) {
  return member?.department?.name || '';
}

export function orgUnitName(member) {
  return member?.org_unit?.name || '';
}

export function teamName(member) {
  return member?.team?.name || '';
}

export function companyName(member) {
  return member?.company?.name || '';
}

export function concurrentTitleText(member) {
  return (member?.concurrent_positions ?? [])
    .map((item) => item?.job_title_name)
    .filter(Boolean)
    .join(', ');
}

export function memberKey(member) {
  return member?.hrm_employee_uuid || `user-${member?.id ?? ''}`;
}
