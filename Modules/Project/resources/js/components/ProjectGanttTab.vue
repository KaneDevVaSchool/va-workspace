<script setup>
//
// Gantt WBS trên tab Công việc (chi tiết dự án) — bố cục 2 pane theo mẫu
// 1Office: bảng cột trái + timeline phải, thanh công việc, quá hạn, zoom.
//
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import { useDragScroll } from '@/composables/useDragScroll';
import TaskPeopleListModal from './TaskPeopleListModal.vue';
import { showClientToast } from '@/lib/clientToast';
import { computeExpectedProgress } from '@/lib/progress';
import {
  TASK_PRIORITY_LABELS,
  TASK_PRIORITY_TONES,
  TASK_STATUS_LABELS,
  TASK_STATUS_TONES,
  TASK_TYPE_LABELS,
  TASK_TYPE_TONES,
  loadVisibility,
  parseYmd,
  saveVisibility,
} from '../constants/task.js';
import {
  GANTT_BAR_H,
  GANTT_BAR_TOP,
  GANTT_COLUMNS,
  GANTT_COL_STORAGE,
  GANTT_COLLAPSE_STORAGE,
  GANTT_DEFAULT_DAY_WIDTH,
  GANTT_DEFAULT_LEFT_WIDTH,
  GANTT_EXTEND_DAYS,
  GANTT_HEAD_GROUP_H,
  GANTT_HEAD_UNIT_H,
  GANTT_LEFT_STORAGE,
  GANTT_MIN_LEFT_WIDTH,
  GANTT_PAD_AFTER,
  GANTT_PAD_BEFORE,
  GANTT_ROW_HEIGHT,
  GANTT_SCALE_STORAGE,
  GANTT_SCALES,
  GANTT_SORT_STORAGE,
  GANTT_SORTS,
  GANTT_WIDTH_STORAGE,
  GANTT_ZOOM_STEPS,
  GANTT_ZOOM_STORAGE,
  barLayout,
  buildTimeline,
  collectParentIds,
  dateToX,
  deadlineDisplay,
  durationDays,
  effectiveSpan,
  flattenGanttRows,
  formatViDate,
  loadJson,
  peopleOf,
  saveJson,
  sortTaskTree,
  startOfToday,
  tailLayout,
} from '../constants/gantt.js';

const props = defineProps({
  tree: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  project: { type: Object, default: null },
  filter: { type: String, default: 'all' },
  filterLabel: { type: String, default: 'Tổng công việc' },
  fill: { type: Boolean, default: false },
});

const leftHead = ref(null);
const leftBody = ref(null);
const rightHead = ref(null);
const rightBody = ref(null);
const splitterBusy = ref(false);
const colResizeKey = ref(null);
const popover = ref(null);
const hoveredId = ref(null);
const exporting = ref(false);
const rightScrollLeft = ref(0);
const rightScrollTop = ref(0);
const rightClientWidth = ref(0);
const collapsed = ref(new Set(loadJson(GANTT_COLLAPSE_STORAGE, [])));
const leftWidth = ref(loadJson(GANTT_LEFT_STORAGE, GANTT_DEFAULT_LEFT_WIDTH) || GANTT_DEFAULT_LEFT_WIDTH);
const dayWidth = ref(loadJson(GANTT_ZOOM_STORAGE, GANTT_DEFAULT_DAY_WIDTH) || GANTT_DEFAULT_DAY_WIDTH);
const scale = ref(loadJson(GANTT_SCALE_STORAGE, 'day') || 'day');
const sortState = reactive(loadJson(GANTT_SORT_STORAGE, { key: 'start_date', dir: 'asc' }) || { key: 'start_date', dir: 'asc' });
const showWeekends = ref(true);
const showToday = ref(true);
const padBefore = ref(GANTT_PAD_BEFORE);
const padAfter = ref(GANTT_PAD_AFTER);
const visibility = reactive(loadVisibility(GANTT_COL_STORAGE, GANTT_COLUMNS));
const colWidths = reactive(loadJson(GANTT_WIDTH_STORAGE, {}) || {});

useDragScroll(leftBody, { isBlocked: () => splitterBusy.value || Boolean(colResizeKey.value) });
useDragScroll(rightBody, { isBlocked: () => splitterBusy.value || Boolean(colResizeKey.value) });

const visibleCols = computed(() =>
  GANTT_COLUMNS.filter((col) => col.always || visibility[col.key]).map((col) => ({
    ...col,
    width: Number(colWidths[col.key]) || col.width,
  })),
);

const leftTableWidth = computed(() => visibleCols.value.reduce((sum, col) => sum + col.width, 0));

const sortedTree = computed(() => sortTaskTree(props.tree || [], sortState.key, sortState.dir));

const rows = computed(() => flattenGanttRows(sortedTree.value, collapsed.value, props.filter));

const scheduledRows = computed(() =>
  rows.value.map((row) => {
    const span = effectiveSpan(row);
    return { row, span };
  }),
);

const unscheduled = computed(() =>
  rows.value.filter((row) => row.type === 'task' && !effectiveSpan(row)),
);

const parentIds = computed(() => collectParentIds(props.tree || []));
const allCollapsed = computed(() => parentIds.value.length > 0 && parentIds.value.every((id) => collapsed.value.has(id)));

const dateBounds = computed(() => {
  const starts = [];
  const ends = [];
  const push = (value) => {
    const date = parseYmd(value);
    if (date) {
      starts.push(date);
      ends.push(date);
    }
  };
  push(props.project?.start_date);
  push(props.project?.end_date);
  for (const { span } of scheduledRows.value) {
    if (!span) continue;
    starts.push(span.start);
    ends.push(span.end);
  }
  const today = startOfToday();
  if (!starts.length) {
    const from = new Date(today.getFullYear(), today.getMonth(), 1);
    const to = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    return { from, to };
  }
  return {
    from: new Date(Math.min(...starts.map((d) => d.getTime()))),
    to: new Date(Math.max(today.getTime(), ...ends.map((d) => d.getTime()))),
  };
});

