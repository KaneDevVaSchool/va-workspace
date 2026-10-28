import { computed, onMounted, ref } from 'vue';
import { isIosDevice } from '../lib/employeeDeviceOs';
import { isPwaStandalone } from '../lib/pwaStandalone';
import { onBeforeLogout, onSessionCleanup } from '../lib/sessionCleanup';

const OPTOUT_KEY = 'va-push-optout';
const SW_READY_TIMEOUT_MS = 10_000;

function readOptOut() {
  try {
    return localStorage.getItem(OPTOUT_KEY) === '1';
  } catch {
    return false;
  }
}

function writeOptOut(value) {
  try {
    if (value) localStorage.setItem(OPTOUT_KEY, '1');
    else localStorage.removeItem(OPTOUT_KEY);
  } catch {
    // localStorage bị chặn — chỉ mất việc nhớ lựa chọn, không ảnh hưởng đăng ký
  }
}

const permission = ref(typeof Notification === 'undefined' ? 'unsupported' : Notification.permission);
const pushReady = ref(false);
const configured = ref(false);
const vapidChecked = ref(false);
const enabling = ref(false);
const publicKey = ref(null);
const lastError = ref('');
/** Người dùng đã chủ động tắt thông báo đẩy trên trình duyệt này — không tự đăng ký lại, không nhắc lại. */
const optedOut = ref(readOptOut());

const isBraveBrowser = computed(() => {
  if (typeof navigator === 'undefined') return false;
  return Boolean(navigator.brave) || /Brave/i.test(navigator.userAgent);
});

const pushSupported = computed(
  () => typeof window !== 'undefined'
    && window.isSecureContext
    && 'serviceWorker' in navigator
    && 'PushManager' in window
    && typeof Notification !== 'undefined',
);

/** iPhone/iPad chỉ có Web Push khi đã thêm app vào Màn hình chính — trong tab Safari không có PushManager. */
const iosNeedsInstall = computed(
  () => isIosDevice() && !isPwaStandalone() && !pushSupported.value,
);

function delay(ms) {
  return new Promise((resolve) => { window.setTimeout(resolve, ms); });
}

/** `serviceWorker.ready` không bao giờ settle nếu SW không kích hoạt được — đặt hạn để nút không quay mãi. */
function waitForServiceWorkerReady() {
  return Promise.race([
    navigator.serviceWorker.ready,
    new Promise((_, reject) => {
      window.setTimeout(
        () => reject(new Error('Service worker chưa sẵn sàng. Tải lại trang rồi bật lại.')),
        SW_READY_TIMEOUT_MS,
      );
    }),
  ]);
}

function normalizeVapidKey(value) {
  return String(value || '').trim().replace(/[^A-Za-z0-9\-_]/g, '');
}

function readMetaVapid() {
  const raw = document.querySelector('meta[name="vapid-public-key"]')?.getAttribute('content');
  return normalizeVapidKey(raw) || null;
}

function urlBase64ToUint8Array(base64String) {
  const cleaned = normalizeVapidKey(base64String);
  const padding = '='.repeat((4 - (cleaned.length % 4)) % 4);
  const base64 = (cleaned + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = atob(base64);
  const output = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; i += 1) {
    output[i] = raw.charCodeAt(i);
  }
  if (output.byteLength !== 65 || output[0] !== 0x04) {
    throw new Error('Khóa thông báo đẩy (VAPID) trên máy chủ không hợp lệ.');
  }
  // Chrome đôi khi từ chối view dùng chung buffer — luôn copy ra ArrayBuffer mới.
  return new Uint8Array(output);
}

function keyBytesMatch(subscription, keyBytes) {
  const current = subscription?.options?.applicationServerKey;
  if (!current) return true;
  const currentBytes = current instanceof ArrayBuffer
    ? new Uint8Array(current)
    : new Uint8Array(current.buffer, current.byteOffset, current.byteLength);
  if (currentBytes.byteLength !== keyBytes.byteLength) return false;
  return currentBytes.every((byte, index) => byte === keyBytes[index]);
}

function errorMessage(error) {
  const fromApi = error?.response?.data?.message;
  if (fromApi) return fromApi;
  const raw = String(error?.message ?? '');
  if (error?.name === 'NotAllowedError') {
    return 'Trình duyệt đang chặn thông báo. Hãy cho phép trong cài đặt trang.';
  }
  if (error?.name === 'InvalidAccessError' || /applicationServerKey/i.test(raw)) {
    return 'Khóa thông báo đẩy (VAPID) không được trình duyệt chấp nhận. Kiểm tra VAPID_PUBLIC_KEY rồi tải lại trang.';
  }
  if (/no active service worker/i.test(raw)) {
    return 'Service worker chưa sẵn sàng. Tải lại trang rồi bật lại.';
  }
  if (error?.name === 'AbortError' || /push service/i.test(raw)) {
    if (/user gesture|user activation|permission/i.test(raw)) {
      return 'Trình duyệt yêu cầu thao tác trực tiếp. Hãy bấm lại nút bật thông báo.';
    }
    if (isBraveBrowser.value) {
      return 'Brave đang chặn dịch vụ đẩy của Google. Mở brave://settings/privacy → bật “Use Google services for push messaging”, tải lại trang, rồi bật lại.';
    }
    return 'Trình duyệt không đăng ký được dịch vụ đẩy. Tải lại trang rồi bật lại.';
  }
  if (typeof error?.message === 'string' && error.message) {
    return error.message;
  }
  return 'Không bật được thông báo đẩy.';
}

