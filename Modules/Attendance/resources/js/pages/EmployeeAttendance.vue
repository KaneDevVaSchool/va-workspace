<script setup>
//
// Chấm công nhân viên — trang chủ, hình thức, GPS,
// WiFi, khuôn mặt, QR, mã, thành công. Lịch sử xem tại tab Lịch sử
// (/dashboard/me/requests). Chưa nối máy chấm công / HRM: lần bấm lưu trên máy.
//
import { computed, inject, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import AttendanceGlyph from '../components/AttendanceGlyph.vue';
import {
  readEmployeeAttendanceLog,
  readEmployeeAttendanceToday,
  writeEmployeeAttendanceToday,
} from '../lib/employeeAttendanceSession';

const WEEKDAYS = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];

const METHODS = [
  { id: 'button', label: 'Chấm bằng nút', hint: 'Nhanh chóng, tiện lợi', glyph: 'fingerprint', tone: 'blue' },
  { id: 'qr', label: 'Quét mã QR', hint: 'Tại điểm chấm công', glyph: 'qr', tone: 'violet' },
  { id: 'face', label: 'Camera Hanet', hint: 'Quét khuôn mặt qua camera Hanet', glyph: 'camera', tone: 'orange' },
  { id: 'gps', label: 'GPS', hint: 'Trong vùng cho phép', glyph: 'pin', tone: 'green' },
  { id: 'wifi', label: 'Chấm bằng WiFi', hint: 'Kết nối WiFi công ty', glyph: 'wifi', tone: 'teal' },
  { id: 'code', label: 'Nhập mã', hint: 'Dành cho trường hợp đặc biệt', glyph: 'keyboard', tone: 'rose' },
];

const HOME_TILES = [
  { id: 'qr', label: 'Quét QR', glyph: 'qr', tone: 'violet' },
  { id: 'face', label: 'Camera Hanet', glyph: 'camera', tone: 'orange' },
  { id: 'gps', label: 'GPS', glyph: 'pin', tone: 'green' },
  { id: 'wifi', label: 'Chấm bằng WiFi', glyph: 'wifi', tone: 'teal' },
  { id: 'code', label: 'Nhập mã', glyph: 'keyboard', tone: 'rose' },
  { id: 'more', label: 'Khác', glyph: 'grid', tone: 'slate' },
];

const HEADINGS = {
  methods: 'Chọn hình thức chấm công',
  face: 'Quét khuôn mặt với Hanet',
  gps: 'Chấm công bằng GPS',
  wifi: 'Chấm công bằng WiFi',
  qr: 'Quét mã QR',
  code: 'Nhập mã',
};

const auth = useAuthStore();
const now = ref(new Date());
const bodyRef = ref(null);
const videoRef = ref(null);
const stack = ref(['home']);
const punchLog = ref(readEmployeeAttendanceLog());
const geoState = ref('idle');
const coords = ref(null);
const weather = ref(null);
const cameraState = ref('idle');
const faceHelp = ref(false);
const codeValue = ref('');
const codeError = ref('');
const lastResult = ref(null);

const stored = readEmployeeAttendanceToday();
const checkInTime = ref(stored?.checkInTime ?? null);
const checkOutTime = ref(stored?.checkOutTime ?? null);
const checkInMethod = ref(stored?.checkInMethod ?? null);
const checkOutMethod = ref(stored?.checkOutMethod ?? null);

let timer = 0;
let cameraToken = 0;
let mediaStream = null;

const registerPageRefresh = inject('employeePageRefreshRegister', null);
let unregisterPageRefresh;

const screen = computed(() => stack.value[stack.value.length - 1] || 'home');
const showHead = computed(() => !['home', 'success'].includes(screen.value));
const headTitle = computed(() => HEADINGS[screen.value] || 'Chấm công');

const displayName = computed(() => auth.user?.name?.trim() || 'Bạn');
const placeLabel = computed(() => {
  const company = auth.user?.company?.name?.trim();
  const department = auth.user?.department?.name?.trim();
  if (department && company) return `${department} · ${company}`;
  return department || company || 'Văn phòng làm việc';
});

