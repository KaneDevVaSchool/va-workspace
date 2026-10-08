//
// Phát hiện "nhân viên thường đang mở trên màn hình nhỏ" để App.vue/DashboardMe.vue
// chuyển sang trải nghiệm mobile riêng (bottom nav) thay vì AppLayout (sidebar desktop).
// Vai trò quản lý trở lên LUÔN dùng AppLayout, kể cả trên mobile.
//
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';

const MOBILE_MQ = '(max-width: 768px)';

const MANAGER_ROLES = [
  'super_admin',
  'admin',
  'department_director',
  'deputy_department_director',
  'section_head',
  'team_lead',
];

function hasManagerRole(user) {
  if (!user) return false;
  if (user.active_role) return MANAGER_ROLES.includes(user.active_role);
  const roles = Array.isArray(user.roles) ? user.roles : [];
  return roles.some((code) => MANAGER_ROLES.includes(code));
}

export function useEmployeeMobileView() {
  const auth = useAuthStore();
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

  const isEmployeeMobile = computed(
    () => isMobileViewport.value && !hasManagerRole(auth.user),
  );

  return { isEmployeeMobile, isMobileViewport };
}
