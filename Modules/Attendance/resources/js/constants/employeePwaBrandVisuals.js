/** Logo PWA — preload + watermark clip-path trên shell mobile. */
export const EMPLOYEE_PWA_LOGO_ASSETS = [
  '/images/pwa/icon-192.png',
  '/images/pwa/icon-512.png',
  '/images/pwa/maskable-192.png',
  '/images/pwa/maskable-512.png',
  '/images/pwa/apple-touch-icon.png',
];

/** Hình cắt trang trí (CSS clip-path) — xoay vòng theo visualIndex. */
export const EMPLOYEE_PWA_LOGO_CLIPS = [
  'polygon(0 0, 100% 0, 100% calc(100% - 10px), calc(100% - 12px) 100%, 12px 100%, 0 calc(100% - 10px))',
  'polygon(12px 0, 100% 0, 100% calc(100% - 12px), calc(100% - 12px) 100%, 0 100%, 0 12px)',
  'inset(12% 10% 10% 12% round 0.65rem)',
  'circle(72% at 88% 14%)',
  'ellipse(48% 58% at 90% 86%)',
  'polygon(0 18%, 18% 0, 100% 0, 100% 100%, 0 100%)',
  'polygon(0 0, 88% 0, 100% 22%, 100% 100%, 0 100%)',
  'inset(8% 18% 22% 8% round 50%)',
];

export function historyHubVisual(visualIndex = 0) {
  const i = Number(visualIndex) || 0;
  return {
    src: EMPLOYEE_PWA_LOGO_ASSETS[i % EMPLOYEE_PWA_LOGO_ASSETS.length],
    clipPath: EMPLOYEE_PWA_LOGO_CLIPS[i % EMPLOYEE_PWA_LOGO_CLIPS.length],
  };
}

/** Preload ảnh logo (PWA offline / hub Lịch sử). */
export function preloadEmployeePwaLogos() {
  if (typeof Image === 'undefined') return;
  EMPLOYEE_PWA_LOGO_ASSETS.forEach((src) => {
    const img = new Image();
    img.decoding = 'async';
    img.src = src;
  });
}
