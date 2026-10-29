<script setup>
import { computed, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useChatStore } from '../store/chatStore';

const props = defineProps({
  open: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);
const store = useChatStore();
const files = ref([]);
const tab = ref('week');
const slide = ref(0);
const preview = ref(null);

const WEEK_MS = 7 * 24 * 60 * 60 * 1000;

watch(() => props.open, async (value) => {
  if (!value) {
    preview.value = null;
    return;
  }
  tab.value = 'week';
  slide.value = 0;
  files.value = await store.listMedia(store.activeConversationId).catch(() => []);
});

function images() {
  return files.value.filter((file) => file.type === 'image');
}

const weekImages = computed(() => {
  const cutoff = Date.now() - WEEK_MS;
  return images()
    .filter((file) => {
      const time = new Date(file.created_at).getTime();
      return Number.isFinite(time) && time >= cutoff;
    })
    .slice(0, 6);
});

function docs() {
  return files.value.filter((file) => file.type !== 'image' && file.type !== 'audio');
}

function showSlide(next) {
  const total = weekImages.value.length;
  if (total === 0) return;
  slide.value = (next + total) % total;
}
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="chat-album" role="presentation" @mousedown.self="emit('close')">
      <div class="chat-album__panel" role="dialog" aria-modal="true" aria-label="Ảnh và tệp">
        <div class="chat-album__head">
          <h2>Ảnh và tệp</h2>
          <button type="button" aria-label="Đóng" @click="emit('close')"><AppIcon name="close" :size="16" /></button>
        </div>
        <div class="chat-album__tabs" role="tablist">
          <button type="button" role="tab" :aria-selected="tab === 'week'" :class="{ 'chat-album__tab--on': tab === 'week' }" @click="tab = 'week'">
            Tuần này
          </button>
          <button type="button" role="tab" :aria-selected="tab === 'all'" :class="{ 'chat-album__tab--on': tab === 'all' }" @click="tab = 'all'">
            Tất cả
          </button>
        </div>
        <div class="chat-album__body hide-scrollbar">
          <template v-if="tab === 'week'">
            <p v-if="weekImages.length === 0">Tuần này chưa có ảnh mới.</p>
            <div v-else class="chat-album__stage">
              <button type="button" class="chat-album__nav" aria-label="Ảnh trước" @click="showSlide(slide - 1)">
                <AppIcon name="chevronLeft" :size="16" />
              </button>
              <button type="button" class="chat-album__hero" @click="preview = weekImages[slide]">
                <img :src="weekImages[slide].url" :alt="weekImages[slide].name || 'Ảnh nổi bật'" />
              </button>
              <button type="button" class="chat-album__nav" aria-label="Ảnh sau" @click="showSlide(slide + 1)">
                <AppIcon name="chevronRight" :size="16" />
              </button>
            </div>
            <div v-if="weekImages.length > 1" class="chat-album__dots">
              <button
                v-for="(file, index) in weekImages"
                :key="file.id"
                type="button"
                class="chat-album__dot"
                :class="{ 'chat-album__dot--on': index === slide }"
                :aria-label="`Ảnh ${index + 1}`"
                @click="slide = index"
              />
            </div>
          </template>
          <template v-else>
            <p v-if="files.length === 0">Chưa có ảnh hoặc tệp trong cuộc trò chuyện này.</p>
            <div v-if="images().length" class="chat-album__grid">
              <button v-for="file in images()" :key="file.id" type="button" @click="preview = file">
                <img :src="file.url" :alt="file.name || 'Ảnh'" />
              </button>
            </div>
            <a v-for="file in docs()" :key="file.id" class="chat-album__doc" :href="file.url" target="_blank" rel="noopener">
              {{ file.name }}
            </a>
          </template>
        </div>
      </div>
      <div v-if="preview" class="chat-album__preview" role="dialog" aria-label="Xem ảnh" @click.self="preview = null">
        <button type="button" aria-label="Đóng ảnh" @click="preview = null"><AppIcon name="close" :size="18" /></button>
        <img :src="preview.url" :alt="preview.name || 'Ảnh'" />
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.chat-album {
  position: fixed;
  inset: 0;
  z-index: 70;
  display: grid;
  place-items: center;
  padding: 1.25rem;
  background: color-mix(in srgb, var(--color-text) 28%, transparent);
}
.chat-album__panel {
  display: flex;
  flex-direction: column;
  width: min(46rem, calc(100vw - 2.5rem));
  height: min(36rem, calc(100vh - 2.5rem));
  overflow: hidden;
  border-radius: 16px;
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}
.chat-album__head, .chat-album__body { padding: 0.9rem 1rem; }
.chat-album__head { display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
.chat-album__head h2 { margin: 0; font-size: 1.05rem; }
.chat-album__head button,
.chat-album__tabs button,
.chat-album__nav,
.chat-album__hero,
.chat-album__dot,
.chat-album__grid button,
.chat-album__preview button {
  border: 0;
  background: transparent;
  color: var(--color-text);
  cursor: pointer;
  font-family: inherit;
}
.chat-album__tabs {
  display: flex;
  gap: 0.35rem;
  padding: 0 1rem;
  flex-shrink: 0;
}
.chat-album__tabs button {
  padding: 0.3rem 0.7rem;
  border-radius: var(--radius-full);
  box-shadow: inset 0 0 0 1px var(--color-border);
  font-size: 0.8125rem;
}
.chat-album__tab--on {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}
.chat-album__body { flex: 1; min-height: 0; overflow: auto; }
.chat-album__stage {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  gap: 0.4rem;
  align-items: center;
}
.chat-album__hero img,
.chat-album__preview img {
  width: 100%;
  max-height: min(22rem, 52vh);
  object-fit: contain;
  border-radius: 12px;
  background: var(--color-surface-muted);
}
.chat-album__dots { display: flex; justify-content: center; gap: 0.35rem; margin-top: 0.6rem; }
.chat-album__dot { width: 0.45rem; height: 0.45rem; border-radius: var(--radius-full); background: var(--color-border); }
.chat-album__dot--on { background: var(--color-primary); }
.chat-album__grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.4rem;
}
.chat-album__grid img { width: 100%; height: 7rem; object-fit: cover; border-radius: 8px; }
.chat-album__doc { display: block; margin-top: 0.45rem; color: var(--color-primary); }
.chat-album__preview {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  padding: 2.5rem 1rem 1rem;
  background: color-mix(in srgb, var(--color-text) 55%, transparent);
}
.chat-album__preview button {
  position: absolute;
  top: 0.8rem;
  right: 0.8rem;
  color: var(--color-on-primary, #fff);
}
</style>
