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

function actorInitial(log) {
  const name = log?.actor?.name || log?.actor_name || 'Hệ thống';
  return name.trim().charAt(0).toUpperCase() || '?';
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
      <AppIcon name="clock" :size="20" :stroke-width="1.75" />
    </button>

    <div v-if="isOpen" class="header-pop__panel" role="dialog" aria-label="Nhật ký hoạt động">
      <div class="header-pop__head">
        <span class="header-pop__tab">Nhật ký hoạt động</span>
        <button type="button" class="header-pop__all" @click="openAll">
          Xem tất cả
          <AppIcon name="arrowRight" :size="14" :stroke-width="2" />
        </button>
      </div>

      <div class="activity-list">
        <div v-if="loading" class="activity-skeleton">
          <div v-for="n in 4" :key="n" class="activity-skeleton__row">
            <span class="activity-skeleton__avatar" />
            <span class="activity-skeleton__lines">
              <span class="activity-skeleton__line activity-skeleton__line--long" />
              <span class="activity-skeleton__line activity-skeleton__line--short" />
            </span>
          </div>
        </div>
        <p v-else-if="logs.length === 0" class="activity-list__empty">
          <AppIcon name="clock" :size="22" :stroke-width="1.5" />
          Chưa có hoạt động nào.
        </p>
        <div v-else class="activity-list__track">
          <div v-for="log in logs" :key="log.id" class="activity-item">
            <span class="activity-item__rail" aria-hidden="true">
              <span class="activity-item__avatar">
                <img
                  v-if="log.actor?.avatar_url"
                  :src="log.actor.avatar_url"
                  alt=""
                  class="activity-item__avatar-img"
                  referrerpolicy="no-referrer"
                />
                <template v-else>{{ actorInitial(log) }}</template>
              </span>
              <span class="activity-item__thread" />
            </span>
            <div class="activity-item__body">
              <p class="activity-item__desc">{{ log.description }}</p>
              <p class="activity-item__meta">
                <span class="activity-item__actor">{{ log.actor_name || log.actor?.name || 'Hệ thống' }}</span>
                <span class="activity-item__time">{{ formatRelativeTime(log.created_at) }}</span>
              </p>
            </div>
          </div>
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
  width: 2.5rem;
  height: 2.5rem;
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
  display: flex;
  align-items: center;
  gap: 0.25rem;
  border: none;
  background: transparent;
  color: var(--color-primary);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  border-radius: var(--radius-sm);
  padding: 0.25rem 0.375rem;
  transition: background-color 150ms ease, transform 150ms ease;
}

.header-pop__all:hover {
  background: var(--color-primary-surface);
  transform: translateX(1px);
}

.activity-list {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

.activity-list__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
  padding: var(--space-6) var(--space-4);
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.activity-list__track {
  display: flex;
  flex-direction: column;
}

.activity-item {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.625rem var(--space-3);
  transition: background-color 150ms ease;
}

.activity-item:hover {
  background: var(--color-surface-muted);
}

.activity-item + .activity-item {
  box-shadow: 0 1px 0 var(--color-border);
}

.activity-item__rail {
  position: relative;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  align-self: stretch;
}

.activity-item__avatar {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 2.25rem;
  height: 2.25rem;
  overflow: hidden;
  border-radius: var(--radius-full);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-size: 0.8125rem;
  font-weight: 700;
  line-height: 1;
  box-shadow: 0 0 0 2px var(--color-surface);
}

.activity-item__avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.activity-item__thread {
  flex: 1;
  width: 2px;
  margin-top: 0.25rem;
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.activity-item:last-child .activity-item__thread {
  display: none;
}

.activity-item__body {
  min-width: 0;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding-top: 0.125rem;
  padding-bottom: 0.75rem;
}

.activity-item__desc {
  margin: 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 500;
  line-height: 1.4;
}

.activity-item__meta {
  margin: 0;
  display: flex;
  align-items: center;
  gap: var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.activity-item__actor {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-weight: 600;
}

.activity-item__time {
  flex-shrink: 0;
  margin-left: auto;
}

/* ---------- Skeleton loading ---------- */
.activity-skeleton {
  display: flex;
  flex-direction: column;
}

.activity-skeleton__row {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.625rem var(--space-3);
}

.activity-skeleton__avatar {
  flex-shrink: 0;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  animation: activity-skeleton-pulse 1.2s ease-in-out infinite;
}

.activity-skeleton__lines {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding-top: 0.25rem;
}

.activity-skeleton__line {
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  animation: activity-skeleton-pulse 1.2s ease-in-out infinite;
}

.activity-skeleton__line--long {
  width: 85%;
}

.activity-skeleton__line--short {
  width: 40%;
}

@keyframes activity-skeleton-pulse {
  0%, 100% { opacity: 0.6; }
  50% { opacity: 1; }
}

@media (prefers-reduced-motion: reduce) {
  .activity-skeleton__avatar,
  .activity-skeleton__line {
    animation: none;
  }
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
