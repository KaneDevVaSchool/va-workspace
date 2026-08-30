<script setup>
//
// manager/evaluation-score-kit — khung chấm điểm của phòng ban.
// Hai cách tính, phòng chọn đúng một:
//   1. base_adjust   — điểm gốc ± theo số việc hoàn thành và tiêu chí
//                      cộng/trừ. Không tính khó/dễ. Xếp loại theo thang phòng.
//   2. weighted_task — không điểm gốc; cộng theo số việc, mỗi việc nhân
//                      trọng số khó/dễ và mức độ dự án.
//
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import OptionPicker from '@modules/Project/resources/js/components/OptionPicker.vue';

const MODES = [
  {
    id: 'base_adjust',
    icon: 'hash',
    title: 'Điểm gốc ± theo việc',
    lead: 'Phòng đặt điểm khởi đầu, rồi cộng hoặc trừ theo số việc hoàn thành và các tiêu chí khác. Không tính khó hay dễ. Kết quả xếp theo thang phân loại của phòng.',
  },
  {
    id: 'weighted_task',
    icon: 'layers',
    title: 'Theo trọng số việc & dự án',
    lead: 'Không cần điểm khởi đầu. Mỗi việc nhân trọng số khó/dễ và mức độ dự án, rồi cộng theo số lượng việc đã làm.',
  },
];

const TYPE_LABELS = { scale: 'Thang điểm', behavior: 'Cộng/trừ' };

const auth = useAuthStore();

const allCriteria = ref([]);
const criterionTypes = ref([]);
const loading = ref(false);
const hydrating = ref(true);
const togglingId = ref(null);
const packingId = ref(null);
const packingCount = ref(0);
const packingGroupKey = ref(null);
const reloading = ref(false);
const savingKit = ref(false);
const query = ref('');
const groupCollapsed = reactive({});

const kit = reactive({
  id: null,
  mode: null,
  base_score: 100,
  points_per_completed_task: 0,
  points_per_incomplete_task: 0,
  use_project_importance: true,
  classification_criterion_id: null,
});

let saveTimer = null;

const hasDepartment = computed(() => Boolean(auth.user?.department?.id));
const canManage = computed(() => auth.can('evaluation.manage_department'));
const departmentName = computed(() => auth.user?.department?.name || 'Chưa gắn phòng ban');

const assigned = computed(() => allCriteria.value.find((c) => c.use_for_task_type) ?? null);

const classification = computed(() => {
  const id = kit.classification_criterion_id;
  if (id == null) return null;
  return allCriteria.value.find((c) => String(c.id) === String(id)) ?? null;
});

const scaleCriteria = computed(() =>
  allCriteria.value.filter((c) => c.type === 'scale' && c.is_active),
);

const classificationOptions = computed(() =>
  scaleCriteria.value
    .filter((c) => !c.use_for_task_type)
    .map((c) => ({
      value: String(c.id),
      label: c.name,
      description: c.criterion_type?.name || 'Thang điểm',
    })),
);

const taskTypeOptions = computed(() =>
  scaleCriteria.value
    .filter((c) => String(c.id) !== String(kit.classification_criterion_id ?? ''))
    .map((c) => ({
      value: String(c.id),
      label: c.name,
      description: c.criterion_type?.name || 'Thang điểm',
    })),
);

const classificationValue = computed({
  get: () => (kit.classification_criterion_id != null ? String(kit.classification_criterion_id) : ''),
  set: (value) => {
    kit.classification_criterion_id = value === '' ? null : Number(value);
    saveKit();
  },
});

const taskTypeValue = computed({
  get: () => (assigned.value ? String(assigned.value.id) : ''),
  set: (value) => {
    setTaskType(value === '' ? null : Number(value));
  },
});

function applyKit(data) {
  kit.id = data?.id ?? null;
  kit.mode = data?.mode ?? null;
  kit.base_score = data?.base_score ?? 100;
  kit.points_per_completed_task = data?.points_per_completed_task ?? 0;
  kit.points_per_incomplete_task = data?.points_per_incomplete_task ?? 0;
  kit.use_project_importance = data?.use_project_importance !== false;
  kit.classification_criterion_id = data?.classification_criterion_id ?? null;
}

function levelKey(level, index) {
  const code = String(level?.code ?? '').trim().toUpperCase();
  return code !== '' ? code : `#${index}`;
}

function packedCodesOf(criterion) {
  return Array.isArray(criterion?.task_score_level_codes)
    ? criterion.task_score_level_codes.map((code) => String(code).trim().toUpperCase()).filter(Boolean)
    : [];
}

function levelRows(criterion) {
  return (criterion?.levels ?? []).filter((level) => {
    const code = String(level.code ?? '').trim();
    const label = String(level.label ?? '').trim();
    return code !== '' || label !== '';
  });
}

function isLevelPacked(criterion, index) {
  const rows = levelRows(criterion);
  const key = levelKey(rows[index], index);
  return packedCodesOf(criterion).includes(key);
}

function packedCountOf(criterion) {
  const rows = levelRows(criterion);
  return rows.filter((_, index) => isLevelPacked(criterion, index)).length;
}

function criterionPackedState(criterion) {
  const total = levelRows(criterion).length;
  const packed = packedCountOf(criterion);
  if (total === 0 || packed === 0) return 'none';
  if (packed === total) return 'all';
  return 'some';
}

function typeOrderIndex(typeId) {
  if (typeId == null || typeId === '') return Number.MAX_SAFE_INTEGER;
  const idx = criterionTypes.value.findIndex((t) => String(t.id) === String(typeId));
  return idx >= 0 ? idx : Number.MAX_SAFE_INTEGER;
}

