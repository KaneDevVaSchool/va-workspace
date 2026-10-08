import './bootstrap';
import './echo';
import '../css/app.css';

import { applyPwaStandaloneClass, bootstrapPwaServiceWorker } from './lib/pwaStandalone';

applyPwaStandaloneClass();
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
