<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { formatRelativeTime } from '@/lib/formatTime';
import { isPwaStandalone } from '@/lib/pwaStandalone';
import SocialReactionPicker from './SocialReactionPicker.vue';
import { useReactionAction } from '../lib/useReactionAction.js';
import { useSocialStoryRealtime } from '../lib/useSocialStoryRealtime.js';

const STORY_IMAGE_MS = 5000;

const emit = defineEmits(['posted', 'open-profile']);

const rings = ref(null);
const viewer = ref(null);
const index = ref(0);
const progress = ref(0);
const fileInput = ref(null);
const posting = ref(false);
const viewersOpen = ref(false);
const viewersLoading = ref(false);
const viewersList = ref([]);
const viewersCount = ref(0);
const viewerPreview = ref([]);
const storyPaused = ref(false);
const activeVideoRef = ref(null);
let progressRaf = null;
let videoProgressRaf = null;
let ringsRefreshTimer = null;
let imageProgressStartedAt = 0;
let imageProgressElapsedBeforePause = 0;
const preloadedUrls = new Set();

const storyImmersive = computed(() => isPwaStandalone());
const { playFeedback, nextGen, isLatest } = useReactionAction();

const activePack = computed(() => viewer.value);
const activeStory = computed(() => activePack.value?.stories?.[index.value] ?? null);

const isOwnStory = computed(() => {
  const mineId = rings.value?.mine?.user?.id;
  const packId = activePack.value?.user?.id;
  return mineId != null && packId != null && mineId === packId;
});

const minePack = computed(() => rings.value?.mine ?? null);
const mineHasStories = computed(() => {
  const mine = minePack.value;
  return Boolean(mine?.has_stories || (mine?.stories?.length ?? 0) > 0);
});

function mineThumbUrl(pack) {
  if (!pack) return null;
  const stories = pack.stories || [];
  for (let i = stories.length - 1; i >= 0; i -= 1) {
    const story = stories[i];
    if (story.media_kind === 'image' && story.media_url) return story.media_url;
  }
  return pack.user?.avatar_url || null;
}

function stopProgressLoop() {
  if (progressRaf != null) {
    window.cancelAnimationFrame(progressRaf);
    progressRaf = null;
  }
}

function syncVideoPlayback() {
  const video = activeVideoRef.value;
  if (!video) return;
  if (storyPaused.value || viewersOpen.value) {
    video.pause();
    return;
  }
  const play = video.play();
  if (play?.catch) play.catch(() => {});
}

function runImageProgressFrame(now) {
  if (!activeStory.value || activeStory.value.media_kind !== 'image') return;
  if (storyPaused.value || viewersOpen.value) {
    progressRaf = null;
    return;
  }
  const elapsed = imageProgressElapsedBeforePause + (now - imageProgressStartedAt);
  progress.value = Math.min(100, (elapsed / STORY_IMAGE_MS) * 100);
  if (progress.value >= 100) {
    stopProgressLoop();
    next();
    return;
  }
  progressRaf = window.requestAnimationFrame(runImageProgressFrame);
}

function startImageProgress() {
  stopProgressLoop();
  imageProgressElapsedBeforePause = 0;
  imageProgressStartedAt = performance.now();
  progressRaf = window.requestAnimationFrame(runImageProgressFrame);
}

function pauseStoryPlayback() {
  if (storyPaused.value || viewersOpen.value) return;
  storyPaused.value = true;
  if (activeStory.value?.media_kind === 'image') {
    imageProgressElapsedBeforePause += performance.now() - imageProgressStartedAt;
    stopProgressLoop();
  }
  syncVideoPlayback();
}

function resumeStoryPlayback() {
  if (!storyPaused.value) return;
  storyPaused.value = false;
  if (activeStory.value?.media_kind === 'image') {
    imageProgressStartedAt = performance.now();
    progressRaf = window.requestAnimationFrame(runImageProgressFrame);
  }
  syncVideoPlayback();
}

function preloadStoryMedia(story) {
  if (!story?.media_url || preloadedUrls.has(story.media_url)) return;
  preloadedUrls.add(story.media_url);
  if (story.media_kind === 'image') {
    const img = new Image();
    img.decoding = 'async';
    img.src = story.media_url;
    return;
  }
  const link = document.createElement('link');
  link.rel = 'preload';
  link.as = 'video';
  link.href = story.media_url;
  document.head.appendChild(link);
}

