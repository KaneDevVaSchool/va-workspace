<script setup>
import { computed, inject, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { useEmployeeMobileView } from '@/composables/useEmployeeMobileView';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';

const auth = useAuthStore();
const { isEmployeeMobile } = useEmployeeMobileView();
/** Trong shell mobile: một header (EmployeeMobileLayout), không hero gradient thứ hai. */
const useMobileShellProfile = computed(() => isEmployeeMobile.value);
const registerPageRefresh = inject('employeePageRefreshRegister', null);

const loading = ref(true);
const profile = ref(null);
const avatarBroken = ref(false);
const avatarUploading = ref(false);
const avatarInputRef = ref(null);
const avatarVersion = ref(0);

const changeDialogOpen = ref(false);
const changeSubmitting = ref(false);
const changeNote = ref('');
const changeDraft = ref({});
const pageRevealed = ref(false);

const displayName = computed(() => {
  const hrm = profile.value?.hrm;
  return hrm?.full_name || profile.value?.workspace?.name || auth.user?.name || '';
});

const avatarUrl = computed(() => {
  const base = profile.value?.avatar_url || auth.user?.avatar_url || null;
  if (!base || avatarBroken.value) return null;
  if (!avatarVersion.value) return base;
  const sep = base.includes('?') ? '&' : '?';
  return `${base}${sep}v=${avatarVersion.value}`;
});

const hasAvatarPhoto = computed(() => Boolean(avatarUrl.value));

const avatarInitial = computed(() => {
  const name = displayName.value || auth.user?.email || '?';
  return name.trim().charAt(0).toUpperCase() || '?';
});

const departmentDisplayName = computed(() => {
  const hrm = profile.value?.hrm;
  const ws = profile.value?.workspace;
  return hrm?.department_name || hrm?.profile_department_name || ws?.department_name || null;
});

const divisionDisplayName = computed(() => {
  const hrm = profile.value?.hrm;
  if (filled(hrm?.division_name)) return hrm.division_name;
  const orgUnit = hrm?.org_unit_name;
  if (filled(orgUnit) && !labelsEqual(orgUnit, departmentDisplayName.value)) {
    return orgUnit;
  }
  return null;
});

const orgUnitDisplayName = computed(() => {
  const hrm = profile.value?.hrm;
  const name = hrm?.org_unit_name;
  if (!filled(name)) return null;
  if (labelsEqual(name, departmentDisplayName.value)) return null;
  if (labelsEqual(name, divisionDisplayName.value)) return null;
  return name;
});

const subtitle = computed(() => {
  const hrm = profile.value?.hrm;
  return uniqueDisplayParts(hrm?.job_title, departmentDisplayName.value, divisionDisplayName.value).join(' · ');
});

/** Chỉ mã NS trên hero — email/mã/công ty nằm trong thẻ bên dưới. */
const heroCode = computed(() => {
  const hrm = profile.value?.hrm;
  const ws = profile.value?.workspace;
  return hrm?.code || ws?.employee_code || null;
});

const pendingRequests = computed(() => profile.value?.pending_requests ?? []);

function profileField(label, value, icon = 'info') {
  if (!filled(value)) return null;
  return { label, value, icon };
}

/** true nếu giá trị đã hiển thị trên hero (không liệt kê lại). */
function isHeroDuplicate(value) {
  if (!filled(value)) return true;
  const v = String(value).trim().toLowerCase();
  const hrm = profile.value?.hrm;
  const ws = profile.value?.workspace;
  const code = hrm?.code || ws?.employee_code;
  const heroBits = [
    hrm?.job_title,
    departmentDisplayName.value,
    divisionDisplayName.value,
    displayName.value,
    code,
  ]
    .filter(filled)
    .map((s) => String(s).trim().toLowerCase());
  return heroBits.includes(v);
}

const contactRows = computed(() => {
  const hrm = profile.value?.hrm;
  const ws = profile.value?.workspace;
  return [
    profileField('Email công ty', hrm?.company_email || ws?.email, 'mail'),
    profileField('Email cá nhân', hrm?.personal_email, 'mail'),
    profileField('Số điện thoại', hrm?.phone, 'messageCircle'),
  ].filter(Boolean);
});

const personalRows = computed(() => {
  const hrm = profile.value?.hrm;
  return [
    profileField('Giới tính', formatGender(hrm?.gender), 'user'),
    profileField('Ngày sinh', formatDate(hrm?.birthdate), 'calendar'),
  ].filter(Boolean);
});

const orgGroupRows = computed(() => {
  const hrm = profile.value?.hrm;
  const ws = profile.value?.workspace;
  return [
    profileField('Công ty', hrm?.company_name || ws?.company_name, 'building'),
    profileField('Phòng ban', departmentDisplayName.value, 'building'),
    profileField('Bộ phận', divisionDisplayName.value, 'users'),
    profileField('Đơn vị', orgUnitDisplayName.value, 'globe'),
    profileField('Quản lý trực tiếp', directManagerLabel.value, 'userPlus'),
  ].filter(Boolean);
});

const workGroupRows = computed(() => {
  const hrm = profile.value?.hrm;
  const ws = profile.value?.workspace;
  const code = hrm?.code || ws?.employee_code;
  const jobTitle = hrm?.job_title;
  return [
    isHeroDuplicate(jobTitle) ? null : profileField('Chức danh', jobTitle, 'clipboardCheck'),
    isHeroDuplicate(code) ? null : profileField('Mã nhân sự', code, 'hash'),
    profileField('Mã chấm công', hrm?.attendance_code, 'hash'),
    profileField('Mã công ty', hrm?.company_code, 'building'),
    profileField('Mã đơn vị', hrm?.org_unit_code, 'hash'),
    profileField('Cấp bậc', hrm?.level_name, 'layers'),
    profileField('Loại nhân sự', hrm?.personnel_type, 'shield'),
    profileField('Trạng thái làm việc', formatEmploymentStatus(hrm?.employment_status), 'activity'),
    profileField('Trạng thái hồ sơ', formatHrmStatus(hrm?.status), 'shield'),
    profileField('Nơi làm việc', hrm?.workplace, 'globe'),
    profileField('Ngày vào làm', formatDate(hrm?.hired_at), 'calendar'),
    profileField('Ngày bắt đầu thực tế', formatDate(hrm?.actual_start_date), 'calendar'),
  ].filter(Boolean);
});

const subtitleChips = computed(() => {
  const hrm = profile.value?.hrm;
  return uniqueDisplayParts(
    hrm?.job_title,
    departmentDisplayName.value,
    divisionDisplayName.value,
    filled(heroCode.value) ? `Mã ${heroCode.value}` : null,
  );
});

const profileBlocks = computed(() => {
  const blocks = [];
  const contact = contactRows.value;
  const personal = personalRows.value;
  if (contact.length) {
    blocks.push({
      id: 'contact',
      title: 'Liên hệ',
      icon: 'mail',
      accent: 'tertiary',
      groups: [{ title: '', rows: contact }],
    });
  }
  if (personal.length) {
    blocks.push({
      id: 'personal',
      title: 'Thông tin cá nhân',
      icon: 'user',
      accent: 'secondary',
      groups: [{ title: '', rows: personal }],
    });
  }
  const org = orgGroupRows.value;
  if (org.length) {
    blocks.push({
      id: 'org',
      title: 'Tổ chức',
      icon: 'building',
      accent: 'primary',
      groups: [{ title: 'Phân công', rows: org }],
    });
  }
  const work = workGroupRows.value;
  if (work.length) {
    blocks.push({
      id: 'work',
      title: 'Hành chính & công việc',
      icon: 'clipboardCheck',
      accent: 'tertiary',
      iconVariant: 'work',
      groups: [{ title: '', rows: work }],
    });
  }
  return blocks;
});

const editableFieldGroups = computed(() => {
  const fields = profile.value?.editable_fields ?? [];
  const personalKeys = new Set(['full_name', 'gender', 'birthdate']);
  const contactKeys = new Set(['phone', 'personal_email', 'company_email']);
  const workKeys = new Set([
    'workplace',
    'attendance_code',
    'job_title',
    'personnel_type',
    'level_name',
    'hired_at',
    'actual_start_date',
  ]);
  const orgKeys = new Set([
    'manager_name',
    'department_name',
    'division_name',
    'org_unit_name',
    'profile_department_name',
  ]);
  const groups = [
    { id: 'personal', title: 'Thông tin cá nhân', keys: personalKeys },
    { id: 'contact', title: 'Liên hệ', keys: contactKeys },
    { id: 'org', title: 'Tổ chức', keys: orgKeys },
    { id: 'work', title: 'Công việc', keys: workKeys },
  ];
  return groups
    .map((group) => ({
      ...group,
      fields: fields.filter((field) => group.keys.has(field.key)),
    }))
    .filter((group) => group.fields.length > 0);
});

const canRequestProfileChanges = computed(
  () => Boolean(profile.value?.hrm) && (profile.value?.editable_fields?.length ?? 0) > 0,
);

const directManagerLabel = computed(() => {
  const hrm = profile.value?.hrm;
  if (hrm && filled(hrm.manager_name)) {
    return hrm.manager_name;
  }
  if (!hrm && filled(profile.value?.workspace?.manager_display_name)) {
    return profile.value.workspace.manager_display_name;
  }
  return null;
});

const concurrentRows = computed(() => profile.value?.hrm?.concurrent_positions ?? []);

const hrmMissing = computed(() => !loading.value && profile.value && !profile.value.hrm);

function filled(value) {
  return value !== null && value !== undefined && String(value).trim() !== '';
}

function labelsEqual(a, b) {
  if (!filled(a) || !filled(b)) return false;
  return String(a).trim().toLowerCase() === String(b).trim().toLowerCase();
}

/** Các nhãn hiển thị trên hero — bỏ trùng (không phân biệt hoa thường). */
function uniqueDisplayParts(...values) {
  const seen = new Set();
  const out = [];
  for (const value of values) {
    if (!filled(value)) continue;
    const key = String(value).trim().toLowerCase();
    if (seen.has(key)) continue;
    seen.add(key);
    out.push(String(value).trim());
  }
  return out;
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

function formatEmploymentStatus(value) {
  if (!filled(value)) return null;
  const key = String(value).toLowerCase();
  const map = {
    active: 'Đang làm việc',
    inactive: 'Ngừng làm việc',
    resigned: 'Đã nghỉ',
    terminated: 'Chấm dứt hợp đồng',
    on_leave: 'Tạm nghỉ',
  };
  return map[key] || String(value);
}

function formatHrmStatus(value) {
  if (!filled(value)) return null;
  const key = String(value).toLowerCase();
  if (key === 'active') return 'Đang hoạt động';
  if (key === 'inactive') return 'Không hoạt động';
  return String(value);
}

function formatFieldValueForDisplay(field) {
  if (!filled(field?.value)) return '—';
  if (field.key === 'gender') return formatGender(field.value) || field.value;
  if (field.type === 'date') return formatDate(field.value) || field.value;
  return field.value;
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
    requestAnimationFrame(() => {
      pageRevealed.value = true;
    });
  }
}

function openAvatarPicker() {
  if (avatarUploading.value) return;
  avatarInputRef.value?.click();
}

async function onAvatarSelected(event) {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file) return;

  if (file.size > 5 * 1024 * 1024) {
    showClientToast('error', 'Ảnh không được vượt quá 5MB.');
    return;
  }

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
      profile.value = {
        ...profile.value,
        avatar_url: data.avatar_url ?? auth.user?.avatar_url,
        avatar_is_custom: true,
      };
    }
    avatarBroken.value = false;
    avatarVersion.value += 1;
    if (typeof navigator !== 'undefined' && navigator.vibrate) {
      navigator.vibrate(10);
    }
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
  <div
    class="emp-profile"
    :class="{
      'emp-profile--revealed': pageRevealed && !loading,
      'emp-profile--mobile-shell': useMobileShellProfile,
    }"
  >
    <section
      v-if="loading && !useMobileShellProfile"
      class="emp-profile__hero emp-profile__hero--loading"
      aria-busy="true"
    >
      <div class="emp-profile__hero-bg" aria-hidden="true" />
      <div class="emp-profile__hero-body">
        <div class="emp-profile__sk-avatar" />
        <div class="emp-profile__sk-lines">
          <span class="emp-profile__sk-line emp-profile__sk-line--title" />
          <span class="emp-profile__sk-line" />
          <span class="emp-profile__sk-chips">
            <span /><span /><span />
          </span>
        </div>
      </div>
    </section>

    <section v-else-if="!useMobileShellProfile" class="emp-profile__hero">
      <div class="emp-profile__hero-bg" aria-hidden="true">
        <div class="emp-profile__hero-glow" />
      </div>
      <div class="emp-profile__hero-body">
        <button
          type="button"
          class="emp-profile__avatar-btn"
          :class="{ 'emp-profile__avatar-btn--busy': avatarUploading }"
          :disabled="avatarUploading"
          aria-label="Đổi ảnh đại diện"
          @click="openAvatarPicker"
        >
          <span class="emp-profile__avatar-ring" aria-hidden="true" />
          <img
            v-if="hasAvatarPhoto"
            class="emp-profile__avatar-img"
            :src="avatarUrl"
            alt=""
            @error="avatarBroken = true"
          />
          <span v-else class="emp-profile__avatar-fallback">{{ avatarInitial }}</span>
          <span class="emp-profile__avatar-edit" aria-hidden="true">
            <AppIcon v-if="!avatarUploading" name="camera" :size="16" :stroke-width="2" />
            <span v-else class="emp-profile__avatar-spinner" />
          </span>
        </button>
        <input
          ref="avatarInputRef"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          class="emp-profile__avatar-input"
          @change="onAvatarSelected"
        />

        <div class="emp-profile__identity-text">
          <h1 class="emp-profile__name">{{ displayName || 'Hồ sơ của tôi' }}</h1>
          <p v-if="subtitle" class="emp-profile__subtitle">{{ subtitle }}</p>
          <p v-if="filled(heroCode)" class="emp-profile__kicker">
            Mã {{ heroCode }}
          </p>
        </div>
      </div>
    </section>

    <section
      v-if="useMobileShellProfile && loading"
      class="emp-profile__hub emp-profile__hub--loading"
      aria-busy="true"
    >
      <div class="emp-profile__hub-top">
        <div class="emp-profile__sk-avatar emp-profile__sk-avatar--hub" />
        <div class="emp-profile__sk-lines emp-profile__sk-lines--hub">
          <span class="emp-profile__sk-line" />
          <span class="emp-profile__sk-line emp-profile__sk-line--short" />
        </div>
      </div>
      <div class="emp-profile__sk-block" />
    </section>

    <section v-else-if="useMobileShellProfile" class="emp-profile__hub">
      <div class="emp-profile__hub-top">
        <button
          type="button"
          class="emp-profile__avatar-btn emp-profile__avatar-btn--hub"
          :class="{ 'emp-profile__avatar-btn--busy': avatarUploading }"
          :disabled="avatarUploading"
          aria-label="Đổi ảnh đại diện"
          @click="openAvatarPicker"
        >
          <span class="emp-profile__avatar-ring emp-profile__avatar-ring--hub" aria-hidden="true" />
          <img
            v-if="hasAvatarPhoto"
            class="emp-profile__avatar-img"
            :src="avatarUrl"
            alt=""
            @error="avatarBroken = true"
          />
          <span v-else class="emp-profile__avatar-fallback emp-profile__avatar-fallback--hub">{{ avatarInitial }}</span>
          <span class="emp-profile__avatar-edit emp-profile__avatar-edit--hub" aria-hidden="true">
            <AppIcon v-if="!avatarUploading" name="camera" :size="14" :stroke-width="2" />
            <span v-else class="emp-profile__avatar-spinner" />
          </span>
        </button>
        <input
          ref="avatarInputRef"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          class="emp-profile__avatar-input"
          @change="onAvatarSelected"
        />
        <div class="emp-profile__hub-body">
          <h2 class="emp-profile__hub-name">{{ displayName || 'Hồ sơ của tôi' }}</h2>
          <p v-if="subtitle" class="emp-profile__hub-subtitle">{{ subtitle }}</p>
          <div v-if="subtitleChips.length" class="emp-profile__chips">
            <span v-for="chip in subtitleChips" :key="chip" class="emp-profile__chip">{{ chip }}</span>
          </div>
        </div>
      </div>
      <button
        v-if="canRequestProfileChanges"
        type="button"
        class="emp-profile__hub-cta"
        @click="openChangeDialog"
      >
        <AppIcon name="pencil" :size="18" :stroke-width="2" />
        <span>Yêu cầu chỉnh sửa hồ sơ</span>
      </button>
    </section>

    <div
      v-if="canRequestProfileChanges && !loading && !useMobileShellProfile"
      class="emp-profile__actions"
    >
      <button type="button" class="emp-profile__actions-btn" @click="openChangeDialog">
        <AppIcon name="pencil" :size="18" />
        <span>Yêu cầu chỉnh sửa hồ sơ</span>
      </button>
    </div>

    <div v-if="hrmMissing" class="emp-profile__notice">
      <span class="emp-profile__notice-icon" aria-hidden="true">
        <AppIcon name="info" :size="18" />
      </span>
      <p>Chưa liên kết hồ sơ HRM. Thông tin hiển thị từ tài khoản Workspace.</p>
    </div>

    <section v-if="pendingRequests.length" class="emp-profile__card emp-profile__card--pending">
      <header class="emp-profile__card-head">
        <div class="emp-profile__card-title">
          <span class="emp-profile__card-icon emp-profile__card-icon--pending" aria-hidden="true">
            <AppIcon name="clock" :size="16" />
          </span>
          <h2 class="emp-profile__section-title emp-profile__section-title--card">Yêu cầu đang chờ</h2>
        </div>
      </header>
      <article
        v-for="req in pendingRequests"
        :key="req.id"
        class="emp-profile__pending-card"
      >
        <p class="emp-profile__pending-meta">
          Chờ HCNS duyệt · gửi lúc {{ formatDate(req.created_at) }}
        </p>
        <ul class="emp-profile__pending-list">
          <li v-for="(item, key) in req.changes" :key="key">
            <span class="emp-profile__pending-label">{{ item.label }}:</span>
            <span class="emp-profile__pending-val">{{ item.requested }}</span>
          </li>
        </ul>
        <p v-if="req.note" class="emp-profile__pending-note">{{ req.note }}</p>
      </article>
    </section>

    <template v-if="loading && useMobileShellProfile">
      <div v-for="n in 3" :key="n" class="emp-profile__sk-card" aria-hidden="true" />
    </template>

    <template v-else-if="!loading">
    <section
      v-for="(block, blockIndex) in profileBlocks"
      :key="block.id"
      class="emp-profile__card emp-profile__card--anim emp-profile__card--accent"
      :class="`emp-profile__card--accent-${block.accent}`"
      :style="{ '--emp-card-i': blockIndex }"
    >
      <div class="emp-profile__card-head">
        <div class="emp-profile__card-title">
          <span
            class="emp-profile__card-icon"
            :class="block.iconVariant ? `emp-profile__card-icon--${block.iconVariant}` : null"
            aria-hidden="true"
          >
            <AppIcon :name="block.icon" :size="16" />
          </span>
          <h2 class="emp-profile__section-title emp-profile__section-title--card">{{ block.title }}</h2>
        </div>
      </div>
      <div
        v-for="(group, groupIndex) in block.groups"
        :key="group.title || groupIndex"
        class="emp-profile__group"
        :class="{ 'emp-profile__group--border': groupIndex > 0 }"
      >
        <h3 v-if="group.title" class="emp-profile__group-title">{{ group.title }}</h3>
        <ul class="emp-profile__fields">
          <li
            v-for="row in group.rows"
            :key="row.label"
            class="emp-profile__field"
            :class="{ 'emp-profile__field--manager': row.label === 'Quản lý trực tiếp' }"
          >
            <span class="emp-profile__field-icon" aria-hidden="true">
              <AppIcon :name="row.icon" :size="16" :stroke-width="1.75" />
            </span>
            <div class="emp-profile__field-body">
              <span class="emp-profile__field-label">{{ row.label }}</span>
              <span class="emp-profile__field-value">{{ row.value }}</span>
            </div>
          </li>
        </ul>
      </div>
    </section>
    </template>

    <section
      v-if="!loading && concurrentRows.length"
      class="emp-profile__card emp-profile__card--anim emp-profile__card--accent emp-profile__card--accent-secondary"
      :style="{ '--emp-card-i': profileBlocks.length }"
    >
      <div class="emp-profile__card-title emp-profile__card-title--solo">
        <span class="emp-profile__card-icon emp-profile__card-icon--concurrent" aria-hidden="true">
          <AppIcon name="layers" :size="16" />
        </span>
        <h2 class="emp-profile__section-title">Kiêm nhiệm</h2>
      </div>
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
      <Transition name="emp-sheet">
        <div
          v-if="changeDialogOpen"
          class="emp-profile-dialog"
          role="dialog"
          aria-modal="true"
          aria-labelledby="emp-profile-change-title"
          @click.self="closeChangeDialog"
        >
          <div class="emp-profile-dialog__panel" @click.stop>
            <div class="emp-profile-dialog__handle" aria-hidden="true" />
            <header class="emp-profile-dialog__head">
              <div class="emp-profile-dialog__head-text">
                <h2 id="emp-profile-change-title">Yêu cầu chỉnh sửa hồ sơ</h2>
              </div>
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
              <form class="emp-profile-dialog__form" @submit.prevent="submitChangeRequest">
                <section
                  v-for="group in editableFieldGroups"
                  :key="group.id"
                  class="emp-profile-dialog__section"
                >
                  <h3 class="emp-profile-dialog__section-title">{{ group.title }}</h3>
                  <div
                    v-for="field in group.fields"
                    :key="field.key"
                    class="emp-profile-dialog__field-card"
                  >
                    <label :for="`chg-${field.key}`" class="emp-profile-dialog__field-label">
                      {{ field.label }}
                    </label>
                    <p class="emp-profile-dialog__current">
                      {{ formatFieldValueForDisplay(field) }}
                    </p>
                    <select
                      v-if="field.type === 'select'"
                      :id="`chg-${field.key}`"
                      v-model="changeDraft[field.key]"
                      class="emp-profile-dialog__input emp-profile-dialog__select"
                    >
                      <option value="">—</option>
                      <option
                        v-for="opt in field.options ?? []"
                        :key="opt.value"
                        :value="opt.value"
                      >
                        {{ opt.label }}
                      </option>
                    </select>
                    <input
                      v-else
                      :id="`chg-${field.key}`"
                      v-model="changeDraft[field.key]"
                      :type="field.type === 'date' ? 'date' : field.type === 'email' ? 'email' : field.type === 'tel' ? 'tel' : 'text'"
                      class="emp-profile-dialog__input"
                      :placeholder="field.placeholder || ''"
                      autocomplete="off"
                    />
                  </div>
                </section>
                <div class="emp-profile-dialog__field-card emp-profile-dialog__field-card--full">
                  <label for="chg-note" class="emp-profile-dialog__field-label">Ghi chú</label>
                  <textarea
                    id="chg-note"
                    v-model="changeNote"
                    class="emp-profile-dialog__textarea"
                    rows="3"
                    maxlength="2000"
                    placeholder=""
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
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.emp-profile {
  padding: 0 0 var(--space-6);
  max-width: 36rem;
  margin-inline: auto;
}

