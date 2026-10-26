/** Hub Lịch sử (/dashboard/me/requests) — dùng chung trang + preview PWA. */
export const EMPLOYEE_HISTORY_UTILITIES = [
  {
    id: 'attendance',
    label: 'Lịch sử chấm công',
    icon: 'clock',
    tone: 'tertiary',
    visualIndex: 0,
  },
  {
    id: 'leave',
    label: 'Lịch sử nghỉ phép',
    icon: 'calendar',
    tone: 'primary',
    visualIndex: 1,
  },
];

export function historyUtilityById(id) {
  return EMPLOYEE_HISTORY_UTILITIES.find((item) => item.id === id) ?? null;
}

export function isHistoryUtilityId(id) {
  return EMPLOYEE_HISTORY_UTILITIES.some((item) => item.id === id);
}
