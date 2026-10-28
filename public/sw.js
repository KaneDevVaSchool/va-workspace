// VA Workspace — service worker
// v15: không tự skipWaiting (banner "Có bản cập nhật" hoạt động, không reload giữa chừng),
//      cache shell chỉ nhận HTML hợp lệ, click thông báo điều hướng bằng postMessage.
//
// Chiến lược:
// - App shell (navigate trong SPA): network-first (timeout ngắn), cache theo pathname
//   + bản fallback "/" luôn là HTML mới nhất. File tải về (/storage/...) không đụng tới.
// - Asset build (/build/..., content-hash trong tên file): cache-first —
//   file không bao giờ đổi nội dung dưới 1 tên, an toàn cache dài hạn.
// - Ảnh/icon tĩnh (/images/...): stale-while-revalidate — hiện ngay bản cache,
//   âm thầm lấy bản mới cho lần sau.
// - API (/api/...): không can thiệp — luôn đi thẳng mạng, không cache dữ liệu nhạy cảm.

const CACHE_VERSION = 'v15';
const SHELL_CACHE = `va-shell-${CACHE_VERSION}`;
const ASSET_CACHE = `va-assets-${CACHE_VERSION}`;
const IMAGE_CACHE = `va-images-${CACHE_VERSION}`;
const CURRENT_CACHES = [SHELL_CACHE, ASSET_CACHE, IMAGE_CACHE];

const OFFLINE_URL = '/offline.html';
/** Mọi navigate offline dùng chung bản shell đã cache dưới key "/". */
const SHELL_FALLBACK_REQUEST = new Request('/', { method: 'GET' });
const SHELL_PRECACHE_URLS = ['/', OFFLINE_URL];

/** Giữ lại khi dọn cache người dùng / cắt bớt cache shell. */
const SHELL_PROTECTED_PATHS = ['/', OFFLINE_URL];

const NAV_NETWORK_TIMEOUT_MS = 4000;
const ASSET_CACHE_MAX_ENTRIES = 450;
const SHELL_CACHE_MAX_ENTRIES = 60;
const TRIM_INTERVAL_MS = 10 * 60 * 1000;

/** Path không phải trang SPA — để trình duyệt xử lý trực tiếp, không trả shell. */
const NON_SHELL_PREFIXES = [
  '/api/',
  '/storage/',
  '/broadcasting',
  '/sanctum/',
  '/auth/',
  '/logout',
  '/build/',
  '/images/',
  '/vendor/',
];

const EMPLOYEE_SHELL_DEFAULT = '/dashboard/me/feed';
const EMPLOYEE_SHELL_PATTERN = /^\/dashboard\/me(\/|$)/;
/** Link ngoài origin (vd. VA-HRM) chỉ mở khi cùng domain trường. */
const TRUSTED_EXTERNAL_HOST = 'vaschools.edu.vn';

let lastTrimAt = 0;

function normalizePathname(pathname) {
  if (!pathname) return '/';
  const p = pathname.length > 1 && pathname.endsWith('/') ? pathname.slice(0, -1) : pathname;
  return p || '/';
}

function isHtmlResponse(response) {
  return /text\/html/i.test(response.headers.get('content-type') || '');
}

/** Response HTML 200 cùng origin, không qua redirect — đủ điều kiện làm shell offline. */
function isCacheableShellResponse(response) {
  return response.status === 200
    && response.type === 'basic'
    && !response.redirected
    && isHtmlResponse(response);
}

/** Asset/ảnh: loại HTML (Laravel fallback trả 200 text/html cho file không tồn tại). */
function isCacheableAsset(response) {
  return response.status === 200 && !isHtmlResponse(response);
}

function shellKeyFor(url) {
  return `${url.origin}${normalizePathname(url.pathname)}`;
}

async function precacheShellDocument(url) {
  const cache = await caches.open(SHELL_CACHE);
  try {
    const response = await fetch(url, { credentials: 'same-origin', cache: 'reload' });
    if (!isCacheableShellResponse(response)) return;
    await cache.put(url, response.clone());
    if (url === '/') {
      await cache.put(SHELL_FALLBACK_REQUEST, response.clone());
    }
  } catch {
    /* mạng chưa có lúc install — bỏ qua từng URL */
  }
}

self.addEventListener('install', (event) => {
  // Không skipWaiting ở đây: bản mới chờ trong trạng thái "waiting" để trang hiện
  // banner và người dùng tự bấm "Tải lại" (xem usePwaUpdate.js).
  event.waitUntil(Promise.all(SHELL_PRECACHE_URLS.map((url) => precacheShellDocument(url))));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      const names = await caches.keys();
      await Promise.all(
        names
          .filter((name) => !CURRENT_CACHES.includes(name))
          .map((name) => caches.delete(name)),
      );
      await purgeCachedPwaBrandImages();
      await self.clients.claim();
    })(),
  );
});

self.addEventListener('message', (event) => {
  const type = event.data?.type ?? event.data;
  if (type === 'skipWaiting' || type === 'SKIP_WAITING') {
    self.skipWaiting();
  } else if (type === 'CLEAR_USER_CACHES') {
    event.waitUntil(clearUserCaches());
  }
});

