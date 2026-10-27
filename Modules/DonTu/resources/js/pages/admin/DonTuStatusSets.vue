<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useDonTuConfigHub } from '../../composables/useDonTuConfigHub.js';
import AppIcon from '@/components/AppIcon.vue';
import { useDragScroll } from '@/composables/useDragScroll.js';
import { showClientToast } from '@/lib/clientToast';
import { formatDonTuApiError } from '../../lib/donTuToast.js';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import DonTuStatusBadge from '../../components/DonTuStatusBadge.vue';
import { createStatus, createStatusSet, fetchStatusSet, fetchStatusSets } from '../../api/donTuConfigApi.js';
import {
  ACTION_LABEL,
  ACTOR_LABEL,
  GROUP_KIND_LABEL,
  friendlySetCodeLabel,
  friendlySetName,
  guardHint,
  humanSetDescription,
  statusStepLine,
} from '../../constants/statusTone.js';

const auth = useAuthStore();
const configHub = useDonTuConfigHub();

const canConfig = computed(
  () =>
    auth.can('don_tu.manage_department') ||
    auth.can('don_tu.manage_system') ||
    auth.can('don_tu.*'),
);

const canWrite = computed(
  () => auth.can('don_tu.manage_department') || auth.can('don_tu.manage_system') || auth.can('don_tu.*'),
);

const sets = ref([]);
const setQuery = ref('');
const selectedId = ref(null);
const detail = ref(null);
const loadingList = ref(false);
const loadingDetail = ref(false);
const transitionQuery = ref('');
const focusCode = ref('');
const createOpen = ref(false);
const createSaving = ref(false);
const createForm = ref(emptyStatusForm());
const createSetOpen = ref(false);
const createSetSaving = ref(false);
const createSetForm = ref(emptySetForm());
const codeUnlocked = ref(false);
const codeInput = ref(null);
const templatePreview = ref(null);
const templatePreviewLoading = ref(false);
const previewCache = new Map();
let previewToken = 0;

const pipelineWrap = ref(null);
const transWrap = ref(null);
useDragScroll(pipelineWrap, { axis: 'x' });
useDragScroll(transWrap, { axis: 'x' });

const filteredSets = computed(() => {
  const q = setQuery.value.trim().toLowerCase();
  if (!q) return sets.value;
  return sets.value.filter(
    (s) =>
      friendlySetName(s.name, s.code).toLowerCase().includes(q) ||
      (s.name || '').toLowerCase().includes(q) ||
      (s.code || '').toLowerCase().includes(q),
  );
});

const showRail = computed(() => sets.value.length > 0);
const railCollapsed = ref(false);
const RAIL_COLLAPSE_KEY = 'don-tu.status-rail-collapsed';
const viewTab = ref('flow');
const stepDrawerOpen = ref(false);

const mainFlow = computed(() =>
  (detail.value?.statuses ?? []).filter((s) => s.group_kind === 'main').sort((a, b) => a.sort_order - b.sort_order),
);

const ZZ_STEP = 148;
const ZZ_H = 292;

const waveWidth = computed(() => Math.max(mainFlow.value.length, 1) * ZZ_STEP);

const waveD = computed(() => {
  const n = mainFlow.value.length;
  if (n < 2) return '';
  const yUp = 102;
  const yDown = 190;
  let d = '';
  for (let i = 0; i < n - 1; i += 1) {
    const x1 = i * ZZ_STEP + ZZ_STEP / 2;
    const x2 = (i + 1) * ZZ_STEP + ZZ_STEP / 2;
    const y1 = i % 2 === 0 ? yUp : yDown;
    const y2 = i % 2 === 0 ? yDown : yUp;
    const mid = (x1 + x2) / 2;
    d += i === 0 ? `M ${x1} ${y1}` : '';
    d += ` C ${mid} ${y1}, ${mid} ${y2}, ${x2} ${y2}`;
  }
  return d;
});
const conditional = computed(() =>
  (detail.value?.statuses ?? [])
    .filter((s) => s.group_kind === 'conditional')
    .sort((a, b) => a.sort_order - b.sort_order),
);
const terminal = computed(() =>
  (detail.value?.statuses ?? [])
    .filter((s) => s.group_kind === 'terminal')
    .sort((a, b) => a.sort_order - b.sort_order),
);

const FORWARD_ACTIONS = new Set([
  'SAVE_DRAFT',
  'SUBMIT',
  'ACCEPT',
  'APPROVE',
  'CLOSE',
  'SUPPLEMENT',
  'CONFIRM_PAYMENT',
]);
const STOP_ACTIONS = new Set(['REJECT', 'CANCEL', 'REQUEST_CHANGES']);

const trackStages = computed(() => [
  ...mainFlow.value,
  ...terminal.value.filter((status) => status.terminal_kind !== 'failed'),
]);

const actorTracks = computed(() => {
  const q = transitionQuery.value.trim().toLowerCase();
  const transitions = detail.value?.transitions ?? [];
  const stages = trackStages.value;
  const order = ['requester', 'assignee', 'closure_actor', 'admin'];
  const actors = order.filter((key) => transitions.some((tr) => tr.actor_policy === key));
  for (const tr of transitions) {
    if (tr.actor_policy && !actors.includes(tr.actor_policy)) actors.push(tr.actor_policy);
  }
  return actors
    .map((actor) => {
      const mine = transitions.filter((tr) => tr.actor_policy === actor);
      const nodes = stages.map((stage) => {
        const here = mine.filter(
          (tr) => tr.from_code === stage.code || (tr.from_code === 'NONE' && tr.to_code === stage.code),
        );
        const keys = here.map((tr) => tr.action_key);
        let tone = 'off';
        if (keys.some((key) => FORWARD_ACTIONS.has(key))) tone = 'go';
        else if (keys.some((key) => STOP_ACTIONS.has(key))) tone = 'hold';
        else if (keys.length) tone = 'go';
        return {
          code: stage.code,
          label: stage.label,
          tone,
          actions: [...new Set(here.map((tr) => actionLabel(tr.action_key)))],
        };
      });
      return {
        actor,
        label: actorLabel(actor),
        touch: nodes.filter((node) => node.tone !== 'off').length,
        nodes,
      };
    })
    .filter((row) => {
      if (!q) return true;
      return [row.label, ...row.nodes.flatMap((node) => [node.label, ...node.actions])]
        .join(' ')
        .toLowerCase()
        .includes(q);
    });
});
const detailDescription = computed(() => humanSetDescription(detail.value?.description));
const fromOptions = computed(() => [
  { code: 'NONE', label: 'Lúc chưa có hồ sơ' },
  ...(detail.value?.statuses ?? []).map((st) => ({ code: st.code, label: st.label })),
]);

async function loadSets() {
  loadingList.value = true;
  try {
    sets.value = await fetchStatusSets();
    if (!selectedId.value && sets.value.length) {
      selectedId.value = sets.value[0].id;
    }
    if (selectedId.value && !sets.value.some((s) => s.id === selectedId.value)) {
      selectedId.value = sets.value[0]?.id ?? null;
    }
  } catch (err) {
    showClientToast('error', err.response?.data?.message || 'Không tải được danh sách trạng thái.');
  } finally {
    loadingList.value = false;
  }
}

