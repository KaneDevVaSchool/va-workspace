export const REACTIONS = [
  { type: 'like', emoji: '👍', label: 'Thích' },
  { type: 'love', emoji: '❤️', label: 'Yêu thích' },
  { type: 'haha', emoji: '😂', label: 'Haha' },
  { type: 'wow', emoji: '😮', label: 'Wow' },
  { type: 'sad', emoji: '😢', label: 'Buồn' },
  { type: 'angry', emoji: '😡', label: 'Phẫn nộ' },
];

export function reactionByType(type) {
  return REACTIONS.find((r) => r.type === type) ?? null;
}