function criterionGroupKey(typeId) {
  return typeId == null || typeId === '' ? '__none__' : String(typeId);
}

function groupTitle(type) {
  return type?.name ?? 'Chưa nhóm';
}

function groupTone(criteria) {
  const kinds = new Set(criteria.map((c) => c.type).filter(Boolean));
  if (kinds.has('scale') && kinds.has('behavior')) return 'mixed';
  if (kinds.has('scale')) return 'scale';
  const scores = criteria.flatMap((c) => levelRows(c).map((level) => Number(level.score)));
  const hasNeg = scores.some((s) => s < 0);
  const hasPos = scores.some((s) => s > 0);
  if (hasNeg && !hasPos) return 'penalty';
  if (hasPos && !hasNeg) return 'bonus';
  return 'behavior';
}

function isReserved(criterion) {
  if (String(criterion.id) === String(kit.classification_criterion_id ?? '')) {
    return true;
  }
  if (criterion.use_for_task_type) {
    return true;
  }
  return false;
}

const packableCriteria = computed(() => {
  const q = query.value.trim().toLowerCase();
  return allCriteria.value.filter((c) => {
    if (isReserved(c)) return false;
    if (!q) return true;
    const hay = [
      c.name,
      c.description,
      c.criterion_type?.name,
      c.criterion_type?.code,
      ...levelRows(c).flatMap((level) => [level.code, level.label, level.description]),
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();
    return hay.includes(q);
  });
});

const criteriaGroups = computed(() => {
  const sorted = [...packableCriteria.value].sort((a, b) => {
    const orderA = typeOrderIndex(a.criterion_type_id);
    const orderB = typeOrderIndex(b.criterion_type_id);
    if (orderA !== orderB) return orderA - orderB;
    return (a.sort_order ?? 0) - (b.sort_order ?? 0) || (a.name || '').localeCompare(b.name || '', 'vi');
  });
  const groups = [];
  const map = new Map();
  for (const criterion of sorted) {
    const key = criterionGroupKey(criterion.criterion_type_id);
    if (!map.has(key)) {
      const entry = { key, type: criterion.criterion_type ?? null, criteria: [] };
      map.set(key, entry);
      groups.push(entry);
    }
    map.get(key).criteria.push(criterion);
  }
  return groups.map((group) => ({
    ...group,
    title: groupTitle(group.type),
    tone: groupTone(group.criteria),
    packedLevels: group.criteria.reduce((n, c) => n + packedCountOf(c), 0),
    totalLevels: group.criteria.reduce((n, c) => n + levelRows(c).length, 0),
  }));
});

const packedItems = computed(() => {
  const items = [];
  for (const criterion of allCriteria.value) {
    if (isReserved(criterion)) continue;
    const rows = levelRows(criterion);
    rows.forEach((level, index) => {
      if (!isLevelPacked(criterion, index)) return;
      items.push({
        criterionId: criterion.id,
        criterionName: criterion.name,
        typeName: criterion.criterion_type?.name || '',
        type: criterion.type,
        index,
        key: levelKey(level, index),
        code: String(level.code ?? '').trim(),
        label: String(level.label ?? '').trim(),
        score: level.score,
      });
    });
  }
  return items;
});

const packedByType = computed(() => {
  const groups = [];
  const map = new Map();
  for (const item of packedItems.value) {
    const key = item.typeName || '__none__';
    if (!map.has(key)) {
      const entry = { key, title: item.typeName || 'Khác', items: [] };
      map.set(key, entry);
      groups.push(entry);
    }
    map.get(key).items.push(item);
  }
  return groups;
});

const classificationLevels = computed(() => {
  const rows = classification.value ? levelRows(classification.value) : [];
  return [...rows].sort((a, b) => Number(b.score) - Number(a.score));
});

const importanceLevels = computed(() => (assigned.value ? levelRows(assigned.value) : []));

function formatScore(score, type) {
  const n = Number(score);
  if (!Number.isFinite(n)) return '—';
  if (type === 'scale') return `x${n}`;
  const abs = Number.isInteger(n) ? String(Math.abs(n)) : String(Math.abs(n));
  if (n > 0) return `+${abs}`;
  if (n < 0) return `−${abs}`;
  return abs;
}

function formatSigned(score) {
  const n = Number(score);
  if (!Number.isFinite(n)) return '0';
  const abs = Number.isInteger(n) ? String(Math.abs(n)) : String(Math.abs(n));
  if (n > 0) return `+${abs}`;
  if (n < 0) return `−${abs}`;
  return abs;
}

function scoreTone(score) {
  const n = Number(score);
  if (n > 0) return 'pos';
  if (n < 0) return 'neg';
  return 'zero';
}

function replaceCriterion(updated) {
  allCriteria.value = allCriteria.value.map((c) => (c.id === updated.id ? updated : c));
}

async function load() {
  if (!hasDepartment.value) {
    allCriteria.value = [];
    criterionTypes.value = [];
    applyKit(null);
    return;
  }
  loading.value = true;
  hydrating.value = true;
  try {
    const { data } = await window.axios.get('/api/evaluation/score-kit');
    allCriteria.value = data.criteria ?? [];
    criterionTypes.value = data.types ?? [];
    applyKit(data.kit);
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không tải được khung chấm điểm.');
  } finally {
    loading.value = false;
    hydrating.value = false;
  }
}

function kitPayload() {
  return {
    mode: kit.mode,
    base_score: Number(kit.base_score) || 0,
    points_per_completed_task: Number(kit.points_per_completed_task) || 0,
    points_per_incomplete_task: Number(kit.points_per_incomplete_task) || 0,
    use_project_importance: Boolean(kit.use_project_importance),
    classification_criterion_id: kit.classification_criterion_id,
  };
}

async function saveKit({ silent = false } = {}) {
  if (!canManage.value || hydrating.value) return;
  savingKit.value = true;
  try {
    const { data } = await window.axios.put('/api/evaluation/score-kit', kitPayload());
    applyKit(data.kit);
    if (!silent) {
      showClientToast('success', 'Đã lưu cách tính điểm.');
    }
  } catch (err) {
    const first = err?.response?.data?.errors
      ? Object.values(err.response.data.errors).flat()[0]
      : null;
    showClientToast('error', first || err?.response?.data?.message || 'Không lưu được khung chấm điểm.');
  } finally {
    savingKit.value = false;
  }
}

function scheduleSaveKit() {
  if (hydrating.value || !canManage.value) return;
  clearTimeout(saveTimer);
  saveTimer = setTimeout(() => saveKit({ silent: true }), 450);
}

function selectMode(id) {
  if (!canManage.value || kit.mode === id) return;
  kit.mode = id;
  saveKit();
}

function toggleProjectImportance() {
  if (!canManage.value) return;
  kit.use_project_importance = !kit.use_project_importance;
  saveKit({ silent: true });
}

async function setTaskType(criterionId) {
  if (!canManage.value || togglingId.value) return;
  const currentId = assigned.value?.id ?? null;
  if (currentId === criterionId) return;
  const targetId = criterionId ?? currentId;
  const criterion = allCriteria.value.find((c) => c.id === targetId);
  if (!criterion) return;
  await assignCriterion(criterion);
}

async function assignCriterion(criterion) {
  if (!canManage.value || togglingId.value) return;
  if (criterion.type !== 'scale' && !criterion.use_for_task_type) {
    showClientToast('error', 'Chỉ tiêu chí thang điểm mới gán được làm trọng số khó/dễ.');
    return;
  }
  togglingId.value = criterion.id;
  try {
    const { data } = await window.axios.patch(
      `/api/evaluation/criteria/${criterion.id}/toggle-task-type`,
    );
    const nextAssigned = Boolean(data.criterion.use_for_task_type);
    allCriteria.value = allCriteria.value.map((c) => {
      if (c.id === data.criterion.id) return data.criterion;
      return nextAssigned ? { ...c, use_for_task_type: false } : c;
    });
    showClientToast(
      'success',
      nextAssigned
        ? `Đã dùng “${data.criterion.name}” làm trọng số khó/dễ.`
        : `Đã bỏ trọng số khó/dễ “${data.criterion.name}”.`,
    );
  } catch (err) {
    showClientToast(
      'error',
      err?.response?.data?.message || 'Không gán được trọng số khó/dễ.',
    );
  } finally {
    togglingId.value = null;
  }
}

async function savePackedCodes(criterion, codes, { silent = false } = {}) {
  if (!canManage.value) return;
  packingCount.value += 1;
  packingId.value = criterion.id;
  try {
    const { data } = await window.axios.patch(
      `/api/evaluation/criteria/${criterion.id}/task-score-levels`,
      { codes },
    );
    replaceCriterion(data.criterion);
    if (!silent) {
      const count = (data.criterion.task_score_level_codes ?? []).length;
      showClientToast(
        'success',
        count > 0
          ? `Đã chọn ${count} mức của “${data.criterion.name}”.`
          : `Đã bỏ “${data.criterion.name}” khỏi khung.`,
      );
    }
  } catch (err) {
    showClientToast(
      'error',
      err?.response?.data?.message || 'Không cập nhật được tiêu chí trong khung.',
    );
  } finally {
    packingCount.value = Math.max(0, packingCount.value - 1);
    if (packingCount.value === 0) packingId.value = null;
  }
}

function toggleLevel(criterion, index) {
  if (!canManage.value || packingId.value) return;
  const rows = levelRows(criterion);
  const key = levelKey(rows[index], index);
  const next = packedCodesOf(criterion).filter((code) => code !== key);
  if (!packedCodesOf(criterion).includes(key)) next.push(key);
  savePackedCodes(criterion, next, { silent: true });
}

function toggleCriterionPack(criterion) {
  if (!canManage.value || packingId.value) return;
  const rows = levelRows(criterion);
  const allKeys = rows.map((level, index) => levelKey(level, index));
  const next = criterionPackedState(criterion) === 'all' ? [] : allKeys;
  savePackedCodes(criterion, next);
}

async function toggleGroupPack(group) {
  if (!canManage.value || packingId.value || packingGroupKey.value) return;
  const shouldPack = group.packedLevels < group.totalLevels;
  packingGroupKey.value = group.key;
  try {
    for (const criterion of group.criteria) {
      const rows = levelRows(criterion);
      const keys = shouldPack ? rows.map((level, index) => levelKey(level, index)) : [];
      const current = packedCodesOf(criterion);
      const same =
        keys.length === current.length && keys.every((key) => current.includes(key));
      if (same) continue;
      await savePackedCodes(criterion, keys, { silent: true });
    }
    showClientToast(
      'success',
      shouldPack ? `Đã chọn nhóm “${group.title}”.` : `Đã bỏ nhóm “${group.title}”.`,
    );
  } finally {
    packingGroupKey.value = null;
  }
}

function unpackItem(item) {
  const criterion = allCriteria.value.find((c) => c.id === item.criterionId);
  if (!criterion) return;
  const next = packedCodesOf(criterion).filter((code) => code !== item.key);
  savePackedCodes(criterion, next, { silent: true });
}

async function unpackAll() {
  if (!canManage.value || packingId.value) return;
  const targets = allCriteria.value.filter((c) => !isReserved(c) && packedCodesOf(c).length);
  if (!targets.length) return;
  packingGroupKey.value = '__all__';
  try {
    for (const criterion of targets) {
      await savePackedCodes(criterion, [], { silent: true });
    }
    showClientToast('success', 'Đã bỏ hết tiêu chí cộng/trừ.');
  } finally {
    packingGroupKey.value = null;
  }
}

function toggleGroup(key) {
  groupCollapsed[key] = !groupCollapsed[key];
}

async function reload() {
  if (reloading.value) return;
  reloading.value = true;
  try {
    await load();
  } finally {
    reloading.value = false;
  }
}

watch(
  () => kit.mode,
  () => {
    query.value = '';
  },
);

onMounted(() => {
  load();
});

onBeforeUnmount(() => {
  clearTimeout(saveTimer);
});
</script>

<template>
  <section class="kit-wrap">
    <PageHeader
      title="Khung chấm điểm"
      :subtitle="departmentName"
      icon="layers"
      :breadcrumbs="[
        { label: 'Trang chủ', to: { name: 'home' } },
        { label: 'Khung chấm điểm' },
      ]"
    >
      <template #actions>
        <button type="button" class="kit-header-btn" :disabled="reloading" @click="reload">
          <AppIcon name="refresh" :size="16" :class="{ 'kit-header-btn__spin': reloading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

    <div class="kit-page">
      <p v-if="!hasDepartment" class="kit-empty">
        Tài khoản chưa gắn phòng ban — không thể cấu hình khung chấm điểm.
      </p>

      <p v-else-if="loading" class="kit-empty">Đang tải khung chấm điểm…</p>

      <template v-else>
        <div class="kit-body">
          <div class="kit-main hide-scrollbar">
            <section class="kit-modes" aria-label="Cách tính điểm">
              <button
                v-for="mode in MODES"
                :key="mode.id"
                type="button"
                class="kit-mode"
                :class="{ 'kit-mode--on': kit.mode === mode.id }"
                :disabled="!canManage"
                :aria-pressed="kit.mode === mode.id ? 'true' : 'false'"
                @click="selectMode(mode.id)"
              >
                <span class="kit-mode__icon" aria-hidden="true">
                  <AppIcon :name="mode.icon" :size="18" :stroke-width="1.75" />
                </span>
                <span class="kit-mode__copy">
                  <span class="kit-mode__title">{{ mode.title }}</span>
                  <span class="kit-mode__lead">{{ mode.lead }}</span>
                </span>
                <span class="kit-mode__mark" aria-hidden="true">
                  <AppIcon v-if="kit.mode === mode.id" name="check" :size="14" :stroke-width="2.25" />
                </span>
              </button>
            </section>

            <template v-if="kit.mode === 'base_adjust'">
              <section class="kit-card">
                <h2 class="kit-card__title">Điểm khởi đầu và số việc</h2>
                <div class="kit-fields">
                  <label class="kit-field" for="kit-base-score">
                    <span class="kit-field__label">Điểm khởi đầu</span>
                    <input
                      id="kit-base-score"
                      v-model.number="kit.base_score"
                      type="number"
                      min="0"
                      max="9999"
                      step="1"
                      class="kit-field__input"
                      :disabled="!canManage || savingKit"
                      @input="scheduleSaveKit"
                    />
                  </label>
                  <label class="kit-field" for="kit-done">
                    <span class="kit-field__label">Mỗi việc hoàn thành</span>
                    <input
                      id="kit-done"
                      v-model.number="kit.points_per_completed_task"
                      type="number"
                      min="-999"
                      max="999"
                      step="0.5"
                      class="kit-field__input"
                      :disabled="!canManage || savingKit"
                      @input="scheduleSaveKit"
                    />
                  </label>
                  <label class="kit-field" for="kit-undone">
                    <span class="kit-field__label">Mỗi việc chưa hoàn thành</span>
                    <input
                      id="kit-undone"
                      v-model.number="kit.points_per_incomplete_task"
                      type="number"
                      min="-999"
                      max="999"
                      step="0.5"
                      class="kit-field__input"
                      :disabled="!canManage || savingKit"
                      @input="scheduleSaveKit"
                    />
                  </label>
                </div>
              </section>

              <section class="kit-card">
                <h2 class="kit-card__title" id="kit-class-label">Thang phân loại</h2>
                <OptionPicker
                  v-model="classificationValue"
                  :options="classificationOptions"
                  :disabled="!canManage || savingKit || !classificationOptions.length"
                  :clearable="true"
                  labelled-by="kit-class-label"
                  placeholder="Chọn thang điểm xếp loại"
                />
                <ul v-if="classificationLevels.length" class="kit-ranks">
                  <li v-for="(level, idx) in classificationLevels" :key="`${level.code}-${idx}`" class="kit-rank">
                    <span class="kit-rank__code">{{ String(level.code || '').trim() || '—' }}</span>
                    <span class="kit-rank__label">{{ level.label }}</span>
                    <span class="kit-rank__score">≥ {{ formatSigned(level.score).replace('+', '') }}</span>
                  </li>
                </ul>
                <p v-else-if="!classificationOptions.length" class="kit-card__empty">
                  Chưa có tiêu chí thang điểm.
                  <RouterLink class="kit-link" :to="{ name: 'manager.workspace-config.evaluation' }">
                    Tạo thang phân loại
                  </RouterLink>
                </p>
              </section>
            </template>

            <template v-else-if="kit.mode === 'weighted_task'">
              <section class="kit-card">
                <h2 class="kit-card__title" id="kit-weight-label">Trọng số khó / dễ của việc</h2>
                <OptionPicker
                  v-model="taskTypeValue"
                  :options="taskTypeOptions"
                  :disabled="!canManage || Boolean(togglingId) || !taskTypeOptions.length"
                  :clearable="true"
                  labelled-by="kit-weight-label"
                  placeholder="Chọn thang trọng số công việc"
                />
                <ul v-if="importanceLevels.length" class="kit-ranks">
                  <li v-for="(level, idx) in importanceLevels" :key="`${level.code}-${idx}`" class="kit-rank">
                    <span class="kit-rank__code">{{ String(level.code || '').trim() || '—' }}</span>
                    <span class="kit-rank__copy">
                      <span class="kit-rank__label">{{ level.label }}</span>
                      <span v-if="level.description" class="kit-rank__note">{{ level.description }}</span>
                    </span>
                    <span class="kit-score kit-score--pos">{{ formatScore(level.score, 'scale') }}</span>
                  </li>
                </ul>
                <p v-else-if="!taskTypeOptions.length" class="kit-card__empty">
                  Chưa có tiêu chí thang điểm.
                  <RouterLink class="kit-link" :to="{ name: 'manager.workspace-config.evaluation' }">
                    Tạo thang trọng số
                  </RouterLink>
                </p>
              </section>

              <section class="kit-card kit-card--switch">
                <div class="kit-switch-copy">
                  <h2 class="kit-card__title">Nhân thêm mức độ dự án</h2>
                  <p class="kit-card__lead">Mỗi việc còn nhân trọng số mức độ quan trọng của dự án chứa nó.</p>
                </div>
                <button
                  type="button"
                  class="kit-switch"
                  :class="{ 'kit-switch--on': kit.use_project_importance }"
                  :disabled="!canManage || savingKit"
                  :aria-pressed="kit.use_project_importance ? 'true' : 'false'"
                  aria-label="Nhân thêm mức độ dự án"
                  @click="toggleProjectImportance"
                >
                  <span class="kit-switch__thumb" />
                </button>
              </section>
            </template>

            <template v-if="kit.mode">
              <p v-if="!allCriteria.length" class="kit-empty">
                Phòng ban chưa có tiêu chí đánh giá.
                <RouterLink class="kit-link" :to="{ name: 'manager.workspace-config.evaluation' }">
                  Tạo tiêu chí
                </RouterLink>
              </p>

              <template v-else>
                <div class="kit-pack-head">
                  <h2 class="kit-pack-head__title">
                    {{ kit.mode === 'base_adjust' ? 'Tiêu chí cộng / trừ' : 'Tiêu chí khác' }}
                  </h2>
                  <label class="kit-search">
                    <AppIcon name="search" :size="16" :stroke-width="1.75" />
                    <input
                      v-model="query"
                      type="search"
                      class="kit-search__input"
                      placeholder="Tìm tiêu chí…"
                      aria-label="Tìm tiêu chí"
                    />
                  </label>
                </div>

                <p v-if="!criteriaGroups.length" class="kit-empty kit-empty--plain">
                  Không còn tiêu chí nào để chọn.
                </p>

                <section
                  v-for="group in criteriaGroups"
                  :key="group.key"
                  class="kit-group"
                  :class="`kit-group--${group.tone}`"
                >
                  <div class="kit-group__bar">
                    <button
                      type="button"
                      class="kit-group__toggle"
                      :aria-expanded="!groupCollapsed[group.key]"
                      @click="toggleGroup(group.key)"
                    >
                      <AppIcon
                        :name="groupCollapsed[group.key] ? 'chevronRight' : 'chevronDown'"
                        :size="16"
                        :stroke-width="1.75"
                      />
                      <span class="kit-group__title">{{ group.title }}</span>
                      <span class="kit-group__count">
                        {{ group.packedLevels }}/{{ group.totalLevels }} mức
                      </span>
                    </button>
                    <button
                      v-if="canManage && group.totalLevels"
                      type="button"
                      class="kit-group__pack"
                      :disabled="Boolean(packingId) || Boolean(packingGroupKey)"
                      @click="toggleGroupPack(group)"
                    >
                      {{ group.packedLevels >= group.totalLevels && group.totalLevels ? 'Bỏ nhóm' : 'Chọn cả nhóm' }}
                    </button>
                  </div>

                  <div v-if="!groupCollapsed[group.key]" class="kit-group__body">
                    <article
                      v-for="criterion in group.criteria"
                      :key="criterion.id"
                      class="kit-crit"
                      :class="{
                        'kit-crit--on': criterionPackedState(criterion) !== 'none',
                        'kit-crit--off': !criterion.is_active,
                      }"
                    >
                      <header class="kit-crit__head">
                        <button
                          type="button"
                          class="kit-check"
                          :class="{
                            'kit-check--on': criterionPackedState(criterion) === 'all',
                            'kit-check--some': criterionPackedState(criterion) === 'some',
                          }"
                          :disabled="!canManage || Boolean(packingId) || !levelRows(criterion).length"
                          :aria-pressed="criterionPackedState(criterion) !== 'none' ? 'true' : 'false'"
                          :aria-label="criterionPackedState(criterion) === 'all' ? 'Bỏ tiêu chí' : 'Chọn toàn bộ mức'"
                          @click="toggleCriterionPack(criterion)"
                        >
                          <AppIcon
                            v-if="criterionPackedState(criterion) === 'all'"
                            name="check"
                            :size="12"
                            :stroke-width="2.25"
                          />
                          <AppIcon
                            v-else-if="criterionPackedState(criterion) === 'some'"
                            name="minus"
                            :size="12"
                            :stroke-width="2.25"
                          />
                        </button>
                        <div class="kit-crit__identity">
                          <div class="kit-crit__name-row">
                            <h3 class="kit-crit__name">{{ criterion.name }}</h3>
                            <span class="kit-tag">{{ TYPE_LABELS[criterion.type] ?? criterion.type }}</span>
                            <span v-if="!criterion.is_active" class="kit-tag kit-tag--muted">Đang tắt</span>
                          </div>
                          <p v-if="criterion.description" class="kit-crit__desc">{{ criterion.description }}</p>
                        </div>
                      </header>

                      <ul v-if="levelRows(criterion).length" class="kit-levels">
                        <li
                          v-for="(level, idx) in levelRows(criterion)"
                          :key="`${criterion.id}-${idx}`"
                          class="kit-level"
                          :class="{ 'kit-level--on': isLevelPacked(criterion, idx) }"
                        >
                          <button
                            type="button"
                            class="kit-check kit-check--sm"
                            :class="{ 'kit-check--on': isLevelPacked(criterion, idx) }"
                            :disabled="!canManage || Boolean(packingId)"
                            :aria-pressed="isLevelPacked(criterion, idx) ? 'true' : 'false'"
                            :aria-label="isLevelPacked(criterion, idx) ? 'Bỏ khỏi khung' : 'Thêm vào khung'"
                            @click="toggleLevel(criterion, idx)"
                          >
                            <AppIcon
                              v-if="isLevelPacked(criterion, idx)"
                              name="check"
                              :size="11"
                              :stroke-width="2.25"
                            />
                          </button>
                          <span class="kit-level__code">{{ String(level.code || '').trim() || '—' }}</span>
                          <span class="kit-level__copy">
                            <span class="kit-level__label">{{ level.label }}</span>
                            <span v-if="level.description" class="kit-level__note">{{ level.description }}</span>
                          </span>
                          <span class="kit-score" :class="`kit-score--${scoreTone(level.score)}`">
                            {{ formatScore(level.score, criterion.type) }}
                          </span>
                        </li>
                      </ul>
                    </article>
                  </div>
                </section>
              </template>
            </template>
          </div>

          <aside class="kit-side" aria-label="Cách tính đang dùng">
            <div class="kit-side__head">
              <span class="kit-side__title">Cách tính</span>
              <button
                v-if="canManage && packedItems.length"
                type="button"
                class="kit-side__clear"
                :disabled="Boolean(packingId) || Boolean(packingGroupKey)"
                @click="unpackAll"
              >
                Bỏ tiêu chí
              </button>
            </div>

            <div class="kit-side__body hide-scrollbar">
              <p v-if="!kit.mode" class="kit-side__empty">Chọn một cách tính bên trái để bắt đầu.</p>

              <ol v-else class="kit-formula">
                <template v-if="kit.mode === 'base_adjust'">
                  <li class="kit-formula__step">
                    Bắt đầu với
                    <strong>{{ formatSigned(kit.base_score).replace('+', '') }}</strong>
                    điểm
                  </li>
                  <li class="kit-formula__step">
                    Việc hoàn thành
                    <strong>{{ formatSigned(kit.points_per_completed_task) }}</strong>
                    / việc
                  </li>
                  <li class="kit-formula__step">
                    Việc chưa hoàn thành
                    <strong>{{ formatSigned(kit.points_per_incomplete_task) }}</strong>
                    / việc
                  </li>
                  <li class="kit-formula__step">
                    Cộng trừ các tiêu chí đã chọn
                    <strong>{{ packedItems.length }}</strong>
                    mức
                  </li>
                  <li class="kit-formula__step">
                    Xếp loại theo
                    <strong>{{ classification?.name || 'chưa chọn thang' }}</strong>
                  </li>
                </template>
                <template v-else>
                  <li class="kit-formula__step">
                    Mỗi việc nhân trọng số khó/dễ
                    <strong>{{ assigned?.name || 'chưa chọn thang' }}</strong>
                  </li>
                  <li v-if="kit.use_project_importance" class="kit-formula__step">
                    Nhân thêm mức độ dự án chứa việc đó
                  </li>
                  <li class="kit-formula__step">
                    Cộng theo số việc đã làm
                  </li>
                  <li v-if="packedItems.length" class="kit-formula__step">
                    Cộng trừ
                    <strong>{{ packedItems.length }}</strong>
                    mức tiêu chí khác
                  </li>
                </template>
              </ol>

              <section v-if="packedByType.length" class="kit-picked">
                <h3 class="kit-picked__title">Đã chọn</h3>
                <div v-for="bundle in packedByType" :key="bundle.key" class="kit-bundle">
                  <p class="kit-bundle__title">{{ bundle.title }}</p>
                  <ul class="kit-bundle__list">
                    <li
                      v-for="item in bundle.items"
                      :key="`${item.criterionId}-${item.key}`"
                      class="kit-bundle__item"
                    >
                      <span class="kit-bundle__code">{{ item.code || '—' }}</span>
                      <span class="kit-bundle__label">{{ item.label }}</span>
                      <span class="kit-score" :class="`kit-score--${scoreTone(item.score)}`">
                        {{ formatScore(item.score, item.type) }}
                      </span>
                      <button
                        v-if="canManage"
                        type="button"
                        class="kit-bundle__drop"
                        :disabled="Boolean(packingId)"
                        aria-label="Bỏ khỏi khung"
                        @click="unpackItem(item)"
                      >
                        <AppIcon name="close" :size="12" />
                      </button>
                    </li>
                  </ul>
                </div>
              </section>

              <p v-if="!canManage" class="kit-side__note">Chỉ xem được — cần quyền quản lý tiêu chí để thay đổi.</p>
            </div>
          </aside>
        </div>
      </template>
    </div>
  </section>
