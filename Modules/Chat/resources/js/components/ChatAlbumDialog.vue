<script setup>
import { ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useChatStore } from '../store/chatStore';

const props = defineProps({
  open: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);
const store = useChatStore();
const files = ref([]);

watch(() => props.open, async (value) => {
  if (!value) return;
  files.value = await store.listMedia(store.activeConversationId).catch(() => []);
});

function images() {
  return files.value.filter((file) => file.type === 'image');
}
function docs() {
  return files.value.filter((file) => file.type !== 'image' && file.type !== 'audio');
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
        <div class="chat-album__body hide-scrollbar">
          <p v-if="files.length === 0">Chưa có ảnh hoặc tệp trong cuộc trò chuyện này.</p>
          <div v-if="images().length" class="chat-album__grid">
            <a v-for="file in images()" :key="file.id" :href="file.url" target="_blank" rel="noopener">
              <img :src="file.url" :alt="file.name || 'Ảnh'" />
            </a>
          </div>
          <a v-for="file in docs()" :key="file.id" class="chat-album__doc" :href="file.url" target="_blank" rel="noopener">
            {{ file.name }}
          </a>
        </div>
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
.chat-album__head button { border: 0; background: transparent; color: var(--color-text); cursor: pointer; }
.chat-album__body { flex: 1; min-height: 0; overflow: auto; }
.chat-album__grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.4rem;
}
.chat-album__grid img { width: 100%; height: 7rem; object-fit: cover; border-radius: 8px; }
.chat-album__doc { display: block; margin-top: 0.45rem; color: var(--color-primary); }
</style>
