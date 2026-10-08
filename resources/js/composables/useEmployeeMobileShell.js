//
// Phân biệt shell mobile trình duyệt (tab) vs PWA cài màn hình.
// Ưu tiên class sớm trên <html> (app.blade.php) — khớp CSS html.pwa-standalone.
//
import { computed, onMounted, ref } from 'vue';
import { isPwaStandalone } from '@/lib/pwaStandalone';

function readStandalone() {
  if (typeof document === 'undefined') return false;
  return (
    document.documentElement.classList.contains('pwa-standalone')
    || isPwaStandalone()
  );
}

export function useEmployeeMobileShell() {
  const standalone = ref(readStandalone());

  onMounted(() => {
    standalone.value = readStandalone();
  });

  const shellMode = computed(() => (standalone.value ? 'pwa' : 'browser'));

  return {
    shellMode,
    isMobilePwa: computed(() => standalone.value),
    isMobileBrowser: computed(() => !standalone.value),
  };
}
