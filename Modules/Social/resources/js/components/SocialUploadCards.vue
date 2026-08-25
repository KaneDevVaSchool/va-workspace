<script setup>
import { computed, onBeforeUnmount, ref, shallowRef, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import SocialImageLightbox from './SocialImageLightbox.vue';

const props = defineProps({
  files: { type: Array, required: true },
  compact: { type: Boolean, default: false },
});

const emit = defineEmits(['remove']);

const previewUrls = shallowRef(new Map());
const lightboxOpen = ref(false);
const lightboxIndex = ref(0);

function isImageFile(file) {
  return Boolean(file?.type?.startsWith('image/'));
}

function formatSize(bytes) {
  if (!Number.isFinite(bytes) || bytes < 0) return '';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function fileIcon(file) {
  const name = file.name?.toLowerCase() ?? '';
  if (name.endsWith('.xlsx') || name.endsWith('.xls') || name.endsWith('.csv')) {
    return 'fileSpreadsheet';
  }
  if (name.endsWith('.pdf') || name.endsWith('.doc') || name.endsWith('.docx')) {
    return 'fileText';
  }
  return 'paperclip';
}

function syncPreviewUrls(list) {
  const next = new Map(previewUrls.value);
  const live = new Set(list);
  let changed = false;

  for (const file of list) {
    if (!isImageFile(file) || next.has(file)) continue;
    next.set(file, URL.createObjectURL(file));
    changed = true;
  }

  for (const [file, url] of next) {
    if (live.has(file)) continue;
    URL.revokeObjectURL(url);
    next.delete(file);
    changed = true;
  }

  if (changed) previewUrls.value = next;
}

watch(
  () => props.files,
  (list) => syncPreviewUrls(list ?? []),
  { immediate: true, flush: 'sync' },
);

onBeforeUnmount(() => {
  previewUrls.value.forEach((url) => URL.revokeObjectURL(url));
  previewUrls.value = new Map();
});

const imageItems = computed(() =>
  props.files
    .map((file, index) => ({ file, index }))
    .filter((item) => isImageFile(item.file))
    .map((item) => ({
      ...item,
      url: previewUrls.value.get(item.file) ?? '',
      name: item.file.name,
      sizeLabel: formatSize(item.file.size),
    }))
    .filter((item) => item.url),
);

const fileItems = computed(() =>
  props.files
    .map((file, index) => ({ file, index }))
    .filter((item) => !isImageFile(item.file))
    .map((item) => ({
      ...item,
      name: item.file.name,
      sizeLabel: formatSize(item.file.size),
      icon: fileIcon(item.file),
    })),
);

const layoutKey = computed(() => {
  const count = imageItems.value.length;
  if (count <= 4) return String(count);
  return 'many';
});

const lightboxImages = computed(() =>
  imageItems.value.map((item) => ({ url: item.url, name: item.name })),
);

function openAt(index) {
  lightboxIndex.value = index;
  lightboxOpen.value = true;
}

function removeAt(index, event) {
  event.stopPropagation();
  emit('remove', index);
}

watch(
  () => imageItems.value.length,
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
  <div class="upload-cards" :class="{ 'upload-cards--compact': compact }">
    <div
      v-if="imageItems.length > 0"
      class="upload-cards__images"
      :class="`upload-cards__images--${layoutKey}`"
    >
      <article
        v-for="(item, imageIndex) in imageItems"
        :key="`${item.index}-${item.name}`"
        class="upload-cards__tile"
        :style="{ animationDelay: `${Math.min(imageIndex, 8) * 40}ms` }"
      >
        <button
          type="button"
          class="upload-cards__open"
          :aria-label="`Phóng to ${item.name}`"
          @click="openAt(imageIndex)"
        >
          <img :src="item.url" :alt="item.name" class="upload-cards__image" />
          <span class="upload-cards__zoom" aria-hidden="true">
            <AppIcon name="zoomIn" :size="compact ? 16 : 18" />
          </span>
          <span class="upload-cards__caption">
            <span class="upload-cards__filename">{{ item.name }}</span>
            <span v-if="item.sizeLabel" class="upload-cards__size">{{ item.sizeLabel }}</span>
          </span>
        </button>
        <button
          type="button"
          class="upload-cards__remove"
          :aria-label="`Bỏ ảnh ${item.name}`"
          @click="removeAt(item.index, $event)"
        >
          <AppIcon name="close" :size="compact ? 12 : 14" />
        </button>
      </article>
    </div>

    <ul v-if="fileItems.length > 0" class="upload-cards__files">
      <li v-for="item in fileItems" :key="`file-${item.index}`" class="upload-cards__file">
        <span class="upload-cards__file-icon" aria-hidden="true">
          <AppIcon :name="item.icon" :size="18" />
        </span>
        <span class="upload-cards__file-copy">
          <span class="upload-cards__file-name">{{ item.name }}</span>
          <span v-if="item.sizeLabel" class="upload-cards__file-size">{{ item.sizeLabel }}</span>
        </span>
        <button
          type="button"
          class="upload-cards__file-remove"
          :aria-label="`Bỏ tệp ${item.name}`"
          @click="removeAt(item.index, $event)"
        >
          <AppIcon name="close" :size="14" />
        </button>
      </li>
    </ul>

    <SocialImageLightbox
      :open="lightboxOpen"
      :images="lightboxImages"
      :index="lightboxIndex"
      @close="lightboxOpen = false"
      @update:index="lightboxIndex = $event"
    />
  </div>
</template>

<style scoped>
.upload-cards {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-width: 0;
}

.upload-cards__images {
  display: grid;
  gap: var(--space-2);
  min-width: 0;
}

.upload-cards__images--1 {
  grid-template-columns: 1fr;
}

.upload-cards__images--2,
.upload-cards__images--4 {
  grid-template-columns: 1fr 1fr;
}

.upload-cards__images--3 {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.upload-cards__images--many {
  grid-template-columns: repeat(auto-fill, minmax(7.5rem, 1fr));
}

.upload-cards__tile {
  position: relative;
  display: grid;
  isolation: isolate;
  min-width: 0;
  overflow: hidden;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow:
    var(--shadow-sm),
    inset 0 0 0 1px var(--color-border);
  animation: upload-card-in 0.32s cubic-bezier(0.22, 1, 0.36, 1) both;
  transition: box-shadow 0.25s ease;
}

.upload-cards__tile:hover {
  box-shadow:
    var(--shadow-md),
    inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 22%, var(--color-border));
}

.upload-cards__images--1 .upload-cards__tile {
  max-height: 18rem;
  min-height: 10rem;
}

.upload-cards__images--2 .upload-cards__tile,
.upload-cards__images--4 .upload-cards__tile {
  aspect-ratio: 4 / 3;
}

.upload-cards__images--3 .upload-cards__tile,
.upload-cards__images--many .upload-cards__tile {
  aspect-ratio: 1;
}

.upload-cards__open {
  display: block;
  grid-area: 1 / 1;
  width: 100%;
  height: 100%;
  min-width: 0;
  min-height: 0;
  padding: 0;
  overflow: hidden;
  border: none;
  background: none;
  cursor: pointer;
  color: inherit;
}

.upload-cards__image {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.35s ease;
}

.upload-cards__images--1 .upload-cards__image {
  height: auto;
  max-height: 18rem;
  min-height: 10rem;
  object-fit: contain;
  background: var(--color-surface-muted);
}

.upload-cards__zoom {
  position: absolute;
  inset: 0;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  background: color-mix(in srgb, #000000 0%, transparent);
  opacity: 0;
  pointer-events: none;
  transition:
    opacity 0.25s ease,
    background 0.25s ease;
}

.upload-cards__caption {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  padding: 1.75rem 0.65rem 0.55rem;
  background: linear-gradient(
    to top,
    color-mix(in srgb, #000000 58%, transparent) 0%,
    transparent 100%
  );
  color: #fff;
  text-align: left;
  pointer-events: none;
}

.upload-cards__filename {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.25;
}

.upload-cards__size {
  font-size: 0.6875rem;
  font-weight: 500;
  opacity: 0.84;
}

.upload-cards__remove {
  position: absolute;
  top: 0.45rem;
  right: 0.45rem;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, #000000 58%, transparent);
  color: #fff;
  cursor: pointer;
  box-shadow: 0 0 0 1px color-mix(in srgb, #ffffff 22%, transparent);
  transition:
    background 0.2s ease,
    transform 0.2s ease;
}

.upload-cards__remove:hover {
  background: color-mix(in srgb, #000000 78%, transparent);
  transform: scale(1.06);
}

.upload-cards__open:hover .upload-cards__image,
.upload-cards__open:focus-visible .upload-cards__image {
  transform: scale(1.04);
}

.upload-cards__open:hover .upload-cards__zoom,
.upload-cards__open:focus-visible .upload-cards__zoom {
  opacity: 1;
  background: color-mix(in srgb, #000000 28%, transparent);
}

.upload-cards__open:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: -2px;
}

.upload-cards__files {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.upload-cards__file {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  min-width: 0;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.upload-cards__file-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-sm);
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.upload-cards__file-copy {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  min-width: 0;
  flex: 1;
}

.upload-cards__file-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text);
}

.upload-cards__file-size {
  font-size: 0.6875rem;
  font-weight: 500;
  color: var(--color-text-muted);
}

.upload-cards__file-remove {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-full);
  background: none;
  color: var(--color-text-muted);
  cursor: pointer;
}

.upload-cards__file-remove:hover {
  background: var(--color-surface);
  color: var(--color-text);
}

.upload-cards--compact .upload-cards__images--1 .upload-cards__tile,
.upload-cards--compact .upload-cards__images--1 .upload-cards__image {
  min-height: 7rem;
  max-height: 11rem;
}

.upload-cards--compact .upload-cards__images--many {
  grid-template-columns: repeat(auto-fill, minmax(5.75rem, 1fr));
}

.upload-cards--compact .upload-cards__remove {
  width: 1.5rem;
  height: 1.5rem;
  top: 0.35rem;
  right: 0.35rem;
}

.upload-cards--compact .upload-cards__caption {
  padding: 1.25rem 0.45rem 0.4rem;
}

.upload-cards--compact .upload-cards__filename {
  font-size: 0.6875rem;
}

@keyframes upload-card-in {
  from {
    opacity: 0;
    transform: translateY(6px) scale(0.96);
  }

  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

@media (prefers-reduced-motion: reduce) {
  .upload-cards__tile {
    animation: none;
  }

  .upload-cards__image,
  .upload-cards__remove,
  .upload-cards__tile {
    transition: none;
  }
}

@media (max-width: 480px) {
  .upload-cards__images--3 {
    grid-template-columns: 1fr 1fr;
  }

  .upload-cards__images--3 .upload-cards__tile:first-child {
    grid-column: 1 / -1;
    aspect-ratio: 16 / 9;
  }

  .upload-cards__images--many {
    grid-template-columns: repeat(auto-fill, minmax(6.25rem, 1fr));
  }

  .upload-cards__images--1 .upload-cards__tile,
  .upload-cards__images--1 .upload-cards__image {
    min-height: 8rem;
    max-height: 14rem;
  }
}
</style>
