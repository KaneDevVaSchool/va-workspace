<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import DonTuStatusBadge from '../../components/DonTuStatusBadge.vue';
import { fetchStatusSet, fetchStatusSets } from '../../api/donTuConfigApi.js';
import { ACTION_LABEL, ACTOR_LABEL, GROUP_KIND_LABEL } from '../../constants/statusTone.js';

const auth = useAuthStore();

const canConfig = computed(
  () =>
    auth.can('don_tu.manage_department') ||
    auth.can('don_tu.manage_system') ||
    auth.can('don_tu.*'),
);

const sets = ref([]);
const selectedId = ref(null);
const detail = ref(null);
const loadingList = ref(false);
const loadingDetail = ref(false);

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

async function loadSets() {
  loadingList.value = true;
  try {
    sets.value = await fetchStatusSets();
    if (!selectedId.value && sets.value.length) {
      selectedId.value = sets.value[0].id;
    }
  } catch (err) {
    showClientToast(err.response?.data?.message || 'Không tải được bộ trạng thái.', 'error');
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
  try {
    detail.value = await fetchStatusSet(id);
  } catch (err) {
    showClientToast(err.response?.data?.message || 'Không tải chi tiết bộ trạng thái.', 'error');
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
</script>

<template>
  <div v-if="!canConfig" class="dt-config-deny">
    <AppIcon name="lock" :size="28" :stroke-width="1.75" />
    <p>Bạn cần quyền cấu hình đơn từ phòng ban để xem bộ trạng thái.</p>
  </div>

  <div v-else class="dt-status-page">
    <header class="dt-status-page__hero">
      <div class="dt-status-page__hero-text">
        <p class="dt-status-page__kicker">Master data · Matrix V3</p>
        <h2 class="dt-status-page__hero-title">Bộ trạng thái vĩ mô</h2>
        <p class="dt-status-page__hero-desc">
          Mỗi quy trình gắn một bộ trạng thái. Hồ sơ hiển thị nhãn tiếng Việt; engine chuyển trạng thái theo ma trận bên dưới.
        </p>
      </div>
      <div class="dt-status-page__hero-stat" aria-hidden="true">
        <span class="dt-status-page__stat-num">{{ detail?.statuses?.length ?? '—' }}</span>
        <span class="dt-status-page__stat-label">trạng thái</span>
      </div>
    </header>

    <div class="dt-status-page__body">
      <aside class="dt-status-page__sets">
        <h3 class="dt-status-page__aside-title">Danh mục bộ</h3>
        <ul v-if="!loadingList" class="dt-status-page__set-list">
          <li v-for="set in sets" :key="set.id">
            <button
              type="button"
              class="dt-set-card"
              :class="{ 'dt-set-card--active': selectedId === set.id }"
              @click="selectedId = set.id"
            >
              <span class="dt-set-card__name">{{ set.name }}</span>
              <span class="dt-set-card__meta">
                <code>{{ set.code }}</code>
                · {{ set.status_count }} trạng thái
              </span>
              <span v-if="set.is_system" class="dt-set-card__tag">Hệ thống</span>
            </button>
          </li>
        </ul>
        <p v-else class="dt-status-page__loading">Đang tải…</p>
      </aside>

      <div v-if="loadingDetail" class="dt-status-page__loading dt-status-page__main">
        Đang tải chi tiết…
      </div>

      <div v-else-if="detail" class="dt-status-page__main">
        <section class="dt-flow-card">
          <h3 class="dt-flow-card__title">Luồng chính</h3>
          <div class="dt-flow-pipeline hide-scrollbar">
            <template v-for="(st, idx) in mainFlow" :key="st.code">
              <DonTuStatusBadge :code="st.code" :label="st.label" />
              <AppIcon
                v-if="idx < mainFlow.length - 1"
                name="chevronRight"
                class="dt-flow-pipeline__arrow"
                :size="18"
                :stroke-width="2"
              />
            </template>
          </div>
        </section>

        <div v-if="conditional.length" class="dt-status-page__row">
          <section class="dt-mini-card dt-mini-card--conditional">
            <h3 class="dt-mini-card__title">{{ GROUP_KIND_LABEL.conditional }}</h3>
            <div class="dt-mini-card__chips">
              <DonTuStatusBadge
                v-for="st in conditional"
                :key="st.code"
                compact
                :code="st.code"
                :label="st.label"
              />
            </div>
          </section>
        </div>

        <section v-if="terminal.length" class="dt-mini-card dt-mini-card--terminal">
          <h3 class="dt-mini-card__title">{{ GROUP_KIND_LABEL.terminal }}</h3>
          <div class="dt-mini-card__chips">
            <DonTuStatusBadge
              v-for="st in terminal"
              :key="st.code"
              compact
              :code="st.code"
              :label="st.label"
            />
          </div>
        </section>

        <section class="dt-trans-table-wrap hide-scrollbar">
          <h3 class="dt-flow-card__title">Ma trận chuyển trạng thái</h3>
          <table class="dt-trans-table">
            <thead>
              <tr>
                <th>Từ</th>
                <th>Hành động</th>
                <th>Đến</th>
                <th>Ai thực hiện</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="tr in detail.transitions" :key="tr.id">
                <td>
                  <DonTuStatusBadge
                    v-if="tr.from_code !== 'NONE'"
                    compact
                    :code="tr.from_code"
                    :label="detail.statuses.find((s) => s.code === tr.from_code)?.label"
                  />
                  <span v-else class="dt-trans-none">— (chưa có hồ sơ)</span>
                </td>
                <td><span class="dt-action-pill">{{ actionLabel(tr.action_key) }}</span></td>
                <td>
                  <DonTuStatusBadge
                    compact
                    :code="tr.to_code"
                    :label="detail.statuses.find((s) => s.code === tr.to_code)?.label"
                  />
                </td>
                <td class="dt-trans-actor">{{ actorLabel(tr.actor_policy) }}</td>
              </tr>
            </tbody>
          </table>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
.dt-config-deny {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-8);
  color: var(--color-text-muted);
  text-align: center;
}

.dt-status-page__hero {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-4);
  margin-bottom: var(--space-5);
  padding: var(--space-5);
  border-radius: var(--radius-md);
  background: linear-gradient(
    135deg,
    color-mix(in srgb, var(--color-primary) 8%, var(--color-surface)) 0%,
    var(--color-surface-muted) 100%
  );
  box-shadow: var(--shadow-sm);
}

.dt-status-page__kicker {
  margin: 0 0 var(--space-1);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--color-primary);
}

