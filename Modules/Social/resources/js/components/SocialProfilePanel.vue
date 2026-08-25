<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';

const props = defineProps({
  scope: { type: String, default: 'all' },
  postScope: { type: String, default: 'company' },
  wallProfile: { type: Object, default: null },
});

const emit = defineEmits(['update:scope', 'update:postScope', 'open-wall']);

const auth = useAuthStore();
const router = useRouter();
const stats = ref({ posts_count: 0, reactions_received: 0, comments_count: 0 });
const statsLoaded = ref(false);

const viewingOther = computed(() => Boolean(props.wallProfile && !props.wallProfile.is_own));
const profileUser = computed(() => props.wallProfile?.user ?? auth.user);
const displayName = computed(() => profileUser.value?.name ?? 'Người dùng');
const firstName = computed(() => {
  const parts = displayName.value.trim().split(/\s+/).filter(Boolean);
  return parts[parts.length - 1] || displayName.value;
});
const initial = computed(() => displayName.value.trim().charAt(0).toUpperCase() || '?');
const departmentName = computed(() => (
  viewingOther.value
    ? (profileUser.value?.department ?? '')
    : (auth.user?.department?.name ?? '')
));
const hasDepartment = computed(() => Boolean(auth.user?.department?.id));
const wallItems = computed(() => [
  {
    id: 'company',
    icon: 'megaphone',
    label: 'Bảng tin chung',
    hint: 'Toàn công ty cùng xem',
  },
  ...(hasDepartment.value
    ? [{
        id: 'department',
        icon: 'building',
        label: auth.user?.department?.name ? `Tường ${auth.user.department.name}` : 'Tường phòng ban',
        hint: 'Chỉ thành viên phòng ban',
      }]
    : []),
  {
    id: 'personal',
    icon: 'user',
    label: 'Tường của tôi',
    hint: 'Bài viết trên tường cá nhân',
  },
]);
const userEmail = computed(() => (
  viewingOther.value ? '' : (auth.user?.email ?? '')
));
const greeting = computed(() => {
  const hour = new Date().getHours();
  if (hour < 12) return 'Chào buổi sáng';
  if (hour < 18) return 'Chào buổi chiều';
  return 'Chào buổi tối';
});

const navItems = computed(() => [
  {
    scope: 'all',
    icon: 'megaphone',
    label: 'Bảng tin',
    hint: props.postScope === 'department'
      ? 'Tin mới trong phòng ban'
      : (props.postScope === 'personal' ? 'Tin trên tường này' : 'Tin mới toàn công ty'),
  },
  {
    scope: 'mine',
    icon: 'fileText',
    label: 'Bài của tôi',
    hint: 'Nội dung bạn đã đăng',
  },
  {
    scope: 'reacted',
    icon: 'heart',
    label: 'Đã tương tác',
    hint: 'Bài bạn đã thả cảm xúc',
  },
]);

function formatCount(value) {
  return Number(value || 0).toLocaleString('vi-VN');
}

function setScope(scope) {
  emit('update:scope', scope);
}

function setPostScope(scope) {
  emit('update:postScope', scope);
}

function openOwnWall() {
  if (auth.user?.id) emit('open-wall', auth.user.id);
}

function openGroups() {
  router.push({ name: 'social.groups.index' });
}

function isWallActive(itemId) {
  if (itemId === 'personal') {
    return props.postScope === 'personal' && !viewingOther.value;
  }
  return props.postScope === itemId;
}

async function copyEmail() {
  if (!userEmail.value) return;
  try {
    await navigator.clipboard.writeText(userEmail.value);
    showClientToast('success', 'Đã sao chép email.');
  } catch {
    showClientToast('error', 'Không thể sao chép email.');
  }
}

async function loadStats() {
  if (viewingOther.value) {
    stats.value = {
      posts_count: props.wallProfile?.stats?.posts_count ?? 0,
      reactions_received: props.wallProfile?.stats?.reactions_received ?? 0,
      comments_count: props.wallProfile?.stats?.comments_count ?? 0,
    };
    statsLoaded.value = true;
    return;
  }
  try {
    const { data } = await window.axios.get('/api/social/me/stats');
    stats.value = {
      posts_count: data.posts_count ?? 0,
      reactions_received: data.reactions_received ?? 0,
      comments_count: data.comments_count ?? 0,
    };
  } catch {
    stats.value = { posts_count: 0, reactions_received: 0, comments_count: 0 };
  } finally {
    statsLoaded.value = true;
  }
}

defineExpose({ loadStats });
onMounted(loadStats);
watch(() => props.scope, loadStats);
watch(() => props.wallProfile, loadStats);
</script>

