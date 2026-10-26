//
// Shell mobile không dùng safe-area iOS. Hàm này chỉ xóa biến inline cũ
// (--employee-safe-* từng bị probe gán 34px) để stylesheet 0px có hiệu lực.
//

const SIDES = ['top', 'bottom', 'left', 'right'];

export function syncEmployeeSafeAreaInsets() {
  if (typeof document === 'undefined') return;
  const root = document.documentElement;
  for (const side of SIDES) {
    root.style.removeProperty(`--employee-safe-${side}`);
  }
}
