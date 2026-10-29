<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  tick: { type: Number, default: 0 },
  emoji: { type: String, default: '' },
});

const particles = ref([]);

watch(
  () => props.tick,
  (tick) => {
    if (!tick || !props.emoji) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    particles.value = Array.from({ length: 12 }, (_, index) => ({
      id: `${tick}-${index}`,
      emoji: props.emoji,
      x: 12 + Math.random() * 76,
      drift: (Math.random() - 0.5) * 90,
      delay: Math.round(Math.random() * 90),
      size: 0.9 + Math.random() * 0.7,
    }));
  },
);
</script>

<template>
  <div class="chat-burst" aria-hidden="true">
    <span
      v-for="particle in particles"
      :key="particle.id"
      class="chat-burst__bit"
      :style="{
        left: `${particle.x}%`,
        animationDelay: `${particle.delay}ms`,
        '--chat-burst-drift': `${particle.drift}px`,
        fontSize: `${particle.size}rem`,
      }"
    >{{ particle.emoji }}</span>
  </div>
</template>

<style scoped>
.chat-burst {
  position: fixed;
  inset: 0;
  z-index: 80;
  pointer-events: none;
  overflow: hidden;
}

.chat-burst__bit {
  position: absolute;
  bottom: 18%;
  animation: chat-burst-float 900ms ease-out forwards;
}

@keyframes chat-burst-float {
  from {
    opacity: 0;
    transform: translate3d(0, 12px, 0) scale(0.6);
  }
  20% {
    opacity: 1;
  }
  to {
    opacity: 0;
    transform: translate3d(var(--chat-burst-drift), -42vh, 0) scale(1.15);
  }
}
</style>
