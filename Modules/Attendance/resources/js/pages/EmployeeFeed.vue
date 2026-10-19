<script setup>
//
// Bảng tin mobile — đầy đủ tính năng SocialFeed (đăng bài, tường, hashtag,
// ghim, reaction, bình luận) với UX kiểu Facebook.
//
import {
  inject,
  onBeforeUnmount,
  onMounted,
  ref,
  watch,
} from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import SocialColleagueSearch from '@modules/Social/resources/js/components/SocialColleagueSearch.vue';
import SocialHashtagPanel from '@modules/Social/resources/js/components/SocialHashtagPanel.vue';
import SocialPinnedPanel from '@modules/Social/resources/js/components/SocialPinnedPanel.vue';
import SocialPostCard from '@modules/Social/resources/js/components/SocialPostCard.vue';
import SocialPostComposer from '@modules/Social/resources/js/components/SocialPostComposer.vue';
import { useEmployeeSocialFeed } from '../composables/useEmployeeSocialFeed.js';

const registerFeedRefresh = inject('employeeFeedRefreshRegister', null);

const {
  auth,
  loading,
  loadingMore,
  searchQuery,
  peopleResults,
  peopleOpen,
  feedScope,
  postScope,
  focusedPostId,
  openCommentsPostId,
  hashtagTotal,
  feedScopes,
  activeHashtag,
  activeHashtagLabel,
  departmentName,
  currentWallUserId,
  wallUserName,
  viewingOtherWall,
  activeFilterLabel,
  visiblePosts,
  canLoadMore,
  firstUnpinnedIndex,
  loadMore,
  onScopeChange,
  onTabChange,
  openColleagueWall,
  onPosted,
  onUpdated,
  onShared,
  onDeleted,
  onPinned,
  onUnpinned,
  scrollToPost,
  setActiveHashtag,
  onOpenHashtag,
  openWall,
  initFeed,
  refreshFeed,
} = useEmployeeSocialFeed();

const pinnedSystem = ref(null);
const pinnedCompany = ref(null);
const hashtagPanel = ref(null);
const filterSheetOpen = ref(false);
const colleagueSheetOpen = ref(false);
const loadMoreSentinel = ref(null);
let unregisterRefresh;
let loadMoreObserver;

function closeSheets() {
  filterSheetOpen.value = false;
  colleagueSheetOpen.value = false;
}

function onPinnedSelect(post) {
  scrollToPost(post);
}

function bindLoadMoreObserver() {
  loadMoreObserver?.disconnect();
  if (typeof IntersectionObserver === 'undefined' || !loadMoreSentinel.value) return;
  loadMoreObserver = new IntersectionObserver(
    (entries) => {
      if (entries.some((e) => e.isIntersecting)) {
        loadMore();
      }
    },
    { root: null, rootMargin: '240px 0px', threshold: 0 },
  );
  loadMoreObserver.observe(loadMoreSentinel.value);
}

watch(loadMoreSentinel, () => bindLoadMoreObserver());

onMounted(async () => {
  await initFeed();
  bindLoadMoreObserver();
  if (typeof registerFeedRefresh === 'function') {
    unregisterRefresh = registerFeedRefresh(async () => {
      await refreshFeed({
        pinnedSystem: pinnedSystem.value,
        pinnedCompany: pinnedCompany.value,
        hashtagPanel: hashtagPanel.value,
      });
    });
  }
});

onBeforeUnmount(() => {
  unregisterRefresh?.();
  loadMoreObserver?.disconnect();
});
</script>

