//
// Phân biệt shell mobile trình duyệt (tab) vs PWA cài màn hình — class
// employee-shell--browser | employee-shell--pwa để chỉnh CSS riêng từng mode.
//
import { computed, onMounted, ref } from 'vue';
import { isPwaStandalone } from '@/lib/pwaStandalone';

export function useEmployeeMobileShell() {
  const standalone = ref(typeof window !== 'undefined' && isPwaStandalone());

  onMounted(() => {
    standalone.value = isPwaStandalone();
  });

  const shellMode = computed(() => (standalone.value ? 'pwa' : 'browser'));

  return {
    shellMode,
    isMobilePwa: computed(() => standalone.value),
    isMobileBrowser: computed(() => !standalone.value),
  };
}
