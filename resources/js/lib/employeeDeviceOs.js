//
// Nhận diện iOS/Android — dùng để chia CSS bàn phím ảo/safe-area khác nhau
// giữa 2 hệ (Android Chrome tự co visualViewport khác iOS Safari WebView).
// Class html.employee-os-ios / html.employee-os-android gắn sớm trong
// resources/views/app.blade.php (tránh flash); hàm sync ở đây chỉ để JS phía
// sau (Vue) đọc lại hoặc gắn lại nếu cần (ví dụ test, SSR giả lập).
//

export function isIosDevice() {
  if (typeof navigator === 'undefined') return false;
  return (
    /iPad|iPhone|iPod/.test(navigator.userAgent)
    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
  );
}

export function isAndroidDevice() {
  if (typeof navigator === 'undefined') return false;
  return /Android/.test(navigator.userAgent);
}

export function syncEmployeeDeviceOsClass() {
  if (typeof document === 'undefined') return;
  const root = document.documentElement;
  root.classList.toggle('employee-os-ios', isIosDevice());
  root.classList.toggle('employee-os-android', !isIosDevice() && isAndroidDevice());
}
