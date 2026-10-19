const STORAGE_KEY = 'employee-attendance-session-v1';

function todayKey() {
  const d = new Date();
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

export function readEmployeeAttendanceToday() {
  if (typeof sessionStorage === 'undefined') return null;
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    if (parsed?.date !== todayKey()) return null;
    return {
      checkInTime: parsed.checkInTime || null,
      checkOutTime: parsed.checkOutTime || null,
    };
  } catch {
    return null;
  }
}

export function writeEmployeeAttendanceToday({ checkInTime, checkOutTime }) {
  if (typeof sessionStorage === 'undefined') return;
  try {
    sessionStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({
        date: todayKey(),
        checkInTime: checkInTime || null,
        checkOutTime: checkOutTime || null,
      }),
    );
  } catch {
    /* ignore quota */
  }
}
