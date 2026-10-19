<script setup>
import { computed, inject, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';

const router = useRouter();
const auth = useAuthStore();
const registerPageRefresh = inject('employeePageRefreshRegister', null);

const loading = ref(true);
const profile = ref(null);
const avatarBroken = ref(false);
const avatarUploading = ref(false);
const avatarInputRef = ref(null);

const changeDialogOpen = ref(false);
const changeSubmitting = ref(false);
const changeNote = ref('');
const changeDraft = ref({});

const displayName = computed(() => {
  const hrm = profile.value?.hrm;
  return hrm?.full_name || profile.value?.workspace?.name || auth.user?.name || '';
});

const avatarUrl = computed(() => profile.value?.avatar_url || auth.user?.avatar_url || null);

const hasAvatarPhoto = computed(() => Boolean(avatarUrl.value) && !avatarBroken.value);

const avatarInitial = computed(() => {
  const name = displayName.value || auth.user?.email || '?';
  return name.trim().charAt(0).toUpperCase() || '?';
});

const subtitle = computed(() => {
  const hrm = profile.value?.hrm;
  const ws = profile.value?.workspace;
  const parts = [
    hrm?.job_title || ws?.job_title_name,
    hrm?.department_name || ws?.department_name,
  ].filter(Boolean);
  return parts.join(' · ');
});

const pendingRequests = computed(() => profile.value?.pending_requests ?? []);

const identityRows = computed(() => {
  const hrm = profile.value?.hrm;
  const ws = profile.value?.workspace;
  return [
    { label: 'Họ và tên', value: hrm?.full_name || ws?.name },
    { label: 'Mã nhân sự', value: hrm?.code || ws?.employee_code },
    { label: 'Email công ty', value: hrm?.company_email || ws?.email },
    { label: 'Email cá nhân', value: hrm?.personal_email },
    { label: 'Số điện thoại', value: hrm?.phone },
    { label: 'Giới tính', value: formatGender(hrm?.gender) },
    { label: 'Ngày sinh', value: formatDate(hrm?.birthdate) },
  ].filter((row) => filled(row.value));
});

const workRows = computed(() => {
  const hrm = profile.value?.hrm;
  const ws = profile.value?.workspace;
  return [
    { label: 'Chức danh', value: hrm?.job_title || ws?.job_title_name },
    { label: 'Cấp bậc', value: hrm?.level_name },
    { label: 'Phòng ban', value: hrm?.department_name || ws?.department_name },
    { label: 'Bộ phận', value: hrm?.division_name },
    { label: 'Đơn vị', value: hrm?.org_unit_name },
    { label: 'Công ty', value: hrm?.company_name || ws?.company_name },
    { label: 'Quản lý trực tiếp', value: hrm?.manager_name || ws?.manager_display_name },
    { label: 'Nơi làm việc', value: hrm?.workplace },
    { label: 'Loại nhân sự', value: hrm?.personnel_type },
    { label: 'Mã chấm công', value: hrm?.attendance_code },
    { label: 'Ngày vào làm', value: formatDate(hrm?.hired_at) },
    { label: 'Ngày bắt đầu thực tế', value: formatDate(hrm?.actual_start_date) },
    { label: 'Trạng thái', value: hrm?.employment_status || hrm?.status },
  ].filter((row) => filled(row.value));
});

const concurrentRows = computed(() => profile.value?.hrm?.concurrent_positions ?? []);

const hrmMissing = computed(() => !loading.value && profile.value && !profile.value.hrm);

function filled(value) {
  return value !== null && value !== undefined && String(value).trim() !== '';
}

function formatGender(value) {
  if (!filled(value)) return null;
  const key = String(value).toLowerCase();
  if (key === 'male' || key === 'm') return 'Nam';
  if (key === 'female' || key === 'f') return 'Nữ';
  return String(value);
}

function formatDate(value) {
  if (!filled(value)) return null;
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  return new Intl.DateTimeFormat('vi-VN').format(d);
}

async function loadProfile() {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/dashboard/me/profile');
    profile.value = data;
    avatarBroken.value = false;
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được hồ sơ cá nhân.');
  } finally {
    loading.value = false;
  }
}

function openAvatarPicker() {
  avatarInputRef.value?.click();
}

