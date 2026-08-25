<script setup>
import { computed } from 'vue';

const props = defineProps({
  users: { type: Array, default: () => [] },
  activeIndex: { type: Number, default: 0 },
  query: { type: String, default: '' },
});

const emit = defineEmits(['pick']);

const emptyLabel = computed(() =>
  props.query.trim()
    ? `Không tìm thấy đồng nghiệp khớp “${props.query.trim()}”.`
    : 'Không có đồng nghiệp để nhắc.',
);

function initial(name) {
  return name?.trim().charAt(0).toUpperCase() || '?';
}
</script>

<template>
  <div class="mention-picker" role="listbox" aria-label="Nhắc đồng nghiệp">
    <button
      v-for="(user, index) in users"
      :key="user.id"
      type="button"
      class="mention-picker__row"
      :class="{ 'mention-picker__row--active': index === activeIndex }"
      role="option"
      :aria-selected="index === activeIndex"
      @mousedown.prevent="emit('pick', user)"
    >
      <img
        v-if="user.avatar_url"
        class="mention-picker__avatar"
        :src="user.avatar_url"
        :alt="`Ảnh đại diện của ${user.name}`"
      />
      <span v-else class="mention-picker__avatar mention-picker__avatar--placeholder">
        {{ initial(user.name) }}
      </span>
      <span class="mention-picker__meta">
        <span class="mention-picker__name">{{ user.name }}</span>
        <span v-if="user.department" class="mention-picker__dept">{{ user.department }}</span>
      </span>
    </button>

    <p v-if="users.length === 0" class="mention-picker__empty">{{ emptyLabel }}</p>
  </div>
</template>

<style scoped>
.mention-picker {
  position: absolute;
  left: 0;
  right: 0;
  top: calc(100% + var(--space-1));
  z-index: 40;
  max-height: 14rem;
  overflow-y: auto;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-lg);
  padding: var(--space-1);
}

.mention-picker__row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  border: none;
  background: none;
  text-align: left;
  padding: var(--space-2);
  border-radius: var(--radius-md);
  cursor: pointer;
  font-family: inherit;
}

.mention-picker__row:hover,
.mention-picker__row--active {
  background: var(--color-primary-surface);
}

.mention-picker__avatar {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
}

.mention-picker__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 0.75rem;
  font-weight: 600;
}

.mention-picker__meta {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.mention-picker__name {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text);
}

.mention-picker__dept {
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.mention-picker__empty {
  margin: 0;
  padding: var(--space-2);
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}
</style>