.emp-profile--mobile-shell {
  --eh-shadow: var(
    --eh-card-shadow,
    0 4px 18px color-mix(in srgb, var(--color-tertiary-900) 7%, transparent)
  );
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: 0 0 var(--space-2);
  max-width: none;
  margin-inline: 0;
}

.emp-profile__hub {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: linear-gradient(
    165deg,
    color-mix(in srgb, var(--color-tertiary-surface) 55%, var(--color-surface) 45%) 0%,
    var(--color-surface) 58%
  );
  box-shadow: var(--eh-shadow);
}

.emp-profile__hub--loading {
  min-height: 9rem;
}

.emp-profile__hub-top {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: var(--space-3);
  align-items: start;
}

.emp-profile__hub-body {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  align-items: flex-start;
  text-align: left;
}

.emp-profile__hub-name {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  line-height: 1.25;
  color: var(--color-text);
}

.emp-profile__hub-subtitle {
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.4;
  color: var(--color-text-muted);
}

.emp-profile__chips {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-1);
}

.emp-profile__chip {
  display: inline-flex;
  align-items: center;
  max-width: 100%;
  padding: 0.25rem 0.55rem;
  border-radius: var(--radius-full);
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.01em;
  color: var(--color-tertiary-800);
  background: color-mix(in srgb, var(--color-tertiary-surface) 70%, var(--color-surface) 30%);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-tertiary-200) 55%, transparent);
}

