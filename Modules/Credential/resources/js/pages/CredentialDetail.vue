<script setup>
//
// Chi tiết tài khoản dịch vụ — panel field ngay hàng (mục 14 CLAUDE.md):
// mỗi field 1 dòng ngang, nhãn trái mờ, giá trị phải. Mật khẩu/username
// thật chỉ hiện khi API trả can_see_secret=true (CredentialService::present()).
//
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import { formatDate } from '@/lib/formatTime';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { accountTypeLabel, statusLabel, STATUS_DOT_TONE } from '../constants/credential.js';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const credential = ref(null);
const loading = ref(false);
const showPassword = ref(false);

const allUsers = ref([]);
const addingViewerId = ref('');
const savingViewer = ref(false);

const canManage = computed(() => auth.can('credential.manage'));
const canManageViewers = computed(() => credential.value && (credential.value.created_by === auth.user?.id || canManage.value));

const availableUsersToAdd = computed(() => {
  if (!credential.value) return [];
  const existingIds = new Set((credential.value.viewers || []).map((v) => v.id));
  existingIds.add(credential.value.created_by);
  return allUsers.value.filter((u) => !existingIds.has(u.id));
});

async function load() {
  loading.value = true;
  try {
    const { data } = await window.axios.get(`/api/credential/${route.params.id}`);
    credential.value = data.credential;
    showPassword.value = false;
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được tài khoản.');
    router.push({ name: 'manager.credential.index' });
  } finally {
    loading.value = false;
  }
}

async function loadUsers() {
  try {
    const { data } = await window.axios.get('/api/credential/users');
    allUsers.value = data.users ?? [];
  } catch {
    allUsers.value = [];
  }
}

function statusTone(value) {
  return STATUS_DOT_TONE[value] || 'neutral';
}

function costText() {
  if (!credential.value || credential.value.cost_hidden || credential.value.monthly_cost == null) return null;
  return `${Number(credential.value.monthly_cost).toLocaleString('vi-VN')} ${credential.value.currency || 'VND'} / tháng`;
}

async function addViewer() {
  if (!addingViewerId.value) return;
  savingViewer.value = true;
  try {
    const { data } = await window.axios.post(`/api/credential/${credential.value.id}/viewers`, {
      user_id: addingViewerId.value,
    });
    credential.value = data.credential;
    addingViewerId.value = '';
    showClientToast('success', 'Đã cấp quyền xem.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không cấp được quyền xem.');
  } finally {
    savingViewer.value = false;
  }
}

async function removeViewer(userId) {
  try {
    const { data } = await window.axios.delete(`/api/credential/${credential.value.id}/viewers/${userId}`);
    credential.value = data.credential;
    showClientToast('success', 'Đã thu hồi quyền xem.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không thu hồi được quyền xem.');
  }
}

onMounted(() => {
  load();
  loadUsers();
});
</script>

