<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import { formatDate, formatDateTime, formatTime } from '@/lib/formatTime';
import { showClientToast } from '@/lib/clientToast';
import {
  ACTIVITY_ACTIONS,
  ACTIVITY_COLUMNS,
  ACTIVITY_FILTERS,
  COLUMN_STORAGE_KEY,
  COLUMN_WIDTH_KEY,
  FILTER_STORAGE_KEY,
  ZOOM_STORAGE_KEY,
  loadVisibility,
  saveVisibility,
} from '../constants/activity.js';

const CELL_PAD_X = 32;
const COL_EXTRA = 24;
const ACTOR_AVATAR_EXTRA = 42;
let measureCtx = null;
let wrapObserver = null;

const logs = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 20 });
const loading = ref(false);
const exporting = ref(false);
const selected = ref(null);

const query = ref('');
const action = ref('');
const actorId = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const perPage = ref(20);

const actions = ref(ACTIVITY_ACTIONS);
const actors = ref([]);

const visibleColumns = reactive(loadVisibility(COLUMN_STORAGE_KEY, ACTIVITY_COLUMNS));
const visibleFilters = reactive(loadVisibility(FILTER_STORAGE_KEY, ACTIVITY_FILTERS));

const tableWrap = ref(null);
const resizing = ref(false);
const MIN_COL_PX = 72;

const columnWidths = reactive(loadColumnWidths());

const exportDialog = ref(null); // 'date' | 'user' | null
const exportDateFrom = ref('');
const exportDateTo = ref('');
const exportActorId = ref('');

const shownColumns = computed(() => ACTIVITY_COLUMNS.filter((col) => visibleColumns[col.key]));
const colSpan = computed(() => Math.max(shownColumns.value.length, 1));

const hasActiveFilters = computed(
  () =>
    Boolean(query.value.trim()) ||
    Boolean(action.value) ||
    Boolean(actorId.value) ||
    Boolean(dateFrom.value) ||
    Boolean(dateTo.value),
);

const hiddenActiveFilterLabels = computed(() =>
  ACTIVITY_FILTERS.filter((item) => !visibleFilters[item.key] && filterHasValue(item.key)).map(
    (item) => item.label,
  ),
);

const hasVisibleFilterFields = computed(() =>
  ACTIVITY_FILTERS.some((item) => visibleFilters[item.key]),
);

const tableZoom = ref(loadZoom());
const tableWidthPx = computed(() => {
  const keys = shownColumns.value.map((col) => col.key);
  const sum = keys.reduce((total, key) => total + (Number(columnWidths[key]) || 0), 0);
  return sum > 0 ? `${sum}px` : '100%';
});

const exportOptions = computed(() => [
  {
    key: 'excel-filter',
    label: 'Xuất theo bộ lọc hiện tại',
    description: 'Tải file Excel (.xlsx) đúng theo tìm kiếm và bộ lọc đang chọn trên trang.',
    onSelect: () => exportCurrentFilters(),
  },
  {
    key: 'excel-date',
    label: 'Xuất theo khoảng ngày',
    description: 'Chọn ngày bắt đầu và ngày kết thúc, rồi tải toàn bộ thao tác trong khoảng đó.',
    onSelect: () => openExportDialog('date'),
  },
  {
    key: 'excel-user',
    label: 'Xuất theo người dùng',
    description: 'Chọn một người và tải toàn bộ thao tác của người đó.',
    onSelect: () => openExportDialog('user'),
  },
]);

function filterHasValue(key) {
  if (key === 'q') return Boolean(query.value.trim());
  if (key === 'action') return Boolean(action.value);
  if (key === 'actor_id') return Boolean(actorId.value);
  if (key === 'date_from') return Boolean(dateFrom.value);
  if (key === 'date_to') return Boolean(dateTo.value);
  return false;
}

function currentFilterParams() {
  return {
    q: query.value.trim() || undefined,
    action: action.value || undefined,
    actor_id: actorId.value || undefined,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
  };
}