const rangeStart = computed(() => {
  const from = dateBounds.value.from;
  return new Date(from.getFullYear(), from.getMonth(), from.getDate() - padBefore.value);
});

const rangeEnd = computed(() => {
  const to = dateBounds.value.to;
  return new Date(to.getFullYear(), to.getMonth(), to.getDate() + padAfter.value);
});

const timeline = computed(() => buildTimeline(rangeStart.value, rangeEnd.value, scale.value, dayWidth.value));

const today = computed(() => startOfToday());
const todayX = computed(() => dateToX(today.value, timeline.value.origin, timeline.value.pxPerDay));
const todayVisible = computed(() => {
  if (!showToday.value) return false;
  return today.value >= rangeStart.value && today.value <= rangeEnd.value;
});

const bodyHeight = computed(() => Math.max(GANTT_ROW_HEIGHT, rows.value.length * GANTT_ROW_HEIGHT));
const zoomIndex = computed(() => {
  const idx = GANTT_ZOOM_STEPS.indexOf(dayWidth.value);
  return idx >= 0 ? idx : 2;
});

const sortLabel = computed(() => {
  const item = GANTT_SORTS.find((s) => s.key === sortState.key);
  return item?.label || 'Thứ tự';
});

const weekendUnits = computed(() =>
  showWeekends.value && scale.value === 'day' ? timeline.value.units.filter((u) => u.weekend) : [],
);

function colStyle(col) {
  return { width: `${col.width}px`, minWidth: `${col.width}px` };
}

function statusChipTone(status) {
  if (status === 'in_progress') return 'info';
  return TASK_STATUS_TONES[status] || 'neutral';
}

function statusLabel(status) {
  return TASK_STATUS_LABELS[status] || status || '—';
}

function barTone(row) {
  if (row.is_overdue) return 'overdue';
  if (row.status === 'completed') return 'completed';
  if (row.status === 'on_hold') return 'hold';
  if (row.status === 'cancelled') return 'cancelled';
  if (row.type !== 'task') return 'group';
  if (row.status === 'in_progress') return 'active';
  return 'idle';
}

function barMeta(row, span) {
  if (!span) return null;
  const box = barLayout(span, timeline.value.origin, timeline.value.pxPerDay);
  if (!box) return null;
  const tail = tailLayout(row, span, timeline.value.origin, timeline.value.pxPerDay, today.value);
  const pct = Math.max(0, Math.min(100, Number(row.progress_percent) || 0));
  return { ...box, tail, pct, tone: barTone(row) };
}

const viewRows = computed(() =>
  scheduledRows.value.map((item, index) => {
    const meta = barMeta(item.row, item.span);
    return { ...item, index, meta, expected: expectedPct(item.row), deadline: deadlineDisplay(item.row, today.value) };
  }),
);

function jumpDir(meta) {
  if (!meta) return null;
  const viewLeft = rightScrollLeft.value;
  const viewRight = viewLeft + rightClientWidth.value;
  if (meta.left + meta.width < viewLeft + 8) return 'left';
  if (meta.left > viewRight - 8) return 'right';
  return null;
}

function jumpTo(meta) {
  const wrap = rightBody.value;
  if (!wrap || !meta) return;
  wrap.scrollLeft = Math.max(0, meta.left - 48);
}

function toggleCollapse(row) {
  if (!row.hasChildren) return;
  const next = new Set(collapsed.value);
  if (next.has(row.id)) next.delete(row.id);
  else next.add(row.id);
  collapsed.value = next;
  saveJson(GANTT_COLLAPSE_STORAGE, [...next]);
}

function toggleExpandAll() {
  const next = new Set();
  if (!allCollapsed.value) {
    for (const id of parentIds.value) next.add(id);
  }
  collapsed.value = next;
  saveJson(GANTT_COLLAPSE_STORAGE, [...next]);
}

function toggleCol(key) {
  const def = GANTT_COLUMNS.find((c) => c.key === key);
  if (!def || def.always) return;
  visibility[key] = !visibility[key];
  saveVisibility(GANTT_COL_STORAGE, { ...visibility });
}

function setSort(key) {
  if (sortState.key === key) {
    sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
  } else {
    sortState.key = key;
    sortState.dir = key === 'title' ? 'asc' : 'asc';
  }
  saveJson(GANTT_SORT_STORAGE, { key: sortState.key, dir: sortState.dir });
}

function setScale(value) {
  scale.value = value;
  saveJson(GANTT_SCALE_STORAGE, value);
}

function zoomBy(delta) {
  const next = Math.max(0, Math.min(GANTT_ZOOM_STEPS.length - 1, zoomIndex.value + delta));
  dayWidth.value = GANTT_ZOOM_STEPS[next];
  saveJson(GANTT_ZOOM_STORAGE, dayWidth.value);
}

function persistLeftWidth() {
  saveJson(GANTT_LEFT_STORAGE, Math.round(leftWidth.value));
}

function persistColWidths() {
  saveJson(GANTT_WIDTH_STORAGE, { ...colWidths });
}

function onLeftScroll() {
  if (!leftBody.value) return;
  if (leftHead.value) leftHead.value.scrollLeft = leftBody.value.scrollLeft;
  if (rightBody.value && rightBody.value.scrollTop !== leftBody.value.scrollTop) {
    rightBody.value.scrollTop = leftBody.value.scrollTop;
  }
  rightScrollTop.value = leftBody.value.scrollTop;
}

function onRightScroll() {
  if (!rightBody.value) return;
  rightScrollLeft.value = rightBody.value.scrollLeft;
  rightScrollTop.value = rightBody.value.scrollTop;
  rightClientWidth.value = rightBody.value.clientWidth;
  if (rightHead.value) rightHead.value.scrollLeft = rightBody.value.scrollLeft;
  if (leftBody.value && leftBody.value.scrollTop !== rightBody.value.scrollTop) {
    leftBody.value.scrollTop = rightBody.value.scrollTop;
  }
}

function measureRight() {
  if (!rightBody.value) return;
  rightClientWidth.value = rightBody.value.clientWidth;
  rightScrollLeft.value = rightBody.value.scrollLeft;
  rightScrollTop.value = rightBody.value.scrollTop;
}