<template>
  <div class="emp-feed">
    <div class="emp-feed__toolbar">
      <label class="emp-feed__search">
        <AppIcon name="search" :size="18" class="emp-feed__search-icon" />
        <input
          v-model="searchQuery"
          type="search"
          class="emp-feed__search-input"
          placeholder="Tìm bài viết, đồng nghiệp..."
          autocomplete="off"
          enterkeyhint="search"
          @focus="peopleOpen = peopleResults.length > 0"
        />
      </label>
      <button
        type="button"
        class="emp-feed__icon-btn"
        aria-label="Tìm đồng nghiệp"
        @click="colleagueSheetOpen = true"
      >
        <AppIcon name="users" :size="20" :stroke-width="1.8" />
      </button>
      <button
        v-if="!viewingOtherWall"
        type="button"
        class="emp-feed__icon-btn"
        :aria-expanded="filterSheetOpen"
        aria-label="Lọc bảng tin"
        @click="filterSheetOpen = !filterSheetOpen"
      >
        <AppIcon name="sliders" :size="20" :stroke-width="1.8" />
      </button>
    </div>

    <div
      v-if="peopleOpen && searchQuery.trim().length >= 2"
      class="emp-feed__people"
      role="listbox"
      aria-label="Đồng nghiệp"
    >
      <p class="emp-feed__people-label">Đồng nghiệp</p>
      <p v-if="peopleResults.length === 0" class="emp-feed__people-empty">
        Không có đồng nghiệp khớp tên.
      </p>
      <button
        v-for="user in peopleResults"
        :key="user.id"
        type="button"
        class="emp-feed__people-item"
        @click="openColleagueWall(user.id)"
      >
        <span>{{ user.name }}</span>
        <span v-if="user.department" class="emp-feed__people-dept">{{ user.department }}</span>
      </button>
    </div>

    <nav class="emp-feed__walls hide-scrollbar" aria-label="Chọn tường">
      <button
        type="button"
        class="emp-feed__wall-pill"
        :class="{ 'emp-feed__wall-pill--active': postScope === 'company' }"
        @click="onTabChange('company')"
      >
        Bảng tin
      </button>
      <button
        v-if="departmentName"
        type="button"
        class="emp-feed__wall-pill"
        :class="{ 'emp-feed__wall-pill--active': postScope === 'department' }"
        @click="onTabChange('department')"
      >
        {{ departmentName }}
      </button>
      <button
        type="button"
        class="emp-feed__wall-pill"
        :class="{
          'emp-feed__wall-pill--active':
            postScope === 'personal' && !viewingOtherWall,
        }"
        @click="onTabChange('personal')"
      >
        Tường của tôi
      </button>
      <button
        v-if="viewingOtherWall"
        type="button"
        class="emp-feed__wall-pill emp-feed__wall-pill--active"
        aria-current="page"
      >
        {{ wallUserName }}
      </button>
    </nav>

    <div v-if="!viewingOtherWall" class="emp-feed__filters hide-scrollbar">
      <button
        v-for="item in feedScopes"
        :key="item.id"
        type="button"
        class="emp-feed__filter-chip"
        :class="{ 'emp-feed__filter-chip--active': feedScope === item.id }"
        @click="onScopeChange(item.id)"
      >
        {{ item.label }}
      </button>
    </div>

    <div class="emp-feed__composer">
      <SocialPostComposer
        :key="`${postScope}-${currentWallUserId ?? 'none'}`"
        :author-avatar-url="auth.user?.avatar_url"
        :author-name="auth.user?.name"
        :default-scope="postScope"
        :department-name="departmentName"
        :wall-user-id="currentWallUserId"
        :wall-user-name="wallUserName"
        @posted="onPosted"
      />
    </div>

    <div v-if="activeHashtag" class="emp-feed__hashtag-banner">
      <AppIcon name="hash" :size="16" />
      <span>
        #{{ activeHashtagLabel }}
        <template v-if="!loading && hashtagTotal > 0"> · {{ hashtagTotal }} bài</template>
      </span>
      <button type="button" class="emp-feed__hashtag-clear" @click="setActiveHashtag('')">
        Bỏ lọc
      </button>
    </div>

    <div class="emp-feed__side-panels">
      <SocialHashtagPanel
        ref="hashtagPanel"
        class="emp-feed__hashtag-panel"
        :post-scope="postScope"
        :wall-user-id="currentWallUserId"
        :active-hashtag="activeHashtag"
        @select="setActiveHashtag"
      />
    </div>

    <div
      v-if="postScope === 'company' && !activeHashtag"
      class="emp-feed__announce-row hide-scrollbar"
    >
      <SocialPinnedPanel
        ref="pinnedSystem"
        variant="system"
        class="emp-feed__announce-card"
        @select="onPinnedSelect"
      />
      <SocialPinnedPanel
        ref="pinnedCompany"
        variant="company"
        class="emp-feed__announce-card"
        @select="onPinnedSelect"
      />
    </div>

    <div v-if="loading" class="emp-feed__skeleton">
      <div v-for="n in 3" :key="n" class="emp-feed__skeleton-card">
        <div class="emp-feed__sk-row"></div>
        <div class="emp-feed__sk-line"></div>
        <div class="emp-feed__sk-line emp-feed__sk-line--short"></div>
      </div>
    </div>

    <div v-else class="emp-feed__stream">
      <template v-for="(post, index) in visiblePosts" :key="post.id">
        <div
          v-if="index === firstUnpinnedIndex && firstUnpinnedIndex > 0"
          class="emp-feed__split"
          role="separator"
        >
          Bài viết mới
        </div>
        <div :id="`social-post-${post.id}`" class="emp-feed__post">
          <SocialPostCard
            :post="post"
            :post-scope="postScope"
            :department-name="departmentName"
            :open-comments="openCommentsPostId === post.id"
            :highlighted="focusedPostId === post.id"
            @deleted="onDeleted"
            @pinned="onPinned"
            @unpinned="onUnpinned"
            @shared="onShared"
            @updated="onUpdated"
            @open-wall="openWall"
            @open-hashtag="onOpenHashtag"
          />
        </div>
      </template>

      <div v-if="visiblePosts.length === 0" class="emp-feed__empty">
        <span class="emp-feed__empty-icon" aria-hidden="true">
          <AppIcon name="megaphone" :size="28" />
        </span>
        <p v-if="searchQuery.trim()">
          Không tìm thấy bài viết khớp “{{ searchQuery.trim() }}”.
        </p>
        <p v-else-if="activeHashtag">
          Chưa có bài gắn #{{ activeHashtagLabel }} trên tường này.
        </p>
        <p v-else-if="feedScope === 'mine'">Bạn chưa đăng bài viết nào.</p>
        <p v-else-if="feedScope === 'reacted'">Bạn chưa tương tác bài viết nào.</p>
        <p v-else-if="viewingOtherWall">
          Chưa có bài trên tường của {{ wallUserName }}. Hãy là người đăng đầu tiên!
        </p>
        <p v-else-if="postScope === 'personal'">
          Chưa có bài trên tường của bạn. Hãy là người đăng đầu tiên!
        </p>
        <p v-else-if="postScope === 'department'">
          Chưa có bài trên tường {{ departmentName }}.
        </p>
        <p v-else>Chưa có bài trên bảng tin. Hãy là người đăng đầu tiên!</p>
      </div>

      <div
        v-if="canLoadMore && visiblePosts.length"
        ref="loadMoreSentinel"
        class="emp-feed__sentinel"
        aria-hidden="true"
      />

      <p v-if="loadingMore" class="emp-feed__loading-more">Đang tải thêm…</p>
      <p v-else-if="!canLoadMore && visiblePosts.length" class="emp-feed__end">
        Bạn đã xem hết bảng tin
      </p>
    </div>

    <Teleport to="body">
      <Transition name="emp-feed-sheet">
        <div
          v-if="filterSheetOpen"
          class="emp-feed-sheet"
          role="presentation"
          @mousedown.self="closeSheets"
        >
          <div class="emp-feed-sheet__panel" role="dialog" aria-modal="true" aria-label="Lọc bảng tin">
            <header class="emp-feed-sheet__head">
              <h2 class="emp-feed-sheet__title">Lọc bảng tin</h2>
              <button type="button" class="emp-feed-sheet__close" aria-label="Đóng" @click="closeSheets">
                <AppIcon name="close" :size="20" />
              </button>
            </header>
            <ul class="emp-feed-sheet__list">
              <li v-for="item in feedScopes" :key="item.id">
                <button
                  type="button"
                  class="emp-feed-sheet__option"
                  :class="{ 'emp-feed-sheet__option--active': feedScope === item.id }"
                  @click="onScopeChange(item.id); closeSheets()"
                >
                  {{ item.label }}
                  <AppIcon v-if="feedScope === item.id" name="check" :size="18" />
                </button>
              </li>
            </ul>
            <p class="emp-feed-sheet__hint">Đang chọn: {{ activeFilterLabel }}</p>
          </div>
        </div>
      </Transition>

      <Transition name="emp-feed-sheet">
        <div
          v-if="colleagueSheetOpen"
          class="emp-feed-sheet"
          role="presentation"
          @mousedown.self="closeSheets"
        >
          <div class="emp-feed-sheet__panel" role="dialog" aria-modal="true" aria-label="Tìm đồng nghiệp">
            <header class="emp-feed-sheet__head">
              <h2 class="emp-feed-sheet__title">Tìm đồng nghiệp</h2>
              <button type="button" class="emp-feed-sheet__close" aria-label="Đóng" @click="closeSheets">
                <AppIcon name="close" :size="20" />
              </button>
            </header>
            <SocialColleagueSearch @select="(id) => { openColleagueWall(id); closeSheets(); }" />
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.emp-feed {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin: calc(-1 * var(--space-1)) calc(-1 * var(--space-1)) 0;
  padding-bottom: var(--space-2);
}

