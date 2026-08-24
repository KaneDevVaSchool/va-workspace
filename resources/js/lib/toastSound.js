//
// Âm thanh toast qua Web Audio API (không cần file mp3) — port từ va-hrm
// (resources/js/lib/toastSound.ts).
//

let sharedCtx = null;

function getCtx() {
  if (typeof window === 'undefined') return null;
  const Ctx = window.AudioContext || window.webkitAudioContext;
  if (!Ctx) return null;
  if (!sharedCtx || sharedCtx.state === 'closed') {
    sharedCtx = new Ctx();
  }
  return sharedCtx;
}

function beep(ctx, freq, start, duration, gainValue, type = 'sine') {
  const osc = ctx.createOscillator();
  const gain = ctx.createGain();
  osc.type = type;
  osc.frequency.value = freq;
  gain.gain.setValueAtTime(0.0001, start);
  gain.gain.exponentialRampToValueAtTime(gainValue, start + 0.02);
  gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
  osc.connect(gain);
  gain.connect(ctx.destination);
  osc.start(start);
  osc.stop(start + duration + 0.02);
}

/** Phát tiếng ngắn khi hiện toast. */
export function playToastSound(tone) {
  try {
    const ctx = getCtx();
    if (!ctx) return;
    const play = () => {
      const t = ctx.currentTime;
      if (tone === 'success') {
        beep(ctx, 880, t, 0.1, 0.12);
        beep(ctx, 1174.7, t + 0.1, 0.14, 0.1);
      } else if (tone === 'error') {
        beep(ctx, 320, t, 0.16, 0.14, 'triangle');
        beep(ctx, 220, t + 0.14, 0.2, 0.12, 'triangle');
      } else {
        // warning/info: 1 tiếng "tick" trung tính, ngắn hơn — không cần
        // nổi bật bằng success/error.
        beep(ctx, 660, t, 0.09, 0.08, 'sine');
      }
    };
    if (ctx.state === 'suspended') {
      ctx.resume().then(play).catch(() => {});
    } else {
      play();
    }
  } catch {
    /* ignore autoplay / unsupported */
  }
}