function scrollToToday() {
  const wrap = rightBody.value;
  if (!wrap || !todayVisible.value) return;
  wrap.scrollLeft = Math.max(0, todayX.value - wrap.clientWidth * 0.35);
  onRightScroll();
}

function startSplitter(event) {
  event.preventDefault();
  splitterBusy.value = true;
  const startX = event.clientX;
  const startW = leftWidth.value;
  const onMove = (ev) => {
    const max = Math.round((rootWidth() || 1200) * 0.72);
    leftWidth.value = Math.max(GANTT_MIN_LEFT_WIDTH, Math.min(max, startW + ev.clientX - startX));
  };
  const onUp = () => {
    splitterBusy.value = false;
    persistLeftWidth();
    window.removeEventListener('mousemove', onMove);
    window.removeEventListener('mouseup', onUp);
  };
  window.addEventListener('mousemove', onMove);
  window.addEventListener('mouseup', onUp);
}

function startColResize(event, key) {
  event.preventDefault();
  event.stopPropagation();
  colResizeKey.value = key;
  const startX = event.clientX;
  const startW = Number(colWidths[key]) || GANTT_COLUMNS.find((c) => c.key === key)?.width || 100;
  const onMove = (ev) => {
    colWidths[key] = Math.max(64, startW + ev.clientX - startX);
  };
  const onUp = () => {
    colResizeKey.value = null;
    persistColWidths();
    window.removeEventListener('mousemove', onMove);
    window.removeEventListener('mouseup', onUp);
  };
  window.addEventListener('mousemove', onMove);
  window.addEventListener('mouseup', onUp);
}

function rootWidth() {
  return leftBody.value?.closest('.gantt')?.clientWidth || 0;
}

function togglePopover(name) {
  popover.value = popover.value === name ? null : name;
}

function onDocClick(event) {
  if (!popover.value) return;
  if (event.target.closest?.('[data-gantt-pop]')) return;
  popover.value = null;
}

function onKeydown(event) {
  if (event.key === 'Escape') popover.value = null;
}

async function exportTasks() {
  if (exporting.value || !props.project?.id) return;
  exporting.value = true;
  try {
    const response = await window.axios.get('/api/project/tasks/export', {
      params: { project_id: props.project.id },
      paramsSerializer: { indexes: null },
      responseType: 'blob',
    });
    const blob = response.data;
    if (blob.type && blob.type.includes('json')) {
      const json = JSON.parse(await blob.text());
      throw new Error(json.message || 'Không xuất được file Excel.');
    }
    const disposition = response.headers['content-disposition'] || '';
    const utfMatch = disposition.match(/filename\*=UTF-8''([^;]+)/i);
    const plainMatch = disposition.match(/filename="?([^"]+)"?/i);
    const filename = decodeURIComponent(utfMatch?.[1] || plainMatch?.[1] || 'Cong_viec.xlsx');
    const objectUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = objectUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(objectUrl);
    showClientToast('success', 'Đã tải file Excel.');
  } catch (error) {
    showClientToast('error', error?.message || error?.response?.data?.message || 'Không xuất được file Excel.');
  } finally {
    exporting.value = false;
    popover.value = null;
  }
}

function people(row) {
  return peopleOf(row);
}

const peopleModalOpen = ref(false);
const peopleModalTitle = ref('Người thực hiện');
const peopleModalPeople = ref([]);

function openPeopleModal(row) {
  const list = people(row);
  if (!list.length) return;
  peopleModalTitle.value = row.title ? `Người thực hiện — ${row.title}` : 'Người thực hiện';
  peopleModalPeople.value = list;
  peopleModalOpen.value = true;
}

function closePeopleModal() {
  peopleModalOpen.value = false;
}

function expectedPct(row) {
  return computeExpectedProgress(row.start_date, row.end_date);
}

function formatHours(value) {
  if (value == null || value === '') return '—';
  const n = Number(value);
  if (!Number.isFinite(n)) return '—';
  return `${n}`.replace(/\.0$/, '');
}

function cellText(row, key) {
  if (key === 'code') return row.code || '—';
  if (key === 'description') return row.description || '—';
  if (key === 'parent') return row.parent?.title || '—';
  if (key === 'duration') {
    const days = durationDays(row.start_date, row.end_date);
    return days ? `${days} ngày` : '—';
  }
  if (key === 'weight') return row.weight != null ? `${row.weight}%` : '—';
  if (key === 'estimated_hours') return formatHours(row.estimated_hours);
  if (key === 'worklog_hours') return formatHours(row.worklog_hours);
  if (key === 'attachments_count') return row.attachments_count ?? 0;
  if (key === 'variance_days') {
    if (row.variance_days == null) return '—';
    return row.variance_days > 0 ? `+${row.variance_days}` : String(row.variance_days);
  }
  if (key === 'created_at' || key === 'updated_at') return formatViDate(row[key]);
  if (key === 'actual_start_date' || key === 'actual_end_date' || key === 'start_date' || key === 'end_date') {
    return formatViDate(row[key]);
  }
  return '—';
}

watch(scale, () => nextTick(() => { measureRight(); scrollToToday(); }));
watch(dayWidth, () => nextTick(measureRight));
watch(() => rows.value.length, () => nextTick(measureRight));
watch(() => props.loading, (busy) => {
  if (!busy) nextTick(() => { measureRight(); scrollToToday(); });
});

onMounted(() => {
  document.addEventListener('mousedown', onDocClick);
  document.addEventListener('keydown', onKeydown);
  window.addEventListener('resize', measureRight);
  const max = Math.round((rootWidth() || 1200) * 0.72);
  leftWidth.value = Math.max(GANTT_MIN_LEFT_WIDTH, Math.min(max, Number(leftWidth.value) || GANTT_DEFAULT_LEFT_WIDTH));
  nextTick(() => {
    measureRight();
    scrollToToday();
  });
});

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onDocClick);
  document.removeEventListener('keydown', onKeydown);
  window.removeEventListener('resize', measureRight);
});
</script>

