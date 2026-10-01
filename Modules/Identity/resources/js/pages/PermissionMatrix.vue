<script setup>
//
// superadmin/permissions — chỉnh quyền theo từng vai trò.
// Chọn phạm vi và vai trò bên trái, bật/tắt quyền theo nhóm module bên phải.
// Backend (PermissionService::matrixFor()) là nguồn sự thật: trang chỉ đọc
// ma trận và gọi API khi người dùng xác nhận cấp, thu hồi hoặc khôi phục.
//
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { showClientToast } from '@/lib/clientToast';
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';

const ROLE_STORAGE_KEY = 'va-permissions-role';

const STATUS_OPTIONS = [
  { value: '', label: 'Tất cả' },
  { value: 'granted', label: 'Được cấp' },
  { value: 'denied', label: 'Chưa cấp' },
  { value: 'override', label: 'Sửa riêng' },
  { value: 'reserved', label: 'Hệ thống' },
];

const scope = ref({ type: 'global', id: null });
const scopeLabel = ref('Toàn hệ thống');
const departmentId = ref(null);
const teamId = ref(null);
const departments = ref([]);
const teams = ref([]);
const loadingTeams = ref(false);

const roles = ref([]);
const modules = ref([]);
const permissions = ref([]);
const matrix = ref({});
const isLoading = ref(true);
const pendingCells = reactive({});
const restoring = ref(false);
const pendingAction = ref(null);
const confirmLoading = ref(false);

const roleQuery = ref('');
const query = ref('');
const moduleFilter = ref('all');
const statusFilter = ref('');
const selectedRoleCode = ref(loadStoredRole());
const collapsed = reactive({});

function loadStoredRole() {
  try {
    return localStorage.getItem(ROLE_STORAGE_KEY) || '';
  } catch {
    return '';
  }
}

const scopeReady = computed(() => scope.value.type === 'global' || Boolean(scope.value.id));

const blockedMessage = computed(() => {
  if (scopeReady.value) return '';
  return scope.value.type === 'department'
    ? 'Chọn phòng ban để xem và sửa quyền trong phạm vi đó.'
    : 'Chọn phòng ban, rồi chọn nhóm, để xem và sửa quyền trong phạm vi đó.';
});

const permissionByKey = computed(() => {
  const map = {};
  for (const perm of permissions.value) map[perm.key] = perm;
  return map;
});

const roleByCode = computed(() => {
  const map = {};
  for (const role of roles.value) map[role.code] = role;
  return map;
});

const selectedRole = computed(() => roleByCode.value[selectedRoleCode.value] ?? null);

const permissionsByModule = computed(() => {
  const map = {};
  for (const perm of permissions.value) {
    const key = perm.module || 'Khác';
    (map[key] ??= []).push(perm);
  }
  return map;
});

const sortedPermissions = computed(() =>
  [...permissions.value].sort((a, b) => {
    const mod = (a.module || '').localeCompare(b.module || '', 'vi');
    if (mod !== 0) return mod;
    return (a.label || '').localeCompare(b.label || '', 'vi');
  }),
);

const hasActiveFilters = computed(
  () => Boolean(query.value.trim()) || moduleFilter.value !== 'all' || Boolean(statusFilter.value),
);

const roleSummaries = computed(() =>
  roles.value.map((role) => {
    if (!scopeReady.value) {
      return { ...role, granted: null, overridden: null, editable: null };
    }
    let granted = 0;
    let overridden = 0;
    let editable = 0;
    for (const perm of permissions.value) {
      const cell = cellFor(role.code, perm.key);
      if (cell.reserved) continue;
      editable += 1;
      if (cell.effective) granted += 1;
      if (overrideHere(cell) !== null) overridden += 1;
    }
    return { ...role, granted, overridden, editable };
  }),
);

const visibleRoles = computed(() => {
  const term = roleQuery.value.trim().toLowerCase();
  if (!term) return roleSummaries.value;
  return roleSummaries.value.filter((role) => role.label.toLowerCase().includes(term));
});

const selectedSummary = computed(
  () => roleSummaries.value.find((role) => role.code === selectedRoleCode.value) ?? null,
);

