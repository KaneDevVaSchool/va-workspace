<script setup>
//
// Danh sách tài khoản dịch vụ (Credential module) — theo mẫu vàng
// Modules/Identity/resources/js/pages/ActivityLog.vue (skill data-table):
// filter → TablePagesBar trên → bảng kéo cột → TablePagesBar dưới → panel
// chi tiết đẩy ngang. Modal tạo/sửa theo mẫu vàng WorkspaceConfigEvaluation.vue
// (skill form-modal): lưới ngang 2 tab (Thông tin/Nâng cao), trong viewport.
//
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { formatDate } from '@/lib/formatTime';
import { showClientToast } from '@/lib/clientToast';
import { useDragScroll } from '@/composables/useDragScroll';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import {
  ACCOUNT_TYPES,
  STATUS_OPTIONS,
  STATUS_DOT_TONE,
  CREDENTIAL_COLUMNS,
  CREDENTIAL_FILTERS,
  COLUMN_STORAGE_KEY,
  COLUMN_WIDTH_KEY,
  FILTER_STORAGE_KEY,
  ZOOM_STORAGE_KEY,
  accountTypeLabel,
  statusLabel,
  loadVisibility,
  saveVisibility,
  countdownLabel,
  countdownTone,
  isCountdownUrgent,
} from '../constants/credential.js';

const CELL_PAD_X = 32;
const COL_EXTRA = 24;
let measureCtx = null;
let wrapObserver = null;

const router = useRouter();
const auth = useAuthStore();

const credentials = ref([]);
const providers = ref([]);
const departments = ref([]);
const allUsers = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 20 });
const loading = ref(false);
const selected = ref(null);

// ---------- Tab: "Phần mềm/Dịch vụ" (external) / "Công cụ nội bộ" (internal) / "Dự toán chi phí" (cost) ----------
const activeTab = ref('external');
const costSummary = ref(null);
const costForecast = ref(null);
const costLoading = ref(false);
const costLoaded = ref(false);
const costHoverSlice = ref(null);
const forecastHoverMonth = ref(null);
const sheetSortKey = ref('name');
const sheetSortDir = ref('asc');

// Số hiển thị đếm dần (count-up) cho 3 ô số liệu lớn khi tab mở lần đầu.
const animatedTotalMonthly = ref(0);
const animatedYearly = ref(0);
const animatedCount = ref(0);

function animateCount(setter, finalValue, duration = 800) {
  const start = performance.now();
  function tick(now) {
    const progress = Math.min((now - start) / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    setter(Math.round(finalValue * eased));
    if (progress < 1) requestAnimationFrame(tick);
  }
  requestAnimationFrame(tick);
}

async function loadCostSummary() {
  costLoading.value = true;
  try {
    const [summaryRes, forecastRes] = await Promise.all([
      window.axios.get('/api/credential/cost-summary'),
      window.axios.get('/api/credential/cost-forecast'),
    ]);
    costSummary.value = summaryRes.data;
    costForecast.value = forecastRes.data;
    costLoaded.value = true;
    animateCount((v) => { animatedTotalMonthly.value = v; }, costSummary.value.total_monthly);
    animateCount((v) => { animatedYearly.value = v; }, costSummary.value.total_yearly_estimate);
    animateCount((v) => { animatedCount.value = v; }, costSummary.value.account_count, 500);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được dự toán chi phí.');
  } finally {
    costLoading.value = false;
  }
}

function switchTab(tab) {
  activeTab.value = tab;
  if (tab === 'cost') {
    if (!costLoaded.value) loadCostSummary();
    return;
  }
  loadCredentials(1);
}

function toggleSheetSort(key) {
  if (sheetSortKey.value === key) {
    sheetSortDir.value = sheetSortDir.value === 'asc' ? 'desc' : 'asc';
  } else {
    sheetSortKey.value = key;
    sheetSortDir.value = 'asc';
  }
}

const sortedSheetItems = computed(() => {
  const items = [...(costSummary.value?.items ?? [])];
  const key = sheetSortKey.value;
  items.sort((a, b) => {
    const av = a[key];
    const bv = b[key];
    const cmp = typeof av === 'number' && typeof bv === 'number'
      ? av - bv
      : String(av ?? '').localeCompare(String(bv ?? ''), 'vi');
    return sheetSortDir.value === 'asc' ? cmp : -cmp;
  });
  return items;
});

/** Nhãn tháng rút gọn "T9", "T10"... từ khoá "2026-09". */
function monthShortLabel(monthKey) {
  const parts = String(monthKey).split('-');
  return `T${Number(parts[1])}`;
}

/** Dựng path SVG tuyến tính (đường + vùng fill dưới) cho biểu đồ dự phóng 12 tháng. */
function buildForecastPath(months, width = 600, height = 160, padding = 24) {
  if (!months.length) return { path: '', points: [], areaPath: '' };
  const maxY = Math.max(...months.map((m) => m.total_vnd), 1);
  const stepX = (width - padding * 2) / (months.length - 1 || 1);
  const points = months.map((m, i) => ({
    x: padding + i * stepX,
    y: height - padding - (m.total_vnd / maxY) * (height - padding * 2),
    ...m,
  }));
  const path = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');
  const areaPath = `${path} L ${points[points.length - 1].x} ${height - padding} L ${points[0].x} ${height - padding} Z`;
  return { path, points, areaPath };
}

const forecastChart = computed(() => buildForecastPath(costForecast.value?.months ?? []));

const query = ref('');
const providerId = ref('');
const accountType = ref('');
const status = ref('');
const perPage = ref(20);

const visibleColumns = reactive(loadVisibility(COLUMN_STORAGE_KEY, CREDENTIAL_COLUMNS));
const visibleFilters = reactive(loadVisibility(FILTER_STORAGE_KEY, CREDENTIAL_FILTERS));

const tableWrap = ref(null);
const resizing = ref(false);
const MIN_COL_PX = 72;

useDragScroll(tableWrap, { isBlocked: () => resizing.value });

const columnWidths = reactive(loadColumnWidths());
const tableZoom = ref(loadZoom());

const canManage = computed(() => auth.can('credential.manage'));
// Chỉ role đang giữ credential.manage KHÔNG scope theo phòng ban mới được
// chọn/đổi phòng ban sở hữu khi tạo/sửa — khớp CredentialService::
// departmentScopeFor() (null = không giới hạn) ở backend. Người khác luôn
// bị backend ép về đúng phòng ban mình dù có gửi field này hay không, nên
// ẩn hẳn field đi cho đỡ gây hiểu lầm "chọn được nhưng không có tác dụng".
const canChooseDepartment = computed(() => canManage.value);

const shownColumns = computed(() => CREDENTIAL_COLUMNS.filter((col) => visibleColumns[col.key]));
const colSpan = computed(() => Math.max(shownColumns.value.length, 1));

const hasActiveFilters = computed(
  () =>
    Boolean(query.value.trim()) || Boolean(providerId.value) || Boolean(accountType.value) || Boolean(status.value),
);

const hasVisibleFilterFields = computed(() => CREDENTIAL_FILTERS.some((item) => visibleFilters[item.key]));

// 2 hue categorical đã chạy validate_palette.js (skill dataviz) xác nhận PASS
// mọi check colorblind-safe SAU KHI loại --color-primary theo yêu cầu tab
// dashboard này — mọi ứng viên thứ 3 (gold/umber các bậc, --color-info) đều
// FAIL chroma/lightness hoặc trùng nghĩa màu trạng thái đã dùng trong chính
// trang này (--color-warning = "sắp hết hạn"). Nhóm thứ 3 trở đi gộp "Khác"
// ở backend, dùng màu xám trung tính (không phải hue phân loại). Khớp
// CredentialEnums::COST_CHART_MAX_SLICES = 2.
const COST_SLICE_COLORS = ['var(--color-secondary)', 'var(--color-tertiary)', 'var(--color-text-muted)'];

/** Stop-color đậm hơn cho gradient — dùng color-mix() trên chính màu slice, không hex mới. */
function sliceGradientDark(color) {
  return `color-mix(in srgb, ${color} 70%, black)`;
}

function sliceColor(index) {
  return COST_SLICE_COLORS[index] ?? COST_SLICE_COLORS[COST_SLICE_COLORS.length - 1];
}

function costFormat(amount) {
  return `${Number(amount || 0).toLocaleString('vi-VN')} đ`;
}

/** Toạ độ điểm trên vòng tròn bán kính r, tâm (cx, cy), góc tính từ đỉnh 12h. */
function polarPoint(cx, cy, r, angleDeg) {
  const rad = ((angleDeg - 90) * Math.PI) / 180;
  return { x: cx + r * Math.cos(rad), y: cy + r * Math.sin(rad) };
}

/** Chuẩn bị lát donut (path SVG) từ danh sách {label, amount} — kèm % và màu cố định theo thứ tự. */
function buildDonutSlices(entries) {
  const total = entries.reduce((sum, e) => sum + Number(e.amount || 0), 0);
  if (total <= 0) return { total: 0, slices: [] };

  const cx = 60;
  const cy = 60;
  const rOuter = 54;
  const rInner = 32;
  const gapDeg = entries.length > 1 ? 2 : 0;
  let angle = 0;

  const slices = entries.map((entry, index) => {
    const fraction = Number(entry.amount || 0) / total;
    const sweep = Math.max(fraction * 360 - gapDeg, 0);
    const startAngle = angle;
    const endAngle = angle + sweep;
    angle += fraction * 360;

    const large = sweep > 180 ? 1 : 0;
    const outerStart = polarPoint(cx, cy, rOuter, startAngle);
    const outerEnd = polarPoint(cx, cy, rOuter, endAngle);
    const innerEnd = polarPoint(cx, cy, rInner, endAngle);
    const innerStart = polarPoint(cx, cy, rInner, startAngle);

    const path = [
      `M ${outerStart.x} ${outerStart.y}`,
      `A ${rOuter} ${rOuter} 0 ${large} 1 ${outerEnd.x} ${outerEnd.y}`,
      `L ${innerEnd.x} ${innerEnd.y}`,
      `A ${rInner} ${rInner} 0 ${large} 0 ${innerStart.x} ${innerStart.y}`,
      'Z',
    ].join(' ');

    return {
      key: entry.label,
      label: entry.label,
      amount: entry.amount,
      percent: fraction * 100,
      color: sliceColor(index),
      path,
    };
  });

  return { total, slices };
}

const providerDonut = computed(() => buildDonutSlices(costSummary.value?.by_provider ?? []));

const costHoverInfo = computed(() => {
  if (!costHoverSlice.value) return null;
  return providerDonut.value.slices.find((s) => s.key === costHoverSlice.value) || null;
});

const accountTypeMaxAmount = computed(() => {
  const list = costSummary.value?.by_account_type ?? [];
  return list.reduce((max, e) => Math.max(max, Number(e.amount || 0)), 0) || 1;
});

const tableWidthPx = computed(() => {
  const keys = shownColumns.value.map((col) => col.key);
  const sum = keys.reduce((total, key) => total + (Number(columnWidths[key]) || 0), 0);
  return sum > 0 ? `${sum}px` : '100%';
});

function currentFilterParams() {
  return {
    q: query.value.trim() || undefined,
    provider_id: providerId.value || undefined,
    account_type: accountType.value || undefined,
    status: status.value || undefined,
    group: activeTab.value !== 'cost' ? activeTab.value : undefined,
  };
}

async function loadProviders() {
  try {
    const { data } = await window.axios.get('/api/credential/providers');
    providers.value = data.providers ?? [];
  } catch {
    providers.value = [];
  }
}

async function loadDepartments() {
  try {
    const { data } = await window.axios.get('/manager/departments');
    departments.value = data.departments ?? [];
  } catch {
    departments.value = [];
  }
}

async function loadUsers() {
  try {
    const { data } = await window.axios.get('/api/credential/users');
    allUsers.value = data.users ?? [];
  } catch {
    allUsers.value = [];
  }
}

async function loadCredentials(page = 1) {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/credential', {
      params: { ...currentFilterParams(), page, per_page: perPage.value },
    });
    credentials.value = data.credentials ?? [];
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 20 };

    if (selected.value && !credentials.value.some((c) => c.id === selected.value.id)) {
      selected.value = null;
    }
    nextTick(fitColumnsToContent);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được danh sách tài khoản.');
  } finally {
    loading.value = false;
  }
}

