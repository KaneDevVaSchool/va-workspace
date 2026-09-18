<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import { formatDate, formatDateTime, formatRelativeTime } from '@/lib/formatTime';
import { showClientToast } from '@/lib/clientToast';
import { useDragScroll } from '@/composables/useDragScroll';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import {
  REPORT_LIST_COLUMNS,
  REPORT_LIST_COLUMN_KEY,
  REPORT_LIST_FILTERS,
  REPORT_LIST_FILTER_KEY,
  REPORT_LIST_GROUP_KEY,
  REPORT_LIST_GROUP_MODE_KEY,
  REPORT_LIST_WIDTH_KEY,
  REPORT_LIST_ZOOM_KEY,
  REPORT_PERIOD_TYPE_LABELS,
  REPORT_STATUS_LABELS,
  REPORT_TYPE_LABELS,
  REPORT_TYPES,
  loadColumnWidths,
  loadVisibility,
  loadZoom,
  personnelEvaluationScoringRoute,
  saveVisibility,
  saveZoom,
} from '../constants/report.js';

const CELL_PAD_X = 32;
const COL_EXTRA = 24;
const MIN_COL_PX = 72;
const ACTION_COL_PX = 56;
const ACTION_MENU_WIDTH = 176;
const ACTOR_AVATAR_EXTRA = 42;
const SKELETON_ROWS = 7;
const GROUP_TONES = ['primary', 'secondary', 'info', 'gold', 'tertiary'];
let measureCtx = null;
let wrapObserver = null;

const router = useRouter();
const auth = useAuthStore();

const reports = ref([]);
const rawReports = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 20 });
const loading = ref(false);
const selected = ref(null);
const confirmTarget = ref(null);
const actionMenuId = ref(null);
const actionMenuPos = reactive({ top: 0, left: 0 });
const exportingPdfId = ref(null);

const query = ref('');
const reportType = ref('');
const periodType = ref('');
const status = ref('');
const departmentFilter = ref('');
const creatorFilter = ref('');
const perPage = ref(20);
const showCreatePicker = ref(false);

/**
 * Loại tạo được và loại sắp ra mắt tách thành 2 nhóm riêng trong modal —
 * người dùng thấy ngay thứ bấm được mà vẫn biết còn gì đang tới.
 */
const availableTypes = computed(() => REPORT_TYPES.filter((item) => item.available));
const comingSoonTypes = computed(() => REPORT_TYPES.filter((item) => !item.available));

const visibleColumns = reactive(loadVisibility(REPORT_LIST_COLUMN_KEY, REPORT_LIST_COLUMNS));
const visibleFilters = reactive(loadVisibility(REPORT_LIST_FILTER_KEY, REPORT_LIST_FILTERS));
const columnWidths = reactive(loadColumnWidths(REPORT_LIST_WIDTH_KEY));
const tableZoom = ref(loadZoom(REPORT_LIST_ZOOM_KEY));

const tableWrap = ref(null);
const resizing = ref(false);
const collapsedGroups = ref(new Set(loadCollapsedGroups()));
const groupMode = ref(loadGroupMode());

useDragScroll(tableWrap, { isBlocked: () => resizing.value });

const canCreate = computed(() => auth.can('report.manage_department'));
const canScore = computed(() => auth.can('evaluation.manage_department'));

const shownColumns = computed(() => REPORT_LIST_COLUMNS.filter((col) => visibleColumns[col.key]));
const colSpan = computed(() => Math.max(shownColumns.value.length, 1) + 1);

const hasActiveFilters = computed(
  () =>
    Boolean(query.value.trim()) ||
    Boolean(reportType.value) ||
    Boolean(periodType.value) ||
    Boolean(status.value) ||
    Boolean(departmentFilter.value) ||
    Boolean(creatorFilter.value),
);

/**
 * Danh mục phòng ban / người tạo rút từ trang báo cáo đang tải (trước khi
 * lọc tại chỗ) — dùng `rawReports` chứ không phải `reports` đã lọc, để chọn
 * một phòng ban không làm dropdown tự thu hẹp chỉ còn mỗi lựa chọn đó.
 */
const departmentOptions = computed(() => {
  const names = new Set();
  for (const row of rawReports.value) {
    if (row.department_name) names.add(row.department_name);
  }
  return Array.from(names).sort((a, b) => a.localeCompare(b, 'vi'));
});

const creatorOptions = computed(() => {
  const names = new Set();
  for (const row of rawReports.value) {
    if (row.created_by_name) names.add(row.created_by_name);
  }
  return Array.from(names).sort((a, b) => a.localeCompare(b, 'vi'));
});

const hasVisibleFilterFields = computed(() =>
  REPORT_LIST_FILTERS.some((item) => visibleFilters[item.key]),
);

const hiddenActiveFilterLabels = computed(() =>
  REPORT_LIST_FILTERS.filter((item) => !visibleFilters[item.key] && filterHasValue(item.key)).map(
    (item) => item.label,
  ),
);

const tableWidthPx = computed(() => {
  const sum = shownColumns.value.reduce(
    (total, col) => total + (Number(columnWidths[col.key]) || 0),
    0,
  );
  const total = sum + ACTION_COL_PX;
  return total > ACTION_COL_PX ? `${total}px` : '100%';
});

const groupedReports = computed(() => {
  const groups =
    groupMode.value === 'report_type'
      ? buildTypeGroups(reports.value)
      : groupMode.value === 'period'
        ? buildPeriodGroups(reports.value)
        : buildDateGroups(reports.value);

  for (const group of groups) {
    group.typesSummary = groupMode.value === 'report_type' ? '' : groupTypesSummary(group.rows);
  }

  return groups;
});

const reportStats = computed(() => {
  const rows = rawReports.value;
  return {
    total: rows.length,
    saved: rows.filter((row) => row.status === 'saved').length,
    draft: rows.filter((row) => row.status === 'draft').length,
    month: rows.filter((row) => row.period_type === 'month').length,
    quarter: rows.filter((row) => row.period_type === 'quarter').length,
    custom: rows.filter((row) => row.period_type === 'custom').length,
  };
});

function buildPeriodGroups(rows) {
  const order = [
    { key: 'month', label: 'Theo tháng', icon: 'calendar' },
    { key: 'quarter', label: 'Theo quý', icon: 'layers' },
    { key: 'custom', label: 'Khoảng ngày', icon: 'clock' },
  ];
  const byKey = new Map();
  for (const row of rows) {
    const key = row.period_type || 'custom';
    if (!byKey.has(key)) byKey.set(key, []);
    byKey.get(key).push(row);
  }

  const groups = [];
  for (const item of order) {
    const rowsOfType = byKey.get(item.key);
    if (!rowsOfType) continue;
    groups.push({
      key: item.key,
      label: item.label,
      tone: groupTone(item.key),
      icon: item.icon,
      rows: rowsOfType,
    });
    byKey.delete(item.key);
  }
  for (const [key, rowsOfType] of byKey) {
    groups.push({
      key,
      label: REPORT_PERIOD_TYPE_LABELS[key] ?? key,
      tone: groupTone(key),
      icon: 'calendar',
      rows: rowsOfType,
    });
  }

  return groups;
}

function buildDateGroups(rows) {
  const groups = [];
  const indexByKey = new Map();

  for (const row of rows) {
    const key = localDateKey(row.created_at) || 'unknown';
    if (!indexByKey.has(key)) {
      indexByKey.set(key, groups.length);
      groups.push({
        key,
        label: groupDateLabel(key),
        tone: groupTone(key),
        icon: 'calendar',
        rows: [],
      });
    }
    groups[indexByKey.get(key)].rows.push(row);
  }

  return groups;
}

/**
 * Nhóm theo loại báo cáo — thứ tự nhóm theo đúng thứ tự khai báo trong
 * `REPORT_TYPES` (không phải thứ tự xuất hiện trong dữ liệu) để ổn định
 * qua các lần tải trang.
 */