const filteredPermissions = computed(() => {
  const term = query.value.trim().toLowerCase();
  return sortedPermissions.value.filter((perm) => {
    if (moduleFilter.value !== 'all' && perm.module !== moduleFilter.value) return false;
    if (!permissionMatchesStatus(perm)) return false;
    if (!term) return true;
    return (
      perm.label.toLowerCase().includes(term) ||
      perm.key.toLowerCase().includes(term) ||
      (perm.description ?? '').toLowerCase().includes(term)
    );
  });
});

const groups = computed(() => {
  const map = new Map();
  for (const perm of filteredPermissions.value) {
    const label = perm.module || 'Khác';
    if (!map.has(label)) map.set(label, []);
    map.get(label).push(perm);
  }
  return [...map.entries()].map(([module, items]) => ({ module, items }));
});

const confirmOpen = computed({
  get: () => pendingAction.value !== null,
  set: (open) => {
    if (!open && !confirmLoading.value) pendingAction.value = null;
  },
});

const confirmCopy = computed(() => {
  const action = pendingAction.value;
  if (!action) {
    return { title: '', description: '', confirmLabel: 'Xác nhận', danger: false };
  }

  if (action.type === 'bulk-module') {
    const role = roleLabel(action.roleCode);
    if (action.granted) {
      return {
        title: 'Cấp cả module này?',
        description: `Cấp toàn bộ ${action.keys.length} quyền thuộc “${action.moduleLabel}” cho vai trò ${role} trong ${scopeLabel.value}.`,
        confirmLabel: 'Cấp cả module',
        danger: false,
      };
    }
    return {
      title: 'Thu hồi cả module này?',
      description: `Thu hồi toàn bộ ${action.keys.length} quyền thuộc “${action.moduleLabel}” của vai trò ${role} trong ${scopeLabel.value}.`,
      confirmLabel: 'Thu hồi cả module',
      danger: true,
    };
  }

  const perm = permissionLabel(action.permissionKey);
  const role = roleLabel(action.roleCode);

  if (action.type === 'restore') {
    return {
      title: 'Khôi phục mặc định?',
      description: `Bỏ thiết lập riêng của quyền “${perm}” cho vai trò ${role} trong ${scopeLabel.value}, quay về giá trị mặc định của hệ thống.`,
      confirmLabel: 'Khôi phục mặc định',
      danger: false,
    };
  }

  if (action.cell.effective) {
    return {
      title: 'Thu hồi quyền này?',
      description: `Thu hồi quyền “${perm}” của vai trò ${role} trong ${scopeLabel.value}. Người dùng với vai trò này sẽ không còn quyền đó tại phạm vi đang xem.`,
      confirmLabel: 'Thu hồi quyền',
      danger: true,
    };
  }

  return {
    title: 'Cấp quyền này?',
    description: `Cấp quyền “${perm}” cho vai trò ${role} trong ${scopeLabel.value}.`,
    confirmLabel: 'Cấp quyền',
    danger: false,
  };
});

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
  return matrix.value?.[roleCode]?.[key] ?? emptyCell();
}

function overrideHere(cell) {
  return scope.value.type === 'global' ? cell.global_override : cell.scoped_override;
}

function permissionLabel(key) {
  return permissionByKey.value[key]?.label ?? key;
}

function roleLabel(code) {
  return roleByCode.value[code]?.label ?? code;
}

function permissionMatchesStatus(perm) {
  if (!statusFilter.value || !selectedRoleCode.value) return true;
  const cell = cellFor(selectedRoleCode.value, perm.key);
  if (statusFilter.value === 'reserved') return cell.reserved;
  if (statusFilter.value === 'override') return overrideHere(cell) !== null;
  if (cell.reserved) return false;
  return statusFilter.value === 'granted' ? cell.effective : !cell.effective;
}

function grantableKeys(moduleLabel) {
  return (permissionsByModule.value[moduleLabel] ?? [])
    .filter((perm) => !cellFor(selectedRoleCode.value, perm.key).reserved)
    .map((perm) => perm.key);
}

function moduleStats(moduleLabel) {
  const keys = grantableKeys(moduleLabel);
  const granted = keys.filter((key) => cellFor(selectedRoleCode.value, key).effective).length;
  return { total: keys.length, granted };
}

function moduleCaption(moduleLabel) {
  const { total, granted } = moduleStats(moduleLabel);
  if (total === 0) return 'Chỉ có quyền hệ thống';
  return `${granted} trên ${total} quyền được cấp`;
}

