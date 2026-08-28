<script setup>
//
// "Tất cả công việc" — trang xem/sửa/xoá Task xuyên nhiều dự án, theo mẫu
// vàng data-table (ActivityLog.vue): filter → TablePagesBar top → bảng kéo
// cột → TablePagesBar bottom → panel chi tiết 28rem. Tạo Task mới KHÔNG có
// ở trang này (giữ nguyên tập trung ở menu chuột phải trong ProjectList) —
// xem plan Project Giai đoạn 2.
//
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { formatDate } from '@/lib/formatTime';
import { showClientToast } from '@/lib/clientToast';
import { useDragScroll } from '@/composables/useDragScroll';
import {
  COLUMN_STORAGE_KEY,
  COLUMN_WIDTH_KEY,
  FILTER_STORAGE_KEY,
  TASK_COLUMNS,
  TASK_FILTERS,
  TASK_PRIORITY_LABELS,
  TASK_STATUS_LABELS,
  TASK_STATUS_TONES,
  TASK_STATUSES,
  TASK_TYPE_LABELS,
  ZOOM_STORAGE_KEY,
  loadVisibility,
  saveVisibility,
} from '../constants/task.js';

const CELL_PAD_X = 32;
const COL_EXTRA = 24;
const ASSIGNEE_AVATAR_EXTRA = 42;
let measureCtx = null;
let wrapObserver = null;

const tasks = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 20 });
const loading = ref(false);
const selected = ref(null);
const editing = ref(false);
const saving = ref(false);
const deleting = ref(false);
const confirmingDelete = ref(false);

const query = ref('');
const projectId = ref('');
const assigneeId = ref('');
const status = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const perPage = ref(20);

const projects = ref([]);
const users = ref([]);

const visibleColumns = reactive(loadVisibility(COLUMN_STORAGE_KEY, TASK_COLUMNS));
const visibleFilters = reactive(loadVisibility(FILTER_STORAGE_KEY, TASK_FILTERS));

const tableWrap = ref(null);
const resizing = ref(false);
const MIN_COL_PX = 72;

useDragScroll(tableWrap, { isBlocked: () => resizing.value });

const columnWidths = reactive(loadColumnWidths());

const editForm = reactive({
  title: '',
  status: 'not_started',
  priority: '',
  start_date: '',
  end_date: '',
  actual_start_date: '',
  actual_end_date: '',
  assignee_id: '',
  progress_percent: '',
  description: '',
});

const shownColumns = computed(() => TASK_COLUMNS.filter((col) => col.always || visibleColumns[col.key]));
const colSpan = computed(() => Math.max(shownColumns.value.length, 1));

const hasActiveFilters = computed(
  () =>
    Boolean(query.value.trim()) ||
    Boolean(projectId.value) ||
    Boolean(assigneeId.value) ||
    Boolean(status.value) ||
    Boolean(dateFrom.value) ||
    Boolean(dateTo.value),
);

const hasVisibleFilterFields = computed(() => TASK_FILTERS.some((item) => visibleFilters[item.key]));

const tableZoom = ref(loadZoom());
const tableWidthPx = computed(() => {
  const keys = shownColumns.value.map((col) => col.key);
  const sum = keys.reduce((total, key) => total + (Number(columnWidths[key]) || 0), 0);
  return sum > 0 ? `${sum}px` : '100%';
});

function currentFilterParams() {
  return {
    q: query.value.trim() || undefined,
    project_id: projectId.value || undefined,
    assignee_id: assigneeId.value || undefined,
    status: status.value || undefined,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
  };
}

async function loadOptions() {
  // 2 API độc lập — role chỉ có task.view_assigned (không có project.view,
  // VD nhân viên thường) sẽ bị 403 ở /api/project nhưng vẫn cần
  // assignable-users hoạt động bình thường, nên KHÔNG gộp Promise.all
  // (1 API lỗi không được kéo API còn lại theo).
  try {
    const { data } = await window.axios.get('/api/project', { params: { per_page: 200 } });
    projects.value = (data.projects ?? []).map((p) => ({ id: p.id, name: p.name, code: p.code }));
  } catch {
    // Không có quyền xem danh sách dự án — bỏ qua, dropdown "Dự án" chỉ rỗng.
  }

  try {
    const { data } = await window.axios.get('/api/project/assignable-users');
    users.value = data.users ?? [];
  } catch {
    // Bỏ qua — dropdown "Người thực hiện" chỉ rỗng.
  }
}

