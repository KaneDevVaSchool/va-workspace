<script setup>
//
// Chi tiết tài khoản dịch vụ — panel field ngay hàng (mục 14 CLAUDE.md):
// mỗi field 1 dòng ngang, nhãn trái mờ, giá trị phải. Chỉ mật khẩu thật
// chỉ hiện khi API trả can_see_secret=true (CredentialService::present())
// — username/email hiện công khai cho mọi người có quyền credential.view.
//
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import { formatDate } from '@/lib/formatTime';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { accountTypeLabel, statusLabel, STATUS_DOT_TONE, countdownLabel, countdownTone, isCountdownUrgent } from '../constants/credential.js';
import CredentialViewerPicker from '../components/CredentialViewerPicker.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const credential = ref(null);
const loading = ref(false);
const showPassword = ref(false);

const allUsers = ref([]);
const savingViewer = ref(false);

// ---------- Picker multi-select "Người được xem thông tin đăng nhập" ----------
// Đồng bộ 1 lần (PUT .../viewers) thay vì gọi API tuần tự từng người.
const viewerIds = ref([]);
const initialViewerIds = ref([]);

const viewerIdsChanged = computed(() => {
  const a = [...viewerIds.value].map(String).sort();
  const b = [...initialViewerIds.value].map(String).sort();
  return JSON.stringify(a) !== JSON.stringify(b);
});

function syncViewerIdsFromCredential() {
  const ids = (credential.value?.viewers || []).map((v) => v.id);
  viewerIds.value = [...ids];
  initialViewerIds.value = [...ids];
}

const canManage = computed(() => auth.can('credential.manage'));
const canManageViewers = computed(() => credential.value && (credential.value.created_by === auth.user?.id || canManage.value));