function moduleTone(moduleLabel) {
  const { total, granted } = moduleStats(moduleLabel);
  if (total === 0) return 'info';
  if (granted === 0) return 'empty';
  if (granted === total) return 'success';
  return 'warning';
}

function moduleOpen(moduleLabel) {
  if (hasActiveFilters.value) return true;
  return !collapsed[moduleLabel];
}

function toggleModule(moduleLabel) {
  if (hasActiveFilters.value) return;
  collapsed[moduleLabel] = !collapsed[moduleLabel];
}

function statusText(cell) {
  if (cell.reserved) return 'Quyền hệ thống';
  return cell.effective ? 'Được cấp' : 'Chưa cấp';
}

function statusTone(cell) {
  if (cell.reserved) return 'lock';
  return cell.effective ? 'on' : 'off';
}

function explain(cell) {
  if (cell.reserved) return 'Không đổi được trên trang này.';
  const here = overrideHere(cell);
  if (here !== null) {
    return scope.value.type === 'global'
      ? 'Có thiết lập riêng cho toàn hệ thống.'
      : `Có thiết lập riêng cho ${scopeLabel.value}.`;
  }
  if (cell.effective_source === 'global' && scope.value.type !== 'global') {
    return 'Đang theo thiết lập riêng của toàn hệ thống. Gạt công tắc chỉ đổi ở phạm vi này.';
  }
  return 'Đang dùng mặc định của hệ thống.';
}

function isPending(roleCode, key) {
  return Boolean(pendingCells[`${roleCode}|${key}`]);
}

function moduleBusy(moduleLabel) {
  const roleCode = selectedRoleCode.value;
  return grantableKeys(moduleLabel).some((key) => isPending(roleCode, key));
}

async function loadDepartments() {
  try {
    const { data } = await window.axios.get('/manager/departments');
    departments.value = data.departments ?? [];
  } catch {
    showClientToast('error', 'Không tải được danh sách phòng ban.');
  }
}

async function loadTeams() {
  if (!departmentId.value) {
    teams.value = [];
    return;
  }
  loadingTeams.value = true;
  try {
    const { data } = await window.axios.get('/manager/teams', {
      params: { department_id: departmentId.value },
    });
    teams.value = data.teams ?? [];
  } catch {
    teams.value = [];
    showClientToast('error', 'Không tải được danh sách nhóm.');
  } finally {
    loadingTeams.value = false;
  }
}

function publishScope() {
  if (scope.value.type === 'global') {
    scope.value = { type: 'global', id: null };
    scopeLabel.value = 'Toàn hệ thống';
    return;
  }
  if (scope.value.type === 'department') {
    const dept = departments.value.find((item) => item.id === departmentId.value);
    scope.value = { type: 'department', id: departmentId.value };
    scopeLabel.value = dept ? `Phòng ban: ${dept.name}` : 'Phòng ban';
    return;
  }
  const team = teams.value.find((item) => item.id === teamId.value);
  scope.value = { type: 'team', id: teamId.value };
  scopeLabel.value = team ? `Nhóm: ${team.name}` : 'Nhóm';
}

function setScopeType(type) {
  if (scope.value.type === type) return;
  scope.value = { ...scope.value, type };
  if (type === 'team' && departmentId.value) loadTeams();
  publishScope();
  loadMatrix();
}

function onDepartmentChange() {
  teamId.value = null;
  if (scope.value.type === 'team') loadTeams();
  publishScope();
  loadMatrix();
}

function onTeamChange() {
  publishScope();
  loadMatrix();
}

async function loadMatrix() {
  if (!scopeReady.value) {
    matrix.value = {};
    return;
  }

  isLoading.value = true;
  try {
    const { data } = await window.axios.get('/api/permissions/matrix', {
      params: { scope_type: scope.value.type, scope_id: scope.value.id },
    });
    roles.value = data.roles ?? [];
    modules.value = data.modules ?? [];
    permissions.value = data.permissions ?? [];
    matrix.value = data.matrix ?? {};
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không tải được danh sách quyền.');
  } finally {
    isLoading.value = false;
  }
}

function selectRole(code) {
  selectedRoleCode.value = code;
}

