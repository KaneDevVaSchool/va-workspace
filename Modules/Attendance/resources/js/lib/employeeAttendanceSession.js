const STORAGE_KEY = 'employee-attendance-session-v2';
const LOG_KEY = 'employee-attendance-log-v2';
const LEGACY_LOG_KEY = 'employee-attendance-log-v1';

function todayKey() {
  const d = new Date();
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function emptyRecord() {
  return {
    morningInTime: null,
    morningOutTime: null,
    afternoonInTime: null,
    afternoonOutTime: null,
    morningInMethod: null,
    morningOutMethod: null,
    afternoonInMethod: null,
    afternoonOutMethod: null,
  };
}

function normalizeRecord(parsed) {
  if (!parsed || typeof parsed !== 'object') return emptyRecord();
  if (parsed.morningInTime != null || parsed.morningOutTime != null
    || parsed.afternoonInTime != null || parsed.afternoonOutTime != null) {
    return {
      morningInTime: parsed.morningInTime || null,
      morningOutTime: parsed.morningOutTime || null,
      afternoonInTime: parsed.afternoonInTime || null,
      afternoonOutTime: parsed.afternoonOutTime || null,
      morningInMethod: parsed.morningInMethod || null,
      morningOutMethod: parsed.morningOutMethod || null,
      afternoonInMethod: parsed.afternoonInMethod || null,
      afternoonOutMethod: parsed.afternoonOutMethod || null,
    };
  }
  return {
    morningInTime: parsed.checkInTime || null,
    morningOutTime: parsed.morningOutTime || null,
    afternoonInTime: parsed.afternoonInTime || null,
    afternoonOutTime: parsed.checkOutTime || null,
    morningInMethod: parsed.checkInMethod || null,
    morningOutMethod: parsed.morningOutMethod || null,
    afternoonInMethod: parsed.afternoonInMethod || null,
    afternoonOutMethod: parsed.checkOutMethod || null,
  };
}

function readLog() {
  if (typeof localStorage === 'undefined') return {};
  try {
    const raw = localStorage.getItem(LOG_KEY);
    if (raw) {
      const parsed = JSON.parse(raw);
      if (parsed && typeof parsed === 'object') return parsed;
    }
    const legacyRaw = localStorage.getItem(LEGACY_LOG_KEY);
    if (!legacyRaw) return {};
    const legacy = JSON.parse(legacyRaw);
    if (!legacy || typeof legacy !== 'object') return {};
    const migrated = {};
    Object.entries(legacy).forEach(([date, row]) => {
      migrated[date] = normalizeRecord(row);
    });
    localStorage.setItem(LOG_KEY, JSON.stringify(migrated));
    return migrated;
  } catch {
    return {};
  }
}

export function readEmployeeAttendanceToday() {
  const today = todayKey();

  if (typeof sessionStorage !== 'undefined') {
    try {
      const raw = sessionStorage.getItem(STORAGE_KEY);
      if (raw) {
        const parsed = JSON.parse(raw);
        if (parsed?.date === today) return normalizeRecord(parsed);
      }
    } catch {
      /* fall through to local log */
    }
  }

  const fromLog = readLog()[today];
  return fromLog ? normalizeRecord(fromLog) : null;
}

export function recordFromServerRow(row) {
  return normalizeRecord({
    morningInTime: row?.check_in_time,
    morningOutTime: row?.morning_check_out_time,
    afternoonInTime: row?.afternoon_check_in_time,
    afternoonOutTime: row?.check_out_time,
    morningInMethod: row?.check_in_method,
    morningOutMethod: row?.morning_check_out_method,
    afternoonInMethod: row?.afternoon_check_in_method,
    afternoonOutMethod: row?.check_out_method,
  });
}

export function recordToServerPayload(record) {
  const row = normalizeRecord(record);
  const payload = {};
  if (row.morningInTime) {
    payload.check_in_time = row.morningInTime;
    payload.check_in_method = row.morningInMethod;
  }
  if (row.morningOutTime) {
    payload.morning_check_out_time = row.morningOutTime;
    payload.morning_check_out_method = row.morningOutMethod;
  }
  if (row.afternoonInTime) {
    payload.afternoon_check_in_time = row.afternoonInTime;
    payload.afternoon_check_in_method = row.afternoonInMethod;
  }
  if (row.afternoonOutTime) {
    payload.check_out_time = row.afternoonOutTime;
    payload.check_out_method = row.afternoonOutMethod;
  }
  return payload;
}

/** Giờ HRM trả về ghi đè nhật ký trên máy (kể cả phiên hôm nay). */
export function applyServerAttendancePunches(rows) {
  if (!Array.isArray(rows) || rows.length === 0) return;
  const log = readLog();
  rows.forEach((row) => {
    const date = String(row?.work_date || '');
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return;
    log[date] = recordFromServerRow(row);
  });

  if (typeof localStorage !== 'undefined') {
    try {
      localStorage.setItem(LOG_KEY, JSON.stringify(log));
    } catch {
      /* ignore quota */
    }
  }

  const today = todayKey();
  const todayRow = log[today];
  if (!todayRow || typeof sessionStorage === 'undefined') return;
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ date: today, ...todayRow }));
  } catch {
    /* ignore quota */
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

export function writeEmployeeAttendanceToday(record) {
  const normalized = normalizeRecord(record);
  const date = todayKey();

  if (typeof sessionStorage !== 'undefined') {
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ date, ...normalized }));
    } catch {
      /* ignore quota */
    }
  }

  if (typeof localStorage === 'undefined') return;
  try {
    const log = readLog();
    log[date] = normalized;
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

export function hasAnyPunch(record) {
  const row = normalizeRecord(record);
  return Boolean(
    row.morningInTime
    || row.morningOutTime
    || row.afternoonInTime
    || row.afternoonOutTime,
  );
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
