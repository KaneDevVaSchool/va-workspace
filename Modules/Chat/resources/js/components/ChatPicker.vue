<script setup>
import { computed, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { EMOJI_GROUPS } from '@modules/Project/resources/js/constants/emojiGroups.js';
import { STICKER_PACKS } from '@modules/Social/resources/js/constants/stickers.js';
import { prefetchStickerIds, warmStickerAssets } from '@modules/Social/resources/js/lib/socialStickers.js';
import { vaStickerById } from '@modules/Social/resources/js/lib/vaStickers.js';

const props = defineProps({
  panel: { type: String, default: 'emoji' },
});

const emit = defineEmits(['pick-emoji', 'pick-sticker', 'update:panel']);

const activeGroup = ref(EMOJI_GROUPS[0]?.key ?? 'faces');
const activePack = ref(STICKER_PACKS[0]?.key ?? 'faces');

const emojis = computed(() => EMOJI_GROUPS.find((group) => group.key === activeGroup.value)?.emojis ?? []);
const stickers = computed(() => STICKER_PACKS.find((pack) => pack.key === activePack.value)?.stickers ?? []);

function setPanel(panel) {
  emit('update:panel', panel);
}

function vaSticker(sticker) {
  return vaStickerById(sticker.id);
}

const PREFETCH_CHUNK = 20;

function prefetchPackStickerJson(ids) {
  const unique = [...new Set(ids.filter(Boolean))];
  if (unique.length === 0) return;
  warmStickerAssets();
  let index = 0;
  const step = () => {
    const slice = unique.slice(index, index + PREFETCH_CHUNK);
    index += PREFETCH_CHUNK;
    if (slice.length === 0) return;
    void prefetchStickerIds(slice).finally(() => {
      if (index < unique.length) {
        if (typeof requestIdleCallback === 'function') {
          requestIdleCallback(step, { timeout: 1200 });
        } else {
          window.setTimeout(step, 16);
        }
      }
    });
  };
  step();
}

watch(
  () => [props.panel, activePack.value],
  ([panel, packKey]) => {
    if (panel !== 'sticker') return;
    const pack = STICKER_PACKS.find((item) => item.key === packKey);
    prefetchPackStickerJson((pack?.stickers ?? []).map((sticker) => sticker.id));
  },
  { immediate: true },
);
</script>

<template>
  <div
    class="chat-picker"
    :class="{
      'chat-picker--sticker': props.panel === 'sticker',
      'chat-picker--emoji': props.panel === 'emoji',
    }"
    role="dialog"
    aria-label="Chọn emoji hoặc sticker"
  >
    <div class="chat-picker__tabs" role="tablist">
      <button
        type="button"
        class="chat-picker__tab"
        :class="{ 'chat-picker__tab--on': props.panel === 'emoji' }"
        role="tab"
        :aria-selected="props.panel === 'emoji'"
        @click="setPanel('emoji')"
      >
        <AppIcon name="smile" :size="16" :stroke-width="1.75" />
        Emoji
      </button>
      <button
        type="button"
        class="chat-picker__tab"
        :class="{ 'chat-picker__tab--on': props.panel === 'sticker' }"
        role="tab"
        :aria-selected="props.panel === 'sticker'"
        @click="setPanel('sticker')"
      >
        <AppIcon name="sticker" :size="16" :stroke-width="1.75" />
        Sticker
      </button>
    </div>

    <div v-if="props.panel === 'emoji'" class="chat-picker__packs" role="tablist" aria-label="Nhóm emoji">
      <button
        v-for="group in EMOJI_GROUPS"
        :key="group.key"
        type="button"
        class="chat-picker__pack"
        :class="{ 'chat-picker__pack--on': group.key === activeGroup }"
        :aria-label="group.label"
        @click="activeGroup = group.key"
      >
        {{ group.icon }}
      </button>
    </div>
    <div v-else class="chat-picker__packs" role="tablist" aria-label="Bộ sticker">
      <button
        v-for="pack in STICKER_PACKS"
        :key="pack.key"
        type="button"
        class="chat-picker__pack"
        :class="{ 'chat-picker__pack--on': pack.key === activePack }"
        :aria-label="pack.label"
        @click="activePack = pack.key"
      >
        <img
          v-if="pack.stickers?.[0] && vaSticker(pack.stickers[0])"
          class="chat-picker__pack-img"
          :src="vaSticker(pack.stickers[0]).src"
          alt=""
        />
        <template v-else>{{ pack.icon }}</template>
      </button>
    </div>

    <div v-if="props.panel === 'emoji'" class="chat-picker__grid">
      <button
        v-for="emoji in emojis"
        :key="emoji"
        type="button"
        class="chat-picker__emoji"
        :aria-label="`Chèn ${emoji}`"
        @click="emit('pick-emoji', emoji)"
      >
        {{ emoji }}
      </button>
    </div>
    <div v-else class="chat-picker__grid chat-picker__grid--stickers hide-scrollbar">
      <button
        v-for="sticker in stickers"
        :key="sticker.id"
        type="button"
        class="chat-picker__sticker"
        :aria-label="`Gửi sticker ${sticker.emoji}`"
        @click="emit('pick-sticker', sticker)"
      >
        <img
          v-if="vaSticker(sticker)"
          class="chat-picker__sticker-img"
          :src="vaSticker(sticker).src"
          alt=""
        />
        <span v-else class="chat-picker__sticker-emoji">{{ sticker.emoji }}</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.chat-picker {
  display: flex;
  flex-direction: column;
  flex: 0 0 auto;
  min-height: 0;
  max-height: min(14rem, 34dvh);
  margin: var(--space-2) var(--space-3) 0;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    0 8px 24px color-mix(in srgb, var(--color-tertiary-900) 8%, transparent);
  overflow: hidden;
}