function applyCellUpdate(roleCode, permissionKey, cell) {
  if (!matrix.value[roleCode]) matrix.value[roleCode] = {};
  matrix.value[roleCode][permissionKey] = cell;
}

function ensureScope() {
  if (scopeReady.value && selectedRoleCode.value) return true;
  showClientToast('error', blockedMessage.value || 'Chọn vai trò trước khi đổi quyền.');
  return false;
}

function requestToggle(perm) {
  const roleCode = selectedRoleCode.value;
  const cell = cellFor(roleCode, perm.key);
  if (cell.reserved || isPending(roleCode, perm.key) || !ensureScope()) return;
  pendingAction.value = { type: 'toggle', roleCode, permissionKey: perm.key, cell };
}

function requestRestore(perm) {
  const roleCode = selectedRoleCode.value;
  const cell = cellFor(roleCode, perm.key);
  if (cell.reserved || restoring.value || overrideHere(cell) === null || !ensureScope()) return;
  pendingAction.value = { type: 'restore', roleCode, permissionKey: perm.key, cell };
}

function requestBulk(moduleLabel, granted) {
  const roleCode = selectedRoleCode.value;
  const keys = grantableKeys(moduleLabel);
  if (!keys.length || !ensureScope() || moduleBusy(moduleLabel)) return;
  const { granted: current } = moduleStats(moduleLabel);
  if (granted && current === keys.length) return;
  if (!granted && current === 0) return;
  pendingAction.value = { type: 'bulk-module', roleCode, moduleLabel, keys, granted };
}

async function onConfirmAction() {
  const action = pendingAction.value;
  if (!action || confirmLoading.value) return;

  confirmLoading.value = true;
  try {
    if (action.type === 'restore') await restoreDefault(action);
    else if (action.type === 'bulk-module') await applyBulkModule(action);
    else await applyToggle(action);
    pendingAction.value = null;
  } catch {
    // Toast đã hiện; giữ hộp thoại để thử lại hoặc huỷ.
  } finally {
    confirmLoading.value = false;
  }
}

async function applyBulkModule({ roleCode, moduleLabel, keys, granted }) {
  for (const key of keys) pendingCells[`${roleCode}|${key}`] = true;
  try {
    const { data } = await window.axios.put('/api/permissions/grants/bulk', {
      role_code: roleCode,
      permission_keys: keys,
      granted,
      scope_type: scope.value.type,
      scope_id: scope.value.id,
    });
    for (const [key, cell] of Object.entries(data.cells ?? {})) {
      applyCellUpdate(roleCode, key, cell);
    }
    showClientToast(
      'success',
      granted
        ? `Đã cấp cả module “${moduleLabel}” cho vai trò ${roleLabel(roleCode)}.`
        : `Đã thu hồi cả module “${moduleLabel}” của vai trò ${roleLabel(roleCode)}.`,
    );
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không lưu được thay đổi. Vui lòng thử lại.');
    throw error;
  } finally {
    for (const key of keys) delete pendingCells[`${roleCode}|${key}`];
  }
}

async function applyToggle({ roleCode, permissionKey, cell }) {
  const cellKey = `${roleCode}|${permissionKey}`;
  if (pendingCells[cellKey]) throw new Error('pending');

  const newValue = !cell.effective;
  const here = scope.value.type === 'global' ? cell.global_override : cell.scoped_override;
  pendingCells[cellKey] = true;

  try {
    let cellResult;
    if (newValue === cell.default && here !== null) {
      const { data } = await window.axios.delete('/api/permissions/grants', {
        data: {
          role_code: roleCode,
          permission_key: permissionKey,
          scope_type: scope.value.type,
          scope_id: scope.value.id,
        },
      });
      cellResult = data.cell;
    } else {
      const { data } = await window.axios.put('/api/permissions/grants', {
        role_code: roleCode,
        permission_key: permissionKey,
        granted: newValue,
        scope_type: scope.value.type,
        scope_id: scope.value.id,
      });
      cellResult = data.cell;
    }
    applyCellUpdate(roleCode, permissionKey, cellResult);
    showClientToast(
      'success',
      newValue
        ? `Đã cấp quyền “${permissionLabel(permissionKey)}” cho vai trò ${roleLabel(roleCode)}.`
        : `Đã thu hồi quyền “${permissionLabel(permissionKey)}” của vai trò ${roleLabel(roleCode)}.`,
    );
  } catch (error) {
    const status = error?.response?.status;
    const message = error?.response?.data?.message;
    if (status === 422) showClientToast('error', message || 'Dữ liệu không hợp lệ.');
    else if (status === 403) showClientToast('error', message || 'Bạn không có quyền thay đổi quyền hệ thống này.');
    else showClientToast('error', message || 'Không lưu được thay đổi. Vui lòng thử lại.');
    throw error;
  } finally {
    delete pendingCells[cellKey];
  }
}

