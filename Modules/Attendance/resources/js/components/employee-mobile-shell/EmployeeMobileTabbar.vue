<script setup>
//
// Bottom tab bar (5 mục cố định) của shell mobile nhân viên. CSS dùng chung:
// resources/css/employee-mobile-shell/base.css (khung). Không cộng safe-area iOS.
//
import { ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  tabs: { type: Array, required: true },
  activeName: { type: String, default: '' },
  isMobilePwa: { type: Boolean, default: false },
});

const emit = defineEmits(['reselect']);

const navEl = ref(null);
defineExpose({ navEl });

function onTabClick(event, tab) {
  if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
    navigator.vibrate(10);
  }
  if (props.activeName !== tab.name) return;
  event.preventDefault();
  emit('reselect', tab.name);
}
</script>

<template>
  <nav
    ref="navEl"
    class="employee-tabbar employee-tabbar--shell"
    :class="isMobilePwa ? 'employee-tabbar--pwa' : 'employee-tabbar--browser'"
    aria-label="Điều hướng chính"
  >
    <div class="employee-tabbar__bar">
      <router-link
        v-for="tab in tabs"
        :key="tab.name"
        :to="{ name: tab.name }"
        class="employee-tabbar__item"
        :class="{
          'employee-tabbar__item--active': activeName === tab.name,
          'employee-tabbar__item--primary': tab.primary,
        }"
        :aria-current="activeName === tab.name ? 'page' : undefined"
        @click="onTabClick($event, tab)"
      >
        <span class="employee-tabbar__icon">
          <AppIcon
            :name="tab.icon"
            :size="tab.primary ? 22 : 20"
            :stroke-width="1.9"
          />
        </span>
        <span v-if="tab.badge" class="employee-tabbar__badge">{{ tab.badge }}</span>
        <span class="employee-tabbar__label">{{ tab.label }}</span>
      </router-link>
    </div>
  </nav>
</template>
