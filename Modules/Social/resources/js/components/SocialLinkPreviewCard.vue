<script setup>
import { computed, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import {
  buildYoutubeEmbedSrc,
  preconnectYoutubePlayer,
  youtubeThumbnailCandidates,
} from '../lib/linkPreviewUtils.js';

const props = defineProps({
  preview: { type: Object, required: true },
  removable: { type: Boolean, default: false },
});

const emit = defineEmits(['remove']);

const playing = ref(false);
const frameReady = ref(false);
const thumbIndex = ref(0);
const thumbReady = ref(false);
const thumbFailed = ref(false);

const PROVIDER_LABELS = {
  youtube: 'YouTube',
  facebook: 'Facebook',
  tiktok: 'TikTok',
  workspace: 'VA Workspace',
  website: 'Liên kết',
};

const isArticleEmbed = computed(() => ['workspace', 'website'].includes(props.preview.provider));
const isVertical = computed(() => props.preview.provider === 'tiktok');
const isYoutube = computed(() => props.preview.provider === 'youtube');
const sourceUrl = computed(() => props.preview.url || props.preview.embed_url || '');

const thumbCandidates = computed(() => {
  if (isYoutube.value) {
    return youtubeThumbnailCandidates(sourceUrl.value, props.preview.thumbnail_url);
  }
  return props.preview.thumbnail_url ? [props.preview.thumbnail_url] : [];
});

const thumbSrc = computed(() => (
  thumbFailed.value ? '' : (thumbCandidates.value[thumbIndex.value] || '')
));

const canPlay = computed(() => {
  if (isYoutube.value) {
    return Boolean(buildYoutubeEmbedSrc(sourceUrl.value) || props.preview.embed_url);
  }
  return Boolean(props.preview.embed_url);
});

const playerSrc = computed(() => {
  if (!playing.value) return '';
  if (isYoutube.value) {
    return buildYoutubeEmbedSrc(sourceUrl.value, { autoplay: true }) || props.preview.embed_url || '';
  }
  return props.preview.embed_url || '';
});

watch(
  () => [props.preview.url, props.preview.embed_url, props.preview.thumbnail_url],
  () => {
    playing.value = false;
    frameReady.value = false;
    thumbIndex.value = 0;
    thumbReady.value = false;
    thumbFailed.value = false;
  },
);

const siteLabel = computed(() => {
  if (props.preview.site_name) return props.preview.site_name;
  try {
    return new URL(props.preview.url).hostname;
  } catch {
    return 'VA Workspace';
  }
});

function providerLabel() {
  return PROVIDER_LABELS[props.preview.provider] || props.preview.provider;
}

function warmPlayer() {
  if (isYoutube.value) preconnectYoutubePlayer();
}

function play() {
  if (!canPlay.value) {
    openOriginal();
    return;
  }
  warmPlayer();
  frameReady.value = false;
  playing.value = true;
}

function onThumbLoad(event) {
  const img = event.target;
  if (
    img?.naturalWidth
    && img.naturalWidth < 200
    && thumbIndex.value < thumbCandidates.value.length - 1
  ) {
    thumbReady.value = false;
    thumbIndex.value += 1;
    return;
  }
  thumbReady.value = true;
}

function onThumbError() {
  thumbReady.value = false;
  if (thumbIndex.value < thumbCandidates.value.length - 1) {
    thumbIndex.value += 1;
    return;
  }
  thumbFailed.value = true;
}

function openOriginal() {
  window.open(props.preview.url, '_blank', 'noopener,noreferrer');
}

function onRemove(event) {
  event.stopPropagation();
  event.preventDefault();
  emit('remove');
}
</script>

<template>
  <component
    :is="isArticleEmbed ? 'a' : 'div'"
    v-if="isArticleEmbed"
    :href="preview.url"
    class="link-preview link-preview--article"
    :class="`link-preview--${preview.provider}`"
    target="_blank"
    rel="noopener noreferrer"
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

    <div class="link-preview__media link-preview__media--article">
      <img
        v-if="preview.thumbnail_url"
        :src="preview.thumbnail_url"
        alt=""
        class="link-preview__thumb-img link-preview__thumb-img--cover"
        loading="lazy"
        decoding="async"
      />
      <div v-else class="link-preview__article-fallback" aria-hidden="true">
        <img src="/images/pwa/icon-192.png" alt="" class="link-preview__article-logo" />
      </div>
    </div>

    <div class="link-preview__body link-preview__body--article">
      <p class="link-preview__title link-preview__title--article">{{ preview.title || preview.url }}</p>
      <p v-if="preview.description" class="link-preview__description">{{ preview.description }}</p>
      <p class="link-preview__site">{{ siteLabel }}</p>
    </div>
  </component>

  <div
    v-else
    class="link-preview"
    :class="[
      `link-preview--${preview.provider}`,
      { 'link-preview--vertical': isVertical, 'link-preview--playing': playing },
    ]"
    @pointerenter="warmPlayer"
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
        v-if="playing && playerSrc"
        :src="playerSrc"
        :title="preview.title || providerLabel()"
        class="link-preview__frame"
        :class="{ 'link-preview__frame--ready': frameReady }"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
        referrerpolicy="strict-origin-when-cross-origin"
        allowfullscreen
        @load="frameReady = true"
      ></iframe>
      <span v-if="playing && !frameReady" class="link-preview__loading" aria-hidden="true"></span>

      <button
        v-if="!playing"
        type="button"
        class="link-preview__thumb-btn"
        :aria-label="canPlay ? `Phát video ${providerLabel()}` : `Mở ${providerLabel()} ở tab mới`"
        @pointerdown="warmPlayer"
        @click="play"
      >
        <img
          v-if="thumbSrc"
          :src="thumbSrc"
          alt=""
          class="link-preview__thumb-img"
          :class="{ 'link-preview__thumb-img--ready': thumbReady }"
          loading="lazy"
          decoding="async"
          @load="onThumbLoad"
          @error="onThumbError"
        />
        <span v-else class="link-preview__thumb-fallback" aria-hidden="true">
          <AppIcon name="video" :size="32" />
        </span>

        <span class="link-preview__scrim" aria-hidden="true"></span>

        <span v-if="canPlay" class="link-preview__play" aria-hidden="true">
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
      <a
        class="link-preview__title"
        :href="sourceUrl || preview.url"
        target="_blank"
        rel="noopener noreferrer"
      >{{ preview.title || preview.url }}</a>
      <p v-if="preview.description" class="link-preview__description">{{ preview.description }}</p>
      <p v-if="preview.site_name" class="link-preview__site">{{ preview.site_name }}</p>
      <p v-if="!canPlay" class="link-preview__meta">
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

