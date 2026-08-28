<script setup>
//
// Autocomplete chọn nhiều người — cùng mẫu ProjectDepartmentPicker /
// ProjectUserPicker: mở danh sách khi focus, mũi tên / Enter, mục đã chọn
// hiện thành hàng (avatar + tên + phòng ban), không dùng pill.
//
import { computed, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  modelValue: { type: Array, required: true },
  users: { type: Array, required: true },
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: 'Tìm tên, email hoặc phòng ban' },
  emptyText: { type: String, default: '' },
  searchLabel: { type: String, default: 'Tìm người' },
  removeAriaLabel: { type: String, default: 'Bỏ người này' },
  preferredDepartmentIds: { type: Array, default: () => [] },
  tone: { type: String, default: 'primary' },
});

const emit = defineEmits(['update:modelValue']);

const selectedIds = computed(() => new Set(props.modelValue.map((id) => String(id))));
const query = ref('');
const open = ref(false);
const highlighted = ref(0);
const inputRef = ref(null);
const brokenAvatars = ref(new Set());

const preferredSet = computed(
  () => new Set((props.preferredDepartmentIds || []).map((id) => String(id))),
);

const selectedUsers = computed(() => {
  const byId = new Map(props.users.map((u) => [String(u.id), u]));
  return props.modelValue.map((id) => byId.get(String(id))).filter(Boolean);
});

const preferredCount = computed(() => {
  let n = 0;
  for (const u of matches.value) {
    if (u.department?.id && preferredSet.value.has(String(u.department.id))) n += 1;
    else break;
  }
  return n;
});

const matches = computed(() => {
  const q = query.value.trim().toLowerCase();
  const list = props.users.filter((u) => {
    if (selectedIds.value.has(String(u.id))) return false;
    if (!q) return true;
    const dept = (u.department?.name || '').toLowerCase();
    return (
      u.name.toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q) || dept.includes(q)
    );
  });

  const preferred = [];
  const rest = [];
  for (const u of list) {
    if (u.department?.id && preferredSet.value.has(String(u.department.id))) {
      preferred.push(u);
    } else {
      rest.push(u);
    }
  }
  const ordered = [...preferred, ...rest];
  if (q) return ordered;
  return ordered.slice(0, 40);
});

watch(matches, () => {
  highlighted.value = 0;
});

function pick(user) {
  if (!selectedIds.value.has(String(user.id))) {
    emit('update:modelValue', [...props.modelValue, user.id]);
  }
  query.value = '';
  open.value = true;
  highlighted.value = 0;
}

function remove(userId) {
  emit(
    'update:modelValue',
    props.modelValue.filter((id) => String(id) !== String(userId)),
  );
}

function onFocus() {
  if (props.disabled) return;
  open.value = true;
}

function onBlur() {
  open.value = false;
  highlighted.value = 0;
}

function onKeydown(event) {
  if (event.key === 'ArrowDown') {
    event.preventDefault();
    open.value = true;
    const n = matches.value.length;
    if (!n) return;
    highlighted.value = (highlighted.value + 1) % n;
    return;
  }
  if (event.key === 'ArrowUp') {
    event.preventDefault();
    open.value = true;
    const n = matches.value.length;
    if (!n) return;
    highlighted.value = (highlighted.value - 1 + n) % n;
    return;
  }
  if (event.key === 'Enter') {
    const item = matches.value[highlighted.value];
    if (open.value && item) {
      event.preventDefault();
      pick(item);
    }
    return;
  }
  if (event.key === 'Escape') {
    open.value = false;
  }
}

function initial(name) {
  return (name || '').trim().charAt(0).toUpperCase() || '?';
}

function hasPhoto(user) {
  return Boolean(user?.avatar_url) && !brokenAvatars.value.has(String(user.id));
}

function onAvatarError(userId) {
  const next = new Set(brokenAvatars.value);
  next.add(String(userId));
  brokenAvatars.value = next;
}

function showPreferredSplit(index) {
  return preferredCount.value > 0 && index === preferredCount.value && matches.value.length > preferredCount.value;
}
</script>