async function onAvatarSelected(event) {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file) return;

  avatarUploading.value = true;
  try {
    const form = new FormData();
    form.append('avatar', file);
    const { data } = await window.axios.post('/api/dashboard/me/profile/avatar', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    if (data.user) {
      auth.user = data.user;
    } else if (data.avatar_url) {
      auth.user = { ...auth.user, avatar_url: data.avatar_url };
    }
    if (profile.value) {
      profile.value = { ...profile.value, avatar_url: data.avatar_url ?? auth.user?.avatar_url };
    }
    avatarBroken.value = false;
    showClientToast('success', 'Đã cập nhật ảnh đại diện.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được ảnh đại diện.');
  } finally {
    avatarUploading.value = false;
  }
}

function openChangeDialog() {
  const draft = {};
  for (const field of profile.value?.editable_fields ?? []) {
    draft[field.key] = '';
  }
  changeDraft.value = draft;
  changeNote.value = '';
  changeDialogOpen.value = true;
}

function closeChangeDialog() {
  if (changeSubmitting.value) return;
  changeDialogOpen.value = false;
}

async function submitChangeRequest() {
  changeSubmitting.value = true;
  try {
    const { data } = await window.axios.post('/api/dashboard/me/profile/change-requests', {
      changes: changeDraft.value,
      note: changeNote.value.trim() || null,
    });
    if (profile.value && data.request) {
      profile.value = {
        ...profile.value,
        pending_requests: [data.request, ...(profile.value.pending_requests ?? [])],
      };
    }
    changeDialogOpen.value = false;
    showClientToast('success', 'Đã gửi yêu cầu chỉnh sửa. HR sẽ xử lý sau.');
  } catch (error) {
    const msg = error?.response?.data?.message
      || error?.response?.data?.errors?.changes?.[0]
      || 'Không gửi được yêu cầu.';
    showClientToast('error', msg);
  } finally {
    changeSubmitting.value = false;
  }
}

function goBack() {
  if (window.history.length > 1) {
    router.back();
    return;
  }
  router.push({ name: 'dashboard.me' });
}

let unregisterPageRefresh;
onMounted(() => {
  loadProfile();
  if (typeof registerPageRefresh === 'function') {
    unregisterPageRefresh = registerPageRefresh(loadProfile);
  }
});
onBeforeUnmount(() => {
  unregisterPageRefresh?.();
});
</script>

