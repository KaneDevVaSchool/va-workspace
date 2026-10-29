<script setup>
import { computed, inject, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { formatRelativeTime } from '@/lib/formatTime';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { formatPeerMeta } from '../lib/chatPeerMeta';
import { moodById } from '../lib/chatPlay';
import { useColleagueSearch } from '../lib/useColleagueSearch';
import ChatGroupDialog from './ChatGroupDialog.vue';
import { useChatStore } from '../store/chatStore';

const store = useChatStore();
const auth = useAuthStore();
const shellGroupOpen = inject('employeeChatGroupOpen', null);
const shellListQuery = inject('employeeChatListQuery', null);
const groupOpenLocal = ref(false);
const listQueryLocal = ref('');
const groupOpen = shellGroupOpen ?? groupOpenLocal;
const listQuery = shellListQuery ?? listQueryLocal;
const useShellToolbar = Boolean(shellListQuery);

// Một ô tìm duy nhất (header PWA hoặc ô trong khung desktop): từ khoá vừa lọc hội thoại,
// vừa hiện đồng nghiệp khớp ngay trên danh sách hội thoại.
const {
  results: colleagues,
  searching: colleaguesSearching,
  loadingMore: colleaguesLoadingMore,
  hasMore: colleaguesHasMore,
  failed: colleaguesFailed,
  setQuery: searchColleagues,
  loadMore: loadMoreColleagues,
} = useColleagueSearch();
const showColleagues = computed(() => listQuery.value.trim() !== '');

watch(listQuery, searchColleagues, { immediate: true });

async function openColleague(user) {
  listQuery.value = '';
  await store.openWithUser(user.id);
}

function initial(name) {
  return name?.trim().charAt(0).toUpperCase() || '?';
}

function isGroup(conversation) {
  return conversation.type === 'group';
}

function conversationName(conversation) {
  if (isGroup(conversation)) return conversation.title || 'Nhóm';
  return conversation.other_user?.name || 'Người dùng';
}

function peerMeta(conversation) {
  if (isGroup(conversation)) {
    const count = conversation.member_count || conversation.members?.length || 0;
    return count ? `${count} thành viên` : 'Nhóm';
  }
  return formatPeerMeta(conversation.other_user);
}

function typingText(conversation) {
  const users = (store.typingByConversation[conversation.id]?.users ?? []).filter((user) => user.until > Date.now());
  if (users.length === 0) return '';
  if (isGroup(conversation) && users[0]?.name) return `${users[0].name} đang nhập…`;
  return 'Đang nhập…';
}

function excerpt(conversation) {
  const msg = conversation.last_message;
  if (!msg?.message) return 'Chưa có tin nhắn';
  const text = msg.message;
  if (isGroup(conversation) && auth.user?.id && msg.sender_id !== auth.user.id && msg.sender_name && !msg.recalled) {
    return `${msg.sender_name}: ${text}`;
  }
  if (auth.user?.id && msg.sender_id === auth.user.id && !msg.recalled) {
    return `Bạn: ${text}`;
  }
  return text;
}

function listMarks(conversation) {
  const marks = [];
  const birthday = isGroup(conversation)
    ? (conversation.members || []).some((user) => user.birthday_today)
    : Boolean(conversation.other_user?.birthday_today);
  if (birthday) marks.push('🎂');
  const mood = moodById(isGroup(conversation) ? null : conversation.other_user?.mood);
  if (mood) marks.push(mood.emoji);
  if (!isGroup(conversation) && (conversation.streak || 0) >= 2) marks.push('🔥');
  return marks.join(' ');
}

function open(conversationId) {
  store.openConversationView(conversationId);
}

const visibleConversations = computed(() => {
  const query = listQuery.value.trim().toLowerCase();
  if (!query) return store.conversations;
  return store.conversations.filter((conversation) => conversationName(conversation).toLowerCase().includes(query));
});
</script>

<template>
  <div class="chat-list" :class="{ 'chat-list--shell': useShellToolbar }">
    <div v-if="!useShellToolbar" class="chat-list__toolbar">
      <label class="chat-list__find">
        <AppIcon name="search" :size="14" :stroke-width="1.75" />
        <input
          v-model="listQuery"
          type="search"
          placeholder="Tìm hội thoại, đồng nghiệp…"
          autocomplete="off"
          aria-label="Tìm hội thoại hoặc đồng nghiệp"
        />
      </label>
      <button type="button" class="chat-list__group-btn" aria-label="Tạo nhóm" @click="groupOpen = true">
        <AppIcon name="userPlus" :size="18" :stroke-width="1.75" />
      </button>
    </div>

    <div class="chat-list__items">
      <section v-if="showColleagues" class="chat-people" aria-label="Đồng nghiệp">
        <p class="chat-list__section">Đồng nghiệp</p>
        <p v-if="colleaguesSearching" class="chat-list__empty chat-list__empty--inline">Đang tìm…</p>
        <p v-else-if="colleaguesFailed && colleagues.length === 0" class="chat-list__empty chat-list__empty--inline">
          Chưa tải được danh sách đồng nghiệp. Bạn thử lại sau ít phút.
        </p>
        <p v-else-if="colleagues.length === 0" class="chat-list__empty chat-list__empty--inline">
          Không có đồng nghiệp nào khớp với từ khoá.
        </p>
        <button
          v-for="(user, index) in colleagues"
          v-else
          :key="user.id"
          type="button"
          class="chat-person"
          :style="{ animationDelay: `${Math.min(index, 12) * 30}ms` }"
          @click="openColleague(user)"
        >
          <img
            v-if="user.avatar_url"
            class="chat-item__avatar"
            :src="user.avatar_url"
            :alt="`Ảnh đại diện của ${user.name}`"
          />
          <span v-else class="chat-item__avatar chat-item__avatar--placeholder">{{ initial(user.name) }}</span>
          <span class="chat-item__body">
            <span class="chat-item__name">{{ user.name }}</span>
            <span v-if="formatPeerMeta(user)" class="chat-item__meta">{{ formatPeerMeta(user) }}</span>
          </span>
          <AppIcon class="chat-person__icon" name="messageCircle" :size="16" :stroke-width="1.75" />
        </button>
        <button
          v-if="colleaguesHasMore"
          type="button"
          class="chat-people__more"
          :disabled="colleaguesLoadingMore"
          @click="loadMoreColleagues"
        >
          {{ colleaguesLoadingMore ? 'Đang tải…' : 'Xem thêm đồng nghiệp' }}
        </button>
      </section>
      <p v-if="showColleagues && visibleConversations.length > 0" class="chat-list__section">Hội thoại</p>

      <p v-if="store.loadingConversations" class="chat-list__empty chat-list__empty--loading">
        <span class="chat-list__shimmer" aria-hidden="true" />
        Đang tải hội thoại…
      </p>
      <p v-else-if="store.conversations.length === 0 && !showColleagues" class="chat-list__empty">
        Chưa có cuộc trò chuyện nào. Tìm đồng nghiệp hoặc tạo nhóm để bắt đầu.
      </p>
      <button
        v-for="(conversation, index) in visibleConversations"
        v-else
        :key="conversation.id"
        type="button"
        class="chat-item"
        :class="{
          'chat-item--unread': conversation.unread_count > 0,
          'chat-item--fresh': store.freshConversationId === conversation.id,
        }"
        :style="{ animationDelay: `${Math.min(index, 12) * 35}ms` }"
        @click="open(conversation.id)"
      >
        <span class="chat-item__avatar-wrap" aria-hidden="true">
          <img
            v-if="isGroup(conversation) && conversation.avatar_url"
            class="chat-item__avatar"
            :src="conversation.avatar_url"
            :alt="`Ảnh nhóm ${conversationName(conversation)}`"
          />
          <span v-else-if="isGroup(conversation)" class="chat-item__avatar chat-item__avatar--group">
            <AppIcon name="users" :size="16" :stroke-width="1.75" />
          </span>
          <img
            v-else-if="conversation.other_user?.avatar_url"
            class="chat-item__avatar"
            :src="conversation.other_user.avatar_url"
            :alt="`Ảnh đại diện của ${conversation.other_user.name}`"
          />
          <span v-else class="chat-item__avatar chat-item__avatar--placeholder">
            {{ initial(conversation.other_user?.name) }}
          </span>
        </span>
        <span class="chat-item__body">
          <span class="chat-item__row">
            <span class="chat-item__name">
              <AppIcon v-if="conversation.pinned_at" name="pin" :size="12" :stroke-width="1.75" />
              {{ conversationName(conversation) }}
              <span v-if="listMarks(conversation)" class="chat-item__marks">{{ listMarks(conversation) }}</span>
            </span>
            <span v-if="conversation.last_message" class="chat-item__time">
              {{ formatRelativeTime(conversation.last_message.created_at) }}
            </span>
          </span>
          <span v-if="peerMeta(conversation)" class="chat-item__meta">{{ peerMeta(conversation) }}</span>
          <span v-if="typingText(conversation)" class="chat-item__excerpt chat-item__excerpt--typing">
            <span class="chat-typing" aria-hidden="true"><span /><span /><span /></span>
            {{ typingText(conversation) }}
          </span>
          <span
            v-else
            class="chat-item__excerpt"
            :class="{
              'chat-item__excerpt--muted':
                !conversation.last_message
                || conversation.last_message.recalled
                || conversation.last_message.message_type === 'sticker',
              'chat-item__excerpt--bold': conversation.unread_count > 0,
            }"
          >
            {{ excerpt(conversation) }}
          </span>
        </span>
        <span v-if="conversation.unread_count > 0" class="chat-item__count">
          {{ conversation.unread_count > 99 ? '99+' : conversation.unread_count }}
        </span>
      </button>
    </div>

    <ChatGroupDialog :open="groupOpen" @close="groupOpen = false" />
  </div>
