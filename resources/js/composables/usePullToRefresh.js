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
  let rafId = 0;
  let pendingPull = 0;

  // Gộp cập nhật theo frame: touchmove bắn nhanh hơn tốc độ vẽ, mỗi lần đổi pullY là một lần
  // layout lại (chiều cao khối "kéo để làm mới" đẩy cả nội dung bên dưới).
  function setPull(value) {
    pendingPull = value;
    if (rafId) return;
    rafId = requestAnimationFrame(() => {
      rafId = 0;
      pullY.value = pendingPull;
    });
  }

  function setPullNow(value) {
    if (rafId) {
      cancelAnimationFrame(rafId);
      rafId = 0;
    }
    pendingPull = value;
    pullY.value = value;
  }

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
    // Listener không-passive chỉ tồn tại trong lúc kéo; cuộn bình thường vẫn dùng đường passive
    // nên trình duyệt không phải chờ JS trước mỗi lần cuộn.
    el.addEventListener('touchmove', onTouchMove, { passive: false });
  }

  function onTouchMove(event) {
    if (!tracking || refreshing.value) return;
    const y = event.touches[0]?.clientY ?? 0;
    const delta = y - startY;
    if (delta <= 0) {
      setPull(0);
      return;
    }
    if (el && el.scrollTop > 0) {
      tracking = false;
      el.removeEventListener('touchmove', onTouchMove);
      setPullNow(0);
      return;
    }
    const damped = Math.min(MAX_PULL, delta * 0.45);
    setPull(damped);
    if (damped > 8) {
      event.preventDefault();
    }
  }

  async function onTouchEnd() {
    el?.removeEventListener('touchmove', onTouchMove);
    if (!tracking) return;
    tracking = false;
    // Giá trị kéo cuối cùng có thể còn đang chờ frame tiếp theo.
    const finalPull = pendingPull;
    if (finalPull >= threshold && typeof onRefresh === 'function') {
      refreshing.value = true;
      setPullNow(threshold * 0.65);
      try {
        await onRefresh();
      } finally {
        refreshing.value = false;
        setPullNow(0);
      }
    } else {
      setPullNow(0);
    }
  }

  function bind(target) {
    unbind();
    el = target;
    if (!el) return;
    el.addEventListener('touchstart', onTouchStart, { passive: true });
    el.addEventListener('touchend', onTouchEnd);
    el.addEventListener('touchcancel', onTouchEnd);
  }

  function unbind() {
    if (!el) return;
    el.removeEventListener('touchstart', onTouchStart);
    el.removeEventListener('touchmove', onTouchMove);
    el.removeEventListener('touchend', onTouchEnd);
    el.removeEventListener('touchcancel', onTouchEnd);
    if (rafId) {
      cancelAnimationFrame(rafId);
      rafId = 0;
    }
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