.link-preview--article {
  --lp-accent: var(--color-primary);
  position: relative;
  display: block;
  text-decoration: none;
  color: inherit;
  max-width: 26rem;
  padding-left: calc(var(--space-2) + 3px + var(--space-2));
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-sm);
}

.link-preview--article::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--lp-accent);
}

.link-preview--article:hover {
  box-shadow:
    inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 35%, var(--color-border)),
    var(--shadow-md);
}

.link-preview--website {
  --lp-accent: var(--color-info);
}

.link-preview--vertical {
  max-width: 22rem;
}

.link-preview--vertical.link-preview--playing {
  max-width: 26rem;
}

.link-preview--youtube {
  --lp-accent: #ff2d2d;
  max-width: min(100%, 40rem);
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

.link-preview__media--article {
  aspect-ratio: 1.91 / 1;
  max-height: 12.5rem;
  background: var(--color-surface-muted);
}

.link-preview__article-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  min-height: 5.5rem;
  background: linear-gradient(
    145deg,
    color-mix(in srgb, var(--color-primary) 8%, var(--color-surface-muted)),
    var(--color-surface-muted)
  );
}

.link-preview__article-logo {
  width: 3rem;
  height: 3rem;
  object-fit: contain;
  opacity: 0.85;
}

.link-preview--vertical .link-preview__media {
  aspect-ratio: 9 / 16;
}

