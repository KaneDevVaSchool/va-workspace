<script setup>
//
// Bảng chính: role x permission key, group theo module (accordion), header
// sticky khi cuộn dọc. Ô đọc trực tiếp từ props.matrix (PermissionService::matrixFor()),
// không tự suy luận lại ở frontend.
//
import { computed, reactive } from 'vue';
import PermissionCell from './PermissionCell.vue';

const props = defineProps({
  roles: { type: Array, required: true }, // [{ code, label }]
  modules: { type: Array, required: true }, // [{ key, label }]
  permissions: { type: Array, required: true }, // [{ key, label, description, module, reserved }]
  matrix: { type: Object, required: true }, // { [roleCode]: { [key]: cell } }
  pendingCells: { type: Object, default: () => ({}) }, // { "roleCode|key": true }
});
const emit = defineEmits(['toggle', 'inspect']);

const expanded = reactive({});

function isExpanded(moduleLabel) {
  return expanded[moduleLabel] !== false; // mặc định mở
}

function toggleModule(moduleLabel) {
  expanded[moduleLabel] = !isExpanded(moduleLabel);
}

const groupedPermissions = computed(() => {
  const groups = {};
  for (const perm of props.permissions) {
    if (!groups[perm.module]) groups[perm.module] = [];
    groups[perm.module].push(perm);
  }
  return props.modules
    .map((m) => ({ module: m, permissions: groups[m.label] ?? groups[m.key] ?? [] }))
    .filter((g) => g.permissions.length > 0);
});

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
</script>

<template>
  <div class="perm-table-wrap">
    <table class="perm-table">
      <thead>
        <tr>
          <th class="perm-table__key-col">Quyền</th>
          <th v-for="role in roles" :key="role.code" class="perm-table__role-col">
            {{ role.label }}
          </th>
        </tr>
      </thead>
      <tbody>
        <template v-for="group in groupedPermissions" :key="group.module.key">
          <tr class="perm-table__group-row">
            <td :colspan="roles.length + 1">
              <button type="button" class="perm-table__group-toggle" @click="toggleModule(group.module.label)">
                <span class="perm-table__group-caret">{{ isExpanded(group.module.label) ? '▾' : '▸' }}</span>
                {{ group.module.label }}
                <span class="perm-table__group-count">({{ group.permissions.length }})</span>
              </button>
            </td>
          </tr>
          <template v-if="isExpanded(group.module.label)">
            <tr v-for="perm in group.permissions" :key="perm.key">
              <td class="perm-table__key-col">
                <div class="perm-table__key-label">
                  {{ perm.label }}
                  <span v-if="perm.reserved" class="perm-table__reserved-badge">🔒 Hệ thống</span>
                </div>
                <div v-if="perm.description" class="perm-table__key-desc">{{ perm.description }}</div>
                <div class="perm-table__key-raw">{{ perm.key }}</div>
              </td>
              <td v-for="role in roles" :key="role.code" class="perm-table__cell-col">
                <PermissionCell
                  :cell="cellFor(role.code, perm.key)"
                  :loading="isPending(role.code, perm.key)"
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
</template>

<style scoped>
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
}

.perm-table__reserved-badge {
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.perm-table__key-desc {
  margin-top: 0.125rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.perm-table__key-raw {
  margin-top: 0.125rem;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
}

.perm-table__cell-col {
  text-align: center;
}
</style>
