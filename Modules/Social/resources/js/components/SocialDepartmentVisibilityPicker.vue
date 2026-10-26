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
  { value: 'all', label: 'Tất cả phòng ban', detail: 'Mọi phòng ban đều thấy bài viết.', icon: 'globe' },
  { value: 'include', label: 'Chỉ vài phòng ban', detail: 'Chỉ phòng ban được chọn mới thấy.', icon: 'building' },
  { value: 'exclude', label: 'Trừ vài phòng ban', detail: 'Ẩn bài với phòng ban được chọn.', icon: 'eyeOff' },
];

const departments = ref([]);
const loading = ref(false);
const loaded = ref(false);
const search = ref('');
const draftMode = ref('all');
const draftIds = ref([]);
let previousBodyOverflow = '';

const needsDepartments = computed(() => draftMode.value !== 'all');
const selectedCount = computed(() => draftIds.value.length);

function deptMeta(department) {
  const hrm = String(department?.hrm_code ?? '').trim();
  const company = String(department?.company_code ?? '').trim();
  if (hrm && company) return `${hrm} · ${company}`;
  return hrm || company;
}

const filteredDepartments = computed(() => {
  const needle = search.value.trim().toLowerCase();
  if (!needle) return departments.value;
  return departments.value.filter((department) => {
    const haystack = [department.name, department.hrm_code, department.company_code, department.company_name]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();
    return haystack.includes(needle);
  });
});

const showCompanyHeadings = computed(() => {
  const names = new Set(
    filteredDepartments.value
      .map((department) => String(department.company_name || department.company_code || '').trim())
      .filter(Boolean),
  );
  return names.size > 1;
});

const departmentGroups = computed(() => {
  const groups = new Map();
  for (const department of filteredDepartments.value) {
    const label = String(department.company_name || department.company_code || 'Phòng ban').trim() || 'Phòng ban';
    if (!groups.has(label)) groups.set(label, []);
    groups.get(label).push(department);
  }
  return [...groups.entries()].map(([label, items]) => ({ label, items }));
});

const summaryText = computed(() => {
  if (draftMode.value === 'include') {
    return selectedCount.value > 0
      ? `Chỉ ${selectedCount.value} phòng ban được chọn thấy bài viết.`
      : 'Chọn phòng ban được thấy bài viết.';
  }
  if (draftMode.value === 'exclude') {
    return selectedCount.value > 0
      ? `Ẩn bài với ${selectedCount.value} phòng ban được chọn.`
      : 'Chọn phòng ban sẽ không thấy bài viết.';
  }
  return 'Mọi phòng ban đều thấy bài viết này.';
});

async function loadDepartments() {
  if (loaded.value) return;
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/social/departments');
    departments.value = Array.isArray(data?.departments) ? data.departments : [];
    loaded.value = departments.value.length > 0;
  } catch {
    showClientToast('error', 'Không tải được danh sách phòng ban HRM.');
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
  return draftIds.value.some((existing) => Number(existing) === Number(id));
}

function toggleDepartment(id) {
  const numericId = Number(id);
  draftIds.value = isChecked(numericId)
    ? draftIds.value.filter((existing) => Number(existing) !== numericId)
    : [...draftIds.value, numericId];
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
  emit('update:department-ids', draftMode.value === 'all' ? [] : draftIds.value.map((id) => Number(id)));
  emit('close');
}

function onKeydown(event) {
  if (event.key === 'Escape' && props.open) close();
}

