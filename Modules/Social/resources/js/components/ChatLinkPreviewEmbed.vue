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
  <div v-if="loading || preview" class="chat-link-embed" :class="{ 'chat-link-embed--compact': compact }">
    <p v-if="loading" class="chat-link-embed__loading">Đang tải xem trước liên kết…</p>
    <SocialLinkPreviewCard v-else-if="preview" :preview="preview" />
  </div>
</template>

<style scoped>
.chat-link-embed {
  margin-top: var(--space-2);
  max-width: min(100%, 22rem);
}

.chat-link-embed--compact :deep(.link-preview) {
  max-width: 100%;
}

.chat-link-embed__loading {
  margin: 0;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}
</style>