.emp-profile__hub-cta {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  width: 100%;
  padding: var(--space-3);
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-tertiary-600);
  color: var(--color-on-primary);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 8px 20px color-mix(in srgb, var(--color-tertiary-800) 18%, transparent);
}

.emp-profile__hub-cta:active {
  transform: scale(0.99);
}

.emp-profile__avatar-btn--hub {
  width: 4.25rem;
  height: 4.25rem;
  flex-shrink: 0;
  background: transparent;
  box-shadow: none;
}

.emp-profile__avatar-ring--hub {
  box-shadow:
    0 0 0 2px color-mix(in srgb, var(--color-tertiary-400) 55%, var(--color-surface)),
    0 6px 16px color-mix(in srgb, var(--color-tertiary-800) 14%, transparent);
}

.emp-profile__avatar-fallback--hub {
  font-size: 1.25rem;
  color: var(--color-tertiary-800);
  background: linear-gradient(
    145deg,
    var(--color-surface) 0%,
    color-mix(in srgb, var(--color-tertiary-100) 75%, var(--color-surface)) 100%
  );
}

.emp-profile__avatar-edit--hub {
  width: 1.5rem;
  height: 1.5rem;
}

.emp-profile__sk-avatar--hub {
  width: 4rem;
  height: 4rem;
  border-radius: var(--radius-full);
  flex-shrink: 0;
  background: var(--color-surface-muted);
  animation: emp-profile-pulse 1.2s ease-in-out infinite;
}

