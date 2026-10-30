<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  users: { type: Array, default: () => [] },
  activeIndex: { type: Number, default: 0 },
  query: { type: String, default: '' },
  /** Ô soạn — khung gợi ý neo theo ô này khi chưa có vị trí con trỏ. */
  anchor: { type: Object, default: null },
  /** Đọc vị trí con trỏ lúc vẽ, để khung bám theo @ khi cuộn. */
  caretReader: { type: Function, default: null },
  loading: { type: Boolean, default: false },
  refreshing: { type: Boolean, default: false },
  loadingMore: { type: Boolean, default: false },
  failed: { type: Boolean, default: false },
  hasMore: { type: Boolean, default: false },
  focusSearch: { type: Boolean, default: false },
});

const emit = defineEmits(['pick', 'activate', 'search', 'search-focus', 'more', 'retry', 'close']);

const root = ref(null);
const listEl = ref(null);
const searchInput = ref(null);
const listId = `mention-picker-${Math.random().toString(36).slice(2, 8)}`;
const popupStyle = ref({});
const brokenAvatars = ref({});
let bound = false;

const statusLabel = computed(() => {
  if (props.failed && props.users.length === 0) return 'Chưa tải được danh sách';
  if (props.loading && props.users.length === 0) return 'Đang tải danh sách đồng nghiệp…';
  if (props.refreshing) return 'Đang tìm…';
  if (props.query.trim()) {
    const more = props.hasMore ? ' Bấm Xem thêm nếu chưa thấy người cần gắn.' : '';
    return `Đang hiện ${props.users.length} người khớp “${props.query.trim()}”.${more}`;
  }
  if (props.hasMore) return 'Gõ đủ họ tên để thu hẹp, hoặc bấm Xem thêm để lần ra người cần gắn.';
  return 'Gõ họ và tên, ví dụ Trần Minh Quang.';
});

const emptyLabel = computed(() =>
  props.query.trim()
    ? `Không thấy đồng nghiệp khớp “${props.query.trim()}”. Thử gõ đủ họ và tên.`
    : 'Chưa có đồng nghiệp để gắn thẻ.',
);

function foldChar(char) {
  return char.normalize('NFD').replace(/\p{M}/gu, '').replace(/đ/gi, 'd').toLowerCase();
}

function nameParts(name, query) {
  const chars = [...(name || '')];
  if (chars.length === 0) return [{ text: '', hit: false }];
  const folded = chars.map(foldChar);
  const tokens = [...(query || '')]
    .map(foldChar)
    .join('')
    .split(/\s+/)
    .filter(Boolean);
  const hits = chars.map(() => false);
  tokens.forEach((token) => {
    const tokenChars = [...token];
    for (let start = 0; start <= folded.length - tokenChars.length; start += 1) {
      const matched = tokenChars.every((tokenChar, offset) => folded[start + offset] === tokenChar);
      if (!matched) continue;
      tokenChars.forEach((_, offset) => {
        hits[start + offset] = true;
      });
    }
  });
  const parts = [];
  let buffer = '';
  let hit = hits[0];
  chars.forEach((char, index) => {
    if (hits[index] !== hit && buffer) {
      parts.push({ text: buffer, hit });
      buffer = '';
    }
    hit = hits[index];
    buffer += char;
  });
  if (buffer) parts.push({ text: buffer, hit });
  return parts;
}

function personMeta(user) {
  return [user.job_title_name, user.department, user.company_name].filter(Boolean).join(' · ');
}

function initial(name) {
  return name?.trim().charAt(0).toUpperCase() || '?';
}

function showAvatar(user) {
  return Boolean(user.avatar_url) && !brokenAvatars.value[user.id];
}

function onAvatarError(id) {
  brokenAvatars.value = { ...brokenAvatars.value, [id]: true };
}

function anchorEl() {
  const anchor = props.anchor;
  if (anchor instanceof HTMLElement) return anchor;
  return null;
}

function place() {
  const el = anchorEl();
  const caret = props.caretReader?.() ?? null;
  const rect = caret
    ? { left: caret.left, top: caret.top, bottom: caret.bottom }
    : el?.getBoundingClientRect();
  if (!rect) {
    popupStyle.value = {};
    return;
  }

  const viewport = window.visualViewport;
  const viewportTop = viewport?.offsetTop ?? 0;
  const viewportHeight = viewport?.height ?? window.innerHeight;
  const gap = 6;
  const margin = 8;
  const width = Math.min(360, window.innerWidth - margin * 2);
  const left = Math.max(margin, Math.min(rect.left, window.innerWidth - width - margin));
  const topInView = rect.top - viewportTop;
  const bottomInView = rect.bottom - viewportTop;
  const spaceBelow = viewportHeight - bottomInView - gap - margin;
  const spaceAbove = topInView - gap - margin;
  const openUp = spaceBelow < 220 && spaceAbove > spaceBelow;
  const available = Math.max(180, openUp ? spaceAbove : spaceBelow);
  const height = Math.min(440, available);

  popupStyle.value = {
    left: `${left}px`,
    width: `${width}px`,
    maxHeight: `${height}px`,
    ...(openUp
      ? { top: 'auto', bottom: `${window.innerHeight - rect.top + gap}px` }
      : { top: `${rect.bottom + gap}px`, bottom: 'auto' }),
  };
}

