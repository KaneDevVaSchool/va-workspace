<script setup>
// Copy nguyên từ Modules/Social/resources/js/components/SocialReactionBursts.vue.
defineProps({
  bursts: { type: Array, default: () => [] },
});
</script>

<template>
  <Teleport to="body">
    <span
      v-for="burst in bursts"
      :key="burst.id"
      class="comment-reaction-burst"
      :style="{
        left: `${burst.x}px`,
        top: `${burst.y}px`,
        animationDelay: `${burst.delay}px`,
        '--burst-drift': `${burst.drift}px`,
      }"
    >{{ burst.emoji }}</span>
  </Teleport>
</template>

<style scoped>
.comment-reaction-burst {
  position: fixed;
  z-index: 90;
  font-size: 1.75rem;
  line-height: 1;
  pointer-events: none;
  animation: comment-reaction-burst-fly 0.72s ease-out forwards;
}

@keyframes comment-reaction-burst-fly {
  0% {
    opacity: 1;
    transform: translate(-50%, -50%) scale(0.4);
  }

  35% {
    opacity: 1;
    transform: translate(calc(-50% + var(--burst-drift)), -72px) scale(1.28);
  }

  100% {
    opacity: 0;
    transform: translate(calc(-50% + var(--burst-drift) * 1.4), -132px) scale(0.75);
  }
}

@media (prefers-reduced-motion: reduce) {
  .comment-reaction-burst {
    display: none;
  }
}
</style>
