//
// Logic bảng tin mobile (/dashboard/me/feed) — tái sử dụng API & quy tắc SocialFeed.
//
import { computed, inject, nextTick, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { searchUsers } from '@modules/Chat/resources/js/api/chatApi';

const FEED_ROUTE = 'employee.feed';

export function useEmployeeSocialFeed() {
  const auth = useAuthStore();
  const route = useRoute();
  const router = useRouter();

  const posts = ref([]);
  const loading = ref(false);
  const loadingMore = ref(false);
  const page = ref(1);
  const lastPage = ref(1);
  const searchQuery = inject('employeeFeedSearch', ref(''));
  const serverQuery = ref('');
  const peopleResults = ref([]);
  const peopleOpen = ref(false);
  const feedScope = ref('all');
  const postScope = ref('company');
  const wallUserId = ref(null);
  const wallProfile = ref(null);
  const focusedPostId = ref(null);
  const openCommentsPostId = ref(null);
  const suppressFeedReload = ref(false);
  const hashtagTotal = ref(0);
  let searchTimer = null;

  const activeHashtag = computed(() => {
    const raw = route.query.hashtag;
    const value = Array.isArray(raw) ? raw[0] : raw;
    if (typeof value !== 'string') return '';
    return value.trim().replace(/^#/, '').toLowerCase();
  });

  const activeHashtagLabel = computed(() => {
    const fromPost = posts.value
      .flatMap((post) => post.hashtags ?? [])
      .find((tag) => tag.name === activeHashtag.value);
    return fromPost?.label || activeHashtag.value;
  });

  const departmentName = computed(() => auth.user?.department?.name ?? '');
  const currentWallUserId = computed(() => wallUserId.value ?? auth.user?.id ?? null);
  const wallUserName = computed(
    () => wallProfile.value?.user?.name ?? auth.user?.name ?? '',
  );
  const viewingPersonalWall = computed(() => postScope.value === 'personal');
  const viewingOtherWall = computed(
    () => postScope.value === 'personal' && wallProfile.value?.is_own === false,
  );
  const visiblePosts = computed(() => posts.value);
  const canLoadMore = computed(
    () => page.value < lastPage.value && !serverQuery.value.trim(),
  );

  const firstUnpinnedIndex = computed(() =>
    visiblePosts.value.findIndex((post) => !post.is_pinned),
  );

  function belongsToCurrentScope(post) {
    if (postScope.value === 'personal') {
      const currentWall = currentWallUserId.value;
      if (
        post.post_scope !== 'personal'
        || post.wall_user?.id !== currentWall
      ) {
        return false;
      }
    } else if ((post.post_scope ?? 'company') !== postScope.value) {
      return false;
    }
    if (activeHashtag.value) {
      return (post.hashtags ?? []).some(
        (tag) => tag.name === activeHashtag.value,
      );
    }
    return true;
  }

  async function loadFeed(targetPage = 1) {
    const isFirstPage = targetPage === 1;
    if (isFirstPage) loading.value = true;
    else loadingMore.value = true;

    try {
      const params = {
        page: targetPage,
        per_page: 10,
        scope: feedScope.value,
        post_scope: postScope.value,
      };
      if (postScope.value === 'personal' && currentWallUserId.value) {
        params.wall_user_id = currentWallUserId.value;
      }
      if (activeHashtag.value) params.hashtag = activeHashtag.value;
      if (serverQuery.value) params.q = serverQuery.value;
      const { data } = await window.axios.get('/api/social/posts', { params });

      posts.value = isFirstPage
        ? data.posts
        : [...posts.value, ...data.posts];
      page.value = data.current_page;
      lastPage.value = data.last_page;
      hashtagTotal.value = data.total ?? 0;
    } catch {
      showClientToast('error', 'Không thể tải bảng tin.');
    } finally {
      loading.value = false;
      loadingMore.value = false;
    }
  }

  function wallIdFromQuery() {
    const raw = route.query.wall;
    const value = Array.isArray(raw) ? raw[0] : raw;
    const id = Number(value);
    return Number.isFinite(id) && id > 0 ? id : null;
  }

  function replaceWallQuery(userId) {
    const query = { ...route.query };
    const own = Number(auth.user?.id);
    const next = Number(userId);
    if (next && next !== own) {
      if (String(query.wall ?? '') === String(next)) return;
      query.wall = String(next);
    } else if (query.wall == null) {
      return;
    } else {
      delete query.wall;
    }
    router.replace({ name: FEED_ROUTE, query });
  }

  function onTabChange(tab) {
    if (tab === 'personal') {
      openPersonalWall(auth.user?.id);
      return;
    }
    if (postScope.value === tab && wallUserId.value === null) return;
    postScope.value = tab;
    wallUserId.value = null;
    wallProfile.value = null;
    replaceWallQuery(null);
  }

  async function loadWallProfile(userId) {
    try {
      const { data } = await window.axios.get(`/api/social/walls/${userId}`);
      wallProfile.value = data;
    } catch {
      wallProfile.value = null;
      showClientToast('error', 'Không thể mở tường cá nhân.');
    }
  }

  async function openPersonalWall(userId, options = {}) {
    if (!userId) return;
    const switching =
      postScope.value !== 'personal' || wallUserId.value !== userId;
    postScope.value = 'personal';
    wallUserId.value = userId;
    await loadWallProfile(userId);
    if (options.syncQuery !== false) replaceWallQuery(userId);
    if (!switching) {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  }

  function openColleagueWall(userId) {
    peopleOpen.value = false;
    searchQuery.value = '';
    serverQuery.value = '';
    peopleResults.value = [];
    openPersonalWall(userId);
  }

  function loadMore() {
    if (page.value < lastPage.value && !loadingMore.value && !loading.value) {
      loadFeed(page.value + 1);
    }
  }

  function onPosted(post) {
    if (belongsToCurrentScope(post)) {
      posts.value = [post, ...posts.value];
    }
  }

  function onUpdated(updatedPost) {
    posts.value = posts.value.map((p) =>
      (p.id === updatedPost.id ? updatedPost : p),
    );
  }

  function onShared(post) {
    if (belongsToCurrentScope(post)) {
      posts.value = [post, ...posts.value];
    }
  }

  function onDeleted(postId) {
    posts.value = posts.value.filter((p) => p.id !== postId);
  }

  function onPinned(updatedPost) {
    posts.value = posts.value
      .map((p) => (p.id === updatedPost.id ? updatedPost : p))
      .sort((a, b) => Number(b.is_pinned) - Number(a.is_pinned));
  }

  function onUnpinned(updatedPost) {
    onPinned(updatedPost);
  }

  async function scrollToPost(post) {
    const fullPost = post && typeof post === 'object' ? post : null;
    const postId = fullPost?.id ?? post;
    if (!postId) return;

    if (
      fullPost?.pin_scope === 'system'
      && (postScope.value !== 'company' || wallUserId.value !== null)
    ) {
      suppressFeedReload.value = true;
      postScope.value = 'company';
      wallUserId.value = null;
      wallProfile.value = null;
      await loadFeed(1);
    }

    await nextTick();
    let el = document.getElementById(`social-post-${postId}`);
    if (!el && fullPost && !posts.value.some((item) => item.id === postId)) {
      posts.value = [fullPost, ...posts.value];
      await nextTick();
      el = document.getElementById(`social-post-${postId}`);
    }
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  async function applyFocusedPost() {
    const postId = Number(route.query.post);
    if (!Number.isFinite(postId) || postId < 1) {
      focusedPostId.value = null;
      openCommentsPostId.value = null;
      return;
    }

    focusedPostId.value = postId;
    openCommentsPostId.value = postId;

    try {
      const { data } = await window.axios.get(`/api/social/posts/${postId}`);
      const post = data.post;
      if (!post) return;

      let switched = false;
      if (post.post_scope === 'personal' && post.wall_user?.id) {
        if (
          postScope.value !== 'personal'
          || wallUserId.value !== post.wall_user.id
        ) {
          suppressFeedReload.value = true;
          postScope.value = 'personal';
          wallUserId.value = post.wall_user.id;
          await loadWallProfile(post.wall_user.id);
          switched = true;
        }
      } else if (post.post_scope === 'department') {
        if (postScope.value !== 'department' || wallUserId.value !== null) {
          suppressFeedReload.value = true;
          postScope.value = 'department';
          wallUserId.value = null;
          wallProfile.value = null;
          switched = true;
        }
      } else if (postScope.value !== 'company' || wallUserId.value !== null) {
        suppressFeedReload.value = true;
        postScope.value = 'company';
        wallUserId.value = null;
        wallProfile.value = null;
        switched = true;
      }

      if (switched) {
        await loadFeed(1);
      }

      if (!posts.value.some((item) => item.id === post.id)) {
        posts.value = [post, ...posts.value];
      }
      await scrollToPost(post);
    } catch {
      showClientToast('error', 'Không tìm thấy bài viết được nhắc.');
    }
  }

  function setActiveHashtag(name) {
    const next = String(name || '')
      .replace(/^#/, '')
      .trim()
      .toLowerCase();
    const query = { ...route.query };
    if (next && next !== activeHashtag.value) {
      query.hashtag = next;
    } else {
      delete query.hashtag;
    }
    delete query.post;
    delete query.comment;
    router.replace({ name: FEED_ROUTE, query });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function onOpenHashtag(name) {
    setActiveHashtag(name);
  }

  function openWall(userId) {
    if (!userId) return;
    openPersonalWall(userId);
  }

  watch(searchQuery, (value) => {
    clearTimeout(searchTimer);
    const trimmed = value.trim();
    searchTimer = window.setTimeout(async () => {
      const next = trimmed.length >= 2 ? trimmed : '';
      if (next !== serverQuery.value) {
        serverQuery.value = next;
        loadFeed(1);
      }
      if (trimmed.length < 2) {
        peopleResults.value = [];
        peopleOpen.value = false;
        return;
      }
      try {
        peopleResults.value = await searchUsers(trimmed);
        peopleOpen.value = true;
      } catch {
        peopleResults.value = [];
      }
    }, 300);
  });

  watch(activeHashtag, () => loadFeed(1));
  watch([postScope, wallUserId], () => {
    if (suppressFeedReload.value) {
      suppressFeedReload.value = false;
      return;
    }
    loadFeed(1);
  });

  watch(
    () => [route.query.post, route.query.comment],
    () => applyFocusedPost(),
  );

  watch(
    () => route.query.wall,
    (value) => {
      const id = wallIdFromQuery();
      if (!id) return;
      if (postScope.value === 'personal' && Number(wallUserId.value) === id) return;
      if (value == null) return;
      openPersonalWall(id, { syncQuery: false });
    },
  );

  async function initFeed() {
    const wall = wallIdFromQuery();
    if (wall) {
      suppressFeedReload.value = true;
      postScope.value = 'personal';
      wallUserId.value = wall;
      await loadWallProfile(wall);
    }
    await loadFeed(1);
    await applyFocusedPost();
  }

  async function refreshFeed(panels = {}) {
    const tasks = [loadFeed(1)];
    if (panels.pinnedSystem?.load) tasks.push(panels.pinnedSystem.load());
    if (panels.pinnedCompany?.load) tasks.push(panels.pinnedCompany.load());
    if (panels.hashtagPanel?.load) tasks.push(panels.hashtagPanel.load());
    if (postScope.value === 'personal' && currentWallUserId.value) {
      tasks.push(loadWallProfile(currentWallUserId.value));
    }
    await Promise.all(tasks);
  }

  return {
    auth,
    posts,
    loading,
    loadingMore,
    page,
    lastPage,
    searchQuery,
    peopleResults,
    peopleOpen,
    feedScope,
    postScope,
    wallUserId,
    wallProfile,
    focusedPostId,
    openCommentsPostId,
    hashtagTotal,
    activeHashtag,
    activeHashtagLabel,
    departmentName,
    currentWallUserId,
    wallUserName,
    viewingPersonalWall,
    viewingOtherWall,
    visiblePosts,
    canLoadMore,
    firstUnpinnedIndex,
    loadFeed,
    loadMore,
    onTabChange,
    openPersonalWall,
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
  };
}