<template>
  <div class="proj-member-picker" :class="`proj-member-picker--${tone}`">
    <ul v-if="selectedUsers.length" class="proj-member-picker__picked hide-scrollbar">
      <li v-for="item in selectedUsers" :key="item.id" class="proj-member-picker__row">
        <span class="proj-member-picker__avatar" aria-hidden="true">
          <img
            v-if="hasPhoto(item)"
            :src="item.avatar_url"
            alt=""
            class="proj-member-picker__avatar-img"
            @error="onAvatarError(item.id)"
          />
          <template v-else>{{ initial(item.name) }}</template>
        </span>
        <span class="proj-member-picker__copy">
          <span class="proj-member-picker__name">{{ item.name }}</span>
          <span v-if="item.department?.name || item.email" class="proj-member-picker__meta">
            {{ item.department?.name || item.email }}
          </span>
        </span>
        <button
          type="button"
          class="proj-member-picker__remove"
          :aria-label="removeAriaLabel"
          :disabled="disabled"
          @click="remove(item.id)"
        >
          <AppIcon name="close" :size="12" :stroke-width="2.25" />
        </button>
      </li>
    </ul>
    <p v-else-if="emptyText" class="proj-member-picker__empty-state">{{ emptyText }}</p>

    <div class="proj-member-picker__search-wrap">
      <span class="proj-member-picker__search-icon" aria-hidden="true">
        <AppIcon name="search" :size="15" :stroke-width="1.75" />
      </span>
      <input
        ref="inputRef"
        v-model="query"
        type="search"
        class="proj-member-picker__search"
        :aria-label="searchLabel"
        :placeholder="placeholder"
        :disabled="disabled"
        autocomplete="off"
        @focus="onFocus"
        @blur="onBlur"
        @keydown="onKeydown"
      />
      <ul
        v-if="open && (matches.length || query.trim())"
        class="proj-member-picker__list hide-scrollbar"
        role="listbox"
        :aria-label="searchLabel"
      >
        <template v-for="(item, index) in matches" :key="item.id">
          <li v-if="index === 0 && preferredCount > 0" class="proj-member-picker__split" role="presentation">
            Phòng thực hiện
          </li>
          <li v-if="showPreferredSplit(index)" class="proj-member-picker__split" role="presentation">Khác</li>
          <li
            class="proj-member-picker__option"
            :class="{ 'proj-member-picker__option--on': index === highlighted }"
            role="option"
            :aria-selected="index === highlighted ? 'true' : 'false'"
            @mousedown.prevent="pick(item)"
          >
            <span class="proj-member-picker__option-avatar" aria-hidden="true">
              <img
                v-if="hasPhoto(item)"
                :src="item.avatar_url"
                alt=""
                class="proj-member-picker__avatar-img"
                @error="onAvatarError(item.id)"
              />
              <template v-else>{{ initial(item.name) }}</template>
            </span>
            <span class="proj-member-picker__option-copy">
              <span>{{ item.name }}</span>
              <span class="proj-member-picker__option-meta">
                {{ item.department?.name || item.email || '' }}
              </span>
            </span>
          </li>
        </template>
        <li v-if="matches.length === 0" class="proj-member-picker__empty">Không tìm thấy.</li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.proj-member-picker {
  --picker-accent: var(--color-primary);
  --picker-surface: var(--color-primary-surface);

  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-width: 0;
}

.proj-member-picker--secondary {
  --picker-accent: var(--color-secondary);
  --picker-surface: var(--color-secondary-surface);
}

.proj-member-picker--tertiary {
  --picker-accent: var(--color-tertiary);
  --picker-surface: var(--color-tertiary-surface);
}

.proj-member-picker--gold {
  --picker-accent: var(--color-gold-600);
  --picker-surface: var(--color-gold-surface);
}

.proj-member-picker__picked {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  max-height: 14rem;
  margin: 0;
  padding: 0;
  overflow-y: auto;
  list-style: none;
}

.proj-member-picker__row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-width: 0;
  padding: 0.5rem 0.5rem 0.5rem 0.75rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.proj-member-picker__avatar,
.proj-member-picker__option-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 1.75rem;
  height: 1.75rem;
  overflow: hidden;
  border-radius: var(--radius-full);
  background: var(--picker-surface);
  color: var(--picker-accent);
  font-size: 0.75rem;
  font-weight: 700;
}

.proj-member-picker__avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.proj-member-picker__copy,
.proj-member-picker__option-copy {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}

.proj-member-picker__name {
  overflow-wrap: anywhere;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.35;
}

.proj-member-picker__meta {
  overflow-wrap: anywhere;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.35;
}

.proj-member-picker__remove {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 1.5rem;
  height: 1.5rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.proj-member-picker__remove:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-danger);
}

.proj-member-picker__remove:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.proj-member-picker__empty-state {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-style: italic;
}

.proj-member-picker__search-wrap {
  position: relative;
}

.proj-member-picker__search-icon {
  position: absolute;
  top: 50%;
  left: 0.75rem;
  z-index: 1;
  display: inline-flex;
  color: var(--color-text-muted);
  transform: translateY(-50%);
  pointer-events: none;
}

.proj-member-picker__search {
  width: 100%;
  min-width: 0;
  padding: 0.5rem 0.75rem 0.5rem 2.25rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
}

.proj-member-picker__search::placeholder {
  color: var(--color-text-muted);
}

.proj-member-picker__search::-webkit-search-decoration,
.proj-member-picker__search::-webkit-search-cancel-button {
  -webkit-appearance: none;
}

.proj-member-picker__search:hover:not(:disabled) {
  border-color: var(--color-border-strong);
}

.proj-member-picker__search:focus {
  border-color: var(--color-primary-300);
  outline: 2px solid var(--color-primary-200);
  outline-offset: 1px;
}

.proj-member-picker__search:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.proj-member-picker__list {
  position: absolute;
  z-index: 20;
  top: calc(100% + 0.25rem);
  left: 0;
  right: 0;
  max-height: 14rem;
  overflow-y: auto;
  margin: 0;
  padding: var(--space-1);
  list-style: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-md, 0 8px 24px rgba(0, 0, 0, 0.12)), inset 0 0 0 1px var(--color-border);
}

.proj-member-picker__split {
  padding: 0.375rem 0.625rem 0.25rem;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.proj-member-picker__option {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.5rem 0.625rem;
  border-radius: var(--radius-sm);
  font-size: 0.8125rem;
  color: var(--color-text);
  cursor: pointer;
}

.proj-member-picker__option:hover,
.proj-member-picker__option--on {
  background: var(--picker-surface);
  color: var(--picker-accent);
}

.proj-member-picker__option-meta {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.proj-member-picker__empty {
  padding: 0.5rem 0.625rem;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}
</style>
