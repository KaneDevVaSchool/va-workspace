<script setup>
import { ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  preview: { type: Object, required: true },
  removable: { type: Boolean, default: false },
});

const emit = defineEmits(['remove']);

const playing = ref(false);

const PROVIDER_LABELS = {
  youtube: 'YouTube',
  facebook: 'Facebook',
  tiktok: 'TikTok',
};

function providerLabel() {
  return PROVIDER_LABELS[props.preview.provider] || props.preview.provider;
}

function play() {
  if (props.preview.embed_url) playing.value = true;
}

function openOriginal() {
  window.open(props.preview.url, '_blank', 'noopener,noreferrer');
}

function onRemove(event) {
  event.stopPropagation();
  emit('remove');
}
</script>

<template>
  <div class="link-preview">
    <button
      v-if="removable"
      type="button"
      class="link-preview__remove"
      aria-label="Bỏ preview này"
      @click="onRemove"
    >
      <AppIcon name="close" :size="14" />
    </button>

    <div class="link-preview__media">
      <iframe
        v-if="playing && preview.embed_url"
        :src="preview.embed_url"
        class="link-preview__frame"
        allow="autoplay; encrypted-media; picture-in-picture"
        allowfullscreen
      ></iframe>
      <button
        v-else
        type="button"
        class="link-preview__thumb-btn"
        :aria-label="preview.embed_url ? `Phát video ${providerLabel()}` : `Mở ${providerLabel()} ở tab mới`"
        @click="preview.embed_url ? play() : openOriginal()"
      >
        <img
          v-if="preview.thumbnail_url"
          :src="preview.thumbnail_url"
          alt=""
          class="link-preview__thumb-img"
        />
        <span v-else class="link-preview__thumb-fallback">
          <AppIcon name="video" :size="28" />
        </span>
        <span v-if="preview.embed_url" class="link-preview__play">
          <AppIcon name="play" :size="20" />
        </span>
      </button>
    </div>

    <div class="link-preview__body">
      <p v-if="preview.is_live" class="link-preview__live">
        <span class="link-preview__live-dot" aria-hidden="true"></span>
        Đang phát trực tiếp
      </p>
      <p class="link-preview__title">{{ preview.title || preview.url }}</p>
      <p class="link-preview__meta">
        {{ providerLabel() }}
        <template v-if="!preview.embed_url"> · Mở ở {{ providerLabel() }} để xem</template>
      </p>
    </div>
  </div>
</template>

<style scoped>
.link-preview {
  position: relative;
  display: flex;
  flex-direction: column;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  background: var(--color-surface);
  max-width: 28rem;
}

.link-preview__remove {
  position: absolute;
  top: var(--space-2);
  right: var(--space-2);
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, black 55%, transparent);
  color: white;
  cursor: pointer;
}

.link-preview__media {
  position: relative;
  width: 100%;
  aspect-ratio: 16 / 9;
  background: var(--color-surface-muted);
}

.link-preview__frame {
  width: 100%;
  height: 100%;
  border: none;
  display: block;
}

.link-preview__thumb-btn {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  border: none;
  padding: 0;
  background: none;
  cursor: pointer;
}

.link-preview__thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.link-preview__thumb-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  color: var(--color-text-muted);
}

.link-preview__play {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  background: color-mix(in srgb, black 25%, transparent);
}

.link-preview__body {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  padding: var(--space-3);
}

.link-preview__live {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin: 0;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-danger);
}

.link-preview__live-dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-danger);
  flex-shrink: 0;
}

.link-preview__title {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-text);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.link-preview__meta {
  margin: 0;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

@media (max-width: 480px) {
  .link-preview {
    max-width: 100%;
  }
}
</style>
