<script setup>
//
// Bảng tin — bản mobile đơn giản, 1 cột, chỉ xem (chưa đăng bài/bình luận
// từ đây). Gọi thẳng API thật của module Social (GET /api/social/posts) —
// khác với 4 trang kia (chấm công/nghỉ phép/đơn/trang chủ) vẫn đang mock,
// vì dữ liệu bảng tin thật đã có sẵn và không có rủi ro logic nghiệp vụ mới.
// Không tái dùng SocialFeed.vue (3 cột, composer, poll...) vì nó là layout
// desktop, nhét vào shell mobile sẽ vỡ bố cục.
//
import { onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { formatSocialTime } from '@modules/Social/resources/js/lib/formatSocialTime.js';

const posts = ref([]);
const loading = ref(true);
const loadingMore = ref(false);
const page = ref(1);
const hasMore = ref(true);

function reactionTotal(reactions) {
  if (!reactions || typeof reactions !== 'object') return 0;
  return Object.values(reactions).reduce((sum, n) => sum + (Number(n) || 0), 0);
}

function firstImage(attachments) {
  return (attachments ?? []).find((item) => item.type === 'image') ?? null;
}

async function loadPosts({ append = false } = {}) {
  if (append) loadingMore.value = true; else loading.value = true;
  try {
    const { data } = await window.axios.get('/api/social/posts', {
      params: { page: page.value, per_page: 10, scope: 'all' },
    });
    const items = data?.data ?? [];
    posts.value = append ? [...posts.value, ...items] : items;
    hasMore.value = Boolean(data?.next_page_url ?? (data?.current_page < data?.last_page));
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

onMounted(() => loadPosts());
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
      <article v-for="post in posts" :key="post.id" class="feed-card">
        <header class="feed-card__head">
          <div class="feed-card__avatar" aria-hidden="true">
            <img v-if="post.author?.avatar_url" :src="post.author.avatar_url" :alt="`Ảnh đại diện của ${post.author.name}`" />
            <span v-else>{{ post.author?.name?.charAt(0) ?? '?' }}</span>
          </div>
          <div class="feed-card__who">
            <p class="feed-card__name">{{ post.author?.name ?? 'Thông báo hệ thống' }}</p>
            <p class="feed-card__meta">
              <span v-if="post.author?.department">{{ post.author.department }} · </span>
              <time :datetime="post.created_at">{{ formatSocialTime(post.created_at) }}</time>
            </p>
          </div>
        </header>

        <p v-if="post.content" class="feed-card__content">{{ post.content }}</p>

        <div v-if="firstImage(post.attachments)" class="feed-card__image">
          <img :src="firstImage(post.attachments).url" alt="" loading="lazy" />
        </div>

        <footer class="feed-card__footer">
          <span class="feed-card__stat">
            <AppIcon name="heart" :size="14" />
            {{ reactionTotal(post.reactions) }}
          </span>
          <span class="feed-card__stat">
            <AppIcon name="messageCircle" :size="14" />
            {{ post.comments_count ?? 0 }}
          </span>
        </footer>
      </article>

      <p v-if="!posts.length" class="feed__empty">Chưa có bài đăng nào trên bảng tin.</p>

      <button v-if="hasMore && posts.length" type="button" class="feed__more" :disabled="loadingMore" @click="loadMore">
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

.feed-card {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.feed-card__head {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.feed-card__avatar {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-full);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 0.9375rem;
  font-weight: 700;
  overflow: hidden;
}

.feed-card__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.feed-card__who {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.feed-card__name {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--color-text);
}

.feed-card__meta {
  margin: 0;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.feed-card__content {
  margin: 0;
  font-size: 0.875rem;
  line-height: 1.5;
  color: var(--color-text);
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.feed-card__image {
  border-radius: var(--radius-md);
  overflow: hidden;
  background: var(--color-surface-muted);
}

.feed-card__image img {
  display: block;
  width: 100%;
  max-height: 16rem;
  object-fit: cover;
}

.feed-card__footer {
  display: flex;
  align-items: center;
  gap: var(--space-4);
  padding-top: var(--space-1);
}

.feed-card__stat {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.feed-card__stat :deep(svg) {
  color: var(--color-text-muted);
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
  gap: var(--space-3);
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
