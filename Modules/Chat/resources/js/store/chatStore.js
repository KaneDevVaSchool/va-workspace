import { defineStore } from 'pinia';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import {
  editMessage as apiEditMessage,
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

export function chatPreview(message) {
  if (message?.recalled_at) return 'Tin nhắn đã được thu hồi';
  if (message?.message_type === 'sticker') return 'Sticker';
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
    created_at: message.created_at,
  };
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
      if (existing.some((m) => m.id === message.id)) return;
      this.messagesByConversation = {
        ...this.messagesByConversation,
        [conversationId]: [...existing, message],
      };
      this.syncLastMessage(conversationId, message, true);
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

      const isViewingThisConversation =
        this.panelOpen && this.activeView === 'conversation' && this.activeConversationId === conversationId;

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
      if (!conversation?.id || !message?.id) return;

      const known = this.conversations.some((item) => item.id === conversation.id);
      if (known) return;

      this.conversations = [conversation, ...this.conversations];
      this.subscribeToConversation(conversation.id);
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

      window.Echo.private(`chat.inbox.${userId}`).listen('.inbox.updated', (payload) => {
        this.handleInbox(payload);
      });
      this.inboxSubscribed = true;
    },

    applyReadReceipt(conversationId, payload) {
      const auth = useAuthStore();
      if (!payload?.read_at || payload.user_id === auth.user?.id) return;
      const conversation = this.conversations.find((item) => item.id === conversationId);
      if (conversation) conversation.other_read_at = payload.read_at;
    },

    applyTyping(conversationId, payload) {
      const auth = useAuthStore();
      if (!payload?.user_id || payload.user_id === auth.user?.id) return;
      const until = Date.now() + 3000;
      this.typingByConversation = { ...this.typingByConversation, [conversationId]: until };
      window.setTimeout(() => {
        if (this.typingByConversation[conversationId] === until) {
          const next = { ...this.typingByConversation };
          delete next[conversationId];
          this.typingByConversation = next;
        }
      }, 3000);
    },
  },
});