async function loadTasks(page = 1) {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/project/tasks', {
      params: { ...currentFilterParams(), page, per_page: perPage.value },
    });
    tasks.value = data.tasks ?? [];
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 20 };

    if (selected.value && !tasks.value.some((t) => t.id === selected.value.id)) {
      selected.value = null;
      editing.value = false;
    }
    nextTick(fitColumnsToContent);
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không tải được danh sách công việc.');
  } finally {
    loading.value = false;
  }
}

function goPage(page) {
  if (page < 1 || page > meta.value.last_page || page === meta.value.current_page) return;
  loadTasks(page);
}

function clearFilters() {
  query.value = '';
  projectId.value = '';
  assigneeId.value = '';
  status.value = '';
  dateFrom.value = '';
  dateTo.value = '';
  loadTasks(1);
}

function inspect(task) {
  if (selected.value?.id === task.id) return;
  selected.value = task;
  editing.value = false;
}

function closePanel() {
  selected.value = null;
  editing.value = false;
}

function startEdit() {
  if (!selected.value) return;
  editForm.title = selected.value.title || '';
  editForm.status = selected.value.status || 'not_started';
  editForm.priority = selected.value.priority || '';
  editForm.start_date = selected.value.start_date || '';
  editForm.end_date = selected.value.end_date || '';
  editForm.actual_start_date = selected.value.actual_start_date || '';
  editForm.actual_end_date = selected.value.actual_end_date || '';
  editForm.assignee_id = selected.value.assignee_id || '';
  editForm.progress_percent = selected.value.progress_percent ?? '';
  editForm.description = selected.value.description || '';
  editing.value = true;
}

function cancelEdit() {
  editing.value = false;
}

async function saveEdit() {
  if (!selected.value) return;
  saving.value = true;
  try {
    const payload = {
      title: editForm.title.trim(),
      status: editForm.status,
      priority: editForm.priority || null,
      start_date: editForm.start_date || null,
      end_date: editForm.end_date || null,
      actual_start_date: editForm.actual_start_date || null,
      actual_end_date: editForm.actual_end_date || null,
      assignee_id: editForm.assignee_id || null,
      progress_percent: editForm.progress_percent === '' ? null : Number(editForm.progress_percent),
      description: editForm.description || null,
    };
    const { data } = await window.axios.put(`/api/project/tasks/${selected.value.id}`, payload);
    const updated = data.task;
    const index = tasks.value.findIndex((t) => t.id === updated.id);
    if (index !== -1) tasks.value[index] = updated;
    selected.value = updated;
    editing.value = false;
    showClientToast('success', 'Đã cập nhật công việc.');
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không cập nhật được công việc.');
  } finally {
    saving.value = false;
  }
}

function askDelete() {
  confirmingDelete.value = true;
}

async function confirmDelete() {
  if (!selected.value) return;
  deleting.value = true;
  try {
    await window.axios.delete(`/api/project/tasks/${selected.value.id}`);
    tasks.value = tasks.value.filter((t) => t.id !== selected.value.id);
    meta.value.total = Math.max(0, meta.value.total - 1);
    showClientToast('success', 'Đã xoá công việc.');
    closePanel();
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không xoá được công việc — có thể còn công việc con.');
  } finally {
    deleting.value = false;
    confirmingDelete.value = false;
  }
}

function statusLabel(value) {
  return TASK_STATUS_LABELS[value] || value || '—';
}

function statusTone(value) {
  return TASK_STATUS_TONES[value] || 'neutral';
}

function typeLabel(value) {
  return TASK_TYPE_LABELS[value] || value || '—';
}

function priorityLabel(value) {
  if (!value) return '—';
  return TASK_PRIORITY_LABELS[value] || value;
}

function cellText(task, key) {
  if (key === 'title') return task.title || '—';
  if (key === 'project') return task.project?.name || '—';
  if (key === 'start_date') return formatDate(task.start_date) || '—';
  if (key === 'end_date') return formatDate(task.end_date) || '—';
  if (key === 'actual_start_date') return formatDate(task.actual_start_date) || '—';
  if (key === 'actual_end_date') return formatDate(task.actual_end_date) || '—';
  if (key === 'progress_percent') return task.progress_percent == null ? '—' : `${task.progress_percent}%`;
  if (key === 'type') return typeLabel(task.type);
  if (key === 'priority') return priorityLabel(task.priority);
  if (key === 'id') return String(task.id ?? '—');
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
  const table = tableWrap.value?.querySelector('.task-page__table');
  return {
    header: fontOf(table?.querySelector('thead th'), '600 12px "Be Vietnam Pro", sans-serif'),
    cell: fontOf(table?.querySelector('tbody td'), '400 14px "Be Vietnam Pro", sans-serif'),
  };
}

