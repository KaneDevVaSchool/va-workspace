<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { usageLabel } from '../lib/hashtagClick.js';

const props = defineProps({
  postScope: { type: String, default: 'company' },
  wallUserId: { type: Number, default: null },
  groupId: { type: Number, default: null },
  activeHashtag: { type: String, default: '' },
});

const emit = defineEmits(['select']);

const tags = ref([]);
const loading = ref(false);
const query = ref('');
let searchTimer = null;

const wallParams = computed(() => {
  const params = { post_scope: props.postScope, limit: 12 };
  if (props.postScope === 'personal' && props.wallUserId) {
    params.wall_user_id = props.wallUserId;
  }
  if (props.postScope === 'group' && props.groupId) {
    params.group_id = props.groupId;
  }
  return params;
});

async function load(search = query.value) {
  loading.value = true;
  try {
    const params = { ...wallParams.value };
    const needle = search.trim().replace(/^#/, '');
    if (needle) params.q = needle;
    const { data } = await window.axios.get('/api/social/hashtags', { params });
    tags.value = data.hashtags ?? [];
  } catch {
    tags.value = [];
  } finally {
    loading.value = false;
  }
}

function onQueryInput(event) {
  query.value = event.target.value;
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => load(query.value), 200);
}

function isActive(tag) {
  return props.activeHashtag && tag.name === props.activeHashtag;
}

watch(
  () => [props.postScope, props.wallUserId, props.groupId],
  () => load(),
  { immediate: true },
);

onBeforeUnmount(() => clearTimeout(searchTimer));

defineExpose({ load });
</script>

<template>
  <section class="hashtag-panel">
    <h2 class="hashtag-panel__title">
      <span class="hashtag-panel__icon" aria-hidden="true">
        <AppIcon name="hash" :size="16" />
      </span>
      <span class="hashtag-panel__title-text">Hashtag gần đây</span>
      <span v-if="tags.length > 0 && !query.trim()" class="hashtag-panel__count">{{ tags.length }}</span>
    </h2>

    <label class="hashtag-panel__search">
      <AppIcon name="search" :size="14" />
      <input
        :value="query"
        type="search"
        placeholder="Tìm hashtag..."
        aria-label="Tìm hashtag"
        @input="onQueryInput"
      />
    </label>

    <p v-if="loading" class="hashtag-panel__empty">Đang tải hashtag...</p>
    <p v-else-if="tags.length === 0 && query.trim()" class="hashtag-panel__empty">
      Không có hashtag khớp “{{ query.trim() }}”.
    </p>
    <p v-else-if="tags.length === 0" class="hashtag-panel__empty">
      Gắn #hashtag trong bài viết để xuất hiện tại đây.
    </p>
    <ul v-else class="hashtag-panel__list hide-scrollbar">
      <li v-for="tag in tags" :key="tag.name">
        <button
          type="button"
          class="hashtag-panel__item"
          :class="{ 'hashtag-panel__item--active': isActive(tag) }"
          :aria-current="isActive(tag) ? 'true' : undefined"
          @click="emit('select', tag.name)"
        >
          <span class="hashtag-panel__name">#{{ tag.label || tag.name }}</span>
          <span class="hashtag-panel__usage">{{ usageLabel(tag.usage_count) }}</span>
        </button>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.hashtag-panel {
  position: relative;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-2));
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  box-shadow: var(--shadow-sm);
  min-width: 0;
}

.hashtag-panel::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-primary);
}

.hashtag-panel__title {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: 0.8125rem;
  font-weight: 700;
  line-height: 1.3;
  margin: 0;
  color: var(--color-primary);
}

.hashtag-panel__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.hashtag-panel__title-text {
  flex: 1;
  min-width: 0;
}

.hashtag-panel__count {
  flex-shrink: 0;
  font-size: 0.6875rem;
  font-weight: 700;
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 12%, var(--color-surface));
  border-radius: var(--radius-full);
  padding: 0.1rem 0.45rem;
}

.hashtag-panel__search {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  min-width: 0;
  padding: 0.35rem 0.6rem;
  background: var(--color-surface-muted);
  border-radius: var(--radius-full);
  color: var(--color-text-muted);
}

.hashtag-panel__search input {
  flex: 1;
  min-width: 0;
  border: none;
  background: none;
  font-family: inherit;
  font-size: 0.75rem;
  color: var(--color-text);
  outline: none;
}

.hashtag-panel__empty {
  margin: 0;
  font-size: 0.75rem;
  color: var(--color-text-muted);
  line-height: 1.4;
}

.hashtag-panel__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
  max-height: 18rem;
  overflow-y: auto;
}

.hashtag-panel__item {
  width: 100%;
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
  padding: 0.4rem 0.5rem;
  border: none;
  background: none;
  border-radius: var(--radius-sm);
  cursor: pointer;
  text-align: left;
  font-family: inherit;
}

.hashtag-panel__item:hover {
  background: var(--color-surface-muted);
}

.hashtag-panel__item--active {
  background: color-mix(in srgb, var(--color-primary) 10%, var(--color-surface));
}

.hashtag-panel__name {
  min-width: 0;
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-primary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.hashtag-panel__usage {
  flex-shrink: 0;
  font-size: 0.6875rem;
  color: var(--color-text-muted);
  font-variant-numeric: tabular-nums;
}

.hashtag-panel__item--active .hashtag-panel__usage {
  color: var(--color-primary);
}
</style>