function buildTypeGroups(rows) {
  const byType = new Map();
  for (const row of rows) {
    const key = row.report_type || 'unknown';
    if (!byType.has(key)) byType.set(key, []);
    byType.get(key).push(row);
  }

  const groups = [];
  for (const item of REPORT_TYPES) {
    const rowsOfType = byType.get(item.key);
    if (!rowsOfType) continue;
    groups.push({
      key: item.key,
      label: item.label,
      tone: groupTone(item.key),
      icon: item.icon,
      rows: rowsOfType,
    });
    byType.delete(item.key);
  }
  for (const [key, rowsOfType] of byType) {
    groups.push({
      key,
      label: REPORT_TYPE_LABELS[key] ?? key,
      tone: groupTone(key),
      icon: 'barChart',
      rows: rowsOfType,
    });
  }

  return groups;
}

/**
 * Tóm tắt loại báo cáo trong nhóm để thấy ngay khi nhóm đang thu gọn, ví dụ
 * "3 Đánh giá nhân sự, 1 Báo cáo công việc phòng ban" — không cần mở nhóm ra
 * mới biết bên trong có gì. Chỉ dùng khi đang nhóm theo ngày; nhóm theo loại
 * thì chính nhãn nhóm đã là loại báo cáo rồi nên không cần lặp lại.
 */
function groupTypesSummary(rows) {
  const counts = new Map();
  for (const row of rows) {
    const label = REPORT_TYPE_LABELS[row.report_type] ?? row.report_type;
    counts.set(label, (counts.get(label) || 0) + 1);
  }
  return Array.from(counts, ([label, count]) => `${count} ${label}`).join(', ');
}

function loadGroupMode() {
  try {
    const raw = localStorage.getItem(REPORT_LIST_GROUP_MODE_KEY);
    if (raw === 'report_type' || raw === 'period') return raw;
    return 'date';
  } catch {
    return 'date';
  }
}

function setGroupMode(mode) {
  if (groupMode.value === mode) return;
  closeActionMenu();
  groupMode.value = mode;
  try {
    localStorage.setItem(REPORT_LIST_GROUP_MODE_KEY, mode);
  } catch {
    // Bỏ qua nếu trình duyệt chặn localStorage.
  }
}

function filterHasValue(key) {
  if (key === 'q') return Boolean(query.value.trim());
  if (key === 'report_type') return Boolean(reportType.value);
  if (key === 'period_type') return Boolean(periodType.value);
  if (key === 'status') return Boolean(status.value);
  if (key === 'department_name') return Boolean(departmentFilter.value);
  if (key === 'created_by_name') return Boolean(creatorFilter.value);
  return false;
}

function setQuickFilter(kind, value) {
  closeActionMenu();
  if (kind === 'status') {
    status.value = status.value === value ? '' : value;
    return;
  }
  if (kind === 'period_type') {
    periodType.value = periodType.value === value ? '' : value;
  }
}

function periodText(row) {
  if (!row?.period_from || !row?.period_to) return '—';
  return `${formatDate(row.period_from)} — ${formatDate(row.period_to)}`;
}

function scopeText(row) {
  const count = Number(row?.filter_user_count) || 0;
  return count === 0 ? 'Toàn phòng ban' : `${count} nhân sự`;
}

function viewerText(row) {
  const count = Number(row?.viewer_count) || 0;
  return count === 0 ? 'Chưa chia sẻ' : `${count} người`;
}

function criterionText(row) {
  const count = Number(row?.criterion_count) || 0;
  return count === 0 ? 'Tất cả tiêu chí' : `${count} tiêu chí`;
}

function revisionText(row) {
  const revision = String(row?.revision || '').trim();
  if (!revision) return '';
  if (row?.revision_kind === 'appendix') return `${revision} · Phụ lục`;
  return revision;
}

/**
 * Diễn giải ý nghĩa "khoá kỳ": báo cáo đã lưu thì kỳ báo cáo đó bị khoá,
 * không ai tạo báo cáo mới đè lên khoảng ngày này được nữa; bản nháp thì
 * kỳ vẫn còn mở, có thể sửa hoặc xoá bình thường.
 */
function lockText(row) {
  return row?.status === 'saved' ? 'Đã khoá kỳ báo cáo' : 'Còn sửa được';
}

function cellText(row, key) {
  if (key === 'period_type') return REPORT_PERIOD_TYPE_LABELS[row.period_type] ?? row.period_type ?? '—';
  if (key === 'report_type') return REPORT_TYPE_LABELS[row.report_type] ?? row.report_type;
  if (key === 'status') return REPORT_STATUS_LABELS[row.status] ?? row.status;
  if (key === 'scope') return scopeText(row);
  if (key === 'viewer_count') return viewerText(row);
  if (key === 'revision') return revisionText(row) || '1.0';
  if (key === 'created_by') return row.created_by_name || '—';
  if (key === 'updated_by') return row.updated_by_name || '—';
  if (key === 'updated_at') return formatDateTime(row.updated_at) || '—';
  return row[key] ?? '—';
}

function creatorUser(row) {
  if (!row?.created_by_name && !row?.created_by) return null;
  return {
    id: row.created_by,
    name: row.created_by_name || '—',
    email: row.created_by_email || null,
    avatar_url: row.created_by_avatar_url || null,
    department: row.created_by_department ? { name: row.created_by_department } : null,
  };
}

function localDateKey(iso) {
  if (!iso) return '';
  const date = iso instanceof Date ? iso : new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function groupDateLabel(key) {
  if (!key || key === 'unknown') return 'Chưa có ngày tạo';

  const today = localDateKey(new Date());
  const yesterdayDate = new Date();
  yesterdayDate.setDate(yesterdayDate.getDate() - 1);
  const yesterday = localDateKey(yesterdayDate);

  if (key === today) return 'Hôm nay';
  if (key === yesterday) return 'Hôm qua';

  const [year, month, day] = key.split('-').map(Number);
  const date = new Date(year, month - 1, day);
  const weekday = date.toLocaleDateString('vi-VN', { weekday: 'long' });
  const formatted = date.toLocaleDateString('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
  const prettyWeekday = weekday ? weekday.charAt(0).toUpperCase() + weekday.slice(1) : '';
  return prettyWeekday ? `${prettyWeekday}, ${formatted}` : formatted;
}

function groupTone(key) {
  let hash = 0;
  for (let index = 0; index < key.length; index += 1) {
    hash = (hash + key.charCodeAt(index)) % GROUP_TONES.length;
  }
  return GROUP_TONES[hash];
}

function loadCollapsedGroups() {
  try {
    const raw = localStorage.getItem(REPORT_LIST_GROUP_KEY);
    const parsed = raw ? JSON.parse(raw) : [];
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

function saveCollapsedGroups() {
  try {
    localStorage.setItem(REPORT_LIST_GROUP_KEY, JSON.stringify(Array.from(collapsedGroups.value)));
  } catch {
    // Bỏ qua nếu trình duyệt chặn localStorage.
  }
}

function isGroupCollapsed(key) {
  return collapsedGroups.value.has(key);
}

function toggleGroup(key) {
  closeActionMenu();
  const next = new Set(collapsedGroups.value);
  if (next.has(key)) next.delete(key);
  else next.add(key);
  collapsedGroups.value = next;
  saveCollapsedGroups();
}

const allGroupsCollapsed = computed(
  () => groupedReports.value.length > 0 && groupedReports.value.every((group) => isGroupCollapsed(group.key)),
);

function collapseAllGroups() {
  closeActionMenu();
  collapsedGroups.value = new Set(groupedReports.value.map((group) => group.key));
  saveCollapsedGroups();
}

function expandAllGroups() {
  closeActionMenu();
  collapsedGroups.value = new Set();
  saveCollapsedGroups();
}

function viewerNamesText(row) {
  const names = Array.isArray(row?.viewer_names) ? row.viewer_names.filter(Boolean) : [];
  if (names.length === 0) return 'Chưa chia sẻ';
  return names.join(', ');
}

async function loadReports(page = 1) {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/report', {
      params: {
        q: query.value.trim() || undefined,
        report_type: reportType.value || undefined,
        page,
        per_page: perPage.value,
      },
    });
    rawReports.value = data.reports ?? [];
    let rows = rawReports.value;
    // Tình trạng / phòng ban / người tạo lọc tại chỗ trên trang đã tải —
    // cùng cách status đang làm, không cần thêm tham số máy chủ.
    if (status.value) {
      rows = rows.filter((row) => row.status === status.value);
    }
    if (periodType.value) {
      rows = rows.filter((row) => row.period_type === periodType.value);
    }
    if (departmentFilter.value) {
      rows = rows.filter((row) => row.department_name === departmentFilter.value);
    }
    if (creatorFilter.value) {
      rows = rows.filter((row) => row.created_by_name === creatorFilter.value);
    }
    reports.value = rows;
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: perPage.value };

    if (selected.value && !rows.some((row) => row.id === selected.value.id)) {
      selected.value = null;
    }
    nextTick(fitColumnsToContent);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không tải được danh sách báo cáo.');
  } finally {
    loading.value = false;
  }
}

