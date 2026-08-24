<script setup>
//
// superadmin/permissions — Ma trận phân quyền: xem quyền hiệu lực theo role,
// override (cấp/thu hồi) theo scope global/department/team, khôi phục mặc định.
// Backend (PermissionService::matrixFor()) là single source of truth — trang
// này không tự suy luận effective/default, chỉ đọc và gọi API khi toggle.
//
import { computed, reactive, ref } from 'vue';
import { showClientToast } from '@/lib/clientToast';
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import PermissionScopeFilter from '../components/PermissionScopeFilter.vue';
import PermissionMatrixTable from '../components/PermissionMatrixTable.vue';

const scope = ref({ type: 'global', id: null });
const scopeLabel = ref('Toàn hệ thống');
const roles = ref([]);
const modules = ref([]);
const permissions = ref([]);
const matrix = ref({});
const isLoading = ref(false);
const pendingCells = reactive({});
const inspectPanel = ref(null); // { roleCode, permissionKey, cell }
const restoring = ref(false);
const pendingAction = ref(null); // { type: 'toggle'|'restore', roleCode, permissionKey, cell }
const confirmLoading = ref(false);

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

const activeCellKey = computed(() =>
  inspectPanel.value ? `${inspectPanel.value.roleCode}|${inspectPanel.value.permissionKey}` : null,
);

// Câu giải thích "vì sao quyền này đang có/không có" — viết thành câu bình
// thường thay vì nhãn kỹ thuật (global/scoped/config), cho người không rành
// hệ thống cũng hiểu được ngay.
function sourceExplanation(cell) {
  if (cell.effective_source === 'scoped') {
    return `Do có thiết lập riêng cho ${scopeLabel.value}`;
  }
  if (cell.effective_source === 'global') {
    return 'Do có thiết lập áp dụng cho toàn hệ thống';
  }
  return 'Theo thiết lập mặc định của hệ thống, chưa có thay đổi riêng';
}

async function loadMatrix() {
  // scope department/team cần scope_id mới có ý nghĩa — chờ người dùng chọn xong
  if (scope.value.type !== 'global' && !scope.value.id) {
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

    // Đồng bộ lại panel chi tiết đang mở (nếu có) với dữ liệu mới nhất
    if (inspectPanel.value) {
      const { roleCode, permissionKey } = inspectPanel.value;
      const freshCell = matrix.value?.[roleCode]?.[permissionKey];
      if (freshCell) {
        inspectPanel.value = { roleCode, permissionKey, cell: freshCell };
      } else {
        inspectPanel.value = null;
      }
    }
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không tải được ma trận phân quyền.');
  } finally {
    isLoading.value = false;
  }
}

function onScopeChange(newScope) {
  scope.value = { type: newScope.type, id: newScope.id };
  scopeLabel.value = newScope.label ?? 'Toàn hệ thống';
  loadMatrix();
}

// Ghi cell mới vào đúng vị trí trong matrix + đồng bộ panel chi tiết nếu
// đang mở đúng ô đó — patch tại chỗ bằng response của API, KHÔNG gọi lại
// loadMatrix() (tránh tải lại toàn bộ ma trận chỉ để đổi 1 ô).
function applyCellUpdate(roleCode, permissionKey, cell) {
  if (!matrix.value[roleCode]) matrix.value[roleCode] = {};
  matrix.value[roleCode][permissionKey] = cell;

  if (
    inspectPanel.value &&
    inspectPanel.value.roleCode === roleCode &&
    inspectPanel.value.permissionKey === permissionKey
  ) {
    inspectPanel.value = { roleCode, permissionKey, cell };
  }
}

function permissionLabel(key) {
  return permissionByKey.value[key]?.label ?? key;
}

function roleLabel(code) {
  return roleByCode.value[code]?.label ?? code;
}

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

