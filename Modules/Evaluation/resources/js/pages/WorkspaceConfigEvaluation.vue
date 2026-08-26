<script setup>
//
// manager/workspace-config/evaluation — tiêu chí đánh giá của phòng ban.
// Tab thứ 3 của WorkspaceConfigHub; không có mục sidebar riêng.
//
// Hai kiểu tiêu chí:
//   scale    — thang điểm nhiều mức (Xuất sắc 5 / Tốt 4 / Khá 3…)
//   behavior — cộng/trừ theo hành vi (Đi muộn −1 / Hoàn thành sớm +2…)
//
import {
  computed,
  inject,
  nextTick,
  onBeforeUnmount,
  onMounted,
  reactive,
  ref,
  watch,
} from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import { formatDateTime } from '@/lib/formatTime';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';

// ─── constants ───────────────────────────────────────────────────────────────

const COLUMNS = [
  { key: 'name',               label: 'Tên tiêu chí',      defaultOn: true  },
  { key: 'level_count',        label: 'Số mức',             defaultOn: true,  align: 'center' },
  { key: 'max_score',          label: 'Điểm tối đa',        defaultOn: true,  align: 'center' },
  { key: 'status',             label: 'Trạng thái',         defaultOn: true,  align: 'center' },
  { key: 'use_in_evaluation',  label: 'Dùng trong ĐGNL',    defaultOn: true,  align: 'center' },
  { key: 'created_at',         label: 'Ngày tạo',           defaultOn: false },
  { key: 'creator',            label: 'Người tạo',          defaultOn: true,  align: 'center' },
  { key: 'updater',            label: 'Người cập nhật',     defaultOn: true,  align: 'center' },
  { key: 'description',        label: 'Mô tả',              defaultOn: false },
];

const FILTERS = [
  { key: 'q',      label: 'Tìm kiếm',   defaultOn: true },
  { key: 'kind',   label: 'Loại',       defaultOn: true },
  { key: 'type',   label: 'Cách chấm',  defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: true },
];

const TYPE_LABELS = { scale: 'Thang điểm', behavior: 'Cộng/trừ' };
const TYPE_CODE_PREFIX = 'TCA';
const TYPE_CODE_PAD    = 4;
const COL_KEY     = 'va-eval-criteria-columns-v5';
const FILTER_KEY  = 'va-eval-criteria-filters-v2';
const WIDTH_KEY   = 'va-eval-criteria-widths';
const ZOOM_KEY    = 'va-eval-criteria-zoom';

const CELL_PAD_X = 32;
const COL_EXTRA  = 24;
/** Badge cột bảng (Cách chấm / Trạng thái) — khớp `.eval-page__badge--cell` */
const BADGE_CELL_PX = 144;
const SWITCH_CELL_PX = 52;
const AVATAR_CELL_PX = 40;
let measureCtx   = null;
let wrapObserver = null;

// ─── stores / inject ─────────────────────────────────────────────────────────

const auth = useAuthStore();
const hub  = inject('workspaceConfigHub', null);

// ─── state ───────────────────────────────────────────────────────────────────

const allCriteria = ref([]);
const criterionTypes = ref([]);
const loading     = ref(false);
const selected    = ref(null); // criterion đang xem
const dialogKind  = ref(null); // 'criterion' | null
const typeDialogOpen = ref(false);
const dialogTab   = ref('create');
const listFilter  = ref('');
const historyLogs = ref([]);
const historyLoading = ref(false);

const query     = ref('');
const typeFilter = ref('');
const kindFilter = ref('');
const statusFilter = ref('');
const page      = ref(1);
const perPage   = ref(20);

const visibleColumns = reactive(loadVisibility(COL_KEY, COLUMNS));
const visibleFilters = reactive(loadVisibility(FILTER_KEY, FILTERS));

const tableWrap  = ref(null);
const resizing   = ref(false);
const MIN_COL_PX = 80;
const colWidths  = reactive(loadWidths());
const tableZoom  = ref(loadZoom());

// ─── form state ──────────────────────────────────────────────────────────────

const form = reactive({
  id:                '',
  name:              '',
  criterion_type_id: '',
  type:              'scale',
  description:       '',
  is_active:         true,
  use_in_evaluation: true,
  allow_half:        false,
  levels:            [],
});
const formErrors  = ref({});
const formSaving  = ref(false);
const deletingId  = ref(null);
const togglingId  = ref(null);
const confirmDelete = ref(null);

const typeFormSaving = ref(false);
const typeFormErrors = ref({});
const typeForm = reactive({
  name:        '',
  code:        '',
  description: '',
  codeLocked:  true,
});

/** Thu gọn nhóm theo loại tiêu chí (bảng chính + danh sách Sửa trong modal). */
const typeGroupCollapsed = reactive({});
const dialogTypeGroupCollapsed = reactive({});

// ─── computed ─────────────────────────────────────────────────────────────────

const hasDepartment = computed(() => Boolean(auth.user?.department?.id));

const canManage = computed(() =>
  auth.can('evaluation.manage_department'),
);

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase();
  return allCriteria.value.filter((c) => {
    if (q && !c.name.toLowerCase().includes(q) && !(c.criterion_type?.name ?? '').toLowerCase().includes(q)
      && !(c.criterion_type?.code ?? '').toLowerCase().includes(q)) return false;
    if (kindFilter.value && String(c.criterion_type_id) !== String(kindFilter.value)) return false;
    if (typeFilter.value && c.type !== typeFilter.value) return false;
    if (statusFilter.value === 'active'   && !c.is_active) return false;
    if (statusFilter.value === 'inactive' &&  c.is_active) return false;
    return true;
  });
});

const lastPage = computed(() => 1);

const pagerMeta = computed(() => {
  const total = filtered.value.length;
  const visible = criteriaGroups.value.reduce(
    (n, g) => n + (isTypeGroupCollapsed(typeGroupCollapsed, g.key) ? 0 : g.criteria.length),
    0,
  );
  return { from: visible ? 1 : 0, to: visible, total };
});

const hasActiveFilters = computed(() =>
  Boolean(query.value.trim() || kindFilter.value || typeFilter.value || statusFilter.value),
);

const criterionDialogTitle = computed(() => {
  if (dialogTab.value === 'edit') return 'Sửa tiêu chí';
  if (dialogTab.value === 'history') return 'Lịch sử thay đổi';
  return 'Thêm tiêu chí mới';
});

const criterionSubmitLabel = computed(() => {
  if (formSaving.value) return 'Đang lưu…';
  return dialogTab.value === 'edit' ? 'Lưu tiêu chí' : 'Tạo tiêu chí';
});

const typeSubmitLabel = computed(() =>
  typeFormSaving.value ? 'Đang tạo…' : 'Tạo loại',
);

const selectedDialogCriterion = computed(() =>
  allCriteria.value.find((item) => String(item.id) === String(form.id)) ?? null,
);

function criterionGroupKey(typeId) {
  return typeId == null || typeId === '' ? '__none__' : String(typeId);
}

function typeOrderIndex(typeId) {
  if (typeId == null || typeId === '') return Number.MAX_SAFE_INTEGER;
  const idx = criterionTypes.value.findIndex((t) => String(t.id) === String(typeId));
  return idx >= 0 ? idx : Number.MAX_SAFE_INTEGER;
}

function compareCriteriaByType(a, b) {
  const orderA = typeOrderIndex(a.criterion_type_id);
  const orderB = typeOrderIndex(b.criterion_type_id);
  if (orderA !== orderB) return orderA - orderB;
  return String(a.name ?? '').localeCompare(String(b.name ?? ''), 'vi');
}

function buildCriteriaGroups(criteriaList) {
  const groups = [];
  const map = new Map();
  for (const criterion of criteriaList) {
    const key = criterionGroupKey(criterion.criterion_type_id);
    if (!map.has(key)) {
      const entry = {
        key,
        type: criterion.criterion_type ?? null,
        criteria: [],
      };
      map.set(key, entry);
      groups.push(entry);
    }
    map.get(key).criteria.push(criterion);
  }
  return groups;
}

function groupTitle(type) {
  return type?.name ?? 'Chưa phân loại';
}

function groupScoringKinds(criteria) {
  const order = { scale: 0, behavior: 1 };
  return [...new Set(criteria.map((c) => c.type).filter(Boolean))].sort(
    (a, b) => (order[a] ?? 9) - (order[b] ?? 9),
  );
}

function groupScoringAccent(criteria) {
  const kinds = groupScoringKinds(criteria);
  if (kinds.length === 1) return kinds[0];
  if (kinds.length > 1) return 'mixed';
  return '';
}

function isTypeGroupCollapsed(store, key) {
  return Boolean(store[key]);
}

function toggleTypeGroup(key) {
  typeGroupCollapsed[key] = !typeGroupCollapsed[key];
}

function toggleDialogTypeGroup(key) {
  dialogTypeGroupCollapsed[key] = !dialogTypeGroupCollapsed[key];
}

const criteriaGroups = computed(() => {
  const sorted = [...filtered.value].sort(compareCriteriaByType);
  return buildCriteriaGroups(sorted);
});

const tableBodyRows = computed(() => {
  const rows = [];
  for (const group of criteriaGroups.value) {
    rows.push({
      kind: 'group',
      key: `group-${group.key}`,
      groupKey: group.key,
      title: groupTitle(group.type),
      code: group.type?.code ?? '',
      count: group.criteria.length,
      collapsed: isTypeGroupCollapsed(typeGroupCollapsed, group.key),
      scoringAccent: groupScoringAccent(group.criteria),
      scoringKinds: groupScoringKinds(group.criteria),
    });
    if (!isTypeGroupCollapsed(typeGroupCollapsed, group.key)) {
      for (const criterion of group.criteria) {
        rows.push({ kind: 'criterion', key: criterion.id, criterion });
      }
    }
  }
  return rows;
});

const tableColspan = computed(
  () => shownColumns.value.length + (canManage.value ? 1 : 0),
);

const dialogCriteriaGroups = computed(() => {
  const q = listFilter.value.trim().toLowerCase();
  const list = allCriteria.value
    .filter((item) => {
      if (!q) return true;
      const hay = `${item.name ?? ''} ${item.criterion_type?.name ?? ''} ${item.criterion_type?.code ?? ''}`.toLowerCase();
      return hay.includes(q);
    })
    .sort(compareCriteriaByType);
  return buildCriteriaGroups(list);
});

const dialogListHasItems = computed(() =>
  dialogCriteriaGroups.value.some((g) => g.criteria.length > 0),
);

const criterionEmptyMessage = computed(() =>
  allCriteria.value.length === 0 ? 'Phòng ban chưa có tiêu chí nào. Tạo tiêu chí mới ở tab Thêm.' : '',
);

const criterionUnchanged = computed(() => {
  if (dialogTab.value !== 'edit' || !selectedDialogCriterion.value) return false;
  const current = selectedDialogCriterion.value;
  const typeId = form.criterion_type_id ? Number(form.criterion_type_id) : null;
  const currentTypeId = current.criterion_type_id == null ? null : Number(current.criterion_type_id);
  if (form.name.trim() !== (current.name || '')) return false;
  if (typeId !== currentTypeId) return false;
  if (form.type !== current.type) return false;
  if ((form.description || '') !== (current.description || '')) return false;
  if (Boolean(form.is_active) !== Boolean(current.is_active)) return false;
  if (Boolean(form.use_in_evaluation) !== Boolean(current.use_in_evaluation)) return false;
  if (Boolean(form.allow_half) !== Boolean(current.allow_half)) return false;
  const nextLevels = form.levels.map(levelSnapshot);
  const prevLevels = (current.levels ?? []).map(levelSnapshot);
  return JSON.stringify(nextLevels) === JSON.stringify(prevLevels);
});

const hasVisibleFilterFields = computed(() =>
  FILTERS.some((item) => visibleFilters[item.key]),
);

const hiddenActiveFilterLabels = computed(() =>
  FILTERS.filter((item) => !visibleFilters[item.key] && filterHasValue(item.key)).map(
    (item) => item.label,
  ),
);

const shownColumns = computed(() =>
  COLUMNS.filter((col) => visibleColumns[col.key]),
);

function filterHasValue(key) {
  if (key === 'q') return Boolean(query.value.trim());
  if (key === 'kind') return Boolean(kindFilter.value);
  if (key === 'type') return Boolean(typeFilter.value);
  if (key === 'status') return Boolean(statusFilter.value);
  return false;
}

function clearFilters() {
  query.value = '';
  kindFilter.value = '';
  typeFilter.value = '';
  statusFilter.value = '';
  page.value = 1;
}

