<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { showClientToast } from '@/lib/clientToast';
import { formatRelativeTime } from '@/lib/formatTime';
import { formatPeerMeta } from '../lib/chatPeerMeta';
import { ChatFileError, prepareChatFile } from '../lib/prepareChatFile';
import { useColleagueSearch } from '../lib/useColleagueSearch';
import { useChatStore } from '../store/chatStore';
import ChatAvatar from './ChatAvatar.vue';

const MAX_MEMBERS = 50;
const STACK_LIMIT = 6;

const props = defineProps({
  open: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);

const store = useChatStore();
const auth = useAuthStore();
const query = ref('');
const selected = ref([]);
const saving = ref(false);
const avatarSaving = ref(false);
const titleDraft = ref('');
const titleSaving = ref(false);
const error = ref('');
const avatarInput = ref(null);
/** Chỉ dùng ở màn hình hẹp, nơi hai khối được xếp thành hai tab. */
const tab = ref('members');
const { results, searching, loadingMore, failed, setQuery, onScroll, reset } = useColleagueSearch({
  exclude: (user) => takenIds.value.has(user.id) || selected.value.some((item) => item.id === user.id),
});

const conversation = computed(() => store.activeConversation);
const isOwner = computed(() => Boolean(conversation.value?.is_owner));
const members = computed(() => conversation.value?.members || []);
const groupAvatarUrl = computed(() => conversation.value?.avatar_url ?? null);
const groupTitle = computed(() => conversation.value?.title || 'Nhóm');
const takenIds = computed(() => new Set(members.value.map((user) => user.id)));
const stackMembers = computed(() => members.value.slice(0, STACK_LIMIT));
const slotsLeft = computed(() => MAX_MEMBERS - members.value.length - selected.value.length);
const canAdd = computed(() => !saving.value && selected.value.length > 0);
const canSaveTitle = computed(() => {
  if (titleSaving.value || saving.value) return false;
  const next = titleDraft.value.trim();
  return next !== '' && next !== (conversation.value?.title || '').trim();
});

watch(() => props.open, (value) => {
  if (!value) {
    window.removeEventListener('keydown', onKeydown);
    return;
  }
  query.value = '';
  reset();
  selected.value = [];
  error.value = '';
  tab.value = 'members';
  titleDraft.value = conversation.value?.title || '';
  window.addEventListener('keydown', onKeydown);
});

watch(
  () => conversation.value?.title,
  (value) => {
    if (props.open && !titleSaving.value) {
      titleDraft.value = value || '';
    }
  },
);

watch(query, (value) => {
  if (!isOwner.value) {
    reset();
    return;
  }
  setQuery(value);
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown);
});

function onKeydown(event) {
  if (event.key === 'Escape') close();
}

function select(user) {
  if (selected.value.some((item) => item.id === user.id)) return;
  if (slotsLeft.value <= 0) {
    error.value = `Nhóm chỉ có tối đa ${MAX_MEMBERS} thành viên.`;
    return;
  }
  selected.value = [...selected.value, user];
  results.value = results.value.filter((item) => item.id !== user.id);
  error.value = '';
}

function unselect(id) {
  selected.value = selected.value.filter((user) => user.id !== id);
}

function close() {
  if (saving.value) return;
  emit('close');
}

async function addSelected() {
  if (!canAdd.value) return;
  const count = selected.value.length;
  saving.value = true;
  error.value = '';
  try {
    await store.addMembers(conversation.value.id, selected.value.map((user) => user.id));
    selected.value = [];
    reset();
    query.value = '';
    tab.value = 'members';
    showClientToast('success', `Đã thêm ${count} người vào nhóm.`);
  } catch (err) {
    error.value = err?.response?.data?.message || err?.response?.data?.errors?.user_ids?.[0] || 'Không thêm được thành viên.';
  } finally {
    saving.value = false;
  }
}

async function remove(user) {
  if (saving.value) return;
  saving.value = true;
  error.value = '';
  try {
    const result = await store.removeMember(conversation.value.id, user.id);
    if (result?.left) emit('close');
  } catch (err) {
    error.value = err?.response?.data?.message || err?.response?.data?.errors?.user_id?.[0] || 'Không cập nhật được nhóm.';
  } finally {
    saving.value = false;
  }
}

