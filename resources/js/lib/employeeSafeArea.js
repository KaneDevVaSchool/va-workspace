//
// iOS PWA đôi khi không áp env(safe-area-inset-*) đúng trên phần tử lồng nhau.
// Đo padding computed từ phần tử probe rồi gán --employee-safe-* (px) lên <html>.
//

const SIDES = ['top', 'bottom', 'left', 'right'];

function measureSafeAreaInset(side) {
  if (typeof document === 'undefined') return 0;
  const el = document.createElement('div');
  el.setAttribute('aria-hidden', 'true');
  el.style.cssText = [
    'position:fixed',
    'top:0',
    'left:0',
    'width:0',
    'height:0',
    'visibility:hidden',
    'pointer-events:none',
    'padding:0',
    `padding-${side}:constant(safe-area-inset-${side})`,
    `padding-${side}:env(safe-area-inset-${side},0px)`,
  ].join(';');
  document.documentElement.appendChild(el);
  const raw = getComputedStyle(el).getPropertyValue(`padding-${side}`);
  el.remove();
  const px = parseFloat(raw);
  return Number.isFinite(px) && px > 0 ? px : 0;
}

export function isEmployeeMobileShellActive() {
  if (typeof document === 'undefined') return false;
  const root = document.documentElement;
  if (
    root.classList.contains('employee-mobile-pwa')
    || root.classList.contains('employee-mobile-browser')
  ) {
    return true;
  }
  return Boolean(document.querySelector('.employee-shell'));
}

export function syncEmployeeSafeAreaInsets() {
  if (typeof document === 'undefined') return;
  const root = document.documentElement;
  if (!isEmployeeMobileShellActive()) {
    for (const side of SIDES) {
      root.style.removeProperty(`--employee-safe-${side}`);
    }
    return;
  }
  for (const side of SIDES) {
    const px = measureSafeAreaInset(side);
    if (px > 0) {
      root.style.setProperty(`--employee-safe-${side}`, `${px}px`);
    } else {
      root.style.removeProperty(`--employee-safe-${side}`);
    }
  }
}