.emp-feed__toolbar {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  position: sticky;
  top: 0;
  z-index: 5;
  padding: var(--space-1) 0 var(--space-2);
  background: linear-gradient(
    180deg,
    var(--color-tertiary-surface) 70%,
    transparent
  );
}

.emp-feed__search {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0 var(--space-3);
  min-height: 2.5rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.emp-feed__search-input {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  font: inherit;
  font-size: 0.9375rem;
  color: var(--color-text);
}

.emp-feed__search-input:focus {
  outline: none;
}

.emp-feed__search-icon {
  color: var(--color-text-muted);
  flex-shrink: 0;
}

.emp-feed__icon-btn {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: var(--shadow-sm);
  cursor: pointer;
}

.emp-feed__people {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.emp-feed__people-label {
  margin: 0;
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-muted);
}

.emp-feed__people-empty {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.emp-feed__people-item {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  width: 100%;
  padding: var(--space-2) 0;
  border: none;
  border-top: 1px solid var(--color-border-subtle);
  background: transparent;
  text-align: left;
  font: inherit;
  color: var(--color-text);
  cursor: pointer;
}

.emp-feed__people-dept {
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.emp-feed__walls {
  display: flex;
  gap: var(--space-2);
  overflow-x: auto;
  padding-bottom: 2px;
}

.emp-feed__wall-pill {
  flex-shrink: 0;
  padding: var(--space-2) var(--space-3);
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border-subtle);
  font: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text-muted);
  cursor: pointer;
  transition: background 0.15s, color 0.15s, box-shadow 0.15s;
}

.emp-feed__wall-pill--active {
  background: var(--color-primary);
  color: var(--color-on-primary);
  box-shadow: none;
}

.emp-feed__filters {
  display: flex;
  gap: var(--space-2);
  overflow-x: auto;
}

.emp-feed__filter-chip {
  flex-shrink: 0;
  padding: var(--space-1) var(--space-3);
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  font: inherit;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-muted);
  cursor: pointer;
}

