import { computed, onMounted, ref } from 'vue';

const permission = ref(typeof Notification === 'undefined' ? 'unsupported' : Notification.permission);
const pushReady = ref(false);
const configured = ref(false);
const vapidChecked = ref(false);
const enabling = ref(false);
const publicKey = ref(null);
const lastError = ref('');

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

function delay(ms) {
  return new Promise((resolve) => { window.setTimeout(resolve, ms); });
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
  const registration = await navigator.serviceWorker.ready;
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
    const ready = await navigator.serviceWorker.ready;
    return subscribeOnce(ready, keyBytes);
  }
}

async function syncExistingSubscription() {
  if (!pushSupported.value || permission.value !== 'granted' || !publicKey.value) {
    return;
  }
  try {
    const registration = await getPushRegistration();
    const existing = await registration.pushManager.getSubscription();
    if (!existing) return;
    await persistSubscription(existing);
    pushReady.value = true;
  } catch {
    pushReady.value = false;
  }
}

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
    return true;
  } catch (error) {
    pushReady.value = false;
    lastError.value = errorMessage(error);
    return false;
  } finally {
    enabling.value = false;
  }
}

export async function disablePush() {
  lastError.value = '';
  enabling.value = true;
  try {
    if (pushSupported.value && 'serviceWorker' in navigator) {
      const registration = await navigator.serviceWorker.getRegistration('/');
      const existing = registration
        ? await registration.pushManager.getSubscription()
        : null;
      if (existing) {
        try {
          await window.axios.delete('/api/notifications/push/subscribe', {
            data: { endpoint: existing.endpoint },
          });
        } catch {
          // vẫn hủy đăng ký local
        }
        await existing.unsubscribe().catch(() => {});
      }
    }
    pushReady.value = false;
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
    await loadVapid();
    await getPushRegistration().catch(() => {});
    await syncExistingSubscription();
  });

  return {
    permission,
    pushReady,
    pushSupported,
    configured,
    vapidChecked,
    enabling,
    lastError,
    isBraveBrowser,
    enablePush,
    disablePush,
  };
}
