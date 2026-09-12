<script setup>
//
// Modal thao tác nhanh từ menu chuột phải trên danh sách dự án.
// Form nằm trong viewport; 2–3 cột khi có nhiều field; field dài span full.
//
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { showClientToast } from '@/lib/clientToast';
import { TASK_PROGRESS_METHOD_OPTIONS } from '../constants/task.js';
import ProjectMemberPicker from './ProjectMemberPicker.vue';
import ProjectUserPicker from './ProjectUserPicker.vue';

const props = defineProps({
  kind: { type: String, default: null },
  project: { type: Object, default: null },
  extra: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'updated', 'duplicated', 'tasks-changed']);

const TASK_TITLES = {
  normal: 'Thêm công việc thường',
  bulk: 'Thêm nhiều công việc thường',
  by_category: 'Thêm công việc theo danh mục',
  by_phase: 'Thêm công việc theo phase',
};

const KIND_META = {
  members: { title: 'Thêm người thực hiện', icon: 'userPlus', tone: 'tertiary' },
  category: { title: 'Cập nhật danh mục công việc', icon: 'listChecks', tone: 'gold' },
  task: { title: 'Thêm công việc', icon: 'plus', tone: 'info' },
  phase: { title: 'Cập nhật phase', icon: 'flag', tone: 'secondary' },
  baseline: { title: 'Chốt baseline', icon: 'flag', tone: 'warning' },
  dates: { title: 'Cập nhật thời gian dự án', icon: 'calendar', tone: 'info' },
  description: { title: 'Cập nhật mô tả dự án', icon: 'fileText', tone: 'violet' },
  duplicate: { title: 'Nhân bản dự án', icon: 'copy', tone: 'success' },
  tabs_config: { title: 'Cấu hình tab hiển thị', icon: 'listChecks', tone: 'tertiary' },
};

// 5 tab tuỳ chọn bật/tắt riêng theo dự án — khớp ProjectService::OPTIONAL_TABS.
const OPTIONAL_TAB_DEFS = [
  { key: 'discussion', label: 'Thảo luận' },
  { key: 'report', label: 'Báo cáo' },
  { key: 'attachments', label: 'Đính kèm' },
  { key: 'test_case', label: 'Testcase' },
  { key: 'feedback', label: 'Phản hồi' },
];

const PROGRESS_OPTIONS = TASK_PROGRESS_METHOD_OPTIONS;

let rowSeq = 0;
function nextRowKey() {
  rowSeq += 1;
  return `row-${rowSeq}`;
}

const STATUS_TONES = {
  in_progress: 'primary',
  completed: 'success',
  on_hold: 'gold',
  cancelled: 'umber',
  planning: 'tertiary',
};

const STATUS_LABELS = {
  in_progress: 'Đang thực hiện',
  completed: 'Hoàn thành',
  on_hold: 'Tạm dừng',
  cancelled: 'Hủy',
  planning: 'Đang chờ',
};

const saving = ref(false);
const listsLoading = ref(false);
const assignableUsers = ref([]);
const categories = ref([]);
const phases = ref([]);
const baselines = ref([]);
const itemCounts = ref({ work_items: 0, baseline: 0, task: 0, task_category: 0, phase: 0 });
const startInput = ref(null);
const endInput = ref(null);
const actualStartInput = ref(null);
const actualEndInput = ref(null);
const plannedBlock = ref(null);
const actualBlock = ref(null);
const firstField = ref(null);

const membersForm = reactive({ member_ids: [], follower_ids: [] });
const datesForm = reactive({
  start_date: '',
  end_date: '',
  actual_start_date: '',
  actual_end_date: '',
  shift_task_dates: false,
});
const descriptionForm = reactive({ description: '' });
const structureRows = ref([]);
const deletedStructureIds = ref([]);
const taskForm = reactive({
  title: '',
  description: '',
  start_date: '',
  end_date: '',
  assignee_id: '',
  category_id: '',
  phase_id: '',
});
const bulkRows = ref([]);
const baselineForm = reactive({ title: '' });
const tabsConfigForm = reactive(
  Object.fromEntries(OPTIONAL_TAB_DEFS.map((tab) => [tab.key, true])),
);

const isOpen = computed(() => Boolean(props.kind && props.project && props.kind !== 'signature'));
const taskVariant = computed(() => props.extra?.variant || 'normal');
const dateFocus = computed(() => props.extra?.focus || 'range');

const dialogMeta = computed(() => {
  const base = KIND_META[props.kind] || { title: '', icon: 'layers', tone: 'primary' };
  if (props.kind === 'task') {
    return { ...base, title: TASK_TITLES[taskVariant.value] || base.title };
  }
  return base;
});

const projectLabel = computed(() => {
  const p = props.project;
  if (!p) return '';
  return p.code ? `[${p.code}] ${p.name}` : p.name;
});

const statusTone = computed(() => STATUS_TONES[props.project?.status] || 'neutral');
const statusLabel = computed(() => STATUS_LABELS[props.project?.status] || props.project?.status || '—');

const bannerMeta = computed(() => {
  const p = props.project;
  if (!p) return [];
  const items = [];
  if (p.code) {
    items.push({ key: 'code', label: 'Mã dự án', value: p.code });
  }
  if (props.kind === 'members' && p.name) {
    items.push({ key: 'name', label: 'Tên dự án', value: p.name });
  }
  items.push({ key: 'status', label: 'Trạng thái', value: statusLabel.value, status: true });
  if (props.kind !== 'members' && (p.start_date || p.end_date)) {
    items.push({
      key: 'dates',
      label: 'Thời gian',
      value: `${formatDate(p.start_date)} – ${formatDate(p.end_date)}`,
    });
  }
  return items;
});

const preferredDeptIds = computed(() => {
  const ids = [];
  const p = props.project;
  if (!p) return ids;
  if (p.executing_department?.id) ids.push(p.executing_department.id);
  for (const d of p.executing_departments || []) {
    if (d?.id) ids.push(d.id);
  }
  if (p.owner_department?.id) ids.push(p.owner_department.id);
  return [...new Set(ids)];
});

const baselineInfo = computed(() => {
  const n = itemCounts.value.work_items || 0;
  if ((itemCounts.value.baseline || 0) === 0) {
    return `Lần đầu chốt: toàn bộ ${n} mục hiện tại sẽ được ghi làm baseline gốc.`;
  }
  return `Sẽ ghi thêm một mốc baseline mới từ ${n} mục hiện tại.`;
});