function goPage(page) {
  if (page < 1 || page > meta.value.last_page || page === meta.value.current_page) {
    return;
  }
  loadReports(page);
}

function clearFilters() {
  query.value = '';
  reportType.value = '';
  periodType.value = '';
  status.value = '';
  departmentFilter.value = '';
  creatorFilter.value = '';
  loadReports(1);
}

async function removeReport() {
  if (!confirmTarget.value || !canDeleteReport(confirmTarget.value)) return;

  try {
    await window.axios.delete(`/api/report/${confirmTarget.value.id}`);
    if (selected.value?.id === confirmTarget.value.id) selected.value = null;
    confirmTarget.value = null;
    showClientToast('success', 'Đã xoá báo cáo.');
    await loadReports(meta.value.current_page);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không xoá được báo cáo.');
  }
}

function isSavedReport(row) {
  return row?.status === 'saved';
}

function canScoreReport(row) {
  return (
    canScore.value &&
    row?.report_type === 'personnel_evaluation' &&
    Boolean(row.period_from) &&
    Boolean(row.period_to)
  );
}

function canDeleteReport(row) {
  return canCreate.value && !isSavedReport(row);
}

function canExportPdfReport(row) {
  return canScoreReport(row) && isSavedReport(row);
}

function openScoring(row) {
  if (!canScoreReport(row)) return;
  closeActionMenu();
  router.push(personnelEvaluationScoringRoute(row.period_from, row.period_to, row.id));
}

function periodStamp(row) {
  const from = typeof row?.period_from === 'string' ? row.period_from.slice(0, 10) : '';
  const to = typeof row?.period_to === 'string' ? row.period_to.slice(0, 10) : '';
  return from && to ? `${from}_${to}` : '';
}

async function exportReportPdf(row) {
  if (!canExportPdfReport(row) || exportingPdfId.value) return;
  closeActionMenu();
  exportingPdfId.value = row.id;
  try {
    const stamp = periodStamp(row);
    const response = await window.axios.get('/api/evaluation/summary/export-pdf', {
      params: {
        from: String(row.period_from).slice(0, 10),
        to: String(row.period_to).slice(0, 10),
        report: row.id,
      },
      responseType: 'blob',
      timeout: 180000,
    });
    const blob = response.data;
    if (blob.type && blob.type.includes('json')) {
      const json = JSON.parse(await blob.text());
      throw new Error(json.message || 'Không xuất được file PDF.');
    }

    const disposition = response.headers['content-disposition'] || '';
    const utfMatch = disposition.match(/filename\*=UTF-8''([^;]+)/i);
    const plainMatch = disposition.match(/filename="?([^"]+)"?/i);
    const filename = decodeURIComponent(
      utfMatch?.[1] || plainMatch?.[1] || `Danh_gia_nhan_su_${stamp || row.id}.pdf`,
    );

    const objectUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = objectUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(objectUrl);
    showClientToast('success', 'Đã tải file PDF.');
  } catch (err) {
    let message = err?.message;
    if (err?.response?.data instanceof Blob) {
      try {
        const json = JSON.parse(await err.response.data.text());
        message = json.message || Object.values(json.errors || {})[0]?.[0];
      } catch {
        message = 'Không xuất được file PDF.';
      }
    } else {
      message = err?.response?.data?.message || message;
    }
    showClientToast('error', message || 'Không xuất được file PDF.');
  } finally {
    exportingPdfId.value = null;
  }
}

function inspectRow(row) {
  closeActionMenu();
  selected.value = row;
}

function chooseReportType(item) {
  if (!item.available || !item.routeName) return;
  showCreatePicker.value = false;
  router.push({ name: item.routeName });
}

function toggleActionMenu(row, event) {
  event.stopPropagation();
  if (actionMenuId.value === row.id) {
    actionMenuId.value = null;
    return;
  }
  selected.value = row;
  const rect = event.currentTarget.getBoundingClientRect();
  const spaceBelow = window.innerHeight - rect.bottom;
  const itemCount = 1 + (canScoreReport(row) ? 1 : 0) + (canCreate.value ? 1 : 0);
  const menuHeight = 12 + itemCount * 36;
  actionMenuPos.top =
    spaceBelow < menuHeight ? Math.max(8, rect.top - menuHeight) : rect.bottom + 4;
  actionMenuPos.left = Math.max(8, rect.right - ACTION_MENU_WIDTH);
  actionMenuId.value = row.id;
}

function closeActionMenu() {
  actionMenuId.value = null;
}

function handleDocumentClick(event) {
  if (!actionMenuId.value) return;
  if (event.target?.closest?.('.report-list__actions, .report-list__action-menu')) return;
  closeActionMenu();
}

/* ---------- Cột: đo, kéo, bật/tắt ---------- */

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
  const table = tableWrap.value?.querySelector('.report-list__table');
  return {
    header: fontOf(table?.querySelector('thead th'), '600 12px "Be Vietnam Pro", sans-serif'),
    cell: fontOf(table?.querySelector('tbody td'), '400 14px "Be Vietnam Pro", sans-serif'),
  };
}

