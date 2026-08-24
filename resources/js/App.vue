<script setup>
//
// Root component. Layout tổng dùng flex + overflow nội bộ để tránh
// scroll toàn trang (quy tắc responsive + hạn chế scroll).
//
// Trang cần đăng nhập (meta.requiresAuth) được bọc trong AppLayout (sidebar
// + topbar); trang guest (login, callback...) render trực tiếp, không sidebar.
//
import AppLayout from './components/AppLayout.vue';
import ToastHost from './components/ToastHost.vue';
</script>

<template>
  <div class="app-shell">
    <router-view v-slot="{ Component, route }">
      <AppLayout v-if="route.meta.requiresAuth" class="app-shell__auth-content">
        <component :is="Component" />
      </AppLayout>
      <div v-else class="app-shell__content">
        <component :is="Component" />
      </div>
    </router-view>
    <ToastHost />
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
