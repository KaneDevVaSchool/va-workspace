function toLocalDayStart(value) {
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value));
  if (match) {
    return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])).getTime();
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return null;
  return new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
}

/**
 * Tính tiến độ dự kiến (%) theo thời gian: tỉ lệ số ngày đã trôi qua giữa
 * start_date và end_date tính đến hôm nay. Dùng cho thanh tiến độ 2 lớp
 * (thực tế vs dự kiến) ở Task/Project.
 *
 * Ngày kết thúc tính hết ngày (23:59:59 local), không parse ISO thành
 * 00:00 UTC — tránh hiện 100% ngay trong ngày hạn.
 * Trả về null nếu thiếu ngày hoặc khoảng không hợp lệ — khi đó không hiện lớp dự kiến.
 */
export function computeExpectedProgress(startDate, endDate) {
  if (!startDate || !endDate) return null;

  const start = toLocalDayStart(startDate);
  const endDay = toLocalDayStart(endDate);
  if (start == null || endDay == null || endDay < start) return null;

  const end = endDay + 24 * 60 * 60 * 1000 - 1;
  const now = Date.now();

  if (now <= start) return 0;
  if (now >= end) return 100;

  return Math.round(((now - start) / (end - start)) * 1000) / 10;
}
