import { computed, ref } from 'vue';

const openId = ref(null);
let seq = 0;

export function useUserAvatarTip() {
  const id = ++seq;
  const isOpen = computed(() => openId.value === id);

  function toggle() {
    openId.value = isOpen.value ? null : id;
  }

  function close() {
    if (openId.value === id) {
      openId.value = null;
    }
  }

  return { isOpen, toggle, close };
}