<template>
  <section class="credential-detail">
    <PageHeader title="Chi tiết tài khoản" icon="lock" :description="credential?.name || ''">
      <template #actions>
        <button type="button" class="credential-detail__header-btn" @click="router.push({ name: 'manager.credential.index' })">
          <AppIcon name="chevronLeft" :size="16" />
          Quay lại danh sách
        </button>
      </template>
    </PageHeader>

    <div v-if="loading" class="credential-detail__empty">Đang tải…</div>

    <div v-else-if="credential" class="credential-detail__body">
      <div class="credential-detail__main">
        <div class="credential-detail__lead">
          <span class="credential-detail__dot" :class="`credential-detail__dot--${statusTone(credential.status)}`" />
          <div>
            <h2 class="credential-detail__name">{{ credential.name }}</h2>
            <p class="credential-detail__sub">{{ statusLabel(credential.status) }}</p>
          </div>
        </div>

        <div class="credential-detail__card">
          <h3 class="credential-detail__card-title">Thông tin chung</h3>
          <div class="credential-detail__row">
            <span class="credential-detail__row-label">Nhà cung cấp</span>
            <span class="credential-detail__row-value">{{ credential.provider?.name || '—' }}</span>
          </div>
          <div class="credential-detail__row">
            <span class="credential-detail__row-label">Loại tài khoản</span>
            <span class="credential-detail__row-value">{{ accountTypeLabel(credential.account_type) }}</span>
          </div>
          <div class="credential-detail__row">
            <span class="credential-detail__row-label">Người tạo</span>
            <span class="credential-detail__row-value">{{ credential.creator_name || '—' }}</span>
          </div>
          <div v-if="credential.is_google_login" class="credential-detail__row">
            <span class="credential-detail__row-label">Đăng nhập Google công ty</span>
            <span class="credential-detail__row-value">{{ credential.google_account_owner || 'Chưa ghi chú người sở hữu' }}</span>
          </div>
          <div v-if="credential.server_name" class="credential-detail__row">
            <span class="credential-detail__row-label">Server</span>
            <span class="credential-detail__row-value">{{ credential.server_name }}</span>
          </div>
          <div v-if="credential.vps_cluster" class="credential-detail__row">
            <span class="credential-detail__row-label">Cụm VPS</span>
            <span class="credential-detail__row-value">{{ credential.vps_cluster }}</span>
          </div>
          <div v-if="credential.domain" class="credential-detail__row">
            <span class="credential-detail__row-label">Domain</span>
            <span class="credential-detail__row-value">{{ credential.domain }}</span>
          </div>
          <div v-if="credential.database_name" class="credential-detail__row">
            <span class="credential-detail__row-label">Database</span>
            <span class="credential-detail__row-value">{{ credential.database_name }}</span>
          </div>
          <div v-if="credential.is_root_account" class="credential-detail__row">
            <span class="credential-detail__row-label">Tài khoản root</span>
            <span class="credential-detail__row-value">Có</span>
          </div>
          <div v-if="credential.is_iam_account" class="credential-detail__row">
            <span class="credential-detail__row-label">Tài khoản IAM</span>
            <span class="credential-detail__row-value">Có</span>
          </div>
          <div v-if="credential.notes" class="credential-detail__row">
            <span class="credential-detail__row-label">Ghi chú</span>
            <span class="credential-detail__row-value">{{ credential.notes }}</span>
          </div>
        </div>

        <div class="credential-detail__card">
          <h3 class="credential-detail__card-title">Thông tin đăng nhập</h3>
          <template v-if="credential.can_see_secret">
            <div class="credential-detail__row">
              <span class="credential-detail__row-label">Tên đăng nhập</span>
              <span class="credential-detail__row-value">{{ credential.username || '—' }}</span>
            </div>
            <div class="credential-detail__row">
              <span class="credential-detail__row-label">Email</span>
              <span class="credential-detail__row-value">{{ credential.email || '—' }}</span>
            </div>
            <div class="credential-detail__row">
              <span class="credential-detail__row-label">Mật khẩu</span>
              <span class="credential-detail__row-value credential-detail__row-value--password">
                <span>{{ showPassword ? (credential.password || '—') : '••••••••' }}</span>
                <button
                  type="button"
                  class="credential-detail__icon-btn"
                  :aria-label="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                  @click="showPassword = !showPassword"
                >
                  <AppIcon :name="showPassword ? 'eyeOff' : 'eye'" :size="16" />
                </button>
              </span>
            </div>
          </template>
          <p v-else class="credential-detail__note">
            Chỉ {{ credential.creator_name || 'người tạo' }} và người được cấp quyền mới xem được thông tin đăng nhập.
          </p>
        </div>

        <div v-if="costText()" class="credential-detail__card">
          <h3 class="credential-detail__card-title">Chi phí</h3>
          <div class="credential-detail__row">
            <span class="credential-detail__row-label">Chi phí hằng tháng</span>
            <span class="credential-detail__row-value">{{ costText() }}</span>
          </div>
          <div v-if="credential.purchased_at" class="credential-detail__row">
            <span class="credential-detail__row-label">Ngày mua</span>
            <span class="credential-detail__row-value">{{ formatDate(credential.purchased_at) }}</span>
          </div>
          <div v-if="credential.expires_at" class="credential-detail__row">
            <span class="credential-detail__row-label">Ngày hết hạn</span>
            <span class="credential-detail__row-value">{{ formatDate(credential.expires_at) }}</span>
          </div>
        </div>
      </div>

      <aside v-if="canManageViewers" class="credential-detail__side" aria-label="Người được cấp quyền xem">
        <h3 class="credential-detail__card-title">Người được xem thông tin đăng nhập</h3>
        <ul class="credential-detail__viewer-list">
          <li v-if="!credential.viewers?.length" class="credential-detail__note">Chưa cấp cho ai ngoài người tạo.</li>
          <li v-for="viewer in credential.viewers" :key="viewer.id" class="credential-detail__viewer-item">
            <span>{{ viewer.name }}</span>
            <button type="button" class="credential-detail__icon-btn" aria-label="Thu hồi quyền xem" @click="removeViewer(viewer.id)">
              <AppIcon name="close" :size="14" />
            </button>
          </li>
        </ul>

        <div class="credential-detail__add-viewer">
          <select v-model="addingViewerId" class="credential-detail__select">
            <option value="">Chọn người để cấp quyền</option>
            <option v-for="u in availableUsersToAdd" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
          <button type="button" class="credential-detail__add-btn" :disabled="!addingViewerId || savingViewer" @click="addViewer">
            {{ savingViewer ? 'Đang cấp…' : 'Cấp quyền xem' }}
          </button>
        </div>
      </aside>
    </div>
  </section>
