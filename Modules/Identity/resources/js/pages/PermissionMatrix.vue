<script setup>
//
// superadmin/permissions — Ma trận phân quyền: xem quyền hiệu lực theo role,
// override (cấp/thu hồi) theo scope global/department/team, khôi phục mặc định.
// Backend (PermissionService::matrixFor()) là single source of truth — trang
// này không tự suy luận effective/default, chỉ đọc và gọi API khi toggle.
//
import { reactive, ref } from 'vue';
import { showClientToast } from '@/lib/clientToast';
import PermissionScopeFilter from '../components/PermissionScopeFilter.vue';
import PermissionMatrixTable from '../components/PermissionMatrixTable.vue';

const scope = ref({ type: 'global', id: null });
const roles = ref([]);
const modules = ref([]);
const permissions = ref([]);
const matrix = ref({});
const isLoading = ref(false);
const pendingCells = reactive({});
const inspectPanel = ref(null); // { roleCode, permissionKey, cell }

const SOURCE_LABEL = {
  config: 'Mặc định (config)',
  global: 'Override cấp Toàn hệ thống',
  scoped: 'Override cấp phạm vi hiện tại',
};

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
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không tải được ma trận phân quyền.');
  } finally {
    isLoading.value = false;
  }
}

function onScopeChange(newScope) {
  scope.value = newScope;
  loadMatrix();
}

async function onToggle({ roleCode, permissionKey, cell }) {
  const cellKey = `${roleCode}|${permissionKey}`;
  if (pendingCells[cellKey]) return;

  const newValue = !cell.effective;
  const scopeType = scope.value.type;
  const scopeId = scope.value.id;

  // Nếu scope != global mà chưa chọn scope_id cụ thể → không có gì để ghi
  if (scopeType !== 'global' && !scopeId) {
    showClientToast('error', 'Vui lòng chọn phòng ban/team trước khi thay đổi quyền.');
    return;
  }

  pendingCells[cellKey] = true;

  // Optimistic update
  const previousCell = { ...cell };
  // Override tồn tại ĐÚNG tại scope hiện tại (không đụng override ở scope
  // khác — global_override khi scope=global, scoped_override khi scope=
  // department/team, xem PermissionService::matrixFor()).
  const overrideAtCurrentScope = scopeType === 'global' ? cell.global_override : cell.scoped_override;

  try {
    if (newValue === cell.default && overrideAtCurrentScope !== null) {
      // Giá trị mới trùng default VÀ đang có override tại đúng scope này
      // (override đó chính là lý do effective khác default trước đây) →
      // xoá override để quay lại default/scope khác, không tạo override "vô nghĩa".
      await window.axios.delete('/api/permissions/grants', {
        data: {
          role_code: roleCode,
          permission_key: permissionKey,
          scope_type: scopeType,
          scope_id: scopeId,
        },
      });
    } else {
      // Khác default, hoặc chưa có override tại scope này (kể cả khi giá
      // trị mới trùng default — vẫn cần ghi rõ override để thắng 1 override
      // khác giá trị đang tồn tại ở global, xem ca "global=false + scoped=true"
      // trong matrixFor()) → luôn upsert tại đúng scope hiện tại.
      await window.axios.put('/api/permissions/grants', {
        role_code: roleCode,
        permission_key: permissionKey,
        granted: newValue,
        scope_type: scopeType,
        scope_id: scopeId,
      });
    }

    // Cập nhật lại từ server để đảm bảo effective_source/global_override/scoped_override đúng
    await loadMatrix();
  } catch (error) {
    // revert optimistic update (loadMatrix() ở nhánh success đã tự đồng bộ,
    // nhánh lỗi cần đảm bảo state cũ không bị thay đổi ngoài ý muốn)
    if (matrix.value[roleCode]) {
      matrix.value[roleCode][permissionKey] = previousCell;
    }
    const status = error?.response?.status;
    const message = error?.response?.data?.message;
    if (status === 422) {
      showClientToast('error', message || 'Dữ liệu không hợp lệ.');
    } else if (status === 403) {
      showClientToast('error', message || 'Bạn không có quyền thay đổi quyền hệ thống này.');
    } else {
      showClientToast('error', message || 'Không lưu được thay đổi. Vui lòng thử lại.');
    }
  } finally {
    delete pendingCells[cellKey];
  }
}

function onInspect({ roleCode, permissionKey, cell }) {
  inspectPanel.value = { roleCode, permissionKey, cell };
}

function closeInspect() {
  inspectPanel.value = null;
}

loadMatrix();
</script>

