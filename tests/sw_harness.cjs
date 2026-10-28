'use strict';
// Harness kiểm thử hành vi service worker (public/sw.js) trong Node — không cần trình duyệt.
// Nạp sw.js vào một vm context với cache/clients/fetch giả rồi bắn các sự kiện thật.
const vm = require('node:vm');
const fs = require('node:fs');
const assert = require('node:assert/strict');

const ORIGIN = 'https://workspace.vaschools.edu.vn';
const SW_PATH = process.argv[2];
const abs = (u) => new URL(u, ORIGIN).href;
// Đối tượng tạo trong vm có prototype khác realm: chuẩn hoá trước khi so sánh sâu.
const plain = (v) => JSON.parse(JSON.stringify(v));

// Request của Node đòi URL tuyệt đối; trong SW thật URL tương đối được hiểu theo origin của SW.
class SWRequest extends Request {
  constructor(input, init) {
    super(typeof input === 'string' ? abs(input) : input, init);
  }
}

function mkRes(body, { status = 200, ct = 'text/html; charset=UTF-8', type = 'basic', redirected = false } = {}) {
  const res = new Response(body, { status, headers: { 'content-type': ct } });
  Object.defineProperty(res, 'type', { value: type });
  Object.defineProperty(res, 'redirected', { value: redirected });
  return res;
}

function makeCaches() {
  const stores = new Map();
  class FakeCache {
    constructor() { this.map = new Map(); }
    key(req) { return typeof req === 'string' ? abs(req) : req.url; }
    async match(req, opts) {
      const k = this.key(req);
      let hit = this.map.get(k);
      if (!hit && opts && opts.ignoreSearch) {
        const base = k.split('?')[0];
        for (const [kk, v] of this.map) { if (kk.split('?')[0] === base) { hit = v; break; } }
      }
      return hit ? hit.clone() : undefined;
    }
    async put(req, res) { this.map.set(this.key(req), res); }
    async delete(req) { return this.map.delete(this.key(req)); }
    async keys() { return [...this.map.keys()].map((u) => new SWRequest(u)); }
  }
  return {
    async open(name) { if (!stores.has(name)) stores.set(name, new FakeCache()); return stores.get(name); },
    async keys() { return [...stores.keys()]; },
    async delete(name) { return stores.delete(name); },
    _stores: stores,
  };
}

function loadSW({ fetchImpl }) {
  const handlers = {};
  const caches = makeCaches();
  const calls = { skipWaiting: 0, showNotification: [], openWindow: [] };
  const sandbox = {
    URL, Request: SWRequest, Response, Headers, Promise, console, Date, JSON, Set, Map, Array, Object,
    Math, String, Number, Boolean, RegExp, Error, TypeError, Symbol,
    // Rút ngắn mọi hẹn giờ dài (timeout mạng 4s) để test chạy nhanh.
    setTimeout: (fn, ms) => setTimeout(fn, ms >= 1000 ? 30 : ms),
    clearTimeout,
    caches,
    fetch: (...args) => fetchImpl(...args),
    location: { origin: ORIGIN },
    addEventListener: (type, fn) => { handlers[type] = fn; },
    skipWaiting: () => { calls.skipWaiting += 1; return Promise.resolve(); },
    _clients: [],
    registration: { showNotification: async (title, options) => { calls.showNotification.push({ title, options }); } },
  };
  sandbox.self = sandbox;
  sandbox.clients = {
    claim: async () => {},
    matchAll: async () => sandbox._clients,
    openWindow: async (u) => { calls.openWindow.push(u); },
  };
  vm.createContext(sandbox);
  vm.runInContext(fs.readFileSync(SW_PATH, 'utf8'), sandbox, { filename: 'sw.js' });
  return { handlers, caches, calls, sandbox };
}

function lifecycleEvent(extra = {}) {
  return { _waits: [], waitUntil(p) { this._waits.push(Promise.resolve(p)); }, ...extra };
}

function fetchEvent(url, { mode = 'navigate', headers = {} } = {}) {
  const request = new SWRequest(url, { method: 'GET', headers });
  Object.defineProperty(request, 'mode', { value: mode });
  return {
    request,
    _respond: undefined,
    _waits: [],
    respondWith(p) { this._respond = Promise.resolve(p); },
    waitUntil(p) { this._waits.push(Promise.resolve(p)); },
  };
}

