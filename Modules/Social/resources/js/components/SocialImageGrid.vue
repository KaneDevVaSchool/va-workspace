<script setup>
import { computed, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { VISIBLE_IMAGE_LIMIT } from '../constants/attachments.js';
import SocialImageLightbox from './SocialImageLightbox.vue';

const props = defineProps({
  images: { type: Array, required: true },
  compact: { type: Boolean, default: false },
  removable: { type: Boolean, default: false },
});

const emit = defineEmits(['remove']);

const lightboxOpen = ref(false);
const lightboxIndex = ref(0);

const extraCount = computed(() => Math.max(0, props.images.length - VISIBLE_IMAGE_LIMIT));
const visibleImages = computed(() => {
  if (props.images.length <= VISIBLE_IMAGE_LIMIT) return props.images;
  return props.images.slice(0, VISIBLE_IMAGE_LIMIT);
});
const layoutCount = computed(() => Math.min(props.images.length, VISIBLE_IMAGE_LIMIT));

function openAt(index) {
  lightboxIndex.value = index;
  lightboxOpen.value = true;
}

function onTileClick(index, isMoreTile) {
  openAt(isMoreTile ? VISIBLE_IMAGE_LIMIT : index);
}

function removeAt(index, event) {
  event.stopPropagation();
  emit('remove', index);
}

watch(
  () => props.images.length,
  (count) => {
    if (count === 0) {
      lightboxOpen.value = false;
      return;
    }
    if (lightboxIndex.value >= count) {
      lightboxIndex.value = count - 1;
    }
  },
);
</script>

<template>
  <div class="image-grid-wrap">
    <div
      v-if="images.length > 0"
      class="image-grid"
      :class="[`image-grid--${layoutCount}`, { 'image-grid--compact': compact }]"
    >
      <div
        v-for="(image, index) in visibleImages"
        :key="`${image.url}-${index}`"
        class="image-grid__tile"
      >
        <button
          type="button"
          class="image-grid__open"
          :aria-label="
            extraCount > 0 && index === visibleImages.length - 1
              ? `Xem thêm ${extraCount} ảnh`
              : `Phóng to ảnh ${index + 1}`
          "
          @click="onTileClick(index, extraCount > 0 && index === visibleImages.length - 1)"
        >
          <img :src="image.url" :alt="image.name || 'Ảnh đính kèm'" class="image-grid__image" />
          <span
            v-if="extraCount > 0 && index === visibleImages.length - 1"
            class="image-grid__more"
          >
            +{{ extraCount }}
          </span>
        </button>
        <button
          v-if="removable"
          type="button"
          class="image-grid__remove"
          aria-label="Bỏ ảnh"
          @click="removeAt(index, $event)"
        >
          <AppIcon name="close" :size="12" />
        </button>
      </div>
    </div>

  <SocialImageLightbox
    :open="lightboxOpen"
    :images="images"
    :index="lightboxIndex"
    @close="lightboxOpen = false"
    @update:index="lightboxIndex = $event"
  />
  </div>
</template>

<style scoped>
.image-grid-wrap {
  min-width: 0;
}

.image-grid {
  display: grid;
  gap: 3px;
  overflow: hidden;
  border-radius: var(--radius-md);
  background: var(--color-border);
}

.image-grid--1 {
  grid-template-columns: 1fr;
}

.image-grid--2 {
  grid-template-columns: 1fr 1fr;
}

.image-grid--3 {
  grid-template-columns: 1.15fr 1fr;
  grid-template-rows: 9rem 9rem;
}

.image-grid--3 .image-grid__tile:first-child {
  grid-row: 1 / 3;
}

.image-grid--4 {
  grid-template-columns: 1fr 1fr;
  grid-template-rows: 10rem 10rem;
}

.image-grid--5 {
  grid-template-columns: repeat(6, 1fr);
  grid-template-rows: 11rem 9rem;
}

.image-grid--5 .image-grid__tile:nth-child(1),
.image-grid--5 .image-grid__tile:nth-child(2) {
  grid-column: span 3;
}

.image-grid--5 .image-grid__tile:nth-child(n + 3) {
  grid-column: span 2;
}

.image-grid--compact.image-grid--3 {
  grid-template-rows: 6rem 6rem;
}

.image-grid--compact.image-grid--4 {
  grid-template-rows: 6.5rem 6.5rem;
}

.image-grid--compact.image-grid--5 {
  grid-template-rows: 7rem 6rem;
}

.image-grid__tile {
  position: relative;
  min-width: 0;
  min-height: 0;
  overflow: hidden;
  background: var(--color-surface-muted);
}

.image-grid__open {
  display: block;
  width: 100%;
  height: 100%;
  padding: 0;
  border: none;
  background: none;
  cursor: pointer;
}

.image-grid--1 .image-grid__tile {
  max-height: 28rem;
}

.image-grid--2 .image-grid__tile {
  height: 16rem;
}

.image-grid--compact.image-grid--1 .image-grid__tile {
  max-height: 14rem;
}

.image-grid--compact.image-grid--2 .image-grid__tile {
  height: 9rem;
}

.image-grid__image {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.image-grid--1 .image-grid__image {
  height: auto;
  max-height: 28rem;
  object-fit: contain;
  background: var(--color-surface-muted);
}

.image-grid--compact.image-grid--1 .image-grid__image {
  max-height: 14rem;
}

.image-grid__more {
  position: absolute;
  inset: 0;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  background: color-mix(in srgb, #000000 48%, transparent);
  color: #fff;
  font-size: 1.75rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.image-grid--compact .image-grid__more {
  font-size: 1.25rem;
}

.image-grid__open:hover .image-grid__image {
  filter: brightness(0.96);
}

.image-grid__open:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: -2px;
}

.image-grid__remove {
  position: absolute;
  top: 0.375rem;
  right: 0.375rem;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
  box-shadow: var(--shadow-sm);
}

.image-grid__remove:hover {
  background: var(--color-surface-muted);
}

@media (max-width: 480px) {
  .image-grid--2 .image-grid__tile {
    height: 9rem;
  }

  .image-grid--3 {
    grid-template-rows: 6.5rem 6.5rem;
  }

  .image-grid--4 {
    grid-template-rows: 7rem 7rem;
  }

  .image-grid--5 {
    grid-template-rows: 8rem 6.5rem;
  }

  .image-grid__more {
    font-size: 1.375rem;
  }
}
</style>