function preloadAdjacentStories() {
  const stories = activePack.value?.stories || [];
  const i = index.value;
  [stories[i + 1], stories[i + 2], stories[i - 1]].forEach((story) => {
    if (story) preloadStoryMedia(story);
  });
  const list = storyPacks();
  const currentId = activePack.value?.user?.id;
  const at = list.findIndex((item) => item.user?.id === currentId);
  if (at >= 0 && i >= stories.length - 2) {
    const nextPack = list[at + 1];
    const first = nextPack?.stories?.[0];
    if (first) preloadStoryMedia(first);
  }
}

watch(activeStory, (story) => {
  progress.value = 0;
  storyPaused.value = false;
  stopProgressLoop();
  if (!story) return;

  if (isOwnStory.value) {
    viewersCount.value = story.view_count ?? 0;
    viewerPreview.value = story.viewer_preview ?? [];
    refreshViewersQuiet(story.id);
  } else {
    viewersCount.value = 0;
    viewerPreview.value = [];
  }

  preloadStoryMedia(story);
  preloadAdjacentStories();

  if (story.media_kind === 'image') {
    startImageProgress();
  }

  if (!story.viewed && activePack.value?.user && !isOwnStory.value) {
    window.axios.post(`/api/social/stories/${story.id}/view`).catch(() => {});
    story.viewed = true;
    syncRingSeenState();
  }
});

function resumeImageProgressIfNeeded() {
  if (
    viewersOpen.value ||
    storyPaused.value ||
    activeStory.value?.media_kind !== 'image' ||
    progress.value >= 100 ||
    progressRaf != null
  ) {
    return;
  }
  imageProgressStartedAt = performance.now();
  progressRaf = window.requestAnimationFrame(runImageProgressFrame);
}

watch(viewersOpen, (open) => {
  if (open && activeStory.value?.media_kind === 'image' && progressRaf != null) {
    imageProgressElapsedBeforePause += performance.now() - imageProgressStartedAt;
    stopProgressLoop();
  }
  syncVideoPlayback();
  if (!open) resumeImageProgressIfNeeded();
});

watch(storyPaused, () => {
  syncVideoPlayback();
  if (!storyPaused.value) resumeImageProgressIfNeeded();
});

watch(activeVideoRef, () => syncVideoPlayback());

onBeforeUnmount(() => {
  stopProgressLoop();
  if (ringsRefreshTimer) window.clearTimeout(ringsRefreshTimer);
  if (videoProgressRaf != null) window.cancelAnimationFrame(videoProgressRaf);
});

function scheduleRingsRefresh(preserveViewer = false) {
  if (ringsRefreshTimer) window.clearTimeout(ringsRefreshTimer);
  ringsRefreshTimer = window.setTimeout(() => {
    ringsRefreshTimer = null;
    load({ preserveViewer });
  }, 350);
}

load();
useSocialStoryRealtime(() => scheduleRingsRefresh(Boolean(viewer.value)));

const storyViewRoot = ref(null);
watch(viewer, (open) => {
  if (!open) return;
  nextTick(() => storyViewRoot.value?.focus?.());
});

async function load(options = {}) {
  const preserveViewerId = options.preserveViewer ? viewer.value?.user?.id : null;
  try {
    const response = await window.axios.get('/api/social/stories');
    rings.value = response.data;
    if (preserveViewerId && rings.value) {
      const pack = findRingPack(preserveViewerId);
      if (pack?.stories?.length) {
        viewer.value = pack;
        index.value = Math.min(index.value, pack.stories.length - 1);
      }
    }
  } catch {
    if (!preserveViewerId) {
      rings.value = null;
    }
  }
}

function findRingPack(userId) {
  if (!rings.value || userId == null) return null;
  if (rings.value.mine?.user?.id === userId) return rings.value.mine;
  return rings.value.rings?.find((ring) => ring.user?.id === userId) ?? null;
}

function syncRingSeenState() {
  const pack = activePack.value;
  if (!pack?.user?.id || !rings.value) return;

  const userId = pack.user.id;
  const stories = pack.stories || [];
  const unseen = stories.some((story) => !story.viewed);

  const merge = (ring) => {
    if (ring?.user?.id !== userId) return ring;
    return {
      ...ring,
      stories,
      unseen,
      has_stories: stories.length > 0,
    };
  };

  if (rings.value.mine?.user?.id === userId) {
    rings.value.mine = merge(rings.value.mine);
  } else if (Array.isArray(rings.value.rings)) {
    rings.value.rings = rings.value.rings.map(merge);
  }
}

