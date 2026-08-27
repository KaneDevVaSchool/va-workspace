<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useUserAvatarTip } from '@/composables/useUserAvatarTip';

const props = defineProps({
  user: { type: Object, default: null },
  label: { type: String, default: '' },
});

const { isOpen, toggle: toggleOpen, close } = useUserAvatarTip();
const rootRef = ref(null);
const btnRef = ref(null);
const cardRef = ref(null);
const photoBroken = ref(false);
const cardStyle = ref({});

const displayName = computed(() => props.user?.name || '—');
const initial = computed(() => displayName.value.trim().charAt(0).toUpperCase() || '?');
const hasPhoto = computed(() => Boolean(props.user?.avatar_url) && !photoBroken.value);
const departmentName = computed(() => props.user?.department?.name || '');
const triggerLabel = computed(
  () => props.label || `Thông tin ${displayName.value}`,
);
const isActive = computed(() => (props.user?.status ?? 'active') === 'active');
const statusLabel = computed(() => (isActive.value ? 'Đang làm việc' : 'Ngừng làm việc'));

function onPhotoError() {
  photoBroken.value = true;
}

function placeCard() {
  const btn = btnRef.value;
  const card = cardRef.value;
  if (!btn || !card) return;

  const rect = btn.getBoundingClientRect();
  const cardRect = card.getBoundingClientRect();
  const gap = 8;
  const vw = window.innerWidth;
  const vh = window.innerHeight;

  let left = rect.left;
  if (left + cardRect.width > vw - 8) {
    left = Math.max(8, rect.right - cardRect.width);
  }
  if (left < 8) left = 8;

  let top = rect.bottom + gap;
  if (top + cardRect.height > vh - 8 && rect.top - gap - cardRect.height > 8) {
    top = rect.top - gap - cardRect.height;
  }

  cardStyle.value = {
    top: `${Math.round(top)}px`,
    left: `${Math.round(left)}px`,
  };
}

async function toggle() {
  if (!props.user) return;
  toggleOpen();
  if (!isOpen.value) return;
  const btn = btnRef.value;
  if (btn) {
    const rect = btn.getBoundingClientRect();
    cardStyle.value = {
      top: `${Math.round(rect.bottom + 8)}px`,
      left: `${Math.round(rect.left)}px`,
    };
  }
  await nextTick();
  placeCard();
}

function onDocumentClick(event) {
  if (!isOpen.value) return;
  const target = event.target;
  if (rootRef.value?.contains(target) || cardRef.value?.contains(target)) {
    return;
  }
  close();
}

function onDocumentKeydown(event) {
  if (event.key === 'Escape' && isOpen.value) {
    close();
  }
}

function onReposition() {
  if (isOpen.value) placeCard();
}

function bind() {
  document.addEventListener('click', onDocumentClick);
  document.addEventListener('keydown', onDocumentKeydown);
  window.addEventListener('resize', onReposition);
  window.addEventListener('scroll', onReposition, true);
}

function unbind() {
  document.removeEventListener('click', onDocumentClick);
  document.removeEventListener('keydown', onDocumentKeydown);
  window.removeEventListener('resize', onReposition);
  window.removeEventListener('scroll', onReposition, true);
}

watch(
  () => props.user?.avatar_url,
  () => {
    photoBroken.value = false;
  },
);

watch(isOpen, (open) => {
  unbind();
  if (open) bind();
}, { flush: 'post' });

onBeforeUnmount(() => {
  unbind();
  close();
});
</script>