function goPage(page) {
  if (page < 1 || page > meta.value.last_page || page === meta.value.current_page) return;
  loadCredentials(page);
}

function clearFilters() {
  query.value = '';
  providerId.value = '';
  accountType.value = '';
  status.value = '';
  loadCredentials(1);
}

function inspect(credential) {
  router.push({ name: 'manager.credential.detail', params: { id: credential.id } });
}

function providerLabel(credential) {
  return credential.provider?.name || '—';
}

function cellText(credential, key) {
  if (key === 'name') return credential.name || '—';
  if (key === 'department') return credential.department?.name || '—';
  if (key === 'provider') return providerLabel(credential);
  if (key === 'account_type') return accountTypeLabel(credential.account_type);
  if (key === 'email') return credential.email || '—';
  if (key === 'status') return statusLabel(credential.status);
  if (key === 'expires_at') return credential.expires_at ? formatDate(credential.expires_at) : '—';
  if (key === 'access_url') return credential.access_url || '—';
  if (key === 'monthly_cost') {
    if (credential.cost_hidden || credential.monthly_cost == null) return '—';
    return `${Number(credential.monthly_cost).toLocaleString('vi-VN')} ${credential.currency || 'VND'}`;
  }
  if (key === 'server_name') return credential.server_name || '—';
  if (key === 'domain') return credential.domain || '—';
  if (key === 'creator_name') return credential.creator_name || '—';
  return '—';
}

function statusTone(value) {
  return STATUS_DOT_TONE[value] || 'neutral';
}

function loadZoom() {
  try {
    const raw = Number(localStorage.getItem(ZOOM_STORAGE_KEY));
    if (raw === 0.9 || raw === 1 || raw === 1.15) return raw;
  } catch {
    // Bỏ qua.
  }
  return 1;
}

function loadColumnWidths() {
  try {
    const raw = localStorage.getItem(COLUMN_WIDTH_KEY);
    const parsed = raw ? JSON.parse(raw) : {};
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) return parsed;
  } catch {
    // Bỏ qua nếu trình duyệt chặn localStorage.
  }
  return {};
}

function colWidthStyle(key) {
  const width = columnWidths[key];
  return width ? `${width}px` : undefined;
}

function measureText(text, font) {
  if (!measureCtx) {
    measureCtx = document.createElement('canvas').getContext('2d');
  }
  measureCtx.font = font;
  return measureCtx.measureText(String(text ?? '')).width;
}

