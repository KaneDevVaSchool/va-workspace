export function listConversations() {
  return window.axios.get('/api/chat/conversations').then((r) => r.data.conversations);
}

export function openConversation(userId) {
  return window.axios.post('/api/chat/conversations', { user_id: userId }).then((r) => r.data.conversation);
}

export function createGroup(payload) {
  return window.axios.post('/api/chat/conversations/groups', payload).then((r) => r.data.conversation);
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
  if (payload.voice_duration) form.append('voice_duration', String(payload.voice_duration));
  (payload.mention_ids || []).forEach((id) => form.append('mention_ids[]', String(id)));
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

export function clearHistory(conversationId) {
  return window.axios.delete(`/api/chat/conversations/${conversationId}/history`);
}

export function disbandGroup(conversationId) {
  return window.axios.delete(`/api/chat/conversations/${conversationId}`);
}

export function sendTyping(conversationId, active = true) {
  return window.axios.post(`/api/chat/conversations/${conversationId}/typing`, { active });
}

export function beatPresence() {
  return window.axios.post('/api/chat/presence');
}

export function updateGroupAvatar(conversationId, file) {
  const form = new FormData();
  form.append('avatar', file);
  return window.axios
    .post(`/api/chat/conversations/${conversationId}/avatar`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    .then((r) => r.data.conversation);
}

export function addMembers(conversationId, userIds) {
  return window.axios
    .post(`/api/chat/conversations/${conversationId}/members`, { user_ids: userIds })
    .then((r) => r.data.conversation);
}

export function removeMember(conversationId, userId) {
  return window.axios
    .delete(`/api/chat/conversations/${conversationId}/members/${userId}`)
    .then((r) => r.data);
}

export function transferOwner(conversationId, userId) {
  return window.axios
    .post(`/api/chat/conversations/${conversationId}/transfer`, { user_id: userId })
    .then((r) => r.data.conversation);
}

export function pinConversation(conversationId, pinned) {
  return window.axios
    .post(`/api/chat/conversations/${conversationId}/pin`, { pinned })
    .then((r) => r.data.conversation);
}

export function muteConversation(conversationId, hours) {
  return window.axios
    .post(`/api/chat/conversations/${conversationId}/mute`, { hours })
    .then((r) => r.data.conversation);
}

export function setDisappear(conversationId, seconds) {
  return window.axios
    .post(`/api/chat/conversations/${conversationId}/disappear`, { seconds })
    .then((r) => r.data.conversation);
}

export function pinChatMessage(conversationId, messageId) {
  return window.axios
    .post(`/api/chat/conversations/${conversationId}/pinned-message`, { message_id: messageId })
    .then((r) => r.data.conversation);
}

export function searchMessages(conversationId, query) {
  return window.axios
    .get(`/api/chat/conversations/${conversationId}/messages`, { params: { q: query } })
    .then((r) => r.data.messages);
}

export function listMedia(conversationId) {
  return window.axios
    .get(`/api/chat/conversations/${conversationId}/media`)
    .then((r) => r.data.files);
}

export function reactToMessage(conversationId, messageId, emoji) {
  return window.axios
    .post(`/api/chat/conversations/${conversationId}/messages/${messageId}/reactions`, { emoji })
    .then((r) => r.data.message);
}

export function votePoll(conversationId, messageId, optionId) {
  return window.axios
    .post(`/api/chat/conversations/${conversationId}/messages/${messageId}/vote`, { option_id: optionId })
    .then((r) => r.data.message);
}

export function fetchUnreadCount() {
  return window.axios.get('/api/chat/unread-count').then((r) => r.data.unread_total ?? 0);
}

/** Một trang kết quả tìm đồng nghiệp; `offset` là số người đã nhận từ máy chủ ở các trang trước. */
export function searchUsersPage(query, offset = 0) {
  return window.axios
    .get('/api/chat/users/search', { params: { q: query, offset } })
    .then((r) => ({ users: r.data.users ?? [], hasMore: Boolean(r.data.has_more) }));
}

export function searchUsers(query) {
  return searchUsersPage(query).then((page) => page.users);
}
