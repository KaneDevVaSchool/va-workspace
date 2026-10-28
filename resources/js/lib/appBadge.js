//
// Số chưa đọc trên icon app (Badging API): Android/desktop Chrome/Edge và iOS 16.4+
// khi đã thêm vào Màn hình chính (cần quyền thông báo). Trình duyệt không hỗ trợ thì bỏ qua.
//
export function setAppBadgeCount(count) {
  if (typeof navigator === 'undefined') return;
  const n = Math.max(0, Math.floor(Number(count) || 0));
  try {
    if (n > 0 && typeof navigator.setAppBadge === 'function') {
      navigator.setAppBadge(n).catch(() => {});
    } else if (n === 0 && typeof navigator.clearAppBadge === 'function') {
      navigator.clearAppBadge().catch(() => {});
    }
  } catch {
    // Badging API có thể ném đồng bộ khi chưa đủ quyền — không ảnh hưởng app
  }
}
