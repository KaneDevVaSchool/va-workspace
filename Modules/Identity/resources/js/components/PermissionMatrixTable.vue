<script setup>
//
// Bảng ma trận role × permission, nhóm theo module. Ô đọc trực tiếp từ
// props.matrix (PermissionService::matrixFor()). Click ô/dòng = mở chi tiết,
// double-click ô = cấp/thu hồi ngay (cha hiện ConfirmDialog). Header mỗi
// nhóm module còn có nút "Cấp cả module"/"Thu hồi cả module" theo từng cột
// role — gộp nhiều lần bấm ô lẻ thành 1 thao tác khi cấu hình cả module.
//
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import PermissionCell from './PermissionCell.vue';
import { roleCodeFromColumn } from '../constants/permissions.js';

const props = defineProps({
  shownColumns: { type: Array, required: true },
  permissions: { type: Array, required: true },
  // Toàn bộ quyền đã lọc nhưng CHƯA phân trang — chỉ dùng để tính đúng
  // trạng thái nút "Cấp/Thu hồi cả module" khi 1 module bị cắt ngang bởi
  // phân trang (permissions ở trên chỉ là trang đang xem).
  allPermissionsByModule: { type: Object, default: () => ({}) },
  matrix: { type: Object, required: true },
  pendingCells: { type: Object, default: () => ({}) },
  activeKey: { type: String, default: null },
  selectedKey: { type: String, default: null },
  loading: { type: Boolean, default: false },
  blockedMessage: { type: String, default: null },
  columnWidths: { type: Object, default: () => ({}) },
  tableWidthPx: { type: String, default: '100%' },
});

const emit = defineEmits(['inspect', 'inspect-row', 'resize-start', 'toggle', 'bulk-module']);

const colSpan = computed(() => Math.max(props.shownColumns.length, 1));

const tableRows = computed(() => {
  const rows = [];
  let lastModule = null;
  for (const perm of props.permissions) {
    const moduleLabel = perm.module || 'Khác';
    if (moduleLabel !== lastModule) {
      rows.push({ type: 'group', key: `group:${moduleLabel}`, module: moduleLabel });
      lastModule = moduleLabel;
    }
    rows.push({ type: 'perm', key: perm.key, perm });
  }
  return rows;
});

// Module chỉ có 1 quyền hiện trên bảng thì bấm ô lẻ đã đủ nhanh — nút bulk
// chỉ đáng có khi nhóm từ 2 quyền trở lên. Dùng allPermissionsByModule (toàn
// bộ, không phân trang) thay vì row.perms để không sai lệch khi module bị
// cắt ngang bởi phân trang.
function moduleGrantableKeys(moduleLabel) {
  const perms = props.allPermissionsByModule[moduleLabel] ?? [];
  return perms.filter((perm) => !perm.reserved).map((perm) => perm.key);
}

function moduleState(moduleLabel, roleCode) {
  const keys = moduleGrantableKeys(moduleLabel);
  if (keys.length === 0) return 'none';
  const grantedCount = keys.filter((key) => cellFor(roleCode, key).effective).length;
  if (grantedCount === 0) return 'none';
  if (grantedCount === keys.length) return 'all';
  return 'partial';
}

function onBulkModule(row, col) {
  const roleCode = roleCodeOf(col);
  if (!roleCode) return;
  const state = moduleState(row.module, roleCode);
  emit('bulk-module', {
    roleCode,
    moduleLabel: row.module,
    granted: state !== 'all',
  });
}

function colWidthStyle(key) {
  const width = props.columnWidths[key];
  return width ? `${width}px` : undefined;
}

function emptyCell() {
  return {
    default: false,
    effective: false,
    reserved: false,
    global_override: null,
    scoped_override: null,
    effective_source: 'config',
  };
}

function cellFor(roleCode, key) {
  return props.matrix?.[roleCode]?.[key] ?? emptyCell();
}

function isPending(roleCode, key) {
  return !!props.pendingCells[`${roleCode}|${key}`];
}

function isActive(roleCode, key) {
  return props.activeKey === `${roleCode}|${key}`;
}

function payload(roleCode, perm) {
  return { roleCode, permissionKey: perm.key, cell: cellFor(roleCode, perm.key) };
}

function roleCodeOf(col) {
  return col.roleCode || roleCodeFromColumn(col.key);
}

function isStickyCol(col) {
  return col.key === 'permission';
}
</script>