async function onStoryReaction(type, event) {
  const story = activeStory.value;
  if (!story || isOwnStory.value) return;

  const previous = story.my_reaction ?? null;
  const removing = previous === type;
  story.my_reaction = removing ? null : type;
  playFeedback(type, event, removing);

  const generation = nextGen();
  try {
    const { data } = await window.axios.post(`/api/social/stories/${story.id}/reactions`, { type });
    if (isLatest(generation)) {
      story.my_reaction = data.my_reaction ?? null;
    }
  } catch {
    if (isLatest(generation)) {
      story.my_reaction = previous;
    }
  }
}

function initial(name) {
  return name?.trim().charAt(0).toUpperCase() || '?';
}

function openMine() {
  const mine = rings.value?.mine;
  if (!mine?.has_stories && !mine?.stories?.length) {
    fileInput.value?.click();
    return;
  }
  openPack(mine);
}

function openAddStory() {
  fileInput.value?.click();
}

function storyPacks() {
  return (rings.value?.rings || []).filter((ring) => ring.has_stories !== false && (ring.stories?.length ?? 0) > 0);
}

function openPack(pack) {
  if (!pack?.has_stories && !(pack?.stories?.length)) {
    if (pack?.user?.id) emit('open-profile', pack.user.id);
    return;
  }
  viewer.value = pack;
  const firstUnseen = (pack.stories || []).findIndex((story) => !story.viewed);
  index.value = firstUnseen >= 0 ? firstUnseen : 0;
  storyPaused.value = false;
}

function ringLabel(ring) {
  if (!ring.has_stories) return `${ring.user.name}, chưa có story`;
  if (ring.unseen) return `${ring.user.name}, story mới`;
  return `${ring.user.name}, đã xem story`;
}

function closeViewer() {
  viewer.value = null;
  viewersOpen.value = false;
  viewersList.value = [];
  storyPaused.value = false;
  stopProgressLoop();
  activeVideoRef.value = null;
}

function onViewerKeydown(event) {
  if (!viewer.value) return;
  if (event.key === 'Escape') {
    if (viewersOpen.value) closeViewersSheet();
    else closeViewer();
    return;
  }
  if (viewersOpen.value) return;
  if (event.key === 'ArrowRight') next();
  if (event.key === 'ArrowLeft') previous();
}

function storyTimeLabel(iso) {
  return formatRelativeTime(iso) || '';
}

async function refreshViewersQuiet(storyId) {
  try {
    const { data } = await window.axios.get(`/api/social/stories/${storyId}/viewers`);
    viewersCount.value = data.view_count ?? 0;
    viewerPreview.value = (data.viewers ?? []).slice(0, 4).map((row) => row.user);
    if (activeStory.value?.id === storyId) {
      activeStory.value.view_count = viewersCount.value;
      activeStory.value.viewer_preview = viewerPreview.value;
    }
  } catch {
    /* giữ số liệu từ rings nếu không tải được */
  }
}

async function openViewersSheet() {
  const story = activeStory.value;
  if (!story || !isOwnStory.value) return;
  viewersOpen.value = true;
  viewersLoading.value = true;
  try {
    const { data } = await window.axios.get(`/api/social/stories/${story.id}/viewers`);
    viewersList.value = data.viewers ?? [];
    viewersCount.value = data.view_count ?? viewersList.value.length;
    viewerPreview.value = viewersList.value.slice(0, 4).map((row) => row.user);
  } catch {
    viewersList.value = [];
  } finally {
    viewersLoading.value = false;
  }
}

function closeViewersSheet() {
  viewersOpen.value = false;
  viewersList.value = [];
}

function viewersCountLabel(count) {
  if (!count) return 'Chưa có lượt xem';
  if (count === 1) return '1 lượt xem';
  return `${count} lượt xem`;
}

function next() {
  const stories = activePack.value?.stories || [];
  if (index.value < stories.length - 1) {
    index.value += 1;
    return;
  }
  const list = storyPacks();
  const currentId = activePack.value?.user?.id;
  const at = list.findIndex((item) => item.user?.id === currentId);
  if (at >= 0 && list[at + 1]) {
    openPack(list[at + 1]);
    return;
  }
  closeViewer();
}

function previous() {
  if (index.value > 0) {
    index.value -= 1;
    return;
  }
  const list = storyPacks();
  const currentId = activePack.value?.user?.id;
  const at = list.findIndex((item) => item.user?.id === currentId);
  if (at > 0) openPack(list[at - 1]);
}

function onVideoTimeUpdate(event) {
  if (viewersOpen.value || storyPaused.value) return;
  if (videoProgressRaf != null) return;
  const el = event.target;
  videoProgressRaf = window.requestAnimationFrame(() => {
    videoProgressRaf = null;
    if (!el || el !== activeVideoRef.value) return;
    progress.value = (el.currentTime / Math.max(el.duration || 1, 0.1)) * 100;
  });
}

