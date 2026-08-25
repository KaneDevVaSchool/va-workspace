<script setup>
import { computed, onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import SocialBirthdayPanel from '../components/SocialBirthdayPanel.vue';
import SocialPinnedPanel from '../components/SocialPinnedPanel.vue';
import SocialPostCard from '../components/SocialPostCard.vue';
import SocialPostComposer from '../components/SocialPostComposer.vue';
import SocialProfilePanel from '../components/SocialProfilePanel.vue';

const auth = useAuthStore();

const posts = ref([]);
const loading = ref(false);
const loadingMore = ref(false);
const page = ref(1);
const lastPage = ref(1);
const pinnedPanel = ref(null);
const composer = ref(null);
const searchQuery = ref('');
const feedScope = ref('all');

const visiblePosts = computed(() => {
  let list = posts.value;

  if (feedScope.value === 'mine' && auth.user?.id) {
    list = list.filter((post) => post.author?.id === auth.user.id);
  }

  const needle = searchQuery.value.trim().toLowerCase();
  if (!needle) return list;

  return list.filter((post) => {
    const hay = [
      post.content,
      post.author?.name,
      post.author?.department,
      post.shared_from?.content,
      post.shared_from?.author?.name,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    return hay.includes(needle);
  });
});

async function loadFeed(targetPage = 1) {
  const isFirstPage = targetPage === 1;
  isFirstPage ? (loading.value = true) : (loadingMore.value = true);

  try {
    const { data } = await window.axios.get('/api/social/posts', {
      params: { page: targetPage, per_page: 10 },
    });

    posts.value = isFirstPage ? data.posts : [...posts.value, ...data.posts];
    page.value = data.current_page;
    lastPage.value = data.last_page;
  } catch (error) {
    showClientToast('error', 'Không thể tải bảng tin.');
  } finally {
    loading.value = false;
    loadingMore.value = false;
  }
}

function loadMore() {
  if (page.value < lastPage.value && !loadingMore.value) {
    loadFeed(page.value + 1);
  }
}

function focusComposer() {
  document.getElementById('social-composer')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  composer.value?.expand();
}

function onPosted(post) {
  posts.value = [post, ...posts.value];
}

function onShared(post) {
  posts.value = [post, ...posts.value];
}

function onDeleted(postId) {
  posts.value = posts.value.filter((p) => p.id !== postId);
}

function onPinned(updatedPost) {
  posts.value = posts.value.map((p) => (p.id === updatedPost.id ? updatedPost : p));
  posts.value.sort((a, b) => Number(b.is_pinned) - Number(a.is_pinned));
  pinnedPanel.value?.load();
}

function onUnpinned(updatedPost) {
  posts.value = posts.value.map((p) => (p.id === updatedPost.id ? updatedPost : p));
  pinnedPanel.value?.load();
}

function scrollToPost(postId) {
  const el = document.getElementById(`social-post-${postId}`);
  el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

onMounted(() => loadFeed(1));
</script>

<template>
  <section class="social-page">
    <PageHeader
      title="Bảng tin nội bộ"
      icon="megaphone"
      description="Cập nhật tin mới, chia sẻ khoảnh khắc và trao đổi cùng đồng nghiệp"
    >
      <template #title>
        <span class="social-head">
          <span class="social-head-brand" style="--i: 0">
            <AppIcon name="megaphone" :size="16" />
            Bảng tin <span class="social-head-brand__sub">nội bộ</span>
          </span>
          <label class="social-head-search" style="--i: 1">
            <AppIcon name="search" :size="16" />
            <input
              v-model="searchQuery"
              type="search"
              placeholder="Tìm bài viết..."
              aria-label="Tìm bài viết, người đăng"
            />
          </label>
          <span class="social-head-copy">
            <span class="social-head-word social-head-word--info" style="--i: 2">
              <AppIcon name="refresh" :size="14" />
              Cập nhật tin mới
            </span>
            <span class="social-head-slash" style="--i: 3">·</span>
            <span class="social-head-word social-head-word--warn" style="--i: 4">
              <AppIcon name="share" :size="14" />
              Chia sẻ khoảnh khắc
            </span>
            <span class="social-head-slash" style="--i: 5">·</span>
            <span class="social-head-word social-head-word--ok" style="--i: 6">
              <AppIcon name="messageCircle" :size="14" />
              Trao đổi cùng đồng nghiệp
            </span>
          </span>
        </span>
      </template>
    </PageHeader>

    <div class="social-page__body">
      <aside class="social-page__rail social-page__rail--left">
        <SocialProfilePanel
          v-model:scope="feedScope"
          @compose="focusComposer"
        />
      </aside>

      <div class="social-page__main">
        <nav class="social-page__scope-bar" aria-label="Lọc bảng tin">
          <button
            type="button"
            class="social-page__scope-btn"
            :class="{ 'social-page__scope-btn--active': feedScope === 'all' }"
            @click="feedScope = 'all'"
          >
            Bảng tin
          </button>
          <button
            type="button"
            class="social-page__scope-btn"
            :class="{ 'social-page__scope-btn--active': feedScope === 'mine' }"
            @click="feedScope = 'mine'"
          >
            Bài của tôi
          </button>
        </nav>

        <SocialPostComposer
          ref="composer"
          :author-avatar-url="auth.user?.avatar_url"
          :author-name="auth.user?.name"
          @posted="onPosted"
        />

        <div v-if="loading" class="social-page__loading">Đang tải bảng tin...</div>

        <div v-else class="social-page__list">
          <div v-for="post in visiblePosts" :id="`social-post-${post.id}`" :key="post.id">
            <SocialPostCard
              :post="post"
              @deleted="onDeleted"
              @pinned="onPinned"
              @unpinned="onUnpinned"
              @shared="onShared"
            />
          </div>

          <div v-if="visiblePosts.length === 0" class="social-page__empty">
            <AppIcon name="megaphone" :size="32" />
            <p v-if="searchQuery.trim()">
              Không tìm thấy bài viết khớp với “{{ searchQuery.trim() }}”.
            </p>
            <p v-else-if="feedScope === 'mine'">Bạn chưa đăng bài viết nào.</p>
            <p v-else>Chưa có bài viết nào trên bảng tin. Hãy là người đăng đầu tiên!</p>
          </div>

          <button
            v-if="page < lastPage && !searchQuery.trim()"
            type="button"
            class="social-page__load-more"
            :disabled="loadingMore"
            @click="loadMore"
          >
            {{ loadingMore ? 'Đang tải...' : 'Xem thêm' }}
          </button>
        </div>
      </div>

      <aside class="social-page__rail social-page__rail--right">
        <SocialPinnedPanel ref="pinnedPanel" @select="scrollToPost" />
        <SocialBirthdayPanel />
      </aside>
    </div>
  </section>
</template>

<style scoped>
.social-page {
  height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-2);
  overflow: hidden;
  position: relative;
  background: var(--color-surface-muted);
}

.social-head {
  display: flex;
  align-items: center;
  gap: var(--space-4);
  width: 100%;
  min-width: 0;
}

.social-head-brand,
.social-head-word,
.social-head-slash {
  animation: social-head-in 0.7s cubic-bezier(0.22, 1, 0.36, 1) backwards;
  animation-delay: calc(var(--i, 0) * 110ms);
}

.social-head-brand {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
  padding-right: var(--space-4);
  color: var(--color-primary);
  box-shadow: 1px 0 0 var(--color-border);
  transition: color 0.45s ease;
}

.social-head-brand__sub {
  font-weight: 500;
  color: var(--color-primary-700);
  opacity: 0.78;
}

.social-head-brand :deep(.app-icon) {
  animation: social-megaphone 3.4s ease-in-out 0.8s infinite;
  transform-origin: 30% 70%;
}

.social-head-brand:hover :deep(.app-icon) {
  animation-play-state: paused;
  transform: rotate(-12deg) scale(1.08);
}

.social-head-search {
  display: flex;
  flex: 0 0 20rem;
  align-items: center;
  gap: 0.5rem;
  width: 20rem;
  height: 2rem;
  padding: 0 0.75rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  transform-origin: left center;
  animation: social-search-in 1.35s cubic-bezier(0.22, 1, 0.36, 1) 0.28s backwards;
  transition:
    box-shadow 0.65s ease,
    color 0.65s ease,
    background 0.65s ease,
    transform 0.65s ease;
}

.social-head-search :deep(.app-icon) {
  transition: transform 0.7s ease, color 0.65s ease;
}

.social-head-search:hover {
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 35%, var(--color-border));
}

.social-head-search:focus-within {
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 5%, var(--color-surface));
  box-shadow:
    inset 0 0 0 1px var(--color-primary),
    0 0 0 4px color-mix(in srgb, var(--color-primary) 12%, transparent);
}