async function loadDetail(id) {
  if (!id) {
    detail.value = null;
    return;
  }
  loadingDetail.value = true;
  transitionQuery.value = '';
  focusCode.value = '';
  stepDrawerOpen.value = false;
  try {
    detail.value = await fetchStatusSet(id);
  } catch (err) {
    showClientToast('error', err.response?.data?.message || 'Không tải được chi tiết trạng thái.');
    detail.value = null;
  } finally {
    loadingDetail.value = false;
  }
}

watch(selectedId, (id) => loadDetail(id));

function syncHeaderPrimary() {
  if (!configHub || !canWrite.value) {
    configHub?.clearPrimaryAction();
    return;
  }
  configHub.setPrimaryAction({
    label: 'Thêm',
    icon: 'plus',
    items: [
      { label: 'Tạo bộ trạng thái', onSelect: () => openCreateSet() },
      {
        label: 'Thêm trạng thái',
        description: !detail.value ? 'Chọn hoặc tạo nhóm trước' : undefined,
        onSelect: () => {
          if (!detail.value || loadingDetail.value) {
            showClientToast('error', 'Chọn bộ trạng thái trước khi thêm trạng thái.');
            return;
          }
          openCreate();
        },
      },
    ],
  });
}

watch([canWrite, detail, loadingDetail], syncHeaderPrimary, { immediate: true });

onMounted(async () => {
  try {
    railCollapsed.value = localStorage.getItem(RAIL_COLLAPSE_KEY) === '1';
  } catch {
    railCollapsed.value = false;
  }
  configHub?.registerReload(reloadPage);
  if (!canConfig.value) {
    return;
  }
  await loadSets();
});

onBeforeUnmount(() => {
  configHub?.unregisterReload();
  configHub?.clearPrimaryAction();
});

function toggleRail() {
  railCollapsed.value = !railCollapsed.value;
  try {
    localStorage.setItem(RAIL_COLLAPSE_KEY, railCollapsed.value ? '1' : '0');
  } catch {
    /* giữ trạng thái trong phiên */
  }
}

async function reloadPage() {
  await loadSets();
  if (selectedId.value) {
    await loadDetail(selectedId.value);
  }
}

function actionLabel(key) {
  return ACTION_LABEL[key] || key;
}

function actorLabel(key) {
  return ACTOR_LABEL[key] || key;
}

function statusLabel(code) {
  if (!code || code === 'NONE') return 'Lúc chưa có hồ sơ';
  return detail.value?.statuses?.find((s) => s.code === code)?.label || code;
}

const focusStatus = computed(
  () => detail.value?.statuses?.find((status) => status.code === focusCode.value) ?? null,
);

const focusInbound = computed(() =>
  (detail.value?.transitions ?? []).filter((tr) => tr.to_code === focusCode.value),
);

const focusOutbound = computed(() =>
  (detail.value?.transitions ?? []).filter((tr) => tr.from_code === focusCode.value),
);

const linkedCodes = computed(() => {
  if (!focusCode.value) return null;
  const codes = new Set([focusCode.value]);
  for (const tr of detail.value?.transitions ?? []) {
    if (tr.from_code === focusCode.value && tr.to_code) codes.add(tr.to_code);
    if (tr.to_code === focusCode.value && tr.from_code && tr.from_code !== 'NONE') codes.add(tr.from_code);
  }
  return codes;
});

function stepClass(code) {
  if (!linkedCodes.value) return {};
  return {
    'is-on': code === focusCode.value,
    'is-link': code !== focusCode.value && linkedCodes.value.has(code),
    'is-dim': !linkedCodes.value.has(code),
  };
}

function toggleFocus(code) {
  if (focusCode.value === code && stepDrawerOpen.value) {
    closeStepDrawer();
    return;
  }
  focusCode.value = code;
  stepDrawerOpen.value = true;
}

function closeStepDrawer() {
  stepDrawerOpen.value = false;
  focusCode.value = '';
}

function openRules() {
  viewTab.value = 'rules';
  stepDrawerOpen.value = false;
}

function scrollFlow(direction) {
  pipelineWrap.value?.scrollBy({ left: direction * ZZ_STEP * 2, behavior: 'smooth' });
}

const orderedSteps = computed(() => [...mainFlow.value, ...conditional.value, ...terminal.value]);

function moveFocus(delta) {
  const list = orderedSteps.value;
  if (!list.length) return;
  const index = list.findIndex((status) => status.code === focusCode.value);
  const nextIndex = index < 0 ? (delta > 0 ? 0 : list.length - 1) : index + delta;
  const next = list[(nextIndex + list.length) % list.length];
  focusCode.value = next.code;
}

async function copyCode(code) {
  try {
    await navigator.clipboard.writeText(code);
    showClientToast('success', 'Đã sao chép mã.');
  } catch {
    showClientToast('error', 'Không sao chép được.');
  }
}

function nextBmttCode(list) {
  let max = 0;
  for (const set of list || []) {
    const match = /^BMTT(\d+)$/i.exec(String(set.code || '').trim());
    if (match) max = Math.max(max, Number.parseInt(match[1], 10));
  }
  return `BMTT${String(max + 1).padStart(2, '0')}`;
}

function emptySetForm() {
  return {
    name: '',
    code: '',
    description: '',
    use_template: false,
    copy_from_id: '',
  };
}

function emptyStatusForm() {
  return {
    label: '',
    group_kind: 'main',
    terminal_kind: 'success',
    from_code: '',
    action_key: 'APPROVE',
    actor_policy: 'assignee',
  };
}

function openCreate() {
  createForm.value = emptyStatusForm();
  createOpen.value = true;
}

const previewGroups = computed(() => {
  const statuses = templatePreview.value?.statuses ?? [];
  return ['main', 'conditional', 'terminal']
    .map((kind) => ({
      kind,
      label: GROUP_KIND_LABEL[kind],
      items: statuses
        .filter((status) => status.group_kind === kind)
        .sort((a, b) => a.sort_order - b.sort_order),
    }))
    .filter((group) => group.items.length);
});

const previewDescription = computed(() => humanSetDescription(templatePreview.value?.description));

const previewStats = computed(() => {
  if (!templatePreview.value) return null;
  return {
    statuses: templatePreview.value.statuses?.length ?? 0,
    transitions: templatePreview.value.transitions?.length ?? 0,
  };
});

const createSetFootSummary = computed(() => {
  const name = createSetForm.value.name.trim() || '—';
  const code = createSetForm.value.code.trim().toUpperCase() || '—';
  if (createSetForm.value.use_template) {
    const picked = sets.value.find((s) => String(s.id) === String(createSetForm.value.copy_from_id));
    if (!picked) {
      return `Bộ «${name}» · mã ${code} · chọn mẫu để sao chép trạng thái và quy tắc.`;
    }
    if (previewStats.value) {
      return `Bộ «${name}» · mã ${code} · ${previewStats.value.statuses} trạng thái, ${previewStats.value.transitions} quy tắc từ «${friendlySetName(picked.name, picked.code)}».`;
    }
    return `Bộ «${name}» · mã ${code} · sao chép từ «${friendlySetName(picked.name, picked.code)}».`;
  }
  return `Bộ «${name}» · mã ${code} · bắt đầu trống, thêm trạng thái sau khi tạo.`;
});

function setCreateInitMode(useTemplate) {
  createSetForm.value.use_template = useTemplate;
  if (useTemplate && createSetForm.value.copy_from_id === '' && sets.value.length) {
    const preferred = sets.value.find((s) => s.is_system) || sets.value[0];
    createSetForm.value.copy_from_id = String(preferred.id);
  }
}

