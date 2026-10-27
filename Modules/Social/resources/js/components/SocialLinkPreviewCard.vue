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
  workspace: 'VA Workspace',
};

const isWorkspace = computed(() => props.preview.provider === 'workspace');
const isVertical = computed(() => props.preview.provider === 'tiktok');

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

function play() {
  if (props.preview.embed_url) playing.value = true;
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
    :is="isWorkspace ? 'a' : 'div'"
    v-if="isWorkspace"
    :href="preview.url"
    class="link-preview link-preview--workspace"
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

    <div v-if="preview.thumbnail_url" class="link-preview__media link-preview__media--article">
      <img
        :src="preview.thumbnail_url"
        alt=""
        class="link-preview__thumb-img link-preview__thumb-img--cover"
      />
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

.link-preview--workspace {
  --lp-accent: var(--color-primary);
  display: block;
  text-decoration: none;
  color: inherit;
  max-width: 26rem;
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-sm);
}

.link-preview--workspace:hover {
  box-shadow:
    inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 35%, var(--color-border)),
    var(--shadow-md);
}

.link-preview--vertical {
  max-width: 22rem;
}

.link-preview--vertical.link-preview--playing {
  max-width: 26rem;
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

.link-preview__media--article {
  aspect-ratio: 1.91 / 1;
  max-height: 12.5rem;
  background: var(--color-surface-muted);
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
