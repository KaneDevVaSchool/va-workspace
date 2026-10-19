import './bootstrap';
import './echo';
import '../css/app.css';

import {
  applyPwaStandaloneClass,
  isPwaStandalone,
  loadPwaStandaloneAssets,
} from './lib/pwaStandalone';
import { syncEmployeeSafeAreaInsets } from './lib/employeeSafeArea';
import { bootstrapPwaVisualViewport } from './lib/pwaVisualViewport';
import { bootstrapPwaServiceWorker } from './composables/usePwaUpdate';

applyPwaStandaloneClass();
loadPwaStandaloneAssets();
bootstrapPwaVisualViewport();

if (typeof document !== 'undefined' && isPwaStandalone()) {
  syncEmployeeSafeAreaInsets();
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      syncEmployeeSafeAreaInsets();
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

router.isReady().then(() => {
  requestAnimationFrame(() => dismissAppBoot());
});
