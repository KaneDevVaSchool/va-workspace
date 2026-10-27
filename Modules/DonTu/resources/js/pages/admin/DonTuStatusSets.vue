<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useDonTuConfigHub } from '../../composables/useDonTuConfigHub.js';
import { useRouter } from 'vue-router';
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
  friendlySetName,
  guardHint,
  humanSetDescription,
  toneOfStatusCode,
} from '../../constants/statusTone.js';

const auth = useAuthStore();
const router = useRouter();
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
const viewTab = ref('flow');

const mainFlow = computed(() =>
  (detail.value?.statuses ?? []).filter((s) => s.group_kind === 'main').sort((a, b) => a.sort_order - b.sort_order),
);
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

const filteredTransitions = computed(() => {
  const q = transitionQuery.value.trim().toLowerCase();
  let list = detail.value?.transitions ?? [];
  if (focusCode.value) {
    const code = focusCode.value;
    list = list.filter((tr) => tr.from_code === code || tr.to_code === code);
  }
  if (!q) return list;
  return list.filter((tr) => {
    const haystack = [
      statusLabel(tr.from_code),
      statusLabel(tr.to_code),
      actionLabel(tr.action_key),
      actorLabel(tr.actor_policy),
      guardHint(tr.guard_json),
    ]
      .join(' ')
      .toLowerCase();
    return haystack.includes(q);
  });
});

const detailTitle = computed(() => friendlySetName(detail.value?.name, detail.value?.code));
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

function toggleFocus(code) {
  focusCode.value = focusCode.value === code ? '' : code;
  if (focusCode.value) {
    viewTab.value = 'rules';
  }
}

async function copyCode(code) {
  try {
    await navigator.clipboard.writeText(code);
    showClientToast('success', 'Đã sao chép mã.');
  } catch {
    showClientToast('error', 'Không sao chép được.');
  }
}

