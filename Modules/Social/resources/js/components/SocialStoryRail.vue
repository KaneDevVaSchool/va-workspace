<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';

const emit = defineEmits(['posted', 'open-profile']);

const rings = ref(null);
const viewer = ref(null);
const index = ref(0);
const progress = ref(0);
const fileInput = ref(null);
const posting = ref(false);
let timer = null;

const activePack = computed(() => viewer.value);
const activeStory = computed(() => activePack.value?.stories?.[index.value] ?? null);

watch(activeStory, (story) => {
  progress.value = 0;
  window.clearInterval(timer);
  if (!story) return;
  if (story.media_kind === 'image') {
    const started = Date.now();
    timer = window.setInterval(() => {
      progress.value = Math.min(100, ((Date.now() - started) / 5000) * 100);
      if (progress.value >= 100) next();
    }, 80);
  }
  if (!story.viewed && activePack.value?.user) {
    window.axios.post(`/api/social/stories/${story.id}/view`).catch(() => {});
    story.viewed = true;
  }
});

onBeforeUnmount(() => window.clearInterval(timer));

load();

async function load() {
  try {
    const response = await window.axios.get('/api/social/stories');
    rings.value = response.data;
  } catch {
    rings.value = null;
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
}

function ringLabel(ring) {
  if (!ring.has_stories) return `${ring.user.name}, chưa có story`;
  if (ring.unseen) return `${ring.user.name}, story mới`;
  return `${ring.user.name}, đã xem story`;
}

function closeViewer() {
  viewer.value = null;
  window.clearInterval(timer);
  load();
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

function onStageClick(event) {
  const width = event.currentTarget?.getBoundingClientRect?.().width || 1;
  if (event.offsetX < width * 0.35) previous();
  else next();
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
          :class="{ 'story-rail__ring--mine': !rings.mine.has_stories && !rings.mine.stories.length }"
        >
          <img v-if="rings.mine.user.avatar_url" :src="rings.mine.user.avatar_url" alt="" />
          <span v-else>{{ initial(rings.mine.user.name) }}</span>
          <span v-if="!rings.mine.has_stories && !rings.mine.stories.length" class="story-rail__plus" aria-hidden="true">
            <AppIcon name="plus" :size="12" :stroke-width="2.4" />
          </span>
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
            'story-rail__ring--seen': ring.has_stories && !ring.unseen,
            'story-rail__ring--idle': !ring.has_stories,
          }"
        >
          <img v-if="ring.user.avatar_url" :src="ring.user.avatar_url" alt="" />
          <span v-else>{{ initial(ring.user.name) }}</span>
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
        role="dialog"
        aria-modal="true"
        :aria-label="`Story của ${viewer.user.name}`"
      >
        <div class="story-view__panel">
          <div class="story-view__bars" aria-hidden="true">
            <span
              v-for="(story, barIndex) in viewer.stories"
              :key="story.id"
              class="story-view__bar"
            >
              <span
                class="story-view__bar-fill"
                :style="{ width: barIndex < index ? '100%' : barIndex === index ? `${progress}%` : '0%' }"
              />
            </span>
          </div>
          <div class="story-view__head">
            <span class="story-view__who">{{ viewer.user.name }}</span>
            <button
              v-if="viewer.user.id === rings.mine.user.id"
              type="button"
              class="story-view__icon"
              aria-label="Xoá story"
              @click="removeActive"
            >
              <AppIcon name="trash" :size="16" :stroke-width="1.75" />
            </button>
            <button type="button" class="story-view__icon" aria-label="Đóng story" @click="closeViewer">
              <AppIcon name="close" :size="16" :stroke-width="1.75" />
            </button>
          </div>
          <button type="button" class="story-view__stage" aria-label="Chuyển story" @click="onStageClick">
            <img v-if="activeStory.media_kind === 'image'" :src="activeStory.media_url" alt="" />
            <video
              v-else
              :src="activeStory.media_url"
              autoplay
              playsinline
              @ended="next"
              @timeupdate="progress = ($event.target.currentTime / Math.max($event.target.duration || 1, 0.1)) * 100"
            />
          </button>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
.story-rail {
  margin: 0 0 0.75rem;
}
.story-rail__scroller {
  display: flex;
  gap: 0.75rem;
  overflow-x: auto;
  padding: 0.15rem 0.1rem 0.35rem;
}
.story-rail__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.35rem;
  width: 4.6rem;
  flex: 0 0 auto;
  border: 0;
  background: transparent;
  color: var(--color-text);
  cursor: pointer;
}
.story-rail__ring {
  position: relative;
  display: grid;
  place-items: center;
  width: 4.1rem;
  height: 4.1rem;
  border-radius: 999px;
  overflow: hidden;
  background: var(--color-surface);
  box-shadow: 0 0 0 2px var(--color-surface), 0 0 0 4px var(--color-primary);
  font-weight: 650;
}
.story-rail__ring--seen,
.story-rail__ring--mine,
.story-rail__ring--idle {
  box-shadow: 0 0 0 2px var(--color-surface), 0 0 0 4px var(--color-border);
}
.story-rail__ring--idle {
  opacity: 0.92;
}
.story-rail__ring img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.story-rail__plus {
  position: absolute;
  right: 0;
  bottom: 0;
  display: grid;
  place-items: center;
  width: 1.15rem;
  height: 1.15rem;
  border-radius: 999px;
  background: var(--color-primary);
  color: var(--color-on-primary, #fff);
}
.story-rail__name {
  max-width: 4.6rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.72rem;
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
  padding: 1rem;
  background: color-mix(in srgb, var(--color-text) 35%, transparent);
}
.story-view__panel {
  display: flex;
  flex-direction: column;
  width: min(26rem, 100%);
  height: min(40rem, calc(100vh - 2rem));
  overflow: hidden;
  border-radius: 16px;
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}
.story-view__bars {
  display: flex;
  gap: 0.25rem;
  padding: 0.7rem 0.7rem 0;
}
.story-view__bar {
  flex: 1;
  height: 3px;
  border-radius: 99px;
  background: var(--color-border);
  overflow: hidden;
}
.story-view__bar-fill {
  display: block;
  height: 100%;
  background: var(--color-primary);
}
.story-view__head {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.55rem 0.7rem;
}
.story-view__who {
  flex: 1;
  font-weight: 650;
}
.story-view__icon {
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
.story-view__stage {
  flex: 1;
  min-height: 0;
  border: 0;
  padding: 0;
  background: var(--color-primary-surface, var(--color-surface));
  cursor: pointer;
}
.story-view__stage img,
.story-view__stage video {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
</style>