const timeLabel = computed(() => formatHm(now.value));
const homeDateLabel = computed(() => formatLongDate(now.value));
const heroLabel = computed(() => (
  checkInTime.value && !checkOutTime.value ? 'Chấm công ra' : 'Chấm công ngay'
));
const punchHint = computed(() => {
  if (checkInTime.value && checkOutTime.value) {
    return `Vào ${checkInTime.value} · Ra ${checkOutTime.value}`;
  }
  if (checkInTime.value) return `Đã vào lúc ${checkInTime.value}`;
  return 'Chưa chấm công hôm nay';
});
const geoBanner = computed(() => {
  if (geoState.value === 'granted') {
    const accuracy = coords.value?.accuracy;
    const detail = accuracy ? ` · lệch khoảng ${accuracy} m` : '';
    return { tone: 'ok', text: `Đã xác định vị trí thiết bị${detail}` };
  }
  if (geoState.value === 'pending') {
    return { tone: 'ok', text: 'Đang kiểm tra vị trí thiết bị…' };
  }
  return { tone: 'info', text: 'Chưa bật định vị — vẫn chấm được bằng nút, WiFi hoặc mã' };
});
const weatherGlyph = computed(() => {
  const code = weather.value?.code;
  if (code == null || code === 0) return 'sun';
  if (code <= 3) return 'cloud';
  return 'rain';
});
const faceCaption = computed(() => {
  if (cameraState.value === 'live') return 'Đưa mặt vào khung hình và giữ nguyên';
  if (cameraState.value === 'connecting') return 'Đang mở camera…';
  return 'Đưa mặt vào khung khi camera Hanet sẵn sàng';
});
const faceStatus = computed(() => {
  if (cameraState.value === 'live') return 'Đang xem trước camera';
  if (cameraState.value === 'connecting') return 'Đang kết nối camera Hanet...';
  return 'Chưa xem trước được camera';
});
const screenSubtitle = computed(() => {
  if (screen.value === 'methods') return 'Vui lòng chọn phương thức phù hợp';
  if (screen.value === 'face') return 'Quét khuôn mặt tại điểm chấm công';
  if (screen.value === 'gps') return 'Đối chiếu vị trí thiết bị với vùng cho phép';
  if (screen.value === 'wifi') return 'Đối chiếu mạng WiFi công ty';
  if (screen.value === 'qr') return 'Đưa mã tại điểm chấm công vào khung hình';
  if (screen.value === 'code') return 'Dành cho trường hợp đặc biệt';
  return '';
});
const dock = computed(() => {
  if (screen.value === 'success') return { label: 'Quay lại trang chủ', action: 'home' };
  if (screen.value === 'gps') return { label: 'Chấm công', action: 'punch-gps' };
  if (screen.value === 'wifi') return { label: 'Chấm công', action: 'punch-wifi' };
  if (screen.value === 'face') return { label: 'Ghi nhận khuôn mặt', action: 'punch-face' };
  if (screen.value === 'qr') return { label: 'Xác nhận đã quét', action: 'punch-qr' };
  if (screen.value === 'code') return { label: 'Chấm công', action: 'punch-code' };
  return null;
});
const dockDisabled = computed(() => screen.value === 'code' && codeValue.value.trim().length < 4);
const resultGlyph = computed(() => {
  const id = lastResult.value?.methodId;
  return METHODS.find((item) => item.id === id)?.glyph || 'check';
});

watch(codeValue, () => {
  codeError.value = '';
});

watch(screen, (value, prev) => {
  if (value === 'face') startCamera();
  else if (prev === 'face') stopCamera();
}, { flush: 'post' });

onMounted(() => {
  timer = window.setInterval(() => {
    now.value = new Date();
  }, 1000);
  requestLocation();
  if (typeof registerPageRefresh === 'function') {
    unregisterPageRefresh = registerPageRefresh(async () => {
      reloadPunch();
      now.value = new Date();
      if (geoState.value !== 'pending') await requestLocation();
    });
  }
});

onBeforeUnmount(() => {
  window.clearInterval(timer);
  stopCamera();
  unregisterPageRefresh?.();
});

function formatHm(date) {
  return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}

function formatLongDate(date) {
  const text = `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}/${date.getFullYear()}`;
  return `${WEEKDAYS[date.getDay()]}, ${text}`;
}

function scrollTop() {
  requestAnimationFrame(() => {
    bodyRef.value?.scrollTo?.({ top: 0 });
  });
}

function push(next) {
  stack.value = [...stack.value, next];
  scrollTop();
}

function back() {
  stack.value = stack.value.length > 1 ? stack.value.slice(0, -1) : ['home'];
  scrollTop();
}

function goHome() {
  stack.value = ['home'];
  scrollTop();
}

function reloadPunch() {
  const row = readEmployeeAttendanceToday();
  checkInTime.value = row?.checkInTime ?? null;
  checkOutTime.value = row?.checkOutTime ?? null;
  checkInMethod.value = row?.checkInMethod ?? null;
  checkOutMethod.value = row?.checkOutMethod ?? null;
  punchLog.value = readEmployeeAttendanceLog();
}

function onHero() {
  commitPunch('button');
}

function openMethod(id) {
  if (id === 'more') {
    push('methods');
    return;
  }
  if (id === 'button') {
    commitPunch('button');
    return;
  }
  if (id === 'code') {
    codeValue.value = '';
    codeError.value = '';
  }
  push(id);
}

function commitPunch(methodId) {
  const method = METHODS.find((item) => item.id === methodId);
  const fresh = new Date();
  const label = formatHm(fresh);
  now.value = fresh;
  const checkingOut = Boolean(checkInTime.value && !checkOutTime.value);
  if (checkingOut) {
    checkOutTime.value = label;
    checkOutMethod.value = method?.label || methodId;
  } else {
    checkInTime.value = label;
    checkOutTime.value = null;
    checkInMethod.value = method?.label || methodId;
    checkOutMethod.value = null;
  }
  writeEmployeeAttendanceToday({
    checkInTime: checkInTime.value,
    checkOutTime: checkOutTime.value,
    checkInMethod: checkInMethod.value,
    checkOutMethod: checkOutMethod.value,
  });
  punchLog.value = readEmployeeAttendanceLog();
  lastResult.value = {
    action: checkingOut ? 'out' : 'in',
    time: label,
    methodId,
    method: method?.label || methodId,
    dateLabel: formatLongDate(fresh),
  };
  stack.value = ['home', 'success'];
  scrollTop();
  if (typeof navigator !== 'undefined' && navigator.vibrate) navigator.vibrate(12);
}

async function punchGps() {
  if (geoState.value !== 'granted') {
    const ok = await requestLocation();
    if (!ok) return;
  }
  commitPunch('gps');
}

async function onDock() {
  const action = dock.value?.action;
  if (action === 'home') {
    goHome();
    return;
  }
  if (action === 'punch-gps') {
    await punchGps();
    return;
  }
  if (action === 'punch-wifi') {
    commitPunch('wifi');
    return;
  }
  if (action === 'punch-face') {
    commitPunch('face');
    return;
  }
  if (action === 'punch-qr') {
    commitPunch('qr');
    return;
  }
  if (action === 'punch-code') {
    if (codeValue.value.trim().length < 4) {
      codeError.value = 'Nhập ít nhất 4 ký tự.';
      return;
    }
    commitPunch('code');
  }
}