<template>
  <section class="gantt" :class="{ 'gantt--fill': fill }">
    <header class="gantt__toolbar">
      <div class="gantt__toolbar-left">
        <h3 class="gantt__title">{{ filterLabel }}</h3>
        <div class="gantt__btns">
          <button
            type="button"
            class="gantt__btn"
            :aria-label="allCollapsed ? 'Mở rộng' : 'Thu gọn'"
            @click="toggleExpandAll"
          >
            <AppIcon :name="allCollapsed ? 'chevronsDown' : 'chevronsUp'" :size="15" />
          </button>

          <div class="gantt__popwrap" data-gantt-pop>
            <button type="button" class="gantt__btn" :aria-expanded="popover === 'cols'" @click="togglePopover('cols')">
              <AppIcon name="columns" :size="15" />
              <span>Hiển thị</span>
            </button>
            <div v-if="popover === 'cols'" class="gantt__menu" role="menu">
              <label v-for="col in GANTT_COLUMNS" :key="col.key" class="gantt__check">
                <input type="checkbox" :checked="col.always || visibility[col.key]" :disabled="col.always" @change="toggleCol(col.key)" />
                <span>{{ col.label }}</span>
              </label>
            </div>
          </div>

          <div class="gantt__popwrap" data-gantt-pop>
            <button type="button" class="gantt__btn" :aria-expanded="popover === 'sort'" @click="togglePopover('sort')">
              <AppIcon name="chevronsUp" :size="15" :class="{ 'gantt__sort-icon--desc': sortState.dir === 'desc' }" />
              <span>Sắp xếp: {{ sortLabel }}</span>
            </button>
            <div v-if="popover === 'sort'" class="gantt__menu" role="menu">
              <button
                v-for="item in GANTT_SORTS"
                :key="item.key"
                type="button"
                class="gantt__menu-item"
                :class="{ 'gantt__menu-item--on': sortState.key === item.key }"
                role="menuitem"
                @click="setSort(item.key)"
              >
                <span>{{ item.label }}</span>
                <AppIcon v-if="sortState.key === item.key" :name="sortState.dir === 'desc' ? 'chevronDown' : 'chevronsUp'" :size="14" />
              </button>
            </div>
          </div>

          <div class="gantt__popwrap" data-gantt-pop>
            <button type="button" class="gantt__btn" :aria-expanded="popover === 'settings'" @click="togglePopover('settings')">
              <AppIcon name="sliders" :size="15" />
              <span>Tùy chỉnh</span>
            </button>
            <div v-if="popover === 'settings'" class="gantt__menu gantt__menu--settings" role="menu">
              <p class="gantt__menu-label">Thang thời gian</p>
              <button
                v-for="item in GANTT_SCALES"
                :key="item.value"
                type="button"
                class="gantt__menu-item"
                :class="{ 'gantt__menu-item--on': scale === item.value }"
                role="menuitem"
                @click="setScale(item.value)"
              >
                <span>{{ item.label }}</span>
                <AppIcon v-if="scale === item.value" name="check" :size="14" />
              </button>
              <label class="gantt__check">
                <input v-model="showWeekends" type="checkbox" />
                <span>Tô cuối tuần</span>
              </label>
              <label class="gantt__check">
                <input v-model="showToday" type="checkbox" />
                <span>Đường hôm nay</span>
              </label>
            </div>
          </div>

          <button type="button" class="gantt__btn" :disabled="exporting || !project?.id" @click="exportTasks">
            <AppIcon name="fileDown" :size="15" />
            <span>{{ exporting ? 'Đang xuất…' : 'Export công việc' }}</span>
          </button>
        </div>
      </div>
    </header>

    <p v-if="loading" class="gantt__empty">Đang tải công việc…</p>
    <p v-else-if="!rows.length" class="gantt__empty">
      {{ filter === 'all' ? 'Dự án chưa có công việc nào.' : 'Không có công việc phù hợp.' }}
    </p>

    <div v-else class="gantt__board">
      <div class="gantt__left" :style="{ width: `${leftWidth}px` }">
        <div ref="leftHead" class="gantt__left-head hide-scrollbar">
          <div class="gantt__head-row" :style="{ width: `${leftTableWidth}px` }">
            <div
              v-for="col in visibleCols"
              :key="col.key"
              class="gantt__hcol"
              :class="[`gantt__hcol--${col.align || 'left'}`, { 'gantt__hcol--sort': col.sortBy }]"
              :style="colStyle(col)"
              @click="col.sortBy && setSort(col.sortBy)"
            >
              <span>{{ col.label }}</span>
              <AppIcon
                v-if="sortState.key === col.sortBy"
                :name="sortState.dir === 'desc' ? 'chevronDown' : 'chevronsUp'"
                :size="12"
              />
              <span class="gantt__col-resizer" data-no-drag-scroll @mousedown.stop="startColResize($event, col.key)" />
            </div>
          </div>
        </div>
        <div ref="leftBody" class="gantt__left-body hide-scrollbar" @scroll="onLeftScroll">
          <div class="gantt__left-inner" :style="{ width: `${leftTableWidth}px`, height: `${bodyHeight}px` }">
            <div
              v-for="item in viewRows"
              :key="item.row.id"
              class="gantt__row"
              :class="{
                'gantt__row--hover': hoveredId === item.row.id,
                'gantt__row--group': item.row.hasChildren,
                'gantt__row--sub': item.row.depth > 0,
              }"
              :style="{ top: `${item.index * GANTT_ROW_HEIGHT}px`, height: `${GANTT_ROW_HEIGHT}px` }"
              @mouseenter="hoveredId = item.row.id"
              @mouseleave="hoveredId = null"
            >
              <div v-for="col in visibleCols" :key="col.key" class="gantt__cell" :class="`gantt__cell--${col.align || 'left'}`" :style="colStyle(col)">
                <template v-if="col.key === 'deadline'">
                  <span class="gantt__deadline" :class="`gantt__deadline--${item.deadline.tone}`">{{ item.deadline.text }}</span>
                </template>
                <template v-else-if="col.key === 'outline'">
                  <span class="gantt__outline">{{ item.row.wbs_code || '—' }}</span>
                </template>
                <template v-else-if="col.key === 'title'">
                  <div class="gantt__title-cell" :style="{ paddingLeft: `${item.row.depth * 1.1}rem` }">
                    <button
                      v-if="item.row.hasChildren"
                      type="button"
                      class="gantt__caret"
                      :aria-label="item.row.collapsed ? 'Mở nhóm' : 'Thu nhóm'"
                      :aria-expanded="!item.row.collapsed"
                      @click="toggleCollapse(item.row)"
                    >
                      <AppIcon name="folder" :size="14" />
                      <AppIcon name="chevronDown" :size="12" :class="{ 'gantt__caret-chevron--closed': item.row.collapsed }" />
                    </button>
                    <router-link
                      :to="{ name: 'manager.project.tasks.detail', params: { id: item.row.id } }"
                      class="gantt__task-link"
                      :class="{ 'gantt__task-link--overdue': item.row.is_overdue }"
                    >
                      {{ item.row.title }}
                    </router-link>
                  </div>
                </template>
                <template v-else-if="col.key === 'assignee'">
                  <button
                    type="button"
                    class="gantt__assignee-btn"
                    :class="{ 'gantt__assignee-btn--empty': !people(item.row).length }"
                    :disabled="!people(item.row).length"
                    :aria-label="`Xem người thực hiện của ${item.row.title}`"
                    @click.stop="openPeopleModal(item.row)"
                  >
                    <span v-if="!people(item.row).length">—</span>
                    <template v-else>
                      <span v-for="user in people(item.row).slice(0, 2)" :key="user.id" class="gantt__assignee-avatar" aria-hidden="true">
                        <img v-if="user.avatar_url" :src="user.avatar_url" alt="" referrerpolicy="no-referrer" />
                        <template v-else>{{ (user.name || '?').trim().charAt(0).toUpperCase() }}</template>
                      </span>
                      <span v-if="people(item.row).length > 2" class="gantt__people-more">+{{ people(item.row).length - 2 }}</span>
                    </template>
                  </button>
                </template>
                <template v-else-if="col.key === 'status'">
                  <span class="gantt__chip" :class="`gantt__chip--${statusChipTone(item.row.status)}`">
                    {{ statusLabel(item.row.status) }}
                  </span>
                </template>
                <template v-else-if="col.key === 'percent'">
                  <div class="gantt-pct" :aria-label="`Tiến độ ${item.row.progress_percent ?? 0}%`">
                    <div class="gantt-pct__track">
                      <span
                        v-if="item.expected != null"
                        class="gantt-pct__expected"
                        :style="{ width: `${Math.max(0, Math.min(100, item.expected))}%` }"
                      />
                      <span class="gantt-pct__actual" :style="{ width: `${Math.max(0, Math.min(100, Number(item.row.progress_percent) || 0))}%` }" />
                      <span
                        class="gantt-pct__knob"
                        :style="{ left: `${Math.max(0, Math.min(100, Number(item.row.progress_percent) || 0))}%` }"
                      >
                        {{ item.row.progress_percent ?? 0 }}
                      </span>
                    </div>
                  </div>
                </template>
                <template v-else-if="col.key === 'type'">
                  <span class="gantt__chip" :class="`gantt__chip--${TASK_TYPE_TONES[item.row.type] || 'neutral'}`">
                    {{ TASK_TYPE_LABELS[item.row.type] || item.row.type }}
                  </span>
                </template>
                <template v-else-if="col.key === 'priority'">
                  <span v-if="item.row.priority" class="gantt__chip" :class="`gantt__chip--${TASK_PRIORITY_TONES[item.row.priority] || 'neutral'}`">
                    {{ TASK_PRIORITY_LABELS[item.row.priority] || item.row.priority }}
                  </span>
                  <span v-else>—</span>
                </template>
                <template v-else-if="col.key === 'manager'">
                  <UserAvatarTip v-if="item.row.manager" :user="item.row.manager" label="Người quản lý" />
                  <span v-else>—</span>
                </template>
                <template v-else-if="col.key === 'watchers'">
                  <span class="gantt__people">
                    <UserAvatarTip
                      v-for="user in (item.row.watchers || []).slice(0, 2)"
                      :key="user.id"
                      :user="user"
                      label="Người theo dõi"
                    />
                    <span v-if="(item.row.watchers || []).length > 2" class="gantt__people-more">+{{ item.row.watchers.length - 2 }}</span>
                    <span v-if="!(item.row.watchers || []).length">—</span>
                  </span>
                </template>
                <template v-else>
                  {{ cellText(item.row, col.key) }}
                </template>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="gantt__splitter" data-no-drag-scroll @mousedown="startSplitter">
        <AppIcon name="gripVertical" :size="14" />
      </div>

      <div class="gantt__right">
        <div
          ref="rightHead"
          class="gantt__right-head hide-scrollbar"
          :style="{ height: `${GANTT_HEAD_GROUP_H + GANTT_HEAD_UNIT_H}px` }"
        >
          <div class="gantt__time-inner" :style="{ width: `${timeline.totalWidth}px`, height: `${GANTT_HEAD_GROUP_H + GANTT_HEAD_UNIT_H}px` }">
            <div
              v-for="group in timeline.groups"
              :key="group.key"
              class="gantt__month"
              :style="{ left: `${group.left}px`, width: `${group.width}px`, height: `${GANTT_HEAD_GROUP_H}px` }"
            >
              {{ group.label }}
            </div>
            <div
              v-for="unit in timeline.units"
              :key="unit.key"
              class="gantt__day"
              :class="{ 'gantt__day--weekend': unit.weekend }"
              :style="{
                left: `${unit.left}px`,
                width: `${unit.width}px`,
                top: `${GANTT_HEAD_GROUP_H}px`,
                height: `${GANTT_HEAD_UNIT_H}px`,
              }"
            >
              <span>{{ unit.label }}</span>
              <small v-if="unit.sub">{{ unit.sub }}</small>
            </div>
          </div>
        </div>

        <div ref="rightBody" class="gantt__right-body hide-scrollbar" @scroll="onRightScroll">
          <div class="gantt__time-inner" :style="{ width: `${timeline.totalWidth}px`, height: `${bodyHeight}px` }">
            <div
              v-for="unit in weekendUnits"
              :key="`w-${unit.key}`"
              class="gantt__weekend"
              :style="{ left: `${unit.left}px`, width: `${unit.width}px` }"
            />
            <div
              v-for="item in viewRows"
              :key="`r-${item.row.id}`"
              class="gantt__lane"
              :class="{ 'gantt__lane--hover': hoveredId === item.row.id }"
              :style="{ top: `${item.index * GANTT_ROW_HEIGHT}px`, height: `${GANTT_ROW_HEIGHT}px` }"
              @mouseenter="hoveredId = item.row.id"
              @mouseleave="hoveredId = null"
            />
            <div
              v-if="todayVisible"
              class="gantt__today"
              :style="{ left: `${todayX}px` }"
            >
              <span>Hôm nay</span>
            </div>
            <router-link
              v-for="item in viewRows"
              v-show="item.meta"
              :key="`b-${item.row.id}`"
              :to="{ name: 'manager.project.tasks.detail', params: { id: item.row.id } }"
              class="gantt-bar"
              :class="item.meta ? `gantt-bar--${item.meta.tone}` : ''"
              :aria-label="item.span
                ? `${item.row.title}, ${formatViDate(item.span.start)} – ${formatViDate(item.span.end)}`
                : item.row.title"
              :style="item.meta ? {
                top: `${item.index * GANTT_ROW_HEIGHT + GANTT_BAR_TOP}px`,
                left: `${item.meta.left}px`,
                width: `${item.meta.width}px`,
                height: `${GANTT_BAR_H}px`,
              } : {}"
            >
              <span v-if="item.meta" class="gantt-bar__fill" :style="{ width: `${item.meta.pct}%` }" />
              <span
                v-if="item.meta?.tail"
                class="gantt-bar__tail"
                :style="{ width: `${item.meta.tail.width}px` }"
              />
            </router-link>
          </div>
        </div>

        <div class="gantt__jumps">
          <button
            v-for="item in viewRows"
            v-show="item.meta && jumpDir(item.meta)"
            :key="`j-${item.row.id}`"
            type="button"
            class="gantt__jump"
            :class="`gantt__jump--${jumpDir(item.meta)}`"
            :style="{ top: `${item.index * GANTT_ROW_HEIGHT + 12 - rightScrollTop}px` }"
            aria-label="Nhảy tới công việc"
            @click="jumpTo(item.meta)"
          >
            <AppIcon :name="jumpDir(item.meta) === 'left' ? 'chevronLeft' : 'chevronRight'" :size="14" />
          </button>
        </div>

        <div class="gantt__zoom">
          <button type="button" class="gantt__zoom-btn" aria-label="Phóng to" :disabled="zoomIndex >= GANTT_ZOOM_STEPS.length - 1" @click="zoomBy(1)">
            <AppIcon name="plus" :size="14" />
          </button>
          <button type="button" class="gantt__zoom-btn" aria-label="Thu nhỏ" :disabled="zoomIndex <= 0" @click="zoomBy(-1)">
            <AppIcon name="minus" :size="14" />
          </button>
        </div>
      </div>
    </div>

    <footer v-if="!loading && rows.length" class="gantt__footer">
      <button type="button" class="gantt__nav" aria-label="Lùi thêm thời gian quá khứ" @click="padBefore += GANTT_EXTEND_DAYS">
        <AppIcon name="chevronLeft" :size="16" />
      </button>
      <button type="button" class="gantt__nav" aria-label="Mở rộng thêm thời gian tương lai" @click="padAfter += GANTT_EXTEND_DAYS">
        <AppIcon name="chevronRight" :size="16" />
      </button>
    </footer>

    <div v-if="unscheduled.length" class="gantt__unused">
      <div class="gantt__unused-head">
        <span>Công việc chưa sắp xếp</span>
        <span>{{ unscheduled.length }}</span>
      </div>
      <div class="gantt__unused-body">
        <router-link
          v-for="task in unscheduled"
          :key="task.id"
          :to="{ name: 'manager.project.tasks.detail', params: { id: task.id } }"
          class="gantt__unused-item"
        >
          {{ task.title }}
        </router-link>
      </div>
    </div>

    <TaskPeopleListModal
      :open="peopleModalOpen"
      :title="peopleModalTitle"
      :people="peopleModalPeople"
      @close="closePeopleModal"
    />
  </section>