function nextTypeCode() {
  const pattern = new RegExp(`^${TYPE_CODE_PREFIX}(\\d+)$`, 'i');
  let max = 0;
  for (const type of criterionTypes.value) {
    const match = String(type.code ?? '').match(pattern);
    if (match) max = Math.max(max, Number.parseInt(match[1], 10));
  }
  const next = max + 1;
  const width = Math.max(TYPE_CODE_PAD, String(next).length);
  return TYPE_CODE_PREFIX + String(next).padStart(width, '0');
}

function onColumnToggle(key, checked) {
  if (!checked) {
    const remaining = COLUMNS.filter((col) => visibleColumns[col.key] && col.key !== key).length;
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

// ─── persistence helpers ─────────────────────────────────────────────────────

function loadVisibility(key, items) {
  const defaults = {};
  for (const item of items) defaults[item.key] = item.defaultOn;
  try {
    const raw = localStorage.getItem(key);
    if (!raw) return defaults;
    const parsed = JSON.parse(raw);
    const next = { ...defaults };
    for (const item of items) {
      if (typeof parsed[item.key] === 'boolean') next[item.key] = parsed[item.key];
    }
    return next;
  } catch { return defaults; }
}

function saveVisibility(key, value) {
  try { localStorage.setItem(key, JSON.stringify(value)); } catch {}
}

function loadWidths() {
  try {
    const raw = localStorage.getItem(WIDTH_KEY);
    return raw ? JSON.parse(raw) : {};
  } catch { return {}; }
}

function saveWidths() {
  try { localStorage.setItem(WIDTH_KEY, JSON.stringify({ ...colWidths })); } catch {}
}

function loadZoom() {
  try {
    const v = parseFloat(localStorage.getItem(ZOOM_KEY));
    return isNaN(v) ? 1 : v;
  } catch { return 1; }
}

function saveZoom(v) {
  try { localStorage.setItem(ZOOM_KEY, String(v)); } catch {}
}

// ─── column widths ────────────────────────────────────────────────────────────

function measureText(text) {
  if (!measureCtx) {
    const canvas = document.createElement('canvas');
    measureCtx = canvas.getContext('2d');
    measureCtx.font = `600 0.75rem var(--font-family-base, sans-serif)`;
  }
  return measureCtx.measureText(String(text ?? '')).width;
}

function computeDefaultWidths() {
  const wrapWidth = tableWrap.value?.clientWidth ?? 800;
  const rawWidths = {};

  for (const col of shownColumns.value) {
    let max = measureText(col.label) + COL_EXTRA;
    if (col.key === 'status') {
      max = Math.max(max, BADGE_CELL_PX + CELL_PAD_X);
    } else if (col.key === 'use_in_evaluation') {
      max = Math.max(max, SWITCH_CELL_PX + CELL_PAD_X);
    } else if (col.key === 'creator' || col.key === 'updater') {
      max = Math.max(max, AVATAR_CELL_PX + CELL_PAD_X);
    } else {
      for (const row of allCriteria.value) {
        let val = '';
        if (col.key === 'name')        val = row.name ?? '';
        if (col.key === 'level_count') val = String(row.level_count ?? 0);
        if (col.key === 'max_score')   val = formatScore(row.max_score);
        if (col.key === 'created_at')  val = formatDateTime(row.created_at) || '—';
        if (col.key === 'description') val = row.description ?? '';
        const w = measureText(val) + CELL_PAD_X;
        if (w > max) max = w;
      }
    }
    rawWidths[col.key] = Math.max(Math.ceil(max), MIN_COL_PX);
  }

  const totalRaw = Object.values(rawWidths).reduce((a, b) => a + b, 0);
  if (totalRaw < wrapWidth && shownColumns.value.length > 0) {
    const factor = wrapWidth / totalRaw;
    for (const k in rawWidths) rawWidths[k] = Math.floor(rawWidths[k] * factor);
  }

  for (const col of shownColumns.value) {
    if (!colWidths[col.key]) colWidths[col.key] = rawWidths[col.key];
  }
}

// ─── column resize ───────────────────────────────────────────────────────────

let resizeState = null;

function startResize(event, colKey) {
  event.preventDefault();
  const cols  = shownColumns.value;
  const idx   = cols.findIndex((c) => c.key === colKey);
  const nextCol = cols[idx + 1] ?? null;

  resizeState = {
    startX: event.clientX,
    startW: colWidths[colKey] ?? MIN_COL_PX,
    nextKey: nextCol?.key ?? null,
    startNextW: nextCol ? (colWidths[nextCol.key] ?? MIN_COL_PX) : null,
  };

  resizing.value = true;

  function onMove(e) {
    if (!resizeState) return;
    const dx = e.clientX - resizeState.startX;
    const newW  = Math.max(MIN_COL_PX, resizeState.startW + dx);
    colWidths[colKey] = newW;

    if (resizeState.nextKey) {
      const newNext = Math.max(MIN_COL_PX, resizeState.startNextW - dx);
      colWidths[resizeState.nextKey] = newNext;
    }
  }

  function onUp() {
    resizing.value = false;
    resizeState = null;
    saveWidths();
    document.removeEventListener('mousemove', onMove);
    document.removeEventListener('mouseup', onUp);
  }

  document.addEventListener('mousemove', onMove);
  document.addEventListener('mouseup', onUp);
}

// ─── API ────────────────────────────────────────────────────────────────────

async function load() {
  if (!hasDepartment.value) {
    allCriteria.value = [];
    criterionTypes.value = [];
    return;
  }
  loading.value = true;
  try {
    const [criteriaRes, typesRes] = await Promise.all([
      window.axios.get('/api/evaluation/criteria'),
      window.axios.get('/api/evaluation/criterion-types'),
    ]);
    allCriteria.value = criteriaRes.data.criteria ?? [];
    criterionTypes.value = typesRes.data.types ?? [];
    await nextTick();
    computeDefaultWidths();
  } catch (err) {
    const msg = err?.response?.data?.message;
    showClientToast('error', msg || 'Không tải được danh sách tiêu chí.');
  } finally {
    loading.value = false;
  }
}

async function loadHistory() {
  historyLoading.value = true;
  try {
    const { data } = await window.axios.get('/api/evaluation/criteria/history');
    historyLogs.value = data.logs ?? [];
  } catch (err) {
    historyLogs.value = [];
    showClientToast('error', err?.response?.data?.message || 'Không tải được lịch sử thay đổi.');
  } finally {
    historyLoading.value = false;
  }
}

function historyVerbIcon(action) {
  if (String(action).includes('delete')) return 'trash';
  if (String(action).includes('create')) return 'plus';
  return 'pencil';
}

function historyVerbTone(action) {
  if (String(action).includes('delete')) return 'danger';
  if (String(action).includes('create')) return 'success';
  return 'info';
}

function historyInitial(name) {
  return String(name ?? '').trim().charAt(0).toUpperCase() || '?';
}

function formatHistoryTime(iso) {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  const hh = String(date.getHours()).padStart(2, '0');
  const mm = String(date.getMinutes()).padStart(2, '0');
  const dd = String(date.getDate()).padStart(2, '0');
  const mo = String(date.getMonth() + 1).padStart(2, '0');
  return `${hh}:${mm} ng ${dd}/${mo}/${date.getFullYear()}`;
}

function openHistoryCriterion(log) {
  if (!log?.can_open || !log.subject_id) return;
  const criterion = allCriteria.value.find((item) => String(item.id) === String(log.subject_id));
  if (!criterion) {
    showClientToast('error', 'Tiêu chí không còn tồn tại.');
    return;
  }
  fillCriterionForm(criterion);
  dialogTab.value = 'edit';
}

async function saveCriterion() {
  if (dialogTab.value === 'history') return;
  if (dialogTab.value === 'edit' && (form.id === '' || form.id == null)) {
    showClientToast('error', 'Vui lòng chọn tiêu chí cần sửa.');
    return;
  }
  if (dialogTab.value === 'edit' && criterionUnchanged.value) {
    showClientToast('warning', 'Chưa có thay đổi để lưu.');
    return;
  }

  formErrors.value = {};
  formSaving.value = true;

  const payload = {
    name:               form.name,
    criterion_type_id:  form.criterion_type_id || null,
    type:               form.type,
    description:        form.description || null,
    is_active:          form.is_active,
    use_in_evaluation:  Boolean(form.use_in_evaluation),
    allow_half:         Boolean(form.allow_half),
    levels: form.levels
      .filter((l) => (l.label ?? '').trim() !== '')
      .map((l) => ({
        code: (l.code ?? '').trim(),
        label: (l.label ?? '').trim(),
        description: (l.description ?? '').trim() || null,
        score: roundScore(l.score),
      })),
  };

  try {
    if (dialogTab.value === 'edit') {
      const { data } = await window.axios.put(
        `/api/evaluation/criteria/${form.id}`,
        payload,
      );
      const idx = allCriteria.value.findIndex((c) => c.id === data.criterion.id);
      if (idx !== -1) allCriteria.value[idx] = data.criterion;
      if (selected.value?.id === data.criterion.id) selected.value = data.criterion;
      dialogKind.value = null;
      showClientToast('success', `Đã lưu tiêu chí "${data.criterion.name}".`);
    } else {
      const { data } = await window.axios.post('/api/evaluation/criteria', payload);
      allCriteria.value.unshift(data.criterion);
      dialogKind.value = null;
      showClientToast('success', `Đã tạo tiêu chí "${data.criterion.name}".`);
    }
    await nextTick();
    computeDefaultWidths();
  } catch (err) {
    if (err?.response?.status === 422) {
      formErrors.value = err.response.data?.errors ?? {};
      const msg = err.response.data?.message;
      if (msg) showClientToast('error', msg);
    } else {
      showClientToast('error', err?.response?.data?.message || 'Không lưu được tiêu chí.');
    }
  } finally {
    formSaving.value = false;
  }
}

async function doDelete() {
  if (!confirmDelete.value) return;
  const id = confirmDelete.value.id;
  const name = confirmDelete.value.name;
  deletingId.value = id;
  confirmDelete.value = null;

  try {
    await window.axios.delete(`/api/evaluation/criteria/${id}`);
    allCriteria.value = allCriteria.value.filter((c) => c.id !== id);
    if (selected.value?.id === id) closePanel();
    showClientToast('success', `Đã xoá tiêu chí "${name}".`);
    await nextTick();
    computeDefaultWidths();
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không xoá được tiêu chí.');
  } finally {
    deletingId.value = null;
  }
}

async function toggleActive(criterion) {
  togglingId.value = criterion.id;
  try {
    const { data } = await window.axios.patch(
      `/api/evaluation/criteria/${criterion.id}/toggle`,
    );
    const idx = allCriteria.value.findIndex((c) => c.id === criterion.id);
    if (idx !== -1) allCriteria.value[idx] = data.criterion;
    if (selected.value?.id === criterion.id) selected.value = data.criterion;
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không cập nhật được trạng thái.');
  } finally {
    togglingId.value = null;
  }
}

async function toggleUseInEvaluation(criterion) {
  togglingId.value = criterion.id;
  try {
    const { data } = await window.axios.patch(
      `/api/evaluation/criteria/${criterion.id}/toggle-evaluation`,
    );
    const idx = allCriteria.value.findIndex((c) => c.id === criterion.id);
    if (idx !== -1) allCriteria.value[idx] = data.criterion;
    if (selected.value?.id === criterion.id) selected.value = data.criterion;
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không cập nhật được dùng trong ĐGNL.');
  } finally {
    togglingId.value = null;
  }
}

// ─── dialog / panel helpers ──────────────────────────────────────────────────

function resetCriterionForm() {
  form.id = '';
  form.name = '';
  form.criterion_type_id = '';
  form.type = 'scale';
  form.description = '';
  form.is_active = true;
  form.use_in_evaluation = true;
  form.allow_half = false;
  form.levels = defaultLevels('scale');
  formErrors.value = {};
}

function fillCriterionForm(criterion) {
  form.id = criterion.id;
  form.name = criterion.name;
  form.criterion_type_id = criterion.criterion_type_id
    ? String(criterion.criterion_type_id)
    : '';
  form.type = criterion.type;
  form.description = criterion.description ?? '';
  form.is_active = criterion.is_active;
  form.use_in_evaluation = criterion.use_in_evaluation !== false;
  form.allow_half = Boolean(criterion.allow_half) || hasHalfScore(criterion.levels);
  form.levels = (criterion.levels ?? []).map((level) => mapLevel(level));
  formErrors.value = {};
}

function openAdd() {
  dialogTab.value = 'create';
  listFilter.value = '';
  resetCriterionForm();
  dialogKind.value = 'criterion';
  nextTick(focusDialog);
}

function openTypeForm() {
  resetTypeForm();
  typeDialogOpen.value = true;
  nextTick(() => document.getElementById('eval-type-name')?.focus());
}

function resetTypeForm() {
  typeForm.name = '';
  typeForm.code = nextTypeCode();
  typeForm.description = '';
  typeForm.codeLocked = true;
  typeFormErrors.value = {};
}

function closeTypeDialog() {
  if (typeFormSaving.value) return;
  typeDialogOpen.value = false;
}

function openView(criterion) {
  selected.value = criterion;
}

function startEdit() {
  if (!selected.value) return;
  dialogTab.value = 'edit';
  listFilter.value = '';
  fillCriterionForm(selected.value);
  dialogKind.value = 'criterion';
}

function closePanel() {
  selected.value = null;
}

function setDialogTab(tab) {
  if (formSaving.value || dialogTab.value === tab) return;
  dialogTab.value = tab;
  listFilter.value = '';
  if (tab === 'history') {
    loadHistory();
    return;
  }
  if (tab === 'edit' && selected.value && !form.id) {
    fillCriterionForm(selected.value);
  } else if (tab === 'create') {
    resetCriterionForm();
  }
  nextTick(focusDialog);
}

function closeDialog() {
  if (typeDialogOpen.value) {
    closeTypeDialog();
    return;
  }
  if (formSaving.value) return;
  dialogKind.value = null;
}

function focusDialog() {
  if (typeDialogOpen.value) {
    document.getElementById('eval-type-name')?.focus();
    return;
  }
  if (dialogTab.value === 'edit' || dialogTab.value === 'history') return;
  document.getElementById('eval-criterion-name')?.focus();
}

function toggleTypeCodeLock() {
  if (typeForm.codeLocked) {
    typeForm.codeLocked = false;
    if (!typeForm.code.trim()) {
      typeForm.code = nextTypeCode();
    }
    return;
  }
  typeForm.codeLocked = true;
  typeForm.code = nextTypeCode();
}

async function saveType() {
  typeFormErrors.value = {};
  if (!typeForm.name.trim()) {
    typeFormErrors.value = { name: 'Tên loại tiêu chí là bắt buộc.' };
    return;
  }
  typeFormSaving.value = true;
  try {
    const { data } = await window.axios.post('/api/evaluation/criterion-types', {
      name: typeForm.name.trim(),
      code: typeForm.codeLocked ? '' : typeForm.code.trim(),
      description: typeForm.description.trim() || null,
    });
    const created = data.type;
    criterionTypes.value = [...criterionTypes.value, created]
      .sort((a, b) => a.name.localeCompare(b.name, 'vi'));
    form.criterion_type_id = String(created.id);
    showClientToast('success', `Đã tạo loại «${created.name}».`);
    typeDialogOpen.value = false;
  } catch (err) {
    if (err?.response?.status === 422) {
      typeFormErrors.value = err.response.data?.errors ?? {};
      const msg = err.response.data?.message;
      if (msg) showClientToast('error', msg);
    } else {
      showClientToast('error', err?.response?.data?.message || 'Không tạo được loại tiêu chí.');
    }
  } finally {
    typeFormSaving.value = false;
  }
}

function mapLevel(level) {
  return {
    code: level.code ?? '',
    label: level.label ?? '',
    description: level.description ?? '',
    score: roundScore(level.score ?? 1),
  };
}

function levelSnapshot(level) {
  return {
    code: (level.code ?? '').trim(),
    label: (level.label ?? '').trim(),
    description: (level.description ?? '').trim(),
    score: roundScore(level.score),
  };
}

function defaultLevels(type) {
  if (type === 'scale') {
    return [
      { code: 'M1', label: 'Không đáp ứng',     description: '', score: 1 },
      { code: 'M2', label: 'Cần cố gắng hơn',   description: '', score: 2 },
      { code: 'M3', label: 'Đạt yêu cầu',       description: '', score: 3 },
      { code: 'M4', label: 'Tốt',               description: '', score: 4 },
      { code: 'M5', label: 'Rất tốt',           description: '', score: 5 },
    ];
  }
  return [{ code: 'H1', label: '', description: '', score: 1 }];
}

function nextLevelCode(type) {
  const prefix = type === 'scale' ? 'M' : 'H';
  const pattern = new RegExp(`^${prefix}(\\d+)$`, 'i');
  let max = 0;
  for (const level of form.levels) {
    const match = String(level.code ?? '').match(pattern);
    if (match) max = Math.max(max, Number.parseInt(match[1], 10));
  }
  return prefix + String(max + 1);
}

function changeType(type) {
  if (form.type === type) return;
  form.type = type;
  form.levels = defaultLevels(type);
}

function hasHalfScore(levels) {
  return (levels ?? []).some((level) => {
    const n = Number(level?.score);
    return Number.isFinite(n) && Math.abs(n % 1) > 0.001;
  });
}

function roundScore(score, allowHalf = form.allow_half) {
  const n = Number(score);
  const step = allowHalf ? 0.5 : 1;
  if (!Number.isFinite(n)) return step;
  const rounded = allowHalf ? Math.round(n * 2) / 2 : Math.round(n);
  if (form.type === 'scale') {
    return Math.max(step, rounded);
  }
  return rounded;
}

function formatScore(score) {
  const n = Number(score);
  if (!Number.isFinite(n)) return '0';
  return Number.isInteger(n) ? String(n) : n.toFixed(1);
}

function setAllowHalf(enabled) {
  form.allow_half = Boolean(enabled);
  form.levels = form.levels.map((level) => ({
    ...level,
    score: roundScore(level.score),
  }));
}

function addLevel() {
  const last = form.levels[form.levels.length - 1];
  const step = form.allow_half ? 0.5 : 1;
  const nextScore = form.type === 'scale'
    ? Math.max(step, roundScore((Number(last?.score) || 0) + 1))
    : step;
  form.levels.push({
    code: nextLevelCode(form.type),
    label: '',
    description: '',
    score: nextScore,
  });
}

function removeLevel(idx) {
  form.levels.splice(idx, 1);
}

// Dấu + trên header xổ dropdown: thêm tiêu chí / thêm loại tiêu chí.
function registerPrimaryAction() {
  if (!canManage.value || !hasDepartment.value) {
    hub?.clearPrimaryAction?.();
    return;
  }
  hub?.setPrimaryAction?.({
    label: 'Thêm',
    icon: 'plus',
    items: [
      {
        key: 'criterion',
        label: 'Thêm tiêu chí',
        icon: 'clipboardCheck',
        onSelect: openAdd,
      },
      {
        key: 'type',
        label: 'Thêm loại tiêu chí',
        icon: 'layers',
        onSelect: openTypeForm,
      },
    ],
  });
}

function handleKeydown(e) {
  if (e.key !== 'Escape') return;
  if (confirmDelete.value) {
    confirmDelete.value = null;
    return;
  }
  if (typeDialogOpen.value) {
    closeTypeDialog();
    return;
  }
  if (dialogKind.value) {
    closeDialog();
    return;
  }
  if (selected.value) closePanel();
}

watch(criterionTypes, () => {
  if (typeDialogOpen.value && typeForm.codeLocked) {
    typeForm.code = nextTypeCode();
  }
});

watch(
  () => form.id,
  (id) => {
    if (dialogKind.value !== 'criterion' || dialogTab.value !== 'edit') return;
    const criterion = allCriteria.value.find((item) => String(item.id) === String(id));
    if (criterion) fillCriterionForm(criterion);
  },
);

watch([query, kindFilter, typeFilter, statusFilter], () => { page.value = 1; });

watch(visibleColumns, (value) => saveVisibility(COL_KEY, value), { deep: true });
watch(visibleFilters, (value) => saveVisibility(FILTER_KEY, value), { deep: true });

watch([canManage, hasDepartment], () => registerPrimaryAction());

onMounted(async () => {
  hub?.registerReload?.(load);
  registerPrimaryAction();
  document.addEventListener('keydown', handleKeydown);

  await load();

  wrapObserver = new ResizeObserver(() => computeDefaultWidths());
  if (tableWrap.value) wrapObserver.observe(tableWrap.value);
});

onBeforeUnmount(() => {
  hub?.unregisterReload?.();
  hub?.clearPrimaryAction?.();
  document.removeEventListener('keydown', handleKeydown);
  wrapObserver?.disconnect();
});
</script>

<template>
  <div class="eval-page" :class="{ 'eval-page--with-panel': selected }">
    <!-- ── main area ──────────────────────────────────────────────────────── -->
    <div class="eval-page__main">

      <div v-if="hasVisibleFilterFields" class="eval-page__toolbar">
        <div class="eval-page__filters">
          <div v-if="visibleFilters.q" class="eval-page__field">
            <label class="eval-page__label" for="eval-q">Tìm kiếm</label>
            <input
              id="eval-q"
              v-model="query"
              type="search"
              class="eval-page__input"
              placeholder="Tên tiêu chí, loại…"
              @keydown.enter="page = 1"
            />
          </div>
          <div v-if="visibleFilters.kind" class="eval-page__field">
            <label class="eval-page__label" for="eval-kind">Loại</label>
            <select id="eval-kind" v-model="kindFilter" class="eval-page__input">
              <option value="">Tất cả loại</option>
              <option
                v-for="item in criterionTypes"
                :key="item.id"
                :value="String(item.id)"
              >
                {{ item.name }}{{ item.code ? ` — ${item.code}` : '' }}
              </option>
            </select>
          </div>
          <div v-if="visibleFilters.type" class="eval-page__field">
            <label class="eval-page__label" for="eval-type">Cách chấm</label>
            <select id="eval-type" v-model="typeFilter" class="eval-page__input">
              <option value="">Tất cả cách chấm</option>
              <option value="scale">Thang điểm</option>
              <option value="behavior">Cộng/trừ</option>
            </select>
          </div>
          <div v-if="visibleFilters.status" class="eval-page__field">
            <label class="eval-page__label" for="eval-status">Trạng thái</label>
            <select id="eval-status" v-model="statusFilter" class="eval-page__input">
              <option value="">Tất cả trạng thái</option>
              <option value="active">Hoạt động</option>
              <option value="inactive">Không hoạt động</option>
            </select>
          </div>
        </div>
      </div>

      <TablePagesBar
        placement="top"
        :from="pagerMeta.from"
        :to="pagerMeta.to"
        :total="pagerMeta.total"
        :page="page"
        :last-page="lastPage"
        :per-page="perPage"
        :zoom="tableZoom"
        show-search
        :show-clear-filters="hasActiveFilters"
        :filters-active="hasActiveFilters"
        @search="page = 1"
        @clear-filters="clearFilters"
        @update:page="page = $event"
        @update:per-page="perPage = $event; page = 1"
        @update:zoom="tableZoom = $event; saveZoom($event)"
      >
        <template #filters>
          <label v-for="item in FILTERS" :key="item.key" class="eval-page__check">
            <input
              type="checkbox"
              :checked="visibleFilters[item.key]"
              @change="onFilterToggle(item.key, $event.target.checked)"
            />
            <span>{{ item.label }}</span>
          </label>
        </template>
        <template #settings>
          <label v-for="col in COLUMNS" :key="col.key" class="eval-page__check">
            <input
              type="checkbox"
              :checked="visibleColumns[col.key]"
              @change="onColumnToggle(col.key, $event.target.checked)"
            />
            <span>{{ col.label }}</span>
          </label>
        </template>
      </TablePagesBar>

      <p v-if="hiddenActiveFilterLabels.length" class="eval-page__note">
        Đang lọc thêm theo: {{ hiddenActiveFilterLabels.join(', ') }} (bộ lọc đang ẩn).
      </p>

      <!-- Bảng tiêu chí -->
      <div
        ref="tableWrap"
        class="eval-page__table-wrap hide-scrollbar"
        :class="{ 'eval-page__table-wrap--resizing': resizing }"
        :style="{ '--table-zoom': tableZoom }"
      >
        <p v-if="!hasDepartment" class="eval-page__empty">
          Tài khoản chưa gắn với phòng ban nào.
        </p>

        <p v-else-if="loading" class="eval-page__empty">Đang tải…</p>

        <p v-else-if="allCriteria.length === 0" class="eval-page__empty">
          Phòng ban chưa có tiêu chí đánh giá nào.
          <template v-if="canManage"> Bấm dấu <strong>+</strong> rồi chọn Thêm tiêu chí để bắt đầu.</template>
        </p>

        <p v-else-if="filtered.length === 0" class="eval-page__empty">
          Không tìm thấy tiêu chí phù hợp.
        </p>

        <table v-else class="eval-page__table">
          <colgroup>
            <col
              v-for="col in shownColumns"
              :key="col.key"
              :style="{ width: (colWidths[col.key] ?? MIN_COL_PX) + 'px' }"
            />
            <col v-if="canManage" style="width:80px" />
          </colgroup>
          <thead>
            <tr>
              <th
                v-for="col in shownColumns"
                :key="col.key"
                class="eval-page__th"
                :class="{ 'eval-page__th--center': col.align === 'center' }"
              >
                {{ col.label }}
                <button
                  type="button"
                  class="eval-page__resize"
                  aria-label="Kéo để đổi độ rộng cột"
                  @mousedown.stop="startResize($event, col.key)"
                />
              </th>
              <th v-if="canManage" class="eval-page__th eval-page__th--center">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="entry in tableBodyRows" :key="entry.key">
              <tr
                v-if="entry.kind === 'group'"
                class="eval-page__tr eval-page__tr--group"
                @click.stop="toggleTypeGroup(entry.groupKey)"
              >
                <td :colspan="tableColspan" class="eval-page__td eval-page__td--group">
                  <div
                    class="eval-page__group-inner"
                    :class="entry.scoringAccent ? `eval-page__group-inner--${entry.scoringAccent}` : ''"
                  >
                    <span class="eval-page__group-toggle" aria-hidden="true">
                      <AppIcon
                        :name="entry.collapsed ? 'chevronRight' : 'chevronDown'"
                        :size="16"
                        :stroke-width="1.75"
                      />
                    </span>
                    <span class="eval-page__group-copy">
                      <span class="eval-page__group-title">{{ entry.title }}</span>
                      <span v-if="entry.code" class="eval-page__group-code">{{ entry.code }}</span>
                    </span>
                    <span v-if="entry.scoringKinds.length" class="eval-page__group-badges">
                      <span
                        v-for="kind in entry.scoringKinds"
                        :key="kind"
                        class="eval-page__badge eval-page__badge--group"
                        :class="'eval-page__badge--' + kind"
                      >
                        {{ TYPE_LABELS[kind] ?? kind }}
                      </span>
                    </span>
                    <span class="eval-page__group-count">{{ entry.count }} tiêu chí</span>
                  </div>
                </td>
              </tr>
              <tr
                v-else
                class="eval-page__tr"
                :class="{ 'eval-page__tr--active': selected?.id === entry.criterion.id }"
                @click="openView(entry.criterion)"
              >
                <td v-if="visibleColumns.name" class="eval-page__td">
                  <span class="eval-page__name">{{ entry.criterion.name }}</span>
                </td>
                <td v-if="visibleColumns.level_count" class="eval-page__td eval-page__td--center">
                  {{ entry.criterion.level_count }}
                </td>
                <td v-if="visibleColumns.max_score" class="eval-page__td eval-page__td--center">
                  {{ formatScore(entry.criterion.max_score) }}
                </td>
                <td v-if="visibleColumns.status" class="eval-page__td eval-page__td--center">
                  <span
                    class="eval-page__badge eval-page__badge--cell"
                    :class="entry.criterion.is_active ? 'eval-page__badge--active' : 'eval-page__badge--inactive'"
                  >
                    {{ entry.criterion.is_active ? 'Hoạt động' : 'Không hoạt động' }}
                  </span>
                </td>
                <td
                  v-if="visibleColumns.use_in_evaluation"
                  class="eval-page__td eval-page__td--center"
                >
                  <button
                    type="button"
                    class="eval-page__switch"
                    :class="{ 'eval-page__switch--on': entry.criterion.use_in_evaluation }"
                    role="switch"
                    :aria-checked="entry.criterion.use_in_evaluation ? 'true' : 'false'"
                    aria-label="Dùng trong ĐGNL"
                    :disabled="!canManage || togglingId === entry.criterion.id"
                    @click.stop="toggleUseInEvaluation(entry.criterion)"
                  >
                    <span class="eval-page__switch-thumb" aria-hidden="true" />
                  </button>
                </td>
                <td v-if="visibleColumns.created_at" class="eval-page__td">
                  {{ formatDateTime(entry.criterion.created_at) || '—' }}
                </td>
                <td v-if="visibleColumns.creator" class="eval-page__td eval-page__td--center">
                  <UserAvatarTip :user="entry.criterion.creator" label="Người tạo" />
                </td>
                <td v-if="visibleColumns.updater" class="eval-page__td eval-page__td--center">
                  <UserAvatarTip :user="entry.criterion.updater" label="Người cập nhật" />
                </td>
                <td v-if="visibleColumns.description" class="eval-page__td eval-page__td--desc">
                  {{ entry.criterion.description || '—' }}
                </td>
                <td v-if="canManage" class="eval-page__td eval-page__td--center eval-page__td--action" @click.stop>
                  <span class="eval-page__actions">
                    <button
                      type="button"
                      class="eval-page__icon-btn eval-page__icon-btn--ghost"
                      :aria-label="entry.criterion.is_active ? 'Ẩn tiêu chí' : 'Hiện tiêu chí'"
                      :disabled="togglingId === entry.criterion.id"
                      @click="toggleActive(entry.criterion)"
                    >
                      <AppIcon
                        :name="entry.criterion.is_active ? 'eyeOff' : 'eye'"
                        :size="15"
                      />
                    </button>
                    <button
                      type="button"
                      class="eval-page__icon-btn eval-page__icon-btn--danger"
                      aria-label="Xoá tiêu chí"
                      :disabled="deletingId === entry.criterion.id"
                      @click="confirmDelete = entry.criterion"
                    >
                      <AppIcon name="trash2" :size="15" />
                    </button>
                  </span>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- TablePagesBar dưới -->
      <TablePagesBar
        placement="bottom"
        paging-only
        :from="pagerMeta.from"
        :to="pagerMeta.to"
        :total="pagerMeta.total"
        :page="page"
        :last-page="lastPage"
        :per-page="perPage"
        @update:page="page = $event"
        @update:per-page="perPage = $event; page = 1"
      />
    </div>

    <!-- ── panel chi tiết ────────────────────────────────────────────────── -->
    <aside
      v-if="selected"
      class="eval-page__panel"
      role="complementary"
      aria-label="Chi tiết tiêu chí"
    >
      <div class="eval-page__panel-head">
        <span class="eval-page__panel-title">Chi tiết</span>
        <div style="display:flex;gap:0.375rem;align-items:center">
          <button
            v-if="canManage"
            type="button"
            class="eval-page__panel-btn"
            @click="startEdit"
          >
            <AppIcon name="pencil" :size="14" />
            Sửa
          </button>
          <button
            type="button"
            class="eval-page__panel-btn eval-page__panel-btn--ghost"
            @click="closePanel"
          >
            <AppIcon name="close" :size="14" />
          </button>
        </div>
      </div>

      <div class="eval-page__panel-body hide-scrollbar">
        <dl class="eval-page__dl">
          <div class="eval-page__dl-row">
            <dt>Tên tiêu chí</dt>
            <dd>{{ selected.name }}</dd>
          </div>
          <div class="eval-page__dl-row">
            <dt>Loại tiêu chí</dt>
            <dd>{{ selected.criterion_type?.name || '—' }}</dd>
          </div>
          <div v-if="selected.criterion_type?.code" class="eval-page__dl-row">
            <dt>Mã loại</dt>
            <dd>{{ selected.criterion_type.code }}</dd>
          </div>
          <div v-if="selected.criterion_type?.description" class="eval-page__dl-row">
            <dt>Mô tả loại</dt>
            <dd>{{ selected.criterion_type.description }}</dd>
          </div>
          <div class="eval-page__dl-row">
            <dt>Cách chấm</dt>
            <dd>
              <span class="eval-page__badge" :class="'eval-page__badge--' + selected.type">
                {{ TYPE_LABELS[selected.type] ?? selected.type }}
              </span>
            </dd>
          </div>
          <div v-if="selected.description" class="eval-page__dl-row">
            <dt>Mô tả</dt>
            <dd>{{ selected.description }}</dd>
          </div>
          <div class="eval-page__dl-row">
            <dt>Trạng thái</dt>
            <dd>
              <span
                class="eval-page__badge"
                :class="selected.is_active ? 'eval-page__badge--active' : 'eval-page__badge--inactive'"
              >
                {{ selected.is_active ? 'Hoạt động' : 'Không hoạt động' }}
              </span>
            </dd>
          </div>
          <div class="eval-page__dl-row">
            <dt>Dùng trong ĐGNL</dt>
            <dd>{{ selected.use_in_evaluation ? 'Có' : 'Không' }}</dd>
          </div>
          <div class="eval-page__dl-row">
            <dt>Chấm 0.5</dt>
            <dd>{{ selected.allow_half ? 'Có' : 'Không' }}</dd>
          </div>
          <div class="eval-page__dl-row">
            <dt>Điểm tối đa</dt>
            <dd>{{ formatScore(selected.max_score) }}</dd>
          </div>
          <div class="eval-page__dl-row">
            <dt>Ngày tạo</dt>
            <dd>{{ formatDateTime(selected.created_at) || '—' }}</dd>
          </div>
          <div class="eval-page__dl-row">
            <dt>Người tạo</dt>
            <dd>
              <UserAvatarTip :user="selected.creator" label="Người tạo" />
            </dd>
          </div>
          <div class="eval-page__dl-row">
            <dt>Người cập nhật</dt>
            <dd>
              <UserAvatarTip :user="selected.updater" label="Người cập nhật" />
            </dd>
          </div>
        </dl>

        <h4 class="eval-page__levels-title">
          {{ selected.type === 'scale' ? 'Thang điểm đánh giá' : 'Hành vi & điểm' }}
        </h4>
        <ul class="eval-page__levels-list">
          <li
            v-for="(lv, idx) in (selected.levels ?? [])"
            :key="idx"
            class="eval-page__level-item"
          >
            <span class="eval-page__level-copy">
              <span class="eval-page__level-label">
                <span v-if="lv.code" class="eval-page__level-code">{{ lv.code }}</span>
                {{ lv.label }}
              </span>
              <span v-if="lv.description" class="eval-page__level-note">{{ lv.description }}</span>
            </span>
            <span class="eval-page__level-score" :class="lv.score < 0 ? 'eval-page__level-score--neg' : ''">
              {{ lv.score > 0 ? '+' : '' }}{{ formatScore(lv.score) }}
            </span>
          </li>
        </ul>
      </div>

    </aside>

    <Teleport to="body">
      <Transition name="eval-dialog-fade">
        <div
          v-if="dialogKind === 'criterion'"
          class="eval-page__dialog"
          role="presentation"
          @mousedown.self="closeDialog"
        >
          <div
            class="eval-page__dialog-panel eval-page__dialog-panel--fill"
            role="dialog"
            aria-modal="true"
            aria-labelledby="eval-criterion-form-title"
          >
            <div class="eval-page__dialog-head">
                <span class="eval-page__dialog-icon" aria-hidden="true">
                  <AppIcon
                    :name="dialogTab === 'edit' ? 'pencil' : dialogTab === 'history' ? 'clock' : 'clipboardCheck'"
                    :size="22"
                    :stroke-width="1.75"
                  />
                </span>
                <div class="eval-page__dialog-head-copy">
                  <h2 id="eval-criterion-form-title" class="eval-page__dialog-title">{{ criterionDialogTitle }}</h2>
                </div>
                <button
                  type="button"
                  class="eval-page__dialog-close"
                  aria-label="Đóng"
                  :disabled="formSaving"
                  @click="closeDialog"
                >
                  <AppIcon name="close" :size="16" />
                </button>
              </div>

              <div class="eval-page__dialog-tabs" role="tablist" aria-label="Thêm, sửa hoặc lịch sử tiêu chí">
                <button
                  type="button"
                  class="eval-page__dialog-tab"
                  :class="{ 'eval-page__dialog-tab--active': dialogTab === 'create' }"
                  role="tab"
                  :aria-selected="dialogTab === 'create' ? 'true' : 'false'"
                  :disabled="formSaving"
                  @click="setDialogTab('create')"
                >
                  Thêm
                </button>
                <button
                  type="button"
                  class="eval-page__dialog-tab"
                  :class="{ 'eval-page__dialog-tab--active': dialogTab === 'edit' }"
                  role="tab"
                  :aria-selected="dialogTab === 'edit' ? 'true' : 'false'"
                  :disabled="formSaving"
                  @click="setDialogTab('edit')"
                >
                  Sửa
                </button>
                <button
                  type="button"
                  class="eval-page__dialog-tab"
                  :class="{ 'eval-page__dialog-tab--active': dialogTab === 'history' }"
                  role="tab"
                  :aria-selected="dialogTab === 'history' ? 'true' : 'false'"
                  :disabled="formSaving"
                  @click="setDialogTab('history')"
                >
                  Lịch sử
                </button>
              </div>

              <div
                class="eval-page__dialog-body hide-scrollbar"
                :class="{
                  'eval-page__dialog-body--edit': dialogTab === 'edit',
                  'eval-page__dialog-body--history': dialogTab === 'history',
                }"
              >
                <ol
                  v-if="dialogTab === 'history'"
                  class="eval-page__history hide-scrollbar"
                  :class="{ 'eval-page__history--line': !historyLoading && historyLogs.length > 0 }"
                  aria-label="Lịch sử thay đổi tiêu chí"
                >
                  <li v-if="historyLoading" class="eval-page__history-empty">Đang tải…</li>
                  <li v-else-if="historyLogs.length === 0" class="eval-page__history-empty">
                    Chưa có lịch sử thay đổi tiêu chí.
                  </li>
                  <template v-else>
                    <li
                      v-for="log in historyLogs"
                      :key="log.id"
                      class="eval-page__history-item"
                    >
                    <span
                      class="eval-page__history-dot"
                      :class="'eval-page__history-dot--' + historyVerbTone(log.action)"
                      aria-hidden="true"
                    >
                      <AppIcon :name="historyVerbIcon(log.action)" :size="13" :stroke-width="1.75" />
                    </span>
                    <div class="eval-page__history-card">
                      <div class="eval-page__history-head">
                        <div class="eval-page__history-who">
                          <span class="eval-page__history-name">{{ log.actor_name }}</span>
                          <span class="eval-page__history-verb">{{ log.verb }}</span>
                          <button
                            v-if="log.can_open"
                            type="button"
                            class="eval-page__history-open"
                            aria-label="Mở tiêu chí để sửa"
                            @click="openHistoryCriterion(log)"
                          >
                            <AppIcon name="externalLink" :size="13" :stroke-width="1.75" />
                          </button>
                        </div>
                        <span class="eval-page__history-avatar" aria-hidden="true">
                          <img
                            v-if="log.actor?.avatar_url"
                            :src="log.actor.avatar_url"
                            alt=""
                            class="eval-page__history-avatar-img"
                            referrerpolicy="no-referrer"
                          />
                          <template v-else>{{ historyInitial(log.actor_name) }}</template>
                        </span>
                      </div>
                      <p class="eval-page__history-time">
                        <AppIcon name="clock" :size="13" :stroke-width="1.75" />
                        {{ formatHistoryTime(log.created_at) }}
                      </p>
                      <p class="eval-page__history-detail">{{ log.detail }}</p>
                    </div>
                  </li>
                  </template>
                </ol>

                <template v-else>
                <div v-if="dialogTab === 'edit'" class="eval-page__dialog-list-panel">
                  <label class="eval-page__dialog-label" for="eval-criterion-list-q">Tiêu chí</label>
                  <input
                    id="eval-criterion-list-q"
                    v-model="listFilter"
                    type="search"
                    class="eval-page__dialog-input"
                    placeholder="Lọc theo tên hoặc loại…"
                    autocomplete="off"
                    :disabled="formSaving || allCriteria.length === 0"
                  />
                  <ul class="eval-page__dialog-list hide-scrollbar" role="listbox" aria-label="Danh sách tiêu chí">
                    <li v-if="allCriteria.length === 0" class="eval-page__dialog-list-empty">
                      {{ criterionEmptyMessage }}
                    </li>
                    <li v-else-if="!dialogListHasItems" class="eval-page__dialog-list-empty">
                      Không tìm thấy tiêu chí khớp.
                    </li>
                    <template v-else>
                      <template v-for="group in dialogCriteriaGroups" :key="group.key">
                        <li class="eval-page__dialog-list-group">
                          <button
                            type="button"
                            class="eval-page__dialog-list-group-head"
                            :class="groupScoringAccent(group.criteria) ? `eval-page__dialog-list-group-head--${groupScoringAccent(group.criteria)}` : ''"
                            :aria-expanded="!isTypeGroupCollapsed(dialogTypeGroupCollapsed, group.key) ? 'true' : 'false'"
                            @click="toggleDialogTypeGroup(group.key)"
                          >
                            <span class="eval-page__dialog-list-group-toggle" aria-hidden="true">
                              <AppIcon
                                :name="isTypeGroupCollapsed(dialogTypeGroupCollapsed, group.key) ? 'chevronRight' : 'chevronDown'"
                                :size="15"
                                :stroke-width="1.75"
                              />
                            </span>
                            <span class="eval-page__dialog-list-group-copy">
                              <span class="eval-page__dialog-list-group-title">{{ groupTitle(group.type) }}</span>
                              <span v-if="group.type?.code" class="eval-page__dialog-list-group-code">{{ group.type.code }}</span>
                            </span>
                            <span v-if="groupScoringKinds(group.criteria).length" class="eval-page__dialog-list-group-badges">
                              <span
                                v-for="kind in groupScoringKinds(group.criteria)"
                                :key="kind"
                                class="eval-page__badge eval-page__badge--group"
                                :class="'eval-page__badge--' + kind"
                              >
                                {{ TYPE_LABELS[kind] ?? kind }}
                              </span>
                            </span>
                            <span class="eval-page__dialog-list-group-count">{{ group.criteria.length }}</span>
                          </button>
                        </li>
                        <li
                          v-for="criterion in group.criteria"
                          v-show="!isTypeGroupCollapsed(dialogTypeGroupCollapsed, group.key)"
                          :key="criterion.id"
                          class="eval-page__dialog-list-item"
                          :class="{ 'eval-page__dialog-list-item--active': String(form.id) === String(criterion.id) }"
                          role="option"
                          :aria-selected="String(form.id) === String(criterion.id) ? 'true' : 'false'"
                          @click="form.id = criterion.id"
                        >
                          <span class="eval-page__dialog-list-copy">
                            <span class="eval-page__dialog-list-name">{{ criterion.name }}</span>
                          </span>
                          <span
                            class="eval-page__badge eval-page__badge--dialog-meta"
                            :class="'eval-page__badge--' + criterion.type"
                          >
                            {{ TYPE_LABELS[criterion.type] ?? criterion.type }}
                          </span>
                        </li>
                      </template>
                    </template>
                  </ul>
                </div>

                <div
                  class="eval-page__form eval-page__form--cols hide-scrollbar"
                  :class="{ 'eval-page__form--disabled': dialogTab === 'edit' && !form.id }"
                >
                  <div class="eval-page__form-info">
                    <div class="eval-page__form-field eval-page__form-field--name">
                      <label class="eval-page__form-label" for="eval-criterion-name">
                        Tên tiêu chí <span class="eval-page__required">*</span>
                      </label>
                      <input
                        id="eval-criterion-name"
                        v-model="form.name"
                        type="text"
                        class="eval-page__input"
                        :class="{ 'eval-page__input--error': formErrors.name }"
                        placeholder="VD: Thái độ làm việc…"
                        maxlength="255"
                        :disabled="formSaving || (dialogTab === 'edit' && !form.id)"
                      />
                      <span v-if="formErrors.name" class="eval-page__field-error">
                        {{ Array.isArray(formErrors.name) ? formErrors.name[0] : formErrors.name }}
                      </span>
                    </div>

                    <div class="eval-page__form-field eval-page__form-field--kind">
                      <label class="eval-page__form-label" for="eval-criterion-type">Loại tiêu chí</label>
                      <div class="eval-page__kind-row">
                        <select
                          id="eval-criterion-type"
                          v-model="form.criterion_type_id"
                          class="eval-page__input"
                          :disabled="formSaving || (dialogTab === 'edit' && !form.id)"
                        >
                          <option value="">Chưa chọn loại</option>
                          <option v-for="t in criterionTypes" :key="t.id" :value="String(t.id)">
                            {{ t.code ? `${t.name} · ${t.code}` : t.name }}
                          </option>
                        </select>
                        <button
                          type="button"
                          class="eval-page__kind-add"
                          aria-label="Thêm loại tiêu chí"
                          :disabled="formSaving || (dialogTab === 'edit' && !form.id)"
                          @click="openTypeForm"
                        >
                          <AppIcon name="plus" :size="16" :stroke-width="1.75" />
                        </button>
                      </div>
                    </div>

                    <div class="eval-page__form-field eval-page__form-field--scoring">
                      <span class="eval-page__form-label" id="eval-criterion-scoring-label">
                        Cách chấm <span class="eval-page__required">*</span>
                      </span>
                      <div
                        class="eval-page__seg"
                        role="radiogroup"
                        aria-labelledby="eval-criterion-scoring-label"
                      >
                        <label
                          class="eval-page__seg-opt"
                          :class="{ 'eval-page__seg-opt--on': form.type === 'scale' }"
                        >
                          <input
                            class="eval-page__seg-input"
                            type="radio"
                            value="scale"
                            :checked="form.type === 'scale'"
                            :disabled="formSaving || (dialogTab === 'edit' && !form.id)"
                            @change="changeType('scale')"
                          />
                          Thang điểm
                        </label>
                        <label
                          class="eval-page__seg-opt"
                          :class="{ 'eval-page__seg-opt--on': form.type === 'behavior' }"
                        >
                          <input
                            class="eval-page__seg-input"
                            type="radio"
                            value="behavior"
                            :checked="form.type === 'behavior'"
                            :disabled="formSaving || (dialogTab === 'edit' && !form.id)"
                            @change="changeType('behavior')"
                          />
                          Cộng/trừ
                        </label>
                      </div>
                    </div>

                    <div class="eval-page__form-field eval-page__form-field--desc">
                      <label class="eval-page__form-label" for="eval-criterion-desc">Mô tả</label>
                      <textarea
                        id="eval-criterion-desc"
                        v-model="form.description"
                        class="eval-page__textarea"
                        rows="2"
                        placeholder="Ghi chú cách áp dụng tiêu chí…"
                        maxlength="1000"
                        :disabled="formSaving || (dialogTab === 'edit' && !form.id)"
                      />
                    </div>

                    <div class="eval-page__form-field eval-page__form-field--status">
                      <span class="eval-page__form-label" id="eval-criterion-status-label">Trạng thái</span>
                      <div
                        class="eval-page__seg"
                        role="radiogroup"
                        aria-labelledby="eval-criterion-status-label"
                      >
                        <label
                          class="eval-page__seg-opt"
                          :class="{ 'eval-page__seg-opt--on': form.is_active }"
                        >
                          <input
                            class="eval-page__seg-input"
                            type="radio"
                            :checked="form.is_active"
                            :disabled="formSaving || (dialogTab === 'edit' && !form.id)"
                            @change="form.is_active = true"
                          />
                          Hoạt động
                        </label>
                        <label
                          class="eval-page__seg-opt"
                          :class="{ 'eval-page__seg-opt--on': !form.is_active }"
                        >
                          <input
                            class="eval-page__seg-input"
                            type="radio"
                            :checked="!form.is_active"
                            :disabled="formSaving || (dialogTab === 'edit' && !form.id)"
                            @change="form.is_active = false"
                          />
                          Không hoạt động
                        </label>
                      </div>
                      <div class="eval-page__half-toggle">
                        <span id="eval-use-dgnl-label" class="eval-page__half-toggle-label">Dùng trong ĐGNL</span>
                        <button
                          type="button"
                          class="eval-page__switch"
                          :class="{ 'eval-page__switch--on': form.use_in_evaluation }"
                          role="switch"
                          aria-labelledby="eval-use-dgnl-label"
                          :aria-checked="form.use_in_evaluation ? 'true' : 'false'"
                          :disabled="formSaving || (dialogTab === 'edit' && !form.id)"
                          @click="form.use_in_evaluation = !form.use_in_evaluation"
                        >
                          <span class="eval-page__switch-thumb" aria-hidden="true" />
                        </button>
                      </div>
                    </div>
                  </div>

                  <section class="eval-page__scale" :aria-label="form.type === 'scale' ? 'Thang điểm đánh giá' : 'Hành vi và điểm'">
                    <div class="eval-page__scale-head">
                      <div class="eval-page__scale-title">
                        <span class="eval-page__scale-label">
                          {{ form.type === 'scale' ? 'Thang điểm đánh giá' : 'Hành vi & điểm' }}
                          <span class="eval-page__required">*</span>
                        </span>
                        <span class="eval-page__scale-count">{{ form.levels.length }} mức</span>
                      </div>
                      <div class="eval-page__scale-actions">
                        <div class="eval-page__half-toggle">
                          <span id="eval-allow-half-label" class="eval-page__half-toggle-label">Chấm 0.5</span>
                          <button
                            type="button"
                            class="eval-page__switch"
                            :class="{ 'eval-page__switch--on': form.allow_half }"
                            role="switch"
                            aria-labelledby="eval-allow-half-label"
                            :aria-checked="form.allow_half ? 'true' : 'false'"
                            :disabled="formSaving || (dialogTab === 'edit' && !form.id)"
                            @click="setAllowHalf(!form.allow_half)"
                          >
                            <span class="eval-page__switch-thumb" aria-hidden="true" />
                          </button>
                        </div>
                        <button
                          type="button"
                          class="eval-page__add-level-btn"
                          :disabled="formSaving || (dialogTab === 'edit' && !form.id)"
                          @click="addLevel"
                        >
                          <AppIcon name="plus" :size="13" /> Thêm mức
                        </button>
                      </div>
                    </div>

                    <div
                      v-if="formErrors.levels"
                      class="eval-page__field-error eval-page__field-error--block"
                    >
                      {{ Array.isArray(formErrors.levels) ? formErrors.levels[0] : formErrors.levels }}
                    </div>

                    <div class="eval-page__levels-form hide-scrollbar">
                      <div class="eval-page__levels-head" aria-hidden="true">
                        <span>Mã mức</span>
                        <span>Nhãn mức</span>
                        <span>Mô tả ngắn</span>
                        <span>{{ form.type === 'scale' ? 'Trọng số' : 'Điểm' }}</span>
                        <span></span>
                      </div>
                      <div
                        v-for="(lv, idx) in form.levels"
                        :key="idx"
                        class="eval-page__level-row"
                      >
                        <input
                          v-model="form.levels[idx].code"
                          type="text"
                          class="eval-page__input eval-page__input--code"
                          :placeholder="form.type === 'scale' ? 'M1' : 'H1'"
                          maxlength="20"
                          autocomplete="off"
                          spellcheck="false"
                          :aria-label="'Mã mức ' + (idx + 1)"
                          :disabled="formSaving || (dialogTab === 'edit' && !form.id)"
                        />
                        <input
                          v-model="form.levels[idx].label"
                          type="text"
                          class="eval-page__input"
                          :placeholder="form.type === 'scale' ? 'Nhãn mức' : 'Tên hành vi'"
                          maxlength="100"
                          :aria-label="'Nhãn mức ' + (idx + 1)"
                          :disabled="formSaving || (dialogTab === 'edit' && !form.id)"
                        />
                        <input
                          v-model="form.levels[idx].description"
                          type="text"
                          class="eval-page__input"
                          placeholder="Tuỳ chọn"
                          maxlength="255"
                          :aria-label="'Mô tả ngắn mức ' + (idx + 1)"
                          :disabled="formSaving || (dialogTab === 'edit' && !form.id)"
                        />
                        <input
                          v-model.number="form.levels[idx].score"
                          type="number"
                          class="eval-page__input eval-page__input--score"
                          :placeholder="form.type === 'scale' ? (form.allow_half ? '0.5' : '1') : (form.allow_half ? '±0.5' : '±1')"
                          :min="form.type === 'scale' ? (form.allow_half ? 0.5 : 1) : undefined"
                          :step="form.allow_half ? 0.5 : 1"
                          :aria-label="(form.type === 'scale' ? 'Trọng số' : 'Điểm') + ' mức ' + (idx + 1)"
                          :disabled="formSaving || (dialogTab === 'edit' && !form.id)"
                          @change="form.levels[idx].score = roundScore(form.levels[idx].score)"
                        />
                        <button
                          v-if="form.levels.length > 1"
                          type="button"
                          class="eval-page__icon-btn eval-page__icon-btn--ghost"
                          :aria-label="'Xoá mức ' + (idx + 1)"
                          :disabled="formSaving || (dialogTab === 'edit' && !form.id)"
                          @click="removeLevel(idx)"
                        >
                          <AppIcon name="close" :size="13" />
                        </button>
                        <span v-else class="eval-page__level-spacer" aria-hidden="true"></span>
                      </div>
                    </div>
                  </section>
                </div>
                </template>
              </div>

              <div class="eval-page__dialog-actions">
                <button type="button" class="eval-page__dialog-btn eval-page__dialog-btn--ghost" :disabled="formSaving" @click="closeDialog">
                  {{ dialogTab === 'history' ? 'Đóng' : 'Huỷ' }}
                </button>
                <button
                  v-if="dialogTab !== 'history'"
                  type="button"
                  class="eval-page__dialog-btn eval-page__dialog-btn--primary"
                  :disabled="
                    formSaving ||
                    !form.name.trim() ||
                    (dialogTab === 'edit' && (!form.id || criterionUnchanged || allCriteria.length === 0))
                  "
                  @click="saveCriterion"
                >
                  {{ criterionSubmitLabel }}
                </button>
              </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <Teleport to="body">
      <Transition name="eval-dialog-fade">
        <div
          v-if="typeDialogOpen"
          class="eval-page__dialog eval-page__dialog--nested"
          role="presentation"
          @mousedown.self="closeTypeDialog"
        >
          <div
            class="eval-page__dialog-panel eval-page__dialog-panel--type"
            role="dialog"
            aria-modal="true"
            aria-labelledby="eval-type-form-title"
          >
              <div class="eval-page__dialog-head">
                <span class="eval-page__dialog-icon" aria-hidden="true">
                  <AppIcon name="layers" :size="22" :stroke-width="1.75" />
                </span>
                <div class="eval-page__dialog-head-copy">
                  <h2 id="eval-type-form-title" class="eval-page__dialog-title">Thêm loại tiêu chí mới</h2>
                </div>
                <button
                  type="button"
                  class="eval-page__dialog-close"
                  aria-label="Đóng"
                  :disabled="typeFormSaving"
                  @click="closeTypeDialog"
                >
                  <AppIcon name="close" :size="16" />
                </button>
              </div>

              <div class="eval-page__dialog-body">
                <div class="eval-page__dialog-stack">
                  <div class="eval-page__dialog-field">
                    <label class="eval-page__dialog-label" for="eval-type-name">
                      Tên loại <span class="eval-page__dialog-req" aria-hidden="true">*</span>
                    </label>
                    <div class="eval-page__dialog-control">
                      <input
                        id="eval-type-name"
                        v-model="typeForm.name"
                        type="text"
                        class="eval-page__dialog-input"
                        :class="{ 'eval-page__input--error': typeFormErrors.name }"
                        placeholder="Ví dụ: Thái độ, Năng lực, Kỷ luật…"
                        maxlength="255"
                        autocomplete="off"
                        :disabled="typeFormSaving"
                        @keydown.enter="saveType"
                      />
                      <span v-if="typeFormErrors.name" class="eval-page__field-error">
                        {{ Array.isArray(typeFormErrors.name) ? typeFormErrors.name[0] : typeFormErrors.name }}
                      </span>
                    </div>
                  </div>

                  <div class="eval-page__dialog-field">
                    <label class="eval-page__dialog-label" for="eval-type-code">Mã loại</label>
                    <div class="eval-page__dialog-control">
                      <div class="eval-page__code-row">
                        <input
                          id="eval-type-code"
                          v-model="typeForm.code"
                          type="text"
                          class="eval-page__dialog-input eval-page__input--code"
                          :readonly="typeForm.codeLocked"
                          placeholder="TCA0001"
                          maxlength="40"
                          autocomplete="off"
                          spellcheck="false"
                          :disabled="typeFormSaving"
                        />
                        <button
                          type="button"
                          class="eval-page__lock-btn"
                          :class="{ 'eval-page__lock-btn--open': !typeForm.codeLocked }"
                          :aria-label="typeForm.codeLocked
                            ? 'Mở khoá để nhập mã. Nếu để trống, hệ thống cấp TCA tiếp theo.'
                            : 'Khoá — tự cấp mã dạng TCA0001'"
                          :aria-pressed="!typeForm.codeLocked"
                          :disabled="typeFormSaving"
                          @click="toggleTypeCodeLock"
                        >
                          <AppIcon :name="typeForm.codeLocked ? 'lock' : 'unlock'" :size="14" />
                        </button>
                      </div>
                      <span v-if="typeFormErrors.code" class="eval-page__field-error">
                        {{ Array.isArray(typeFormErrors.code) ? typeFormErrors.code[0] : typeFormErrors.code }}
                      </span>
                    </div>
                  </div>

                  <div class="eval-page__dialog-field">
                    <label class="eval-page__dialog-label" for="eval-type-desc">Mô tả</label>
                    <textarea
                      id="eval-type-desc"
                      v-model="typeForm.description"
                      class="eval-page__textarea"
                      rows="3"
                      placeholder="Mô tả loại tiêu chí này…"
                      maxlength="1000"
                      :disabled="typeFormSaving"
                    />
                  </div>
                </div>
              </div>

              <div class="eval-page__dialog-actions">
                <button type="button" class="eval-page__dialog-btn eval-page__dialog-btn--ghost" :disabled="typeFormSaving" @click="closeTypeDialog">
                  Huỷ
                </button>
                <button
                  type="button"
                  class="eval-page__dialog-btn eval-page__dialog-btn--primary"
                  :disabled="typeFormSaving || !typeForm.name.trim()"
                  @click="saveType"
                >
                  {{ typeSubmitLabel }}
                </button>
              </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ── Confirm xoá ────────────────────────────────────────────────────── -->
    <div v-if="confirmDelete" class="eval-page__confirm-overlay" @click.self="confirmDelete = null">
      <div class="eval-page__confirm" role="alertdialog" aria-modal="true">
        <h3 class="eval-page__confirm-title">Xoá tiêu chí đánh giá</h3>
        <p class="eval-page__confirm-msg">
          Bạn có chắc muốn xoá tiêu chí
          <strong>«{{ confirmDelete.name }}»</strong>?
          Hành động này không thể hoàn tác.
        </p>
        <div class="eval-page__confirm-actions">
          <button
            type="button"
            class="eval-page__btn eval-page__btn--danger"
            @click="doDelete"
          >
            Xoá
          </button>
          <button
            type="button"
            class="eval-page__btn"
            @click="confirmDelete = null"
          >
            Huỷ
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ─── layout ──────────────────────────────────────────────────────────────── */
.eval-page {
  height: 100%;
  display: flex;
  overflow: hidden;
}

.eval-page__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.eval-page--with-panel .eval-page__main {
  flex: 1 1 0;
  min-width: 0;
}

.eval-page__panel {
  flex: 0 0 22rem;
  width: 22rem;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  margin-left: var(--space-3);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
}

/* ─── filters ─────────────────────────────────────────────────────────────── */
.eval-page__toolbar {
  position: relative;
  z-index: 6;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin: 0 0 var(--space-3);
}

.eval-page__filters {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--space-3);
  width: 100%;
}