<template>
  <table class="perm-table" :style="{ width: tableWidthPx }">
    <colgroup>
      <col
        v-for="col in shownColumns"
        :key="col.key"
        :style="{ width: colWidthStyle(col.key) }"
      />
    </colgroup>
    <thead>
      <tr>
        <th
          v-for="col in shownColumns"
          :key="col.key"
          :class="{
            'perm-table__th--role': Boolean(col.roleCode),
            'perm-table__th--sticky': isStickyCol(col),
          }"
        >
          <span>{{ col.label }}</span>
          <button
            type="button"
            class="perm-table__resize"
            aria-label="Kéo để đổi độ rộng cột"
            @click.stop
            @mousedown.stop.prevent="emit('resize-start', $event, col.key)"
          />
        </th>
      </tr>
    </thead>
    <tbody>
      <tr v-if="blockedMessage">
        <td :colspan="colSpan" class="perm-table__empty">{{ blockedMessage }}</td>
      </tr>
      <tr v-else-if="loading">
        <td :colspan="colSpan" class="perm-table__empty">Đang tải…</td>
      </tr>
      <tr v-else-if="permissions.length === 0">
        <td :colspan="colSpan" class="perm-table__empty">
          Không tìm thấy quyền nào phù hợp với bộ lọc hiện tại.
        </td>
      </tr>
      <template v-else>
        <tr v-for="row in tableRows" :key="row.key" :class="{
          'perm-table__group': row.type === 'group',
          'perm-table__row--active': row.type === 'perm' && selectedKey === row.perm.key,
        }">
          <template v-if="row.type === 'group'">
            <td
              v-for="col in shownColumns"
              :key="col.key"
              class="perm-table__group-cell"
              :class="{
                'perm-table__group-cell--role': Boolean(roleCodeOf(col)),
                'perm-table__td--sticky': isStickyCol(col),
              }"
            >
              <template v-if="roleCodeOf(col)">
                <button
                  v-if="moduleGrantableKeys(row.module).length > 1"
                  type="button"
                  class="perm-table__bulk-btn"
                  :class="`perm-table__bulk-btn--${moduleState(row.module, roleCodeOf(col))}`"
                  :aria-label="`${moduleState(row.module, roleCodeOf(col)) === 'all' ? 'Thu hồi' : 'Cấp'} toàn bộ quyền module ${row.module} cho vai trò này`"
                  @click="onBulkModule(row, col)"
                >
                  <AppIcon
                    :name="moduleState(row.module, roleCodeOf(col)) === 'all' ? 'close' : 'check'"
                    :size="12"
                  />
                  {{ moduleState(row.module, roleCodeOf(col)) === 'all' ? 'Thu hồi' : 'Cấp cả' }}
                </button>
              </template>
              <template v-else-if="isStickyCol(col)">{{ row.module }}</template>
            </td>
          </template>
          <template v-else>
            <td
              v-for="col in shownColumns"
              :key="col.key"
              :class="{
                'perm-table__td--role': Boolean(col.roleCode),
                'perm-table__td--sticky': isStickyCol(col),
              }"
              @click="col.roleCode ? undefined : emit('inspect-row', row.perm)"
            >
              <template v-if="col.key === 'permission'">
                <span class="perm-table__name">{{ row.perm.label }}</span>
              </template>
              <span v-else-if="col.key === 'module'">{{ row.perm.module || '—' }}</span>
              <span v-else-if="col.key === 'key'">{{ row.perm.key }}</span>
              <PermissionCell
                v-else-if="roleCodeOf(col)"
                :cell="cellFor(roleCodeOf(col), row.perm.key)"
                :loading="isPending(roleCodeOf(col), row.perm.key)"
                :active="isActive(roleCodeOf(col), row.perm.key)"
                @inspect="emit('inspect', payload(roleCodeOf(col), row.perm))"
                @toggle="emit('toggle', payload(roleCodeOf(col), row.perm))"
              />
            </td>
          </template>
        </tr>
      </template>
    </tbody>
  </table>
</template>

<style scoped>
.perm-table {
  min-width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: calc(0.875rem * var(--table-zoom, 1));
}

.perm-table thead th {
  position: sticky;
  top: 0;
  z-index: 2;
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

.perm-table__th--role {
  text-align: center;
}

.perm-table__th--sticky {
  left: 0;
  z-index: 3;
}

.perm-table__resize {
  position: absolute;
  top: 0;
  right: 0;
  z-index: 2;
  width: 0.5rem;
  height: 100%;
  padding: 0;
  border: none;
  background: transparent;
  cursor: col-resize;
}

.perm-table__resize::after {
  content: '';
  position: absolute;
  top: 25%;
  right: 2px;
  width: 2px;
  height: 50%;
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.perm-table__resize:hover::after {
  background: var(--color-primary);
}

.perm-table tbody td {
  padding: var(--space-2) var(--space-4);
  color: var(--color-text);
  vertical-align: middle;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.perm-table tbody tr:not(.perm-table__group) {
  cursor: pointer;
}

.perm-table tbody tr:not(.perm-table__group):hover td {
  background: var(--color-surface-muted);
}

.perm-table__row--active td {
  background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface));
}

.perm-table tbody td span:not(.perm-table__name) {
  display: block;
  white-space: nowrap;
}

.perm-table__td--sticky {
  position: sticky;
  left: 0;
  z-index: 1;
  background: var(--color-surface);
  white-space: normal;
}

.perm-table tbody tr:not(.perm-table__group):hover .perm-table__td--sticky {
  background: var(--color-surface-muted);
}

.perm-table__row--active .perm-table__td--sticky {
  background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface));
}

.perm-table__name {
  display: block;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.perm-table__td--role {
  text-align: center;
  padding: var(--space-1) var(--space-2);
}

.perm-table__empty {
  padding: var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
  white-space: normal;
}

.perm-table__group-cell {
  position: sticky;
  top: 2.5rem;
  z-index: 1;
  padding: 0.4rem var(--space-4);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  white-space: nowrap;
}

.perm-table__group-cell.perm-table__td--sticky {
  z-index: 2;
}

.perm-table__group-cell--role {
  text-align: center;
  text-transform: none;
  letter-spacing: normal;
}

.perm-table__bulk-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.1875rem 0.5rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: none;
  letter-spacing: normal;
  cursor: pointer;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.perm-table__bulk-btn:hover {
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1px var(--color-primary-300);
}

.perm-table__bulk-btn--all {
  color: var(--color-danger);
}

.perm-table__bulk-btn--all:hover {
  color: var(--color-danger);
  box-shadow: inset 0 0 0 1px var(--color-danger);
}

.perm-table__group {
  cursor: default;
}
</style>
