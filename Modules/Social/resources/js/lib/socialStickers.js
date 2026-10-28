import '../styles/socialStickers.css';
import { vaStickerById } from './vaStickers.js';

const STICKER_ID_RE = /^[0-9a-f]{2,8}(?:_[0-9a-f]{2,8}){0,12}$/;
const LOTTIE_BASE = 'https://fonts.gstatic.com/s/e/notoemoji/latest';

const jsonCache = new Map();
let lottieLoader = null;

export function isValidStickerId(id) {
  return typeof id === 'string' && STICKER_ID_RE.test(id);
}

export function emojiToStickerId(emoji) {
  if (!emoji) return '';
  return Array.from(String(emoji))
    .map((ch) => ch.codePointAt(0))
    .filter((cp) => cp !== 0xfe0f && cp !== 0xfe0e)
    .map((cp) => cp.toString(16))
    .join('_');
}

export function stickerLottieUrl(id) {
  return `${LOTTIE_BASE}/${id}/lottie.json`;
}

function prefersReducedMotion() {
  return window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false;
}

function loadLottie() {
  if (!lottieLoader) {
    lottieLoader = import('lottie-web/build/player/lottie_light').then((mod) => mod.default ?? mod);
  }
  return lottieLoader;
}

function fetchLottieJson(id) {
  if (!jsonCache.has(id)) {
    jsonCache.set(
      id,
      fetch(stickerLottieUrl(id))
        .then((res) => {
          if (!res.ok) throw new Error('sticker missing');
          return res.json();
        })
        .catch((error) => {
          jsonCache.delete(id);
          throw error;
        }),
    );
  }
  return jsonCache.get(id);
}

/** Tải trước JSON Lottie (bỏ qua VA sticker tĩnh). */
export function prefetchStickerJson(id) {
  if (vaStickerById(id) || !isValidStickerId(id)) return Promise.resolve();
  return fetchLottieJson(id).catch(() => {});
}

export function prefetchStickerIds(ids) {
  const unique = [...new Set(ids.filter(Boolean))];
  if (unique.length === 0) return Promise.resolve();
  return Promise.all(unique.map((id) => prefetchStickerJson(id)));
}

/** Gọi khi mở panel sticker để giảm trễ lần play đầu. */
export function warmStickerAssets() {
  return loadLottie().catch(() => {});
}

function useHoverPlaySticker(hoverPlay) {
  if (!hoverPlay) return false;
  return !(window.matchMedia?.('(pointer: coarse)')?.matches ?? false);
}

/**
 * Gắn animation Lottie vào một element. Trả về hàm huỷ.
 * `hoverPlay`: chỉ chạy khi hover (dùng trong picker).
 */
