//
// iOS Safari (tab): chiều cao layout theo visualViewport khi URL bar co giãn.
// PWA standalone: dùng innerHeight + inset:0 trên shell — không lắng nghe
// visualViewport.scroll (hay làm shell thấp hơn màn → tabbar “nổi” lên).
//
import { onBeforeUnmount, onMounted } from 'vue';

const ROOT_VAR = '--employee-mobile-vh';

function isPwaStandalone() {
  if (typeof document === 'undefined') return false;
  return document.documentElement.classList.contains('pwa-standalone');
}

function readViewportHeightPx() {
  if (typeof window === 'undefined') return 0;
  if (isPwaStandalone()) {
    return Math.max(0, Math.round(window.innerHeight));
  }
  const vv = window.visualViewport;
  const h = vv?.height ?? window.innerHeight;
  return Math.max(0, Math.round(h));
}

function syncMobileViewportHeight() {
  if (typeof document === 'undefined') return;
  if (isPwaStandalone()) {
    document.documentElement.style.removeProperty(ROOT_VAR);
    return;
  }
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
