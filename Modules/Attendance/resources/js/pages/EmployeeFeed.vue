<script setup>
//
// Bảng tin mobile — đầy đủ tính năng SocialFeed (đăng bài, tường, hashtag,
// ghim, reaction, bình luận) với UX kiểu Facebook.
//
import {
  computed,
  inject,
  nextTick,
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
const colleagueSearchRef = ref(null);
const loadMoreSentinel = ref(null);
let unregisterRefresh;
let loadMoreObserver;

const wallSegments = computed(() => {
  const items = [{ id: 'company', label: 'Bảng tin' }];
  if (departmentName.value) {
    items.push({ id: 'department', label: departmentName.value });
  }
  items.push({ id: 'personal', label: 'Tường của tôi' });
  return items;
});

const showScopeSubtabs = computed(
  () => !viewingOtherWall.value && postScope.value === 'company',
);

const showFilterSheetTrigger = computed(
  () => !viewingOtherWall.value && postScope.value !== 'company',
);

function closeSheets() {
  filterSheetOpen.value = false;
  colleagueSheetOpen.value = false;
}

function userInitial(name) {
  return name?.trim().charAt(0).toUpperCase() || '?';
}

function userDepartment(user) {
  const dept = user?.department;
  if (!dept) return '';
  if (typeof dept === 'string') return dept.trim();
  if (typeof dept === 'object' && dept.name) return String(dept.name).trim();
  return '';
}

function userCompany(user) {
  const name = user?.company_name;
  return typeof name === 'string' ? name.trim() : '';
}

watch(colleagueSheetOpen, (open) => {
  if (open) {
    nextTick(() => colleagueSearchRef.value?.focus());
  }
});

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
    <div class="emp-feed__top">
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
          v-if="showFilterSheetTrigger"
          type="button"
          class="emp-feed__icon-btn"
          :aria-expanded="filterSheetOpen"
          aria-label="Lọc bài viết"
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
          <img
            v-if="user.avatar_url"
            class="emp-feed__people-avatar"
            :src="user.avatar_url"
            alt=""
          />
          <span v-else class="emp-feed__people-avatar emp-feed__people-avatar--placeholder">
            {{ userInitial(user.name) }}
          </span>
          <span class="emp-feed__people-copy">
            <span class="emp-feed__people-name">{{ user.name }}</span>
            <span v-if="userDepartment(user)" class="emp-feed__people-dept">
              {{ userDepartment(user) }}
            </span>
            <span v-if="userCompany(user)" class="emp-feed__people-company">
              {{ userCompany(user) }}
            </span>
          </span>
          <AppIcon name="chevronRight" :size="16" class="emp-feed__people-chevron" aria-hidden="true" />
        </button>
      </div>

      <section class="emp-feed__nav-card" aria-label="Chọn tường và bộ lọc">
        <div v-if="viewingOtherWall" class="emp-feed__wall-visit">
          <button
            type="button"
            class="emp-feed__wall-back"
            aria-label="Quay lại bảng tin"
            @click="onTabChange('company')"
          >
            <AppIcon name="chevronLeft" :size="20" :stroke-width="2" />
          </button>
          <div class="emp-feed__wall-visit-text">
            <p class="emp-feed__wall-visit-kicker">Tường cá nhân</p>
            <p class="emp-feed__wall-visit-name">{{ wallUserName }}</p>
          </div>
        </div>

        <template v-else>
          <div class="emp-feed__segmented hide-scrollbar" role="tablist" aria-label="Chọn tường">
            <button
              v-for="segment in wallSegments"
              :key="segment.id"
              type="button"
              role="tab"
              class="emp-feed__segment"
              :class="{
                'emp-feed__segment--active':
                  postScope === segment.id
                  && (segment.id !== 'personal' || !viewingOtherWall),
              }"
              :aria-selected="postScope === segment.id"
              @click="onTabChange(segment.id)"
            >
              {{ segment.label }}
            </button>
          </div>

          <div
            v-if="showScopeSubtabs"
            class="emp-feed__subtabs"
            role="tablist"
            aria-label="Lọc nội dung bảng tin"
          >
            <button
              v-for="item in feedScopes"
              :key="item.id"
              type="button"
              role="tab"
              class="emp-feed__subtab"
              :class="{ 'emp-feed__subtab--active': feedScope === item.id }"
              :aria-selected="feedScope === item.id"
              @click="onScopeChange(item.id)"
            >
              {{ item.label }}
            </button>
          </div>
        </template>
      </section>
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
            mobile-feed-layout
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
          <div
            class="emp-feed-sheet__panel emp-feed-sheet__panel--colleague"
            role="dialog"
            aria-modal="true"
            aria-label="Tìm đồng nghiệp"
          >
            <header class="emp-feed-sheet__head">
              <div class="emp-feed-sheet__head-copy">
                <h2 class="emp-feed-sheet__title">Tìm đồng nghiệp</h2>
                <p class="emp-feed-sheet__subtitle">Chọn người để xem tường cá nhân</p>
              </div>
              <button type="button" class="emp-feed-sheet__close" aria-label="Đóng" @click="closeSheets">
                <AppIcon name="close" :size="20" />
              </button>
            </header>
            <SocialColleagueSearch
              ref="colleagueSearchRef"
              layout="panel"
              @select="(id) => { openColleagueWall(id); closeSheets(); }"
            />
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

