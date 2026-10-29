/** Tránh response HTTP cũ ghi đè list sau khi đã có fetch mới hơn (race loadOlder / refresh / mở thread). */

const epochByConversation = new Map();

export function bumpMessageFetchEpoch(conversationId) {
  const next = (epochByConversation.get(conversationId) ?? 0) + 1;
  epochByConversation.set(conversationId, next);
  return next;
}

export function isMessageFetchEpochCurrent(conversationId, epoch) {
  return epochByConversation.get(conversationId) === epoch;
}

export function resetMessageFetchEpochs() {
  epochByConversation.clear();
}