async function transfer(user) {
  if (saving.value) return;
  saving.value = true;
  error.value = '';
  try {
    await store.transferOwner(conversation.value.id, user.id);
  } catch (err) {
    error.value = err?.response?.data?.message || 'Không chuyển được quyền.';
  } finally {
    saving.value = false;
  }
}

function memberLine(user) {
  const parts = [];
  if (user.id === conversation.value?.created_by) parts.push('Chủ nhóm');
  if (user.online) parts.push('Đang hoạt động');
  else if (user.last_seen_at) parts.push(`Truy cập ${formatRelativeTime(user.last_seen_at)}`);
  return parts.join(' · ');
}

function pickAvatar() {
  avatarInput.value?.click();
}

async function onAvatarChosen(event) {
  const raw = event.target.files?.[0];
  event.target.value = '';
  if (!raw || !conversation.value?.id) return;
  avatarSaving.value = true;
  error.value = '';
  try {
    const file = await prepareChatFile(raw);
    await store.updateGroupAvatar(conversation.value.id, file);
    showClientToast('success', 'Đã cập nhật ảnh nhóm.');
  } catch (err) {
    if (err instanceof ChatFileError) {
      error.value = err.message;
    } else {
      error.value = err?.response?.data?.message
        || err?.response?.data?.errors?.avatar?.[0]
        || 'Không cập nhật được ảnh nhóm.';
    }
  } finally {
    avatarSaving.value = false;
  }
}

