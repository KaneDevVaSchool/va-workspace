<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { EMOJI_GROUPS } from '../constants/emojiGroups.js';
import { EMOJI_KEYWORDS } from '../constants/emojiKeywords.js';
import { STICKER_PACKS } from '../constants/stickers.js';
import { getRecentPicks, pushRecentPick } from '../lib/socialRecentPicks.js';
import SocialAnimatedSticker from './SocialAnimatedSticker.vue';
import SocialGifPicker from './SocialGifPicker.vue';

const props = defineProps({
  /** Nút/khung neo — click vào đây không đóng picker. */
  anchor: { type: Object, default: null },
  panel: { type: String, default: 'emoji' },
});

const emit = defineEmits(['pick', 'pick-sticker', 'pick-gif', 'close', 'update:panel']);

const RECENT_KEY = '__recent__';

const activeGroup = ref(EMOJI_GROUPS[0].key);
const activePack = ref(STICKER_PACKS[0].key);
const root = ref(null);
const popupStyle = ref({});
const emojiQuery = ref('');
const stickerQuery = ref('');
const recentEmojis = ref(getRecentPicks('emoji'));
const recentStickers = ref(getRecentPicks('sticker'));
let pageBound = false;

const isStickerPanel = computed(() => props.panel === 'sticker');
const isGifPanel = computed(() => props.panel === 'gif');

const emojiGroupsWithRecent = computed(() => {
  if (recentEmojis.value.length === 0) return EMOJI_GROUPS;
  return [{ key: RECENT_KEY, label: 'Hay dùng', icon: '🕒', emojis: recentEmojis.value }, ...EMOJI_GROUPS];
});

const stickerPacksWithRecent = computed(() => {
  if (recentStickers.value.length === 0) return STICKER_PACKS;
  return [{ key: RECENT_KEY, label: 'Hay dùng', icon: '🕒', stickers: recentStickers.value }, ...STICKER_PACKS];
});

function normalize(text) {
  return String(text ?? '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '');
}

function matchesQuery(emoji, groupLabel, query) {
  const q = normalize(query);
  if (!q) return true;
  const keywords = normalize(EMOJI_KEYWORDS[emoji] ?? '');
  return keywords.includes(q) || normalize(groupLabel).includes(q);
}

function currentEmojis() {
  const groups = emojiGroupsWithRecent.value;
  const query = emojiQuery.value.trim();

  if (query) {
    const seen = new Set();
    const matches = [];
    for (const group of EMOJI_GROUPS) {
      for (const emoji of group.emojis) {
        if (seen.has(emoji)) continue;
        if (matchesQuery(emoji, group.label, query)) {
          seen.add(emoji);
          matches.push(emoji);
        }
      }
    }
    return matches;
  }

  return groups.find((g) => g.key === activeGroup.value)?.emojis ?? groups[0]?.emojis ?? [];
}

function currentStickers() {
  const packs = stickerPacksWithRecent.value;
  const query = stickerQuery.value.trim();

  if (query) {
    const seen = new Set();
    const matches = [];
    for (const pack of STICKER_PACKS) {
      for (const sticker of pack.stickers) {
        if (seen.has(sticker.id)) continue;
        if (matchesQuery(sticker.emoji, pack.label, query)) {
          seen.add(sticker.id);
          matches.push(sticker);
        }
      }
    }
    return matches;
  }

  return packs.find((g) => g.key === activePack.value)?.stickers ?? packs[0]?.stickers ?? [];
}

function setPanel(panel) {
  if (props.panel !== panel) emit('update:panel', panel);
}

function pickEmoji(emoji) {
  recentEmojis.value = pushRecentPick('emoji', emoji);
  emit('pick', emoji);
}

function pickSticker(sticker) {
  recentStickers.value = pushRecentPick('sticker', sticker, (a, b) => a.id === b.id);
  emit('pick-sticker', sticker);
}

watch(emojiQuery, (value) => {
  if (value.trim()) activeGroup.value = EMOJI_GROUPS[0].key;
});

watch(stickerQuery, (value) => {
  if (value.trim()) activePack.value = STICKER_PACKS[0].key;
});

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
      :aria-label="isGifPanel ? 'Tìm GIF' : (isStickerPanel ? 'Chọn sticker động' : 'Chọn emoji')"
    >
      <div class="emoji-picker__modes">
        <button
          type="button"
          class="emoji-picker__mode"
          :class="{ 'emoji-picker__mode--active': !isStickerPanel && !isGifPanel }"
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
        <button
          type="button"
          class="emoji-picker__mode"
          :class="{ 'emoji-picker__mode--active': isGifPanel }"
          @click="setPanel('gif')"
        >
          GIF
        </button>
        <button type="button" class="emoji-picker__close" aria-label="Đóng" @click="emit('close')">
          <AppIcon name="close" :size="14" />
        </button>
      </div>

      <div v-if="!isGifPanel && !isStickerPanel" class="emoji-picker__search">
        <AppIcon name="search" :size="14" class="emoji-picker__search-icon" />
        <input
          v-model="emojiQuery"
          type="text"
          class="emoji-picker__search-input"
          placeholder="Tìm emoji... (vd. cười, tim, chó)"
        />
      </div>
      <div v-if="!isGifPanel && isStickerPanel" class="emoji-picker__search">
        <AppIcon name="search" :size="14" class="emoji-picker__search-icon" />
        <input
          v-model="stickerQuery"
          type="text"
          class="emoji-picker__search-input"
          placeholder="Tìm sticker... (vd. cười, tim, chó)"
        />
      </div>

      <div v-if="!isGifPanel && !emojiQuery.trim() && !isStickerPanel" class="emoji-picker__tabs">
        <div class="emoji-picker__tab-list hide-scrollbar">
          <button
            v-for="group in emojiGroupsWithRecent"
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
      </div>
      <div v-if="!isGifPanel && !stickerQuery.trim() && isStickerPanel" class="emoji-picker__tabs">
        <div class="emoji-picker__tab-list hide-scrollbar">
          <button
            v-for="group in stickerPacksWithRecent"
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

      <Transition name="emoji-picker-fade" mode="out-in">
        <div v-if="isGifPanel" key="gif" class="emoji-picker__gif-wrap">
          <SocialGifPicker kind="gif" @pick="emit('pick-gif', $event)" />
        </div>
        <div v-else-if="!isStickerPanel" key="emoji" class="emoji-picker__grid hide-scrollbar">
          <button
            v-for="(emoji, index) in currentEmojis()"
            :key="index"
            type="button"
            class="emoji-picker__cell"
            @click="pickEmoji(emoji)"
          >
            {{ emoji }}
          </button>
          <p v-if="emojiQuery.trim() && currentEmojis().length === 0" class="emoji-picker__empty">
            Không tìm thấy emoji phù hợp.
          </p>
        </div>
        <div v-else key="sticker" class="emoji-picker__stickers hide-scrollbar">
          <button
            v-for="sticker in currentStickers()"
            :key="sticker.id"
            type="button"
            class="emoji-picker__sticker"
            :aria-label="`Sticker ${sticker.emoji}`"
            @click="pickSticker(sticker)"
          >
            <SocialAnimatedSticker hover-play :id="sticker.id" :emoji="sticker.emoji" />
          </button>
          <p v-if="stickerQuery.trim() && currentStickers().length === 0" class="emoji-picker__empty">
            Không tìm thấy sticker phù hợp.
          </p>
        </div>
      </Transition>
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
  transition:
    background 0.16s ease,
    color 0.16s ease;
}