async function load() {
  loading.value = true;
  try {
    const { data } = await window.axios.get(`/api/credential/${route.params.id}`);
    credential.value = data.credential;
    showPassword.value = false;
    syncViewerIdsFromCredential();
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

async function saveViewers() {
  savingViewer.value = true;
  try {
    const { data } = await window.axios.put(`/api/credential/${credential.value.id}/viewers`, {
      user_ids: viewerIds.value,
    });
    credential.value = data.credential;
    syncViewerIdsFromCredential();
    showClientToast('success', 'Đã cập nhật danh sách người được cấp quyền.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không lưu được thay đổi.');
  } finally {
    savingViewer.value = false;
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
            <p
              v-if="countdownLabel(credential.expires_at)"
              class="credential-detail__countdown"
              :class="[
                countdownTone(credential.expires_at) ? `credential-detail__countdown--${countdownTone(credential.expires_at)}` : '',
                isCountdownUrgent(credential.expires_at) ? 'credential-detail__countdown--urgent' : '',
              ]"
            >
              <AppIcon v-if="isCountdownUrgent(credential.expires_at)" name="clock" :size="12" />
              {{ countdownLabel(credential.expires_at) }}
            </p>
          </div>
        </div>

        <div class="credential-detail__card">
          <div class="credential-detail__card-head">
            <span class="credential-detail__card-icon credential-detail__card-icon--tertiary">
              <AppIcon name="info" :size="15" />
            </span>
            <h3 class="credential-detail__card-title">Thông tin chung</h3>
          </div>
          <div class="credential-detail__row">
            <span class="credential-detail__row-label">Phòng ban sở hữu</span>
            <span class="credential-detail__row-value">{{ credential.department?.name || 'Chưa gán phòng ban' }}</span>
          </div>
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
          <div v-if="credential.access_url" class="credential-detail__row">
            <span class="credential-detail__row-label">Link truy cập</span>
            <span class="credential-detail__row-value">
              <a :href="credential.access_url" target="_blank" rel="noopener noreferrer" class="credential-detail__link">{{ credential.access_url }}</a>
            </span>
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
          <div class="credential-detail__card-head">
            <span class="credential-detail__card-icon credential-detail__card-icon--primary">
              <AppIcon name="lock" :size="15" />
            </span>
            <h3 class="credential-detail__card-title">Thông tin đăng nhập</h3>
          </div>

          <template v-if="credential.is_google_login">
            <div class="credential-detail__row">
              <span class="credential-detail__row-label">Cách đăng nhập</span>
              <span class="credential-detail__row-value">Bằng tài khoản Google công ty</span>
            </div>
            <div class="credential-detail__row">
              <span class="credential-detail__row-label">Google này thuộc về ai</span>
              <span class="credential-detail__row-value">
                {{ credential.google_account_owner_user?.name || credential.google_account_owner || 'Chưa ghi chú người sở hữu' }}
              </span>
            </div>
          </template>

          <template v-else>
            <div class="credential-detail__row">
              <span class="credential-detail__row-label">Tên đăng nhập</span>
              <span class="credential-detail__row-value">{{ credential.username || '—' }}</span>
            </div>
            <div class="credential-detail__row">
              <span class="credential-detail__row-label">Email</span>
              <span class="credential-detail__row-value">{{ credential.email || '—' }}</span>
            </div>
            <div v-if="credential.can_see_secret" class="credential-detail__row">
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
            <p v-else class="credential-detail__note">
              Chỉ {{ credential.creator_name || 'người tạo' }} và người được cấp quyền mới xem được mật khẩu.
            </p>
          </template>
        </div>

        <div v-if="costText()" class="credential-detail__card">
          <div class="credential-detail__card-head">
            <span class="credential-detail__card-icon credential-detail__card-icon--secondary">
              <AppIcon name="dollarSign" :size="15" />
            </span>
            <h3 class="credential-detail__card-title">Chi phí</h3>
          </div>
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
            <span class="credential-detail__row-value credential-detail__row-value--stacked">
              <span>{{ formatDate(credential.expires_at) }}</span>
              <span
                v-if="countdownLabel(credential.expires_at)"
                class="credential-detail__countdown"
                :class="[
                  countdownTone(credential.expires_at) ? `credential-detail__countdown--${countdownTone(credential.expires_at)}` : '',
                  isCountdownUrgent(credential.expires_at) ? 'credential-detail__countdown--urgent' : '',
                ]"
              >
                <AppIcon v-if="isCountdownUrgent(credential.expires_at)" name="clock" :size="11" />
                {{ countdownLabel(credential.expires_at) }}
              </span>
            </span>
          </div>
        </div>
      </div>

      <aside v-if="canManageViewers" class="credential-detail__side" aria-label="Người được cấp quyền xem">
        <div class="credential-detail__card-head">
          <span class="credential-detail__card-icon credential-detail__card-icon--primary">
            <AppIcon name="users" :size="15" />
          </span>
          <h3 class="credential-detail__card-title">Người được xem thông tin đăng nhập</h3>
        </div>

        <CredentialViewerPicker
          v-model="viewerIds"
          :users="allUsers"
          :disabled="savingViewer"
          placeholder="Gõ tên hoặc email để thêm người…"
          empty-text="Chưa cấp cho ai ngoài người tạo."
          tone="primary"
        />

        <button
          type="button"
          class="credential-detail__add-btn"
          :disabled="savingViewer || !viewerIdsChanged"
          @click="saveViewers"
        >
          {{ savingViewer ? 'Đang lưu…' : 'Lưu thay đổi' }}
        </button>
      </aside>
    </div>
  </section>
</template>

<style scoped>
.credential-detail {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: 0 var(--space-5) var(--space-3);
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

.credential-detail__countdown {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  margin: 0.25rem 0 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.credential-detail__countdown--danger {
  color: var(--color-danger);
}

.credential-detail__countdown--warning {
  color: var(--color-warning);
}

.credential-detail__countdown--urgent {
  font-weight: 700;
  animation: credential-detail-countdown-pulse 1.6s ease-in-out infinite;
}

@keyframes credential-detail-countdown-pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.55;
  }
}

@media (prefers-reduced-motion: reduce) {
  .credential-detail__countdown--urgent {
    animation: none;
  }
}

.credential-detail__card {
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
}

.credential-detail__card-head {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
}

.credential-detail__card-icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-sm);
}

.credential-detail__card-icon--tertiary {
  background: color-mix(in srgb, var(--color-tertiary) 10%, transparent);
  color: var(--color-tertiary);
}

.credential-detail__card-icon--primary {
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  color: var(--color-primary);
}

.credential-detail__card-icon--secondary {
  background: color-mix(in srgb, var(--color-secondary) 10%, transparent);
  color: var(--color-secondary);
}

.credential-detail__card-title {
  margin: 0;
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

.credential-detail__row-value--stacked {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.credential-detail__link {
  color: var(--color-tertiary);
  font-style: normal;
  text-decoration: none;
  overflow-wrap: anywhere;
}

.credential-detail__link:hover {
  text-decoration: underline;
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
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.credential-detail__add-btn {
  flex-shrink: 0;
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
