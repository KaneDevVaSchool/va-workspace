export const CHAT_REACTIONS = [
  { emoji: '👍', label: 'Thích' },
  { emoji: '❤️', label: 'Yêu thích' },
  { emoji: '😂', label: 'Haha' },
  { emoji: '😮', label: 'Wow' },
  { emoji: '😢', label: 'Buồn' },
  { emoji: '😡', label: 'Phẫn nộ' },
];

export function reactionLabel(emoji) {
  return CHAT_REACTIONS.find((item) => item.emoji === emoji)?.label ?? 'Cảm xúc';
}