</template>

<style scoped>
.kit-wrap {
  height: 100%;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.kit-header-btn {
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

.kit-header-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.kit-header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.kit-header-btn__spin {
  animation: kit-spin 0.8s linear infinite;
}

@keyframes kit-spin {
  to {
    transform: rotate(360deg);
  }
}

.kit-page {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  padding: var(--space-4);
}

.kit-empty {
  margin: 0;
  padding: var(--space-5);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text-muted);
  font-size: 0.875rem;
  line-height: 1.5;
  box-shadow: var(--shadow-sm);
}

.kit-empty--plain {
  box-shadow: none;
  background: transparent;
  padding: var(--space-3) 0;
}

.kit-link {
  color: var(--color-primary);
  font-weight: 600;
  text-decoration: none;
}

.kit-link:hover {
  text-decoration: underline;
}

.kit-body {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-3);
  overflow: hidden;
}

.kit-main {
  flex: 1;
  min-width: 0;
  overflow: auto;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.kit-modes {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
}

.kit-mode {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  min-width: 0;
  padding: var(--space-4);
  padding-left: calc(var(--space-2) + 3px + var(--space-3));
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  text-align: left;
  box-shadow: var(--shadow-sm);
  cursor: pointer;
}

.kit-mode::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.kit-mode--on::before {
  background: var(--color-primary);
}

.kit-mode:hover:not(:disabled):not(.kit-mode--on) {
  background: var(--color-surface-muted);
}

.kit-mode:disabled {
  cursor: default;
}

.kit-mode__icon {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.kit-mode--on .kit-mode__icon {
  background: color-mix(in srgb, var(--color-primary) 12%, var(--color-surface));
  color: var(--color-primary);
}

.kit-mode__copy {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.kit-mode__title {
  font-size: 0.9375rem;
  font-weight: 650;
}

.kit-mode__lead {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.45;
}

.kit-mode__mark {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.25rem;
  height: 1.25rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-on-primary);
}

.kit-mode--on .kit-mode__mark {
  background: var(--color-primary);
}

.kit-card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4);
  padding-left: calc(var(--space-2) + 3px + var(--space-3));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.kit-card::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-primary);
}