async function saveTitle() {
  if (!canSaveTitle.value || !conversation.value?.id) return;
  titleSaving.value = true;
  error.value = '';
  try {
    await store.updateGroupTitle(conversation.value.id, titleDraft.value.trim());
    showClientToast('success', 'Đã cập nhật tên nhóm.');
  } catch (err) {
    error.value = err?.response?.data?.message
      || err?.response?.data?.errors?.title?.[0]
      || 'Không cập nhật được tên nhóm.';
  } finally {
    titleSaving.value = false;
  }
}
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="chat-room" role="presentation" @mousedown.self="close">
      <div class="chat-room__panel" role="dialog" aria-modal="true" aria-labelledby="chat-room-title">
        <div class="chat-room__head">
          <h2 id="chat-room-title" class="chat-room__heading">Thành viên nhóm</h2>
          <button type="button" class="chat-room__close" aria-label="Đóng" @click="close">
            <AppIcon name="close" :size="16" />
          </button>
        </div>

        <div class="chat-room__body">
          <div class="chat-room__brand">
            <button
              type="button"
              class="chat-room__brand-img-btn"
              :class="{
                'chat-room__brand-img-btn--owner': isOwner,
                'chat-room__brand-img-btn--busy': avatarSaving,
              }"
              :disabled="!isOwner || avatarSaving || saving"
              :aria-label="isOwner ? 'Chọn ảnh đại diện nhóm' : `Ảnh nhóm ${groupTitle}`"
              @click="pickAvatar"
            >
              <img
                v-if="groupAvatarUrl"
                class="chat-room__brand-img"
                :src="groupAvatarUrl"
                :alt="`Ảnh nhóm ${groupTitle}`"
              />
              <span v-else class="chat-room__brand-img chat-room__brand-img--ph">
                <AppIcon name="users" :size="28" :stroke-width="1.5" />
              </span>
              <span v-if="isOwner" class="chat-room__brand-camera" aria-hidden="true">
                <AppIcon name="camera" :size="14" :stroke-width="1.75" />
              </span>
            </button>
            <span class="chat-room__brand-copy">
              <span v-if="!isOwner" class="chat-room__brand-name">{{ groupTitle }}</span>
              <label v-else class="chat-room__title-field">
                <span class="chat-room__title-label">Tên nhóm</span>
                <span class="chat-room__title-row">
                  <input
                    v-model="titleDraft"
                    class="chat-room__title-input"
                    type="text"
                    maxlength="80"
                    autocomplete="off"
                    :disabled="titleSaving || saving"
                    @keydown.enter.prevent="saveTitle"
                  />
                  <button
                    type="button"
                    class="chat-room__title-save"
                    :disabled="!canSaveTitle"
                    @click="saveTitle"
                  >
                    {{ titleSaving ? 'Đang lưu…' : 'Lưu' }}
                  </button>
                </span>
              </label>
              <span class="chat-room__brand-meta">
                <span class="chat-room__stack" aria-hidden="true">
                  <ChatAvatar
                    v-for="user in stackMembers"
                    :key="user.id"
                    class="chat-room__stack-item"
                    :src="user.avatar_url"
                    :name="user.name"
                    size="1.5rem"
                  />
                </span>
                {{ members.length }} thành viên
              </span>
              <button
                v-if="isOwner"
                type="button"
                class="chat-room__brand-action"
                :disabled="avatarSaving || saving"
                @click="pickAvatar"
              >
                {{ avatarSaving ? 'Đang tải ảnh…' : 'Đổi ảnh nhóm' }}
              </button>
            </span>
            <input
              ref="avatarInput"
              class="chat-room__file"
              type="file"
              accept="image/jpeg,image/png,image/webp,image/gif"
              aria-hidden="true"
              tabindex="-1"
              @change="onAvatarChosen"
            />
          </div>

          <div v-if="isOwner" class="chat-room__tabs" role="tablist">
            <button
              type="button"
              role="tab"
              class="chat-room__tab"
              :class="{ 'chat-room__tab--on': tab === 'members' }"
              :aria-selected="tab === 'members'"
              @click="tab = 'members'"
            >
              Đang trong nhóm ({{ members.length }})
            </button>
            <button
              type="button"
              role="tab"
              class="chat-room__tab"
              :class="{ 'chat-room__tab--on': tab === 'add' }"
              :aria-selected="tab === 'add'"
              @click="tab = 'add'"
            >
              Thêm người{{ selected.length ? ` (${selected.length})` : '' }}
            </button>
          </div>

          <p v-if="error" class="chat-room__error" role="alert">{{ error }}</p>

          <div class="chat-room__grid" :class="{ 'chat-room__grid--single': !isOwner }">
            <section v-if="isOwner" class="chat-room__card" :class="{ 'chat-room__card--off': tab !== 'add' }">
              <header class="chat-room__card-head">
                <AppIcon name="userPlus" :size="16" :stroke-width="1.75" />
                <h3 class="chat-room__card-title">Thêm người vào nhóm</h3>
              </header>

              <label class="chat-room__label" for="chat-room-search">Tìm đồng nghiệp</label>
              <span class="chat-room__search">
                <AppIcon name="search" :size="16" :stroke-width="1.75" />
                <input
                  id="chat-room-search"
                  v-model="query"
                  class="chat-room__input"
                  type="text"
                  autocomplete="off"
                  placeholder="Vd. Nguyễn Văn An"
                />
              </span>

              <div v-if="selected.length" class="chat-room__picked hide-scrollbar" aria-label="Người đã chọn">
                <span v-for="user in selected" :key="user.id" class="chat-room__chip">
                  <ChatAvatar :src="user.avatar_url" :name="user.name" size="1.5rem" />
                  <span class="chat-room__chip-name">{{ user.name }}</span>
                  <button
                    type="button"
                    class="chat-room__chip-remove"
                    :aria-label="`Bỏ ${user.name}`"
                    @click="unselect(user.id)"
                  >
                    <AppIcon name="close" :size="12" :stroke-width="2" />
                  </button>
                </span>
              </div>

              <div class="chat-room__list hide-scrollbar" @scroll.passive="onScroll">
                <p v-if="query.trim() === ''" class="chat-room__empty">Nhập tên để tìm đồng nghiệp muốn thêm vào nhóm.</p>
                <p v-else-if="searching" class="chat-room__empty">Đang tìm…</p>
                <p v-else-if="failed && results.length === 0" class="chat-room__empty">
                  Chưa tải được danh sách đồng nghiệp. Bạn thử lại sau ít phút.
                </p>
                <p v-else-if="results.length === 0" class="chat-room__empty">Không tìm thấy ai phù hợp.</p>
                <button
                  v-for="user in results"
                  :key="user.id"
                  type="button"
                  class="chat-room__result"
                  @click="select(user)"
                >
                  <ChatAvatar :src="user.avatar_url" :name="user.name" size="2.5rem" />
                  <span class="chat-room__copy">
                    <span class="chat-room__name">{{ user.name }}</span>
                    <span v-if="formatPeerMeta(user)" class="chat-room__meta">{{ formatPeerMeta(user) }}</span>
                  </span>
                  <span class="chat-room__add">
                    <AppIcon name="plus" :size="14" :stroke-width="2" />
                    Thêm
                  </span>
                </button>
                <p v-if="loadingMore" class="chat-room__empty">Đang tải thêm…</p>
              </div>

              <button type="button" class="chat-room__submit" :disabled="!canAdd" @click="addSelected">
                {{ saving ? 'Đang thêm…' : selected.length ? `Thêm ${selected.length} người vào nhóm` : 'Chọn người để thêm' }}
              </button>
            </section>

            <section class="chat-room__card" :class="{ 'chat-room__card--off': isOwner && tab !== 'members' }">
              <header class="chat-room__card-head">
                <AppIcon name="users" :size="16" :stroke-width="1.75" />
                <h3 class="chat-room__card-title">Đang trong nhóm</h3>
                <span class="chat-room__count">{{ members.length }} người</span>
              </header>

              <div class="chat-room__list chat-room__list--members hide-scrollbar">
                <div v-for="user in members" :key="user.id" class="chat-room__member">
                  <span class="chat-room__avatar-wrap">
                    <ChatAvatar :src="user.avatar_url" :name="user.name" size="2.5rem" />
                    <span v-if="user.online" class="chat-room__online" aria-hidden="true" />
                  </span>
                  <span class="chat-room__copy">
                    <span class="chat-room__name">
                      {{ user.name }}
                      <span v-if="user.id === auth.user?.id" class="chat-room__you">(Bạn)</span>
                    </span>
                    <span v-if="memberLine(user)" class="chat-room__meta">{{ memberLine(user) }}</span>
                  </span>
                  <span v-if="isOwner && user.id !== auth.user?.id" class="chat-room__actions">
                    <button
                      v-if="user.id !== conversation?.created_by"
                      type="button"
                      class="chat-room__text"
                      :disabled="saving"
                      @click="transfer(user)"
                    >
                      Chuyển quyền
                    </button>
                    <button
                      type="button"
                      class="chat-room__text chat-room__text--danger"
                      :disabled="saving"
                      :aria-label="`Mời ${user.name} ra khỏi nhóm`"
                      @click="remove(user)"
                    >
                      Mời ra
                    </button>
                  </span>
                </div>
              </div>

              <button
                v-if="!isOwner"
                type="button"
                class="chat-room__leave"
                :disabled="saving"
                @click="remove({ id: auth.user?.id })"
              >
                Rời nhóm
              </button>
            </section>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.chat-room {
  position: fixed;
  inset: 0;
  z-index: 70;
  display: grid;
  place-items: center;
  padding: 1.25rem;
  background: color-mix(in srgb, var(--color-text) 28%, transparent);
}

