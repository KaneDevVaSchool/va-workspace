import { ref } from 'vue';

// `navigator.onLine` chỉ biết máy có nối mạng hay không (không chắc có internet) —
// đủ để hiện "đang offline"; lỗi API thật vẫn được các màn xử lý riêng.
const online = ref(typeof navigator === 'undefined' ? true : navigator.onLine !== false);
let attached = false;

function attach() {
  if (attached || typeof window === 'undefined') return;
  attached = true;
  window.addEventListener('online', () => { online.value = true; });
  window.addEventListener('offline', () => { online.value = false; });
}

export function useOnlineStatus() {
  attach();
  return { online };
}
