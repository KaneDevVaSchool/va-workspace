import { defineStore } from 'pinia';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import {
  editMessage as apiEditMessage,
  clearHistory as apiClearHistory,
  createGroup as apiCreateGroup,
  disbandGroup as apiDisbandGroup,
  listMedia as apiListMedia,
  addMembers as apiAddMembers,
  beatPresence as apiBeatPresence,
  muteConversation as apiMuteConversation,
  pinChatMessage as apiPinChatMessage,
  pinConversation as apiPinConversation,
  removeMember as apiRemoveMember,
  searchMessages as apiSearchMessages,
  setDisappear as apiSetDisappear,
  transferOwner as apiTransferOwner,
  updateGroupAvatar as apiUpdateGroupAvatar,
  updateGroupTitle as apiUpdateGroupTitle,
  reactToMessage as apiReactToMessage,
  votePoll as apiVotePoll,
  fetchUnreadCount,
  hideMessage as apiHideMessage,
  listConversations,
  listMessages,
  leaveViewing,
  markRead as apiMarkRead,
  openConversation,
  recallMessage as apiRecallMessage,
  sendMessage as apiSendMessage,
  touchViewing,
} from '../api/chatApi';

function withLocalReaction(message, emoji, userId) {
  const rows = (message.reactions || []).map((row) => ({
    emoji: row.emoji,
    user_ids: [...(row.user_ids || [])],
    users: [...(row.users || [])],
  }));
  const had = rows.some((row) => row.emoji === emoji && row.user_ids.includes(userId));
  for (const row of rows) {
    if (!row.user_ids.includes(userId)) continue;
    row.user_ids = row.user_ids.filter((id) => id !== userId);
    row.users = row.users.filter((user) => user.id !== userId);
  }
  if (!had) {
    const target = rows.find((row) => row.emoji === emoji);
    if (target) target.user_ids.push(userId);
    else rows.push({ emoji, user_ids: [userId], users: [] });
  }
  return {
    ...message,
    reactions: rows
      .filter((row) => row.user_ids.length > 0)
      .map((row) => ({ ...row, count: row.user_ids.length })),
  };
}

export function chatPreview(message) {
  if (message?.recalled_at) return 'Tin nhắn đã được thu hồi';
  if (message?.message_type === 'sticker') return 'Sticker';
  if (message?.message_type === 'voice') return 'Tin nhắn thoại';
  if (message?.message_type === 'poll') return 'Bình chọn';
  if (message?.message_type === 'system') return (message.message || '').trim();
  const text = (message?.message || '').trim();
  if (text) return text;
  const files = message?.attachments ?? [];
  if (files.length === 0) return '';
  if (files.every((file) => file.type === 'image')) {
    return files.length > 1 ? `${files.length} ảnh` : 'Ảnh';
  }
  return files.length > 1 ? `${files.length} tệp đính kèm` : 'Tệp đính kèm';
}

function lastMessageFrom(message) {
  return {
    id: message.id,
    message: chatPreview(message),
    message_type: message.message_type,
    recalled: Boolean(message.recalled_at),
    sender_id: message.sender?.id,
    sender_name: message.sender?.name ?? message.sender_name ?? null,
    created_at: message.created_at,
  };
}

function sortConversations(list) {
  return [...list].sort((left, right) => {
    const pin = Number(Boolean(right.pinned_at)) - Number(Boolean(left.pinned_at));
    if (pin !== 0) return pin;
    const rightTime = String(right.updated_at || right.last_message?.created_at || '');
    const leftTime = String(left.updated_at || left.last_message?.created_at || '');
    return rightTime.localeCompare(leftTime);
  });
}

let outboxFlushing = false;

