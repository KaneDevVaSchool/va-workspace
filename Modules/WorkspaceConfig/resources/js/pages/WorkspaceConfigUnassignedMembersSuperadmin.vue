<script setup>
//
// superadmin/workspace-config/unassigned — tài khoản chưa gắn phòng ban nào
// (department_id NULL, thường mới đăng nhập Google lần đầu, chờ tích hợp
// API HRM). Gán phòng ban ở đây là bước chặn: trưởng phòng chỉ thấy nút
// "Gán vai trò" trong /manager/workspace-config/members sau khi tài khoản
// đã có phòng ban — xem WorkspaceConfigMemberController::departmentIdOrFail().
//
import { onMounted, reactive, ref } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import {
  FALLBACK_AVATAR_SRC,
  FALLBACK_AVATAR_SRCSET,
} from '../constants/departmentDetail.js';

const members = ref([]);
const departments = ref([]);
const loading = ref(false);
const assigningId = ref(null);
const pendingDepartmentId = reactive({});
const brokenAvatarIds = ref(new Set());

async function load() {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/workspace-config/members/unassigned');
    members.value = data.members ?? [];
    departments.value = data.departments ?? [];
  } catch {
    showClientToast('error', 'Không tải được danh sách tài khoản chưa gán phòng ban.');
  } finally {
    loading.value = false;
  }
}

function usesPhoto(member) {
  return Boolean(member?.avatar_url) && !brokenAvatarIds.value.has(member.id);
}

function onAvatarError(id) {
  if (brokenAvatarIds.value.has(id)) return;
  const next = new Set(brokenAvatarIds.value);
  next.add(id);
  brokenAvatarIds.value = next;
}

async function assignDepartment(member) {
  const departmentId = pendingDepartmentId[member.id];
  if (!departmentId) {
    showClientToast('warning', 'Chọn phòng ban trước khi gán.');
    return;
  }

  assigningId.value = member.id;
  try {
    const { data } = await window.axios.put(`/api/workspace-config/members/${member.id}/department`, {
      department_id: Number(departmentId),
    });
    members.value = members.value.filter((item) => item.id !== member.id);
    showClientToast('success', `Đã gán "${data.member.name}" vào phòng ban "${data.member.department?.name ?? ''}".`);
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không gán được phòng ban. Vui lòng thử lại.');
  } finally {
    assigningId.value = null;
  }
}

onMounted(load);
</script>

<template>
  <section class="wc-unassigned">
    <PageHeader
      title="Nhân sự chưa gán phòng ban"
      subtitle="Tài khoản mới đăng nhập, chờ gán phòng ban để trưởng phòng gán tiếp vai trò"
      icon="userX"
      :breadcrumbs="[
        { label: 'Trang chủ', to: { name: 'home' } },
        { label: 'Cấu hình Workspace', to: { name: 'superadmin.workspace-config.overview' } },
        { label: 'Nhân sự chưa gán phòng ban' },
      ]"
    >
      <template #actions>
        <button type="button" class="wc-unassigned__header-btn" :disabled="loading" @click="load">
          <AppIcon name="refresh" :size="16" :class="{ 'wc-unassigned__spin': loading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

    <div class="wc-unassigned__body">
      <p v-if="loading" class="wc-unassigned__empty">Đang tải…</p>
      <p v-else-if="members.length === 0" class="wc-unassigned__empty">
        Không có tài khoản nào đang chờ gán phòng ban.
      </p>
      <ul v-else class="wc-unassigned__list">
        <li v-for="member in members" :key="member.id" class="wc-unassigned__item">
          <span class="wc-unassigned__avatar" aria-hidden="true">
            <img
              v-if="usesPhoto(member)"
              :src="member.avatar_url"
              alt=""
              class="wc-unassigned__avatar-img"
              referrerpolicy="no-referrer"
              @error="onAvatarError(member.id)"
            />
            <img
              v-else
              :src="FALLBACK_AVATAR_SRC"
              :srcset="FALLBACK_AVATAR_SRCSET"
              alt=""
              class="wc-unassigned__avatar-fallback"
            />
          </span>
          <span class="wc-unassigned__person">
            <span class="wc-unassigned__name">{{ member.name }}</span>
            <span class="wc-unassigned__email">{{ member.email || '—' }}</span>
          </span>
          <span class="wc-unassigned__assign">
            <select
              v-model="pendingDepartmentId[member.id]"
              class="wc-unassigned__input"
              :disabled="assigningId === member.id"
            >
              <option value="" disabled>Chọn phòng ban</option>
              <option v-for="item in departments" :key="item.id" :value="String(item.id)">
                {{ item.name }}
              </option>
            </select>
            <button
              type="button"
              class="wc-unassigned__btn"
              :disabled="assigningId === member.id || !pendingDepartmentId[member.id]"
              @click="assignDepartment(member)"
            >
              {{ assigningId === member.id ? 'Đang gán…' : 'Gán phòng ban' }}
            </button>
          </span>
        </li>
      </ul>
    </div>
  </section>
</template>

<style scoped>
.wc-unassigned {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
}

.wc-unassigned__header-btn {
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

.wc-unassigned__header-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.wc-unassigned__header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.wc-unassigned__spin {
  animation: wc-unassigned-spin 0.8s linear infinite;
}

@keyframes wc-unassigned-spin {
  to {
    transform: rotate(360deg);
  }
}

.wc-unassigned__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  margin-top: var(--space-4);
}

.wc-unassigned__empty {
  margin: var(--space-6) 0;
  color: var(--color-text-muted);
  text-align: center;
}

.wc-unassigned__list {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wc-unassigned__item {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.wc-unassigned__avatar {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 2.25rem;
  height: 2.25rem;
  overflow: hidden;
  border-radius: var(--radius-full);
  background: var(--color-primary);
}

.wc-unassigned__avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.wc-unassigned__avatar-fallback {
  box-sizing: border-box;
  width: 100%;
  height: 100%;
  padding: 4%;
  object-fit: contain;
}

.wc-unassigned__person {
  display: flex;
  flex: 1;
  min-width: 12rem;
  flex-direction: column;
}

.wc-unassigned__name {
  color: var(--color-text);
  font-weight: 600;
  font-size: 0.9375rem;
}

.wc-unassigned__email {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.wc-unassigned__assign {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: var(--space-2);
}

.wc-unassigned__input {
  min-width: 12rem;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
}

.wc-unassigned__btn {
  flex-shrink: 0;
  padding: 0.5rem 0.875rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.wc-unassigned__btn:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.wc-unassigned__btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .wc-unassigned {
    padding: var(--space-4);
  }

  .wc-unassigned__item {
    flex-direction: column;
    align-items: stretch;
  }

  .wc-unassigned__assign {
    flex-direction: column;
    align-items: stretch;
  }

  .wc-unassigned__input {
    min-width: 0;
  }
}

@media (max-width: 480px) {
  .wc-unassigned {
    padding: var(--space-3);
  }
}

@media (prefers-reduced-motion: reduce) {
  .wc-unassigned__spin {
    animation: none;
  }
}
</style>
