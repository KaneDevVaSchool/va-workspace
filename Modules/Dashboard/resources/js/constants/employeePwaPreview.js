/** Tab bar cố định app PWA nhân viên — dùng chung preview cấu hình superadmin. */
export const EMPLOYEE_PWA_TABBAR = [
  { routeName: 'dashboard.me', icon: 'home', label: 'Trang chủ' },
  { routeName: 'employee.feed', icon: 'megaphone', label: 'Bảng tin' },
  { routeName: 'employee.attendance', icon: 'clock', label: 'Chấm công' },
  { routeName: 'employee.leave', icon: 'calendar', label: 'Nghỉ phép' },
  { routeName: 'employee.requests', icon: 'clipboardCheck', label: 'Lịch sử' },
];

/** Nhãn màn hình xem thử trên trang cấu hình PWA. */
export const EMPLOYEE_PWA_PREVIEW_MODES = {
  'mobile-home': 'Trang chủ',
  'mobile-all': 'Tiện ích',
  'mobile-feed': 'Bảng tin',
  'mobile-attendance': 'Chấm công',
  'mobile-leave': 'Nghỉ phép',
  'mobile-requests': 'Lịch sử',
  'mobile-chat': 'Trò chuyện',
};

export const EMPLOYEE_PWA_TAB_PREVIEW = {
  'mobile-feed': {
    activeRoute: 'employee.feed',
    title: 'Bảng tin',
  },
  'mobile-attendance': {
    activeRoute: 'employee.attendance',
    title: 'Chấm công',
  },
  'mobile-leave': {
    activeRoute: 'employee.leave',
    title: 'Quản lý Nghỉ phép',
  },
  'mobile-requests': {
    activeRoute: 'employee.requests',
    title: 'Lịch sử',
  },
  'mobile-chat': {
    activeRoute: 'employee.chat',
    title: 'Trò chuyện',
  },
};