function outboxMatchesMessage(item, message, conversationId) {
  if (!item || item.status !== 'sending' || item.conversationId !== conversationId || !message) return false;
  const senderId = message.sender?.id;
  if (item.senderId && senderId && Number(item.senderId) !== Number(senderId)) return false;
  const created = new Date(message.created_at).getTime();
  if (!Number.isNaN(created) && item.startedAt && created < item.startedAt - 2000) return false;
  if (item.kind === 'sticker') {
    return message.message_type === 'sticker' && message.sticker_id === item.stickerId;
  }
  const text = (message.message || '').trim();
  if (item.text) return text === item.text;
  return (message.attachments ?? []).length > 0 && item.fileCount > 0;
}

export const useChatStore = defineStore('chat', {
  state: () => ({
    conversations: [],
    messagesByConversation: {},
    hasMoreByConversation: {},
    activeConversationId: null,
    panelOpen: false,
    activeView: 'list',
    unreadTotal: 0,
    outbox: [],
    loadingConversations: false,
    loadingMessages: false,
    subscribedChannels: new Set(),
    inboxSubscribed: false,
    freshConversationId: null,
    typingByConversation: {},
  }),

  getters: {
    activeConversation(state) {
      return state.conversations.find((c) => c.id === state.activeConversationId) ?? null;
    },
    activeMessages(state) {
      return state.messagesByConversation[state.activeConversationId] ?? [];
    },
  },

  actions: {
    async ensureRealtimeSubscriptions() {
      const auth = useAuthStore();
      if (!auth.user?.id) return;

      if (this.conversations.length === 0 && !this.loadingConversations) {
        try {
          await this.fetchConversations();
          return;
        } catch {
          // Vẫn thử inbox nếu danh sách hội thoại lỗi tạm thời.
        }
      }

      this.subscribeToAllConversations();
      this.subscribeInbox();
    },

    async fetchConversations() {
      this.loadingConversations = true;
      try {
        const remote = await listConversations();
        this.conversations = this.mergeConversations(remote);
        this.recomputeUnreadTotal();
        this.subscribeToAllConversations();
        this.subscribeInbox();
      } finally {
        this.loadingConversations = false;
      }
    },

    async fetchUnreadTotal() {
      this.unreadTotal = await fetchUnreadCount();
    },

    recomputeUnreadTotal() {
      this.unreadTotal = this.conversations.reduce((sum, c) => sum + (c.unread_count || 0), 0);
    },

    async openWithUser(userId) {
      const conversation = await openConversation(userId);
      const existing = this.conversations.find((c) => c.id === conversation.id);
      if (!existing) {
        this.conversations.unshift(conversation);
      }
      this.subscribeToConversation(conversation.id);
      await this.openConversationView(conversation.id);
      return conversation;
    },

    async clearHistory(conversationId) {
      await apiClearHistory(conversationId);
      this.messagesByConversation = { ...this.messagesByConversation, [conversationId]: [] };
      this.hasMoreByConversation = { ...this.hasMoreByConversation, [conversationId]: false };
      const conversation = this.conversations.find((item) => item.id === conversationId);
      if (conversation) {
        conversation.last_message = null;
        conversation.unread_count = 0;
      }
      this.recomputeUnreadTotal();
      this.outbox
        .filter((item) => item.conversationId === conversationId && item.status !== 'sending')
        .forEach((item) => this.removeOutbox(item.localId));
    },

    async disbandGroup(conversationId) {
      await apiDisbandGroup(conversationId);
      this.forgetConversation(conversationId);
    },

    replaceConversation(conversation) {
      if (!conversation?.id) return;
      const index = this.conversations.findIndex((item) => item.id === conversation.id);
      if (index < 0) {
        this.conversations = sortConversations([conversation, ...this.conversations]);
        this.subscribeToConversation(conversation.id);
        return;
      }
      const next = [...this.conversations];
      next[index] = conversation;
      this.conversations = sortConversations(next);
    },

    async updateGroupAvatar(conversationId, file) {
      const conversation = await apiUpdateGroupAvatar(conversationId, file);
      this.replaceConversation(conversation);
      return conversation;
    },

    async updateGroupTitle(conversationId, title) {
      const conversation = await apiUpdateGroupTitle(conversationId, title);
      this.replaceConversation(conversation);
      return conversation;
    },

    async addMembers(conversationId, userIds) {
      const conversation = await apiAddMembers(conversationId, userIds);
      this.replaceConversation(conversation);
      return conversation;
    },

    async removeMember(conversationId, userId) {
      const result = await apiRemoveMember(conversationId, userId);
      if (result?.left) this.forgetConversation(conversationId);
      else if (result?.conversation) this.replaceConversation(result.conversation);
      return result;
    },

    async transferOwner(conversationId, userId) {
      const conversation = await apiTransferOwner(conversationId, userId);
      this.replaceConversation(conversation);
      return conversation;
    },

    async pinConversation(conversationId, pinned) {
      const conversation = await apiPinConversation(conversationId, pinned);
      this.replaceConversation(conversation);
    },

    async muteConversation(conversationId, hours) {
      const conversation = await apiMuteConversation(conversationId, hours);
      this.replaceConversation(conversation);
    },

    async setDisappear(conversationId, seconds) {
      const conversation = await apiSetDisappear(conversationId, seconds);
      this.replaceConversation(conversation);
    },

    async pinMessage(conversationId, messageId) {
      const conversation = await apiPinChatMessage(conversationId, messageId);
      this.replaceConversation(conversation);
    },

    async searchMessages(conversationId, query) {
      return apiSearchMessages(conversationId, query);
    },

    async listMedia(conversationId) {
      return apiListMedia(conversationId);
    },

    async votePoll(conversationId, messageId, optionId) {
      const message = await apiVotePoll(conversationId, messageId, optionId);
      this.replaceMessage(conversationId, message);
      return message;
    },

    async reactToMessage(conversationId, messageId, emoji) {
      const existing = this.messagesByConversation[conversationId] ?? [];
      const current = existing.find((message) => message.id === messageId) ?? null;
      const userId = useAuthStore().user?.id;
      if (current && userId) {
        this.replaceMessage(conversationId, withLocalReaction(current, emoji, userId));
      }
      try {
        const message = await apiReactToMessage(conversationId, messageId, emoji);
        this.replaceMessage(conversationId, message);
        return message;
      } catch (error) {
        if (current) this.replaceMessage(conversationId, current);
        throw error;
      }
    },

    beatPresence() {
      return apiBeatPresence().catch(() => {});
    },

    forgetConversation(conversationId) {
      if (this.activeConversationId === conversationId) {
        this.backToList();
      }
      this.conversations = this.conversations.filter((item) => item.id !== conversationId);
      const messages = { ...this.messagesByConversation };
      delete messages[conversationId];
      this.messagesByConversation = messages;
      const more = { ...this.hasMoreByConversation };
      delete more[conversationId];
      this.hasMoreByConversation = more;
      const typing = { ...this.typingByConversation };
      delete typing[conversationId];
      this.typingByConversation = typing;
      this.recomputeUnreadTotal();
    },

    async createGroup(title, userIds) {
      const conversation = await apiCreateGroup({ title, user_ids: userIds });
      const existing = this.conversations.find((item) => item.id === conversation.id);
      if (existing) {
        Object.assign(existing, conversation);
      } else {
        this.conversations.unshift(conversation);
      }
      this.subscribeToConversation(conversation.id);
      await this.openConversationView(conversation.id);
      return conversation;
    },

    mergeConversations(remote) {
      const byId = new Map(remote.map((conversation) => [conversation.id, conversation]));
      for (const local of this.conversations) {
        const server = byId.get(local.id);
        if (!server) {
          byId.set(local.id, local);
          continue;
        }
        const localLast = local.last_message?.id ?? 0;
        const serverLast = server.last_message?.id ?? 0;
        if (localLast > serverLast) {
          server.last_message = local.last_message;
          server.unread_count = Math.max(server.unread_count || 0, local.unread_count || 0);
        }
      }
      return [...byId.values()];
    },

    async openConversationView(conversationId) {
      this.panelOpen = true;
      this.activeConversationId = conversationId;
      this.activeView = 'conversation';
      if (!this.messagesByConversation[conversationId]) {
        await this.fetchMessages(conversationId);
      }
      await this.markRead(conversationId);
      touchViewing(conversationId).catch(() => {});
    },

    backToList() {
      const leaving = this.activeConversationId;
      this.activeView = 'list';
      this.activeConversationId = null;
      if (leaving) leaveViewing(leaving).catch(() => {});
    },

    async fetchMessages(conversationId, beforeId = null) {
      this.loadingMessages = true;
      try {
        const { messages, has_more: hasMore } = await listMessages(conversationId, beforeId);
        const existing = this.messagesByConversation[conversationId] ?? [];
        const merged = beforeId ? [...messages.slice().reverse(), ...existing] : messages.slice().reverse();
        this.messagesByConversation = { ...this.messagesByConversation, [conversationId]: merged };
        this.hasMoreByConversation = { ...this.hasMoreByConversation, [conversationId]: hasMore };
      } finally {
        this.loadingMessages = false;
      }
    },

    async sendMessage(conversationId, payload) {
      const message = await apiSendMessage(conversationId, payload);
      this.appendMessage(conversationId, message);
      return message;
    },

    enqueueOutbox(item) {
      this.outbox = [...this.outbox, { ...item, status: 'queued' }];
      void this.flushOutbox();
    },

    patchOutbox(localId, patch) {
      this.outbox = this.outbox.map((item) => (item.localId === localId ? { ...item, ...patch } : item));
    },

    removeOutbox(localId) {
      const item = this.outbox.find((row) => row.localId === localId);
      for (const url of item?.previews ?? []) {
        if (url) URL.revokeObjectURL(url);
      }
      this.outbox = this.outbox.filter((row) => row.localId !== localId);
    },

    retryOutbox(localId) {
      const item = this.outbox.find((row) => row.localId === localId);
      if (!item || item.status !== 'failed') return;
      this.patchOutbox(localId, { status: 'queued', error: '' });
      void this.flushOutbox();
    },

    settleOutbox(conversationId, message) {
      const item = this.outbox.find((row) => outboxMatchesMessage(row, message, conversationId));
      if (item) this.removeOutbox(item.localId);
    },

    async flushOutbox() {
      if (outboxFlushing) return;
      outboxFlushing = true;
      try {
        for (;;) {
          const item = this.outbox.find((row) => row.status === 'queued');
          if (!item) break;
          this.patchOutbox(item.localId, { status: 'sending' });
          try {
            await this.sendMessage(item.conversationId, item.payload);
            this.removeOutbox(item.localId);
          } catch (error) {
            const still = this.outbox.some((row) => row.localId === item.localId);
            if (!still) continue;
            const message = error?.response?.data?.message || 'Không gửi được tin nhắn.';
            this.patchOutbox(item.localId, { status: 'failed', error: message });
            showClientToast('error', message);
          }
        }
      } finally {
        outboxFlushing = false;
        if (this.outbox.some((row) => row.status === 'queued')) void this.flushOutbox();
      }
    },

    async editMessage(conversationId, messageId, text) {
      const message = await apiEditMessage(conversationId, messageId, text);
      this.replaceMessage(conversationId, message);
      return message;
    },

    async recallMessage(conversationId, messageId) {
      const message = await apiRecallMessage(conversationId, messageId);
      this.replaceMessage(conversationId, message);
      return message;
    },

    async hideMessage(conversationId, messageId) {
      await apiHideMessage(conversationId, messageId);
      this.removeMessage(conversationId, messageId);
    },

    appendMessage(conversationId, message) {
      const existing = this.messagesByConversation[conversationId] ?? [];
      if (existing.some((m) => m.id === message.id)) {
        this.settleOutbox(conversationId, message);
        return;
      }
      this.messagesByConversation = {
        ...this.messagesByConversation,
        [conversationId]: [...existing, message],
      };
      this.syncLastMessage(conversationId, message, true);
      this.settleOutbox(conversationId, message);
    },

    replaceMessage(conversationId, message) {
      const existing = this.messagesByConversation[conversationId] ?? [];
      if (existing.some((m) => m.id === message.id)) {
        this.messagesByConversation = {
          ...this.messagesByConversation,
          [conversationId]: existing.map((m) => (m.id === message.id ? message : m)),
        };
      }
      this.syncLastMessage(conversationId, message, false);
    },

    removeMessage(conversationId, messageId) {
      const existing = this.messagesByConversation[conversationId] ?? [];
      const next = existing.filter((m) => m.id !== messageId);
      this.messagesByConversation = { ...this.messagesByConversation, [conversationId]: next };

      const conversation = this.conversations.find((c) => c.id === conversationId);
      if (!conversation || conversation.last_message?.id !== messageId) return;
      const last = next[next.length - 1];
      conversation.last_message = last ? lastMessageFrom(last) : null;
    },

    syncLastMessage(conversationId, message, force) {
      const conversation = this.conversations.find((c) => c.id === conversationId);
      if (!conversation) return;
      const currentId = conversation.last_message?.id ?? 0;
      if (!force && message.id < currentId) return;
      conversation.last_message = lastMessageFrom(message);
      if (force) {
        this.conversations = [
          conversation,
          ...this.conversations.filter((c) => c.id !== conversationId),
        ];
      }
    },

    async markRead(conversationId) {
      await apiMarkRead(conversationId);
      const conversation = this.conversations.find((c) => c.id === conversationId);
      if (conversation) {
        conversation.unread_count = 0;
      }
      this.recomputeUnreadTotal();
    },

    handleIncomingMessage(conversationId, payload, options = {}) {
      const existing = this.messagesByConversation[conversationId] ?? [];
      const known = existing.some((m) => m.id === payload.id);
      this.appendMessage(conversationId, payload);
      if (known) return;

      const pageVisible = typeof document === 'undefined' || !document.hidden;
      const isViewingThisConversation =
        pageVisible
        && this.panelOpen
        && this.activeView === 'conversation'
        && this.activeConversationId === conversationId;

      if (isViewingThisConversation) {
        this.markRead(conversationId).catch(() => {});
        return;
      }

      const auth = useAuthStore();
      if (payload.sender?.id && payload.sender.id !== auth.user?.id) {
        if (!options.fromInbox) {
          const conversation = this.conversations.find((c) => c.id === conversationId);
          if (conversation) {
            conversation.unread_count = (conversation.unread_count || 0) + 1;
          }
          this.unreadTotal += 1;
        } else {
          this.recomputeUnreadTotal();
        }
        const name = payload.sender?.name || 'Đồng nghiệp';
        showClientToast('info', `${name}: ${chatPreview(payload)}`, { duration: 5000 });
      }
    },

    subscribeToAllConversations() {
      this.conversations.forEach((c) => this.subscribeToConversation(c.id));
    },

    subscribeToConversation(conversationId) {
      if (!window.Echo || this.subscribedChannels.has(conversationId)) return;

      window.Echo.private(`chat.conversation.${conversationId}`)
        .listen('.message.sent', (payload) => {
          this.handleIncomingMessage(conversationId, payload);
        })
        .listen('.message.updated', (payload) => {
          this.replaceMessage(conversationId, payload);
        })
        .listen('.message.read', (payload) => {
          this.applyReadReceipt(conversationId, payload);
        })
        .listen('.user.typing', (payload) => {
          this.applyTyping(conversationId, payload);
        });

      this.subscribedChannels.add(conversationId);
    },

    togglePanel() {
      this.panelOpen = !this.panelOpen;
      if (this.panelOpen && this.conversations.length === 0) {
        this.fetchConversations().catch(() => {});
      }
    },

    closePanel() {
      const leaving = this.activeView === 'conversation' ? this.activeConversationId : null;
      this.panelOpen = false;
      if (leaving) leaveViewing(leaving).catch(() => {});
    },

    handleInbox(payload) {
      const conversation = payload?.conversation;
      const message = payload?.message;
      if (!conversation?.id) return;

      const known = this.conversations.some((item) => item.id === conversation.id);
      if (known && message?.id) return;
      if (known) {
        this.replaceConversation(conversation);
        return;
      }

      this.conversations = sortConversations([conversation, ...this.conversations]);
      this.subscribeToConversation(conversation.id);
      if (!message?.id) return;

      this.freshConversationId = conversation.id;
      window.setTimeout(() => {
        if (this.freshConversationId === conversation.id) this.freshConversationId = null;
      }, 2400);
      this.handleIncomingMessage(conversation.id, message, { fromInbox: true });
    },

    subscribeInbox() {
      const auth = useAuthStore();
      const userId = auth.user?.id;
      if (!window.Echo || !userId || this.inboxSubscribed) return;

      window.Echo.private(`chat.inbox.${userId}`)
        .listen('.inbox.updated', (payload) => {
          this.handleInbox(payload);
        })
        .listen('.conversation.removed', (payload) => {
          const id = Number(payload?.conversation_id);
          if (id > 0) this.forgetConversation(id);
        });
      this.inboxSubscribed = true;
    },

    applyReadReceipt(conversationId, payload) {
      const auth = useAuthStore();
      if (!payload?.read_at || payload.user_id === auth.user?.id) return;
      const conversation = this.conversations.find((item) => item.id === conversationId);
      if (conversation && conversation.type !== 'group') conversation.other_read_at = payload.read_at;
      const ids = new Set((payload.message_ids || []).map((id) => Number(id)));
      if (ids.size === 0) return;
      const list = this.messagesByConversation[conversationId];
      if (!list) return;
      const member = conversation?.members?.find((item) => item.id === payload.user_id);
      const reader = {
        id: payload.user_id,
        name: payload.reader_name || member?.name || 'Đồng nghiệp',
        avatar_url: payload.reader_avatar_url ?? member?.avatar_url ?? null,
      };
      for (const message of list) {
        if (!ids.has(message.id)) continue;
        const reads = message.reads || [];
        if (reads.some((item) => item.id === reader.id)) continue;
        message.reads = [...reads, reader];
      }
    },

    applyTyping(conversationId, payload) {
      const auth = useAuthStore();
      if (!payload?.user_id || payload.user_id === auth.user?.id) return;
      const current = this.typingByConversation[conversationId];
      const users = (current?.users ?? []).filter((user) => user.id !== payload.user_id && user.until > Date.now());
      if (payload.active === false) {
        const next = { ...this.typingByConversation };
        if (users.length === 0) delete next[conversationId];
        else next[conversationId] = { users };
        this.typingByConversation = next;
        return;
      }
      const until = Date.now() + 4000;
      users.push({
        id: payload.user_id,
        name: payload.name || 'Đồng nghiệp',
        until,
      });
      this.typingByConversation = {
        ...this.typingByConversation,
        [conversationId]: { users },
      };
      window.setTimeout(() => {
        const slot = this.typingByConversation[conversationId];
        if (!slot?.users) return;
        const alive = slot.users.filter((user) => user.until > Date.now());
        const next = { ...this.typingByConversation };
        if (alive.length === 0) delete next[conversationId];
        else next[conversationId] = { users: alive };
        this.typingByConversation = next;
      }, 4000);
    },
  },
});
