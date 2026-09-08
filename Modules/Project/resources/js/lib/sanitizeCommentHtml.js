// Rút gọn từ Modules/Social/resources/js/lib/sanitizeSocialHtml.js: bỏ hẳn
// linkify hashtag và filter sticker id — Task/Project không cần 2 tính năng
// này, chỉ giữ rich-text (bold/italic/underline/màu chữ/link) + mention.
import DOMPurify from 'dompurify';

const SANITIZE_OPTIONS = {
  ALLOWED_TAGS: ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h2', 'h3', 'ul', 'ol', 'li', 'a', 'span'],
  ALLOWED_ATTR: ['href', 'style', 'target', 'rel', 'class', 'data-mention-id'],
  ADD_ATTR: ['data-mention-id'],
};

export function sanitizeCommentHtml(html) {
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
