<script setup>
//
// Thanh "xem thử vai trò" — chỉ hiển thị cho super_admin thật (kiểm tra
// user.roles, không phải active_role, để switcher không tự ẩn khi đang
// xem thử role khác). Đổi role qua POST/DELETE /api/view-as, xem
// Modules/Identity/App/Services/ViewAsService.php.
//
import { ref } from 'vue';
import { useAuthStore } from '../stores/auth.js';

const auth = useAuthStore();
const isSwitching = ref(false);

// Khớp đúng 7 code trong Modules/Identity/Database/Seeders/RoleSeeder.php.
const ROLES = [
  { code: 'super_admin', label: 'Super Admin' },
  { code: 'admin', label: 'Admin' },
  { code: 'director_officer', label: 'Giám đốc điều hành' },
  { code: 'department_director', label: 'Trưởng phòng ban' },
  { code: 'team_lead', label: 'Trưởng nhóm' },
  { code: 'member', label: 'Nhân viên' },
  { code: 'viewer', label: 'Người xem' },
];

async function onSelect(event) {
  const roleCode = event.target.value;
  if (!roleCode) return;

  isSwitching.value = true;
  try {
    await auth.viewAs(roleCode);
  } finally {
    isSwitching.value = false;
  }
}

async function onExit() {
  isSwitching.value = true;
  try {
    await auth.exitViewAs();
  } finally {
    isSwitching.value = false;
  }
}
</script>

<template>
  <div v-if="auth.isSuperAdmin" class="view-as">
    <span class="view-as__label">Xem thử vai trò:</span>

    <select
      class="view-as__select"
      :value="auth.activeRole ?? ''"
      :disabled="isSwitching"
      @change="onSelect"
    >
      <option v-for="role in ROLES" :key="role.code" :value="role.code">
        {{ role.label }}
      </option>
    </select>

    <button
      v-if="auth.isImpersonating"
      type="button"
      class="view-as__exit"
      :disabled="isSwitching"
      @click="onExit"
    >
      Thoát xem thử
    </button>
  </div>
</template>

<style scoped>
.view-as {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-4);
  background: var(--color-primary-surface);
  box-shadow: 0 1px 0 var(--color-primary-surface-strong);
  font-size: 0.8125rem;
}

.view-as__label {
  color: var(--color-text-muted);
  font-weight: 600;
}

.view-as__select {
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
}

.view-as__exit {
  padding: var(--space-1) var(--space-3);
  border: 1px solid var(--color-primary);
  border-radius: var(--radius-sm);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.view-as__exit:hover {
  background: var(--color-primary-hover);
}

.view-as__exit:disabled,
.view-as__select:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 480px) {
  .view-as {
    padding: var(--space-2) var(--space-3);
  }
}
</style>
