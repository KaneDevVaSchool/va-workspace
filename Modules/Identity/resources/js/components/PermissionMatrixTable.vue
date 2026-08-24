<script setup>
//
// Bảng chính: role x permission key, group theo module (accordion), header
// sticky khi cuộn dọc. Ô đọc trực tiếp từ props.matrix (PermissionService::matrixFor()),
// không tự suy luận lại ở frontend. Search/lọc module/phân trang xử lý ở
// client vì backend trả nguyên catalog (không phân trang).
//
import { computed, reactive, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import PermissionCell from './PermissionCell.vue';

const props = defineProps({
  roles: { type: Array, required: true }, // [{ code, label }]
  modules: { type: Array, required: true }, // [{ key, label }]
  permissions: { type: Array, required: true }, // [{ key, label, description, module, reserved }]
  matrix: { type: Object, required: true }, // { [roleCode]: { [key]: cell } }
  pendingCells: { type: Object, default: () => ({}) }, // { "roleCode|key": true }
  activeKey: { type: String, default: null }, // "roleCode|key" của ô đang mở panel chi tiết
});
const emit = defineEmits(['toggle', 'inspect']);

const expanded = reactive({});
const searchTerm = ref('');
const moduleFilter = ref('all');
const statusFilter = ref(null); // null | 'granted' | 'denied' | 'reserved' — lọc theo legend, không chọn được 'override'
const page = ref(1);
const perPage = ref(20);

function isExpanded(moduleLabel) {
  return expanded[moduleLabel] !== false; // mặc định mở
}

function toggleModule(moduleLabel) {
  expanded[moduleLabel] = !isExpanded(moduleLabel);
}

function toggleStatusFilter(status) {
  statusFilter.value = statusFilter.value === status ? null : status;
}

function permissionMatchesStatus(perm) {
  if (!statusFilter.value) return true;
  // Hiển thị dòng quyền nếu CÓ ÍT NHẤT 1 role khớp trạng thái đang lọc
  // (bảng tổ chức theo dòng permission x cột role, không theo từng ô).
  return props.roles.some((role) => {
    const cell = cellFor(role.code, perm.key);
    if (statusFilter.value === 'reserved') return cell.reserved;
    if (cell.reserved) return false;
    return statusFilter.value === 'granted' ? cell.effective : !cell.effective;
  });
}

const filteredPermissions = computed(() => {
  const term = searchTerm.value.trim().toLowerCase();
  return props.permissions.filter((perm) => {
    if (moduleFilter.value !== 'all' && perm.module !== moduleFilter.value) return false;
    if (!permissionMatchesStatus(perm)) return false;
    if (!term) return true;
    return (
      perm.key.toLowerCase().includes(term) ||
      perm.label.toLowerCase().includes(term) ||
      (perm.description ?? '').toLowerCase().includes(term)
    );
  });
});

const totalCount = computed(() => filteredPermissions.value.length);
const totalPages = computed(() => Math.max(1, Math.ceil(totalCount.value / perPage.value)));

watch([searchTerm, moduleFilter, statusFilter], () => {
  page.value = 1;
});

watch(totalPages, (max) => {
  if (page.value > max) page.value = max;
});

const pagedPermissions = computed(() => {
  const start = (page.value - 1) * perPage.value;
  return filteredPermissions.value.slice(start, start + perPage.value);
});

const groupedPermissions = computed(() => {
  const groups = {};
  for (const perm of pagedPermissions.value) {
    if (!groups[perm.module]) groups[perm.module] = [];
    groups[perm.module].push(perm);
  }
  return props.modules
    .map((m) => ({ module: m, permissions: groups[m.label] ?? groups[m.key] ?? [] }))
    .filter((g) => g.permissions.length > 0);
});

const rangeLabel = computed(() => {
  if (totalCount.value === 0) return 'Không có quyền nào phù hợp';
  const start = (page.value - 1) * perPage.value + 1;
  const end = Math.min(page.value * perPage.value, totalCount.value);
  return `Hiển thị ${start}–${end} trong tổng số ${totalCount.value} quyền`;
});

const pageNumbers = computed(() => {
  const total = totalPages.value;
  const current = page.value;
  const pages = [];
  const windowSize = 1;

  for (let p = 1; p <= total; p++) {
    if (p === 1 || p === total || Math.abs(p - current) <= windowSize) {
      pages.push(p);
    } else if (pages[pages.length - 1] !== '…') {
      pages.push('…');
    }
  }
  return pages;
});

function goToPage(p) {
  if (p === '…' || p < 1 || p > totalPages.value) return;
  page.value = p;
}

function cellFor(roleCode, key) {
  return props.matrix?.[roleCode]?.[key] ?? {
    default: false,
    effective: false,
    reserved: false,
    global_override: null,
    scoped_override: null,
    effective_source: 'config',
  };
}

function isPending(roleCode, key) {
  return !!props.pendingCells[`${roleCode}|${key}`];
}

function isActive(roleCode, key) {
  return props.activeKey === `${roleCode}|${key}`;
}
</script>

<template>
  <div class="perm-table-shell">
    <div class="perm-table-shell__toolbar">
      <div class="perm-table-shell__search">
        <label class="perm-table-shell__search-label" for="perm-search">Tìm quyền</label>
        <div class="perm-table-shell__search-input-wrap">
          <AppIcon name="search" :size="16" class="perm-table-shell__search-icon" />
          <input
            id="perm-search"
            v-model="searchTerm"
            type="text"
            class="perm-table-shell__search-input"
            placeholder="Ví dụ: quản lý nhóm"
          />
        </div>
      </div>

      <div class="perm-table-shell__module-filter">
        <label class="perm-table-shell__module-label" for="perm-module-filter">Module</label>
        <select id="perm-module-filter" v-model="moduleFilter" class="perm-table-shell__module-select">
          <option value="all">Tất cả</option>
          <option v-for="m in modules" :key="m.key" :value="m.label">{{ m.label }}</option>
        </select>
      </div>

      <div class="perm-table-shell__legend">
        <button
          type="button"
          class="perm-table-shell__legend-item perm-table-shell__legend-item--btn"
          :class="{ 'perm-table-shell__legend-item--active': statusFilter === 'granted' }"
          :aria-pressed="statusFilter === 'granted'"
          @click="toggleStatusFilter('granted')"
        >
          <AppIcon name="check" :size="14" class="perm-table-shell__legend-icon perm-table-shell__legend-icon--granted" />
          Được cấp
        </button>
        <button
          type="button"
          class="perm-table-shell__legend-item perm-table-shell__legend-item--btn"
          :class="{ 'perm-table-shell__legend-item--active': statusFilter === 'denied' }"
          :aria-pressed="statusFilter === 'denied'"
          @click="toggleStatusFilter('denied')"
        >
          <AppIcon name="minus" :size="14" class="perm-table-shell__legend-icon perm-table-shell__legend-icon--denied" />
          Không được cấp
        </button>
        <span class="perm-table-shell__legend-item">
          <span class="perm-table-shell__legend-dot" />
          Có sửa riêng
        </span>
        <button
          type="button"
          class="perm-table-shell__legend-item perm-table-shell__legend-item--btn"
          :class="{ 'perm-table-shell__legend-item--active': statusFilter === 'reserved' }"
          :aria-pressed="statusFilter === 'reserved'"
          @click="toggleStatusFilter('reserved')"
        >
          <AppIcon name="lock" :size="14" class="perm-table-shell__legend-icon" />
          Quyền hệ thống
        </button>
      </div>
    </div>

    <div class="perm-table-wrap">
      <table class="perm-table">
        <thead>
          <tr>
            <th class="perm-table__key-col">Tên quyền</th>
            <th v-for="role in roles" :key="role.code" class="perm-table__role-col">
              {{ role.label }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="groupedPermissions.length === 0">
            <td :colspan="roles.length + 1" class="perm-table__empty">
              Không tìm thấy quyền nào phù hợp với bộ lọc hiện tại.
            </td>
          </tr>
          <template v-for="group in groupedPermissions" :key="group.module.key">
            <tr class="perm-table__group-row">
              <td :colspan="roles.length + 1">
                <button type="button" class="perm-table__group-toggle" @click="toggleModule(group.module.label)">
                  <AppIcon
                    name="chevronDown"
                    :size="14"
                    class="perm-table__group-caret"
                    :class="{ 'perm-table__group-caret--collapsed': !isExpanded(group.module.label) }"
                  />
                  {{ group.module.label }}
                  <span class="perm-table__group-count">({{ group.permissions.length }})</span>
                </button>
              </td>
            </tr>
            <template v-if="isExpanded(group.module.label)">
              <tr v-for="perm in group.permissions" :key="perm.key">
                <td class="perm-table__key-col">
                  <button
                    type="button"
                    class="perm-table__key-btn"
                    :aria-label="`Xem chi tiết quyền ${perm.label}`"
                    @click="roles[0] && emit('inspect', { roleCode: roles[0].code, permissionKey: perm.key, cell: cellFor(roles[0].code, perm.key) })"
                  >
                    <div class="perm-table__key-label">{{ perm.label }}</div>
                    <div class="perm-table__key-desc">{{ perm.description || perm.key }}</div>
                  </button>
                </td>
                <td v-for="role in roles" :key="role.code" class="perm-table__cell-col">
                  <PermissionCell
                    :cell="cellFor(role.code, perm.key)"
                    :loading="isPending(role.code, perm.key)"
                    :active="isActive(role.code, perm.key)"
                    @toggle="emit('toggle', { roleCode: role.code, permissionKey: perm.key, cell: cellFor(role.code, perm.key) })"
                    @inspect="emit('inspect', { roleCode: role.code, permissionKey: perm.key, cell: cellFor(role.code, perm.key) })"
                  />
                </td>
              </tr>
            </template>
          </template>
        </tbody>
      </table>
    </div>

    <div class="perm-table-shell__pagination">
      <span class="perm-table-shell__range">{{ rangeLabel }}</span>

      <div v-if="totalPages > 1 || totalCount > 20" class="perm-table-shell__pagination-controls">
        <select v-model.number="perPage" class="perm-table-shell__per-page">
          <option :value="20">20 / trang</option>
          <option :value="50">50 / trang</option>
          <option :value="100">100 / trang</option>
        </select>

        <button
          type="button"
          class="perm-table-shell__page-btn"
          :disabled="page <= 1"
          aria-label="Trang trước"
          @click="goToPage(page - 1)"
        >
          <AppIcon name="chevronLeft" :size="16" />
        </button>

        <button
          v-for="p in pageNumbers"
          :key="`${p}`"
          type="button"
          class="perm-table-shell__page-btn"
          :class="{ 'perm-table-shell__page-btn--active': p === page, 'perm-table-shell__page-btn--ellipsis': p === '…' }"
          :disabled="p === '…'"
          @click="goToPage(p)"
        >
          {{ p }}
        </button>

        <button
          type="button"
          class="perm-table-shell__page-btn"
          :disabled="page >= totalPages"
          aria-label="Trang sau"
          @click="goToPage(page + 1)"
        >
          <AppIcon name="chevronRight" :size="16" />
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.perm-table-shell {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.perm-table-shell__toolbar {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: var(--space-3);
}

.perm-table-shell__search {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  flex: 1 1 16rem;
  min-width: 14rem;
}

.perm-table-shell__search-label,
.perm-table-shell__module-label {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
  white-space: nowrap;
}

.perm-table-shell__search-input-wrap {
  position: relative;
}

.perm-table-shell__search-icon {
  position: absolute;
  top: 50%;
  left: 0.75rem;
  transform: translateY(-50%);
  color: var(--color-text-muted);
}

.perm-table-shell__search-input {
  width: 100%;
  padding: 0.5rem 0.75rem 0.5rem 2.25rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
}

.perm-table-shell__module-filter {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.perm-table-shell__module-select {
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  min-width: 10rem;
}

.perm-table-shell__legend {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-3);
  margin-left: auto;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.perm-table-shell__legend-item {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  white-space: nowrap;
}

.perm-table-shell__legend-item--btn {
  padding: 0.25rem 0.5rem;
  border: 1px solid transparent;
  border-radius: var(--radius-full);
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  cursor: pointer;
}

.perm-table-shell__legend-item--btn:hover {
  background: var(--color-surface-muted);
}

.perm-table-shell__legend-item--active {
  border-color: var(--color-primary-300);
  background: var(--color-primary-surface);
  color: var(--color-primary-900);
}

.perm-table-shell__legend-icon {
  color: var(--color-text-muted);
}

.perm-table-shell__legend-icon--granted {
  color: var(--color-success);
}

.perm-table-shell__legend-icon--denied {
  color: var(--color-danger);
}

.perm-table-shell__legend-dot {
  width: 0.4375rem;
  height: 0.4375rem;
  border-radius: var(--radius-full);
  background: var(--color-info);
}

.perm-table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.perm-table {
  width: 100%;
  min-width: 48rem;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.perm-table thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  padding: var(--space-3);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-weight: 600;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  text-align: center;
  box-shadow: 0 1px 0 var(--color-border);
}

.perm-table__key-col {
  min-width: 16rem;
  text-align: left;
  position: sticky;
  left: 0;
  z-index: 1;
  background: var(--color-surface);
}

thead .perm-table__key-col {
  z-index: 3;
  text-align: left;
  background: var(--color-surface-muted);
}

.perm-table__role-col {
  min-width: 6rem;
}

.perm-table__empty {
  padding: var(--space-6);
  text-align: center;
  color: var(--color-text-muted);
}

.perm-table__group-row td {
  padding: 0;
  background: var(--color-primary-50);
  box-shadow: 0 1px 0 var(--color-border), 0 -1px 0 var(--color-border);
}

.perm-table__group-toggle {
  width: 100%;
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-3);
  border: none;
  background: transparent;
  color: var(--color-primary-900);
  font-family: var(--font-family-base);
  font-weight: 700;
  font-size: 0.8125rem;
  text-align: left;
  cursor: pointer;
}

.perm-table__group-caret {
  color: var(--color-primary);
  transition: transform 0.15s ease;
}

.perm-table__group-caret--collapsed {
  transform: rotate(-90deg);
}

.perm-table__group-count {
  color: var(--color-text-muted);
  font-weight: 500;
}

.perm-table__key-col,
.perm-table__cell-col {
  padding: var(--space-2) var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
}

.perm-table__key-label {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  color: var(--color-text);
  font-weight: 600;
  font-size: 0.8125rem;
  letter-spacing: 0.01em;
}

.perm-table__key-btn {
  display: block;
  width: 100%;
  padding: 0;
  border: none;
  background: transparent;
  text-align: left;
  cursor: pointer;
  font-family: var(--font-family-base);
}

.perm-table__key-btn:hover .perm-table__key-label {
  color: var(--color-primary);
}

.perm-table__key-desc {
  margin-top: 0.125rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-family: var(--font-family-base);
}

.perm-table__cell-col {
  text-align: center;
}

.perm-table-shell__pagination {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
}

.perm-table-shell__range {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.perm-table-shell__pagination-controls {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.perm-table-shell__per-page {
  padding: 0.4rem 0.6rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
}

.perm-table-shell__page-btn {
  min-width: 2rem;
  height: 2rem;
  padding: 0 0.5rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  cursor: pointer;
}

.perm-table-shell__page-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.perm-table-shell__page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.perm-table-shell__page-btn--active {
  border-color: var(--color-primary);
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.perm-table-shell__page-btn--ellipsis {
  border-color: transparent;
  background: transparent;
  cursor: default;
}

@media (max-width: 768px) {
  .perm-table-shell__legend {
    margin-left: 0;
  }

  .perm-table-shell__pagination {
    justify-content: center;
  }
}
</style>