.social-head-search:focus-within :deep(.app-icon) {
  transform: scale(1.12);
  color: var(--color-primary);
}

.social-head-search input {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 500;
  outline: none;
  appearance: none;
  transition: color 0.55s ease;
}

.social-head-search input::placeholder {
  color: var(--color-text-muted);
  transition: opacity 0.7s ease, letter-spacing 0.7s ease;
}

.social-head-search:focus-within input::placeholder {
  opacity: 0.45;
  letter-spacing: 0.02em;
}

.social-head-copy {
  display: flex;
  flex: 1;
  min-width: 0;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
}

.social-head-word {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  flex-shrink: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: default;
  transition: color 0.4s ease, transform 0.4s ease;
}

.social-head-word::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  bottom: -2px;
  height: 2px;
  border-radius: 1px;
  background: currentColor;
  transform: scaleX(0);
  transform-origin: left;
  animation: social-underline 1.5s ease calc(0.85s + var(--i, 0) * 90ms) backwards;
  transition: transform 0.45s ease;
}

.social-head-word:hover {
  transform: translateY(-1px);
}

.social-head-word:hover::after {
  transform: scaleX(1);
  animation: none;
}

.social-head-word :deep(.app-icon) {
  transition: transform 0.55s ease;
}

.social-head-word--info {
  color: var(--color-info);
}

