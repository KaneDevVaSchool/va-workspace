<script setup>
//
// Chấm công nhân viên — trang chủ, hình thức, GPS,
// WiFi, khuôn mặt, QR, mã, thành công, cài đặt. Lịch sử xem tại tab Lịch sử
// (/dashboard/me/requests). Chưa nối máy chấm công / HRM: lần bấm lưu trên máy.
//
import { computed, inject, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import AttendanceGlyph from '../components/AttendanceGlyph.vue';
import {
  clearEmployeeAttendanceToday,
  readEmployeeAttendanceLog,
  readEmployeeAttendanceToday,
  writeEmployeeAttendanceToday,
} from '../lib/employeeAttendanceSession';

const PREFS_KEY = 'employee-attendance-prefs-v1';
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
  settings: 'Cài đặt',
};

function readPreferredMethod() {
  try {
    const raw = localStorage.getItem(PREFS_KEY);
    const id = raw ? JSON.parse(raw)?.method : '';
    if (METHODS.some((item) => item.id === id)) return id;
  } catch {
    /* ignore broken prefs */
  }
  return 'button';
}

const auth = useAuthStore();
const now = ref(new Date());
const bodyRef = ref(null);
const videoRef = ref(null);
const stack = ref(['home']);
const punchLog = ref(readEmployeeAttendanceLog());
const preferredMethod = ref(readPreferredMethod());
const geoState = ref('idle');
const coords = ref(null);
const weather = ref(null);
const cameraState = ref('idle');
const faceHelp = ref(false);
const codeValue = ref('');
const codeError = ref('');
const clearArmed = ref(false);
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
const replayWorkspaceIntro = inject('employeeWorkspaceIntroReplay', null);
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

watch(preferredMethod, (id) => {
  try {
    localStorage.setItem(PREFS_KEY, JSON.stringify({ method: id }));
  } catch {
    /* ignore quota */
  }
});

watch(codeValue, () => {
  codeError.value = '';
});

