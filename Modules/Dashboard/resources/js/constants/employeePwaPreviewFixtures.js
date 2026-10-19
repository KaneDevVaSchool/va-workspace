/** Dữ liệu mẫu cho xem thử màn hình PWA trên trang cấu hình superadmin. */

export const PREVIEW_LEAVE_BALANCE = {
  remaining: 8,
  used: 4,
  seniority: 1,
  totalFund: 12,
  planned: 1,
  pending: 0.5,
  usagePercent: 33.3,
  breakdown: [
    { id: 'annual', label: 'Phép năm', value: 11 },
    { id: 'seniority', label: 'Phép thâm niên', value: 1 },
  ],
};

export const PREVIEW_LEAVE_REQUESTS = [
  {
    id: '1',
    kind: 'Nghỉ phép năm',
    status: 'pending',
    statusLabel: 'Đang chờ duyệt',
    range: '24/10/2026 – 25/10/2026',
    submittedAt: '19/10/2026',
    totalDays: 2,
  },
  {
    id: '2',
    kind: 'Nghỉ việc riêng có lương',
    status: 'approved',
    statusLabel: 'Đã duyệt',
    range: '03/10/2026',
    submittedAt: '01/10/2026',
    totalDays: 1,
  },
];

export const PREVIEW_FEED_POSTS = [
  {
    id: '1',
    author: 'Phòng Nhân sự',
    time: '2 giờ trước',
    body: 'Thông báo lịch nghỉ lễ 30/4 và 1/5. Mọi người cập nhật kế hoạch công việc trước ngày 25/10.',
    pinned: true,
  },
  {
    id: '2',
    author: 'Nguyễn Minh Anh',
    dept: 'Phòng Công nghệ',
    time: 'Hôm qua',
    body: 'Team IT đã hoàn tất nâng cấp hệ thống chấm công tuần này. Mọi người phản hồi nếu gặp lỗi nhé.',
    pinned: false,
  },
];

export const PREVIEW_CHAT_CONVERSATIONS = [
  {
    id: '1',
    name: 'Trần Thu Hà',
    dept: 'Phòng Nhân sự',
    excerpt: 'Em gửi anh file duyệt đơn nghỉ phép ạ.',
    time: '10:24',
    unread: 2,
  },
  {
    id: '2',
    name: 'Lê Quốc Bảo',
    dept: 'Phòng Công nghệ',
    excerpt: 'Chiều nay họp lúc 15h nhé.',
    time: 'Hôm qua',
    unread: 0,
  },
];