.link-preview--vertical.link-preview--playing .link-preview__media {
  padding: var(--space-2);
  background: var(--color-surface-muted);
}

.link-preview__thumb-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}

.link-preview__thumb-btn .link-preview__thumb-img {
  object-fit: cover;
  opacity: 0;
}

.link-preview__thumb-btn .link-preview__thumb-img--ready {
  opacity: 1;
}

.link-preview__thumb-btn:focus-visible {
  outline: 2px solid var(--lp-accent);
  outline-offset: -2px;
}

.link-preview__thumb-img--cover {
  object-fit: cover;
}

.link-preview__thumb-btn {
  position: relative;
  display: block;
  width: 100%;
  height: 100%;
  padding: 0;
  border: none;
  background: transparent;
  cursor: pointer;
}

.link-preview__thumb-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  color: var(--color-text-muted);
}

.link-preview__scrim {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, color-mix(in srgb, black 45%, transparent), transparent 55%);
  pointer-events: none;
}

.link-preview__play {
  position: absolute;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
  display: flex;
  align-items: center;
  justify-content: center;
  width: 3.25rem;
  height: 3.25rem;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, black 55%, transparent);
  color: white;
  pointer-events: none;
}

.link-preview__live-tag {
  position: absolute;
  top: var(--space-2);
  left: var(--space-2);
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.2rem 0.5rem;
  border-radius: var(--radius-full);
  background: #e11d48;
  color: white;
  font-size: 0.6875rem;
  font-weight: 700;
}

.link-preview__live-dot {
  width: 0.4rem;
  height: 0.4rem;
  border-radius: var(--radius-full);
  background: white;
}

.link-preview__provider-tag {
  position: absolute;
  bottom: var(--space-2);
  left: var(--space-2);
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.2rem 0.45rem;
  border-radius: var(--radius-sm);
  background: color-mix(in srgb, black 62%, transparent);
  color: white;
  font-size: 0.6875rem;
  font-weight: 600;
}

.link-preview__frame {
  width: 100%;
  height: 100%;
  border: none;
  opacity: 0;
}

.link-preview__frame--ready {
  opacity: 1;
}

.link-preview__loading {
  position: absolute;
  left: 50%;
  top: 50%;
  z-index: 1;
  width: 2rem;
  height: 2rem;
  margin: -1rem 0 0 -1rem;
  border-radius: var(--radius-full);
  border: 2px solid color-mix(in srgb, var(--lp-accent) 35%, transparent);
  border-top-color: var(--lp-accent);
  animation: link-preview-spin 0.7s linear infinite;
}

@media (prefers-reduced-motion: reduce) {
  .link-preview__loading {
    animation: none;
    border-top-color: var(--lp-accent);
  }
}

@keyframes link-preview-spin {
  to {
    transform: rotate(360deg);
  }
}

.link-preview__body {
  padding: var(--space-3);
}

.link-preview__body--article {
  padding: var(--space-2) var(--space-3) var(--space-3);
}

.link-preview__title {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-text);
  line-height: 1.35;
  text-decoration: none;
}

.link-preview__body:not(.link-preview__body--article) .link-preview__title {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

a.link-preview__title:hover {
  text-decoration: underline;
}

.link-preview__title--article {
  font-size: 0.9375rem;
  font-weight: 700;
}

.link-preview__description {
  margin: 0.35rem 0 0;
  font-size: 0.8125rem;
  line-height: 1.45;
  color: var(--color-text-muted);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.link-preview__site {
  margin: 0.5rem 0 0;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-primary);
}

.link-preview__meta {
  margin: 0.35rem 0 0;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}
</style>
