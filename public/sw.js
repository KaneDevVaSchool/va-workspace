// VA Workspace — service worker
// v12: precache shell + start_url; offline navigate trả SPA shell (200) thay vì chỉ offline.html.
//
// Chiến lược:
// - App shell (navigate): network-first, cache theo URL + bản fallback "/".
// - Asset build (/build/..., content-hash trong tên file): cache-first —
//   file không bao giờ đổi nội dung dưới 1 tên, an toàn cache dài hạn.
// - Ảnh/icon tĩnh (/images/...): stale-while-revalidate — hiện ngay bản cache,
//   âm thầm lấy bản mới cho lần sau.
// - API (/api/...): network-only — không cache dữ liệu nhạy cảm.

const CACHE_VERSION = 'v12';
const SHELL_CACHE = `va-shell-${CACHE_VERSION}`;
const ASSET_CACHE = `va-assets-${CACHE_VERSION}`;
const IMAGE_CACHE = `va-images-${CACHE_VERSION}`;
const CURRENT_CACHES = [SHELL_CACHE, ASSET_CACHE, IMAGE_CACHE];

const OFFLINE_URL = '/offline.html';
/** Khớp manifest start_url — Lighthouse kiểm tra 200 offline. */
const START_URL = '/dashboard/me/feed';
/** Mọi navigate offline dùng chung bản shell đã cache dưới key "/". */
const SHELL_FALLBACK_REQUEST = new Request('/', { method: 'GET' });

const SHELL_PRECACHE_URLS = [
  '/',
  OFFLINE_URL,
  START_URL,
  '/login',
  '/dashboard/me',
  '/dashboard/me/feed',
  '/dashboard/me/attendance',
  '/dashboard/me/leave',
  '/dashboard/me/requests',
];

async function precacheShellDocument(url) {
  const cache = await caches.open(SHELL_CACHE);
  try {
    const response = await fetch(url, { credentials: 'same-origin' });
    if (!response.ok) return;
    await cache.put(url, response.clone());
    if (url === '/' || url === START_URL) {
      await cache.put(SHELL_FALLBACK_REQUEST, response.clone());
    }
  } catch {
    /* mạng chưa có lúc install — bỏ qua từng URL */
  }
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    (async () => {
      await Promise.all(SHELL_PRECACHE_URLS.map((url) => precacheShellDocument(url)));
      await self.skipWaiting();
    })(),
  );
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
  if (event.data === 'skipWaiting' || event.data?.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

function isBuildAsset(url) {
  return url.origin === self.location.origin && url.pathname.startsWith('/build/');
}

function isPwaBrandImage(url) {
  return url.pathname.startsWith('/images/pwa/')
    || url.pathname === '/images/favicon.png';
}

function isStaticImage(url) {
  return url.origin === self.location.origin
    && (url.pathname.startsWith('/images/') || url.pathname.startsWith('/vendor/'));
}

function isApiRequest(url) {
  return url.origin === self.location.origin && url.pathname.startsWith('/api/');
}

async function cacheFirst(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  if (cached) return cached;
  const response = await fetch(request);
  if (response.ok) cache.put(request, response.clone());
  return response;
}

async function staleWhileRevalidate(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  const networkFetch = fetch(request)
    .then((response) => {
      if (response.ok) cache.put(request, response.clone());
      return response;
    })
    .catch(() => undefined);
  return cached || networkFetch || fetch(request);
}

async function offlineShellResponse(cache, request) {
  const exact = await cache.match(request);
  if (exact) return exact;
  const root = await cache.match(SHELL_FALLBACK_REQUEST, { ignoreSearch: true })
    || await cache.match('/', { ignoreSearch: true });
  if (root) return root;
  const offline = await cache.match(OFFLINE_URL, { ignoreSearch: true });
  if (offline) return offline;
  return Response.error();
}

async function networkFirstShell(request) {
  const cache = await caches.open(SHELL_CACHE);
  try {
    const response = await fetch(request);
    if (response.ok && request.mode === 'navigate') {
      await cache.put(request, response.clone());
      await cache.put(SHELL_FALLBACK_REQUEST, response.clone());
    } else if (response.ok) {
      await cache.put(request, response.clone());
    }
    return response;
  } catch {
    return offlineShellResponse(cache, request);
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

async function networkOnlyWithFallback(request) {
  try {
    return await fetch(request);
  } catch {
    if (request.mode === 'navigate') {
      const cache = await caches.open(SHELL_CACHE);
      return offlineShellResponse(cache, request);
    }
    return Response.error();
  }
}

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  if (isApiRequest(url)) {
    event.respondWith(networkOnlyWithFallback(request));
    return;
  }

  if (isBuildAsset(url)) {
    event.respondWith(cacheFirst(request, ASSET_CACHE));
    return;
  }

  if (isPwaBrandImage(url)) {
    event.respondWith(networkOnlyBrandImage(request));
    return;
  }

  if (isStaticImage(url)) {
    event.respondWith(staleWhileRevalidate(request, IMAGE_CACHE));
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(networkFirstShell(request));
    return;
  }
});

const EMPLOYEE_SHELL_PATHS = new Set([
  '/dashboard/me',
  '/dashboard/me/feed',
  '/dashboard/me/attendance',
  '/dashboard/me/leave',
  '/dashboard/me/requests',
]);

const EMPLOYEE_SHELL_DEFAULT = '/dashboard/me/feed';

function normalizePathname(pathname) {
  if (!pathname) return '/';
  const p = pathname.length > 1 && pathname.endsWith('/') ? pathname.slice(0, -1) : pathname;
  return p || '/';
}

/** Chỉ cho phép deep link trong shell mobile; còn lại mở bảng tin. */
function sanitizeEmployeeShellTarget(raw) {
  const fallback = EMPLOYEE_SHELL_DEFAULT;
  if (!raw || typeof raw !== 'string') return fallback;
  try {
    const url = new URL(raw, self.location.origin);
    if (url.origin !== self.location.origin) return fallback;
    const path = normalizePathname(url.pathname);
    if (!EMPLOYEE_SHELL_PATHS.has(path)) {
      return `${fallback}?vaDesktopOnly=1`;
    }
    return `${path}${url.search}${url.hash}`;
  } catch {
    return fallback;
  }
}

self.addEventListener('push', (event) => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch {
    data = { body: event.data ? event.data.text() : '' };
  }

  const title = data.title || 'VA Workspace';
  const options = {
    body: data.body || '',
    icon: data.icon || '/images/pwa/icon-192.png',
    badge: '/images/favicon.png',
    tag: data.tag || 'va-workspace',
    data: { url: sanitizeEmployeeShellTarget(data.url || EMPLOYEE_SHELL_DEFAULT) },
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = sanitizeEmployeeShellTarget(
    event.notification.data?.url || EMPLOYEE_SHELL_DEFAULT,
  );
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
      for (const client of clients) {
        if ('focus' in client) {
          client.focus();
          if ('navigate' in client) {
            return client.navigate(target);
          }
          return undefined;
        }
      }
      if (self.clients.openWindow) {
        return self.clients.openWindow(target);
      }
      return undefined;
    }),
  );
});