function bindVideoRef(el) {
  activeVideoRef.value = el;
}

async function onFile(event) {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file || posting.value) return;
  posting.value = true;
  try {
    const form = new FormData();
    form.append('file', file);
    await window.axios.post('/api/social/stories', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    await load();
    emit('posted');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || error?.response?.data?.errors?.file?.[0] || 'Không đăng được story.');
  } finally {
    posting.value = false;
  }
}

async function removeActive() {
  const story = activeStory.value;
  if (!story || !rings.value?.mine || activePack.value?.user?.id !== rings.value.mine.user?.id) return;
  await window.axios.delete(`/api/social/stories/${story.id}`).catch(() => {});
  await load();
  const mine = rings.value?.mine;
  if (!mine?.stories?.length) closeViewer();
  else openPack(mine);
}

defineExpose({ reload: load });
</script>

<template>
  <section v-if="rings" class="story-rail" aria-label="Story">
    <div class="story-rail__scroller hide-scrollbar">
      <button type="button" class="story-rail__item" @click="openMine">
        <span
          class="story-rail__ring"
          :class="{
            'story-rail__ring--fresh': mineHasStories && rings.mine.unseen,
            'story-rail__ring--seen': mineHasStories && !rings.mine.unseen,
            'story-rail__ring--empty': !mineHasStories,
          }"
        >
          <span class="story-rail__avatar">
            <img v-if="mineThumbUrl(rings.mine)" :src="mineThumbUrl(rings.mine)" alt="" />
            <span v-else class="story-rail__initial">{{ initial(rings.mine.user.name) }}</span>
          </span>
        </span>
        <span class="story-rail__plus" aria-label="Thêm story" @click.stop="openAddStory">
          <AppIcon name="plus" :size="13" :stroke-width="2.5" />
        </span>
        <span class="story-rail__name">Story của bạn</span>
      </button>
      <button
        v-for="ring in rings.rings"
        :key="ring.user.id"
        type="button"
        class="story-rail__item"
        :aria-label="ringLabel(ring)"
        @click="openPack(ring)"
      >
        <span
          class="story-rail__ring"
          :class="{
            'story-rail__ring--fresh': ring.has_stories && ring.unseen,
            'story-rail__ring--seen': ring.has_stories && !ring.unseen,
            'story-rail__ring--idle': !ring.has_stories,
          }"
        >
          <span class="story-rail__avatar">
            <img v-if="mineThumbUrl(ring) || ring.user.avatar_url" :src="mineThumbUrl(ring) || ring.user.avatar_url" alt="" />
            <span v-else class="story-rail__initial">{{ initial(ring.user.name) }}</span>
          </span>
        </span>
        <span class="story-rail__name">{{ ring.user.name }}</span>
      </button>
    </div>
    <input
      ref="fileInput"
      class="story-rail__file"
      type="file"
      accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime"
      @change="onFile"
    />

    <Teleport to="body">
      <div
        v-if="viewer && activeStory"
        class="story-view"
        :class="{ 'story-view--immersive': storyImmersive, 'story-view--paused': storyPaused }"
        role="dialog"
        aria-modal="true"
        :aria-label="`Story của ${viewer.user.name}`"
        ref="storyViewRoot"
        tabindex="-1"
        @mousedown.self="closeViewer"
        @keydown="onViewerKeydown"
      >
        <div class="story-view__shell">
          <div
            class="story-view__frame"
            @pointerdown="pauseStoryPlayback"
            @pointerup="resumeStoryPlayback"
            @pointercancel="resumeStoryPlayback"
            @pointerleave="resumeStoryPlayback"
          >
            <div class="story-view__media">
              <Transition name="story-media">
                <img
                  v-if="activeStory.media_kind === 'image'"
                  :key="`img-${activeStory.id}`"
                  :src="activeStory.media_url"
                  alt=""
                  decoding="async"
                  fetchpriority="high"
                />
                <video
                  v-else
                  :key="`vid-${activeStory.id}`"
                  :ref="bindVideoRef"
                  :src="activeStory.media_url"
                  autoplay
                  playsinline
                  preload="auto"
                  @ended="next"
                  @timeupdate="onVideoTimeUpdate"
                />
              </Transition>
            </div>
            <div v-if="storyPaused && !viewersOpen" class="story-view__pause-hint" aria-hidden="true">
              <AppIcon name="pauseCircle" :size="36" :stroke-width="1.75" />
            </div>

            <div class="story-view__shade story-view__shade--top" aria-hidden="true" />
            <div class="story-view__shade story-view__shade--bottom" aria-hidden="true" />

            <div class="story-view__bars" aria-hidden="true">
              <span
                v-for="(story, barIndex) in viewer.stories"
                :key="story.id"
                class="story-view__bar"
              >
                <span
                  class="story-view__bar-fill"
                  :class="{ 'story-view__bar-fill--live': barIndex === index }"
                  :style="{
                    transform: `scaleX(${
                      barIndex < index ? 1 : barIndex === index ? progress / 100 : 0
                    })`,
                  }"
                />
              </span>
            </div>

            <header class="story-view__head" @pointerdown.stop>
              <span class="story-view__avatar">
                <img v-if="viewer.user.avatar_url" :src="viewer.user.avatar_url" alt="" />
                <span v-else>{{ initial(viewer.user.name) }}</span>
              </span>
              <div class="story-view__meta">
                <span class="story-view__who">{{ viewer.user.name }}</span>
                <span v-if="activeStory.created_at" class="story-view__time">{{ storyTimeLabel(activeStory.created_at) }}</span>
              </div>
              <button
                v-if="isOwnStory"
                type="button"
                class="story-view__icon"
                aria-label="Xoá story"
                @click.stop="removeActive"
              >
                <AppIcon name="trash" :size="17" :stroke-width="1.75" />
              </button>
              <button type="button" class="story-view__icon" aria-label="Đóng story" @click.stop="closeViewer">
                <AppIcon name="close" :size="18" :stroke-width="1.75" />
              </button>
            </header>

            <button
              type="button"
              class="story-view__nav story-view__nav--prev"
              aria-label="Story trước"
              @pointerdown.stop
              @click.stop="previous"
            />
            <button
              type="button"
              class="story-view__nav story-view__nav--next"
              aria-label="Story sau"
              @pointerdown.stop
              @click.stop="next"
            />

            <div
              v-if="!isOwnStory && !viewersOpen"
              class="story-view__react-row"
              @pointerdown.stop
            >
              <SocialReactionPicker
                :my-reaction="activeStory.my_reaction"
                compact
                @pick="onStoryReaction"
              />
            </div>

            <button
              v-if="isOwnStory"
              type="button"
              class="story-view__views"
              :aria-label="viewersCountLabel(viewersCount)"
              @pointerdown.stop
              @click.stop="openViewersSheet"
            >
              <span v-if="viewerPreview.length" class="story-view__views-stack" aria-hidden="true">
                <span
                  v-for="(person, previewIndex) in viewerPreview.slice(0, 3)"
                  :key="person.id"
                  class="story-view__views-chip"
                  :style="{ zIndex: 3 - previewIndex }"
                >
                  <img v-if="person.avatar_url" :src="person.avatar_url" alt="" />
                  <span v-else>{{ initial(person.name) }}</span>
                </span>
              </span>
              <span class="story-view__views-label">{{ viewersCountLabel(viewersCount) }}</span>
              <AppIcon class="story-view__views-chevron" name="chevronDown" :size="16" :stroke-width="2" />
            </button>
          </div>
        </div>

        <Transition name="story-viewers-fade">
          <div
            v-if="viewersOpen"
            class="story-viewers"
            role="presentation"
            @mousedown.self="closeViewersSheet"
          >
            <div class="story-viewers__panel" role="dialog" aria-modal="true" aria-label="Người đã xem story">
              <header class="story-viewers__header">
                <h2 class="story-viewers__title">{{ viewersCountLabel(viewersCount) }}</h2>
                <button type="button" class="story-viewers__close" aria-label="Đóng" @click="closeViewersSheet">
                  <AppIcon name="close" :size="18" />
                </button>
              </header>
              <div class="story-viewers__body hide-scrollbar">
                <p v-if="viewersLoading" class="story-viewers__empty">Đang tải...</p>
                <p v-else-if="viewersList.length === 0" class="story-viewers__empty">Chưa có ai xem story này.</p>
                <ul v-else class="story-viewers__people">
                  <li v-for="item in viewersList" :key="item.user.id" class="story-viewers__person">
                    <img
                      v-if="item.user.avatar_url"
                      class="story-viewers__avatar"
                      :src="item.user.avatar_url"
                      :alt="`Ảnh đại diện của ${item.user.name}`"
                    />
                    <div v-else class="story-viewers__avatar story-viewers__avatar--placeholder">
                      {{ initial(item.user.name) }}
                    </div>
                    <div class="story-viewers__info">
                      <div class="story-viewers__name">{{ item.user.name }}</div>
                      <div v-if="item.viewed_at" class="story-viewers__time">{{ storyTimeLabel(item.viewed_at) }}</div>
                    </div>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
