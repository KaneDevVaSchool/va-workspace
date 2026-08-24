import { computed, ref } from 'vue';

const openId = ref(null);

export function closeHeaderPopovers() {
  openId.value = null;
}

export function useHeaderPopover(id) {
  const isOpen = computed(() => openId.value === id);

  function toggle() {
    openId.value = openId.value === id ? null : id;
  }

  function open() {
    openId.value = id;
  }

  function close() {
    if (openId.value === id) {
      openId.value = null;
    }
  }

  return { isOpen, toggle, open, close };
}