async function loadVapid() {
  const fromMeta = readMetaVapid();
  if (fromMeta) {
    publicKey.value = fromMeta;
    configured.value = true;
  }
  try {
    const { data } = await window.axios.get('/api/notifications/push/vapid-key');
    configured.value = Boolean(data.configured);
    publicKey.value = data.public_key ? normalizeVapidKey(data.public_key) : fromMeta;
  } catch (error) {
    if (!fromMeta) {
      configured.value = false;
      publicKey.value = null;
      lastError.value = errorMessage(error);
    }
  } finally {
    vapidChecked.value = true;
  }
}

async function persistSubscription(subscription) {
  await window.axios.post('/api/notifications/push/subscribe', {
    ...subscription.toJSON(),
    contentEncoding: 'aes128gcm',
  });
}

async function getPushRegistration() {
  const existing = await navigator.serviceWorker.getRegistration('/');
  if (!existing) {
    await navigator.serviceWorker.register('/sw.js', {
      scope: '/',
      updateViaCache: 'none',
    });
  }
  const registration = await waitForServiceWorkerReady();
  if (!registration.active) {
    throw new Error('Service worker không kích hoạt được. Tải lại trang rồi bật lại.');
  }
  return registration;
}

async function subscribeOnce(registration, keyBytes) {
  try {
    return await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: keyBytes,
    });
  } catch (error) {
    if (error?.name === 'InvalidAccessError') {
      return registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: keyBytes.buffer,
      });
    }
    throw error;
  }
}

async function subscribeToPush(registration, keyBytes) {
  const existing = await registration.pushManager.getSubscription();
  if (existing && keyBytesMatch(existing, keyBytes)) {
    return existing;
  }
  if (existing) {
    await existing.unsubscribe().catch(() => {});
    await delay(400);
  }

  try {
    return await subscribeOnce(registration, keyBytes);
  } catch (first) {
    if (first?.name !== 'AbortError') {
      throw first;
    }
    await delay(700);
    const ready = await waitForServiceWorkerReady();
    return subscribeOnce(ready, keyBytes);
  }
}

/**
 * Đồng bộ subscription của trình duyệt lên server mỗi lần mở app.
 * Quyền thông báo đã cấp mà subscription biến mất (endpoint hết hạn / trình duyệt xoay khoá /
 * VAPID đổi) thì tự đăng ký lại — trừ khi người dùng đã chủ động tắt.
 * Trả 'failed' khi cần thử lại lần mở sau (chưa đăng nhập, mất mạng…).
 */
async function syncExistingSubscription() {
  if (!pushSupported.value || permission.value !== 'granted' || !publicKey.value || optedOut.value) {
    return 'none';
  }
  try {
    const registration = await getPushRegistration();
    const keyBytes = urlBase64ToUint8Array(publicKey.value);
    const existing = await registration.pushManager.getSubscription();
    const subscription = existing && keyBytesMatch(existing, keyBytes)
      ? existing
      : await subscribeToPush(registration, keyBytes);
    await persistSubscription(subscription);
    pushReady.value = true;
    return 'synced';
  } catch {
    pushReady.value = false;
    return 'failed';
  }
}

let initPromise = null;
let resyncListenerAttached = false;

/**
 * Khởi tạo một lần cho cả trang. Trước đây mỗi component dùng useWebPush() tự chạy lại
 * (PwaPushPrompt + HeaderNotifications cùng lúc → hai POST /subscribe đua nhau và một cái
 * có thể 500, làm công tắc hiển thị tắt dù máy đã đăng ký).
 */
function ensureInit() {
  if (initPromise) return initPromise;

  if (!resyncListenerAttached && typeof navigator !== 'undefined' && 'serviceWorker' in navigator) {
    resyncListenerAttached = true;
    // Trình duyệt đổi/hết hạn endpoint: SW báo để đăng ký lại ngay (SW không có CSRF để tự gọi API).
    navigator.serviceWorker.addEventListener('message', (event) => {
      if (event.data?.type !== 'PUSH_RESYNC') return;
      initPromise = null;
      ensureInit();
    });
  }

  initPromise = (async () => {
    await loadVapid();
    await getPushRegistration().catch(() => {});
    return syncExistingSubscription();
  })()
    .catch(() => 'failed')
    .then((status) => {
      // Lần này chưa xong (vd. chưa đăng nhập) → cho lần mount sau thử lại.
      if (status === 'failed') initPromise = null;
      return status;
    });

  return initPromise;
}

