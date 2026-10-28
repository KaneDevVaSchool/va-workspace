//
// Bắt sự kiện beforeinstallprompt (Chrome/Edge/Android) một lần ở module
// scope — không phải trong component — vì trình duyệt chỉ bắn sự kiện này
// 1 lần sớm trong vòng đời trang, trước khi component nào kịp mount.
//
import { computed, ref } from 'vue';
import { isIosDevice } from '@/lib/employeeDeviceOs';
import { isPwaStandalone, PWA_DISPLAY_MODE_MEDIA } from '@/lib/pwaStandalone';

const DISMISSED_KEY = 'va-pwa-install-dismissed-at';
const DISMISS_SNOOZE_DAYS = 14;

const deferredPrompt = ref(null);
const installed = ref(false);
/** Tăng mỗi lần ghi dấu "để sau" — localStorage không reactive nên computed cần tín hiệu này để tính lại. */
const snoozeTick = ref(0);

installed.value = isPwaStandalone();

if (typeof window !== 'undefined') {
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredPrompt.value = event;
  });

  window.addEventListener('appinstalled', () => {
    installed.value = true;
    deferredPrompt.value = null;
  });

  if (typeof window.matchMedia === 'function') {
    const mq = window.matchMedia(PWA_DISPLAY_MODE_MEDIA);
    const syncInstalled = () => {
      installed.value = isPwaStandalone();
    };
    if (typeof mq.addEventListener === 'function') {
      mq.addEventListener('change', syncInstalled);
    } else if (typeof mq.addListener === 'function') {
      mq.addListener(syncInstalled);
    }
  }
}

function wasDismissedRecently() {
  try {
    const raw = localStorage.getItem(DISMISSED_KEY);
    if (!raw) return false;
    const dismissedAt = Number(raw);
    if (!Number.isFinite(dismissedAt)) return false;
    const days = (Date.now() - dismissedAt) / (1000 * 60 * 60 * 24);
    return days < DISMISS_SNOOZE_DAYS;
  } catch {
    return false;
  }
}

function snooze() {
  try {
    localStorage.setItem(DISMISSED_KEY, String(Date.now()));
  } catch {
    // localStorage có thể bị chặn — bỏ qua, banner vẫn ẩn trong phiên này.
  }
  snoozeTick.value += 1;
}

export function usePwaInstall() {
  const canInstall = computed(() => {
    // eslint-disable-next-line no-unused-expressions
    snoozeTick.value;
    return Boolean(deferredPrompt.value) && !installed.value && !wasDismissedRecently();
  });

  // iPhone/iPad không có beforeinstallprompt: người dùng phải tự bấm Chia sẻ → Thêm vào Màn hình chính,
  // nên cần hướng dẫn bằng chữ. Chỉ hiện khi chưa cài và chưa bấm "để sau".
  const showIosGuide = computed(() => {
    // eslint-disable-next-line no-unused-expressions
    snoozeTick.value;
    return isIosDevice() && !installed.value && !wasDismissedRecently();
  });

  async function promptInstall() {
    const prompt = deferredPrompt.value;
    if (!prompt) return false;
    prompt.prompt();
    const { outcome } = await prompt.userChoice;
    deferredPrompt.value = null;
    // Người dùng từ chối hộp thoại của trình duyệt: đừng mời lại ngay ở lần mở app kế tiếp.
    if (outcome !== 'accepted') snooze();
    return outcome === 'accepted';
  }

  function dismiss() {
    snooze();
    deferredPrompt.value = null;
  }

  return { canInstall, showIosGuide, installed, promptInstall, dismiss };
}