function requestLocation() {
  if (typeof navigator === 'undefined' || !navigator.geolocation) {
    geoState.value = 'unsupported';
    return Promise.resolve(false);
  }
  geoState.value = 'pending';
  return new Promise((resolve) => {
    navigator.geolocation.getCurrentPosition(
      (position) => {
        coords.value = {
          lat: position.coords.latitude,
          lon: position.coords.longitude,
          accuracy: Math.max(1, Math.round(position.coords.accuracy || 0)),
        };
        geoState.value = 'granted';
        loadWeather(position.coords.latitude, position.coords.longitude);
        resolve(true);
      },
      () => {
        geoState.value = 'denied';
        resolve(false);
      },
      { enableHighAccuracy: true, timeout: 8000, maximumAge: 120000 },
    );
  });
}

async function loadWeather(latitude, longitude) {
  try {
    const url = `https://api.open-meteo.com/v1/forecast?latitude=${latitude.toFixed(3)}&longitude=${longitude.toFixed(3)}&current=temperature_2m,weather_code`;
    const response = await fetch(url);
    if (!response.ok) return;
    const data = await response.json();
    if (data?.current?.temperature_2m == null) return;
    weather.value = {
      temp: Math.round(data.current.temperature_2m),
      code: data.current.weather_code,
    };
  } catch {
    /* giữ chip thời tiết trống khi mất mạng */
  }
}

async function startCamera() {
  const token = ++cameraToken;
  if (!navigator.mediaDevices?.getUserMedia) {
    cameraState.value = 'unavailable';
    return;
  }
  cameraState.value = 'connecting';
  try {
    const stream = await navigator.mediaDevices.getUserMedia({
      audio: false,
      video: { facingMode: 'user' },
    });
    if (token !== cameraToken) {
      stream.getTracks().forEach((track) => track.stop());
      return;
    }
    mediaStream = stream;
    cameraState.value = 'live';
    await nextTick();
    if (token !== cameraToken || !videoRef.value) return;
    videoRef.value.srcObject = stream;
    await videoRef.value.play().catch(() => {});
  } catch {
    if (token === cameraToken) cameraState.value = 'denied';
  }
}

function stopCamera() {
  cameraToken += 1;
  mediaStream?.getTracks()?.forEach((track) => track.stop());
  mediaStream = null;
  if (videoRef.value) videoRef.value.srcObject = null;
}
</script>