.eval-page__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
  width: 100%;
}

.eval-page__label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.eval-page__input {
  width: 100%;
  padding: 0.4375rem 0.625rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
}

.eval-page__input:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: -1px;
}

.eval-page__input--error { outline: 2px solid var(--color-danger, #dc2626); outline-offset: -1px; }

.eval-page__textarea {
  width: 100%;
  padding: 0.4375rem 0.625rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  resize: vertical;
}

.eval-page__textarea:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: -1px;
}

/* ─── table ───────────────────────────────────────────────────────────────── */
.eval-page__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.eval-page__table-wrap--resizing {
  cursor: col-resize;
  user-select: none;
}

.eval-page__table {
  width: 100%;
  min-width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: calc(0.875rem * var(--table-zoom, 1));
}

.eval-page__th {
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
  vertical-align: middle;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.eval-page__th--center { text-align: center; }

.eval-page__resize {
  position: absolute;
  top: 0; right: 0;
  z-index: 2;
  width: 0.5rem;
  height: 100%;
  padding: 0;
  border: none;
  background: transparent;
  cursor: col-resize;
}

.eval-page__resize::after {
  content: '';
  position: absolute;
  top: 25%; right: 2px;
  width: 1px;
  height: 50%;
  background: var(--color-border);
  opacity: 0;
  transition: opacity 0.15s;
}

.eval-page__th:hover .eval-page__resize::after { opacity: 1; }

.eval-page__tr { cursor: pointer; }

.eval-page__tr:hover td { background: var(--color-surface-muted); }

.eval-page__tr--active td {
  background: color-mix(in srgb, var(--color-primary) 8%, transparent);
}

.eval-page__tr--group {
  cursor: pointer;
}

.eval-page__tr--group:hover .eval-page__group-inner {
  background: var(--color-surface-muted);
}

.eval-page__td--group {
  padding: 0;
  overflow: visible;
  white-space: normal;
  vertical-align: middle;
}

.eval-page__group-inner {
  position: relative;
  display: flex;
  box-sizing: border-box;
  width: 100%;
  align-items: center;
  gap: 0.625rem;
  padding: 0.5rem var(--space-4);
  padding-left: calc(var(--space-4) + var(--space-2) + 3px);
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text);
  background: color-mix(in srgb, var(--color-text) 4%, var(--color-surface));
  box-shadow: 0 1px 0 var(--color-border);
}

.eval-page__group-inner::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-4);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.eval-page__group-inner--scale::before {
  background: var(--color-primary);
}

