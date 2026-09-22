import { defineStore } from 'pinia';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import {
  editMessage as apiEditMessage,
  fetchUnreadCount,
  hideMessage as apiHideMessage,
  listConversations,
  listMessages,
  markRead as apiMarkRead,
  openConversation,
  recallMessage as apiRecallMessage,
  sendMessage as apiSendMessage,
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
        this.conversations = await listConversations();
        this.recomputeUnreadTotal();
        this.subscribeToAllConversations();
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

    async openConversationView(conversationId) {
      this.panelOpen = true;
      this.activeConversationId = conversationId;
      this.activeView = 'conversation';
      if (!this.messagesByConversation[conversationId]) {
        await this.fetchMessages(conversationId);
      }
      await this.markRead(conversationId);
    },

    backToList() {
      this.activeView = 'list';
      this.activeConversationId = null;
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

    handleIncomingMessage(conversationId, payload) {
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
        const conversation = this.conversations.find((c) => c.id === conversationId);
        if (conversation) {
          conversation.unread_count = (conversation.unread_count || 0) + 1;
        }
        this.unreadTotal += 1;
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
        .listen('.message.read', () => {
          // Điểm mở rộng cho trạng thái "đã xem" ở phase sau.
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
      this.panelOpen = false;
    },
  },
});
