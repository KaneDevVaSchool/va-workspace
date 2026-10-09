//
// iOS Safari / PWA: 100dvh và fixed inset lệch nhau → tabbar hoặc nội dung “dính”
// mép dưới. Ghi chiều cao visual viewport lên --employee-mobile-vh (px).
//
import { onBeforeUnmount, onMounted } from 'vue';

const ROOT_VAR = '--employee-mobile-vh';

function readViewportHeightPx() {
  if (typeof window === 'undefined') return 0;
  const vv = window.visualViewport;
  const h = vv?.height ?? window.innerHeight;
  return Math.max(0, Math.round(h));
}

function syncMobileViewportHeight() {
  if (typeof document === 'undefined') return;
  const height = readViewportHeightPx();
  if (height > 0) {
    document.documentElement.style.setProperty(ROOT_VAR, `${height}px`);
  }
}

export function useEmployeeMobileViewport() {
  let rafId = 0;

  function scheduleSync() {
    if (typeof window === 'undefined') return;
    cancelAnimationFrame(rafId);
    rafId = requestAnimationFrame(() => {
      syncMobileViewportHeight();
    });
  }

  onMounted(() => {
    syncMobileViewportHeight();
    scheduleSync();
    window.addEventListener('resize', scheduleSync, { passive: true });
    window.addEventListener('orientationchange', scheduleSync, { passive: true });
    window.visualViewport?.addEventListener('resize', scheduleSync, { passive: true });
    window.visualViewport?.addEventListener('scroll', scheduleSync, { passive: true });
  });

  onBeforeUnmount(() => {
    cancelAnimationFrame(rafId);
    if (typeof window === 'undefined') return;
    window.removeEventListener('resize', scheduleSync);
    window.removeEventListener('orientationchange', scheduleSync);
    window.visualViewport?.removeEventListener('resize', scheduleSync);
    window.visualViewport?.removeEventListener('scroll', scheduleSync);
    document.documentElement.style.removeProperty(ROOT_VAR);
  });
}