.chat-room__panel {
  display: flex;
  flex-direction: column;
  width: min(56rem, calc(100vw - 2.5rem));
  height: min(40rem, calc(100vh - 2.5rem));
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-lg);
}

.chat-room__head {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
  padding: 0.9rem 1rem;
  box-shadow: 0 1px 0 var(--color-border);
}

.chat-room__heading {
  flex: 1;
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
}

.chat-room__close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: 0;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.chat-room__close:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.chat-room__body {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 0.85rem;
  min-height: 0;
  padding: 1rem;
  overflow: hidden;
}

/* ── Đầu nhóm: ảnh, tên, hàng avatar thành viên ───────────────────────── */
.chat-room__brand {
  position: relative;
  display: flex;
  align-items: center;
  flex-shrink: 0;
  gap: 1rem;
  padding: 0.85rem 1rem;
  border-radius: var(--radius-lg);
  background: linear-gradient(135deg, var(--color-primary-50), var(--color-surface) 70%);
  box-shadow: inset 0 0 0 1px var(--color-primary-100);
}

.chat-room__brand-img-btn {
  position: relative;
  flex-shrink: 0;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  cursor: default;
}

.chat-room__brand-img-btn--owner {
  cursor: pointer;
}

.chat-room__brand-img-btn--owner:hover .chat-room__brand-img {
  filter: brightness(0.94);
}

.chat-room__brand-img-btn:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 5px;
}

.chat-room__brand-img-btn:disabled {
  cursor: default;
}

.chat-room__brand-img-btn--busy .chat-room__brand-img {
  opacity: 0.55;
}

.chat-room__brand-img {
  display: block;
  width: 4.5rem;
  height: 4.5rem;
  border-radius: var(--radius-full);
  object-fit: cover;
  box-shadow:
    0 0 0 3px var(--color-surface),
    0 0 0 5px var(--color-primary-200),
    var(--shadow-md);
  transition: filter 0.15s ease, opacity 0.15s ease;
}