<template>
  <div class="emp-profile">
    <button type="button" class="emp-profile__back" @click="goBack">
      <AppIcon name="chevronLeft" :size="18" :stroke-width="2" />
      <span>Quay lại</span>
    </button>

    <section v-if="loading" class="emp-profile__hero emp-profile__hero--loading" aria-busy="true">
      <div class="emp-profile__sk-avatar" />
      <div class="emp-profile__sk-lines">
        <span class="emp-profile__sk-line emp-profile__sk-line--title" />
        <span class="emp-profile__sk-line" />
      </div>
    </section>

    <section v-else class="emp-profile__hero">
      <div class="emp-profile__hero-bg" aria-hidden="true">
        <span class="emp-profile__orb emp-profile__orb--a" />
        <span class="emp-profile__orb emp-profile__orb--b" />
      </div>

      <div class="emp-profile__avatar-block">
        <button
          type="button"
          class="emp-profile__avatar-btn"
          :disabled="avatarUploading"
          aria-label="Đổi ảnh đại diện"
          @click="openAvatarPicker"
        >
          <img
            v-if="hasAvatarPhoto"
            class="emp-profile__avatar-img"
            :src="avatarUrl"
            alt=""
            @error="avatarBroken = true"
          />
          <span v-else class="emp-profile__avatar-fallback">{{ avatarInitial }}</span>
          <span class="emp-profile__avatar-edit">
            <AppIcon name="camera" :size="16" :stroke-width="2" />
          </span>
        </button>
        <input
          ref="avatarInputRef"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          class="emp-profile__avatar-input"
          @change="onAvatarSelected"
        />
        <p class="emp-profile__avatar-hint">Chạm để đổi ảnh đại diện Workspace</p>
      </div>

      <h1 class="emp-profile__name">{{ displayName || 'Hồ sơ của tôi' }}</h1>
      <p v-if="subtitle" class="emp-profile__subtitle">{{ subtitle }}</p>
    </section>

    <div v-if="hrmMissing" class="emp-profile__notice">
      <AppIcon name="info" :size="18" />
      <p>Chưa liên kết hồ sơ HRM. Thông tin hiển thị từ tài khoản Workspace.</p>
    </div>

    <div v-if="pendingRequests.length" class="emp-profile__pending">
      <h2 class="emp-profile__section-title">Yêu cầu đang chờ</h2>
      <article
        v-for="req in pendingRequests"
        :key="req.id"
        class="emp-profile__pending-card"
      >
        <p class="emp-profile__pending-meta">
          Gửi lúc {{ formatDate(req.created_at) }}
        </p>
        <ul class="emp-profile__pending-list">
          <li v-for="(item, key) in req.changes" :key="key">
            <span class="emp-profile__pending-label">{{ item.label }}:</span>
            <span class="emp-profile__pending-val">{{ item.requested }}</span>
          </li>
        </ul>
        <p v-if="req.note" class="emp-profile__pending-note">{{ req.note }}</p>
      </article>
    </div>

    <section v-if="!loading" class="emp-profile__card">
      <div class="emp-profile__card-head">
        <h2 class="emp-profile__section-title">Thông tin cá nhân</h2>
        <button
          type="button"
          class="emp-profile__edit-btn"
          :disabled="!profile?.hrm"
          @click="openChangeDialog"
        >
          <AppIcon name="pencil" :size="16" />
          <span>Yêu cầu sửa</span>
        </button>
      </div>
      <dl class="emp-profile__dl">
        <div v-for="row in identityRows" :key="row.label" class="emp-profile__row">
          <dt>{{ row.label }}</dt>
          <dd>{{ row.value }}</dd>
        </div>
      </dl>
    </section>

    <section v-if="!loading && workRows.length" class="emp-profile__card">
      <h2 class="emp-profile__section-title">Công việc &amp; tổ chức</h2>
      <dl class="emp-profile__dl">
        <div v-for="row in workRows" :key="row.label" class="emp-profile__row">
          <dt>{{ row.label }}</dt>
          <dd>{{ row.value }}</dd>
        </div>
      </dl>
    </section>

    <section v-if="!loading && concurrentRows.length" class="emp-profile__card">
      <h2 class="emp-profile__section-title">Kiêm nhiệm</h2>
      <ul class="emp-profile__concurrent">
        <li v-for="(item, idx) in concurrentRows" :key="idx" class="emp-profile__concurrent-item">
          <span class="emp-profile__concurrent-title">{{ item.job_title_name || 'Kiêm nhiệm' }}</span>
          <span class="emp-profile__concurrent-meta">
            {{ [item.company_name, item.department_name || item.org_unit_name].filter(Boolean).join(' · ') }}
          </span>
        </li>
      </ul>
    </section>

    <Teleport to="body">
      <div
        v-if="changeDialogOpen"
        class="emp-profile-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="emp-profile-change-title"
        @click.self="closeChangeDialog"
      >
        <div class="emp-profile-dialog__panel">
          <header class="emp-profile-dialog__head">
            <h2 id="emp-profile-change-title">Yêu cầu chỉnh sửa hồ sơ</h2>
            <button
              type="button"
              class="emp-profile-dialog__close"
              aria-label="Đóng"
              @click="closeChangeDialog"
            >
              <AppIcon name="close" :size="18" />
            </button>
          </header>
          <div class="emp-profile-dialog__body hide-scrollbar">
            <p class="emp-profile-dialog__lead">
              Chỉ điền các trường bạn muốn thay đổi. HR sẽ xem xét và cập nhật trên HRM.
            </p>
            <form class="emp-profile-dialog__form" @submit.prevent="submitChangeRequest">
              <div
                v-for="field in profile?.editable_fields ?? []"
                :key="field.key"
                class="emp-profile-dialog__field"
              >
                <label :for="`chg-${field.key}`">{{ field.label }}</label>
                <p class="emp-profile-dialog__current">
                  Hiện tại:
                  <span>{{ filled(field.value) ? field.value : '—' }}</span>
                </p>
                <input
                  :id="`chg-${field.key}`"
                  v-model="changeDraft[field.key]"
                  type="text"
                  class="emp-profile-dialog__input"
                  :placeholder="`Giá trị mới (${field.label.toLowerCase()})`"
                  autocomplete="off"
                />
              </div>
              <div class="emp-profile-dialog__field emp-profile-dialog__field--full">
                <label for="chg-note">Ghi chú (tuỳ chọn)</label>
                <textarea
                  id="chg-note"
                  v-model="changeNote"
                  class="emp-profile-dialog__textarea"
                  rows="3"
                  maxlength="2000"
                />
              </div>
            </form>
          </div>
          <footer class="emp-profile-dialog__actions">
            <button type="button" class="emp-profile-dialog__btn" @click="closeChangeDialog">
              Huỷ
            </button>
            <button
              type="button"
              class="emp-profile-dialog__btn emp-profile-dialog__btn--primary"
              :disabled="changeSubmitting"
              @click="submitChangeRequest"
            >
              {{ changeSubmitting ? 'Đang gửi…' : 'Gửi yêu cầu' }}
            </button>
          </footer>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.emp-profile {
  padding: var(--space-3) var(--space-4) var(--space-8);
  max-width: 32rem;
  margin-inline: auto;
}