function columnContentWidth(key, fonts) {
  const label = REPORT_LIST_COLUMNS.find((col) => col.key === key)?.label ?? '';
  let maxW = measureText(label, fonts.header);
  for (const row of reports.value) {
    maxW = Math.max(maxW, measureText(cellText(row, key), fonts.cell));
    if (key === 'created_by' && row.created_at) {
      const meta = `${formatDateTime(row.created_at)} · ${formatRelativeTime(row.created_at)}`;
      maxW = Math.max(maxW, measureText(meta, fonts.cell));
    }
    if (key === 'title') {
      maxW = Math.max(maxW, measureText(periodText(row), fonts.cell));
    }
  }
  const extra = key === 'created_by' ? ACTOR_AVATAR_EXTRA : 0;
  return Math.max(MIN_COL_PX, Math.ceil(maxW + CELL_PAD_X + COL_EXTRA + extra));
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

  const next = distributeExtraWidth(measured, keys, wrap.clientWidth - ACTION_COL_PX);
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
    const remaining = REPORT_LIST_COLUMNS.filter(
      (col) => visibleColumns[col.key] && col.key !== key,
    ).length;
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

function handleDocumentKeydown(event) {
  if (event.key !== 'Escape') return;
  if (actionMenuId.value) {
    actionMenuId.value = null;
    return;
  }
  if (confirmTarget.value) {
    confirmTarget.value = null;
    return;
  }
  if (showCreatePicker.value) {
    showCreatePicker.value = false;
    return;
  }
  if (selected.value) {
    selected.value = null;
  }
}

watch(visibleColumns, (value) => saveVisibility(REPORT_LIST_COLUMN_KEY, value), { deep: true });
watch(visibleFilters, (value) => saveVisibility(REPORT_LIST_FILTER_KEY, value), { deep: true });
watch(columnWidths, (value) => saveVisibility(REPORT_LIST_WIDTH_KEY, value), { deep: true });
watch(tableZoom, (value) => {
  saveZoom(REPORT_LIST_ZOOM_KEY, value);
  nextTick(fitColumnsToContent);
});
watch(selected, () => nextTick(fitColumnsToContent));
watch(shownColumns, () => nextTick(fitColumnsToContent));
watch([reportType, periodType, status, departmentFilter, creatorFilter, perPage], () => loadReports(1));

onMounted(() => {
  document.addEventListener('keydown', handleDocumentKeydown);
  document.addEventListener('mousedown', handleDocumentClick);
  loadReports(1);
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
  document.removeEventListener('keydown', handleDocumentKeydown);
  document.removeEventListener('mousedown', handleDocumentClick);
  wrapObserver?.disconnect();
});
</script>

<template>
  <section class="report-list">
    <PageHeader
      title="Báo cáo"
      icon="barChart"
      description="Tìm nhanh theo kỳ tháng, quý hoặc khoảng ngày. Bấm một dòng để xem đủ thông tin bên phải."
      :primary-action="canCreate ? { label: 'Tạo báo cáo', icon: 'plus', onClick: () => (showCreatePicker = true) } : null"
    >
      <template #actions>
        <button
          type="button"
          class="report-list__header-btn"
          :disabled="loading"
          @click="loadReports(meta.current_page)"
        >
          <AppIcon name="refresh" :size="16" :class="{ 'report-list__spin': loading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

    <div class="report-list__body">
      <div class="report-list__main">
        <div v-if="hasVisibleFilterFields" class="report-list__toolbar">
          <div class="report-list__filters">
            <div v-if="visibleFilters.q" class="report-list__field">
              <label class="report-list__label" for="report-q">Tìm kiếm</label>
              <input
                id="report-q"
                v-model="query"
                type="search"
                class="report-list__input"
                placeholder="Tên báo cáo, đánh giá nhân sự…"
                @keydown.enter="loadReports(1)"
              />
            </div>

            <div v-if="visibleFilters.report_type" class="report-list__field">
              <label class="report-list__label" for="report-type">Loại báo cáo</label>
              <select id="report-type" v-model="reportType" class="report-list__input">
                <option value="">Tất cả loại báo cáo</option>
                <option
                  v-for="(label, value) in REPORT_TYPE_LABELS"
                  :key="value"
                  :value="value"
                >
                  {{ label }}
                </option>
              </select>
            </div>

            <div v-if="visibleFilters.period_type" class="report-list__field">
              <label class="report-list__label" for="report-period-type">Kiểu kỳ</label>
              <select id="report-period-type" v-model="periodType" class="report-list__input">
                <option value="">Tất cả kiểu kỳ</option>
                <option
                  v-for="(label, value) in REPORT_PERIOD_TYPE_LABELS"
                  :key="value"
                  :value="value"
                >
                  {{ label }}
                </option>
              </select>
            </div>

            <div v-if="visibleFilters.status" class="report-list__field">
              <label class="report-list__label" for="report-status">Tình trạng</label>
              <select id="report-status" v-model="status" class="report-list__input">
                <option value="">Tất cả tình trạng</option>
                <option
                  v-for="(label, value) in REPORT_STATUS_LABELS"
                  :key="value"
                  :value="value"
                >
                  {{ label }}
                </option>
              </select>
            </div>

            <div v-if="visibleFilters.department_name" class="report-list__field">
              <label class="report-list__label" for="report-department">Phòng ban</label>
              <select id="report-department" v-model="departmentFilter" class="report-list__input">
                <option value="">Tất cả phòng ban</option>
                <option v-for="name in departmentOptions" :key="name" :value="name">
                  {{ name }}
                </option>
              </select>
            </div>

            <div v-if="visibleFilters.created_by_name" class="report-list__field">
              <label class="report-list__label" for="report-creator">Người tạo</label>
              <select id="report-creator" v-model="creatorFilter" class="report-list__input">
                <option value="">Tất cả người tạo</option>
                <option v-for="name in creatorOptions" :key="name" :value="name">
                  {{ name }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <div v-if="!loading && rawReports.length" class="report-list__stats" role="group" aria-label="Lọc nhanh">
          <button
            type="button"
            class="report-list__stat"
            :class="{ 'report-list__stat--on': status === 'saved' }"
            @click="setQuickFilter('status', 'saved')"
          >
            {{ reportStats.saved }} đã lưu
          </button>
          <button
            type="button"
            class="report-list__stat"
            :class="{ 'report-list__stat--on': status === 'draft' }"
            @click="setQuickFilter('status', 'draft')"
          >
            {{ reportStats.draft }} bản nháp
          </button>
          <button
            type="button"
            class="report-list__stat"
            :class="{ 'report-list__stat--on': periodType === 'month' }"
            @click="setQuickFilter('period_type', 'month')"
          >
            {{ reportStats.month }} theo tháng
          </button>
          <button
            type="button"
            class="report-list__stat"
            :class="{ 'report-list__stat--on': periodType === 'quarter' }"
            @click="setQuickFilter('period_type', 'quarter')"
          >
            {{ reportStats.quarter }} theo quý
          </button>
          <button
            type="button"
            class="report-list__stat"
            :class="{ 'report-list__stat--on': periodType === 'custom' }"
            @click="setQuickFilter('period_type', 'custom')"
          >
            {{ reportStats.custom }} khoảng ngày
          </button>
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
          @search="loadReports(1)"
          @clear-filters="clearFilters"
          @update:page="goPage"
          @update:per-page="perPage = $event"
          @update:zoom="tableZoom = $event"
        >
          <template #filters>
            <label v-for="item in REPORT_LIST_FILTERS" :key="item.key" class="report-list__check">
              <input
                type="checkbox"
                :checked="visibleFilters[item.key]"
                @change="onFilterToggle(item.key, $event.target.checked)"
              />
              <span>{{ item.label }}</span>
            </label>
          </template>
          <template #settings>
            <label v-for="col in REPORT_LIST_COLUMNS" :key="col.key" class="report-list__check">
              <input
                type="checkbox"
                :checked="visibleColumns[col.key]"
                @change="onColumnToggle(col.key, $event.target.checked)"
              />
              <span>{{ col.label }}</span>
            </label>
          </template>
          <template v-if="groupedReports.length" #actions>
            <div class="report-list__group-mode" role="group" aria-label="Cách nhóm báo cáo">
              <button
                type="button"
                class="report-list__group-mode-btn"
                :class="{ 'report-list__group-mode-btn--active': groupMode === 'date' }"
                @click="setGroupMode('date')"
              >
                Theo ngày tạo
              </button>
              <button
                type="button"
                class="report-list__group-mode-btn"
                :class="{ 'report-list__group-mode-btn--active': groupMode === 'report_type' }"
                @click="setGroupMode('report_type')"
              >
                Theo loại báo cáo
              </button>
              <button
                type="button"
                class="report-list__group-mode-btn"
                :class="{ 'report-list__group-mode-btn--active': groupMode === 'period' }"
                @click="setGroupMode('period')"
              >
                Theo kỳ
              </button>
            </div>
            <button
              type="button"
              class="report-list__header-btn"
              @click="allGroupsCollapsed ? expandAllGroups() : collapseAllGroups()"
            >
              <AppIcon :name="allGroupsCollapsed ? 'chevronsDown' : 'chevronsUp'" :size="16" />
              {{ allGroupsCollapsed ? 'Mở tất cả nhóm' : 'Thu tất cả nhóm' }}
            </button>
          </template>
        </TablePagesBar>

        <p v-if="hiddenActiveFilterLabels.length" class="report-list__note">
          Đang lọc thêm theo: {{ hiddenActiveFilterLabels.join(', ') }} (bộ lọc đang ẩn).
        </p>

        <div
          ref="tableWrap"
          class="report-list__table-wrap hide-scrollbar"
          :class="{ 'report-list__table-wrap--resizing': resizing }"
          :style="{ '--table-zoom': tableZoom }"
          @scroll="closeActionMenu"
        >
          <table class="report-list__table" :style="{ width: tableWidthPx }">
            <colgroup>
              <col
                v-for="col in shownColumns"
                :key="col.key"
                :style="{ width: colWidthStyle(col.key) }"
              />
              <col :style="{ width: `${ACTION_COL_PX}px` }" />
            </colgroup>
            <thead>
              <tr>
                <th v-for="col in shownColumns" :key="col.key">
                  <span>{{ col.label }}</span>
                  <button
                    type="button"
                    class="report-list__resize"
                    aria-label="Kéo để đổi độ rộng cột"
                    @click.stop
                    @mousedown.stop.prevent="startResize($event, col.key)"
                  />
                </th>
                <th class="report-list__th-action">
                  <span class="report-list__sr">Thao tác</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <template v-if="loading">
                <tr v-for="n in SKELETON_ROWS" :key="`skel-${n}`" class="report-list__skel-row">
                  <td v-for="col in shownColumns" :key="col.key">
                    <span class="report-list__skel" :style="{ width: `${48 + ((n + col.key.length) % 5) * 8}%` }" />
                  </td>
                  <td class="report-list__td-action">
                    <span class="report-list__skel report-list__skel--icon" />
                  </td>
                </tr>
              </template>
              <tr v-else-if="reports.length === 0">
                <td :colspan="colSpan" class="report-list__empty">
                  <span class="report-list__empty-icon" aria-hidden="true">
                    <AppIcon name="barChart" :size="28" />
                  </span>
                  <strong v-if="hasActiveFilters">Không khớp bộ lọc</strong>
                  <strong v-else-if="canCreate">Chưa có báo cáo nào</strong>
                  <strong v-else>Chưa được chia sẻ báo cáo</strong>
                  <span v-if="hasActiveFilters">
                    Không có báo cáo nào khớp với từ khoá hoặc bộ lọc đang chọn. Thử xoá lọc để xem lại toàn bộ.
                  </span>
                  <span v-else-if="canCreate">
                    Phòng ban chưa có báo cáo nào. Bấm “Tạo báo cáo” để lập báo cáo theo tháng, quý hoặc khoảng ngày.
                  </span>
                  <span v-else>Chưa có báo cáo nào được chia sẻ với bạn.</span>
                  <button
                    v-if="canCreate && !hasActiveFilters"
                    type="button"
                    class="report-list__empty-btn"
                    @click="showCreatePicker = true"
                  >
                    Tạo báo cáo
                  </button>
                  <button
                    v-else-if="hasActiveFilters"
                    type="button"
                    class="report-list__empty-btn"
                    @click="clearFilters"
                  >
                    Xoá bộ lọc
                  </button>
                </td>
              </tr>
              <template v-for="group in groupedReports" v-else :key="group.key">
                <tr
                  class="report-list__group-row"
                  :class="`report-list__group-row--${group.tone}`"
                  @click="toggleGroup(group.key)"
                >
                  <td :colspan="colSpan">
                    <span class="report-list__group-toggle">
                      <span class="report-list__group-head">
                        <AppIcon
                          name="chevronRight"
                          :size="14"
                          class="report-list__group-chevron"
                          :class="{ 'report-list__group-chevron--open': !isGroupCollapsed(group.key) }"
                        />
                        <AppIcon :name="group.icon || 'calendar'" :size="14" />
                        <span class="report-list__group-label">{{ group.label }}</span>
                        <span
                          v-if="isGroupCollapsed(group.key) && group.typesSummary"
                          class="report-list__group-types"
                        >
                          {{ group.typesSummary }}
                        </span>
                      </span>
                      <span class="report-list__group-count">
                        {{ group.rows.length }} báo cáo
                      </span>
                    </span>
                  </td>
                </tr>
                <tr
                  v-for="row in group.rows"
                  v-show="!isGroupCollapsed(group.key)"
                  :key="row.id"
                  class="report-list__data-row"
                  :class="{ 'report-list__row--active': selected?.id === row.id }"
                  @click="selected = row"
                >
                  <td v-for="col in shownColumns" :key="col.key">
                    <span v-if="col.key === 'title'" class="report-list__title-cell">
                      <button
                        v-if="canScoreReport(row)"
                        type="button"
                        class="report-list__title-btn"
                        @click.stop="openScoring(row)"
                      >
                        {{ row.title || '—' }}
                      </button>
                      <span v-else class="report-list__title">{{ row.title || '—' }}</span>
                      <span class="report-list__muted">{{ periodText(row) }}</span>
                    </span>
                    <span v-else-if="col.key === 'report_type'" class="report-list__cell">
                      {{ cellText(row, 'report_type') }}
                    </span>
                    <span v-else-if="col.key === 'status'" class="report-list__status-cell">
                      <span class="report-list__status-line">
                        <span
                          class="report-list__dot"
                          :class="row.status === 'saved' ? 'report-list__dot--ok' : 'report-list__dot--draft'"
                        />
                        {{ REPORT_STATUS_LABELS[row.status] ?? row.status }}
                      </span>
                      <span class="report-list__muted">{{ lockText(row) }}</span>
                    </span>
                    <span v-else-if="col.key === 'created_by'" class="report-list__person">
                      <UserAvatarTip :user="creatorUser(row)" label="Người tạo" />
                      <span class="report-list__person-text">
                        <span>{{ row.created_by_name || '—' }}</span>
                        <span v-if="row.created_at" class="report-list__muted">
                          {{ formatDateTime(row.created_at) }} · {{ formatRelativeTime(row.created_at) }}
                        </span>
                      </span>
                    </span>
                    <span v-else class="report-list__cell">{{ cellText(row, col.key) }}</span>
                  </td>
                  <td class="report-list__td-action" @click.stop>
                    <span class="report-list__actions">
                      <button
                        type="button"
                        class="report-list__action-trigger"
                        :class="{ 'report-list__action-trigger--open': actionMenuId === row.id }"
                        aria-haspopup="menu"
                        :aria-expanded="actionMenuId === row.id"
                        aria-label="Thao tác"
                        @click="toggleActionMenu(row, $event)"
                      >
                        <AppIcon name="moreVertical" :size="16" />
                      </button>
                      <Teleport to="body">
                        <div
                          v-if="actionMenuId === row.id"
                          class="report-list__action-menu"
                          role="menu"
                          aria-label="Thao tác báo cáo"
                          :style="{ top: `${actionMenuPos.top}px`, left: `${actionMenuPos.left}px` }"
                        >
                          <button
                            type="button"
                            role="menuitem"
                            class="report-list__action-item"
                            @click="inspectRow(row)"
                          >
                            <AppIcon name="eye" :size="15" />
                            <span>Xem chi tiết</span>
                          </button>
                          <button
                            v-if="canScoreReport(row)"
                            type="button"
                            role="menuitem"
                            class="report-list__action-item"
                            @click="openScoring(row)"
                          >
                            <AppIcon :name="isSavedReport(row) ? 'layoutGrid' : 'clipboardCheck'" :size="15" />
                            <span>{{ isSavedReport(row) ? 'Chi tiết' : 'Chấm điểm' }}</span>
                          </button>
                          <button
                            v-if="canExportPdfReport(row)"
                            type="button"
                            role="menuitem"
                            class="report-list__action-item"
                            :disabled="exportingPdfId === row.id"
                            @click="exportReportPdf(row)"
                          >
                            <AppIcon name="fileText" :size="15" />
                            <span>Xuất PDF</span>
                          </button>
                          <button
                            v-if="canDeleteReport(row)"
                            type="button"
                            role="menuitem"
                            class="report-list__action-item report-list__action-item--danger"
                            @click="closeActionMenu(); confirmTarget = row"
                          >
                            <AppIcon name="trash" :size="15" />
                            <span>Xoá</span>
                          </button>
                        </div>
                      </Teleport>
                    </span>
                  </td>
                </tr>
              </template>
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

      <aside v-if="selected" class="report-list__side" aria-label="Chi tiết báo cáo">
        <div class="report-list__side-head">
          <h2 class="report-list__side-title">Chi tiết báo cáo</h2>
          <button
            type="button"
            class="report-list__icon-btn"
            aria-label="Đóng"
            @click="selected = null"
          >
            <AppIcon name="close" :size="16" />
          </button>
        </div>

        <div
          class="report-list__side-lead"
          :class="selected.status === 'saved' ? 'report-list__side-lead--ok' : 'report-list__side-lead--draft'"
        >
          <span
            class="report-list__dot"
            :class="selected.status === 'saved' ? 'report-list__dot--ok' : 'report-list__dot--draft'"
          />
          <div>
            <span class="report-list__side-lead-kicker">
              {{ REPORT_TYPE_LABELS[selected.report_type] ?? selected.report_type }}
              ·
              {{ REPORT_STATUS_LABELS[selected.status] ?? selected.status }}
            </span>
            <p class="report-list__side-lead-title">{{ selected.title }}</p>
            <p class="report-list__side-lead-meta">
              {{ periodText(selected) }}{{ revisionText(selected) ? ` · ${revisionText(selected)}` : '' }}
            </p>
          </div>
        </div>

        <div class="report-list__rows">
          <div class="report-list__row">
            <span class="report-list__row-label">Mã báo cáo</span>
            <span class="report-list__row-value">{{ selected.id }}</span>
          </div>
          <div class="report-list__row">
            <span class="report-list__row-label">Loại báo cáo</span>
            <span class="report-list__row-value">
              {{ REPORT_TYPE_LABELS[selected.report_type] ?? selected.report_type }}
            </span>
          </div>
          <div class="report-list__row">
            <span class="report-list__row-label">Kiểu kỳ</span>
            <span class="report-list__row-value">
              {{ REPORT_PERIOD_TYPE_LABELS[selected.period_type] ?? selected.period_type ?? '—' }}
            </span>
          </div>
          <div class="report-list__row">
            <span class="report-list__row-label">Kỳ báo cáo</span>
            <span class="report-list__row-value">{{ periodText(selected) }}</span>
          </div>
          <div class="report-list__row">
            <span class="report-list__row-label">Từ ngày</span>
            <span class="report-list__row-value">{{ formatDate(selected.period_from) || '—' }}</span>
          </div>
          <div class="report-list__row">
            <span class="report-list__row-label">Đến ngày</span>
            <span class="report-list__row-value">{{ formatDate(selected.period_to) || '—' }}</span>
          </div>
          <div class="report-list__row">
            <span class="report-list__row-label">Phiên bản</span>
            <span class="report-list__row-value">{{ revisionText(selected) || '1.0' }}</span>
          </div>
          <div class="report-list__row">
            <span class="report-list__row-label">Phòng ban</span>
            <span class="report-list__row-value">{{ selected.department_name ?? '—' }}</span>
          </div>
          <div class="report-list__row">
            <span class="report-list__row-label">Phạm vi nhân sự</span>
            <span class="report-list__row-value">{{ scopeText(selected) }}</span>
          </div>
          <div class="report-list__row">
            <span class="report-list__row-label">Người được xem</span>
            <span class="report-list__row-value">{{ viewerNamesText(selected) }}</span>
          </div>
          <div class="report-list__row">
            <span class="report-list__row-label">Số cột</span>
            <span class="report-list__row-value">
              {{ selected.column_count ? `${selected.column_count} cột` : '—' }}
            </span>
          </div>
          <div class="report-list__row">
            <span class="report-list__row-label">Tiêu chí hành vi</span>
            <span class="report-list__row-value">{{ criterionText(selected) }}</span>
          </div>
          <div class="report-list__row">
            <span class="report-list__row-label">Tình trạng</span>
            <span class="report-list__row-value">
              {{ REPORT_STATUS_LABELS[selected.status] }} · {{ lockText(selected) }}
            </span>
          </div>
          <div class="report-list__row">
            <span class="report-list__row-label">Người tạo</span>
            <span class="report-list__row-value">{{ selected.created_by_name ?? '—' }}</span>
          </div>
          <div v-if="selected.created_by_email" class="report-list__row">
            <span class="report-list__row-label">Email người tạo</span>
            <span class="report-list__row-value">{{ selected.created_by_email }}</span>
          </div>
          <div class="report-list__row">
            <span class="report-list__row-label">Tạo lúc</span>
            <span class="report-list__row-value">{{ formatDateTime(selected.created_at) }}</span>
          </div>
          <div class="report-list__row">
            <span class="report-list__row-label">Người cập nhật</span>
            <span class="report-list__row-value">{{ selected.updated_by_name ?? '—' }}</span>
          </div>
          <div class="report-list__row">
            <span class="report-list__row-label">Cập nhật lúc</span>
            <span class="report-list__row-value">{{ formatDateTime(selected.updated_at) || '—' }}</span>
          </div>
        </div>

        <div class="report-list__side-actions">
          <button
            v-if="canScoreReport(selected)"
            type="button"
            class="report-list__btn"
            @click="openScoring(selected)"
          >
            {{ isSavedReport(selected) ? 'Chi tiết' : 'Chấm điểm' }}
          </button>
          <button
            v-if="canExportPdfReport(selected)"
            type="button"
            class="report-list__btn report-list__btn--ghost"
            :disabled="exportingPdfId === selected.id"
            @click="exportReportPdf(selected)"
          >
            {{ exportingPdfId === selected.id ? 'Đang xuất PDF…' : 'Xuất PDF' }}
          </button>
          <button
            v-if="canDeleteReport(selected)"
            type="button"
            class="report-list__btn report-list__btn--ghost"
            @click="confirmTarget = selected"
          >
            Xoá
          </button>
        </div>
      </aside>
    </div>

    <Teleport to="body">
      <div
        v-if="showCreatePicker"
        class="report-picker"
        role="dialog"
        aria-modal="true"
        aria-label="Chọn loại báo cáo"
      >
        <div class="report-picker__backdrop" @click="showCreatePicker = false" />
        <div class="report-picker__panel">
          <div class="report-picker__head">
            <span class="report-picker__head-icon">
              <AppIcon name="barChart" :size="20" />
            </span>
            <span class="report-picker__head-copy">
              <h2 class="report-picker__title">Chọn loại báo cáo</h2>
              <p class="report-picker__subtitle">Chọn loại báo cáo muốn lập cho phòng ban.</p>
            </span>
            <button
              type="button"
              class="report-list__icon-btn"
              aria-label="Đóng"
              @click="showCreatePicker = false"
            >
              <AppIcon name="close" :size="16" />
            </button>
          </div>

          <div class="report-picker__body hide-scrollbar">
            <p class="report-picker__section-label">Có thể tạo ngay</p>
            <div class="report-picker__grid">
              <button
                v-for="item in availableTypes"
                :key="item.key"
                type="button"
                class="report-picker__card"
                @click="chooseReportType(item)"
              >
                <span class="report-picker__icon">
                  <AppIcon :name="item.icon" :size="22" />
                </span>
                <span class="report-picker__card-copy">
                  <span class="report-picker__card-title">{{ item.label }}</span>
                  <span class="report-picker__card-text">{{ item.description }}</span>
                </span>
                <AppIcon name="chevronRight" :size="16" class="report-picker__card-arrow" />
              </button>
            </div>

            <template v-if="comingSoonTypes.length">
              <p class="report-picker__section-label report-picker__section-label--soon">Sắp ra mắt</p>
              <div class="report-picker__grid">
                <div
                  v-for="item in comingSoonTypes"
                  :key="item.key"
                  class="report-picker__card report-picker__card--soon"
                  aria-disabled="true"
                >
                  <span class="report-picker__icon report-picker__icon--soon">
                    <AppIcon :name="item.icon" :size="22" />
                  </span>
                  <span class="report-picker__card-copy">
                    <span class="report-picker__card-title">{{ item.label }}</span>
                    <span class="report-picker__card-text">{{ item.description }}</span>
                  </span>
                  <span class="report-picker__soon">Sắp ra mắt</span>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="confirmTarget"
        class="report-confirm"
        role="alertdialog"
        aria-modal="true"
        aria-label="Xác nhận xoá báo cáo"
      >
        <div class="report-confirm__backdrop" @click="confirmTarget = null" />
        <div class="report-confirm__panel">
          <h2 class="report-confirm__title">Xoá báo cáo</h2>
          <p class="report-confirm__text">
            Xoá báo cáo "{{ confirmTarget.title }}"? Thao tác này không hoàn tác được.
          </p>
          <div class="report-confirm__foot">
            <button type="button" class="report-list__btn report-list__btn--ghost" @click="confirmTarget = null">
              Huỷ
            </button>
            <button type="button" class="report-list__btn" @click="removeReport">Xoá</button>
          </div>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
.report-list {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
}

.report-list__header-btn {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  height: 2rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 500;
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  cursor: pointer;
}

.report-list__header-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.report-list__header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.report-list__group-mode {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  height: 2rem;
  padding: 0.1875rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.report-list__group-mode-btn {
  height: 100%;
  padding: 0 0.625rem;
  border: none;
  border-radius: calc(var(--radius-md) - 0.1875rem);
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
}

.report-list__group-mode-btn:hover:not(.report-list__group-mode-btn--active) {
  color: var(--color-text);
}

.report-list__group-mode-btn--active {
  background: var(--color-surface);
  color: var(--color-primary);
  font-weight: 600;
  box-shadow: var(--shadow-sm);
}

.report-list__body {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-4);
  overflow: hidden;
}

.report-list__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.report-list__toolbar {
  position: relative;
  z-index: 6;
  flex-shrink: 0;
  margin: var(--space-3) 0;
}

.report-list__filters {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(11.5rem, 1fr));
  gap: var(--space-3);
  width: 100%;
}

.report-list__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
  width: 100%;
}