.story-rail {
  margin: 0 0 0.85rem;
  padding: 0.65rem 0.75rem 0.55rem;
  border-radius: 1rem;
  background: linear-gradient(
    165deg,
    color-mix(in srgb, var(--color-primary-surface) 55%, var(--color-surface)) 0%,
    var(--color-surface) 48%,
    color-mix(in srgb, var(--color-tertiary-100) 35%, var(--color-surface)) 100%
  );
  box-shadow:
    inset 0 0 0 1px color-mix(in srgb, var(--color-border) 80%, transparent),
    0 10px 28px color-mix(in srgb, var(--color-primary) 6%, transparent);
}

.story-rail__scroller {
  display: flex;
  gap: 0.65rem;
  overflow-x: auto;
  padding: 0.15rem 0.05rem 0.35rem;
}

.story-rail__item {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.4rem;
  width: 4.75rem;
  flex: 0 0 auto;
  border: 0;
  background: transparent;
  color: var(--color-text);
  cursor: pointer;
  padding: 0;
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
}

.story-rail__ring {
  display: grid;
  place-items: center;
  width: 4.25rem;
  height: 4.25rem;
  border-radius: 999px;
  padding: 3px;
  background: linear-gradient(
    145deg,
    var(--color-tertiary-500) 0%,
    var(--color-primary) 52%,
    var(--color-tertiary-600) 100%
  );
  box-sizing: border-box;
  flex-shrink: 0;
}