const durationLabel = computed(() => daysBetween(datesForm.start_date, datesForm.end_date));
const actualDurationLabel = computed(() => daysBetween(datesForm.actual_start_date, datesForm.actual_end_date));
const plannedDaysCompact = computed(() => compactDays(datesForm.start_date, datesForm.end_date));
const actualDaysCompact = computed(() => compactDays(datesForm.actual_start_date, datesForm.actual_end_date));
const plannedRangeInvalid = computed(() => Boolean(datesForm.start_date && datesForm.end_date && !plannedDaysCompact.value));
const actualRangeInvalid = computed(() => Boolean(datesForm.actual_start_date && datesForm.actual_end_date && !actualDaysCompact.value));
const isActualFocus = computed(() => dateFocus.value === 'actual' || dateFocus.value === 'actual_end');
const taskDurationLabel = computed(() => daysBetween(taskForm.start_date, taskForm.end_date));

const panelClass = computed(() => {
  if (props.kind === 'members' || props.kind === 'task' || props.kind === 'description' || props.kind === 'category' || props.kind === 'phase') {
    return 'proj-qa__panel--xl';
  }
  if (props.kind === 'baseline' || props.kind === 'dates') {
    return 'proj-qa__panel--lg';
  }
  return 'proj-qa__panel--md';
});

const primaryLabel = computed(() => {
  if (props.kind === 'members' || props.kind === 'dates' || props.kind === 'description' || props.kind === 'baseline' || props.kind === 'category' || props.kind === 'phase' || props.kind === 'tabs_config') {
    return 'Cập nhật';
  }
  return 'Thêm';
});

function emptyStructureRow(overrides = {}) {
  return {
    key: nextRowKey(),
    id: null,
    title: '',
    progress_type: 'average',
    start_date: props.project?.start_date || '',
    end_date: props.project?.end_date || '',
    description: '',
    ...overrides,
  };
}

function emptyBulkRow(overrides = {}) {
  return {
    key: nextRowKey(),
    title: '',
    start_date: props.project?.start_date || '',
    end_date: props.project?.end_date || '',
    assignee_id: '',
    description: '',
    ...overrides,
  };
}

function addStructureRow() {
  structureRows.value.push(emptyStructureRow());
}

function removeStructureRow(row) {
  structureRows.value = structureRows.value.filter((r) => r.key !== row.key);
  if (row.id) deletedStructureIds.value.push(row.id);
  if (!structureRows.value.length) addStructureRow();
}

function addBulkRow() {
  bulkRows.value.push(emptyBulkRow());
}

function removeBulkRow(row) {
  bulkRows.value = bulkRows.value.filter((r) => r.key !== row.key);
  if (!bulkRows.value.length) addBulkRow();
}

function daysBetween(start, end) {
  const n = dayCount(start, end);
  return n == null ? null : `${n} ngày`;
}

function compactDays(start, end) {
  const n = dayCount(start, end);
  return n == null ? '' : `${n}d`;
}

function dayCount(start, end) {
  if (!start || !end) return null;
  const a = new Date(`${start}T00:00:00`);
  const b = new Date(`${end}T00:00:00`);
  if (Number.isNaN(a.getTime()) || Number.isNaN(b.getTime()) || b < a) return null;
  return Math.round((b - a) / 86400000) + 1;
}

function formatDate(value) {
  if (!value) return '—';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleDateString('vi-VN');
}

function formatWhen(value) {
  if (!value) return '';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '';
  return d.toLocaleString('vi-VN');
}

function close() {
  if (saving.value) return;
  emit('close');
}

function onKeydown(event) {
  if (event.key === 'Escape' && isOpen.value && props.kind !== 'duplicate') {
    event.preventDefault();
    close();
  }
}

async function ensureAssignableUsers() {
  if (assignableUsers.value.length) return;
  try {
    const { data } = await window.axios.get('/api/project/assignable-users');
    assignableUsers.value = data.users ?? [];
  } catch {
    assignableUsers.value = [];
  }
}

async function loadQuick(kind) {
  if (!props.project) return { items: [], counts: {} };
  const { data } = await window.axios.get(`/api/project/${props.project.id}/quick-items`, {
    params: kind ? { kind } : {},
  });
  return data;
}

/**
 * Task thật (Project Giai đoạn 2) thay cho project_quick_items cho
 * kind ∈ {task, category, phase}. baseline/signature KHÔNG đổi — vẫn dùng
 * loadQuick()/postQuick() ở trên (bản chất khác: snapshot immutable log,
 * không phải Task).
 *
 * API cây WBS (/api/project/{project}/tasks) trả cây lồng nhau
 * (children: [...]) — dẹt phẳng ra để lấy danh sách theo type, dùng cho
 * dropdown "Danh mục"/"Phase" và list hiển thị trong modal.
 */
async function loadTaskTree() {
  if (!props.project) return [];
  const { data } = await window.axios.get(`/api/project/${props.project.id}/tasks`);
  const flat = [];
  const walk = (nodes) => {
    for (const node of nodes || []) {
      flat.push(node);
      walk(node.children);
    }
  };
  walk(data.tasks);
  return flat;
}

async function postTask(payload) {
  const { data } = await window.axios.post(`/api/project/${props.project.id}/tasks`, payload);
  return data.task;
}

function resetForms(p) {
  membersForm.member_ids = (p.members || []).map((m) => m.id);
  membersForm.follower_ids = (p.followers || []).map((f) => f.id);
  datesForm.start_date = p.start_date || '';
  datesForm.end_date = p.end_date || '';
  datesForm.actual_start_date = p.actual_start_date || '';
  datesForm.actual_end_date = p.actual_end_date || '';
  datesForm.shift_task_dates = Boolean(p.shift_task_dates_with_project);
  descriptionForm.description = p.description || '';
  structureRows.value = [];
  deletedStructureIds.value = [];
  taskForm.title = '';
  taskForm.description = '';
  taskForm.start_date = p.start_date || '';
  taskForm.end_date = p.end_date || '';
  taskForm.assignee_id = '';
  taskForm.category_id = '';
  taskForm.phase_id = '';
  bulkRows.value = [emptyBulkRow()];
  baselineForm.title = '';
  const disabled = new Set(p.disabled_tabs || []);
  for (const tab of OPTIONAL_TAB_DEFS) {
    tabsConfigForm[tab.key] = !disabled.has(tab.key);
  }
}

function focusFirst() {
  nextTick(() => {
    if (props.kind === 'dates') {
      if (isActualFocus.value) {
        if (dateFocus.value === 'actual_end') actualEndInput.value?.focus();
        else actualStartInput.value?.focus();
        actualBlock.value?.scrollIntoView({ block: 'nearest' });
        return;
      }
      if (dateFocus.value === 'end') endInput.value?.focus();
      else startInput.value?.focus();
      plannedBlock.value?.scrollIntoView({ block: 'nearest' });
      return;
    }
    firstField.value?.focus();
  });
}