.emp-profile__sk-lines--hub {
  flex: 1;
  align-items: flex-start;
}

.emp-profile__sk-block {
  height: 2.75rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  animation: emp-profile-pulse 1.2s ease-in-out infinite;
}

.emp-profile__sk-card {
  height: 7.5rem;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--eh-shadow);
  animation: emp-profile-pulse 1.2s ease-in-out infinite;
}

.emp-profile__actions {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.emp-profile__actions-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  width: 100%;
  padding: var(--space-3) var(--space-4);
  border: none;
  border-radius: var(--radius-lg);
  background: linear-gradient(
    135deg,
    var(--color-tertiary-600),
    var(--color-tertiary-700)
  );
  color: var(--color-on-primary);
  font-size: 0.9375rem;
  font-weight: 600;
  cursor: pointer;
  box-shadow: var(--eh-shadow);
}

.emp-profile--mobile-shell .emp-profile__actions-btn {
  background: var(--color-surface);
  color: var(--color-tertiary-700);
  box-shadow:
    var(--eh-shadow),
    inset 0 0 0 1px color-mix(in srgb, var(--color-tertiary-300) 45%, var(--color-border));
}

.emp-profile__actions-btn:active {
  transform: scale(0.99);
}

.emp-profile--mobile-shell .emp-profile__card,
.emp-profile--mobile-shell .emp-profile__notice {
  margin-inline: 0;
}

