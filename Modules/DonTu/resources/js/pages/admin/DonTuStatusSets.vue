<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import { useDragScroll } from '@/composables/useDragScroll.js';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import DonTuConfigHubCards from '../../components/DonTuConfigHubCards.vue';
import DonTuStatusBadge from '../../components/DonTuStatusBadge.vue';
import { createStatus, fetchStatusSet, fetchStatusSets } from '../../api/donTuConfigApi.js';
import {
  ACTION_LABEL,
  ACTOR_LABEL,
  GROUP_KIND_LABEL,
  friendlySetName,
  guardHint,
  humanSetDescription,
} from '../../constants/statusTone.js';

const auth = useAuthStore();
const router = useRouter();

const canConfig = computed(
  () =>
    auth.can('don_tu.manage_department') ||
    auth.can('don_tu.manage_system') ||
    auth.can('don_tu.*'),
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

const showSetList = computed(() => sets.value.length > 1);

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
    showClientToast(err.response?.data?.message || 'Không tải được danh sách trạng thái.', 'error');
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
    showClientToast(err.response?.data?.message || 'Không tải được chi tiết trạng thái.', 'error');
    detail.value = null;
  } finally {
    loadingDetail.value = false;
  }
}

watch(selectedId, (id) => loadDetail(id));

onMounted(async () => {
  if (!canConfig.value) {
    return;
  }
  await loadSets();
});

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
}

async function copyCode(code) {
  try {
    await navigator.clipboard.writeText(code);
    showClientToast('Đã sao chép mã.', 'success');
  } catch {
    showClientToast('Không sao chép được.', 'error');
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

function errorText(err, fallback) {
  const errors = err.response?.data?.errors;
  if (errors) {
    const first = Object.values(errors)[0];
    if (Array.isArray(first) && first[0]) return first[0];
  }
  return err.response?.data?.message || fallback;
}

async function submitCreate() {
  if (!selectedId.value) return;
  if (!createForm.value.label.trim()) {
    showClientToast('Nhập tên trạng thái.', 'error');
    return;
  }
  if (!createForm.value.from_code) {
    showClientToast('Chọn trạng thái phía trước.', 'error');
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
    showClientToast('Đã thêm trạng thái.', 'success');
  } catch (err) {
    showClientToast(errorText(err, 'Không thêm được trạng thái.'), 'error');
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

  <div v-else class="dt-status-page">
    <DonTuConfigHubCards />

    <div class="dt-status-page__body" :class="{ 'dt-status-page__body--solo': !showSetList }">
      <aside v-if="showSetList" class="dt-status-page__sets">
        <input
          v-model="setQuery"
          type="search"
          class="dt-status-page__set-search"
          placeholder="Tìm tên bộ trạng thái"
          aria-label="Tìm bộ trạng thái"
        />
        <ul v-if="!loadingList" class="dt-status-page__set-list">
          <li v-if="!filteredSets.length">
            <p class="dt-status-page__empty">Không thấy bộ nào.</p>
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

      <div v-if="loadingDetail" class="dt-status-page__loading dt-status-page__main">
        Đang tải…
      </div>

      <div v-else-if="detail" class="dt-status-page__main">
        <div class="dt-status-page__detail-head">
          <div class="dt-status-page__detail-copy">
            <h2 class="dt-status-page__detail-title">{{ detailTitle }}</h2>
            <p v-if="detailDescription" class="dt-status-page__detail-desc">{{ detailDescription }}</p>
          </div>
          <div class="dt-status-page__stats">
            <button type="button" class="dt-admin-list__primary" @click="openCreate">
              <AppIcon name="plus" :size="16" :stroke-width="2" />
              Thêm trạng thái
            </button>
            <span>{{ detail.statuses?.length ?? 0 }} trạng thái</span>
            <span>{{ detail.transitions?.length ?? 0 }} cách đổi</span>
            <span>{{ detail.workflow_count ?? 0 }} quy trình</span>
            <span v-if="detail.is_system" class="dt-set-card__tag">Có sẵn</span>
            <button type="button" class="dt-link-btn" @click="copyCode(detail.code)">Sao chép mã</button>
          </div>
        </div>

        <section v-if="detail.workflows?.length" class="dt-panel dt-workflow-strip">
          <div class="dt-panel__row">
            <h3 class="dt-panel__title">Quy trình đang dùng</h3>
            <router-link :to="{ name: 'manager.don-tu.workflows' }" class="dt-workflow-strip__link">
              Mở danh sách
              <AppIcon name="chevronRight" :size="16" :stroke-width="2" />
            </router-link>
          </div>
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
        </section>

        <section class="dt-panel">
          <h3 class="dt-panel__title">Hồ sơ đi qua các trạng thái này</h3>
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
        </section>

        <section class="dt-panel">
          <div class="dt-panel__row">
            <h3 class="dt-panel__title">Ai được bấm gì</h3>
            <button v-if="focusCode" type="button" class="dt-link-btn" @click="focusCode = ''">
              Xem tất cả
            </button>
            <input
              v-model="transitionQuery"
              type="search"
              class="dt-trans-table__search"
              placeholder="Tìm người, việc làm hoặc trạng thái"
              aria-label="Tìm cách đổi trạng thái"
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
        </section>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="createOpen" class="dt-modal-backdrop" @click.self="createOpen = false">
        <div class="dt-modal" role="dialog" aria-labelledby="dt-status-create-title">
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
  background: linear-gradient(
    180deg,
    var(--color-surface),
    color-mix(in srgb, var(--color-primary) 4%, var(--color-surface))
  );
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
  background: linear-gradient(
    135deg,
    color-mix(in srgb, var(--color-primary) 6%, var(--color-surface)),
    var(--color-surface) 55%,
    color-mix(in srgb, var(--color-info) 6%, var(--color-surface))
  );
  box-shadow: var(--shadow-sm);
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
  background: linear-gradient(
    135deg,
    color-mix(in srgb, var(--color-primary) 14%, var(--color-surface)),
    color-mix(in srgb, var(--color-info) 8%, var(--color-surface))
  );
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
  background: linear-gradient(
    180deg,
    color-mix(in srgb, var(--color-info) 16%, var(--color-surface)),
    color-mix(in srgb, var(--color-info) 6%, var(--color-surface))
  );
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
  background: linear-gradient(
    145deg,
    color-mix(in srgb, var(--color-primary) 6%, var(--color-surface)) 0%,
    var(--color-surface) 46%,
    color-mix(in srgb, var(--color-info) 7%, var(--color-surface)) 100%
  );
  box-shadow: var(--shadow-sm);
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
  background: linear-gradient(
    180deg,
    color-mix(in srgb, var(--color-primary) 8%, var(--color-surface)),
    color-mix(in srgb, var(--color-info) 6%, var(--color-surface))
  );
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
  background: linear-gradient(
    180deg,
    color-mix(in srgb, var(--color-primary) 16%, var(--color-surface)),
    color-mix(in srgb, var(--color-primary) 6%, var(--color-surface))
  );
  color: var(--color-primary);
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
