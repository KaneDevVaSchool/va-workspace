<script setup>
//
// Chấm công nhân viên — trang chủ, hình thức, GPS,
// WiFi, khuôn mặt, QR, mã, thành công. Lịch sử xem tại tab Lịch sử
// (/dashboard/me/requests). Giờ vào/ra gửi về HRM; nếu HRM lỗi thì giữ
// trên máy và báo đang đồng bộ lại.
//
import { computed, inject, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import AttendancePunchConfirmDialog from '../components/AttendancePunchConfirmDialog.vue';
import { showClientToast } from '@/lib/clientToast';
import { onAppResume } from '@/lib/appResume';
import { useOnlineStatus } from '@/composables/useOnlineStatus';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import AttendanceGlyph from '../components/AttendanceGlyph.vue';
import {
  hasAnyPunch,
  readEmployeeAttendanceToday,
  recordFromServerRow,
  recordToServerPayload,
  writeEmployeeAttendanceToday,
} from '../lib/employeeAttendanceSession';
import {
  branchesFromCheckInContext,
  contextWithBranchCenter,
  evaluateCheckInLocation,
  formatBranchCoordinates,
  nearestCheckInBranch,
} from '../lib/employeeAttendanceGeo';
import { nextPunchAction } from '../lib/employeeAttendanceWorkSession';

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
  { id: 'qr', label: 'Quét QR', hint: 'Đang bảo trì', glyph: 'qr', tone: 'violet' },
  { id: 'face', label: 'Camera Hanet', hint: 'Đang bảo trì', glyph: 'camera', tone: 'orange' },
];

/** Màn con tạm tắt — chỉ hiện thông báo bảo trì, không mở camera / quét QR. */
const MAINTENANCE_STEPS = new Set(['qr', 'face']);

const HEADINGS = {
  face: 'Quét khuôn mặt với Hanet',
  gps: 'Chấm công bằng GPS',
  wifi: 'Chấm công bằng WiFi',
  qr: 'Quét mã QR',
  code: 'Nhập mã',
};

// Câu hướng dẫn khi không lấy được vị trí / mở được camera (tiếng Việt phổ thông, nói rõ phải làm gì).
const GEO_MESSAGES = {
  denied: {
    short: 'Chưa cho phép định vị',
    long: 'Chưa cho phép định vị. Hãy bật quyền vị trí cho ứng dụng trong Cài đặt của máy, rồi bấm Thử lại.',
  },
  unavailable: {
    short: 'Chưa xác định được vị trí',
    long: 'Chưa xác định được vị trí. Hãy bật định vị (GPS) của máy hoặc ra nơi thoáng, rồi bấm Thử lại.',
  },
  timeout: {
    short: 'Lấy vị trí quá lâu',
    long: 'Lấy vị trí quá lâu. Bấm Thử lại, hoặc ra nơi thoáng để bắt tín hiệu tốt hơn.',
  },
  unsupported: {
    short: 'Thiết bị không hỗ trợ định vị',
    long: 'Thiết bị này không hỗ trợ định vị nên không chấm bằng GPS được.',
  },
};

const CAMERA_HELP = {
  denied: 'Chưa cho phép dùng camera. Hãy bật quyền camera cho ứng dụng trong Cài đặt của máy, rồi bấm Thử lại.',
  busy: 'Camera đang được ứng dụng khác sử dụng. Đóng ứng dụng đó rồi bấm Thử lại.',
  unavailable: 'Máy này không có camera dùng được.',
};

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const { online } = useOnlineStatus();
const now = ref(new Date());
const bodyRef = ref(null);
const videoRef = ref(null);
const qrVideoRef = ref(null);
const qrCanvasRef = ref(null);
const mapElRef = ref(null);
const punching = ref(false);
const syncState = ref('idle');
const syncIssueMessage = ref('');
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
const confirmOpen = ref(false);
const pendingMethodId = ref(null);
const checkInContext = ref(null);
const selectedBranchId = ref(null);
const branchPickerOpen = ref(false);

const stored = readEmployeeAttendanceToday();
const punchRecord = ref(stored ?? {
  morningInTime: null,
  morningOutTime: null,
  afternoonInTime: null,
  afternoonOutTime: null,
  morningInMethod: null,
  morningOutMethod: null,
  afternoonInMethod: null,
  afternoonOutMethod: null,
});

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
let geoBranchRing = null;
let wifiTimer = 0;
let syncRetryTimer = 0;
let stopResume = null;
// Leaflet + jsQR chỉ tải khi vào màn GPS / QR, không đẩy vào bundle của trang chấm công.
let L = null;
let jsQR = null;
let geoMapLoading = false;

const registerPageRefresh = inject('employeePageRefreshRegister', null);
let unregisterPageRefresh;

// Màn con (GPS, QR, mã…) lưu trong query `?step=` của router để nút Back của hệ thống (Android Back,
// vuốt cạnh trên iOS) đóng màn con thay vì thoát cả trang chấm công. PWA không có nút Back của trình duyệt.
const STEP_SCREENS = new Set(['face', 'gps', 'wifi', 'qr']);
let stepEntries = 0;

const screen = computed(() => {
  const step = typeof route.query.step === 'string' ? route.query.step : '';
  if (step === 'success' && lastResult.value) return 'success';
  return STEP_SCREENS.has(step) ? step : 'home';
});
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
const punchAction = computed(() => nextPunchAction(punchRecord.value));
const punchComplete = computed(() => punchAction.value.complete);
const morningInTime = computed(() => punchRecord.value.morningInTime || null);
const afternoonOutTime = computed(() => punchRecord.value.afternoonOutTime || null);
const punchHint = computed(() => {
  if (punchComplete.value) return 'Đã chấm vào buổi sáng và ra buổi chiều';
  if (punchAction.value.direction === 'out') {
    return `Đã vào ${morningInTime.value} · tiếp theo: chấm ra buổi chiều`;
  }
  return 'Chưa chấm vào buổi sáng';
});
const punchConfirmMethodLabel = computed(() => {
  const methodId = pendingMethodId.value;
  if (!methodId) return 'Chấm bằng nút';
  return METHODS.find((item) => item.id === methodId)?.label || methodId;
});

const punchConfirmDeviceCoords = computed(() => {
  if (!coords.value || typeof coords.value.lat !== 'number' || typeof coords.value.lon !== 'number') {
    return '';
  }
  return formatBranchCoordinates({ lat: coords.value.lat, lon: coords.value.lon });
});

const punchConfirmBlocked = computed(() => {
  if (geoState.value === 'unsupported') return false;
  return punchLocationBlocked.value || geoState.value === 'pending';
});

