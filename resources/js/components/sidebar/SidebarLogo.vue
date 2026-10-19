<script setup>
import AppIcon from '../AppIcon.vue';

defineProps({
  collapsed: { type: Boolean, default: false },
  showClose: { type: Boolean, default: false },
});

defineEmits(['close']);

const SYSTEM_NAME = 'Không gian làm việc';
</script>

<template>
  <div
    class="sidebar-logo"
    :class="{ 'sidebar-logo--expanded': !collapsed }"
    data-tour="sidebar-logo"
  >
    <router-link
      :to="{ name: 'home' }"
      class="sidebar-logo__brand"
      :class="{ 'sidebar-logo__brand--lockup': !collapsed }"
      :aria-label="collapsed ? undefined : `VA Schools — ${SYSTEM_NAME}`"
      @click="$emit('close')"
    >
      <img
        v-if="collapsed"
        src="/images/congnghe/brand/vas-white-mark.png"
        srcset="/images/congnghe/brand/vas-white-mark.png 1x, /images/congnghe/brand/vas-white-mark@2x.png 2x"
        alt="Trường Việt Mỹ"
        class="sidebar-logo__mark"
        draggable="false"
      />
      <span v-else class="sidebar-logo__lockup">
        <span class="sidebar-logo__primary">
          <img
            src="/images/congnghe/brand/vas-white-mark.png"
            srcset="/images/congnghe/brand/vas-white-mark.png 1x, /images/congnghe/brand/vas-white-mark@2x.png 2x"
            alt=""
            class="sidebar-logo__mark sidebar-logo__mark--lockup"
            draggable="false"
            aria-hidden="true"
          />
          <span class="sidebar-logo__rule" aria-hidden="true" />
          <span class="sidebar-logo__wordmark">VA Schools</span>
        </span>
        <span class="sidebar-logo__foot">
          <span class="sidebar-logo__system-label">{{ SYSTEM_NAME }}</span>
          <span class="sidebar-logo__sparkles" aria-hidden="true">
            <AppIcon name="sparkles" :size="15" />
            <AppIcon name="sparkles" :size="11" />
            <AppIcon name="sparkles" :size="8" />
          </span>
        </span>
      </span>
    </router-link>

    <button
      v-if="showClose"
      type="button"
      class="sidebar-logo__close"
      aria-label="Đóng menu điều hướng"
      @click="$emit('close')"
    >
      <AppIcon name="close" :size="18" />
    </button>
  </div>
</template>
