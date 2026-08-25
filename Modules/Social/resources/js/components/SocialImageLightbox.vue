<script setup>
import { computed, onBeforeUnmount, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  open: { type: Boolean, required: true },
  images: { type: Array, required: true },
  index: { type: Number, default: 0 },
});

const emit = defineEmits(['close', 'update:index']);

const current = computed(() => props.images[props.index] ?? null);
const hasMany = computed(() => props.images.length > 1);
const counter = computed(() => `${props.index + 1} / ${props.images.length}`);

function close() {
  emit('close');
}

function go(delta) {
  const len = props.images.length;
  if (len < 2) return;
  const next = (props.index + delta + len) % len;
  emit('update:index', next);
}

function onKey(event) {
  if (event.key === 'Escape') {
    event.preventDefault();
    close();
    return;
  }
  if (event.key === 'ArrowLeft') {
    event.preventDefault();
    go(-1);
    return;
  }
  if (event.key === 'ArrowRight') {
    event.preventDefault();
    go(1);
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      document.addEventListener('keydown', onKey);
      document.body.style.overflow = 'hidden';
    } else {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = '';
    }
  },
);

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKey);
  document.body.style.overflow = '';
});
</script>

<template>
  <Teleport to="body">
    <Transition name="image-lightbox-fade">
      <div
        v-if="open && current"
        class="image-lightbox"
        role="dialog"
        aria-modal="true"
        aria-label="Xem ảnh"
        @mousedown.self="close"
      >
        <button
          type="button"
          class="image-lightbox__close"
          aria-label="Đóng"
          @click="close"
        >
          <AppIcon name="close" :size="22" />
        </button>

        <button
          v-if="hasMany"
          type="button"
          class="image-lightbox__nav image-lightbox__nav--prev"
          aria-label="Ảnh trước"
          @click="go(-1)"
        >
          <AppIcon name="chevronLeft" :size="28" />
        </button>

        <figure class="image-lightbox__stage">
          <img
            :key="current.url"
            :src="current.url"
            :alt="current.name || 'Ảnh đính kèm'"
            class="image-lightbox__image"
            @mousedown.stop
          />
        </figure>

        <button
          v-if="hasMany"
          type="button"
          class="image-lightbox__nav image-lightbox__nav--next"
          aria-label="Ảnh tiếp"
          @click="go(1)"
        >
          <AppIcon name="chevronRight" :size="28" />
        </button>

        <div v-if="hasMany" class="image-lightbox__counter">{{ counter }}</div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.image-lightbox {
  position: fixed;
  inset: 0;
  z-index: 320;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: color-mix(in srgb, #000000 72%, transparent);
}

.image-lightbox__close {
  position: absolute;
  top: var(--space-4);
  right: var(--space-4);
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
  box-shadow: var(--shadow-md);
}

.image-lightbox__close:hover {
  background: var(--color-surface-muted);
}

.image-lightbox__nav {
  position: absolute;
  top: 50%;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
  transform: translateY(-50%);
  box-shadow: var(--shadow-md);
}

.image-lightbox__nav:hover {
  background: var(--color-surface-muted);
}

.image-lightbox__nav--prev {
  left: var(--space-4);
}

.image-lightbox__nav--next {
  right: var(--space-4);
}

.image-lightbox__stage {
  margin: 0;
  max-width: min(92vw, 72rem);
  max-height: calc(100vh - 5rem);
  display: flex;
  align-items: center;
  justify-content: center;
}

.image-lightbox__image {
  display: block;
  max-width: 100%;
  max-height: calc(100vh - 5.5rem);
  object-fit: contain;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.image-lightbox__counter {
  position: absolute;
  bottom: var(--space-4);
  left: 50%;
  transform: translateX(-50%);
  padding: var(--space-1) var(--space-3);
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 700;
  box-shadow: var(--shadow-sm);
}

.image-lightbox-fade-enter-active,
.image-lightbox-fade-leave-active {
  transition: opacity 0.16s ease;
}

.image-lightbox-fade-enter-from,
.image-lightbox-fade-leave-to {
  opacity: 0;
}

@media (max-width: 768px) {
  .image-lightbox {
    padding: var(--space-3);
  }

  .image-lightbox__close {
    top: var(--space-3);
    right: var(--space-3);
  }

  .image-lightbox__nav--prev {
    left: var(--space-2);
  }

  .image-lightbox__nav--next {
    right: var(--space-2);
  }
}

@media (prefers-reduced-motion: reduce) {
  .image-lightbox-fade-enter-active,
  .image-lightbox-fade-leave-active {
    transition: none;
  }
}
</style>