const punchConfirmHint = computed(() => {
  if (geoState.value === 'pending') return 'Đang lấy vị trí — vui lòng đợi vài giây rồi xác nhận.';
  if (geoState.value !== 'granted' && geoState.value !== 'unsupported') {
    return GEO_MESSAGES[geoState.value]?.long || 'Cần cho phép định vị trên thiết bị.';
  }
  if (punchLocationBlocked.value) {
    return geoBlockMessage.value || 'Chưa đủ điều kiện vị trí để chấm công.';
  }
  return '';
});
const syncLine = computed(() => {
  if (syncState.value === 'syncing') {
    return { text: 'Đang đồng bộ với HRM', wait: true, retry: false };
  }
  if (syncState.value === 'pending') {
    return {
      text: syncIssueMessage.value || 'Đã chấm trên máy — chưa lưu lên HRM',
      wait: true,
      retry: true,
    };
  }
  if (syncState.value === 'synced' && hasAnyPunch(punchRecord.value)) {
    return { text: 'Đã lưu và đồng bộ với HRM', wait: false, retry: false };
  }
  return null;
});
const branchOptions = computed(() => branchesFromCheckInContext(checkInContext.value));

const autoNearestBranch = computed(() => {
  const options = branchOptions.value;
  if (!options.length) return null;
  if (coords.value) return nearestCheckInBranch(options, coords.value) ?? options[0];
  return options[0];
});

const activeCheckInBranch = computed(() => {
  const options = branchOptions.value;
  if (!options.length) return null;
  if (selectedBranchId.value) {
    return options.find((item) => item.id === selectedBranchId.value) ?? autoNearestBranch.value;
  }
  return autoNearestBranch.value;
});

const effectiveCheckInContext = computed(() => {
  const base = checkInContext.value;
  const branch = activeCheckInBranch.value;
  if (!base || !branch) return base;
  return contextWithBranchCenter(base, branch);
});

const locationEval = computed(() => evaluateCheckInLocation(effectiveCheckInContext.value, coords.value));

const branchCheckInLabel = computed(() => {
  if (activeCheckInBranch.value?.name) return activeCheckInBranch.value.name;
  const branch = checkInContext.value?.branch;
  if (branch?.short_name) return branch.short_name;
  if (branch?.name) return branch.name;
  return checkInContext.value?.workplace_label || null;
});

const activeBranchCoordsLabel = computed(() => formatBranchCoordinates(activeCheckInBranch.value));

const canPickCheckInBranch = computed(() => branchOptions.value.length > 1);

const geoBlockMessage = computed(() => {
  if (!checkInContext.value?.gps_enforced) return '';
  const branch = branchCheckInLabel.value || 'cơ sở';
  const tolerance = locationEval.value.toleranceMeters ?? checkInContext.value?.gps_tolerance_meters ?? 100;
  if (locationEval.value.state === 'outside' && locationEval.value.distanceMeters != null) {
    return `Ngoài vùng chấm công ${branch} (cách ${locationEval.value.distanceMeters} m, dung sai ${tolerance} m).`;
  }
  if (geoState.value === 'pending') return 'Đang kiểm tra vị trí…';
  const failure = GEO_MESSAGES[geoState.value];
  if (failure) return failure.long;
  if (locationEval.value.state === 'unknown') {
    return 'Cần bật định vị để chấm công tại cơ sở.';
  }
  return '';
});

const punchLocationBlocked = computed(() => {
  if (geoState.value === 'unsupported') return false;
  if (geoState.value !== 'granted') return true;
  if (geoState.value === 'pending') return true;
  if (!checkInContext.value?.gps_enforced) return false;
  if (locationEval.value.state === 'outside') return true;
  return locationEval.value.state === 'unknown';
});

const checkInSiteStatus = computed(() => {
  const enforced = Boolean(checkInContext.value?.gps_enforced);
  const branch = branchCheckInLabel.value;
  const tolerance = locationEval.value.toleranceMeters ?? checkInContext.value?.gps_tolerance_meters;

  if (geoState.value !== 'granted' && geoState.value !== 'pending') {
    const failure = GEO_MESSAGES[geoState.value];
    if (failure) return { tone: 'warn', text: failure.long };
    return { tone: 'warn', text: 'Cần cho phép định vị trên thiết bị để chấm công.' };
  }
  if (geoState.value === 'pending') {
    return { tone: 'info', text: 'Đang lấy vị trí thiết bị…' };
  }

  if (enforced && locationEval.value.state === 'inside') {
    const dist = locationEval.value.distanceMeters;
    const detail = dist != null && tolerance != null ? ` · cách điểm chấm ${dist} m / dung sai ${tolerance} m` : '';
    const place = branch ? `Trong vùng ${branch}` : 'Trong vùng chấm công';
    return { tone: 'ok', text: `${place}${detail}` };
  }
  if (enforced && locationEval.value.state === 'outside') {
    return { tone: 'warn', text: geoBlockMessage.value || 'Ngoài vùng chấm công' };
  }
  if (enforced && geoBlockMessage.value) {
    return { tone: 'info', text: geoBlockMessage.value };
  }

  const accuracy = coords.value?.accuracy;
  const detail = accuracy ? ` · độ chính xác khoảng ${Math.round(accuracy)} m` : '';
  return { tone: 'ok', text: `Đã cho phép định vị${detail}` };
});
const geoCanRetry = computed(() => Boolean(GEO_MESSAGES[geoState.value]) && geoState.value !== 'unsupported');
const geoLongMessage = computed(() => GEO_MESSAGES[geoState.value]?.long || 'Cần quyền vị trí để chấm bằng GPS.');
const weatherGlyph = computed(() => {
  const code = weather.value?.code;
  if (code == null || code === 0) return 'sun';
  if (code <= 3) return 'cloud';
  return 'rain';
});
const faceCaption = computed(() => {
  if (cameraState.value === 'live') return 'Đưa mặt vào khung hình và giữ nguyên';
  if (cameraState.value === 'connecting') return 'Đang mở camera…';
  if (CAMERA_HELP[cameraState.value]) return 'Không mở được camera';
  return 'Đưa mặt vào khung khi camera Hanet sẵn sàng';
});
const faceHelpText = computed(() => CAMERA_HELP[cameraState.value] || '');
const faceCanRetry = computed(() => ['denied', 'busy'].includes(cameraState.value));
const qrCanRetry = computed(() => ['denied', 'busy'].includes(qrState.value));
const faceStatus = computed(() => {
  if (cameraState.value === 'live') return 'Đang xem trước camera';
  if (cameraState.value === 'connecting') return 'Đang kết nối camera Hanet...';
  return 'Chưa xem trước được camera';
});
const qrCaption = computed(() => {
  if (qrState.value === 'found') return 'Đã nhận diện mã QR, đang ghi nhận...';
  if (qrState.value === 'live') return 'Đưa mã QR vào giữa khung hình';
  if (qrState.value === 'connecting') return 'Đang mở camera…';
  if (qrState.value === 'denied') return 'Chưa cấp quyền camera — nhập mã thủ công bên dưới, hoặc cho phép camera rồi bấm Thử lại';
  if (qrState.value === 'busy') return 'Camera đang được ứng dụng khác dùng — nhập mã thủ công bên dưới, hoặc đóng ứng dụng đó rồi bấm Thử lại';
  if (qrState.value === 'unavailable') return 'Thiết bị không hỗ trợ camera — nhập mã thủ công bên dưới';
  return 'Đưa mã QR tại điểm chấm công vào khung hình';
});
const maintenanceLead = computed(() => {
  if (screen.value === 'qr') return 'Tính năng quét mã QR tạm thời không khả dụng.';
  if (screen.value === 'face') return 'Tính năng camera Hanet tạm thời không khả dụng.';
  return '';
});