.story-rail__ring--fresh {
  padding: 3px;
  background: linear-gradient(
    145deg,
    var(--color-tertiary-500) 0%,
    var(--color-primary) 52%,
    var(--color-tertiary-600) 100%
  );
}

.story-rail__ring--seen {
  padding: 3.5px;
  background: linear-gradient(
    145deg,
    color-mix(in srgb, var(--color-text-muted) 92%, var(--color-text)) 0%,
    var(--color-text-muted) 48%,
    color-mix(in srgb, var(--color-text-muted) 88%, var(--color-tertiary-700, #333)) 100%
  );
  box-shadow:
    0 0 0 1px color-mix(in srgb, var(--color-text-muted) 35%, transparent),
    0 2px 6px color-mix(in srgb, var(--color-text) 10%, transparent);
}

.story-rail__ring--empty {
  padding: 2px;
  background: var(--color-border);
}

.story-rail__ring--idle {
  padding: 0;
  background: transparent;
  box-shadow: none;
}

.story-rail__ring--idle .story-rail__avatar {
  box-shadow: inset 0 0 0 2px var(--color-border-strong, #bbbbbb);
}

.story-rail__avatar {
  display: grid;
  place-items: center;
  width: 100%;
  height: 100%;
  border-radius: 999px;
  overflow: hidden;
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-border) 65%, transparent);
}

.story-rail__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.story-rail__initial {
  font-size: 1.125rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--color-tertiary-700);
  background: linear-gradient(
    165deg,
    color-mix(in srgb, var(--color-tertiary-100) 85%, var(--color-surface)) 0%,
    color-mix(in srgb, var(--color-primary-surface) 70%, var(--color-surface)) 100%
  );
  width: 100%;
  height: 100%;
  display: grid;
  place-items: center;
}