.chat-room__brand-img--ph {
  display: grid;
  place-items: center;
  background: linear-gradient(135deg, var(--color-primary-100), var(--color-primary-200));
  color: var(--color-primary);
}

.chat-room__brand-camera {
  position: absolute;
  right: 0;
  bottom: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.65rem;
  height: 1.65rem;
  border-radius: var(--radius-full);
  background: var(--color-primary);
  color: var(--color-on-primary);
  box-shadow: 0 0 0 3px var(--color-surface), var(--shadow-sm);
}

.chat-room__brand-copy {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

.chat-room__brand-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--color-text);
}

.chat-room__title-field {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  min-width: 0;
}

.chat-room__title-label {
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.chat-room__title-row {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  min-width: 0;
}

.chat-room__title-input {
  flex: 1;
  min-width: 0;
  padding: 0.4rem 0.55rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-text);
}

.chat-room__title-input:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 1px;
  border-color: var(--color-primary);
}

.chat-room__title-input:disabled {
  opacity: 0.65;
}

.chat-room__title-save {
  flex-shrink: 0;
  padding: 0.4rem 0.75rem;
  border: 0;
  border-radius: var(--radius-md);
  background: var(--color-primary);
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-on-primary);
  cursor: pointer;
}

.chat-room__title-save:disabled {
  opacity: 0.45;
  cursor: default;
}

.chat-room__brand-meta {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.chat-room__stack {
  display: inline-flex;
  flex-shrink: 0;
}

.chat-room__stack-item {
  box-shadow: 0 0 0 2px var(--color-surface);
}

.chat-room__stack-item + .chat-room__stack-item {
  margin-left: -0.4rem;
}

.chat-room__brand-action {
  align-self: flex-start;
  margin-top: 0.2rem;
  padding: 0.3rem 0.85rem;
  border: 0;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-primary-200);
  color: var(--color-primary);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s ease;
}

.chat-room__brand-action:hover:not(:disabled) {
  background: var(--color-primary-50);
}

.chat-room__brand-action:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.chat-room__file {
  position: absolute;
  width: 0;
  height: 0;
  opacity: 0;
  pointer-events: none;
}

/* ── Tab (chỉ hiện ở màn hình hẹp) ────────────────────────────────────── */
.chat-room__tabs {
  display: none;
  flex-shrink: 0;
  gap: 0.25rem;
  box-shadow: 0 1px 0 var(--color-border);
}