.emp-feed__top {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding-bottom: var(--space-1);
}

.emp-feed__toolbar {
  display: flex;
  align-items: center;
  gap: var(--space-2);
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
  align-items: center;
  gap: var(--space-2);
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

.emp-feed__people-avatar {
  flex-shrink: 0;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-full);
  object-fit: cover;
}

.emp-feed__people-avatar--placeholder {
  display: grid;
  place-items: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 0.8125rem;
  font-weight: 700;
}

.emp-feed__people-copy {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  flex: 1;
}

.emp-feed__people-name {
  font-size: 0.875rem;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.emp-feed__people-dept {
  font-size: 0.75rem;
  color: var(--color-text-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.emp-feed__people-company {
  font-size: 0.6875rem;
  color: var(--color-text-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.emp-feed__people-chevron {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.emp-feed__nav-card {
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.emp-feed__segmented {
  display: flex;
  gap: 3px;
  margin: var(--space-2);
  padding: 3px;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  overflow-x: auto;
}

.emp-feed__segment {
  flex: 1 1 0;
  min-width: 4.5rem;
  padding: var(--space-2) var(--space-2);
  border: none;
  border-radius: calc(var(--radius-md) - 2px);
  background: transparent;
  font: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.2;
  color: var(--color-text-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  cursor: pointer;
  transition: background 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
}

.emp-feed__segment--active {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: 0 1px 4px rgba(9, 36, 78, 0.1);
}

.emp-feed__subtabs {
  display: flex;
  border-top: 1px solid var(--color-border-subtle);
}

.emp-feed__subtab {
  position: relative;
  flex: 1;
  min-width: 0;
  padding: var(--space-2) var(--space-1) calc(var(--space-2) + 2px);
  border: none;
  background: transparent;
  font: inherit;
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--color-text-muted);
  cursor: pointer;
  transition: color 0.15s ease;
}

.emp-feed__subtab--active {
  color: var(--color-tertiary);
  font-weight: 600;
}

.emp-feed__subtab--active::after {
  content: '';
  position: absolute;
  left: 12%;
  right: 12%;
  bottom: 0;
  height: 2px;
  border-radius: 2px 2px 0 0;
  background: var(--color-tertiary);
}

.emp-feed__wall-visit {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3);
}

.emp-feed__wall-back {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 2.25rem;
  height: 2.25rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text);
  cursor: pointer;
}

.emp-feed__wall-visit-text {
  min-width: 0;
}

.emp-feed__wall-visit-kicker {
  margin: 0;
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.emp-feed__wall-visit-name {
  margin: 2px 0 0;
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--color-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
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
  font-size: 0.75rem;
}

.emp-feed__hashtag-clear {
  margin-left: auto;
  border: none;
  background: transparent;
  font: inherit;
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--color-tertiary);
  cursor: pointer;
}

/* Hashtag — chip cuộn ngang */
.emp-feed__side-panels {
  margin-inline: calc(-1 * var(--space-1));
  padding-inline: var(--space-1);
}

.emp-feed__side-panels :deep(.hashtag-panel) {
  box-shadow: var(--shadow-sm);
  padding: var(--space-2) var(--space-2) var(--space-2) calc(var(--space-2) + 3px + var(--space-1));
  gap: var(--space-1);
}

.emp-feed__side-panels :deep(.hashtag-panel__title) {
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.emp-feed__side-panels :deep(.hashtag-panel__list) {
  flex-direction: row;
  flex-wrap: nowrap;
  gap: var(--space-2);
  overflow-x: auto;
  padding-bottom: 2px;
  scroll-snap-type: x proximity;
  -webkit-overflow-scrolling: touch;
}

.emp-feed__side-panels :deep(.hashtag-panel__item) {
  flex: 0 0 auto;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  min-width: 6.5rem;
  max-width: 9rem;
  padding: var(--space-2);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  scroll-snap-align: start;
}

.emp-feed__side-panels :deep(.hashtag-panel__item--active) {
  background: color-mix(in srgb, var(--color-tertiary) 12%, var(--color-surface-muted));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-tertiary) 28%, transparent);
}

.emp-feed__side-panels :deep(.hashtag-panel__name) {
  font-size: 0.6875rem;
  font-weight: 700;
}

.emp-feed__side-panels :deep(.hashtag-panel__usage) {
  font-size: 0.625rem;
}

/* Thông báo — panel + bài cuộn ngang */
.emp-feed__announce-row {
  display: flex;
  gap: var(--space-2);
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  margin-inline: calc(-1 * var(--space-1));
  padding: 0 var(--space-1) var(--space-1);
  -webkit-overflow-scrolling: touch;
}

.emp-feed__announce-row :deep(.announce-panel) {
  flex: 0 0 min(92vw, 17.5rem);
  scroll-snap-align: start;
  max-height: none;
  padding: var(--space-2) var(--space-2) var(--space-2) calc(var(--space-2) + 3px + var(--space-1));
  gap: var(--space-1);
}

.emp-feed__announce-row :deep(.announce-panel__title) {
  font-size: 0.6875rem;
}

.emp-feed__announce-row :deep(.announce-panel__icon) {
  width: 1.375rem;
  height: 1.375rem;
}

.emp-feed__announce-row :deep(.announce-panel__count) {
  min-width: 1.125rem;
  height: 1.125rem;
  font-size: 0.625rem;
}

.emp-feed__announce-row :deep(.announce-panel__empty) {
  font-size: 0.6875rem;
  line-height: 1.4;
}

.emp-feed__announce-row :deep(.announce-panel__list) {
  flex-direction: row;
  flex-wrap: nowrap;
  gap: var(--space-2);
  max-height: none;
  overflow-x: auto;
  overflow-y: hidden;
  padding-bottom: 2px;
  scroll-snap-type: x proximity;
}

.emp-feed__announce-row :deep(.announce-panel__item) {
  flex: 0 0 min(74vw, 13.5rem);
  scroll-snap-align: start;
  padding: var(--space-2);
  gap: 0.25rem;
}

.emp-feed__announce-row :deep(.announce-panel__item-heading) {
  font-size: 0.6875rem;
  line-height: 1.35;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  max-height: calc(1.35em * 2);
}

.emp-feed__announce-row :deep(.announce-panel__item-excerpt) {
  font-size: 0.625rem;
  line-height: 1.4;
  -webkit-line-clamp: 3;
  line-clamp: 3;
  max-height: calc(1.4em * 3);
}

.emp-feed__announce-row :deep(.announce-panel__item-meta) {
  font-size: 0.5625rem;
  padding-top: var(--space-1);
  margin-top: 0;
}

/* Bài viết — stream gọn, thẻ kiểu feed mobile */
.emp-feed__stream {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding-top: var(--space-1);
}

.emp-feed__post :deep(.post-card) {
  gap: 0;
  padding: var(--space-3);
  border: none;
  border-radius: var(--radius-lg);
  box-shadow:
    0 1px 2px rgba(9, 36, 78, 0.05),
    0 4px 14px rgba(9, 36, 78, 0.06);
  overflow: hidden;
}

.emp-feed__post :deep(.post-card--pinned) {
  padding-left: calc(var(--space-3) + var(--space-1) + 3px);
  box-shadow:
    0 2px 4px rgba(9, 36, 78, 0.06),
    0 6px 18px rgba(9, 36, 78, 0.08);
}

.emp-feed__post :deep(.post-card--focused) {
  box-shadow:
    0 0 0 2px color-mix(in srgb, var(--color-tertiary) 40%, transparent),
    0 4px 14px rgba(9, 36, 78, 0.08);
}

.emp-feed__post :deep(.post-card__pinned-badge) {
  margin-bottom: var(--space-2);
  padding: var(--space-1) var(--space-2);
  gap: var(--space-1);
  border-radius: var(--radius-sm);
}

.emp-feed__post :deep(.post-card__pinned-icon) {
  width: 1.25rem;
  height: 1.25rem;
}

.emp-feed__post :deep(.post-card__pinned-label) {
  font-size: 0.6875rem;
  line-height: 1.25;
}

.emp-feed__post :deep(.post-card__pinned-by) {
  font-size: 0.5625rem;
  line-height: 1.3;
}

.emp-feed__post :deep(.post-card__header) {
  padding-bottom: var(--space-2);
  border-bottom: 1px solid var(--color-border-subtle);
  margin-bottom: var(--space-2);
}

.emp-feed__post :deep(.post-card__avatar) {
  width: 2.25rem;
  height: 2.25rem;
  box-shadow: inset 0 0 0 2px color-mix(in srgb, var(--color-tertiary-300) 55%, var(--color-border));
}

.emp-feed__post :deep(.post-card__author-name) {
  font-size: 0.8125rem;
  line-height: 1.25;
  font-weight: 600;
}

.emp-feed__post :deep(.post-card__meta) {
  font-size: 0.6875rem;
  line-height: 1.3;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.emp-feed__post :deep(.post-card__meta--visibility) {
  font-size: 0.625rem;
}

.emp-feed__post :deep(.post-card__icon-btn) {
  width: 1.75rem;
  height: 1.75rem;
  display: grid;
  place-items: center;
  padding: 0;
  color: var(--color-text-muted);
}

.emp-feed__post :deep(.post-card__time),
.emp-feed__post :deep(.post-card__today) {
  font-size: 0.625rem;
  font-weight: 500;
  color: var(--color-text-muted);
}

.emp-feed__post :deep(.post-card__edited) {
  font-size: 0.5625rem;
  font-weight: 600;
}

.emp-feed__post :deep(.post-card__stream-hero) {
  margin-inline: calc(-1 * var(--space-3));
}

.emp-feed__post :deep(.post-card__content-block) {
  width: 100%;
}

.emp-feed__post :deep(.post-card__read-more) {
  color: var(--color-tertiary-700);
}

.emp-feed__post :deep(.post-card__content) {
  padding-bottom: 0;
}

.emp-feed__post :deep(.post-card__content),
.emp-feed__post :deep(.post-card__content p),
.emp-feed__post :deep(.post-card__shared-content) {
  font-size: 0.8125rem;
  line-height: 1.5;
}

.emp-feed__post :deep(.post-card__content p),
.emp-feed__post :deep(.post-card__shared-content p) {
  margin: 0 0 0.35rem;
}

.emp-feed__post :deep(.post-card__content p:last-child),
.emp-feed__post :deep(.post-card__shared-content p:last-child) {
  margin-bottom: 0;
}

.emp-feed__post :deep(.post-card__images:not(.post-card__stream-hero)) {
  margin: 0 0 var(--space-2);
  border-radius: var(--radius-md);
  overflow: hidden;
}

.emp-feed__post :deep(.post-card__shared .post-card__stream-hero) {
  margin: var(--space-2) calc(-1 * var(--space-2)) var(--space-1);
  border-radius: var(--radius-sm);
  overflow: hidden;
}

.emp-feed__post :deep(.post-card__attachments),
.emp-feed__post :deep(.post-card__link-previews) {
  margin-bottom: var(--space-2);
}

.emp-feed__post :deep(.post-card__shared) {
  margin-bottom: var(--space-2);
  padding: var(--space-2);
  border-radius: var(--radius-sm);
}

.emp-feed__post :deep(.post-card__hashtags) {
  flex-wrap: nowrap;
  gap: 0.35rem;
  overflow-x: auto;
  padding-bottom: var(--space-2);
  -webkit-overflow-scrolling: touch;
}

.emp-feed__post :deep(.post-card__hashtag-chip) {
  flex-shrink: 0;
  font-size: 0.625rem;
  font-weight: 600;
  padding: 0.15rem 0.5rem;
}

.emp-feed__post :deep(.post-card__stats) {
  font-size: 0.625rem;
  margin-inline: calc(-1 * var(--space-3));
  padding: var(--space-2) var(--space-3);
  gap: var(--space-2);
  box-shadow: none;
  border-top: 1px solid var(--color-border-subtle);
  background: color-mix(in srgb, var(--color-surface-muted) 45%, var(--color-surface));
}

.emp-feed__post :deep(.post-card__reaction-summary) {
  gap: 0.25rem;
  min-width: 0;
  flex-wrap: wrap;
}

.emp-feed__post :deep(.post-card__reaction-chip) {
  font-size: 0.5625rem;
  font-weight: 600;
  padding: 0.08rem 0.35rem 0.08rem 0.2rem;
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border-subtle);
}

.emp-feed__post :deep(.post-card__reaction-emoji) {
  font-size: 0.6875rem;
}

.emp-feed__post :deep(.post-card__stats-meta) {
  gap: var(--space-2);
  font-size: 0.5625rem;
  font-weight: 500;
}

.emp-feed__post :deep(.post-card__views) {
  gap: 0.2rem;
}

.emp-feed__post :deep(.post-card__actions) {
  gap: 0;
  margin-inline: calc(-1 * var(--space-3));
  padding: 0 var(--space-1);
  border-top: 1px solid var(--color-border-subtle);
  background: var(--color-surface);
}

.emp-feed__post :deep(.post-card__action-btn) {
  font-size: 0.6875rem;
  font-weight: 600;
  padding: var(--space-2) 0.15rem;
  gap: 0.3rem;
  border-radius: 0;
  min-height: 2.25rem;
}

.emp-feed__post :deep(.post-card__action-btn:hover) {
  background: var(--color-surface-muted);
}

.emp-feed__post :deep(.post-card__action-btn .app-icon) {
  width: 1rem;
  height: 1rem;
}

.emp-feed__post :deep(.post-card__reaction-current-emoji) {
  font-size: 0.875rem;
}

.emp-feed__post :deep(.post-card__stats + .post-card__actions) {
  border-top: none;
}

.emp-feed__post :deep(.post-card:not(:has(.post-card__stats)) .post-card__actions) {
  margin-top: var(--space-1);
}

.emp-feed__split {
  display: flex;
  align-items: center;
  justify-content: center;
  width: fit-content;
  max-width: 100%;
  margin: var(--space-2) auto var(--space-1);
  padding: 0.35rem var(--space-3);
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-surface-muted) 70%, var(--color-surface));
  box-shadow: inset 0 0 0 1px var(--color-border-subtle);
  font-size: 0.625rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-text-muted);
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
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
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

.emp-feed-sheet__panel--colleague {
  display: flex;
  flex-direction: column;
  max-height: min(82vh, 36rem);
  overflow: hidden;
}

.emp-feed-sheet__panel--colleague .emp-feed-sheet__head {
  flex-shrink: 0;
}

.emp-feed-sheet__panel--colleague :deep(.colleague-search) {
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.emp-feed-sheet__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
}

.emp-feed-sheet__head-copy {
  min-width: 0;
}

.emp-feed-sheet__title {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
}

.emp-feed-sheet__subtitle {
  margin: 0.2rem 0 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
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
  color: var(--color-tertiary);
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