const pathOf = (u) => new URL(typeof u === 'string' ? abs(u) : u.url).pathname;
async function shellKeys(t) {
  const shell = await t.caches.open('va-shell-v15');
  return (await shell.keys()).map((k) => k.url.replace(ORIGIN, '')).sort();
}

let passed = 0;
let failed = 0;
async function test(name, fn) {
  try {
    await fn();
    passed += 1;
    console.log(`  ok   ${name}`);
  } catch (error) {
    failed += 1;
    console.log(`  FAIL ${name}\n       ${String(error && error.message).split('\n').join('\n       ')}`);
  }
}

async function main() {
  console.log('Service worker harness:', SW_PATH);

  await test('install precaches only "/" and offline.html, and does NOT call skipWaiting', async () => {
    const fetched = [];
    const t = loadSW({ fetchImpl: async (u) => { fetched.push(pathOf(u)); return mkRes('<html>shell</html>'); } });
    const ev = lifecycleEvent();
    t.handlers.install(ev);
    await Promise.all(ev._waits);
    assert.deepEqual(fetched.sort(), ['/', '/offline.html']);
    assert.equal(t.calls.skipWaiting, 0, 'install must not skipWaiting');
    assert.deepEqual(await shellKeys(t), ['/', '/offline.html']);
  });

  await test('install skips redirected / non-html responses instead of caching them', async () => {
    const t = loadSW({ fetchImpl: async (u) => (pathOf(u) === '/'
      ? mkRes('login', { redirected: true })
      : mkRes('%PDF', { ct: 'application/pdf' })) });
    const ev = lifecycleEvent();
    t.handlers.install(ev);
    await Promise.all(ev._waits);
    assert.deepEqual(await shellKeys(t), []);
  });

  await test('message "skipWaiting" and {type:SKIP_WAITING} trigger skipWaiting; unknown ignored', async () => {
    const t = loadSW({ fetchImpl: async () => mkRes('x') });
    t.handlers.message(lifecycleEvent({ data: 'skipWaiting' }));
    t.handlers.message(lifecycleEvent({ data: { type: 'SKIP_WAITING' } }));
    t.handlers.message(lifecycleEvent({ data: { type: 'NOPE' } }));
    assert.equal(t.calls.skipWaiting, 2);
  });

  await test('navigation (online): caches html by pathname without query and refreshes "/" fallback', async () => {
    const t = loadSW({ fetchImpl: async () => mkRes('<html>feed</html>') });
    const ev = fetchEvent('/dashboard/me/feed?chat=5');
    t.handlers.fetch(ev);
    assert.ok(ev._respond, 'must respondWith');
    const res = await ev._respond;
    assert.equal(await res.text(), '<html>feed</html>');
    await Promise.all(ev._waits);
    assert.deepEqual(await shellKeys(t), ['/', '/dashboard/me/feed']);
  });

  await test('navigation: cache write failure never breaks the response served to the page', async () => {
    const t = loadSW({ fetchImpl: async () => mkRes('<html>ok</html>') });
    const shell = await t.caches.open('va-shell-v15');
    shell.put = async () => { throw new Error('QuotaExceededError'); };
    const ev = fetchEvent('/dashboard/me/leave');
    t.handlers.fetch(ev);
    const res = await ev._respond;
    assert.equal(await res.text(), '<html>ok</html>');
    await Promise.all(ev._waits); // waitUntil promise must not reject
  });

  await test('navigation: non-html (pdf) and redirected responses are served but NOT cached', async () => {
    let mode = 'pdf';
    const t = loadSW({ fetchImpl: async () => (mode === 'pdf'
      ? mkRes('%PDF-1.7', { ct: 'application/pdf' })
      : mkRes('login', { redirected: true })) });
    for (const m of ['pdf', 'redirect']) {
      mode = m;
      const ev = fetchEvent('/some/report');
      t.handlers.fetch(ev);
      await ev._respond;
      await Promise.all(ev._waits);
    }
    assert.deepEqual(await shellKeys(t), []);
  });

  await test('navigation offline: exact page > latest root shell > offline.html > network error', async () => {
    let online = true;
    const t = loadSW({ fetchImpl: async (u) => {
      if (!online) throw new TypeError('Failed to fetch');
      return mkRes(`PAGE:${pathOf(u)}`);
    } });
    for (const p of ['/dashboard/me/feed', '/dashboard/me/leave']) {
      const ev = fetchEvent(p);
      t.handlers.fetch(ev);
      await ev._respond;
      await Promise.all(ev._waits);
    }
    online = false;
    let ev = fetchEvent('/dashboard/me/feed?x=1');
    t.handlers.fetch(ev);
    assert.equal(await (await ev._respond).text(), 'PAGE:/dashboard/me/feed', 'exact page, query ignored');
    ev = fetchEvent('/dashboard/me/utilities');
    t.handlers.fetch(ev);
    assert.equal(await (await ev._respond).text(), 'PAGE:/dashboard/me/leave', 'falls back to latest shell');

    const empty = loadSW({ fetchImpl: async () => { throw new TypeError('Failed to fetch'); } });
    const shell = await empty.caches.open('va-shell-v15');
    ev = fetchEvent('/dashboard/me/feed');
    empty.handlers.fetch(ev);
    assert.equal((await ev._respond).type, 'error', 'nothing cached -> network error');
    await shell.put('/offline.html', mkRes('OFFLINE'));
    ev = fetchEvent('/dashboard/me/feed');
    empty.handlers.fetch(ev);
    assert.equal(await (await ev._respond).text(), 'OFFLINE');
  });

  await test('navigation on a slow network falls back to the cached shell after the timeout', async () => {
    let slow = false;
    const t = loadSW({ fetchImpl: async (u) => (slow ? new Promise(() => {}) : mkRes(`PAGE:${pathOf(u)}`)) });
    let ev = fetchEvent('/dashboard/me/feed');
    t.handlers.fetch(ev);
    await ev._respond;
    await Promise.all(ev._waits);
    slow = true;
    ev = fetchEvent('/dashboard/me/feed');
    t.handlers.fetch(ev);
    assert.equal(await (await ev._respond).text(), 'PAGE:/dashboard/me/feed');
  });

  await test('files and technical paths are NOT intercepted (/storage, /api, /auth, extensions, Range)', async () => {
    const t = loadSW({ fetchImpl: async () => mkRes('x') });
    for (const u of ['/storage/chat/a.pdf', '/api/me', '/auth/google/redirect', '/logout', '/sanctum/csrf-cookie', '/foo/bar.docx']) {
      const ev = fetchEvent(u);
      t.handlers.fetch(ev);
      assert.equal(ev._respond, undefined, `${u} must pass through`);
    }
    const ranged = fetchEvent('/dashboard/me/feed', { headers: { range: 'bytes=0-1' } });
    t.handlers.fetch(ranged);
    assert.equal(ranged._respond, undefined, 'Range request must pass through');
    const apiNav = fetchEvent('/api/export', { mode: 'cors' });
    t.handlers.fetch(apiNav);
    assert.equal(apiNav._respond, undefined);
  });

  await test('/build assets: js cached and reused; html fallback (200) is never cached', async () => {
    let calls = 0;
    const t = loadSW({ fetchImpl: async (u) => {
      calls += 1;
      return pathOf(u).includes('missing')
        ? mkRes('<html>spa</html>')
        : mkRes('console.log(1)', { ct: 'text/javascript' });
    } });
    for (let i = 0; i < 2; i += 1) {
      const ev = fetchEvent('/build/assets/app-abc.js', { mode: 'cors' });
      t.handlers.fetch(ev);
      await ev._respond;
      await Promise.all(ev._waits);
    }
    assert.equal(calls, 1, 'second request served from cache');
    for (let i = 0; i < 2; i += 1) {
      const ev = fetchEvent('/build/assets/missing-1.js', { mode: 'cors' });
      t.handlers.fetch(ev);
      await ev._respond;
      await Promise.all(ev._waits);
    }
    assert.equal(calls, 3, 'html fallback fetched twice => not cached');
  });

  await test('asset cache is trimmed to its limit (oldest first) on a miss', async () => {
    const t = loadSW({ fetchImpl: async () => mkRes('x', { ct: 'text/javascript' }) });
    const assets = await t.caches.open('va-assets-v15');
    for (let i = 0; i < 470; i += 1) await assets.put(`/build/assets/old-${i}.js`, mkRes('x', { ct: 'text/javascript' }));
    const ev = fetchEvent('/build/assets/new.js', { mode: 'cors' });
    t.handlers.fetch(ev);
    await ev._respond;
    await Promise.all(ev._waits);
    const keys = (await assets.keys()).map((k) => k.url);
    assert.ok(keys.length <= 450, `expected <= 450, got ${keys.length}`);
    assert.ok(!keys.some((k) => k.endsWith('/old-0.js')), 'oldest entry evicted');
    assert.ok(keys.some((k) => k.endsWith('/new.js')), 'newest entry kept');
  });

  await test('static images: uncached + offline => network error (no throw); cached + offline => cached', async () => {
    let online = false;
    const t = loadSW({ fetchImpl: async () => { if (!online) throw new TypeError('offline'); return mkRes('PNG', { ct: 'image/png' }); } });
    let ev = fetchEvent('/images/logo.png', { mode: 'no-cors' });
    t.handlers.fetch(ev);
    assert.equal((await ev._respond).type, 'error');
    online = true;
    ev = fetchEvent('/images/logo.png', { mode: 'no-cors' });
    t.handlers.fetch(ev);
    await ev._respond;
    await Promise.all(ev._waits);
    online = false;
    ev = fetchEvent('/images/logo.png', { mode: 'no-cors' });
    t.handlers.fetch(ev);
    assert.equal(await (await ev._respond).text(), 'PNG');
  });

  await test('PWA brand images are network-only with no-store (never cached)', async () => {
    const seen = [];
    const t = loadSW({ fetchImpl: async (req, init) => { seen.push(init); return mkRes('PNG', { ct: 'image/png' }); } });
    const ev = fetchEvent('/images/pwa/icon-192.png', { mode: 'no-cors' });
    t.handlers.fetch(ev);
    await ev._respond;
    assert.equal(seen[0] && seen[0].cache, 'no-store');
    const images = await t.caches.open('va-images-v15');
    assert.equal((await images.keys()).length, 0);
  });

  await test('CLEAR_USER_CACHES keeps only "/" and /offline.html', async () => {
    const t = loadSW({ fetchImpl: async (u) => mkRes(`PAGE:${pathOf(u)}`) });
    const inst = lifecycleEvent();
    t.handlers.install(inst);
    await Promise.all(inst._waits);
    for (const p of ['/dashboard/me/feed', '/dashboard/me/chat', '/manager/project/tasks/12']) {
      const ev = fetchEvent(p);
      t.handlers.fetch(ev);
      await ev._respond;
      await Promise.all(ev._waits);
    }
    assert.ok((await shellKeys(t)).length >= 4);
    const msg = lifecycleEvent({ data: { type: 'CLEAR_USER_CACHES' } });
    t.handlers.message(msg);
    await Promise.all(msg._waits);
    assert.deepEqual(await shellKeys(t), ['/', '/offline.html']);
  });

  await test('activate deletes caches of other versions', async () => {
    const t = loadSW({ fetchImpl: async () => mkRes('x') });
    await t.caches.open('va-shell-v14');
    await t.caches.open('va-assets-v14');
    await t.caches.open('va-shell-v15');
    const ev = lifecycleEvent();
    t.handlers.activate(ev);
    await Promise.all(ev._waits);
    assert.deepEqual((await t.caches.keys()).sort(), ['va-shell-v15']);
  });

  const mkClient = (path, { visible = true, failFocus = false } = {}) => {
    const c = { url: ORIGIN + path, visibilityState: visible ? 'visible' : 'hidden', messages: [], focused: 0 };
    c.focus = async () => { if (failFocus) throw new Error('denied'); c.focused += 1; return c; };
    c.postMessage = (m) => c.messages.push(m);
    return c;
  };
  const clickEvent = (url) => ({ notification: { close() {}, data: { url } }, _waits: [], waitUntil(p) { this._waits.push(Promise.resolve(p)); } });

  await test('notificationclick: reuses the /dashboard/me window and navigates via postMessage (no reload)', async () => {
    const t = loadSW({ fetchImpl: async () => mkRes('x') });
    const manager = mkClient('/manager/project', { visible: true });
    const mine = mkClient('/dashboard/me/feed', { visible: false });
    t.sandbox._clients = [manager, mine];
    const ev = clickEvent('/social?post=3');
    t.handlers.notificationclick(ev);
    await Promise.all(ev._waits);
    assert.equal(mine.focused, 1);
    assert.deepEqual(plain(mine.messages), [{ type: 'NAVIGATE', url: '/social?post=3' }]);
    assert.equal(manager.messages.length, 0, 'must not hijack the manager tab');
    assert.equal(t.calls.openWindow.length, 0);
  });

  await test('notificationclick: no /dashboard/me window => opens a new window (manager tab untouched)', async () => {
    const t = loadSW({ fetchImpl: async () => mkRes('x') });
    const manager = mkClient('/manager/project');
    t.sandbox._clients = [manager];
    const ev = clickEvent('/dashboard/me/chat?chat=9');
    t.handlers.notificationclick(ev);
    await Promise.all(ev._waits);
    assert.deepEqual(t.calls.openWindow, ['/dashboard/me/chat?chat=9']);
    assert.equal(manager.messages.length, 0);
  });

  await test('notificationclick: focus rejection falls back to openWindow instead of throwing', async () => {
    const t = loadSW({ fetchImpl: async () => mkRes('x') });
    t.sandbox._clients = [mkClient('/dashboard/me/feed', { failFocus: true })];
    const ev = clickEvent('/dashboard/me/leave');
    t.handlers.notificationclick(ev);
    await Promise.all(ev._waits);
    assert.deepEqual(t.calls.openWindow, ['/dashboard/me/leave']);
  });

  await test('notificationclick: trusted school-domain link opens a window; untrusted falls back to feed', async () => {
    const t = loadSW({ fetchImpl: async () => mkRes('x') });
    t.sandbox._clients = [mkClient('/dashboard/me/feed')];
    let ev = clickEvent('https://hrm.vaschools.edu.vn/attendance/timesheets/1');
    t.handlers.notificationclick(ev);
    await Promise.all(ev._waits);
    ev = clickEvent('https://evil.example.com/phish');
    t.handlers.notificationclick(ev);
    await Promise.all(ev._waits);
    ev = clickEvent('https://vaschools.edu.vn.evil.com/x');
    t.handlers.notificationclick(ev);
    await Promise.all(ev._waits);
    assert.equal(t.calls.openWindow[0], 'https://hrm.vaschools.edu.vn/attendance/timesheets/1');
    const mine = t.sandbox._clients[0];
    assert.deepEqual(plain(mine.messages), [
      { type: 'NAVIGATE', url: '/dashboard/me/feed' },
      { type: 'NAVIGATE', url: '/dashboard/me/feed' },
    ], 'untrusted hosts become the default feed path');
    assert.equal(t.calls.openWindow.length, 1);
  });

  await test('push: shows the notification with sanitized target url and tag', async () => {
    const t = loadSW({ fetchImpl: async () => mkRes('x') });
    const mkPush = (data) => ({ data: { json: () => data, text: () => 'raw' }, _waits: [], waitUntil(p) { this._waits.push(Promise.resolve(p)); } });
    let ev = mkPush({ title: 'VA Workspace', body: 'Mai: xin chào', url: '/social?post=3', tag: 'va-chat-1' });
    t.handlers.push(ev);
    await Promise.all(ev._waits);
    ev = mkPush({ body: 'x', url: 'https://evil.example.com/' });
    t.handlers.push(ev);
    await Promise.all(ev._waits);
    const [a, b] = t.calls.showNotification;
    assert.equal(a.title, 'VA Workspace');
    assert.equal(a.options.data.url, '/social?post=3');
    assert.equal(a.options.tag, 'va-chat-1');
    assert.equal(b.options.data.url, '/dashboard/me/feed');
    // payload hỏng (json ném lỗi) => rơi về text
    const broken = { data: { json: () => { throw new Error('bad'); }, text: () => 'plain text' }, _waits: [], waitUntil(p) { this._waits.push(Promise.resolve(p)); } };
    t.handlers.push(broken);
    await Promise.all(broken._waits);
    assert.equal(t.calls.showNotification[2].options.body, 'plain text');
  });

  await test('pushsubscriptionchange tells open windows to re-subscribe', async () => {
    const t = loadSW({ fetchImpl: async () => mkRes('x') });
    const c1 = mkClient('/dashboard/me/feed');
    const c2 = mkClient('/manager/project');
    t.sandbox._clients = [c1, c2];
    const ev = lifecycleEvent();
    t.handlers.pushsubscriptionchange(ev);
    await Promise.all(ev._waits);
    assert.deepEqual(plain(c1.messages), [{ type: 'PUSH_RESYNC' }]);
    assert.deepEqual(plain(c2.messages), [{ type: 'PUSH_RESYNC' }]);
  });

  console.log(`\n${passed} passed, ${failed} failed`);
  process.exit(failed ? 1 : 0);
}

main();

