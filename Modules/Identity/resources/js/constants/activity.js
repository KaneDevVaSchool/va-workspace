export const ACTIVITY_ACTIONS = [
  { value: '', label: 'Tất cả thao tác' },
  { value: 'auth.login', label: 'Đăng nhập' },
  { value: 'auth.logout', label: 'Đăng xuất' },
  { value: 'view_as.activate', label: 'Xem thử vai trò' },
  { value: 'view_as.deactivate', label: 'Thoát xem thử' },
  { value: 'permission.grant', label: 'Cấp quyền' },
  { value: 'permission.deny', label: 'Tắt quyền' },
  { value: 'permission.revoke', label: 'Khôi phục quyền' },
  { value: 'team.create', label: 'Tạo nhóm' },
  { value: 'team.update', label: 'Cập nhật nhóm' },
  { value: 'team.delete', label: 'Xoá nhóm' },
];

export function activityActionLabel(action) {
  return ACTIVITY_ACTIONS.find((item) => item.value === action)?.label ?? action ?? '';
}
