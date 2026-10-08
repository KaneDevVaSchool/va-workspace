<script setup>
//
// Root component. Layout tổng dùng flex + overflow nội bộ để tránh
// scroll toàn trang (quy tắc responsive + hạn chế scroll).
//
// Trang cần đăng nhập (meta.requiresAuth) được bọc trong AppLayout (sidebar
// + topbar); trang guest (login, callback...) render trực tiếp, không sidebar.
// Riêng route đánh dấu meta.employeeMobile (trang chủ "của tôi" + bảng tin/
// chấm công/nghỉ phép/đơn) đổi sang EmployeeMobileLayout (bottom nav) khi
// đang ở viewport ≤768px — áp dụng cho MỌI tài khoản, không riêng nhân viên
// thường (xem useEmployeeMobileView.js). Desktop (>768px) luôn dùng AppLayout.
//
import { watch } from 'vue';
import { useRoute } from 'vue-router';
import AppLayout from './components/AppLayout.vue';
import ToastHost from './components/ToastHost.vue';
import PwaPushPrompt from './components/PwaPushPrompt.vue';
import PwaUpdatePrompt from './components/PwaUpdatePrompt.vue';
import EmployeeMobileLayout from '@modules/Attendance/resources/js/layouts/EmployeeMobileLayout.vue';
import { useEmployeeMobileView } from './composables/useEmployeeMobileView';
import { isPwaStandalone, setPwaShellSurface } from './lib/pwaStandalone';

const route = useRoute();
const { isEmployeeMobile } = useEmployeeMobileView();

function syncPwaShellSurface() {
  const root = document.documentElement;
  const employeeTabShell = Boolean(
    route.meta.requiresAuth && route.meta.employeeMobile && isEmployeeMobile.value,
  );
  root.style.setProperty('--toast-shell-bottom-inset', employeeTabShell ? '3.5rem' : '0px');

  root.classList.toggle('employee-mobile-browser', employeeTabShell && !isPwaStandalone());
  root.classList.toggle('employee-mobile-pwa', employeeTabShell && isPwaStandalone());

  if (route.meta.requiresAuth) {
    if (employeeTabShell) {
      setPwaShellSurface('employee');
    } else {
      setPwaShellSurface('app');
    }
    return;
  }
  setPwaShellSurface('guest');
}

watch([() => route.fullPath, isEmployeeMobile], syncPwaShellSurface, { immediate: true });
</script>

<template>
  <div class="app-shell">
    <router-view v-slot="{ Component, route }">
      <EmployeeMobileLayout
        v-if="route.meta.requiresAuth && route.meta.employeeMobile && isEmployeeMobile"
        class="app-shell__auth-content"
      >
        <component :is="Component" />
      </EmployeeMobileLayout>
      <AppLayout v-else-if="route.meta.requiresAuth" class="app-shell__auth-content">
        <component :is="Component" />
      </AppLayout>
      <div v-else class="app-shell__content app-shell__content--guest">
        <component :is="Component" />
      </div>
    </router-view>
    <ToastHost />
    <PwaPushPrompt />
    <PwaUpdatePrompt />
  </div>
</template>

<style>
/* Khác với .app-shell__content (overflow-y: auto, dùng cho trang guest),
   trang có AppLayout tự quản lý scroll ở vùng nội dung bên trong sidebar
   nên vùng bọc ngoài chỉ cần chiếm hết chỗ còn lại, không tự cuộn. */
.app-shell__auth-content {
  flex: 1;
  min-height: 0;
  overflow: hidden;
}
</style>
