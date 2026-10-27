<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useDonTuConfigHub } from '../../composables/useDonTuConfigHub.js';
import { useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import { useDragScroll } from '@/composables/useDragScroll.js';
import { showClientToast } from '@/lib/clientToast';
import { formatDonTuApiError } from '../../lib/donTuToast.js';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import DonTuConfigHubCards from '../../components/DonTuConfigHubCards.vue';
import DonTuStatusBadge from '../../components/DonTuStatusBadge.vue';
import { createStatus, createStatusSet, fetchStatusSet, fetchStatusSets } from '../../api/donTuConfigApi.js';
import {
  ACTION_LABEL,
  ACTOR_LABEL,
  GROUP_KIND_LABEL,
  friendlySetName,
  guardHint,
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
const createSetForm = ref({ name: '', description: '', copy_from_id: '' });

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
      { label: 'Tạo nhóm trạng thái', onSelect: () => openCreateSet() },
      {
        label: 'Thêm trạng thái',
        description: !detail.value ? 'Chọn hoặc tạo nhóm trước' : undefined,
        onSelect: () => {
          if (!detail.value || loadingDetail.value) {
            showClientToast('error', 'Chọn nhóm trạng thái trước khi thêm trạng thái.');
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

function openCreateSet() {
  const defaultSet = sets.value.find((s) => s.code === 'STATUS_SET_DEFAULT') || sets.value[0];
  createSetForm.value = {
    name: '',
    description: '',
    copy_from_id: defaultSet?.id ?? '',
  };
  createSetOpen.value = true;
}

async function submitCreateSet() {
  if (!createSetForm.value.name.trim()) {
    showClientToast('error', 'Nhập tên nhóm trạng thái.');
    return;
  }
  createSetSaving.value = true;
  try {
    const payload = {
      name: createSetForm.value.name.trim(),
      description: createSetForm.value.description.trim() || null,
    };
    if (createSetForm.value.copy_from_id !== '' && createSetForm.value.copy_from_id != null) {
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
    <DonTuConfigHubCards />

    <p class="dt-setup__intro">
      Khai báo nhóm trạng thái và quy tắc chuyển trước khi soạn mẫu biểu và quy trình duyệt. Bấm dấu
      <strong>+</strong> cạnh tiêu đề trang để tạo nhóm hoặc thêm trạng thái.
    </p>

    <div v-if="!loadingList && !sets.length" class="dt-status-page__empty-panel">
      <AppIcon name="layers" :size="28" :stroke-width="1.75" />
      <p>Chưa có nhóm trạng thái. Dùng dấu <strong>+</strong> trên thanh tiêu đề để tạo nhóm đầu tiên.</p>
    </div>

    <div v-else class="dt-setup__layout" :class="{ 'dt-setup__layout--solo': !showRail }">
      <aside v-if="showRail" class="dt-setup__rail">
        <h3 class="dt-setup__rail-title">Nhóm đang thiết lập</h3>
        <input
          v-model="setQuery"
          type="search"
          class="dt-status-page__set-search"
          placeholder="Tìm nhóm trạng thái"
          aria-label="Tìm nhóm trạng thái"
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
        <div class="dt-setup__metrics">
          <div class="dt-setup__metric">
            <span class="dt-setup__metric-num">{{ detail.statuses?.length ?? 0 }}</span>
            <span class="dt-setup__metric-label">trạng thái</span>
          </div>
          <div class="dt-setup__metric">
            <span class="dt-setup__metric-num">{{ detail.transitions?.length ?? 0 }}</span>
            <span class="dt-setup__metric-label">quy tắc</span>
          </div>
          <div class="dt-setup__metric">
            <span class="dt-setup__metric-num">{{ detail.workflow_count ?? 0 }}</span>
            <span class="dt-setup__metric-label">quy trình</span>
          </div>
          <button type="button" class="dt-link-btn" @click="copyCode(detail.code)">Sao chép mã</button>
          <span v-if="detail.is_system" class="dt-set-card__tag">Có sẵn</span>
        </div>

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
            <h3 class="dt-setup__group-title">{{ detailTitle }}</h3>
            <div class="dt-setup__seg" role="tablist" aria-label="Xem thiết lập">
              <button
                type="button"
                role="tab"
                class="dt-setup__seg-btn"
                :class="{ 'dt-setup__seg-btn--on': viewTab === 'flow' }"
                :aria-selected="viewTab === 'flow'"
                @click="viewTab = 'flow'"
              >
                Luồng trạng thái
              </button>
              <button
                type="button"
                role="tab"
                class="dt-setup__seg-btn"
                :class="{ 'dt-setup__seg-btn--on': viewTab === 'rules' }"
                :aria-selected="viewTab === 'rules'"
                @click="viewTab = 'rules'"
              >
                Quy tắc chuyển
              </button>
            </div>
          </header>

          <div v-show="viewTab === 'flow'" class="dt-setup__group-body">
          <div class="dt-lanes">
            <div class="dt-lane dt-lane--main">
              <span class="dt-lane__label">{{ GROUP_KIND_LABEL.main }}</span>
              <div ref="pipelineWrap" class="dt-flow-pipeline hide-scrollbar">
                <template v-for="(st, idx) in mainFlow" :key="st.code">
                  <button
                    type="button"
                    class="dt-status-hit"
                    :class="{ 'dt-status-hit--on': focusCode === st.code }"
                    :aria-pressed="focusCode === st.code"
                    @click="toggleFocus(st.code)"
                  >
                    <DonTuStatusBadge compact :code="st.code" :label="st.label" />
                  </button>
                  <AppIcon
                    v-if="idx < mainFlow.length - 1"
                    name="chevronRight"
                    class="dt-flow-pipeline__arrow"
                    :size="16"
                    :stroke-width="2"
                  />
                </template>
                <p v-if="!mainFlow.length" class="dt-status-page__empty">Chưa có trạng thái trên đường đi chính.</p>
              </div>
            </div>

            <div v-if="conditional.length" class="dt-lane">
              <span class="dt-lane__label">{{ GROUP_KIND_LABEL.conditional }}</span>
              <div class="dt-lane__chips">
                <button
                  v-for="st in conditional"
                  :key="st.code"
                  type="button"
                  class="dt-status-hit"
                  :class="{ 'dt-status-hit--on': focusCode === st.code }"
                  :aria-pressed="focusCode === st.code"
                  @click="toggleFocus(st.code)"
                >
                  <DonTuStatusBadge compact :code="st.code" :label="st.label" />
                </button>
              </div>
            </div>

            <div v-if="terminal.length" class="dt-lane">
              <span class="dt-lane__label">{{ GROUP_KIND_LABEL.terminal }}</span>
              <div class="dt-lane__chips">
                <button
                  v-for="st in terminal"
                  :key="st.code"
                  type="button"
                  class="dt-status-hit"
                  :class="{ 'dt-status-hit--on': focusCode === st.code }"
                  :aria-pressed="focusCode === st.code"
                  @click="toggleFocus(st.code)"
                >
                  <DonTuStatusBadge compact :code="st.code" :label="st.label" />
                </button>
              </div>
            </div>
          </div>
          </div>

          <div v-show="viewTab === 'rules'" class="dt-setup__group-body">
          <div class="dt-panel__row">
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
            <table class="dt-trans-table">
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
                <tr v-for="tr in filteredTransitions" :key="tr.id">
                  <td>
                    <DonTuStatusBadge
                      v-if="tr.from_code !== 'NONE'"
                      compact
                      :code="tr.from_code"
                      :label="statusLabel(tr.from_code)"
                    />
                    <span v-else class="dt-trans-none">Lúc chưa có hồ sơ</span>
                  </td>
                  <td>
                    <span class="dt-action-pill">{{ actionLabel(tr.action_key) }}</span>
                    <span v-if="guardHint(tr.guard_json)" class="dt-guard">{{ guardHint(tr.guard_json) }}</span>
                  </td>
                  <td>
                    <DonTuStatusBadge
                      compact
                      :code="tr.to_code"
                      :label="statusLabel(tr.to_code)"
                    />
                  </td>
                  <td class="dt-trans-actor">{{ actorLabel(tr.actor_policy) }}</td>
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
        <div class="dt-modal dt-modal--wide" role="dialog" aria-labelledby="dt-set-create-title">
          <header class="dt-modal__head">
            <h2 id="dt-set-create-title">Tạo nhóm trạng thái</h2>
            <button type="button" class="dt-modal__close" aria-label="Đóng" @click="createSetOpen = false">
              <AppIcon name="x" :size="18" />
            </button>
          </header>
          <div class="dt-modal__body dt-modal__grid">
            <label class="dt-field dt-field--span2">
              <span>Tên nhóm<span class="dt-req" aria-hidden="true">*</span></span>
              <input
                v-model="createSetForm.name"
                type="text"
                required
                placeholder="Ví dụ: Trạng thái đơn phòng Hành chính"
              />
            </label>
            <label class="dt-field dt-field--span2">
              <span>Sao chép từ</span>
              <select v-model="createSetForm.copy_from_id">
                <option v-if="!sets.length" disabled value="">Chưa có nhóm để sao chép</option>
                <option v-for="set in sets" :key="set.id" :value="set.id">
                  {{ friendlySetName(set.name, set.code) }}
                </option>
              </select>
            </label>
            <label class="dt-field dt-field--span2">
              <span>Ghi chú</span>
              <textarea
                v-model="createSetForm.description"
                rows="2"
                placeholder="Ghi ngắn nhóm này dùng cho loại đơn nào"
              />
            </label>
          </div>
          <footer class="dt-modal__foot">
            <button type="button" class="dt-btn-ghost" @click="createSetOpen = false">Huỷ</button>
            <button type="button" class="dt-btn-primary" :disabled="createSetSaving" @click="submitCreateSet">
              {{ createSetSaving ? 'Đang lưu…' : 'Tạo nhóm' }}
            </button>
          </footer>
        </div>
      </div>

      <div v-if="createOpen" class="dt-modal-backdrop" @click.self="createOpen = false">
        <div class="dt-modal dt-modal--wide" role="dialog" aria-labelledby="dt-status-create-title">
          <header class="dt-modal__head">
            <h2 id="dt-status-create-title">Thêm trạng thái</h2>
            <button type="button" class="dt-modal__close" aria-label="Đóng" @click="createOpen = false">
              <AppIcon name="x" :size="18" />
            </button>
          </header>
          <div class="dt-modal__body dt-modal__grid">
            <label class="dt-field">
              <span>Tên trạng thái<span class="dt-req" aria-hidden="true">*</span></span>
              <input v-model="createForm.label" type="text" required placeholder="Ví dụ: Chờ ký" />
            </label>
            <label class="dt-field">
              <span>Nằm ở đâu<span class="dt-req" aria-hidden="true">*</span></span>
              <select v-model="createForm.group_kind" required>
                <option v-for="(label, key) in GROUP_KIND_LABEL" :key="key" :value="key">{{ label }}</option>
              </select>
            </label>
            <label v-if="createForm.group_kind === 'terminal'" class="dt-field">
              <span>Kết quả<span class="dt-req" aria-hidden="true">*</span></span>
              <select v-model="createForm.terminal_kind" required>
                <option value="success">Xong việc</option>
                <option value="failed">Không xong</option>
              </select>
            </label>
            <label class="dt-field">
              <span>Đi từ trạng thái nào<span class="dt-req" aria-hidden="true">*</span></span>
              <select v-model="createForm.from_code" required>
                <option disabled value="">Chọn trạng thái phía trước</option>
                <option v-for="opt in fromOptions" :key="opt.code" :value="opt.code">{{ opt.label }}</option>
              </select>
            </label>
            <label class="dt-field">
              <span>Việc làm<span class="dt-req" aria-hidden="true">*</span></span>
              <select v-model="createForm.action_key" required>
                <option v-for="(label, key) in ACTION_LABEL" :key="key" :value="key">{{ label }}</option>
              </select>
            </label>
            <label class="dt-field">
              <span>Ai được bấm<span class="dt-req" aria-hidden="true">*</span></span>
              <select v-model="createForm.actor_policy" required>
                <option v-for="(label, key) in ACTOR_LABEL" :key="key" :value="key">{{ label }}</option>
              </select>
            </label>
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

.dt-status-page__empty-panel p {
  margin: 0;
  max-width: 28rem;
  font-size: 0.875rem;
  line-height: 1.5;
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
  font-weight: 600;
  font-size: 0.875rem;
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
  font-weight: 600;
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
  font-weight: 600;
  color: var(--color-primary);
  text-decoration: none;
}

.dt-lanes {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: var(--space-3);
}

.dt-lane--main {
  grid-column: 1 / -1;
}

.dt-lane__label {
  display: block;
  margin-bottom: var(--space-2);
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.dt-lane__chips,
.dt-flow-pipeline {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
}

.dt-flow-pipeline {
  flex-wrap: nowrap;
  overflow-x: auto;
  cursor: grab;
}

.dt-flow-pipeline:active {
  cursor: grabbing;
}

.dt-flow-pipeline__arrow {
  flex-shrink: 0;
  color: var(--color-text-muted);
  opacity: 0.7;
}

.dt-status-hit {
  border: none;
  padding: 0;
  background: transparent;
  cursor: pointer;
  border-radius: var(--radius-sm);
}

.dt-status-hit--on {
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary) 55%, transparent);
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
  font-weight: 600;
  color: var(--color-text-muted);
  box-shadow: 0 1px 0 var(--color-border);
}

.dt-trans-table td {
  padding: 0.45rem 0.65rem;
  vertical-align: middle;
  box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 60%, transparent);
}

.dt-action-pill {
  display: inline-block;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  font-weight: 600;
  font-size: 0.75rem;
  background: var(--color-primary-100);
  color: var(--color-primary-700);
}

.dt-guard {
  display: block;
  margin-top: 0.2rem;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.dt-trans-none,
.dt-trans-actor {
  font-size: 0.8125rem;
  color: var(--color-text);
}

.dt-status-page__loading,
.dt-status-page__empty {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

@media (max-width: 900px) {
  .dt-status-page__body,
  .dt-lanes {
    grid-template-columns: 1fr;
  }

  .dt-lane--main {
    grid-column: auto;
  }
}
</style>
