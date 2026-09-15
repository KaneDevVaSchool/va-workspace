<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { downloadGif, searchGifs } from '../lib/socialGifs.js';

const props = defineProps({
  kind: { type: String, default: 'gif' },
});

const emit = defineEmits(['pick']);

const query = ref('');
const results = ref([]);
const loading = ref(false);
const nextPage = ref(1);
const hasMore = ref(true);
const downloadingId = ref(null);
const errorMessage = ref('');
const gridRef = ref(null);
let searchTimer = null;
let requestToken = 0;

async function runSearch(reset) {
  const token = ++requestToken;
  loading.value = true;
  errorMessage.value = '';
  try {
    const page = reset ? 1 : nextPage.value;
    const data = await searchGifs(query.value, page, props.kind);
    if (token !== requestToken) return;
    results.value = reset ? data.results : [...results.value, ...data.results];
    hasMore.value = data.next_page !== null;
    nextPage.value = data.next_page ?? page;
  } catch {
    if (token !== requestToken) return;
    errorMessage.value = 'Không thể tải GIF, vui lòng thử lại.';
  } finally {
    if (token === requestToken) loading.value = false;
  }
}

function onQueryInput() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => runSearch(true), 320);
}

function onScroll() {
  const el = gridRef.value;
  if (!el || loading.value || !hasMore.value) return;
  if (el.scrollTop + el.clientHeight >= el.scrollHeight - 120) {
    runSearch(false);
  }
}

async function pick(item) {
  if (downloadingId.value) return;
  downloadingId.value = item.id;
  errorMessage.value = '';
  try {
    const descriptor = await downloadGif(item.download_url);
    emit('pick', { ...descriptor, previewUrl: item.preview_url });
  } catch {
    errorMessage.value = 'Không thể tải GIF này, vui lòng chọn ảnh khác.';
  } finally {
    downloadingId.value = null;
  }
}

watch(() => props.kind, () => runSearch(true));

onMounted(() => runSearch(true));
onBeforeUnmount(() => clearTimeout(searchTimer));
</script>

<template>
  <div class="gif-picker">
    <div class="gif-picker__search">
      <input
        v-model="query"
        type="text"
        class="gif-picker__input"
        :placeholder="kind === 'sticker' ? 'Tìm sticker động... (vd. chào buổi sáng)' : 'Tìm GIF... (vd. chúc mừng)'"
        @input="onQueryInput"
      />
    </div>

    <div v-if="errorMessage" class="gif-picker__error">{{ errorMessage }}</div>

    <div ref="gridRef" class="gif-picker__grid hide-scrollbar" @scroll="onScroll">
      <button
        v-for="item in results"
        :key="item.id"
        type="button"
        class="gif-picker__tile"
        :class="{ 'gif-picker__tile--busy': downloadingId === item.id }"
        :style="{ aspectRatio: item.preview_width && item.preview_height ? `${item.preview_width} / ${item.preview_height}` : '1 / 1' }"
        :disabled="downloadingId !== null"
        :aria-label="item.title || 'Chọn GIF này'"
        @click="pick(item)"
      >
        <img :src="item.preview_url" :alt="item.title" loading="lazy" />
        <span v-if="downloadingId === item.id" class="gif-picker__spinner"></span>
      </button>

      <p v-if="!loading && results.length === 0" class="gif-picker__empty">
        Không tìm thấy GIF phù hợp.
      </p>
      <p v-if="loading" class="gif-picker__loading">Đang tải...</p>
    </div>

    <p class="gif-picker__credit">Cung cấp bởi GIPHY</p>
  </div>
</template>

<style scoped>
.gif-picker {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  flex: 1;
  min-height: 0;
}

.gif-picker__search {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px transparent;
  transition: box-shadow 0.16s ease;
}

.gif-picker__search:focus-within {
  box-shadow: inset 0 0 0 1px var(--color-primary);
}

.gif-picker__input {
  width: 100%;
  border: none;
  background: none;
  padding: var(--space-2) var(--space-3);
  font-family: inherit;
  font-size: 0.8125rem;
  color: var(--color-text);
}

.gif-picker__input:focus {
  outline: none;
}

.gif-picker__input::placeholder {
  color: var(--color-text-muted);
}

.gif-picker__grid {
  column-count: 2;
  column-gap: var(--space-1);
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
}

.gif-picker__tile {
  position: relative;
  display: block;
  width: 100%;
  border: none;
  padding: 0;
  margin: 0 0 var(--space-1);
  border-radius: var(--radius-md);
  overflow: hidden;
  cursor: pointer;
  background: var(--color-surface-muted);
  break-inside: avoid;
  animation: gif-picker-tile-in 0.22s cubic-bezier(0.22, 1, 0.36, 1) both;
  transition:
    outline-color 0.14s ease,
    transform 0.14s ease;
}

.gif-picker__tile img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}

.gif-picker__tile:hover {
  outline: 2px solid var(--color-primary);
  outline-offset: -2px;
  transform: scale(1.015);
}

.gif-picker__tile:active {
  transform: scale(0.98);
}

.gif-picker__tile--busy {
  opacity: 0.6;
  pointer-events: none;
}

@keyframes gif-picker-tile-in {
  from {
    opacity: 0;
    transform: translateY(4px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.gif-picker__spinner-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: color-mix(in srgb, #000000 28%, transparent);
}

.gif-picker__spinner {
  width: 1.25rem;
  height: 1.25rem;
  border-radius: var(--radius-full);
  border: 2px solid color-mix(in srgb, #ffffff 40%, transparent);
  border-top-color: #fff;
  animation: gif-picker-spin 0.7s linear infinite;
}

@keyframes gif-picker-spin {
  to {
    transform: rotate(360deg);
  }
}

.gif-picker__empty,
.gif-picker__loading {
  column-span: all;
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  padding: var(--space-4) 0;
}

.gif-picker__error {
  flex-shrink: 0;
  color: var(--color-danger);
  font-size: 0.75rem;
}

.gif-picker__credit {
  flex-shrink: 0;
  margin: 0;
  text-align: right;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
}

</style>