.report-list__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.report-list__input {
  width: 100%;
  min-width: 0;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
}

.report-list__input::placeholder {
  color: var(--color-text-muted);
  opacity: 0.8;
}

.report-list__spin {
  animation: report-list-spin 0.8s linear infinite;
}

@keyframes report-list-spin {
  to {
    transform: rotate(360deg);
  }
}

.report-list__check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.report-list__note {
  flex-shrink: 0;
  margin: 0 0 var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.report-list__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.report-list__table-wrap--resizing {
  cursor: col-resize;
  user-select: none;
}

.report-list__table {
  min-width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: calc(0.875rem * var(--table-zoom, 1));
}

.report-list__table thead th {
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

.report-list__table th {
  position: relative;
}

.report-list__resize {
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

.report-list__resize::after {
  content: '';
  position: absolute;
  top: 25%;
  right: 2px;
  width: 2px;
  height: 50%;
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.report-list__resize:hover::after {
  background: var(--color-primary);
}

.report-list__table tbody td {
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  vertical-align: middle;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.report-list__data-row {
  cursor: pointer;
}

.report-list__data-row:hover td {
  background: var(--color-surface-muted);
}

.report-list__row--active td {
  background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface));
}

.report-list__cell {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.report-list__title-cell,
.report-list__time-cell,
.report-list__status-cell,
.report-list__person-text {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.125rem;
}

.report-list__title {
  overflow: hidden;
  color: var(--color-text);
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.report-list__muted {
  overflow: hidden;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.report-list__status-line {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  color: var(--color-text);
  font-weight: 600;
}

.report-list__dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.report-list__dot--ok {
  background: var(--color-success);
}

.report-list__dot--draft {
  background: var(--color-warning);
}

.report-list__person {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  min-width: 0;
}

.report-list__person-text span {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.report-list__skel-row {
  cursor: default;
}

.report-list__skel-row:hover td {
  background: transparent;
}

.report-list__skel {
  display: block;
  height: 0.75rem;
  max-width: 14rem;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-text-muted) 16%, var(--color-surface));
  animation: report-list-skel 1.2s ease-in-out infinite;
}

.report-list__skel--icon {
  width: 1.25rem;
  height: 1.25rem;
  margin: 0 auto;
  border-radius: var(--radius-sm);
}

@keyframes report-list-skel {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.45;
  }
}

.report-list__group-row {
  cursor: pointer;
}

.report-list__table tbody tr.report-list__group-row td {
  position: relative;
  padding: var(--space-2) var(--space-4) var(--space-2) calc(var(--space-4) + 3px + var(--space-2));
  background: var(--color-surface);
  color: var(--group-fg, var(--color-text));
  box-shadow: inset 0 -2px 0 var(--group-accent, var(--color-border));
}

.report-list__table tbody tr.report-list__group-row td::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--group-accent, var(--color-border));
}

.report-list__table tbody tr.report-list__group-row:hover td {
  background: color-mix(in srgb, var(--group-accent, var(--color-text-muted)) 6%, var(--color-surface));
}

.report-list__group-row--primary {
  --group-fg: var(--color-primary);
  --group-accent: var(--color-primary);
}

.report-list__group-row--secondary {
  --group-fg: var(--color-secondary);
  --group-accent: var(--color-secondary);
}

.report-list__group-row--info {
  --group-fg: var(--color-info);
  --group-accent: var(--color-info);
}

.report-list__group-row--gold {
  --group-fg: var(--color-gold);
  --group-accent: var(--color-gold);
}

.report-list__group-row--tertiary {
  --group-fg: var(--color-tertiary);
  --group-accent: var(--color-tertiary);
}

.report-list__group-toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  width: 100%;
  min-width: 0;
}

.report-list__group-head {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-width: 0;
}

.report-list__group-chevron {
  flex-shrink: 0;
  color: var(--group-accent, var(--color-text-muted));
  transition: transform 0.15s ease;
}

.report-list__group-chevron--open {
  transform: rotate(90deg);
}

.report-list__group-label {
  flex-shrink: 0;
  color: inherit;
  font-size: calc(0.8125rem * var(--table-zoom, 1));
  font-weight: 700;
  white-space: nowrap;
}

.report-list__group-types {
  min-width: 0;
  overflow: hidden;
  color: var(--color-text-muted);
  font-size: calc(0.75rem * var(--table-zoom, 1));
  font-weight: 500;
  font-style: italic;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.report-list__group-count {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  height: 1.25rem;
  padding: 0 0.5rem;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--group-accent, var(--color-text-muted)) 14%, var(--color-surface));
  color: var(--group-accent, var(--color-text-muted));
  font-size: calc(0.6875rem * var(--table-zoom, 1));
  font-weight: 700;
  white-space: nowrap;
}

