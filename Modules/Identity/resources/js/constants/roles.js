/** 9 role hệ thống — khớp RoleSeeder (thứ tự phân cấp giảm dần) */
export const SYSTEM_ROLES = [
  { code: 'super_admin', label: 'Super Admin' },
  { code: 'admin', label: 'Admin' },
  { code: 'director_officer', label: 'Giám đốc điều hành' },
  { code: 'department_director', label: 'Trưởng phòng ban' },
  { code: 'deputy_department_director', label: 'Phó phòng' },
  { code: 'section_head', label: 'Trưởng bộ phận' },
  { code: 'team_lead', label: 'Trưởng nhóm' },
  { code: 'member', label: 'Nhân viên' },
  { code: 'viewer', label: 'Người xem' },
];

export function roleLabel(code) {
  return SYSTEM_ROLES.find((r) => r.code === code)?.label ?? code ?? '';
}
