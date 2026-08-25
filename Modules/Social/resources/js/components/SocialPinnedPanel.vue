<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import DOMPurify from 'dompurify';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { formatSocialTime } from '../lib/formatSocialTime.js';

const PREVIEW_LIMIT = 3;
const COMPANY_LIMIT = 5;
const DIALOG_PAGE_SIZE = 10;

const props = defineProps({
  variant: {
    type: String,
    default: 'company',
    validator: (value) => value === 'company' || value === 'system',
  },
  departmentScope: { type: Boolean, default: false },
  departmentName: { type: String, default: '' },
  personalScope: { type: Boolean, default: false },
  wallUserId: { type: Number, default: null },
  wallUserName: { type: String, default: '' },
});

const emit = defineEmits(['select']);

const posts = ref([]);
const total = ref(0);
const loading = ref(false);

const browseOpen = ref(false);
const browseQuery = ref('');
const browsePosts = ref([]);
const browsePage = ref(1);
const browseLastPage = ref(1);
const browseTotal = ref(0);
const browseLoading = ref(false);
const browseLoadingMore = ref(false);
let searchTimer = null;

const isSystem = computed(() => props.variant === 'system');
const previewLimit = computed(() => (isSystem.value ? PREVIEW_LIMIT : COMPANY_LIMIT));
const hasMore = computed(() => isSystem.value && total.value > previewLimit.value);

const title = computed(() => {
  if (isSystem.value) return 'Thông báo quan trọng';
  if (props.personalScope) {
    return props.wallUserName ? `Tin nổi bật của ${props.wallUserName}` : 'Tin nổi bật';
  }
  return props.departmentScope ? `Tin nổi bật của ${props.departmentName}` : 'Thông báo công ty';
});
const icon = computed(() => (isSystem.value ? 'shield' : 'megaphone'));
const emptyText = computed(() => {
  if (isSystem.value) return 'Hiện chưa có thông báo nào.';
  if (props.personalScope) {
    return props.wallUserName
      ? `Hiện chưa có tin nổi bật của ${props.wallUserName}.`
      : 'Hiện chưa có tin nổi bật.';
  }
  return props.departmentScope
    ? `Hiện chưa có tin nổi bật của ${props.departmentName}.`
    : 'Hiện chưa có thông báo nào.';
});
const loadingText = computed(() => 'Đang tải thông báo...');
const countLabel = computed(() => (isSystem.value ? (total.value || posts.value.length) : posts.value.length));
const moreLabel = computed(() => (
  total.value > PREVIEW_LIMIT
    ? `Xem tất cả ${total.value} thông báo`
    : 'Xem tất cả'
));

const BLOCK_TAGS = new Set(['P', 'H1', 'H2', 'H3', 'H4', 'LI', 'DIV', 'BLOCKQUOTE', 'TR', 'PRE']);

function htmlToSnippet(html) {
  const root = document.createElement('div');
  root.innerHTML = DOMPurify.sanitize(html ?? '');

  const chunks = [];

  function walk(node) {
    if (node.nodeType === Node.TEXT_NODE) {
      const text = (node.textContent ?? '').replace(/\s+/g, ' ');
      if (text) chunks.push(text);
      return;
    }
    if (node.nodeType !== Node.ELEMENT_NODE) return;

    const tag = node.tagName;
    if (tag === 'BR') {
      chunks.push('\n');
      return;
    }
    if (BLOCK_TAGS.has(tag) && chunks.length && chunks[chunks.length - 1] !== '\n') {
      chunks.push('\n');
    }
    for (const child of node.childNodes) walk(child);
    if (BLOCK_TAGS.has(tag)) chunks.push('\n');
  }

  walk(root);

  const lines = chunks
    .join('')
    .replace(/https?:\/\/\S+/gi, ' ')
    .split('\n')
    .map((line) => line.replace(/\s+/g, ' ').trim())
    .filter(Boolean);

  return {
    heading: lines[0] ?? '',
    excerpt: lines.slice(1).join(' '),
  };
}

function toItems(list) {
  return list.map((post) => {
    const snippet = htmlToSnippet(post.content);
    return {
      post,
      heading: snippet.heading || post.poll?.title || post.author?.name || 'Thông báo',
      excerpt: snippet.excerpt || post.poll?.content || '',
    };
  });
}

