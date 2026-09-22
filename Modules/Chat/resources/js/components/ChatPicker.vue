<script setup>
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { EMOJI_GROUPS } from '@modules/Project/resources/js/constants/emojiGroups.js';
import SocialAnimatedSticker from '@modules/Social/resources/js/components/SocialAnimatedSticker.vue';
import { STICKER_PACKS } from '@modules/Social/resources/js/constants/stickers.js';

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
</script>

<template>
  <div class="chat-picker" role="dialog" aria-label="Chọn emoji hoặc sticker">
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
        {{ pack.icon }}
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
    <div v-else class="chat-picker__grid chat-picker__grid--stickers">
      <button
        v-for="sticker in stickers"
        :key="sticker.id"
        type="button"
        class="chat-picker__sticker"
        :aria-label="`Gửi sticker ${sticker.emoji}`"
        @click="emit('pick-sticker', sticker)"
      >
        <SocialAnimatedSticker :id="sticker.id" :emoji="sticker.emoji" hover-play />
      </button>
    </div>
  </div>
</template>

<style scoped>
.chat-picker {
  display: flex;
  flex-direction: column;
  min-height: 0;
  max-height: 16rem;
  margin: 0 var(--space-3);
  border-radius: 12px;
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  overflow: hidden;
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
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-sm);
  font-size: 1rem;
  line-height: 1;
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
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.chat-picker__emoji {
  height: 2rem;
  border-radius: var(--radius-sm);
  font-size: 1.25rem;
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
  height: 3.25rem;
  border-radius: var(--radius-sm);
}
</style>
