<script setup>
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';

const props = defineProps({
  scope: { type: String, default: 'all' },
});

const emit = defineEmits(['update:scope', 'compose']);

const auth = useAuthStore();

const displayName = computed(() => auth.user?.name ?? 'Người dùng');
const initial = computed(() => displayName.value.trim().charAt(0).toUpperCase() || '?');
const departmentName = computed(() => auth.user?.department?.name ?? '');
</script>

<template>
  <div class="profile-panel">
    <section class="profile-card">
      <div class="profile-card__banner" />
      <div class="profile-card__body">
        <img
          v-if="auth.user?.avatar_url"
          class="profile-card__avatar"
          :src="auth.user.avatar_url"
          :alt="`Ảnh đại diện của ${displayName}`"
        />
        <div v-else class="profile-card__avatar profile-card__avatar--placeholder">
          {{ initial }}
        </div>
        <div class="profile-card__name">{{ displayName }}</div>
        <div v-if="departmentName" class="profile-card__dept">
          <AppIcon name="building" :size="14" />
          {{ departmentName }}
        </div>
      </div>
    </section>

    <nav class="profile-nav" aria-label="Lối tắt bảng tin">
      <button
        type="button"
        class="profile-nav__btn"
        :class="{ 'profile-nav__btn--active': props.scope === 'all' }"
        @click="emit('update:scope', 'all')"
      >
        <AppIcon name="megaphone" :size="18" />
        Bảng tin
      </button>
      <button
        type="button"
        class="profile-nav__btn"
        :class="{ 'profile-nav__btn--active': props.scope === 'mine' }"
        @click="emit('update:scope', 'mine')"
      >
        <AppIcon name="fileText" :size="18" />
        Bài của tôi
      </button>
      <button type="button" class="profile-nav__btn" @click="emit('compose')">
        <AppIcon name="plus" :size="18" />
        Đăng bài viết
      </button>
    </nav>
  </div>
</template>

<style scoped>
.profile-panel {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.profile-card {
  position: relative;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  overflow: hidden;
  box-shadow: var(--shadow-sm);
}

.profile-card::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-primary);
  z-index: 1;
}

.profile-card__banner {
  height: 3.5rem;
  background: linear-gradient(
    135deg,
    var(--color-primary-100) 0%,
    var(--color-primary-50) 100%
  );
}

.profile-card__body {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0 var(--space-4) var(--space-4);
  text-align: center;
}

.profile-card__avatar {
  width: 4.5rem;
  height: 4.5rem;
  margin-top: -2.25rem;
  border-radius: var(--radius-full);
  object-fit: cover;
  box-shadow: 0 0 0 3px var(--color-surface);
  position: relative;
  z-index: 1;
}

.profile-card__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 1.5rem;
  font-weight: 700;
}

.profile-card__name {
  margin-top: var(--space-3);
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-text);
  word-break: break-word;
}

.profile-card__dept {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-1);
  margin-top: var(--space-1);
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-primary);
}

.profile-nav {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--space-2);
  display: flex;
  flex-direction: column;
  gap: 2px;
  box-shadow: var(--shadow-sm);
}

.profile-nav__btn {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  width: 100%;
  border: none;
  background: none;
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.875rem;
  font-weight: 600;
  text-align: left;
  padding: var(--space-3);
  border-radius: var(--radius-md);
  cursor: pointer;
}

.profile-nav__btn:hover {
  background: var(--color-surface-muted);
}

.profile-nav__btn--active {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.profile-nav__btn :deep(.app-icon) {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.profile-nav__btn--active :deep(.app-icon) {
  color: var(--color-primary);
}
</style>
