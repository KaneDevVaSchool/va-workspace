import DOMPurify from 'dompurify';

const SANITIZE_OPTIONS = {
  ALLOWED_TAGS: ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h1', 'h2', 'h3', 'ul', 'ol', 'li', 'a', 'span'],
  ALLOWED_ATTR: ['href', 'style', 'target', 'rel', 'class'],
};

export function sanitizeSocialHtml(html) {
  if (!html) return '';
  const trimmed = String(html).trim();
  if (!trimmed.startsWith('<')) {
    const escaped = trimmed
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
    return DOMPurify.sanitize(escaped.replace(/\n/g, '<br>'), SANITIZE_OPTIONS);
  }
  return DOMPurify.sanitize(trimmed, SANITIZE_OPTIONS);
}
