// VA Workspace — service worker
// v9: bỏ skipWaiting chờ tay — PWA đang mở phải nhận shell/menu mới.
//
// Chiến lược:
// - App shell ("/"): network-first, cache lại bản mới nhất để mở offline được.
// - Asset build (/build/..., content-hash trong tên file): cache-first —
//   file không bao giờ đổi nội dung dưới 1 tên, an toàn cache dài hạn.
// - Ảnh/icon tĩnh (/images/...): stale-while-revalidate — hiện ngay bản cache,
//   âm thầm lấy bản mới cho lần sau.
// - API (/api/...) và điều hướng trang khác: network-first, không cache dữ liệu
//   nhạy cảm — chỉ dùng fallback offline.html khi mất mạng hoàn toàn.

const CACHE_VERSION = 'v9';
const SHELL_CACHE = `va-shell-${CACHE_VERSION}`;
const ASSET_CACHE = `va-assets-${CACHE_VERSION}`;
const IMAGE_CACHE = `va-images-${CACHE_VERSION}`;
const CURRENT_CACHES = [SHELL_CACHE, ASSET_CACHE, IMAGE_CACHE];

const OFFLINE_URL = '/offline.html';
const APP_SHELL_URLS = ['/', OFFLINE_URL];

self.addEventListener('install', (event) => {
  event.waitUntil(
    (async () => {
      const cache = await caches.open(SHELL_CACHE);
      await cache.addAll(APP_SHELL_URLS).catch(() => {});
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

async function networkFirstShell(request) {
  const cache = await caches.open(SHELL_CACHE);
  try {
    const response = await fetch(request);
    if (response.ok) cache.put(request, response.clone());
    return response;
  } catch {
    const cached = await cache.match(request);
    return cached || cache.match(OFFLINE_URL);
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
      return (await cache.match(OFFLINE_URL)) || Response.error();
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
    data: { url: data.url || '/social' },
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = event.notification.data?.url || '/social';
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
