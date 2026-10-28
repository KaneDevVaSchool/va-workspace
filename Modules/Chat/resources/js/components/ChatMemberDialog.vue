<script setup>
import { computed, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { searchUsers } from '../api/chatApi';
import { formatPeerMeta } from '../lib/chatPeerMeta';
import { useChatStore } from '../store/chatStore';

const props = defineProps({
  open: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);

const store = useChatStore();
const auth = useAuthStore();
const query = ref('');
const results = ref([]);
const selected = ref([]);
const saving = ref(false);
const error = ref('');
let timer = null;

const conversation = computed(() => store.activeConversation);
const isOwner = computed(() => Boolean(conversation.value?.is_owner));
const members = computed(() => conversation.value?.members || []);

watch(() => props.open, (value) => {
  if (!value) return;
  query.value = '';
  results.value = [];
  selected.value = [];
  error.value = '';
});

watch(query, (value) => {
  window.clearTimeout(timer);
  const trimmed = value.trim();
  if (!isOwner.value || trimmed === '') {
    results.value = [];
    return;
  }
  timer = window.setTimeout(() => runSearch(trimmed), 250);
});

const takenIds = computed(() => new Set(members.value.map((user) => user.id)));

async function runSearch(value) {
  try {
    const users = await searchUsers(value);
    results.value = users.filter((user) => !takenIds.value.has(user.id) && !selected.value.some((item) => item.id === user.id));
  } catch {
    results.value = [];
  }
}

function close() {
  if (saving.value) return;
  emit('close');
}

async function addSelected() {
  if (!selected.value.length || saving.value) return;
  saving.value = true;
  error.value = '';
  try {
    await store.addMembers(conversation.value.id, selected.value.map((user) => user.id));
    selected.value = [];
    results.value = [];
    query.value = '';
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

function initial(name) {
  return name?.trim().charAt(0).toUpperCase() || '?';
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
        <div class="chat-room__body hide-scrollbar">
          <p v-if="error" class="chat-room__error">{{ error }}</p>
          <div class="chat-room__grid">
            <div v-if="isOwner" class="chat-room__column">
              <label class="chat-room__label" for="chat-room-search">Thêm người</label>
              <input id="chat-room-search" v-model="query" class="chat-room__input" type="text" placeholder="Tìm đồng nghiệp" />
              <button
                v-for="user in results"
                :key="user.id"
                type="button"
                class="chat-room__person"
                @click="selected = [...selected, user]; results = results.filter((item) => item.id !== user.id)"
              >
                <span>{{ user.name }}</span>
                <AppIcon name="plus" :size="14" />
              </button>
              <button v-if="selected.length" type="button" class="chat-room__submit" :disabled="saving" @click="addSelected">
                Thêm {{ selected.length }} người
              </button>
            </div>
            <div class="chat-room__column">
              <p class="chat-room__label">Đang trong nhóm</p>
              <div v-for="user in members" :key="user.id" class="chat-room__person chat-room__person--static">
                <span class="chat-room__avatar">{{ initial(user.name) }}</span>
                <span class="chat-room__copy">
                  <span class="chat-room__name">
                    {{ user.name }}
                    <span v-if="user.id === conversation?.created_by"> · chủ nhóm</span>
                  </span>
                  <span class="chat-room__meta">{{ user.online ? 'Đang hoạt động' : formatPeerMeta(user) }}</span>
                </span>
                <button
                  v-if="isOwner && user.id !== auth.user?.id && user.id !== conversation?.created_by"
                  type="button"
                  class="chat-room__text"
                  @click="transfer(user)"
                >
                  Chuyển quyền
                </button>
                <button
                  v-if="isOwner && user.id !== auth.user?.id"
                  type="button"
                  class="chat-room__text"
                  :aria-label="`Mời ${user.name} ra`"
                  @click="remove(user)"
                >
                  Mời ra
                </button>
              </div>
              <button
                v-if="!isOwner"
                type="button"
                class="chat-room__submit"
                :disabled="saving"
                @click="remove({ id: auth.user?.id })"
              >
                Rời nhóm
              </button>
            </div>
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
  width: min(52rem, calc(100vw - 2.5rem));
  height: min(36rem, calc(100vh - 2.5rem));
  overflow: hidden;
  border-radius: 16px;
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}
.chat-room__head, .chat-room__body { padding: 0.9rem 1rem; }
.chat-room__head {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
  box-shadow: inset 0 -1px 0 var(--color-border);
}
.chat-room__heading { flex: 1; margin: 0; font-size: 1.05rem; }
.chat-room__close, .chat-room__text, .chat-room__submit, .chat-room__person {
  border: 0;
  background: transparent;
  color: var(--color-text);
  cursor: pointer;
}
.chat-room__body { flex: 1; min-height: 0; overflow: auto; }
.chat-room__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}
.chat-room__label { display: block; margin-bottom: 0.35rem; font-size: 0.82rem; }
.chat-room__input {
  width: 100%;
  margin-bottom: 0.5rem;
  padding: 0.55rem 0.7rem;
  border: 1px solid var(--color-border);
  border-radius: 10px;
  background: var(--color-surface);
  color: var(--color-text);
}
.chat-room__person {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  width: 100%;
  padding: 0.4rem 0;
  text-align: left;
}
.chat-room__copy { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.chat-room__name { font-weight: 640; }
.chat-room__meta { font-size: 0.75rem; color: var(--color-text-muted, var(--color-text)); }
.chat-room__avatar {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: 999px;
  background: var(--color-primary-surface, var(--color-border));
}
.chat-room__submit {
  margin-top: 0.6rem;
  padding: 0.5rem 0.8rem;
  border-radius: 10px;
  background: var(--color-primary);
  color: var(--color-on-primary, #fff);
}
.chat-room__error { color: var(--color-danger); }
.chat-room__text { font-size: 0.78rem; color: var(--color-primary); }
@media (max-width: 720px) {
  .chat-room__grid { grid-template-columns: 1fr; }
}
</style>
