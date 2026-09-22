export function listConversations() {
  return window.axios.get('/api/chat/conversations').then((r) => r.data.conversations);
}

export function openConversation(userId) {
  return window.axios.post('/api/chat/conversations', { user_id: userId }).then((r) => r.data.conversation);
}

export function listMessages(conversationId, beforeId = null) {
  return window.axios
    .get(`/api/chat/conversations/${conversationId}/messages`, { params: beforeId ? { before_id: beforeId } : {} })
    .then((r) => r.data);
}

export function sendMessage(conversationId, payload) {
  const body = typeof payload === 'string' ? { message: payload } : payload;
  return window.axios
    .post(`/api/chat/conversations/${conversationId}/messages`, body)
    .then((r) => r.data.message);
}

export function editMessage(conversationId, messageId, message) {
  return window.axios
    .patch(`/api/chat/conversations/${conversationId}/messages/${messageId}`, { message })
    .then((r) => r.data.message);
}

export function recallMessage(conversationId, messageId) {
  return window.axios
    .post(`/api/chat/conversations/${conversationId}/messages/${messageId}/recall`)
    .then((r) => r.data.message);
}

export function hideMessage(conversationId, messageId) {
  return window.axios.delete(`/api/chat/conversations/${conversationId}/messages/${messageId}`);
}

export function markRead(conversationId) {
  return window.axios.post(`/api/chat/conversations/${conversationId}/read`).then((r) => r.data);
}

export function fetchUnreadCount() {
  return window.axios.get('/api/chat/unread-count').then((r) => r.data.unread_total ?? 0);
}

export function searchUsers(query) {
  return window.axios.get('/api/chat/users/search', { params: { q: query } }).then((r) => r.data.users);
}
