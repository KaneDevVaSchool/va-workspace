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
  if (typeof payload === 'string' || !payload?.files?.length) {
    const body = typeof payload === 'string' ? { message: payload } : { ...payload };
    delete body.files;
    return window.axios
      .post(`/api/chat/conversations/${conversationId}/messages`, body)
      .then((r) => r.data.message);
  }

  const form = new FormData();
  if (payload.message) form.append('message', payload.message);
  if (payload.reply_to_id) form.append('reply_to_id', String(payload.reply_to_id));
  if (payload.message_type) form.append('message_type', payload.message_type);
  if (payload.sticker_id) form.append('sticker_id', payload.sticker_id);
  payload.files.forEach((file) => form.append('attachments[]', file));
  return window.axios
    .post(`/api/chat/conversations/${conversationId}/messages`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
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

export function touchViewing(conversationId) {
  return window.axios.post(`/api/chat/conversations/${conversationId}/viewing`);
}

export function leaveViewing(conversationId) {
  return window.axios.delete(`/api/chat/conversations/${conversationId}/viewing`);
}

export function sendTyping(conversationId) {
  return window.axios.post(`/api/chat/conversations/${conversationId}/typing`);
}

export function fetchUnreadCount() {
  return window.axios.get('/api/chat/unread-count').then((r) => r.data.unread_total ?? 0);
}

export function searchUsers(query) {
  return window.axios.get('/api/chat/users/search', { params: { q: query } }).then((r) => r.data.users);
}