function fontOf(el, fallback) {
  if (!el) return fallback;
  const style = getComputedStyle(el);
  return `${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
}

function readTableFonts() {
  const table = tableWrap.value?.querySelector('.credential-page__table');
  return {
    header: fontOf(table?.querySelector('thead th'), '600 12px "Be Vietnam Pro", sans-serif'),
    cell: fontOf(table?.querySelector('tbody td'), '400 14px "Be Vietnam Pro", sans-serif'),
  };
}

function columnContentWidth(key, fonts) {
  const label = CREDENTIAL_COLUMNS.find((col) => col.key === key)?.label ?? '';
  let maxW = measureText(label, fonts.header);
  for (const credential of credentials.value) {
    if (key === 'status') {
      // Trạng thái giờ xếp 2 dòng dọc (label / countdown, xem template) —
      // đo dòng DÀI NHẤT trong 2 dòng, không cộng dồn ngang. Trước đây
      // cellText('status') chỉ đo statusLabel(), hoàn toàn bỏ sót phần
      // countdown mà template render thêm — cột bị đo hụt, gây tràn/đè
      // chữ sang cột kế khi countdown xuất hiện.
      const line1 = measureText(cellText(credential, key), fonts.cell);
      const countdown = countdownLabel(credential.expires_at);
      const line2 = countdown ? measureText(countdown, fonts.cell) + 32 : 0; // buffer icon + margin-left
      maxW = Math.max(maxW, line1, line2);
      continue;
    }
    maxW = Math.max(maxW, measureText(cellText(credential, key), fonts.cell));
  }
  return Math.max(MIN_COL_PX, Math.ceil(maxW + CELL_PAD_X + COL_EXTRA));
}

function distributeExtraWidth(widths, keys, available) {
  const sum = keys.reduce((total, key) => total + widths[key], 0);
  if (sum <= 0 || available <= sum) return widths;

  const extra = available - sum;
  const next = { ...widths };
  let used = 0;
  keys.forEach((key, index) => {
    if (index === keys.length - 1) {
      next[key] = available - used;
      return;
    }
    next[key] = widths[key] + Math.floor((widths[key] / sum) * extra);
    used += next[key];
  });
  return next;
}

function fitColumnsToContent() {
  const wrap = tableWrap.value;
  const keys = shownColumns.value.map((col) => col.key);
  if (!wrap || keys.length === 0 || resizing.value) return;

  const fonts = readTableFonts();
  const measured = {};
  for (const key of keys) {
    measured[key] = columnContentWidth(key, fonts);
  }

  const next = distributeExtraWidth(measured, keys, wrap.clientWidth);
  for (const key of keys) {
    columnWidths[key] = next[key];
  }
}

function startResize(event, key) {
  const keys = shownColumns.value.map((col) => col.key);
  const index = keys.indexOf(key);
  if (index < 0) return;

  const neighbor = keys[index + 1] ?? keys[index - 1];
  if (!neighbor || neighbor === key) return;

  const towardNext = keys.indexOf(neighbor) > index;
  const startX = event.clientX;
  const startA = Number(columnWidths[key]) || MIN_COL_PX;
  const startB = Number(columnWidths[neighbor]) || MIN_COL_PX;
  const pair = startA + startB;

  resizing.value = true;

  function onMove(moveEvent) {
    const delta = (moveEvent.clientX - startX) * (towardNext ? 1 : -1);
    let nextA = Math.round(startA + delta);
    nextA = Math.min(Math.max(nextA, MIN_COL_PX), pair - MIN_COL_PX);
    columnWidths[key] = nextA;
    columnWidths[neighbor] = pair - nextA;
  }

  function onUp() {
    resizing.value = false;
    window.removeEventListener('mousemove', onMove);
    window.removeEventListener('mouseup', onUp);
  }

  window.addEventListener('mousemove', onMove);
  window.addEventListener('mouseup', onUp);
}

function onColumnToggle(key, checked) {
  if (!checked) {
    const remaining = CREDENTIAL_COLUMNS.filter((col) => visibleColumns[col.key] && col.key !== key).length;
    if (remaining < 1) {
      showClientToast('warning', 'Cần giữ ít nhất một cột trên bảng.');
      return;
    }
  }
  visibleColumns[key] = checked;
}

function onFilterToggle(key, checked) {
  visibleFilters[key] = checked;
}

// ---------- Modal tạo/sửa (skill form-modal) ----------
const dialogOpen = ref(false);
const dialogMode = ref('create'); // 'create' | 'edit'
const dialogTab = ref('info'); // 'info' | 'advanced'
const formSaving = ref(false);
const editingId = ref(null);

const form = reactive(emptyForm());

function emptyForm() {
  return {
    department_id: '',
    name: '',
    provider_id: '',
    account_type: 'user',
    group: activeTab.value === 'internal' ? 'internal' : 'external',
    username: '',
    email: '',
    password: '',
    is_google_login: false,
    google_account_owner_id: '',
    server_name: '',
    vps_cluster: '',
    domain: '',
    access_url: '',
    database_name: '',
    is_root_account: false,
    is_iam_account: false,
    notes: '',
    purchased_at: '',
    monthly_cost: '',
    cost_hidden: false,
    currency: 'VND',
  };
}

const selectedProviderCategory = computed(() => {
  const provider = providers.value.find((p) => p.id === Number(form.provider_id));
  return provider?.category || '';
});

const showInfraFields = computed(() => {
  const category = selectedProviderCategory.value;
  return category === 'Hạ tầng server' || category === 'Hạ tầng cloud' || category === 'Cơ sở dữ liệu' || category === 'Tên miền';
});

function openCreateDialog() {
  Object.assign(form, emptyForm());
  dialogMode.value = 'create';
  dialogTab.value = 'info';
  editingId.value = null;
  dialogOpen.value = true;
}

// ---------- Tạo nhanh Nhà cung cấp (nút + cạnh select trong modal) ----------
const providerQuickOpen = ref(false);
const providerQuickForm = reactive({ name: '', category: '' });
const providerQuickSaving = ref(false);

function openProviderQuick() {
  providerQuickForm.name = '';
  providerQuickForm.category = '';
  providerQuickOpen.value = true;
}

function closeProviderQuick() {
  if (providerQuickSaving.value) return;
  providerQuickOpen.value = false;
}

async function submitProviderQuick() {
  if (!providerQuickForm.name.trim()) {
    showClientToast('error', 'Vui lòng nhập tên nhà cung cấp.');
    return;
  }
  providerQuickSaving.value = true;
  try {
    const { data } = await window.axios.post('/api/credential/providers', {
      name: providerQuickForm.name.trim(),
      category: providerQuickForm.category.trim() || null,
    });
    providers.value.push(data.provider);
    form.provider_id = data.provider.id;
    providerQuickOpen.value = false;
    showClientToast('success', 'Đã thêm nhà cung cấp.');
  } catch (error) {
    const message = error?.response?.data?.message || Object.values(error?.response?.data?.errors || {})[0]?.[0];
    showClientToast('error', message || 'Không thêm được nhà cung cấp.');
  } finally {
    providerQuickSaving.value = false;
  }
}

function openEditDialog(credential) {
  Object.assign(form, {
    department_id: credential.department_id || '',
    name: credential.name || '',
    provider_id: credential.provider?.id || '',
    account_type: credential.account_type || 'user',
    group: credential.group || 'external',
    username: credential.username || '',
    email: credential.email || '',
    password: '',
    is_google_login: Boolean(credential.is_google_login),
    google_account_owner_id: credential.google_account_owner_id || '',
    server_name: credential.server_name || '',
    vps_cluster: credential.vps_cluster || '',
    domain: credential.domain || '',
    access_url: credential.access_url || '',
    database_name: credential.database_name || '',
    is_root_account: Boolean(credential.is_root_account),
    is_iam_account: Boolean(credential.is_iam_account),
    notes: credential.notes || '',
    purchased_at: credential.purchased_at || '',
    monthly_cost: credential.monthly_cost ?? '',
    cost_hidden: Boolean(credential.cost_hidden),
    currency: credential.currency || 'VND',
  });
  dialogMode.value = 'edit';
  dialogTab.value = 'info';
  editingId.value = credential.id;
  dialogOpen.value = true;
}

function closeDialog() {
  if (formSaving.value) return;
  dialogOpen.value = false;
}

async function submitForm() {
  if (!form.name.trim()) {
    showClientToast('error', 'Vui lòng nhập tên tài khoản.');
    return;
  }

  formSaving.value = true;
  try {
    const payload = { ...form };
    if (payload.department_id === '') payload.department_id = null;
    if (payload.provider_id === '') payload.provider_id = null;
    if (payload.monthly_cost === '') payload.monthly_cost = null;
    if (payload.purchased_at === '') payload.purchased_at = null;
    if (payload.google_account_owner_id === '') payload.google_account_owner_id = null;
    if (dialogMode.value === 'edit' && payload.password === '') delete payload.password;

    if (dialogMode.value === 'create') {
      await window.axios.post('/api/credential', payload);
      showClientToast('success', 'Đã tạo tài khoản.');
    } else {
      await window.axios.put(`/api/credential/${editingId.value}`, payload);
      showClientToast('success', 'Đã cập nhật tài khoản.');
    }
    dialogOpen.value = false;
    await loadCredentials(meta.value.current_page);
  } catch (error) {
    const message = error?.response?.data?.message || Object.values(error?.response?.data?.errors || {})[0]?.[0];
    showClientToast('error', message || 'Không lưu được tài khoản.');
  } finally {
    formSaving.value = false;
  }
}

// ---------- Xoá ----------
const deleteTarget = ref(null);
const deleting = ref(false);

function askDelete(credential) {
  deleteTarget.value = credential;
}

async function confirmDelete() {
  if (!deleteTarget.value) return;
  deleting.value = true;
  try {
    await window.axios.delete(`/api/credential/${deleteTarget.value.id}`);
    showClientToast('success', 'Đã xoá tài khoản.');
    deleteTarget.value = null;
    await loadCredentials(meta.value.current_page);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không xoá được tài khoản.');
  } finally {
    deleting.value = false;
  }
}

// ---------- Menu thao tác theo dòng ----------
const openMenuId = ref(null);

function toggleRowMenu(id) {
  openMenuId.value = openMenuId.value === id ? null : id;
}

function closeRowMenu() {
  openMenuId.value = null;
}

function onDocumentClick(event) {
  if (openMenuId.value === null) return;
  if (!event.target.closest('.credential-page__row-menu')) {
    closeRowMenu();
  }
}

function handleDocumentKeydown(event) {
  if (event.key === 'Escape') {
    closeRowMenu();
    if (providerQuickOpen.value) {
      closeProviderQuick();
      return;
    }
    if (dialogOpen.value) closeDialog();
  }
}

watch(visibleColumns, (value) => saveVisibility(COLUMN_STORAGE_KEY, value), { deep: true });
watch(visibleFilters, (value) => saveVisibility(FILTER_STORAGE_KEY, value), { deep: true });
watch(columnWidths, (value) => saveVisibility(COLUMN_WIDTH_KEY, value), { deep: true });
watch(tableZoom, (value) => {
  try {
    localStorage.setItem(ZOOM_STORAGE_KEY, String(value));
  } catch {
    // Bỏ qua.
  }
  nextTick(fitColumnsToContent);
});
watch(shownColumns, () => nextTick(fitColumnsToContent));
watch([providerId, accountType, status, perPage], () => loadCredentials(1));

onMounted(() => {
  document.addEventListener('mousedown', onDocumentClick);
  document.addEventListener('keydown', handleDocumentKeydown);
  loadProviders();
  loadDepartments();
  loadUsers();
  loadCredentials(1);
  nextTick(() => {
    fitColumnsToContent();
    if (tableWrap.value) {
      let lastWrapWidth = tableWrap.value.clientWidth;
      wrapObserver = new ResizeObserver((entries) => {
        const width = Math.round(entries[0]?.contentRect?.width || 0);
        if (!width || width === lastWrapWidth || resizing.value) return;
        lastWrapWidth = width;
        fitColumnsToContent();
      });
      wrapObserver.observe(tableWrap.value);
    }
  });
  document.fonts?.ready?.then(() => nextTick(fitColumnsToContent));
});

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onDocumentClick);
  document.removeEventListener('keydown', handleDocumentKeydown);
  wrapObserver?.disconnect();
});
</script>

<template>
  <section class="credential-page">
    <PageHeader
      title="Quản lý tài khoản"
      description="Tài khoản dịch vụ dùng trong công ty — Google, Canva, Cursor, Claude, AWS, VPS, database, IAM, domain..."
    >
      <template #title>
        <span class="credential-page__title">
          <button
            v-if="canManage"
            type="button"
            class="credential-page__title-icon-btn credential-page__title-icon-btn--add"
            aria-label="Thêm tài khoản"
            @click="openCreateDialog"
          >
            <AppIcon name="plus" :size="18" :stroke-width="2" />
          </button>
          <AppIcon v-else name="lock" :size="16" />
          Quản lý tài khoản
          <button
            type="button"
            class="credential-page__title-icon-btn"
            aria-label="Làm mới danh sách"
            :disabled="loading"
            @click="loadCredentials(meta.current_page)"
          >
            <AppIcon name="refresh" :size="15" :class="{ 'credential-page__spin': loading }" />
          </button>
          <AppIcon v-if="canManage" name="lock" :size="16" />
        </span>
      </template>
    </PageHeader>

    <div class="credential-page__tabs" role="tablist" aria-label="Chế độ xem tài khoản">
      <button
        type="button"
        class="credential-page__tab"
        :class="{ 'credential-page__tab--active': activeTab === 'external' }"
        role="tab"
        :aria-selected="activeTab === 'external' ? 'true' : 'false'"
        @click="switchTab('external')"
      >
        Phần mềm/Dịch vụ
      </button>
      <button
        type="button"
        class="credential-page__tab"
        :class="{ 'credential-page__tab--active': activeTab === 'internal' }"
        role="tab"
        :aria-selected="activeTab === 'internal' ? 'true' : 'false'"
        @click="switchTab('internal')"
      >
        Công cụ nội bộ
      </button>
      <button
        type="button"
        class="credential-page__tab"
        :class="{ 'credential-page__tab--active': activeTab === 'cost' }"
        role="tab"
        :aria-selected="activeTab === 'cost' ? 'true' : 'false'"
        @click="switchTab('cost')"
      >
        Dự toán chi phí
      </button>
    </div>

    <div v-if="activeTab !== 'cost'" class="credential-page__body">
      <div class="credential-page__main">
        <div v-if="hasVisibleFilterFields" class="credential-page__toolbar">
          <div class="credential-page__filters">
            <div v-if="visibleFilters.q" class="credential-page__field">
              <label class="credential-page__label" for="credential-q">Tìm kiếm</label>
              <input
                id="credential-q"
                v-model="query"
                type="search"
                class="credential-page__input"
                placeholder="Vd. Canva Pro, admin@vaschools.edu.vn"
                @keydown.enter="loadCredentials(1)"
              />
            </div>

            <div v-if="visibleFilters.provider_id" class="credential-page__field">
              <label class="credential-page__label" for="credential-provider">Nhà cung cấp</label>
              <select id="credential-provider" v-model="providerId" class="credential-page__input">
                <option value="">Tất cả nhà cung cấp</option>
                <option v-for="p in providers" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>

            <div v-if="visibleFilters.account_type" class="credential-page__field">
              <label class="credential-page__label" for="credential-type">Loại tài khoản</label>
              <select id="credential-type" v-model="accountType" class="credential-page__input">
                <option value="">Tất cả loại</option>
                <option v-for="item in ACCOUNT_TYPES" :key="item.value" :value="item.value">{{ item.label }}</option>
              </select>
            </div>

            <div v-if="visibleFilters.status" class="credential-page__field">
              <label class="credential-page__label" for="credential-status">Trạng thái</label>
              <select id="credential-status" v-model="status" class="credential-page__input">
                <option v-for="item in STATUS_OPTIONS" :key="item.value || 'all'" :value="item.value">{{ item.label }}</option>
              </select>
            </div>
          </div>
        </div>

        <TablePagesBar
          placement="top"
          :from="meta.from || 0"
          :to="meta.to || 0"
          :total="meta.total || 0"
          :page="meta.current_page || 1"
          :last-page="meta.last_page || 1"
          :per-page="perPage"
          :zoom="tableZoom"
          show-search
          :show-clear-filters="hasActiveFilters"
          :filters-active="hasActiveFilters"
          @search="loadCredentials(1)"
          @clear-filters="clearFilters"
          @update:page="goPage"
          @update:per-page="perPage = $event"
          @update:zoom="tableZoom = $event"
        >
          <template #filters>
            <label v-for="item in CREDENTIAL_FILTERS" :key="item.key" class="credential-page__check">
              <input type="checkbox" :checked="visibleFilters[item.key]" @change="onFilterToggle(item.key, $event.target.checked)" />
              <span>{{ item.label }}</span>
            </label>
          </template>
          <template #settings>
            <label v-for="col in CREDENTIAL_COLUMNS" :key="col.key" class="credential-page__check">
              <input type="checkbox" :checked="visibleColumns[col.key]" @change="onColumnToggle(col.key, $event.target.checked)" />
              <span>{{ col.label }}</span>
            </label>
          </template>
        </TablePagesBar>

        <div
          ref="tableWrap"
          class="credential-page__table-wrap hide-scrollbar"
          :class="{ 'credential-page__table-wrap--resizing': resizing }"
          :style="{ '--table-zoom': tableZoom }"
        >
          <table class="credential-page__table" :style="{ width: tableWidthPx }">
            <colgroup>
              <col v-for="col in shownColumns" :key="col.key" :style="{ width: colWidthStyle(col.key) }" />
              <col style="width: 3rem" />
            </colgroup>
            <thead>
              <tr>
                <th v-for="col in shownColumns" :key="col.key">
                  <span>{{ col.label }}</span>
                  <button
                    type="button"
                    class="credential-page__resize"
                    aria-label="Kéo để đổi độ rộng cột"
                    @click.stop
                    @mousedown.stop.prevent="startResize($event, col.key)"
                  />
                </th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td :colspan="colSpan + 1" class="credential-page__empty">Đang tải…</td>
              </tr>
              <tr v-else-if="credentials.length === 0">
                <td :colspan="colSpan + 1" class="credential-page__empty">Chưa có tài khoản nào.</td>
              </tr>
              <tr v-for="credential in credentials" v-else :key="credential.id" @click="inspect(credential)">
                <td v-for="col in shownColumns" :key="col.key">
                  <template v-if="col.key === 'status'">
                    <span class="credential-page__status-cell">
                      <span class="credential-page__status">
                        <span class="credential-page__dot" :class="`credential-page__dot--${statusTone(credential.status)}`" />
                        {{ statusLabel(credential.status) }}
                      </span>
                      <span
                        v-if="countdownLabel(credential.expires_at)"
                        class="credential-page__countdown"
                        :class="[
                          countdownTone(credential.expires_at) ? `credential-page__countdown--${countdownTone(credential.expires_at)}` : '',
                          isCountdownUrgent(credential.expires_at) ? 'credential-page__countdown--urgent' : '',
                        ]"
                      >
                        <AppIcon v-if="isCountdownUrgent(credential.expires_at)" name="clock" :size="11" />
                        {{ countdownLabel(credential.expires_at) }}
                      </span>
                    </span>
                  </template>
                  <template v-else-if="col.key === 'access_url'">
                    <a
                      v-if="credential.access_url"
                      :href="credential.access_url"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="credential-page__link"
                      @click.stop
                    >
                      {{ credential.access_url }}
                    </a>
                    <span v-else class="credential-page__cell">—</span>
                  </template>
                  <span v-else class="credential-page__cell">{{ cellText(credential, col.key) }}</span>
                </td>
                <td class="credential-page__row-menu" @click.stop>
                  <button
                    type="button"
                    class="credential-page__menu-trigger"
                    aria-label="Thao tác"
                    aria-haspopup="menu"
                    :aria-expanded="openMenuId === credential.id"
                    @click="toggleRowMenu(credential.id)"
                  >
                    <AppIcon name="gripVertical" :size="16" />
                  </button>
                  <div v-if="openMenuId === credential.id" class="credential-page__menu" role="menu">
                    <button type="button" role="menuitem" class="credential-page__menu-item" @click="inspect(credential); closeRowMenu()">
                      <AppIcon name="eye" :size="15" />
                      Xem chi tiết
                    </button>
                    <button
                      v-if="canManage"
                      type="button"
                      role="menuitem"
                      class="credential-page__menu-item"
                      @click="openEditDialog(credential); closeRowMenu()"
                    >
                      <AppIcon name="pencil" :size="15" />
                      Sửa
                    </button>
                    <button
                      v-if="canManage"
                      type="button"
                      role="menuitem"
                      class="credential-page__menu-item credential-page__menu-item--danger"
                      @click="askDelete(credential); closeRowMenu()"
                    >
                      <AppIcon name="trash" :size="15" />
                      Xoá
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <TablePagesBar
          placement="bottom"
          paging-only
          :from="meta.from || 0"
          :to="meta.to || 0"
          :total="meta.total || 0"
          :page="meta.current_page || 1"
          :last-page="meta.last_page || 1"
          :per-page="perPage"
          @update:page="goPage"
          @update:per-page="perPage = $event"
        />
      </div>
    </div>

    <!-- Tab Dự toán chi phí -->
    <div v-else class="credential-page__cost">
      <div v-if="costLoading" class="credential-page__cost-loading">Đang tải dự toán chi phí…</div>

      <template v-else-if="costSummary">
        <div class="credential-page__report-head">
          <div>
            <h2 class="credential-page__report-title">Báo cáo chi phí tài khoản dịch vụ</h2>
            <p class="credential-page__report-desc">Tổng hợp chi phí hiện tại, phân bổ theo nhà cung cấp/loại tài khoản và dự phóng 12 tháng tới.</p>
          </div>
          <div class="credential-page__report-actions">
            <a class="credential-page__export-btn" href="/api/credential/cost-summary/export-excel" target="_blank" rel="noopener">
              <AppIcon name="fileSpreadsheet" :size="15" />
              Xuất Excel
            </a>
            <a class="credential-page__export-btn" href="/api/credential/cost-summary/export-pdf" target="_blank" rel="noopener">
              <AppIcon name="fileText" :size="15" />
              Xuất PDF
            </a>
          </div>
        </div>

        <div class="credential-page__cost-stats">
          <div class="credential-page__cost-stat credential-page__cost-stat--secondary">
            <svg class="credential-page__cost-stat-deco" viewBox="0 0 80 80" aria-hidden="true">
              <defs>
                <clipPath id="cred-stat-clip-0">
                  <path d="M80 0 L80 80 L20 80 C50 60 60 30 80 0 Z" />
                </clipPath>
              </defs>
              <rect width="80" height="80" fill="var(--color-secondary)" clip-path="url(#cred-stat-clip-0)" opacity="0.1" />
            </svg>
            <span class="credential-page__cost-stat-label">Tổng chi phí mỗi tháng</span>
            <span class="credential-page__cost-stat-value">{{ costFormat(animatedTotalMonthly) }}</span>
          </div>
          <div class="credential-page__cost-stat credential-page__cost-stat--tertiary">
            <svg class="credential-page__cost-stat-deco" viewBox="0 0 80 80" aria-hidden="true">
              <defs>
                <clipPath id="cred-stat-clip-1">
                  <path d="M80 0 L80 80 L20 80 C50 60 60 30 80 0 Z" />
                </clipPath>
              </defs>
              <rect width="80" height="80" fill="var(--color-tertiary)" clip-path="url(#cred-stat-clip-1)" opacity="0.1" />
            </svg>
            <span class="credential-page__cost-stat-label">Ước tính mỗi năm</span>
            <span class="credential-page__cost-stat-value">{{ costFormat(animatedYearly) }}</span>
          </div>
          <div class="credential-page__cost-stat credential-page__cost-stat--muted">
            <svg class="credential-page__cost-stat-deco" viewBox="0 0 80 80" aria-hidden="true">
              <defs>
                <clipPath id="cred-stat-clip-2">
                  <path d="M80 0 L80 80 L20 80 C50 60 60 30 80 0 Z" />
                </clipPath>
              </defs>
              <rect width="80" height="80" fill="var(--color-text-muted)" clip-path="url(#cred-stat-clip-2)" opacity="0.1" />
            </svg>
            <span class="credential-page__cost-stat-label">Số tài khoản đang tính phí</span>
            <span class="credential-page__cost-stat-value">{{ animatedCount }}</span>
          </div>
        </div>

        <div v-if="providerDonut.total > 0" class="credential-page__cost-panels">
          <div class="credential-page__cost-card">
            <h3 class="credential-page__cost-card-title">Theo nhà cung cấp</h3>
            <div class="credential-page__donut-row">
              <div class="credential-page__donut-wrap">
                <svg viewBox="0 0 120 120" class="credential-page__donut" role="img" aria-label="Biểu đồ chi phí theo nhà cung cấp">
                  <defs>
                    <linearGradient
                      v-for="(slice, i) in providerDonut.slices"
                      :id="`cred-cost-grad-${i}`"
                      :key="`grad-${slice.key}`"
                      x1="0%"
                      y1="0%"
                      x2="100%"
                      y2="100%"
                    >
                      <stop offset="0%" :style="{ stopColor: slice.color }" />
                      <stop offset="100%" :style="{ stopColor: sliceGradientDark(slice.color) }" />
                    </linearGradient>
                  </defs>
                  <path
                    v-for="(slice, i) in providerDonut.slices"
                    :key="slice.key"
                    :d="slice.path"
                    :fill="`url(#cred-cost-grad-${i})`"
                    class="credential-page__donut-slice"
                    :class="{ 'credential-page__donut-slice--dim': costHoverSlice && costHoverSlice !== slice.key }"
                    @mouseenter="costHoverSlice = slice.key"
                    @mouseleave="costHoverSlice = null"
                  />
                </svg>
                <div class="credential-page__donut-center">
                  <span class="credential-page__donut-center-label">{{ costHoverInfo ? costHoverInfo.label : 'Tổng' }}</span>
                  <span class="credential-page__donut-center-value">
                    {{ costFormat(costHoverInfo ? costHoverInfo.amount : providerDonut.total) }}
                  </span>
                </div>
              </div>

              <ul class="credential-page__legend">
                <li
                  v-for="slice in providerDonut.slices"
                  :key="slice.key"
                  class="credential-page__legend-item"
                  :class="{ 'credential-page__legend-item--dim': costHoverSlice && costHoverSlice !== slice.key }"
                  @mouseenter="costHoverSlice = slice.key"
                  @mouseleave="costHoverSlice = null"
                >
                  <span class="credential-page__legend-dot" :style="{ background: slice.color }" />
                  <span class="credential-page__legend-label">{{ slice.label }}</span>
                  <span class="credential-page__legend-value">{{ costFormat(slice.amount) }}</span>
                  <span class="credential-page__legend-percent">{{ slice.percent.toFixed(1) }}%</span>
                </li>
              </ul>
            </div>
          </div>

          <div class="credential-page__cost-card">
            <h3 class="credential-page__cost-card-title">Theo loại tài khoản</h3>
            <ul class="credential-page__bars">
              <li v-for="(entry, index) in costSummary.by_account_type" :key="entry.label" class="credential-page__bar-row">
                <span class="credential-page__bar-label">{{ entry.label }}</span>
                <span class="credential-page__bar-track">
                  <span
                    class="credential-page__bar-fill"
                    :style="{
                      width: `${(entry.amount / accountTypeMaxAmount) * 100}%`,
                      background: `linear-gradient(90deg, ${sliceColor(index)} 0%, ${sliceGradientDark(sliceColor(index))} 100%)`,
                    }"
                  />
                </span>
                <span class="credential-page__bar-value">{{ costFormat(entry.amount) }}</span>
              </li>
            </ul>
          </div>
        </div>

        <p v-else class="credential-page__cost-empty">Chưa có tài khoản nào tính chi phí để dự toán.</p>

        <div v-if="costForecast" class="credential-page__cost-card">
          <h3 class="credential-page__cost-card-title">Dự phóng chi phí 12 tháng tới</h3>
          <p class="credential-page__cost-note">{{ costForecast.note }}</p>

          <div class="credential-page__forecast-wrap">
            <svg viewBox="0 0 600 160" class="credential-page__forecast" preserveAspectRatio="none" role="img" aria-label="Biểu đồ dự phóng chi phí 12 tháng tới">
              <defs>
                <linearGradient id="cred-forecast-area" x1="0%" y1="0%" x2="0%" y2="100%">
                  <stop offset="0%" :style="{ stopColor: 'var(--color-secondary)', stopOpacity: 0.35 }" />
                  <stop offset="100%" :style="{ stopColor: 'var(--color-secondary)', stopOpacity: 0 }" />
                </linearGradient>
              </defs>
              <path :d="forecastChart.areaPath" fill="url(#cred-forecast-area)" />
              <path :d="forecastChart.path" fill="none" stroke="var(--color-secondary)" stroke-width="2" />
              <g v-for="(point, i) in forecastChart.points" :key="point.month">
                <circle
                  :cx="point.x"
                  :cy="point.y"
                  :r="point.expiring.length ? 6 : 3"
                  :fill="point.expiring.length ? 'var(--color-danger)' : 'var(--color-secondary)'"
                  class="credential-page__forecast-point"
                  @mouseenter="forecastHoverMonth = i"
                  @mouseleave="forecastHoverMonth = null"
                />
              </g>
            </svg>
            <div class="credential-page__forecast-labels">
              <span v-for="m in costForecast.months" :key="m.month">{{ monthShortLabel(m.month) }}</span>
            </div>
          </div>

          <div v-if="forecastHoverMonth !== null" class="credential-page__forecast-tooltip">
            <strong>{{ monthShortLabel(costForecast.months[forecastHoverMonth].month) }}:</strong>
            {{ costFormat(costForecast.months[forecastHoverMonth].total_vnd) }}
            <span v-if="costForecast.months[forecastHoverMonth].expiring.length">
              — hết hạn: {{ costForecast.months[forecastHoverMonth].expiring.map((e) => e.name).join(', ') }}
            </span>
          </div>
        </div>

        <div v-if="sortedSheetItems.length" class="credential-page__cost-card">
          <h3 class="credential-page__cost-card-title">Bảng chi tiết chi phí</h3>
          <div class="credential-page__sheet-wrap hide-scrollbar">
            <table class="credential-page__table credential-page__sheet-table">
              <thead>
                <tr>
                  <th @click="toggleSheetSort('name')">Tên</th>
                  <th @click="toggleSheetSort('provider')">Nhà cung cấp</th>
                  <th @click="toggleSheetSort('account_type')">Loại</th>
                  <th @click="toggleSheetSort('monthly_cost_vnd')">Chi phí/tháng</th>
                  <th @click="toggleSheetSort('expires_at')">Ngày hết hạn</th>
                  <th @click="toggleSheetSort('status')">Trạng thái</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in sortedSheetItems" :key="row.id">
                  <td>{{ row.name }}</td>
                  <td>{{ row.provider }}</td>
                  <td>{{ row.account_type }}</td>
                  <td>{{ costFormat(row.monthly_cost_vnd) }}</td>
                  <td>{{ row.expires_at ? formatDate(row.expires_at) : '—' }}</td>
                  <td>{{ statusLabel(row.status) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </div>

    <!-- Modal tạo/sửa (skill form-modal) -->
    <Teleport to="body">
      <Transition name="credential-dialog-fade">
        <div v-if="dialogOpen" class="credential-page__dialog" role="presentation" @mousedown.self="closeDialog">
          <div class="credential-page__dialog-panel" role="dialog" aria-modal="true" aria-labelledby="credential-form-title">
            <div class="credential-page__dialog-head">
              <span class="credential-page__dialog-icon" aria-hidden="true">
                <AppIcon name="lock" :size="22" :stroke-width="1.75" />
              </span>
              <h2 id="credential-form-title" class="credential-page__dialog-title">
                {{ dialogMode === 'create' ? 'Thêm tài khoản' : 'Sửa tài khoản' }}
              </h2>
              <button type="button" class="credential-page__dialog-close" aria-label="Đóng" :disabled="formSaving" @click="closeDialog">
                <AppIcon name="close" :size="16" />
              </button>
            </div>

            <div class="credential-page__dialog-tabs" role="tablist" aria-label="Thông tin tài khoản">
              <button
                type="button"
                class="credential-page__dialog-tab"
                :class="{ 'credential-page__dialog-tab--active': dialogTab === 'info' }"
                role="tab"
                :aria-selected="dialogTab === 'info' ? 'true' : 'false'"
                @click="dialogTab = 'info'"
              >
                Thông tin
              </button>
              <button
                type="button"
                class="credential-page__dialog-tab"
                :class="{ 'credential-page__dialog-tab--active': dialogTab === 'advanced' }"
                role="tab"
                :aria-selected="dialogTab === 'advanced' ? 'true' : 'false'"
                @click="dialogTab = 'advanced'"
              >
                Nâng cao
              </button>
            </div>

            <div class="credential-page__dialog-body hide-scrollbar">
              <form v-if="dialogTab === 'info'" class="credential-page__form-info" @submit.prevent="submitForm">
                <div class="credential-page__form-field">
                  <label class="credential-page__label" for="cred-name">Tên tài khoản <span class="credential-page__required">*</span></label>
                  <input id="cred-name" v-model="form.name" type="text" class="credential-page__input" placeholder="Canva Pro - Marketing" />
                </div>
                <div v-if="canChooseDepartment" class="credential-page__form-field">
                  <label class="credential-page__label" for="cred-department">Phòng ban sở hữu</label>
                  <select id="cred-department" v-model="form.department_id" class="credential-page__input">
                    <option value="">{{ dialogMode === 'create' ? 'Phòng ban của tôi' : 'Chưa gán phòng ban' }}</option>
                    <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                  </select>
                </div>
                <div class="credential-page__form-field">
                  <label class="credential-page__label" for="cred-provider">Nhà cung cấp</label>
                  <div class="credential-page__inline-add">
                    <select id="cred-provider" v-model="form.provider_id" class="credential-page__input">
                      <option value="">Chưa chọn</option>
                      <option v-for="p in providers" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                    <button
                      type="button"
                      class="credential-page__inline-add-btn"
                      aria-label="Thêm nhà cung cấp mới"
                      @click="openProviderQuick"
                    >
                      <AppIcon name="plus" :size="14" />
                    </button>
                  </div>
                </div>
                <div class="credential-page__form-field">
                  <label class="credential-page__label" for="cred-type">Loại tài khoản</label>
                  <select id="cred-type" v-model="form.account_type" class="credential-page__input">
                    <option v-for="item in ACCOUNT_TYPES" :key="item.value" :value="item.value">{{ item.label }}</option>
                  </select>
                </div>

                <div class="credential-page__form-field">
                  <label class="credential-page__label" for="cred-username">Tên đăng nhập</label>
                  <input id="cred-username" v-model="form.username" type="text" class="credential-page__input" placeholder="Vd. admin.marketing" />
                </div>
                <div class="credential-page__form-field">
                  <label class="credential-page__label" for="cred-email">Email</label>
                  <input id="cred-email" v-model="form.email" type="email" class="credential-page__input" placeholder="admin@vaschools.edu.vn" />
                </div>
                <div class="credential-page__form-field">
                  <label class="credential-page__label" for="cred-password">Mật khẩu</label>
                  <input
                    id="cred-password"
                    v-model="form.password"
                    type="text"
                    class="credential-page__input"
                    :placeholder="dialogMode === 'edit' ? 'Để trống nếu không đổi' : 'Nhập mật khẩu'"
                  />
                </div>

                <div class="credential-page__form-field credential-page__form-field--span2">
                  <label class="credential-page__check">
                    <input type="checkbox" v-model="form.is_google_login" />
                    <span>Đăng nhập bằng tài khoản Google công ty</span>
                  </label>
                </div>
                <div v-if="form.is_google_login" class="credential-page__form-field">
                  <label class="credential-page__label" for="cred-google-owner">Google này thuộc về ai</label>
                  <select id="cred-google-owner" v-model="form.google_account_owner_id" class="credential-page__input">
                    <option value="">Chưa gắn với người cụ thể</option>
                    <option v-for="u in allUsers" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
                  </select>
                </div>

                <div class="credential-page__form-field credential-page__form-field--span2">
                  <label class="credential-page__label" for="cred-access-url">Link truy cập</label>
                  <input id="cred-access-url" v-model="form.access_url" type="text" class="credential-page__input" placeholder="https://..." />
                </div>

                <template v-if="showInfraFields">
                  <div class="credential-page__form-field">
                    <label class="credential-page__label" for="cred-server">Tên server</label>
                    <input id="cred-server" v-model="form.server_name" type="text" class="credential-page__input" placeholder="Vd. srv-app-01" />
                  </div>
                  <div class="credential-page__form-field">
                    <label class="credential-page__label" for="cred-cluster">Cụm VPS</label>
                    <input id="cred-cluster" v-model="form.vps_cluster" type="text" class="credential-page__input" placeholder="Vd. Cluster HCM-01" />
                  </div>
                  <div class="credential-page__form-field">
                    <label class="credential-page__label" for="cred-domain">Domain</label>
                    <input id="cred-domain" v-model="form.domain" type="text" class="credential-page__input" placeholder="vaschools.edu.vn" />
                  </div>
                  <div class="credential-page__form-field">
                    <label class="credential-page__label" for="cred-database">Tên database</label>
                    <input id="cred-database" v-model="form.database_name" type="text" class="credential-page__input" placeholder="Vd. va_workspace_prod" />
                  </div>
                  <div class="credential-page__form-field">
                    <label class="credential-page__check">
                      <input type="checkbox" v-model="form.is_root_account" />
                      <span>Tài khoản root</span>
                    </label>
                  </div>
                  <div class="credential-page__form-field">
                    <label class="credential-page__check">
                      <input type="checkbox" v-model="form.is_iam_account" />
                      <span>Tài khoản IAM</span>
                    </label>
                  </div>
                </template>

                <div class="credential-page__form-field credential-page__form-field--span3">
                  <label class="credential-page__label" for="cred-notes">Ghi chú</label>
                  <textarea
                    id="cred-notes"
                    v-model="form.notes"
                    class="credential-page__input credential-page__textarea"
                    rows="3"
                    placeholder="Vd. Dùng cho chiến dịch marketing quý 3"
                  />
                </div>
              </form>

              <form v-else class="credential-page__form-info credential-page__form-info--advanced" @submit.prevent="submitForm">
                <div class="credential-page__form-field">
                  <label class="credential-page__label" for="cred-purchased">Ngày mua</label>
                  <input id="cred-purchased" v-model="form.purchased_at" type="date" class="credential-page__input" placeholder="dd/mm/yyyy" />
                  <p class="credential-page__hint">Ngày hết hạn sẽ tự động = ngày mua + 1 tháng.</p>
                </div>
                <div class="credential-page__form-field">
                  <label class="credential-page__label" for="cred-cost">Chi phí / tháng</label>
                  <input id="cred-cost" v-model="form.monthly_cost" type="number" min="0" step="1000" class="credential-page__input" placeholder="Vd. 500000" />
                </div>
                <div class="credential-page__form-field">
                  <label class="credential-page__check">
                    <input type="checkbox" v-model="form.cost_hidden" />
                    <span>Ẩn chi phí trên danh sách</span>
                  </label>
                </div>
              </form>
            </div>

            <div class="credential-page__dialog-actions">
              <button type="button" class="credential-page__dialog-btn credential-page__dialog-btn--ghost" :disabled="formSaving" @click="closeDialog">
                Huỷ
              </button>
              <button type="button" class="credential-page__dialog-btn credential-page__dialog-btn--primary" :disabled="formSaving" @click="submitForm">
                {{ formSaving ? 'Đang lưu…' : 'Lưu' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Modal phụ: tạo nhanh Nhà cung cấp -->
    <Teleport to="body">
      <Transition name="credential-dialog-fade">
        <div
          v-if="providerQuickOpen"
          class="credential-page__dialog credential-page__dialog--quick"
          role="presentation"
          @mousedown.self="closeProviderQuick"
        >
          <div class="credential-page__quick-panel" role="dialog" aria-modal="true" aria-labelledby="provider-quick-title">
            <div class="credential-page__dialog-head">
              <h3 id="provider-quick-title" class="credential-page__dialog-title">Thêm nhà cung cấp</h3>
              <button type="button" class="credential-page__dialog-close" aria-label="Đóng" :disabled="providerQuickSaving" @click="closeProviderQuick">
                <AppIcon name="close" :size="16" />
              </button>
            </div>

            <div class="credential-page__form-field">
              <label class="credential-page__label" for="pq-name">Tên nhà cung cấp <span class="credential-page__required">*</span></label>
              <input id="pq-name" v-model="providerQuickForm.name" type="text" class="credential-page__input" placeholder="Vd. Zoom" />
            </div>
            <div class="credential-page__form-field">
              <label class="credential-page__label" for="pq-category">Nhóm (không bắt buộc)</label>
              <input id="pq-category" v-model="providerQuickForm.category" type="text" class="credential-page__input" placeholder="Vd. Công cụ họp trực tuyến" />
            </div>

            <div class="credential-page__dialog-actions">
              <button type="button" class="credential-page__dialog-btn credential-page__dialog-btn--ghost" :disabled="providerQuickSaving" @click="closeProviderQuick">
                Huỷ
              </button>
              <button type="button" class="credential-page__dialog-btn credential-page__dialog-btn--primary" :disabled="providerQuickSaving" @click="submitProviderQuick">
                {{ providerQuickSaving ? 'Đang lưu…' : 'Lưu' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <ConfirmDialog
      :open="Boolean(deleteTarget)"
      title="Xoá tài khoản?"
      :description="`Tài khoản “${deleteTarget?.name || ''}” sẽ bị xoá khỏi danh sách.`"
      confirm-label="Xoá"
      danger
      :loading="deleting"
      @update:open="(v) => { if (!v) deleteTarget = null; }"
      @confirm="confirmDelete"
    />
  </section>
</template>

<style scoped>
.credential-page {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: 0 var(--space-5) var(--space-3);
  overflow: hidden;
}

.credential-page__title {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.credential-page__title-icon-btn {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  margin-left: 0.125rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.credential-page__title-icon-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-primary);
}

.credential-page__title-icon-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.credential-page__title-icon-btn--add {
  width: 2rem;
  height: 2rem;
  color: var(--color-primary);
}

.credential-page__title-icon-btn--add:hover:not(:disabled) {
  background: color-mix(in srgb, var(--color-primary) 8%, transparent);
  color: var(--color-primary);
}

/* ---------- Tab switcher (Danh sách / Dự toán chi phí) ---------- */
.credential-page__tabs {
  flex-shrink: 0;
  display: flex;
  gap: var(--space-4);
  padding: 0 var(--space-1);
  box-shadow: 0 1px 0 var(--color-border);
}

.credential-page__tab {
  padding: var(--space-3) var(--space-1);
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 600;
  box-shadow: 0 2px 0 transparent;
  cursor: pointer;
}

.credential-page__tab:hover {
  color: var(--color-text);
}

.credential-page__tab--active {
  color: var(--color-primary);
  box-shadow: 0 2px 0 var(--color-primary);
}

.credential-page__spin {
  animation: credential-spin 0.8s linear infinite;
}

@keyframes credential-spin {
  to {
    transform: rotate(360deg);
  }
}

.credential-page__body {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-4);
  overflow: hidden;
}

.credential-page__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.credential-page__toolbar {
  position: relative;
  z-index: 6;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin: var(--space-3) 0;
}

.credential-page__filters {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--space-3);
  width: 100%;
}

.credential-page__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
  width: 100%;
}

.credential-page__field--span2 {
  grid-column: span 2;
}

.credential-page__field--span3 {
  grid-column: span 3;
}

.credential-page__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.credential-page__required {
  color: var(--color-danger);
}

.credential-page__input {
  width: 100%;
  min-width: 0;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  transition: box-shadow 0.15s ease, border-color 0.15s ease;
}

.credential-page__input::placeholder {
  color: var(--color-text-muted);
  opacity: 0.75;
}

.credential-page__input:focus-visible,
.credential-page__input:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 12%, transparent);
}

.credential-page__textarea {
  resize: vertical;
  min-height: 4.5rem;
}

.credential-page__hint {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.credential-page__check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.credential-page__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.credential-page__table-wrap--resizing {
  cursor: col-resize;
  user-select: none;
}

.credential-page__table {
  min-width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: calc(0.875rem * var(--table-zoom, 1));
}

.credential-page__table thead th {
  position: sticky;
  top: 0;
  z-index: 1;
  padding: var(--space-3) var(--space-4);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-weight: 600;
  font-size: 0.75rem;
  letter-spacing: 0.02em;
  text-align: left;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.credential-page__resize {
  position: absolute;
  top: 0;
  right: 0;
  z-index: 2;
  width: 0.5rem;
  height: 100%;
  padding: 0;
  border: none;
  background: transparent;
  cursor: col-resize;
}

.credential-page__resize::after {
  content: '';
  position: absolute;
  top: 25%;
  right: 2px;
  width: 2px;
  height: 50%;
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.credential-page__resize:hover::after {
  background: var(--color-primary);
}

.credential-page__table thead th {
  position: relative;
}

.credential-page__table tbody td {
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  vertical-align: middle;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.credential-page__table tbody tr {
  cursor: pointer;
  transition: background-color 0.12s ease;
}

.credential-page__table tbody tr:hover td {
  background: var(--color-surface-muted);
}

.credential-page__table tbody tr:nth-child(even) td {
  background: color-mix(in srgb, var(--color-surface-muted) 45%, var(--color-surface));
}

.credential-page__table tbody tr:nth-child(even):hover td {
  background: var(--color-surface-muted);
}

.credential-page__cell {
  display: block;
  white-space: nowrap;
}

.credential-page__status-cell {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  white-space: normal;
}

.credential-page__status {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  white-space: nowrap;
}

.credential-page__countdown {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  margin-left: 1rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  white-space: nowrap;
}

.credential-page__countdown--danger {
  color: var(--color-danger);
}

.credential-page__countdown--warning {
  color: var(--color-warning);
}

.credential-page__countdown--urgent {
  font-weight: 700;
  animation: credential-countdown-pulse 1.6s ease-in-out infinite;
}

@keyframes credential-countdown-pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.55;
  }
}

.credential-page__link {
  display: block;
  overflow: hidden;
  color: var(--color-tertiary);
  font-size: 0.8125rem;
  text-decoration: none;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.credential-page__link:hover {
  text-decoration: underline;
}

@media (prefers-reduced-motion: reduce) {
  .credential-page__countdown--urgent {
    animation: none;
  }
}

.credential-page__dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.credential-page__dot--success {
  background: var(--color-success);
}

.credential-page__dot--info {
  background: var(--color-info);
}

.credential-page__dot--warning {
  background: var(--color-warning);
}

.credential-page__dot--danger {
  background: var(--color-danger);
}

.credential-page__empty {
  padding: var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
  white-space: normal;
}

.credential-page__row-menu {
  position: relative;
  text-align: center;
}

.credential-page__menu-trigger {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.credential-page__menu-trigger:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.credential-page__menu {
  position: absolute;
  right: 0;
  top: calc(100% + 0.25rem);
  z-index: 20;
  width: 11rem;
  padding: var(--space-2);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-lg);
}

.credential-page__menu-item {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  text-align: left;
  cursor: pointer;
}

.credential-page__menu-item:hover {
  background: var(--color-surface-muted);
}

.credential-page__menu-item--danger {
  color: var(--color-danger);
}

/* ---------- Tab Dự toán chi phí ---------- */
.credential-page__cost {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: var(--space-5) var(--space-1) var(--space-2);
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

.credential-page__cost-loading,
.credential-page__cost-empty {
  color: var(--color-text-muted);
  font-size: 0.875rem;
  padding: var(--space-5) 0;
}

.credential-page__report-head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-4);
}

.credential-page__report-title {
  margin: 0 0 0.25rem;
  color: var(--color-text);
  font-size: 1.125rem;
  font-weight: 700;
}

.credential-page__report-desc {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.credential-page__report-actions {
  flex-shrink: 0;
  display: flex;
  gap: var(--space-2);
}

.credential-page__export-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  height: 2.25rem;
  padding: 0 var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  text-decoration: none;
  box-shadow: inset 0 0 0 1px var(--color-border);
  transition: background 0.15s ease, box-shadow 0.15s ease;
}

.credential-page__export-btn:hover {
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border-strong);
}

.credential-page__cost-stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-4);
}

.credential-page__cost-stat {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-4) var(--space-5);
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  animation: credential-cost-in 0.28s cubic-bezier(0.22, 1, 0.36, 1) both;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.credential-page__cost-stat:hover {
  transform: translateY(-2px);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-md);
}

