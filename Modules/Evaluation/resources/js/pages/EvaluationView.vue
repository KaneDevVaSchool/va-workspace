<script setup>
//
// manager/evaluation — trang riêng ngoài sidebar, dành cho MỌI thành viên có
// phòng ban xem tiêu chí đánh giá của phòng ban mình. Chỉ xem, không có nút
// thêm/sửa/xoá — trang quản lý (tạo/sửa/xoá) vẫn nằm trong hub Cấu hình
// phòng ban (WorkspaceConfigEvaluation.vue), dành cho trưởng phòng.
//
// Hai kiểu tiêu chí:
//   scale    — thang điểm nhiều mức (Xuất sắc 5 / Tốt 4 / Khá 3…)
//   behavior — cộng/trừ theo hành vi (Đi muộn −1 / Hoàn thành sớm +2…)
//
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import { showClientToast } from '@/lib/clientToast';
import { useDragScroll } from '@/composables/useDragScroll';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';

// ─── constants ───────────────────────────────────────────────────────────────

const COLUMNS = [
  { key: 'name',        label: 'Tên tiêu chí', defaultOn: true },
  { key: 'level_count', label: 'Số mức',        defaultOn: true, align: 'center' },
  { key: 'max_score',   label: 'Điểm tối đa',   defaultOn: true, align: 'center' },
  { key: 'status',      label: 'Trạng thái',    defaultOn: true, align: 'center' },
  { key: 'description', label: 'Mô tả',         defaultOn: false },
];

const FILTERS = [
  { key: 'q',    label: 'Tìm kiếm',  defaultOn: true },
  { key: 'kind', label: 'Loại',      defaultOn: true },
  { key: 'type', label: 'Cách chấm', defaultOn: true },
];

const TYPE_LABELS = { scale: 'Thang điểm', behavior: 'Cộng/trừ' };
const COL_KEY    = 'va-eval-view-columns-v1';
const FILTER_KEY = 'va-eval-view-filters-v1';
const WIDTH_KEY  = 'va-eval-view-widths';
const ZOOM_KEY   = 'va-eval-view-zoom';

const CELL_PAD_X = 32;
const COL_EXTRA  = 24;
let measureCtx   = null;
let wrapObserver = null;

// ─── stores ──────────────────────────────────────────────────────────────────

const auth = useAuthStore();

// ─── state ───────────────────────────────────────────────────────────────────

const allCriteria    = ref([]);
const criterionTypes = ref([]);
const loading        = ref(false);
const selected       = ref(null);
const exporting      = ref(false);
const exportingPdf   = ref(false);

const query      = ref('');
const kindFilter = ref('');
const typeFilter = ref('');
const page       = ref(1);
const perPage    = ref(20);

const visibleColumns = reactive(loadVisibility(COL_KEY, COLUMNS));
const visibleFilters = reactive(loadVisibility(FILTER_KEY, FILTERS));

const tableWrap  = ref(null);
const resizing   = ref(false);

useDragScroll(tableWrap, { isBlocked: () => resizing.value });
const MIN_COL_PX = 80;
const colWidths  = reactive(loadWidths());
const tableZoom  = ref(loadZoom());

/** Thu gọn nhóm theo loại tiêu chí. */
const typeGroupCollapsed = reactive({});

// ─── computed ─────────────────────────────────────────────────────────────────

