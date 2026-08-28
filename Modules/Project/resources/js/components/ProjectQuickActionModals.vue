<script setup>
//
// Modal thao tác nhanh từ menu chuột phải trên danh sách dự án.
// Form nằm trong viewport; 2–3 cột khi có nhiều field; field dài span full.
//
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { showClientToast } from '@/lib/clientToast';
import ProjectMemberPicker from './ProjectMemberPicker.vue';
import ProjectUserPicker from './ProjectUserPicker.vue';

const props = defineProps({
  kind: { type: String, default: null },
  project: { type: Object, default: null },
  extra: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'updated', 'duplicated']);

const TASK_TITLES = {
  normal: 'Thêm công việc thường',
  bulk: 'Thêm nhiều công việc thường',
  by_category: 'Thêm công việc theo danh mục',
  process: 'Thêm công việc quy trình',
  by_phase: 'Thêm công việc theo phase',
};

const KIND_META = {
  members: { title: 'Thêm người thực hiện', icon: 'userPlus', tone: 'tertiary' },
  category: { title: 'Thêm danh mục công việc', icon: 'listChecks', tone: 'gold' },
  task: { title: 'Thêm công việc', icon: 'plus', tone: 'info' },
  phase: { title: 'Thêm phase', icon: 'flag', tone: 'secondary' },
  baseline: { title: 'Chốt baseline', icon: 'flag', tone: 'warning' },
  dates: { title: 'Cập nhật thời gian', icon: 'calendar', tone: 'info' },
  description: { title: 'Cập nhật mô tả dự án', icon: 'fileText', tone: 'violet' },
  duplicate: { title: 'Nhân bản dự án', icon: 'copy', tone: 'success' },
};

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
const firstField = ref(null);

const membersForm = reactive({ member_ids: [], follower_ids: [] });
const datesForm = reactive({ start_date: '', end_date: '' });
const descriptionForm = reactive({ description: '' });
const categoryForm = reactive({ title: '' });
const phaseForm = reactive({ title: '', start_date: '', end_date: '' });
const taskForm = reactive({
  title: '',
  titles: '',
  start_date: '',
  end_date: '',
  assignee_id: '',
  category_id: '',
  phase_id: '',
});
const baselineForm = reactive({ title: '' });

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
const phaseDurationLabel = computed(() => daysBetween(phaseForm.start_date, phaseForm.end_date));
const taskDurationLabel = computed(() => daysBetween(taskForm.start_date, taskForm.end_date));

const bulkTitles = computed(() =>
  taskForm.titles
    .split(/\r?\n/)
    .map((s) => s.trim())
    .filter(Boolean),
);

const panelClass = computed(() => {
  if (props.kind === 'members' || props.kind === 'task' || props.kind === 'description') return 'proj-qa__panel--xl';
  if (props.kind === 'phase' || props.kind === 'category' || props.kind === 'baseline') return 'proj-qa__panel--lg';
  return 'proj-qa__panel--md';
});

const primaryLabel = computed(() => {
  if (props.kind === 'members' || props.kind === 'dates' || props.kind === 'description' || props.kind === 'baseline') {
    return 'Cập nhật';
  }
  return 'Thêm';
});