</template>

<style scoped>
.credential-detail {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
}

.credential-detail__header-btn {
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

.credential-detail__header-btn:hover {
  background: var(--color-surface-muted);
}

.credential-detail__empty {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-text-muted);
}

.credential-detail__body {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-4);
  margin-top: var(--space-4);
  overflow: hidden;
}

.credential-detail__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  overflow-y: auto;
}

.credential-detail__lead {
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
}

.credential-detail__dot {
  flex-shrink: 0;
  margin-top: 0.5rem;
  width: 0.625rem;
  height: 0.625rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.credential-detail__dot--success {
  background: var(--color-success);
}
.credential-detail__dot--info {
  background: var(--color-info);
}
.credential-detail__dot--warning {
  background: var(--color-warning);
}
.credential-detail__dot--danger {
  background: var(--color-danger);
}

.credential-detail__name {
  margin: 0;
  color: var(--color-text);
  font-size: 1.375rem;
  font-weight: 700;
}

.credential-detail__sub {
  margin: 0.25rem 0 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.credential-detail__card {
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
}

.credential-detail__card-title {
  margin: 0 0 var(--space-3);
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 700;
}

.credential-detail__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.credential-detail__row:last-child {
  box-shadow: none;
}

.credential-detail__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.credential-detail__row-label::after {
  content: ':';
}

.credential-detail__row-value {
  color: var(--color-text);
  font-style: italic;
  text-align: right;
  overflow-wrap: anywhere;
}

.credential-detail__row-value--password {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  font-style: normal;
  font-family: var(--font-family-mono, ui-monospace, monospace);
}

.credential-detail__note {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.credential-detail__icon-btn {
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

.credential-detail__icon-btn:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.credential-detail__side {
  flex-shrink: 0;
  width: 20rem;
  overflow-y: auto;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.credential-detail__viewer-list {
  list-style: none;
  margin: 0 0 var(--space-3);
  padding: 0;
}

.credential-detail__viewer-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  color: var(--color-text);
  font-size: 0.875rem;
}

.credential-detail__viewer-item:last-child {
  box-shadow: none;
}

.credential-detail__add-viewer {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.credential-detail__select {
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  transition: box-shadow 0.15s ease, border-color 0.15s ease;
}

.credential-detail__select:focus-visible,
.credential-detail__select:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 12%, transparent);
}

.credential-detail__add-btn {
  height: 2.25rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.credential-detail__add-btn:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.credential-detail__add-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 1024px) {
  .credential-detail__body {
    flex-direction: column;
    overflow-y: auto;
  }

  .credential-detail__side {
    width: 100%;
  }
}
</style>