watch(
  () => [props.kind, props.project?.id, taskVariant.value],
  async ([kind]) => {
    document.removeEventListener('keydown', onKeydown);
    if (!kind || kind === 'signature' || kind === 'duplicate' || !props.project) return;
    document.addEventListener('keydown', onKeydown);

    const p = props.project;
    resetForms(p);
    focusFirst();

    if (kind === 'members' || kind === 'task') {
      await ensureAssignableUsers();
    }

    const needLists =
      kind === 'category' ||
      kind === 'phase' ||
      kind === 'baseline' ||
      (kind === 'task' && (taskVariant.value === 'by_category' || taskVariant.value === 'by_phase'));

    if (!needLists) return;

    listsLoading.value = true;
    try {
      if (
        kind === 'category' ||
        kind === 'phase' ||
        (kind === 'task' && (taskVariant.value === 'by_category' || taskVariant.value === 'by_phase'))
      ) {
        const flat = await loadTaskTree();
        categories.value = flat.filter((t) => t.type === 'category');
        phases.value = flat.filter((t) => t.type === 'phase');

        if (kind === 'category') {
          structureRows.value = categories.value.length
            ? categories.value.map((item) => emptyStructureRow({
              id: item.id,
              title: item.title || '',
              progress_type: item.progress_type || 'average',
              description: item.description || '',
            }))
            : [emptyStructureRow()];
          deletedStructureIds.value = [];
        }
        if (kind === 'phase') {
          structureRows.value = phases.value.length
            ? phases.value.map((item) => emptyStructureRow({
              id: item.id,
              title: item.title || '',
              progress_type: item.progress_type || 'average',
              start_date: item.start_date || '',
              end_date: item.end_date || '',
              description: item.description || '',
            }))
            : [emptyStructureRow()];
          deletedStructureIds.value = [];
        }
      }
      if (kind === 'baseline') {
        const data = await loadQuick();
        itemCounts.value = data.counts ?? itemCounts.value;
        baselines.value = (data.items ?? []).filter((i) => i.kind === 'baseline');
      }
    } catch {
      categories.value = [];
      phases.value = [];
      baselines.value = [];
      if (kind === 'category' || kind === 'phase') {
        structureRows.value = [emptyStructureRow()];
      }
    } finally {
      listsLoading.value = false;
    }
  },
);

function dateRangeError(start, end) {
  if (start && end && end < start) return 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.';
  return null;
}

async function patchProject(payload, message) {
  const { data } = await window.axios.put(`/api/project/${props.project.id}`, payload);
  emit('updated', data.project);
  showClientToast('success', message);
  emit('close');
}

// Chỉ còn dùng cho kind='baseline' — kind='task'/'category'/'phase' đã
// chuyển sang postTask() (bảng tasks thật) ở trên.
async function postQuick(payload, message) {
  const { data } = await window.axios.post(`/api/project/${props.project.id}/quick-items`, payload);
  baselines.value = (data.items ?? []).filter((i) => i.kind === 'baseline');
  itemCounts.value = data.counts ?? itemCounts.value;
  showClientToast('success', message);
  emit('close');
}

async function submit() {
  if (!props.project || saving.value) return;
  saving.value = true;
  try {
    if (props.kind === 'members') {
      await patchProject(
        { member_ids: membersForm.member_ids, follower_ids: membersForm.follower_ids },
        'Đã cập nhật người thực hiện.',
      );
      return;
    }
    if (props.kind === 'dates') {
      const plannedErr = dateRangeError(datesForm.start_date, datesForm.end_date);
      if (plannedErr) {
        showClientToast('error', plannedErr);
        return;
      }
      const actualErr = dateRangeError(datesForm.actual_start_date, datesForm.actual_end_date);
      if (actualErr) {
        showClientToast('error', 'Ngày kết thúc thực tế phải sau hoặc bằng ngày bắt đầu thực tế.');
        return;
      }
      await patchProject(
        {
          start_date: datesForm.start_date || null,
          end_date: datesForm.end_date || null,
          actual_start_date: datesForm.actual_start_date || null,
          actual_end_date: datesForm.actual_end_date || null,
          shift_task_dates_with_project: Boolean(datesForm.shift_task_dates),
        },
        'Đã cập nhật thời gian dự án.',
      );
      return;
    }
    if (props.kind === 'description') {
      await patchProject({ description: descriptionForm.description || null }, 'Đã cập nhật mô tả dự án.');
      return;
    }
    if (props.kind === 'tabs_config') {
      const disabledTabs = OPTIONAL_TAB_DEFS
        .filter((tab) => !tabsConfigForm[tab.key])
        .map((tab) => tab.key);
      const { data } = await window.axios.put(`/api/project/${props.project.id}/tab-config`, {
        disabled_tabs: disabledTabs,
      });
      emit('updated', data.project);
      showClientToast('success', 'Đã cập nhật tab hiển thị.');
      emit('close');
      return;
    }
    if (props.kind === 'category' || props.kind === 'phase') {
      const type = props.kind;
      const filled = structureRows.value.filter((row) => row.title.trim());
      if (!filled.length && !deletedStructureIds.value.length) {
        showClientToast('error', type === 'category' ? 'Nhập ít nhất một danh mục.' : 'Nhập ít nhất một phase.');
        return;
      }
      for (const row of filled) {
        const err = dateRangeError(row.start_date, row.end_date);
        if (type === 'phase' && err) {
          showClientToast('error', err);
          return;
        }
        if (!row.progress_type) {
          showClientToast('error', 'Chọn cách tính tiến độ cho mọi dòng.');
          return;
        }
      }
      const items = filled.map((row, index) => {
        const item = {
          title: row.title.trim(),
          progress_type: row.progress_type || 'average',
          description: row.description?.trim() || null,
          sort_order: index,
        };
        if (row.id) item.id = row.id;
        if (type === 'phase') {
          item.start_date = row.start_date || null;
          item.end_date = row.end_date || null;
        }
        return item;
      });
      const { data } = await window.axios.put(`/api/project/${props.project.id}/structure/${type}`, {
        items,
        deleted_ids: deletedStructureIds.value,
      });
      if (type === 'category') categories.value = data.tasks ?? [];
      else phases.value = data.tasks ?? [];
      emit('tasks-changed');
      showClientToast('success', type === 'category' ? 'Đã cập nhật danh mục công việc.' : 'Đã cập nhật phase.');
      emit('close');
      return;
    }
    if (props.kind === 'task') {
      const variant = taskVariant.value;
      const err = dateRangeError(taskForm.start_date, taskForm.end_date);
      if (err) {
        showClientToast('error', err);
        return;
      }
      if (variant === 'bulk') {
        const rows = bulkRows.value.filter((row) => row.title.trim());
        if (!rows.length) {
          showClientToast('error', 'Nhập ít nhất một công việc.');
          return;
        }
        for (const row of rows) {
          const rowErr = dateRangeError(row.start_date, row.end_date);
          if (rowErr) {
            showClientToast('error', rowErr);
            return;
          }
        }
        await window.axios.post(`/api/project/${props.project.id}/tasks/bulk`, {
          items: rows.map((row) => ({
            title: row.title.trim(),
            description: row.description?.trim() || null,
            start_date: row.start_date || null,
            end_date: row.end_date || null,
            assignee_id: row.assignee_id || null,
          })),
        });
        emit('tasks-changed');
        showClientToast('success', `Đã thêm ${rows.length} công việc.`);
        emit('close');
        return;
      }
      const title = taskForm.title.trim();
      if (!title) {
        showClientToast('error', 'Nhập tên công việc.');
        return;
      }
      if (variant === 'by_category' && !taskForm.category_id) {
        showClientToast('error', 'Chọn danh mục công việc.');
        return;
      }
      if (variant === 'by_phase' && !taskForm.phase_id) {
        showClientToast('error', 'Chọn phase.');
        return;
      }
      const parentId =
        variant === 'by_category' ? taskForm.category_id
        : variant === 'by_phase' ? taskForm.phase_id
        : null;
      await postTask({
        type: 'task',
        title,
        description: taskForm.description?.trim() || null,
        parent_id: parentId || null,
        start_date: taskForm.start_date || null,
        end_date: taskForm.end_date || null,
        assignee_id: taskForm.assignee_id || null,
      });
      emit('tasks-changed');
      showClientToast('success', 'Đã thêm công việc.');
      emit('close');
      return;
    }
    if (props.kind === 'baseline') {
      await postQuick(
        { kind: 'baseline', title: baselineForm.title.trim() || null },
        'Đã chốt baseline.',
      );
    }
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không lưu được.');
  } finally {
    saving.value = false;
  }
}