</template>

<style scoped>
.gantt {
  display: flex;
  flex-direction: column;
  min-height: calc(100dvh - 12.5rem);
  height: calc(100dvh - 12.5rem);
  overflow: hidden;
  background: var(--color-surface);
}

.gantt--fill {
  min-height: 0;
  height: 100%;
}

.gantt__toolbar {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: 0.5rem 0.75rem;
  box-shadow: 0 1px 0 var(--color-border);
}

.gantt__toolbar-left {
  display: flex;
  min-width: 0;
  flex: 1;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
}

.gantt__title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 700;
}

.gantt__btns {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.25rem;
}

.gantt__btn,
.gantt__add,
.gantt__nav,
.gantt__zoom-btn,
.gantt__jump,
.gantt__caret {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
}

.gantt__btn {
  height: 1.875rem;
  padding: 0 0.55rem;
  border-radius: var(--radius-sm);
}

.gantt__btn:hover,
.gantt__btn[aria-expanded='true'] {
  background: var(--color-surface-muted);
  color: var(--color-primary);
}

.gantt__btn:disabled {
  opacity: 0.5;
  cursor: default;
}

.gantt__add {
  height: 1.875rem;
  padding: 0 0.75rem;
  border-radius: var(--radius-sm);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-weight: 600;
}