function previewTone(status) {
  if (status.group_kind === 'terminal') {
    return status.terminal_kind === 'failed' ? 'danger' : 'success';
  }
  if (status.group_kind === 'conditional') return 'info';
  return 'primary';
}

function openCreateSet() {
  createSetForm.value = {
    ...emptySetForm(),
    code: nextBmttCode(sets.value),
  };
  codeUnlocked.value = false;
  templatePreview.value = null;
  templatePreviewLoading.value = false;
  createSetOpen.value = true;
}

async function toggleCodeLock() {
  if (!codeUnlocked.value) {
    codeUnlocked.value = true;
    await nextTick();
    codeInput.value?.focus();
    codeInput.value?.select();
    return;
  }
  const code = createSetForm.value.code.trim().toUpperCase();
  createSetForm.value.code = code || nextBmttCode(sets.value);
  codeUnlocked.value = false;
}

function onCodeInput(event) {
  createSetForm.value.code = event.target.value.toUpperCase();
}

async function loadTemplatePreview() {
  const token = ++previewToken;
  const useTemplate = createSetForm.value.use_template;
  const rawId = createSetForm.value.copy_from_id;
  if (!createSetOpen.value || !useTemplate || rawId === '' || rawId == null) {
    templatePreview.value = null;
    templatePreviewLoading.value = false;
    return;
  }
  const id = Number(rawId);
  if (previewCache.has(id)) {
    templatePreview.value = previewCache.get(id);
    templatePreviewLoading.value = false;
    return;
  }
  templatePreviewLoading.value = true;
  templatePreview.value = null;
  try {
    const item = await fetchStatusSet(id);
    previewCache.set(id, item);
    if (token === previewToken) templatePreview.value = item;
  } catch (err) {
    if (token === previewToken) {
      templatePreview.value = null;
      showClientToast('error', err.response?.data?.message || 'Không tải được mẫu trạng thái.');
    }
  } finally {
    if (token === previewToken) templatePreviewLoading.value = false;
  }
}

watch(
  () => [createSetOpen.value, createSetForm.value.use_template, createSetForm.value.copy_from_id],
  () => {
    if (!createSetForm.value.use_template && createSetForm.value.copy_from_id !== '') {
      createSetForm.value.copy_from_id = '';
      return;
    }
    loadTemplatePreview();
  },
);

async function submitCreateSet() {
  if (!createSetForm.value.name.trim()) {
    showClientToast('error', 'Nhập tên nhóm trạng thái.');
    return;
  }
  const code = createSetForm.value.code.trim().toUpperCase();
  if (!/^[A-Z][A-Z0-9_]{1,63}$/.test(code)) {
    showClientToast('error', 'Mã bộ trạng thái chỉ gồm chữ, số và dấu gạch dưới.');
    return;
  }
  if (sets.value.some((set) => String(set.code || '').toUpperCase() === code)) {
    showClientToast('error', 'Mã bộ trạng thái đã được dùng.');
    return;
  }
  if (createSetForm.value.use_template && (createSetForm.value.copy_from_id === '' || createSetForm.value.copy_from_id == null)) {
    showClientToast('error', 'Chọn mẫu có sẵn.');
    return;
  }
  createSetSaving.value = true;
  try {
    const payload = {
      name: createSetForm.value.name.trim(),
      code,
      description: createSetForm.value.description.trim() || null,
    };
    if (createSetForm.value.use_template) {
      payload.copy_from_id = Number(createSetForm.value.copy_from_id);
    }
    const item = await createStatusSet(payload);
    createSetOpen.value = false;
    await loadSets();
    selectedId.value = item.id;
    detail.value = item;
    showClientToast('success', 'Đã tạo nhóm trạng thái.');
  } catch (err) {
    showClientToast('error', formatDonTuApiError(err, 'Không tạo được nhóm trạng thái.'));
  } finally {
    createSetSaving.value = false;
  }
}

async function submitCreate() {
  if (!selectedId.value) return;
  if (!createForm.value.label.trim()) {
    showClientToast('error', 'Nhập tên trạng thái.');
    return;
  }
  if (!createForm.value.from_code) {
    showClientToast('error', 'Chọn trạng thái phía trước.');
    return;
  }
  createSaving.value = true;
  try {
    const item = await createStatus(selectedId.value, {
      ...createForm.value,
      label: createForm.value.label.trim(),
      terminal_kind: createForm.value.group_kind === 'terminal' ? createForm.value.terminal_kind : null,
    });
    detail.value = item;
    const row = sets.value.find((s) => s.id === selectedId.value);
    if (row) row.status_count = item.statuses?.length ?? row.status_count;
    createOpen.value = false;
    showClientToast('success', 'Đã thêm trạng thái.');
  } catch (err) {
    showClientToast('error', formatDonTuApiError(err, 'Không thêm được trạng thái.'));
  } finally {
    createSaving.value = false;
  }
}
</script>