.kit-card--switch {
  flex-direction: row;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-4);
}

.kit-card--switch::before {
  background: var(--color-secondary);
}

.kit-card__title {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 650;
}

.kit-card__lead,
.kit-card__empty {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.45;
}

.kit-switch-copy {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.25rem;
}

.kit-fields {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-3);
}

.kit-field {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: var(--space-1);
}

.kit-field__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.kit-field__input {
  width: 100%;
  height: 2.5rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-variant-numeric: tabular-nums;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.kit-field__input:focus {
  outline: none;
  box-shadow: inset 0 0 0 1.5px var(--color-primary);
}

.kit-field__input:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.kit-ranks {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
}

.kit-rank {
  display: grid;
  grid-template-columns: 4.5rem 1fr auto;
  gap: var(--space-2);
  align-items: start;
  padding: 0.45rem 0;
  box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 55%, transparent);
}

.kit-rank:last-child {
  box-shadow: none;
}

.kit-rank__code,
.kit-rank__score {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-variant-numeric: tabular-nums;
  font-weight: 650;
}

.kit-rank__copy {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.1rem;
}

.kit-rank__label {
  font-size: 0.8125rem;
  font-weight: 600;
}

.kit-rank__note {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-style: italic;
  line-height: 1.4;
}

.kit-switch {
  position: relative;
  flex-shrink: 0;
  width: 2.75rem;
  height: 1.5rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-text-muted) 35%, var(--color-surface-muted));
  cursor: pointer;
}