/** Đăng xuất: bỏ lịch sử trang đã mở, chỉ giữ shell "/" và trang offline. */
async function clearUserCaches() {
  const cache = await caches.open(SHELL_CACHE);
  const keys = await cache.keys();
  await Promise.all(
    keys
      .filter((req) => !SHELL_PROTECTED_PATHS.includes(new URL(req.url).pathname))
      .map((req) => cache.delete(req)),
  );
}

/** Xoá bớt mục cũ nhất (thứ tự chèn) khi cache vượt ngưỡng. */
async function trimCache(cacheName, maxEntries, protectedPaths = []) {
  const cache = await caches.open(cacheName);
  const keys = await cache.keys();
  const removable = keys.filter((req) => !protectedPaths.includes(new URL(req.url).pathname));
  const excess = removable.length - maxEntries;
  for (let i = 0; i < excess; i += 1) {
    await cache.delete(removable[i]);
  }
}

/** SW bị dừng/khởi động lại liên tục nên chỉ cắt cache tối đa mỗi 10 phút. */
function scheduleTrim(event, cacheName, maxEntries, protectedPaths = []) {
  const now = Date.now();
  if (now - lastTrimAt < TRIM_INTERVAL_MS) return;
  lastTrimAt = now;
  event.waitUntil(trimCache(cacheName, maxEntries, protectedPaths).catch(() => {}));
}

function isBuildAsset(url) {
  return url.pathname.startsWith('/build/');
}

function isPwaBrandImage(url) {
  return url.pathname.startsWith('/images/pwa/')
    || url.pathname === '/images/favicon.png';
}

function isStaticImage(url) {
  return url.pathname.startsWith('/images/') || url.pathname.startsWith('/vendor/');
}

function isApiRequest(url) {
  return url.pathname.startsWith('/api/');
}

/** Chỉ điều hướng tới trang SPA mới được trả shell; file/đường dẫn kỹ thuật thì không. */
function isShellNavigation(url) {
  const path = url.pathname;
  if (NON_SHELL_PREFIXES.some((prefix) => path.startsWith(prefix))) return false;
  if (/\.[A-Za-z0-9]{1,8}$/.test(path)) return false;
  return true;
}

async function cacheFirst(event, cacheName) {
  const { request } = event;
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  if (cached) return cached;
  const response = await fetch(request);
  if (isCacheableAsset(response)) {
    event.waitUntil(cache.put(request, response.clone()).catch(() => {}));
    scheduleTrim(event, cacheName, ASSET_CACHE_MAX_ENTRIES);
  }
  return response;
}

async function staleWhileRevalidate(event, cacheName) {
  const { request } = event;
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  const networkFetch = fetch(request)
    .then((response) => {
      if (isCacheableAsset(response)) {
        event.waitUntil(cache.put(request, response.clone()).catch(() => {}));
      }
      return response;
    })
    .catch(() => null);

  if (cached) {
    event.waitUntil(networkFetch);
    return cached;
  }
  return (await networkFetch) || Response.error();
}

async function shellFromCache(cache, url) {
  const exact = await cache.match(shellKeyFor(url));
  if (exact) return exact;
  return (await cache.match(SHELL_FALLBACK_REQUEST)) || null;
}

async function offlineShellResponse(cache, url) {
  const shell = await shellFromCache(cache, url);
  if (shell) return shell;
  const offline = await cache.match(OFFLINE_URL, { ignoreSearch: true });
  if (offline) return offline;
  return Response.error();
}

/** Lưu HTML mới nhất theo pathname + làm mới bản fallback "/" (luôn trỏ asset hiện hành). */
async function storeShell(cache, url, response) {
  await cache.put(shellKeyFor(url), response.clone());
  await cache.put(SHELL_FALLBACK_REQUEST, response.clone());
  await trimCache(SHELL_CACHE, SHELL_CACHE_MAX_ENTRIES, SHELL_PROTECTED_PATHS);
}

async function networkFirstShell(event) {
  const { request } = event;
  const url = new URL(request.url);
  const cache = await caches.open(SHELL_CACHE);

  const networkPromise = fetch(request).then((response) => {
    if (isCacheableShellResponse(response)) {
      // Lỗi ghi cache (hết quota...) không được làm hỏng response đang trả cho trang.
      event.waitUntil(storeShell(cache, url, response.clone()).catch(() => {}));
    }
    return response;
  });
  // Tránh unhandled rejection khi timeout thắng và ta đã trả bản cache.
  const settled = networkPromise.catch(() => null);

  let timer;
  const timeout = new Promise((resolve) => {
    timer = setTimeout(() => resolve(null), NAV_NETWORK_TIMEOUT_MS);
  });

  try {
    const first = await Promise.race([networkPromise, timeout]);
    if (first) return first;

    // Mạng chậm: có bản shell thì hiện ngay, bản mới vẫn tải ngầm để lần sau.
    const cached = await shellFromCache(cache, url);
    if (cached) {
      event.waitUntil(settled);
      return cached;
    }
    return await networkPromise;
  } catch {
    return offlineShellResponse(cache, url);
  } finally {
    clearTimeout(timer);
  }
}

