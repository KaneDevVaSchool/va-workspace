<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';

const props = defineProps({
  open: { type: Boolean, required: true },
  postId: { type: Number, default: null },
  departmentName: { type: String, default: '' },
  defaultScope: { type: String, default: 'company' },
  defaultWallUser: { type: Object, default: null },
  defaultGroup: { type: Object, default: null },
});

const emit = defineEmits(['close', 'shared']);

const auth = useAuthStore();
const destination = ref('company');
const caption = ref('');
const submitting = ref(false);
const query = ref('');
const results = ref([]);
const searching = ref(false);
const selectedUser = ref(null);
const groupQuery = ref('');
const groupResults = ref([]);
const searchingGroups = ref(false);
const selectedGroup = ref(null);
let searchTimer = null;
let groupSearchTimer = null;

const hasDepartment = computed(() => Boolean(auth.user?.department?.id && props.departmentName));
const isOwnDefaultWall = computed(() => {
  if (!props.defaultWallUser?.id) return true;
  return props.defaultWallUser.id === auth.user?.id;
});

const destinations = computed(() => {
  const items = [
    { id: 'company', icon: 'megaphone', label: 'Bảng tin chung' },
  ];
  if (hasDepartment.value) {
    items.push({
      id: 'department',
      icon: 'building',
      label: `Tường phòng ${props.departmentName}`,
    });
  }
  items.push({
    id: 'personal',
    icon: 'user',
    label: 'Tường của tôi',
  });
  items.push({
    id: 'group',
    icon: 'users',
    label: 'Nhóm',
  });
  items.push({
    id: 'other',
    icon: 'users',
    label: 'Tường người khác',
  });
  return items;
});

function resetState() {
  caption.value = '';
  query.value = '';
  results.value = [];
  selectedUser.value = null;
  groupQuery.value = '';
  groupResults.value = [];
  selectedGroup.value = null;
  if (props.defaultScope === 'department' && hasDepartment.value) {
    destination.value = 'department';
    return;
  }
  if (props.defaultScope === 'personal' && !isOwnDefaultWall.value && props.defaultWallUser) {
    destination.value = 'other';
    selectedUser.value = props.defaultWallUser;
    return;
  }
  if (props.defaultScope === 'personal') {
    destination.value = 'personal';
    return;
  }
  if (props.defaultScope === 'group') {
    destination.value = 'group';
    selectedGroup.value = props.defaultGroup;
    return;
  }
  destination.value = 'company';
}

function close() {
  if (submitting.value) return;
  emit('close');
}

