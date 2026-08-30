/**
 * Tính tiến độ dự kiến (%) theo thời gian: tỉ lệ số ngày đã trôi qua giữa
 * start_date và end_date tính đến hôm nay. Dùng cho thanh tiến độ 2 lớp
 * (thực tế vs dự kiến) ở Task/Project.
 *
 * Trả về null nếu thiếu ngày hoặc khoảng thời gian không hợp lệ — khi đó
 * component không hiển thị lớp dự kiến.
 */
export function computeExpectedProgress(startDate, endDate) {
  if (!startDate || !endDate) return null;

  const start = new Date(startDate).getTime();
  const end = new Date(endDate).getTime();
  const now = Date.now();

  if (!Number.isFinite(start) || !Number.isFinite(end) || end <= start) return null;
  if (now <= start) return 0;
  if (now >= end) return 100;

  return Math.round(((now - start) / (end - start)) * 1000) / 10;
}