function setBodyLock(locked) {
  if (typeof document === 'undefined') return;
  if (locked) {
    previousBodyOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return;
  }
  document.body.style.overflow = previousBodyOverflow;
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      draftMode.value = props.mode;
      draftIds.value = props.departmentIds.map((id) => Number(id));
      search.value = '';
      loadDepartments();
      document.addEventListener('keydown', onKeydown);
      setBodyLock(true);
      return;
    }
    document.removeEventListener('keydown', onKeydown);
    setBodyLock(false);
  },
  { immediate: true },
);

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown);
  setBodyLock(false);
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
          :class="{ 'dept-picker__panel--pick': needsDepartments }"
          role="dialog"
          aria-modal="true"
          aria-labelledby="dept-picker-title"
        >
          <div class="dept-picker__head">
            <span class="dept-picker__icon" aria-hidden="true">
              <AppIcon name="users" :size="18" :stroke-width="1.75" />
            </span>
            <div class="dept-picker__head-copy">
              <h2 id="dept-picker-title" class="dept-picker__title">Ai được thấy bài viết này?</h2>
              <p class="dept-picker__subtitle">{{ summaryText }}</p>
            </div>
            <button type="button" class="dept-picker__close" aria-label="Đóng" @click="close">
              <AppIcon name="close" :size="16" />
            </button>
          </div>

          <div class="dept-picker__body hide-scrollbar">
            <div class="dept-picker__modes" role="radiogroup" aria-label="Cách chọn phòng ban">
              <button
                v-for="item in MODES"
                :key="item.value"
                type="button"
                class="dept-picker__mode"
                :class="{ 'dept-picker__mode--on': draftMode === item.value }"
                role="radio"
                :aria-checked="draftMode === item.value ? 'true' : 'false'"
                @click="setMode(item.value)"
              >
                <span class="dept-picker__mode-icon" aria-hidden="true">
                  <AppIcon :name="item.icon" :size="16" :stroke-width="1.75" />
                </span>
                <span class="dept-picker__mode-copy">
                  <span class="dept-picker__mode-label">{{ item.label }}</span>
                  <span class="dept-picker__mode-detail">{{ item.detail }}</span>
                </span>
              </button>
            </div>

            <div v-if="needsDepartments" class="dept-picker__depts">
              <div class="dept-picker__toolbar">
                <input
                  v-model="search"
                  type="search"
                  class="dept-picker__search"
                  placeholder="Tìm theo tên, mã HRM hoặc công ty"
                  aria-label="Tìm phòng ban"
                  autocomplete="off"
                />
                <p class="dept-picker__count">
                  {{ selectedCount }} / {{ departments.length }}
                </p>
              </div>

              <div class="dept-picker__list hide-scrollbar">
                <p v-if="loading" class="dept-picker__empty">Đang tải phòng ban HRM...</p>
                <p v-else-if="departments.length === 0" class="dept-picker__empty">
                  Chưa có phòng ban HRM.
                </p>
                <p v-else-if="filteredDepartments.length === 0" class="dept-picker__empty">
                  Không tìm thấy phòng ban phù hợp.
                </p>
                <template v-else>
                <section
                  v-for="group in departmentGroups"
                  :key="group.label"
                  class="dept-picker__group"
                >
                  <h3 v-if="showCompanyHeadings" class="dept-picker__group-title">{{ group.label }}</h3>
                  <div class="dept-picker__grid">
                    <label
                      v-for="dept in group.items"
                      :key="dept.id"
                      class="dept-picker__row"
                      :class="{ 'dept-picker__row--on': isChecked(dept.id) }"
                    >
                      <input
                        type="checkbox"
                        class="dept-picker__checkbox"
                        :checked="isChecked(dept.id)"
                        @change="toggleDepartment(dept.id)"
                      />
                      <span class="dept-picker__row-copy">
                        <span class="dept-picker__row-name">{{ dept.name }}</span>
                        <span v-if="deptMeta(dept)" class="dept-picker__row-meta">{{ deptMeta(dept) }}</span>
                      </span>
                    </label>
                  </div>
                </section>
                </template>
              </div>
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
  z-index: 400;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.dept-picker__panel {
  width: min(52rem, calc(100vw - 2.5rem));
  max-height: calc(100vh - 2.5rem);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.dept-picker__panel--pick {
  width: min(72rem, calc(100vw - 2.5rem));
  height: calc(100vh - 2.5rem);
}

.dept-picker__head,
.dept-picker__actions {
  flex-shrink: 0;
}

.dept-picker__head {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-4) var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
}

.dept-picker__icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-md);
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
  font-size: 1.0625rem;
  font-weight: 700;
  line-height: 1.3;
}

.dept-picker__subtitle {
  margin: 0.15rem 0 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.35;
}