.kit-switch--on {
  background: var(--color-primary);
}

.kit-switch:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.kit-switch__thumb {
  position: absolute;
  top: 0.125rem;
  left: 0.125rem;
  width: 1.25rem;
  height: 1.25rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  transition: transform 0.15s ease;
}

.kit-switch--on .kit-switch__thumb {
  transform: translateX(1.25rem);
}

.kit-pack-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding-top: var(--space-1);
}

.kit-pack-head__title {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 650;
}

.kit-search {
  display: inline-flex;
  flex: 1 1 14rem;
  max-width: 22rem;
  align-items: center;
  gap: var(--space-2);
  height: 2.25rem;
  padding: 0 0.75rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.kit-search__input {
  flex: 1;
  min-width: 0;
  height: 100%;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  outline: none;
}

.kit-group {
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.kit-group__bar {
  display: flex;
  align-items: stretch;
}

.kit-group__toggle {
  position: relative;
  display: flex;
  flex: 1;
  min-width: 0;
  align-items: center;
  gap: 0.625rem;
  padding: 0.65rem var(--space-4);
  padding-left: calc(var(--space-4) + var(--space-2) + 3px);
  border: none;
  background: color-mix(in srgb, var(--color-text) 4%, var(--color-surface));
  color: var(--color-text);
  font-family: var(--font-family-base);
  text-align: left;
  cursor: pointer;
}

.kit-group__toggle::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-4);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.kit-group--scale .kit-group__toggle::before,
.kit-group--mixed .kit-group__toggle::before {
  background: var(--color-primary);
}

.kit-group--bonus .kit-group__toggle::before {
  background: var(--color-success);
}

.kit-group--penalty .kit-group__toggle::before {
  background: var(--color-danger);
}

.kit-group--behavior .kit-group__toggle::before {
  background: var(--color-warning);
}

.kit-group__toggle:hover {
  background: var(--color-surface-muted);
}

.kit-group__title {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  font-size: 0.875rem;
  font-weight: 650;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.kit-group__count {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 500;
}

.kit-group__pack {
  flex-shrink: 0;
  padding: 0 var(--space-4);
  border: none;
  background: transparent;
  color: var(--color-primary);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 650;
  box-shadow: -1px 0 0 color-mix(in srgb, var(--color-border) 80%, transparent);
  cursor: pointer;
}

.kit-group__pack:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.kit-group__pack:disabled {
  opacity: 0.55;
  cursor: wait;
}

.kit-group__body {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4);
}

.kit-crit {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.kit-crit--on {
  background: color-mix(in srgb, var(--color-secondary) 8%, var(--color-surface-muted));
}

.kit-crit--off {
  opacity: 0.72;
}

.kit-crit__head {
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
}

.kit-crit__identity {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.kit-crit__name-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
}

.kit-crit__name {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 650;
}

.kit-crit__desc {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-style: italic;
  line-height: 1.45;
}

.kit-tag {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 500;
}

.kit-tag--muted {
  font-style: italic;
}

.kit-check {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.125rem;
  height: 1.125rem;
  margin-top: 0.2rem;
  border: none;
  border-radius: 4px;
  background: var(--color-surface);
  color: var(--color-on-primary);
  box-shadow: inset 0 0 0 1.5px var(--color-border-strong, var(--color-border));
  cursor: pointer;
}

.kit-check--sm {
  width: 1rem;
  height: 1rem;
  margin-top: 0.1rem;
}

.kit-check--on {
  background: var(--color-primary);
  box-shadow: none;
}

.kit-check--some {
  background: color-mix(in srgb, var(--color-primary) 18%, var(--color-surface));
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1.5px var(--color-primary);
}

.kit-check:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.kit-levels {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
}

.kit-level {
  display: grid;
  grid-template-columns: auto minmax(3.25rem, 4.5rem) minmax(0, 1fr) auto;
  gap: var(--space-2);
  align-items: start;
  padding: 0.45rem 0;
  box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 55%, transparent);
}

.kit-level:last-child {
  box-shadow: none;
}

.kit-level--on {
  background: color-mix(in srgb, var(--color-secondary) 6%, transparent);
}

.kit-level__code {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-variant-numeric: tabular-nums;
  font-weight: 650;
}

.kit-level__copy {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.1rem;
}

.kit-level__label {
  font-size: 0.8125rem;
  font-weight: 600;
}

.kit-level__note {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-style: italic;
  line-height: 1.4;
}

.kit-score {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  height: 1.375rem;
  padding: 0 0.45rem;
  border-radius: var(--radius-sm);
  font-size: 0.75rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.kit-score--pos {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
}

.kit-score--neg {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}

.kit-score--zero {
  background: var(--color-surface);
  color: var(--color-text-muted);
}

.kit-side {
  flex: 0 0 28rem;
  width: 28rem;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.kit-side__head {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 75%, transparent);
}

.kit-side__title {
  font-size: 0.875rem;
  font-weight: 650;
}

.kit-side__clear {
  border: none;
  background: transparent;
  color: var(--color-primary);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 650;
  cursor: pointer;
}

.kit-side__clear:disabled {
  opacity: 0.55;
  cursor: wait;
}

.kit-side__body {
  flex: 1;
  min-height: 0;
  overflow: auto;
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  padding: var(--space-4);
}

.kit-side__empty,
.kit-side__note {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.45;
}

.kit-formula {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0;
}

.kit-formula__step {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.35rem;
  padding: 0.5rem 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  line-height: 1.45;
  box-shadow: 0 1px 0 var(--color-border);
}

.kit-formula__step:last-child {
  box-shadow: none;
}

.kit-formula__step strong {
  font-style: italic;
  font-weight: 500;
}

.kit-picked__title,
.kit-bundle__title {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.kit-picked {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.kit-bundle {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.kit-bundle__list {
  margin: 0;
  padding: 0;
  list-style: none;
}

.kit-bundle__item {
  display: grid;
  grid-template-columns: minmax(2.5rem, auto) minmax(0, 1fr) auto auto;
  gap: var(--space-2);
  align-items: center;
  padding: 0.4rem 0;
  box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 55%, transparent);
}

.kit-bundle__item:last-child {
  box-shadow: none;
}

.kit-bundle__code {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 650;
  font-variant-numeric: tabular-nums;
}

.kit-bundle__label {
  min-width: 0;
  overflow: hidden;
  font-size: 0.8125rem;
  font-style: italic;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.kit-bundle__drop {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.25rem;
  height: 1.25rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.kit-bundle__drop:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

@media (max-width: 1100px) {
  .kit-body {
    flex-direction: column;
  }

  .kit-side {
    flex: 0 0 auto;
    width: 100%;
    max-height: 42%;
  }
}

@media (max-width: 860px) {
  .kit-modes,
  .kit-fields {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 720px) {
  .kit-level,
  .kit-rank {
    grid-template-columns: auto minmax(0, 1fr) auto;
  }

  .kit-level__code,
  .kit-rank__code {
    display: none;
  }
}

@media (max-width: 768px) {
  .kit-page {
    padding: var(--space-3);
  }
}

@media (prefers-reduced-motion: reduce) {
  .kit-header-btn__spin,
  .kit-switch__thumb {
    animation: none;
    transition: none;
  }
}
</style>
