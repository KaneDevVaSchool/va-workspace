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
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';

// ─── constants ───────────────────────────────────────────────────────────────

const COLUMNS = [
  { key: 'name',        label: 'Tên tiêu chí',     defaultOn: true  },
  { key: 'type',        label: 'Kiểu',              defaultOn: true  },
  { key: 'level_count', label: 'Số mức',            defaultOn: true  },
  { key: 'max_score',   label: 'Điểm tối đa',       defaultOn: true  },
  { key: 'status',      label: 'Trạng thái',        defaultOn: true  },
  { key: 'description', label: 'Mô tả',             defaultOn: false },
];

const TYPE_LABELS = { scale: 'Thang điểm', behavior: 'Cộng/trừ' };
const COL_KEY     = 'va-eval-criteria-columns-v1';
const WIDTH_KEY   = 'va-eval-criteria-widths';
const ZOOM_KEY    = 'va-eval-criteria-zoom';

const CELL_PAD_X = 32;
const COL_EXTRA  = 24;
let measureCtx   = null;
let wrapObserver = null;

// ─── stores / inject ─────────────────────────────────────────────────────────

const auth = useAuthStore();
const hub  = inject('workspaceConfigHub', null);

// ─── state ───────────────────────────────────────────────────────────────────

const allCriteria = ref([]);
const loading     = ref(false);
const selected    = ref(null); // criterion đang xem/sửa
const panelMode   = ref('view'); // 'view' | 'edit' | 'add'

const query     = ref('');
const typeFilter = ref('');
const statusFilter = ref('');
const page      = ref(1);
const perPage   = ref(20);

const visibleColumns = reactive(loadVisibility(COL_KEY, COLUMNS));

const tableWrap  = ref(null);
const resizing   = ref(false);
const MIN_COL_PX = 80;
const colWidths  = reactive(loadWidths());
const tableZoom  = ref(loadZoom());

// ─── form state ──────────────────────────────────────────────────────────────

const form = reactive({
  name:        '',
  type:        'scale',
  description: '',
  is_active:   true,
  levels:      [],
});
const formErrors  = ref({});
const formSaving  = ref(false);
const deletingId  = ref(null);
const togglingId  = ref(null);
const confirmDelete = ref(null); // criterion về to delete

// ─── computed ─────────────────────────────────────────────────────────────────

const hasDepartment = computed(() => Boolean(auth.user?.department?.id));

const canManage = computed(() =>
  auth.can('evaluation.manage_department'),
);

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase();
  return allCriteria.value.filter((c) => {
    if (q && !c.name.toLowerCase().includes(q)) return false;
    if (typeFilter.value && c.type !== typeFilter.value) return false;
    if (statusFilter.value === 'active'   && !c.is_active) return false;
    if (statusFilter.value === 'inactive' &&  c.is_active) return false;
    return true;
  });
});

const lastPage = computed(() =>
  Math.max(1, Math.ceil(filtered.value.length / perPage.value)),
);

const paginated = computed(() => {
  const start = (page.value - 1) * perPage.value;
  return filtered.value.slice(start, start + perPage.value);
});

const pagerMeta = computed(() => {
  const total = filtered.value.length;
  const start = Math.min((page.value - 1) * perPage.value + 1, total || 0);
  const end   = Math.min(page.value * perPage.value, total);
  return { from: total ? start : 0, to: total ? end : 0, total };
});

const hasActiveFilters = computed(() =>
  Boolean(query.value || typeFilter.value || statusFilter.value),
);

