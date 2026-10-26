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
    {
      id: 'company',
      icon: 'megaphone',
      label: 'Bảng tin chung',
      hint: 'Mọi người trong workspace',
    },
  ];
  if (hasDepartment.value) {
    items.push({
      id: 'department',
      icon: 'building',
      label: 'Tường phòng ban',
      hint: props.departmentName,
    });
  }
  items.push({
    id: 'personal',
    icon: 'user',
    label: 'Tường của tôi',
    hint: 'Chỉ trên trang cá nhân',
  });
  items.push({
    id: 'group',
    icon: 'users',
    label: 'Nhóm',
    hint: 'Nhóm bạn đã tham gia',
  });
  items.push({
    id: 'other',
    icon: 'userPlus',
    label: 'Tường đồng nghiệp',
    hint: 'Chọn người cụ thể',
  });
  return items;
});

function companyLabel(user) {
  const name = user?.company_name;
  return typeof name === 'string' ? name.trim() : '';
}

function personMeta(user) {
  const parts = [];
  const dept = user?.department;
  if (typeof dept === 'string' && dept.trim()) parts.push(dept.trim());
  const company = companyLabel(user);
  if (company) parts.push(company);
  return parts.join(' · ');
}

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
          <div class="share-dialog__accent" aria-hidden="true" />

          <header class="share-dialog__header">
            <div class="share-dialog__header-copy">
              <p class="share-dialog__kicker">Chia sẻ</p>
              <h2 class="share-dialog__title">Chọn nơi đăng bài</h2>
            </div>
            <button type="button" class="share-dialog__close" aria-label="Đóng" @click="close">
              <AppIcon name="close" :size="18" />
            </button>
          </header>

          <div class="share-dialog__body hide-scrollbar">
            <section class="share-dialog__section">
              <p class="share-dialog__section-label">Đăng lên</p>
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
                    <AppIcon :name="item.icon" :size="18" :stroke-width="1.75" />
                  </span>
                  <span class="share-dialog__dest-copy">
                    <span class="share-dialog__dest-label">{{ item.label }}</span>
                    <span class="share-dialog__dest-hint">{{ item.hint }}</span>
                  </span>
                  <span
                    class="share-dialog__dest-check"
                    :class="{ 'share-dialog__dest-check--on': destination === item.id }"
                    aria-hidden="true"
                  >
                    <AppIcon name="check" :size="14" :stroke-width="2.5" />
                  </span>
                </button>
              </div>
            </section>

            <section v-if="destination === 'other'" class="share-dialog__section share-dialog__section--people">
              <p class="share-dialog__section-label">Chọn đồng nghiệp</p>

              <div v-if="selectedUser" class="share-dialog__picked">
                <img
                  v-if="selectedUser.avatar_url"
                  class="share-dialog__avatar share-dialog__avatar--lg"
                  :src="selectedUser.avatar_url"
                  :alt="`Ảnh đại diện của ${selectedUser.name}`"
                />
                <span v-else class="share-dialog__avatar share-dialog__avatar--lg share-dialog__avatar--placeholder">
                  {{ selectedUser.name?.charAt(0) ?? '?' }}
                </span>
                <span class="share-dialog__picked-copy">
                  <span class="share-dialog__picked-name">{{ selectedUser.name }}</span>
                  <span v-if="personMeta(selectedUser)" class="share-dialog__picked-meta">
                    {{ personMeta(selectedUser) }}
                  </span>
                </span>
                <button type="button" class="share-dialog__picked-clear" aria-label="Bỏ chọn" @click="clearSelectedUser">
                  <AppIcon name="close" :size="16" />
                </button>
              </div>

              <label class="share-dialog__search">
                <AppIcon name="search" :size="18" />
                <input
                  :value="query"
                  type="search"
                  placeholder="Tên hoặc phòng ban…"
                  aria-label="Tìm đồng nghiệp để chia sẻ lên tường"
                  enterkeyhint="search"
                  autocomplete="off"
                  @input="onQueryInput"
                />
              </label>

              <p v-if="searching" class="share-dialog__empty">
                <AppIcon name="refresh" :size="16" class="share-dialog__empty-spin" />
                Đang tìm…
              </p>
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
                      <span v-if="personMeta(user)" class="share-dialog__person-meta">{{ personMeta(user) }}</span>
                    </span>
                    <AppIcon name="chevronRight" :size="18" class="share-dialog__person-chevron" aria-hidden="true" />
                  </button>
                </li>
              </ul>
              <p v-else-if="query.trim()" class="share-dialog__empty">
                Không tìm thấy đồng nghiệp khớp “{{ query.trim() }}”.
              </p>
            </section>

            <section v-if="destination === 'group'" class="share-dialog__section share-dialog__section--people">
              <p class="share-dialog__section-label">Chọn nhóm</p>

              <div v-if="selectedGroup" class="share-dialog__picked">
                <img
                  v-if="selectedGroup.avatar_url"
                  class="share-dialog__avatar share-dialog__avatar--lg"
                  :src="selectedGroup.avatar_url"
                  :alt="`Ảnh đại diện nhóm ${selectedGroup.name}`"
                />
                <span v-else class="share-dialog__avatar share-dialog__avatar--lg share-dialog__avatar--placeholder">
                  {{ groupInitial(selectedGroup) }}
                </span>
                <span class="share-dialog__picked-copy">
                  <span class="share-dialog__picked-name">{{ selectedGroup.name }}</span>
                  <span class="share-dialog__picked-meta">
                    {{ selectedGroup.visibility === 'private' ? 'Nhóm bảo mật' : 'Nhóm công khai' }}
                    · {{ selectedGroup.members_count }} thành viên
                  </span>
                </span>
                <button type="button" class="share-dialog__picked-clear" aria-label="Bỏ chọn nhóm" @click="clearSelectedGroup">
                  <AppIcon name="close" :size="16" />
                </button>
              </div>

              <label class="share-dialog__search">
                <AppIcon name="search" :size="18" />
                <input
                  :value="groupQuery"
                  type="search"
                  placeholder="Tìm nhóm của bạn…"
                  aria-label="Tìm nhóm để chia sẻ"
                  enterkeyhint="search"
                  autocomplete="off"
                  @input="onGroupQueryInput"
                />
              </label>

              <p v-if="searchingGroups" class="share-dialog__empty">
                <AppIcon name="refresh" :size="16" class="share-dialog__empty-spin" />
                Đang tìm…
              </p>
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
                    <AppIcon name="chevronRight" :size="18" class="share-dialog__person-chevron" aria-hidden="true" />
                  </button>
                </li>
              </ul>
              <p v-else-if="groupQuery.trim()" class="share-dialog__empty">
                Không tìm thấy nhóm khớp “{{ groupQuery.trim() }}”.
              </p>
              <p v-else-if="!selectedGroup" class="share-dialog__empty">
                Bạn chưa tham gia nhóm nào.
              </p>
            </section>

            <section class="share-dialog__section">
              <label class="share-dialog__caption">
                <span class="share-dialog__section-label">Lời nhắn kèm theo</span>
                <span class="share-dialog__caption-optional">Tuỳ chọn</span>
                <textarea
                  v-model="caption"
                  rows="3"
                  maxlength="5000"
                  placeholder="Viết vài dòng về bài viết này…"
                />
              </label>
            </section>
          </div>

          <div class="share-dialog__actions">
            <button type="button" class="share-dialog__btn share-dialog__btn--ghost" :disabled="submitting" @click="close">
              Huỷ
            </button>
            <button type="button" class="share-dialog__btn share-dialog__btn--primary" :disabled="submitting" @click="submit">
              {{ submitting ? 'Đang chia sẻ…' : 'Chia sẻ bài viết' }}
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
  align-items: flex-end;
  justify-content: center;
  padding: 0;
  background: color-mix(in srgb, #1a1a1a 42%, transparent);
  backdrop-filter: blur(4px);
}