.chat-picker--sticker {
  max-height: min(21.5rem, 46dvh);
}

.chat-picker__tabs,
.chat-picker__packs {
  display: flex;
  gap: 0.25rem;
  padding: 0.35rem;
  flex-shrink: 0;
}

.chat-picker__packs {
  overflow-x: auto;
  scrollbar-width: none;
  box-shadow: 0 1px 0 var(--color-border);
}

.chat-picker__packs::-webkit-scrollbar {
  display: none;
}

.chat-picker__tab,
.chat-picker__pack,
.chat-picker__emoji,
.chat-picker__sticker {
  border: none;
  background: transparent;
  cursor: pointer;
  font-family: inherit;
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
}

.chat-picker__tab {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.35rem 0.6rem;
  border-radius: var(--radius-sm);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 700;
}

.chat-picker__tab--on,
.chat-picker__pack--on {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.chat-picker__pack {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-sm);
  font-size: 1rem;
  line-height: 1;
}

.chat-picker--sticker .chat-picker__pack {
  width: 2.125rem;
  height: 2.125rem;
  border-radius: var(--radius-md);
}

.chat-picker--sticker .chat-picker__pack-img {
  width: 1.5rem;
  height: 1.5rem;
}

.chat-picker__pack-img {
  width: 1.35rem;
  height: 1.35rem;
  object-fit: contain;
}

.chat-picker__grid {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  display: grid;
  grid-template-columns: repeat(8, minmax(0, 1fr));
  gap: 0.15rem;
  padding: 0.35rem;
}

.chat-picker__grid--stickers {
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.4rem;
  padding: 0.5rem 0.45rem 0.6rem;
  align-content: start;
  background: linear-gradient(
    180deg,
    color-mix(in srgb, var(--color-surface-muted) 55%, var(--color-surface)) 0%,
    var(--color-surface) 100%
  );
}

.chat-picker__emoji {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 2rem;
  overflow: hidden;
  padding: 0;
  border-radius: var(--radius-sm);
  font-size: 1.15rem;
  line-height: 1;
}

.chat-picker__emoji:hover,
.chat-picker__sticker:hover,
.chat-picker__pack:hover,
.chat-picker__tab:hover {
  background: var(--color-surface-muted);
}

.chat-picker__sticker {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 4.5rem;
  overflow: visible;
  border-radius: var(--radius-md);
  transition:
    background 0.16s ease,
    transform 0.14s ease;
}

.chat-picker__sticker-emoji {
  font-size: 2.65rem;
  line-height: 1;
  transform-origin: center center;
  transition: transform 0.18s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.chat-picker__sticker-img {
  width: 3.25rem;
  height: 3.25rem;
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  transform-origin: center center;
  transition: transform 0.18s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.chat-picker__sticker:active {
  transform: scale(0.96);
  background: color-mix(in srgb, var(--color-primary-surface) 65%, var(--color-surface-muted));
}

@media (hover: hover) and (pointer: fine) {
  .chat-picker__sticker:hover,
  .chat-picker__sticker:focus-visible {
    background: var(--color-surface-muted);
  }

  .chat-picker__sticker:hover .chat-picker__sticker-emoji,
  .chat-picker__sticker:focus-visible .chat-picker__sticker-emoji,
  .chat-picker__sticker:hover .chat-picker__sticker-img,
  .chat-picker__sticker:focus-visible .chat-picker__sticker-img {
    transform: scale(1.12);
  }
}

@media (min-width: 480px) {
  .chat-picker__grid--stickers {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }

  .chat-picker__sticker {
    height: 4.25rem;
  }

  .chat-picker__sticker-emoji {
    font-size: 2.45rem;
  }

  .chat-picker__sticker-img {
    width: 3rem;
    height: 3rem;
  }
}

@media (min-width: 768px) {
  .chat-picker--sticker {
    max-height: min(26rem, 52dvh);
  }

  .chat-picker__grid--stickers {
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 0.35rem;
  }
}

.chat-picker__emoji:active,
.chat-picker__sticker:active,
.chat-picker__pack:active {
  transform: none;
}
</style>
