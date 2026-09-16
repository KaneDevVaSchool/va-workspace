<script setup>
import { computed, ref } from 'vue';
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

// TikTok nhúng theo khung dọc tự nhiên (9:16, giống clip TikTok thật) —
// YouTube/Facebook giữ khung ngang chuẩn (16:9). Ép chung 1 tỉ lệ làm
// video TikTok bị bó méo/cắt hai bên.
const isVertical = computed(() => props.preview.provider === 'tiktok');

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
  <div
    class="link-preview"
    :class="[
      `link-preview--${preview.provider}`,
      { 'link-preview--vertical': isVertical, 'link-preview--playing': playing },
    ]"
  >
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
        <span v-else class="link-preview__thumb-fallback" aria-hidden="true">
          <AppIcon name="video" :size="32" />
        </span>

        <span class="link-preview__scrim" aria-hidden="true"></span>

        <span v-if="preview.embed_url" class="link-preview__play" aria-hidden="true">
          <AppIcon name="play" :size="26" />
        </span>

        <span v-if="preview.is_live" class="link-preview__live-tag">
          <span class="link-preview__live-dot" aria-hidden="true"></span>
          Trực tiếp
        </span>

        <span class="link-preview__provider-tag" :class="`link-preview__provider-tag--${preview.provider}`">
          <AppIcon :name="preview.provider === 'youtube' ? 'video' : 'globe'" :size="12" />
          {{ providerLabel() }}
        </span>
      </button>
    </div>

    <div class="link-preview__body">
      <p class="link-preview__title">{{ preview.title || preview.url }}</p>
      <p v-if="!preview.embed_url" class="link-preview__meta">
        Không xem trước được &middot; mở ở {{ providerLabel() }} để xem
      </p>
    </div>
  </div>
</template>

<style scoped>
.link-preview {
  --lp-accent: var(--color-primary);

  position: relative;
  display: flex;
  flex-direction: column;
  border-radius: var(--radius-xl, var(--radius-lg));
  overflow: hidden;
  background: var(--color-surface);
  box-shadow: var(--shadow-md);
  max-width: 28rem;
  transition:
    box-shadow 0.25s ease,
    transform 0.25s ease;
}

.link-preview:hover {
  box-shadow: var(--shadow-lg);
}

.link-preview--vertical {
  max-width: 16rem;
}

.link-preview--youtube {
  --lp-accent: #ff2d2d;
}

.link-preview--facebook {
  --lp-accent: #1877f2;
}

.link-preview--tiktok {
  --lp-accent: #25f4ee;
}

.link-preview__remove {
  position: absolute;
  top: var(--space-2);
  right: var(--space-2);
  z-index: 3;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-sidebar-overlay);
  color: white;
  cursor: pointer;
  transition:
    background 0.2s ease,
    transform 0.2s ease;
}

.link-preview__remove:hover {
  background: color-mix(in srgb, black 75%, transparent);
  transform: scale(1.08);
}

.link-preview__media {
  position: relative;
  width: 100%;
  aspect-ratio: 16 / 9;
  background: linear-gradient(
    160deg,
    color-mix(in srgb, var(--lp-accent) 18%, var(--color-surface-muted)),
    var(--color-surface-muted)
  );
}

.link-preview--vertical .link-preview__media {
  aspect-ratio: 9 / 16;
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
  overflow: hidden;
}

.link-preview__thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.4s ease;
}

.link-preview__thumb-btn:hover .link-preview__thumb-img {
  transform: scale(1.04);
}

.link-preview__thumb-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  color: color-mix(in srgb, var(--lp-accent) 55%, var(--color-text-muted));
}

.link-preview__scrim {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    to top,
    color-mix(in srgb, black 55%, transparent) 0%,
    transparent 45%
  );
  opacity: 0;
  transition: opacity 0.25s ease;
}

.link-preview__thumb-btn:hover .link-preview__scrim {
  opacity: 1;
}

.link-preview__play {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: auto;
  width: 3.75rem;
  height: 3.75rem;
  border-radius: var(--radius-full);
  color: white;
  background: color-mix(in srgb, black 35%, transparent);
  box-shadow: 0 0 0 1px color-mix(in srgb, white 25%, transparent) inset;
  backdrop-filter: blur(2px);
  transition:
    transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1),
    background 0.25s ease;
}

.link-preview__play :deep(svg) {
  margin-left: 3px;
}

.link-preview__thumb-btn:hover .link-preview__play {
  transform: scale(1.1);
  background: var(--lp-accent);
}

.link-preview__provider-tag {
  position: absolute;
  left: var(--space-2);
  bottom: var(--space-2);
  z-index: 1;
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.2rem 0.55rem;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, black 45%, transparent);
  color: white;
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.01em;
  backdrop-filter: blur(2px);
}

.link-preview__provider-tag :deep(svg) {
  color: var(--lp-accent);
}

.link-preview__live-tag {
  position: absolute;
  right: var(--space-2);
  top: var(--space-2);
  z-index: 1;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.25rem 0.6rem;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, black 45%, transparent);
  color: white;
  font-size: 0.6875rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  backdrop-filter: blur(2px);
}

.link-preview--vertical .link-preview__live-tag {
  right: calc(var(--space-2) + 1.75rem + var(--space-2));
}

.link-preview__live-dot {
  width: 0.4rem;
  height: 0.4rem;
  border-radius: var(--radius-full);
  background: var(--color-danger, #ff3b3b);
  flex-shrink: 0;
  animation: link-preview-pulse 1.6s ease-in-out infinite;
}

.link-preview__body {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: var(--space-3);
  box-shadow: inset 0 1px 0 var(--color-border);
}

.link-preview__title {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.35;
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

@keyframes link-preview-pulse {
  0%,
  100% {
    opacity: 1;
    transform: scale(1);
  }

  50% {
    opacity: 0.45;
    transform: scale(0.75);
  }
}

@media (prefers-reduced-motion: reduce) {
  .link-preview,
  .link-preview__remove,
  .link-preview__thumb-img,
  .link-preview__scrim,
  .link-preview__play {
    transition: none;
  }

  .link-preview__live-dot {
    animation: none;
  }
}

@media (max-width: 480px) {
  .link-preview,
  .link-preview--vertical {
    max-width: 100%;
  }
}
</style>