.dept-picker__close {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
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
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4);
  overflow: hidden;
}

.dept-picker__modes {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-2);
  flex-shrink: 0;
}

.dept-picker__mode {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  width: 100%;
  min-width: 0;
  min-height: 4.75rem;
  margin: 0;
  padding: var(--space-3);
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text);
  text-align: left;
  font-family: inherit;
  cursor: pointer;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.dept-picker__mode:hover {
  background: var(--color-surface);
}

.dept-picker__mode--on {
  background: var(--color-primary-surface);
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1px var(--color-primary);
}

.dept-picker__mode-icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text-muted);
}

.dept-picker__mode--on .dept-picker__mode-icon {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.dept-picker__mode-copy {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 0;
}

.dept-picker__mode-label {
  font-size: 0.8125rem;
  font-weight: 700;
  line-height: 1.3;
}

.dept-picker__mode-detail {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 500;
  line-height: 1.35;
}

.dept-picker__mode--on .dept-picker__mode-detail {
  color: color-mix(in srgb, var(--color-primary) 72%, var(--color-text));
}

.dept-picker__depts {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  overflow: hidden;
}

.dept-picker__toolbar {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex-shrink: 0;
}

.dept-picker__search {
  flex: 1;
  min-width: 0;
  width: 100%;
  margin: 0;
  padding: 0.5rem 0.75rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.8125rem;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.dept-picker__search:focus {
  outline: none;
  box-shadow:
    inset 0 0 0 1px var(--color-primary),
    0 0 0 3px color-mix(in srgb, var(--color-primary) 16%, transparent);
}

.dept-picker__count {
  margin: 0;
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 700;
  white-space: nowrap;
}

.dept-picker__list {
  flex: 1;
  min-height: 0;
  overflow: auto;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: 2px;
}

.dept-picker__group {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-width: 0;
}

.dept-picker__group-title {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 700;
  line-height: 1.3;
}

.dept-picker__grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-2);
  align-content: start;
}

.dept-picker__row {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  min-width: 0;
  margin: 0;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text);
  cursor: pointer;
  box-shadow: inset 0 0 0 1px transparent;
}

.dept-picker__row:hover {
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.dept-picker__row--on {
  background: var(--color-primary-surface);
  box-shadow: inset 0 0 0 1px var(--color-primary);
}

.dept-picker__checkbox {
  width: 1rem;
  height: 1rem;
  margin: 0.15rem 0 0;
  accent-color: var(--color-primary);
  flex-shrink: 0;
}

.dept-picker__row-copy {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  min-width: 0;
}

.dept-picker__row-name {
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.35;
  overflow-wrap: anywhere;
}

.dept-picker__row-meta {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-style: italic;
  font-weight: 500;
  line-height: 1.3;
  overflow-wrap: anywhere;
}

.dept-picker__empty {
  margin: 0;
  padding: var(--space-4) var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  text-align: center;
}

.dept-picker__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 -1px 0 var(--color-border);
}

.dept-picker__btn {
  width: auto;
  margin: 0;
  padding: 0.45rem 0.9rem;
  border: none;
  border-radius: var(--radius-md);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.dept-picker__btn--ghost {
  background: var(--color-surface);
  color: var(--color-text-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.dept-picker__btn--ghost:hover {
  background: var(--color-surface-muted);
}

.dept-picker__btn--primary {
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

@media (max-width: 900px) {
  .dept-picker__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 720px) {
  .dept-picker {
    padding: var(--space-3);
  }

  .dept-picker__panel,
  .dept-picker__panel--pick {
    width: min(100%, calc(100vw - 1.5rem));
    max-height: calc(100dvh - 1.5rem);
  }

  .dept-picker__panel--pick {
    height: calc(100dvh - 1.5rem);
  }

  .dept-picker__modes,
  .dept-picker__grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .dept-picker__mode {
    min-height: 0;
    align-items: center;
  }

  .dept-picker__toolbar {
    align-items: stretch;
  }
}

@media (prefers-reduced-motion: reduce) {
  .dept-picker-fade-enter-active,
  .dept-picker-fade-leave-active {
    transition: none;
  }
}
</style>
