import { defineStore } from 'pinia';
import {
  fetchUnreadCount,
  listConversations,
  listMessages,
  markRead as apiMarkRead,
  openConversation,
  sendMessage as apiSendMessage,
} from '../api/chatApi';

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

    async sendMessage(conversationId, text) {
      const message = await apiSendMessage(conversationId, text);
      this.appendMessage(conversationId, message);
      return message;
    },

    appendMessage(conversationId, message) {
      const existing = this.messagesByConversation[conversationId] ?? [];
      if (existing.some((m) => m.id === message.id)) return;
      this.messagesByConversation = {
        ...this.messagesByConversation,
        [conversationId]: [...existing, message],
      };

      const conversation = this.conversations.find((c) => c.id === conversationId);
      if (conversation) {
        conversation.last_message = {
          message: message.message,
          sender_id: message.sender.id,
          created_at: message.created_at,
        };
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
      this.appendMessage(conversationId, payload);

      const isViewingThisConversation =
        this.panelOpen && this.activeView === 'conversation' && this.activeConversationId === conversationId;

      if (isViewingThisConversation) {
        this.markRead(conversationId).catch(() => {});
        return;
      }

      const conversation = this.conversations.find((c) => c.id === conversationId);
      if (conversation) {
        conversation.unread_count = (conversation.unread_count || 0) + 1;
      }
      this.unreadTotal += 1;
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
