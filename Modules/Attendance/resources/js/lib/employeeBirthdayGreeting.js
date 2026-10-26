const DISMISS_PREFIX = 'employee-birthday-greeting-dismissed-';
const VARIANT_PREFIX = 'employee-birthday-greeting-variant-';

function todayKey() {
  const d = new Date();
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

export function isBirthdayGreetingDismissedToday() {
  try {
    return localStorage.getItem(`${DISMISS_PREFIX}${todayKey()}`) === '1';
  } catch {
    return false;
  }
}

export function markBirthdayGreetingDismissedToday() {
  try {
    localStorage.setItem(`${DISMISS_PREFIX}${todayKey()}`, '1');
  } catch {
    /* ignore quota */
  }
}

/** Giữ cùng một sticker trong ngày (0–3). */
export function pickBirthdayGreetingVariantIndex(variantCount) {
  const count = Math.max(1, variantCount);
  const storageKey = `${VARIANT_PREFIX}${todayKey()}`;

  try {
    const stored = localStorage.getItem(storageKey);
    if (stored !== null) {
      const parsed = Number.parseInt(stored, 10);
      if (Number.isFinite(parsed) && parsed >= 0 && parsed < count) {
        return parsed;
      }
    }
  } catch {
    /* fall through */
  }

  const index = Math.floor(Math.random() * count);

  try {
    localStorage.setItem(storageKey, String(index));
  } catch {
    /* ignore */
  }

  return index;
}
