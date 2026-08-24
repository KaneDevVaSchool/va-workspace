<script setup>
//
// Layout khung cho toàn bộ trang cần đăng nhập: sidebar bên trái + nội dung
// bên phải. Topbar chỉ hiện dưới desktop (<1280px) để chứa nút mở sidebar
// off-canvas, vì trên desktop sidebar luôn hiển thị cố định.
//
import { ref } from 'vue';
import { useRoute } from 'vue-router';
import AppSidebar from './AppSidebar.vue';
import AppIcon from './AppIcon.vue';

const route = useRoute();
const sidebarOpen = ref(false);
</script>

<template>
  <div class="app-layout">
    <AppSidebar :open="sidebarOpen" @close="sidebarOpen = false" />

    <div class="app-layout__main">
      <header class="app-layout__topbar">
        <button
          type="button"
          class="app-layout__menu-btn"
          aria-label="Mở menu"
          @click="sidebarOpen = true"
        >
          <AppIcon name="menu" :size="20" />
        </button>
        <span class="app-layout__topbar-title">{{ route.meta.title ?? '' }}</span>
      </header>

      <main class="app-layout__content">
        <slot />
      </main>
    </div>
  </div>
</template>

<style scoped>
.app-layout {
  height: 100%;
  display: flex;
}

.app-layout__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.app-layout__topbar {
  display: none;
  flex-shrink: 0;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  background: var(--color-surface);
  box-shadow: 0 1px 0 var(--color-border);
}

.app-layout__menu-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  flex-shrink: 0;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
}

.app-layout__menu-btn:hover {
  border: 1px solid var(--color-primary-200);
  color: var(--color-primary);
}

.app-layout__topbar-title {
  color: var(--color-text);
  font-weight: 700;
  font-size: 0.9375rem;
}

.app-layout__content {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

@media (max-width: 1279px) {
  .app-layout__topbar {
    display: flex;
  }
}
</style>