async function loadOptions() {
  try {
    const { data } = await window.axios.get('/api/activity-logs/options');
    actions.value = data.actions?.length ? data.actions : ACTIVITY_ACTIONS;
    actors.value = data.actors ?? [];
  } catch {
    actions.value = ACTIVITY_ACTIONS;
  }
}

async function loadLogs(page = 1) {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/activity-logs', {
      params: {
        ...currentFilterParams(),
        page,
        per_page: perPage.value,
      },
    });
    logs.value = data.logs ?? [];
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 20 };

    if (selected.value && !logs.value.some((log) => log.id === selected.value.id)) {
      selected.value = null;
    }
    nextTick(fitColumnsToContent);
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không tải được nhật ký hoạt động.');
  } finally {
    loading.value = false;
  }
}

function goPage(page) {
  if (page < 1 || page > meta.value.last_page || page === meta.value.current_page) {
    return;
  }
  loadLogs(page);
}

function clearFilters() {
  query.value = '';
  action.value = '';
  actorId.value = '';
  dateFrom.value = '';
  dateTo.value = '';
  loadLogs(1);
}

function inspect(log) {
  selected.value = log;
}

function actionTone(action) {
  if (!action) return 'neutral';
  if (action.includes('delete') || action.includes('deny') || action.includes('logout') || action === 'view_as.deactivate') {
    return 'danger';
  }
  if (action.includes('create') || action.includes('grant') || action.includes('login')) {
    return 'success';
  }
  return 'info';
}

function actorUser(log) {
  if (!log) return null;
  if (log.actor) {
    return {
      id: log.actor.id,
      name: log.actor.name || log.actor_name || 'Hệ thống',
      email: log.actor.email || log.actor_email || null,
      avatar_url: log.actor.avatar_url || null,
      department: log.actor.department || null,
    };
  }

  return {
    id: log.actor_id,
    name: log.actor_name || 'Hệ thống',
    email: log.actor_email || null,
    avatar_url: null,
    department: null,
  };
}

function cellText(log, key) {
  if (key === 'created_at') return formatDateTime(log.created_at) || '—';
  if (key === 'actor') return log.actor_name || log.actor?.name || 'Hệ thống';
  if (key === 'action') return log.action_label || log.action || '—';
  if (key === 'description') return log.description || '—';
  if (key === 'subject') return log.subject_label || '—';
  if (key === 'properties') return log.properties_summary || '—';
  if (key === 'ip_address') return log.ip_address || '—';
  if (key === 'browser') return log.browser || '—';
  if (key === 'id') return String(log.id ?? '—');
  return '—';
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
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
      if (parsed.actor_name && !parsed.actor) {
        parsed.actor = parsed.actor_name;
      }
      return parsed;
    }
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
  const table = tableWrap.value?.querySelector('.activity-page__table');
  return {
    header: fontOf(table?.querySelector('thead th'), '600 12px "Be Vietnam Pro", sans-serif'),
    cell: fontOf(table?.querySelector('tbody td'), '400 14px "Be Vietnam Pro", sans-serif'),
    muted: fontOf(table?.querySelector('.activity-page__muted'), '400 12px "Be Vietnam Pro", sans-serif'),
  };
}

