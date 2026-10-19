import DOMPurify from 'dompurify';

const BLOCK_TAGS = new Set(['P', 'H1', 'H2', 'H3', 'H4', 'LI', 'DIV', 'BLOCKQUOTE', 'TR', 'PRE']);

/**
 * Rút gọn HTML bài đăng bảng tin thành dòng tiêu đề + đoạn mô tả (plain text).
 */
export function postContentSnippet(html) {
  if (typeof document === 'undefined') {
    const text = String(html ?? '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    return { heading: text, excerpt: '' };
  }

  const root = document.createElement('div');
  root.innerHTML = DOMPurify.sanitize(html ?? '');

  const chunks = [];

  function walk(node) {
    if (node.nodeType === Node.TEXT_NODE) {
      const text = (node.textContent ?? '').replace(/\s+/g, ' ');
      if (text) chunks.push(text);
      return;
    }
    if (node.nodeType !== Node.ELEMENT_NODE) return;

    const tag = node.tagName;
    if (tag === 'BR') {
      chunks.push('\n');
      return;
    }
    if (BLOCK_TAGS.has(tag) && chunks.length && chunks[chunks.length - 1] !== '\n') {
      chunks.push('\n');
    }
    for (const child of node.childNodes) walk(child);
    if (BLOCK_TAGS.has(tag)) chunks.push('\n');
  }

  walk(root);

  const lines = chunks
    .join('')
    .replace(/https?:\/\/\S+/gi, ' ')
    .split('\n')
    .map((line) => line.replace(/\s+/g, ' ').trim())
    .filter(Boolean);

  return {
    heading: lines[0] ?? '',
    excerpt: lines.slice(1).join(' '),
  };
}

export function postDisplayHeading(post) {
  const pollTitle = post?.poll?.title?.trim();
  if (pollTitle) return pollTitle;

  const { heading } = postContentSnippet(post?.content);
  if (heading) return heading;

  return 'Thông báo bảng tin';
}
