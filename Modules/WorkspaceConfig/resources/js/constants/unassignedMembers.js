export {
  FALLBACK_AVATAR_SRC,
  FALLBACK_AVATAR_SRCSET,
  MEMBER_STATUS_OPTIONS,
  loadVisibility,
  memberRoles,
  memberRolesText,
  memberStatusLabel,
  saveVisibility,
} from './members.js';

//
// Cột bảng nhân sự workspace. `sortable` = cho phép bấm đổi chiều sắp xếp
// trên tiêu đề; `filterable` = có ô lọc "chứa" riêng ngay trên tiêu đề cột.
//
export const UNASSIGNED_COLUMNS = [
  { key: 'person', label: 'Họ tên', defaultOn: true, sortable: true, filterable: true },
  { key: 'employee_code', label: 'Mã nhân viên', defaultOn: false, sortable: true, filterable: true },
  { key: 'job_title', label: 'Chức danh', defaultOn: true, sortable: true, filterable: true },
  { key: 'position_level', label: 'Cấp bậc', defaultOn: false, sortable: true, filterable: true },
  { key: 'company', label: 'Pháp nhân', defaultOn: true, sortable: true, filterable: true },
  { key: 'manager', label: 'Cấp trên trực tiếp', defaultOn: true, sortable: true, filterable: true },
  { key: 'department', label: 'Phòng ban', defaultOn: true, sortable: true, filterable: true },
  { key: 'concurrent', label: 'Chức danh kiêm nhiệm', defaultOn: false, sortable: false, filterable: true },
  { key: 'email', label: 'Email công ty', defaultOn: false, sortable: true, filterable: true },
  { key: 'team', label: 'Nhóm', defaultOn: false, sortable: true, filterable: true },
  { key: 'roles', label: 'Vai trò', defaultOn: false, sortable: false, filterable: true },
  { key: 'status', label: 'Trạng thái', defaultOn: true, sortable: true, filterable: false },
  { key: 'id', label: 'Mã thành viên', defaultOn: false, sortable: true, filterable: false },
];

export const UNASSIGNED_FILTERS = [
  { key: 'department_id', label: 'Phòng ban', defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: true },
  { key: 'role', label: 'Vai trò', defaultOn: false },
  { key: 'sort', label: 'Sắp xếp', defaultOn: false },
];

export const SORT_OPTIONS = [
  { value: 'name_asc', label: 'Tên A → Z' },
  { value: 'name_desc', label: 'Tên Z → A' },
  { value: 'department_asc', label: 'Phòng ban A → Z' },
  { value: 'unassigned_first', label: 'Chưa gán phòng ban lên đầu' },
  { value: 'id_desc', label: 'Mới thêm sau cùng' },
];

export const COLUMN_STORAGE_KEY = 'va-wc-unassigned-columns-v3';
export const FILTER_STORAGE_KEY = 'va-wc-unassigned-filters-v2';
export const COLUMN_WIDTH_KEY = 'va-wc-unassigned-column-widths-v3';
export const ZOOM_STORAGE_KEY = 'va-wc-unassigned-zoom';

export function departmentName(member) {
  return member?.department?.name || '';
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

/** Dòng phụ dưới họ tên: mã nhân viên HRM và email công ty. */
export function personMetaText(member) {
  return [member?.employee_code, member?.email].filter(Boolean).join(' · ');
}
