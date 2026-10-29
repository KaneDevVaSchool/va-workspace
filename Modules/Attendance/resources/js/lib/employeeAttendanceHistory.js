import { addDays, startOfWeekMonday } from './employeeHistoryWeek.js';

const WEEKDAYS = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];

export function dateKey(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

export function isLateTime(value) {
  if (!value) return false;
  const [hour, minute] = String(value).split(':').map(Number);
  if (Number.isNaN(hour) || Number.isNaN(minute)) return false;
  return hour > 8 || (hour === 8 && minute > 15);
}

function rowFromLog(date, log, todayKey) {
  const key = dateKey(date);
  const year = date.getFullYear();
  const month = date.getMonth();
  const day = date.getDate();
  const dateText = `${String(day).padStart(2, '0')}/${String(month + 1).padStart(2, '0')}/${year}`;
  const title = key === todayKey
    ? `Hôm nay, ${WEEKDAYS[date.getDay()]} - ${dateText}`
    : `${WEEKDAYS[date.getDay()]} - ${dateText}`;

  const real = log?.[key];
  const checkIn = real?.morningInTime ?? real?.checkInTime ?? null;
  const checkOut = real?.afternoonOutTime ?? real?.checkOutTime ?? null;
  const hasReal = Boolean(
    checkIn || checkOut || real?.morningOutTime || real?.afternoonInTime,
  );

  if (key === todayKey && !hasReal) {
    return { key, title, kind: 'pending', date, weekKey: null };
  }

  if (!hasReal) {
    return null;
  }

  const late = isLateTime(checkIn);
  return {
    key,
    title,
    kind: 'work',
    date,
    late,
    checkIn,
    checkOut,
    methodIn: real.morningInMethod ?? real.checkInMethod,
    methodOut: real.afternoonOutMethod ?? real.checkOutMethod,
  };
}

function countWeekdayRange(fromDay, toDay, year, month) {
  let count = 0;
  for (let day = fromDay; day <= toDay; day += 1) {
    const date = new Date(year, month, day);
    if (date.getDay() !== 0) count += 1;
  }
  return count;
}

/**
 * Tháng chấm công — chỉ dữ liệu thật trên máy (không minh họa).
 * @param {Date} cursor
 * @param {Record<string, object>} log
 */
export function buildAttendanceMonth(cursor, log) {
  const year = cursor.getFullYear();
  const month = cursor.getMonth();
  const today = new Date();
  const todayKey = dateKey(today);
  const isCurrent = today.getFullYear() === year && today.getMonth() === month;
  const lastDay = isCurrent ? today.getDate() : new Date(year, month + 1, 0).getDate();
  const rows = [];
  const stats = { scheduled: 0, onTime: 0, late: 0, absent: 0, recorded: 0 };

  const weekdayElapsed = countWeekdayRange(1, lastDay, year, month);
  stats.scheduled = weekdayElapsed;

  for (let day = lastDay; day >= 1; day -= 1) {
    const date = new Date(year, month, day);
    if (date.getDay() === 0) continue;

    const real = log?.[dateKey(date)];
    const checkIn = real?.morningInTime ?? real?.checkInTime ?? null;
    const checkOut = real?.afternoonOutTime ?? real?.checkOutTime ?? null;
    const hasReal = Boolean(
      checkIn || checkOut || real?.morningOutTime || real?.afternoonInTime,
    );

    const key = dateKey(date);
    const isPast = key < todayKey;

    if (hasReal) {
      stats.recorded += 1;
    } else if (isPast && key !== todayKey) {
      stats.absent += 1;
    }

    const row = rowFromLog(date, log, todayKey);
    if (row) rows.push(row);
  }

  for (const row of rows) {
    if (row.kind !== 'work') continue;
    if (row.late) stats.late += 1;
    else stats.onTime += 1;
  }

  return { rows, stats };
}

/**
 * Lưới 7 ngày (T2–CN) cho một tuần.
 * @param {Date} weekStart — thứ Hai
 * @param {Record<string, object>} log
 */
export function buildAttendanceWeekGrid(weekStart, log) {
  const today = new Date();
  const todayKey = dateKey(today);
  const days = [];

  for (let i = 0; i < 7; i += 1) {
    const date = addDays(weekStart, i);
    const key = dateKey(date);
    const row = rowFromLog(date, log, todayKey);
    const inMonth = date.getMonth();
    days.push({
      key,
      date,
      weekday: date.getDay(),
      dayNum: date.getDate(),
      month: inMonth + 1,
      isToday: key === todayKey,
      isFuture: key > todayKey,
      row,
    });
  }

  const workRows = days.filter((d) => d.row?.kind === 'work');
  const stats = {
    recorded: workRows.length,
    onTime: workRows.filter((d) => !d.row.late).length,
    late: workRows.filter((d) => d.row.late).length,
    pending: days.some((d) => d.row?.kind === 'pending') ? 1 : 0,
  };

  return { days, stats, weekStart, weekLabel: formatWeekLabel(weekStart) };
}

function formatWeekLabel(weekStart) {
  const end = addDays(weekStart, 6);
  const fmt = (d) => d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' });
  const endPart = end.toLocaleDateString('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
  return `${fmt(weekStart)} – ${endPart}`;
}

export { startOfWeekMonday, WEEKDAYS };