<template>
  <div v-if="!canConfig" class="dt-config-deny">
    <AppIcon name="lock" :size="28" :stroke-width="1.75" />
    <p>Bạn cần quyền cấu hình đơn từ của phòng để xem trang này.</p>
  </div>

  <div v-else class="dt-setup">
    <div v-if="!loadingList && !sets.length" class="dt-status-page__empty-panel">
      <AppIcon name="layers" :size="32" :stroke-width="1.75" />
      <h2 class="dt-status-page__empty-title">Chưa có bộ trạng thái</h2>
      <p class="dt-status-page__empty-lead">
        Bộ trạng thái quy định hồ sơ đơn đi qua những bước nào — tạo bộ đầu tiên để khai báo trạng thái.
      </p>
      <button v-if="canWrite" type="button" class="dt-btn-primary" @click="openCreateSet">Tạo bộ trạng thái</button>
    </div>

    <div
      v-else
      class="dt-setup__layout"
      :class="{
        'dt-setup__layout--solo': !showRail,
        'dt-setup__layout--rail-collapsed': showRail && railCollapsed,
      }"
    >
      <aside v-if="showRail" class="dt-set-rail" :class="{ 'dt-set-rail--collapsed': railCollapsed }">
        <div class="dt-set-rail__top">
          <h3 v-show="!railCollapsed" class="dt-set-rail__title">Bộ trạng thái</h3>
          <button v-if="canWrite && !railCollapsed" type="button" class="dt-link-btn dt-set-rail__create" @click="openCreateSet">
            Tạo mới
          </button>
          <button
            type="button"
            class="dt-set-rail__toggle"
            :aria-expanded="!railCollapsed"
            :aria-label="railCollapsed ? 'Mở danh sách bộ trạng thái' : 'Thu gọn danh sách bộ trạng thái'"
            @click="toggleRail"
          >
            <AppIcon :name="railCollapsed ? 'chevronRight' : 'chevronLeft'" :size="16" :stroke-width="2" />
          </button>
        </div>

        <template v-if="!railCollapsed">
        <label class="dt-set-rail__search">
          <AppIcon name="search" :size="16" :stroke-width="1.75" aria-hidden="true" />
          <input
            v-model="setQuery"
            type="search"
            placeholder="Tìm…"
            aria-label="Tìm bộ trạng thái"
          />
        </label>

        <ul v-if="!loadingList" class="dt-set-rail__list hide-scrollbar">
          <li v-if="!filteredSets.length" class="dt-set-rail__empty">
            <p>Không có kết quả.</p>
          </li>
          <li v-for="set in filteredSets" :key="set.id">
            <button
              type="button"
              class="dt-set-pick"
              :class="{ 'dt-set-pick--active': selectedId === set.id }"
              :aria-current="selectedId === set.id ? 'true' : undefined"
              @click="selectedId = set.id"
            >
              <span class="dt-set-pick__main">
                <span class="dt-set-pick__name">{{ friendlySetName(set.name, set.code) }}</span>
                <span v-if="friendlySetCodeLabel(set.code)" class="dt-set-pick__sub">
                  {{ friendlySetCodeLabel(set.code) }}
                </span>
              </span>
              <span class="dt-set-pick__count">{{ set.status_count }} bước</span>
            </button>
          </li>
        </ul>
        <p v-else class="dt-set-rail__loading">Đang tải…</p>
        </template>
      </aside>

      <div v-if="loadingDetail" class="dt-status-page__loading dt-setup__main">
        Đang tải…
      </div>

      <div v-else-if="detail" class="dt-setup__main">
        <section class="dt-setup__group">
          <header class="dt-setup__bar">
            <h2 class="dt-setup__hero-title">{{ detailTitle }}</h2>
            <p v-if="detailDescription" class="dt-setup__hero-desc">{{ detailDescription }}</p>
            <button
              v-if="detail.code"
              type="button"
              class="dt-link-btn dt-setup__bar-copy"
              @click="copyCode(detail.code)"
            >
              Sao chép mã nội bộ
            </button>
            <div class="dt-setup__metrics" aria-label="Thống kê bộ trạng thái">
              <span class="dt-setup__metric">
                <span class="dt-setup__metric-num">{{ detail.statuses?.length ?? 0 }}</span>
                bước
              </span>
              <span class="dt-setup__metric">
                <span class="dt-setup__metric-num">{{ detail.transitions?.length ?? 0 }}</span>
                lần chuyển
              </span>
            </div>
            <div class="dt-setup__seg dt-setup__seg--view" role="tablist" aria-label="Xem thiết lập">
              <button
                type="button"
                role="tab"
                class="dt-setup__seg-btn"
                :class="{ 'dt-setup__seg-btn--on': viewTab === 'flow' }"
                :aria-selected="viewTab === 'flow'"
                @click="viewTab = 'flow'"
              >
                <AppIcon name="gitBranch" :size="15" :stroke-width="1.75" aria-hidden="true" />
                Sơ đồ các bước
              </button>
              <button
                type="button"
                role="tab"
                class="dt-setup__seg-btn"
                :class="{ 'dt-setup__seg-btn--on': viewTab === 'rules' }"
                :aria-selected="viewTab === 'rules'"
                @click="viewTab = 'rules'"
              >
                <AppIcon name="users" :size="15" :stroke-width="1.75" aria-hidden="true" />
                Người phụ trách
              </button>
            </div>
          </header>

          <div v-show="viewTab === 'flow'" class="dt-setup__group-body">
          <p v-if="!(detail.statuses?.length)" class="dt-status-page__empty">Chưa có trạng thái.</p>
          <div v-else class="dt-flow-board" @keydown.left.prevent="moveFocus(-1)" @keydown.right.prevent="moveFocus(1)">
            <div v-if="mainFlow.length" class="dt-zz-wrap">
              <button
                v-if="mainFlow.length > 4"
                type="button"
                class="dt-zz-nav dt-zz-nav--prev"
                aria-label="Xem bước phía trước"
                @click="scrollFlow(-1)"
              >
                <AppIcon name="chevronLeft" :size="18" :stroke-width="2" />
              </button>
              <div ref="pipelineWrap" class="dt-zz hide-scrollbar">
              <ol class="dt-zz__track" :style="{ width: `${waveWidth}px`, height: `${ZZ_H}px` }">
                <svg
                  v-if="waveD"
                  class="dt-zz__wave"
                  :width="waveWidth"
                  :height="ZZ_H"
                  :viewBox="`0 0 ${waveWidth} ${ZZ_H}`"
                  aria-hidden="true"
                >
                  <defs>
                    <linearGradient :id="`dt-zz-stroke-${detail.id}`" x1="0" y1="0" x2="1" y2="0">
                      <stop offset="0%" stop-color="var(--color-primary)" />
                      <stop offset="100%" stop-color="var(--color-secondary)" />
                    </linearGradient>
                  </defs>
                  <path :d="waveD" class="dt-zz__wave-soft" />
                  <path :d="waveD" class="dt-zz__wave-line" :stroke="`url(#dt-zz-stroke-${detail.id})`" />
                </svg>
                <li
                  v-for="(st, idx) in mainFlow"
                  :key="st.code"
                  class="dt-zz__step"
                  :class="[idx % 2 === 0 ? 'is-up' : 'is-down', stepClass(st.code)]"
                >
                  <button
                    type="button"
                    class="dt-zz__hit"
                    :aria-pressed="focusCode === st.code"
                    @click="toggleFocus(st.code)"
                  >
                    <span class="dt-zz__copy">
                      <span class="dt-zz__title">{{ st.label }}</span>
                      <span v-if="statusStepLine(st.code)" class="dt-zz__sub">{{ statusStepLine(st.code) }}</span>
                    </span>
                    <span class="dt-zz__node">
                      {{ String(idx + 1).padStart(2, '0') }}
                    </span>
                  </button>
                </li>
              </ol>
              </div>
              <button
                v-if="mainFlow.length > 4"
                type="button"
                class="dt-zz-nav dt-zz-nav--next"
                aria-label="Xem bước phía sau"
                @click="scrollFlow(1)"
              >
                <AppIcon name="chevronRight" :size="18" :stroke-width="2" />
              </button>
            </div>

          </div>
          </div>

          <div v-show="viewTab === 'rules'" class="dt-setup__group-body dt-track-wrap">
            <header class="dt-track__head">
              <div class="dt-track__head-copy">
                <h3 class="dt-track__title">Người phụ trách từng bước</h3>
                <ul class="dt-track__legend">
                  <li><i class="is-go" /> Được đi tiếp</li>
                  <li><i class="is-hold" /> Được dừng</li>
                  <li><i class="is-off" /> Không việc</li>
                </ul>
              </div>
              <div class="dt-track__tools">
                <button v-if="focusCode" type="button" class="dt-link-btn" @click="focusCode = ''">
                  Xem mọi bước
                </button>
                <label class="dt-track__search">
                  <AppIcon name="search" :size="16" :stroke-width="1.75" aria-hidden="true" />
                  <input
                    v-model="transitionQuery"
                    type="search"
                    placeholder="Tìm vai hoặc bước"
                    aria-label="Tìm vai hoặc bước"
                  />
                </label>
              </div>
            </header>
            <div ref="transWrap" class="dt-track hide-scrollbar">
              <p v-if="!actorTracks.length" class="dt-status-page__empty">Không có vai nào khớp.</p>
              <article v-for="row in actorTracks" :key="row.actor" class="dt-track__row">
                <div class="dt-track__who">
                  <span class="dt-track__avatar" aria-hidden="true">
                    <AppIcon name="user" :size="18" :stroke-width="1.75" />
                  </span>
                  <span class="dt-track__who-copy">
                    <span class="dt-track__name">{{ row.label }}</span>
                    <span class="dt-track__meta">Làm ở {{ row.touch }} bước</span>
                  </span>
                </div>
                <div class="dt-track__line" :style="{ '--dt-steps': row.nodes.length }">
                  <button
                    v-for="node in row.nodes"
                    :key="`${row.actor}-${node.code}`"
                    type="button"
                    class="dt-track__node"
                    :class="{ 'is-dim': focusCode && focusCode !== node.code, 'is-on': focusCode === node.code }"
                    @click="toggleFocus(node.code)"
                  >
                    <span class="dt-track__dot" :class="`is-${node.tone}`" aria-hidden="true">
                      <AppIcon v-if="node.tone === 'go'" name="check" :size="14" :stroke-width="2.5" />
                    </span>
                    <span class="dt-track__cap">{{ node.label }}</span>
                    <span v-if="node.actions.length" class="dt-track__act">{{ node.actions.join(' · ') }}</span>
                  </button>
                </div>
              </article>
            </div>
          </div>
        </section>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="stepDrawerOpen && focusStatus" class="dt-drawer-backdrop" @click.self="closeStepDrawer">
        <aside
          class="dt-drawer"
          role="dialog"
          aria-modal="true"
          aria-labelledby="dt-step-drawer-title"
          @keydown.escape="closeStepDrawer"
        >
          <header class="dt-drawer__head">
            <div class="dt-drawer__head-copy">
              <p class="dt-step-card__kicker">{{ GROUP_KIND_LABEL[focusStatus.group_kind] || 'Trạng thái' }}</p>
              <h4 id="dt-step-drawer-title" class="dt-step-card__title">{{ focusStatus.label }}</h4>
              <p v-if="statusStepLine(focusStatus.code)" class="dt-step-card__sub">
                {{ statusStepLine(focusStatus.code) }}
              </p>
            </div>
            <button type="button" class="dt-modal__close" aria-label="Đóng" @click="closeStepDrawer">
              <AppIcon name="close" :size="18" />
            </button>
          </header>
          <div class="dt-drawer__body hide-scrollbar">
            <div class="dt-step-card__actions">
              <button type="button" class="dt-link-btn" @click="openRules">Xem người phụ trách</button>
              <button type="button" class="dt-link-btn" @click="closeStepDrawer">Bỏ chọn</button>
            </div>
            <div class="dt-step-card__cols">
              <section>
                <h5>Đi vào</h5>
                <ul>
                  <li v-if="!focusInbound.length" class="dt-step-card__empty">Không có lối vào</li>
                  <li v-for="tr in focusInbound" :key="`in-${tr.id}`">
                    <span class="dt-step-card__from">{{ statusLabel(tr.from_code) }}</span>
                    <span class="dt-step-card__meta">{{ actionLabel(tr.action_key) }} · {{ actorLabel(tr.actor_policy) }}</span>
                    <span v-if="guardHint(tr.guard_json)" class="dt-step-card__guard">{{ guardHint(tr.guard_json) }}</span>
                  </li>
                </ul>
              </section>
              <section>
                <h5>Đi ra</h5>
                <ul>
                  <li v-if="!focusOutbound.length" class="dt-step-card__empty">Không có lối ra</li>
                  <li v-for="tr in focusOutbound" :key="`out-${tr.id}`">
                    <span class="dt-step-card__from">{{ statusLabel(tr.to_code) }}</span>
                    <span class="dt-step-card__meta">{{ actionLabel(tr.action_key) }} · {{ actorLabel(tr.actor_policy) }}</span>
                    <span v-if="guardHint(tr.guard_json)" class="dt-step-card__guard">{{ guardHint(tr.guard_json) }}</span>
                  </li>
                </ul>
              </section>
            </div>
            <div v-if="conditional.length || terminal.length" class="dt-zz-side dt-zz-side--drawer">
              <section v-if="conditional.length" aria-label="Khi cần thêm việc">
                <h4 class="dt-zz-side__title">{{ GROUP_KIND_LABEL.conditional }}</h4>
                <ul class="dt-zz-side__list">
                  <li v-for="st in conditional" :key="st.code">
                    <button
                      type="button"
                      class="dt-zz-pill"
                      :class="stepClass(st.code)"
                      :aria-pressed="focusCode === st.code"
                      @click="toggleFocus(st.code)"
                    >
                      <span class="dt-zz-pill__node dt-zz-pill__node--need" aria-hidden="true" />
                      <span class="dt-zz-pill__text">
                        <span class="dt-zz-pill__title">{{ st.label }}</span>
                        <span v-if="statusStepLine(st.code)" class="dt-zz-pill__sub">{{ statusStepLine(st.code) }}</span>
                      </span>
                    </button>
                  </li>
                </ul>
              </section>
              <section v-if="terminal.length" aria-label="Kết thúc">
                <h4 class="dt-zz-side__title">{{ GROUP_KIND_LABEL.terminal }}</h4>
                <ul class="dt-zz-side__list">
                  <li v-for="st in terminal" :key="st.code">
                    <button
                      type="button"
                      class="dt-zz-pill"
                      :class="stepClass(st.code)"
                      :aria-pressed="focusCode === st.code"
                      @click="toggleFocus(st.code)"
                    >
                      <span
                        class="dt-zz-pill__node"
                        :class="st.terminal_kind === 'failed' ? 'dt-zz-pill__node--fail' : 'dt-zz-pill__node--done'"
                        aria-hidden="true"
                      />
                      <span class="dt-zz-pill__text">
                        <span class="dt-zz-pill__title">{{ st.label }}</span>
                        <span v-if="statusStepLine(st.code)" class="dt-zz-pill__sub">{{ statusStepLine(st.code) }}</span>
                      </span>
                    </button>
                  </li>
                </ul>
              </section>
            </div>
          </div>
        </aside>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="createSetOpen" class="dt-modal-backdrop" @click.self="createSetOpen = false">
        <div
          class="dt-modal dt-modal--fill"
          role="dialog"
          aria-modal="true"
          aria-labelledby="dt-set-create-title"
          @keydown.escape="createSetOpen = false"
        >
          <header class="dt-modal__head dt-modal__head--rich">
            <span class="dt-modal__mark" aria-hidden="true">
              <AppIcon name="layers" :size="22" :stroke-width="1.75" />
            </span>
            <div class="dt-modal__head-copy">
              <p class="dt-modal__head-kicker">Bước 1 · Thiết lập đơn từ</p>
              <h2 id="dt-set-create-title">Tạo bộ trạng thái</h2>
            </div>
            <button type="button" class="dt-modal__close" aria-label="Đóng" @click="createSetOpen = false">
              <AppIcon name="close" :size="18" />
            </button>
          </header>
          <div class="dt-modal__body dt-set-create">
            <div class="dt-set-create__fields hide-scrollbar">
              <section class="dt-form-section" aria-labelledby="dt-set-section-info">
                <header class="dt-form-section__head">
                  <span class="dt-form-section__step" aria-hidden="true">1</span>
                  <h3 id="dt-set-section-info" class="dt-form-section__title">Thông tin bộ</h3>
                </header>
                <div class="dt-form-section__grid">
                  <label class="dt-field">
                    <span>Tên bộ trạng thái<span class="dt-req" aria-hidden="true">*</span></span>
                    <input
                      v-model="createSetForm.name"
                      type="text"
                      required
                      autocomplete="off"
                      placeholder="Ví dụ: Quy trình nghỉ việc"
                    />
                  </label>
                  <label class="dt-field">
                    <span>Mã bộ trạng thái<span class="dt-req" aria-hidden="true">*</span></span>
                    <span class="dt-code-lock" :class="{ 'dt-code-lock--open': codeUnlocked }">
                      <input
                        ref="codeInput"
                        :value="createSetForm.code"
                        type="text"
                        required
                        maxlength="64"
                        spellcheck="false"
                        autocomplete="off"
                        :readonly="!codeUnlocked"
                        placeholder="BMTT03"
                        @input="onCodeInput"
                      />
                      <button
                        type="button"
                        class="dt-code-lock__btn"
                        :aria-label="codeUnlocked ? 'Khóa mã' : 'Mở khóa để sửa mã'"
                        :aria-pressed="codeUnlocked"
                        @click="toggleCodeLock"
                      >
                        <AppIcon :name="codeUnlocked ? 'unlock' : 'lock'" :size="16" :stroke-width="1.75" />
                      </button>
                    </span>
                  </label>
                  <label class="dt-field dt-field--span2">
                    <span>Mô tả</span>
                    <textarea
                      v-model="createSetForm.description"
                      rows="3"
                      placeholder="Ghi loại đơn hoặc phòng ban sẽ dùng bộ này"
                    />
                  </label>
                </div>
              </section>

              <section class="dt-form-section dt-form-section--compact" aria-labelledby="dt-set-section-init">
                <header class="dt-form-section__head dt-form-section__head--inline">
                  <span class="dt-form-section__step" aria-hidden="true">2</span>
                  <h3 id="dt-set-section-init" class="dt-form-section__title">Nội dung khởi tạo</h3>
                </header>
                <div class="dt-init-compact">
                  <div class="dt-setup__seg dt-init-compact__seg" role="radiogroup" aria-label="Cách khởi tạo trạng thái">
                    <button
                      type="button"
                      role="radio"
                      class="dt-setup__seg-btn"
                      :class="{ 'dt-setup__seg-btn--on': !createSetForm.use_template }"
                      :aria-checked="!createSetForm.use_template"
                      @click="setCreateInitMode(false)"
                    >
                      Bắt đầu trống
                    </button>
                    <button
                      type="button"
                      role="radio"
                      class="dt-setup__seg-btn"
                      :class="{ 'dt-setup__seg-btn--on': createSetForm.use_template }"
                      :aria-checked="createSetForm.use_template"
                      :disabled="!sets.length"
                      @click="setCreateInitMode(true)"
                    >
                      Sao chép mẫu
                    </button>
                  </div>
                  <label v-if="createSetForm.use_template" class="dt-field dt-init-compact__select">
                    <span class="dt-sr-only">Chọn mẫu</span>
                    <select v-model="createSetForm.copy_from_id">
                      <option disabled value="">Chọn bộ mẫu…</option>
                      <option v-for="set in sets" :key="set.id" :value="String(set.id)">
                        {{ friendlySetName(set.name, set.code) }} · {{ set.status_count }} trạng thái
                      </option>
                    </select>
                  </label>
                </div>
              </section>
            </div>
            <aside class="dt-set-preview" aria-label="Kết quả dự kiến">
              <header class="dt-set-preview__head">
                <h3>Kết quả dự kiến</h3>
                <p v-if="createSetForm.use_template && templatePreview">
                  {{ friendlySetName(templatePreview.name, templatePreview.code) }}
                </p>
                <p v-else-if="!createSetForm.use_template">Bộ trống sau khi tạo</p>
              </header>
              <div v-if="previewStats && createSetForm.use_template" class="dt-set-preview__stats">
                <span>{{ previewStats.statuses }} trạng thái</span>
                <span>{{ previewStats.transitions }} quy tắc</span>
              </div>
              <div class="dt-set-preview__body hide-scrollbar">
                <p v-if="templatePreviewLoading" class="dt-set-preview__empty">Đang tải…</p>
                <div v-else-if="!createSetForm.use_template" class="dt-set-preview__plan">
                  <ol class="dt-set-preview__steps">
                    <li>Tạo bộ với tên và mã đã nhập</li>
                    <li>Thêm trạng thái từ menu Thêm</li>
                    <li>Gắn bộ vào quy trình duyệt</li>
                  </ol>
                </div>
                <p v-else-if="!createSetForm.copy_from_id" class="dt-set-preview__empty">Chọn mẫu bên trái</p>
                <p v-else-if="!previewGroups.length" class="dt-set-preview__empty">Mẫu này chưa có trạng thái.</p>
                <div v-else class="dt-set-preview__groups">
                  <p v-if="previewDescription" class="dt-set-preview__desc">{{ previewDescription }}</p>
                  <section v-for="group in previewGroups" :key="group.kind" class="dt-set-preview__group">
                    <h4>{{ group.label }}</h4>
                    <ul>
                      <li
                        v-for="status in group.items"
                        :key="status.code"
                        class="dt-set-preview__item"
                        :class="`dt-set-preview__item--${previewTone(status)}`"
                      >
                        <DonTuStatusBadge :code="status.code" :label="status.label" />
                      </li>
                    </ul>
                  </section>
                </div>
              </div>
            </aside>
          </div>
          <footer class="dt-modal__foot dt-modal__foot--split">
            <p class="dt-modal__foot-summary">{{ createSetFootSummary }}</p>
            <div class="dt-modal__foot-actions">
              <button type="button" class="dt-btn-ghost" @click="createSetOpen = false">Huỷ</button>
              <button type="button" class="dt-btn-primary" :disabled="createSetSaving" @click="submitCreateSet">
                {{ createSetSaving ? 'Đang lưu…' : 'Tạo bộ' }}
              </button>
            </div>
          </footer>
        </div>
      </div>

      <div v-if="createOpen" class="dt-modal-backdrop" @click.self="createOpen = false">
        <div
          class="dt-modal dt-modal--wide dt-modal--status-create"
          role="dialog"
          aria-modal="true"
          aria-labelledby="dt-status-create-title"
          @keydown.escape="createOpen = false"
        >
          <header class="dt-modal__head dt-modal__head--rich">
            <span class="dt-modal__mark" aria-hidden="true">
              <AppIcon name="plusCircle" :size="22" :stroke-width="1.75" />
            </span>
            <div class="dt-modal__head-copy">
              <p class="dt-modal__head-kicker">{{ detailTitle }}</p>
              <h2 id="dt-status-create-title">Thêm trạng thái</h2>
            </div>
            <button type="button" class="dt-modal__close" aria-label="Đóng" @click="createOpen = false">
              <AppIcon name="close" :size="18" />
            </button>
          </header>
          <div class="dt-modal__body dt-status-create hide-scrollbar">
            <section class="dt-form-section" aria-labelledby="dt-status-section-identity">
              <header class="dt-form-section__head">
                <span class="dt-form-section__step" aria-hidden="true">1</span>
                <h3 id="dt-status-section-identity" class="dt-form-section__title">Tên và vị trí</h3>
              </header>
              <div class="dt-form-section__grid">
                <label class="dt-field">
                  <span>Tên trạng thái<span class="dt-req" aria-hidden="true">*</span></span>
                  <input
                    v-model="createForm.label"
                    type="text"
                    required
                    autocomplete="off"
                    placeholder="Ví dụ: Chờ trưởng phòng duyệt"
                  />
                </label>
                <label class="dt-field">
                  <span>Nhóm trên luồng<span class="dt-req" aria-hidden="true">*</span></span>
                  <select v-model="createForm.group_kind" required>
                    <option v-for="(label, key) in GROUP_KIND_LABEL" :key="key" :value="key">{{ label }}</option>
                  </select>
                </label>
                <label v-if="createForm.group_kind === 'terminal'" class="dt-field dt-field--span2">
                  <span>Kết quả cuối<span class="dt-req" aria-hidden="true">*</span></span>
                  <select v-model="createForm.terminal_kind" required>
                    <option value="success">Xong việc</option>
                    <option value="failed">Không xong</option>
                  </select>
                </label>
              </div>
            </section>
            <section class="dt-form-section" aria-labelledby="dt-status-section-rule">
              <header class="dt-form-section__head">
                <span class="dt-form-section__step" aria-hidden="true">2</span>
                <h3 id="dt-status-section-rule" class="dt-form-section__title">Quy tắc chuyển vào trạng thái này</h3>
              </header>
              <div class="dt-form-section__grid">
                <label class="dt-field">
                  <span>Từ trạng thái<span class="dt-req" aria-hidden="true">*</span></span>
                  <select v-model="createForm.from_code" required>
                    <option disabled value="">Chọn bước trước…</option>
                    <option v-for="opt in fromOptions" :key="opt.code" :value="opt.code">{{ opt.label }}</option>
                  </select>
                </label>
                <label class="dt-field">
                  <span>Thao tác<span class="dt-req" aria-hidden="true">*</span></span>
                  <select v-model="createForm.action_key" required>
                    <option v-for="(label, key) in ACTION_LABEL" :key="key" :value="key">{{ label }}</option>
                  </select>
                </label>
                <label class="dt-field dt-field--span2">
                  <span>Người được thực hiện<span class="dt-req" aria-hidden="true">*</span></span>
                  <select v-model="createForm.actor_policy" required>
                    <option v-for="(label, key) in ACTOR_LABEL" :key="key" :value="key">{{ label }}</option>
                  </select>
                </label>
              </div>
            </section>
          </div>
          <footer class="dt-modal__foot">
            <button type="button" class="dt-btn-ghost" @click="createOpen = false">Huỷ</button>
            <button type="button" class="dt-btn-primary" :disabled="createSaving" @click="submitCreate">
              {{ createSaving ? 'Đang lưu…' : 'Thêm trạng thái' }}
            </button>
          </footer>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