function scrollActiveIntoView() {
  const list = listEl.value;
  if (!list) return;
  const row = list.querySelector('.mention-picker__row--active');
  if (!(row instanceof HTMLElement)) return;
  const top = row.offsetTop;
  const bottom = top + row.offsetHeight;
  if (top < list.scrollTop) list.scrollTop = top;
  else if (bottom > list.scrollTop + list.clientHeight) {
    list.scrollTop = bottom - list.clientHeight;
  }
}

function onReposition() {
  place();
}

function bind() {
  if (bound) return;
  bound = true;
  window.addEventListener('scroll', onReposition, true);
  window.addEventListener('resize', onReposition);
  window.visualViewport?.addEventListener('resize', onReposition);
  window.visualViewport?.addEventListener('scroll', onReposition);
}

function unbind() {
  if (!bound) return;
  bound = false;
  window.removeEventListener('scroll', onReposition, true);
  window.removeEventListener('resize', onReposition);
  window.visualViewport?.removeEventListener('resize', onReposition);
  window.visualViewport?.removeEventListener('scroll', onReposition);
}

function focusSearchInput() {
  if (!props.focusSearch) return;
  nextTick(() => searchInput.value?.focus());
}

function moveActive(step) {
  const last = Math.max(props.users.length - 1, 0);
  if (step > 0 && props.activeIndex >= last && props.hasMore && !props.loadingMore) {
    emit('more');
    return;
  }
  emit('activate', Math.min(last, Math.max(props.activeIndex + step, 0)));
}

function onSearchKeydown(event) {
  if (event.key === 'ArrowDown') {
    event.preventDefault();
    moveActive(1);
    return;
  }
  if (event.key === 'ArrowUp') {
    event.preventDefault();
    moveActive(-1);
    return;
  }
  if (event.key === 'Enter') {
    event.preventDefault();
    const user = props.users[props.activeIndex];
    if (user) emit('pick', user);
    return;
  }
  if (event.key === 'Escape') {
    event.preventDefault();
    emit('close');
  }
}

onMounted(() => {
  nextTick(() => {
    place();
    scrollActiveIntoView();
    bind();
    focusSearchInput();
  });
});

onBeforeUnmount(unbind);

watch(
  () => [props.activeIndex, props.users.length, props.anchor, props.loading],
  () => {
    nextTick(() => {
      place();
      scrollActiveIntoView();
    });
  },
);

watch(
  () => props.focusSearch,
  (value) => {
    if (value) focusSearchInput();
  },
);
</script>

<template>
  <Teleport to="body">
    <div
      ref="root"
      class="mention-picker"
      role="dialog"
      aria-label="Gắn thẻ đồng nghiệp"
      :style="popupStyle"
    >
      <div class="mention-picker__head">
        <span class="mention-picker__mark" aria-hidden="true">@</span>
        <span class="mention-picker__heading">
          <span class="mention-picker__title">Gắn thẻ đồng nghiệp</span>
          <span class="mention-picker__status">{{ statusLabel }}</span>
        </span>
      </div>

      <label class="mention-picker__search">
        <AppIcon name="search" :size="16" />
        <input
          ref="searchInput"
          :value="query"
          type="search"
          placeholder="Tìm họ tên, ví dụ Trần Minh Quang"
          aria-label="Tìm đồng nghiệp để gắn thẻ"
          aria-autocomplete="list"
          :aria-controls="listId"
          autocomplete="off"
          enterkeyhint="search"
          @input="emit('search', $event.target.value)"
          @focus="emit('search-focus', true)"
          @blur="emit('search-focus', false)"
          @keydown="onSearchKeydown"
        />
      </label>

      <div
        :id="listId"
        ref="listEl"
        class="mention-picker__list hide-scrollbar"
        role="listbox"
        aria-label="Danh sách đồng nghiệp"
        :aria-busy="loading || refreshing || loadingMore"
      >
        <div v-if="loading && users.length === 0" class="mention-picker__skeletons" aria-hidden="true">
          <div v-for="row in 4" :key="row" class="mention-picker__skeleton">
            <span class="mention-picker__skeleton-avatar"></span>
            <span class="mention-picker__skeleton-lines">
              <span></span>
              <span></span>
            </span>
          </div>
        </div>

        <button
          v-for="(user, index) in users"
          :key="user.id"
          type="button"
          class="mention-picker__row"
          :class="{ 'mention-picker__row--active': index === activeIndex }"
          role="option"
          :aria-selected="index === activeIndex"
          @mouseenter="emit('activate', index)"
          @mousedown.prevent="emit('pick', user)"
        >
          <img
            v-if="showAvatar(user)"
            class="mention-picker__avatar"
            :src="user.avatar_url"
            :alt="`Ảnh đại diện của ${user.name}`"
            @error="onAvatarError(user.id)"
          />
          <span v-else class="mention-picker__avatar mention-picker__avatar--placeholder">
            {{ initial(user.name) }}
          </span>
          <span class="mention-picker__meta">
            <span class="mention-picker__name">
              <template v-for="(part, partIndex) in nameParts(user.name, query)" :key="partIndex">
                <mark v-if="part.hit" class="mention-picker__hit">{{ part.text }}</mark>
                <template v-else>{{ part.text }}</template>
              </template>
            </span>
            <span v-if="personMeta(user)" class="mention-picker__dept">{{ personMeta(user) }}</span>
          </span>
        </button>

        <p v-if="!loading && users.length === 0 && !failed" class="mention-picker__empty">{{ emptyLabel }}</p>
        <p v-if="failed && users.length === 0" class="mention-picker__empty">
          Chưa tải được danh sách đồng nghiệp. Bạn thử lại sau ít phút.
        </p>
      </div>

      <div v-if="failed || hasMore || loadingMore" class="mention-picker__more-wrap">
        <button
          v-if="failed"
          type="button"
          class="mention-picker__more"
          @mousedown.prevent="emit('retry')"
        >
          Thử lại
        </button>
        <button
          v-else
          type="button"
          class="mention-picker__more"
          :disabled="loadingMore"
          @mousedown.prevent="emit('more')"
        >
          {{ loadingMore ? 'Đang tải…' : 'Xem thêm đồng nghiệp' }}
        </button>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.mention-picker {
  position: fixed;
  z-index: 90;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);
  padding: var(--space-3) 0 var(--space-2);
}