function columnContentWidth(key, fonts) {
  const label = ACTIVITY_COLUMNS.find((col) => col.key === key)?.label ?? '';
  let maxW = measureText(label, fonts.header);
  for (const log of logs.value) {
    if (key === 'actor') {
      maxW = Math.max(maxW, measureText(cellText(log, 'actor'), fonts.cell));
      if (log.actor_email) {
        maxW = Math.max(maxW, measureText(log.actor_email, fonts.muted));
      }
    } else {
      maxW = Math.max(maxW, measureText(cellText(log, key), fonts.cell));
    }
  }
  const extra = key === 'actor' ? ACTOR_AVATAR_EXTRA : 0;
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
    const remaining = ACTIVITY_COLUMNS.filter((col) => visibleColumns[col.key] && col.key !== key).length;
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

function openExportDialog(kind) {
  exportDateFrom.value = dateFrom.value;
  exportDateTo.value = dateTo.value;
  exportActorId.value = actorId.value;
  exportDialog.value = kind;
}

function closeExportDialog() {
  if (exporting.value) return;
  exportDialog.value = null;
}

async function exportCurrentFilters() {
  await downloadExcel({ ...currentFilterParams(), export_kind: 'filter' });
}

async function confirmExportDate() {
  if (!exportDateFrom.value || !exportDateTo.value) {
    showClientToast('error', 'Vui lòng chọn đủ ngày bắt đầu và ngày kết thúc.');
    return;
  }
  if (exportDateFrom.value > exportDateTo.value) {
    showClientToast('error', 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.');
    return;
  }
  const ok = await downloadExcel({
    export_kind: 'date',
    date_from: exportDateFrom.value,
    date_to: exportDateTo.value,
  });
  if (ok) {
    exportDialog.value = null;
  }
}

async function confirmExportUser() {
  if (!exportActorId.value) {
    showClientToast('error', 'Vui lòng chọn người dùng cần xuất.');
    return;
  }
  const ok = await downloadExcel({
    export_kind: 'user',
    actor_id: exportActorId.value,
  });
  if (ok) {
    exportDialog.value = null;
  }
}

async function downloadExcel(params) {
  exporting.value = true;
  try {
    const response = await window.axios.get('/api/activity-logs/export', {
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
    const filename = decodeURIComponent(utfMatch?.[1] || plainMatch?.[1] || 'Nhat_ky_hoat_dong.xlsx');

    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    showClientToast('success', 'Đã tải file Excel.');
    return true;
  } catch (error) {
    let message = error?.message;
    if (error?.response?.data instanceof Blob) {
      try {
        const json = JSON.parse(await error.response.data.text());
        message = json.message || Object.values(json.errors || {})[0]?.[0];
      } catch {
        message = 'Không xuất được file Excel.';
      }
    } else {
      message = error?.response?.data?.message || message;
    }
    showClientToast('error', message || 'Không xuất được file Excel.');
    return false;
  } finally {
    exporting.value = false;
  }
}

function handleDocumentKeydown(event) {
  if (event.key !== 'Escape') return;
  if (selected.value) {
    selected.value = null;
  }
  if (exportDialog.value) {
    closeExportDialog();
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
watch(selected, () => nextTick(fitColumnsToContent));
watch(shownColumns, () => nextTick(fitColumnsToContent));

watch([action, actorId, dateFrom, dateTo, perPage], () => {
  loadLogs(1);
});

onMounted(() => {
  document.addEventListener('keydown', handleDocumentKeydown);
  loadOptions();
  loadLogs(1);
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
  wrapObserver?.disconnect();
});
</script>

<template>
  <section class="activity-page">
    <PageHeader
      title="Nhật ký hoạt động"
      icon="clock"
      description="Theo dõi các thao tác gần đây trên hệ thống."
      export-label="Dữ liệu"
      :export-options="exportOptions"
      :export-busy-key="exporting ? 'xlsx' : undefined"
    >
      <template #actions>
        <button type="button" class="activity-page__header-btn" :disabled="loading" @click="loadLogs(meta.current_page)">
          <AppIcon name="refresh" :size="16" :class="{ 'activity-page__spin': loading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

    <div class="activity-page__body">
      <div class="activity-page__main">
        <div v-if="hasVisibleFilterFields" class="activity-page__toolbar">
          <div class="activity-page__filters">
            <div v-if="visibleFilters.q" class="activity-page__field">
              <label class="activity-page__label" for="activity-q">Tìm kiếm</label>
              <input
                id="activity-q"
                v-model="query"
                type="search"
                class="activity-page__input"
                placeholder="Nguyễn Văn A, đăng nhập…"
                @keydown.enter="loadLogs(1)"
              />
            </div>

            <div v-if="visibleFilters.action" class="activity-page__field">
              <label class="activity-page__label" for="activity-action">Loại thao tác</label>
              <select id="activity-action" v-model="action" class="activity-page__input">
                <option v-for="item in actions" :key="item.value || 'all'" :value="item.value">
                  {{ item.label }}
                </option>
              </select>
            </div>

            <div v-if="visibleFilters.actor_id" class="activity-page__field">
              <label class="activity-page__label" for="activity-actor">Người dùng</label>
              <select id="activity-actor" v-model="actorId" class="activity-page__input">
                <option value="">Tất cả người dùng</option>
                <option value="system">Hệ thống</option>
                <option v-for="actor in actors" :key="actor.id" :value="actor.id">
                  {{ actor.name }}{{ actor.email ? ` — ${actor.email}` : '' }}
                </option>
              </select>
            </div>

            <div v-if="visibleFilters.date_from" class="activity-page__field">
              <label class="activity-page__label" for="activity-from">Từ ngày</label>
              <input id="activity-from" v-model="dateFrom" type="date" class="activity-page__input" />
            </div>

            <div v-if="visibleFilters.date_to" class="activity-page__field">
              <label class="activity-page__label" for="activity-to">Đến ngày</label>
              <input id="activity-to" v-model="dateTo" type="date" class="activity-page__input" />
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
          @search="loadLogs(1)"
          @clear-filters="clearFilters"
          @update:page="goPage"
          @update:per-page="perPage = $event"
          @update:zoom="tableZoom = $event"
        >
          <template #filters>
            <label v-for="item in ACTIVITY_FILTERS" :key="item.key" class="activity-page__check">
              <input
                type="checkbox"
                :checked="visibleFilters[item.key]"
                @change="onFilterToggle(item.key, $event.target.checked)"
              />
              <span>{{ item.label }}</span>
            </label>
          </template>
          <template #settings>
            <label v-for="col in ACTIVITY_COLUMNS" :key="col.key" class="activity-page__check">
              <input
                type="checkbox"
                :checked="visibleColumns[col.key]"
                @change="onColumnToggle(col.key, $event.target.checked)"
              />
              <span>{{ col.label }}</span>
            </label>
          </template>
        </TablePagesBar>

        <p v-if="hiddenActiveFilterLabels.length" class="activity-page__note">
          Đang lọc thêm theo: {{ hiddenActiveFilterLabels.join(', ') }} (bộ lọc đang ẩn).
        </p>

        <div
          ref="tableWrap"
          class="activity-page__table-wrap hide-scrollbar"
          :class="{ 'activity-page__table-wrap--resizing': resizing }"
          :style="{ '--table-zoom': tableZoom }"
        >
          <table class="activity-page__table" :style="{ width: tableWidthPx }">
            <colgroup>
              <col
                v-for="col in shownColumns"
                :key="col.key"
                :style="{ width: colWidthStyle(col.key) }"
              />
            </colgroup>
            <thead>
              <tr>
                <th v-for="col in shownColumns" :key="col.key">
                  <span>{{ col.label }}</span>
                  <button
                    type="button"
                    class="activity-page__resize"
                    aria-label="Kéo để đổi độ rộng cột"
                    @click.stop
                    @mousedown.stop.prevent="startResize($event, col.key)"
                  />
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td :colspan="colSpan" class="activity-page__empty">Đang tải…</td>
              </tr>
              <tr v-else-if="logs.length === 0">
                <td :colspan="colSpan" class="activity-page__empty">Chưa có hoạt động nào.</td>
              </tr>
              <tr
                v-for="log in logs"
                v-else
                :key="log.id"
                :class="{ 'activity-page__row--active': selected?.id === log.id }"
                @click="inspect(log)"
              >
                <td v-for="col in shownColumns" :key="col.key">
                  <template v-if="col.key === 'actor'">
                    <span class="activity-page__person">
                      <UserAvatarTip :user="actorUser(log)" label="Người thực hiện" />
                      <span class="activity-page__person-text">
                        <span>{{ cellText(log, 'actor') }}</span>
                        <span v-if="log.actor_email || log.actor?.email" class="activity-page__muted">
                          {{ log.actor_email || log.actor?.email }}
                        </span>
                      </span>
                    </span>
                  </template>
                  <span v-else class="activity-page__cell">{{ cellText(log, col.key) }}</span>
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

      <aside
        v-if="selected"
        class="activity-page__side"
        aria-label="Chi tiết thao tác"
      >
        <div class="activity-page__side-head">
          <h2 class="activity-page__side-title">Chi tiết thao tác</h2>
          <button type="button" class="activity-page__icon-btn" aria-label="Đóng" @click="selected = null">
            <AppIcon name="close" :size="16" />
          </button>
        </div>

        <div class="activity-page__side-lead" :class="`activity-page__side-lead--${actionTone(selected.action)}`">
          <span class="activity-page__dot" :class="`activity-page__dot--${actionTone(selected.action)}`" />
          <div>
            <span class="activity-page__side-lead-action">{{ selected.action_label || selected.action }}</span>
            <p class="activity-page__side-lead-desc">{{ selected.description }}</p>
          </div>
        </div>

        <div class="activity-page__rows">
          <div class="activity-page__row">
            <span class="activity-page__row-label">Thời gian</span>
            <span class="activity-page__row-value">{{ formatDateTime(selected.created_at) }}</span>
          </div>
          <div class="activity-page__row">
            <span class="activity-page__row-label">Ngày</span>
            <span class="activity-page__row-value">{{ formatDate(selected.created_at) }}</span>
          </div>
          <div class="activity-page__row">
            <span class="activity-page__row-label">Giờ</span>
            <span class="activity-page__row-value">{{ formatTime(selected.created_at) }}</span>
          </div>
          <div class="activity-page__row">
            <span class="activity-page__row-label">Người thực hiện</span>
            <span class="activity-page__row-value activity-page__row-actor">
              <UserAvatarTip :user="actorUser(selected)" label="Người thực hiện" />
              <span>{{ selected.actor_name || selected.actor?.name || 'Hệ thống' }}</span>
            </span>
          </div>
          <div v-if="selected.actor_email || selected.actor?.email" class="activity-page__row">
            <span class="activity-page__row-label">Email</span>
            <span class="activity-page__row-value">{{ selected.actor_email || selected.actor?.email }}</span>
          </div>
          <div class="activity-page__row">
            <span class="activity-page__row-label">Đối tượng</span>
            <span class="activity-page__row-value">{{ selected.subject_label || '—' }}</span>
          </div>
          <div v-if="selected.properties_summary" class="activity-page__row">
            <span class="activity-page__row-label">Chi tiết thêm</span>
            <span class="activity-page__row-value">{{ selected.properties_summary }}</span>
          </div>
          <div class="activity-page__row">
            <span class="activity-page__row-label">Địa chỉ mạng</span>
            <span class="activity-page__row-value">{{ selected.ip_address || '—' }}</span>
          </div>
          <div class="activity-page__row">
            <span class="activity-page__row-label">Trình duyệt</span>
            <span class="activity-page__row-value">{{ selected.browser || '—' }}</span>
          </div>
          <div v-if="selected.user_agent" class="activity-page__row">
            <span class="activity-page__row-label">Thông tin máy</span>
            <span class="activity-page__row-value">{{ selected.user_agent }}</span>
          </div>
          <div class="activity-page__row">
            <span class="activity-page__row-label">Mã bản ghi</span>
            <span class="activity-page__row-value">{{ selected.id }}</span>
          </div>
        </div>
      </aside>
    </div>

    <Teleport to="body">
      <div
        v-if="exportDialog"
        class="activity-export"
        role="presentation"
        @mousedown.self="closeExportDialog"
      >
        <div
          class="activity-export__panel"
          role="dialog"
          aria-modal="true"
          :aria-label="exportDialog === 'date' ? 'Xuất theo khoảng ngày' : 'Xuất theo người dùng'"
        >
          <template v-if="exportDialog === 'date'">
            <h2 class="activity-export__title">Xuất theo khoảng ngày</h2>
            <p class="activity-export__desc">
              File Excel sẽ gồm mọi thao tác từ ngày bắt đầu đến ngày kết thúc, không phụ thuộc bộ lọc khác trên trang.
            </p>
            <div class="activity-export__fields">
              <div class="activity-page__field">
                <label class="activity-page__label" for="export-from">Từ ngày</label>
                <input id="export-from" v-model="exportDateFrom" type="date" class="activity-page__input" />
              </div>
              <div class="activity-page__field">
                <label class="activity-page__label" for="export-to">Đến ngày</label>
                <input id="export-to" v-model="exportDateTo" type="date" class="activity-page__input" />
              </div>
            </div>
            <div class="activity-export__actions">
              <button type="button" class="activity-page__btn activity-page__btn--ghost" :disabled="exporting" @click="closeExportDialog">
                Huỷ
              </button>
              <button type="button" class="activity-page__btn" :disabled="exporting" @click="confirmExportDate">
                {{ exporting ? 'Đang xuất…' : 'Tải file Excel' }}
              </button>
            </div>
          </template>

          <template v-else>
            <h2 class="activity-export__title">Xuất theo người dùng</h2>
            <p class="activity-export__desc">
              File Excel sẽ gồm mọi thao tác của người được chọn, không phụ thuộc bộ lọc khác trên trang.
            </p>
            <div class="activity-page__field">
              <label class="activity-page__label" for="export-actor">Người dùng</label>
              <select id="export-actor" v-model="exportActorId" class="activity-page__input">
                <option value="">Chọn người dùng</option>
                <option value="system">Hệ thống</option>
                <option v-for="actor in actors" :key="`export-${actor.id}`" :value="actor.id">
                  {{ actor.name }}{{ actor.email ? ` — ${actor.email}` : '' }}
                </option>
              </select>
            </div>
            <div class="activity-export__actions">
              <button type="button" class="activity-page__btn activity-page__btn--ghost" :disabled="exporting" @click="closeExportDialog">
                Huỷ
              </button>
              <button type="button" class="activity-page__btn" :disabled="exporting" @click="confirmExportUser">
                {{ exporting ? 'Đang xuất…' : 'Tải file Excel' }}
              </button>
            </div>
          </template>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
.activity-page {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
}

.activity-page__header-btn {
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

.activity-page__header-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.activity-page__header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.activity-page__spin {
  animation: activity-spin 0.8s linear infinite;
}

@keyframes activity-spin {
  to {
    transform: rotate(360deg);
  }
}

.activity-page__body {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-4);
  overflow: hidden;
}

.activity-page__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.activity-page__toolbar {
  position: relative;
  z-index: 6;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin: var(--space-3) 0;
}

.activity-page__filters {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: var(--space-3);
  width: 100%;
}

.activity-page__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
  width: 100%;
}

.activity-page__field--inline {
  flex-direction: row;
  align-items: center;
  width: auto;
  gap: var(--space-2);
}

.activity-page__field--inline .activity-page__input {
  width: auto;
}

.activity-page__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.activity-page__input {
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

.activity-page__input--sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.8125rem;
}

.activity-page__btn {
  height: 2.375rem;
  padding: 0 1rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.activity-page__btn:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.activity-page__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.activity-page__btn--ghost {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.activity-page__btn--ghost:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.activity-page__check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.activity-page__note {
  flex-shrink: 0;
  margin: 0 0 var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.activity-page__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.activity-page__table-wrap--resizing {
  cursor: col-resize;
  user-select: none;
}

.activity-page__table {
  min-width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: calc(0.875rem * var(--table-zoom, 1));
}

.activity-page__table thead th {
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

.activity-page__resize {
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

.activity-page__resize::after {
  content: '';
  position: absolute;
  top: 25%;
  right: 2px;
  width: 2px;
  height: 50%;
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.activity-page__resize:hover::after,
.activity-page__table-wrap--resizing .activity-page__resize:hover::after {
  background: var(--color-primary);
}

.activity-page__table tbody td {
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  vertical-align: middle;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.activity-page__table tbody tr {
  cursor: pointer;
}

.activity-page__table tbody tr:hover td {
  background: var(--color-surface-muted);
}

.activity-page__row--active td {
  background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface));
}

.activity-page__cell {
  display: block;
  white-space: nowrap;
}

.activity-page__person {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  min-width: 0;
}

.activity-page__person-text {
  display: flex;
  min-width: 0;
  flex-direction: column;
}

.activity-page__person-text span {
  display: block;
  white-space: nowrap;
}

.activity-page__empty {
  padding: var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
  white-space: normal;
}

.activity-page__muted {
  margin-top: 0.125rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.activity-page__side {
  flex-shrink: 0;
  width: 20rem;
  overflow-y: auto;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.activity-page__side-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.activity-page__side-title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 700;
}

.activity-page__icon-btn {
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

.activity-page__icon-btn:hover {
  background: var(--color-surface);
}

.activity-page__side-lead {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  margin: var(--space-3) 0 var(--space-4);
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.activity-page__side-lead::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.activity-page__side-lead--success::before {
  background: var(--color-success);
}

.activity-page__side-lead--danger::before {
  background: var(--color-danger);
}

.activity-page__side-lead--info::before {
  background: var(--color-info);
}

.activity-page__side-lead-action {
  display: block;
  margin-bottom: var(--space-1);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.activity-page__side-lead-desc {
  margin: 0;
  color: var(--color-text);
  font-weight: 600;
  font-size: 0.9375rem;
  line-height: 1.45;
}

.activity-page__dot {
  flex-shrink: 0;
  margin-top: 0.375rem;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.activity-page__dot--success {
  background: var(--color-success);
}

.activity-page__dot--danger {
  background: var(--color-danger);
}

.activity-page__dot--info {
  background: var(--color-info);
}

.activity-page__dot--neutral {
  background: var(--color-text-muted);
}

.activity-page__rows {
  display: flex;
  flex-direction: column;
}

.activity-page__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.activity-page__row:last-child {
  box-shadow: none;
}

.activity-page__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.activity-page__row-value {
  color: var(--color-text);
  font-weight: 600;
  text-align: right;
  overflow-wrap: anywhere;
}

.activity-page__row-actor {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
}

.activity-export {
  position: fixed;
  inset: 0;
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
  background: var(--color-sidebar-overlay);
}

.activity-export__panel {
  width: 100%;
  max-width: 26rem;
  padding: var(--space-5);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.activity-export__title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 700;
}

.activity-export__desc {
  margin: var(--space-2) 0 var(--space-4);
  color: var(--color-text-muted);
  font-size: 0.875rem;
  line-height: 1.5;
}

.activity-export__fields {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-3);
}

.activity-export__actions {
  margin-top: var(--space-5);
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

@media (max-width: 1024px) {
  .activity-page__body {
    flex-direction: column;
  }

  .activity-page__side {
    width: 100%;
    max-height: 42%;
  }

  .activity-page__table-wrap {
    min-height: 16rem;
  }

  .activity-page__filters {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .activity-page {
    padding: var(--space-4);
  }

  .activity-page__filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .activity-export__fields {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .activity-page {
    padding: var(--space-3);
  }

  .activity-page__filters {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (prefers-reduced-motion: reduce) {
  .activity-page__spin {
    animation: none;
  }
}
</style>