@import './donTuAdminShared.css';

.dt-config-deny {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-6);
  color: var(--color-text-muted);
  text-align: center;
}

.dt-status-page__toolbar {
  margin-bottom: var(--space-3);
}

.dt-status-page__empty-panel {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-6) var(--space-4);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  text-align: center;
  color: var(--color-text-muted);
}

.dt-status-page__empty-title {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 500;
  color: var(--color-text);
}

.dt-status-page__empty-lead {
  margin: 0;
  max-width: 28rem;
  font-size: 0.875rem;
  line-height: 1.55;
}

.dt-setup__rail-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.dt-setup__rail-add {
  font-size: 0.75rem;
}

.dt-setup__callout {
  margin: 0 0 var(--space-3);
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-md);
  background: var(--color-info-tint-bg);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-info) 25%, var(--color-border));
  font-size: 0.8125rem;
  line-height: 1.5;
  color: var(--color-text);
}

.dt-set-card__code {
  display: block;
  margin-top: 0.15rem;
  font-size: 0.6875rem;
  letter-spacing: 0.04em;
  color: var(--color-text-muted);
}

.dt-status-page__body {
  display: grid;
  grid-template-columns: minmax(13rem, 16rem) minmax(0, 1fr);
  gap: var(--space-3);
  align-items: start;
}