<template>
  <div class="att-page">
    <div class="att-aurora" aria-hidden="true"></div>
    <img
      src="/images/background/background-logo.png"
      alt=""
      class="att-watermark"
      aria-hidden="true"
      decoding="async"
      fetchpriority="low"
    />
    <div class="att">
      <header v-if="showHead" class="att-head">
        <button type="button" class="att-icon-btn" aria-label="Quay lại" @click="back">
          <AppIcon name="chevronLeft" :size="20" />
        </button>
        <span class="att-head__text">
          <h1>{{ headTitle }}</h1>
          <small v-if="screenSubtitle">{{ screenSubtitle }}</small>
        </span>
        <button
          v-if="screen === 'face'"
          type="button"
          class="att-icon-btn"
          aria-label="Giải thích quét khuôn mặt"
          :aria-expanded="faceHelp"
          @click="faceHelp = !faceHelp"
        >
          <AppIcon name="helpCircle" :size="18" />
        </button>
        <span v-else class="att-icon-btn att-icon-btn--ghost" aria-hidden="true"></span>
      </header>

      <div ref="bodyRef" class="att__body hide-scrollbar">
        <section v-if="screen === 'home'" class="att-home">
          <article class="att-hero">
            <svg class="att-hero__art" viewBox="0 0 300 190" aria-hidden="true">
              <defs>
                <linearGradient id="att-hero-sky" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="var(--color-tertiary-500)" />
                  <stop offset="100%" stop-color="var(--color-tertiary-700)" />
                </linearGradient>
              </defs>
              <rect width="300" height="190" fill="url(#att-hero-sky)" />
              <circle cx="252" cy="36" r="30" fill="color-mix(in srgb, white 16%, transparent)" />
              <circle cx="40" cy="150" r="46" fill="color-mix(in srgb, white 8%, transparent)" />
              <path d="M0 150c36-14 70-14 100-2s64 16 96 2 72-18 104-2v40H0z" fill="color-mix(in srgb, white 10%, transparent)" />
              <rect x="30" y="92" width="34" height="58" rx="3" fill="color-mix(in srgb, white 20%, transparent)" />
              <rect x="70" y="70" width="42" height="80" rx="3" fill="color-mix(in srgb, white 28%, transparent)" />
              <rect x="200" y="78" width="30" height="72" rx="3" fill="color-mix(in srgb, white 22%, transparent)" />
              <rect x="236" y="100" width="40" height="50" rx="3" fill="color-mix(in srgb, white 16%, transparent)" />
              <g transform="translate(118 58)">
                <path d="M30 150v-34c0-22 14-38 34-38s34 16 34 38v34z" fill="var(--color-tertiary-900)" />
                <circle cx="64" cy="34" r="22" fill="#ffd7b8" />
                <path d="M44 28a20 20 0 0 1 40-2c-8 2-28 2-40 2z" fill="#2c2420" />
                <rect x="48" y="78" width="32" height="46" rx="8" fill="var(--color-on-primary)" />
                <rect x="48" y="78" width="32" height="14" rx="6" fill="var(--color-tertiary-300)" />
                <rect x="40" y="96" width="10" height="34" rx="5" fill="#ffd7b8" />
                <rect x="34" y="124" width="22" height="16" rx="6" fill="var(--color-tertiary-800)" />
              </g>
              <path
                d="M236 18 238 24 244 26 238 28 236 34 234 28 228 26 234 24z"
                fill="color-mix(in srgb, white 85%, transparent)"
              />
              <path d="M220 44h2.4v2.4H220zM86 118h2.4v2.4H86z" fill="color-mix(in srgb, white 70%, transparent)" />
            </svg>
            <div class="att-hero__face"></div>
            <div class="att-hero__bar">
              <div class="att-clock">
                <p>Thời gian hiện tại</p>
                <strong>{{ timeLabel }}</strong>
                <small>{{ homeDateLabel }}</small>
              </div>
              <div class="att-weather">
                <AttendanceGlyph :name="weatherGlyph" :size="20" />
                <b>{{ weather ? `${weather.temp}°C` : '—°' }}</b>
                <small>{{ weather ? 'Gần bạn' : 'Thời tiết' }}</small>
              </div>
            </div>
          </article>

          <p class="att-banner" :class="`att-banner--${geoBanner.tone}`">
            <AppIcon :name="geoBanner.tone === 'ok' ? 'check' : 'info'" :size="16" />
            <span>{{ geoBanner.text }}</span>
          </p>

          <div class="att-punch-wrap">
            <button type="button" class="att-punch" @click="onHero">
              <AttendanceGlyph name="fingerprint" :size="36" />
              <span>{{ heroLabel }}</span>
            </button>
          </div>
          <p class="att-punch-hint">{{ punchHint }}</p>

          <div class="att-grid">
            <button
              v-for="tile in HOME_TILES"
              :key="tile.id"
              type="button"
              class="att-tile"
              @click="openMethod(tile.id)"
            >
              <span class="att-tile__icon" :class="`att-tone--${tile.tone}`">
                <AttendanceGlyph :name="tile.glyph" :size="20" />
              </span>
              <span>{{ tile.label }}</span>
            </button>
          </div>
        </section>

        <section v-else-if="screen === 'methods'" class="att-stack">
          <button
            v-for="method in METHODS"
            :key="method.id"
            type="button"
            class="att-method"
            @click="openMethod(method.id)"
          >
            <span class="att-tile__icon" :class="`att-tone--${method.tone}`">
              <AttendanceGlyph :name="method.glyph" :size="20" />
            </span>
            <span class="att-method__text">
              <strong>{{ method.label }}</strong>
              <small>{{ method.hint }}</small>
            </span>
            <AppIcon name="chevronRight" :size="18" />
          </button>
        </section>

        <section v-else-if="screen === 'face'" class="att-stack">
          <p v-if="faceHelp" class="att-note">
            Đưa mặt vào khung và giữ yên. Camera Hanet tại văn phòng sẽ được nối sau; máy này dùng camera trước để xem trước.
          </p>
          <div class="att-face">
            <video
              v-show="cameraState === 'live'"
              ref="videoRef"
              class="att-face__video"
              playsinline
              muted
              autoplay
            />
            <svg v-if="cameraState !== 'live'" class="att-face__art" viewBox="0 0 300 260" preserveAspectRatio="xMidYMax slice" aria-hidden="true">
              <defs>
                <linearGradient id="att-face-sky" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#bcd9f7" />
                  <stop offset="100%" stop-color="#7eaddf" />
                </linearGradient>
              </defs>
              <rect width="300" height="260" fill="url(#att-face-sky)" />
              <circle cx="60" cy="52" r="34" fill="color-mix(in srgb, white 30%, transparent)" />
              <g transform="translate(90 70)">
                <ellipse cx="60" cy="150" rx="70" ry="14" fill="color-mix(in srgb, black 10%, transparent)" />
                <path d="M18 150v-40c0-30 18-52 42-52s42 22 42 52v40z" fill="var(--color-tertiary-900)" />
                <circle cx="60" cy="48" r="30" fill="#f3d2bd" />
                <path d="M32 40a28 28 0 0 1 56-2c-10 3-40 3-56 2z" fill="#352922" />
                <circle cx="48" cy="48" r="2.6" fill="#352922" />
                <circle cx="72" cy="48" r="2.6" fill="#352922" />
                <path d="M50 60c4 4 16 4 20 0" stroke="#a9644a" stroke-width="2.4" fill="none" stroke-linecap="round" />
              </g>
            </svg>
            <div class="att-face__frame" aria-hidden="true">
              <span></span><span></span><span></span><span></span>
            </div>
            <p class="att-face__caption">{{ faceCaption }}</p>
          </div>
          <p class="att-face__status">
            <span class="att-dot" :class="{ 'att-dot--wait': cameraState !== 'live' }"></span>
            {{ faceStatus }}
          </p>
        </section>

        <section v-else-if="screen === 'gps'" class="att-stack">
          <div class="att-map" aria-hidden="true">
            <span class="att-map__road"></span>
            <span class="att-map__road att-map__road--alt"></span>
            <span class="att-map__ring"></span>
            <span class="att-map__pin">
              <AttendanceGlyph name="pin" :size="22" />
            </span>
            <span class="att-map__bubble">{{ placeLabel }}</span>
          </div>
          <article class="att-place">
            <span class="att-tile__icon att-tone--green">
              <AttendanceGlyph name="pin" :size="18" />
            </span>
            <span>
              <strong>{{ placeLabel }}</strong>
              <small v-if="geoState === 'granted'">Độ chính xác khoảng {{ coords?.accuracy || '—' }} m</small>
              <small v-else>Bật định vị để gắn lần chấm với vị trí của bạn</small>
            </span>
          </article>
          <p class="att-banner" :class="geoState === 'granted' ? 'att-banner--ok' : 'att-banner--info'">
            <AppIcon :name="geoState === 'granted' ? 'check' : 'info'" :size="16" />
            <span>
              {{ geoState === 'granted'
                ? 'Đã lấy vị trí thiết bị. Vùng cho phép sẽ được đối chiếu khi nối máy chấm công.'
                : 'Cần quyền vị trí để chấm bằng GPS.' }}
            </span>
          </p>
        </section>

        <section v-else-if="screen === 'wifi'" class="att-stack att-wifi">
          <span class="att-wifi__orb" aria-hidden="true">
            <span class="att-wifi__ring att-wifi__ring--1"></span>
            <span class="att-wifi__ring att-wifi__ring--2"></span>
            <AttendanceGlyph name="wifi" :size="40" />
          </span>
          <h2>Đã sẵn sàng chấm bằng WiFi</h2>
          <p class="att-lead">Dùng khi máy đang vào mạng công ty.</p>
          <article class="att-panel">
            <p>
              <AttendanceGlyph name="wifi" :size="18" />
              <span>Mạng</span>
              <strong>WiFi công ty</strong>
            </p>
            <p>
              <AttendanceGlyph name="signal" :size="18" />
              <span>Tín hiệu</span>
              <strong>Sẵn sàng</strong>
            </p>
          </article>
          <p class="att-banner att-banner--ok">
            <AppIcon name="check" :size="16" />
            <span>Bạn có thể chấm công. Tên mạng sẽ hiện khi máy đọc được WiFi nội bộ.</span>
          </p>
        </section>

        <section v-else-if="screen === 'qr'" class="att-stack att-qr">
          <div class="att-qr__frame" aria-hidden="true">
            <span></span><span></span><span></span><span></span>
            <i></i>
          </div>
          <p class="att-lead">Đưa mã QR tại điểm chấm công vào khung hình.</p>
        </section>

        <section v-else-if="screen === 'code'" class="att-stack">
          <label class="att-code">
            <span>Mã chấm công</span>
            <input
              v-model="codeValue"
              type="text"
              inputmode="text"
              autocomplete="off"
              autocapitalize="off"
              maxlength="12"
              placeholder="Nhập mã"
            />
          </label>
          <p v-if="codeError" class="att-code__error">{{ codeError }}</p>
        </section>

        <section v-else-if="screen === 'success'" class="att-success">
          <svg class="att-success__confetti" viewBox="0 0 320 120" aria-hidden="true">
            <g fill="var(--color-gold-400)">
              <rect x="24" y="18" width="7" height="7" rx="1.5" transform="rotate(18 27 21)" />
              <rect x="268" y="30" width="6" height="6" rx="1.5" transform="rotate(-24 271 33)" />
              <rect x="150" y="8" width="7" height="7" rx="1.5" transform="rotate(8 153 11)" />
            </g>
            <g fill="var(--color-secondary-400)">
              <circle cx="60" cy="46" r="4" />
              <circle cx="250" cy="60" r="3.4" />
            </g>
            <g fill="var(--color-tertiary-400)">
              <circle cx="110" cy="20" r="3.2" />
              <rect x="208" y="14" width="6" height="6" rx="1.4" transform="rotate(30 211 17)" />
            </g>
          </svg>
          <div class="att-success__badge" aria-hidden="true">
            <AppIcon name="check" :size="40" :stroke-width="2.6" />
          </div>
          <h2>Chấm công thành công!</h2>
          <p class="att-success__name">{{ displayName }}</p>
          <p class="att-success__time">
            {{ lastResult?.action === 'out' ? 'Đã chấm ra lúc' : 'Đã chấm công lúc' }}
            {{ lastResult?.time }}
          </p>
          <p class="att-success__date">{{ lastResult?.dateLabel }}</p>
          <ul class="att-facts">
            <li>
              <span class="att-tile__icon att-tone--blue">
                <AttendanceGlyph :name="resultGlyph" :size="18" />
              </span>
              <span>
                <small>Hình thức</small>
                <strong>{{ lastResult?.method }}</strong>
              </span>
            </li>
            <li>
              <span class="att-tile__icon att-tone--green">
                <AttendanceGlyph name="pin" :size="18" />
              </span>
              <span>
                <small>Vị trí</small>
                <strong>{{ placeLabel }}</strong>
              </span>
            </li>
            <li v-if="lastResult?.methodId === 'wifi'">
              <span class="att-tile__icon att-tone--teal">
                <AttendanceGlyph name="wifi" :size="18" />
              </span>
              <span>
                <small>WiFi</small>
                <strong>Mạng công ty</strong>
              </span>
            </li>
          </ul>
          <svg class="att-skyline" viewBox="0 0 360 90" aria-hidden="true">
            <path d="M0 70h360v20H0z" fill="var(--color-secondary-100)" />
            <path d="M0 78c40-16 70 8 120-4s70-6 110 8 80-18 130-6v14H0z" fill="var(--color-secondary-300)" />
            <rect x="18" y="34" width="36" height="40" fill="var(--color-tertiary-200)" />
            <rect x="62" y="18" width="48" height="56" fill="var(--color-tertiary-300)" />
            <rect x="118" y="40" width="28" height="34" fill="var(--color-tertiary-100)" />
            <circle cx="250" cy="58" r="16" fill="var(--color-secondary-400)" />
            <rect x="242" y="70" width="16" height="16" fill="var(--color-secondary-600)" />
          </svg>
        </section>

      </div>

      <footer v-if="dock" class="att-dock">
        <button type="button" class="att-cta" :disabled="dockDisabled" @click="onDock">
          {{ dock.label }}
        </button>
      </footer>
    </div>
  </div>