const items = computed(() => toItems(posts.value));
const browseItems = computed(() => toItems(browsePosts.value));

function wallParams() {
  const params = { scope: props.variant, post_scope: 'company' };
  if (!isSystem.value && props.personalScope) {
    params.post_scope = 'personal';
    if (props.wallUserId) params.wall_user_id = props.wallUserId;
  } else if (!isSystem.value && props.departmentScope) {
    params.post_scope = 'department';
  }
  return params;
}

async function fetchPinned({ page = 1, perPage = previewLimit.value, q = '' } = {}) {
  const params = { ...wallParams(), page, per_page: perPage };
  const needle = q.trim();
  if (needle) params.q = needle;
  const { data } = await window.axios.get('/api/social/pinned', { params });
  const list = data.posts ?? [];

  return {
    posts: list,
    current_page: data.current_page ?? page,
    last_page: data.last_page ?? 1,
    total: data.total ?? list.length,
  };
}

async function load() {
  loading.value = true;
  try {
    const data = await fetchPinned({ page: 1, perPage: previewLimit.value });
    posts.value = data.posts;
    total.value = data.total;
    if (browseOpen.value) {
      await loadBrowse(1, false);
    }
  } finally {
    loading.value = false;
  }
}

async function loadBrowse(targetPage = 1, append = false) {
  append ? (browseLoadingMore.value = true) : (browseLoading.value = true);
  try {
    const data = await fetchPinned({
      page: targetPage,
      perPage: DIALOG_PAGE_SIZE,
      q: browseQuery.value,
    });
    browsePosts.value = append ? [...browsePosts.value, ...data.posts] : data.posts;
    browsePage.value = data.current_page;
    browseLastPage.value = data.last_page;
    browseTotal.value = data.total;
  } catch (error) {
    if (!append) browsePosts.value = [];
    showClientToast('error', error?.response?.data?.message ?? 'Không thể tải thông báo.');
  } finally {
    browseLoading.value = false;
    browseLoadingMore.value = false;
  }
}

function selectPost(post) {
  closeBrowse();
  emit('select', post);
}

function onKey(event) {
  if (event.key === 'Escape') {
    event.preventDefault();
    closeBrowse();
  }
}

function bindPage() {
  document.addEventListener('keydown', onKey);
  document.body.style.overflow = 'hidden';
}

function unbindPage() {
  document.removeEventListener('keydown', onKey);
  document.body.style.overflow = '';
}

function openBrowse() {
  browseOpen.value = true;
}

function closeBrowse() {
  browseOpen.value = false;
}

function onBrowseQueryInput(event) {
  browseQuery.value = event.target.value;
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => loadBrowse(1, false), 250);
}

watch(
  () => [props.variant, props.departmentScope, props.personalScope, props.wallUserId],
  () => load(),
  { immediate: true },
);

watch(browseOpen, (isOpen) => {
  if (isOpen) {
    browseQuery.value = '';
    bindPage();
    loadBrowse(1, false);
    return;
  }
  unbindPage();
  clearTimeout(searchTimer);
  browsePosts.value = [];
  browseQuery.value = '';
});

onBeforeUnmount(() => {
  unbindPage();
  clearTimeout(searchTimer);
});

defineExpose({ load });
</script>