<template>
  <div class="profile-panel">
    <section class="profile-card" :aria-label="`Hồ sơ ${displayName}`">
      <div class="profile-card__banner" aria-hidden="true">
        <span class="profile-card__banner-orb profile-card__banner-orb--a" />
        <span class="profile-card__banner-orb profile-card__banner-orb--b" />
        <span class="profile-card__banner-grid" />
      </div>

      <div class="profile-card__body">
        <div class="profile-card__avatar-wrap">
          <img
            v-if="profileUser?.avatar_url"
            class="profile-card__avatar"
            :src="profileUser.avatar_url"
            :alt="`Ảnh đại diện của ${displayName}`"
          />
          <div v-else class="profile-card__avatar profile-card__avatar--placeholder">
            {{ initial }}
          </div>
        </div>

        <p class="profile-card__hello">
          {{ viewingOther ? `Tường của ${firstName}` : `${greeting}, ${firstName}` }}
        </p>
        <button
          v-if="!viewingOther"
          type="button"
          class="profile-card__name profile-card__name-btn"
          @click="openOwnWall"
        >
          {{ displayName }}
        </button>
        <h2 v-else class="profile-card__name">{{ displayName }}</h2>

        <button
          v-if="departmentName && !viewingOther"
          type="button"
          class="profile-card__meta profile-card__meta--link"
          :aria-current="props.postScope === 'department' ? 'page' : undefined"
          @click="setPostScope('department')"
        >
          <AppIcon name="building" :size="14" />
          <span>Tường {{ departmentName }}</span>
        </button>
        <p v-else-if="departmentName" class="profile-card__meta">
          <AppIcon name="building" :size="14" />
          <span>{{ departmentName }}</span>
        </p>

        <button
          v-if="userEmail"
          type="button"
          class="profile-card__email"
          :aria-label="`Sao chép email ${userEmail}`"
          @click="copyEmail"
        >
          <AppIcon name="mail" :size="14" />
          <span>{{ userEmail }}</span>
        </button>

        <div class="profile-card__stats" aria-label="Thống kê bảng tin">
          <button
            type="button"
            class="profile-card__stat"
            :class="{ 'profile-card__stat--active': props.scope === 'mine' }"
            @click="setScope('mine')"
          >
            <span class="profile-card__stat-value">
              {{ statsLoaded ? formatCount(stats.posts_count) : '—' }}
            </span>
            <span class="profile-card__stat-label">Bài viết</span>
          </button>
          <button
            type="button"
            class="profile-card__stat"
            :class="{ 'profile-card__stat--active': props.scope === 'reacted' }"
            @click="setScope('reacted')"
          >
            <span class="profile-card__stat-value">
              {{ statsLoaded ? formatCount(stats.reactions_received) : '—' }}
            </span>
            <span class="profile-card__stat-label">Tương tác</span>
          </button>
          <div class="profile-card__stat profile-card__stat--static">
            <span class="profile-card__stat-value">
              {{ statsLoaded ? formatCount(stats.comments_count) : '—' }}
            </span>
            <span class="profile-card__stat-label">Bình luận</span>
          </div>
        </div>
      </div>
    </section>

    <nav v-if="wallItems.length > 1" class="profile-nav" aria-label="Chọn tường">
      <button
        v-for="item in wallItems"
        :key="item.id"
        type="button"
        class="profile-nav__btn"
        :class="{ 'profile-nav__btn--active': isWallActive(item.id) }"
        :aria-current="isWallActive(item.id) ? 'page' : undefined"
        @click="setPostScope(item.id)"
      >
        <span class="profile-nav__icon" aria-hidden="true">
          <AppIcon :name="item.icon" :size="16" />
        </span>
        <span class="profile-nav__copy">
          <span class="profile-nav__label">{{ item.label }}</span>
          <span class="profile-nav__hint">{{ item.hint }}</span>
        </span>
      </button>
    </nav>

    <nav class="profile-nav" aria-label="Nhóm">
      <button type="button" class="profile-nav__btn" @click="openGroups">
        <span class="profile-nav__icon" aria-hidden="true">
          <AppIcon name="users" :size="16" />
        </span>
        <span class="profile-nav__copy">
          <span class="profile-nav__label">Nhóm của tôi</span>
          <span class="profile-nav__hint">Nhóm bảo mật & công khai</span>
        </span>
      </button>
    </nav>

    <nav class="profile-nav" aria-label="Lối tắt bảng tin">
      <button
        v-for="item in navItems"
        :key="item.scope"
        type="button"
        class="profile-nav__btn"
        :class="{ 'profile-nav__btn--active': props.scope === item.scope }"
        :aria-current="props.scope === item.scope ? 'page' : undefined"
        @click="setScope(item.scope)"
      >
        <span class="profile-nav__icon" aria-hidden="true">
          <AppIcon :name="item.icon" :size="16" />
        </span>
        <span class="profile-nav__copy">
          <span class="profile-nav__label">{{ item.label }}</span>
          <span class="profile-nav__hint">{{ item.hint }}</span>
        </span>
      </button>
    </nav>
  </div>
</template>