watch(screen, (value, prev) => {
  if (value === 'face') startCamera();
  else if (prev === 'face') stopCamera();
  if (value !== 'settings') clearArmed.value = false;
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

function openSettings() {
  stack.value = ['home', 'settings'];
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
  const id = preferredMethod.value || 'button';
  if (id === 'button') {
    commitPunch('button');
    return;
  }
  openMethod(id);
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

function clearToday() {
  if (!clearArmed.value) {
    clearArmed.value = true;
    return;
  }
  clearEmployeeAttendanceToday();
  reloadPunch();
  clearArmed.value = false;
  showClientToast('success', 'Đã xóa lần chấm hôm nay trên máy này.');
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
    <div class="att">
      <header v-if="showHead" class="att-head">
        <button type="button" class="att-icon-btn" aria-label="Quay lại" @click="back">
          <AppIcon name="chevronLeft" :size="20" />
        </button>
        <h1>{{ headTitle }}</h1>
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
          <article class="att-clock">
            <div class="att-clock__main">
              <p>Thời gian hiện tại</p>
              <strong>{{ timeLabel }}</strong>
              <small>{{ homeDateLabel }}</small>
            </div>
            <div class="att-weather">
              <AttendanceGlyph :name="weatherGlyph" :size="22" />
              <b>{{ weather ? `${weather.temp}°C` : '—°' }}</b>
              <small>{{ weather ? 'Gần bạn' : 'Thời tiết' }}</small>
            </div>
            <button type="button" class="att-icon-btn att-clock__settings" aria-label="Cài đặt chấm công" @click="openSettings">
              <AppIcon name="settings" :size="18" />
            </button>
          </article>

          <p class="att-banner" :class="`att-banner--${geoBanner.tone}`">
            <AppIcon :name="geoBanner.tone === 'ok' ? 'check' : 'info'" :size="16" />
            <span>{{ geoBanner.text }}</span>
          </p>

          <button type="button" class="att-punch" @click="onHero">
            <AttendanceGlyph name="fingerprint" :size="34" />
            <span>{{ heroLabel }}</span>
          </button>
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
          <p class="att-lead">Vui lòng chọn phương thức phù hợp:</p>
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
            <div v-if="cameraState !== 'live'" class="att-face__fallback">
              <AttendanceGlyph name="camera" :size="36" />
            </div>
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
            <AttendanceGlyph name="wifi" :size="42" />
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
          <p class="att-lead">Dành cho trường hợp đặc biệt, khi không dùng được các hình thức kia.</p>
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

        <section v-else-if="screen === 'settings'" class="att-stack">
          <h2 class="att-section">Hình thức ưu tiên</h2>
          <p class="att-lead">Nút Chấm công ngay sẽ mở hình thức này. Chấm bằng nút thì ghi nhận luôn.</p>
          <div class="att-choices" role="radiogroup" aria-label="Hình thức ưu tiên">
            <button
              v-for="method in METHODS"
              :key="method.id"
              type="button"
              role="radio"
              class="att-choice"
              :aria-checked="preferredMethod === method.id"
              @click="preferredMethod = method.id"
            >
              <span class="att-tile__icon" :class="`att-tone--${method.tone}`">
                <AttendanceGlyph :name="method.glyph" :size="18" />
              </span>
              <span>{{ method.label }}</span>
              <AppIcon v-if="preferredMethod === method.id" name="check" :size="16" />
            </button>
          </div>
          <button type="button" class="att-choice" @click="replayWorkspaceIntro?.()">
            <span class="att-tile__icon att-tone--blue"><AppIcon name="sparkles" :size="16" /></span>
            <span>Xem lại giới thiệu Workspace</span>
            <AppIcon name="chevronRight" :size="16" />
          </button>
          <button type="button" class="att-choice att-choice--danger" @click="clearToday">
            <span class="att-tile__icon att-tone--rose"><AppIcon name="trash" :size="16" /></span>
            <span>{{ clearArmed ? 'Bấm lần nữa để xóa' : 'Xóa lần chấm hôm nay trên máy này' }}</span>
          </button>
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
  height: 100%;
  min-height: 0;
  display: flex;
  justify-content: center;
  background: linear-gradient(
    180deg,
    color-mix(in srgb, var(--color-tertiary-200) 72%, white) 0%,
    color-mix(in srgb, var(--color-tertiary-50) 90%, white) 46%,
    color-mix(in srgb, var(--color-tertiary-50) 35%, white) 100%
  );
  color: var(--color-text);
}

.att {
  --att-shadow: 0 10px 28px color-mix(in srgb, var(--color-tertiary-900) 8%, transparent);
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
  padding-bottom: var(--space-2);
}

.att-head {
  display: grid;
  grid-template-columns: 2.5rem 1fr 2.5rem;
  align-items: center;
  gap: var(--space-2);
  min-height: 2.75rem;
  margin-bottom: var(--space-2);
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
  text-align: center;
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
.att-choice:focus-visible,
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
  gap: var(--space-2);
}

.att-clock,
.att-place,
.att-panel,
.att-facts,
.att-day,
.att-notice,
.att-method,
.att-choice,
.att-tile {
  background: var(--color-surface);
  box-shadow: var(--att-shadow);
}

.att-clock {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-3);
  border-radius: 18px;
}

.att-clock__main,
.att-place span:last-child,
.att-notice span:last-child {
  min-width: 0;
  flex: 1;
}

.att-clock__settings {
  flex-shrink: 0;
}

.att-clock p,
.att-success__time,
.att-success__date,
.att-punch-hint {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.att-place strong,
.att-notice strong {
  display: block;
  color: var(--color-tertiary-900);
  font-size: 1rem;
}

.att-clock small,
.att-place small,
.att-notice small,
.att-method small,
.att-day small {
  display: block;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.att-clock strong {
  display: block;
  color: var(--color-tertiary-900);
  font-size: 2.35rem;
  line-height: 1;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.04em;
}

.att-weather {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  min-width: 4.5rem;
  padding: var(--space-2);
  border-radius: 18px;
  background: var(--color-tertiary-50);
  color: var(--color-gold-600);
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

.att-punch {
  align-self: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  width: 9.25rem;
  height: 9.25rem;
  margin-top: var(--space-2);
  border: 8px solid color-mix(in srgb, var(--color-tertiary-200) 80%, white);
  border-radius: var(--radius-full);
  background: radial-gradient(circle at 35% 28%, var(--color-tertiary-400), var(--color-tertiary-600) 58%, var(--color-tertiary-800));
  color: var(--color-on-tertiary);
  font: inherit;
  cursor: pointer;
  box-shadow: 0 14px 28px color-mix(in srgb, var(--color-tertiary-800) 28%, transparent);
}

.att-punch span {
  max-width: 6.6rem;
  font-size: 0.84rem;
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
.att-method,
.att-choice {
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
  border-radius: 18px;
  text-align: center;
  font-size: 0.75rem;
  font-weight: 700;
}

.att-tile__icon {
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 14px;
  color: var(--color-on-tertiary);
}

.att-tone--blue { background: var(--color-tertiary-500); }
.att-tone--violet { background: #6d5efc; }
.att-tone--orange { background: var(--color-warning); }
.att-tone--green { background: var(--color-success); }
.att-tone--teal { background: var(--color-secondary); }
.att-tone--rose { background: #e85d8c; }
.att-tone--slate { background: #64748b; }

.att-method,
.att-choice {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  width: 100%;
  padding: var(--space-3);
  border-radius: 18px;
}

.att-method__text {
  flex: 1;
  min-width: 0;
}

.att-method strong,
.att-choice span:nth-child(2) {
  display: block;
  color: var(--color-text);
  font-weight: 700;
}

.att-method svg:last-child,
.att-choice svg:last-child {
  color: var(--color-text-muted);
}

.att-choice[aria-checked='true'] {
  box-shadow: 0 0 0 2px var(--color-tertiary-500), var(--att-shadow);
}

.att-choice[aria-checked='true'] svg:last-child {
  color: var(--color-tertiary-600);
}

.att-choice--danger {
  color: var(--color-danger-tint-fg);
}

.att-map {
  position: relative;
  height: 220px;
  overflow: hidden;
  border-radius: 22px;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.2), transparent 28%),
    repeating-linear-gradient(90deg, #d5e7c4 0 26px, #c3dcb0 26px 48px);
  box-shadow: var(--att-shadow);
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
  border-radius: 18px;
}

.att-wifi {
  align-items: center;
  text-align: center;
}

.att-wifi__orb {
  display: grid;
  place-items: center;
  width: 6.5rem;
  height: 6.5rem;
  border-radius: var(--radius-full);
  background: radial-gradient(circle at 35% 30%, var(--color-tertiary-300), var(--color-tertiary-600));
  color: var(--color-on-tertiary);
  box-shadow: 0 12px 24px color-mix(in srgb, var(--color-tertiary-700) 28%, transparent);
}

.att-wifi h2 {
  font-size: 1.25rem;
}

.att-panel {
  width: 100%;
  padding: var(--space-2) var(--space-3);
  border-radius: 18px;
  text-align: left;
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
  border-radius: 28px;
  background: #8eb6e4;
}

.att-face__video,
.att-face__fallback {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}

.att-face__video {
  object-fit: cover;
  transform: scaleX(-1);
}

.att-face__fallback {
  display: grid;
  place-items: center;
  color: var(--color-on-tertiary);
  background:
    radial-gradient(circle at 50% 42%, #f3d2bd 0 54px, transparent 55px),
    linear-gradient(#d5e7fb, #7eaddf);
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
  border: 3px solid #3dce86;
}

.att-face__frame span:nth-child(1),
.att-qr__frame span:nth-child(1) { top: 0; left: 0; border-right: 0; border-bottom: 0; }
.att-face__frame span:nth-child(2),
.att-qr__frame span:nth-child(2) { top: 0; right: 0; border-left: 0; border-bottom: 0; }
.att-face__frame span:nth-child(3),
.att-qr__frame span:nth-child(3) { bottom: 0; left: 0; border-right: 0; border-top: 0; }
.att-face__frame span:nth-child(4),
.att-qr__frame span:nth-child(4) { right: 0; bottom: 0; border-left: 0; border-top: 0; }

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
}

.att-qr__frame {
  position: relative;
  width: min(100%, 280px);
  height: 280px;
  border-radius: 24px;
  background:
    linear-gradient(#eef5ff, #d5e6fb);
  box-shadow: var(--att-shadow);
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
  border-radius: 18px;
  background: var(--color-surface);
  box-shadow: var(--att-shadow);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-weight: 700;
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
  padding-top: var(--space-4);
}

.att-success__badge {
  display: grid;
  place-items: center;
  width: 5.5rem;
  height: 5.5rem;
  border-radius: var(--radius-full);
  background: radial-gradient(circle at 35% 30%, color-mix(in srgb, white 28%, var(--color-success)), var(--color-success));
  color: var(--color-on-tertiary);
  box-shadow: 0 0 0 12px color-mix(in srgb, var(--color-success) 16%, transparent);
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
  border-radius: 18px;
  list-style: none;
  text-align: left;
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

.att-month {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-2) var(--space-3);
  border-radius: 16px;
  background: var(--color-surface);
  box-shadow: var(--att-shadow);
  color: var(--color-tertiary-900);
  font-weight: 800;
}

.att-month button {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border: 0;
  border-radius: var(--radius-full);
  background: var(--color-tertiary-50);
  color: var(--color-tertiary-700);
  cursor: pointer;
}

.att-month button:disabled {
  opacity: 0.35;
  cursor: default;
}

.att-stats {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-2);
}

.att-stats article {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3);
  border-radius: 16px;
  background: var(--color-surface);
  box-shadow: var(--att-shadow);
}

.att-stats small,
.att-day h3 {
  display: block;
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.att-stats strong {
  color: var(--color-tertiary-900);
  font-size: 0.95rem;
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

.att-day {
  padding: var(--space-3);
  border-radius: 18px;
}

.att-day h3 {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
  margin-bottom: var(--space-2);
  color: var(--color-tertiary-800);
  font-size: 0.84rem;
}

.att-day__sample {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.68rem;
  font-weight: 700;
}

.att-day ul {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin: 0;
  padding: 0;
  list-style: none;
}

.att-day li {
  display: grid;
  grid-template-columns: 1.6rem 1fr auto;
  gap: var(--space-2);
  align-items: start;
}

.att-day b {
  font-variant-numeric: tabular-nums;
}

.att-day em {
  font-style: normal;
  font-size: 0.68rem;
  font-weight: 800;
}

.att-day em.is-ok { color: var(--color-success-tint-fg); }
.att-day em.is-late { color: var(--color-warning-tint-fg); }

.att-day__mark {
  display: grid;
  place-items: center;
  width: 1.6rem;
  height: 1.6rem;
  border-radius: var(--radius-full);
  color: var(--color-on-tertiary);
}

.att-day__mark--in { background: var(--color-success); }
.att-day__mark--out { background: var(--color-tertiary-500); }

.att-day__empty {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.att-notice {
  display: flex;
  gap: var(--space-3);
  padding: var(--space-3);
  border-radius: 18px;
}

.att-section {
  font-size: 0.95rem;
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

.att-nav {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  margin: 0 calc(var(--space-3) * -1);
  padding: 8px 6px calc(8px + env(safe-area-inset-bottom, 0px));
  border-radius: 22px 22px 0 0;
  background: var(--color-surface);
  box-shadow: 0 -8px 24px color-mix(in srgb, var(--color-tertiary-900) 8%, transparent);
}

.att-nav__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  border: 0;
  background: transparent;
  color: var(--color-text-muted);
  font: inherit;
  font-size: 0.68rem;
  font-weight: 700;
  cursor: pointer;
}

.att-nav__item--active {
  color: var(--color-tertiary-600);
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
