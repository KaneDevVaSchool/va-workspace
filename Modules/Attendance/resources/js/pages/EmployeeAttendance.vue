<script setup>
//
// Chấm công nhân viên — trang chủ, hình thức, GPS,
// WiFi, khuôn mặt, QR, mã, thành công. Lịch sử xem tại tab Lịch sử
// (/dashboard/me/requests). Giờ vào/ra gửi về HRM; nếu HRM lỗi thì giữ
// trên máy và báo đang đồng bộ lại.
//
import { computed, inject, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import jsQR from 'jsqr';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import AppIcon from '@/components/AppIcon.vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import AttendanceGlyph from '../components/AttendanceGlyph.vue';
import {
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
  { id: 'qr', label: 'Quét QR', hint: 'Đưa mã tại điểm chấm vào khung', glyph: 'qr', tone: 'violet' },
  { id: 'face', label: 'Camera Hanet', hint: 'Đưa mặt vào khung camera', glyph: 'camera', tone: 'orange' },
  { id: 'gps', label: 'GPS', hint: 'Gắn lần chấm với vị trí máy', glyph: 'pin', tone: 'green' },
  { id: 'wifi', label: 'Chấm bằng WiFi', hint: 'Khi đang dùng mạng công ty', glyph: 'wifi', tone: 'teal' },
  { id: 'code', label: 'Nhập mã', hint: 'Khi không dùng được cách khác', glyph: 'keyboard', tone: 'rose' },
];

const HEADINGS = {
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
const qrVideoRef = ref(null);
const qrCanvasRef = ref(null);
const mapElRef = ref(null);
const stack = ref(['home']);
const punching = ref(false);
const syncState = ref('idle');
const geoState = ref('idle');
const coords = ref(null);
const weather = ref(null);
const cameraState = ref('idle');
const faceHelp = ref(false);
const codeValue = ref('');
const codeError = ref('');
const lastResult = ref(null);
const qrState = ref('idle');
const qrValue = ref('');
const wifiState = ref('idle');
const wifiSignal = ref(0);

const stored = readEmployeeAttendanceToday();
const checkInTime = ref(stored?.checkInTime ?? null);
const checkOutTime = ref(stored?.checkOutTime ?? null);
const checkInMethod = ref(stored?.checkInMethod ?? null);
const checkOutMethod = ref(stored?.checkOutMethod ?? null);

let timer = 0;
let hydrateToken = 0;
let cameraToken = 0;
let mediaStream = null;
let qrCameraToken = 0;
let qrMediaStream = null;
let qrScanFrame = 0;
let geoMap = null;
let geoMarker = null;
let geoRing = null;
let wifiTimer = 0;

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
const syncLine = computed(() => {
  if (syncState.value === 'syncing') {
    return { text: 'Đang đồng bộ với HRM', wait: true };
  }
  if (syncState.value === 'pending') {
    return { text: 'Đã chấm công, đang đồng bộ lại với HRM', wait: true };
  }
  if (syncState.value === 'synced' && (checkInTime.value || checkOutTime.value)) {
    return { text: 'Đã đồng bộ với HRM', wait: false };
  }
  return null;
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
const qrCaption = computed(() => {
  if (qrState.value === 'found') return 'Đã nhận diện mã QR, đang ghi nhận...';
  if (qrState.value === 'live') return 'Đưa mã QR vào giữa khung hình';
  if (qrState.value === 'connecting') return 'Đang mở camera…';
  if (qrState.value === 'denied') return 'Chưa cấp quyền camera — nhập mã thủ công bên dưới';
  if (qrState.value === 'unavailable') return 'Thiết bị không hỗ trợ camera — nhập mã thủ công bên dưới';
  return 'Đưa mã QR tại điểm chấm công vào khung hình';
});
const screenSubtitle = computed(() => {
  if (screen.value === 'face') return 'Quét khuôn mặt tại điểm chấm công';
  if (screen.value === 'gps') return 'Đối chiếu vị trí thiết bị với vùng cho phép';
  if (screen.value === 'wifi') return 'Đối chiếu mạng WiFi công ty';
  if (screen.value === 'qr') return 'Đưa mã tại điểm chấm công vào khung hình';
  if (screen.value === 'code') return 'Dành cho trường hợp đặc biệt';
  return '';
});
const qrFallback = computed(() => ['denied', 'unavailable'].includes(qrState.value));
const wifiCaption = computed(() => (
  wifiState.value === 'connected' ? 'Đã kết nối WiFi công ty' : 'Đang kiểm tra kết nối…'
));
const wifiBars = computed(() => Math.min(4, Math.max(0, Math.round(wifiSignal.value / 25))));
const dock = computed(() => {
  if (screen.value === 'success') return { label: 'Quay lại trang chủ', action: 'home' };
  if (screen.value === 'gps') return { label: 'Chấm công', action: 'punch-gps' };
  if (screen.value === 'wifi') return { label: 'Chấm công', action: 'punch-wifi' };
  if (screen.value === 'face') return { label: 'Ghi nhận khuôn mặt', action: 'punch-face' };
  if (screen.value === 'qr' && qrFallback.value) return { label: 'Chấm công', action: 'punch-qr-code' };
  if (screen.value === 'code') return { label: 'Chấm công', action: 'punch-code' };
  return null;
});
const dockDisabled = computed(() => {
  if (punching.value) return true;
  if (screen.value === 'code') return codeValue.value.trim().length < 4;
  if (screen.value === 'qr' && qrFallback.value) return qrValue.value.trim().length < 4;
  if (screen.value === 'wifi') return wifiState.value !== 'connected';
  return false;
});
const resultGlyph = computed(() => {
  const id = lastResult.value?.methodId;
  return METHODS.find((item) => item.id === id)?.glyph || 'check';
});

watch(codeValue, () => {
  codeError.value = '';
});

watch(qrValue, () => {
  codeError.value = '';
});

watch(screen, (value, prev) => {
  if (value === 'face') startCamera();
  else if (prev === 'face') stopCamera();
  if (value === 'qr') startQrCamera();
  else if (prev === 'qr') stopQrCamera();
  if (value === 'gps') nextTick(() => initGeoMap());
  else if (prev === 'gps') destroyGeoMap();
  if (value === 'wifi') startWifiCheck();
  else if (prev === 'wifi') stopWifiCheck();
}, { flush: 'post' });

watch(coords, (value) => {
  if (value) updateGeoMap(value);
});

onMounted(() => {
  timer = window.setInterval(() => {
    now.value = new Date();
  }, 1000);
  void hydrateTodayFromHrm();
  requestLocation();
  if (typeof registerPageRefresh === 'function') {
    unregisterPageRefresh = registerPageRefresh(async () => {
      await hydrateTodayFromHrm();
      now.value = new Date();
      if (geoState.value !== 'pending') await requestLocation();
    });
  }
});

onBeforeUnmount(() => {
  window.clearInterval(timer);
  stopCamera();
  stopQrCamera();
  destroyGeoMap();
  stopWifiCheck();
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
}

function hasPunch(record) {
  return Boolean(record?.checkInTime || record?.checkOutTime);
}

function rememberToday(row) {
  writeEmployeeAttendanceToday({
    checkInTime: row?.checkInTime || row?.check_in_time || null,
    checkOutTime: row?.checkOutTime || row?.check_out_time || null,
    checkInMethod: row?.checkInMethod || row?.check_in_method || null,
    checkOutMethod: row?.checkOutMethod || row?.check_out_method || null,
  });
  reloadPunch();
}

function onHero() {
  commitPunch('button');
}

function openMethod(id) {
  if (id === 'code') {
    codeValue.value = '';
    codeError.value = '';
  }
  if (id === 'qr') {
    qrValue.value = '';
    codeError.value = '';
  }
  push(id);
}

async function commitPunch(methodId) {
  if (punching.value) return;
  const method = METHODS.find((item) => item.id === methodId);
  const methodLabel = method?.label || methodId;
  const fresh = new Date();
  const label = formatHm(fresh);
  now.value = fresh;
  const checkingOut = Boolean(checkInTime.value && !checkOutTime.value);
  punching.value = true;
  hydrateToken += 1;
  if (checkingOut) {
    checkOutTime.value = label;
    checkOutMethod.value = methodLabel;
  } else {
    checkInTime.value = label;
    checkInMethod.value = methodLabel;
  }
  rememberToday({
    checkInTime: checkInTime.value,
    checkOutTime: checkOutTime.value,
    checkInMethod: checkInMethod.value,
    checkOutMethod: checkOutMethod.value,
  });
  syncState.value = 'syncing';
  let synced = false;
  try {
    const { data } = await window.axios.post('/api/attendance/punches', {
      direction: checkingOut ? 'out' : 'in',
      method: methodLabel,
    });
    const remote = {
      checkInTime: data?.data?.check_in_time || null,
      checkOutTime: data?.data?.check_out_time || null,
      checkInMethod: data?.data?.check_in_method || null,
      checkOutMethod: data?.data?.check_out_method || null,
    };
    if (hasPunch(remote)) rememberToday(remote);
    synced = true;
    syncState.value = 'synced';
  } catch {
    syncState.value = 'pending';
  } finally {
    punching.value = false;
  }
  lastResult.value = {
    action: checkingOut ? 'out' : 'in',
    time: checkingOut ? (checkOutTime.value || label) : (checkInTime.value || label),
    methodId,
    method: methodLabel,
    dateLabel: formatLongDate(fresh),
    synced,
  };
  stack.value = ['home', 'success'];
  scrollTop();
  if (typeof navigator !== 'undefined' && navigator.vibrate) navigator.vibrate(12);
}

async function hydrateTodayFromHrm() {
  const token = ++hydrateToken;
  if (hasPunch(readEmployeeAttendanceToday())) syncState.value = 'syncing';
  try {
    const { data } = await window.axios.get('/api/attendance/punches/today');
    if (token !== hydrateToken) return;
    const remote = {
      checkInTime: data?.data?.check_in_time || null,
      checkOutTime: data?.data?.check_out_time || null,
      checkInMethod: data?.data?.check_in_method || null,
      checkOutMethod: data?.data?.check_out_method || null,
    };
    if (hasPunch(remote)) {
      rememberToday(remote);
      syncState.value = 'synced';
      return;
    }
    syncState.value = hasPunch(readEmployeeAttendanceToday()) ? 'pending' : 'idle';
  } catch {
    if (token !== hydrateToken) return;
    syncState.value = hasPunch(readEmployeeAttendanceToday()) ? 'pending' : 'idle';
  }
}

async function punchGps() {
  if (geoState.value !== 'granted') {
    const ok = await requestLocation();
    if (!ok) return;
  }
  await commitPunch('gps');
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
    await commitPunch('wifi');
    return;
  }
  if (action === 'punch-face') {
    await commitPunch('face');
    return;
  }
  if (action === 'punch-qr-code') {
    if (qrValue.value.trim().length < 4) {
      codeError.value = 'Nhập ít nhất 4 ký tự.';
      return;
    }
    await commitPunch('qr');
    return;
  }
  if (action === 'punch-code') {
    if (codeValue.value.trim().length < 4) {
      codeError.value = 'Nhập ít nhất 4 ký tự.';
      return;
    }
    await commitPunch('code');
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

function initGeoMap() {
  if (geoMap || !mapElRef.value) return;
  const fallback = { lat: 21.0278, lon: 105.8342 };
  const point = coords.value || fallback;
  geoMap = L.map(mapElRef.value, {
    attributionControl: false,
    zoomControl: false,
    dragging: true,
    scrollWheelZoom: false,
  }).setView([point.lat, point.lon], coords.value ? 17 : 13);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
  }).addTo(geoMap);

  const pinIcon = L.divIcon({
    className: 'att-geo-pin',
    html: '<span></span>',
    iconSize: [34, 34],
    iconAnchor: [17, 30],
  });
  geoMarker = L.marker([point.lat, point.lon], { icon: pinIcon }).addTo(geoMap);
  geoRing = L.circle([point.lat, point.lon], {
    radius: coords.value?.accuracy || 40,
    className: 'att-geo-ring',
    weight: 1.5,
    fillOpacity: 0.12,
  }).addTo(geoMap);

  requestAnimationFrame(() => geoMap?.invalidateSize());
}

function updateGeoMap(point) {
  if (!geoMap) return;
  geoMap.setView([point.lat, point.lon], 17);
  geoMarker?.setLatLng([point.lat, point.lon]);
  geoRing?.setLatLng([point.lat, point.lon]);
  geoRing?.setRadius(point.accuracy || 40);
}

function destroyGeoMap() {
  geoMap?.remove();
  geoMap = null;
  geoMarker = null;
  geoRing = null;
}

function startWifiCheck() {
  wifiState.value = 'checking';
  wifiSignal.value = 0;
  window.clearInterval(wifiTimer);
  wifiTimer = window.setInterval(() => {
    wifiSignal.value = Math.min(100, wifiSignal.value + 18);
    if (wifiSignal.value >= 100) {
      window.clearInterval(wifiTimer);
      wifiState.value = 'connected';
    }
  }, 160);
}

function stopWifiCheck() {
  window.clearInterval(wifiTimer);
  wifiState.value = 'idle';
  wifiSignal.value = 0;
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

async function startQrCamera() {
  const token = ++qrCameraToken;
  if (!navigator.mediaDevices?.getUserMedia) {
    qrState.value = 'unavailable';
    return;
  }
  qrState.value = 'connecting';
  try {
    const stream = await navigator.mediaDevices.getUserMedia({
      audio: false,
      video: { facingMode: { ideal: 'environment' } },
    });
    if (token !== qrCameraToken) {
      stream.getTracks().forEach((track) => track.stop());
      return;
    }
    qrMediaStream = stream;
    await nextTick();
    if (token !== qrCameraToken || !qrVideoRef.value) return;
    qrVideoRef.value.srcObject = stream;
    await qrVideoRef.value.play().catch(() => {});
    if (token !== qrCameraToken) return;
    qrState.value = 'live';
    scanQrFrame(token);
  } catch {
    if (token === qrCameraToken) qrState.value = 'denied';
  }
}

function scanQrFrame(token) {
  qrScanFrame = requestAnimationFrame(() => {
    if (token !== qrCameraToken || qrState.value !== 'live') return;
    const video = qrVideoRef.value;
    const canvas = qrCanvasRef.value;
    if (video && canvas && video.readyState === video.HAVE_ENOUGH_DATA) {
      const width = video.videoWidth;
      const height = video.videoHeight;
      if (width && height) {
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, width, height);
        const frame = ctx.getImageData(0, 0, width, height);
        const result = jsQR(frame.data, width, height, { inversionAttempts: 'dontInvert' });
        if (result?.data) {
          qrState.value = 'found';
          qrValue.value = result.data;
          if (typeof navigator !== 'undefined' && navigator.vibrate) navigator.vibrate(16);
          window.setTimeout(() => {
            if (token === qrCameraToken) commitPunch('qr');
          }, 420);
          return;
        }
      }
    }
    scanQrFrame(token);
  });
}

function stopQrCamera() {
  qrCameraToken += 1;
  cancelAnimationFrame(qrScanFrame);
  qrMediaStream?.getTracks()?.forEach((track) => track.stop());
  qrMediaStream = null;
  if (qrVideoRef.value) qrVideoRef.value.srcObject = null;
  qrState.value = 'idle';
}
</script>

<template>
  <div class="att-page">
    <div class="att-aurora" aria-hidden="true"></div>
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
            <div class="att-hero__top">
              <p class="att-hero__greet">
                {{ heroLabel === 'Chấm công ngay' ? 'Chào' : 'Đang làm việc,' }} <b>{{ displayName }}</b>
              </p>
              <p class="att-hero__place">
                <AttendanceGlyph name="pin" :size="13" />
                <span>{{ placeLabel }}</span>
              </p>
            </div>
            <div class="att-hero__row">
              <div class="att-clock">
                <strong>{{ timeLabel }}</strong>
                <small>{{ homeDateLabel }}</small>
              </div>
              <div class="att-weather">
                <AttendanceGlyph :name="weatherGlyph" :size="20" />
                <b>{{ weather ? `${weather.temp}°C` : '—°' }}</b>
              </div>
            </div>
            <p class="att-hero__geo" :class="`att-hero__geo--${geoBanner.tone}`">
              <AppIcon :name="geoBanner.tone === 'ok' ? 'check' : 'info'" :size="14" />
              <span>{{ geoBanner.text }}</span>
            </p>
            <p v-if="syncLine" class="att-sync att-sync--on-hero">
              <span class="att-dot" :class="{ 'att-dot--wait': syncLine.wait }"></span>
              <span>{{ syncLine.text }}</span>
            </p>
          </article>

          <div class="att-punch-wrap">
            <button
              type="button"
              class="att-punch"
              :disabled="punching"
              :aria-busy="punching"
              @click="onHero"
            >
              <svg v-if="punching" class="att-spin" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2.5" stroke-dasharray="14 42" stroke-linecap="round" />
              </svg>
              <AttendanceGlyph v-else name="fingerprint" :size="36" />
              <span>{{ punching ? 'Đang gửi…' : heroLabel }}</span>
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
              <span class="att-tile__label">{{ tile.label }}</span>
              <small class="att-tile__hint">{{ tile.hint }}</small>
            </button>
          </div>
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
                  <stop offset="0%" stop-color="var(--color-tertiary-200)" />
                  <stop offset="100%" stop-color="var(--color-tertiary-400)" />
                </linearGradient>
              </defs>
              <rect width="300" height="260" fill="url(#att-face-sky)" />
              <circle cx="60" cy="52" r="34" fill="color-mix(in srgb, white 30%, transparent)" />
              <g transform="translate(90 70)">
                <ellipse cx="60" cy="150" rx="70" ry="14" fill="color-mix(in srgb, black 10%, transparent)" />
                <path d="M18 150v-40c0-30 18-52 42-52s42 22 42 52v40z" fill="var(--color-tertiary-900)" />
                <circle cx="60" cy="48" r="30" fill="var(--color-gold-200)" />
                <path d="M32 40a28 28 0 0 1 56-2c-10 3-40 3-56 2z" fill="var(--color-umber-900)" />
                <circle cx="48" cy="48" r="2.6" fill="var(--color-umber-900)" />
                <circle cx="72" cy="48" r="2.6" fill="var(--color-umber-900)" />
                <path d="M50 60c4 4 16 4 20 0" stroke="var(--color-umber-400)" stroke-width="2.4" fill="none" stroke-linecap="round" />
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
          <div class="att-map">
            <div ref="mapElRef" class="att-map__canvas"></div>
            <span class="att-map__bubble">{{ placeLabel }}</span>
            <span v-if="geoState === 'pending'" class="att-map__loading">
              <svg class="att-spin" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2.5" stroke-dasharray="14 42" stroke-linecap="round" />
              </svg>
              <span>Đang định vị…</span>
            </span>
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
          <span class="att-wifi__orb" :class="{ 'att-wifi__orb--on': wifiState === 'connected' }" aria-hidden="true">
            <span class="att-wifi__ring att-wifi__ring--1"></span>
            <span class="att-wifi__ring att-wifi__ring--2"></span>
            <AttendanceGlyph name="wifi" :size="40" />
          </span>
          <h2>{{ wifiCaption }}</h2>

          <article class="att-panel att-panel--wifi">
            <p>
              <AttendanceGlyph name="wifi" :size="18" />
              <span>Mạng</span>
              <strong>WiFi công ty</strong>
            </p>
            <p>
              <AttendanceGlyph name="signal" :size="18" />
              <span>Cường độ</span>
              <span class="att-wifi__bars" role="img" aria-label="Cường độ tín hiệu">
                <i v-for="bar in 4" :key="bar" :class="{ 'att-wifi__bar--on': bar <= wifiBars }"></i>
              </span>
            </p>
          </article>

          <p v-if="wifiState === 'connected'" class="att-banner att-banner--ok">
            <AppIcon name="check" :size="16" />
            <span>Đã kết nối — có thể chấm công.</span>
          </p>
        </section>

        <section v-else-if="screen === 'qr'" class="att-stack att-qr">
          <div class="att-qr__frame" :class="{ 'att-qr__frame--found': qrState === 'found' }">
            <video
              v-show="qrState === 'live' || qrState === 'found'"
              ref="qrVideoRef"
              class="att-qr__video"
              playsinline
              muted
              autoplay
            />
            <canvas ref="qrCanvasRef" class="att-qr__canvas" aria-hidden="true"></canvas>
            <div v-if="qrState !== 'live' && qrState !== 'found'" class="att-qr__fallback" aria-hidden="true">
              <AttendanceGlyph name="qr" :size="40" />
            </div>
            <span></span><span></span><span></span><span></span>
            <i v-if="qrState === 'live'"></i>
            <span v-if="qrState === 'found'" class="att-qr__check" aria-hidden="true">
              <AppIcon name="check" :size="30" :stroke-width="3" />
            </span>
          </div>
          <p class="att-lead">{{ qrCaption }}</p>

          <label v-if="qrFallback" class="att-code">
            <span>Mã QR (nhập thủ công)</span>
            <input
              v-model="qrValue"
              type="text"
              inputmode="text"
              autocomplete="off"
              autocapitalize="off"
              maxlength="40"
              placeholder="Nhập nội dung mã QR"
            />
          </label>
          <p v-if="qrFallback && codeError" class="att-code__error">{{ codeError }}</p>
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
          <div class="att-success__mark" aria-hidden="true">
            <AppIcon name="check" :size="40" :stroke-width="2.6" />
          </div>
          <h2>Chấm công thành công!</h2>
          <p class="att-success__name">{{ displayName }}</p>
          <p class="att-success__time">
            {{ lastResult?.action === 'out' ? 'Đã chấm ra lúc' : 'Đã chấm công lúc' }}
            {{ lastResult?.time }}
          </p>
          <p class="att-success__date">{{ lastResult?.dateLabel }}</p>
          <p v-if="lastResult?.synced" class="att-sync att-sync--plain">
            <span class="att-dot"></span>
            <span>Đã đồng bộ với HRM</span>
          </p>
          <p v-else-if="lastResult" class="att-sync att-sync--plain">
            <span class="att-dot att-dot--wait"></span>
            <span>Đã chấm công, đang đồng bộ lại với HRM</span>
          </p>
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
        <button type="button" class="att-cta" :disabled="dockDisabled" :aria-busy="punching" @click="onDock">
          <svg v-if="punching" class="att-spin" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2.5" stroke-dasharray="14 42" stroke-linecap="round" />
          </svg>
          {{ punching ? 'Đang gửi…' : dock.label }}
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
  background:
    radial-gradient(circle at 1px 1px, color-mix(in srgb, var(--color-tertiary-300) 38%, transparent) 1px, transparent 0)
      0 0 / 22px 22px,
    var(--color-tertiary-50);
  color: var(--color-text);
  overflow: hidden;
}

.att-aurora {
  position: absolute;
  inset: -10% -10% -6% -10%;
  z-index: 0;
  background:
    radial-gradient(55% 38% at 16% 0%, color-mix(in srgb, var(--color-tertiary-200) 60%, transparent), transparent 72%),
    radial-gradient(45% 32% at 88% 4%, color-mix(in srgb, var(--color-secondary-200) 50%, transparent), transparent 74%),
    radial-gradient(50% 34% at 12% 100%, color-mix(in srgb, var(--color-secondary-100) 55%, transparent), transparent 70%),
    radial-gradient(55% 38% at 92% 96%, color-mix(in srgb, var(--color-tertiary-100) 60%, transparent), transparent 72%);
  pointer-events: none;
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
.att-wifi h2 {
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
.att-tile {
  background: var(--color-surface);
  box-shadow: var(--att-shadow);
}

.att-hero {
  position: relative;
  overflow: hidden;
  width: 100%;
  background: linear-gradient(135deg, var(--color-tertiary-500) 0%, var(--color-tertiary-700) 100%);
  box-shadow: 0 16px 34px color-mix(in srgb, var(--color-tertiary-800) 26%, transparent);
  border-radius: 24px;
  padding: var(--space-4);
}

.att-hero::after {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(70% 80% at 100% 0%, color-mix(in srgb, var(--color-on-tertiary) 18%, transparent), transparent 62%);
  pointer-events: none;
}

.att-hero__top,
.att-hero__row,
.att-hero__geo,
.att-sync--on-hero {
  position: relative;
  z-index: 1;
}

.att-hero__greet {
  margin: 0;
  color: var(--color-on-tertiary);
  font-size: 0.86rem;
}

.att-hero__greet b {
  font-weight: 800;
}

.att-hero__place {
  display: flex;
  align-items: center;
  gap: 4px;
  margin: 2px 0 0;
  overflow: hidden;
  color: color-mix(in srgb, white 76%, transparent);
  font-size: 0.75rem;
}

.att-hero__place span {
  min-width: 0;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.att-hero__row {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--space-2);
  margin-top: var(--space-3);
}

.att-clock {
  min-width: 0;
}

.att-clock strong {
  display: block;
  color: var(--color-on-tertiary);
  font-size: 2.85rem;
  line-height: 1;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.045em;
  text-shadow: 0 1px 0 color-mix(in srgb, var(--color-tertiary-900) 45%, transparent);
}

.att-clock small {
  display: block;
  margin-top: 4px;
  color: var(--color-on-tertiary);
  font-size: 0.84rem;
  font-weight: 600;
}

.att-weather {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 6px var(--space-3);
  background: color-mix(in srgb, white 16%, transparent);
  color: var(--color-on-tertiary);
  border-radius: var(--radius-full);
  backdrop-filter: blur(4px);
}

.att-weather b {
  font-size: 0.88rem;
  font-weight: 700;
}

.att-hero__geo {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: var(--space-3) 0 0;
  padding: 7px var(--space-3);
  background: color-mix(in srgb, white 14%, transparent);
  border-radius: 12px;
  color: var(--color-on-tertiary);
  font-size: 0.75rem;
  font-weight: 700;
}

.att-hero__geo--info {
  background: color-mix(in srgb, var(--color-tertiary-900) 30%, transparent);
  color: color-mix(in srgb, white 88%, transparent);
  font-weight: 600;
}

.att-success__time,
.att-success__date,
.att-punch-hint {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
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
  gap: 6px;
  width: 9.5rem;
  height: 9.5rem;
  border: 8px solid color-mix(in srgb, var(--color-tertiary-100) 82%, var(--color-on-tertiary));
  border-radius: var(--radius-full);
  background:
    radial-gradient(circle at 32% 24%, color-mix(in srgb, var(--color-on-tertiary) 32%, var(--color-tertiary-400)), transparent 46%),
    linear-gradient(165deg, var(--color-tertiary-400) 0%, var(--color-tertiary-600) 48%, var(--color-tertiary-900) 100%);
  color: var(--color-on-tertiary);
  font: inherit;
  cursor: pointer;
  box-shadow:
    0 18px 34px color-mix(in srgb, var(--color-tertiary-900) 34%, transparent),
    inset 0 1px 0 color-mix(in srgb, var(--color-on-tertiary) 42%, transparent);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.att-punch:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow:
    0 22px 40px color-mix(in srgb, var(--color-tertiary-900) 38%, transparent),
    inset 0 1px 0 color-mix(in srgb, var(--color-on-tertiary) 42%, transparent);
}

.att-punch:active:not(:disabled) {
  transform: scale(0.97);
}

.att-punch:disabled {
  cursor: progress;
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

.att-tile {
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
  justify-content: flex-start;
  gap: 0.35rem;
  min-height: 7.25rem;
  padding: var(--space-3) var(--space-2);
  text-align: center;
  font-size: 0.75rem;
  font-weight: 700;
  border-radius: 20px;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.att-tile:hover {
  transform: translateY(-2px);
  box-shadow: 0 16px 28px color-mix(in srgb, var(--color-tertiary-900) 14%, transparent);
}

.att-tile:active {
  transform: translateY(0) scale(0.98);
}

.att-tile__label {
  line-height: 1.25;
}

.att-tile__hint {
  color: var(--color-text-muted);
  font-size: 0.68rem;
  font-weight: 500;
  line-height: 1.35;
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
.att-tone--violet { background: var(--color-tertiary-800); }
.att-tone--orange { background: var(--color-warning); }
.att-tone--green { background: var(--color-success); }
.att-tone--teal { background: var(--color-secondary); }
.att-tone--rose { background: var(--color-primary-500); }

.att-map {
  position: relative;
  height: 220px;
  overflow: hidden;
  background: var(--color-tertiary-50);
  box-shadow: var(--att-shadow);
  border-radius: 24px;
}

.att-map__canvas {
  position: absolute;
  inset: 0;
  z-index: 0;
}

.att-map__bubble {
  position: absolute;
  z-index: 1;
  left: 50%;
  top: var(--space-3);
  max-width: 70%;
  padding: 6px 10px;
  border-radius: 12px;
  background: var(--color-surface);
  color: var(--color-tertiary-900);
  font-size: 0.75rem;
  font-weight: 700;
  transform: translateX(-50%);
  box-shadow: var(--shadow-sm);
  pointer-events: none;
}

.att-map__loading {
  position: absolute;
  z-index: 1;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  background: color-mix(in srgb, var(--color-tertiary-50) 88%, transparent);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-weight: 700;
  pointer-events: none;
}

.att-spin {
  width: 1.2rem;
  height: 1.2rem;
  flex-shrink: 0;
  animation: att-spin 0.7s linear infinite;
}

@keyframes att-spin {
  to { transform: rotate(360deg); }
}

@media (prefers-reduced-motion: reduce) {
  .att-spin {
    animation: none;
  }
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
  transition: background 0.4s ease, box-shadow 0.4s ease;
}

.att-wifi__orb--on {
  background: radial-gradient(circle at 35% 30%, color-mix(in srgb, white 20%, var(--color-success)), var(--color-success));
  box-shadow: 0 12px 24px color-mix(in srgb, var(--color-success) 32%, transparent);
}

.att-wifi__ring {
  position: absolute;
  inset: 0;
  border-radius: var(--radius-full);
  border: 1.5px solid color-mix(in srgb, var(--color-tertiary-400) 55%, transparent);
  animation: att-ping 2.4s ease-out infinite;
}

.att-wifi__orb--on .att-wifi__ring {
  border-color: color-mix(in srgb, var(--color-success) 55%, transparent);
  animation-play-state: paused;
  opacity: 0;
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
  transition: color 0.2s ease;
}

.att-panel {
  width: 100%;
  padding: var(--space-2) var(--space-3);
  text-align: left;
  border-radius: 20px;
}

.att-panel--wifi {
  margin-top: var(--space-2);
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

.att-wifi__bars {
  display: flex;
  align-items: flex-end;
  gap: 3px;
  height: 1rem;
}

.att-wifi__bars i {
  display: block;
  width: 4px;
  border-radius: 2px;
  background: var(--color-border);
  transition: background 0.3s ease;
}

.att-wifi__bars i:nth-child(1) { height: 25%; }
.att-wifi__bars i:nth-child(2) { height: 50%; }
.att-wifi__bars i:nth-child(3) { height: 75%; }
.att-wifi__bars i:nth-child(4) { height: 100%; }

.att-wifi__bars i.att-wifi__bar--on {
  background: var(--color-success);
}

.att-face {
  position: relative;
  height: min(62vw, 420px);
  min-height: 280px;
  overflow: hidden;
  background: var(--color-tertiary-300);
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
  overflow: hidden;
  width: min(100%, 280px);
  height: 280px;
  background: linear-gradient(var(--color-tertiary-50), var(--color-tertiary-100));
  box-shadow: var(--att-shadow);
  border-radius: 28px;
  transition: box-shadow 0.25s ease;
}

.att-qr__frame--found {
  box-shadow: var(--att-shadow), 0 0 0 4px var(--color-success);
}

.att-qr__video {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.att-qr__canvas {
  display: none;
}

.att-qr__fallback {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  color: var(--color-tertiary-400);
}

.att-qr__check {
  position: absolute;
  inset: 0;
  z-index: 1;
  display: grid;
  place-items: center;
  background: color-mix(in srgb, var(--color-success) 22%, transparent);
  color: var(--color-success);
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

.att-success__mark {
  position: relative;
  display: grid;
  place-items: center;
  width: 5.5rem;
  height: 5.5rem;
  border-radius: var(--radius-full);
  background: radial-gradient(circle at 35% 30%, color-mix(in srgb, var(--color-on-tertiary) 28%, var(--color-success)), var(--color-success));
  color: var(--color-on-tertiary);
  box-shadow: 0 0 0 12px color-mix(in srgb, var(--color-success) 16%, transparent);
}

.att-success__mark::before {
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

.att-sync {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  margin: var(--space-3) 0 0;
  font-size: 0.78rem;
  font-weight: 600;
  line-height: 1.35;
}

.att-sync--on-hero {
  color: var(--color-on-tertiary);
}

.att-sync--plain {
  justify-content: center;
  margin-top: var(--space-2);
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

@media (max-width: 480px) {
  .att-hero {
    padding: var(--space-3);
  }

  .att-clock strong {
    font-size: 2.4rem;
  }

  .att-punch-wrap {
    width: 11.5rem;
    height: 11.5rem;
  }

  .att-punch {
    width: 8.25rem;
    height: 8.25rem;
  }

  .att-tile {
    min-height: 6.5rem;
    padding: var(--space-2);
  }
}

@media (min-width: 768px) {
  .att {
    width: min(100%, 460px);
  }

  .att-grid {
    gap: var(--space-3);
  }
}

@media (min-width: 1280px) {
  .att {
    width: min(100%, 480px);
  }

  .att-clock strong {
    font-size: 3rem;
  }
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

:global(.att-geo-pin) {
  position: relative;
  display: grid;
  place-items: center;
}

:global(.att-geo-pin span) {
  display: block;
  width: 1.9rem;
  height: 1.9rem;
  border-radius: 50% 50% 50% 0;
  background: var(--color-tertiary-600);
  box-shadow: 0 6px 14px color-mix(in srgb, var(--color-tertiary-800) 35%, transparent);
  transform: rotate(-45deg);
  animation: att-pin-drop 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
}

:global(.att-geo-pin span::after) {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 0.7rem;
  height: 0.7rem;
  border-radius: var(--radius-full);
  background: var(--color-on-tertiary);
  transform: translate(-50%, -50%) rotate(45deg);
}

@keyframes att-pin-drop {
  0% { transform: rotate(-45deg) translateY(-14px) scale(0.6); opacity: 0; }
  100% { transform: rotate(-45deg) translateY(0) scale(1); opacity: 1; }
}

@media (prefers-reduced-motion: reduce) {
  :global(.att-geo-pin span) {
    animation: none;
  }
}

:global(.leaflet-container) {
  width: 100%;
  height: 100%;
  font-family: var(--font-family-base);
}

:global(.att-geo-ring) {
  stroke: var(--color-success);
  fill: var(--color-success);
}
</style>
