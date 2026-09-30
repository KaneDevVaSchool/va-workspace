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

export const UNASSIGNED_COLUMNS = [
  { key: 'person', label: 'Họ tên', defaultOn: true },
  { key: 'department', label: 'Phòng ban', defaultOn: true },
  { key: 'roles', label: 'Vai trò', defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: false },
  { key: 'id', label: 'Mã thành viên', defaultOn: false },
];

export const UNASSIGNED_FILTERS = [
  { key: 'q', label: 'Tìm kiếm', defaultOn: true },
  { key: 'department_id', label: 'Phòng ban', defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: false },
];

export const COLUMN_STORAGE_KEY = 'va-wc-unassigned-columns-v1';
export const FILTER_STORAGE_KEY = 'va-wc-unassigned-filters';
export const COLUMN_WIDTH_KEY = 'va-wc-unassigned-column-widths';
export const ZOOM_STORAGE_KEY = 'va-wc-unassigned-zoom';

export function departmentName(member) {
  return member?.department?.name || '';
}