async function confirmDuplicate() {
  if (!props.project || saving.value) return;
  saving.value = true;
  try {
    const { data } = await window.axios.post(`/api/project/${props.project.id}/duplicate`);
    showClientToast('success', `Đã nhân bản thành "${data.project.name}".`);
    emit('duplicated', data.project);
    emit('close');
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không nhân bản được dự án.');
  } finally {
    saving.value = false;
  }
}

watch(
  () => props.kind,
  (kind, prev) => {
    if (!kind && prev) document.removeEventListener('keydown', onKeydown);
  },
);

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown);
});

watch(
  () => props.extra?.focus,
  () => {
    if (props.kind === 'dates') focusFirst();
  },
);
</script>

<template>
  <ConfirmDialog
    :open="kind === 'duplicate' && Boolean(project)"
    title="Nhân bản dự án"
    :description="project ? `Tạo bản sao của “${project.name}”? Bản sao sẽ có mã mới, giữ người tham gia, nhãn và thời gian.` : ''"
    confirm-label="Nhân bản"
    cancel-label="Huỷ bỏ"
    :loading="saving"
    @update:open="(v) => !v && close()"
    @confirm="confirmDuplicate"
  />

  <Teleport to="body">
    <div
      v-if="isOpen && kind !== 'duplicate'"
      class="proj-qa"
      role="presentation"
      @mousedown.self="close"
    >
      <div
        class="proj-qa__panel"
        :class="[panelClass, `proj-qa__panel--${dialogMeta.tone}`, { 'proj-qa__panel--float': kind === 'members' || kind === 'task' }]"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="`proj-qa-title-${kind}`"
      >
        <div class="proj-qa__head">
          <span class="proj-qa__icon" aria-hidden="true">
            <AppIcon :name="dialogMeta.icon" :size="22" :stroke-width="1.75" />
          </span>
          <div class="proj-qa__head-copy">
            <h2 :id="`proj-qa-title-${kind}`" class="proj-qa__title">{{ dialogMeta.title }}</h2>
            <p v-if="project && kind !== 'dates'" class="proj-qa__sub">{{ projectLabel }}</p>
          </div>
          <button type="button" class="proj-qa__close" aria-label="Đóng" :disabled="saving" @click="close">
            <AppIcon name="close" :size="16" />
          </button>
        </div>

        <form class="proj-qa__body hide-scrollbar" @submit.prevent="submit">
          <div v-if="project" class="proj-qa__banner" :class="`proj-qa__banner--${statusTone}`">
            <div
              v-for="item in bannerMeta"
              :key="item.key"
              class="proj-qa__meta"
              :class="{ 'proj-qa__meta--grow': item.key === 'name' }"
            >
              <span class="proj-qa__meta-label">{{ item.label }}</span>
              <span
                class="proj-qa__meta-value"
                :class="{ 'proj-qa__meta-value--status': item.status }"
              >
                <span v-if="item.status" class="proj-qa__meta-dot" aria-hidden="true" />
                {{ item.value }}
              </span>
            </div>
          </div>

          <div v-if="kind === 'members'" class="proj-qa__grid">
            <div class="proj-qa__field">
              <span class="proj-qa__label">
                Người tham gia
                <em>{{ membersForm.member_ids.length }}</em>
              </span>
              <ProjectMemberPicker
                v-model="membersForm.member_ids"
                :users="assignableUsers"
                :preferred-department-ids="preferredDeptIds"
                search-label="Tìm người tham gia"
                placeholder="Tìm tên, email hoặc phòng ban"
                empty-text="Chưa chọn người tham gia"
                remove-aria-label="Bỏ người tham gia này"
                tone="tertiary"
              />
            </div>
            <div class="proj-qa__field">
              <span class="proj-qa__label">
                Theo dõi
                <em>{{ membersForm.follower_ids.length }}</em>
              </span>
              <ProjectMemberPicker
                v-model="membersForm.follower_ids"
                :users="assignableUsers"
                :preferred-department-ids="preferredDeptIds"
                search-label="Tìm người theo dõi"
                placeholder="Chọn người theo dõi"
                empty-text="Chưa chọn người theo dõi"
                remove-aria-label="Bỏ người theo dõi này"
                tone="gold"
              />
            </div>
          </div>

          <div v-else-if="kind === 'category'" class="proj-qa__structure">
            <p class="proj-qa__hint">
              Tạo các danh mục để nhóm công việc — dễ phân loại, tìm kiếm và tính tiến độ theo tập việc.
            </p>
            <p v-if="listsLoading" class="proj-qa__muted">Đang tải…</p>
            <div v-else class="proj-qa__structure-list">
              <div v-for="row in structureRows" :key="row.key" class="proj-qa__structure-row">
                <label class="proj-qa__field">
                  <span class="proj-qa__label">Tên danh mục</span>
                  <input v-model="row.title" class="proj-qa__input" maxlength="255" placeholder="Nhập tên danh mục">
                </label>
                <label class="proj-qa__field proj-qa__field--grow">
                  <span class="proj-qa__label">Cách tính tiến độ</span>
                  <select v-model="row.progress_type" class="proj-qa__input" required>
                    <option v-for="opt in PROGRESS_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                  </select>
                </label>
                <button type="button" class="proj-qa__row-remove" aria-label="Xoá dòng" @click="removeStructureRow(row)">
                  <AppIcon name="close" :size="14" />
                </button>
              </div>
              <button type="button" class="proj-qa__add-row" @click="addStructureRow">
                <AppIcon name="plus" :size="14" />
                Thêm danh mục
              </button>
            </div>
          </div>

          <div v-else-if="kind === 'task'" class="proj-qa__grid">
            <template v-if="taskVariant === 'bulk'">
              <div class="proj-qa__structure-list proj-qa__field--full">
                <div v-for="row in bulkRows" :key="row.key" class="proj-qa__structure-row proj-qa__structure-row--bulk">
                  <label class="proj-qa__field">
                    <span class="proj-qa__label">Tên công việc</span>
                    <input v-model="row.title" class="proj-qa__input" maxlength="255" placeholder="Tên công việc">
                  </label>
                  <div class="proj-qa__field">
                    <span class="proj-qa__label">Người thực hiện</span>
                    <ProjectUserPicker
                      v-model="row.assignee_id"
                      :users="assignableUsers"
                      :preferred-department-ids="preferredDeptIds"
                      search-label="Tìm người thực hiện"
                      placeholder="Chọn người thực hiện"
                    />
                  </div>
                  <label class="proj-qa__field">
                    <span class="proj-qa__label">Ngày bắt đầu</span>
                    <input v-model="row.start_date" type="date" class="proj-qa__input">
                  </label>
                  <label class="proj-qa__field">
                    <span class="proj-qa__label">Ngày kết thúc</span>
                    <input v-model="row.end_date" type="date" class="proj-qa__input">
                  </label>
                  <button type="button" class="proj-qa__row-remove" aria-label="Xoá dòng" @click="removeBulkRow(row)">
                    <AppIcon name="close" :size="14" />
                  </button>
                </div>
                <button type="button" class="proj-qa__add-row" @click="addBulkRow">
                  <AppIcon name="plus" :size="14" />
                  Thêm dòng
                </button>
              </div>
            </template>
            <template v-else>
              <label class="proj-qa__field">
                <span class="proj-qa__label">Tên công việc</span>
                <input
                  ref="firstField"
                  v-model="taskForm.title"
                  class="proj-qa__input"
                  maxlength="255"
                  placeholder="Ví dụ: Lập hồ sơ mời thầu"
                  required
                >
              </label>
              <div class="proj-qa__field">
                <span class="proj-qa__label">Người thực hiện</span>
                <ProjectUserPicker
                  v-model="taskForm.assignee_id"
                  :users="assignableUsers"
                  :preferred-department-ids="preferredDeptIds"
                  search-label="Tìm người thực hiện"
                  placeholder="Chọn người thực hiện"
                />
              </div>
              <label v-if="taskVariant === 'by_category'" class="proj-qa__field proj-qa__field--full">
                <span class="proj-qa__label">Danh mục</span>
                <select v-model="taskForm.category_id" class="proj-qa__input" required>
                  <option value="">Chọn danh mục công việc</option>
                  <option v-for="item in categories" :key="item.id" :value="item.id">{{ item.title }}</option>
                </select>
              </label>
              <label v-if="taskVariant === 'by_phase'" class="proj-qa__field proj-qa__field--full">
                <span class="proj-qa__label">Phase</span>
                <select v-model="taskForm.phase_id" class="proj-qa__input" required>
                  <option value="">Chọn phase</option>
                  <option v-for="item in phases" :key="item.id" :value="item.id">{{ item.title }}</option>
                </select>
              </label>
              <label class="proj-qa__field">
                <span class="proj-qa__label">Ngày bắt đầu</span>
                <input v-model="taskForm.start_date" type="date" class="proj-qa__input">
              </label>
              <label class="proj-qa__field">
                <span class="proj-qa__label">Ngày kết thúc</span>
                <input v-model="taskForm.end_date" type="date" class="proj-qa__input">
              </label>
              <div v-if="taskDurationLabel" class="proj-qa__stat proj-qa__field--full">
                Thời gian thực hiện <strong>{{ taskDurationLabel }}</strong>
              </div>
              <label class="proj-qa__field proj-qa__field--full">
                <span class="proj-qa__label">Mô tả</span>
                <textarea
                  v-model="taskForm.description"
                  class="proj-qa__input proj-qa__textarea"
                  rows="3"
                  maxlength="5000"
                  placeholder="Mô tả ngắn (tuỳ chọn)"
                />
              </label>
            </template>
          </div>

          <div v-else-if="kind === 'phase'" class="proj-qa__structure">
            <p class="proj-qa__hint">
              Phase là giai đoạn trong dự án. Công việc thuộc phase phải nằm trong khoảng thời gian của phase.
            </p>
            <p v-if="listsLoading" class="proj-qa__muted">Đang tải…</p>
            <div v-else class="proj-qa__structure-list">
              <div v-for="row in structureRows" :key="row.key" class="proj-qa__structure-row proj-qa__structure-row--phase">
                <label class="proj-qa__field">
                  <span class="proj-qa__label">Tên phase</span>
                  <input v-model="row.title" class="proj-qa__input" maxlength="255" placeholder="Ví dụ: Giai đoạn khởi tạo">
                </label>
                <label class="proj-qa__field">
                  <span class="proj-qa__label">Ngày bắt đầu</span>
                  <input v-model="row.start_date" type="date" class="proj-qa__input">
                </label>
                <label class="proj-qa__field">
                  <span class="proj-qa__label">Ngày kết thúc</span>
                  <input v-model="row.end_date" type="date" class="proj-qa__input">
                </label>
                <label class="proj-qa__field">
                  <span class="proj-qa__label">Cách tính tiến độ</span>
                  <select v-model="row.progress_type" class="proj-qa__input" required>
                    <option v-for="opt in PROGRESS_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                  </select>
                </label>
                <label class="proj-qa__field proj-qa__field--full">
                  <span class="proj-qa__label">Mô tả</span>
                  <input v-model="row.description" class="proj-qa__input" maxlength="5000" placeholder="Mô tả ngắn cho phase này (tuỳ chọn)">
                </label>
                <button type="button" class="proj-qa__row-remove" aria-label="Xoá dòng" @click="removeStructureRow(row)">
                  <AppIcon name="close" :size="14" />
                </button>
              </div>
              <button type="button" class="proj-qa__add-row" @click="addStructureRow">
                <AppIcon name="plus" :size="14" />
                Thêm phase
              </button>
            </div>
          </div>

          <div v-else-if="kind === 'baseline'" class="proj-qa__grid proj-qa__grid--stack">
            <section class="proj-qa__section">
              <h3 class="proj-qa__section-title">Cập nhật baseline</h3>
              <div class="proj-qa__card proj-qa__card--warning">
                {{ baselineInfo }}
              </div>
              <div class="proj-qa__stats">
                <span class="proj-qa__chip proj-qa__chip--gold">{{ itemCounts.task_category || 0 }} danh mục</span>
                <span class="proj-qa__chip proj-qa__chip--info">{{ itemCounts.task || 0 }} công việc</span>
                <span class="proj-qa__chip proj-qa__chip--secondary">{{ itemCounts.phase || 0 }} phase</span>
              </div>
              <label class="proj-qa__field">
                <span class="proj-qa__label">Tên baseline</span>
                <input
                  ref="firstField"
                  v-model="baselineForm.title"
                  class="proj-qa__input"
                  maxlength="255"
                  placeholder="Tên baseline (tuỳ chọn)"
                >
              </label>
            </section>
            <section class="proj-qa__section">
              <h3 class="proj-qa__section-title">Lịch sử baseline</h3>
              <div v-if="listsLoading" class="proj-qa__empty">Đang tải…</div>
              <div v-else-if="!baselines.length" class="proj-qa__empty">Chưa có baseline nào</div>
              <ul v-else class="proj-qa__list hide-scrollbar">
                <li v-for="item in baselines" :key="item.id" class="proj-qa__card proj-qa__card--gold">
                  <span>{{ item.title }}</span>
                  <span>{{ formatWhen(item.created_at) }}</span>
                </li>
              </ul>
            </section>
          </div>

          <div v-else-if="kind === 'dates'" class="proj-qa__dates">
            <label class="proj-qa__field">
              <span class="proj-qa__label">Tên dự án</span>
              <input :value="projectLabel" type="text" class="proj-qa__input" disabled>
            </label>

            <section
              ref="plannedBlock"
              class="proj-qa__block"
              :class="{ 'proj-qa__block--active': !isActualFocus }"
            >
              <h3 class="proj-qa__block-title">Thời gian dự án</h3>
              <div class="proj-qa__dates-row">
                <label class="proj-qa__field">
                  <span class="proj-qa__label">Bắt đầu</span>
                  <input ref="startInput" v-model="datesForm.start_date" type="date" class="proj-qa__input">
                </label>
                <label class="proj-qa__field">
                  <span class="proj-qa__label">Kết thúc</span>
                  <input ref="endInput" v-model="datesForm.end_date" type="date" class="proj-qa__input">
                </label>
                <label class="proj-qa__field">
                  <span class="proj-qa__label">Ngày</span>
                  <input
                    :value="plannedDaysCompact"
                    type="text"
                    class="proj-qa__input"
                    disabled
                    :aria-label="durationLabel || 'Số ngày dự án'"
                  >
                </label>
              </div>
              <div v-if="plannedRangeInvalid" class="proj-qa__stat proj-qa__stat--warn">
                Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.
              </div>
              <label class="proj-qa__check">
                <input v-model="datesForm.shift_task_dates" type="checkbox">
                <span>Thay đổi thời gian của công việc</span>
              </label>
            </section>

            <section
              ref="actualBlock"
              class="proj-qa__block proj-qa__block--actual"
              :class="{ 'proj-qa__block--active': isActualFocus }"
            >
              <h3 class="proj-qa__block-title">Thời gian thực tế</h3>
              <div class="proj-qa__dates-row">
                <label class="proj-qa__field">
                  <span class="proj-qa__label">BĐ thực tế</span>
                  <input
                    ref="actualStartInput"
                    v-model="datesForm.actual_start_date"
                    type="date"
                    class="proj-qa__input"
                  >
                </label>
                <label class="proj-qa__field">
                  <span class="proj-qa__label">KT thực tế</span>
                  <input
                    ref="actualEndInput"
                    v-model="datesForm.actual_end_date"
                    type="date"
                    class="proj-qa__input"
                  >
                </label>
                <label class="proj-qa__field">
                  <span class="proj-qa__label">Ngày</span>
                  <input
                    :value="actualDaysCompact"
                    type="text"
                    class="proj-qa__input"
                    disabled
                    :aria-label="actualDurationLabel || 'Số ngày thực tế'"
                  >
                </label>
              </div>
              <div v-if="actualRangeInvalid" class="proj-qa__stat proj-qa__stat--warn">
                Ngày kết thúc thực tế phải sau hoặc bằng ngày bắt đầu thực tế.
              </div>
            </section>
          </div>

          <div v-else-if="kind === 'description'" class="proj-qa__grid">
            <label class="proj-qa__field proj-qa__field--full">
              <span class="proj-qa__label">Mô tả dự án</span>
              <textarea
                ref="firstField"
                v-model="descriptionForm.description"
                class="proj-qa__input proj-qa__textarea"
                rows="10"
                maxlength="5000"
                placeholder="Mô tả ngắn gọn mục tiêu, phạm vi công việc của dự án…"
              />
            </label>
          </div>

          <div v-else-if="kind === 'tabs_config'" class="proj-qa__grid">
            <p class="proj-qa__hint proj-qa__field--full">
              Chọn các tab sẽ hiển thị cho riêng dự án này. Tab Chi tiết và Công việc luôn hiển thị.
            </p>
            <label v-for="tab in OPTIONAL_TAB_DEFS" :key="tab.key" class="proj-qa__check">
              <input v-model="tabsConfigForm[tab.key]" type="checkbox">
              <span>{{ tab.label }}</span>
            </label>
          </div>
        </form>

        <div class="proj-qa__actions">
          <button type="button" class="proj-qa__btn proj-qa__btn--ghost" :disabled="saving" @click="close">
            Huỷ bỏ
          </button>
          <button type="button" class="proj-qa__btn proj-qa__btn--primary" :disabled="saving" @click="submit">
            {{ saving ? 'Đang lưu…' : primaryLabel }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.proj-qa {
  position: fixed;
  inset: 0;
  z-index: 1300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.proj-qa__panel {
  --qa-tone: var(--color-primary);
  --qa-tone-fg: var(--color-primary-900);
  --qa-tone-surface: var(--color-primary-surface);
  --qa-on: var(--color-on-primary);

  display: flex;
  flex-direction: column;
  overflow: hidden;
  width: min(54rem, calc(100vw - 2.5rem));
  max-width: calc(100vw - 2.5rem);
  max-height: calc(100vh - 2.5rem);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.proj-qa__panel--md {
  width: min(40rem, calc(100vw - 2.5rem));
}

.proj-qa__panel--lg {
  width: min(54rem, calc(100vw - 2.5rem));
}

.proj-qa__panel--xl {
  width: min(64rem, calc(100vw - 2.5rem));
}

.proj-qa__panel--tertiary {
  --qa-tone: var(--color-tertiary);
  --qa-tone-fg: var(--color-tertiary-800);
  --qa-tone-surface: var(--color-tertiary-surface);
  --qa-on: var(--color-on-tertiary);
}

.proj-qa__panel--gold,
.proj-qa__panel--warning {
  --qa-tone: var(--color-gold-600);
  --qa-tone-fg: var(--color-gold-800);
  --qa-tone-surface: var(--color-gold-surface);
  --qa-on: var(--color-on-gold);
}

.proj-qa__panel--info {
  --qa-tone: var(--color-info);
  --qa-tone-fg: var(--color-info-tint-fg);
  --qa-tone-surface: var(--color-info-tint-bg);
  --qa-on: #ffffff;
}

.proj-qa__panel--secondary {
  --qa-tone: var(--color-secondary);
  --qa-tone-fg: var(--color-secondary-800);
  --qa-tone-surface: var(--color-secondary-surface);
  --qa-on: var(--color-on-secondary);
}

.proj-qa__panel--violet {
  --qa-tone: color-mix(in srgb, var(--color-tertiary) 55%, var(--color-primary));
  --qa-tone-fg: var(--color-tertiary-800);
  --qa-tone-surface: color-mix(in srgb, var(--color-tertiary-surface) 55%, var(--color-primary-surface));
  --qa-on: #ffffff;
}

.proj-qa__panel--float {
  overflow: visible;
}

.proj-qa__panel--float .proj-qa__body {
  overflow: visible;
}

.proj-qa__head,
.proj-qa__actions {
  flex-shrink: 0;
}

.proj-qa__head {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: 1rem 1.25rem;
  box-shadow: 0 1px 0 var(--color-border);
}

.proj-qa__icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-md);
  background: var(--qa-tone-surface);
  color: var(--qa-tone);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--qa-tone) 18%, transparent);
}