const hasDepartment = computed(() => Boolean(auth.user?.department?.id));

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase();
  return allCriteria.value.filter((c) => {
    if (c.use_in_evaluation === false) return false;
    if (q && !c.name.toLowerCase().includes(q) && !(c.criterion_type?.name ?? '').toLowerCase().includes(q)
      && !(c.criterion_type?.code ?? '').toLowerCase().includes(q)) return false;
    if (kindFilter.value && String(c.criterion_type_id) !== String(kindFilter.value)) return false;
    if (typeFilter.value && c.type !== typeFilter.value) return false;
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
  Boolean(query.value.trim() || kindFilter.value || typeFilter.value),
);

const hasVisibleFilterFields = computed(() =>
  FILTERS.some((item) => visibleFilters[item.key]),
);

const hiddenActiveFilterLabels = computed(() =>
  FILTERS.filter((item) => !visibleFilters[item.key] && filterHasValue(item.key)).map(
    (item) => item.label,
  ),
);

const shownColumns = computed(() => COLUMNS.filter((col) => visibleColumns[col.key]));

const tableColspan = computed(() => shownColumns.value.length);

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
      const entry = { key, type: criterion.criterion_type ?? null, criteria: [] };
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

function isTypeGroupCollapsed(store, key) {
  return Boolean(store[key]);
}

function toggleTypeGroup(key) {
  typeGroupCollapsed[key] = !typeGroupCollapsed[key];
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

function filterHasValue(key) {
  if (key === 'q') return Boolean(query.value.trim());
  if (key === 'kind') return Boolean(kindFilter.value);
  if (key === 'type') return Boolean(typeFilter.value);
  return false;
}

function clearFilters() {
  query.value = '';
  kindFilter.value = '';
  typeFilter.value = '';
  page.value = 1;
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
    for (const row of allCriteria.value) {
      let val = '';
      if (col.key === 'name')        val = row.name ?? '';
      if (col.key === 'level_count') val = String(row.level_count ?? 0);
      if (col.key === 'max_score')   val = formatScore(row.max_score);
      if (col.key === 'status')      val = row.is_active ? 'Đang áp dụng' : 'Ngừng áp dụng';
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
    const newW = Math.max(MIN_COL_PX, resizeState.startW + dx);
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

function openView(criterion) {
  selected.value = criterion;
}

function closePanel() {
  selected.value = null;
}

function currentExportParams() {
  return {
    q: query.value.trim(),
    kind: kindFilter.value,
    type: typeFilter.value,
  };
}

async function downloadFile(url, params, busyRef, defaultFilename, okMsg, failMsg) {
  busyRef.value = true;
  try {
    const response = await window.axios.get(url, {
      params,
      responseType: 'blob',
    });
    const blob = response.data;
    if (blob.type && blob.type.includes('json')) {
      const json = JSON.parse(await blob.text());
      throw new Error(json.message || 'Không xuất được file.');
    }

    const disposition = response.headers['content-disposition'] || '';
    const utfMatch = disposition.match(/filename\*=UTF-8''([^;]+)/i);
    const plainMatch = disposition.match(/filename="?([^"]+)"?/i);
    const filename = decodeURIComponent(utfMatch?.[1] || plainMatch?.[1] || defaultFilename);

    const objectUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = objectUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(objectUrl);
    showClientToast('success', okMsg);
  } catch (err) {
    let message = err?.message;
    if (err?.response?.data instanceof Blob) {
      try {
        const json = JSON.parse(await err.response.data.text());
        message = json.message || Object.values(json.errors || {})[0]?.[0];
      } catch {
        message = failMsg;
      }
    } else {
      message = err?.response?.data?.message || message;
    }
    showClientToast('error', message || failMsg);
  } finally {
    busyRef.value = false;
  }
}

async function exportExcel() {
  await downloadFile(
    '/api/evaluation/criteria/export',
    currentExportParams(),
    exporting,
    'Tieu_chi_danh_gia.xlsx',
    'Đã tải file Excel.',
    'Không xuất được file Excel.',
  );
}

async function exportPdf() {
  await downloadFile(
    '/api/evaluation/criteria/export-pdf',
    currentExportParams(),
    exportingPdf,
    'Tieu_chi_danh_gia.pdf',
    'Đã tải file PDF.',
    'Không xuất được file PDF.',
  );
}

const exportOptions = computed(() => [
  {
    key: 'excel',
    label: 'Xuất Excel',
    description: 'Theo bộ lọc hiện tại trên trang.',
    onSelect: exportExcel,
  },
  {
    key: 'pdf',
    label: 'Xuất PDF',
    description: 'Theo bộ lọc hiện tại trên trang.',
    icon: 'fileText',
    onSelect: exportPdf,
  },
]);

function formatScore(score) {
  const n = Number(score);
  if (!Number.isFinite(n)) return '0';
  return Number.isInteger(n) ? String(n) : n.toFixed(1);
}

function handleKeydown(e) {
  if (e.key === 'Escape' && selected.value) closePanel();
}

onMounted(async () => {
  document.addEventListener('keydown', handleKeydown);
  await load();

  wrapObserver = new ResizeObserver(() => computeDefaultWidths());
  if (tableWrap.value) wrapObserver.observe(tableWrap.value);
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleKeydown);
  wrapObserver?.disconnect();
});
</script>

<template>
  <div class="eval-view" :class="{ 'eval-view--with-panel': selected }">
    <PageHeader
      title="Tiêu chí đánh giá"
      icon="clipboardCheck"
      description="Tiêu chí đánh giá đang áp dụng cho phòng ban của bạn."
      export-label="Dữ liệu"
      :export-options="hasDepartment ? exportOptions : []"
      :export-busy-key="exporting ? 'excel' : exportingPdf ? 'pdf' : undefined"
    />

    <div class="eval-view__main">
      <div v-if="hasVisibleFilterFields" class="eval-view__toolbar">
        <div class="eval-view__filters">
          <div v-if="visibleFilters.q" class="eval-view__field">
            <label class="eval-view__label" for="eval-view-q">Tìm kiếm</label>
            <input
              id="eval-view-q"
              v-model="query"
              type="search"
              class="eval-view__input"
              placeholder="Tên tiêu chí, loại…"
              @keydown.enter="page = 1"
            />
          </div>
          <div v-if="visibleFilters.kind" class="eval-view__field">
            <label class="eval-view__label" for="eval-view-kind">Loại</label>
            <select id="eval-view-kind" v-model="kindFilter" class="eval-view__input">
              <option value="">Tất cả loại</option>
              <option v-for="item in criterionTypes" :key="item.id" :value="String(item.id)">
                {{ item.name }}{{ item.code ? ` — ${item.code}` : '' }}
              </option>
            </select>
          </div>
          <div v-if="visibleFilters.type" class="eval-view__field">
            <label class="eval-view__label" for="eval-view-type">Cách chấm</label>
            <select id="eval-view-type" v-model="typeFilter" class="eval-view__input">
              <option value="">Tất cả cách chấm</option>
              <option value="scale">Thang điểm</option>
              <option value="behavior">Cộng/trừ</option>
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
          <label v-for="item in FILTERS" :key="item.key" class="eval-view__check">
            <input
              type="checkbox"
              :checked="visibleFilters[item.key]"
              @change="onFilterToggle(item.key, $event.target.checked)"
            />
            <span>{{ item.label }}</span>
          </label>
        </template>
        <template #settings>
          <label v-for="col in COLUMNS" :key="col.key" class="eval-view__check">
            <input
              type="checkbox"
              :checked="visibleColumns[col.key]"
              @change="onColumnToggle(col.key, $event.target.checked)"
            />
            <span>{{ col.label }}</span>
          </label>
        </template>
      </TablePagesBar>

      <p v-if="hiddenActiveFilterLabels.length" class="eval-view__note">
        Đang lọc thêm theo: {{ hiddenActiveFilterLabels.join(', ') }} (bộ lọc đang ẩn).
      </p>

      <div
        ref="tableWrap"
        class="eval-view__table-wrap hide-scrollbar"
        :class="{ 'eval-view__table-wrap--resizing': resizing }"
        :style="{ '--table-zoom': tableZoom }"
      >
        <p v-if="!hasDepartment" class="eval-view__empty">
          Tài khoản chưa gắn với phòng ban nào.
        </p>

        <p v-else-if="loading" class="eval-view__empty">Đang tải…</p>

        <p v-else-if="allCriteria.length === 0" class="eval-view__empty">
          Phòng ban chưa có tiêu chí đánh giá nào.
        </p>

        <p v-else-if="filtered.length === 0" class="eval-view__empty">
          {{ hasActiveFilters ? 'Không tìm thấy tiêu chí phù hợp.' : 'Không có tiêu chí nào đang dùng trong ĐGNL.' }}
        </p>

        <table v-else class="eval-view__table">
          <colgroup>
            <col
              v-for="col in shownColumns"
              :key="col.key"
              :style="{ width: (colWidths[col.key] ?? MIN_COL_PX) + 'px' }"
            />
          </colgroup>
          <thead>
            <tr>
              <th
                v-for="col in shownColumns"
                :key="col.key"
                class="eval-view__th"
                :class="{ 'eval-view__th--center': col.align === 'center' }"
              >
                {{ col.label }}
                <button
                  type="button"
                  class="eval-view__resize"
                  aria-label="Kéo để đổi độ rộng cột"
                  @mousedown.stop="startResize($event, col.key)"
                />
              </th>
            </tr>
          </thead>
          <tbody>
            <template v-for="entry in tableBodyRows" :key="entry.key">
              <tr
                v-if="entry.kind === 'group'"
                class="eval-view__tr eval-view__tr--group"
                @click.stop="toggleTypeGroup(entry.groupKey)"
              >
                <td :colspan="tableColspan" class="eval-view__td eval-view__td--group">
                  <div class="eval-view__group-inner">
                    <span class="eval-view__group-toggle" aria-hidden="true">
                      <AppIcon
                        :name="entry.collapsed ? 'chevronRight' : 'chevronDown'"
                        :size="16"
                        :stroke-width="1.75"
                      />
                    </span>
                    <span class="eval-view__group-copy">
                      <span class="eval-view__group-title">{{ entry.title }}</span>
                      <span v-if="entry.code" class="eval-view__group-code">{{ entry.code }}</span>
                    </span>
                    <span class="eval-view__group-count">{{ entry.count }} tiêu chí</span>
                  </div>
                </td>
              </tr>
              <tr
                v-else
                class="eval-view__tr"
                :class="{ 'eval-view__tr--active': selected?.id === entry.criterion.id }"
                @click="openView(entry.criterion)"
              >
                <td v-if="visibleColumns.name" class="eval-view__td">
                  <span class="eval-view__name">{{ entry.criterion.name }}</span>
                </td>
                <td v-if="visibleColumns.level_count" class="eval-view__td eval-view__td--center">
                  {{ entry.criterion.level_count }}
                </td>
                <td v-if="visibleColumns.max_score" class="eval-view__td eval-view__td--center">
                  {{ formatScore(entry.criterion.max_score) }}
                </td>
                <td v-if="visibleColumns.status" class="eval-view__td eval-view__td--center">
                  <span class="eval-view__status">
                    <span
                      class="eval-view__dot"
                      :class="entry.criterion.is_active ? 'eval-view__dot--on' : 'eval-view__dot--off'"
                    />
                    {{ entry.criterion.is_active ? 'Đang áp dụng' : 'Ngừng áp dụng' }}
                  </span>
                </td>
                <td v-if="visibleColumns.description" class="eval-view__td eval-view__td--desc">
                  {{ entry.criterion.description || '—' }}
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

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

    <aside
      v-if="selected"
      class="eval-view__panel"
      role="complementary"
      aria-label="Chi tiết tiêu chí"
    >
      <div class="eval-view__panel-head">
        <span class="eval-view__panel-title">Chi tiết</span>
        <button type="button" class="eval-view__panel-btn" @click="closePanel">
          <AppIcon name="close" :size="14" />
        </button>
      </div>

      <div class="eval-view__panel-body hide-scrollbar">
        <div class="eval-view__row">
          <span class="eval-view__row-label">Tên tiêu chí</span>
          <span class="eval-view__row-value">{{ selected.name }}</span>
        </div>
        <div class="eval-view__row">
          <span class="eval-view__row-label">Loại tiêu chí</span>
          <span class="eval-view__row-value">{{ selected.criterion_type?.name || '—' }}</span>
        </div>
        <div v-if="selected.criterion_type?.description" class="eval-view__row">
          <span class="eval-view__row-label">Mô tả loại</span>
          <span class="eval-view__row-value">{{ selected.criterion_type.description }}</span>
        </div>
        <div class="eval-view__row">
          <span class="eval-view__row-label">Cách chấm</span>
          <span class="eval-view__row-value">{{ TYPE_LABELS[selected.type] ?? selected.type }}</span>
        </div>
        <div v-if="selected.description" class="eval-view__row">
          <span class="eval-view__row-label">Mô tả</span>
          <span class="eval-view__row-value">{{ selected.description }}</span>
        </div>
        <div class="eval-view__row">
          <span class="eval-view__row-label">Trạng thái</span>
          <span class="eval-view__row-value">
            <span
              class="eval-view__dot"
              :class="selected.is_active ? 'eval-view__dot--on' : 'eval-view__dot--off'"
            />
            {{ selected.is_active ? 'Đang áp dụng' : 'Ngừng áp dụng' }}
          </span>
        </div>
        <div class="eval-view__row">
          <span class="eval-view__row-label">Cho phép chấm nửa điểm</span>
          <span class="eval-view__row-value">{{ selected.allow_half ? 'Có' : 'Không' }}</span>
        </div>
        <div class="eval-view__row">
          <span class="eval-view__row-label">Điểm tối đa</span>
          <span class="eval-view__row-value">{{ formatScore(selected.max_score) }}</span>
        </div>

        <h2 class="eval-view__levels-title">
          {{ selected.type === 'scale' ? 'Thang điểm đánh giá' : 'Hành vi & điểm' }}
        </h2>
        <ul class="eval-view__levels-list">
          <li v-for="(lv, idx) in (selected.levels ?? [])" :key="idx" class="eval-view__level-item">
            <span class="eval-view__level-copy">
              <span class="eval-view__level-label">
                <span v-if="lv.code" class="eval-view__level-code">{{ lv.code }}</span>
                {{ lv.label }}
              </span>
              <span v-if="lv.description" class="eval-view__level-note">{{ lv.description }}</span>
            </span>
            <span class="eval-view__level-score" :class="lv.score < 0 ? 'eval-view__level-score--neg' : ''">
              {{ lv.score > 0 ? '+' : '' }}{{ formatScore(lv.score) }}
            </span>
          </li>
        </ul>
      </div>
    </aside>
  </div>
</template>

<style scoped>
/* ─── layout ──────────────────────────────────────────────────────────────── */
.eval-view {
  height: 100%;
  display: flex;
  overflow: hidden;
  padding: var(--space-4);
  gap: var(--space-3);
}

.eval-view__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.eval-view__panel {
  flex: 0 0 22rem;
  width: 24rem;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
}

/* ─── filters ─────────────────────────────────────────────────────────────── */
.eval-view__toolbar {
  position: relative;
  z-index: 6;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin: 0 0 var(--space-3);
}

.eval-view__filters {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-3);
  width: 100%;
}

.eval-view__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
  width: 100%;
}