.dt-status-page__body--solo {
  grid-template-columns: minmax(0, 1fr);
}

.dt-status-page__set-search,
.dt-trans-table__search {
  width: 100%;
  padding: 0.4rem 0.6rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font: inherit;
  font-size: 0.8125rem;
  background: var(--color-surface);
}

.dt-status-page__set-search::placeholder,
.dt-trans-table__search::placeholder {
  color: var(--color-text-muted);
  opacity: 0.8;
}

.dt-status-page__set-search {
  margin-bottom: var(--space-2);
}

.dt-status-page__set-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.dt-set-card {
  position: relative;
  width: 100%;
  text-align: left;
  padding: var(--space-2) var(--space-3) var(--space-2) calc(var(--space-2) + 3px + var(--space-2));
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
  font: inherit;
  color: var(--color-text);
}

.dt-set-card::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.dt-set-card--active::before {
  background: var(--color-primary);
}

.dt-set-card--active {
  background: var(--color-primary-100);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 35%, var(--color-border));
}

.dt-set-card__name {
  display: block;
  font-weight: 500;
  font-size: 0.875rem;
  line-height: 1.35;
}

.dt-set-card__meta {
  display: block;
  margin-top: 0.125rem;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.dt-set-card__tag {
  display: inline-block;
  margin-top: var(--space-1);
  padding: 0.1rem 0.45rem;
  border-radius: var(--radius-sm);
  font-size: 0.6875rem;
  font-weight: 600;
  background: var(--color-info-tint-bg);
  color: var(--color-info);
}

.dt-status-page__detail-head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-2) var(--space-4);
  margin-bottom: var(--space-3);
}

