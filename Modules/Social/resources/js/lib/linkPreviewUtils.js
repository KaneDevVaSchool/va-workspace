const VIDEO_HOST_PATTERN = /(youtube\.com|youtube-nocookie\.com|youtu\.be|facebook\.com|fb\.watch|tiktok\.com)/i;
const YOUTUBE_ID = /^[A-Za-z0-9_-]{11}$/;
const LOW_RES_YOUTUBE_THUMB = /\/(hqdefault|mqdefault|default|0|1|2|3)\.jpg(?:$|\?)/i;

function stripWww(hostname) {
  return String(hostname || '').toLowerCase().replace(/^www\./, '');
}

function isYoutubeHost(hostname) {
  const host = stripWww(hostname);
  return host === 'youtu.be'
    || host === 'youtube.com'
    || host === 'youtube-nocookie.com'
    || host.endsWith('.youtube.com')
    || host.endsWith('.youtube-nocookie.com');
}

function parseYoutubeTimestamp(raw) {
  const value = String(raw || '').trim().toLowerCase();
  if (!value) return null;
  if (/^\d+$/.test(value)) {
    const seconds = Number(value);
    return seconds > 0 ? seconds : null;
  }
  const match = value.match(/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/);
  if (!match || (!match[1] && !match[2] && !match[3])) return null;
  const seconds = (Number(match[1] || 0) * 3600) + (Number(match[2] || 0) * 60) + Number(match[3] || 0);
  return seconds > 0 ? seconds : null;
}

function youtubeStartSeconds(rawUrl) {
  try {
    const parsed = new URL(rawUrl);
    const fromQuery = parsed.searchParams.get('t') || parsed.searchParams.get('start');
    if (fromQuery) return parseYoutubeTimestamp(fromQuery);
    const fragment = new URLSearchParams(parsed.hash.replace(/^#/, ''));
    return parseYoutubeTimestamp(fragment.get('t') || '');
  } catch {
    return null;
  }
}

export function extractYoutubeVideoId(rawUrl) {
  try {
    const parsed = new URL(rawUrl);
    if (!isYoutubeHost(parsed.hostname)) return null;
    const host = stripWww(parsed.hostname);
    if (host === 'youtu.be') {
      const id = (parsed.pathname.split('/').filter(Boolean)[0] || '');
      return YOUTUBE_ID.test(id) ? id : null;
    }
    const fromQuery = parsed.searchParams.get('v');
    if (fromQuery && YOUTUBE_ID.test(fromQuery)) return fromQuery;
    const match = parsed.pathname.match(/\/(?:embed|live|shorts|v)\/([A-Za-z0-9_-]{11})(?:\/|$)/);
    return match ? match[1] : null;
  } catch {
    return null;
  }
}

/**
 * URL player dựng lúc bấm phát. `origin` lấy từ trang đang mở — không lưu DB,
 * vì localhost và production khác origin. Thiếu Referer / origin → YouTube Error 153.
 */
export function buildYoutubeEmbedSrc(rawUrl, { autoplay = false } = {}) {
  const id = extractYoutubeVideoId(rawUrl);
  if (!id) return null;
  const params = new URLSearchParams({
    playsinline: '1',
    rel: '0',
    hl: 'vi',
  });
  if (autoplay) params.set('autoplay', '1');
  const start = youtubeStartSeconds(rawUrl);
  if (start) params.set('start', String(start));
  if (typeof window !== 'undefined' && window.location?.origin) {
    params.set('origin', window.location.origin);
  }
  return `https://www.youtube.com/embed/${id}?${params.toString()}`;
}

export function youtubeThumbnailCandidates(rawUrl, storedThumb) {
  const id = extractYoutubeVideoId(rawUrl);
  const hi = id
    ? [
      `https://i.ytimg.com/vi/${id}/maxresdefault.jpg`,
      `https://i.ytimg.com/vi/${id}/sddefault.jpg`,
    ]
    : [];
  const safe = id ? [`https://i.ytimg.com/vi/${id}/hqdefault.jpg`] : [];
  const stored = storedThumb || '';
  const ordered = stored && !LOW_RES_YOUTUBE_THUMB.test(stored)
    ? [stored, ...hi, ...safe]
    : [...hi, ...safe, ...(stored ? [stored] : [])];
  return [...new Set(ordered.filter(Boolean))];
}

export function preconnectYoutubePlayer() {
  if (typeof document === 'undefined') return;
  const href = 'https://www.youtube.com';
  if (document.head.querySelector(`link[data-yt-preconnect="${href}"]`)) return;
  const link = document.createElement('link');
  link.rel = 'preconnect';
  link.href = href;
  link.dataset.ytPreconnect = href;
  document.head.appendChild(link);
}

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