.emp-profile--mobile-shell .emp-profile__notice {
  box-shadow: var(--eh-shadow);
}

.emp-profile__fields {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0;
}

.emp-profile__field {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: var(--space-2);
  align-items: start;
  padding: var(--space-2) 0;
  border-bottom: 1px solid color-mix(in srgb, var(--color-border) 75%, transparent);
}

.emp-profile__field:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.emp-profile__field:first-child {
  padding-top: 0;
}

.emp-profile__field-icon {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  margin-top: 1px;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-tertiary-100) 45%, var(--color-surface-muted));
  color: var(--color-tertiary-700);
}

.emp-profile__field-body {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.emp-profile__field-label {
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.emp-profile__field-value {
  font-size: 0.9375rem;
  font-weight: 500;
  line-height: 1.4;
  color: var(--color-text);
  word-break: break-word;
}

.emp-profile__field--manager {
  position: relative;
  margin: var(--space-1) 0;
  padding: var(--space-2);
  border: none;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-tertiary-surface) 55%, var(--color-surface));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-tertiary-200) 45%, var(--color-border));
}

.emp-profile__field--manager .emp-profile__field-icon {
  background: color-mix(in srgb, var(--color-tertiary-500) 16%, var(--color-surface));
}

.emp-profile__field--manager .emp-profile__field-value {
  font-weight: 600;
  color: var(--color-tertiary-800);
}