@media (min-width: 640px) {
  .share-dialog {
    align-items: center;
    padding: var(--space-4);
  }
}

.share-dialog__panel {
  position: relative;
  width: 100%;
  max-height: min(36rem, calc(100vh - 1rem));
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

@media (min-width: 640px) {
  .share-dialog__panel {
    width: min(28rem, calc(100vw - 2.5rem));
    max-height: calc(100vh - 2.5rem);
    border-radius: var(--radius-lg);
  }
}

.share-dialog__accent {
  position: absolute;
  top: 0;
  left: 0;
  bottom: 0;
  width: 4px;
  background: linear-gradient(
    180deg,
    var(--color-primary) 0%,
    color-mix(in srgb, var(--color-tertiary) 70%, var(--color-primary)) 100%
  );
  pointer-events: none;
}

.share-dialog__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  flex-shrink: 0;
  padding: var(--space-4) var(--space-4) var(--space-3);
  padding-left: calc(var(--space-4) + 4px);
  box-shadow: 0 1px 0 var(--color-border-subtle);
}

.share-dialog__header-copy {
  min-width: 0;
}

.share-dialog__kicker {
  margin: 0 0 0.15rem;
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--color-primary);
}

.share-dialog__title {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 700;
  line-height: 1.25;
  color: var(--color-text);
}

.share-dialog__close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  flex-shrink: 0;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}

.share-dialog__close:hover {
  background: var(--color-border-subtle);
  color: var(--color-text);
}

.share-dialog__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: var(--space-3) var(--space-4) var(--space-4);
  padding-left: calc(var(--space-4) + 4px);
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.share-dialog__section {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.share-dialog__section-label {
  margin: 0;
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.share-dialog__destinations {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-2);
}

.share-dialog__dest {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--space-2);
  width: 100%;
  min-height: 5.5rem;
  text-align: left;
  padding: var(--space-3);
  border: none;
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border-subtle);
  cursor: pointer;
  font-family: inherit;
  color: var(--color-text);
  transition:
    background 0.15s ease,
    box-shadow 0.15s ease,
    transform 0.12s ease;
}

.share-dialog__dest:active {
  transform: scale(0.98);
}

.share-dialog__dest--active {
  background: color-mix(in srgb, var(--color-primary) 8%, var(--color-surface));
  box-shadow:
    inset 0 0 0 2px var(--color-primary),
    var(--shadow-sm);
}