const screenSubtitle = computed(() => {
  if (MAINTENANCE_STEPS.has(screen.value)) return 'Đang bảo trì';
  if (screen.value === 'gps') return 'Đối chiếu vị trí thiết bị với vùng cho phép';
  if (screen.value === 'wifi') return 'Đối chiếu mạng WiFi công ty';
  return '';
});
const qrFallback = computed(() => ['denied', 'busy', 'unavailable'].includes(qrState.value));
const wifiCaption = computed(() => (
  wifiState.value === 'connected' ? 'Đã kết nối WiFi công ty' : 'Đang kiểm tra kết nối…'
));
const wifiBars = computed(() => Math.min(4, Math.max(0, Math.round(wifiSignal.value / 25))));
const dock = computed(() => {
  if (screen.value === 'success') return { label: 'Quay lại trang chủ', action: 'home' };
  if (screen.value === 'gps') return { label: 'Chấm công', action: 'punch-gps' };
  if (screen.value === 'wifi') return { label: 'Chấm công', action: 'punch-wifi' };
  return null;
});
const dockDisabled = computed(() => {
  if (punching.value) return true;
  if (screen.value === 'qr' && qrFallback.value) return qrValue.value.trim().length < 4;
  if (screen.value === 'wifi') return wifiState.value !== 'connected';
  return false;
});
const resultGlyph = computed(() => {
  const id = lastResult.value?.methodId;
  return METHODS.find((item) => item.id === id)?.glyph || 'check';
});

watch(() => route.query.step, (step) => {
  if (!step) stepEntries = 0;
});

watch(codeValue, () => {
  codeError.value = '';
});

watch(qrValue, () => {
  codeError.value = '';
});

watch(screen, (value, prev) => {
  if (value === 'face' && !MAINTENANCE_STEPS.has('face')) startCamera();
  else if (prev === 'face') stopCamera();
  if (value === 'qr' && !MAINTENANCE_STEPS.has('qr')) startQrCamera();
  else if (prev === 'qr') stopQrCamera();
  if (value === 'gps') {
    nextTick(() => initGeoMap());
    // Chỉ xin quyền vị trí khi người dùng thật sự mở màn GPS (trước đây hỏi ngay lúc mở trang chấm công).
    if (geoState.value === 'idle') requestLocation();
  } else if (prev === 'gps') destroyGeoMap();
  if (value === 'wifi') startWifiCheck();
  else if (prev === 'wifi') stopWifiCheck();
// immediate: tải lại trang khi đang ở `?step=gps` (hoặc mở thẳng link) vẫn phải khởi tạo bản đồ/camera của màn đó.
}, { flush: 'post', immediate: true });

watch(coords, (value) => {
  if (value) updateGeoMap(value);
});

watch(checkInContext, () => {
  selectedBranchId.value = null;
  branchPickerOpen.value = false;
});

onMounted(() => {
  timer = window.setInterval(() => {
    now.value = new Date();
  }, 1000);
  syncRetryTimer = window.setInterval(() => {
    if (syncState.value === 'pending' && hasAnyPunch(readEmployeeAttendanceToday())) {
      void hydrateTodayFromHrm();
    }
  }, 45000);
  void hydrateTodayFromHrm();
  void loadCheckInContext();
  void requestLocation();
  stopResume = onAppResume(() => {
    // Có mạng lại / quay lại app: đẩy ngay lần chấm đang chờ lên HRM thay vì đợi nhịp 45 giây.
    if (syncState.value === 'pending' && hasAnyPunch(readEmployeeAttendanceToday())) {
      void hydrateTodayFromHrm();
    }
  });
  document.addEventListener('visibilitychange', onVisibilityChange);
  if (typeof registerPageRefresh === 'function') {
    unregisterPageRefresh = registerPageRefresh(async () => {
      await hydrateTodayFromHrm();
      await loadCheckInContext();
      now.value = new Date();
      // Làm mới vị trí chỉ khi đã được cấp quyền — kéo để làm mới không được bật hộp thoại xin quyền.
      if (geoState.value === 'granted') await requestLocation();
    });
  }
});

async function loadCheckInContext() {
  try {
    const { data } = await window.axios.get('/api/attendance/check-in-context');
    checkInContext.value = data?.data ?? null;
    if (geoState.value === 'idle') {
      void requestLocation();
    }
  } catch {
    checkInContext.value = null;
  }
}

onBeforeUnmount(() => {
  window.clearInterval(timer);
  window.clearInterval(syncRetryTimer);
  stopCamera();
  stopQrCamera();
  destroyGeoMap();
  stopWifiCheck();
  unregisterPageRefresh?.();
  stopResume?.();
  document.removeEventListener('visibilitychange', onVisibilityChange);
});

// App xuống nền: tắt camera ngay (riêng tư + tiết kiệm pin); quay lại thì mở lại đúng màn đang xem.
function onVisibilityChange() {
  if (document.hidden) {
    if (screen.value === 'face') stopCamera();
    if (screen.value === 'qr') stopQrCamera();
    return;
  }
  if (screen.value === 'face') startCamera();
  if (screen.value === 'qr') startQrCamera();
}

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

function queryWithStep(step) {
  const query = { ...route.query };
  if (step) query.step = step;
  else delete query.step;
  return { query };
}

function push(next) {
  stepEntries += 1;
  router.push(queryWithStep(next)).catch(() => {});
  scrollTop();
}

function back() {
  if (stepEntries > 0) {
    router.back();
  } else if (route.query.step) {
    router.replace(queryWithStep(null)).catch(() => {});
  }
  scrollTop();
}

function goHome() {
  if (stepEntries > 0) {
    // Quay về đúng mục lịch sử của trang chủ chấm công thay vì chồng thêm mục mới.
    router.go(-stepEntries);
  } else if (route.query.step) {
    router.replace(queryWithStep(null)).catch(() => {});
  }
  scrollTop();
}

function reloadPunch() {
  const row = readEmployeeAttendanceToday();
  if (row) punchRecord.value = { ...punchRecord.value, ...row };
}

function normalizeRemoteRow(data) {
  return recordFromServerRow(data);
}

function recordSnapshot() {
  return { ...punchRecord.value };
}

function applyLocalPunch(sessionId, direction, time, methodLabel) {
  const next = recordSnapshot();
  if (sessionId === 'morning' && direction === 'in') {
    next.morningInTime = time;
    next.morningInMethod = methodLabel;
  } else if (sessionId === 'morning' && direction === 'out') {
    next.morningOutTime = time;
    next.morningOutMethod = methodLabel;
  } else if (sessionId === 'afternoon' && direction === 'in') {
    next.afternoonInTime = time;
    next.afternoonInMethod = methodLabel;
  } else if (sessionId === 'afternoon' && direction === 'out') {
    next.afternoonOutTime = time;
    next.afternoonOutMethod = methodLabel;
  }
  punchRecord.value = next;
  return next;
}

function recordsDiffer(local, remote) {
  const keys = [
    'morningInTime', 'morningOutTime', 'afternoonInTime', 'afternoonOutTime',
  ];
  return keys.some((key) => (local?.[key] || null) !== (remote?.[key] || null));
}