.credential-page__cost-stat-deco {
  position: absolute;
  top: 0;
  right: 0;
  width: 5rem;
  height: 5rem;
  pointer-events: none;
}

.credential-page__cost-stat-label {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.credential-page__cost-stat-value {
  color: var(--color-text);
  font-size: 1.5rem;
  font-weight: 700;
  letter-spacing: -0.01em;
}

.credential-page__cost-panels {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-4);
  align-items: start;
}

.credential-page__cost-card {
  padding: var(--space-5);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  animation: credential-cost-in 0.32s cubic-bezier(0.22, 1, 0.36, 1) both;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.credential-page__cost-card:hover {
  transform: translateY(-1px);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-md);
}

.credential-page__cost-card-title {
  margin: 0 0 var(--space-4);
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 700;
}

.credential-page__donut-row {
  display: flex;
  align-items: center;
  gap: var(--space-5);
  flex-wrap: wrap;
}

.credential-page__donut-wrap {
  position: relative;
  flex-shrink: 0;
  width: 9rem;
  height: 9rem;
}

.credential-page__donut {
  width: 100%;
  height: 100%;
  transform: rotate(0deg);
}

.credential-page__donut-slice {
  transition: opacity 0.15s ease, transform 0.15s ease;
  transform-origin: 60px 60px;
  cursor: pointer;
}

