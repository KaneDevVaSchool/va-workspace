import { computed, ref } from 'vue';

//
// Một popover duy nhất trên thanh header được mở tại một thời điểm. Mọi panel
// trên header (thông báo, ghi nhận, nhật ký, lối tắt, tài khoản, trò chuyện)
// PHẢI đăng ký qua đây — nếu một panel tự giữ trạng thái mở riêng, nó sẽ hiện
// song song với panel khác và chồng lên nhau (các panel cùng z-index: 50 nên
// thứ tự vẽ rơi về thứ tự DOM trong AppHeader.vue).
//
// Panel giữ state ở nơi khác (ví dụ Chat dùng Pinia store) thì đăng ký bằng
// registerHeaderPopover() để vẫn nằm trong cùng nhóm loại trừ này.
//
const openId = ref(null);

/** Hàm đóng riêng của các panel giữ state bên ngoài composable này. */
const externalClosers = new Map();

export function closeHeaderPopovers() {
  openId.value = null;
  for (const close of externalClosers.values()) {
    close();
  }
}

/**
 * Đưa một panel giữ state bên ngoài (ví dụ trong Pinia store) vào cùng nhóm
 * loại trừ. Trả về hàm huỷ đăng ký để gọi khi component unmount.
 *
 * @param {string} id  id riêng của panel, khác với các panel đang có
 * @param {() => void} close  hàm đóng panel đó
 */
export function registerHeaderPopover(id, close) {
  externalClosers.set(id, close);
  return () => externalClosers.delete(id);
}

/**
 * Panel ngoài vừa được mở → đóng mọi panel khác (cả panel dùng composable này
 * và panel ngoài khác).
 */
export function notifyHeaderPopoverOpened(id) {
  openId.value = null;
  for (const [key, close] of externalClosers) {
    if (key !== id) close();
  }
}

export function useHeaderPopover(id) {
  const isOpen = computed(() => openId.value === id);

  /** Đóng các panel giữ state bên ngoài khi panel này mở ra. */
  function closeExternal() {
    for (const close of externalClosers.values()) {
      close();
    }
  }

  function toggle() {
    const next = openId.value === id ? null : id;
    openId.value = next;
    if (next !== null) closeExternal();
  }

  function open() {
    openId.value = id;
    closeExternal();
  }

  function close() {
    if (openId.value === id) {
      openId.value = null;
    }
  }

  return { isOpen, toggle, open, close };
}