.emoji-picker__mode:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.emoji-picker__mode--active {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.emoji-picker__search {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex-shrink: 0;
  margin-bottom: var(--space-2);
  padding: 0 var(--space-2);
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px transparent;
  transition: box-shadow 0.16s ease;
}

.emoji-picker__search:focus-within {
  box-shadow: inset 0 0 0 1px var(--color-primary);
}

.emoji-picker__search-icon {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.emoji-picker__search-input {
  flex: 1;
  min-width: 0;
  border: none;
  background: none;
  padding: var(--space-2) 0;
  font-family: inherit;
  font-size: 0.8125rem;
  color: var(--color-text);
}

.emoji-picker__search-input:focus {
  outline: none;
}

.emoji-picker__search-input::placeholder {
  color: var(--color-text-muted);
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
  scroll-behavior: smooth;
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
  transition:
    background 0.16s ease,
    transform 0.16s ease;
}

.emoji-picker__tab:hover {
  background: var(--color-surface-muted);
}

.emoji-picker__tab--active {
  background: var(--color-primary-surface);
  transform: translateY(-1px);
}

.emoji-picker__close {
  margin-left: auto;
  border: none;
  background: none;
  color: var(--color-text-muted);
  cursor: pointer;
  padding: var(--space-1);
  border-radius: var(--radius-md);
  flex-shrink: 0;
  transition: background 0.16s ease;
}

.emoji-picker__close:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
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

.emoji-picker__gif-wrap {
  display: flex;
  flex: 1;
  min-height: 0;
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

.emoji-picker__cell {
  transition:
    background 0.14s ease,
    transform 0.14s ease;
}

.emoji-picker__cell:hover {
  background: var(--color-surface-muted);
  transform: scale(1.15);
}

.emoji-picker__cell:active {
  transform: scale(0.95);
}

.emoji-picker__empty {
  grid-column: 1 / -1;
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  padding: var(--space-4) 0;
  margin: 0;
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

.emoji-picker-fade-enter-active,
.emoji-picker-fade-leave-active {
  transition:
    opacity 0.14s ease,
    transform 0.14s ease;
}

.emoji-picker-fade-enter-from {
  opacity: 0;
  transform: translateY(4px);
}

.emoji-picker-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

@media (prefers-reduced-motion: reduce) {
  .emoji-picker__sticker :deep(.social-sticker) {
    transition: none;
  }

  .emoji-picker__sticker:hover :deep(.social-sticker),
  .emoji-picker__sticker:focus-visible :deep(.social-sticker) {
    transform: none;
  }

  .emoji-picker__cell:hover {
    transform: none;
  }

  .emoji-picker__tab--active {
    transform: none;
  }

  .emoji-picker-fade-enter-active,
  .emoji-picker-fade-leave-active {
    transition: none;
  }
}
</style>