<template>
  <section class="perm-page">
    <header class="perm-page__header">
      <h1 class="perm-page__title">Quản lý phân quyền</h1>
      <p class="perm-page__subtitle">
        Xem và chỉnh sửa ma trận quyền theo vai trò. Thay đổi áp dụng ngay tại phạm vi đang chọn.
      </p>
    </header>

    <div class="perm-page__toolbar">
      <PermissionScopeFilter :model-value="scope" @update:model-value="onScopeChange" />
    </div>

    <div class="perm-page__legend">
      <span class="perm-page__legend-item"><span class="perm-page__legend-mark perm-page__legend-mark--granted">●</span> Được cấp</span>
      <span class="perm-page__legend-item"><span class="perm-page__legend-mark">○</span> Không được cấp</span>
      <span class="perm-page__legend-item"><span class="perm-page__legend-mark perm-page__legend-mark--override">◆</span> Có override</span>
      <span class="perm-page__legend-item"><span class="perm-page__legend-mark">🔒</span> Quyền hệ thống (khoá)</span>
    </div>

    <div v-if="scope.type !== 'global' && !scope.id" class="perm-page__hint">
      Vui lòng chọn {{ scope.type === 'department' ? 'phòng ban' : 'team' }} để xem ma trận theo phạm vi này.
    </div>

    <div v-else-if="isLoading" class="perm-page__hint">Đang tải…</div>

    <PermissionMatrixTable
      v-else
      :roles="roles"
      :modules="modules"
      :permissions="permissions"
      :matrix="matrix"
      :pending-cells="pendingCells"
      @toggle="onToggle"
      @inspect="onInspect"
    />

    <Teleport to="body">
      <div v-if="inspectPanel" class="inspect-panel" role="presentation" @mousedown.self="closeInspect">
        <div class="inspect-panel__box" role="dialog" aria-modal="true">
          <h2 class="inspect-panel__title">Nguồn gốc quyền</h2>
          <p class="inspect-panel__key">{{ inspectPanel.permissionKey }}</p>
          <dl class="inspect-panel__list">
            <dt>Đang áp dụng</dt>
            <dd>{{ SOURCE_LABEL[inspectPanel.cell.effective_source] }}</dd>
            <dt>Giá trị hiệu lực</dt>
            <dd>{{ inspectPanel.cell.effective ? 'Được cấp' : 'Không được cấp' }}</dd>
            <dt>Mặc định (config)</dt>
            <dd>{{ inspectPanel.cell.default ? 'Được cấp' : 'Không được cấp' }}</dd>
            <dt>Override toàn hệ thống</dt>
            <dd>
              {{
                inspectPanel.cell.global_override === null
                  ? 'Không có'
                  : inspectPanel.cell.global_override
                    ? 'Cấp'
                    : 'Thu hồi'
              }}
            </dd>
            <dt>Override phạm vi hiện tại</dt>
            <dd>
              {{
                inspectPanel.cell.scoped_override === null
                  ? 'Không có'
                  : inspectPanel.cell.scoped_override
                    ? 'Cấp'
                    : 'Thu hồi'
              }}
            </dd>
          </dl>
          <button type="button" class="inspect-panel__close" @click="closeInspect">Đóng</button>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
.perm-page {
  height: 100%;
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  padding: var(--space-5);
  overflow: hidden;
}

.perm-page__header {
  flex-shrink: 0;
}

.perm-page__title {
  margin: 0 0 var(--space-1);
  color: var(--color-text);
  font-size: 1.375rem;
  font-weight: 700;
}

.perm-page__subtitle {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.perm-page__toolbar {
  flex-shrink: 0;
}

.perm-page__legend {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-4);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  font-size: 0.8125rem;
  color: var(--color-text);
}

.perm-page__legend-item {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
}

.perm-page__legend-mark {
  color: var(--color-text-muted);
  font-weight: 700;
}

.perm-page__legend-mark--granted {
  color: var(--color-success);
}

.perm-page__legend-mark--override {
  color: var(--color-primary);
}

.perm-page__hint {
  padding: var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.inspect-panel {
  position: fixed;
  inset: 0;
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
  background: color-mix(in srgb, #000000 45%, transparent);
}

.inspect-panel__box {
  width: 100%;
  max-width: 24rem;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
  padding: var(--space-5);
}

.inspect-panel__title {
  margin: 0 0 var(--space-1);
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 700;
}

.inspect-panel__key {
  margin: 0 0 var(--space-3);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.inspect-panel__list {
  margin: 0 0 var(--space-4);
  display: grid;
  grid-template-columns: auto 1fr;
  gap: var(--space-1) var(--space-3);
  font-size: 0.8125rem;
}

.inspect-panel__list dt {
  color: var(--color-text-muted);
}

.inspect-panel__list dd {
  margin: 0;
  color: var(--color-text);
  font-weight: 600;
  text-align: right;
}

.inspect-panel__close {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-weight: 600;
  cursor: pointer;
}

.inspect-panel__close:hover {
  background: var(--color-surface-muted);
}

@media (max-width: 768px) {
  .perm-page {
    padding: var(--space-4);
  }
}
</style>
