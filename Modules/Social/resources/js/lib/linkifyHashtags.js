const HASHTAG_RE = /(?<![\p{L}\p{N}_/#])#([\p{L}\p{N}_]{1,64})/gu;

function shouldSkip(element) {
  return Boolean(
    element.closest?.('a, .mention, .hashtag, [data-sticker], [data-mention-id], [data-hashtag]'),
  );
}

function wrapTextNode(node) {
  const text = node.nodeValue ?? '';
  if (!text.includes('#')) return;

  HASHTAG_RE.lastIndex = 0;
  if (!HASHTAG_RE.test(text)) return;

  HASHTAG_RE.lastIndex = 0;
  const frag = document.createDocumentFragment();
  let last = 0;
  let match = HASHTAG_RE.exec(text);
  while (match) {
    if (match.index > last) {
      frag.appendChild(document.createTextNode(text.slice(last, match.index)));
    }
    const span = document.createElement('span');
    span.className = 'hashtag';
    span.setAttribute('data-hashtag', match[1].toLowerCase());
    span.textContent = match[0];
    frag.appendChild(span);
    last = match.index + match[0].length;
    match = HASHTAG_RE.exec(text);
  }
  if (last < text.length) {
    frag.appendChild(document.createTextNode(text.slice(last)));
  }
  node.parentNode?.replaceChild(frag, node);
}

function walk(node) {
  if (node.nodeType === Node.ELEMENT_NODE) {
    if (shouldSkip(node)) return;
    [...node.childNodes].forEach(walk);
    return;
  }
  if (node.nodeType === Node.TEXT_NODE) {
    wrapTextNode(node);
  }
}

export function linkifyHashtags(html) {
  if (!html || typeof document === 'undefined') return html;
  const root = document.createElement('div');
  root.innerHTML = html;
  walk(root);
  return root.innerHTML;
}