.eval-view__label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.eval-view__input {
  width: 100%;
  padding: 0.4375rem 0.625rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
}

.eval-view__input:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: -1px;
}

/* ─── table ───────────────────────────────────────────────────────────────── */
.eval-view__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.eval-view__table-wrap--resizing {
  cursor: col-resize;
  user-select: none;
}

.eval-view__table {
  width: 100%;
  min-width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: calc(0.875rem * var(--table-zoom, 1));
}

.eval-view__th {
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

.eval-view__th--center { text-align: center; }

.eval-view__resize {
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

.eval-view__resize::after {
  content: '';
  position: absolute;
  top: 25%; right: 2px;
  width: 1px;
  height: 50%;
  background: var(--color-border);
  opacity: 0;
  transition: opacity 0.15s;
}

.eval-view__th:hover .eval-view__resize::after { opacity: 1; }

.eval-view__tr { cursor: pointer; }
.eval-view__tr:hover td { background: var(--color-surface-muted); }

.eval-view__tr--active td {
  background: color-mix(in srgb, var(--color-primary) 8%, transparent);
}

.eval-view__tr--group { cursor: pointer; }
.eval-view__tr--group:hover .eval-view__group-inner { background: var(--color-surface-muted); }

.eval-view__td--group {
  padding: 0;
  overflow: visible;
  white-space: normal;
  vertical-align: middle;
}

.eval-view__group-inner {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.5rem var(--space-4);
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text);
  background: color-mix(in srgb, var(--color-text) 4%, var(--color-surface));
  box-shadow: 0 1px 0 var(--color-border);
}

.eval-view__group-toggle {
  display: inline-flex;
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.eval-view__group-copy {
  display: flex;
  min-width: 0;
  flex: 1;
  align-items: baseline;
  gap: 0.5rem;
}

.eval-view__group-title {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.eval-view__group-code {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 500;
}

.eval-view__group-count {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 500;
}

.eval-view__td {
  padding: var(--space-3) var(--space-4);
  vertical-align: middle;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  box-shadow: 0 1px 0 var(--color-border);
}

.eval-view__td--center { text-align: center; }

.eval-view__td--desc {
  max-width: 20ch;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.eval-view__name { font-weight: 600; }

.eval-view__status {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  color: var(--color-text);
}

.eval-view__dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  flex-shrink: 0;
}

.eval-view__dot--on  { background: var(--color-success); }
.eval-view__dot--off { background: var(--color-text-muted); }

.eval-view__empty {
  margin: 2rem auto;
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.eval-view__check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.eval-view__note {
  flex-shrink: 0;
  margin: 0 0 var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

/* ─── panel ───────────────────────────────────────────────────────────────── */
.eval-view__panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
  flex-shrink: 0;
}

.eval-view__panel-title {
  font-weight: 700;
  font-size: 0.9375rem;
}

.eval-view__panel-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  cursor: pointer;
}

.eval-view__panel-btn:hover { background: var(--color-surface); color: var(--color-text); }

.eval-view__panel-body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: var(--space-4);
}

.eval-view__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.875rem;
}

.eval-view__row:last-of-type { box-shadow: none; }

.eval-view__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.eval-view__row-label::after {
  content: ':';
}

.eval-view__row-value {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  color: var(--color-text);
  font-style: italic;
  text-align: right;
}

.eval-view__levels-title {
  margin: var(--space-4) 0 var(--space-2);
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 700;
}

.eval-view__levels-list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
}

.eval-view__level-item {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.eval-view__level-item:last-child { box-shadow: none; }

.eval-view__level-copy {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 0;
}

.eval-view__level-label {
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
}

.eval-view__level-code {
  margin-right: 0.375rem;
  color: var(--color-text-muted);
  font-weight: 500;
}

.eval-view__level-note {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.eval-view__level-score {
  flex-shrink: 0;
  color: var(--color-success);
  font-weight: 700;
}

.eval-view__level-score--neg { color: var(--color-danger); }

/* ─── responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
  .eval-view { flex-direction: column; padding: var(--space-3); }
  .eval-view__filters { grid-template-columns: 1fr; }
  .eval-view__panel { width: 100%; flex: 1 1 auto; }
}

</style>