.emp-profile__back {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  margin-bottom: var(--space-3);
  padding: var(--space-1) var(--space-2);
  border: none;
  border-radius: var(--radius-md);
  background: transparent;
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  cursor: pointer;
}

.emp-profile__hero {
  position: relative;
  margin-bottom: var(--space-4);
  padding: var(--space-5) var(--space-4) var(--space-4);
  border-radius: var(--radius-xl);
  background: linear-gradient(
    145deg,
    var(--color-tertiary-600) 0%,
    var(--color-tertiary-800) 100%
  );
  color: var(--color-on-primary);
  overflow: hidden;
  text-align: center;
  box-shadow: var(--shadow-md);
}

.emp-profile__hero-bg {
  pointer-events: none;
  position: absolute;
  inset: 0;
}

.emp-profile__orb {
  position: absolute;
  border-radius: var(--radius-full);
  filter: blur(0);
}

.emp-profile__orb--a {
  top: -2rem;
  right: -1rem;
  width: 7rem;
  height: 7rem;
  background: color-mix(in srgb, var(--color-gold) 35%, transparent);
  opacity: 0.7;
}

.emp-profile__orb--b {
  bottom: -3rem;
  left: -2rem;
  width: 9rem;
  height: 9rem;
  background: color-mix(in srgb, var(--color-on-primary) 12%, transparent);
}

.emp-profile__avatar-block {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
}

.emp-profile__avatar-btn {
  position: relative;
  width: 5.5rem;
  height: 5.5rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-on-primary) 18%, transparent);
  box-shadow:
    0 0 0 3px color-mix(in srgb, var(--color-on-primary) 35%, transparent),
    var(--shadow-md);
  cursor: pointer;
}

.emp-profile__avatar-btn:disabled {
  opacity: 0.7;
  cursor: wait;
}

.emp-profile__avatar-img,
.emp-profile__avatar-fallback {
  width: 100%;
  height: 100%;
  border-radius: var(--radius-full);
  object-fit: cover;
}

.emp-profile__avatar-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--color-on-primary);
}

.emp-profile__avatar-edit {
  position: absolute;
  right: 0;
  bottom: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-tertiary-700);
  box-shadow: var(--shadow-sm);
}

.emp-profile__avatar-input {
  position: absolute;
  width: 0;
  height: 0;
  opacity: 0;
  pointer-events: none;
}

.emp-profile__avatar-hint {
  margin: 0;
  font-size: var(--text-xs);
  color: color-mix(in srgb, var(--color-on-primary) 78%, transparent);
}

.emp-profile__name {
  position: relative;
  z-index: 1;
  margin: var(--space-3) 0 var(--space-1);
  font-size: 1.35rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.emp-profile__subtitle {
  position: relative;
  z-index: 1;
  margin: 0;
  font-size: var(--text-sm);
  color: color-mix(in srgb, var(--color-on-primary) 82%, transparent);
}

.emp-profile__notice {
  display: flex;
  gap: var(--space-2);
  align-items: flex-start;
  margin-bottom: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-info) 10%, var(--color-surface));
  color: var(--color-text);
  font-size: var(--text-sm);
  box-shadow: var(--shadow-sm);
}

.emp-profile__card {
  position: relative;
  margin-bottom: var(--space-3);
  padding: var(--space-4);
  padding-left: calc(var(--space-4) + var(--space-2) + 3px);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.emp-profile__card::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-tertiary-500);
}

.emp-profile__card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
}

.emp-profile__section-title {
  margin: 0 0 var(--space-3);
  font-size: var(--text-sm);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-muted);
}

.emp-profile__card-head .emp-profile__section-title {
  margin-bottom: 0;
}

.emp-profile__edit-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  padding: var(--space-1) var(--space-2);
  border: none;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-tertiary-500) 12%, var(--color-surface));
  color: var(--color-tertiary-700);
  font-size: var(--text-xs);
  font-weight: 600;
  cursor: pointer;
}

