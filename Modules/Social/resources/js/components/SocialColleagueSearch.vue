<script setup>
import { computed, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { searchUsers } from '@modules/Chat/resources/js/api/chatApi';

const props = defineProps({
  layout: {
    type: String,
    default: 'dropdown',
    validator: (value) => value === 'dropdown' || value === 'panel',
  },
});

const emit = defineEmits(['select']);

const query = ref('');
const results = ref([]);
const searching = ref(false);
const inputRef = ref(null);
let timer = null;

const trimmedQuery = computed(() => query.value.trim());
const isPanel = computed(() => props.layout === 'panel');
const showResultsRegion = computed(
  () => isPanel.value || trimmedQuery.value !== '',
);

watch(query, (value) => {
  clearTimeout(timer);
  const trimmed = value.trim();
  if (trimmed === '') {
    results.value = [];
    searching.value = false;
    return;
  }
  timer = window.setTimeout(() => runSearch(trimmed), 250);
});

async function runSearch(value) {
  searching.value = true;
  try {
    results.value = await searchUsers(value);
  } catch {
    results.value = [];
  } finally {
    searching.value = false;
  }
}

function pick(user) {
  query.value = '';
  results.value = [];
  emit('select', user.id);
}

function initial(name) {
  return name?.trim().charAt(0).toUpperCase() || '?';
}

function departmentLabel(user) {
  const dept = user?.department;
  if (!dept) return '';
  if (typeof dept === 'string') return dept.trim();
  if (typeof dept === 'object' && dept.name) return String(dept.name).trim();
  return '';
}

function focus() {
  inputRef.value?.focus();
}

defineExpose({ focus });
</script>

<template>
  <div
    class="colleague-search"
    :class="{ 'colleague-search--panel': isPanel }"
  >
    <label class="colleague-search__field">
      <span class="colleague-search__field-icon" aria-hidden="true">
        <AppIcon name="search" :size="18" />
      </span>
      <input
        ref="inputRef"
        v-model="query"
        type="search"
        class="colleague-search__input"
        :placeholder="isPanel ? 'Nhập tên hoặc bộ phận…' : 'Tìm đồng nghiệp...'"
        aria-label="Tìm đồng nghiệp để xem tường"
        enterkeyhint="search"
        autocomplete="off"
      />
    </label>

    <div
      v-if="isPanel && trimmedQuery === ''"
      class="colleague-search__intro"
    >
      <span class="colleague-search__intro-icon" aria-hidden="true">
        <AppIcon name="users" :size="22" :stroke-width="1.75" />
      </span>
      <p class="colleague-search__intro-title">Tìm và xem tường cá nhân</p>
      <p class="colleague-search__intro-hint">
        Gõ ít nhất một ký tự tên hoặc phòng ban để gợi ý đồng nghiệp trong hệ thống.
      </p>
    </div>

    <div
      v-if="showResultsRegion && trimmedQuery !== ''"
      class="colleague-search__results"
      :class="{ 'colleague-search__results--panel': isPanel }"
      role="listbox"
      aria-label="Kết quả tìm đồng nghiệp"
    >
      <p v-if="searching" class="colleague-search__empty">
        <AppIcon name="refresh" :size="16" class="colleague-search__empty-spin" />
        Đang tìm…
      </p>
      <p v-else-if="results.length === 0" class="colleague-search__empty">
        Không tìm thấy ai phù hợp với “{{ trimmedQuery }}”.
      </p>
      <template v-else>
        <button
          v-for="user in results"
          :key="user.id"
          type="button"
          class="colleague-search__item"
          role="option"
          @click="pick(user)"
        >
          <img
            v-if="user.avatar_url"
            class="colleague-search__avatar"
            :src="user.avatar_url"
            :alt="`Ảnh đại diện của ${user.name}`"
          />
          <span v-else class="colleague-search__avatar colleague-search__avatar--placeholder">
            {{ initial(user.name) }}
          </span>
          <span class="colleague-search__copy">
            <span class="colleague-search__name">{{ user.name }}</span>
            <span v-if="departmentLabel(user)" class="colleague-search__dept">
              {{ departmentLabel(user) }}
            </span>
          </span>
          <AppIcon
            name="chevronRight"
            :size="18"
            :stroke-width="2"
            class="colleague-search__chevron"
            aria-hidden="true"
          />
        </button>
      </template>
    </div>
  </div>
</template>

<style scoped>
.colleague-search {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  min-height: 0;
}

.colleague-search--panel {
  flex: 1;
  min-height: 0;
}

.colleague-search__field {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0 var(--space-3);
  min-height: 2.75rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  box-shadow: inset 0 0 0 1px var(--color-border-subtle);
}

.colleague-search--panel .colleague-search__field {
  min-height: 3rem;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.colleague-search__field-icon {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.colleague-search__input {
  width: 100%;
  min-width: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.9375rem;
  outline: none;
}

.colleague-search__input::placeholder {
  color: var(--color-text-muted);
}

.colleague-search__intro {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-5) var(--space-3);
  text-align: center;
}

.colleague-search__intro-icon {
  display: grid;
  place-items: center;
  width: 3rem;
  height: 3rem;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-tertiary) 10%, var(--color-surface-muted));
  color: var(--color-tertiary);
}

.colleague-search__intro-title {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--color-text);
}

.colleague-search__intro-hint {
  margin: 0;
  max-width: 18rem;
  font-size: 0.8125rem;
  line-height: 1.45;
  color: var(--color-text-muted);
}

.colleague-search__results {
  position: absolute;
  z-index: 5;
  top: calc(100% + 0.35rem);
  left: 0;
  right: 0;
  max-height: 16rem;
  overflow: auto;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg), inset 0 0 0 1px var(--color-border);
}

.colleague-search__results--panel {
  position: static;
  flex: 1;
  min-height: 0;
  max-height: none;
  overflow: auto;
  padding: var(--space-1);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border-subtle);
}

.colleague-search__empty {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  margin: 0;
  padding: var(--space-4) var(--space-3);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.45;
  text-align: center;
}

.colleague-search__empty-spin {
  animation: colleague-search-spin 0.8s linear infinite;
}

.colleague-search__item {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  width: 100%;
  border: none;
  background: transparent;
  text-align: left;
  padding: var(--space-2) var(--space-2);
  cursor: pointer;
  font-family: inherit;
  border-radius: var(--radius-md);
  transition: background 0.15s ease;
}

.colleague-search__results:not(.colleague-search__results--panel) .colleague-search__item {
  padding: 0.65rem 0.75rem;
  border-radius: 0;
  box-shadow: 0 1px 0 var(--color-border-subtle);
}

.colleague-search__results:not(.colleague-search__results--panel) .colleague-search__item:last-child {
  box-shadow: none;
}

.colleague-search__item:active,
.colleague-search__item:hover {
  background: color-mix(in srgb, var(--color-tertiary) 6%, var(--color-surface));
}

.colleague-search__results--panel .colleague-search__item {
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  margin-bottom: var(--space-2);
}

.colleague-search__results--panel .colleague-search__item:last-child {
  margin-bottom: 0;
}

.colleague-search__avatar {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
}

.colleague-search__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 0.875rem;
  font-weight: 700;
}

.colleague-search__copy {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  flex: 1;
}

.colleague-search__name {
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.colleague-search__dept {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.colleague-search__chevron {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

@keyframes colleague-search-spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