.dt-status-page__hero-title {
  margin: 0 0 var(--space-2);
  font-size: 1.375rem;
  font-weight: 700;
  color: var(--color-text);
}

.dt-status-page__hero-desc {
  margin: 0;
  max-width: 40rem;
  font-size: 0.9375rem;
  line-height: 1.5;
  color: var(--color-text-muted);
}

.dt-status-page__hero-stat {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-width: 5.5rem;
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.dt-status-page__stat-num {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--color-primary);
  line-height: 1;
}

.dt-status-page__stat-label {
  margin-top: var(--space-1);
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.dt-status-page__body {
  display: grid;
  grid-template-columns: minmax(14rem, 18rem) minmax(0, 1fr);
  gap: var(--space-5);
  min-height: 0;
}

.dt-status-page__aside-title {
  margin: 0 0 var(--space-3);
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text-muted);
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
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-2));
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
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
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 35%, var(--color-border));
}

.dt-set-card__name {
  display: block;
  font-weight: 600;
  font-size: 0.875rem;
}

.dt-set-card__meta {
  display: block;
  margin-top: var(--space-1);
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.dt-set-card__meta code {
  font-size: 0.6875rem;
}

.dt-set-card__tag {
  display: inline-block;
  margin-top: var(--space-2);
  padding: 0.125rem 0.5rem;
  border-radius: var(--radius-sm);
  font-size: 0.6875rem;
  font-weight: 600;
  background: color-mix(in srgb, var(--color-info) 12%, var(--color-surface));
  color: var(--color-info);
}

.dt-flow-card {
  margin-bottom: var(--space-4);
  padding: var(--space-4);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.dt-flow-card__title {
  margin: 0 0 var(--space-3);
  font-size: 0.9375rem;
  font-weight: 600;
}

.dt-flow-pipeline {
  display: flex;
  flex-wrap: nowrap;
  align-items: center;
  gap: var(--space-2);
  overflow-x: auto;
  padding-bottom: var(--space-1);
}

.dt-flow-pipeline__arrow {
  flex-shrink: 0;
  color: var(--color-text-muted);
  opacity: 0.7;
}

.dt-mini-card {
  position: relative;
  margin-bottom: var(--space-4);
  padding: var(--space-4) var(--space-4) var(--space-4) calc(var(--space-2) + 3px + var(--space-3));
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.dt-mini-card::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-info);
}

.dt-mini-card--conditional::before {
  background: #7c3aed;
}

.dt-mini-card--terminal::before {
  background: var(--color-success);
}

.dt-mini-card__title {
  margin: 0 0 var(--space-3);
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.dt-mini-card__chips {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}

.dt-trans-table-wrap {
  overflow-x: auto;
  padding: var(--space-4);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.dt-trans-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.dt-trans-table th {
  text-align: left;
  padding: 0.5rem 0.75rem;
  font-weight: 600;
  color: var(--color-text-muted);
  box-shadow: 0 1px 0 var(--color-border);
}

.dt-trans-table td {
  padding: 0.65rem 0.75rem;
  vertical-align: middle;
  box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 60%, transparent);
}

.dt-action-pill {
  display: inline-block;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  font-weight: 600;
  font-size: 0.75rem;
  background: color-mix(in srgb, var(--color-primary) 12%, var(--color-surface));
  color: var(--color-primary);
}

.dt-trans-none {
  font-size: 0.75rem;
  color: var(--color-text-muted);
  font-style: italic;
}

.dt-trans-actor {
  color: var(--color-text-muted);
}

.dt-status-page__loading {
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

@media (max-width: 900px) {
  .dt-status-page__body {
    grid-template-columns: 1fr;
  }
}
</style>
