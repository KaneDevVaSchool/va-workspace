//
// Kéo xuống để làm mới — dùng trên vùng scroll touch (shell mobile nhân viên).
//
import { onBeforeUnmount, ref, unref, watch } from 'vue';

const DEFAULT_THRESHOLD = 72;
const MAX_PULL = 120;

export function usePullToRefresh(scrollRef, onRefresh, options = {}) {
  const enabled = options.enabled ?? (() => true);
  const threshold = options.threshold ?? DEFAULT_THRESHOLD;

  const pullY = ref(0);
  const refreshing = ref(false);
  let startY = 0;
  let tracking = false;
  let el = null;

  function canStartPull() {
    if (refreshing.value) return false;
    if (typeof enabled === 'function' && !enabled()) return false;
    if (!el) return false;
    return el.scrollTop <= 0;
  }

  function onTouchStart(event) {
    if (!canStartPull()) return;
    startY = event.touches[0]?.clientY ?? 0;
    tracking = true;
  }

  function onTouchMove(event) {
    if (!tracking || refreshing.value) return;
    const y = event.touches[0]?.clientY ?? 0;
    const delta = y - startY;
    if (delta <= 0) {
      pullY.value = 0;
      return;
    }
    if (el && el.scrollTop > 0) {
      tracking = false;
      pullY.value = 0;
      return;
    }
    const damped = Math.min(MAX_PULL, delta * 0.45);
    pullY.value = damped;
    if (damped > 8) {
      event.preventDefault();
    }
  }

  async function onTouchEnd() {
    if (!tracking) return;
    tracking = false;
    if (pullY.value >= threshold && typeof onRefresh === 'function') {
      refreshing.value = true;
      pullY.value = threshold * 0.65;
      try {
        await onRefresh();
      } finally {
        refreshing.value = false;
        pullY.value = 0;
      }
    } else {
      pullY.value = 0;
    }
  }

  function bind(target) {
    unbind();
    el = target;
    if (!el) return;
    el.addEventListener('touchstart', onTouchStart, { passive: true });
    el.addEventListener('touchmove', onTouchMove, { passive: false });
    el.addEventListener('touchend', onTouchEnd);
    el.addEventListener('touchcancel', onTouchEnd);
  }

  function unbind() {
    if (!el) return;
    el.removeEventListener('touchstart', onTouchStart);
    el.removeEventListener('touchmove', onTouchMove);
    el.removeEventListener('touchend', onTouchEnd);
    el.removeEventListener('touchcancel', onTouchEnd);
    el = null;
  }

  watch(
    () => unref(scrollRef),
    (next) => bind(next),
    { flush: 'post', immediate: true },
  );

  onBeforeUnmount(unbind);

  const pullProgress = () => Math.min(1, pullY.value / threshold);

  return { pullY, refreshing, pullProgress };
}