.emp-profile__hero {
  position: relative;
  margin-bottom: var(--space-3);
  overflow: hidden;
  border-radius: 0 0 var(--radius-xl) var(--radius-xl);
  box-shadow: var(--shadow-md);
  clip-path: polygon(
    0 0,
    100% 0,
    100% calc(100% - 14px),
    calc(100% - 16px) 100%,
    16px 100%,
    0 calc(100% - 14px)
  );
  transform: translateY(8px);
  opacity: 0;
  transition:
    clip-path 0.55s cubic-bezier(0.22, 1, 0.36, 1),
    transform 0.55s cubic-bezier(0.22, 1, 0.36, 1),
    opacity 0.45s ease;
}

.emp-profile--revealed .emp-profile__hero {
  transform: none;
  opacity: 1;
  clip-path: polygon(
    0 0,
    100% 0,
    100% calc(100% - 14px),
    calc(100% - 16px) 100%,
    16px 100%,
    0 calc(100% - 14px)
  );
}

.emp-profile__hero-bg {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    135deg,
    color-mix(in srgb, var(--color-tertiary-600) 92%, #000) 0%,
    color-mix(in srgb, var(--color-tertiary-500) 75%, var(--color-primary-600)) 55%,
    color-mix(in srgb, var(--color-primary-500) 70%, var(--color-tertiary-400)) 100%
  );
}

.emp-profile__hero-glow {
  position: absolute;
  top: -30%;
  right: -20%;
  width: 70%;
  height: 120%;
  border-radius: 50%;
  background: radial-gradient(circle, rgb(255 255 255 / 0.22) 0%, transparent 68%);
  pointer-events: none;
}

.emp-profile__hero-body {
  position: relative;
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: var(--space-3);
  align-items: center;
  padding: var(--space-5) var(--space-4) var(--space-4);
  text-align: left;
}

.emp-profile__avatar-btn {
  position: relative;
  flex-shrink: 0;
  width: 6rem;
  height: 6rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: rgb(255 255 255 / 0.15);
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.emp-profile__avatar-btn:active:not(:disabled) {
  transform: scale(0.97);
}

.emp-profile__avatar-btn--busy {
  cursor: wait;
}

.emp-profile__avatar-ring {
  position: absolute;
  inset: -3px;
  border-radius: var(--radius-full);
  box-shadow:
    0 0 0 3px rgb(255 255 255 / 0.85),
    0 8px 24px rgb(0 0 0 / 0.18);
  pointer-events: none;
}

.emp-profile__avatar-img,
.emp-profile__avatar-fallback {
  position: relative;
  z-index: 1;
  width: 100%;
  height: 100%;
  border-radius: var(--radius-full);
  object-fit: cover;
}

.emp-profile__avatar-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--color-tertiary-800);
  background: linear-gradient(145deg, #fff 0%, color-mix(in srgb, var(--color-tertiary-100) 80%, #fff) 100%);
}

.emp-profile__avatar-edit {
  position: absolute;
  z-index: 2;
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
  box-shadow: var(--shadow-md);
}

.emp-profile__avatar-spinner {
  width: 0.875rem;
  height: 0.875rem;
  border: 2px solid color-mix(in srgb, var(--color-tertiary-500) 25%, transparent);
  border-top-color: var(--color-tertiary-600);
  border-radius: 50%;
  animation: emp-profile-spin 0.7s linear infinite;
}

@keyframes emp-profile-spin {
  to {
    transform: rotate(360deg);
  }
}

.emp-profile__avatar-input {
  position: absolute;
  width: 0;
  height: 0;
  opacity: 0;
  pointer-events: none;
}

.emp-profile__identity-text {
  min-width: 0;
  width: 100%;
  color: #fff;
}

.emp-profile__name {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  line-height: 1.25;
  text-shadow: 0 1px 2px rgb(0 0 0 / 0.12);
}

.emp-profile__subtitle {
  margin: var(--space-1) 0 0;
  font-size: var(--text-sm);
  line-height: 1.4;
  color: rgb(255 255 255 / 0.88);
}

.emp-profile__kicker {
  margin: var(--space-2) 0 0;
  font-size: var(--text-xs);
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: rgb(255 255 255 / 0.72);
}

.emp-profile__notice {
  display: flex;
  gap: var(--space-2);
  align-items: flex-start;
  margin: 0 0 var(--space-2);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-lg);
  background: color-mix(in srgb, var(--color-info) 10%, var(--color-surface));
  color: var(--color-text);
  font-size: var(--text-sm);
  line-height: 1.45;
  box-shadow: var(--shadow-sm);
}

.emp-profile__notice-icon {
  flex-shrink: 0;
  margin-top: 1px;
  color: var(--color-info);
}

.emp-profile__notice p {
  margin: 0;
}

.emp-profile__card {
  position: relative;
  margin: 0 0 var(--space-3);
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.emp-profile--mobile-shell .emp-profile__card {
  box-shadow: var(--eh-shadow);
}

.emp-profile__card--accent {
  padding-left: calc(var(--space-2) + 3px + var(--space-3));
}

.emp-profile__card--accent::before {
  content: '';
  position: absolute;
  top: var(--space-3);
  bottom: var(--space-3);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-tertiary-500);
}