const shownColumns = computed(() =>
  COLUMNS.filter((col) => visibleColumns[col.key]),
);

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
    for (const row of allCriteria.value) {
      let val = '';
      if (col.key === 'name')        val = row.name ?? '';
      if (col.key === 'type')        val = TYPE_LABELS[row.type] ?? '';
      if (col.key === 'level_count') val = String(row.level_count ?? 0);
      if (col.key === 'max_score')   val = String(row.max_score ?? 0);
      if (col.key === 'status')      val = row.is_active ? 'Đang dùng' : 'Tắt';
      if (col.key === 'description') val = row.description ?? '';
      const w = measureText(val) + CELL_PAD_X;
      if (w > max) max = w;
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
  if (!hasDepartment.value) { allCriteria.value = []; return; }
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/evaluation/criteria');
    allCriteria.value = data.criteria ?? [];
    await nextTick();
    computeDefaultWidths();
  } catch (err) {
    const msg = err?.response?.data?.message;
    showClientToast('error', msg || 'Không tải được danh sách tiêu chí.');
  } finally {
    loading.value = false;
  }
}

async function saveCriterion() {
  formErrors.value = {};
  formSaving.value = true;

  const payload = {
    name:        form.name,
    type:        form.type,
    description: form.description || null,
    is_active:   form.is_active,
    levels:      form.levels.filter((l) => l.label.trim() !== ''),
  };

  try {
    if (panelMode.value === 'add') {
      const { data } = await window.axios.post('/api/evaluation/criteria', payload);
      allCriteria.value.unshift(data.criterion);
      showClientToast('success', `Đã tạo tiêu chí "${data.criterion.name}".`);
      closePanel();
    } else {
      const { data } = await window.axios.put(
        `/api/evaluation/criteria/${selected.value.id}`,
        payload,
      );
      const idx = allCriteria.value.findIndex((c) => c.id === data.criterion.id);
      if (idx !== -1) allCriteria.value[idx] = data.criterion;
      selected.value = data.criterion;
      panelMode.value = 'view';
      showClientToast('success', `Đã lưu tiêu chí "${data.criterion.name}".`);
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

// ─── panel helpers ───────────────────────────────────────────────────────────

function openAdd() {
  selected.value = null;
  form.name        = '';
  form.type        = 'scale';
  form.description = '';
  form.is_active   = true;
  form.levels      = defaultLevels('scale');
  formErrors.value = {};
  panelMode.value  = 'add';
}

function openView(criterion) {
  selected.value   = criterion;
  panelMode.value  = 'view';
  formErrors.value = {};
}

function startEdit() {
  if (!selected.value) return;
  form.name        = selected.value.name;
  form.type        = selected.value.type;
  form.description = selected.value.description ?? '';
  form.is_active   = selected.value.is_active;
  form.levels      = (selected.value.levels ?? []).map((l) => ({ ...l }));
  formErrors.value = {};
  panelMode.value  = 'edit';
}

function closePanel() {
  selected.value  = null;
  panelMode.value = 'view';
  formErrors.value = {};
  registerPrimaryAction();
}

function defaultLevels(type) {
  if (type === 'scale') {
    return [
      { label: 'Xuất sắc', score: 5 },
      { label: 'Tốt',      score: 4 },
      { label: 'Khá',      score: 3 },
      { label: 'Trung bình', score: 2 },
      { label: 'Yếu',      score: 1 },
    ];
  }
  return [{ label: '', score: 1 }];
}

function changeType(type) {
  if (form.type === type) return;
  form.type = type;
  form.levels = defaultLevels(type);
}

function addLevel() {
  const last = form.levels[form.levels.length - 1];
  const nextScore = form.type === 'scale'
    ? Math.max(1, (last?.score ?? 2) - 1)
    : 1;
  form.levels.push({ label: '', score: nextScore });
}

function removeLevel(idx) {
  form.levels.splice(idx, 1);
}

// PageHeader gọi primaryAction.onClick — không phải action.
function registerPrimaryAction() {
  if (!canManage.value) {
    hub?.clearPrimaryAction?.();
    return;
  }
  hub?.setPrimaryAction?.({
    label: 'Thêm tiêu chí',
    icon:  'plus',
    onClick() { openAdd(); },
  });
}

// Đóng confirm delete khi Escape
function handleKeydown(e) {
  if (e.key === 'Escape') {
    if (confirmDelete.value) { confirmDelete.value = null; return; }
    if (panelMode.value !== 'view') { closePanel(); }
  }
}

// reset page khi filter thay đổi
watch([query, typeFilter, statusFilter], () => { page.value = 1; });

watch(canManage, () => registerPrimaryAction());

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
  <div class="eval-page" :class="{ 'eval-page--with-panel': selected || panelMode === 'add' }">
    <!-- ── main area ──────────────────────────────────────────────────────── -->
    <div class="eval-page__main">

      <!-- Bộ lọc -->
      <div v-if="Object.values(visibleColumns).some(Boolean)" class="eval-page__filters">
        <div class="eval-page__field">
          <label class="eval-page__label">Tìm kiếm</label>
          <input
            v-model="query"
            type="text"
            class="eval-page__input"
            placeholder="Tên tiêu chí…"
          />
        </div>
        <div class="eval-page__field">
          <label class="eval-page__label">Kiểu</label>
          <select v-model="typeFilter" class="eval-page__input">
            <option value="">Tất cả kiểu</option>
            <option value="scale">Thang điểm</option>
            <option value="behavior">Cộng/trừ</option>
          </select>
        </div>
        <div class="eval-page__field">
          <label class="eval-page__label">Trạng thái</label>
          <select v-model="statusFilter" class="eval-page__input">
            <option value="">Tất cả trạng thái</option>
            <option value="active">Đang dùng</option>
            <option value="inactive">Tắt</option>
          </select>
        </div>
      </div>

      <!-- TablePagesBar trên -->
      <TablePagesBar
        placement="top"
        :from="pagerMeta.from"
        :to="pagerMeta.to"
        :total="pagerMeta.total"
        :page="page"
        :last-page="lastPage"
        :per-page="perPage"
        :zoom="tableZoom"
        :show-search="false"
        :show-clear-filters="hasActiveFilters"
        :filters-active="hasActiveFilters"
        @update:page="page = $event"
        @update:per-page="perPage = $event; page = 1"
        @update:zoom="tableZoom = $event; saveZoom($event)"
        @clear-filters="query = ''; typeFilter = ''; statusFilter = ''"
      >
        <template #settings>
          <div class="eval-page__col-toggle">
            <label
              v-for="col in COLUMNS"
              :key="col.key"
              class="eval-page__col-opt"
            >
              <input
                type="checkbox"
                :checked="visibleColumns[col.key]"
                @change="
                  visibleColumns[col.key] = $event.target.checked;
                  saveVisibility(COL_KEY, visibleColumns);
                "
              />
              {{ col.label }}
            </label>
          </div>
        </template>
      </TablePagesBar>

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
          <template v-if="canManage"> Bấm <strong>Thêm tiêu chí</strong> để bắt đầu.</template>
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
              >
                {{ col.label }}
                <button
                  type="button"
                  class="eval-page__resize"
                  aria-label="Kéo để đổi độ rộng cột"
                  @mousedown.stop="startResize($event, col.key)"
                />
              </th>
              <th v-if="canManage" class="eval-page__th eval-page__th--action">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="criterion in paginated"
              :key="criterion.id"
              class="eval-page__tr"
              :class="{ 'eval-page__tr--active': selected?.id === criterion.id }"
              @click="openView(criterion)"
            >
              <td v-if="visibleColumns.name" class="eval-page__td">
                <span class="eval-page__name">{{ criterion.name }}</span>
              </td>
              <td v-if="visibleColumns.type" class="eval-page__td">
                <span class="eval-page__badge" :class="'eval-page__badge--' + criterion.type">
                  {{ TYPE_LABELS[criterion.type] ?? criterion.type }}
                </span>
              </td>
              <td v-if="visibleColumns.level_count" class="eval-page__td eval-page__td--num">
                {{ criterion.level_count }}
              </td>
              <td v-if="visibleColumns.max_score" class="eval-page__td eval-page__td--num">
                {{ criterion.max_score }}
              </td>
              <td v-if="visibleColumns.status" class="eval-page__td">
                <span
                  class="eval-page__badge"
                  :class="criterion.is_active ? 'eval-page__badge--active' : 'eval-page__badge--inactive'"
                >
                  {{ criterion.is_active ? 'Đang dùng' : 'Tắt' }}
                </span>
              </td>
              <td v-if="visibleColumns.description" class="eval-page__td eval-page__td--desc">
                {{ criterion.description || '—' }}
              </td>
              <td v-if="canManage" class="eval-page__td eval-page__td--action" @click.stop>
                <button
                  type="button"
                  class="eval-page__icon-btn eval-page__icon-btn--ghost"
                  title=""
                  :disabled="togglingId === criterion.id"
                  @click="toggleActive(criterion)"
                >
                  <AppIcon
                    :name="criterion.is_active ? 'eyeOff' : 'eye'"
                    :size="15"
                  />
                </button>
                <button
                  type="button"
                  class="eval-page__icon-btn eval-page__icon-btn--danger"
                  title=""
                  :disabled="deletingId === criterion.id"
                  @click="confirmDelete = criterion"
                >
                  <AppIcon name="trash2" :size="15" />
                </button>
              </td>
            </tr>
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

    <!-- ── panel chi tiết / sửa / thêm ──────────────────────────────────── -->
    <aside
      v-if="selected || panelMode === 'add'"
      class="eval-page__panel"
      role="complementary"
      :aria-label="panelMode === 'add' ? 'Thêm tiêu chí' : 'Chi tiết tiêu chí'"
    >
      <div class="eval-page__panel-head">
        <span class="eval-page__panel-title">
          {{ panelMode === 'add' ? 'Thêm tiêu chí' : panelMode === 'edit' ? 'Sửa tiêu chí' : 'Chi tiết' }}
        </span>
        <div style="display:flex;gap:0.375rem;align-items:center">
          <button
            v-if="panelMode === 'view' && canManage"
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
            <AppIcon name="x" :size="14" />
          </button>
        </div>
      </div>

      <!-- ── VIEW MODE ──────────────────────────────────────────────────── -->
      <div v-if="panelMode === 'view' && selected" class="eval-page__panel-body hide-scrollbar">
        <dl class="eval-page__dl">
          <div class="eval-page__dl-row">
            <dt>Tên tiêu chí</dt>
            <dd>{{ selected.name }}</dd>
          </div>
          <div class="eval-page__dl-row">
            <dt>Kiểu</dt>
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
                {{ selected.is_active ? 'Đang dùng' : 'Tắt' }}
              </span>
            </dd>
          </div>
          <div class="eval-page__dl-row">
            <dt>Điểm tối đa</dt>
            <dd>{{ selected.max_score }}</dd>
          </div>
        </dl>

        <h4 class="eval-page__levels-title">
          {{ selected.type === 'scale' ? 'Các mức điểm' : 'Hành vi & điểm' }}
        </h4>
        <ul class="eval-page__levels-list">
          <li
            v-for="(lv, idx) in (selected.levels ?? [])"
            :key="idx"
            class="eval-page__level-item"
          >
            <span class="eval-page__level-label">{{ lv.label }}</span>
            <span class="eval-page__level-score" :class="lv.score < 0 ? 'eval-page__level-score--neg' : ''">
              {{ lv.score > 0 ? '+' : '' }}{{ lv.score }}
            </span>
          </li>
        </ul>
      </div>

      <!-- ── FORM MODE (add / edit) ─────────────────────────────────────── -->
      <div v-else-if="panelMode === 'add' || panelMode === 'edit'" class="eval-page__panel-body hide-scrollbar">
        <div class="eval-page__form">

          <div class="eval-page__form-field">
            <label class="eval-page__form-label">
              Tên tiêu chí <span class="eval-page__required">*</span>
            </label>
            <input
              v-model="form.name"
              type="text"
              class="eval-page__input"
              :class="{ 'eval-page__input--error': formErrors.name }"
              placeholder="VD: Thái độ làm việc, Chất lượng công việc…"
              maxlength="255"
            />
            <span v-if="formErrors.name" class="eval-page__field-error">
              {{ Array.isArray(formErrors.name) ? formErrors.name[0] : formErrors.name }}
            </span>
          </div>

          <div class="eval-page__form-field">
            <label class="eval-page__form-label">
              Kiểu tiêu chí <span class="eval-page__required">*</span>
            </label>
            <div class="eval-page__type-opts">
              <label class="eval-page__type-opt">
                <input
                  type="radio"
                  value="scale"
                  :checked="form.type === 'scale'"
                  @change="changeType('scale')"
                />
                <span>
                  <strong>Thang điểm</strong>
                  <small>Chọn 1 mức trong thang</small>
                </span>
              </label>
              <label class="eval-page__type-opt">
                <input
                  type="radio"
                  value="behavior"
                  :checked="form.type === 'behavior'"
                  @change="changeType('behavior')"
                />
                <span>
                  <strong>Cộng/trừ hành vi</strong>
                  <small>Ghi nhận từng hành vi có điểm riêng</small>
                </span>
              </label>
            </div>
          </div>

          <div class="eval-page__form-field">
            <label class="eval-page__form-label">Mô tả <span class="eval-page__muted">(tuỳ chọn)</span></label>
            <textarea
              v-model="form.description"
              class="eval-page__textarea"
              rows="2"
              placeholder="Ghi chú về cách áp dụng tiêu chí này…"
              maxlength="1000"
            />
          </div>

          <div class="eval-page__form-field">
            <div class="eval-page__form-label-row">
              <label class="eval-page__form-label">
                {{ form.type === 'scale' ? 'Các mức điểm' : 'Hành vi & điểm' }}
                <span class="eval-page__required">*</span>
              </label>
              <button type="button" class="eval-page__add-level-btn" @click="addLevel">
                <AppIcon name="plus" :size="13" /> Thêm
              </button>
            </div>

            <div
              v-if="formErrors.levels"
              class="eval-page__field-error eval-page__field-error--block"
            >
              {{ Array.isArray(formErrors.levels) ? formErrors.levels[0] : formErrors.levels }}
            </div>

            <div class="eval-page__levels-form">
              <div
                v-for="(lv, idx) in form.levels"
                :key="idx"
                class="eval-page__level-row"
              >
                <input
                  v-model="form.levels[idx].label"
                  type="text"
                  class="eval-page__input eval-page__input--level"
                  :placeholder="form.type === 'scale' ? 'Nhãn mức (VD: Xuất sắc)' : 'Tên hành vi (VD: Đi muộn)'"
                />
                <input
                  v-model.number="form.levels[idx].score"
                  type="number"
                  class="eval-page__input eval-page__input--score"
                  :placeholder="form.type === 'scale' ? 'Điểm' : '±Điểm'"
                  :min="form.type === 'scale' ? 1 : undefined"
                  :step="1"
                />
                <button
                  v-if="form.levels.length > 1"
                  type="button"
                  class="eval-page__icon-btn eval-page__icon-btn--ghost"
                  @click="removeLevel(idx)"
                >
                  <AppIcon name="x" :size="13" />
                </button>
              </div>
            </div>
          </div>

          <div class="eval-page__form-field eval-page__form-field--check">
            <label class="eval-page__check-label">
              <input v-model="form.is_active" type="checkbox" />
              Đang dùng (hiển thị khi đánh giá)
            </label>
          </div>

          <div class="eval-page__form-actions">
            <button
              type="button"
              class="eval-page__btn eval-page__btn--primary"
              :disabled="formSaving"
              @click="saveCriterion"
            >
              <AppIcon v-if="formSaving" name="loader" :size="15" class="eval-page__spin" />
              {{ formSaving ? 'Đang lưu…' : panelMode === 'add' ? 'Tạo tiêu chí' : 'Lưu thay đổi' }}
            </button>
            <button
              type="button"
              class="eval-page__btn"
              :disabled="formSaving"
              @click="panelMode === 'add' ? closePanel() : (panelMode = 'view')"
            >
              Huỷ
            </button>
          </div>
        </div>
      </div>
    </aside>

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
.eval-page__filters {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-3);
  margin-bottom: var(--space-2);
}

.eval-page__field { display: flex; flex-direction: column; gap: var(--space-1); }

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
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.eval-page__th--action { text-align: center; }

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

.eval-page__td {
  padding: var(--space-3) var(--space-4);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  box-shadow: 0 1px 0 var(--color-border);
}

.eval-page__td--num { text-align: right; }

.eval-page__td--desc {
  max-width: 20ch;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.eval-page__td--action {
  text-align: center;
  white-space: nowrap;
}

.eval-page__name { font-weight: 600; }

.eval-page__badge {
  display: inline-flex;
  align-items: center;
  padding: 0.125rem 0.5rem;
  border-radius: 100px;
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
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

/* col toggle menu */
.eval-page__col-toggle {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-2);
}

.eval-page__col-opt {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: 0.8125rem;
  cursor: pointer;
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
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  box-shadow: 0 1px 0 var(--color-border);
}

.eval-page__level-item:last-child { box-shadow: none; }

.eval-page__level-score {
  font-weight: 700;
  color: var(--color-success, #16a34a);
}

.eval-page__level-score--neg { color: var(--color-danger, #dc2626); }

/* form mode */
.eval-page__form { display: flex; flex-direction: column; gap: var(--space-4); }

.eval-page__form-field { display: flex; flex-direction: column; gap: var(--space-2); }

.eval-page__form-field--check { flex-direction: row; align-items: center; }

.eval-page__form-label {
  font-size: 0.8125rem;
  font-weight: 600;
}

.eval-page__form-label-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.eval-page__required { color: var(--color-danger, #dc2626); }
.eval-page__muted    { font-weight: 400; color: var(--color-text-muted); }

.eval-page__field-error {
  font-size: 0.75rem;
  color: var(--color-danger, #dc2626);
}

.eval-page__field-error--block { display: block; }

.eval-page__type-opts {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-2);
}

.eval-page__type-opt {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  padding: var(--space-3);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  cursor: pointer;
  font-size: 0.8125rem;
}

.eval-page__type-opt:has(input:checked) {
  border-color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 6%, transparent);
}

.eval-page__type-opt span { display: flex; flex-direction: column; gap: 0.125rem; }
.eval-page__type-opt strong { font-size: 0.875rem; }
.eval-page__type-opt small { font-weight: 400; color: var(--color-text-muted); }

.eval-page__levels-form { display: flex; flex-direction: column; gap: var(--space-2); }

.eval-page__level-row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.eval-page__input--level { flex: 1; }

.eval-page__input--score { width: 5rem; flex-shrink: 0; text-align: right; }

.eval-page__add-level-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  height: 1.5rem;
  padding: 0 0.5rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 500;
  cursor: pointer;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.eval-page__add-level-btn:hover { background: var(--color-surface); color: var(--color-text); }

.eval-page__check-label {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: 0.875rem;
  cursor: pointer;
}

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
    grid-template-columns: 1fr;
    gap: var(--space-2);
  }

  .eval-page__type-opts {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .eval-page__dl-row { flex-direction: column; gap: var(--space-1); }
  .eval-page__dl-row dt { flex: none; }
}
</style>