.share-dialog__dest-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  flex-shrink: 0;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text-muted);
  box-shadow: var(--shadow-sm);
}

.share-dialog__dest--active .share-dialog__dest-icon {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.share-dialog__dest-copy {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  flex: 1;
}

.share-dialog__dest-label {
  font-size: 0.8125rem;
  font-weight: 700;
  line-height: 1.25;
}

.share-dialog__dest-hint {
  font-size: 0.6875rem;
  line-height: 1.35;
  color: var(--color-text-muted);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.share-dialog__dest-check {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
  overflow: hidden;
}

.share-dialog__dest-check--on {
  position: absolute;
  top: var(--space-2);
  right: var(--space-2);
  opacity: 1;
  width: 1.35rem;
  height: 1.35rem;
  display: grid;
  place-items: center;
  border-radius: var(--radius-full);
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.share-dialog__section--people {
  padding: var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border-subtle);
}

.share-dialog__search {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0 var(--space-3);
  min-height: 2.75rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text-muted);
  box-shadow: var(--shadow-sm);
}

.share-dialog__search input {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.9375rem;
  outline: none;
}

.share-dialog__search input::placeholder {
  color: var(--color-text-muted);
}

.share-dialog__picked {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.share-dialog__person {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  width: 100%;
  text-align: left;
  padding: var(--space-2) var(--space-2);
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  cursor: pointer;
  font-family: inherit;
  color: var(--color-text);
  box-shadow: var(--shadow-sm);
  transition: background 0.15s ease;
}

.share-dialog__person:hover {
  background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface));
}

.share-dialog__avatar {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
}

.share-dialog__avatar--lg {
  width: 2.75rem;
  height: 2.75rem;
}

.share-dialog__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-weight: 700;
  font-size: 0.875rem;
}

.share-dialog__picked-copy {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  flex: 1;
}

.share-dialog__picked-name,
.share-dialog__person-name {
  font-size: 0.9375rem;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.share-dialog__picked-meta,
.share-dialog__person-meta {
  font-size: 0.75rem;
  color: var(--color-text-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.share-dialog__picked-clear {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  flex-shrink: 0;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  cursor: pointer;
}

.share-dialog__person-copy {
  display: flex;
  min-width: 0;
  flex: 1;
  flex-direction: column;
  gap: 2px;
}

.share-dialog__person-chevron {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.share-dialog__results {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  max-height: 11rem;
  overflow-y: auto;
}

.share-dialog__empty {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  margin: 0;
  padding: var(--space-3);
  font-size: 0.8125rem;
  line-height: 1.45;
  color: var(--color-text-muted);
  text-align: center;
}

.share-dialog__empty-spin {
  animation: share-dialog-spin 0.8s linear infinite;
}

.share-dialog__caption {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: var(--space-2);
  align-items: baseline;
}

.share-dialog__caption-optional {
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.share-dialog__caption textarea {
  grid-column: 1 / -1;
  width: 100%;
  resize: vertical;
  min-height: 4.75rem;
  padding: var(--space-3);
  border: none;
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.9375rem;
  line-height: 1.45;
  outline: none;
  box-shadow: inset 0 0 0 1px var(--color-border-subtle);
}

.share-dialog__caption textarea:focus {
  box-shadow: inset 0 0 0 1px var(--color-primary);
}

.share-dialog__actions {
  display: flex;
  flex-direction: column-reverse;
  gap: var(--space-2);
  flex-shrink: 0;
  padding: var(--space-3) var(--space-4) var(--space-4);
  padding-left: calc(var(--space-4) + 4px);
  box-shadow: 0 -1px 0 var(--color-border-subtle);
  background: color-mix(in srgb, var(--color-surface-muted) 40%, var(--color-surface));
}

@media (min-width: 640px) {
  .share-dialog__actions {
    flex-direction: row;
    justify-content: flex-end;
  }
}

.share-dialog__btn {
  border: none;
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-lg);
  font-family: inherit;
  font-size: 0.9375rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s ease, opacity 0.15s ease;
}

.share-dialog__btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.share-dialog__btn--primary:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.share-dialog__btn--ghost {
  background: transparent;
  color: var(--color-text-muted);
}

.share-dialog__btn--ghost:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.share-dialog__btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.share-dialog-fade-enter-active,
.share-dialog-fade-leave-active {
  transition: opacity 0.2s ease;
}

.share-dialog-fade-enter-active .share-dialog__panel,
.share-dialog-fade-leave-active .share-dialog__panel {
  transition: transform 0.22s ease;
}

.share-dialog-fade-enter-from,
.share-dialog-fade-leave-to {
  opacity: 0;
}

.share-dialog-fade-enter-from .share-dialog__panel,
.share-dialog-fade-leave-to .share-dialog__panel {
  transform: translateY(1.25rem);
}

@media (min-width: 640px) {
  .share-dialog-fade-enter-from .share-dialog__panel,
  .share-dialog-fade-leave-to .share-dialog__panel {
    transform: translateY(0.5rem) scale(0.98);
  }
}

@keyframes share-dialog-spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