function punchApiMessage(error) {
  const data = error?.response?.data;
  if (typeof data?.message === 'string' && data.message.trim()) return data.message.trim();
  const errors = data?.errors;
  if (errors && typeof errors === 'object') {
    const first = Object.values(errors).flat().find((item) => typeof item === 'string' && item.trim());
    if (first) return first.trim();
  }
  return null;
}

function needsHrmPush(local, remote) {
  if (!hasAnyPunch(local)) return false;
  if (!hasAnyPunch(remote)) return true;
  return recordsDiffer(local, remote);
}

async function pushLocalToHrm(local, token) {
  if (!hasAnyPunch(local)) return false;
  try {
    const { data } = await window.axios.post('/api/attendance/punches/sync', recordToServerPayload(local));
    if (token !== hydrateToken) return false;
    const remote = normalizeRemoteRow(data?.data);
    if (hasAnyPunch(remote)) rememberToday(remote);
    syncIssueMessage.value = '';
    return true;
  } catch (error) {
    const message = punchApiMessage(error);
    if (message) syncIssueMessage.value = message;
    return false;
  }
}

function rememberToday(row) {
  writeEmployeeAttendanceToday(row);
  reloadPunch();
}

function askPunch(methodId) {
  if (punching.value || punchComplete.value || confirmOpen.value) return;
  if (!punchAction.value.direction) return;
  pendingMethodId.value = methodId;
  confirmOpen.value = true;
}

function onConfirmOpenChange(open) {
  confirmOpen.value = open;
  if (open) {
    now.value = new Date();
    if (geoState.value === 'granted') void requestLocation();
    return;
  }
  if (punching.value) return;
  const methodId = pendingMethodId.value;
  pendingMethodId.value = null;
  if (methodId === 'qr' && screen.value === 'qr' && qrState.value === 'found') {
    qrValue.value = '';
    qrState.value = 'live';
    scanQrFrame(qrCameraToken);
  }
}

async function onConfirmPunch() {
  const methodId = pendingMethodId.value;
  if (!methodId || punching.value) return;
  await commitPunch(methodId);
  pendingMethodId.value = null;
  confirmOpen.value = false;
}

async function onHero() {
  if (geoState.value === 'unsupported') {
    showClientToast('warning', GEO_MESSAGES.unsupported.long);
    return;
  }
  if (geoState.value !== 'granted') {
    const ok = await requestLocation();
    if (!ok) {
      showClientToast('warning', geoLongMessage.value);
      return;
    }
  }
  if (punchLocationBlocked.value) {
    showClientToast('warning', geoBlockMessage.value || 'Chưa đủ điều kiện vị trí để chấm công.');
    return;
  }
  if (checkInContext.value?.gps_enforced && locationEval.value.state === 'outside') {
    showClientToast('warning', geoBlockMessage.value);
    return;
  }
  askPunch('button');
}

function toggleBranchPicker() {
  if (!canPickCheckInBranch.value) return;
  branchPickerOpen.value = !branchPickerOpen.value;
}

function selectCheckInBranch(branch) {
  if (!branch?.id) return;
  selectedBranchId.value = branch.id;
  branchPickerOpen.value = false;
}

function openMethod(id) {
  if (id === 'qr') {
    qrValue.value = '';
    codeError.value = '';
  }
  push(id);
}

async function commitPunch(methodId) {
  if (punching.value || punchComplete.value) return;
  const action = punchAction.value;
  if (!action.direction) return;
  const method = METHODS.find((item) => item.id === methodId);
  const methodLabel = method?.label || methodId;
  const fresh = new Date();
  const label = formatHm(fresh);
  now.value = fresh;
  const { session, direction } = action;
  punching.value = true;
  hydrateToken += 1;
  const localAfter = applyLocalPunch(session.id, direction, label, methodLabel);
  rememberToday(localAfter);
  syncState.value = 'syncing';
  let synced = false;
  try {
    const payload = {
      session: session.id,
      direction,
      method: methodLabel,
    };
    if (coords.value && typeof coords.value.lat === 'number' && typeof coords.value.lon === 'number') {
      payload.latitude = coords.value.lat;
      payload.longitude = coords.value.lon;
      if (coords.value.accuracy > 0) payload.accuracy_meters = coords.value.accuracy;
    }
    const branchId = activeCheckInBranch.value?.id;
    if (branchId && branchId !== 'default') {
      payload.branch_uuid = branchId;
    }
    const { data } = await window.axios.post('/api/attendance/punches', payload);
    const remote = normalizeRemoteRow(data?.data);
    if (hasAnyPunch(remote)) rememberToday(remote);
    synced = true;
    syncIssueMessage.value = '';
    syncState.value = 'synced';
  } catch (error) {
    syncIssueMessage.value = punchApiMessage(error) || syncIssueMessage.value;
    syncState.value = 'pending';
    void pushLocalToHrm(readEmployeeAttendanceToday(), hydrateToken);
  } finally {
    punching.value = false;
  }
  lastResult.value = {
    action: direction,
    session: session.id,
    time: label,
    methodId,
    method: methodLabel,
    dateLabel: formatLongDate(fresh),
    synced,
  };
  if (route.query.step) {
    // Thay mục của màn con (GPS/QR…) bằng màn kết quả: Back từ kết quả về thẳng trang chủ.
    router.replace(queryWithStep('success')).catch(() => {});
  } else {
    stepEntries += 1;
    router.push(queryWithStep('success')).catch(() => {});
  }
  scrollTop();
  if (typeof navigator !== 'undefined' && navigator.vibrate) navigator.vibrate(12);
}

async function hydrateTodayFromHrm() {
  const token = ++hydrateToken;
  const local = readEmployeeAttendanceToday();
  if (hasAnyPunch(local)) syncState.value = 'syncing';

  let remote = normalizeRemoteRow(null);
  let fetchOk = false;
  try {
    const { data } = await window.axios.get('/api/attendance/punches/today');
    if (token !== hydrateToken) return;
    remote = normalizeRemoteRow(data?.data);
    fetchOk = true;
    syncIssueMessage.value = '';
  } catch (error) {
    if (token !== hydrateToken) return;
    const message = punchApiMessage(error);
    if (message) syncIssueMessage.value = message;
  }

  if (hasAnyPunch(local) && needsHrmPush(local, remote)) {
    const pushed = await pushLocalToHrm(local, token);
    if (token !== hydrateToken) return;
    if (pushed) {
      syncState.value = 'synced';
      return;
    }
  }

  if (fetchOk && hasAnyPunch(remote) && !needsHrmPush(local, remote)) {
    rememberToday(remote);
    syncState.value = 'synced';
    return;
  }

  syncState.value = hasAnyPunch(local) ? 'pending' : 'idle';
}

async function retrySyncNow() {
  if (syncState.value === 'syncing' || !hasAnyPunch(readEmployeeAttendanceToday())) return;
  await hydrateTodayFromHrm();
}