</template>

<style scoped>
.att-page {
  position: relative;
  height: 100%;
  min-height: 0;
  display: flex;
  justify-content: center;
  background: transparent;
  color: var(--color-text);
  overflow: hidden;
}

.att-aurora {
  position: absolute;
  inset: -10% -10% auto -10%;
  z-index: 0;
  height: 30rem;
  background:
    radial-gradient(60% 55% at 18% 0%, color-mix(in srgb, var(--color-tertiary-200) 55%, transparent), transparent 70%),
    radial-gradient(50% 45% at 85% 6%, color-mix(in srgb, var(--color-secondary-200) 45%, transparent), transparent 72%);
  pointer-events: none;
}

.att-watermark {
  position: absolute;
  inset: 0;
  z-index: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center 20%;
  transform: scale(1.1);
  pointer-events: none;
  opacity: 0.04;
}

.att {
  --att-shadow: 0 10px 28px color-mix(in srgb, var(--color-tertiary-900) 8%, transparent);
  --att-notch: 1.1rem;
  position: relative;
  z-index: 1;
  width: min(100%, 430px);
  height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
  padding: 0;
}

.att__body {
  flex: 1 1 auto;
  min-height: 0;
  overflow: auto;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding-bottom: var(--space-2);
}

.att-head {
  display: grid;
  grid-template-columns: 2.5rem 1fr 2.5rem;
  align-items: center;
  gap: var(--space-2);
  min-height: 2.75rem;
}

