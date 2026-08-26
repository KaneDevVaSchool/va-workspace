<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';

const props = defineProps({
  open: { type: Boolean, default: false },
  mode: { type: String, default: 'all' }, // 'all' | 'include' | 'exclude'
  departmentIds: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'update:mode', 'update:department-ids']);

const MODES = [
  { value: 'all', label: 'Tất cả phòng ban', detail: 'Mọi phòng ban đều thấy bài viết.' },
  { value: 'include', label: 'Chỉ vài phòng ban', detail: 'Chỉ phòng ban được chọn mới thấy.' },
  { value: 'exclude', label: 'Trừ vài phòng ban', detail: 'Ẩn bài với phòng ban được chọn.' },
];

const departments = ref([]);
const loading = ref(false);
const search = ref('');
const draftMode = ref('all');
const draftIds = ref([]);

const filteredDepartments = computed(() => {
  const needle = search.value.trim().toLowerCase();
  if (!needle) return departments.value;
  return departments.value.filter((d) => d.name.toLowerCase().includes(needle));
});

const selectedCount = computed(() => draftIds.value.length);
const needsDepartments = computed(() => draftMode.value !== 'all');

async function loadDepartments() {
  if (departments.value.length > 0) return;
  loading.value = true;
  try {
    const { data } = await window.axios.get('/manager/departments');
    departments.value = data.departments ?? [];
  } catch {
    showClientToast('error', 'Không tải được danh sách phòng ban.');
  } finally {
    loading.value = false;
  }
}

function setMode(nextMode) {
  draftMode.value = nextMode;
  if (nextMode === 'all') {
    draftIds.value = [];
    search.value = '';
  }
}

function isChecked(id) {
  return draftIds.value.includes(id);
}

function toggleDepartment(id) {
  draftIds.value = isChecked(id)
    ? draftIds.value.filter((existing) => existing !== id)
    : [...draftIds.value, id];
}

function close() {
  emit('close');
}

function apply() {
  if (needsDepartments.value && draftIds.value.length === 0) {
    showClientToast('error', 'Chọn ít nhất 1 phòng ban.');
    return;
  }
  emit('update:mode', draftMode.value);
  emit('update:department-ids', draftMode.value === 'all' ? [] : [...draftIds.value]);
  emit('close');
}

function onKeydown(event) {
  if (event.key === 'Escape' && props.open) close();
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      draftMode.value = props.mode;
      draftIds.value = [...props.departmentIds];
      search.value = '';
      loadDepartments();
      document.addEventListener('keydown', onKeydown);
      return;
    }
    document.removeEventListener('keydown', onKeydown);
  },
  { immediate: true },
);

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
  <Teleport to="body">
    <Transition name="dept-picker-fade">
      <div
        v-if="open"
        class="dept-picker"
        role="presentation"
        @mousedown.self="close"
      >
        <div
          class="dept-picker__panel"
          role="dialog"
          aria-modal="true"
          aria-labelledby="dept-picker-title"
        >
          <div class="dept-picker__head">
            <span class="dept-picker__icon" aria-hidden="true">
              <AppIcon name="users" :size="16" :stroke-width="1.75" />
            </span>
            <div class="dept-picker__head-copy">
              <h2 id="dept-picker-title" class="dept-picker__title">Ai được thấy bài viết này?</h2>
            </div>
            <button type="button" class="dept-picker__close" aria-label="Đóng" @click="close">
              <AppIcon name="close" :size="15" />
            </button>
          </div>

          <div class="dept-picker__body hide-scrollbar">
            <div class="dept-picker__modes" role="radiogroup" aria-label="Cách chọn phòng ban">
              <label
                v-for="item in MODES"
                :key="item.value"
                class="dept-picker__mode"
                :class="{ 'dept-picker__mode--on': draftMode === item.value }"
              >
                <input
                  class="dept-picker__mode-input"
                  type="radio"
                  name="dept-visibility-mode"
                  :checked="draftMode === item.value"
                  @change="setMode(item.value)"
                />
                <span class="dept-picker__mode-copy">
                  <span class="dept-picker__mode-label">{{ item.label }}</span>
                  <span class="dept-picker__mode-detail">{{ item.detail }}</span>
                </span>
              </label>
            </div>

            <div class="dept-picker__depts">
              <template v-if="needsDepartments">
                <div class="dept-picker__depts-toolbar">
                  <input
                    v-model="search"
                    type="search"
                    class="dept-picker__search"
                    placeholder="Tìm phòng ban..."
                    aria-label="Tìm phòng ban"
                    autocomplete="off"
                  />
                  <p v-if="selectedCount > 0" class="dept-picker__summary">
                    Đã chọn {{ selectedCount }}
                  </p>
                </div>

                <div class="dept-picker__list hide-scrollbar">
                  <p v-if="loading" class="dept-picker__empty">Đang tải danh sách phòng ban...</p>
                  <label
                    v-for="dept in filteredDepartments"
                    v-else
                    :key="dept.id"
                    class="dept-picker__row"
                  >
                    <input
                      type="checkbox"
                      class="dept-picker__checkbox"
                      :checked="isChecked(dept.id)"
                      @change="toggleDepartment(dept.id)"
                    />
                    <span class="dept-picker__row-name">{{ dept.name }}</span>
                  </label>
                  <p v-if="!loading && filteredDepartments.length === 0" class="dept-picker__empty">
                    Không tìm thấy phòng ban phù hợp.
                  </p>
                </div>
              </template>
              <p v-else class="dept-picker__empty dept-picker__empty--panel">
                Mọi phòng ban đều thấy bài viết này.
              </p>
            </div>
          </div>

          <div class="dept-picker__actions">
            <button type="button" class="dept-picker__btn dept-picker__btn--ghost" @click="close">
              Huỷ
            </button>
            <button type="button" class="dept-picker__btn dept-picker__btn--primary" @click="apply">
              Xong
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.dept-picker {
  position: fixed;
  inset: 0;
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.dept-picker__panel {
  width: min(48rem, calc(100vw - 2.5rem));
  height: min(36rem, calc(100vh - 2.5rem));
  max-height: calc(100vh - 2.5rem);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.dept-picker__head,
.dept-picker__actions {
  flex-shrink: 0;
}

.dept-picker__head {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.7rem 0.9rem;
  box-shadow: 0 1px 0 var(--color-border);
}

.dept-picker__icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.85rem;
  height: 1.85rem;
  border-radius: var(--radius-sm);
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 15%, transparent);
}