async function restoreDefault({ roleCode, permissionKey }) {
  restoring.value = true;
  try {
    const { data } = await window.axios.delete('/api/permissions/grants', {
      data: {
        role_code: roleCode,
        permission_key: permissionKey,
        scope_type: scope.value.type,
        scope_id: scope.value.id,
      },
    });
    applyCellUpdate(roleCode, permissionKey, data.cell);
    showClientToast('success', `Đã khôi phục quyền “${permissionLabel(permissionKey)}” về mặc định.`);
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không khôi phục được mặc định.');
    throw error;
  } finally {
    restoring.value = false;
  }
}

function clearFilters() {
  query.value = '';
  moduleFilter.value = 'all';
  statusFilter.value = '';
}

watch(selectedRoleCode, (code) => {
  try {
    if (code) localStorage.setItem(ROLE_STORAGE_KEY, code);
  } catch {
    // Bỏ qua nếu trình duyệt chặn localStorage.
  }
});

watch(roles, (list) => {
  if (!list.length) return;
  if (!list.some((role) => role.code === selectedRoleCode.value)) {
    selectedRoleCode.value = list[0].code;
  }
});

watch(teams, () => {
  if (scope.value.type === 'team' && teamId.value && !teams.value.some((item) => item.id === teamId.value)) {
    teamId.value = null;
    publishScope();
    loadMatrix();
  }
});

onMounted(() => {
  loadDepartments();
  loadMatrix();
});
</script>

