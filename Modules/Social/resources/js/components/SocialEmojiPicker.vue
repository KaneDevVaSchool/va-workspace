<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { EMOJI_GROUPS } from '../constants/emojiGroups.js';

const props = defineProps({
  /** Nút/khung neo — click vào đây không đóng picker. */
  anchor: { type: Object, default: null },
});

const emit = defineEmits(['pick', 'close']);

const activeGroup = ref(EMOJI_GROUPS[0].key);
const root = ref(null);
const popupStyle = ref({});
let pageBound = false;

function currentEmojis() {
  return EMOJI_GROUPS.find((g) => g.key === activeGroup.value)?.emojis ?? [];
}

function placePopup() {
  const trigger = props.anchor instanceof HTMLElement ? props.anchor : null;
  const desktop = window.innerWidth >= 768;
  const width = Math.min(desktop ? 560 : 360, window.innerWidth - 24);
  const gap = 8;
  const preferredHeight = desktop ? 440 : 320;
  const style = { width: `${width}px` };

  if (!trigger) {
    const height = Math.min(preferredHeight, window.innerHeight - 16);
    style.left = `${Math.max(8, (window.innerWidth - width) / 2)}px`;
    style.top = '8px';
    style.height = `${height}px`;
    popupStyle.value = style;
    return;
  }

  const rect = trigger.getBoundingClientRect();
  const left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8));
  const spaceBelow = window.innerHeight - rect.bottom - gap - 8;
  const spaceAbove = rect.top - gap - 8;

  if (spaceBelow < 180 && spaceAbove > spaceBelow) {
    const height = Math.min(preferredHeight, Math.max(200, spaceAbove));
    style.left = `${left}px`;
    style.top = 'auto';
    style.bottom = `${window.innerHeight - rect.top + gap}px`;
    style.height = `${height}px`;
  } else {
    const height = Math.min(preferredHeight, Math.max(200, spaceBelow));
    style.left = `${left}px`;
    style.top = `${rect.bottom + gap}px`;
    style.bottom = 'auto';
    style.height = `${height}px`;
  }

  popupStyle.value = style;
}

function bindPageListeners() {
  if (pageBound) return;
  pageBound = true;
  document.addEventListener('pointerdown', onDocumentPointerDown, true);
  document.addEventListener('keydown', onKeydown);
  window.addEventListener('scroll', onReposition, true);
  window.addEventListener('resize', onReposition);
}

function unbindPageListeners() {
  if (!pageBound) return;
  pageBound = false;
  document.removeEventListener('pointerdown', onDocumentPointerDown, true);
  document.removeEventListener('keydown', onKeydown);
  window.removeEventListener('scroll', onReposition, true);
  window.removeEventListener('resize', onReposition);
}

function onDocumentPointerDown(event) {
  const target = event.target;
  if (!(target instanceof Node)) return;
  if (root.value?.contains(target)) return;
  if (props.anchor instanceof HTMLElement && props.anchor.contains(target)) return;
  emit('close');
}

function onKeydown(event) {
  if (event.key === 'Escape') emit('close');
}

function onReposition() {
  placePopup();
}

onMounted(() => {
  nextTick(() => {
    placePopup();
    bindPageListeners();
  });
});

onBeforeUnmount(() => {
  unbindPageListeners();
});
</script>

<template>
  <Teleport to="body">
    <div
      ref="root"
      class="emoji-picker"
      :style="popupStyle"
      role="dialog"
      aria-label="Chọn emoji"
    >
      <div class="emoji-picker__tabs">
        <div class="emoji-picker__tab-list">
          <button
            v-for="group in EMOJI_GROUPS"
            :key="group.key"
            type="button"
            class="emoji-picker__tab"
            :class="{ 'emoji-picker__tab--active': activeGroup === group.key }"
            :aria-label="group.label"
            @click="activeGroup = group.key"
          >
            {{ group.icon }}
          </button>
        </div>
        <button type="button" class="emoji-picker__close" aria-label="Đóng bảng emoji" @click="emit('close')">
          <AppIcon name="close" :size="14" />
        </button>
      </div>

      <div class="emoji-picker__grid">
        <button
          v-for="(emoji, index) in currentEmojis()"
          :key="index"
          type="button"
          class="emoji-picker__cell"
          @click="emit('pick', emoji)"
        >
          {{ emoji }}
        </button>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.emoji-picker {
  position: fixed;
  z-index: 90;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);
  padding: var(--space-3);
}

.emoji-picker__tabs {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  flex-shrink: 0;
  box-shadow: 0 1px 0 var(--color-border);
  padding-bottom: var(--space-2);
  margin-bottom: var(--space-2);
  min-width: 0;
}

.emoji-picker__tab-list {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--space-1);
  flex: 1;
  min-width: 0;
}

.emoji-picker__tab {
  border: none;
  background: none;
  cursor: pointer;
  font-size: 1.125rem;
  padding: var(--space-1);
  border-radius: var(--radius-md);
  line-height: 1;
  flex-shrink: 0;
}

.emoji-picker__tab--active {
  background: var(--color-primary-surface);
}

.emoji-picker__close {
  margin-left: auto;
  border: none;
  background: none;
  color: var(--color-text-muted);
  cursor: pointer;
  padding: var(--space-1);
  flex-shrink: 0;
}

.emoji-picker__grid {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: var(--space-1);
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
}

.emoji-picker__cell {
  border: none;
  background: none;
  cursor: pointer;
  font-size: 1.375rem;
  padding: var(--space-1);
  border-radius: var(--radius-md);
  line-height: 1;
}

.emoji-picker__cell:hover {
  background: var(--color-surface-muted);
}

@media (max-width: 767px) {
  .emoji-picker__grid {
    grid-template-columns: repeat(8, minmax(0, 1fr));
  }

  .emoji-picker__cell {
    font-size: 1.25rem;
  }
}

@media (max-width: 480px) {
  .emoji-picker__grid {
    grid-template-columns: repeat(7, minmax(0, 1fr));
  }
}
</style>
