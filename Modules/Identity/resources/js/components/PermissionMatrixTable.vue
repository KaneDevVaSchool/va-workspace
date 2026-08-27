<script setup>
//
// Bảng ma trận role × permission. Ô đọc trực tiếp từ props.matrix
// (PermissionService::matrixFor()), không tự suy luận lại ở frontend.
// Chrome trang (filter, TablePagesBar, kéo cột, panel) nằm ở PermissionMatrix.vue.
//
import { computed } from 'vue';
import PermissionCell from './PermissionCell.vue';
import { roleCodeFromColumn } from '../constants/permissions.js';

const props = defineProps({
  shownColumns: { type: Array, required: true },
  permissions: { type: Array, required: true },
  matrix: { type: Object, required: true },
  pendingCells: { type: Object, default: () => ({}) },
  activeKey: { type: String, default: null },
  selectedKey: { type: String, default: null },
  loading: { type: Boolean, default: false },
  blockedMessage: { type: String, default: null },
  columnWidths: { type: Object, default: () => ({}) },
  tableWidthPx: { type: String, default: '100%' },
});

const emit = defineEmits(['toggle', 'inspect', 'inspect-row', 'resize-start']);

const colSpan = computed(() => Math.max(props.shownColumns.length, 1));

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
          :class="{ 'perm-table__th--role': Boolean(col.roleCode) }"
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
      <tr
        v-for="perm in permissions"
        v-else
        :key="perm.key"
        :class="{ 'perm-table__row--active': selectedKey === perm.key }"
        @click="emit('inspect-row', perm)"
      >
        <td
          v-for="col in shownColumns"
          :key="col.key"
          :class="{
            'perm-table__td--role': Boolean(col.roleCode),
            'perm-table__td--wrap': col.key === 'permission',
          }"
          @click="col.roleCode ? $event.stopPropagation() : undefined"
        >
          <template v-if="col.key === 'permission'">
            <span class="perm-table__wrap-text">{{ perm.label }}</span>
            <span v-if="perm.description || perm.key" class="perm-table__muted perm-table__wrap-text">
              {{ perm.description || perm.key }}
            </span>
          </template>
          <span v-else-if="col.key === 'module'">{{ perm.module || '—' }}</span>
          <span v-else-if="col.key === 'key'">{{ perm.key }}</span>
          <PermissionCell
            v-else-if="roleCodeOf(col)"
            :cell="cellFor(roleCodeOf(col), perm.key)"
            :loading="isPending(roleCodeOf(col), perm.key)"
            :active="isActive(roleCodeOf(col), perm.key)"
            @toggle="emit('toggle', payload(roleCodeOf(col), perm))"
            @inspect="emit('inspect', payload(roleCodeOf(col), perm))"
          />
        </td>
      </tr>
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

.perm-table__th--role {
  text-align: center;
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
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  vertical-align: top;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.perm-table tbody tr {
  cursor: pointer;
}

.perm-table tbody tr:hover td {
  background: var(--color-surface-muted);
}

.perm-table__row--active td {
  background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface));
}

.perm-table tbody td span:not(.perm-table__wrap-text) {
  display: block;
  white-space: nowrap;
}

.perm-table__td--wrap {
  white-space: normal;
}

.perm-table__wrap-text {
  display: block;
  white-space: normal;
  overflow-wrap: break-word;
  word-break: break-word;
}

.perm-table__td--role {
  text-align: center;
  vertical-align: middle;
  padding: var(--space-2) var(--space-3);
}

.perm-table__empty {
  padding: var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
  white-space: normal;
}

.perm-table__muted {
  margin-top: 0.125rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}
</style>