<template>
  <section
    class="announce-panel"
    :class="isSystem ? 'announce-panel--system' : 'announce-panel--company'"
  >
    <h2 class="announce-panel__title">
      <span class="announce-panel__icon" aria-hidden="true">
        <AppIcon :name="icon" :size="16" />
      </span>
      <span class="announce-panel__title-text">{{ title }}</span>
      <span v-if="countLabel > 0" class="announce-panel__count">{{ countLabel }}</span>
    </h2>

    <p v-if="loading" class="announce-panel__empty">{{ loadingText }}</p>
    <p v-else-if="items.length === 0" class="announce-panel__empty">{{ emptyText }}</p>
    <ul v-else class="announce-panel__list hide-scrollbar">
      <li v-for="item in items" :key="item.post.id">
        <button type="button" class="announce-panel__item" @click="selectPost(item.post)">
          <span class="announce-panel__item-heading">{{ item.heading }}</span>
          <span v-if="item.excerpt && !isSystem" class="announce-panel__item-excerpt">{{ item.excerpt }}</span>
          <span class="announce-panel__item-meta">
            <span class="announce-panel__item-author">{{ item.post.author.name }}</span>
            <time :datetime="item.post.created_at">{{ formatSocialTime(item.post.created_at) }}</time>
          </span>
        </button>
      </li>
    </ul>

    <button
      v-if="hasMore && !loading"
      type="button"
      class="announce-panel__more"
      @click="openBrowse"
    >
      <span>{{ moreLabel }}</span>
      <AppIcon name="chevronRight" :size="14" />
    </button>
  </section>

  <Teleport v-if="isSystem" to="body">
    <Transition name="announce-browse-fade">
      <div
        v-if="browseOpen"
        class="announce-browse"
        role="presentation"
        @mousedown.self="closeBrowse"
      >
        <div
          class="announce-browse__panel"
          role="dialog"
          aria-modal="true"
          aria-labelledby="announce-browse-title"
        >
          <header class="announce-browse__header">
            <span class="announce-browse__icon" aria-hidden="true">
              <AppIcon name="shield" :size="16" />
            </span>
            <h2 id="announce-browse-title" class="announce-browse__title">
              Thông báo quan trọng
              <span v-if="browseTotal > 0" class="announce-browse__count">{{ browseTotal }}</span>
            </h2>
            <button type="button" class="announce-browse__close" aria-label="Đóng" @click="closeBrowse">
              <AppIcon name="close" :size="18" />
            </button>
          </header>

          <label class="announce-browse__search">
            <AppIcon name="search" :size="16" />
            <input
              :value="browseQuery"
              type="search"
              placeholder="Tìm thông báo..."
              aria-label="Tìm thông báo quan trọng"
              @input="onBrowseQueryInput"
            />
          </label>

          <div class="announce-browse__body hide-scrollbar">
            <p v-if="browseLoading" class="announce-browse__empty">Đang tải thông báo...</p>
            <p v-else-if="browseItems.length === 0 && browseQuery.trim()" class="announce-browse__empty">
              Không tìm thấy thông báo khớp “{{ browseQuery.trim() }}”.
            </p>
            <p v-else-if="browseItems.length === 0" class="announce-browse__empty">
              Hiện chưa có thông báo nào.
            </p>
            <ul v-else class="announce-browse__list">
              <li v-for="item in browseItems" :key="item.post.id">
                <button type="button" class="announce-browse__item" @click="selectPost(item.post)">
                  <span class="announce-browse__item-heading">{{ item.heading }}</span>
                  <span v-if="item.excerpt" class="announce-browse__item-excerpt">{{ item.excerpt }}</span>
                  <span class="announce-browse__item-meta">
                    <span class="announce-browse__item-author">{{ item.post.author.name }}</span>
                    <time :datetime="item.post.created_at">{{ formatSocialTime(item.post.created_at) }}</time>
                  </span>
                </button>
              </li>
            </ul>

            <button
              v-if="browsePage < browseLastPage && !browseLoading"
              type="button"
              class="announce-browse__more"
              :disabled="browseLoadingMore"
              @click="loadBrowse(browsePage + 1, true)"
            >
              {{ browseLoadingMore ? 'Đang tải...' : 'Xem thêm' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.announce-panel {
  position: relative;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-2));
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  box-shadow: var(--shadow-sm);
  min-width: 0;
  min-height: 0;
}

.announce-panel::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.announce-panel--company {
  background: color-mix(in srgb, var(--color-warning-tint-bg) 55%, var(--color-surface));
}

.announce-panel--company::before {
  background: var(--color-warning);
}

.announce-panel--system {
  background: color-mix(in srgb, var(--color-info-tint-bg) 55%, var(--color-surface));
}

.announce-panel--system::before {
  background: var(--color-info);
}

.announce-panel__title {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: 0.8125rem;
  font-weight: 700;
  line-height: 1.3;
  margin: 0;
}

.announce-panel--company .announce-panel__title {
  color: var(--color-warning-tint-fg);
}

.announce-panel--system .announce-panel__title {
  color: var(--color-info-tint-fg);
}

.announce-panel__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  flex-shrink: 0;
  border-radius: var(--radius-full);
}

.announce-panel--company .announce-panel__icon {
  background: var(--color-warning);
  color: var(--color-on-primary);
}

.announce-panel--system .announce-panel__icon {
  background: var(--color-info);
  color: var(--color-on-primary);
}

