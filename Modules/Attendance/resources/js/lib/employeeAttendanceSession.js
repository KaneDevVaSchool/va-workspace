const STORAGE_KEY = 'employee-attendance-session-v1';
const LOG_KEY = 'employee-attendance-log-v1';

function todayKey() {
  const d = new Date();
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function emptyRecord() {
  return {
    checkInTime: null,
    checkOutTime: null,
    checkInMethod: null,
    checkOutMethod: null,
  };
}

function normalizeRecord(parsed) {
  if (!parsed || typeof parsed !== 'object') return emptyRecord();
  return {
    checkInTime: parsed.checkInTime || null,
    checkOutTime: parsed.checkOutTime || null,
    checkInMethod: parsed.checkInMethod || null,
    checkOutMethod: parsed.checkOutMethod || null,
  };
}

function readLog() {
  if (typeof localStorage === 'undefined') return {};
  try {
    const raw = localStorage.getItem(LOG_KEY);
    if (!raw) return {};
    const parsed = JSON.parse(raw);
    return parsed && typeof parsed === 'object' ? parsed : {};
  } catch {
    return {};
  }
}

export function readEmployeeAttendanceToday() {
  if (typeof sessionStorage === 'undefined') return null;
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    if (parsed?.date !== todayKey()) return null;
    return normalizeRecord(parsed);
  } catch {
    return null;
  }
}

export function readEmployeeAttendanceLog() {
  const log = readLog();
  const out = {};
  Object.entries(log).forEach(([date, row]) => {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return;
    out[date] = normalizeRecord(row);
  });
  return out;
}

export function writeEmployeeAttendanceToday({
  checkInTime,
  checkOutTime,
  checkInMethod = null,
  checkOutMethod = null,
}) {
  const record = {
    checkInTime: checkInTime || null,
    checkOutTime: checkOutTime || null,
    checkInMethod: checkInMethod || null,
    checkOutMethod: checkOutMethod || null,
  };
  const date = todayKey();

  if (typeof sessionStorage !== 'undefined') {
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ date, ...record }));
    } catch {
      /* ignore quota */
    }
  }

  if (typeof localStorage === 'undefined') return;
  try {
    const log = readLog();
    log[date] = record;
    const cutoff = new Date();
    cutoff.setDate(cutoff.getDate() - 120);
    const cutoffKey = `${cutoff.getFullYear()}-${String(cutoff.getMonth() + 1).padStart(2, '0')}-${String(cutoff.getDate()).padStart(2, '0')}`;
    Object.keys(log).forEach((key) => {
      if (key < cutoffKey) delete log[key];
    });
    localStorage.setItem(LOG_KEY, JSON.stringify(log));
  } catch {
    /* ignore quota */
  }
}

export function clearEmployeeAttendanceToday() {
  const date = todayKey();
  if (typeof sessionStorage !== 'undefined') {
    try {
      sessionStorage.removeItem(STORAGE_KEY);
    } catch {
      /* ignore */
    }
  }
  if (typeof localStorage === 'undefined') return;
  try {
    const log = readLog();
    delete log[date];
    localStorage.setItem(LOG_KEY, JSON.stringify(log));
  } catch {
    /* ignore quota */
  }
}
