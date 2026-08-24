/** 7 role hệ thống — khớp RoleSeeder / docs VA_WORKSPACE_OVERVIEW §4.1 */
export const SYSTEM_ROLES = [
  { code: 'super_admin', label: 'Super Admin' },
  { code: 'admin', label: 'Admin' },
  { code: 'director_officer', label: 'Giám đốc điều hành' },
  { code: 'department_director', label: 'Trưởng phòng ban' },
  { code: 'team_lead', label: 'Trưởng nhóm' },
  { code: 'member', label: 'Nhân viên' },
  { code: 'viewer', label: 'Người xem' },
];

export function roleLabel(code) {
  return SYSTEM_ROLES.find((r) => r.code === code)?.label ?? code ?? '';
}