.proj-qa__head-copy {
  flex: 1;
  min-width: 0;
}

.proj-qa__title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.125rem;
  font-weight: 700;
  line-height: 1.35;
}

.proj-qa__sub {
  margin: 0.125rem 0 0;
  overflow: hidden;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-style: italic;
  line-height: 1.4;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.proj-qa__close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--qa-tone);
  cursor: pointer;
}

.proj-qa__close:hover {
  background: var(--qa-tone-surface);
}

.proj-qa__body {
  flex: 1;
  min-height: 0;
  padding: 1.25rem;
  overflow: auto;
}

.proj-qa__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-4);
  align-content: start;
}

.proj-qa__grid--stack {
  grid-template-columns: minmax(0, 1fr);
}

.proj-qa__field {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.375rem;
}

.proj-qa__field--full {
  grid-column: 1 / -1;
}

.proj-qa__field--wide {
  grid-column: span 2;
}

.proj-qa__label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
}

.proj-qa__label em {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.25rem;
  padding: 0 0.375rem;
  border-radius: var(--radius-full);
  background: var(--qa-tone-surface);
  color: var(--qa-tone-fg);
  font-size: 0.6875rem;
  font-style: normal;
  font-weight: 700;
}

.proj-qa__input {
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

.proj-qa__input:focus {
  outline: 2px solid color-mix(in srgb, var(--qa-tone) 35%, transparent);
  outline-offset: 1px;
}

.proj-qa__input:disabled {
  color: var(--color-text);
  background: var(--color-surface-muted);
  cursor: default;
}

.proj-qa__textarea {
  resize: vertical;
  min-height: 7rem;
}

.proj-qa__banner {
  position: relative;
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.5rem 1.5rem;
  grid-column: 1 / -1;
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.proj-qa__body > .proj-qa__banner {
  margin-bottom: var(--space-4);
}

.proj-qa__banner::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-primary);
}