/** Xoá subscription của trình duyệt này trên server và trong trình duyệt. */
async function removeCurrentSubscription() {
  if (!pushSupported.value || !('serviceWorker' in navigator)) return;
  const registration = await navigator.serviceWorker.getRegistration('/');
  const existing = registration ? await registration.pushManager.getSubscription() : null;
  if (!existing) return;
  try {
    await window.axios.delete('/api/notifications/push/subscribe', {
      data: { endpoint: existing.endpoint },
    });
  } catch {
    // vẫn hủy đăng ký local
  }
  await existing.unsubscribe().catch(() => {});
}

function resetWebPushState() {
  pushReady.value = false;
  initPromise = null;
}

// Đăng xuất: huỷ push của máy này TRƯỚC khi mất phiên (tránh máy dùng chung vẫn nhận tin của người trước),
// rồi để lần đăng nhập sau khởi tạo lại từ đầu. Không đặt opt-out — đây không phải lựa chọn của người dùng.
onBeforeLogout(removeCurrentSubscription);
onSessionCleanup(resetWebPushState);

export async function enablePush() {
  lastError.value = '';
  if (!pushSupported.value) {
    lastError.value = window.isSecureContext
      ? 'Trình duyệt này không hỗ trợ thông báo đẩy.'
      : 'Thông báo đẩy cần HTTPS (hoặc localhost).';
    return false;
  }
  enabling.value = true;
  try {
    // Xin quyền ngay từ cú bấm — không await mạng trước, Chrome sẽ AbortError vì mất user gesture.
    const result = Notification.permission === 'granted'
      ? 'granted'
      : await Notification.requestPermission();
    permission.value = result;
    if (result !== 'granted') {
      lastError.value = result === 'denied'
        ? 'Trình duyệt đang chặn thông báo. Hãy cho phép trong cài đặt trang.'
        : 'Bạn cần cho phép thông báo để bật đẩy.';
      return false;
    }

    if (!publicKey.value) {
      publicKey.value = readMetaVapid();
    }
    if (!publicKey.value) {
      await loadVapid();
    }
    if (!publicKey.value) {
      lastError.value = lastError.value || 'Máy chủ chưa cấu hình khóa thông báo đẩy (VAPID).';
      return false;
    }

    const keyBytes = urlBase64ToUint8Array(publicKey.value);
    const registration = await getPushRegistration();
    const subscription = await subscribeToPush(registration, keyBytes);
    await persistSubscription(subscription);
    pushReady.value = true;
    optedOut.value = false;
    writeOptOut(false);
    return true;
  } catch (error) {
    pushReady.value = false;
    lastError.value = errorMessage(error);
    return false;
  } finally {
    enabling.value = false;
  }
}

/** Hiện thông báo qua service worker — xuất hiện ở Trung tâm thông báo / màn hình khóa của OS. */
export async function showSystemNotification({
  title = 'VA Workspace',
  body = '',
  tag = 'va-workspace',
  url = '/',
  icon = '/images/pwa/icon-192.png',
  image = null,
} = {}) {
  if (typeof Notification === 'undefined' || Notification.permission !== 'granted') {
    return false;
  }
  try {
    const registration = await getPushRegistration();
    const options = {
      body,
      icon,
      badge: '/images/pwa/apple-touch-icon.png',
      tag,
      renotify: Boolean(tag),
      timestamp: Date.now(),
      vibrate: [72, 36, 72],
      data: { url },
    };
    if (image) {
      options.image = image;
    }
    await registration.showNotification(title, options);
    return true;
  } catch {
    return false;
  }
}

export async function disablePush() {
  lastError.value = '';
  enabling.value = true;
  try {
    await removeCurrentSubscription();
    pushReady.value = false;
    // Nhớ lựa chọn: lần mở sau không tự đăng ký lại và không hiện lại lời mời bật.
    optedOut.value = true;
    writeOptOut(true);
    return true;
  } catch (error) {
    lastError.value = errorMessage(error);
    return false;
  } finally {
    enabling.value = false;
  }
}

export function useWebPush() {
  onMounted(async () => {
    if (typeof Notification !== 'undefined') {
      permission.value = Notification.permission;
    }
    if (!pushSupported.value) {
      vapidChecked.value = true;
      return;
    }
    await ensureInit();
  });

  return {
    permission,
    pushReady,
    pushSupported,
    iosNeedsInstall,
    optedOut,
    configured,
    vapidChecked,
    enabling,
    lastError,
    isBraveBrowser,
    enablePush,
    disablePush,
    showSystemNotification,
  };
}
