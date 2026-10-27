<script setup>
import { onMounted, ref, watch } from 'vue';
import { fetchLinkPreview, previewableUrlsFromText } from '../lib/linkPreviewUtils.js';
import SocialLinkPreviewCard from './SocialLinkPreviewCard.vue';

const props = defineProps({
  text: { type: String, default: '' },
  compact: { type: Boolean, default: true },
});

const preview = ref(null);
const loading = ref(false);

async function load() {
  const [url] = previewableUrlsFromText(props.text, 1);
  if (!url) {
    preview.value = null;
    loading.value = false;
    return;
  }
  loading.value = true;
  preview.value = await fetchLinkPreview(url);
  loading.value = false;
}

onMounted(load);
watch(() => props.text, load);
</script>

<template>
  <div
    v-if="loading || preview"
    class="chat-link-embed"
    :class="{ 'chat-link-embed--compact': compact }"
  >
    <div v-if="loading" class="chat-link-embed__skeleton" aria-hidden="true">
      <div class="chat-link-embed__sk-media" />
      <div class="chat-link-embed__sk-lines">
        <span class="chat-link-embed__sk-line chat-link-embed__sk-line--title" />
        <span class="chat-link-embed__sk-line" />
        <span class="chat-link-embed__sk-line chat-link-embed__sk-line--site" />
      </div>
    </div>
    <SocialLinkPreviewCard v-else-if="preview" :preview="preview" />
  </div>
</template>

<style scoped>
.chat-link-embed {
  margin-top: var(--space-2);
  max-width: min(100%, 24rem);
}

.chat-link-embed--compact :deep(.link-preview) {
  max-width: 100%;
}

.chat-link-embed__skeleton {
  position: relative;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-sm);
}

.chat-link-embed__sk-media {
  aspect-ratio: 1.91 / 1;
  max-height: 9rem;
  background: linear-gradient(
    90deg,
    var(--color-surface-muted) 0%,
    color-mix(in srgb, var(--color-surface-muted) 70%, var(--color-surface)) 50%,
    var(--color-surface-muted) 100%
  );
  background-size: 200% 100%;
  animation: chat-link-shimmer 1.1s ease-in-out infinite;
}

.chat-link-embed__sk-lines {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  padding: var(--space-2) var(--space-3) var(--space-3);
}

.chat-link-embed__sk-line {
  display: block;
  height: 0.65rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
}

.chat-link-embed__sk-line--title {
  width: 78%;
  height: 0.75rem;
}

.chat-link-embed__sk-line--site {
  width: 38%;
  margin-top: 0.15rem;
}

@keyframes chat-link-shimmer {
  0% { background-position: 100% 0; }
  100% { background-position: -100% 0; }
}

@media (prefers-reduced-motion: reduce) {
  .chat-link-embed__sk-media {
    animation: none;
  }
}
</style>