</template>

<style scoped>
.chat-list {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  height: 100%;
  overflow: hidden;
  animation: chat-screen-in 180ms ease both;
}

.chat-list--shell .chat-list__items {
  padding-top: 0.15rem;
}

.chat-list__toolbar {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  flex-shrink: 0;
  padding: var(--space-2) var(--space-3) 0;
}

.chat-list__group-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  flex-shrink: 0;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  cursor: pointer;
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
}

.chat-list__find {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  flex: 1;
  min-width: 0;
  margin: 0;
  padding: 0.45rem 0.65rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  color: var(--color-text-muted, var(--color-text));
}

.chat-list__find input {
  flex: 1;
  border: 0;
  background: transparent;
  color: var(--color-text);
}

.chat-list__items {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

.chat-list__empty {
  margin: 0;
  padding: var(--space-6) var(--space-4);
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.chat-list__empty--inline {
  padding: var(--space-3) var(--space-4);
  font-size: 0.8125rem;
}

.chat-list__section {
  margin: 0;
  padding: var(--space-3) var(--space-3) var(--space-1);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 700;
}

.chat-person {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  border: none;
  background: transparent;
  text-align: left;
  padding: 0.6rem var(--space-3);
  cursor: pointer;
  font-family: inherit;
  transition: background 120ms ease;
}

.chat-person:hover {
  background: var(--color-primary-surface);
}

.chat-person__icon {
  flex-shrink: 0;
  color: var(--color-primary);
}

.chat-people__more {
  display: block;
  width: calc(100% - 2 * var(--space-3));
  margin: var(--space-1) var(--space-3) var(--space-2);
  padding: 0.55rem var(--space-3);
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.chat-people__more:disabled {
  opacity: 0.6;
  cursor: default;
}

.chat-list__empty--loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
}

.chat-list__shimmer {
  display: block;
  width: 2.5rem;
  height: 0.35rem;
  border-radius: var(--radius-full);
  background: linear-gradient(
    90deg,
    var(--color-surface-muted) 0%,
    color-mix(in srgb, var(--color-primary) 12%, var(--color-surface-muted)) 50%,
    var(--color-surface-muted) 100%
  );
  background-size: 200% 100%;
  animation: chat-shimmer 1.4s ease-in-out infinite;
}

.chat-item {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  width: 100%;
  border: none;
  background: transparent;
  text-align: left;
  padding: 0.8rem var(--space-3);
  box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 85%, transparent);
  cursor: pointer;
  font-family: inherit;
  transition: background 140ms ease;
}

.chat-item:active {
  transform: scale(0.995);
}

.chat-item:hover {
  background: color-mix(in srgb, var(--color-surface-muted) 70%, transparent);
}

.chat-item--unread {
  background: color-mix(in srgb, var(--color-primary-surface) 65%, var(--color-surface));
}

.chat-item--unread::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0.65rem;
  bottom: 0.65rem;
  width: 3px;
  border-radius: 0 4px 4px 0;
  background: linear-gradient(
    180deg,
    var(--color-tertiary-500),
    var(--color-primary)
  );
}