function requestToggle({ roleCode, permissionKey, cell }) {
  if (cell.reserved) return;
  if (pendingCells[`${roleCode}|${permissionKey}`]) return;

  if (scope.value.type !== 'global' && !scope.value.id) {
    showClientToast('error', 'Vui lòng chọn phòng ban hoặc nhóm trước khi thay đổi quyền.');
    return;
  }

  pendingAction.value = { type: 'toggle', roleCode, permissionKey, cell };
}

function requestRestore() {
  if (!inspectPanel.value || restoring.value) return;
  pendingAction.value = {
    type: 'restore',
    roleCode: inspectPanel.value.roleCode,
    permissionKey: inspectPanel.value.permissionKey,
    cell: inspectPanel.value.cell,
  };
}

async function onConfirmAction() {
  const action = pendingAction.value;
  if (!action || confirmLoading.value) return;

  confirmLoading.value = true;
  try {
    if (action.type === 'restore') {
      await restoreDefault(action);
    } else {
      await applyToggle(action);
    }
    pendingAction.value = null;
  } catch {
    // Toast lỗi đã hiện; giữ hộp thoại để người dùng thử lại hoặc huỷ.
  } finally {
    confirmLoading.value = false;
  }
}

async function applyToggle({ roleCode, permissionKey, cell }) {
  const cellKey = `${roleCode}|${permissionKey}`;
  if (pendingCells[cellKey]) {
    throw new Error('pending');
  }

  const newValue = !cell.effective;
  const scopeType = scope.value.type;
  const scopeId = scope.value.id;

  // Nếu scope != global mà chưa chọn scope_id cụ thể → không có gì để ghi
  if (scopeType !== 'global' && !scopeId) {
    showClientToast('error', 'Vui lòng chọn phòng ban hoặc nhóm trước khi thay đổi quyền.');
    throw new Error('missing-scope');
  }

  pendingCells[cellKey] = true;

  // Override tồn tại ĐÚNG tại scope hiện tại (không đụng override ở scope
  // khác — global_override khi scope=global, scoped_override khi scope=
  // department/team, xem PermissionService::matrixFor()).
  const overrideAtCurrentScope = scopeType === 'global' ? cell.global_override : cell.scoped_override;

  try {
    let cellResult;

    if (newValue === cell.default && overrideAtCurrentScope !== null) {
      // Giá trị mới trùng default VÀ đang có override tại đúng scope này
      // (override đó chính là lý do effective khác default trước đây) →
      // xoá override để quay lại default/scope khác, không tạo override "vô nghĩa".
      const { data } = await window.axios.delete('/api/permissions/grants', {
        data: {
          role_code: roleCode,
          permission_key: permissionKey,
          scope_type: scopeType,
          scope_id: scopeId,
        },
      });
      cellResult = data.cell;
    } else {
      // Khác default, hoặc chưa có override tại scope này (kể cả khi giá
      // trị mới trùng default — vẫn cần ghi rõ override để thắng 1 override
      // khác giá trị đang tồn tại ở global, xem ca "global=false + scoped=true"
      // trong matrixFor()) → luôn upsert tại đúng scope hiện tại.
      const { data } = await window.axios.put('/api/permissions/grants', {
        role_code: roleCode,
        permission_key: permissionKey,
        granted: newValue,
        scope_type: scopeType,
        scope_id: scopeId,
      });
      cellResult = data.cell;
    }

    // Server trả về cell mới nhất (default/effective/override/nguồn) —
    // patch ngay vào đúng ô, không cần tải lại cả bảng.
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
    if (status === 422) {
      showClientToast('error', message || 'Dữ liệu không hợp lệ.');
    } else if (status === 403) {
      showClientToast('error', message || 'Bạn không có quyền thay đổi quyền hệ thống này.');
    } else {
      showClientToast('error', message || 'Không lưu được thay đổi. Vui lòng thử lại.');
    }
    throw error;
  } finally {
    delete pendingCells[cellKey];
  }
}

function onInspect({ roleCode, permissionKey, cell }) {
  if (!roleCode || !cell) return;
  inspectPanel.value = { roleCode, permissionKey, cell };
}

function closeInspect() {
  inspectPanel.value = null;
}

