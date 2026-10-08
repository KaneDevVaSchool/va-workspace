//
// Gỡ màn hình boot (#app-boot) sau khi Vue + router guard (fetchMe…) xong.
// HTML/CSS boot nằm trong app.blade.php để hiện ngay trước khi Vite bundle tải.
//
const BOOT_HIDE_MS = 420;

export function dismissAppBoot() {
  if (typeof document === 'undefined') return;

  document.body.classList.remove('app-boot-active');

  const el = document.getElementById('app-boot');
  if (!el || el.classList.contains('app-boot--hide')) return;

  el.classList.add('app-boot--hide');

  const remove = () => {
    el.remove();
  };

  el.addEventListener('transitionend', remove, { once: true });
  window.setTimeout(remove, BOOT_HIDE_MS + 80);
}