export function mountStickerAnimation(el, id, { hoverPlay = false } = {}) {
  if (!el) return () => {};

  const va = vaStickerById(id);
  if (va) {
    el.setAttribute('role', 'img');
    el.setAttribute('aria-label', va.label);
    el.textContent = '';
    const image = document.createElement('img');
    image.className = 'social-sticker__va';
    image.src = va.src;
    image.alt = '';
    el.appendChild(image);
    el.classList.add('social-sticker--ready', 'social-sticker--static');
    return () => {
      image.remove();
    };
  }

  if (!isValidStickerId(id)) return () => {};

  let anim = null;
  let destroyed = false;
  let hovered = false;
  const reduced = prefersReducedMotion();

  const fallback = (el.textContent || '').trim();
  if (fallback) el.setAttribute('aria-label', fallback);
  el.setAttribute('role', 'img');

  function stage() {
    let node = el.querySelector(':scope > .social-sticker__anim');
    if (!node) {
      node = document.createElement('span');
      node.className = 'social-sticker__anim';
      node.setAttribute('aria-hidden', 'true');
      el.textContent = '';
      if (fallback) {
        const text = document.createElement('span');
        text.className = 'social-sticker__fallback';
        text.textContent = fallback;
        el.appendChild(text);
      }
      el.appendChild(node);
    }
    return node;
  }

  async function play() {
    if (destroyed || anim) return;
    const host = stage();
    try {
      const [lottie, data] = await Promise.all([loadLottie(), fetchLottieJson(id)]);
      if (destroyed || anim) return;
      host.replaceChildren();
      anim = lottie.loadAnimation({
        container: host,
        renderer: 'svg',
        loop: !reduced && !hoverPlay,
        autoplay: false,
        animationData: data,
        rendererSettings: { preserveAspectRatio: 'xMidYMid meet' },
      });
      el.classList.add('social-sticker--ready');
      if (reduced) {
        anim.goToAndStop(Math.round(anim.totalFrames * 0.35), true);
        return;
      }
      if (hoverPlay) {
        if (hovered) anim.play();
        else anim.goToAndStop(0, true);
      } else {
        anim.play();
      }
    } catch {
      el.classList.add('social-sticker--static');
    }
  }

  function stopAnim() {
    anim?.destroy();
    anim = null;
    el.classList.remove('social-sticker--ready');
  }

  function onEnter() {
    hovered = true;
    if (hoverPlay) {
      play().then(() => {
        if (!destroyed && hovered) anim?.play();
      });
    }
  }

  function onLeave() {
    hovered = false;
    if (hoverPlay && anim && !reduced) {
      anim.goToAndStop(0, true);
    }
  }

  if (useHoverPlaySticker(hoverPlay)) {
    const hoverTarget = el.closest('button, [role="button"]') || el;
    let lastPointer = 'mouse';
    function onPointerDown(event) {
      lastPointer = event.pointerType || 'mouse';
    }
    function onEnterIfFine(event) {
      const type = event?.pointerType || lastPointer;
      if (type === 'touch') return;
      onEnter(event);
    }
    hoverTarget.addEventListener('pointerdown', onPointerDown);
    hoverTarget.addEventListener('pointerenter', onEnterIfFine);
    hoverTarget.addEventListener('pointerleave', onLeave);
    hoverTarget.addEventListener('focus', onEnterIfFine);
    hoverTarget.addEventListener('blur', onLeave);
    return () => {
      destroyed = true;
      hoverTarget.removeEventListener('pointerdown', onPointerDown);
      hoverTarget.removeEventListener('pointerenter', onEnterIfFine);
      hoverTarget.removeEventListener('pointerleave', onLeave);
      hoverTarget.removeEventListener('focus', onEnterIfFine);
      hoverTarget.removeEventListener('blur', onLeave);
      stopAnim();
    };
  }

  const io = new IntersectionObserver(
    (entries) => {
      const visible = entries.some((entry) => entry.isIntersecting);
      if (visible) play();
      else stopAnim();
    },
    { rootMargin: '120px', threshold: 0.01 },
  );
  io.observe(el);

  return () => {
    destroyed = true;
    io.disconnect();
    stopAnim();
  };
}

export function hydrateSocialStickers(root) {
  if (!root || !(root instanceof Element)) return () => {};
  const stops = [];
  root.querySelectorAll('.social-sticker[data-sticker]').forEach((el) => {
    if (el.dataset.stickerReady) return;
    el.dataset.stickerReady = '1';
    stops.push(mountStickerAnimation(el, el.dataset.sticker));
  });
  return () => {
    stops.forEach((stop) => stop?.());
    root.querySelectorAll('[data-sticker-ready]').forEach((el) => {
      delete el.dataset.stickerReady;
    });
  };
}

export const vSocialStickers = {
  mounted(el) {
    el._socialStickerStop = hydrateSocialStickers(el);
  },
  updated(el) {
    const unbound = el.querySelectorAll('.social-sticker[data-sticker]:not([data-sticker-ready])');
    const bound = el.querySelectorAll('.social-sticker[data-sticker][data-sticker-ready]');
    if (unbound.length === 0 && bound.length > 0) return;
    el._socialStickerStop?.();
    el._socialStickerStop = hydrateSocialStickers(el);
  },
  unmounted(el) {
    el._socialStickerStop?.();
    el._socialStickerStop = null;
  },
};
