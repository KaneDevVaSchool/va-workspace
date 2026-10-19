<script setup>
//
// Bảng tin mobile — 1 cột, dùng SocialPostCard (HTML an toàn, reaction, bình luận,
// chia sẻ) giống /social, không duplicate logic feed đơn giản cũ (chỉ xem + text thô).
//
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import SocialPostCard from '@modules/Social/resources/js/components/SocialPostCard.vue';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const posts = ref([]);
const loading = ref(true);
const loadingMore = ref(false);
const page = ref(1);
const hasMore = ref(true);
const focusedPostId = ref(null);
const openCommentsPostId = ref(null);

const departmentName = computed(() => auth.user?.department?.name ?? '');

async function loadPosts({ append = false } = {}) {
  if (append) loadingMore.value = true;
  else loading.value = true;
  try {
    const { data } = await window.axios.get('/api/social/posts', {
      params: {
        page: page.value,
        per_page: 10,
        scope: 'all',
        post_scope: 'company',
      },
    });
    const items = data?.posts ?? [];
    posts.value = append ? [...posts.value, ...items] : items;
    const currentPage = Number(data?.current_page) || 1;
    const lastPage = Number(data?.last_page) || 1;
    hasMore.value = currentPage < lastPage;
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được bảng tin.');
  } finally {
    loading.value = false;
    loadingMore.value = false;
  }
}

function loadMore() {
  if (loadingMore.value || !hasMore.value) return;
  page.value += 1;
  loadPosts({ append: true });
}

function onUpdated(updatedPost) {
  posts.value = posts.value.map((p) => (p.id === updatedPost.id ? updatedPost : p));
}

function onShared(post) {
  if ((post.post_scope ?? 'company') === 'company') {
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

function openWall(userId) {
  if (!userId) return;
  router.push({ name: 'social.feed', query: { wall: String(userId) } });
}

function onOpenHashtag(tag) {
  if (!tag) return;
  router.push({ name: 'social.feed', query: { hashtag: tag.replace(/^#/, '') } });
}

async function scrollToPost(post) {
  const fullPost = post && typeof post === 'object' ? post : null;
  const postId = fullPost?.id ?? post;
  if (!postId) return;

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

    if (!posts.value.some((item) => item.id === post.id)) {
      posts.value = [post, ...posts.value];
    }
    await scrollToPost(post);
  } catch {
    showClientToast('error', 'Không tìm thấy bài viết được nhắc.');
  }
}

watch(
  () => route.query.post,
  () => applyFocusedPost(),
);

onMounted(async () => {
  await loadPosts();
  await applyFocusedPost();
});
</script>

<template>
  <div class="feed">
    <div v-if="loading" class="feed__skeleton">
      <div v-for="n in 3" :key="n" class="feed-card feed-card--skeleton">
        <div class="feed-card__skeleton-row"></div>
        <div class="feed-card__skeleton-line"></div>
        <div class="feed-card__skeleton-line feed-card__skeleton-line--short"></div>
      </div>
    </div>

    <template v-else>
      <div
        v-for="post in posts"
        :id="`social-post-${post.id}`"
        :key="post.id"
        class="feed__post-anchor"
      >
        <SocialPostCard
          :post="post"
          post-scope="company"
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

      <p v-if="!posts.length" class="feed__empty">Chưa có bài đăng nào trên bảng tin.</p>

      <button
        v-if="hasMore && posts.length"
        type="button"
        class="feed__more"
        :disabled="loadingMore"
        @click="loadMore"
      >
        {{ loadingMore ? 'Đang tải...' : 'Xem thêm' }}
      </button>
    </template>
  </div>
</template>

<style scoped>
.feed {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.feed__post-anchor {
  scroll-margin-top: var(--space-3);
}

.feed__empty {
  margin: var(--space-4) 0;
  text-align: center;
  font-size: 0.875rem;
  color: var(--color-text-muted);
}

.feed__more {
  align-self: center;
  margin-top: var(--space-1);
  padding: var(--space-2) var(--space-4);
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.feed__more:disabled {
  opacity: 0.6;
  cursor: default;
}

.feed__skeleton {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.feed-card--skeleton {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.feed-card__skeleton-row {
  height: 2.5rem;
  border-radius: var(--radius-full);
  width: 60%;
  background: var(--color-surface-muted);
}

.feed-card__skeleton-line {
  height: 0.75rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
}

.feed-card__skeleton-line--short {
  width: 40%;
}
</style>
