<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { REACTIONS, reactionByType } from '../constants/reactions.js';

const props = defineProps({
  open: { type: Boolean, required: true },
  endpoint: { type: String, required: true },
  initialType: { type: String, default: null },
});

const emit = defineEmits(['close']);

const loading = ref(false);
const users = ref([]);
const activeType = ref(null);

const tabs = computed(() => {
  const counts = {};
  for (const item of users.value) {
    counts[item.type] = (counts[item.type] ?? 0) + 1;
  }

  const typeTabs = REACTIONS
    .filter((reaction) => (counts[reaction.type] ?? 0) > 0)
    .map((reaction) => ({
      id: reaction.type,
      emoji: reaction.emoji,
      label: reaction.label,
      count: counts[reaction.type],
    }));

  return [
    { id: null, emoji: null, label: 'Tất cả', count: users.value.length },
    ...typeTabs,
  ];
});

const visibleUsers = computed(() => {
  if (!activeType.value) return users.value;
  return users.value.filter((item) => item.type === activeType.value);
});

function close() {
  emit('close');
}

function onKey(event) {
  if (event.key === 'Escape') {
    event.preventDefault();
    close();
  }
}

function reactionInfo(type) {
  return reactionByType(type);
}

async function load() {
  loading.value = true;
  try {
    const { data } = await window.axios.get(props.endpoint);
    users.value = data.users ?? [];
    const hasInitial = props.initialType && users.value.some((item) => item.type === props.initialType);
    activeType.value = hasInitial ? props.initialType : null;
  } catch (error) {
    users.value = [];
    showClientToast('error', error?.response?.data?.message ?? 'Không thể tải danh sách cảm xúc.');
  } finally {
    loading.value = false;
  }
}

function bindPage() {
  document.addEventListener('keydown', onKey);
  document.body.style.overflow = 'hidden';
}

function unbindPage() {
  document.removeEventListener('keydown', onKey);
  document.body.style.overflow = '';
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      activeType.value = props.initialType;
      bindPage();
      load();
      return;
    }
    unbindPage();
    users.value = [];
  },
);

onBeforeUnmount(unbindPage);
</script>

<template>
  <Teleport to="body">
    <Transition name="reaction-list-fade">
      <div
        v-if="open"
        class="reaction-list"
        role="presentation"
        @mousedown.self="close"
      >
        <div
          class="reaction-list__panel"
          role="dialog"
          aria-modal="true"
          aria-label="Người đã bày tỏ cảm xúc"
        >
          <header class="reaction-list__header">
            <h2 class="reaction-list__title">Cảm xúc</h2>
            <button type="button" class="reaction-list__close" aria-label="Đóng" @click="close">
              <AppIcon name="close" :size="18" />
            </button>
          </header>

          <div class="reaction-list__tabs hide-scrollbar" role="tablist" aria-label="Lọc theo cảm xúc">
            <button
              v-for="tab in tabs"
              :key="tab.id ?? 'all'"
              type="button"
              class="reaction-list__tab"
              :class="{ 'reaction-list__tab--active': activeType === tab.id }"
              role="tab"
              :aria-selected="activeType === tab.id"
              :aria-label="tab.emoji ? `${tab.label}: ${tab.count}` : tab.label"
              @click="activeType = tab.id"
            >
              <span v-if="tab.emoji" class="reaction-list__tab-emoji">{{ tab.emoji }}</span>
              <span v-else>{{ tab.label }}</span>
              <span class="reaction-list__tab-count">{{ tab.count }}</span>
            </button>
          </div>

          <div class="reaction-list__body hide-scrollbar">
            <p v-if="loading" class="reaction-list__empty">Đang tải...</p>
            <p v-else-if="visibleUsers.length === 0" class="reaction-list__empty">Chưa có ai bày tỏ cảm xúc.</p>
            <ul v-else class="reaction-list__people">
              <li v-for="item in visibleUsers" :key="`${item.user.id}-${item.type}`" class="reaction-list__person">
                <img
                  v-if="item.user.avatar_url"
                  class="reaction-list__avatar"
                  :src="item.user.avatar_url"
                  :alt="`Ảnh đại diện của ${item.user.name}`"
                />
                <div v-else class="reaction-list__avatar reaction-list__avatar--placeholder">
                  {{ item.user.name?.charAt(0) ?? '?' }}
                </div>
                <div class="reaction-list__person-info">
                  <div class="reaction-list__person-name">{{ item.user.name }}</div>
                  <div v-if="item.user.department" class="reaction-list__person-meta">
                    {{ item.user.department }}
                  </div>
                </div>
                <span class="reaction-list__person-emoji" :aria-label="reactionInfo(item.type)?.label">
                  {{ reactionInfo(item.type)?.emoji }}
                </span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.reaction-list {
  position: fixed;
  inset: 0;
  z-index: 310;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
  background: color-mix(in srgb, #000000 45%, transparent);
}

.reaction-list__panel {
  width: min(26rem, calc(100vw - 2rem));
  height: min(32rem, calc(100vh - 2.5rem));
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.reaction-list__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  flex-shrink: 0;
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.reaction-list__title {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 700;
  color: var(--color-text);
}

.reaction-list__close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text);
  cursor: pointer;
}

.reaction-list__close:hover {
  background: var(--color-border);
}

.reaction-list__tabs {
  display: flex;
  gap: var(--space-1);
  flex-shrink: 0;
  padding: var(--space-2) var(--space-3);
  overflow-x: auto;
  box-shadow: 0 1px 0 var(--color-border);
}

.reaction-list__tab {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  flex-shrink: 0;
  border: none;
  background: none;
  color: var(--color-text-muted);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  cursor: pointer;
}

.reaction-list__tab:hover {
  background: var(--color-surface-muted);
}

.reaction-list__tab--active {
  color: var(--color-primary);
  background: var(--color-primary-surface);
}

.reaction-list__tab-emoji {
  font-size: 1rem;
  line-height: 1;
}

.reaction-list__tab-count {
  font-variant-numeric: tabular-nums;
}

.reaction-list__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

.reaction-list__empty {
  margin: 0;
  padding: var(--space-5);
  text-align: center;
  font-size: 0.875rem;
  color: var(--color-text-muted);
}

.reaction-list__people {
  list-style: none;
  margin: 0;
  padding: var(--space-2) 0;
}

.reaction-list__person {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2) var(--space-4);
}

.reaction-list__avatar {
  width: 40px;
  height: 40px;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
}

.reaction-list__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-weight: 600;
}

.reaction-list__person-info {
  flex: 1;
  min-width: 0;
}

.reaction-list__person-name {
  font-weight: 600;
  color: var(--color-text);
}

.reaction-list__person-meta {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.reaction-list__person-emoji {
  font-size: 1.25rem;
  line-height: 1;
  flex-shrink: 0;
}

.reaction-list-fade-enter-active,
.reaction-list-fade-leave-active {
  transition: opacity 0.15s ease;
}

.reaction-list-fade-enter-from,
.reaction-list-fade-leave-to {
  opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
  .reaction-list-fade-enter-active,
  .reaction-list-fade-leave-active {
    transition: none;
  }
}
</style>