.dept-picker__head-copy {
  flex: 1;
  min-width: 0;
}

.dept-picker__title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 700;
  line-height: 1.25;
}

.dept-picker__close {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.6rem;
  height: 1.6rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.dept-picker__close:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.dept-picker__body {
  flex: 1;
  min-width: 0;
  min-height: 0;
  overflow: hidden;
  display: grid;
  grid-template-columns: minmax(14rem, 16.5rem) minmax(0, 1fr);
  gap: var(--space-3);
  padding: 0.85rem 0.9rem;
}

.dept-picker__modes {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-width: 0;
}

.dept-picker__mode {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text);
  cursor: pointer;
}

.dept-picker__mode--on {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.dept-picker__mode-input {
  margin-top: 0.15rem;
  flex-shrink: 0;
  accent-color: var(--color-primary);
}

.dept-picker__mode-copy {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 0;
}

.dept-picker__mode-label {
  font-size: 0.8125rem;
  font-weight: 600;
}

.dept-picker__mode-detail {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 500;
  line-height: 1.35;
}

.dept-picker__mode--on .dept-picker__mode-detail {
  color: color-mix(in srgb, var(--color-primary) 75%, var(--color-text-muted));
}

.dept-picker__depts {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-width: 0;
  min-height: 0;
  overflow: hidden;
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.dept-picker__depts-toolbar {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex-shrink: 0;
}

.dept-picker__search {
  flex: 1;
  min-width: 0;
  padding: var(--space-2) var(--space-3);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.8125rem;
}

.dept-picker__search:focus {
  outline: 2px solid color-mix(in srgb, var(--color-primary) 35%, transparent);
  outline-offset: 1px;
  border-color: var(--color-primary);
}

.dept-picker__summary {
  margin: 0;
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
}

.dept-picker__list {
  flex: 1;
  min-height: 0;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  align-content: start;
  gap: 0.125rem var(--space-2);
  overflow-y: auto;
}

.dept-picker__row {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  min-width: 0;
  padding: var(--space-2);
  border-radius: var(--radius-md);
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.dept-picker__row:hover {
  background: var(--color-surface);
}

.dept-picker__checkbox {
  margin-top: 0.15rem;
  accent-color: var(--color-primary);
  flex-shrink: 0;
}

.dept-picker__row-name {
  min-width: 0;
  line-height: 1.35;
  overflow-wrap: anywhere;
}

.dept-picker__empty {
  margin: 0;
  padding: var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  text-align: center;
  grid-column: 1 / -1;
}

.dept-picker__empty--panel {
  margin: auto;
}

.dept-picker__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  padding: 0.65rem 0.9rem;
  box-shadow: 0 -1px 0 var(--color-border);
}

.dept-picker__btn {
  padding: 0.4rem 0.85rem;
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.dept-picker__btn--ghost {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text-muted);
}

.dept-picker__btn--ghost:hover {
  background: var(--color-surface-muted);
}

.dept-picker__btn--primary {
  border-color: var(--color-primary);
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.dept-picker__btn--primary:hover {
  background: var(--color-primary-hover);
}

.dept-picker-fade-enter-active,
.dept-picker-fade-leave-active {
  transition: opacity 0.15s ease;
}

.dept-picker-fade-enter-from,
.dept-picker-fade-leave-to {
  opacity: 0;
}

@media (max-width: 768px) {
  .dept-picker__body {
    grid-template-columns: minmax(0, 1fr);
    grid-template-rows: auto minmax(0, 1fr);
  }

  .dept-picker__modes {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: var(--space-1);
  }

  .dept-picker__mode {
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: var(--space-2);
  }

  .dept-picker__mode-detail {
    display: none;
  }

  .dept-picker__list {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 640px) {
  .dept-picker__panel {
    width: calc(100vw - 2.5rem);
    height: calc(100vh - 2.5rem);
  }

  .dept-picker__depts-toolbar {
    flex-direction: column;
    align-items: stretch;
  }

  .dept-picker__summary {
    white-space: normal;
  }
}

@media (prefers-reduced-motion: reduce) {
  .dept-picker-fade-enter-active,
  .dept-picker-fade-leave-active {
    transition: none;
  }
}
</style>
