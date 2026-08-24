<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { formatRelativeTime } from '../lib/formatTime';
import { showClientToast } from '../lib/clientToast';
import { useHeaderPopover } from '../composables/useHeaderPopover';
import AppIcon from './AppIcon.vue';

const router = useRouter();
const rootRef = ref(null);
const { isOpen, toggle, close } = useHeaderPopover('activity');

const logs = ref([]);
const loading = ref(false);

async function loadRecent() {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/activity-logs/recent');
    logs.value = data.logs ?? [];
  } catch {
    showClientToast('error', 'Không tải được nhật ký hoạt động.');
  } finally {
    loading.value = false;
  }
}

function openAll() {
  close();
  router.push({ name: 'superadmin.activity' });
}

function handleDocumentClick(event) {
  if (!isOpen.value || !rootRef.value) {
    return;
  }
  if (rootRef.value.contains(event.target)) {
    return;
  }
  close();
}

function handleDocumentKeydown(event) {
  if (event.key === 'Escape' && isOpen.value) {
    close();
  }
}

watch(isOpen, (open) => {
  if (open) {
    loadRecent();
  }
});

onMounted(() => {
  document.addEventListener('mousedown', handleDocumentClick);
  document.addEventListener('keydown', handleDocumentKeydown);
});

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleDocumentClick);
  document.removeEventListener('keydown', handleDocumentKeydown);
});
</script>

<template>
  <div ref="rootRef" class="header-pop">
    <button
      type="button"
      class="header-pop__btn"
      :class="{ 'header-pop__btn--open': isOpen }"
      aria-haspopup="dialog"
      :aria-expanded="isOpen"
      aria-label="Nhật ký hoạt động"
      @click="toggle"
    >
      <AppIcon name="clock" :size="18" :stroke-width="1.75" />
    </button>

    <div v-if="isOpen" class="header-pop__panel" role="dialog" aria-label="Nhật ký hoạt động">
      <div class="header-pop__head">
        <span class="header-pop__tab">Nhật ký hoạt động</span>
        <button type="button" class="header-pop__all" @click="openAll">Xem tất cả</button>
      </div>

      <div class="activity-list">
        <p v-if="loading" class="activity-list__empty">Đang tải…</p>
        <p v-else-if="logs.length === 0" class="activity-list__empty">Chưa có hoạt động nào.</p>
        <div v-for="log in logs" v-else :key="log.id" class="activity-item">
          <p class="activity-item__desc">{{ log.description }}</p>
          <p class="activity-item__meta">
            <span>{{ log.actor_name || 'Hệ thống' }}</span>
            <span>{{ formatRelativeTime(log.created_at) }}</span>
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.header-pop {
  position: relative;
}

.header-pop__btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text);
  cursor: pointer;
}

.header-pop__btn:hover,
.header-pop__btn--open {
  background: var(--color-surface-muted);
  color: var(--color-primary);
}

.header-pop__btn:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.header-pop__panel {
  position: absolute;
  top: calc(100% + 0.5rem);
  right: 0;
  z-index: 50;
  width: min(24rem, calc(100vw - 1.5rem));
  max-height: min(28rem, calc(100vh - 5rem));
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: 12px;
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
}

.header-pop__head {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  padding: var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
}

.header-pop__tab {
  color: var(--color-primary);
  font-size: 0.8125rem;
  font-weight: 700;
}

.header-pop__all {
  border: none;
  background: transparent;
  color: var(--color-primary);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.activity-list {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

.activity-list__empty {
  margin: 0;
  padding: var(--space-6) var(--space-4);
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.activity-item {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  padding: 0.75rem var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
}

.activity-item__desc {
  margin: 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 500;
}

.activity-item__meta {
  margin: 0;
  display: flex;
  justify-content: space-between;
  gap: var(--space-3);
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

@media (max-width: 480px) {
  .header-pop__panel {
    position: fixed;
    top: 3.5rem;
    right: var(--space-3);
    left: var(--space-3);
    width: auto;
  }
}
</style>
