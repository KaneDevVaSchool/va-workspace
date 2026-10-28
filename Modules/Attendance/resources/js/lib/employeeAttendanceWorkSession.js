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

/** Ca theo đồng hồ (nhãn trên trang). Lần chấm tiếp theo không phụ thuộc ca này. */
export function nextPunchSession({ now = new Date() } = {}) {
  return workSessionFromDate(now);
}

/**
 * @typedef {Object} DayPunchRecord
 * @property {string|null} [morningInTime]
 * @property {string|null} [morningOutTime]
 * @property {string|null} [afternoonInTime]
 * @property {string|null} [afternoonOutTime]
 */

export function sessionSlot(record, sessionId) {
  if (sessionId === 'morning') {
    return { in: record?.morningInTime ?? null, out: record?.morningOutTime ?? null };
  }
  return { in: record?.afternoonInTime ?? null, out: record?.afternoonOutTime ?? null };
}

/**
 * Một ngày chỉ hai lần: vào (ca sáng) rồi ra (ca chiều).
 * Không yêu cầu ra ca sáng hay vào ca chiều.
 */
export function nextPunchAction(record = {}) {
  if (!record?.morningInTime) {
    return {
      verb: 'Chấm vào',
      direction: 'in',
      session: SESSIONS.morning,
      full: 'Chấm vào buổi sáng',
      complete: false,
    };
  }
  if (!record?.afternoonOutTime) {
    return {
      verb: 'Chấm ra',
      direction: 'out',
      session: SESSIONS.afternoon,
      full: 'Chấm ra buổi chiều',
      complete: false,
    };
  }
  return {
    verb: 'Đã chấm xong',
    direction: null,
    session: SESSIONS.afternoon,
    full: 'Đã chấm xong',
    complete: true,
  };
}

/** @deprecated dùng nextPunchAction */
export function punchActionLabel({ checkInTime, checkOutTime, now = new Date() } = {}) {
  return nextPunchAction({
    morningInTime: checkInTime,
    afternoonOutTime: checkOutTime,
  }, { now });
}
