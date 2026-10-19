//
// iOS Safari (tab): chiều cao layout theo visualViewport khi URL bar co giãn.
// PWA standalone: không ghi --employee-mobile-vh; shell CSS dùng inset:0.
// Tab Safari: visualViewport (không lắng nghe vv trên PWA — tránh co shell).
//
import { onBeforeUnmount, onMounted } from 'vue';
import { syncEmployeeSafeAreaInsets } from '@/lib/employeeSafeArea';
import { isPwaStandalone } from '@/lib/pwaStandalone';

const ROOT_VAR = '--employee-mobile-vh';

function readBrowserViewportHeightPx() {
  if (typeof window === 'undefined') return 0;
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
  if (isPwaStandalone()) {
    document.documentElement.style.removeProperty(ROOT_VAR);
    return;
  }
  const height = readBrowserViewportHeightPx();
  if (height > 0) {
    document.documentElement.style.setProperty(ROOT_VAR, `${height}px`);
  }
}

export function useEmployeeMobileViewport() {
  let rafId = 0;
  let boundVisualViewport = false;

  function scheduleSync() {
    if (typeof window === 'undefined') return;
    cancelAnimationFrame(rafId);
    rafId = requestAnimationFrame(() => {
      syncEmployeeMobileViewport();
    });
  }

  function bindVisualViewport() {
    if (boundVisualViewport || typeof window === 'undefined') return;
    if (isPwaStandalone()) return;
    window.visualViewport?.addEventListener('resize', scheduleSync, { passive: true });
    boundVisualViewport = true;
  }

  function unbindVisualViewport() {
    if (!boundVisualViewport || typeof window === 'undefined') return;
    window.visualViewport?.removeEventListener('resize', scheduleSync);
    boundVisualViewport = false;
  }

  onMounted(() => {
    syncEmployeeMobileViewport();
    scheduleSync();
    window.addEventListener('resize', scheduleSync, { passive: true });
    window.addEventListener('orientationchange', scheduleSync, { passive: true });
    bindVisualViewport();
  });

  onBeforeUnmount(() => {
    cancelAnimationFrame(rafId);
    if (typeof window === 'undefined') return;
    window.removeEventListener('resize', scheduleSync);
    window.removeEventListener('orientationchange', scheduleSync);
    unbindVisualViewport();
    document.documentElement.style.removeProperty(ROOT_VAR);
  });
}