.emp-feed__filter-chip--active {
  background: color-mix(in srgb, var(--color-primary) 14%, var(--color-surface));
  color: var(--color-primary);
}

.emp-feed__composer :deep(.composer) {
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
}

.emp-feed__hashtag-banner {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  font-size: 0.8125rem;
}

.emp-feed__hashtag-clear {
  margin-left: auto;
  border: none;
  background: transparent;
  font: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-primary);
  cursor: pointer;
}

.emp-feed__side-panels :deep(.hashtag-panel) {
  box-shadow: var(--shadow-sm);
}

.emp-feed__announce-row {
  display: flex;
  gap: var(--space-3);
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  padding-bottom: var(--space-1);
}

.emp-feed__announce-row :deep(.announce-panel) {
  flex: 0 0 min(88vw, 20rem);
  scroll-snap-align: start;
  max-height: none;
}

.emp-feed__announce-row :deep(.announce-panel__list) {
  max-height: 12rem;
}

.emp-feed__split {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin: var(--space-1) 0;
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--color-text-muted);
}

.emp-feed__split::before,
.emp-feed__split::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--color-border-subtle);
}

.emp-feed__post {
  scroll-margin-top: 4.5rem;
}

.emp-feed__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-6) var(--space-4);
  text-align: center;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.emp-feed__empty-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 3.25rem;
  height: 3.25rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-primary);
}