.att-head__text {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1px;
  min-width: 0;
  text-align: center;
}

.att-head__text small {
  color: var(--color-text-muted);
  font-size: 0.72rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}

.att-head h1,
.att-success h2,
.att-wifi h2,
.att-section {
  margin: 0;
  color: var(--color-tertiary-900);
  font-weight: 800;
  letter-spacing: -0.02em;
}

.att-head h1 {
  font-size: 1.02rem;
}

.att-icon-btn {
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border: 0;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-tertiary-700);
  box-shadow: var(--att-shadow);
  cursor: pointer;
}

.att-icon-btn--ghost {
  background: transparent;
  box-shadow: none;
}

.att-icon-btn:focus-visible,
.att-cta:focus-visible,
.att-punch:focus-visible,
.att-tile:focus-visible,
.att-method:focus-visible,
.att-nav__item:focus-visible,
.att-month button:focus-visible {
  outline: 2px solid var(--color-tertiary-500);
  outline-offset: 2px;
}

.att-home,
.att-success {
  flex: 1;
}

.att-lead {
  margin: var(--space-2) 0 0;
  color: var(--color-text-muted);
  font-size: 0.92rem;
}

.att-skyline {
  width: 100%;
  height: auto;
  margin-top: auto;
}

.att-home,
.att-stack {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.att-place,
.att-panel,
.att-facts,
.att-day,
.att-notice,
.att-method,
.att-tile {
  background: var(--color-surface);
  box-shadow: var(--att-shadow);
}

.att-hero {
  position: relative;
  overflow: hidden;
  background: var(--color-tertiary-600);
  box-shadow: 0 16px 34px color-mix(in srgb, var(--color-tertiary-800) 26%, transparent);
  clip-path: polygon(
    0 0,
    100% 0,
    100% calc(100% - var(--att-notch)),
    calc(100% - var(--att-notch)) 100%,
    0 100%
  );
}

.att-hero__art {
  display: block;
  width: 100%;
  height: auto;
}

.att-hero__face {
  position: absolute;
  inset: 0;
  background: linear-gradient(185deg, transparent 55%, color-mix(in srgb, var(--color-tertiary-900) 55%, transparent) 100%);
  pointer-events: none;
}

.att-hero__bar {
  position: relative;
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin-top: -2.9rem;
  padding: 0 var(--space-3) var(--space-3);
}

.att-clock {
  flex: 1;
  min-width: 0;
  padding: var(--space-3);
  background: var(--color-surface);
  border-radius: 20px;
  box-shadow: var(--att-shadow);
}

.att-clock p,
.att-success__time,
.att-success__date,
.att-punch-hint {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.att-clock strong {
  display: block;
  color: var(--color-tertiary-900);
  font-size: 2.1rem;
  line-height: 1.15;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.04em;
}

.att-clock small {
  display: block;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.att-weather {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  min-width: 4.5rem;
  padding: var(--space-3) var(--space-2);
  background: var(--color-surface);
  color: var(--color-gold-600);
  border-radius: 20px;
  box-shadow: var(--att-shadow);
}

.att-weather b {
  color: var(--color-tertiary-900);
  font-size: 0.95rem;
}

.att-weather small {
  color: var(--color-text-muted);
  font-size: 0.68rem;
}

.att-banner {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  margin: 0;
  padding: var(--space-3);
  border-radius: 16px;
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.4;
}

.att-banner--ok {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
}

.att-banner--info {
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
}

.att-punch-wrap {
  position: relative;
  align-self: center;
  display: grid;
  place-items: center;
  width: 13rem;
  height: 13rem;
  margin-top: var(--space-1);
}

.att-punch-wrap::before {
  content: '';
  position: absolute;
  inset: 0;
  background: conic-gradient(
    from 140deg,
    color-mix(in srgb, var(--color-tertiary-300) 65%, transparent),
    transparent 30%,
    color-mix(in srgb, var(--color-secondary-300) 60%, transparent) 65%,
    transparent 92%
  );
  border-radius: var(--radius-full);
  opacity: 0.6;
  animation: att-ring-spin 16s linear infinite;
}

.att-punch-wrap::after {
  content: '';
  position: absolute;
  inset: 0.6rem;
  border-radius: var(--radius-full);
  box-shadow: 0 0 0 1px color-mix(in srgb, var(--color-tertiary-300) 50%, transparent);
  opacity: 0.6;
}

.att-punch {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  width: 9.5rem;
  height: 9.5rem;
  border: 8px solid color-mix(in srgb, var(--color-tertiary-200) 80%, white);
  border-radius: var(--radius-full);
  background: radial-gradient(circle at 35% 28%, var(--color-tertiary-400), var(--color-tertiary-600) 58%, var(--color-tertiary-800));
  color: var(--color-on-tertiary);
  font: inherit;
  cursor: pointer;
  box-shadow: 0 16px 30px color-mix(in srgb, var(--color-tertiary-800) 32%, transparent);
  transition: transform 0.15s ease;
}

.att-punch:active {
  transform: scale(0.97);
}

@keyframes att-ring-spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@media (prefers-reduced-motion: reduce) {
  .att-punch-wrap::before {
    animation: none;
  }
}

.att-punch span {
  max-width: 6.6rem;
  font-size: 0.86rem;
  font-weight: 800;
  line-height: 1.2;
  text-align: center;
}

.att-punch-hint {
  text-align: center;
}

.att-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-2);
}

.att-tile,
.att-method {
  border: 0;
  font: inherit;
  color: var(--color-text);
  cursor: pointer;
  text-align: left;
}

.att-tile {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-2);
  text-align: center;
  font-size: 0.75rem;
  font-weight: 700;
  border-radius: 20px;
  transition: transform 0.15s ease;
}

