export function mentionUserIdFromEvent(event) {
  const el = event.target?.closest?.('.mention[data-mention-id]');
  if (!el) return null;
  const id = Number(el.getAttribute('data-mention-id'));
  return Number.isFinite(id) && id > 0 ? id : null;
}