.emp-feed__empty p {
  margin: 0;
  font-size: 0.875rem;
  color: var(--color-text-muted);
  max-width: 18rem;
}

.emp-feed__sentinel {
  height: 1px;
}

.emp-feed__loading-more,
.emp-feed__end {
  margin: var(--space-2) 0 0;
  text-align: center;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.emp-feed__skeleton {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.emp-feed__skeleton-card {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.emp-feed__sk-row {
  height: 2.5rem;
  width: 55%;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
}

.emp-feed__sk-line {
  height: 0.75rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
}

.emp-feed__sk-line--short {
  width: 42%;
}
</style>

<style>
.emp-feed-sheet {
  position: fixed;
  inset: 0;
  z-index: 120;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  background: rgba(9, 36, 78, 0.35);
  backdrop-filter: blur(4px);
}

.emp-feed-sheet__panel {
  width: 100%;
  max-width: 32rem;
  max-height: min(70vh, 28rem);
  overflow: auto;
  padding: var(--space-4);
  padding-bottom: calc(var(--space-4) + env(safe-area-inset-bottom, 0px));
  border-radius: var(--radius-xl) var(--radius-xl) 0 0;
  background: var(--color-surface);
  box-shadow: 0 -8px 32px rgba(9, 36, 78, 0.12);
}

.emp-feed-sheet__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
}

.emp-feed-sheet__title {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
}

.emp-feed-sheet__close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  cursor: pointer;
}

.emp-feed-sheet__list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.emp-feed-sheet__option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  padding: var(--space-3) var(--space-2);
  border: none;
  border-bottom: 1px solid var(--color-border-subtle);
  background: transparent;
  font: inherit;
  font-size: 0.9375rem;
  text-align: left;
  cursor: pointer;
}

.emp-feed-sheet__option--active {
  color: var(--color-primary);
  font-weight: 600;
}

.emp-feed-sheet__hint {
  margin: var(--space-3) 0 0;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.emp-feed-sheet-enter-active,
.emp-feed-sheet-leave-active {
  transition: opacity 0.2s ease;
}

.emp-feed-sheet-enter-active .emp-feed-sheet__panel,
.emp-feed-sheet-leave-active .emp-feed-sheet__panel {
  transition: transform 0.25s ease;
}

.emp-feed-sheet-enter-from,
.emp-feed-sheet-leave-to {
  opacity: 0;
}

.emp-feed-sheet-enter-from .emp-feed-sheet__panel,
.emp-feed-sheet-leave-to .emp-feed-sheet__panel {
  transform: translateY(100%);
}
</style>