<template>
  <section class="perm">
    <PageHeader
      title="Quản lý phân quyền"
      icon="shield"
      :breadcrumbs="[
        { label: 'Trang chủ', to: { name: 'home' } },
        { label: 'Quản lý phân quyền' },
      ]"
    >
      <template #actions>
        <button type="button" class="perm__header-btn" :disabled="isLoading" @click="loadMatrix">
          <AppIcon name="refresh" :size="16" :class="{ 'perm__spin': isLoading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

    <div class="perm__body">
      <aside class="perm__rail" aria-label="Chọn phạm vi và vai trò">
        <div class="perm__scope">
          <p class="perm__kicker">Phạm vi áp dụng</p>
          <div class="perm__segments" role="radiogroup" aria-label="Phạm vi áp dụng">
            <button type="button" role="radio" :aria-checked="scope.type === 'global'" @click="setScopeType('global')">
              Toàn hệ thống
            </button>
            <button
              type="button"
              role="radio"
              :aria-checked="scope.type === 'department'"
              @click="setScopeType('department')"
            >
              Phòng ban
            </button>
            <button type="button" role="radio" :aria-checked="scope.type === 'team'" @click="setScopeType('team')">
              Nhóm
            </button>
          </div>

          <label v-if="scope.type !== 'global'" class="perm__field">
            <span>Phòng ban</span>
            <select v-model="departmentId" @change="onDepartmentChange">
              <option :value="null" disabled>Chọn phòng ban</option>
              <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
            </select>
          </label>

          <label v-if="scope.type === 'team'" class="perm__field">
            <span>Nhóm</span>
            <select v-model="teamId" :disabled="!departmentId || loadingTeams" @change="onTeamChange">
              <option :value="null" disabled>{{ departmentId ? 'Chọn nhóm' : 'Chọn phòng ban trước' }}</option>
              <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
            </select>
          </label>

          <p class="perm__scope-now">Đang xem: {{ scopeLabel }}</p>
        </div>

        <label class="perm__search">
          <AppIcon name="search" :size="16" />
          <input v-model="roleQuery" type="search" placeholder="Tìm vai trò" aria-label="Tìm vai trò" />
        </label>

        <div class="perm__roles hide-scrollbar" role="listbox" aria-label="Vai trò">
          <p v-if="!visibleRoles.length" class="perm__empty">Không có vai trò khớp.</p>
          <button
            v-for="role in visibleRoles"
            :key="role.code"
            type="button"
            class="perm__role"
            role="option"
            :aria-selected="role.code === selectedRoleCode"
            @click="selectRole(role.code)"
          >
            <span class="perm__role-name">{{ role.label }}</span>
            <span v-if="role.granted === null" class="perm__role-meta">Chưa chọn phạm vi</span>
            <span v-else class="perm__role-meta">{{ role.granted }} / {{ role.editable }} quyền được cấp</span>
            <span v-if="role.overridden" class="perm__role-note">{{ role.overridden }} thiết lập riêng</span>
          </button>
        </div>
      </aside>

      <div class="perm__main">
        <div v-if="!scopeReady" class="perm__prompt" role="status">
          <AppIcon name="building" :size="22" />
          <p>{{ blockedMessage }}</p>
        </div>

        <div v-else-if="isLoading && !permissions.length" class="perm__prompt" role="status">
          <AppIcon name="refresh" :size="22" class="perm__spin" />
          <p>Đang tải danh sách quyền.</p>
        </div>

        <div v-else-if="!selectedRole" class="perm__prompt" role="status">
          <p>Chưa có vai trò để chỉnh quyền.</p>
        </div>

        <template v-else>
          <header class="perm__head">
            <div>
              <h2>{{ selectedRole.label }}</h2>
              <p v-if="selectedSummary">
                {{ selectedSummary.granted }} trên {{ selectedSummary.editable }} quyền được cấp trong {{ scopeLabel }}.
                <template v-if="selectedSummary.overridden">
                  {{ selectedSummary.overridden }} quyền có thiết lập riêng.
                </template>
              </p>
            </div>
          </header>

          <div class="perm__tools">
            <label class="perm__search perm__search--grow">
              <AppIcon name="search" :size="16" />
              <input v-model="query" type="search" placeholder="Tìm quyền theo tên hoặc mô tả" aria-label="Tìm quyền" />
            </label>

            <label class="perm__field perm__field--inline">
              <span>Module</span>
              <select v-model="moduleFilter" aria-label="Lọc theo module">
                <option value="all">Tất cả</option>
                <option v-for="item in modules" :key="item.key" :value="item.label">{{ item.label }}</option>
              </select>
            </label>

            <div class="perm__segments" role="radiogroup" aria-label="Lọc trạng thái">
              <button
                v-for="item in STATUS_OPTIONS"
                :key="item.value || 'all'"
                type="button"
                role="radio"
                :aria-checked="statusFilter === item.value"
                @click="statusFilter = item.value"
              >
                {{ item.label }}
              </button>
            </div>

            <button v-if="hasActiveFilters" type="button" class="perm__text-btn" @click="clearFilters">
              Bỏ lọc
            </button>
          </div>

          <div class="perm__list hide-scrollbar" :aria-busy="isLoading">
            <p v-if="!groups.length" class="perm__empty perm__empty--pad">Không có quyền khớp bộ lọc.</p>

            <article
              v-for="group in groups"
              :key="group.module"
              class="perm__module"
              :class="`perm__module--${moduleTone(group.module)}`"
            >
              <header class="perm__module-head">
                <button
                  type="button"
                  class="perm__module-toggle"
                  :aria-expanded="moduleOpen(group.module)"
                  @click="toggleModule(group.module)"
                >
                  <AppIcon :name="moduleOpen(group.module) ? 'chevronDown' : 'chevronRight'" :size="16" />
                  <span>
                    <strong>{{ group.module }}</strong>
                    <small>
                      {{ moduleCaption(group.module) }}
                      <template v-if="group.items.length !== (permissionsByModule[group.module] || []).length">
                        · đang hiện {{ group.items.length }}
                      </template>
                    </small>
                  </span>
                </button>
                <div class="perm__module-actions">
                  <button
                    type="button"
                    class="perm__text-btn"
                    :disabled="
                      moduleBusy(group.module) ||
                      moduleStats(group.module).total === 0 ||
                      moduleStats(group.module).granted === moduleStats(group.module).total
                    "
                    @click="requestBulk(group.module, true)"
                  >
                    Cấp cả module
                  </button>
                  <button
                    type="button"
                    class="perm__text-btn perm__text-btn--danger"
                    :disabled="
                      moduleBusy(group.module) ||
                      moduleStats(group.module).total === 0 ||
                      moduleStats(group.module).granted === 0
                    "
                    @click="requestBulk(group.module, false)"
                  >
                    Thu hồi cả module
                  </button>
                </div>
              </header>

              <ul v-if="moduleOpen(group.module)" class="perm__items">
                <li v-for="perm in group.items" :key="perm.key" class="perm__item">
                  <div class="perm__item-copy">
                    <p class="perm__item-title">{{ perm.label }}</p>
                    <p v-if="perm.description" class="perm__item-desc">{{ perm.description }}</p>
                    <p class="perm__item-status">
                      <span class="perm__dot" :class="`perm__dot--${statusTone(cellFor(selectedRoleCode, perm.key))}`" />
                      {{ statusText(cellFor(selectedRoleCode, perm.key)) }}
                    </p>
                    <p class="perm__item-why">{{ explain(cellFor(selectedRoleCode, perm.key)) }}</p>
                  </div>
                  <div class="perm__item-actions">
                    <button
                      v-if="overrideHere(cellFor(selectedRoleCode, perm.key)) !== null"
                      type="button"
                      class="perm__text-btn"
                      :disabled="restoring || isPending(selectedRoleCode, perm.key)"
                      @click="requestRestore(perm)"
                    >
                      Khôi phục mặc định
                    </button>
                    <button
                      type="button"
                      class="perm__switch"
                      role="switch"
                      :aria-checked="cellFor(selectedRoleCode, perm.key).effective"
                      :aria-label="
                        cellFor(selectedRoleCode, perm.key).reserved
                          ? `${perm.label}, quyền hệ thống`
                          : cellFor(selectedRoleCode, perm.key).effective
                            ? `Thu hồi ${perm.label}`
                            : `Cấp ${perm.label}`
                      "
                      :disabled="
                        cellFor(selectedRoleCode, perm.key).reserved || isPending(selectedRoleCode, perm.key)
                      "
                      @click="requestToggle(perm)"
                    >
                      <AppIcon
                        v-if="cellFor(selectedRoleCode, perm.key).reserved"
                        name="lock"
                        :size="12"
                      />
                      <span v-else class="perm__switch-knob" />
                    </button>
                  </div>
                </li>
              </ul>
            </article>
          </div>
        </template>
      </div>
    </div>

    <ConfirmDialog
      v-model:open="confirmOpen"
      :title="confirmCopy.title"
      :description="confirmCopy.description"
      :confirm-label="confirmCopy.confirmLabel"
      :danger="confirmCopy.danger"
      :loading="confirmLoading"
      @confirm="onConfirmAction"
    />
  </section>