.emp-profile__card--accent-primary::before {
  background: var(--color-primary-500);
}

.emp-profile__card--accent-secondary::before {
  background: var(--color-secondary-500);
}

.emp-profile__card--accent-tertiary::before {
  background: var(--color-tertiary-500);
}

.emp-profile__card--pending {
  padding-left: calc(var(--space-2) + 3px + var(--space-3));
  background: linear-gradient(
    180deg,
    color-mix(in srgb, var(--color-warning) 6%, var(--color-surface)) 0%,
    var(--color-surface) 100%
  );
}

.emp-profile__card--pending::before {
  content: '';
  position: absolute;
  top: var(--space-3);
  bottom: var(--space-3);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-warning);
}

.emp-profile__card::after {
  content: '';
  pointer-events: none;
  position: absolute;
  inset: 0;
  border-radius: inherit;
  box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.65);
}

.emp-profile__card--anim {
  opacity: 0;
  transform: translateY(12px);
  clip-path: inset(0 0 100% 0 round var(--radius-lg));
}

.emp-profile--revealed .emp-profile__card--anim {
  animation: emp-profile-card-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
  animation-delay: calc(0.08s + var(--emp-card-i, 0) * 0.07s);
}

@keyframes emp-profile-card-in {
  from {
    opacity: 0;
    transform: translateY(12px);
    clip-path: inset(0 0 100% 0 round var(--radius-lg));
  }
  to {
    opacity: 1;
    transform: none;
    clip-path: inset(0 0 0 0 round var(--radius-lg));
  }
}

.emp-profile__card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
}

.emp-profile__card-title {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-width: 0;
}

.emp-profile__card-title--solo {
  margin-bottom: var(--space-3);
}

.emp-profile__card-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-tertiary-500) 14%, var(--color-surface));
  color: var(--color-tertiary-700);
}

.emp-profile__card-icon--work {
  background: color-mix(in srgb, var(--color-primary-500) 12%, var(--color-surface));
  color: var(--color-primary-700);
}

.emp-profile__card-icon--concurrent {
  background: color-mix(in srgb, var(--color-warning) 14%, var(--color-surface));
  color: color-mix(in srgb, var(--color-warning) 85%, #000);
}

.emp-profile__section-title {
  margin: 0;
  font-size: var(--text-xs);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--color-text-muted);
}

.emp-profile__section-title--card {
  font-size: 0.8125rem;
  letter-spacing: 0.04em;
  color: var(--color-text);
}