.report-list__empty {
  padding: var(--space-8) var(--space-6);
  color: var(--color-text-muted);
  text-align: center;
  white-space: normal;
  cursor: default;
}

.report-list__empty strong {
  display: block;
  margin: var(--space-2) 0 var(--space-1);
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 600;
}

.report-list__empty span {
  display: block;
}

.report-list__empty-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 3rem;
  height: 3rem;
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.report-list__empty-btn {
  margin-top: var(--space-3);
  padding: 0.5rem 1rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-secondary);
  color: var(--color-on-primary);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.report-list__stats {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
  flex-shrink: 0;
  margin: 0 0 var(--space-3);
}

.report-list__stat {
  padding: 0.375rem 0.75rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.report-list__stat:hover {
  background: color-mix(in srgb, var(--color-secondary) 12%, var(--color-surface-muted));
}

.report-list__stat--on {
  background: var(--color-secondary-surface);
  color: var(--color-secondary-700);
}

.report-list__title-btn {
  display: block;
  max-width: 100%;
  padding: 0;
  overflow: hidden;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: inherit;
  font-size: inherit;
  font-weight: 600;
  text-align: left;
  text-overflow: ellipsis;
  white-space: nowrap;
  cursor: pointer;
}

.report-list__title-btn:hover {
  color: var(--color-secondary-700);
  text-decoration: underline;
}

.report-list__sr {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
}

.report-list__th-action,
.report-list__td-action {
  padding-left: var(--space-2);
  padding-right: var(--space-2);
  text-align: center;
}

.report-list__actions {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 100%;
}

.report-list__action-trigger {
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

.report-list__action-trigger:hover,
.report-list__action-trigger--open {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.report-list__action-menu {
  position: fixed;
  z-index: 1200;
  width: 11rem;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  padding: var(--space-1);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
  text-align: left;
}

.report-list__action-item {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  padding: 0.5rem 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 500;
  text-align: left;
  cursor: pointer;
}

.report-list__action-item:hover {
  background: var(--color-surface-muted);
}

.report-list__action-item:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.report-list__action-item--danger {
  color: var(--color-danger);
}

.report-list__action-item--danger:hover {
  background: color-mix(in srgb, var(--color-danger) 10%, var(--color-surface));
}

.report-list__side {
  flex-shrink: 0;
  width: 28rem;
  overflow-y: auto;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.report-list__side-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  margin-bottom: var(--space-3);
}

.report-list__side-title {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 700;
}

.report-list__icon-btn {
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

.report-list__icon-btn:hover {
  background: var(--color-surface);
}

.report-list__side-lead {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  margin: 0 0 var(--space-4);
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.report-list__side-lead::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.report-list__side-lead--ok::before {
  background: var(--color-success);
}

.report-list__side-lead--draft::before {
  background: var(--color-warning);
}

.report-list__side-lead-kicker {
  display: block;
  margin-bottom: var(--space-1);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.report-list__side-lead-title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 1.45;
}

.report-list__side-lead-meta {
  margin: var(--space-1) 0 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.report-list__rows {
  display: flex;
  flex-direction: column;
}

.report-list__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.report-list__row:last-child {
  box-shadow: none;
}

.report-list__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.report-list__row-label::after {
  content: ':';
}

.report-list__row-value {
  color: var(--color-text);
  font-style: italic;
  text-align: right;
  overflow-wrap: anywhere;
}

.report-list__side-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
  margin-top: var(--space-4);
}

.report-list__btn {
  height: 2.375rem;
  padding: 0 1rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-primary);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  box-shadow: inset 0 0 0 1.5px var(--color-primary);
  cursor: pointer;
}

.report-list__btn:hover {
  background: var(--color-primary-surface);
}

.report-list__btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.report-list__btn--ghost {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.report-list__btn--ghost:hover {
  background: var(--color-surface-muted);
}

.report-confirm {
  position: fixed;
  inset: 0;
  z-index: 90;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
}

.report-confirm__backdrop {
  position: absolute;
  inset: 0;
  background: color-mix(in srgb, var(--color-text) 45%, transparent);
}

.report-confirm__panel {
  position: relative;
  z-index: 1;
  width: min(26rem, 100%);
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.report-confirm__title {
  margin: 0 0 var(--space-2);
  font-size: 1rem;
  font-weight: 600;
}

.report-confirm__text {
  margin: 0 0 var(--space-4);
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.report-confirm__foot {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

.report-picker {
  position: fixed;
  inset: 0;
  z-index: 90;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
}

.report-picker__backdrop {
  position: absolute;
  inset: 0;
  background: color-mix(in srgb, var(--color-text) 45%, transparent);
  backdrop-filter: blur(1px);
}

.report-picker__panel {
  position: relative;
  z-index: 1;
  width: min(56rem, calc(100vw - 2.5rem));
  height: min(44rem, calc(100vh - 2.5rem));
  max-height: calc(100vh - 2.5rem);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
  animation: report-picker-in 0.16s ease-out;
}

@keyframes report-picker-in {
  from {
    opacity: 0;
    transform: translateY(6px) scale(0.99);
  }
  to {
    opacity: 1;
    transform: none;
  }
}

.report-picker__head {
  flex-shrink: 0;
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  padding: var(--space-5) var(--space-5) var(--space-4);
  background: var(--color-surface-muted);
  box-shadow: 0 1px 0 var(--color-border);
}

.report-picker__head-icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-primary) 12%, var(--color-surface));
  color: var(--color-primary);
}

.report-picker__head-copy {
  flex: 1;
  min-width: 0;
}

.report-picker__title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.125rem;
  font-weight: 700;
}

.report-picker__subtitle {
  margin: 0.25rem 0 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.report-picker__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: var(--space-4) var(--space-5) var(--space-5);
}

.report-picker__section-label {
  margin: 0 0 var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.report-picker__section-label--soon {
  margin-top: var(--space-5);
}

.report-picker__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-2);
}

.report-picker__card {
  position: relative;
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3);
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  text-align: left;
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
  transition: box-shadow 0.12s ease, background 0.12s ease, transform 0.12s ease;
}

.report-picker__card:hover:not(.report-picker__card--soon) {
  background: color-mix(in srgb, var(--color-primary) 4%, var(--color-surface));
  box-shadow: inset 0 0 0 1.5px var(--color-primary);
  transform: translateY(-1px);
}

.report-picker__card:active:not(.report-picker__card--soon) {
  transform: translateY(0);
}

.report-picker__card--soon {
  background: var(--color-surface-muted);
  cursor: default;
}

.report-picker__icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-primary) 10%, var(--color-surface));
  color: var(--color-primary);
}

