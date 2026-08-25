//
// Âm thanh thả like / reaction qua Web Audio API (không cần file mp3).
// Cùng hướng với resources/js/lib/toastSound.js.
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

function beep(ctx, freq, start, duration, gainValue, type = 'sine', endFreq = null) {
  const osc = ctx.createOscillator();
  const gain = ctx.createGain();
  osc.type = type;
  osc.frequency.setValueAtTime(freq, start);
  if (endFreq != null) {
    osc.frequency.exponentialRampToValueAtTime(Math.max(endFreq, 20), start + duration);
  }
  gain.gain.setValueAtTime(0.0001, start);
  gain.gain.exponentialRampToValueAtTime(gainValue, start + 0.012);
  gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
  osc.connect(gain);
  gain.connect(ctx.destination);
  osc.start(start);
  osc.stop(start + duration + 0.02);
}

function noisePop(ctx, start, duration, freq, gainValue, q = 1.1) {
  const length = Math.max(1, Math.floor(ctx.sampleRate * duration));
  const buffer = ctx.createBuffer(1, length, ctx.sampleRate);
  const data = buffer.getChannelData(0);
  for (let i = 0; i < length; i += 1) {
    data[i] = (Math.random() * 2 - 1) * (1 - i / length);
  }
  const src = ctx.createBufferSource();
  src.buffer = buffer;
  const filter = ctx.createBiquadFilter();
  filter.type = 'bandpass';
  filter.frequency.value = freq;
  filter.Q.value = q;
  const gain = ctx.createGain();
  gain.gain.setValueAtTime(gainValue, start);
  gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
  src.connect(filter);
  filter.connect(gain);
  gain.connect(ctx.destination);
  src.start(start);
  src.stop(start + duration);
}

function playTone(type) {
  const ctx = getCtx();
  if (!ctx) return;
  const t = ctx.currentTime;

  if (type == null) {
    noisePop(ctx, t, 0.04, 420, 0.07, 0.8);
    beep(ctx, 240, t, 0.07, 0.05, 'sine', 140);
    return;
  }

  if (type === 'like') {
    noisePop(ctx, t, 0.045, 980, 0.16, 1.15);
    beep(ctx, 520, t, 0.09, 0.1, 'triangle', 210);
    beep(ctx, 880, t + 0.02, 0.05, 0.05, 'sine');
    return;
  }

  if (type === 'love') {
    noisePop(ctx, t, 0.04, 720, 0.12, 1);
    beep(ctx, 392, t, 0.1, 0.09, 'sine', 330);
    beep(ctx, 659, t + 0.07, 0.12, 0.1, 'sine', 523);
    return;
  }

  if (type === 'haha') {
    beep(ctx, 698, t, 0.055, 0.07, 'triangle');
    beep(ctx, 880, t + 0.07, 0.055, 0.07, 'triangle');
    beep(ctx, 1047, t + 0.14, 0.08, 0.08, 'sine');
    return;
  }

  if (type === 'wow') {
    noisePop(ctx, t, 0.06, 640, 0.08, 0.7);
    beep(ctx, 280, t, 0.2, 0.09, 'sine', 920);
    return;
  }

  if (type === 'sad') {
    beep(ctx, 392, t, 0.12, 0.08, 'sine', 330);
    beep(ctx, 294, t + 0.11, 0.16, 0.07, 'sine', 220);
    return;
  }

  if (type === 'angry') {
    noisePop(ctx, t, 0.07, 180, 0.14, 0.6);
    beep(ctx, 160, t, 0.11, 0.1, 'sawtooth', 90);
    beep(ctx, 110, t + 0.08, 0.12, 0.08, 'triangle');
    return;
  }

  noisePop(ctx, t, 0.045, 980, 0.16, 1.15);
  beep(ctx, 520, t, 0.09, 0.1, 'triangle', 210);
}

/** Phát tiếng khi thả / đổi / bỏ reaction. `type` null = bỏ cảm xúc. */
export function playReactionSound(type) {
  try {
    const ctx = getCtx();
    if (!ctx) return;
    const run = () => playTone(type);
    if (ctx.state === 'suspended') {
      ctx.resume().then(run).catch(() => {});
    } else {
      run();
    }
  } catch {
    /* ignore autoplay / unsupported */
  }
}