function onKey(event) {
  if (event.key === 'Escape') {
    event.preventDefault();
    close();
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

function pickUser(user) {
  selectedUser.value = user;
  query.value = '';
  results.value = [];
}

function clearSelectedUser() {
  selectedUser.value = null;
}

function pickGroup(group) {
  selectedGroup.value = group;
  groupQuery.value = '';
  searchGroups('');
}

function clearSelectedGroup() {
  selectedGroup.value = null;
}

function groupInitial(group) {
  return (group?.name || '?').trim().charAt(0).toUpperCase() || '?';
}

async function searchUsers(needle) {
  searching.value = true;
  try {
    const { data } = await window.axios.get('/api/social/mentions', { params: { q: needle } });
    results.value = (data.users ?? []).filter((user) => user.id !== selectedUser.value?.id);
  } catch {
    results.value = [];
  } finally {
    searching.value = false;
  }
}

async function searchGroups(needle) {
  searchingGroups.value = true;
  try {
    const { data } = await window.axios.get('/api/social/groups', {
      params: { tab: 'mine', per_page: 30, q: needle || undefined },
    });
    groupResults.value = (data.groups ?? []).filter((group) => group.id !== selectedGroup.value?.id);
  } catch {
    groupResults.value = [];
  } finally {
    searchingGroups.value = false;
  }
}

function onQueryInput(event) {
  query.value = event.target.value;
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => searchUsers(query.value.trim()), 200);
}

function onGroupQueryInput(event) {
  groupQuery.value = event.target.value;
  clearTimeout(groupSearchTimer);
  groupSearchTimer = setTimeout(() => searchGroups(groupQuery.value.trim()), 200);
}

function setDestination(id) {
  destination.value = id;
  if (id === 'group') {
    searchGroups(groupQuery.value.trim());
  }
}

function payload() {
  if (destination.value === 'other') {
    return {
      post_scope: 'personal',
      wall_user_id: selectedUser.value?.id,
      caption: caption.value.trim() || undefined,
    };
  }
  if (destination.value === 'personal') {
    return {
      post_scope: 'personal',
      wall_user_id: auth.user?.id,
      caption: caption.value.trim() || undefined,
    };
  }
  if (destination.value === 'group') {
    return {
      post_scope: 'group',
      group_id: selectedGroup.value?.id,
      caption: caption.value.trim() || undefined,
    };
  }
  return {
    post_scope: destination.value,
    caption: caption.value.trim() || undefined,
  };
}

function successMessage() {
  if (destination.value === 'department') return 'Đã chia sẻ bài viết lên tường phòng ban.';
  if (destination.value === 'personal') return 'Đã chia sẻ bài viết lên tường của bạn.';
  if (destination.value === 'other') {
    return `Đã chia sẻ bài viết lên tường của ${selectedUser.value?.name ?? 'đồng nghiệp'}.`;
  }
  if (destination.value === 'group') {
    return `Đã chia sẻ bài viết lên nhóm "${selectedGroup.value?.name ?? ''}".`;
  }
  return 'Đã chia sẻ bài viết lên bảng tin chung.';
}

async function submit() {
  if (submitting.value || !props.postId) return;
  if (destination.value === 'other' && !selectedUser.value?.id) {
    showClientToast('error', 'Hãy chọn người để chia sẻ lên tường của họ.');
    return;
  }
  if (destination.value === 'group' && !selectedGroup.value?.id) {
    showClientToast('error', 'Hãy chọn nhóm để chia sẻ.');
    return;
  }

  submitting.value = true;
  try {
    const { data } = await window.axios.post(`/api/social/posts/${props.postId}/share`, payload());
    emit('shared', data.post);
    showClientToast('success', successMessage());
    emit('close');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không thể chia sẻ bài viết.');
  } finally {
    submitting.value = false;
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      resetState();
      bindPage();
      if (destination.value === 'group') {
        searchGroups('');
      }
      return;
    }
    unbindPage();
    clearTimeout(searchTimer);
    clearTimeout(groupSearchTimer);
  },
);

onBeforeUnmount(() => {
  unbindPage();
  clearTimeout(searchTimer);
  clearTimeout(groupSearchTimer);
});
</script>

