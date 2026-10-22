const WEEKDAYS = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];

const SAMPLE_METHODS = [
  'Chấm bằng nút',
  'Quét mã QR',
  'Camera Hanet',
  'GPS',
  'Chấm bằng WiFi',
  'Nhập mã',
];

function dateKey(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function isLateTime(value) {
  if (!value) return false;
  const [hour, minute] = String(value).split(':').map(Number);
  if (Number.isNaN(hour) || Number.isNaN(minute)) return false;
  return hour > 8 || (hour === 8 && minute > 15);
}

/**
 * Tháng chấm công: ngày đã ghi trên máy + các ngày minh họa còn lại.
 * @param {Date} cursor
 * @param {Record<string, { checkInTime?: string|null, checkOutTime?: string|null, checkInMethod?: string|null, checkOutMethod?: string|null }>} log
 */
export function buildAttendanceMonth(cursor, log) {
  const year = cursor.getFullYear();
  const month = cursor.getMonth();
  const today = new Date();
  const todayKey = dateKey(today);
  const isCurrent = today.getFullYear() === year && today.getMonth() === month;
  const lastDay = isCurrent ? today.getDate() : new Date(year, month + 1, 0).getDate();
  const rows = [];
  const stats = { scheduled: 0, onTime: 0, late: 0, absent: 0 };

  for (let day = lastDay; day >= 1; day -= 1) {
    const date = new Date(year, month, day);
    if (date.getDay() === 0) continue;
    const key = dateKey(date);
    const dateText = `${String(day).padStart(2, '0')}/${String(month + 1).padStart(2, '0')}/${year}`;
    const title = key === todayKey
      ? `Hôm nay, ${WEEKDAYS[date.getDay()]} - ${dateText}`
      : `${WEEKDAYS[date.getDay()]} - ${dateText}`;
    const real = log?.[key];
    const hasReal = Boolean(real?.checkInTime || real?.checkOutTime);

    if (key === todayKey && !hasReal) {
      rows.push({ key, title, kind: 'pending' });
      continue;
    }

    stats.scheduled += 1;

    if (hasReal) {
      const late = isLateTime(real.checkInTime);
      if (late) stats.late += 1;
      else stats.onTime += 1;
      rows.push({
        key,
        title,
        kind: 'work',
        sample: false,
        late,
        checkIn: real.checkInTime,
        checkOut: real.checkOutTime,
        methodIn: real.checkInMethod,
        methodOut: real.checkOutMethod,
      });
      continue;
    }

    if (day % 13 === 0) {
      stats.absent += 1;
      rows.push({ key, title, kind: 'absent', sample: true });
      continue;
    }

    const late = day % 9 === 0;
    if (late) stats.late += 1;
    else stats.onTime += 1;
    rows.push({
      key,
      title,
      kind: 'work',
      sample: true,
      late,
      checkIn: late ? '08:27' : `08:0${day % 5}`,
      checkOut: day % 2 === 0 ? '17:32' : '17:58',
      methodIn: SAMPLE_METHODS[day % SAMPLE_METHODS.length],
      methodOut: SAMPLE_METHODS[(day + 2) % SAMPLE_METHODS.length],
    });
  }

  return { rows, stats };
}