.att-tile:active {
  transform: scale(0.96);
}

.att-tile__icon {
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  color: var(--color-on-tertiary);
  border-radius: 16px;
}

.att-tone--blue { background: var(--color-tertiary-500); }
.att-tone--violet { background: #6d5efc; }
.att-tone--orange { background: var(--color-warning); }
.att-tone--green { background: var(--color-success); }
.att-tone--teal { background: var(--color-secondary); }
.att-tone--rose { background: #e85d8c; }
.att-tone--slate { background: #64748b; }

.att-method {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  width: 100%;
  padding: var(--space-3);
  border-radius: 20px;
}

.att-method__text {
  flex: 1;
  min-width: 0;
}

.att-method strong {
  display: block;
  color: var(--color-text);
  font-weight: 700;
}

.att-method svg:last-child {
  color: var(--color-text-muted);
}

.att-map {
  position: relative;
  height: 220px;
  overflow: hidden;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.2), transparent 28%),
    repeating-linear-gradient(90deg, #d5e7c4 0 26px, #c3dcb0 26px 48px);
  box-shadow: var(--att-shadow);
  border-radius: 24px;
}

.att-map__road {
  position: absolute;
  left: -10%;
  right: -8%;
  top: 46%;
  height: 18px;
  background: #f6f1e4;
  transform: rotate(-8deg);
}

.att-map__road--alt {
  top: 70%;
  left: -14%;
  right: 20%;
  height: 12px;
  transform: rotate(6deg);
  opacity: 0.75;
}

.att-map__ring {
  position: absolute;
  left: 50%;
  top: 48%;
  width: 148px;
  height: 148px;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-success) 30%, transparent);
  box-shadow: inset 0 0 0 2px color-mix(in srgb, var(--color-success) 75%, white);
  transform: translate(-50%, -50%);
}

.att-map__pin {
  position: absolute;
  left: 50%;
  top: 46%;
  display: grid;
  place-items: center;
  width: 2.4rem;
  height: 2.4rem;
  border-radius: var(--radius-full);
  background: var(--color-tertiary-600);
  color: var(--color-on-tertiary);
  transform: translate(-50%, -50%);
}

.att-map__bubble {
  position: absolute;
  left: 50%;
  top: 18%;
  max-width: 70%;
  padding: 6px 10px;
  border-radius: 12px;
  background: var(--color-surface);
  color: var(--color-tertiary-900);
  font-size: 0.75rem;
  font-weight: 700;
  transform: translateX(-50%);
  box-shadow: var(--shadow-sm);
}

.att-place {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3);
  border-radius: 20px;
}

.att-wifi {
  align-items: center;
  text-align: center;
  padding-top: var(--space-2);
}

.att-wifi__orb {
  position: relative;
  display: grid;
  place-items: center;
  width: 6.5rem;
  height: 6.5rem;
  background: radial-gradient(circle at 35% 30%, var(--color-tertiary-300), var(--color-tertiary-600));
  color: var(--color-on-tertiary);
  box-shadow: 0 12px 24px color-mix(in srgb, var(--color-tertiary-700) 28%, transparent);
  border-radius: var(--radius-full);
}

.att-wifi__ring {
  position: absolute;
  inset: 0;
  border-radius: var(--radius-full);
  border: 1.5px solid color-mix(in srgb, var(--color-tertiary-400) 55%, transparent);
  animation: att-ping 2.4s ease-out infinite;
}

.att-wifi__ring--2 {
  animation-delay: 1.2s;
}

@keyframes att-ping {
  0% { transform: scale(1); opacity: 0.6; }
  100% { transform: scale(1.6); opacity: 0; }
}

@media (prefers-reduced-motion: reduce) {
  .att-wifi__ring {
    animation: none;
    display: none;
  }
}

.att-wifi h2 {
  font-size: 1.25rem;
}

.att-panel {
  width: 100%;
  padding: var(--space-2) var(--space-3);
  text-align: left;
  border-radius: 20px;
}

.att-panel p {
  display: grid;
  grid-template-columns: 1.5rem 1fr auto;
  align-items: center;
  gap: var(--space-2);
  margin: 0;
  padding: var(--space-3) 0;
  color: var(--color-tertiary-700);
}