<style scoped>
.profile-panel {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.profile-card {
  position: relative;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  overflow: hidden;
  box-shadow: var(--shadow-sm);
}

.profile-card__banner {
  position: relative;
  height: 4.75rem;
  overflow: hidden;
  background: linear-gradient(
    135deg,
    var(--color-primary-800) 0%,
    var(--color-primary) 52%,
    var(--color-primary-400) 100%
  );
}

.profile-card__banner-grid {
  position: absolute;
  inset: 0;
  background: repeating-linear-gradient(
    -24deg,
    transparent,
    transparent 11px,
    color-mix(in srgb, var(--color-on-primary) 10%, transparent) 11px,
    color-mix(in srgb, var(--color-on-primary) 10%, transparent) 12px
  );
  opacity: 0.7;
}

.profile-card__banner-orb {
  position: absolute;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-on-primary) 22%, transparent);
}

.profile-card__banner-orb--a {
  width: 5.5rem;
  height: 5.5rem;
  top: -2.2rem;
  right: -1.1rem;
}

.profile-card__banner-orb--b {
  width: 3.25rem;
  height: 3.25rem;
  bottom: -1.4rem;
  left: 1.25rem;
  background: color-mix(in srgb, var(--color-on-primary) 14%, transparent);
}

.profile-card__body {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0 var(--space-3) var(--space-3);
  text-align: center;
}

.profile-card__avatar-wrap {
  position: relative;
  z-index: 1;
  margin-top: -2.5rem;
}

.profile-card__avatar {
  width: 5rem;
  height: 5rem;
  border-radius: var(--radius-full);
  object-fit: cover;
  box-shadow:
    0 0 0 3px var(--color-surface),
    var(--shadow-md);
}

.profile-card__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 1.625rem;
  font-weight: 700;
}

.profile-card__hello {
  margin: var(--space-3) 0 0;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.profile-card__name {
  margin: 2px 0 0;
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.3;
  color: var(--color-text);
  word-break: break-word;
}

.profile-card__name-btn {
  border: none;
  background: none;
  padding: 0;
  font-family: inherit;
  cursor: pointer;
}

.profile-card__name-btn:hover {
  color: var(--color-primary);
}

.profile-card__meta,
.profile-card__email {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-1);
  max-width: 100%;
  margin-top: var(--space-2);
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.profile-card__meta--link {
  border: none;
  background: none;
  padding: 0;
  font-family: inherit;
  cursor: pointer;
  transition: color 0.2s ease;
}

.profile-card__meta--link:hover,
.profile-card__meta--link[aria-current='page'] {
  color: var(--color-primary);
}

.profile-card__meta span,
.profile-card__email span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.profile-card__email {
  border: none;
  background: none;
  padding: 0;
  font-family: inherit;
  cursor: pointer;
  transition: color 0.2s ease;
}

.profile-card__email:hover {
  color: var(--color-primary);
}

.profile-card__stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  width: 100%;
  margin-top: var(--space-3);
  padding-top: var(--space-3);
  box-shadow: 0 -1px 0 var(--color-border);
}

.profile-card__stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  min-width: 0;
  border: none;
  background: none;
  padding: var(--space-1);
  border-radius: var(--radius-md);
  font-family: inherit;
  cursor: pointer;
  transition: background 0.2s ease;
}

.profile-card__stat:hover {
  background: var(--color-surface-muted);
}

.profile-card__stat--active {
  background: var(--color-primary-surface);
}

.profile-card__stat--static {
  cursor: default;
}

.profile-card__stat--static:hover {
  background: none;
}

.profile-card__stat-value {
  font-size: 1rem;
  font-weight: 800;
  line-height: 1.2;
  color: var(--color-primary);
}

.profile-card__stat-label {
  font-size: 0.625rem;
  font-weight: 600;
  letter-spacing: 0.01em;
  color: var(--color-text-muted);
}

.profile-nav {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: var(--space-2);
  display: flex;
  flex-direction: column;
  gap: 2px;
  box-shadow: var(--shadow-sm);
}

.profile-nav__btn {
  position: relative;
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  border: none;
  background: none;
  color: var(--color-text);
  font-family: inherit;
  text-align: left;
  padding: var(--space-2);
  padding-left: calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: background 0.2s ease;
}

.profile-nav__btn::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: transparent;
}

.profile-nav__btn:hover {
  background: var(--color-surface-muted);
}

.profile-nav__btn--active {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.profile-nav__btn--active::before {
  background: var(--color-primary);
}

.profile-nav__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  flex-shrink: 0;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.profile-nav__btn--active .profile-nav__icon {
  background: color-mix(in srgb, var(--color-primary) 14%, var(--color-surface));
  color: var(--color-primary);
}

.profile-nav__copy {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 1px;
}

.profile-nav__label {
  font-size: 0.8125rem;
  font-weight: 700;
}

.profile-nav__hint {
  font-size: 0.6875rem;
  font-weight: 500;
  color: var(--color-text-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.profile-nav__btn--active .profile-nav__hint {
  color: color-mix(in srgb, var(--color-primary) 72%, var(--color-text-muted));
}

@media (prefers-reduced-motion: reduce) {
  .profile-nav__btn,
  .profile-card__email,
  .profile-card__meta--link,
  .profile-card__stat {
    transition: none;
  }
}
</style>
