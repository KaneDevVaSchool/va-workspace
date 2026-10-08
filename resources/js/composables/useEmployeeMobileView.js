//
// Phát hiện "đang mở workspace trên màn hình nhỏ" để App.vue/DashboardMe.vue
// chuyển sang trải nghiệm mobile riêng (bottom nav) thay vì AppLayout (sidebar
// desktop). Áp dụng cho MỌI tài khoản, kể cả quản lý — chỉ phụ thuộc viewport,
// không còn phân biệt vai trò (desktop >768px vẫn luôn dùng AppLayout như cũ).
//
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const MOBILE_MQ = '(max-width: 768px)';

export function useEmployeeMobileView() {
  const isMobileViewport = ref(
    typeof window !== 'undefined' && window.matchMedia(MOBILE_MQ).matches,
  );

  let mq;
  function onChange(event) {
    isMobileViewport.value = event.matches;
  }

  onMounted(() => {
    mq = window.matchMedia(MOBILE_MQ);
    isMobileViewport.value = mq.matches;
    mq.addEventListener('change', onChange);
  });

  onBeforeUnmount(() => {
    mq?.removeEventListener('change', onChange);
  });

  const isEmployeeMobile = computed(() => isMobileViewport.value);

  return { isEmployeeMobile, isMobileViewport };
}