function goWorkflow(id) {
  router.push({ name: 'manager.don-tu.workflow', params: { id } });
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
        Bộ trạng thái quy định hồ sơ đơn đi qua những bước nào trước khi gắn mẫu biểu và quy trình duyệt.
      </p>
      <button v-if="canWrite" type="button" class="dt-btn-primary" @click="openCreateSet">Tạo bộ trạng thái</button>
    </div>

    <div v-else class="dt-setup__layout" :class="{ 'dt-setup__layout--solo': !showRail }">
      <aside v-if="showRail" class="dt-setup__rail">
        <div class="dt-setup__rail-head">
          <h3 class="dt-setup__rail-title">Chọn bộ trạng thái</h3>
          <button v-if="canWrite" type="button" class="dt-link-btn dt-setup__rail-add" @click="openCreateSet">
            Tạo mới
          </button>
        </div>
        <input
          v-model="setQuery"
          type="search"
          class="dt-status-page__set-search"
          placeholder="Tìm theo tên hoặc mã"
          aria-label="Tìm bộ trạng thái"
        />
        <ul v-if="!loadingList" class="dt-status-page__set-list">
          <li v-if="!filteredSets.length">
            <p class="dt-status-page__empty">Không thấy nhóm nào.</p>
          </li>
          <li v-for="set in filteredSets" :key="set.id">
            <button
              type="button"
              class="dt-set-card"
              :class="{ 'dt-set-card--active': selectedId === set.id }"
              @click="selectedId = set.id"
            >
              <span class="dt-set-card__name">{{ friendlySetName(set.name, set.code) }}</span>
              <span class="dt-set-card__code">{{ set.code }}</span>
              <span class="dt-set-card__meta">
                {{ set.status_count }} trạng thái
                <template v-if="set.workflow_count"> · {{ set.workflow_count }} quy trình</template>
              </span>
              <span v-if="set.is_system" class="dt-set-card__tag">Có sẵn</span>
            </button>
          </li>
        </ul>
        <p v-else class="dt-status-page__loading">Đang tải…</p>
      </aside>

      <div v-if="loadingDetail" class="dt-status-page__loading dt-setup__main">
        Đang tải…
      </div>

      <div v-else-if="detail" class="dt-setup__main">
        <header class="dt-setup__hero">
          <div class="dt-setup__hero-main">
            <p class="dt-setup__hero-eyebrow">Bộ đang xem</p>
            <h2 class="dt-setup__hero-title">{{ detailTitle }}</h2>
            <p v-if="detailDescription" class="dt-setup__hero-desc">{{ detailDescription }}</p>
            <div class="dt-setup__hero-meta">
              <span class="dt-setup__code-pill">{{ detail.code }}</span>
              <button type="button" class="dt-link-btn" @click="copyCode(detail.code)">Sao chép mã</button>
              <span v-if="detail.is_system" class="dt-set-card__tag">Mẫu có sẵn</span>
            </div>
          </div>
          <div class="dt-setup__metrics" aria-label="Thống kê bộ trạng thái">
            <div class="dt-setup__metric">
              <span class="dt-setup__metric-num">{{ detail.statuses?.length ?? 0 }}</span>
              <span class="dt-setup__metric-label">bước</span>
            </div>
            <div class="dt-setup__metric">
              <span class="dt-setup__metric-num">{{ detail.transitions?.length ?? 0 }}</span>
              <span class="dt-setup__metric-label">lần chuyển</span>
            </div>
            <div class="dt-setup__metric">
              <span class="dt-setup__metric-num">{{ detail.workflow_count ?? 0 }}</span>
              <span class="dt-setup__metric-label">quy trình dùng</span>
            </div>
          </div>
        </header>

        <section v-if="detail.workflows?.length" class="dt-setup__group">
          <header class="dt-setup__group-head">
            <span class="dt-setup__group-icon" aria-hidden="true">
              <AppIcon name="gitBranch" :size="18" :stroke-width="1.75" />
            </span>
            <h3 class="dt-setup__group-title">Quy trình gắn nhóm này</h3>
            <router-link :to="{ name: 'manager.don-tu.workflows' }" class="dt-workflow-strip__link">
              Mở quy trình
              <AppIcon name="chevronRight" :size="16" :stroke-width="2" />
            </router-link>
          </header>
          <div class="dt-setup__group-body">
            <ul class="dt-workflow-strip__list hide-scrollbar">
              <li v-for="wf in detail.workflows" :key="wf.id">
                <button type="button" class="dt-workflow-chip" @click="goWorkflow(wf.id)">
                  <span class="dt-workflow-chip__name">{{ wf.name }}</span>
                  <span
                    class="dt-pill dt-pill--compact"
                    :class="wf.is_active ? 'dt-pill--success' : 'dt-pill--muted'"
                  >
                    {{ wf.is_active ? 'Đang dùng' : 'Đang tắt' }}
                  </span>
                </button>
              </li>
            </ul>
          </div>
        </section>

        <section class="dt-setup__group">
          <header class="dt-setup__group-head">
            <span class="dt-setup__group-icon" aria-hidden="true">
              <AppIcon name="layers" :size="18" :stroke-width="1.75" />
            </span>
            <div class="dt-setup__group-head-copy">
              <h3 class="dt-setup__group-title">Quy trình trạng thái</h3>
              <p class="dt-setup__group-lead">Hồ sơ đi lần lượt qua các bước; bấm một bước để xem ai được chuyển tiếp.</p>
            </div>
            <div class="dt-setup__seg" role="tablist" aria-label="Xem thiết lập">
              <button
                type="button"
                role="tab"
                class="dt-setup__seg-btn"
                :class="{ 'dt-setup__seg-btn--on': viewTab === 'flow' }"
                :aria-selected="viewTab === 'flow'"
                @click="viewTab = 'flow'"
              >
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
                Ai bấm gì
              </button>
            </div>
          </header>

          <div v-show="viewTab === 'flow'" class="dt-setup__group-body">
          <div v-if="!(detail.statuses?.length)" class="dt-flow-note dt-flow-note--info" role="note">
            <span class="dt-flow-note__icon" aria-hidden="true">
              <AppIcon name="info" :size="14" :stroke-width="2" />
            </span>
            <p class="dt-flow-note__text">Bộ này chưa có trạng thái. Mở menu Thêm trên trang để khai báo bước đầu tiên.</p>
          </div>
          <div v-else class="dt-pipeline-board">
            <section class="dt-pipeline-section dt-pipeline-section--canvas" aria-labelledby="dt-pipeline-main-label">
              <header class="dt-pipeline-section__head">
                <div class="dt-pipeline-section__head-row">
                  <h4 id="dt-pipeline-main-label" class="dt-pipeline-section__title">{{ GROUP_KIND_LABEL.main }}</h4>
                  <span class="dt-pipeline-section__kicker">Luồng chính</span>
                </div>
                <div class="dt-flow-note" role="note">
                  <span class="dt-flow-note__icon" aria-hidden="true">
                    <AppIcon name="arrowRight" :size="14" :stroke-width="2" />
                  </span>
                  <p class="dt-flow-note__text">Đi từ trái sang phải — mỗi nút là một trạng thái trên hồ sơ; bấm để lọc quy tắc chuyển.</p>
                </div>
              </header>
              <div ref="pipelineWrap" class="dt-pipeline hide-scrollbar">
                <ol v-if="mainFlow.length" class="dt-pipeline__track">
                  <li class="dt-pipeline__stop dt-pipeline__stop--origin" aria-hidden="true">
                    <span class="dt-pipeline__node dt-pipeline__node--static">
                      <span class="dt-pipeline__dot dt-pipeline__dot--origin" />
                      <span class="dt-pipeline__name">Mới tạo</span>
                    </span>
                  </li>
                  <template v-for="(st, idx) in mainFlow" :key="st.code">
                    <li class="dt-pipeline__seg" aria-hidden="true">
                      <span class="dt-pipeline__seg-line" />
                    </li>
                    <li class="dt-pipeline__stop">
                      <button
                        type="button"
                        class="dt-pipeline__node"
                        :class="{
                          'dt-pipeline__node--on': focusCode === st.code,
                          [`dt-pipeline__node--${toneOfStatusCode(st.code)}`]: true,
                        }"
                        :aria-pressed="focusCode === st.code"
                        @click="toggleFocus(st.code)"
                      >
                        <span class="dt-pipeline__dot">{{ idx + 1 }}</span>
                        <span class="dt-pipeline__name">{{ st.label }}</span>
                      </button>
                    </li>
                  </template>
                </ol>
                <p v-else class="dt-status-page__empty">Chưa có bước trên đường đi chính.</p>
              </div>
            </section>

            <section v-if="conditional.length" class="dt-pipeline-section dt-pipeline-section--branch" aria-labelledby="dt-pipeline-cond-label">
              <header class="dt-pipeline-section__head">
                <div class="dt-pipeline-section__head-row">
                  <h4 id="dt-pipeline-cond-label" class="dt-pipeline-section__title">{{ GROUP_KIND_LABEL.conditional }}</h4>
                  <span class="dt-pipeline-section__kicker dt-pipeline-section__kicker--branch">Nhánh</span>
                </div>
                <div class="dt-flow-note dt-flow-note--info" role="note">
                  <span class="dt-flow-note__icon" aria-hidden="true">
                    <AppIcon name="gitBranch" :size="14" :stroke-width="2" />
                  </span>
                  <p class="dt-flow-note__text">Chỉ xảy ra khi cần thêm việc (bổ sung, sửa lại…).</p>
                </div>
              </header>
              <ul class="dt-pipeline-branches">
                <li v-for="st in conditional" :key="st.code">
                  <button
                    type="button"
                    class="dt-pipeline-branch"
                    :class="{ 'dt-pipeline-branch--on': focusCode === st.code }"
                    :aria-pressed="focusCode === st.code"
                    @click="toggleFocus(st.code)"
                  >
                    <span class="dt-pipeline-branch__mark" aria-hidden="true" />
                    <span class="dt-pipeline-branch__label">{{ st.label }}</span>
                  </button>
                </li>
              </ul>
            </section>

            <section v-if="terminal.length" class="dt-pipeline-section dt-pipeline-section--branch" aria-labelledby="dt-pipeline-term-label">
              <header class="dt-pipeline-section__head">
                <div class="dt-pipeline-section__head-row">
                  <h4 id="dt-pipeline-term-label" class="dt-pipeline-section__title">{{ GROUP_KIND_LABEL.terminal }}</h4>
                  <span class="dt-pipeline-section__kicker dt-pipeline-section__kicker--term">Kết thúc</span>
                </div>
                <div class="dt-flow-note dt-flow-note--muted" role="note">
                  <span class="dt-flow-note__icon" aria-hidden="true">
                    <AppIcon name="flag" :size="14" :stroke-width="2" />
                  </span>
                  <p class="dt-flow-note__text">Hồ sơ dừng lại ở đây — không đi tiếp trên đường chính.</p>
                </div>
              </header>
              <ul class="dt-pipeline-branches">
                <li v-for="st in terminal" :key="st.code">
                  <button
                    type="button"
                    class="dt-pipeline-branch"
                    :class="[
                      { 'dt-pipeline-branch--on': focusCode === st.code },
                      st.terminal_kind === 'failed' ? 'dt-pipeline-branch--fail' : 'dt-pipeline-branch--done',
                    ]"
                    :aria-pressed="focusCode === st.code"
                    @click="toggleFocus(st.code)"
                  >
                    <span class="dt-pipeline-branch__mark" aria-hidden="true" />
                    <span class="dt-pipeline-branch__label">{{ st.label }}</span>
                  </button>
                </li>
              </ul>
            </section>
          </div>
          </div>

          <div v-show="viewTab === 'rules'" class="dt-setup__group-body dt-trans-rules">
          <div class="dt-trans-rules__toolbar">
            <button v-if="focusCode" type="button" class="dt-link-btn" @click="focusCode = ''">
              Xem tất cả trạng thái
            </button>
            <input
              v-model="transitionQuery"
              type="search"
              class="dt-trans-table__search"
              placeholder="Tìm người, việc làm hoặc trạng thái"
              aria-label="Tìm quy tắc chuyển"
            />
          </div>
          <div ref="transWrap" class="dt-trans-table__scroll hide-scrollbar">
            <table class="dt-trans-table dt-trans-table--rules">
              <colgroup>
                <col class="dt-trans-col dt-trans-col--status" />
                <col class="dt-trans-col dt-trans-col--action" />
                <col class="dt-trans-col dt-trans-col--status" />
                <col class="dt-trans-col dt-trans-col--actor" />
              </colgroup>
              <thead>
                <tr>
                  <th>Đang ở</th>
                  <th>Việc làm</th>
                  <th>Chuyển sang</th>
                  <th>Ai được bấm</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!filteredTransitions.length">
                  <td colspan="4" class="dt-status-page__empty">Không thấy dòng nào khớp.</td>
                </tr>
                <tr v-for="tr in filteredTransitions" :key="tr.id" class="dt-trans-table__row">
                  <td class="dt-trans-table__cell dt-trans-table__cell--status">
                    <div class="dt-trans-table__status">
                      <DonTuStatusBadge
                        v-if="tr.from_code !== 'NONE'"
                        compact
                        table-chip
                        :code="tr.from_code"
                        :label="statusLabel(tr.from_code)"
                      />
                      <span v-else class="dt-trans-table__chip dt-trans-table__chip--muted">Lúc chưa có hồ sơ</span>
                    </div>
                  </td>
                  <td class="dt-trans-table__cell dt-trans-table__cell--action">
                    <div class="dt-trans-table__action">
                      <span class="dt-action-chip">{{ actionLabel(tr.action_key) }}</span>
                      <span v-if="guardHint(tr.guard_json)" class="dt-guard">{{ guardHint(tr.guard_json) }}</span>
                    </div>
                  </td>
                  <td class="dt-trans-table__cell dt-trans-table__cell--status">
                    <div class="dt-trans-table__status">
                      <DonTuStatusBadge
                        compact
                        table-chip
                        :code="tr.to_code"
                        :label="statusLabel(tr.to_code)"
                      />
                    </div>
                  </td>
                  <td class="dt-trans-table__cell dt-trans-table__cell--actor">
                    <span class="dt-trans-actor">{{ actorLabel(tr.actor_policy) }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          </div>
        </section>

        <nav class="dt-setup__next" aria-label="Bước thiết lập tiếp theo">
          <span class="dt-setup__next-label">Bước tiếp theo</span>
          <router-link :to="{ name: 'manager.don-tu.forms' }" class="dt-setup__next-link">
            <AppIcon name="clipboardCheck" :size="15" :stroke-width="1.75" />
            Mẫu biểu
            <AppIcon name="chevronRight" :size="14" :stroke-width="2" />
          </router-link>
          <router-link :to="{ name: 'manager.don-tu.workflows' }" class="dt-setup__next-link">
            <AppIcon name="gitBranch" :size="15" :stroke-width="1.75" />
            Quy trình
            <AppIcon name="chevronRight" :size="14" :stroke-width="2" />
          </router-link>
        </nav>
      </div>
    </div>

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
  padding: 0.4rem 0.65rem;
  font-weight: 500;
  font-size: 0.75rem;
  color: var(--color-text-muted);
  box-shadow: 0 1px 0 var(--color-border);
}

.dt-trans-table td {
  padding: 0.45rem 0.65rem;
  vertical-align: middle;
  box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 60%, transparent);
}

.dt-trans-rules {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.dt-trans-rules .dt-trans-table__scroll {
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
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
  background: color-mix(in srgb, var(--color-primary) 4%, var(--color-surface));
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
  font-weight: 500;
  line-height: 1.35;
  text-align: center;
  box-shadow: inset 0 0 0 1px var(--color-border);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.dt-action-chip {
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
  font-weight: 500;
  line-height: 1.35;
  text-align: center;
  color: var(--color-text);
  background: color-mix(in srgb, var(--color-primary) 10%, var(--color-surface));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 28%, var(--color-border));
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
  display: block;
  font-size: 0.8125rem;
  line-height: 1.45;
  color: var(--color-text);
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