.emp-profile__edit-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.emp-profile__dl {
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.emp-profile__row {
  display: grid;
  grid-template-columns: minmax(0, 9rem) minmax(0, 1fr);
  gap: var(--space-2);
  align-items: start;
}

.emp-profile__row dt {
  margin: 0;
  font-size: var(--text-xs);
  font-weight: 600;
  color: var(--color-text-muted);
}

.emp-profile__row dt::after {
  content: ':';
}

.emp-profile__row dd {
  margin: 0;
  font-size: var(--text-sm);
  font-style: italic;
  color: var(--color-text);
  word-break: break-word;
}

.emp-profile__concurrent {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.emp-profile__concurrent-item {
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.emp-profile__concurrent-title {
  display: block;
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--color-text);
}

.emp-profile__concurrent-meta {
  display: block;
  margin-top: 2px;
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

.emp-profile__pending {
  margin-bottom: var(--space-3);
}

.emp-profile__pending-card {
  margin-top: var(--space-2);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-warning) 8%, var(--color-surface));
  box-shadow: var(--shadow-sm);
}

.emp-profile__pending-meta {
  margin: 0 0 var(--space-2);
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

.emp-profile__pending-list {
  margin: 0;
  padding-left: 1.1rem;
  font-size: var(--text-sm);
}

.emp-profile__pending-label {
  color: var(--color-text-muted);
}

.emp-profile__pending-val {
  font-style: italic;
}

.emp-profile__pending-note {
  margin: var(--space-2) 0 0;
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

.emp-profile__hero--loading {
  min-height: 10rem;
  background: var(--color-surface-muted);
}

.emp-profile__sk-avatar {
  width: 5rem;
  height: 5rem;
  margin-inline: auto;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  animation: emp-profile-pulse 1.2s ease-in-out infinite;
}

.emp-profile__sk-lines {
  margin-top: var(--space-3);
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  align-items: center;
}

.emp-profile__sk-line {
  display: block;
  width: 60%;
  height: 0.65rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  animation: emp-profile-pulse 1.2s ease-in-out infinite;
}

.emp-profile__sk-line--title {
  width: 45%;
  height: 0.85rem;
}

@keyframes emp-profile-pulse {
  0%,
  100% {
    opacity: 0.55;
  }
  50% {
    opacity: 1;
  }
}
</style>

<style>
.emp-profile-dialog {
  position: fixed;
  inset: 0;
  z-index: var(--z-modal, 1200);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.emp-profile-dialog__panel {
  display: flex;
  flex-direction: column;
  width: min(40rem, calc(100vw - 2.5rem));
  max-height: calc(100vh - 2.5rem);
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.emp-profile-dialog__head {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  padding: var(--space-4);
  box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 80%, transparent);
}

.emp-profile-dialog__head h2 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
}

.emp-profile-dialog__close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  cursor: pointer;
}

.emp-profile-dialog__body {
  flex: 1;
  min-height: 0;
  overflow: auto;
  padding: var(--space-4);
}

.emp-profile-dialog__lead {
  margin: 0 0 var(--space-4);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.emp-profile-dialog__form {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3) var(--space-4);
}

.emp-profile-dialog__field--full {
  grid-column: 1 / -1;
}

.emp-profile-dialog__field label {
  display: block;
  margin-bottom: var(--space-1);
  font-size: var(--text-xs);
  font-weight: 600;
  color: var(--color-text);
}

.emp-profile-dialog__current {
  margin: 0 0 var(--space-1);
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

.emp-profile-dialog__current span {
  font-style: italic;
}

.emp-profile-dialog__input,
.emp-profile-dialog__textarea {
  width: 100%;
  padding: var(--space-2) var(--space-3);
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font: inherit;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.emp-profile-dialog__actions {
  flex-shrink: 0;
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  padding: var(--space-4);
  box-shadow: 0 -1px 0 color-mix(in srgb, var(--color-border) 80%, transparent);
}

.emp-profile-dialog__btn {
  padding: var(--space-2) var(--space-4);
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  font-weight: 600;
  cursor: pointer;
}

.emp-profile-dialog__btn--primary {
  background: var(--color-tertiary-600);
  color: var(--color-on-primary);
}

@media (max-width: 640px) {
  .emp-profile-dialog__form {
    grid-template-columns: minmax(0, 1fr);
  }

  .emp-profile__row {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