<template>
  <Teleport to="body">
    <Transition name="share-dialog-fade">
      <div
        v-if="open"
        class="share-dialog"
        role="presentation"
        @mousedown.self="close"
      >
        <div
          class="share-dialog__panel"
          role="dialog"
          aria-modal="true"
          aria-label="Chia sẻ bài viết"
        >
          <header class="share-dialog__header">
            <h2 class="share-dialog__title">Chia sẻ bài viết</h2>
            <button type="button" class="share-dialog__close" aria-label="Đóng" @click="close">
              <AppIcon name="close" :size="18" />
            </button>
          </header>

          <div class="share-dialog__body hide-scrollbar">
            <p class="share-dialog__label">Chia sẻ tới</p>
            <div class="share-dialog__destinations" role="radiogroup" aria-label="Nơi chia sẻ">
              <button
                v-for="item in destinations"
                :key="item.id"
                type="button"
                class="share-dialog__dest"
                :class="{ 'share-dialog__dest--active': destination === item.id }"
                role="radio"
                :aria-checked="destination === item.id"
                @click="setDestination(item.id)"
              >
                <span class="share-dialog__dest-icon" aria-hidden="true">
                  <AppIcon :name="item.icon" :size="16" />
                </span>
                <span class="share-dialog__dest-label">{{ item.label }}</span>
              </button>
            </div>

            <div v-if="destination === 'other'" class="share-dialog__people">
              <div v-if="selectedUser" class="share-dialog__picked">
                <img
                  v-if="selectedUser.avatar_url"
                  class="share-dialog__avatar"
                  :src="selectedUser.avatar_url"
                  :alt="`Ảnh đại diện của ${selectedUser.name}`"
                />
                <span v-else class="share-dialog__avatar share-dialog__avatar--placeholder">
                  {{ selectedUser.name?.charAt(0) ?? '?' }}
                </span>
                <span class="share-dialog__picked-name">{{ selectedUser.name }}</span>
                <button type="button" class="share-dialog__picked-clear" aria-label="Bỏ chọn" @click="clearSelectedUser">
                  <AppIcon name="close" :size="14" />
                </button>
              </div>

              <label class="share-dialog__search">
                <AppIcon name="search" :size="16" />
                <input
                  :value="query"
                  type="search"
                  placeholder="Tìm đồng nghiệp..."
                  aria-label="Tìm đồng nghiệp để chia sẻ lên tường"
                  @input="onQueryInput"
                />
              </label>

              <p v-if="searching" class="share-dialog__empty">Đang tìm...</p>
              <ul v-else-if="results.length > 0" class="share-dialog__results">
                <li v-for="user in results" :key="user.id">
                  <button type="button" class="share-dialog__person" @click="pickUser(user)">
                    <img
                      v-if="user.avatar_url"
                      class="share-dialog__avatar"
                      :src="user.avatar_url"
                      :alt="`Ảnh đại diện của ${user.name}`"
                    />
                    <span v-else class="share-dialog__avatar share-dialog__avatar--placeholder">
                      {{ user.name?.charAt(0) ?? '?' }}
                    </span>
                    <span class="share-dialog__person-copy">
                      <span class="share-dialog__person-name">{{ user.name }}</span>
                      <span v-if="user.department" class="share-dialog__person-meta">{{ user.department }}</span>
                    </span>
                  </button>
                </li>
              </ul>
              <p v-else-if="query.trim()" class="share-dialog__empty">
                Không tìm thấy đồng nghiệp khớp “{{ query.trim() }}”.
              </p>
            </div>

            <div v-if="destination === 'group'" class="share-dialog__people">
              <div v-if="selectedGroup" class="share-dialog__picked">
                <img
                  v-if="selectedGroup.avatar_url"
                  class="share-dialog__avatar"
                  :src="selectedGroup.avatar_url"
                  :alt="`Ảnh đại diện nhóm ${selectedGroup.name}`"
                />
                <span v-else class="share-dialog__avatar share-dialog__avatar--placeholder">
                  {{ groupInitial(selectedGroup) }}
                </span>
                <span class="share-dialog__picked-name">{{ selectedGroup.name }}</span>
                <button type="button" class="share-dialog__picked-clear" aria-label="Bỏ chọn nhóm" @click="clearSelectedGroup">
                  <AppIcon name="close" :size="14" />
                </button>
              </div>

              <label class="share-dialog__search">
                <AppIcon name="search" :size="16" />
                <input
                  :value="groupQuery"
                  type="search"
                  placeholder="Tìm nhóm của bạn..."
                  aria-label="Tìm nhóm để chia sẻ"
                  @input="onGroupQueryInput"
                />
              </label>

              <p v-if="searchingGroups" class="share-dialog__empty">Đang tìm...</p>
              <ul v-else-if="groupResults.length > 0" class="share-dialog__results">
                <li v-for="group in groupResults" :key="group.id">
                  <button type="button" class="share-dialog__person" @click="pickGroup(group)">
                    <img
                      v-if="group.avatar_url"
                      class="share-dialog__avatar"
                      :src="group.avatar_url"
                      :alt="`Ảnh đại diện nhóm ${group.name}`"
                    />
                    <span v-else class="share-dialog__avatar share-dialog__avatar--placeholder">
                      {{ groupInitial(group) }}
                    </span>
                    <span class="share-dialog__person-copy">
                      <span class="share-dialog__person-name">{{ group.name }}</span>
                      <span class="share-dialog__person-meta">
                        {{ group.visibility === 'private' ? 'Nhóm bảo mật' : 'Nhóm công khai' }}
                        · {{ group.members_count }} thành viên
                      </span>
                    </span>
                  </button>
                </li>
              </ul>
              <p v-else-if="groupQuery.trim()" class="share-dialog__empty">
                Không tìm thấy nhóm khớp “{{ groupQuery.trim() }}”.
              </p>
              <p v-else-if="!selectedGroup" class="share-dialog__empty">
                Bạn chưa tham gia nhóm nào.
              </p>
            </div>

            <label class="share-dialog__caption">
              <span class="share-dialog__label">Lời nhắn (tuỳ chọn)</span>
              <textarea
                v-model="caption"
                rows="3"
                maxlength="5000"
                placeholder="Viết gì đó về bài viết này..."
              />
            </label>
          </div>

          <div class="share-dialog__actions">
            <button type="button" class="share-dialog__btn share-dialog__btn--ghost" :disabled="submitting" @click="close">
              Huỷ
            </button>
            <button type="button" class="share-dialog__btn" :disabled="submitting" @click="submit">
              {{ submitting ? 'Đang chia sẻ...' : 'Chia sẻ' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.share-dialog {
  position: fixed;
  inset: 0;
  z-index: 310;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
  background: color-mix(in srgb, #000000 45%, transparent);
}

.share-dialog__panel {
  width: min(32rem, calc(100vw - 2.5rem));
  max-height: calc(100vh - 2.5rem);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.share-dialog__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  flex-shrink: 0;
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.share-dialog__title {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 700;
  color: var(--color-text);
}

.share-dialog__close {
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

.share-dialog__close:hover {
  background: var(--color-border);
}

.share-dialog__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: var(--space-4);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.share-dialog__label {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--color-text-muted);
}

.share-dialog__destinations {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.share-dialog__dest {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  text-align: left;
  border: none;
  background: none;
  padding: var(--space-2);
  border-radius: var(--radius-md);
  cursor: pointer;
  font-family: inherit;
  color: var(--color-text);
}

.share-dialog__dest:hover {
  background: var(--color-surface-muted);
}

.share-dialog__dest--active {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.share-dialog__dest-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  flex-shrink: 0;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.share-dialog__dest--active .share-dialog__dest-icon {
  background: color-mix(in srgb, var(--color-primary) 14%, var(--color-surface));
  color: var(--color-primary);
}

.share-dialog__dest-label {
  min-width: 0;
  font-size: 0.875rem;
  font-weight: 700;
}

.share-dialog__people {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.share-dialog__search {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.share-dialog__search input {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.875rem;
  outline: none;
}

.share-dialog__picked,
.share-dialog__person {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  text-align: left;
  padding: var(--space-2);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.share-dialog__person {
  border: none;
  cursor: pointer;
  font-family: inherit;
  color: var(--color-text);
}

.share-dialog__person:hover {
  background: color-mix(in srgb, var(--color-primary) 8%, var(--color-surface-muted));
}

.share-dialog__avatar {
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
}

.share-dialog__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-weight: 700;
  font-size: 0.8125rem;
}

.share-dialog__picked-name,
.share-dialog__person-name {
  font-size: 0.875rem;
  font-weight: 700;
}

.share-dialog__picked-name {
  flex: 1;
  min-width: 0;
}

.share-dialog__picked-clear {
  display: flex;
  border: none;
  background: none;
  color: var(--color-text-muted);
  cursor: pointer;
}

.share-dialog__person-copy {
  display: flex;
  min-width: 0;
  flex-direction: column;
}

.share-dialog__person-meta {
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.share-dialog__results {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
  max-height: 12rem;
  overflow-y: auto;
}

.share-dialog__empty {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.share-dialog__caption {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.share-dialog__caption textarea {
  width: 100%;
  resize: vertical;
  min-height: 4.5rem;
  padding: var(--space-2) var(--space-3);
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.875rem;
  outline: none;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.share-dialog__caption textarea:focus {
  box-shadow: inset 0 0 0 1px var(--color-primary);
}

.share-dialog__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  flex-shrink: 0;
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 -1px 0 var(--color-border);
}

.share-dialog__btn {
  border: none;
  background: var(--color-primary);
  color: var(--color-on-primary);
  padding: var(--space-2) var(--space-4);
  border-radius: var(--radius-md);
  font-family: inherit;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.share-dialog__btn:hover {
  background: var(--color-primary-hover);
}

.share-dialog__btn--ghost {
  background: none;
  color: var(--color-text);
}

.share-dialog__btn--ghost:hover {
  background: var(--color-surface-muted);
}

.share-dialog__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.share-dialog-fade-enter-active,
.share-dialog-fade-leave-active {
  transition: opacity 0.15s ease;
}

.share-dialog-fade-enter-from,
.share-dialog-fade-leave-to {
  opacity: 0;
}
</style>