async function punchGps() {
  if (geoState.value !== 'granted') {
    const ok = await requestLocation();
    if (!ok) {
      showClientToast('warning', geoLongMessage.value);
      return;
    }
  }
  askPunch('gps');
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
    askPunch('wifi');
    return;
  }
  if (action === 'punch-qr-code') {
    if (qrValue.value.trim().length < 4) {
      codeError.value = 'Nhập ít nhất 4 ký tự.';
      return;
    }
    askPunch('qr');
  }
}

function isMaintenanceStep(step) {
  return MAINTENANCE_STEPS.has(step);
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
      (error) => {
        // 1 = từ chối quyền, 2 = không xác định được vị trí, 3 = quá thời gian
        geoState.value = error?.code === 1 ? 'denied' : error?.code === 3 ? 'timeout' : 'unavailable';
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

async function initGeoMap() {
  if (geoMap || geoMapLoading || !mapElRef.value) return;
  geoMapLoading = true;
  try {
    if (!L) {
      const [leaflet] = await Promise.all([
        import('leaflet'),
        import('leaflet/dist/leaflet.css'),
      ]);
      L = leaflet.default;
    }
  } catch {
    // Mất mạng lúc tải bản đồ: màn GPS vẫn dùng được, chỉ không có hình bản đồ.
    return;
  } finally {
    geoMapLoading = false;
  }
  // Người dùng có thể đã rời màn GPS trong lúc tải.
  if (geoMap || screen.value !== 'gps' || !mapElRef.value) return;
  const fallback = { lat: 21.0278, lon: 105.8342 };
  const branch = branchMapPoint();
  const device = coords.value;
  const point = device || branch || fallback;
  geoMap = L.map(mapElRef.value, {
    attributionControl: false,
    zoomControl: false,
    // Trên cảm ứng, kéo bằng một ngón làm kẹt thao tác cuộn trang; bản đồ chỉ để xem vị trí.
    dragging: !L.Browser.touch,
    scrollWheelZoom: false,
  }).setView([point.lat, point.lon], device || branch ? 17 : 13);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
  }).addTo(geoMap);

  const pinIcon = L.divIcon({
    className: 'att-geo-pin',
    html: '<span></span>',
    iconSize: [34, 34],
    iconAnchor: [17, 30],
  });
  if (branch && checkInContext.value?.gps_tolerance_meters) {
    geoBranchRing = L.circle([branch.lat, branch.lon], {
      radius: checkInContext.value.gps_tolerance_meters,
      className: 'att-geo-branch-ring',
      weight: 2,
      fillOpacity: 0.08,
    }).addTo(geoMap);
  }
  if (device) {
    geoMarker = L.marker([device.lat, device.lon], { icon: pinIcon }).addTo(geoMap);
    geoRing = L.circle([device.lat, device.lon], {
      radius: device.accuracy || 40,
      className: 'att-geo-ring',
      weight: 1.5,
      fillOpacity: 0.12,
    }).addTo(geoMap);
  } else if (branch) {
    geoMarker = L.marker([branch.lat, branch.lon], { icon: pinIcon }).addTo(geoMap);
  }

  requestAnimationFrame(() => geoMap?.invalidateSize());
}

function branchMapPoint() {
  const branch = activeCheckInBranch.value;
  if (branch) return { lat: branch.lat, lon: branch.lon };
  const ctx = checkInContext.value;
  if (typeof ctx?.latitude === 'number' && typeof ctx?.longitude === 'number') {
    return { lat: ctx.latitude, lon: ctx.longitude };
  }
  return null;
}

function updateGeoMap(point) {
  if (!geoMap) return;
  geoMap.setView([point.lat, point.lon], 17);
  if (!geoMarker) {
    const pinIcon = L?.divIcon({
      className: 'att-geo-pin',
      html: '<span></span>',
      iconSize: [34, 34],
      iconAnchor: [17, 30],
    });
    if (pinIcon) geoMarker = L.marker([point.lat, point.lon], { icon: pinIcon }).addTo(geoMap);
  } else {
    geoMarker.setLatLng([point.lat, point.lon]);
  }
  if (!geoRing) {
    geoRing = L.circle([point.lat, point.lon], {
      radius: point.accuracy || 40,
      className: 'att-geo-ring',
      weight: 1.5,
      fillOpacity: 0.12,
    }).addTo(geoMap);
  } else {
    geoRing.setLatLng([point.lat, point.lon]);
    geoRing.setRadius(point.accuracy || 40);
  }
}

function destroyGeoMap() {
  geoMap?.remove();
  geoMap = null;
  geoMarker = null;
  geoRing = null;
  geoBranchRing = null;
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
  } catch (error) {
    if (token === cameraToken) cameraState.value = classifyCameraError(error);
  }
}

function classifyCameraError(error) {
  const name = error?.name;
  if (name === 'NotAllowedError' || name === 'SecurityError' || name === 'PermissionDeniedError') return 'denied';
  if (name === 'NotReadableError' || name === 'TrackStartError' || name === 'AbortError') return 'busy';
  return 'unavailable';
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
    if (!jsQR) jsQR = (await import('jsqr')).default;
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
  } catch (error) {
    if (token === qrCameraToken) qrState.value = classifyCameraError(error);
  }
}

// Quét QR ở độ phân giải thấp (≤480px) và ~8 khung/giây: đọc nguyên khung hình camera ở mỗi frame vẽ
// (jsQR chạy trên CPU) làm máy nóng, giật và hao pin mà mã QR chiếm khung vẫn đọc được.
const QR_SCAN_MAX_SIDE = 480;
const QR_SCAN_INTERVAL_MS = 120;
let qrLastScanAt = 0;