.dt-status-page__detail-title {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 700;
}

.dt-status-page__detail-desc {
  margin: var(--space-1) 0 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.dt-status-page__stats {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2) var(--space-3);
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.dt-status-page__stats .dt-set-card__tag {
  margin-top: 0;
}

.dt-link-btn {
  border: none;
  background: transparent;
  padding: 0;
  font: inherit;
  font-size: 0.8125rem;
  color: var(--color-primary);
  cursor: pointer;
  text-decoration: underline;
  text-underline-offset: 2px;
}

.dt-panel {
  margin-bottom: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.dt-panel__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2) var(--space-3);
  margin-bottom: var(--space-2);
}

.dt-panel__title {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 600;
}

.dt-panel > .dt-panel__title {
  margin-bottom: var(--space-2);
}

.dt-workflow-strip__list {
  display: flex;
  flex-wrap: nowrap;
  gap: var(--space-2);
  overflow-x: auto;
  margin: 0;
  padding: 0;
  list-style: none;
}

.dt-workflow-chip {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.35rem 0.65rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
  font: inherit;
  white-space: nowrap;
}

.dt-workflow-chip__name {
  font-weight: 500;
  font-size: 0.8125rem;
}

.dt-pill--compact {
  font-size: 0.625rem;
}

.dt-workflow-strip__link {
  display: inline-flex;
  align-items: center;
  gap: 0.15rem;
  margin-left: auto;
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--color-primary);
  text-decoration: none;
}

.dt-trans-table__search {
  flex: 1;
  min-width: 12rem;
  max-width: 22rem;
  margin-left: auto;
}

.dt-trans-table__scroll {
  overflow-x: auto;
  cursor: grab;
}

.dt-trans-table__scroll:active {
  cursor: grabbing;
}

.dt-trans-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.dt-trans-table th {
  text-align: left;
  padding: 0.55rem 0.75rem;
  font-weight: 600;
  font-size: 0.6875rem;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: color-mix(in srgb, var(--color-text) 65%, var(--color-text-muted));
  background: color-mix(in srgb, var(--color-primary) 8%, var(--color-surface-muted));
  box-shadow: 0 1px 0 color-mix(in srgb, var(--color-primary) 18%, var(--color-border));
}

.dt-trans-table--rules th:first-child {
  border-radius: var(--radius-sm) 0 0 0;
}

.dt-trans-table--rules th:last-child {
  border-radius: 0 var(--radius-sm) 0 0;
}

