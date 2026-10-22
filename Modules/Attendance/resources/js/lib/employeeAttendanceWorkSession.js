/** Phân ca theo giờ thực tế (khớp mặc định TimesheetShiftSchedule trên HRM: nghỉ trưa ~12:00). */
const AFTERNOON_START_MINUTES = 12 * 60;

const SESSIONS = {
  morning: { id: 'morning', label: 'Ca sáng', short: 'Sáng', tone: 'amber' },
  afternoon: { id: 'afternoon', label: 'Ca chiều', short: 'Chiều', tone: 'indigo' },
};

function minutesFromClock(hhmm) {
  const match = String(hhmm || '').match(/^(\d{1,2}):(\d{2})/);
  if (!match) return null;
  return Number(match[1]) * 60 + Number(match[2]);
}

export function workSessionFromDate(date = new Date()) {
  const mins = date.getHours() * 60 + date.getMinutes();
  return mins < AFTERNOON_START_MINUTES ? SESSIONS.morning : SESSIONS.afternoon;
}

export function workSessionFromClock(hhmm) {
  const mins = minutesFromClock(hhmm);
  if (mins == null) return workSessionFromDate();
  return mins < AFTERNOON_START_MINUTES ? SESSIONS.morning : SESSIONS.afternoon;
}

/** Ca của lần chấm tiếp theo — theo giờ thực tế lúc bấm (sáng trước 12:00, chiều từ 12:00). */
export function nextPunchSession({ now = new Date() } = {}) {
  return workSessionFromDate(now);
}

export function punchActionLabel({ checkInTime, checkOutTime, now = new Date() } = {}) {
  const session = nextPunchSession({ now });
  const checkingOut = Boolean(checkInTime && !checkOutTime);
  const verb = checkingOut ? 'Chấm ra' : 'Chấm vào';
  return { verb, session, full: `${verb} · ${session.label}` };
}
