<script setup>
import { computed } from 'vue';
import { historyHubVisual } from '../constants/employeePwaBrandVisuals.js';

const props = defineProps({
  visualIndex: { type: Number, default: 0 },
  /** decorative | mark */
  variant: { type: String, default: 'decorative' },
});

const visual = computed(() => historyHubVisual(props.visualIndex));
</script>

<template>
  <img
    class="emp-pwa-logo-clip"
    :class="`emp-pwa-logo-clip--${variant}`"
    :src="visual.src"
    alt=""
    width="96"
    height="96"
    decoding="async"
    loading="lazy"
    :style="{ clipPath: visual.clipPath }"
  />
</template>

<style scoped>
.emp-pwa-logo-clip {
  display: block;
  object-fit: cover;
  pointer-events: none;
  user-select: none;
}

.emp-pwa-logo-clip--decorative {
  position: absolute;
  right: -0.35rem;
  bottom: -0.35rem;
  width: 4.25rem;
  height: 4.25rem;
  opacity: 0.14;
}

.emp-pwa-logo-clip--mark {
  width: 1.65rem;
  height: 1.65rem;
  opacity: 1;
}
</style>