.dt-trans-table td {
  padding: 0.65rem 0.75rem;
  vertical-align: middle;
  box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 60%, transparent);
}

.dt-trans-table--rules .dt-trans-table__cell--action {
  position: relative;
}

.dt-trans-table--rules .dt-trans-table__cell--action::before,
.dt-trans-table--rules .dt-trans-table__cell--action::after {
  position: absolute;
  top: 50%;
  font-size: 0.875rem;
  font-weight: 600;
  color: color-mix(in srgb, var(--color-primary) 55%, var(--color-text-muted));
  transform: translateY(-50%);
  pointer-events: none;
}

.dt-trans-table--rules .dt-trans-table__cell--action::before {
  content: '→';
  left: -0.35rem;
}

.dt-trans-table--rules .dt-trans-table__cell--action::after {
  content: '→';
  right: -0.35rem;
}

.dt-trans-rules {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.dt-trans-rules .dt-trans-table__scroll {
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-primary) 3%, var(--color-surface));
  box-shadow:
    var(--shadow-sm),
    inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 14%, var(--color-border));
}

.dt-trans-rules__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2) var(--space-3);
}

.dt-trans-table--rules {
  table-layout: fixed;
}

.dt-trans-col--status {
  width: 22%;
}

.dt-trans-col--action {
  width: 26%;
}

.dt-trans-col--actor {
  width: 30%;
}

.dt-trans-table__row:hover {
  background: color-mix(in srgb, var(--color-primary) 7%, var(--color-surface));
}

.dt-trans-table--rules .dt-trans-table__row:nth-child(even) {
  background: color-mix(in srgb, var(--color-primary) 3%, var(--color-surface));
}

.dt-trans-table--rules .dt-trans-table__row:nth-child(even):hover {
  background: color-mix(in srgb, var(--color-primary) 9%, var(--color-surface));
}

.dt-trans-table__cell--status,
.dt-trans-table__cell--action {
  vertical-align: top;
}

.dt-trans-table__status,
.dt-trans-table__action {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 0.35rem;
  max-width: 14rem;
}

.dt-trans-table__chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  min-width: 9.5rem;
  width: 100%;
  max-width: 14rem;
  padding: 0.45rem 0.75rem;
  border-radius: var(--radius-sm);
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.35;
  text-align: center;
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-text-muted) 35%, var(--color-border));
  background: color-mix(in srgb, var(--color-text-muted) 12%, var(--color-surface));
  color: var(--color-text);
}

.dt-action-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  min-width: 9.5rem;
  width: 100%;
  max-width: 14rem;
  padding: 0.5rem 0.75rem;
  border-radius: var(--radius-sm);
  font-size: 0.8125rem;
  font-weight: 700;
  line-height: 1.35;
  text-align: center;
  color: var(--color-primary-700);
  background: color-mix(in srgb, var(--color-primary) 18%, var(--color-surface));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 42%, var(--color-border));
}

.dt-guard {
  display: block;
  padding: 0.25rem 0.5rem;
  border-radius: var(--radius-sm);
  font-size: 0.75rem;
  line-height: 1.4;
  color: var(--color-text-muted);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-border) 80%, transparent);
}

.dt-trans-actor {
  display: inline-flex;
  align-items: center;
  max-width: 100%;
  padding: 0.45rem 0.75rem;
  border-radius: var(--radius-sm);
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.35;
  color: var(--actor-fg, var(--color-text));
  background: color-mix(in srgb, var(--actor-fg, var(--color-text-muted)) 16%, var(--color-surface));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--actor-fg, var(--color-border)) 38%, var(--color-border));
}

.dt-trans-actor--requester {
  --actor-fg: var(--color-info);
}

.dt-trans-actor--assignee {
  --actor-fg: #c27803;
}

.dt-trans-actor--closure_actor {
  --actor-fg: var(--color-success);
}

.dt-trans-actor--admin {
  --actor-fg: var(--color-primary);
}

.dt-status-page__loading,
.dt-status-page__empty {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.dt-modal--fill .dt-modal__close,
.dt-modal--status-create .dt-modal__close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-sm);
}

.dt-modal--fill .dt-modal__close:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.dt-modal.dt-modal--fill .dt-modal__body.dt-set-create {
  display: grid;
  grid-template-columns: minmax(0, 1.15fr) minmax(20rem, 0.85fr);
  gap: var(--space-5);
  overflow: hidden;
}

.dt-set-create__fields {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  min-width: 0;
  min-height: 0;
  height: 100%;
  overflow: auto;
}

.dt-status-create {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.dt-modal--status-create {
  width: min(56rem, calc(100vw - 2.5rem));
  max-height: calc(100vh - 2.5rem);
}

.dt-field .dt-code-lock {
  display: flex;
  align-items: stretch;
  min-height: 2.25rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  overflow: hidden;
}

.dt-field .dt-code-lock--open {
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 45%, var(--color-border));
}

.dt-field .dt-code-lock input {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  letter-spacing: 0.06em;
}

.dt-field .dt-code-lock input[readonly] {
  color: var(--color-text);
}

.dt-code-lock__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
  box-shadow: inset 1px 0 0 var(--color-border);
}

.dt-code-lock--open .dt-code-lock__btn {
  color: var(--color-primary);
}

.dt-code-lock__btn:hover {
  background: color-mix(in srgb, var(--color-primary) 8%, transparent);
}

.dt-set-preview {
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  overflow: hidden;
}

.dt-set-preview__head {
  flex-shrink: 0;
  padding: var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.dt-set-preview__head h3 {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 500;
}

.dt-set-preview__head p,
.dt-set-preview__desc {
  margin: 0.25rem 0 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  line-height: 1.45;
}

.dt-set-preview__stats {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
  padding: 0 var(--space-4) var(--space-3);
  font-size: 0.75rem;
  color: var(--color-primary-700);
}

.dt-set-preview__stats span {
  padding: 0.2rem 0.55rem;
  border-radius: var(--radius-sm);
  background: var(--color-primary-100);
}

.dt-set-preview__plan {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
}

.dt-set-preview__steps {
  margin: 0;
  padding-left: 1.25rem;
  max-width: 20rem;
  font-size: 0.875rem;
  line-height: 1.55;
  color: var(--color-text);
}

.dt-set-preview__steps li + li {
  margin-top: var(--space-2);
}

.dt-set-preview__body {
  flex: 1;
  min-height: 0;
  overflow: auto;
  padding: var(--space-4);
}

.dt-set-preview__empty {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.dt-set-preview__groups {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.dt-set-preview__group h4 {
  margin: 0 0 var(--space-2);
  font-size: 0.75rem;
  font-weight: 500;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.dt-set-preview__group ul {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.dt-set-preview__item {
  position: relative;
  padding: var(--space-2) var(--space-3) var(--space-2) calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.dt-set-preview__item::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-primary);
}

.dt-set-preview__item--info::before {
  background: var(--color-info);
}

.dt-set-preview__item--success::before {
  background: var(--color-success);
}

.dt-set-preview__item--danger::before {
  background: var(--color-danger);
}

@media (max-width: 800px) {
  .dt-modal.dt-modal--fill .dt-modal__body.dt-set-create {
    grid-template-columns: minmax(0, 1fr);
    overflow: auto;
  }

  .dt-set-preview {
    min-height: 18rem;
  }

  .dt-form-section__grid {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 900px) {
  .dt-status-page__body {
    grid-template-columns: 1fr;
  }
}
</style>
