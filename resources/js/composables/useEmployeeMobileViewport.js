//
// Composable: đồng bộ chiều cao shell + safe-area khi mount/resize.
// Logic đo: resources/js/lib/employeeMobileViewport.js
//
import { onBeforeUnmount, onMounted } from 'vue';
import { syncEmployeeSafeAreaInsets } from '@/lib/employeeSafeArea';
import {
  clearEmployeeShellHeight,
  employeeShellViewportMode,
  syncEmployeeShellHeight,
} from '@/lib/employeeMobileViewport';
import { isPwaStandalone } from '@/lib/pwaStandalone';

export function syncEmployeeMobileViewport() {
  syncEmployeeShellHeight();
  syncEmployeeSafeAreaInsets();
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
    clearEmployeeShellHeight();
  });

  return { viewportMode: employeeShellViewportMode };
}