.mention-picker__head {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  padding: 0 var(--space-3) var(--space-2);
}

.mention-picker__mark {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  flex-shrink: 0;
  border-radius: var(--radius-full);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 1rem;
  font-weight: 700;
}

.mention-picker__heading {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 0;
}

.mention-picker__title {
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
}

.mention-picker__status {
  font-size: 0.75rem;
  line-height: 1.35;
  color: var(--color-text-muted);
}

.mention-picker__search {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin: 0 var(--space-3) var(--space-2);
  padding: var(--space-2) var(--space-3);
  background: var(--color-surface-muted);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  color: var(--color-text-muted);
}

.mention-picker__search input {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.9375rem;
  outline: none;
}

.mention-picker__search input::placeholder {
  color: var(--color-text-muted);
}

.mention-picker__list {
  position: relative;
  flex: 1;
  min-height: 0;
  overflow-x: hidden;
  overflow-y: auto;
  padding: 0 var(--space-2);
}

.mention-picker__row {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  width: 100%;
  border: none;
  background: none;
  text-align: left;
  padding: var(--space-2);
  border-radius: var(--radius-md);
  cursor: pointer;
  font-family: inherit;
}

.mention-picker__row:hover,
.mention-picker__row--active {
  background: var(--color-primary-surface);
}

.mention-picker__avatar {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
}

.mention-picker__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface-strong);
  color: var(--color-primary);
  font-size: 0.9375rem;
  font-weight: 700;
}

.mention-picker__meta {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  min-width: 0;
}

.mention-picker__name {
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--color-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.mention-picker__hit {
  background: var(--color-primary-surface-strong);
  color: var(--color-primary);
  font-weight: 700;
  border-radius: 0.2rem;
  padding: 0;
}

.mention-picker__dept {
  font-size: 0.75rem;
  color: var(--color-text-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.mention-picker__empty {
  margin: 0;
  padding: var(--space-3) var(--space-2);
  font-size: 0.8125rem;
  line-height: 1.4;
  color: var(--color-text-muted);
}

.mention-picker__more-wrap {
  flex-shrink: 0;
  padding: var(--space-2) var(--space-3) 0;
}

.mention-picker__more {
  width: 100%;
  border: none;
  background: var(--color-surface-muted);
  color: var(--color-primary);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  cursor: pointer;
}

.mention-picker__more:hover {
  background: var(--color-primary-surface);
}

.mention-picker__more:disabled {
  cursor: default;
  color: var(--color-text-muted);
}

.mention-picker__skeleton {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2);
}

.mention-picker__skeleton-avatar,
.mention-picker__skeleton-lines span {
  background: var(--color-surface-muted);
  border-radius: var(--radius-full);
}

.mention-picker__skeleton-avatar {
  width: 2.5rem;
  height: 2.5rem;
  flex-shrink: 0;
}

.mention-picker__skeleton-lines {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  flex: 1;
}

.mention-picker__skeleton-lines span:first-child {
  width: 58%;
  height: 0.75rem;
}

.mention-picker__skeleton-lines span:last-child {
  width: 78%;
  height: 0.625rem;
}
</style>