async function purgeCachedPwaBrandImages() {
  const names = await caches.keys();
  await Promise.all(
    names.map(async (name) => {
      const cache = await caches.open(name);
      const keys = await cache.keys();
      await Promise.all(
        keys
          .filter((req) => {
            const p = new URL(req.url).pathname;
            return p.startsWith('/images/pwa/') || p === '/images/favicon.png';
          })
          .map((req) => cache.delete(req)),
      );
    }),
  );
}

async function networkOnlyBrandImage(request) {
  try {
    return await fetch(request, { cache: 'no-store' });
  } catch {
    return Response.error();
  }
}

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;
  // Range (video/audio/PDF từng phần) phải đi thẳng mạng — cache không trả 206 đúng.
  if (request.headers.has('range')) return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  // API: không can thiệp, luôn đi thẳng mạng.
  if (isApiRequest(url)) return;

  if (isBuildAsset(url)) {
    event.respondWith(cacheFirst(event, ASSET_CACHE));
    return;
  }

  if (isPwaBrandImage(url)) {
    event.respondWith(networkOnlyBrandImage(request));
    return;
  }

  if (isStaticImage(url)) {
    event.respondWith(staleWhileRevalidate(event, IMAGE_CACHE));
    return;
  }

  if (request.mode === 'navigate' && isShellNavigation(url)) {
    event.respondWith(networkFirstShell(event));
  }
});

// ---------------------------------------------------------------------------
// Web Push
// ---------------------------------------------------------------------------

const PUSH_BRAND = 'VA Workspace';

/**
 * Xác định đích mở khi bấm thông báo.
 * - Cùng origin: giữ nguyên, router SPA tự quyết trang nào xem được trên mobile.
 * - Khác origin: chỉ nhận https thuộc domain trường (vd. VA-HRM).
 */
function resolveNotificationTarget(raw) {
  const fallback = { url: EMPLOYEE_SHELL_DEFAULT, external: false };
  if (!raw || typeof raw !== 'string') return fallback;
  try {
    const url = new URL(raw, self.location.origin);
    if (url.origin === self.location.origin) {
      return {
        url: `${normalizePathname(url.pathname)}${url.search}${url.hash}`,
        external: false,
      };
    }
    const host = url.hostname.toLowerCase();
    const trusted = host === TRUSTED_EXTERNAL_HOST || host.endsWith(`.${TRUSTED_EXTERNAL_HOST}`);
    if (url.protocol === 'https:' && trusted) {
      return { url: url.href, external: true };
    }
  } catch {
    /* URL hỏng — dùng mặc định */
  }
  return fallback;
}

function buildPushOptions(data) {
  const tag = data.tag || 'va-workspace';
  const options = {
    body: data.body || '',
    icon: data.icon || '/images/pwa/icon-192.png',
    badge: '/images/pwa/apple-touch-icon.png',
    tag,
    renotify: Boolean(data.tag),
    timestamp: typeof data.timestamp === 'number' ? data.timestamp : Date.now(),
    vibrate: [72, 36, 72],
    data: { url: resolveNotificationTarget(data.url).url },
  };
  if (data.image) {
    options.image = data.image;
  }
  return options;
}

self.addEventListener('push', (event) => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch {
    data = { body: event.data ? event.data.text() : '' };
  }

  const title = data.title || PUSH_BRAND;
  const options = buildPushOptions(data);

  event.waitUntil(self.registration.showNotification(title, options));
});

function clientPathname(client) {
  try {
    return new URL(client.url).pathname;
  } catch {
    return '';
  }
}

/** Chỉ tái dùng cửa sổ đang ở khu "của tôi" — không kéo tab quản lý đang nhập dở đi chỗ khác. */
function pickEmployeeClient(clients) {
  const mine = clients.filter((c) => EMPLOYEE_SHELL_PATTERN.test(clientPathname(c)));
  return mine.find((c) => c.visibilityState === 'visible') || mine[0] || null;
}

async function openOrFocusClient(url, external) {
  if (!external) {
    const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const client = pickEmployeeClient(clients);
    if (client) {
      try {
        const focused = await client.focus();
        // Điều hướng trong SPA (router.push) — không reload, giữ nháp chat đang gõ.
        (focused || client).postMessage({ type: 'NAVIGATE', url });
        return undefined;
      } catch {
        /* focus bị từ chối — mở cửa sổ mới bên dưới */
      }
    }
  }
  return self.clients.openWindow ? self.clients.openWindow(url) : undefined;
}

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const { url, external } = resolveNotificationTarget(event.notification.data?.url);
  event.waitUntil(openOrFocusClient(url, external));
});

// Endpoint push bị trình duyệt đổi/hết hạn: báo cho các trang đang mở đăng ký lại
// (SW không tự gọi API vì cần cookie CSRF của SPA).
self.addEventListener('pushsubscriptionchange', (event) => {
  event.waitUntil(
    self.clients
      .matchAll({ type: 'window', includeUncontrolled: true })
      .then((clients) => {
        clients.forEach((client) => client.postMessage({ type: 'PUSH_RESYNC' }));
      }),
  );
});
