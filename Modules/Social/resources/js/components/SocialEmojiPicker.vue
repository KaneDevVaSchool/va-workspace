<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { EMOJI_GROUPS } from '../constants/emojiGroups.js';
import { STICKER_PACKS } from '../constants/stickers.js';
import SocialAnimatedSticker from './SocialAnimatedSticker.vue';

const props = defineProps({
  /** Nút/khung neo — click vào đây không đóng picker. */
  anchor: { type: Object, default: null },
  panel: { type: String, default: 'emoji' },
});

const emit = defineEmits(['pick', 'pick-sticker', 'close', 'update:panel']);

const activeGroup = ref(EMOJI_GROUPS[0].key);
const activePack = ref(STICKER_PACKS[0].key);
const root = ref(null);
const popupStyle = ref({});
let pageBound = false;

const isStickerPanel = computed(() => props.panel === 'sticker');

function currentEmojis() {
  return EMOJI_GROUPS.find((g) => g.key === activeGroup.value)?.emojis ?? [];
}

function currentStickers() {
  return STICKER_PACKS.find((g) => g.key === activePack.value)?.stickers ?? [];
}

function setPanel(panel) {
  if (props.panel !== panel) emit('update:panel', panel);
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
      :aria-label="isStickerPanel ? 'Chọn sticker động' : 'Chọn emoji'"
    >
      <div class="emoji-picker__modes">
        <button
          type="button"
          class="emoji-picker__mode"
          :class="{ 'emoji-picker__mode--active': !isStickerPanel }"
          @click="setPanel('emoji')"
        >
          Emoji
        </button>
        <button
          type="button"
          class="emoji-picker__mode"
          :class="{ 'emoji-picker__mode--active': isStickerPanel }"
          @click="setPanel('sticker')"
        >
          Sticker
        </button>
        <button type="button" class="emoji-picker__close" aria-label="Đóng bảng sticker" @click="emit('close')">
          <AppIcon name="close" :size="14" />
        </button>
      </div>

      <div class="emoji-picker__tabs">
        <div v-if="!isStickerPanel" class="emoji-picker__tab-list hide-scrollbar">
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
        <div v-else class="emoji-picker__tab-list hide-scrollbar">
          <button
            v-for="group in STICKER_PACKS"
            :key="group.key"
            type="button"
            class="emoji-picker__tab"
            :class="{ 'emoji-picker__tab--active': activePack === group.key }"
            :aria-label="group.label"
            @click="activePack = group.key"
          >
            {{ group.icon }}
          </button>
        </div>
      </div>

      <div v-if="!isStickerPanel" class="emoji-picker__grid hide-scrollbar">
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
      <div v-else class="emoji-picker__stickers hide-scrollbar">
        <button
          v-for="sticker in currentStickers()"
          :key="sticker.id"
          type="button"
          class="emoji-picker__sticker"
          :aria-label="`Sticker ${sticker.emoji}`"
          @click="emit('pick-sticker', sticker)"
        >
          <SocialAnimatedSticker hover-play :id="sticker.id" :emoji="sticker.emoji" />
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

.emoji-picker__modes {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  flex-shrink: 0;
  padding-bottom: var(--space-2);
  margin-bottom: var(--space-2);
  box-shadow: 0 1px 0 var(--color-border);
}

.emoji-picker__mode {
  border: none;
  background: none;
  cursor: pointer;
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text-muted);
  padding: var(--space-1) var(--space-3);
  border-radius: var(--radius-full);
}

.emoji-picker__mode:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.emoji-picker__mode--active {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.emoji-picker__tabs {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  flex-shrink: 0;
  padding-bottom: var(--space-2);
  margin-bottom: var(--space-2);
  min-width: 0;
}

.emoji-picker__tab-list {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  flex: 1;
  min-width: 0;
  overflow-x: auto;
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

.emoji-picker__stickers {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: var(--space-1);
  flex: 1;
  min-height: 0;
  overflow-x: hidden;
  overflow-y: auto;
  overscroll-behavior: contain;
  align-content: start;
  padding: 0.15rem;
}

.emoji-picker__cell,
.emoji-picker__sticker {
  border: none;
  background: none;
  cursor: pointer;
  border-radius: var(--radius-md);
  line-height: 1;
}

.emoji-picker__cell {
  font-size: 1.375rem;
  padding: var(--space-1);
}

.emoji-picker__sticker {
  position: relative;
  z-index: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-2);
  min-height: 3.75rem;
  transition: background 0.15s ease;
}

.emoji-picker__sticker :deep(.social-sticker) {
  transform: scale(1);
  transform-origin: center center;
  transition: transform 0.18s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.emoji-picker__cell:hover {
  background: var(--color-surface-muted);
}

@media (hover: hover) and (pointer: fine) {
  .emoji-picker__sticker:hover,
  .emoji-picker__sticker:focus-visible {
    z-index: 3;
    background: var(--color-surface-muted);
  }

  .emoji-picker__sticker:hover :deep(.social-sticker),
  .emoji-picker__sticker:focus-visible :deep(.social-sticker) {
    transform: scale(1.7);
  }
}

@media (max-width: 767px) {
  .emoji-picker__grid {
    grid-template-columns: repeat(8, minmax(0, 1fr));
  }

  .emoji-picker__stickers {
    grid-template-columns: repeat(5, minmax(0, 1fr));
  }

  .emoji-picker__cell {
    font-size: 1.25rem;
  }
}

@media (max-width: 480px) {
  .emoji-picker__grid {
    grid-template-columns: repeat(7, minmax(0, 1fr));
  }

  .emoji-picker__stickers {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

@media (prefers-reduced-motion: reduce) {
  .emoji-picker__sticker :deep(.social-sticker) {
    transition: none;
  }

  .emoji-picker__sticker:hover :deep(.social-sticker),
  .emoji-picker__sticker:focus-visible :deep(.social-sticker) {
    transform: none;
  }
}
</style>