.chat-item--fresh {
  animation: chat-item-fresh 2.4s ease;
}

@keyframes chat-item-fresh {
  0%,
  40% {
    background: var(--color-primary-surface);
  }
}

.chat-item__avatar-wrap {
  position: relative;
  flex-shrink: 0;
}

.chat-item__avatar {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-full);
  object-fit: cover;
  box-shadow:
    0 0 0 2px var(--color-surface),
    0 0 0 3px color-mix(in srgb, var(--color-tertiary-400) 35%, transparent);
}

.chat-item__avatar--placeholder,
.chat-item__avatar--group {
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(
    145deg,
    var(--color-primary-surface),
    color-mix(in srgb, var(--color-tertiary-100) 80%, var(--color-primary-surface))
  );
  color: var(--color-primary);
  font-size: 0.875rem;
  font-weight: 700;
}

.chat-item__body {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 0;
  flex: 1;
}

.chat-item__row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
}

.chat-item__marks {
  margin-left: 0.25rem;
}

.chat-item__name {
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
}

.chat-item--unread .chat-item__name {
  font-weight: 700;
}

.chat-item__meta {
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chat-item__time {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
}

.chat-item--unread .chat-item__time {
  color: var(--color-primary);
  font-weight: 600;
}

.chat-item__excerpt {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chat-item__excerpt--bold {
  color: var(--color-text);
  font-weight: 600;
}

.chat-item__excerpt--muted,
.chat-item__excerpt--typing {
  font-style: italic;
}

.chat-item__excerpt--typing {
  display: inline-flex;
  align-items: center;
  color: var(--color-primary);
  font-weight: 600;
}

.chat-item__count {
  flex-shrink: 0;
  min-width: 1.25rem;
  height: 1.25rem;
  margin-top: 0.15rem;
  padding: 0 0.35rem;
  border-radius: var(--radius-full);
  background: linear-gradient(145deg, var(--color-primary), var(--color-tertiary-600));
  color: var(--color-on-primary);
  font-size: 0.6875rem;
  font-weight: 700;
  line-height: 1.25rem;
  text-align: center;
  box-shadow: 0 2px 8px color-mix(in srgb, var(--color-primary) 35%, transparent);
}
</style>