.report-picker__icon--soon {
  background: color-mix(in srgb, var(--color-text-muted) 12%, var(--color-surface));
  color: var(--color-text-muted);
}

.report-picker__card-copy {
  display: flex;
  flex: 1;
  min-width: 0;
  flex-direction: column;
  gap: 0.125rem;
}

.report-picker__card-title {
  overflow: hidden;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.report-picker__card--soon .report-picker__card-title {
  color: var(--color-text-muted);
}

.report-picker__card-text {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.45;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.report-picker__card-arrow {
  flex-shrink: 0;
  color: var(--color-text-muted);
  opacity: 0;
  transform: translateX(-4px);
  transition: opacity 0.12s ease, transform 0.12s ease;
}

.report-picker__card:hover .report-picker__card-arrow {
  color: var(--color-primary);
  opacity: 1;
  transform: none;
}

.report-picker__soon {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-style: italic;
  white-space: nowrap;
}

@media (max-width: 768px) {
  .report-picker__grid {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 1024px) {
  .report-list__body {
    flex-direction: column;
  }

  .report-list__side {
    width: 100%;
    max-height: 42%;
  }

  .report-list__table-wrap {
    min-height: 16rem;
  }

  .report-list__filters {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .report-list {
    padding: var(--space-4);
  }

  .report-list__filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 480px) {
  .report-list {
    padding: var(--space-3);
  }

  .report-list__filters {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