function columnContentWidth(key, fonts) {
  const label = TASK_COLUMNS.find((col) => col.key === key)?.label ?? '';
  let maxW = measureText(label, fonts.header);
  for (const task of tasks.value) {
    if (key === 'assignee') {
      maxW = Math.max(maxW, measureText(task.assignee?.name || '—', fonts.cell));
    } else if (key === 'status') {
      maxW = Math.max(maxW, measureText(statusLabel(task.status), fonts.cell));
    } else {
      maxW = Math.max(maxW, measureText(cellText(task, key), fonts.cell));
    }
  }
  const extra = key === 'assignee' ? ASSIGNEE_AVATAR_EXTRA : 0;
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
    const remaining = TASK_COLUMNS.filter((col) => !col.always && visibleColumns[col.key] && col.key !== key).length;
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
  if (confirmingDelete.value) {
    confirmingDelete.value = false;
    return;
  }
  if (editing.value) {
    cancelEdit();
    return;
  }
  if (selected.value) {
    closePanel();
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

watch([projectId, assigneeId, status, dateFrom, dateTo, perPage], () => {
  loadTasks(1);
});

onMounted(() => {
  document.addEventListener('keydown', handleDocumentKeydown);
  loadOptions();
  loadTasks(1);
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
  <section class="task-page">
    <PageHeader
      title="Tất cả công việc"
      icon="layoutList"
      description="Xem, sửa và xoá công việc xuyên toàn bộ dự án. Tạo công việc mới qua menu chuột phải trong từng dự án."
    >
      <template #actions>
        <button type="button" class="task-page__header-btn" :disabled="loading" @click="loadTasks(meta.current_page)">
          <AppIcon name="refresh" :size="16" :class="{ 'task-page__spin': loading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

    <div class="task-page__body">
      <div class="task-page__main">
        <div v-if="hasVisibleFilterFields" class="task-page__toolbar">
          <div class="task-page__filters">
            <div v-if="visibleFilters.q" class="task-page__field">
              <label class="task-page__label" for="task-q">Tìm kiếm</label>
              <input
                id="task-q"
                v-model="query"
                type="search"
                class="task-page__input"
                placeholder="Tên công việc…"
                @keydown.enter="loadTasks(1)"
              />
            </div>

            <div v-if="visibleFilters.project_id" class="task-page__field">
              <label class="task-page__label" for="task-project">Dự án</label>
              <select id="task-project" v-model="projectId" class="task-page__input">
                <option value="">Tất cả dự án</option>
                <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>

            <div v-if="visibleFilters.assignee_id" class="task-page__field">
              <label class="task-page__label" for="task-assignee">Người thực hiện</label>
              <select id="task-assignee" v-model="assigneeId" class="task-page__input">
                <option value="">Tất cả người thực hiện</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>

            <div v-if="visibleFilters.status" class="task-page__field">
              <label class="task-page__label" for="task-status">Trạng thái</label>
              <select id="task-status" v-model="status" class="task-page__input">
                <option v-for="item in TASK_STATUSES" :key="item.value || 'all'" :value="item.value">
                  {{ item.label }}
                </option>
              </select>
            </div>

            <div v-if="visibleFilters.date_from" class="task-page__field">
              <label class="task-page__label" for="task-from">Từ ngày</label>
              <input id="task-from" v-model="dateFrom" type="date" class="task-page__input" />
            </div>

            <div v-if="visibleFilters.date_to" class="task-page__field">
              <label class="task-page__label" for="task-to">Đến ngày</label>
              <input id="task-to" v-model="dateTo" type="date" class="task-page__input" />
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
          @search="loadTasks(1)"
          @clear-filters="clearFilters"
          @update:page="goPage"
          @update:per-page="perPage = $event"
          @update:zoom="tableZoom = $event"
        >
          <template #filters>
            <label v-for="item in TASK_FILTERS" :key="item.key" class="task-page__check">
              <input
                type="checkbox"
                :checked="visibleFilters[item.key]"
                @change="onFilterToggle(item.key, $event.target.checked)"
              />
              <span>{{ item.label }}</span>
            </label>
          </template>
          <template #settings>
            <label v-for="col in TASK_COLUMNS" :key="col.key" class="task-page__check">
              <input
                type="checkbox"
                :checked="col.always || visibleColumns[col.key]"
                :disabled="col.always"
                @change="onColumnToggle(col.key, $event.target.checked)"
              />
              <span>{{ col.label }}</span>
            </label>
          </template>
        </TablePagesBar>

        <div
          ref="tableWrap"
          class="task-page__table-wrap hide-scrollbar"
          :class="{ 'task-page__table-wrap--resizing': resizing }"
          :style="{ '--table-zoom': tableZoom }"
        >
          <table class="task-page__table" :style="{ width: tableWidthPx }">
            <colgroup>
              <col v-for="col in shownColumns" :key="col.key" :style="{ width: colWidthStyle(col.key) }" />
            </colgroup>
            <thead>
              <tr>
                <th v-for="col in shownColumns" :key="col.key">
                  <span>{{ col.label }}</span>
                  <button
                    type="button"
                    class="task-page__resize"
                    aria-label="Kéo để đổi độ rộng cột"
                    @click.stop
                    @mousedown.stop.prevent="startResize($event, col.key)"
                  />
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td :colspan="colSpan" class="task-page__empty">Đang tải…</td>
              </tr>
              <tr v-else-if="tasks.length === 0">
                <td :colspan="colSpan" class="task-page__empty">Chưa có công việc nào.</td>
              </tr>
              <tr
                v-for="task in tasks"
                v-else
                :key="task.id"
                :class="{ 'task-page__row--active': selected?.id === task.id }"
                @click="inspect(task)"
              >
                <td v-for="col in shownColumns" :key="col.key">
                  <template v-if="col.key === 'assignee'">
                    <span class="task-page__person">
                      <UserAvatarTip :user="task.assignee" label="Người thực hiện" />
                      <span class="task-page__cell">{{ task.assignee?.name || '—' }}</span>
                    </span>
                  </template>
                  <template v-else-if="col.key === 'status'">
                    <span class="task-page__status">
                      <span class="task-page__dot" :class="`task-page__dot--${statusTone(task.status)}`" />
                      {{ statusLabel(task.status) }}
                    </span>
                  </template>
                  <span v-else class="task-page__cell">{{ cellText(task, col.key) }}</span>
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

      <aside v-if="selected" class="task-page__side" aria-label="Chi tiết công việc">
        <div class="task-page__side-head">
          <h2 class="task-page__side-title">Chi tiết công việc</h2>
          <div class="task-page__side-actions">
            <button
              v-if="!editing"
              type="button"
              class="task-page__icon-btn"
              aria-label="Sửa công việc"
              @click="startEdit"
            >
              <AppIcon name="pencil" :size="16" />
            </button>
            <button
              v-if="!editing"
              type="button"
              class="task-page__icon-btn"
              aria-label="Xoá công việc"
              @click="askDelete"
            >
              <AppIcon name="trash" :size="16" />
            </button>
            <button type="button" class="task-page__icon-btn" aria-label="Đóng" @click="closePanel">
              <AppIcon name="close" :size="16" />
            </button>
          </div>
        </div>

        <div class="task-page__side-lead" :class="`task-page__side-lead--${statusTone(selected.status)}`">
          <span class="task-page__dot task-page__dot--lg" :class="`task-page__dot--${statusTone(selected.status)}`" />
          <div>
            <span class="task-page__side-lead-project">{{ selected.project?.name || '—' }}</span>
            <p class="task-page__side-lead-desc">{{ selected.title }}</p>
          </div>
        </div>

        <form v-if="editing" class="task-page__form" @submit.prevent="saveEdit">
          <label class="task-page__field">
            <span class="task-page__label">Tên công việc</span>
            <input v-model="editForm.title" type="text" class="task-page__input" required maxlength="255" />
          </label>
          <label class="task-page__field">
            <span class="task-page__label">Trạng thái</span>
            <select v-model="editForm.status" class="task-page__input">
              <option v-for="item in TASK_STATUSES.filter((s) => s.value)" :key="item.value" :value="item.value">
                {{ item.label }}
              </option>
            </select>
          </label>
          <label class="task-page__field">
            <span class="task-page__label">Người thực hiện</span>
            <select v-model="editForm.assignee_id" class="task-page__input">
              <option value="">Chưa gán</option>
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </label>
          <label class="task-page__field">
            <span class="task-page__label">Tiến độ (%)</span>
            <input v-model="editForm.progress_percent" type="number" min="0" max="100" class="task-page__input" />
          </label>
          <label class="task-page__field">
            <span class="task-page__label">Ngày bắt đầu</span>
            <input v-model="editForm.start_date" type="date" class="task-page__input" />
          </label>
          <label class="task-page__field">
            <span class="task-page__label">Ngày kết thúc</span>
            <input v-model="editForm.end_date" type="date" class="task-page__input" />
          </label>
          <label class="task-page__field">
            <span class="task-page__label">Bắt đầu thực tế</span>
            <input v-model="editForm.actual_start_date" type="date" class="task-page__input" />
          </label>
          <label class="task-page__field">
            <span class="task-page__label">Kết thúc thực tế</span>
            <input v-model="editForm.actual_end_date" type="date" class="task-page__input" />
          </label>
          <label class="task-page__field task-page__field--full">
            <span class="task-page__label">Mô tả</span>
            <textarea v-model="editForm.description" class="task-page__input task-page__textarea" rows="3" />
          </label>
          <div class="task-page__form-actions">
            <button type="button" class="task-page__btn task-page__btn--ghost" :disabled="saving" @click="cancelEdit">
              Huỷ
            </button>
            <button type="submit" class="task-page__btn" :disabled="saving">
              {{ saving ? 'Đang lưu…' : 'Lưu thay đổi' }}
            </button>
          </div>
        </form>

        <div v-else class="task-page__rows">
          <div class="task-page__row">
            <span class="task-page__row-label">Dự án</span>
            <span class="task-page__row-value">{{ selected.project?.name || '—' }}</span>
          </div>
          <div class="task-page__row">
            <span class="task-page__row-label">Loại</span>
            <span class="task-page__row-value">{{ typeLabel(selected.type) }}</span>
          </div>
          <div class="task-page__row">
            <span class="task-page__row-label">Trạng thái</span>
            <span class="task-page__row-value">{{ statusLabel(selected.status) }}</span>
          </div>
          <div class="task-page__row">
            <span class="task-page__row-label">Mức độ ưu tiên</span>
            <span class="task-page__row-value">{{ priorityLabel(selected.priority) }}</span>
          </div>
          <div class="task-page__row">
            <span class="task-page__row-label">Người thực hiện</span>
            <span class="task-page__row-value task-page__row-person">
              <UserAvatarTip :user="selected.assignee" label="Người thực hiện" />
              <span>{{ selected.assignee?.name || 'Chưa gán' }}</span>
            </span>
          </div>
          <div class="task-page__row">
            <span class="task-page__row-label">Tiến độ</span>
            <span class="task-page__row-value">
              {{ selected.progress_percent == null ? 'Chưa cập nhật' : `${selected.progress_percent}%` }}
            </span>
          </div>
          <div class="task-page__row">
            <span class="task-page__row-label">Ngày bắt đầu</span>
            <span class="task-page__row-value">{{ formatDate(selected.start_date) || '—' }}</span>
          </div>
          <div class="task-page__row">
            <span class="task-page__row-label">Ngày kết thúc</span>
            <span class="task-page__row-value">{{ formatDate(selected.end_date) || '—' }}</span>
          </div>
          <div v-if="selected.actual_start_date" class="task-page__row">
            <span class="task-page__row-label">Bắt đầu thực tế</span>
            <span class="task-page__row-value">{{ formatDate(selected.actual_start_date) }}</span>
          </div>
          <div v-if="selected.actual_end_date" class="task-page__row">
            <span class="task-page__row-label">Kết thúc thực tế</span>
            <span class="task-page__row-value">{{ formatDate(selected.actual_end_date) }}</span>
          </div>
          <div v-if="selected.description" class="task-page__row">
            <span class="task-page__row-label">Mô tả</span>
            <span class="task-page__row-value">{{ selected.description }}</span>
          </div>
          <div class="task-page__row">
            <span class="task-page__row-label">Người tạo</span>
            <span class="task-page__row-value">{{ selected.creator?.name || '—' }}</span>
          </div>
          <div class="task-page__row">
            <span class="task-page__row-label">Mã công việc</span>
            <span class="task-page__row-value">{{ selected.id }}</span>
          </div>
        </div>
      </aside>
    </div>

    <ConfirmDialog
      :open="confirmingDelete"
      title="Xoá công việc"
      :description="`Bạn có chắc muốn xoá công việc “${selected?.title || ''}”? Thao tác này không thể hoàn tác.`"
      confirm-label="Xoá"
      :loading="deleting"
      danger
      @confirm="confirmDelete"
      @update:open="confirmingDelete = $event"
    />
  </section>
</template>

<style scoped>
.task-page {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
}

.task-page__header-btn {
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

.task-page__header-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.task-page__header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.task-page__spin {
  animation: task-spin 0.8s linear infinite;
}

@keyframes task-spin {
  to {
    transform: rotate(360deg);
  }
}

.task-page__body {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-4);
  overflow: hidden;
}

.task-page__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.task-page__toolbar {
  position: relative;
  z-index: 6;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin: var(--space-3) 0;
}

.task-page__filters {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: var(--space-3);
  width: 100%;
}

.task-page__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
  width: 100%;
}

.task-page__field--full {
  grid-column: 1 / -1;
}

.task-page__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.task-page__input {
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

.task-page__textarea {
  resize: vertical;
  min-height: 4.5rem;
}

.task-page__btn {
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

.task-page__btn:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.task-page__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.task-page__btn--ghost {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.task-page__btn--ghost:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.task-page__check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.task-page__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.task-page__table-wrap--resizing {
  cursor: col-resize;
  user-select: none;
}

.task-page__table {
  min-width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: calc(0.875rem * var(--table-zoom, 1));
}

.task-page__table thead th {
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

.task-page__resize {
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

.task-page__resize::after {
  content: '';
  position: absolute;
  top: 25%;
  right: 2px;
  width: 2px;
  height: 50%;
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.task-page__resize:hover::after {
  background: var(--color-primary);
}

.task-page__table tbody td {
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  vertical-align: middle;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.task-page__table tbody tr {
  cursor: pointer;
}

.task-page__table tbody tr:hover td {
  background: var(--color-surface-muted);
}

.task-page__row--active td {
  background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface));
}

.task-page__cell {
  display: block;
  white-space: nowrap;
}

.task-page__person {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  min-width: 0;
}

.task-page__status {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.task-page__dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.task-page__dot--lg {
  margin-top: 0.375rem;
  width: 0.625rem;
  height: 0.625rem;
}

.task-page__dot--neutral {
  background: var(--color-text-muted);
}

.task-page__dot--info {
  background: var(--color-info);
}

.task-page__dot--warning {
  background: var(--color-gold);
}

.task-page__dot--success {
  background: var(--color-success);
}

.task-page__dot--danger {
  background: var(--color-danger);
}

.task-page__empty {
  padding: var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
  white-space: normal;
}

.task-page__side {
  flex-shrink: 0;
  width: 28rem;
  overflow-y: auto;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.task-page__side-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.task-page__side-title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 700;
}

.task-page__side-actions {
  display: flex;
  align-items: center;
  gap: var(--space-1);
}

.task-page__icon-btn {
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

.task-page__icon-btn:hover {
  background: var(--color-surface);
}

.task-page__side-lead {
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

.task-page__side-lead::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-text-muted);
}

.task-page__side-lead--info::before {
  background: var(--color-info);
}

.task-page__side-lead--warning::before {
  background: var(--color-gold);
}

.task-page__side-lead--success::before {
  background: var(--color-success);
}

.task-page__side-lead--danger::before {
  background: var(--color-danger);
}

.task-page__side-lead-project {
  display: block;
  margin-bottom: var(--space-1);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.task-page__side-lead-desc {
  margin: 0;
  color: var(--color-text);
  font-weight: 600;
  font-size: 0.9375rem;
  line-height: 1.45;
}

.task-page__rows {
  display: flex;
  flex-direction: column;
}

.task-page__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.task-page__row:last-child {
  box-shadow: none;
}

.task-page__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.task-page__row-label::after {
  content: ':';
}

.task-page__row-value {
  color: var(--color-text);
  font-style: italic;
  text-align: right;
  overflow-wrap: anywhere;
}

.task-page__row-person {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
}

.task-page__form {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
}

.task-page__form-actions {
  grid-column: 1 / -1;
  margin-top: var(--space-2);
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

@media (max-width: 1279px) {
  .task-page__body {
    flex-direction: column;
  }

  .task-page__side {
    width: 100%;
    max-height: 42%;
  }

  .task-page__table-wrap {
    min-height: 16rem;
  }

  .task-page__filters {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .task-page__form {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 768px) {
  .task-page {
    padding: var(--space-4);
  }

  .task-page__filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 480px) {
  .task-page {
    padding: var(--space-3);
  }

  .task-page__filters {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (prefers-reduced-motion: reduce) {
  .task-page__spin {
    animation: none;
  }
}
</style>