function scanQrFrame(token) {
  qrScanFrame = requestAnimationFrame((timestamp) => {
    if (token !== qrCameraToken || qrState.value !== 'live') return;
    if (timestamp - qrLastScanAt < QR_SCAN_INTERVAL_MS) {
      scanQrFrame(token);
      return;
    }
    qrLastScanAt = timestamp;
    const video = qrVideoRef.value;
    const canvas = qrCanvasRef.value;
    if (jsQR && video && canvas && video.readyState === video.HAVE_ENOUGH_DATA) {
      const sourceWidth = video.videoWidth;
      const sourceHeight = video.videoHeight;
      if (sourceWidth && sourceHeight) {
        const ratio = Math.min(1, QR_SCAN_MAX_SIDE / Math.max(sourceWidth, sourceHeight));
        const width = Math.max(1, Math.round(sourceWidth * ratio));
        const height = Math.max(1, Math.round(sourceHeight * ratio));
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(video, 0, 0, width, height);
        const frame = ctx.getImageData(0, 0, width, height);
        const result = jsQR(frame.data, width, height, { inversionAttempts: 'dontInvert' });
        if (result?.data) {
          qrState.value = 'found';
          qrValue.value = result.data;
          if (typeof navigator !== 'undefined' && navigator.vibrate) navigator.vibrate(16);
          window.setTimeout(() => {
            if (token === qrCameraToken) askPunch('qr');
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
                {{ punchAction.direction === 'out' ? 'Đang làm việc,' : 'Chào' }} <b>{{ displayName }}</b>
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
              <div class="att-hero__meta">
                <span
                  class="att-session att-session--on-hero"
                  :class="punchAction.direction === 'in' ? 'att-session--amber' : 'att-session--indigo'"
                >
                  {{ punchComplete ? 'Đã xong' : (punchAction.direction === 'out' ? 'Ra chiều' : 'Vào sáng') }}
                </span>
                <div class="att-weather">
                  <AttendanceGlyph :name="weatherGlyph" :size="20" />
                  <b>{{ weather ? `${weather.temp}°C` : '—°' }}</b>
                </div>
              </div>
            </div>
            <div v-if="syncLine" class="att-sync-row att-sync-row--on-hero">
              <p class="att-sync att-sync--on-hero">
                <span class="att-dot" :class="{ 'att-dot--wait': syncLine.wait }"></span>
                <span>{{ syncLine.text }}</span>
              </p>
              <button
                v-if="syncLine.retry"
                type="button"
                class="att-sync-retry"
                @click="retrySyncNow"
              >
                Thử lại
              </button>
            </div>
          </article>

          <div class="att-dayboard" aria-label="Tóm tắt chấm công hôm nay">
            <article
              class="att-dayboard__card"
              :class="{
                'att-dayboard__card--active': punchAction.direction === 'in',
                'att-dayboard__card--done': Boolean(morningInTime),
              }"
            >
              <header>
                <span class="att-session att-session--amber">Buổi sáng</span>
                <small>Giờ vào</small>
              </header>
              <p><strong>{{ morningInTime || '—' }}</strong></p>
            </article>
            <article
              class="att-dayboard__card"
              :class="{
                'att-dayboard__card--active': punchAction.direction === 'out',
                'att-dayboard__card--done': Boolean(afternoonOutTime),
              }"
            >
              <header>
                <span class="att-session att-session--indigo">Buổi chiều</span>
                <small>Giờ ra</small>
              </header>
              <p><strong>{{ afternoonOutTime || '—' }}</strong></p>
            </article>
          </div>

          <div class="att-punch-wrap">
            <button
              type="button"
              class="att-punch"
              :disabled="punching || punchComplete || punchLocationBlocked"
              :aria-busy="punching"
              @click="onHero"
            >
              <svg v-if="punching" class="att-spin" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2.5" stroke-dasharray="14 42" stroke-linecap="round" />
              </svg>
              <AttendanceGlyph v-else name="fingerprint" :size="44" />
              <span class="att-punch__label">{{ punching ? 'Đang gửi…' : punchAction.verb }}</span>
              <small v-if="!punching && !punchComplete" class="att-punch__session">{{ punchAction.direction === 'out' ? 'Buổi chiều' : 'Buổi sáng' }}</small>
            </button>
          </div>
          <p class="att-punch-hint">{{ punchHint }}</p>

          <article class="att-checkin-site">
            <p class="att-checkin-site__lead">Bạn sẽ chấm công tại chi nhánh bên dưới.</p>

            <div v-if="activeCheckInBranch" class="att-checkin-site__branch">
              <span class="att-tile__icon att-tone--green">
                <AttendanceGlyph name="pin" :size="18" />
              </span>
              <div class="att-checkin-site__branch-main">
                <span class="att-checkin-site__branch-kicker">Chi nhánh</span>
                <strong class="att-checkin-site__branch-name">{{ activeCheckInBranch.name }}</strong>
                <small v-if="activeCheckInBranch.code" class="att-checkin-site__branch-code">
                  {{ activeCheckInBranch.code }}
                </small>
                <small class="att-checkin-site__coords">{{ activeBranchCoordsLabel }}</small>
              </div>
              <button
                v-if="canPickCheckInBranch"
                type="button"
                class="att-checkin-site__change"
                :aria-expanded="branchPickerOpen"
                aria-label="Đổi chi nhánh chấm công"
                @click="toggleBranchPicker"
              >
                Đổi
              </button>
            </div>
            <p v-else class="att-checkin-site__empty">Chưa có tọa độ chi nhánh từ HRM.</p>

            <div
              v-if="branchPickerOpen && canPickCheckInBranch"
              class="att-checkin-site__picker"
            >
              <p class="att-checkin-site__picker-title">Chọn chi nhánh đúng với vị trí bạn đang đứng</p>
              <ul
                class="att-checkin-site__options"
                role="listbox"
                aria-label="Chọn chi nhánh chấm công"
              >
                <li v-for="branch in branchOptions" :key="branch.id" role="presentation">
                  <button
                    type="button"
                    role="option"
                    class="att-checkin-site__option"
                    :aria-selected="branch.id === activeCheckInBranch?.id"
                    @click="selectCheckInBranch(branch)"
                  >
                    <span class="att-checkin-site__option-name">{{ branch.name }}</span>
                    <small v-if="branch.code" class="att-checkin-site__option-code">{{ branch.code }}</small>
                    <small class="att-checkin-site__option-coords">{{ formatBranchCoordinates(branch) }}</small>
                  </button>
                </li>
              </ul>
            </div>

            <p
              class="att-checkin-site__status"
              :class="`att-checkin-site__status--${checkInSiteStatus.tone}`"
            >
              <AppIcon
                :name="checkInSiteStatus.tone === 'ok' ? 'check' : (checkInSiteStatus.tone === 'warn' ? 'alertTriangle' : 'info')"
                :size="14"
              />
              <span>{{ checkInSiteStatus.text }}</span>
            </p>
            <button
              v-if="geoCanRetry"
              type="button"
              class="att-checkin-site__geo-btn"
              @click="requestLocation"
            >
              Cho phép định vị
            </button>
          </article>

          <div class="att-home__footer">
            <p class="att-methods__label">Hình thức khác</p>
            <div class="att-alt-methods">
              <button
                v-for="tile in HOME_TILES"
                :key="tile.id"
                type="button"
                class="att-alt-method"
                @click="openMethod(tile.id)"
              >
                <span class="att-alt-method__icon" :class="`att-tone--${tile.tone}`">
                  <AttendanceGlyph :name="tile.glyph" :size="22" />
                </span>
                <span class="att-alt-method__label">{{ tile.label }}</span>
                <small class="att-alt-method__hint">{{ tile.hint }}</small>
              </button>
            </div>
          </div>
        </section>

        <section v-else-if="isMaintenanceStep(screen)" class="att-stack att-maint">
          <div class="att-maint__badge" aria-hidden="true">
            <AppIcon name="alertTriangle" :size="36" />
          </div>
          <h2 class="att-maint__title">Đang bảo trì</h2>
          <p class="att-maint__lead">{{ maintenanceLead }}</p>
          <p class="att-banner att-banner--info">
            <AppIcon name="info" :size="16" />
            <span>Chấm công bằng nút vân tay ở trang chủ — hệ thống vẫn ghi nhận vị trí và WiFi khi có.</span>
          </p>
        </section>

        <section v-else-if="screen === 'gps'" class="att-stack">
          <div class="att-map">
            <div ref="mapElRef" class="att-map__canvas"></div>
            <span class="att-map__bubble">{{ placeLabel }}</span>
            <span v-if="!online" class="att-map__loading">
              <span>Đang không có mạng — bản đồ chưa tải được</span>
            </span>
            <span v-else-if="geoState === 'pending'" class="att-map__loading">
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
                : geoLongMessage }}
            </span>
          </p>
          <button v-if="geoCanRetry" type="button" class="att-retry" @click="requestLocation">Thử lại</button>
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
          <p
            v-if="lastResult?.time"
            class="att-success__session"
            :class="lastResult.action === 'out' ? 'att-session--indigo' : 'att-session--amber'"
          >
            {{ lastResult.action === 'out' ? 'Ra buổi chiều' : 'Vào buổi sáng' }}
          </p>
          <p class="att-success__name">{{ displayName }}</p>
          <p class="att-success__time">
            {{ lastResult?.action === 'out' ? 'Đã chấm ra buổi chiều lúc' : 'Đã chấm vào buổi sáng lúc' }}
            {{ lastResult?.time }}
          </p>
          <p class="att-success__date">{{ lastResult?.dateLabel }}</p>
          <p v-if="lastResult?.synced" class="att-sync att-sync--plain">
            <span class="att-dot"></span>
            <span>Đã đồng bộ với HRM</span>
          </p>
          <p v-else-if="lastResult" class="att-sync att-sync--plain">
            <span class="att-dot att-dot--wait"></span>
            <span>Đã chấm trên máy — đang thử lưu lên HRM</span>
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

    <AttendancePunchConfirmDialog
      :open="confirmOpen"
      :loading="punching"
      :direction="punchAction.direction || 'in'"
      :time-label="timeLabel"
      :date-label="homeDateLabel"
      :method-label="punchConfirmMethodLabel"
      :branch-name="activeCheckInBranch?.name || branchCheckInLabel || ''"
      :branch-code="activeCheckInBranch?.code || ''"
      :branch-coords="activeBranchCoordsLabel"
      :device-coords="punchConfirmDeviceCoords"
      :accuracy-meters="coords?.accuracy ?? null"
      :location-state="locationEval.state"
      :distance-meters="locationEval.distanceMeters"
      :tolerance-meters="locationEval.toleranceMeters ?? checkInContext?.gps_tolerance_meters ?? null"
      :gps-enforced="Boolean(checkInContext?.gps_enforced)"
      :map-lat="coords?.lat ?? null"
      :map-lon="coords?.lon ?? null"
      :geo-pending="geoState === 'pending'"
      :branch-options="branchOptions"
      :active-branch-id="activeCheckInBranch?.id || ''"
      :confirm-blocked="punchConfirmBlocked"
      :confirm-hint="punchConfirmHint"
      @update:open="onConfirmOpenChange"
      @confirm="onConfirmPunch"
      @select-branch="selectCheckInBranch"
    />
  </div>
</template>

<style scoped>
.att-page {
  position: relative;
  min-height: 100%;
  height: auto;
  display: flex;
  justify-content: center;
  background:
    radial-gradient(circle at 1px 1px, color-mix(in srgb, var(--color-tertiary-300) 38%, transparent) 1px, transparent 0)
      0 0 / 22px 22px,
    var(--color-tertiary-50);
  color: var(--color-text);
  overflow: visible;
}

.att-page:has(.att-dock) {
  height: 100%;
  min-height: 0;
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
  min-height: 100%;
  height: auto;
  display: flex;
  flex-direction: column;
  padding: 0;
}

.att-page:has(.att-dock) .att {
  height: 100%;
  min-height: 0;
}

.att__body {
  flex: 1 0 auto;
  min-height: 0;
  overflow: visible;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding-bottom: var(--space-4);
}

.att-page:has(.att-dock) .att__body {
  flex: 1 1 auto;
  overflow: auto;
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
  min-height: 0;
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
.att-sync-row--on-hero {
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

.att-hero__meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: var(--space-2);
}

.att-session {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: var(--radius-full);
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.att-session--on-hero {
  background: color-mix(in srgb, white 22%, transparent);
  color: var(--color-on-tertiary);
  border: 1px solid color-mix(in srgb, white 28%, transparent);
}

.att-session--amber {
  background: color-mix(in srgb, var(--color-gold-100) 88%, white);
  color: var(--color-gold-800);
}

.att-session--indigo {
  background: color-mix(in srgb, var(--color-secondary-100) 90%, white);
  color: var(--color-secondary-800);
}

.att-sync-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  margin-top: var(--space-2);
}

.att-sync-row--on-hero .att-sync {
  margin: 0;
}

.att-sync-retry {
  flex-shrink: 0;
  border: 1px solid color-mix(in srgb, white 35%, transparent);
  border-radius: var(--radius-full);
  padding: 5px 12px;
  background: color-mix(in srgb, white 18%, transparent);
  color: var(--color-on-tertiary);
  font: inherit;
  font-size: 0.72rem;
  font-weight: 700;
  cursor: pointer;
}

.att-sync-retry {
  position: relative;
}

/* Nút nhỏ trên hero: nới vùng chạm lên 44px mà không đổi kích thước hiển thị. */
.att-sync-retry::after {
  content: '';
  position: absolute;
  inset: -8px -4px;
}

.att-sync-retry:focus-visible {
  outline: 2px solid var(--color-on-tertiary);
  outline-offset: 2px;
}

.att-dayboard {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-2);
}

.att-dayboard__card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: var(--space-3);
  border-radius: 18px;
  background: var(--color-surface);
  box-shadow: var(--att-shadow);
  border: 1px solid color-mix(in srgb, var(--color-border) 70%, transparent);
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.att-dayboard__card--active {
  border-color: color-mix(in srgb, var(--color-tertiary-400) 55%, var(--color-border));
  box-shadow:
    var(--att-shadow),
    0 0 0 2px color-mix(in srgb, var(--color-tertiary-200) 80%, transparent);
}

.att-dayboard__card--done {
  border-color: color-mix(in srgb, var(--color-success-tint-fg) 35%, var(--color-border));
}

.att-dayboard__card header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-1);
}

.att-dayboard__card header small {
  color: var(--color-text-muted);
  font-size: 0.68rem;
  font-weight: 600;
}

.att-dayboard__card p {
  margin: 0;
}

.att-dayboard__card p strong {
  font-size: 1.35rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.03em;
  color: var(--color-tertiary-900);
}

.att-punch__label {
  font-size: 1.05rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.att-punch__session {
  font-size: 0.68rem;
  font-weight: 700;
  opacity: 0.92;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.att-success__session {
  display: inline-flex;
  margin: 0 0 var(--space-1);
  padding: 4px 12px;
  border-radius: var(--radius-full);
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 0.03em;
  text-transform: uppercase;
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

.att-hero__geo--warn {
  background: color-mix(in srgb, var(--color-gold-200) 55%, white);
  color: var(--color-umber-900);
  font-weight: 700;
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

.att-home__footer {
  margin-top: auto;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding-top: var(--space-2);
}

.att-checkin-site {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-3);
  border-radius: 18px;
  background: var(--color-surface);
  box-shadow: var(--att-shadow);
  border: 1px solid color-mix(in srgb, var(--color-border) 70%, transparent);
}

.att-checkin-site__lead {
  margin: 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.45;
}

.att-checkin-site__branch {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
}

.att-checkin-site__branch-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.att-checkin-site__branch-kicker {
  color: var(--color-text-muted);
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.att-checkin-site__branch-name {
  font-size: 1.05rem;
  font-weight: 800;
  line-height: 1.25;
  color: var(--color-text);
  letter-spacing: -0.02em;
}

.att-checkin-site__branch-code {
  color: var(--color-tertiary-800);
  font-size: 0.78rem;
  font-weight: 700;
}

.att-checkin-site__change {
  flex-shrink: 0;
  align-self: center;
  border: 1px solid color-mix(in srgb, var(--color-tertiary-400) 45%, var(--color-border));
  border-radius: var(--radius-full);
  padding: 6px 14px;
  background: color-mix(in srgb, var(--color-tertiary-50) 70%, var(--color-surface));
  color: var(--color-tertiary-800);
  font: inherit;
  font-size: 0.75rem;
  font-weight: 800;
  cursor: pointer;
}

.att-checkin-site__note {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.72rem;
  line-height: 1.4;
}

.att-checkin-site__picker-title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.78rem;
  font-weight: 700;
}

.att-checkin-site__coords {
  display: block;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.01em;
}

.att-checkin-site__empty {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.att-checkin-site__options {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.att-checkin-site__option {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  width: 100%;
  padding: var(--space-2) var(--space-3);
  border: 1px solid color-mix(in srgb, var(--color-border) 80%, transparent);
  border-radius: 12px;
  background: color-mix(in srgb, var(--color-tertiary-50) 45%, var(--color-surface));
  color: var(--color-text);
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.att-checkin-site__option[aria-selected='true'] {
  border-color: color-mix(in srgb, var(--color-tertiary-400) 55%, var(--color-border));
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-tertiary-200) 75%, transparent);
}

.att-checkin-site__picker {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.att-checkin-site__option-name {
  font-weight: 700;
  font-size: 0.8125rem;
}

.att-checkin-site__option-code {
  color: var(--color-tertiary-800);
  font-size: 0.72rem;
  font-weight: 700;
}

.att-checkin-site__option-coords {
  color: var(--color-text-muted);
  font-size: 0.72rem;
  font-variant-numeric: tabular-nums;
}

.att-checkin-site__status {
  display: flex;
  align-items: flex-start;
  gap: 6px;
  margin: 0;
  padding: 8px var(--space-3);
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.4;
}

.att-checkin-site__status--ok {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
}

.att-checkin-site__status--warn {
  background: color-mix(in srgb, var(--color-gold-200) 55%, white);
  color: var(--color-umber-900);
}

.att-checkin-site__status--info {
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
}

.att-checkin-site__geo-btn {
  align-self: flex-start;
  border: 0;
  border-radius: var(--radius-full);
  padding: 6px 14px;
  background: color-mix(in srgb, var(--color-tertiary-100) 80%, white);
  color: var(--color-tertiary-800);
  font: inherit;
  font-size: 0.75rem;
  font-weight: 800;
  cursor: pointer;
}

.att-methods__label {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.att-punch-wrap {
  position: relative;
  align-self: center;
  display: grid;
  place-items: center;
  width: 13rem;
  height: 13rem;
  margin-top: 0;
  overflow: visible;
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
  gap: 8px;
  width: 10.5rem;
  height: 10.5rem;
  border: 9px solid color-mix(in srgb, var(--color-tertiary-100) 82%, var(--color-on-tertiary));
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
  max-width: 8rem;
  font-size: 0.92rem;
  font-weight: 800;
  line-height: 1.2;
  text-align: center;
}

.att-punch-hint {
  align-self: center;
  max-width: 100%;
  margin: 0 0 var(--space-2);
  padding: 0.45rem 0.9rem;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-surface) 92%, var(--color-tertiary-50));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-tertiary-200) 55%, var(--color-border));
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.4;
  text-align: center;
}

.att-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-2);
}

.att-alt-methods {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-2);
}

.att-alt-method {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  gap: 6px;
  min-width: 0;
  min-height: 6.75rem;
  padding: var(--space-3) var(--space-2);
  border: 0;
  border-radius: 18px;
  background: var(--color-surface);
  box-shadow: var(--att-shadow);
  color: var(--color-text);
  font: inherit;
  text-align: center;
  cursor: pointer;
}

.att-alt-method__icon {
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 14px;
  flex-shrink: 0;
  color: var(--color-on-tertiary);
}

.att-alt-method__label {
  display: block;
  width: 100%;
  font-size: 0.8125rem;
  font-weight: 800;
  line-height: 1.25;
}

.att-alt-method__hint {
  display: block;
  width: 100%;
  color: var(--color-warning-tint-fg, var(--color-text-muted));
  font-size: 0.68rem;
  font-weight: 700;
  line-height: 1.3;
}

.att-tile--maintenance .att-tile__hint {
  color: var(--color-warning-tint-fg, var(--color-text-muted));
  font-weight: 700;
}

.att-maint {
  align-items: center;
  text-align: center;
  padding-top: var(--space-4);
}

.att-maint__badge {
  display: grid;
  place-items: center;
  width: 4.5rem;
  height: 4.5rem;
  border-radius: var(--radius-full);
  background: var(--color-warning-tint-bg, color-mix(in srgb, var(--color-gold-200) 40%, white));
  color: var(--color-warning-tint-fg, var(--color-gold-700));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-gold-400) 35%, transparent);
}