.proj-qa__banner--primary::before {
  background: var(--color-primary);
}

.proj-qa__banner--success::before {
  background: var(--color-success);
}

.proj-qa__banner--gold::before,
.proj-qa__banner--warning::before {
  background: var(--color-gold);
}

.proj-qa__banner--umber::before {
  background: var(--color-umber);
}

.proj-qa__banner--tertiary::before {
  background: var(--color-tertiary);
}

.proj-qa__banner--info::before {
  background: var(--color-info);
}

.proj-qa__meta {
  display: inline-flex;
  align-items: baseline;
  gap: 0.375rem;
  min-width: 0;
}

.proj-qa__meta--grow {
  flex: 1;
}

.proj-qa__meta-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.proj-qa__meta-label::after {
  content: ':';
}

.proj-qa__meta-value {
  min-width: 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-style: italic;
}

.proj-qa__meta--grow .proj-qa__meta-value {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.proj-qa__meta-value--status {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
}

.proj-qa__meta-dot {
  flex-shrink: 0;
  width: 0.4375rem;
  height: 0.4375rem;
  border-radius: 50%;
  background: var(--color-text-muted);
}

.proj-qa__banner--primary .proj-qa__meta-dot {
  background: var(--color-primary);
}

.proj-qa__banner--success .proj-qa__meta-dot {
  background: var(--color-success);
}

.proj-qa__banner--gold .proj-qa__meta-dot,
.proj-qa__banner--warning .proj-qa__meta-dot {
  background: var(--color-gold);
}

.proj-qa__banner--umber .proj-qa__meta-dot {
  background: var(--color-umber);
}

.proj-qa__banner--tertiary .proj-qa__meta-dot {
  background: var(--color-tertiary);
}

.proj-qa__banner--info .proj-qa__meta-dot {
  background: var(--color-info);
}

.proj-qa__chip {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  max-width: 100%;
  padding: 0.3125rem 0.75rem;
  border-radius: 999px;
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.3;
  box-shadow: inset 0 0 0 1px color-mix(in srgb, currentColor 16%, transparent);
}

.proj-qa__chip :deep(svg) {
  flex-shrink: 0;
}

.proj-qa__chip-dot {
  flex-shrink: 0;
  width: 0.4375rem;
  height: 0.4375rem;
  border-radius: 50%;
  background: currentColor;
}

.proj-qa__chip--code {
  background: var(--color-primary-50);
  color: var(--color-primary-900);
  font-variant-numeric: tabular-nums;
  letter-spacing: 0.02em;
}

.proj-qa__chip--date,
.proj-qa__chip--neutral {
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
}

.proj-qa__chip--neutral {
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.proj-qa__chip--primary {
  background: var(--color-primary-50);
  color: var(--color-primary-900);
}

.proj-qa__chip--success {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
}

.proj-qa__chip--gold,
.proj-qa__chip--warning {
  background: var(--color-gold-surface);
  color: var(--color-gold-800);
}

.proj-qa__chip--umber {
  background: var(--color-umber-tint-bg);
  color: var(--color-umber-tint-fg);
}

.proj-qa__chip--tertiary {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary-800);
}

.proj-qa__chip--info {
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
}

.proj-qa__chip--secondary {
  background: var(--color-secondary-surface);
  color: var(--color-secondary-800);
}

.proj-qa__chips,
.proj-qa__list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.proj-qa__list {
  flex-direction: column;
  flex-wrap: nowrap;
  max-height: 12rem;
  overflow: auto;
}

.proj-qa__side {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.75rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.proj-qa__side-title {
  color: var(--qa-tone-fg);
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.proj-qa__muted {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-style: italic;
}

.proj-qa__section {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.proj-qa__section-title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 700;
}

.proj-qa__dates {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.proj-qa__dates-row {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(0, 1.2fr) minmax(5.5rem, 0.55fr);
  gap: var(--space-3);
}

.proj-qa__block {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-4) var(--space-4) calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.proj-qa__block::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-info);
}

.proj-qa__block--actual::before {
  background: var(--color-success);
}

.proj-qa__block--active {
  box-shadow: var(--shadow-sm), 0 0 0 1px color-mix(in srgb, var(--qa-tone) 28%, transparent);
}

.proj-qa__block-title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.proj-qa__check {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  width: fit-content;
  color: var(--color-text);
  font-size: 0.875rem;
  cursor: pointer;
}

.proj-qa__check input {
  width: 1rem;
  height: 1rem;
  margin: 0;
  accent-color: var(--qa-tone);
  cursor: pointer;
}

.proj-qa__stats {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
}

.proj-qa__stat {
  padding: 0.75rem 1rem;
  border-radius: var(--radius-md);
  background: var(--qa-tone-surface);
  color: var(--qa-tone-fg);
  font-size: 0.875rem;
}

.proj-qa__stat--warn {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}

.proj-qa__card {
  position: relative;
  display: flex;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 0.875rem;
  line-height: 1.45;
  box-shadow: var(--shadow-sm);
}

.proj-qa__card::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--qa-tone);
}