</template>

<style scoped>
.perm {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
  background: var(--color-bg);
  color: var(--color-text);
}

.perm__header-btn,
.perm__text-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  height: 2rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  cursor: pointer;
  box-shadow: 0 0 0 1px var(--color-border);
}

.perm__header-btn:disabled,
.perm__text-btn:disabled,
.perm__switch:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.perm__text-btn--danger {
  color: var(--color-danger);
}

.perm__body {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-4);
}

.perm__rail {
  width: 18.5rem;
  flex-shrink: 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.perm__scope,
.perm__head,
.perm__module {
  position: relative;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-sm);
}

.perm__scope {
  padding: var(--space-3);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.perm__kicker {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.perm__segments {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-1);
  padding: var(--space-1);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.perm__segments button {
  flex: 1 1 auto;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  padding: 0.4rem 0.55rem;
  cursor: pointer;
}

.perm__segments button[aria-checked='true'] {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: var(--shadow-sm);
}

.perm__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.perm__field select,
.perm__search input {
  width: 100%;
  min-width: 0;
  height: 2.25rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  box-shadow: 0 0 0 1px var(--color-border);
}

.perm__field--inline {
  min-width: 11rem;
}

.perm__scope-now,
.perm__role-meta,
.perm__role-note,
.perm__item-why,
.perm__head p,
.perm__module-toggle small {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.4;
}

.perm__search {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  height: 2.25rem;
  padding: 0 0.75rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text-muted);
  box-shadow: 0 0 0 1px var(--color-border);
}

.perm__search input {
  flex: 1;
  width: auto;
  min-width: 0;
  height: 100%;
  padding: 0;
  box-shadow: none;
  background: transparent;
}

.perm__search--grow {
  flex: 1 1 14rem;
}

.perm__roles,
.perm__list {
  flex: 1;
  min-height: 0;
  overflow: auto;
}

.perm__roles {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.perm__role {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.125rem;
  width: 100%;
  padding: var(--space-3);
  padding-left: calc(var(--space-2) + 3px + var(--space-3));
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  text-align: left;
  cursor: pointer;
  box-shadow: var(--shadow-sm);
}

.perm__role::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.perm__role[aria-selected='true'] {
  background: var(--color-primary-surface);
}

.perm__role[aria-selected='true']::before {
  background: var(--color-primary);
}

.perm__role-name,
.perm__item-title,
.perm__module-toggle strong,
.perm__head h2 {
  font-weight: 600;
}

.perm__role-name {
  font-size: 0.875rem;
}

.perm__main {
  flex: 1;
  min-width: 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.perm__prompt {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--space-3);
  color: var(--color-text-muted);
  text-align: center;
}

.perm__prompt p,
.perm__empty {
  margin: 0;
  font-size: 0.875rem;
}

.perm__empty--pad {
  padding: var(--space-5);
}

.perm__head {
  flex-shrink: 0;
  padding: var(--space-4);
}

.perm__head h2 {
  margin: 0 0 var(--space-1);
  font-size: 1.125rem;
}

.perm__tools {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: var(--space-2);
}

.perm__list {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-1);
}

.perm__module {
  flex-shrink: 0;
}

.perm__module::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.perm__module--success::before {
  background: var(--color-success);
}

.perm__module--warning::before {
  background: var(--color-warning);
}

.perm__module--info::before {
  background: var(--color-info);
}

.perm__module-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-3));
}

