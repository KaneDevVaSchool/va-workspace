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

const unassigned = ref([]);
const departmentGroups = ref([]);
const departmentOptions = ref([]);
const loading = ref(false);
const assigningId = ref(null);
const pendingDepartmentId = reactive({});
const brokenAvatarIds = ref(new Set());
const expandedDepartmentIds = ref(new Set());

function toggleDepartment(id) {
  const next = new Set(expandedDepartmentIds.value);
  if (next.has(id)) {
    next.delete(id);
  } else {
    next.add(id);
  }
  expandedDepartmentIds.value = next;
}

function isDepartmentExpanded(id) {
  return expandedDepartmentIds.value.has(id);
}

function formatRoles(member) {
  const roles = member?.roles ?? [];
  if (!roles.length) {
    return '—';
  }
  return roles.map((role) => role.name).join(', ');
}

async function load() {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/workspace-config/members/by-department');
    unassigned.value = data.unassigned ?? [];
    departmentGroups.value = data.departments ?? [];
    departmentOptions.value = data.department_options ?? [];
  } catch {
    showClientToast('error', 'Không tải được danh sách nhân sự theo phòng ban.');
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
    await load();
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
      subtitle="Danh sách toàn bộ nhân sự workspace theo phòng ban; gán phòng ban cho tài khoản chưa có đơn vị"
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
      <template v-else>
        <section class="wc-unassigned__section">
          <h2 class="wc-unassigned__section-title">Chưa gán phòng ban</h2>
          <p v-if="unassigned.length === 0" class="wc-unassigned__empty wc-unassigned__empty--section">
            Không có tài khoản nào đang chờ gán phòng ban.
          </p>
          <ul v-else class="wc-unassigned__list">
            <li v-for="member in unassigned" :key="member.id" class="wc-unassigned__item">
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
                  <option v-for="item in departmentOptions" :key="item.id" :value="String(item.id)">
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
        </section>

        <section class="wc-unassigned__section">
          <h2 class="wc-unassigned__section-title">Theo phòng ban</h2>
          <p v-if="departmentGroups.length === 0" class="wc-unassigned__empty wc-unassigned__empty--section">
            Chưa có phòng ban nào trong hệ thống.
          </p>
          <div v-else class="wc-unassigned__dept-list">
            <article
              v-for="group in departmentGroups"
              :key="group.id"
              class="wc-unassigned__dept"
              :class="{ 'wc-unassigned__dept--inactive': !group.is_active }"
            >
              <button
                type="button"
                class="wc-unassigned__dept-head"
                :aria-expanded="isDepartmentExpanded(group.id)"
                @click="toggleDepartment(group.id)"
              >
                <span class="wc-unassigned__dept-name">
                  {{ group.name }}
                  <span v-if="!group.is_active" class="wc-unassigned__dept-badge">Ngưng hoạt động</span>
                </span>
                <span class="wc-unassigned__dept-meta">{{ group.member_count }} nhân sự</span>
                <AppIcon
                  name="chevronDown"
                  :size="18"
                  class="wc-unassigned__dept-chevron"
                  :class="{ 'wc-unassigned__dept-chevron--open': isDepartmentExpanded(group.id) }"
                />
              </button>
              <ul v-show="isDepartmentExpanded(group.id)" class="wc-unassigned__list wc-unassigned__list--nested">
                <li v-if="group.members.length === 0" class="wc-unassigned__dept-empty">
                  Không có nhân sự trong phòng ban này.
                </li>
                <li v-for="member in group.members" :key="member.id" class="wc-unassigned__item wc-unassigned__item--roster">
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
                  <span class="wc-unassigned__meta">
                    <span class="wc-unassigned__meta-line">{{ formatRoles(member) }}</span>
                    <span class="wc-unassigned__meta-line wc-unassigned__meta-line--muted">
                      {{ member.status === 'active' ? 'Đang hoạt động' : 'Ngưng hoạt động' }}
                    </span>
                  </span>
                </li>
              </ul>
            </article>
          </div>
        </section>
      </template>
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

.wc-unassigned__section {
  margin-bottom: var(--space-6);
}

.wc-unassigned__section-title {
  margin: 0 0 var(--space-3);
  color: var(--color-text);
  font-size: 1rem;
  font-weight: 600;
}

.wc-unassigned__empty {
  margin: var(--space-6) 0;
  color: var(--color-text-muted);
  text-align: center;
}

.wc-unassigned__empty--section {
  margin: var(--space-3) 0;
  text-align: left;
}

.wc-unassigned__dept-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.wc-unassigned__dept {
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  overflow: hidden;
}

.wc-unassigned__dept--inactive {
  opacity: 0.92;
}

.wc-unassigned__dept-head {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  width: 100%;
  padding: var(--space-3) var(--space-4);
  border: none;
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: var(--font-family-base);
  text-align: left;
  cursor: pointer;
}

.wc-unassigned__dept-head:hover {
  background: var(--color-surface);
}

.wc-unassigned__dept-name {
  flex: 1;
  min-width: 0;
  font-weight: 600;
  font-size: 0.9375rem;
}

.wc-unassigned__dept-badge {
  margin-left: var(--space-2);
  padding: 0.125rem 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 500;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.wc-unassigned__dept-meta {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.wc-unassigned__dept-chevron {
  flex-shrink: 0;
  color: var(--color-text-muted);
  transition: transform 0.2s ease;
}

.wc-unassigned__dept-chevron--open {
  transform: rotate(180deg);
}

.wc-unassigned__list--nested {
  padding: var(--space-2) var(--space-3) var(--space-3);
}

.wc-unassigned__dept-empty {
  padding: var(--space-3);
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.wc-unassigned__item--roster {
  background: var(--color-bg);
}

.wc-unassigned__meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  flex-shrink: 0;
  min-width: 8rem;
  text-align: right;
}

.wc-unassigned__meta-line {
  color: var(--color-text);
  font-size: 0.8125rem;
}

.wc-unassigned__meta-line--muted {
  color: var(--color-text-muted);
  font-size: 0.75rem;
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