.social-head-word--info :deep(.app-icon) {
  animation: social-icon-spin 7s linear 1.2s infinite;
}

.social-head-word--info:hover :deep(.app-icon) {
  animation-play-state: paused;
  transform: rotate(180deg);
}

.social-head-word--warn {
  color: var(--color-warning);
}

.social-head-word--warn :deep(.app-icon) {
  animation: social-icon-bob 2.6s ease-in-out 1.3s infinite;
}

.social-head-word--warn:hover :deep(.app-icon) {
  animation-play-state: paused;
  transform: translateY(-2px);
}

.social-head-word--ok {
  color: var(--color-success);
}

.social-head-word--ok :deep(.app-icon) {
  animation: social-icon-pulse 2.4s ease-in-out 1.4s infinite;
}

.social-head-word--ok:hover :deep(.app-icon) {
  animation-play-state: paused;
  transform: scale(1.12);
}

.social-head-slash {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-weight: 500;
}

@keyframes social-head-in {
  from {
    opacity: 0;
    transform: translateY(10px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes social-search-in {
  from {
    opacity: 0;
    width: 12rem;
    flex-basis: 12rem;
    transform: translateY(8px) scaleX(0.82);
  }

  to {
    opacity: 1;
    width: 20rem;
    flex-basis: 20rem;
    transform: translateY(0) scaleX(1);
  }
}

@keyframes social-underline {
  0% {
    transform: scaleX(0);
  }

  45% {
    transform: scaleX(1);
  }

  100% {
    transform: scaleX(0);
  }
}

@keyframes social-megaphone {
  0%,
  100% {
    transform: rotate(0);
  }

  18% {
    transform: rotate(-14deg);
  }

  36% {
    transform: rotate(10deg);
  }

  54% {
    transform: rotate(-7deg);
  }

  72% {
    transform: rotate(4deg);
  }
}

@keyframes social-icon-spin {
  from {
    transform: rotate(0deg);
  }

  to {
    transform: rotate(360deg);
  }
}

@keyframes social-icon-bob {
  0%,
  100% {
    transform: translateY(0);
  }

  50% {
    transform: translateY(-2px);
  }
}

@keyframes social-icon-pulse {
  0%,
  100% {
    transform: scale(1);
  }

  50% {
    transform: scale(1.12);
  }
}

@media (prefers-reduced-motion: reduce) {
  .social-head-brand,
  .social-head-search,
  .social-head-word,
  .social-head-slash,
  .social-head-brand :deep(.app-icon),
  .social-head-search :deep(.app-icon),
  .social-head-word :deep(.app-icon),
  .social-head-word::after {
    animation: none;
    transition: none;
  }
}

.social-page__body {
  flex: 1;
  min-height: 0;
  display: grid;
  grid-template-columns: 1fr;
  grid-template-areas:
    'main'
    'right';
  gap: var(--space-3);
  width: 100%;
  overflow: hidden;
}

.social-page__rail {
  min-width: 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  overflow-y: auto;
}

@media (hover: hover) and (pointer: fine) {
  .social-page__body,
  .social-page__rail,
  .social-page__main {
    scrollbar-width: none;
    scrollbar-color: transparent transparent;
  }

  .social-page__body:hover,
  .social-page__body:focus-within,
  .social-page__rail:hover,
  .social-page__rail:focus-within,
  .social-page__main:hover,
  .social-page__main:focus-within {
    scrollbar-width: thin;
    scrollbar-color: var(--scrollbar-thumb) transparent;
  }

  .social-page__body::-webkit-scrollbar,
  .social-page__rail::-webkit-scrollbar,
  .social-page__main::-webkit-scrollbar {
    width: 0;
    height: 0;
  }

  .social-page__body:hover::-webkit-scrollbar,
  .social-page__body:focus-within::-webkit-scrollbar,
  .social-page__rail:hover::-webkit-scrollbar,
  .social-page__rail:focus-within::-webkit-scrollbar,
  .social-page__main:hover::-webkit-scrollbar,
  .social-page__main:focus-within::-webkit-scrollbar {
    width: var(--scrollbar-size);
    height: var(--scrollbar-size);
  }
}

.social-page__rail--left {
  display: none;
  grid-area: left;
}

.social-page__rail--right {
  grid-area: right;
}

.social-page__main {
  grid-area: main;
  min-width: 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  overflow-y: auto;
}

.social-page__scope-bar {
  display: flex;
  gap: var(--space-2);
  flex-shrink: 0;
}

.social-page__scope-btn {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-full);
  cursor: pointer;
}

.social-page__scope-btn:hover {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.social-page__scope-btn--active {
  background: var(--color-primary-surface);
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1px var(--color-primary-200);
}

.social-page__list {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.social-page__loading {
  text-align: center;
  color: var(--color-text-muted);
  padding: var(--space-6);
}

.social-page__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-3);
  text-align: center;
  color: var(--color-text-muted);
  padding: var(--space-8) var(--space-6);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.social-page__load-more {
  align-self: center;
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
  padding: var(--space-2) var(--space-4);
  border-radius: var(--radius-md);
  cursor: pointer;
  font-size: 0.875rem;
}

.social-page__load-more:hover {
  background: var(--color-surface-muted);
}

.social-page__load-more:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (min-width: 769px) {
  .social-page__body {
    grid-template-columns: minmax(0, 1fr) 16rem;
    grid-template-areas: 'main right';
  }
}

@media (min-width: 1280px) {
  .social-page__body {
    grid-template-columns: 15rem minmax(0, 1fr) 17rem;
    grid-template-areas: 'left main right';
  }

  .social-page__rail--left {
    display: flex;
  }

  .social-page__scope-bar {
    display: none;
  }
}

@media (max-width: 768px) {
  .social-page__body {
    overflow-y: auto;
    overflow-x: hidden;
  }

  .social-page__main,
  .social-page__rail--right {
    overflow-y: visible;
  }

  .social-head-copy {
    display: none;
  }

  .social-head-search {
    flex: 1 1 auto;
    width: auto;
    max-width: 22rem;
  }
}

@media (max-width: 480px) {
  .social-page {
    padding: var(--space-2);
  }

  .social-head {
    gap: var(--space-2);
  }

  .social-head-brand {
    padding-right: var(--space-2);
  }

  .social-head-search {
    max-width: none;
  }
}
</style>