.att-maint__title {
  margin: var(--space-3) 0 var(--space-1);
  font-size: 1.25rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--color-text);
}

.att-maint__lead {
  margin: 0 0 var(--space-3);
  max-width: 22rem;
  color: var(--color-text-muted);
  font-size: 0.875rem;
  line-height: 1.45;
}

.att-maint .att-banner {
  max-width: 22rem;
  text-align: left;
}

.att-tile:last-child {
  grid-column: 1 / -1;
}

.att-tile {
  border: 0;
  font: inherit;
  color: var(--color-text);
  cursor: pointer;
  text-align: left;
}

.att-tile {
  display: grid;
  grid-template-columns: auto 1fr;
  grid-template-rows: auto auto;
  align-items: center;
  column-gap: var(--space-2);
  row-gap: 2px;
  min-height: 0;
  padding: var(--space-3);
  text-align: left;
  font-size: 0.78rem;
  font-weight: 700;
  border-radius: 18px;
  overflow: visible;
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
  grid-column: 2;
  grid-row: 1;
  line-height: 1.3;
}

.att-tile__hint {
  grid-column: 2;
  grid-row: 2;
  color: var(--color-text-muted);
  font-size: 0.68rem;
  font-weight: 500;
  line-height: 1.35;
}

.att-tile__icon {
  grid-column: 1;
  grid-row: 1 / span 2;
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

.att-retry {
  align-self: center;
  min-height: 2.75rem;
  padding: 0.5rem var(--space-5);
  border: 0;
  border-radius: var(--radius-full);
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary-700);
  font: inherit;
  font-size: 0.875rem;
  font-weight: 700;
  cursor: pointer;
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
    width: 12.5rem;
    height: 12.5rem;
  }

  .att-punch {
    width: 10rem;
    height: 10rem;
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