.story-rail__plus {
  position: absolute;
  top: 2.55rem;
  right: 0.05rem;
  z-index: 2;
  display: grid;
  place-items: center;
  width: 1.45rem;
  height: 1.45rem;
  border-radius: 999px;
  border: 2.5px solid var(--color-surface);
  background: var(--color-primary);
  color: var(--color-on-primary, #fff);
  box-shadow:
    0 2px 8px color-mix(in srgb, var(--color-primary) 38%, transparent),
    inset 0 1px 0 color-mix(in srgb, #fff 22%, transparent);
  pointer-events: auto;
}

.story-rail__name {
  max-width: 4.75rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.6875rem;
  font-weight: 600;
  line-height: 1.25;
  color: var(--color-text-muted, var(--color-text));
}
.story-rail__file {
  display: none;
}
.story-view {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: grid;
  place-items: center;
  padding: max(0.75rem, env(safe-area-inset-top)) max(0.75rem, env(safe-area-inset-right))
    max(0.75rem, env(safe-area-inset-bottom)) max(0.75rem, env(safe-area-inset-left));
  background: #0a0a0cf2;
}

.story-view__shell {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: min(22.5rem, 100%);
  max-height: calc(100vh - 1.5rem);
}

.story-view--immersive {
  padding: 0;
  min-height: 100dvh;
  background: #050508;
  backdrop-filter: none;
}

.story-view--immersive .story-view__shell {
  width: 100%;
  height: 100%;
  max-height: none;
}

.story-view--immersive .story-view__frame {
  width: 100%;
  height: 100%;
  max-height: none;
  aspect-ratio: auto;
  border-radius: 0;
  box-shadow: none;
}

.story-view__frame {
  position: relative;
  width: 100%;
  aspect-ratio: 9 / 16;
  max-height: calc(100vh - 1.5rem);
  border-radius: 1.35rem;
  overflow: hidden;
  background: #050508;
  box-shadow:
    0 28px 80px color-mix(in srgb, #000 55%, transparent),
    0 0 0 1px color-mix(in srgb, #fff 12%, transparent),
    inset 0 1px 0 color-mix(in srgb, #fff 8%, transparent);
}

.story-view__media {
  position: absolute;
  inset: 0;
  background: #0d0d12;
  overflow: hidden;
}

.story-view__media img,
.story-view__media video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.story-view__shade {
  position: absolute;
  left: 0;
  right: 0;
  pointer-events: none;
  z-index: 1;
}

.story-view__shade--top {
  top: 0;
  height: 38%;
  background: linear-gradient(180deg, color-mix(in srgb, #000 72%, transparent) 0%, transparent 100%);
}

.story-view__shade--bottom {
  bottom: 0;
  height: 32%;
  background: linear-gradient(0deg, color-mix(in srgb, #000 78%, transparent) 0%, transparent 100%);
}

.story-view__bars {
  position: absolute;
  top: 0.65rem;
  left: 0.65rem;
  right: 0.65rem;
  z-index: 3;
  display: flex;
  gap: 0.22rem;
}

.story-view__bar {
  flex: 1;
  height: 2.5px;
  border-radius: 99px;
  background: color-mix(in srgb, #fff 28%, transparent);
  overflow: hidden;
}

.story-view__bar-fill {
  display: block;
  width: 100%;
  height: 100%;
  border-radius: inherit;
  background: #fff;
  box-shadow: 0 0 8px color-mix(in srgb, #fff 45%, transparent);
  transform: scaleX(0);
  transform-origin: left center;
  backface-visibility: hidden;
}

.story-view__bar-fill--live {
  will-change: transform;
}

.story-media-enter-active {
  transition: opacity 0.12s ease;
}

.story-media-leave-active {
  transition: opacity 0.08s ease;
  position: absolute;
  inset: 0;
}

.story-media-enter-from,
.story-media-leave-to {
  opacity: 0;
}

.story-view__pause-hint {
  position: absolute;
  inset: 0;
  z-index: 2;
  display: grid;
  place-items: center;
  pointer-events: none;
  color: #fff;
  background: color-mix(in srgb, #000 22%, transparent);
}

.story-view--paused .story-view__bar-fill--live {
  will-change: auto;
}

.story-view__head {
  position: absolute;
  top: 1.15rem;
  left: 0.65rem;
  right: 0.55rem;
  z-index: 4;
  display: flex;
  align-items: center;
  gap: 0.55rem;
  color: #fff;
}

.story-view__avatar {
  display: grid;
  place-items: center;
  width: 2.15rem;
  height: 2.15rem;
  flex-shrink: 0;
  border-radius: 999px;
  overflow: hidden;
  background: color-mix(in srgb, var(--color-primary) 35%, #222);
  box-shadow: 0 0 0 2px color-mix(in srgb, #fff 35%, transparent);
  font-size: 0.875rem;
  font-weight: 700;
}

.story-view__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.story-view__meta {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
}

.story-view__who {
  font-size: 0.875rem;
  font-weight: 650;
  line-height: 1.2;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-shadow: 0 1px 6px color-mix(in srgb, #000 55%, transparent);
}

.story-view__time {
  font-size: 0.6875rem;
  font-weight: 500;
  color: color-mix(in srgb, #fff 78%, transparent);
  text-shadow: 0 1px 4px color-mix(in srgb, #000 45%, transparent);
}

.story-view__icon {
  display: grid;
  place-items: center;
  width: 2.15rem;
  height: 2.15rem;
  flex-shrink: 0;
  border: 0;
  border-radius: 999px;
  background: color-mix(in srgb, #fff 12%, transparent);
  color: #fff;
  cursor: pointer;
  backdrop-filter: blur(6px);
}

.story-view__nav {
  position: absolute;
  top: 4.5rem;
  bottom: 4.5rem;
  z-index: 2;
  width: 38%;
  border: 0;
  padding: 0;
  background: transparent;
  cursor: pointer;
}

.story-view__nav--prev {
  left: 0;
}

.story-view__nav--next {
  right: 0;
}

.story-view__views {
  position: absolute;
  left: 0.75rem;
  right: 0.75rem;
  bottom: 0.85rem;
  z-index: 4;
  display: flex;
  align-items: center;
  gap: 0.55rem;
  padding: 0.55rem 0.75rem;
  border: 0;
  border-radius: 999px;
  background: color-mix(in srgb, #fff 14%, transparent);
  color: #fff;
  cursor: pointer;
  backdrop-filter: blur(10px);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, #fff 18%, transparent);
}

.story-view__views-stack {
  display: flex;
  align-items: center;
  padding-left: 0.15rem;
}

.story-view__views-chip {
  position: relative;
  display: grid;
  place-items: center;
  width: 1.65rem;
  height: 1.65rem;
  margin-left: -0.45rem;
  border-radius: 999px;
  overflow: hidden;
  background: #2a2a32;
  box-shadow: 0 0 0 2px color-mix(in srgb, #fff 22%, transparent);
  font-size: 0.625rem;
  font-weight: 700;
}

.story-view__views-chip:first-child {
  margin-left: 0;
}

.story-view__views-chip img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.story-view__views-label {
  flex: 1;
  min-width: 0;
  text-align: left;
  font-size: 0.8125rem;
  font-weight: 600;
}

.story-view__views-chevron {
  flex-shrink: 0;
  transform: rotate(180deg);
  opacity: 0.9;
}

.story-view__react-row {
  position: absolute;
  left: 50%;
  bottom: max(0.85rem, env(safe-area-inset-bottom));
  z-index: 5;
  transform: translateX(-50%);
  display: flex;
  justify-content: center;
  max-width: calc(100% - 1.5rem);
}

.story-view--immersive .story-view__react-row {
  bottom: max(1rem, env(safe-area-inset-bottom));
}

.story-view__react-row :deep(.reaction-picker--compact) {
  color: #fff;
}

.story-viewers {
  position: fixed;
  inset: 0;
  z-index: 85;
  display: grid;
  align-items: end;
  padding: 0.75rem;
  background: color-mix(in srgb, #0a0a0c 55%, transparent);
}

.story-viewers__panel {
  width: min(26rem, 100%);
  max-height: min(28rem, 72vh);
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: 1.15rem 1.15rem 1rem 1rem;
  background: var(--color-surface);
  box-shadow:
    0 24px 64px color-mix(in srgb, #000 28%, transparent),
    inset 0 0 0 1px var(--color-border);
}

.story-viewers__header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.85rem 1rem;
  border-bottom: 1px solid var(--color-border);
}

.story-viewers__title {
  flex: 1;
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 700;
}

.story-viewers__close {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: var(--color-text);
  cursor: pointer;
}

.story-viewers__body {
  flex: 1;
  min-height: 0;
  overflow: auto;
  padding: 0.35rem 0;
}

.story-viewers__empty {
  margin: 0;
  padding: 1.25rem 1rem;
  text-align: center;
  font-size: 0.875rem;
  color: var(--color-text-muted, var(--color-text));
}

.story-viewers__people {
  list-style: none;
  margin: 0;
  padding: 0;
}

.story-viewers__person {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.55rem 1rem;
}

.story-viewers__avatar {
  width: 2.5rem;
  height: 2.5rem;
  flex-shrink: 0;
  border-radius: 999px;
  object-fit: cover;
}

.story-viewers__avatar--placeholder {
  display: grid;
  place-items: center;
  background: var(--color-primary-surface, var(--color-surface-muted));
  color: var(--color-primary);
  font-weight: 700;
}

.story-viewers__info {
  flex: 1;
  min-width: 0;
}

.story-viewers__name {
  font-size: 0.875rem;
  font-weight: 650;
  line-height: 1.25;
}

.story-viewers__time {
  margin-top: 0.15rem;
  font-size: 0.75rem;
  color: var(--color-text-muted, var(--color-text));
}

.story-viewers-fade-enter-active,
.story-viewers-fade-leave-active {
  transition: opacity 0.18s ease;
}

.story-viewers-fade-enter-active .story-viewers__panel,
.story-viewers-fade-leave-active .story-viewers__panel {
  transition: transform 0.22s ease;
}

.story-viewers-fade-enter-from,
.story-viewers-fade-leave-to {
  opacity: 0;
}

.story-viewers-fade-enter-from .story-viewers__panel,
.story-viewers-fade-leave-to .story-viewers__panel {
  transform: translateY(1.25rem);
}
</style>
