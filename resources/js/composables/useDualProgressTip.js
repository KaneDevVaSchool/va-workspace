import { computed, ref } from 'vue';

// Chỉ 1 popup DualProgressBar được mở tại 1 thời điểm trên toàn trang,
// theo đúng mẫu useUserAvatarTip.js.
const openId = ref(null);
let seq = 0;

export function useDualProgressTip() {
  const id = ++seq;
  const isOpen = computed(() => openId.value === id);

  function open() {
    openId.value = id;
  }

  function close() {
    if (openId.value === id) {
      openId.value = null;
    }
  }

  return { isOpen, open, close };
}