.eval-page__group-inner--behavior::before {
  background: var(--color-warning, #d97706);
}

.eval-page__group-inner--mixed::before {
  background: linear-gradient(
    to bottom,
    var(--color-primary) 0%,
    var(--color-primary) 50%,
    var(--color-warning, #d97706) 50%,
    var(--color-warning, #d97706) 100%
  );
}

.eval-page__group-badges {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: 0.375rem;
}

.eval-page__group-toggle {
  display: inline-flex;
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.eval-page__group-copy {
  display: flex;
  min-width: 0;
  flex: 1;
  align-items: baseline;
  gap: 0.5rem;
}

.eval-page__group-title {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.eval-page__group-code {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 500;
}

.eval-page__group-count {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 500;
}

.eval-page__td {
  padding: var(--space-3) var(--space-4);
  vertical-align: middle;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  box-shadow: 0 1px 0 var(--color-border);
}

.eval-page__td--center { text-align: center; }

.eval-page__td--desc {
  max-width: 20ch;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.eval-page__td--action {
  overflow: visible;
}

.eval-page__actions {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.125rem;
  width: 100%;
  vertical-align: middle;
}

.eval-page__name { font-weight: 600; }

.eval-page__kind {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 0;
}

.eval-page__kind-name {
  overflow: hidden;
  text-overflow: ellipsis;
}

.eval-page__kind-code {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-family: var(--font-family-mono, ui-monospace, monospace);
}

.eval-page__kind {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.eval-page__kind-name { font-weight: 500; }

.eval-page__kind-code {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 400;
}

.eval-page__badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  vertical-align: middle;
  padding: 0.125rem 0.5rem;
  border-radius: 0;
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.4;
  white-space: nowrap;
}

.eval-page__badge--cell {
  box-sizing: border-box;
  width: 9rem;
  max-width: 100%;
}

.eval-page__badge--group {
  padding: 0.0625rem 0.4375rem;
  font-size: 0.6875rem;
}

.eval-page__badge--dialog-meta {
  flex-shrink: 0;
  min-width: 4.75rem;
  justify-content: center;
  font-size: 0.6875rem;
}

.eval-page__badge--scale   { background: color-mix(in srgb, var(--color-primary) 12%, transparent); color: var(--color-primary); }
.eval-page__badge--behavior { background: color-mix(in srgb, var(--color-warning, #d97706) 12%, transparent); color: var(--color-warning, #d97706); }
.eval-page__badge--active   { background: color-mix(in srgb, var(--color-success, #16a34a) 12%, transparent); color: var(--color-success, #16a34a); }
.eval-page__badge--inactive { background: color-mix(in srgb, var(--color-text-muted) 12%, transparent); color: var(--color-text-muted); }

.eval-page__empty {
  margin: 2rem auto;
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.eval-page__icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  cursor: pointer;
}

.eval-page__icon-btn--ghost { color: var(--color-text-muted); }
.eval-page__icon-btn--ghost:hover:not(:disabled) { background: var(--color-surface-muted); color: var(--color-text); }
.eval-page__icon-btn--danger { color: var(--color-danger, #dc2626); }
.eval-page__icon-btn--danger:hover:not(:disabled) { background: color-mix(in srgb, var(--color-danger, #dc2626) 10%, transparent); }
.eval-page__icon-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.eval-page__check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.eval-page__note {
  flex-shrink: 0;
  margin: 0 0 var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

/* ─── panel ───────────────────────────────────────────────────────────────── */
.eval-page__panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
  flex-shrink: 0;
}

.eval-page__panel-title {
  font-weight: 700;
  font-size: 0.9375rem;
}

.eval-page__panel-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  height: 1.75rem;
  padding: 0 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.eval-page__panel-btn--ghost {
  background: transparent;
  box-shadow: none;
  color: var(--color-text-muted);
}

.eval-page__panel-btn:hover:not(:disabled) { background: var(--color-surface); }

.eval-page__panel-body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: var(--space-4);
}

/* view mode */
.eval-page__dl { margin: 0 0 var(--space-4); }

.eval-page__dl-row {
  display: flex;
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  gap: var(--space-3);
}

.eval-page__dl-row dt {
  flex: 0 0 8rem;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.eval-page__dl-row dd {
  flex: 1;
  font-size: 0.8125rem;
  font-weight: 500;
  margin: 0;
}

.eval-page__levels-title {
  margin: 0 0 var(--space-2);
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.eval-page__levels-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.eval-page__level-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  box-shadow: 0 1px 0 var(--color-border);
}

.eval-page__level-item:last-child { box-shadow: none; }

.eval-page__level-copy {
  display: flex;
  min-width: 0;
  flex: 1;
  flex-direction: column;
  gap: 0.125rem;
}

.eval-page__level-label {
  display: flex;
  align-items: baseline;
  gap: var(--space-2);
  min-width: 0;
}

.eval-page__level-code {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-family: var(--font-family-mono, ui-monospace, monospace);
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.04em;
}

.eval-page__level-note {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.eval-page__level-score {
  font-weight: 700;
  color: var(--color-success, #16a34a);
}

.eval-page__level-score--neg { color: var(--color-danger, #dc2626); }

/* form mode */
.eval-page__form { display: flex; flex-direction: column; gap: var(--space-4); }

.eval-page__form--cols {
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
  flex: 1;
  min-width: 0;
  min-height: 0;
}

.eval-page__form-info {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  grid-template-areas:
    "name type scoring"
    "desc desc status";
  gap: var(--space-3) var(--space-4);
  flex-shrink: 0;
  align-items: start;
}

.eval-page__form-field {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  min-width: 0;
}

.eval-page__form-field--name { grid-area: name; }
.eval-page__form-field--kind { grid-area: type; }
.eval-page__form-field--scoring { grid-area: scoring; }
.eval-page__form-field--desc { grid-area: desc; }
.eval-page__form-field--status { grid-area: status; }

.eval-page__kind-row {
  display: flex;
  align-items: stretch;
  gap: var(--space-2);
  min-width: 0;
}

.eval-page__kind-row .eval-page__input {
  flex: 1;
  min-width: 0;
}

.eval-page__kind-add {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  cursor: pointer;
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 22%, transparent);
}

.eval-page__kind-add:hover:not(:disabled) {
  background: var(--color-primary-surface-strong);
}

.eval-page__kind-add:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.eval-page__form-info .eval-page__form-label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.01em;
}

.eval-page__form-label {
  font-size: 0.8125rem;
  font-weight: 600;
}

.eval-page__required { color: var(--color-primary); }
.eval-page__muted    { font-weight: 400; color: var(--color-text-muted); }

.eval-page__field-error {
  font-size: 0.75rem;
  color: var(--color-danger, #dc2626);
}

.eval-page__field-error--block { display: block; }

.eval-page__seg {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.2rem;
  padding: 0.2rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.eval-page__seg-opt {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 2.125rem;
  padding: 0 0.75rem;
  border-radius: calc(var(--radius-md) - 2px);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.eval-page__seg-opt--on {
  background: var(--color-surface);
  color: var(--color-primary);
  box-shadow: var(--shadow-sm);
}

.eval-page__seg-input {
  position: absolute;
  inset: 0;
  opacity: 0;
  margin: 0;
  cursor: pointer;
}

.eval-page__form--cols .eval-page__textarea {
  min-height: 2.5rem;
  resize: none;
}

.eval-page__scale {
  position: relative;
  display: flex;
  flex: 1;
  min-height: 0;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4);
  padding-left: calc(var(--space-2) + 3px + var(--space-4));
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.eval-page__scale::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-primary);
}

.eval-page__scale-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
}

.eval-page__scale-actions {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: var(--space-3);
}

.eval-page__half-toggle {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
}

.eval-page__form-field--status .eval-page__half-toggle {
  justify-content: space-between;
}

.eval-page__half-toggle-label {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-weight: 600;
}

.eval-page__switch {
  position: relative;
  flex-shrink: 0;
  width: 2.25rem;
  height: 1.25rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-text-muted) 35%, var(--color-surface-muted));
  cursor: pointer;
}

.eval-page__switch--on {
  background: var(--color-primary);
}

.eval-page__switch:hover:not(:disabled) {
  filter: brightness(0.96);
}

.eval-page__switch:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.eval-page__switch-thumb {
  position: absolute;
  top: 0.125rem;
  left: 0.125rem;
  width: 1rem;
  height: 1rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  transition: transform 0.15s ease;
}

.eval-page__switch--on .eval-page__switch-thumb {
  transform: translateX(1rem);
}

@media (prefers-reduced-motion: reduce) {
  .eval-page__switch-thumb {
    transition: none;
  }
}

.eval-page__scale-title {
  display: flex;
  min-width: 0;
  align-items: baseline;
  gap: var(--space-3);
}

.eval-page__scale-label {
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 700;
}

.eval-page__scale-count {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 500;
}

.eval-page__code-row {
  display: flex;
  align-items: stretch;
  gap: var(--space-2);
}

.eval-page__input--code {
  font-family: var(--font-family-mono, ui-monospace, monospace);
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.eval-page__input--code[readonly] {
  color: var(--color-text-muted);
  background: color-mix(in srgb, var(--color-surface-muted) 70%, var(--color-surface));
}

.eval-page__lock-btn {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text-muted);
  cursor: pointer;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.eval-page__lock-btn:hover {
  color: var(--color-text);
  background: var(--color-surface-muted);
}

.eval-page__lock-btn--open {
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1px var(--color-primary);
}

.eval-page__levels-form {
  display: flex;
  flex: 1;
  min-width: 0;
  min-height: 0;
  flex-direction: column;
  gap: 0.25rem;
  overflow: auto;
  padding: var(--space-2);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.eval-page__levels-head,
.eval-page__level-row {
  display: grid;
  grid-template-columns: 5.25rem minmax(8rem, 1fr) minmax(10rem, 1.5fr) 5.25rem 2rem;
  gap: var(--space-2);
  align-items: center;
}

.eval-page__levels-head {
  padding: 0.35rem 0.4rem;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  box-shadow: 0 1px 0 var(--color-border);
}

.eval-page__level-row {
  padding: 0.25rem 0.4rem;
  border-radius: var(--radius-sm);
}

.eval-page__level-row:hover {
  background: color-mix(in srgb, var(--color-primary) 4%, transparent);
}

.eval-page__level-row .eval-page__input--code {
  text-align: center;
  font-weight: 600;
  background: var(--color-surface-muted);
}

.eval-page__input--score {
  width: 100%;
  text-align: center;
  font-weight: 700;
  color: var(--color-primary);
  background: var(--color-primary-surface);
  appearance: textfield;
}

.eval-page__input--score::-webkit-outer-spin-button,
.eval-page__input--score::-webkit-inner-spin-button {
  appearance: none;
  margin: 0;
}

.eval-page__level-spacer {
  width: 1.75rem;
  height: 1.75rem;
}

.eval-page__add-level-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  height: 2rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 22%, transparent);
}

.eval-page__add-level-btn:hover { background: var(--color-primary-surface-strong); }
.eval-page__add-level-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.eval-page__form-actions {
  display: flex;
  gap: var(--space-2);
  padding-top: var(--space-2);
}

.eval-page__btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  height: 2rem;
  padding: 0 0.875rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
}

.eval-page__btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary, #fff);
  box-shadow: var(--shadow-sm);
}

.eval-page__btn--danger {
  background: var(--color-danger, #dc2626);
  color: #fff;
  box-shadow: none;
}

.eval-page__btn:hover:not(:disabled) { filter: brightness(0.95); }
.eval-page__btn:disabled { opacity: 0.6; cursor: not-allowed; }

.eval-page__spin { animation: eval-spin 0.8s linear infinite; }

@keyframes eval-spin { to { transform: rotate(360deg); } }

.eval-page__form--disabled {
  opacity: 0.65;
  pointer-events: none;
}

.eval-page__lock-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* ─── dialog (giống thêm nhóm / gán vai trò) ──────────────────────────────── */
.eval-page__dialog {
  position: fixed;
  inset: 0;
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.625rem;
  background: var(--color-sidebar-overlay);
}

.eval-page__dialog--nested {
  z-index: 310;
}

.eval-page__dialog-panel {
  width: min(48rem, 100%);
  max-height: min(46rem, calc(100vh - 2.5rem));
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  padding: 1.5rem 1.75rem 1.25rem;
  overflow: auto;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.eval-page__dialog-panel--fill {
  width: calc(100vw - 1.25rem);
  max-width: calc(100vw - 1.25rem);
  height: calc(100vh - 1.25rem);
  max-height: calc(100vh - 1.25rem);
  overflow: hidden;
}

.eval-page__dialog-panel--type {
  width: min(40rem, calc(100vw - 2.5rem));
  max-height: calc(100vh - 2.5rem);
}

.eval-page__dialog-head,
.eval-page__dialog-tabs,
.eval-page__dialog-actions {
  flex-shrink: 0;
}

.eval-page__dialog-head {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.eval-page__dialog-icon {
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

.eval-page__dialog-head-copy {
  flex: 1;
  min-width: 0;
}

.eval-page__dialog-title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.25rem;
  font-weight: 700;
  line-height: 1.35;
}

.eval-page__dialog-close {
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

.eval-page__dialog-close:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.eval-page__dialog-close:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.eval-page__dialog-tabs {
  display: flex;
  gap: var(--space-2);
  box-shadow: 0 1px 0 var(--color-border);
}

.eval-page__dialog-tab {
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

.eval-page__dialog-tab--active {
  color: var(--color-primary);
  box-shadow: 0 2px 0 var(--color-primary);
}

.eval-page__dialog-tab:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.eval-page__dialog-body {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  min-width: 0;
}

.eval-page__dialog-panel--fill .eval-page__dialog-body {
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.eval-page__dialog-panel--fill .eval-page__form--cols {
  overflow: auto;
  padding: 2px;
  box-sizing: border-box;
}

.eval-page__dialog-body--edit {
  display: grid;
  grid-template-columns: minmax(26rem, 36rem) minmax(0, 1fr);
  gap: var(--space-5);
  align-items: start;
}

.eval-page__dialog-panel--fill .eval-page__dialog-body--edit {
  align-items: stretch;
}

.eval-page__dialog-body--edit .eval-page__form-info {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  grid-template-areas:
    "name type"
    "scoring status"
    "desc desc";
}

.eval-page__dialog-panel--fill .eval-page__dialog-list {
  max-height: none;
}

.eval-page__dialog-panel--fill .eval-page__dialog-list-panel {
  min-height: 0;
}

.eval-page__dialog-panel--fill .eval-page__dialog-body--history {
  overflow: auto;
}

.eval-page__history {
  position: relative;
  max-width: 42rem;
  margin: 0;
  padding: 0.25rem 0 0.5rem;
  list-style: none;
}

.eval-page__history--line::before {
  content: '';
  position: absolute;
  top: 1.15rem;
  bottom: 1.15rem;
  left: 0.6875rem;
  width: 1px;
  background: var(--color-border);
}

.eval-page__history-empty {
  padding: 1.25rem 0.25rem;
  color: var(--color-text-muted);
  font-size: 0.875rem;
  line-height: 1.5;
}

.eval-page__history-item {
  position: relative;
  display: flex;
  gap: 0.875rem;
  padding-bottom: 0.875rem;
}

.eval-page__history-item:last-child {
  padding-bottom: 0;
}

.eval-page__history-dot {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 1.5rem;
  height: 1.5rem;
  margin-top: 0.35rem;
  border-radius: var(--radius-full);
  background: var(--color-info);
  color: var(--color-on-primary);
  box-shadow: 0 0 0 3px var(--color-surface);
}

.eval-page__history-dot--success {
  background: var(--color-success, #16a34a);
}

.eval-page__history-dot--danger {
  background: var(--color-danger, #dc2626);
}

.eval-page__history-dot--info {
  background: var(--color-info);
}

.eval-page__history-card {
  flex: 1;
  min-width: 0;
  padding: 0.75rem 0.875rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.eval-page__history-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
}

.eval-page__history-who {
  display: flex;
  min-width: 0;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem;
}

.eval-page__history-name {
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 700;
  line-height: 1.35;
}

.eval-page__history-verb {
  color: var(--color-text-muted);
  font-size: 0.875rem;
  font-weight: 500;
}

.eval-page__history-open {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.25rem;
  height: 1.25rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-info);
  cursor: pointer;
}

.eval-page__history-open:hover {
  background: color-mix(in srgb, var(--color-info) 12%, transparent);
}

.eval-page__history-avatar {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 2rem;
  height: 2rem;
  overflow: hidden;
  border-radius: var(--radius-full);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-size: 0.75rem;
  font-weight: 700;
  line-height: 1;
}

.eval-page__history-avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.eval-page__history-time {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  margin: 0.375rem 0 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.4;
}

.eval-page__history-detail {
  margin: 0.5rem 0 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.5;
}

.eval-page__dialog-list-panel {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-width: 0;
  padding-inline: 2px;
  box-sizing: border-box;
}

.eval-page__dialog-list {
  flex: 1;
  min-height: 12rem;
  max-height: 22rem;
  overflow: auto;
  margin: 0;
  padding: 0.25rem;
  list-style: none;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}

.eval-page__dialog-list-empty {
  padding: 0.875rem 0.75rem;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.5;
}

.eval-page__dialog-list-group {
  list-style: none;
}

.eval-page__dialog-list-group-head {
  position: relative;
  display: flex;
  width: 100%;
  align-items: center;
  gap: 0.5rem;
  margin: 0.125rem 0;
  padding: 0.4375rem 0.5rem;
  padding-left: calc(0.5rem + var(--space-2) + 3px);
  border: none;
  border-radius: var(--radius-sm);
  background: color-mix(in srgb, var(--color-text) 4%, var(--color-surface));
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
  text-align: left;
  cursor: pointer;
}

.eval-page__dialog-list-group-head::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: 0.5rem;
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.eval-page__dialog-list-group-head--scale::before {
  background: var(--color-primary);
}

.eval-page__dialog-list-group-head--behavior::before {
  background: var(--color-warning, #d97706);
}

.eval-page__dialog-list-group-head--mixed::before {
  background: linear-gradient(
    to bottom,
    var(--color-primary) 0%,
    var(--color-primary) 50%,
    var(--color-warning, #d97706) 50%,
    var(--color-warning, #d97706) 100%
  );
}

.eval-page__dialog-list-group-head:hover {
  background: var(--color-surface-muted);
}

.eval-page__dialog-list-group-toggle {
  display: inline-flex;
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.eval-page__dialog-list-group-copy {
  display: flex;
  min-width: 0;
  flex: 1;
  align-items: baseline;
  gap: 0.375rem;
}

.eval-page__dialog-list-group-title,
.eval-page__dialog-list-group-code {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.eval-page__dialog-list-group-code {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 500;
}

.eval-page__dialog-list-group-badges {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: 0.25rem;
}

.eval-page__dialog-list-group-count {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 500;
}

.eval-page__dialog-list-item {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.5rem 0.625rem;
  border-radius: var(--radius-sm);
  cursor: pointer;
}

.eval-page__dialog-list-item:hover {
  background: var(--color-surface-muted);
}

.eval-page__dialog-list-item--active {
  background: color-mix(in srgb, var(--color-primary) 10%, var(--color-surface));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 28%, transparent);
}

.eval-page__dialog-list-copy {
  display: flex;
  min-width: 0;
  flex: 1;
  flex-direction: column;
}

.eval-page__dialog-list-name,
.eval-page__dialog-list-sub,
.eval-page__dialog-list-meta {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.eval-page__dialog-list-name {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.35;
}

.eval-page__dialog-list-sub {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.35;
}

.eval-page__dialog-stack {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  min-width: 0;
}

.eval-page__dialog-field {
  display: grid;
  grid-template-columns: 7.5rem minmax(0, 1fr);
  column-gap: 0.875rem;
  row-gap: 0.375rem;
  align-items: start;
  min-width: 0;
}

.eval-page__dialog-label {
  padding-top: 0.65rem;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.3;
}

.eval-page__dialog-list-panel > .eval-page__dialog-label {
  padding-top: 0;
}

.eval-page__dialog-field > :not(.eval-page__dialog-label) {
  min-width: 0;
}

.eval-page__dialog-req {
  color: var(--color-primary);
}

.eval-page__dialog-control {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  min-width: 0;
}

.eval-page__dialog-input {
  width: 100%;
  min-width: 0;
  min-height: 2.75rem;
  padding: 0.625rem 0.875rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.9375rem;
}

.eval-page__dialog-input:focus {
  outline: 2px solid color-mix(in srgb, var(--color-primary) 35%, transparent);
  outline-offset: 1px;
  border-color: var(--color-primary);
}

.eval-page__dialog-input::placeholder {
  color: var(--color-text-muted);
}

.eval-page__dialog-input:disabled {
  opacity: 0.65;
  cursor: not-allowed;
  background: var(--color-surface-muted);
}

.eval-page__code-row .eval-page__lock-btn {
  min-height: 2.75rem;
}

.eval-page__dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

.eval-page__dialog-btn {
  padding: 0.625rem 1.25rem;
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.eval-page__dialog-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.eval-page__dialog-btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.eval-page__dialog-btn--primary:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.eval-page__dialog-btn--ghost {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
}

.eval-page__dialog-btn--ghost:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.eval-dialog-fade-enter-active,
.eval-dialog-fade-leave-active {
  transition: opacity 0.15s ease;
}

.eval-dialog-fade-enter-from,
.eval-dialog-fade-leave-to {
  opacity: 0;
}

/* ─── confirm dialog ────────────────────────────────────────────────────────── */
.eval-page__confirm-overlay {
  position: fixed;
  inset: 0;
  z-index: 50;
  background: rgb(0 0 0 / 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
}

.eval-page__confirm {
  width: min(90vw, 26rem);
  padding: var(--space-6);
  border-radius: var(--radius-xl);
  background: var(--color-surface);
  box-shadow: var(--shadow-xl);
}

.eval-page__confirm-title {
  margin: 0 0 var(--space-3);
  font-size: 1rem;
  font-weight: 700;
}

.eval-page__confirm-msg {
  margin: 0 0 var(--space-5);
  font-size: 0.875rem;
  color: var(--color-text-muted);
  line-height: 1.6;
}

.eval-page__confirm-actions { display: flex; gap: var(--space-2); }

/* ─── responsive ────────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
  .eval-page { flex-direction: column; }

  .eval-page__panel {
    flex: 0 0 auto;
    width: 100%;
    max-height: 46%;
    margin-left: 0;
    margin-top: var(--space-3);
  }

  .eval-page__filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--space-2);
  }

  .eval-page__form-info,
  .eval-page__dialog-body--edit .eval-page__form-info {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    grid-template-areas:
      "name type"
      "scoring status"
      "desc desc";
  }

  .eval-page__dialog-body--edit {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 640px) {
  .eval-page__dialog {
    padding: var(--space-4);
    align-items: flex-end;
  }

  .eval-page__dialog-panel,
  .eval-page__dialog-panel--fill,
  .eval-page__dialog-panel--type {
    max-width: 100%;
    max-height: min(92vh, calc(100vh - 2rem));
    padding: var(--space-4);
  }

  .eval-page__dialog-panel--fill {
    width: 100%;
    height: min(94vh, calc(100vh - 1.25rem));
    max-height: min(94vh, calc(100vh - 1.25rem));
  }

  .eval-page__form-info,
  .eval-page__dialog-body--edit .eval-page__form-info {
    grid-template-columns: minmax(0, 1fr);
    grid-template-areas:
      "name"
      "type"
      "scoring"
      "desc"
      "status";
  }

  .eval-page__levels-head,
  .eval-page__level-row {
    grid-template-columns: 4.5rem minmax(7rem, 1fr) minmax(8rem, 1.2fr) 4.5rem 2rem;
  }

  .eval-page__dialog-field {
    grid-template-columns: minmax(0, 1fr);
  }

  .eval-page__dialog-label {
    padding-top: 0;
  }
}

@media (max-width: 480px) {
  .eval-page__filters { grid-template-columns: minmax(0, 1fr); }
  .eval-page__dl-row { flex-direction: column; gap: var(--space-1); }
  .eval-page__dl-row dt { flex: none; }
}

@media (prefers-reduced-motion: reduce) {
  .eval-dialog-fade-enter-active,
  .eval-dialog-fade-leave-active {
    transition: none;
  }
}
</style>