.att-panel p + p {
  box-shadow: inset 0 1px 0 var(--color-border);
}

.att-panel span {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.att-panel strong {
  color: var(--color-text);
}

.att-face {
  position: relative;
  height: min(62vw, 420px);
  min-height: 280px;
  overflow: hidden;
  background: #8eb6e4;
  border-radius: 28px;
  box-shadow: var(--att-shadow);
}

.att-face__video,
.att-face__art {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}

.att-face__video {
  object-fit: cover;
  transform: scaleX(-1);
}

.att-face__frame {
  position: absolute;
  inset: 12% 10%;
}

.att-face__frame span,
.att-qr__frame span {
  position: absolute;
  width: 28px;
  height: 28px;
  border-style: solid;
  border-color: var(--color-success);
}

.att-face__frame span:nth-child(1),
.att-qr__frame span:nth-child(1) { top: 0; left: 0; border-width: 3px 0 0 3px; }
.att-face__frame span:nth-child(2),
.att-qr__frame span:nth-child(2) { top: 0; right: 0; border-width: 3px 3px 0 0; }
.att-face__frame span:nth-child(3),
.att-qr__frame span:nth-child(3) { bottom: 0; left: 0; border-width: 0 0 3px 3px; }
.att-face__frame span:nth-child(4),
.att-qr__frame span:nth-child(4) { right: 0; bottom: 0; border-width: 0 3px 3px 0; }

.att-face__caption {
  position: absolute;
  left: var(--space-3);
  right: var(--space-3);
  bottom: var(--space-3);
  margin: 0;
  padding: var(--space-2) var(--space-3);
  border-radius: 12px;
  background: color-mix(in srgb, var(--color-surface) 92%, transparent);
  color: var(--color-text);
  font-size: 0.8125rem;
  text-align: center;
}

.att-face__status {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.att-dot {
  width: 0.55rem;
  height: 0.55rem;
  border-radius: var(--radius-full);
  background: var(--color-success);
}

.att-dot--wait {
  background: var(--color-warning);
}

.att-qr {
  align-items: center;
  padding-top: var(--space-2);
}

.att-qr__frame {
  position: relative;
  width: min(100%, 280px);
  height: 280px;
  background: linear-gradient(#eef5ff, #d5e6fb);
  box-shadow: var(--att-shadow);
  border-radius: 28px;
}

.att-qr__frame i {
  position: absolute;
  left: 12%;
  right: 12%;
  height: 2px;
  background: var(--color-tertiary-500);
  animation: att-scan 1.8s ease-in-out infinite;
}

.att-code {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-4);
  background: var(--color-surface);
  box-shadow: var(--att-shadow);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-weight: 700;
  border-radius: 24px;
}

.att-code input {
  width: 100%;
  border: 0;
  border-radius: 12px;
  background: var(--color-tertiary-50);
  color: var(--color-text);
  font: inherit;
  font-size: 1.125rem;
  font-weight: 700;
  padding: var(--space-3);
}

.att-code input:focus {
  outline: 2px solid var(--color-tertiary-500);
}

.att-code__error {
  margin: 0;
  color: var(--color-danger-tint-fg);
  font-size: 0.8125rem;
}

.att-success {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding-top: var(--space-3);
}

.att-success__confetti {
  width: 100%;
  max-width: 20rem;
  height: 4rem;
  margin-bottom: calc(-1 * var(--space-3));
}

.att-success__badge {
  position: relative;
  display: grid;
  place-items: center;
  width: 5.5rem;
  height: 5.5rem;
  border-radius: var(--radius-full);
  background: radial-gradient(circle at 35% 30%, color-mix(in srgb, white 28%, var(--color-success)), var(--color-success));
  color: var(--color-on-tertiary);
  box-shadow: 0 0 0 12px color-mix(in srgb, var(--color-success) 16%, transparent);
}

.att-success__badge::before {
  content: '';
  position: absolute;
  inset: -0.85rem;
  border-radius: var(--radius-full);
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-success) 45%, transparent);
}

.att-success h2 {
  margin-top: var(--space-4);
  font-size: 1.55rem;
}

.att-success__name {
  margin: var(--space-3) 0 0;
  font-weight: 800;
}

.att-facts {
  width: 100%;
  margin: var(--space-4) 0 0;
  padding: var(--space-2) var(--space-3);
  list-style: none;
  text-align: left;
  border-radius: 24px;
}

.att-facts li {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3) 0;
}

.att-facts li + li {
  box-shadow: inset 0 1px 0 var(--color-border);
}

.att-facts small {
  display: block;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.att-facts strong {
  color: var(--color-text);
}

.att-note {
  margin: 0;
  padding: var(--space-3);
  border-radius: 14px;
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
  font-size: 0.75rem;
  line-height: 1.45;
}

.att-dock {
  padding: var(--space-2) 0 var(--space-3);
}

.att-cta {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  width: 100%;
  height: 3.25rem;
  border: 0;
  border-radius: var(--radius-full);
  background: linear-gradient(180deg, var(--color-tertiary-500), var(--color-tertiary-700));
  color: var(--color-on-tertiary);
  font: inherit;
  font-size: 1rem;
  font-weight: 800;
  cursor: pointer;
  box-shadow: 0 10px 20px color-mix(in srgb, var(--color-tertiary-700) 30%, transparent);
}

.att-cta:disabled {
  opacity: 0.45;
  cursor: default;
}

@media (prefers-reduced-motion: reduce) {
  .att-qr__frame i {
    animation: none;
    top: 48%;
  }
}

@keyframes att-scan {
  0% { top: 16%; }
  50% { top: 78%; }
  100% { top: 16%; }
}
</style>
