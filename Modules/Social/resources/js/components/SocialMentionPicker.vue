<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
  users: { type: Array, default: () => [] },
  activeIndex: { type: Number, default: 0 },
  query: { type: String, default: '' },
  /** Ô soạn — khung gợi ý neo theo ô này, không bị thẻ bài viết cắt. */
  anchor: { type: Object, default: null },
});

const emit = defineEmits(['pick']);

const root = ref(null);
const popupStyle = ref({});
let bound = false;

const emptyLabel = computed(() =>
  props.query.trim()
    ? `Không tìm thấy đồng nghiệp khớp “${props.query.trim()}”.`
    : 'Không có đồng nghiệp để nhắc.',
);

function initial(name) {
  return name?.trim().charAt(0).toUpperCase() || '?';
}

function anchorEl() {
  const anchor = props.anchor;
  if (anchor instanceof HTMLElement) return anchor;
  return null;
}

function place() {
  const el = anchorEl();
  if (!el) {
    popupStyle.value = {};
    return;
  }

  const rect = el.getBoundingClientRect();
  const viewport = window.visualViewport;
  const viewportTop = viewport?.offsetTop ?? 0;
  const viewportHeight = viewport?.height ?? window.innerHeight;
  const gap = 4;
  const margin = 8;
  const maxHeight = 224;
  const topInView = rect.top - viewportTop;
  const bottomInView = rect.bottom - viewportTop;
  const spaceBelow = viewportHeight - bottomInView - gap - margin;
  const spaceAbove = topInView - gap - margin;
  const openUp = spaceBelow < 160 && spaceAbove > spaceBelow;
  const available = Math.max(80, openUp ? spaceAbove : spaceBelow);
  const height = Math.min(maxHeight, available);
  const width = Math.min(rect.width, window.innerWidth - margin * 2);
  const left = Math.max(margin, Math.min(rect.left, window.innerWidth - width - margin));

  popupStyle.value = {
    left: `${left}px`,
    width: `${width}px`,
    maxHeight: `${height}px`,
    ...(openUp
      ? { top: 'auto', bottom: `${window.innerHeight - rect.top + gap}px` }
      : { top: `${rect.bottom + gap}px`, bottom: 'auto' }),
  };
}

function scrollActiveIntoView() {
  const list = root.value;
  if (!list) return;
  const row = list.querySelector('.mention-picker__row--active');
  if (!(row instanceof HTMLElement)) return;
  const top = row.offsetTop;
  const bottom = top + row.offsetHeight;
  if (top < list.scrollTop) list.scrollTop = top;
  else if (bottom > list.scrollTop + list.clientHeight) {
    list.scrollTop = bottom - list.clientHeight;
  }
}

function onReposition() {
  place();
}

function bind() {
  if (bound) return;
  bound = true;
  window.addEventListener('scroll', onReposition, true);
  window.addEventListener('resize', onReposition);
  window.visualViewport?.addEventListener('resize', onReposition);
  window.visualViewport?.addEventListener('scroll', onReposition);
}

function unbind() {
  if (!bound) return;
  bound = false;
  window.removeEventListener('scroll', onReposition, true);
  window.removeEventListener('resize', onReposition);
  window.visualViewport?.removeEventListener('resize', onReposition);
  window.visualViewport?.removeEventListener('scroll', onReposition);
}

onMounted(() => {
  nextTick(() => {
    place();
    scrollActiveIntoView();
    bind();
  });
});

onBeforeUnmount(unbind);

watch(
  () => [props.activeIndex, props.users.length, props.anchor],
  () => {
    nextTick(() => {
      place();
      scrollActiveIntoView();
    });
  },
);
</script>

<template>
  <Teleport to="body">
    <div
      ref="root"
      class="mention-picker"
      role="listbox"
      aria-label="Nhắc đồng nghiệp"
      :style="popupStyle"
    >
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
  </Teleport>
</template>

<style scoped>
.mention-picker {
  position: fixed;
  z-index: 90;
  max-height: 14rem;
  overflow-x: hidden;
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
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.mention-picker__dept {
  font-size: 0.75rem;
  color: var(--color-text-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.mention-picker__empty {
  margin: 0;
  padding: var(--space-2);
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}
</style>