<template>
  <span v-if="!user" class="user-avatar-tip user-avatar-tip--empty">—</span>
  <span v-else ref="rootRef" class="user-avatar-tip">
    <button
      ref="btnRef"
      type="button"
      class="user-avatar-tip__btn"
      :class="{ 'user-avatar-tip__btn--open': isOpen }"
      :aria-expanded="isOpen"
      :aria-label="triggerLabel"
      @click.stop="toggle"
    >
      <span class="user-avatar-tip__avatar" aria-hidden="true">
        <img
          v-if="hasPhoto"
          :src="user.avatar_url"
          alt=""
          class="user-avatar-tip__img"
          referrerpolicy="no-referrer"
          @error="onPhotoError"
        />
        <template v-else>{{ initial }}</template>
      </span>
    </button>

    <Teleport to="body">
      <div
        v-if="isOpen"
        ref="cardRef"
        class="user-avatar-tip__card"
        role="dialog"
        :aria-label="triggerLabel"
        :style="cardStyle"
        @click.stop
      >
        <span class="user-avatar-tip__card-avatar" aria-hidden="true">
          <img
            v-if="hasPhoto"
            :src="user.avatar_url"
            alt=""
            class="user-avatar-tip__img"
            referrerpolicy="no-referrer"
            @error="onPhotoError"
          />
          <template v-else>{{ initial }}</template>
        </span>
        <span class="user-avatar-tip__name">{{ displayName }}</span>
        <span class="user-avatar-tip__status">
          <span class="user-avatar-tip__status-dot" :class="{ 'user-avatar-tip__status-dot--off': !isActive }" />
          {{ statusLabel }}
        </span>
        <span class="user-avatar-tip__rows">
          <span v-if="departmentName" class="user-avatar-tip__row">
            <span class="user-avatar-tip__row-label">Phòng ban</span>
            <span class="user-avatar-tip__row-value">{{ departmentName }}</span>
          </span>
          <span v-if="user.email" class="user-avatar-tip__row">
            <span class="user-avatar-tip__row-label">Email</span>
            <span class="user-avatar-tip__row-value">{{ user.email }}</span>
          </span>
        </span>
      </div>
    </Teleport>
  </span>
</template>

<style scoped>
.user-avatar-tip {
  position: relative;
  display: inline-flex;
  vertical-align: middle;
}

.user-avatar-tip--empty {
  color: var(--color-text-muted);
}

.user-avatar-tip__btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  cursor: pointer;
}

.user-avatar-tip__btn:hover,
.user-avatar-tip__btn--open,
.user-avatar-tip__btn:focus-visible {
  outline: 2px solid color-mix(in srgb, var(--color-primary) 35%, transparent);
  outline-offset: 2px;
}

.user-avatar-tip__avatar {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  overflow: hidden;
  border-radius: var(--radius-full);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-size: 0.75rem;
  font-weight: 700;
  line-height: 1;
}

.user-avatar-tip__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.user-avatar-tip__card {
  position: fixed;
  z-index: 80;
  display: flex;
  min-width: 15rem;
  max-width: 18rem;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
  padding: var(--space-4) var(--space-4) var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
  text-align: center;
}

.user-avatar-tip__card-avatar {
  display: grid;
  place-items: center;
  width: 3.5rem;
  height: 3.5rem;
  margin-bottom: 0.25rem;
  overflow: hidden;
  border-radius: var(--radius-full);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-size: 1.25rem;
  font-weight: 700;
  line-height: 1;
}

.user-avatar-tip__name {
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 700;
  overflow-wrap: anywhere;
}

.user-avatar-tip__status {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 500;
}

.user-avatar-tip__status-dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-success);
}

.user-avatar-tip__status-dot--off {
  background: var(--color-text-muted);
}

.user-avatar-tip__rows {
  width: 100%;
  margin-top: 0.5rem;
  display: flex;
  flex-direction: column;
}

.user-avatar-tip__row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-3);
  padding: 0.375rem 0;
  box-shadow: 0 1px 0 var(--color-border);
  text-align: left;
}

.user-avatar-tip__row:last-child {
  box-shadow: none;
}

.user-avatar-tip__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.user-avatar-tip__row-value {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 500;
  overflow-wrap: anywhere;
  text-align: right;
}
</style>
