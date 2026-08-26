<script setup>
import { computed } from 'vue';
import { usageLabel } from '../lib/hashtagClick.js';

const props = defineProps({
  tags: { type: Array, default: () => [] },
  activeIndex: { type: Number, default: 0 },
  query: { type: String, default: '' },
});

const emit = defineEmits(['pick']);

const emptyLabel = computed(() =>
  props.query.trim()
    ? `Chưa có hashtag khớp “#${props.query.trim()}”. Enter để dùng tag này.`
    : 'Gõ để tìm hoặc tạo hashtag.',
);
</script>

<template>
  <div class="hashtag-picker" role="listbox" aria-label="Gợi ý hashtag">
    <button
      v-for="(tag, index) in tags"
      :key="tag.name"
      type="button"
      class="hashtag-picker__row"
      :class="{ 'hashtag-picker__row--active': index === activeIndex }"
      role="option"
      :aria-selected="index === activeIndex"
      @mousedown.prevent="emit('pick', tag)"
    >
      <span class="hashtag-picker__hash" aria-hidden="true">#</span>
      <span class="hashtag-picker__meta">
        <span class="hashtag-picker__name">{{ tag.label || tag.name }}</span>
        <span class="hashtag-picker__count">
          {{ tag.isNew ? 'Hashtag mới' : usageLabel(tag.usage_count) }}
        </span>
      </span>
    </button>
    <p v-if="tags.length === 0" class="hashtag-picker__empty">{{ emptyLabel }}</p>
  </div>
</template>

<style scoped>
.hashtag-picker {
  position: absolute;
  z-index: 8;
  left: var(--space-3);
  right: var(--space-3);
  top: calc(100% - 0.35rem);
  background: var(--color-surface);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-lg);
  padding: var(--space-1);
  max-height: 14rem;
  overflow-y: auto;
}

.hashtag-picker__row {
  width: 100%;
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2);
  border: none;
  background: none;
  border-radius: var(--radius-sm);
  cursor: pointer;
  text-align: left;
  font-family: inherit;
}

.hashtag-picker__row:hover,
.hashtag-picker__row--active {
  background: var(--color-surface-muted);
}

.hashtag-picker__hash {
  flex-shrink: 0;
  width: 1.75rem;
  height: 1.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-primary) 12%, var(--color-surface));
  color: var(--color-primary);
  font-weight: 700;
  font-size: 0.875rem;
}

.hashtag-picker__meta {
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.hashtag-picker__name {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-text);
}

.hashtag-picker__count {
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.hashtag-picker__empty {
  margin: 0;
  padding: var(--space-2);
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}
</style>
