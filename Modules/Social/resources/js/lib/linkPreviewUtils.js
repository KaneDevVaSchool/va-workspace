const VIDEO_HOST_PATTERN = /(youtube\.com|youtu\.be|facebook\.com|fb\.watch|tiktok\.com)/i;

export function normalizeUrl(raw) {
  return String(raw || '').replace(/[.,;:!?)'"]+$/, '');
}

export function extractHttpUrls(text) {
  if (!text) return [];
  return Array.from(String(text).matchAll(/https?:\/\/[^\s<>"]+/gi)).map((m) => normalizeUrl(m[0]));
}

export function isPreviewableLink(url) {
  try {
    const parsed = new URL(url);
    if (!/^https?:$/i.test(parsed.protocol)) return false;
    if (!parsed.hostname) return false;
    if (VIDEO_HOST_PATTERN.test(parsed.hostname)) return true;
    if (typeof window !== 'undefined' && parsed.origin === window.location.origin) {
      return true;
    }
    return parsed.protocol === 'https:' || parsed.protocol === 'http:';
  } catch {
    return false;
  }
}

export function previewableUrlsFromText(text, limit = 1) {
  const seen = new Set();
  const out = [];
  for (const url of extractHttpUrls(text)) {
    if (!isPreviewableLink(url) || seen.has(url)) continue;
    seen.add(url);
    out.push(url);
    if (out.length >= limit) break;
  }
  return out;
}

const previewCache = new Map();
const inflight = new Map();

export async function fetchLinkPreview(url) {
  const key = normalizeUrl(url);
  if (previewCache.has(key)) {
    return previewCache.get(key);
  }
  if (inflight.has(key)) {
    return inflight.get(key);
  }

  const task = (async () => {
    try {
      const { data } = await window.axios.post('/api/social/link-preview', { url: key });
      const preview = data.preview ? { ...data.preview, url: key } : null;
      previewCache.set(key, preview);
      return preview;
    } catch {
      previewCache.set(key, null);
      return null;
    } finally {
      inflight.delete(key);
    }
  })();

  inflight.set(key, task);
  return task;
}
