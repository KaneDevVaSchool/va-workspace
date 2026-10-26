<script setup>
//
// Bottom tab bar (5 mục cố định) của shell mobile nhân viên. CSS dùng chung:
// resources/css/employee-mobile-shell/base.css (khung). Không cộng safe-area iOS.
//
import { ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

defineProps({
  tabs: { type: Array, required: true },
  activeName: { type: String, default: '' },
  isMobilePwa: { type: Boolean, default: false },
});

const navEl = ref(null);
defineExpose({ navEl });
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
        :class="{ 'employee-tabbar__item--active': activeName === tab.name }"
      >
        <span class="employee-tabbar__icon">
          <AppIcon
            :name="tab.icon"
            :size="20"
            :stroke-width="1.9"
          />
        </span>
        <span class="employee-tabbar__label">{{ tab.label }}</span>
      </router-link>
    </div>
  </nav>
</template>