.chat-room__tab {
  flex: 1;
  padding: 0.6rem 0.5rem;
  border: 0;
  background: transparent;
  color: var(--color-text-muted);
  font-family: inherit;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.chat-room__tab--on {
  color: var(--color-primary);
  box-shadow: 0 2px 0 var(--color-primary);
}

.chat-room__error {
  flex-shrink: 0;
  margin: 0;
  color: var(--color-danger);
  font-size: 0.8125rem;
}

/* ── Hai khối: thêm người | thành viên hiện tại ───────────────────────── */
.chat-room__grid {
  display: grid;
  flex: 1;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  grid-template-rows: minmax(0, 1fr);
  gap: 1rem;
  min-height: 0;
}

.chat-room__grid--single {
  grid-template-columns: minmax(0, 1fr);
}

.chat-room__card {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  min-width: 0;
  min-height: 0;
  padding: 0.85rem;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.chat-room__card-head {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  gap: 0.5rem;
  color: var(--color-primary);
}

.chat-room__card-title {
  flex: 1;
  margin: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 700;
}

.chat-room__count {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.chat-room__label {
  flex-shrink: 0;
  margin-bottom: -0.3rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.chat-room__search {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  gap: 0.5rem;
  padding: 0 0.75rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.chat-room__search:focus-within {
  outline: 2px solid var(--color-primary);
  outline-offset: -1px;
}

.chat-room__input {
  flex: 1;
  min-width: 0;
  padding: 0.65rem 0;
  border: none;
  outline: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 16px;
}

/* ── Người đã chọn ────────────────────────────────────────────────────── */
.chat-room__picked {
  display: flex;
  flex-shrink: 0;
  flex-wrap: wrap;
  gap: 0.35rem;
  max-height: 5.5rem;
  overflow-y: auto;
}

.chat-room__chip {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  max-width: 100%;
  padding: 0.2rem 0.3rem 0.2rem 0.2rem;
  border-radius: var(--radius-full);
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.chat-room__chip-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.75rem;
  font-weight: 600;
}

.chat-room__chip-remove {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 1.25rem;
  height: 1.25rem;
  border: none;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-primary) 12%, transparent);
  color: var(--color-primary);
  cursor: pointer;
}

.chat-room__chip-remove:hover {
  background: color-mix(in srgb, var(--color-primary) 22%, transparent);
}

/* ── Danh sách (cuộn trong khối, không hiện thanh cuộn) ───────────────── */
.chat-room__list {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
}

.chat-room__empty {
  margin: 0;
  padding: 0.875rem 0.5rem;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  text-align: center;
}

.chat-room__result,
.chat-room__member {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  width: 100%;
  padding: 0.5rem 0.5rem;
  border: 0;
  border-radius: var(--radius-md);
  background: transparent;
  color: var(--color-text);
  font-family: inherit;
  text-align: left;
}

.chat-room__result {
  cursor: pointer;
  transition: background 0.12s ease;
}

.chat-room__result:hover,
.chat-room__result:focus-visible {
  background: var(--color-primary-50);
  outline: none;
}

.chat-room__copy {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}

.chat-room__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.875rem;
  font-weight: 640;
}

.chat-room__you {
  color: var(--color-text-muted);
  font-weight: 500;
}

.chat-room__meta {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.chat-room__add {
  display: inline-flex;
  align-items: center;
  flex-shrink: 0;
  gap: 0.25rem;
  padding: 0.3rem 0.65rem;
  border-radius: var(--radius-full);
  box-shadow: inset 0 0 0 1px var(--color-primary-200);
  color: var(--color-primary);
  font-size: 0.75rem;
  font-weight: 600;
}

.chat-room__result:hover .chat-room__add {
  background: var(--color-primary);
  box-shadow: none;
  color: var(--color-on-primary);
}

.chat-room__avatar-wrap {
  position: relative;
  display: flex;
  flex-shrink: 0;
}

.chat-room__online {
  position: absolute;
  right: -1px;
  bottom: -1px;
  width: 0.7rem;
  height: 0.7rem;
  border-radius: var(--radius-full);
  background: var(--color-success);
  box-shadow: 0 0 0 2px var(--color-surface);
}

.chat-room__member {
  box-shadow: 0 1px 0 var(--color-border);
  border-radius: 0;
}

.chat-room__member:last-child {
  box-shadow: none;
}

.chat-room__actions {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: 0.6rem;
}

.chat-room__text {
  padding: 0.25rem 0;
  border: 0;
  background: transparent;
  color: var(--color-primary);
  font-family: inherit;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
}

.chat-room__text--danger {
  color: var(--color-danger);
}

.chat-room__text:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.chat-room__submit,
.chat-room__leave {
  flex-shrink: 0;
  padding: 0.65rem 0.9rem;
  border: 0;
  border-radius: var(--radius-md);
  font-family: inherit;
  font-size: 0.875rem;
  font-weight: 700;
  cursor: pointer;
}

.chat-room__submit {
  background: linear-gradient(145deg, var(--color-primary), var(--color-tertiary-600));
  color: var(--color-on-primary);
}

.chat-room__leave {
  background: var(--color-surface-muted);
  color: var(--color-danger);
}

.chat-room__submit:disabled,
.chat-room__leave:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* ── Responsive ───────────────────────────────────────────────────────── */
@media (max-width: 768px) {
  .chat-room {
    padding: 0;
  }

  .chat-room__panel {
    width: 100vw;
    height: 100dvh;
    border-radius: 0;
  }

  .chat-room__tabs {
    display: flex;
  }

  .chat-room__grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .chat-room__card--off {
    display: none;
  }
}

@media (max-width: 480px) {
  .chat-room__body {
    padding: 0.75rem;
  }

  .chat-room__brand {
    gap: 0.85rem;
    padding: 0.75rem;
  }

  .chat-room__brand-img {
    width: 3.75rem;
    height: 3.75rem;
  }

  .chat-room__brand-camera {
    width: 1.4rem;
    height: 1.4rem;
  }

  .chat-room__member {
    flex-wrap: wrap;
  }

  .chat-room__actions {
    width: 100%;
    justify-content: flex-end;
  }
}
</style>
