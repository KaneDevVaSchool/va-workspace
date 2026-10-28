import './bootstrap';
import './echo';
import '../css/app.css';

import {
  applyPwaStandaloneClass,
  isPwaStandalone,
  loadPwaStandaloneAssets,
} from './lib/pwaStandalone';
import { syncEmployeeSafeAreaInsets } from './lib/employeeSafeArea';
import { syncEmployeeShellHeight } from './lib/employeeMobileViewport';
import { bootstrapPwaVisualViewport } from './lib/pwaVisualViewport';
import { bootstrapPwaServiceWorker } from './composables/usePwaUpdate';

applyPwaStandaloneClass();
loadPwaStandaloneAssets();
bootstrapPwaVisualViewport();

if (typeof document !== 'undefined' && isPwaStandalone()) {
  syncEmployeeSafeAreaInsets();
  syncEmployeeShellHeight();
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      syncEmployeeSafeAreaInsets();
      syncEmployeeShellHeight();
    }
  });
  window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
      syncEmployeeSafeAreaInsets();
      syncEmployeeShellHeight();
    }
  });
}
bootstrapPwaServiceWorker();

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router';
import App from './App.vue';
import { dismissAppBoot } from './lib/dismissAppBoot';

const app = createApp(App);

app.use(createPinia());
app.use(router);

app.mount('#app');

// Bấm thông báo đẩy khi app đang mở: service worker gửi đích tới trang để điều hướng trong
// SPA (không reload — giữ nháp chat/form đang gõ). Chỉ nhận đường dẫn cùng origin.
if (typeof navigator !== 'undefined' && 'serviceWorker' in navigator) {
  navigator.serviceWorker.addEventListener('message', (event) => {
    const url = event.data?.type === 'NAVIGATE' ? event.data.url : null;
    if (typeof url !== 'string' || !url.startsWith('/') || url.startsWith('//')) return;
    router.push(url).catch(() => {});
  });
  navigator.serviceWorker.startMessages?.();
}

const finishAppBoot = () => requestAnimationFrame(() => dismissAppBoot());

router.isReady().finally(finishAppBoot);

if (typeof window !== 'undefined') {
  window.setTimeout(finishAppBoot, 8_000);
}
