<script setup>
//
// Onboarding lần đầu — giao diện khớp mẫu va-workspace-html (welcome screen).
//
import AppIcon from '@/components/AppIcon.vue';
import {
  EMPLOYEE_WELCOME_COPY,
  EMPLOYEE_WELCOME_LOGO_SRC,
  EMPLOYEE_WELCOME_MASCOT_SRC,
} from '../../constants/employeeWelcomeScreen.js';

const emit = defineEmits(['start']);

function onStart() {
  emit('start');
}
</script>

<template>
  <main
    class="welcome welcome--overlay"
    role="dialog"
    aria-modal="true"
    aria-labelledby="welcome-title"
  >
    <div class="welcome__scene" aria-hidden="true">
      <img
        class="welcome__background"
        :src="EMPLOYEE_WELCOME_MASCOT_SRC"
        alt=""
        fetchpriority="high"
        decoding="async"
      />
    </div>
    <div class="welcome__wash" aria-hidden="true" />

    <header class="welcome__header">
      <img
        class="welcome__logo"
        :src="EMPLOYEE_WELCOME_LOGO_SRC"
        alt="Vietnam America Schools"
        width="100"
        height="116"
        decoding="async"
      />
      <div class="welcome__heading">
        <p class="welcome__eyebrow">{{ EMPLOYEE_WELCOME_COPY.eyebrow }}</p>
        <h1 id="welcome-title" class="welcome__title">{{ EMPLOYEE_WELCOME_COPY.title }}</h1>
        <div class="welcome__rule" aria-hidden="true" />
        <p class="welcome__intro">{{ EMPLOYEE_WELCOME_COPY.intro }}</p>
      </div>
    </header>

    <footer class="welcome__footer">
      <button type="button" class="welcome__cta" @click="onStart">
        {{ EMPLOYEE_WELCOME_COPY.cta }}
        <AppIcon name="chevronRight" :size="18" />
      </button>
      <span class="welcome__school">{{ EMPLOYEE_WELCOME_COPY.school }}</span>
    </footer>
  </main>
</template>
