//
// Tab bar footer trong EmployeeMobileLayout — đo chiều cao thật, ghi
// --employee-tabbar-block-size lên <html> cho toast / PWA prompt (không dùng
// padding-bottom giả trên main; tabbar nằm in-flow trong flex shell).
//
import { onBeforeUnmount, ref, watch } from 'vue';

const ROOT_VAR = '--employee-tabbar-block-size';
const FALLBACK = '2.5rem';

export function useEmployeeTabbarDock(tabbarRef) {
  const ready = ref(false);
  let observer;

  function measure() {
    const el = tabbarRef.value;
    if (!el || typeof document === 'undefined') return;
    const height = Math.ceil(el.getBoundingClientRect().height);
    if (height > 0) {
      document.documentElement.style.setProperty(ROOT_VAR, `${height}px`);
    }
    ready.value = true;
  }

  function bindObserver(el) {
    observer?.disconnect();
    if (!el || typeof ResizeObserver === 'undefined') return;
    observer = new ResizeObserver(() => measure());
    observer.observe(el);
  }

  watch(
    tabbarRef,
    (el) => {
      if (!el) return;
      measure();
      requestAnimationFrame(measure);
      bindObserver(el);
    },
    { flush: 'post' },
  );

  onBeforeUnmount(() => {
    observer?.disconnect();
    if (typeof document !== 'undefined') {
      document.documentElement.style.removeProperty(ROOT_VAR);
    }
  });

  return { ready };
}

export { FALLBACK as EMPLOYEE_TABBAR_BLOCK_FALLBACK };