.announce-panel--company .announce-panel__icon :deep(.app-icon) {
  animation: announce-megaphone 3.2s ease-in-out 0.6s infinite;
  transform-origin: 30% 70%;
}

.announce-panel__title-text {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.announce-panel__count {
  flex-shrink: 0;
  min-width: 1.25rem;
  height: 1.25rem;
  padding: 0 0.375rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-full);
  font-size: 0.6875rem;
  font-weight: 700;
  line-height: 1;
  background: var(--color-surface);
  color: var(--color-text-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.announce-panel--company .announce-panel__count {
  color: var(--color-warning-tint-fg);
  box-shadow: inset 0 0 0 1px var(--color-warning-tint-border);
}

.announce-panel--system .announce-panel__count {
  color: var(--color-info-tint-fg);
  box-shadow: inset 0 0 0 1px var(--color-info-tint-border);
}

.announce-panel__empty {
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.45;
  color: var(--color-text-muted);
}

.announce-panel__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  max-height: 22rem;
  overflow-y: auto;
}

.announce-panel--system .announce-panel__list {
  max-height: none;
  overflow: visible;
}

.announce-panel__item {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 0.375rem;
  width: 100%;
  min-width: 0;
  text-align: left;
  border: none;
  background: var(--color-surface);
  color: var(--color-text);
  font-family: inherit;
  cursor: pointer;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  box-shadow: inset 0 0 0 1px var(--color-border);
  transition: background 0.2s ease, box-shadow 0.2s ease;
}

.announce-panel--company .announce-panel__item {
  box-shadow: inset 0 0 0 1px var(--color-warning-tint-border);
}

.announce-panel--system .announce-panel__item {
  gap: 0.25rem;
  box-shadow: inset 0 0 0 1px var(--color-info-tint-border);
}

.announce-panel__item:hover {
  background: var(--color-surface);
  box-shadow: var(--shadow-sm), inset 0 0 0 1px var(--color-border);
}

.announce-panel--company .announce-panel__item:hover {
  box-shadow: var(--shadow-sm), inset 0 0 0 1px var(--color-warning);
}

.announce-panel--system .announce-panel__item:hover {
  box-shadow: var(--shadow-sm), inset 0 0 0 1px var(--color-info);
}

.announce-panel__item:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.announce-panel__item-heading,
.announce-panel__item-excerpt {
  display: block;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  overflow: hidden;
  min-width: 0;
  width: 100%;
  overflow-wrap: break-word;
  word-break: break-word;
}

.announce-panel__item-heading {
  -webkit-line-clamp: 2;
  line-clamp: 2;
  max-height: calc(1.4em * 2);
  font-size: 0.8125rem;
  font-weight: 700;
  line-height: 1.4;
  color: var(--color-text);
}

.announce-panel--system .announce-panel__item-heading {
  -webkit-line-clamp: 1;
  line-clamp: 1;
  max-height: 1.4em;
}

.announce-panel__item-excerpt {
  -webkit-line-clamp: 3;
  line-clamp: 3;
  max-height: calc(1.45em * 3);
  font-size: 0.75rem;
  font-weight: 500;
  line-height: 1.45;
  color: var(--color-text-muted);
}

.announce-panel__item-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  min-width: 0;
  width: 100%;
  margin-top: 0.125rem;
  padding-top: var(--space-2);
  font-size: 0.6875rem;
  font-weight: 500;
  color: var(--color-text-muted);
  box-shadow: 0 -1px 0 var(--color-border);
}

.announce-panel--system .announce-panel__item-meta {
  margin-top: 0;
  padding-top: 0;
  box-shadow: none;
}

.announce-panel__item-author {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-weight: 600;
}

.announce-panel__item-meta time {
  flex-shrink: 0;
  white-space: nowrap;
}

.announce-panel__more {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.25rem;
  width: 100%;
  margin: 0;
  border: none;
  background: color-mix(in srgb, var(--color-info) 8%, var(--color-surface));
  color: var(--color-info-tint-fg);
  font-family: inherit;
  font-size: 0.75rem;
  font-weight: 700;
  padding: var(--space-2);
  border-radius: var(--radius-md);
  cursor: pointer;
}

.announce-panel__more:hover {
  background: color-mix(in srgb, var(--color-info) 14%, var(--color-surface));
}