.gantt__add:hover {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.gantt__popwrap {
  position: relative;
}

.gantt__menu {
  position: absolute;
  z-index: 30;
  top: calc(100% + 0.25rem);
  left: 0;
  display: flex;
  flex-direction: column;
  min-width: 14.5rem;
  max-height: 18rem;
  padding: 0.25rem;
  overflow: auto;
  border-radius: 12px;
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
}

.gantt__menu--settings {
  min-width: 13.5rem;
}

.gantt__menu-label {
  margin: 0.25rem 0.5rem 0.125rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.gantt__menu-item,
.gantt__check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.45rem 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  text-align: left;
  cursor: pointer;
}

.gantt__menu-item span,
.gantt__check span {
  flex: 1;
}

.gantt__menu-item:hover,
.gantt__check:hover {
  background: var(--color-surface-muted);
}

.gantt__menu-item--on {
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-weight: 600;
}

.gantt__sort-icon--desc {
  transform: rotate(180deg);
}

.gantt__empty {
  margin: 0;
  padding: var(--space-5);
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.gantt__board {
  display: flex;
  flex: 1;
  min-height: 0;
}

.gantt__left {
  display: flex;
  flex-shrink: 0;
  flex-direction: column;
  min-width: 0;
  background: var(--color-surface);
}

.gantt__left-head,
.gantt__right-head {
  flex-shrink: 0;
  overflow: hidden;
  height: 65px;
  background: var(--color-surface-muted);
  box-shadow: 0 1px 0 var(--color-border);
}

.gantt__head-row {
  display: flex;
  min-height: 65px;
  align-items: stretch;
}

.gantt__hcol {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0 0.65rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 700;
  box-shadow: 1px 0 0 var(--color-border);
}

.gantt__hcol--center {
  justify-content: center;
  text-align: center;
}

.gantt__hcol--sort {
  cursor: pointer;
}

.gantt__hcol--sort:hover {
  color: var(--color-primary);
}

.gantt__col-resizer {
  position: absolute;
  top: 0;
  right: 0;
  width: 6px;
  height: 100%;
  cursor: col-resize;
}

.gantt__left-body,
.gantt__right-body {
  flex: 1;
  min-height: 0;
  overflow: auto;
}

.gantt__left-inner,
.gantt__time-inner {
  position: relative;
}

.gantt__row {
  position: absolute;
  left: 0;
  display: flex;
  width: max-content;
  min-width: 100%;
  box-shadow: 0 1px 0 var(--color-border);
}

.gantt__row--hover,
.gantt__lane--hover {
  background: color-mix(in srgb, var(--color-primary-surface) 70%, transparent);
}

.gantt__row--group {
  font-weight: 600;
}

.gantt__cell {
  display: flex;
  align-items: center;
  padding: 0 0.5rem;
  overflow: hidden;
  color: var(--color-text);
  font-size: 0.8125rem;
  box-shadow: 1px 0 0 var(--color-border);
}

.gantt__cell--center {
  justify-content: center;
  text-align: center;
}

.gantt__deadline {
  color: var(--color-text);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.gantt__deadline--muted {
  color: var(--color-text-muted);
}

.gantt__deadline--success {
  color: var(--color-success);
}

.gantt__deadline--warning {
  color: var(--color-warning);
}

.gantt__deadline--danger {
  color: var(--color-danger);
}

.gantt__outline {
  color: var(--color-text-muted);
  font-variant-numeric: tabular-nums;
}

.gantt__title-cell {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 0.25rem;
}

.gantt__caret {
  flex-shrink: 0;
  gap: 0;
  padding: 0;
  color: var(--color-text-muted);
}

.gantt__caret-chevron--closed {
  transform: rotate(-90deg);
}

.gantt__caret :deep(svg:last-child) {
  transition: transform 160ms ease;
}

.gantt__task-link {
  min-width: 0;
  overflow: hidden;
  color: var(--color-text);
  text-decoration: none;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.gantt__task-link:hover {
  color: var(--color-primary);
}

.gantt__task-link--overdue {
  color: var(--color-danger-tint-fg);
}

.gantt__people {
  display: inline-flex;
  align-items: center;
}

.gantt__assignee-btn {
  display: inline-flex;
  align-items: center;
  height: 1.875rem;
  padding: 0 0.25rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  cursor: pointer;
}

.gantt__assignee-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.gantt__assignee-btn--empty,
.gantt__assignee-btn:disabled {
  cursor: default;
}

.gantt__assignee-avatar {
  display: grid;
  place-items: center;
  width: 1.5rem;
  height: 1.5rem;
  margin-left: -0.35rem;
  overflow: hidden;
  border-radius: var(--radius-full);
  background: var(--color-primary);
  box-shadow: 0 0 0 2px var(--color-surface);
  color: var(--color-on-primary);
  font-size: 0.65rem;
  font-weight: 700;
  line-height: 1;
}

.gantt__assignee-avatar:first-child {
  margin-left: 0;
}

.gantt__assignee-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.gantt__people :deep(.user-avatar-tip) {
  margin-left: -0.35rem;
}

.gantt__people :deep(.user-avatar-tip:first-child) {
  margin-left: 0;
}

.gantt__people :deep(.user-avatar-tip__avatar),
.gantt__people :deep(.user-avatar-tip__btn) {
  width: 1.5rem;
  height: 1.5rem;
}

.gantt__people :deep(.user-avatar-tip__avatar) {
  box-shadow: 0 0 0 2px var(--color-surface);
  font-size: 0.65rem;
}

.gantt__people-more {
  margin-left: 0.25rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.gantt__chip {
  display: inline-flex;
  width: fit-content;
  align-items: center;
  padding: 0.125rem 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
}

.gantt__chip--primary { background: var(--color-primary-surface); color: var(--color-primary); }
.gantt__chip--info { background: var(--color-info-tint-bg); color: var(--color-info-tint-fg); }
.gantt__chip--gold { background: var(--color-gold-surface); color: var(--color-gold-600); }
.gantt__chip--success { background: var(--color-success-tint-bg); color: var(--color-success-tint-fg); }
.gantt__chip--umber { background: var(--color-umber-surface); color: var(--color-umber); }
.gantt__chip--tertiary { background: var(--color-tertiary-surface); color: var(--color-tertiary); }
.gantt__chip--danger { background: var(--color-danger-tint-bg); color: var(--color-danger-tint-fg); }
.gantt__chip--neutral { background: var(--color-surface-muted); color: var(--color-text-muted); }
.gantt__chip--violet { background: var(--color-tertiary-surface); color: var(--color-tertiary); }
.gantt__chip--warning { background: var(--color-warning-tint-bg); color: var(--color-warning-tint-fg); }

.gantt-pct {
  width: 100%;
  max-width: 8.5rem;
}

.gantt-pct__track {
  position: relative;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.gantt-pct__expected,
.gantt-pct__actual {
  position: absolute;
  inset: 0 auto 0 0;
  height: 100%;
  border-radius: var(--radius-full);
}

.gantt-pct__expected {
  background: var(--color-gold-400);
}

.gantt-pct__actual {
  background: var(--color-tertiary-500);
}

.gantt-pct__knob {
  position: absolute;
  top: 50%;
  z-index: 1;
  display: grid;
  place-items: center;
  width: 1.25rem;
  height: 1.25rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  color: var(--color-text);
  font-size: 0.625rem;
  font-weight: 700;
  transform: translate(-50%, -50%);
}

.gantt__splitter {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 10px;
  color: var(--color-text-muted);
  cursor: col-resize;
  background: var(--color-surface-muted);
  box-shadow: inset 1px 0 0 var(--color-border), inset -1px 0 0 var(--color-border);
}

.gantt__right {
  position: relative;
  display: flex;
  flex: 1;
  min-width: 0;
  flex-direction: column;
}

.gantt__month,
.gantt__day {
  position: absolute;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  box-sizing: border-box;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  box-shadow: 1px 0 0 var(--color-border);
}

.gantt__month {
  top: 0;
  box-shadow: 1px 0 0 var(--color-border), 0 1px 0 var(--color-border);
}

.gantt__day {
  flex-direction: column;
  gap: 0;
  font-weight: 500;
}

.gantt__day small {
  color: var(--color-text-muted);
  font-size: 0.625rem;
  font-weight: 600;
}

.gantt__day--weekend,
.gantt__weekend {
  background: color-mix(in srgb, var(--color-warning-tint-bg) 55%, transparent);
}

.gantt__weekend {
  position: absolute;
  top: 0;
  bottom: 0;
}

.gantt__lane {
  position: absolute;
  right: 0;
  left: 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.gantt__today {
  position: absolute;
  top: 0;
  bottom: 0;
  z-index: 4;
  width: 2px;
  background: var(--color-primary);
}

.gantt__today span {
  position: sticky;
  top: 0;
  left: 4px;
  padding: 0 0.35rem;
  border-radius: var(--radius-sm);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-size: 0.625rem;
  font-weight: 700;
  white-space: nowrap;
}

.gantt-bar {
  position: absolute;
  z-index: 5;
  overflow: visible;
  border-radius: 3px;
  background: var(--color-tertiary-100);
  text-decoration: none;
}

.gantt-bar__fill {
  display: block;
  height: 100%;
  border-radius: 3px;
  background: var(--color-tertiary-500);
}

.gantt-bar--completed { background: var(--color-success-tint-bg); }
.gantt-bar--completed .gantt-bar__fill { background: var(--color-success); }
.gantt-bar--overdue { background: var(--color-danger-tint-bg); }
.gantt-bar--overdue .gantt-bar__fill { background: var(--color-danger); }
.gantt-bar--hold { background: var(--color-gold-surface); }
.gantt-bar--hold .gantt-bar__fill { background: var(--color-gold-600); }
.gantt-bar--cancelled { background: var(--color-umber-50); }
.gantt-bar--cancelled .gantt-bar__fill { background: var(--color-umber); }
.gantt-bar--group { background: var(--color-secondary-100); }
.gantt-bar--group .gantt-bar__fill { background: var(--color-secondary); }
.gantt-bar--idle { background: var(--color-surface-muted); }
.gantt-bar--idle .gantt-bar__fill { background: var(--color-text-muted); }

.gantt-bar__tail {
  position: absolute;
  top: 50%;
  left: 100%;
  height: 4px;
  background: repeating-linear-gradient(
    90deg,
    var(--color-danger-tint-border) 0 6px,
    transparent 6px 10px
  );
  transform: translateY(-50%);
}

.gantt__jumps {
  position: absolute;
  inset: 65px 0 0 0;
  z-index: 8;
  overflow: hidden;
  pointer-events: none;
}

.gantt__jump {
  position: absolute;
  z-index: 8;
  width: 1.5rem;
  height: 1.5rem;
  justify-content: center;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  pointer-events: auto;
}

.gantt__jump--right { right: 2.75rem; }
.gantt__jump--left { left: 0.5rem; }

.gantt__zoom {
  position: absolute;
  right: 0.75rem;
  bottom: 0.75rem;
  z-index: 12;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
}

.gantt__zoom-btn {
  width: 1.75rem;
  height: 1.75rem;
  justify-content: center;
}

.gantt__zoom-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-primary);
}

.gantt__zoom-btn:disabled {
  opacity: 0.4;
  cursor: default;
}

.gantt__footer {
  display: flex;
  flex-shrink: 0;
  justify-content: flex-end;
  gap: 0.25rem;
  padding: 0.25rem 0.75rem;
  box-shadow: 0 -1px 0 var(--color-border);
}

.gantt__nav {
  width: 1.75rem;
  height: 1.75rem;
  justify-content: center;
  border-radius: var(--radius-sm);
}

.gantt__nav:hover {
  background: var(--color-surface-muted);
  color: var(--color-primary);
}

.gantt__unused {
  flex-shrink: 0;
  max-height: 7rem;
  overflow: auto;
  box-shadow: 0 -1px 0 var(--color-border);
}

.gantt__unused-head {
  display: flex;
  justify-content: space-between;
  padding: 0.4rem 0.75rem;
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 700;
}

.gantt__unused-body {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  padding: 0.4rem 0.75rem 0.6rem;
}

.gantt__unused-item {
  padding: 0.2rem 0.5rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-size: 0.75rem;
  text-decoration: none;
}

.gantt__unused-item:hover {
  color: var(--color-primary);
}

@media (prefers-reduced-motion: reduce) {
  .gantt__caret-chevron--closed,
  .gantt__sort-icon--desc {
    transition: none;
  }
}
</style>
