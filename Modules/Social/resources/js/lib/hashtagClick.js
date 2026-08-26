export function hashtagFromEvent(event) {
  const el = event.target?.closest?.('.hashtag[data-hashtag]');
  if (!el) return null;
  const name = String(el.getAttribute('data-hashtag') || '').trim().toLowerCase();
  return name || null;
}

export function usageLabel(count) {
  const n = Number(count) || 0;
  return `${new Intl.NumberFormat('vi-VN').format(n)} lượt gắn`;
}