function daysBetween(start, end) {
  if (!start || !end) return null;
  const a = new Date(`${start}T00:00:00`);
  const b = new Date(`${end}T00:00:00`);
  if (Number.isNaN(a.getTime()) || Number.isNaN(b.getTime()) || b < a) return null;
  const n = Math.round((b - a) / 86400000) + 1;
  return `${n} ngày`;
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

function resetForms(p) {
  membersForm.member_ids = (p.members || []).map((m) => m.id);
  membersForm.follower_ids = (p.followers || []).map((f) => f.id);
  datesForm.start_date = p.start_date || '';
  datesForm.end_date = p.end_date || '';
  descriptionForm.description = p.description || '';
  categoryForm.title = '';
  phaseForm.title = '';
  phaseForm.start_date = p.start_date || '';
  phaseForm.end_date = p.end_date || '';
  taskForm.title = '';
  taskForm.titles = '';
  taskForm.start_date = p.start_date || '';
  taskForm.end_date = p.end_date || '';
  taskForm.assignee_id = '';
  taskForm.category_id = '';
  taskForm.phase_id = '';
  baselineForm.title = '';
}

function focusFirst() {
  nextTick(() => {
    if (props.kind === 'dates') {
      if (dateFocus.value === 'end') endInput.value?.focus();
      else startInput.value?.focus();
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
      if (kind === 'category' || (kind === 'task' && taskVariant.value === 'by_category')) {
        const data = await loadQuick('task_category');
        categories.value = data.items ?? [];
        itemCounts.value = { ...itemCounts.value, ...(data.counts || {}) };
      }
      if (kind === 'phase' || (kind === 'task' && taskVariant.value === 'by_phase')) {
        const data = await loadQuick('phase');
        phases.value = data.items ?? [];
        itemCounts.value = { ...itemCounts.value, ...(data.counts || {}) };
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

async function postQuick(payload, message) {
  const { data } = await window.axios.post(`/api/project/${props.project.id}/quick-items`, payload);
  if (payload.kind === 'task_category') categories.value = data.items ?? categories.value;
  if (payload.kind === 'phase') phases.value = data.items ?? phases.value;
  if (payload.kind === 'baseline') {
    baselines.value = (data.items ?? []).filter((i) => i.kind === 'baseline');
    itemCounts.value = data.counts ?? itemCounts.value;
  }
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
      const err = dateRangeError(datesForm.start_date, datesForm.end_date);
      if (err) {
        showClientToast('error', err);
        return;
      }
      await patchProject(
        { start_date: datesForm.start_date || null, end_date: datesForm.end_date || null },
        'Đã cập nhật thời gian dự án.',
      );
      return;
    }
    if (props.kind === 'description') {
      await patchProject({ description: descriptionForm.description || null }, 'Đã cập nhật mô tả dự án.');
      return;
    }
    if (props.kind === 'category') {
      const title = categoryForm.title.trim();
      if (!title) {
        showClientToast('error', 'Nhập tên danh mục công việc.');
        return;
      }
      await postQuick({ kind: 'task_category', title }, 'Đã thêm danh mục công việc.');
      return;
    }
    if (props.kind === 'phase') {
      const title = phaseForm.title.trim();
      if (!title) {
        showClientToast('error', 'Nhập tên phase.');
        return;
      }
      const err = dateRangeError(phaseForm.start_date, phaseForm.end_date);
      if (err) {
        showClientToast('error', err);
        return;
      }
      await postQuick(
        {
          kind: 'phase',
          title,
          payload: { start_date: phaseForm.start_date || null, end_date: phaseForm.end_date || null },
        },
        'Đã thêm phase.',
      );
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
        if (!bulkTitles.value.length) {
          showClientToast('error', 'Nhập ít nhất một công việc, mỗi dòng một tên.');
          return;
        }
        await postQuick(
          { kind: 'task', titles: bulkTitles.value, payload: { variant } },
          `Đã thêm ${bulkTitles.value.length} công việc.`,
        );
        return;
      }
      const title = taskForm.title.trim();
      if (!title) {
        showClientToast('error', variant === 'process' ? 'Nhập tên quy trình.' : 'Nhập tên công việc.');
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
      await postQuick(
        {
          kind: 'task',
          title,
          payload: {
            variant,
            start_date: taskForm.start_date || null,
            end_date: taskForm.end_date || null,
            assignee_id: taskForm.assignee_id || null,
            category_id: taskForm.category_id || null,
            phase_id: taskForm.phase_id || null,
          },
        },
        variant === 'process' ? 'Đã thêm công việc quy trình.' : 'Đã thêm công việc.',
      );
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
            <p v-if="project" class="proj-qa__sub">{{ projectLabel }}</p>
          </div>
          <button type="button" class="proj-qa__close" aria-label="Đóng" :disabled="saving" @click="close">
            <AppIcon name="close" :size="16" />
          </button>
        </div>

        <form class="proj-qa__body hide-scrollbar" @submit.prevent="submit">
          <div v-if="project && kind !== 'members'" class="proj-qa__banner">
            <span v-if="project.code" class="proj-qa__chip proj-qa__chip--code">{{ project.code }}</span>
            <span class="proj-qa__chip" :class="`proj-qa__chip--${statusTone}`">{{ statusLabel }}</span>
            <span v-if="project.start_date || project.end_date" class="proj-qa__chip proj-qa__chip--date">
              {{ formatDate(project.start_date) }} – {{ formatDate(project.end_date) }}
            </span>
          </div>

          <div v-if="kind === 'members'" class="proj-qa__grid">
            <div class="proj-qa__banner proj-qa__field--full">
              <span v-if="project?.code" class="proj-qa__chip proj-qa__chip--code">{{ project.code }}</span>
              <span class="proj-qa__banner-name">{{ project?.name }}</span>
              <span class="proj-qa__chip" :class="`proj-qa__chip--${statusTone}`">{{ statusLabel }}</span>
            </div>
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

          <div v-else-if="kind === 'category'" class="proj-qa__grid">
            <label class="proj-qa__field">
              <span class="proj-qa__label">Tên danh mục</span>
              <input
                ref="firstField"
                v-model="categoryForm.title"
                class="proj-qa__input"
                maxlength="255"
                placeholder="Ví dụ: Hạng mục thi công, Hồ sơ pháp lý"
                required
              >
            </label>
            <div class="proj-qa__side">
              <span class="proj-qa__side-title">Danh mục đã có</span>
              <p v-if="listsLoading" class="proj-qa__muted">Đang tải…</p>
              <p v-else-if="!categories.length" class="proj-qa__muted">Chưa có danh mục nào.</p>
              <ul v-else class="proj-qa__chips hide-scrollbar">
                <li v-for="item in categories" :key="item.id" class="proj-qa__chip proj-qa__chip--gold">{{ item.title }}</li>
              </ul>
            </div>
          </div>

          <div v-else-if="kind === 'task'" class="proj-qa__grid">
            <label v-if="taskVariant === 'bulk'" class="proj-qa__field proj-qa__field--full">
              <span class="proj-qa__label">
                Danh sách công việc
                <em v-if="bulkTitles.length">{{ bulkTitles.length }}</em>
              </span>
              <textarea
                ref="firstField"
                v-model="taskForm.titles"
                class="proj-qa__input proj-qa__textarea"
                rows="7"
                placeholder="Mỗi dòng một công việc, ví dụ:&#10;Lập hồ sơ mời thầu&#10;Phát hành hồ sơ&#10;Mở thầu"
              />
              <ul v-if="bulkTitles.length" class="proj-qa__chips">
                <li v-for="(name, idx) in bulkTitles.slice(0, 12)" :key="idx" class="proj-qa__chip proj-qa__chip--info">{{ name }}</li>
              </ul>
            </label>
            <template v-else>
              <label class="proj-qa__field" :class="{ 'proj-qa__field--wide': taskVariant === 'process' }">
                <span class="proj-qa__label">{{ taskVariant === 'process' ? 'Tên quy trình' : 'Tên công việc' }}</span>
                <input
                  ref="firstField"
                  v-model="taskForm.title"
                  class="proj-qa__input"
                  maxlength="255"
                  :placeholder="taskVariant === 'process' ? 'Ví dụ: Quy trình nghiệm thu' : 'Ví dụ: Lập hồ sơ mời thầu'"
                  required
                >
              </label>
              <div v-if="taskVariant !== 'process'" class="proj-qa__field">
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
            </template>
          </div>

          <div v-else-if="kind === 'phase'" class="proj-qa__grid">
            <label class="proj-qa__field proj-qa__field--full">
              <span class="proj-qa__label">Tên phase</span>
              <input
                ref="firstField"
                v-model="phaseForm.title"
                class="proj-qa__input"
                maxlength="255"
                placeholder="Ví dụ: Khởi động, Thi công, Nghiệm thu"
                required
              >
            </label>
            <label class="proj-qa__field">
              <span class="proj-qa__label">Ngày bắt đầu</span>
              <input v-model="phaseForm.start_date" type="date" class="proj-qa__input">
            </label>
            <label class="proj-qa__field">
              <span class="proj-qa__label">Ngày kết thúc</span>
              <input v-model="phaseForm.end_date" type="date" class="proj-qa__input">
            </label>
            <div v-if="phaseDurationLabel" class="proj-qa__stat">
              Thời gian phase <strong>{{ phaseDurationLabel }}</strong>
            </div>
            <div class="proj-qa__side proj-qa__field--full">
              <span class="proj-qa__side-title">Phase đã có</span>
              <p v-if="listsLoading" class="proj-qa__muted">Đang tải…</p>
              <p v-else-if="!phases.length" class="proj-qa__muted">Chưa có phase nào.</p>
              <ul v-else class="proj-qa__list hide-scrollbar">
                <li v-for="item in phases" :key="item.id" class="proj-qa__card proj-qa__card--secondary">
                  <span>{{ item.title }}</span>
                  <span>{{ formatDate(item.payload?.start_date) }} – {{ formatDate(item.payload?.end_date) }}</span>
                </li>
              </ul>
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

          <div v-else-if="kind === 'dates'" class="proj-qa__grid">
            <label class="proj-qa__field">
              <span class="proj-qa__label">Ngày bắt đầu</span>
              <input ref="startInput" v-model="datesForm.start_date" type="date" class="proj-qa__input">
            </label>
            <label class="proj-qa__field">
              <span class="proj-qa__label">Ngày kết thúc</span>
              <input ref="endInput" v-model="datesForm.end_date" type="date" class="proj-qa__input">
            </label>
            <div v-if="durationLabel" class="proj-qa__stat proj-qa__field--full">
              Thời gian thực hiện <strong>{{ durationLabel }}</strong>
            </div>
            <div
              v-else-if="datesForm.start_date && datesForm.end_date"
              class="proj-qa__stat proj-qa__stat--warn proj-qa__field--full"
            >
              Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.
            </div>
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
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  grid-column: 1 / -1;
}

.proj-qa__banner-name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.proj-qa__chip {
  display: inline-flex;
  align-items: center;
  max-width: 100%;
  padding: 0.1875rem 0.5625rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.3;
}

.proj-qa__chip--code,
.proj-qa__chip--date,
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

@media (max-width: 768px) {
  .proj-qa__grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .proj-qa__field--wide {
    grid-column: 1 / -1;
  }
}
</style>
