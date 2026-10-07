export {
  FALLBACK_AVATAR_SRC,
  FALLBACK_AVATAR_SRCSET,
  loadVisibility,
  memberRoles,
  memberRolesText,
  saveVisibility,
} from './members.js';

export const HRM_STATUS_OPTIONS = [
  { value: '', label: 'Chọn trạng thái' },
  { value: 'pending_confirmation', label: 'Chờ xác nhận' },
  { value: 'processing', label: 'Đang xử lý' },
  { value: 'active', label: 'Đang làm việc' },
  { value: 'on_leave', label: 'Tạm nghỉ' },
  { value: 'suspended', label: 'Tạm đình chỉ' },
  { value: 'terminated', label: 'Đã nghỉ việc' },
];

export const EMPLOYMENT_STATUS_OPTIONS = [
  { value: '', label: 'Trạng thái nhân sự' },
  { value: 'unpaid_leave', label: 'Không hưởng lương' },
  { value: 'maternity', label: 'Thai sản' },
  { value: 'suspended', label: 'Tạm ngưng' },
  { value: 'resigning', label: 'Nghỉ việc' },
  { value: 'resigned', label: 'Đã nghỉ việc' },
];

export const GENDER_OPTIONS = [
  { value: '', label: 'Giới tính' },
  { value: 'male', label: 'Nam' },
  { value: 'female', label: 'Nữ' },
  { value: 'other', label: 'Khác' },
];

const GENDER_LABELS = Object.fromEntries(GENDER_OPTIONS.filter((item) => item.value).map((item) => [item.value, item.label]));
const EMPLOYMENT_STATUS_LABELS = Object.fromEntries(
  EMPLOYMENT_STATUS_OPTIONS.filter((item) => item.value).map((item) => [item.value, item.label]),
);

const HRM_STATUS_LABELS = Object.fromEntries(
  HRM_STATUS_OPTIONS.filter((item) => item.value).map((item) => [item.value, item.label]),
);

export function hrmStatusLabel(status) {
  if (!status) return 'Chưa cập nhật';
  return HRM_STATUS_LABELS[status] || (status === 'inactive' ? 'Ngừng hoạt động' : 'Đang làm việc');
}

export function employmentStatusLabel(status) {
  if (!status) return 'Chưa cập nhật';
  return EMPLOYMENT_STATUS_LABELS[status] || status;
}

export function genderLabel(gender) {
  if (!gender) return 'Chưa cập nhật';
  return GENDER_LABELS[gender] || gender;
}

export function formatHrmDate(value) {
  if (!value) return 'Chưa cập nhật';
  const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!match) return String(value);
  return `${match[3]}/${match[2]}/${match[1]}`;
}

export function secondaryPlacement(member) {
  return member?.concurrent_positions?.[0] ?? null;
}

export const UNASSIGNED_COLUMNS = [
  { key: 'person', label: 'Họ Tên', defaultOn: true },
  { key: 'timekeeping_code', label: 'Mã Nhân Viên', defaultOn: true },
  { key: 'company', label: 'Pháp nhân 1', defaultOn: true },
  { key: 'company_2', label: 'Pháp nhân 2', defaultOn: true },
  { key: 'job_title', label: 'Chức danh 1', defaultOn: true },
  { key: 'job_title_2', label: 'Chức danh 2', defaultOn: true },
  { key: 'department_2', label: 'Phòng ban 2', defaultOn: true },
  { key: 'division_2', label: 'Bộ phận 2', defaultOn: true },
  { key: 'position_level', label: 'Phân Loại Cấp Bậc', defaultOn: true },
  { key: 'manager', label: 'Cấp trên trực tiếp', defaultOn: true },
  { key: 'department', label: 'Phòng ban 1', defaultOn: true },
  { key: 'division', label: 'Bộ phận', defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: true },
  { key: 'org_unit', label: 'Đơn Vị', defaultOn: false },
  { key: 'phone', label: 'Số Điện Thoại', defaultOn: false },
  { key: 'email', label: 'Email Công Ty', defaultOn: false },
  { key: 'hired_at', label: 'Ngày Vào Làm', defaultOn: false },
  { key: 'actual_start_date', label: 'Ngày Vào Làm Thực Tế', defaultOn: false },
  { key: 'employment_status', label: 'Trạng thái nhân sự', defaultOn: false },
  { key: 'gender', label: 'Giới Tính', defaultOn: false },
  { key: 'workplace', label: 'Cơ Sở', defaultOn: false },
  { key: 'personnel_type', label: 'Phân Loại', defaultOn: false },
  { key: 'workspace_department', label: 'Phòng ban workspace', defaultOn: false },
];

export const UNASSIGNED_FILTERS = [
  { key: 'status', label: 'Trạng thái', defaultOn: true },
  { key: 'employment_status', label: 'Trạng thái nhân sự', defaultOn: true },
  { key: 'personnel_type', label: 'Phân loại', defaultOn: true },
  { key: 'company', label: 'Công ty', defaultOn: true },
  { key: 'org_unit', label: 'Đơn vị', defaultOn: true },
  { key: 'workplace', label: 'Cơ sở', defaultOn: true },
  { key: 'gender', label: 'Giới tính', defaultOn: true },
];

export const COLUMN_STORAGE_KEY = 'va-wc-unassigned-columns-v6';
export const FILTER_STORAGE_KEY = 'va-wc-unassigned-filters-v4';
export const COLUMN_WIDTH_KEY = 'va-wc-unassigned-column-widths-v6';
export const ZOOM_STORAGE_KEY = 'va-wc-unassigned-zoom';

export function departmentName(member) {
  return member?.department?.name || '';
}

export function orgUnitName(member) {
  return member?.org_unit?.name || '';
}

export function hrmDepartmentName(member) {
  return member?.hrm_department_name || '';
}

export function divisionName(member) {
  return member?.division_name || '';
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
