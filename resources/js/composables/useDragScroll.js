import { onBeforeUnmount, watch } from 'vue';

/**
 * Kéo bằng cách nắm chuột (giữ + kéo) để cuộn ngang/dọc trên vùng bảng
 * không hiện thanh scroll (`.hide-scrollbar`) — dùng cho bảng nhiều cột,
 * máy bàn dùng chuột thường không vuốt được. Xem `.cursor/rules/data-table.mdc`.
 *
 * @param {import('vue').Ref<HTMLElement|null>} wrapRef  phần tử có overflow (table-wrap)
 * @param {object} [options]
 * @param {() => boolean} [options.isBlocked]  trả true để bỏ qua (đang kéo nắm cột…)
 * @param {number} [options.dragThreshold]  số px di chuyển tối thiểu trước khi coi là kéo (mặc định 4)
 * @param {'both'|'x'|'y'} [options.axis]  trục được phép cuộn khi kéo (mặc định 'both')
 */
export function useDragScroll(wrapRef, options = {}) {
  const { isBlocked, dragThreshold = 4, axis = 'both' } = options;

  let dragging = false;
  let moved = false;
  let startX = 0;
  let startY = 0;
  let startScrollLeft = 0;
  let startScrollTop = 0;

  function onMouseDown(event) {
    if (event.button !== 0) return;
    if (isBlocked?.()) return;
    // Bỏ qua khi bắt đầu từ nắm kéo cột hoặc control tương tác (input, button, a, select).
    if (event.target.closest('button, a, input, select, textarea, [data-no-drag-scroll]')) return;

    const wrap = wrapRef.value;
    if (!wrap) return;

    dragging = true;
    moved = false;
    startX = event.clientX;
    startY = event.clientY;
    startScrollLeft = wrap.scrollLeft;
    startScrollTop = wrap.scrollTop;

    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup', onMouseUp);
  }

  function onMouseMove(event) {
    if (!dragging) return;
    const wrap = wrapRef.value;
    if (!wrap) return;

    const dx = event.clientX - startX;
    const dy = event.clientY - startY;

    if (!moved && Math.hypot(dx, dy) < dragThreshold) return;

    if (!moved) {
      moved = true;
      wrap.classList.add('drag-scrolling');
    }

    if (axis !== 'y') wrap.scrollLeft = startScrollLeft - dx;
    if (axis !== 'x') wrap.scrollTop = startScrollTop - dy;
  }

  function onMouseUp(event) {
    if (moved) {
      // Chặn click phát sinh ngay sau khi vừa kéo (tránh chọn nhầm dòng).
      event.target?.addEventListener?.('click', suppressClick, { capture: true, once: true });
    }
    dragging = false;
    moved = false;
    wrapRef.value?.classList.remove('drag-scrolling');
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', onMouseUp);
  }

  function suppressClick(event) {
    event.stopPropagation();
    event.preventDefault();
  }

  watch(
    wrapRef,
    (el, prev) => {
      prev?.removeEventListener('mousedown', onMouseDown);
      el?.addEventListener('mousedown', onMouseDown);
    },
    { immediate: true, flush: 'post' },
  );

  onBeforeUnmount(() => {
    wrapRef.value?.removeEventListener('mousedown', onMouseDown);
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', onMouseUp);
  });
}