.announce-browse {
  position: fixed;
  inset: 0;
  z-index: 310;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.announce-browse__panel {
  width: min(32rem, calc(100vw - 2.5rem));
  height: min(40rem, calc(100vh - 2.5rem));
  max-height: calc(100vh - 2.5rem);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.announce-browse__header {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex-shrink: 0;
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.announce-browse__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  flex-shrink: 0;
  border-radius: var(--radius-full);
  background: var(--color-info);
  color: var(--color-on-primary);
}

.announce-browse__title {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex: 1;
  min-width: 0;
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 700;
  color: var(--color-text);
}

.announce-browse__count {
  flex-shrink: 0;
  min-width: 1.25rem;
  height: 1.25rem;
  padding: 0 0.375rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-full);
  font-size: 0.6875rem;
  font-weight: 700;
  line-height: 1;
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
}

.announce-browse__close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text);
  cursor: pointer;
}

.announce-browse__close:hover {
  background: var(--color-border);
}

.announce-browse__search {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: 0.5rem;
  margin: var(--space-3) var(--space-4) 0;
  height: 2.25rem;
  padding: 0 0.75rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.announce-browse__search:focus-within {
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 5%, var(--color-surface));
  box-shadow: inset 0 0 0 1px var(--color-primary);
}

.announce-browse__search input {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 500;
  outline: none;
}

.announce-browse__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: var(--space-3) var(--space-4) var(--space-4);
}

.announce-browse__empty {
  margin: 0;
  padding: var(--space-6) var(--space-3);
  text-align: center;
  font-size: 0.875rem;
  color: var(--color-text-muted);
}

.announce-browse__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.announce-browse__item {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 0.375rem;
  width: 100%;
  min-width: 0;
  text-align: left;
  border: none;
  background: var(--color-surface);
  color: var(--color-text);
  font-family: inherit;
  cursor: pointer;
  padding: var(--space-3);
  border-radius: var(--radius-md);
  box-shadow: inset 0 0 0 1px var(--color-info-tint-border);
}

.announce-browse__item:hover {
  box-shadow: var(--shadow-sm), inset 0 0 0 1px var(--color-info);
}

.announce-browse__item:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.announce-browse__item-heading,
.announce-browse__item-excerpt {
  display: block;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  overflow: hidden;
  min-width: 0;
  width: 100%;
  overflow-wrap: break-word;
  word-break: break-word;
}

.announce-browse__item-heading {
  -webkit-line-clamp: 2;
  line-clamp: 2;
  font-size: 0.875rem;
  font-weight: 700;
  line-height: 1.4;
  color: var(--color-text);
}

.announce-browse__item-excerpt {
  -webkit-line-clamp: 2;
  line-clamp: 2;
  font-size: 0.8125rem;
  font-weight: 500;
  line-height: 1.45;
  color: var(--color-text-muted);
}

.announce-browse__item-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  min-width: 0;
  width: 100%;
  padding-top: var(--space-2);
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--color-text-muted);
  box-shadow: 0 -1px 0 var(--color-border);
}

.announce-browse__item-author {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-weight: 600;
}

.announce-browse__item-meta time {
  flex-shrink: 0;
  white-space: nowrap;
}

.announce-browse__more {
  display: block;
  width: 100%;
  margin-top: var(--space-3);
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.875rem;
  padding: var(--space-2) var(--space-4);
  border-radius: var(--radius-md);
  cursor: pointer;
}

.announce-browse__more:hover {
  background: var(--color-surface-muted);
}

.announce-browse__more:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.announce-browse-fade-enter-active,
.announce-browse-fade-leave-active {
  transition: opacity 0.15s ease;
}

.announce-browse-fade-enter-from,
.announce-browse-fade-leave-to {
  opacity: 0;
}

@keyframes announce-megaphone {
  0%,
  100% {
    transform: rotate(0);
  }

  18% {
    transform: rotate(-14deg);
  }

  36% {
    transform: rotate(10deg);
  }

  54% {
    transform: rotate(-7deg);
  }

  72% {
    transform: rotate(4deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .announce-panel--company .announce-panel__icon :deep(.app-icon) {
    animation: none;
  }

  .announce-panel__item,
  .announce-browse-fade-enter-active,
  .announce-browse-fade-leave-active {
    transition: none;
  }
}
</style>