.credential-page__donut-slice--dim {
  opacity: 0.35;
}

.credential-page__donut-slice:hover {
  transform: scale(1.03);
}

.credential-page__donut-center {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  gap: 0.125rem;
  pointer-events: none;
}

.credential-page__donut-center-label {
  color: var(--color-text-muted);
  font-size: 0.6875rem;
}

.credential-page__donut-center-value {
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 700;
  max-width: 6.5rem;
  overflow-wrap: break-word;
}

.credential-page__legend {
  flex: 1;
  min-width: 12rem;
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.credential-page__legend-item {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-2);
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: opacity 0.15s ease, background 0.15s ease;
}

.credential-page__legend-item:hover {
  background: var(--color-surface-muted);
}

.credential-page__legend-item--dim {
  opacity: 0.4;
}

.credential-page__legend-dot {
  flex-shrink: 0;
  width: 0.625rem;
  height: 0.625rem;
  border-radius: var(--radius-full);
}

.credential-page__legend-label {
  flex: 1;
  min-width: 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.credential-page__legend-value {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-variant-numeric: tabular-nums;
}

.credential-page__legend-percent {
  flex-shrink: 0;
  width: 3rem;
  text-align: right;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-variant-numeric: tabular-nums;
}

.credential-page__bars {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.credential-page__bar-row {
  display: grid;
  grid-template-columns: 8rem 1fr auto;
  align-items: center;
  gap: var(--space-3);
}

.credential-page__bar-label {
  color: var(--color-text);
  font-size: 0.8125rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.credential-page__bar-track {
  height: 0.625rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  overflow: hidden;
}

.credential-page__bar-fill {
  display: block;
  height: 100%;
  border-radius: var(--radius-full);
  animation: credential-bar-grow 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
}

.credential-page__bar-value {
  min-width: 6rem;
  text-align: right;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-variant-numeric: tabular-nums;
}

.credential-page__cost-note {
  margin: calc(var(--space-4) * -1) 0 var(--space-4);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-style: italic;
}

.credential-page__forecast-wrap {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.credential-page__forecast {
  width: 100%;
  height: 10rem;
}

.credential-page__forecast-point {
  cursor: pointer;
  transform-box: fill-box;
  transform-origin: center;
  transition: transform 0.15s ease;
}

.credential-page__forecast-point:hover {
  transform: scale(1.3);
}

.credential-page__forecast-labels {
  display: flex;
  justify-content: space-between;
  padding: 0 1.5rem;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
}

.credential-page__forecast-tooltip {
  margin-top: var(--space-2);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-size: 0.8125rem;
}

.credential-page__sheet-wrap {
  overflow-x: auto;
  border-radius: var(--radius-md);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.credential-page__sheet-table {
  width: 100%;
  border: none;
  border-radius: 0;
}

.credential-page__sheet-table thead th {
  position: static;
  cursor: pointer;
  user-select: none;
}

.credential-page__sheet-table thead th:hover {
  color: var(--color-text);
}

@keyframes credential-cost-in {
  from {
    opacity: 0;
    transform: translateY(0.5rem);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes credential-bar-grow {
  from {
    width: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .credential-page__cost-stat,
  .credential-page__cost-card,
  .credential-page__bar-fill {
    animation: none;
  }
}

@media (max-width: 900px) {
  .credential-page__cost-stats {
    grid-template-columns: minmax(0, 1fr);
  }

  .credential-page__cost-panels {
    grid-template-columns: minmax(0, 1fr);
  }

  .credential-page__bar-row {
    grid-template-columns: 6rem 1fr auto;
  }
}

/* ---------- Modal tạo/sửa ---------- */
.credential-page__dialog {
  position: fixed;
  inset: 0;
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.credential-page__dialog-panel {
  width: min(56rem, calc(100vw - 2.5rem));
  max-height: calc(100vh - 2.5rem);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: 1.25rem 1.5rem 1.125rem;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.credential-page__dialog-head {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.credential-page__dialog-icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 15%, transparent);
}

.credential-page__dialog-title {
  flex: 1;
  margin: 0;
  color: var(--color-text);
  font-size: 1.25rem;
  font-weight: 700;
}

.credential-page__dialog-close {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.credential-page__dialog-close:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.credential-page__dialog-tabs {
  flex-shrink: 0;
  display: flex;
  gap: var(--space-2);
  box-shadow: 0 1px 0 var(--color-border);
}

.credential-page__dialog-tab {
  padding: var(--space-2) var(--space-3);
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 600;
  box-shadow: 0 2px 0 transparent;
  cursor: pointer;
}

.credential-page__dialog-tab--active {
  color: var(--color-primary);
  box-shadow: 0 2px 0 var(--color-primary);
}

.credential-page__dialog-body {
  min-height: 0;
  overflow: auto;
}

.credential-page__form-info {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-3) var(--space-4);
  align-items: start;
}

.credential-page__form-info--advanced {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.credential-page__form-field {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  min-width: 0;
}

.credential-page__inline-add {
  display: flex;
  align-items: center;
  gap: 0.375rem;
}

.credential-page__inline-add select {
  flex: 1;
  min-width: 0;
}

.credential-page__inline-add-btn {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-primary);
  cursor: pointer;
}

.credential-page__inline-add-btn:hover {
  background: color-mix(in srgb, var(--color-primary) 8%, transparent);
}

/* ---------- Modal phụ: tạo nhanh Nhà cung cấp ---------- */
.credential-page__dialog--quick {
  z-index: 320;
}

.credential-page__quick-panel {
  width: min(24rem, calc(100vw - 2.5rem));
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: 1.25rem 1.5rem;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.credential-page__dialog-actions {
  flex-shrink: 0;
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

.credential-page__dialog-btn {
  padding: 0.625rem 1.25rem;
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.credential-page__dialog-btn--ghost {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.credential-page__dialog-btn--ghost:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.credential-page__dialog-btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.credential-page__dialog-btn--primary:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.credential-page__dialog-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.credential-dialog-fade-enter-active {
  transition: opacity 0.18s ease;
}

.credential-dialog-fade-enter-active .credential-page__dialog-panel {
  transition: opacity 0.18s ease, transform 0.18s cubic-bezier(0.22, 1, 0.36, 1);
}

.credential-dialog-fade-leave-active {
  transition: opacity 0.12s ease;
}

.credential-dialog-fade-enter-from,
.credential-dialog-fade-leave-to {
  opacity: 0;
}

.credential-dialog-fade-enter-from .credential-page__dialog-panel {
  opacity: 0;
  transform: translateY(0.5rem) scale(0.98);
}

@media (max-width: 1024px) {
  .credential-page__filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .credential-page__filters {
    grid-template-columns: minmax(0, 1fr);
  }

  .credential-page__dialog {
    padding: var(--space-4);
    align-items: flex-end;
  }

  .credential-page__dialog-panel {
    width: 100%;
    max-height: min(94vh, calc(100vh - 1.25rem));
    padding: var(--space-4);
  }

  .credential-page__form-info,
  .credential-page__form-info--advanced {
    grid-template-columns: minmax(0, 1fr);
  }

  .credential-page__field--span2,
  .credential-page__field--span3 {
    grid-column: span 1;
  }
}
</style>