.emp-profile__card-icon--pending {
  background: color-mix(in srgb, var(--color-warning) 18%, var(--color-surface));
  color: color-mix(in srgb, var(--color-warning) 90%, #000);
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
  white-space: nowrap;
}

.emp-profile__edit-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.emp-profile__group {
  margin-top: var(--space-2);
}

.emp-profile__group:first-of-type {
  margin-top: 0;
}

.emp-profile__group--border {
  padding-top: var(--space-3);
  margin-top: var(--space-3);
  border-top: 1px solid color-mix(in srgb, var(--color-border) 65%, transparent);
}

.emp-profile__group-title {
  margin: 0 0 var(--space-2);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.emp-profile__dl {
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0;
}

.emp-profile__dl--grid {
  gap: var(--space-1);
}

.emp-profile__row {
  display: grid;
  grid-template-columns: minmax(0, 6.75rem) minmax(0, 1fr);
  gap: var(--space-2);
  align-items: baseline;
  padding: var(--space-2) var(--space-2);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.emp-profile__row dt {
  margin: 0;
  font-size: var(--text-xs);
  font-weight: 600;
  color: var(--color-text-muted);
  line-height: 1.35;
}

.emp-profile__row dd {
  margin: 0;
  font-size: var(--text-sm);
  font-weight: 500;
  font-style: italic;
  color: var(--color-text);
  line-height: 1.4;
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
  padding: var(--space-2);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  border: 1px solid color-mix(in srgb, var(--color-border) 70%, transparent);
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

.emp-profile__pending-card {
  margin-top: var(--space-2);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-border) 70%, transparent);
}

.emp-profile__pending-card:first-of-type {
  margin-top: 0;
}

.emp-profile__pending-meta {
  margin: 0 0 var(--space-2);
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

.emp-profile__pending-list {
  margin: 0;
  padding-left: 1rem;
  font-size: var(--text-sm);
}

.emp-profile__pending-label {
  color: var(--color-text-muted);
}

.emp-profile__pending-val {
  font-weight: 500;
}

.emp-profile__pending-note {
  margin: var(--space-2) 0 0;
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

.emp-profile__hero--loading .emp-profile__hero-body {
  min-height: 11rem;
}

.emp-profile__sk-avatar {
  width: 5.5rem;
  height: 5.5rem;
  border-radius: var(--radius-full);
  background: rgb(255 255 255 / 0.2);
  animation: emp-profile-pulse 1.2s ease-in-out infinite;
}

.emp-profile__sk-lines {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--space-1);
}

.emp-profile__hero--loading .emp-profile__hero-body {
  grid-template-columns: auto minmax(0, 1fr);
  align-items: center;
  text-align: left;
}

.emp-profile__sk-line {
  display: block;
  width: 55%;
  height: 0.75rem;
  border-radius: var(--radius-full);
  background: rgb(255 255 255 / 0.2);
  animation: emp-profile-pulse 1.2s ease-in-out infinite;
}

.emp-profile__sk-line--title {
  width: 40%;
  height: 1rem;
}

.emp-profile__sk-chips {
  display: flex;
  gap: var(--space-1);
  margin-top: var(--space-1);
}

.emp-profile__sk-chips span {
  width: 4.5rem;
  height: 1.25rem;
  border-radius: var(--radius-full);
  background: rgb(255 255 255 / 0.16);
  animation: emp-profile-pulse 1.2s ease-in-out infinite;
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

@media (prefers-reduced-motion: reduce) {
  .emp-profile__hero,
  .emp-profile__card--anim {
    animation: none !important;
    transition: none !important;
    opacity: 1 !important;
    transform: none !important;
    clip-path: none !important;
  }
}

@media (min-width: 380px) {
  .emp-profile__dl--grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--space-2);
  }

  .emp-profile__dl--grid .emp-profile__row {
    grid-template-columns: minmax(0, 1fr);
    gap: 0.15rem;
    min-height: 100%;
  }
}
</style>

<style>
.emp-sheet-enter-active,
.emp-sheet-leave-active {
  transition: opacity 0.32s ease;
}

.emp-sheet-enter-active .emp-profile-dialog__panel,
.emp-sheet-leave-active .emp-profile-dialog__panel {
  transition:
    clip-path 0.48s cubic-bezier(0.22, 1, 0.36, 1),
    transform 0.48s cubic-bezier(0.22, 1, 0.36, 1);
}

.emp-sheet-enter-from,
.emp-sheet-leave-to {
  opacity: 0;
}

.emp-sheet-enter-from .emp-profile-dialog__panel,
.emp-sheet-leave-to .emp-profile-dialog__panel {
  clip-path: circle(0% at 50% 100%);
  transform: translateY(1.25rem);
}

.emp-sheet-enter-to .emp-profile-dialog__panel,
.emp-sheet-leave-from .emp-profile-dialog__panel {
  clip-path: circle(150% at 50% 100%);
  transform: none;
}

@media (prefers-reduced-motion: reduce) {
  .emp-sheet-enter-active .emp-profile-dialog__panel,
  .emp-sheet-leave-active .emp-profile-dialog__panel {
    transition: none;
  }

  .emp-sheet-enter-from .emp-profile-dialog__panel,
  .emp-sheet-leave-to .emp-profile-dialog__panel {
    clip-path: none;
    transform: none;
  }
}

.emp-profile-dialog {
  position: fixed;
  inset: 0;
  z-index: var(--z-modal, 1200);
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding: 0;
  background: color-mix(in srgb, var(--color-sidebar-overlay) 92%, transparent);
  backdrop-filter: blur(4px);
}

@media (min-width: 640px) {
  .emp-profile-dialog {
    align-items: center;
    padding: var(--space-5);
  }
}

.emp-profile-dialog__panel {
  display: flex;
  flex-direction: column;
  width: 100%;
  max-height: min(92vh, 40rem);
  overflow: hidden;
  border-radius: var(--radius-xl) var(--radius-xl) 0 0;
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
  clip-path: circle(150% at 50% 100%);
}

@media (min-width: 640px) {
  .emp-profile-dialog__panel {
    width: min(40rem, calc(100vw - 2.5rem));
    max-height: calc(100vh - 2.5rem);
    border-radius: var(--radius-xl);
    clip-path: circle(150% at 50% 50%);
  }

  .emp-sheet-enter-from .emp-profile-dialog__panel,
  .emp-sheet-leave-to .emp-profile-dialog__panel {
    clip-path: circle(0% at 50% 50%);
  }
}

.emp-profile-dialog__handle {
  flex-shrink: 0;
  width: 2.5rem;
  height: 0.25rem;
  margin: var(--space-2) auto 0;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-border) 85%, transparent);
}

@media (min-width: 640px) {
  .emp-profile-dialog__handle {
    display: none;
  }
}

.emp-profile-dialog__head {
  flex-shrink: 0;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-4) var(--space-4);
  background: linear-gradient(
    180deg,
    color-mix(in srgb, var(--color-tertiary-500) 10%, var(--color-surface)) 0%,
    var(--color-surface) 100%
  );
  box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 80%, transparent);
}

.emp-profile-dialog__head-text {
  min-width: 0;
}

.emp-profile-dialog__head h2 {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 700;
  letter-spacing: -0.02em;
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

.emp-profile-dialog__form {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.emp-profile-dialog__section {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.emp-profile-dialog__section-title {
  margin: 0;
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.emp-profile-dialog__select {
  appearance: none;
  background-image: linear-gradient(45deg, transparent 50%, var(--color-text-muted) 50%),
    linear-gradient(135deg, var(--color-text-muted) 50%, transparent 50%);
  background-position:
    calc(100% - 18px) calc(50% + 2px),
    calc(100% - 12px) calc(50% + 2px);
  background-size:
    6px 6px,
    6px 6px;
  background-repeat: no-repeat;
  padding-right: 2rem;
}

.emp-profile-dialog__field-card {
  padding: var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-border) 75%, transparent);
}

.emp-profile-dialog__field-card--full {
  margin-top: var(--space-1);
}

.emp-profile-dialog__field-label {
  display: block;
  margin-bottom: var(--space-2);
  font-size: var(--text-xs);
  font-weight: 700;
  color: var(--color-text);
}

.emp-profile-dialog__current {
  margin: 0 0 var(--space-2);
  font-size: var(--text-sm);
  font-style: italic;
  color: var(--color-text-muted);
  word-break: break-word;
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
  padding-bottom: var(--space-4);
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
  background: linear-gradient(
    135deg,
    var(--color-tertiary-600),
    color-mix(in srgb, var(--color-tertiary-700) 85%, var(--color-primary-600))
  );
  color: var(--color-on-primary);
  box-shadow: var(--shadow-sm);
}

.emp-profile-dialog__btn--primary:disabled {
  opacity: 0.65;
}
</style>
