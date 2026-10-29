export const CHAT_MOODS = [
  { id: 'coffee', emoji: '☕', label: 'Đang họp' },
  { id: 'book', emoji: '📚', label: 'Soạn bài' },
  { id: 'run', emoji: '🏃', label: 'Đi sân' },
  { id: 'lunch', emoji: '🍜', label: 'Đi ăn' },
  { id: 'focus', emoji: '🎯', label: 'Đang tập trung' },
];

export const CHAT_QUICK_REPLIES = [
  'Ok ạ',
  'Em nhận rồi',
  'Cảm ơn anh/chị',
  'Chiều em gửi lại',
  'Đang làm ạ',
];

export const CHAT_POLL_PRESETS = [
  { label: 'Trưa ăn gì?', question: 'Trưa nay ăn gì?', options: ['Cơm', 'Bún', 'Gọi đồ'] },
  { label: 'Giờ họp', question: 'Họp vào khung giờ nào?', options: ['14:00', '15:00', 'Để mai'] },
  { label: 'Team building', question: 'Team building đi đâu?', options: ['Biển', 'Núi', 'Ở nhà'] },
];

export const CHAT_ACCENTS = [
  { id: 'coral', label: 'Đỏ' },
  { id: 'gold', label: 'Vàng' },
  { id: 'teal', label: 'Xanh' },
  { id: 'violet', label: 'Tím' },
  { id: 'leaf', label: 'Lá' },
];

export const BIRTHDAY_STICKER = { id: 'va_03', emoji: 'Vui mừng' };

export function moodById(id) {
  if (!id) return null;
  return CHAT_MOODS.find((item) => item.id === id) ?? null;
}
