<script setup>
//
// superadmin/workspace-config/departments/:departmentId/evaluation — tab
// "Tiêu chí đánh giá" trong hub chi tiết phòng ban. Chỉ xem — thêm/sửa/xoá
// tiêu chí là việc của department_director tại
// manager.workspace-config.evaluation, super_admin không sửa thay. Dữ liệu
// lấy từ WorkspaceConfigDepartmentDetailHub (module WorkspaceConfig) qua
// inject theo tên chuỗi.
//
// Hai kiểu tiêu chí:
//   scale    — thang điểm nhiều mức (Xuất sắc 5 / Tốt 4 / Khá 3…)
//   behavior — cộng/trừ theo hành vi (Đi muộn −1 / Hoàn thành sớm +2…)
//
import { computed, inject, reactive, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import { formatDateTime } from '@/lib/formatTime';
import { useDragScroll } from '@/composables/useDragScroll';

const TYPE_LABELS = { scale: 'Thang điểm', behavior: 'Cộng/trừ' };
const TABLE_COLSPAN = 8;

const hub = inject('workspaceConfigDeptDetailHub', null);
const criteria = computed(() => hub?.evaluationCriteria?.value ?? []);
const loading = computed(() => hub?.loading?.value ?? false);

const selected = ref(null);
const typeGroupCollapsed = reactive({});
const tableWrap = ref(null);

useDragScroll(tableWrap);

function openView(criterion) {
  selected.value = selected.value?.id === criterion.id ? null : criterion;
}

function formatScore(score) {
  const n = Number(score);
  if (!Number.isFinite(n)) return '0';
  return Number.isInteger(n) ? String(n) : n.toFixed(1);
}

function criterionGroupKey(typeId) {
  return typeId == null || typeId === '' ? '__none__' : String(typeId);
}

function typeOrderIndex(typeId, list) {
  if (typeId == null || typeId === '') return Number.MAX_SAFE_INTEGER;
  const idx = list.findIndex((t) => String(t.id) === String(typeId));
  return idx >= 0 ? idx : Number.MAX_SAFE_INTEGER;
}

function compareCriteriaByType(a, b, typeOrderList) {
  const orderA = typeOrderIndex(a.criterion_type_id, typeOrderList);
  const orderB = typeOrderIndex(b.criterion_type_id, typeOrderList);
  if (orderA !== orderB) return orderA - orderB;
  return String(a.name ?? '').localeCompare(String(b.name ?? ''), 'vi');
}

function buildCriteriaGroups(criteriaList) {
  const groups = [];
  const map = new Map();
  for (const criterion of criteriaList) {
    const key = criterionGroupKey(criterion.criterion_type_id);
    if (!map.has(key)) {
      const entry = {
        key,
        type: criterion.criterion_type ?? null,
        criteria: [],
      };
      map.set(key, entry);
      groups.push(entry);
    }
    map.get(key).criteria.push(criterion);
  }
  return groups;
}

function groupTitle(type) {
  return type?.name ?? 'Chưa phân loại';
}

function groupScoringKinds(list) {
  const order = { scale: 0, behavior: 1 };
  return [...new Set(list.map((c) => c.type).filter(Boolean))].sort(
    (a, b) => (order[a] ?? 9) - (order[b] ?? 9),
  );
}

function groupScoringAccent(list) {
  const kinds = groupScoringKinds(list);
  if (kinds.length === 1) return kinds[0];
  if (kinds.length > 1) return 'mixed';
  return '';
}

function isTypeGroupCollapsed(key) {
  return Boolean(typeGroupCollapsed[key]);
}

function toggleTypeGroup(key) {
  typeGroupCollapsed[key] = !typeGroupCollapsed[key];
}

const criterionTypeOrder = computed(() => {
  const seen = new Map();
  for (const c of criteria.value) {
    const t = c.criterion_type;
    if (t?.id != null && !seen.has(String(t.id))) {
      seen.set(String(t.id), t);
    }
  }
  return [...seen.values()];
});

const criteriaGroups = computed(() => {
  const sorted = [...criteria.value].sort((a, b) =>
    compareCriteriaByType(a, b, criterionTypeOrder.value),
  );
  return buildCriteriaGroups(sorted);
});

const tableBodyRows = computed(() => {
  const rows = [];
  for (const group of criteriaGroups.value) {
    rows.push({
      kind: 'group',
      key: `group-${group.key}`,
      groupKey: group.key,
      title: groupTitle(group.type),
      code: group.type?.code ?? '',
      count: group.criteria.length,
      collapsed: isTypeGroupCollapsed(group.key),
      scoringAccent: groupScoringAccent(group.criteria),
      scoringKinds: groupScoringKinds(group.criteria),
    });
    if (!isTypeGroupCollapsed(group.key)) {
      for (const criterion of group.criteria) {
        rows.push({ kind: 'criterion', key: criterion.id, criterion });
      }
    }
  }
  return rows;
});
</script>

<template>
  <div class="dept-eval" :class="{ 'dept-eval--with-panel': selected }">
    <div class="dept-eval__main">
      <p v-if="loading" class="dept-eval__empty">Đang tải…</p>
      <p v-else-if="criteria.length === 0" class="dept-eval__empty">
        Phòng ban chưa có tiêu chí đánh giá nào.
      </p>

      <div v-else ref="tableWrap" class="dept-eval__table-wrap hide-scrollbar">
        <table class="dept-eval__table">
          <thead>
            <tr>
              <th>Tên tiêu chí</th>
              <th class="dept-eval__th--center">Số mức</th>
              <th class="dept-eval__th--center">Điểm tối đa</th>
              <th class="dept-eval__th--center">Trạng thái</th>
              <th class="dept-eval__th--center">Dùng trong ĐGNL</th>
              <th>Ngày tạo</th>
              <th class="dept-eval__th--center">Người tạo</th>
              <th class="dept-eval__th--center">Người cập nhật</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="entry in tableBodyRows" :key="entry.key">
              <tr
                v-if="entry.kind === 'group'"
                class="dept-eval__tr dept-eval__tr--group"
                @click.stop="toggleTypeGroup(entry.groupKey)"
              >
                <td :colspan="TABLE_COLSPAN" class="dept-eval__td dept-eval__td--group">
                  <div
                    class="dept-eval__group-inner"
                    :class="entry.scoringAccent ? `dept-eval__group-inner--${entry.scoringAccent}` : ''"
                  >
                    <span class="dept-eval__group-toggle" aria-hidden="true">
                      <AppIcon
                        :name="entry.collapsed ? 'chevronRight' : 'chevronDown'"
                        :size="16"
                        :stroke-width="1.75"
                      />
                    </span>
                    <span class="dept-eval__group-copy">
                      <span class="dept-eval__group-title">{{ entry.title }}</span>
                      <span v-if="entry.code" class="dept-eval__group-code">{{ entry.code }}</span>
                    </span>
                    <span v-if="entry.scoringKinds.length" class="dept-eval__group-badges">
                      <span
                        v-for="kind in entry.scoringKinds"
                        :key="kind"
                        class="dept-eval__badge dept-eval__badge--group"
                        :class="'dept-eval__badge--' + kind"
                      >
                        {{ TYPE_LABELS[kind] ?? kind }}
                      </span>
                    </span>
                    <span class="dept-eval__group-count">{{ entry.count }} tiêu chí</span>
                  </div>
                </td>
              </tr>
              <tr
                v-else
                class="dept-eval__tr"
                :class="{ 'dept-eval__tr--active': selected?.id === entry.criterion.id }"
                @click="openView(entry.criterion)"
              >
                <td class="dept-eval__td">{{ entry.criterion.name }}</td>
                <td class="dept-eval__td dept-eval__td--center">{{ entry.criterion.level_count }}</td>
                <td class="dept-eval__td dept-eval__td--center">{{ formatScore(entry.criterion.max_score) }}</td>
                <td class="dept-eval__td dept-eval__td--center">
                  <span class="dept-eval__status" :class="{ 'dept-eval__status--on': entry.criterion.is_active }">
                    <span class="dept-eval__status-dot" aria-hidden="true" />
                    {{ entry.criterion.is_active ? 'Đang dùng' : 'Đang tắt' }}
                  </span>
                </td>
                <td class="dept-eval__td dept-eval__td--center">
                  <span
                    class="dept-eval__switch"
                    :class="{ 'dept-eval__switch--on': entry.criterion.use_in_evaluation }"
                    role="switch"
                    :aria-checked="entry.criterion.use_in_evaluation ? 'true' : 'false'"
                    aria-label="Dùng trong ĐGNL"
                  >
                    <span class="dept-eval__switch-thumb" aria-hidden="true" />
                  </span>
                </td>
                <td class="dept-eval__td">{{ formatDateTime(entry.criterion.created_at) || '—' }}</td>
                <td class="dept-eval__td dept-eval__td--center">
                  <UserAvatarTip :user="entry.criterion.creator" label="Người tạo" />
                </td>
                <td class="dept-eval__td dept-eval__td--center">
                  <UserAvatarTip :user="entry.criterion.updater" label="Người cập nhật" />
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <aside v-if="selected" class="dept-eval__panel" aria-label="Chi tiết tiêu chí">
      <div class="dept-eval__panel-head">
        <h2 class="dept-eval__panel-title">Chi tiết tiêu chí</h2>
        <button type="button" class="dept-eval__icon-btn" aria-label="Đóng" @click="selected = null">
          <AppIcon name="close" :size="16" />
        </button>
      </div>

      <div class="dept-eval__rows">
        <div class="dept-eval__row">
          <span class="dept-eval__row-label">Tên tiêu chí</span>
          <span class="dept-eval__row-value">{{ selected.name }}</span>
        </div>
        <div class="dept-eval__row">
          <span class="dept-eval__row-label">Loại tiêu chí</span>
          <span class="dept-eval__row-value">{{ selected.criterion_type?.name || '—' }}</span>
        </div>
        <div v-if="selected.criterion_type?.description" class="dept-eval__row">
          <span class="dept-eval__row-label">Mô tả loại</span>
          <span class="dept-eval__row-value">{{ selected.criterion_type.description }}</span>
        </div>
        <div class="dept-eval__row">
          <span class="dept-eval__row-label">Cách chấm</span>
          <span class="dept-eval__row-value">
            <span
              class="dept-eval__badge"
              :class="'dept-eval__badge--' + selected.type"
            >
              {{ TYPE_LABELS[selected.type] ?? selected.type }}
            </span>
          </span>
        </div>
        <div v-if="selected.description" class="dept-eval__row">
          <span class="dept-eval__row-label">Mô tả</span>
          <span class="dept-eval__row-value">{{ selected.description }}</span>
        </div>
        <div class="dept-eval__row">
          <span class="dept-eval__row-label">Trạng thái</span>
          <span class="dept-eval__row-value">
            <span class="dept-eval__status" :class="{ 'dept-eval__status--on': selected.is_active }">
              <span class="dept-eval__status-dot" aria-hidden="true" />
              {{ selected.is_active ? 'Đang dùng' : 'Đang tắt' }}
            </span>
          </span>
        </div>
        <div class="dept-eval__row">
          <span class="dept-eval__row-label">Dùng trong ĐGNL</span>
          <span class="dept-eval__row-value">{{ selected.use_in_evaluation ? 'Có' : 'Không' }}</span>
        </div>
        <div class="dept-eval__row">
          <span class="dept-eval__row-label">Điểm tối đa</span>
          <span class="dept-eval__row-value">{{ formatScore(selected.max_score) }}</span>
        </div>
        <div class="dept-eval__row">
          <span class="dept-eval__row-label">Ngày tạo</span>
          <span class="dept-eval__row-value">{{ formatDateTime(selected.created_at) || '—' }}</span>
        </div>
        <div class="dept-eval__row">
          <span class="dept-eval__row-label">Người tạo</span>
          <span class="dept-eval__row-value">
            <UserAvatarTip :user="selected.creator" label="Người tạo" />
          </span>
        </div>
        <div class="dept-eval__row">
          <span class="dept-eval__row-label">Người cập nhật</span>
          <span class="dept-eval__row-value">
            <UserAvatarTip :user="selected.updater" label="Người cập nhật" />
          </span>
        </div>
      </div>

      <h3 class="dept-eval__levels-title">
        {{ selected.type === 'scale' ? 'Thang điểm đánh giá' : 'Hành vi & điểm' }}
      </h3>
      <ul class="dept-eval__levels-list">
        <li v-for="(lv, idx) in (selected.levels ?? [])" :key="idx" class="dept-eval__level-item">
          <span class="dept-eval__level-copy">
            <span class="dept-eval__level-label">
              <span v-if="lv.code" class="dept-eval__level-code">{{ lv.code }}</span>
              {{ lv.label }}
            </span>
            <span v-if="lv.description" class="dept-eval__level-note">{{ lv.description }}</span>
          </span>
          <span class="dept-eval__level-score" :class="{ 'dept-eval__level-score--neg': lv.score < 0 }">
            {{ lv.score > 0 ? '+' : '' }}{{ formatScore(lv.score) }}
          </span>
        </li>
      </ul>
    </aside>
  </div>
</template>

<style scoped>
.dept-eval {
  height: 100%;
  display: flex;
  gap: var(--space-4);
  overflow: hidden;
}

.dept-eval__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.dept-eval__empty {
  margin: 0;
  padding: var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.875rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.dept-eval__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.dept-eval__table {
  width: 100%;
  min-width: 68rem;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.dept-eval__table thead th {
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

.dept-eval__th--center {
  text-align: center;
}

.dept-eval__td {
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  vertical-align: middle;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.dept-eval__td--center {
  text-align: center;
}

.dept-eval__td--group {
  padding: 0;
  overflow: visible;
  white-space: normal;
}

.dept-eval__tr {
  cursor: pointer;
}

.dept-eval__tr--group {
  cursor: pointer;
}

.dept-eval__tr:hover .dept-eval__td {
  background: var(--color-surface-muted);
}

.dept-eval__tr--group:hover .dept-eval__group-inner {
  background: var(--color-surface-muted);
}

.dept-eval__tr--active .dept-eval__td {
  background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface));
}

.dept-eval__group-inner {
  position: relative;
  display: flex;
  box-sizing: border-box;
  width: 100%;
  align-items: center;
  gap: 0.625rem;
  padding: 0.5rem var(--space-4);
  padding-left: calc(var(--space-4) + var(--space-2) + 3px);
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text);
  background: color-mix(in srgb, var(--color-text) 4%, var(--color-surface));
  box-shadow: 0 1px 0 var(--color-border);
}

.dept-eval__group-inner::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-4);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.dept-eval__group-inner--scale::before {
  background: var(--color-primary);
}

.dept-eval__group-inner--behavior::before {
  background: var(--color-warning, #d97706);
}

.dept-eval__group-inner--mixed::before {
  background: linear-gradient(
    to bottom,
    var(--color-primary) 0%,
    var(--color-primary) 50%,
    var(--color-warning, #d97706) 50%,
    var(--color-warning, #d97706) 100%
  );
}

.dept-eval__group-toggle {
  display: inline-flex;
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.dept-eval__group-copy {
  display: flex;
  min-width: 0;
  flex: 1;
  align-items: baseline;
  gap: 0.5rem;
}

.dept-eval__group-title {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dept-eval__group-code {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 500;
}

.dept-eval__group-badges {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: 0.375rem;
}

.dept-eval__group-count {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 500;
}

.dept-eval__kind {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.dept-eval__kind-code {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.dept-eval__switch {
  position: relative;
  display: inline-block;
  flex-shrink: 0;
  width: 2.25rem;
  height: 1.25rem;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-text-muted) 35%, var(--color-surface-muted));
  vertical-align: middle;
}

.dept-eval__switch--on {
  background: var(--color-primary);
}

.dept-eval__switch-thumb {
  position: absolute;
  top: 0.125rem;
  left: 0.125rem;
  width: 1rem;
  height: 1rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.dept-eval__switch--on .dept-eval__switch-thumb {
  transform: translateX(1rem);
}

.dept-eval__badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  vertical-align: middle;
  padding: 0.125rem 0.5rem;
  border-radius: 0;
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.4;
  white-space: nowrap;
}

.dept-eval__badge--cell {
  box-sizing: border-box;
  width: 9rem;
  max-width: 100%;
}

.dept-eval__badge--group {
  padding: 0.0625rem 0.4375rem;
  font-size: 0.6875rem;
}

.dept-eval__badge--scale {
  background: color-mix(in srgb, var(--color-primary) 12%, transparent);
  color: var(--color-primary);
}

.dept-eval__badge--behavior {
  background: color-mix(in srgb, var(--color-warning, #d97706) 12%, transparent);
  color: var(--color-warning, #d97706);
}

.dept-eval__status {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  color: var(--color-text-muted);
}

.dept-eval__status-dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.dept-eval__status--on {
  color: var(--color-success-tint-fg);
}

.dept-eval__status--on .dept-eval__status-dot {
  background: var(--color-success);
}

.dept-eval__panel {
  flex-shrink: 0;
  width: 22rem;
  overflow-y: auto;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
}

.dept-eval__panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
}

.dept-eval__panel-title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 700;
}

.dept-eval__icon-btn {
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

.dept-eval__icon-btn:hover {
  background: var(--color-surface-muted);
}

.dept-eval__rows {
  display: flex;
  flex-direction: column;
}

.dept-eval__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.dept-eval__row:last-child {
  box-shadow: none;
}

.dept-eval__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.dept-eval__row-label::after {
  content: ':';
}

.dept-eval__row-value {
  color: var(--color-text);
  font-style: italic;
  text-align: right;
  overflow-wrap: anywhere;
}

.dept-eval__levels-title {
  margin: var(--space-4) 0 var(--space-2);
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 700;
}

.dept-eval__levels-list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.dept-eval__level-item {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.dept-eval__level-item:last-child {
  box-shadow: none;
}

.dept-eval__level-copy {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 0;
}

.dept-eval__level-code {
  color: var(--color-text-muted);
  margin-right: 0.375rem;
}

.dept-eval__level-note {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.dept-eval__level-score {
  flex-shrink: 0;
  color: var(--color-success-tint-fg);
  font-weight: 700;
}

.dept-eval__level-score--neg {
  color: var(--color-danger);
}

@media (max-width: 1024px) {
  .dept-eval {
    flex-direction: column;
  }

  .dept-eval__panel {
    width: 100%;
    max-height: 42%;
  }
}
</style>
