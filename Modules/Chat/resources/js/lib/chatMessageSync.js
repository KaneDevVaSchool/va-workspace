/**
 * Ghép và so khớp danh sách tin client ↔ metadata hội thoại (last_message, unread).
 */

/** Ghép hai danh sách tin theo id (incoming ghi đè khi trùng), sort tăng dần theo id. */
export function mergeMessageLists(existing, incoming) {
  const byId = new Map();
  for (const message of existing) {
    if (message?.id != null) byId.set(message.id, message);
  }
  for (const message of incoming) {
    if (message?.id != null) byId.set(message.id, message);
  }
  return [...byId.values()].sort((left, right) => Number(left.id) - Number(right.id));
}

export function newestCachedMessageId(cached) {
  if (!Array.isArray(cached) || cached.length === 0) return 0;
  return Number(cached[cached.length - 1].id) || 0;
}

export function conversationLastMessageId(conversation) {
  return Number(conversation?.last_message?.id) || 0;
}

/**
 * Metadata hội thoại báo có tin mới hơn cache hoặc còn chưa đọc mà thread chưa khớp.
 */
export function conversationNeedsMessageSync(conversation, cached) {
  if (!conversation) return false;
  const lastId = conversationLastMessageId(conversation);
  if (!Array.isArray(cached) || cached.length === 0) {
    return lastId > 0 || (conversation.unread_count ?? 0) > 0;
  }
  const newest = newestCachedMessageId(cached);
  if (lastId > newest) return true;
  if ((conversation.unread_count ?? 0) > 0 && lastId >= newest) return true;
  return false;
}
