/**
 * Đếm số chạy dần khi hiện KPI — cùng thuật toán easing cubic đã dùng trong
 * Modules/Credential/resources/js/pages/CredentialList.vue::animateCount().
 */
export function animateCount(setter, finalValue, duration = 800) {
  const start = performance.now();
  const from = 0;
  function tick(now) {
    const progress = Math.min((now - start) / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    setter(from + (finalValue - from) * eased);
    if (progress < 1) requestAnimationFrame(tick);
  }
  requestAnimationFrame(tick);
}