.perm__module-toggle {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  min-width: 0;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  text-align: left;
  cursor: pointer;
  font-family: var(--font-family-base);
}

.perm__module-toggle span {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.perm__module-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: var(--space-2);
}

.perm__items {
  list-style: none;
  margin: 0;
  padding: 0 var(--space-3) var(--space-2) calc(var(--space-2) + 3px + var(--space-3));
}

.perm__item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-4);
  padding: var(--space-3) 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.perm__item:last-child {
  box-shadow: none;
}

.perm__item-copy {
  min-width: 0;
}

.perm__item-title {
  margin: 0;
  font-size: 0.875rem;
}

.perm__item-desc {
  margin: 0.125rem 0 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  line-height: 1.45;
}

.perm__item-status {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin: var(--space-2) 0 0;
  font-size: 0.8125rem;
}

.perm__item-why {
  margin-top: 0.125rem;
}

.perm__dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-border-strong);
  flex-shrink: 0;
}

.perm__dot--on {
  background: var(--color-success);
}

.perm__dot--off {
  background: var(--color-danger);
}

.perm__dot--lock {
  background: var(--color-info);
}

.perm__item-actions {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex-shrink: 0;
}

.perm__switch {
  position: relative;
  width: 2.75rem;
  height: 1.5rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-border-strong);
  cursor: pointer;
  flex-shrink: 0;
}

.perm__switch[aria-checked='true'] {
  background: var(--color-success);
}

.perm__switch-knob {
  position: absolute;
  top: 0.1875rem;
  left: 0.1875rem;
  width: 1.125rem;
  height: 1.125rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  transition: transform 160ms ease;
}

.perm__switch[aria-checked='true'] .perm__switch-knob {
  transform: translateX(1.25rem);
}

.perm__switch :deep(svg) {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  color: var(--color-text);
}

.perm__switch[aria-checked='true'] :deep(svg) {
  color: var(--color-surface);
}

.perm__spin {
  animation: perm-spin 0.8s linear infinite;
}

@keyframes perm-spin {
  to {
    transform: rotate(360deg);
  }
}

button:focus-visible,
select:focus-visible,
input:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

@media (max-width: 900px) {
  .perm {
    height: auto;
    min-height: 100%;
    overflow: visible;
  }

  .perm__body {
    flex-direction: column;
  }

  .perm__rail {
    width: 100%;
  }

  .perm__roles {
    max-height: 16rem;
  }

  .perm__item {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
