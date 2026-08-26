import DOMPurify from 'dompurify';
import { linkifyHashtags } from './linkifyHashtags.js';

const STICKER_ID_RE = /^[0-9a-f]{2,8}(?:_[0-9a-f]{2,8}){0,12}$/;

const SANITIZE_OPTIONS = {
  ALLOWED_TAGS: ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h1', 'h2', 'h3', 'ul', 'ol', 'li', 'a', 'span'],
  ALLOWED_ATTR: ['href', 'style', 'target', 'rel', 'class', 'data-mention-id', 'data-sticker', 'data-hashtag'],
  ADD_ATTR: ['data-mention-id', 'data-sticker', 'data-hashtag'],
};

function filterStickerIds(html) {
  return html.replace(/<span\b([^>]*)>/gi, (full, attrs) => {
    const match = attrs.match(/\sdata-sticker=(["'])([^"']*)\1/i);
    if (!match) return full;
    if (!STICKER_ID_RE.test(String(match[2]).toLowerCase())) {
      return `<span${attrs.replace(/\sdata-sticker=(["'])([^"']*)\1/i, '')}>`;
    }
    return full;
  });
}

export function sanitizeSocialHtml(html) {
  if (!html) return '';
  const trimmed = String(html).trim();
  if (!trimmed.startsWith('<')) {
    const escaped = trimmed
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
    return linkifyHashtags(filterStickerIds(DOMPurify.sanitize(escaped.replace(/\n/g, '<br>'), SANITIZE_OPTIONS)));
  }
  return linkifyHashtags(filterStickerIds(DOMPurify.sanitize(trimmed, SANITIZE_OPTIONS)));
}
