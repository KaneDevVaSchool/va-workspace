<script setup>
import { onMounted, ref } from 'vue';
import DOMPurify from 'dompurify';
import AppIcon from '@/components/AppIcon.vue';

const emit = defineEmits(['select']);

const posts = ref([]);
const loading = ref(false);

function plainText(html) {
  return DOMPurify.sanitize(html ?? '', { ALLOWED_TAGS: [] }).trim();
}

async function load() {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/social/pinned');
    posts.value = data.posts;
  } finally {
    loading.value = false;
  }
}

defineExpose({ load });

onMounted(load);
</script>

<template>
  <section class="pinned-panel">
    <h2 class="pinned-panel__title">
      <AppIcon name="bookmark" :size="18" />
      Thông báo công ty
    </h2>

    <p v-if="loading" class="pinned-panel__empty">Đang tải thông báo...</p>
    <p v-else-if="posts.length === 0" class="pinned-panel__empty">
      Chưa có bài viết được ghim.
    </p>
    <ul v-else class="pinned-panel__list">
      <li v-for="post in posts" :key="post.id">
        <button type="button" class="pinned-panel__item" @click="emit('select', post.id)">
          {{ plainText(post.content) || post.author.name }}
        </button>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.pinned-panel {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  box-shadow: var(--shadow-sm);
}

.pinned-panel__empty {
  margin: 0;
  font-size: 0.875rem;
  color: var(--color-text-muted);
}

.pinned-panel__title {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: 1rem;
  font-weight: 600;
  color: var(--color-text);
  margin: 0;
}

.pinned-panel__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.pinned-panel__item {
  display: block;
  width: 100%;
  text-align: left;
  border: none;
  background: none;
  color: var(--color-text);
  font-size: 0.875rem;
  cursor: pointer;
  padding: var(--space-1) 0;
  box-shadow: 0 1px 0 var(--color-border);
  padding-bottom: var(--space-2);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pinned-panel__item:last-child {
  box-shadow: none;
  padding-bottom: 0;
}

.pinned-panel__item:hover {
  color: var(--color-primary);
}
</style>