.proj-qa__card--warning::before,
.proj-qa__card--gold::before {
  background: var(--color-gold);
}

.proj-qa__card--secondary::before {
  background: var(--color-secondary);
}

.proj-qa__card span:last-child {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-style: italic;
}

.proj-qa__empty {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 7.5rem;
  color: var(--color-text-muted);
  font-size: 0.875rem;
  border-radius: var(--radius-md);
  outline: 1px dashed var(--color-border);
  outline-offset: -1px;
  background: var(--color-surface-muted);
}

.proj-qa__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  padding: 0.875rem 1.25rem 1.125rem;
  box-shadow: 0 -1px 0 var(--color-border);
}

.proj-qa__btn {
  padding: 0.5rem 1rem;
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.proj-qa__btn--primary {
  background: var(--qa-tone);
  color: var(--qa-on);
}

.proj-qa__btn--primary:hover {
  filter: brightness(0.95);
}

.proj-qa__btn--ghost {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
}

.proj-qa__btn--ghost:hover {
  background: var(--color-surface-muted);
}

.proj-qa__btn:disabled {
  opacity: 0.6;
  cursor: default;
}

.proj-qa__hint {
  margin: 0 0 var(--space-3);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.45;
}

.proj-qa__structure {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.proj-qa__structure-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.proj-qa__structure-row {
  position: relative;
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr) auto;
  gap: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.proj-qa__structure-row--phase {
  /* Tên phase | Bắt đầu | Kết thúc | Cách tính tiến độ trên 1 hàng, nút xoá
     ở cuối hàng — Mô tả xuống hàng dưới (field--full) để đỡ tốn chiều cao
     so với xếp mỗi field 1 dòng riêng. */
  grid-template-columns: minmax(0, 1.3fr) minmax(0, 0.85fr) minmax(0, 0.85fr) minmax(0, 1fr) auto;
  row-gap: var(--space-2);
}

.proj-qa__structure-row--phase .proj-qa__row-remove {
  grid-row: 1;
}

.proj-qa__structure-row--bulk {
  grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr) minmax(0, 0.8fr) minmax(0, 0.8fr) auto;
}

.proj-qa__field--grow {
  min-width: 0;
}

.proj-qa__row-remove {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  align-self: end;
  width: 2rem;
  height: 2rem;
  margin-bottom: 0.125rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.proj-qa__row-remove:hover {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}

.proj-qa__add-row {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  width: fit-content;
  padding: 0.5rem 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-secondary-700);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.proj-qa__add-row:hover {
  background: var(--color-secondary-surface);
}

@media (max-width: 768px) {
  .proj-qa__grid,
  .proj-qa__dates-row,
  .proj-qa__structure-row,
  .proj-qa__structure-row--phase,
  .proj-qa__structure-row--bulk {
    grid-template-columns: minmax(0, 1fr);
  }

  .proj-qa__field--wide,
  .proj-qa__field--full {
    grid-column: 1 / -1;
  }

  .proj-qa__structure-row--phase .proj-qa__row-remove {
    grid-row: auto;
  }

  .proj-qa__row-remove {
    align-self: start;
    justify-self: end;
  }
}
</style>