async function restoreDefault(action = inspectPanel.value) {
  if (!action || restoring.value) return;
  const { roleCode, permissionKey } = action;
  const scopeType = scope.value.type;
  const scopeId = scope.value.id;

  restoring.value = true;
  try {
    const { data } = await window.axios.delete('/api/permissions/grants', {
      data: {
        role_code: roleCode,
        permission_key: permissionKey,
        scope_type: scopeType,
        scope_id: scopeId,
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

loadMatrix();
</script>

<template>
  <section class="perm-page">
    <PageHeader
      title="Quản lý phân quyền"
      icon="shield"
      description="Xem và chỉnh quyền theo vai trò trong từng phạm vi: toàn hệ thống, phòng ban hoặc nhóm."
      :breadcrumbs="[
        { label: 'Trang chủ', to: { name: 'home' } },
        { label: 'Quản lý phân quyền' },
      ]"
    >
      <template #actions>
        <button type="button" class="perm-page__refresh" :disabled="isLoading" @click="loadMatrix">
          <AppIcon name="refresh" :size="16" :class="{ 'perm-page__spin': isLoading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

    <div class="perm-page__body">
      <div class="perm-page__main">
        <div class="perm-page__toolbar">
          <PermissionScopeFilter :model-value="scope" @update:model-value="onScopeChange" />
        </div>

        <div v-if="scope.type !== 'global' && !scope.id" class="perm-page__hint">
          Vui lòng chọn {{ scope.type === 'department' ? 'phòng ban' : 'nhóm' }} để xem ma trận theo phạm vi này.
        </div>

        <div v-else-if="isLoading" class="perm-page__hint">Đang tải…</div>

        <PermissionMatrixTable
          v-else
          :roles="roles"
          :modules="modules"
          :permissions="permissions"
          :matrix="matrix"
          :pending-cells="pendingCells"
          :active-key="activeCellKey"
          @toggle="requestToggle"
          @inspect="onInspect"
        />
      </div>

      <aside v-if="inspectPanel" class="perm-page__side">
        <div class="perm-side__header">
          <h2 class="perm-side__title">Chi tiết quyền</h2>
          <button type="button" class="perm-side__close" aria-label="Đóng" @click="closeInspect">
            <AppIcon name="close" :size="16" />
          </button>
        </div>

        <p class="perm-side__name">
          {{ permissionByKey[inspectPanel.permissionKey]?.label ?? inspectPanel.permissionKey }}
        </p>
        <p class="perm-side__desc">
          {{ permissionByKey[inspectPanel.permissionKey]?.description }}
        </p>

        <div class="perm-side__rows">
          <div class="perm-side__row">
            <span class="perm-side__row-label">Vai trò</span>
            <span class="perm-side__row-value">{{ roleByCode[inspectPanel.roleCode]?.label ?? inspectPanel.roleCode }}</span>
          </div>
          <div class="perm-side__row">
            <span class="perm-side__row-label">Phạm vi đang xem</span>
            <span class="perm-side__row-value">{{ scopeLabel }}</span>
          </div>
          <div class="perm-side__row">
            <span class="perm-side__row-label">Hiện tại</span>
            <span class="perm-side__row-value">
              <span
                class="perm-side__dot"
                :class="inspectPanel.cell.effective ? 'perm-side__dot--granted' : 'perm-side__dot--denied'"
              />
              {{ inspectPanel.cell.effective ? 'Được cấp' : 'Không được cấp' }}
            </span>
          </div>
          <div class="perm-side__row">
            <span class="perm-side__row-label">Mặc định ban đầu</span>
            <span class="perm-side__row-value">
              <span
                class="perm-side__dot"
                :class="inspectPanel.cell.default ? 'perm-side__dot--granted' : 'perm-side__dot--denied'"
              />
              {{ inspectPanel.cell.default ? 'Được cấp' : 'Không được cấp' }}
            </span>
          </div>
        </div>

        <p class="perm-side__explain">{{ sourceExplanation(inspectPanel.cell) }}</p>

        <div v-if="inspectPanel.cell.reserved" class="perm-side__reserved-note">
          <AppIcon name="lock" :size="16" />
          Đây là quyền hệ thống, chỉ super_admin mới giữ được. Không thể đổi ở đây.
        </div>
        <template v-else>
          <button
            type="button"
            class="perm-side__toggle-btn"
            :disabled="pendingCells[`${inspectPanel.roleCode}|${inspectPanel.permissionKey}`]"
            @click="requestToggle({ roleCode: inspectPanel.roleCode, permissionKey: inspectPanel.permissionKey, cell: inspectPanel.cell })"
          >
            {{ inspectPanel.cell.effective ? 'Thu hồi quyền này' : 'Cấp quyền này' }}
          </button>

          <button
            v-if="inspectPanel.cell.scoped_override !== null || inspectPanel.cell.global_override !== null"
            type="button"
            class="perm-side__restore-btn"
            :disabled="restoring"
            @click="requestRestore"
          >
            {{ restoring ? 'Đang khôi phục…' : 'Bỏ thiết lập riêng, quay về mặc định' }}
          </button>
        </template>
      </aside>
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
.perm-page {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
}

.perm-page__refresh {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  height: 2rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 500;
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  cursor: pointer;
}

.perm-page__refresh:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.perm-page__refresh:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

:deep(.perm-page__spin) {
  animation: perm-page-spin 0.8s linear infinite;
}

@keyframes perm-page-spin {
  to {
    transform: rotate(360deg);
  }
}

.perm-page__body {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-4);
  overflow: hidden;
}

.perm-page__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  overflow: hidden;
}

.perm-page__toolbar {
  flex-shrink: 0;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
}

.perm-page__hint {
  padding: var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.perm-page__side {
  flex-shrink: 0;
  width: 20rem;
  overflow-y: auto;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
}

.perm-side__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.perm-side__title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 700;
}

.perm-side__close {
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

.perm-side__close:hover {
  background: var(--color-surface-muted);
}

.perm-side__name {
  margin: var(--space-3) 0 0.25rem;
  color: var(--color-text);
  font-weight: 700;
  font-size: 1rem;
}

.perm-side__desc {
  margin: 0 0 var(--space-4);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.5;
}

/* Danh sách field ngay hàng, đều nhau — nhãn trái/giá trị phải trên cùng 1 dòng,
   thay cho bố cục dl 2 cột lệch trước đây. */
.perm-side__rows {
  margin: 0 0 var(--space-3);
  display: flex;
  flex-direction: column;
}

.perm-side__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.perm-side__row:last-child {
  box-shadow: none;
}

.perm-side__row-label {
  color: var(--color-text-muted);
}

.perm-side__row-value {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  color: var(--color-text);
  font-weight: 600;
  text-align: right;
}

.perm-side__dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  flex-shrink: 0;
}

.perm-side__dot--granted {
  background: var(--color-success);
}

.perm-side__dot--denied {
  background: var(--color-danger);
}

.perm-side__explain {
  margin: 0 0 var(--space-4);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.5;
}

.perm-side__toggle-btn {
  width: 100%;
  padding: 0.625rem;
  border: 1px solid var(--color-primary);
  border-radius: var(--radius-md);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: var(--font-family-base);
  font-weight: 700;
  font-size: 0.875rem;
  cursor: pointer;
}

.perm-side__toggle-btn:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.perm-side__toggle-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.perm-side__restore-btn {
  width: 100%;
  margin-top: var(--space-2);
  padding: 0.5rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-weight: 600;
  font-size: 0.8125rem;
  cursor: pointer;
}

.perm-side__restore-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.perm-side__restore-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.perm-side__reserved-note {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.5;
}

@media (max-width: 1024px) {
  .perm-page__body {
    flex-direction: column;
    overflow-y: auto;
  }

  .perm-page__side {
    width: 100%;
  }
}

@media (max-width: 768px) {
  .perm-page {
    padding: var(--space-4);
  }
}
</style>
