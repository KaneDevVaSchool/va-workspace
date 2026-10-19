/** Tab bar cố định app PWA nhân viên — dùng chung preview cấu hình superadmin. */
export const EMPLOYEE_PWA_TABBAR = [
  { routeName: 'dashboard.me', icon: 'home', label: 'Trang chủ' },
  { routeName: 'employee.feed', icon: 'megaphone', label: 'Bảng tin' },
  { routeName: 'employee.attendance', icon: 'clock', label: 'Chấm công' },
  { routeName: 'employee.leave', icon: 'calendar', label: 'Nghỉ phép' },
  { routeName: 'employee.requests', icon: 'fileText', label: 'Đơn' },
];

/** Nhãn màn hình xem thử trên trang cấu hình PWA. */
export const EMPLOYEE_PWA_PREVIEW_MODES = {
  'mobile-home': 'Trang chủ',
  'mobile-all': 'Tiện ích',
  'mobile-feed': 'Bảng tin',
  'mobile-attendance': 'Chấm công',
  'mobile-leave': 'Nghỉ phép',
  'mobile-requests': 'Đơn',
  'mobile-chat': 'Trò chuyện',
};

export const EMPLOYEE_PWA_TAB_PREVIEW = {
  'mobile-feed': {
    activeRoute: 'employee.feed',
    kicker: 'VA Workspace',
    title: 'Bảng tin',
    hint: 'Bài đăng nội bộ và thông báo phòng ban.',
  },
  'mobile-attendance': {
    activeRoute: 'employee.attendance',
    kicker: 'VA Workspace',
    title: 'Chấm công',
    hint: 'Check-in/out và lịch sử chấm công trong tháng.',
  },
  'mobile-leave': {
    activeRoute: 'employee.leave',
    kicker: 'VA Workspace',
    title: 'Quản lý Nghỉ phép',
    hint: 'Số dư phép, tạo đơn và theo dõi duyệt.',
  },
  'mobile-requests': {
    activeRoute: 'employee.requests',
    kicker: 'VA Workspace',
    title: 'Lịch sử nghỉ phép',
    hint: 'Danh sách đơn nghỉ đã gửi và trạng thái duyệt.',
  },
  'mobile-chat': {
    activeRoute: 'employee.chat',
    kicker: 'VA Workspace',
    title: 'Trò chuyện',
    hint: 'Danh sách hội thoại và nhắn tin 1-1 với đồng nghiệp.',
  },
};
