//
// iOS Safari (tab): chiều cao layout theo visualViewport khi URL bar co giãn.
// PWA standalone: khóa theo window.innerHeight (full màn) — không dùng
// visualViewport (hay co shell → tabbar “nổi”, lộ dải trắng body phía dưới).
//
import { onBeforeUnmount, onMounted } from 'vue';
import { syncEmployeeSafeAreaInsets } from '@/lib/employeeSafeArea';
import { isPwaStandalone } from '@/lib/pwaStandalone';

const ROOT_VAR = '--employee-mobile-vh';

function readViewportHeightPx() {
  if (typeof window === 'undefined') return 0;
  if (isPwaStandalone()) {
    return Math.max(0, Math.round(window.innerHeight));
  }
  const vv = window.visualViewport;
  const h = vv?.height ?? window.innerHeight;
  return Math.max(0, Math.round(h));
}

export function syncEmployeeMobileViewport() {
  syncMobileViewportHeight();
  syncEmployeeSafeAreaInsets();
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
      syncEmployeeMobileViewport();
    });
  }

  onMounted(() => {
    syncEmployeeMobileViewport();
    scheduleSync();
    window.addEventListener('resize', scheduleSync, { passive: true });
    window.addEventListener('orientationchange', scheduleSync, { passive: true });
    window.visualViewport?.addEventListener('resize', scheduleSync, { passive: true });
  });

  onBeforeUnmount(() => {
    cancelAnimationFrame(rafId);
    if (typeof window === 'undefined') return;
    window.removeEventListener('resize', scheduleSync);
    window.removeEventListener('orientationchange', scheduleSync);
    window.visualViewport?.removeEventListener('resize', scheduleSync);
    document.documentElement.style.removeProperty(ROOT_VAR);
  });
}
