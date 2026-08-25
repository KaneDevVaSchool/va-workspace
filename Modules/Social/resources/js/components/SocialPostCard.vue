<script setup>
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { REACTIONS, reactionByType } from '../constants/reactions.js';
import { formatSocialTime } from '../lib/formatSocialTime.js';
import { applyOptimistic, cloneReactions, useReactionAction } from '../lib/useReactionAction.js';
import { sanitizeSocialHtml } from '../lib/sanitizeSocialHtml.js';
import SocialCommentList from './SocialCommentList.vue';
import SocialReactionBursts from './SocialReactionBursts.vue';
import SocialReactionPicker from './SocialReactionPicker.vue';

const props = defineProps({
  post: { type: Object, required: true },
});

const sanitizedContent = computed(() => sanitizeSocialHtml(props.post.content));
const sanitizedSharedContent = computed(() => sanitizeSocialHtml(props.post.shared_from?.content));

const emit = defineEmits(['deleted', 'pinned', 'unpinned', 'shared']);

const showComments = ref(false);
const sharing = ref(false);
const localReactions = ref(cloneReactions(props.post.reactions));
const localMyReaction = ref(props.post.my_reaction);
const localCommentsCount = ref(props.post.comments_count);
const { popping, bursts, playFeedback, nextGen, isLatest } = useReactionAction();

const topReactions = computed(() =>
  REACTIONS
    .map((r) => ({ ...r, count: localReactions.value[r.type] ?? 0 }))
    .filter((r) => r.count > 0)
    .sort((a, b) => b.count - a.count),
);

const myReactionInfo = computed(() => reactionByType(localMyReaction.value));

async function setReaction(type, event) {
  const snapshot = {
    reactions: cloneReactions(localReactions.value),
    my: localMyReaction.value,
  };
  const removing = snapshot.my === type;

  applyOptimistic(localReactions, localMyReaction, type);
  playFeedback(type, event, removing);

  const gen = nextGen();
  try {
    const { data } = await window.axios.post(`/api/social/posts/${props.post.id}/reactions`, { type });
    if (!isLatest(gen)) return;
    localReactions.value = cloneReactions(data.reactions);
    localMyReaction.value = data.my_reaction;
  } catch (error) {
    if (!isLatest(gen)) return;
    localReactions.value = snapshot.reactions;
    localMyReaction.value = snapshot.my;
    showClientToast('error', error?.response?.data?.message ?? 'Không thể thực hiện thao tác.');
  }
}

function onLikeButtonClick(event) {
  setReaction(localMyReaction.value ?? 'like', event);
}

async function share() {
  if (sharing.value) return;
  sharing.value = true;
  try {
    const { data } = await window.axios.post(`/api/social/posts/${props.post.id}/share`, {});
    emit('shared', data.post);
    showClientToast('success', 'Đã chia sẻ bài viết lên bảng tin của bạn.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không thể chia sẻ bài viết.');
  } finally {
    sharing.value = false;
  }
}

async function remove() {
  try {
    await window.axios.delete(`/api/social/posts/${props.post.id}`);
    emit('deleted', props.post.id);
    showClientToast('success', 'Đã xoá bài viết.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không thể xoá bài viết.');
  }
}

async function togglePin() {
  try {
    if (props.post.is_pinned) {
      const { data } = await window.axios.delete(`/api/social/posts/${props.post.id}/pin`);
      emit('unpinned', data.post);
    } else {
      const { data } = await window.axios.post(`/api/social/posts/${props.post.id}/pin`);
      emit('pinned', data.post);
    }
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không thể thực hiện thao tác.');
  }
}

function onCommentsCountChanged(count) {
  localCommentsCount.value = count;
}

onBeforeUnmount(() => {
  clearTimeout(popTimer);
});
</script>

<template>
  <article class="post-card">
    <div v-if="post.is_pinned" class="post-card__pinned-badge">
      <AppIcon name="bookmark" :size="14" />
      <span>Đã ghim lên Thông báo công ty</span>
    </div>

    <div class="post-card__header">
      <img
        v-if="post.author.avatar_url"
        class="post-card__avatar"
        :src="post.author.avatar_url"
        :alt="`Ảnh đại diện của ${post.author.name}`"
      />
      <div v-else class="post-card__avatar post-card__avatar--placeholder">
        {{ post.author.name?.charAt(0) ?? '?' }}
      </div>

      <div class="post-card__author-info">
        <div class="post-card__author-name">{{ post.author.name }}</div>
        <div v-if="post.author.department" class="post-card__meta">
          {{ post.author.department }}
        </div>
      </div>

      <time class="post-card__time" :datetime="post.created_at">
        {{ formatSocialTime(post.created_at) }}
      </time>

      <div class="post-card__header-actions">
        <button
          v-if="post.can_pin"
          type="button"
          class="post-card__icon-btn"
          :aria-label="post.is_pinned ? 'Bỏ ghim bài viết' : 'Ghim bài viết'"
          @click="togglePin"
        >
          <AppIcon name="bookmark" :size="16" />
        </button>
        <button
          v-if="post.can_delete"
          type="button"
          class="post-card__icon-btn"
          aria-label="Xoá bài viết"
          @click="remove"
        >
          <AppIcon name="trash" :size="16" />
        </button>
      </div>
    </div>

    <div v-if="post.content" class="post-card__content" v-html="sanitizedContent"></div>

    <div v-if="post.shared_from" class="post-card__shared">
      <div class="post-card__shared-author">{{ post.shared_from.author.name }}</div>
      <div
        v-if="post.shared_from.content"
        class="post-card__shared-content"
        v-html="sanitizedSharedContent"
      ></div>
    </div>

    <div v-if="post.attachments.length > 0" class="post-card__attachments">
      <a
        v-for="(attachment, index) in post.attachments"
        :key="index"
        :href="attachment.url"
        target="_blank"
        rel="noopener"
        class="post-card__attachment"
      >
        <img
          v-if="attachment.type === 'image'"
          :src="attachment.url"
          :alt="attachment.name"
          class="post-card__attachment-image"
        />
        <span v-else class="post-card__attachment-file">
          <AppIcon name="fileText" :size="16" />
          {{ attachment.name }}
        </span>
      </a>
    </div>

    <div v-if="topReactions.length > 0 || localCommentsCount > 0" class="post-card__stats">
      <div v-if="topReactions.length > 0" class="post-card__reaction-summary">
        <button
          v-for="reaction in topReactions.slice(0, 3)"
          :key="reaction.type"
          type="button"
          class="post-card__reaction-emoji"
          :aria-label="reaction.label"
          @click="setReaction(reaction.type, $event)"
        >
          {{ reaction.emoji }}
        </button>
        <span class="post-card__reaction-total">{{ localReactions.total }}</span>
      </div>
      <span v-if="localCommentsCount > 0">{{ localCommentsCount }} bình luận</span>
    </div>

    <div class="post-card__actions">
      <SocialReactionPicker :my-reaction="localMyReaction" @pick="setReaction">
        <button
          type="button"
          class="post-card__action-btn"
          :class="[
            { 'post-card__action-btn--active': myReactionInfo, 'post-card__action-btn--pop': popping },
            myReactionInfo ? `post-card__action-btn--${myReactionInfo.type}` : null,
          ]"
          @click="onLikeButtonClick"
        >
          <span
            v-if="myReactionInfo"
            class="post-card__reaction-current-emoji"
            :class="{ 'post-card__reaction-current-emoji--pop': popping }"
          >{{ myReactionInfo.emoji }}</span>
          <AppIcon v-else name="star" :size="18" />
          <span>{{ myReactionInfo?.label ?? 'Thích' }}</span>
        </button>
      </SocialReactionPicker>
      <button
        type="button"
        class="post-card__action-btn"
        :class="{ 'post-card__action-btn--active': showComments }"
        @click="showComments = !showComments"
      >
        <AppIcon name="messageCircle" :size="18" />
        <span>Bình luận</span>
      </button>
      <button type="button" class="post-card__action-btn" @click="share">
        <AppIcon name="link" :size="18" />
        <span>Chia sẻ</span>
      </button>
    </div>

    <SocialCommentList
      v-if="showComments"
      :post-id="post.id"
      :can-moderate="post.can_delete"
      @count-changed="onCommentsCountChanged"
      @close="showComments = false"
    />

    <Teleport to="body">
      <span
        v-for="burst in bursts"
        :key="burst.id"
        class="reaction-burst"
        :style="{
          left: `${burst.x}px`,
          top: `${burst.y}px`,
          animationDelay: `${burst.delay}px`,
          '--burst-drift': `${burst.drift}px`,
        }"
      >{{ burst.emoji }}</span>
    </Teleport>
  </article>
</template>

<style scoped>
.post-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  box-shadow: var(--shadow-sm);
  overflow: visible;
}

.post-card__pinned-badge {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  color: var(--color-primary);
  font-size: 0.8125rem;
  font-weight: 600;
}

.post-card__header {
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
}

.post-card__avatar {
  width: 40px;
  height: 40px;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
}

.post-card__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-weight: 600;
}

.post-card__author-info {
  flex: 1;
  min-width: 0;
}

.post-card__author-name {
  font-weight: 600;
  color: var(--color-text);
}

.post-card__meta {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.post-card__time {
  margin-left: auto;
  flex-shrink: 0;
  padding-top: 0.2em;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  white-space: nowrap;
}

.post-card__header-actions {
  display: flex;
  gap: var(--space-1);
  flex-shrink: 0;
}

.post-card__icon-btn {
  border: none;
  background: none;
  color: var(--color-text-muted);
  cursor: pointer;
  padding: var(--space-1);
  border-radius: var(--radius-md);
}

.post-card__icon-btn:hover {
  background: var(--color-surface-muted);
}

.post-card__content {
  color: var(--color-text);
  word-break: break-word;
}

.post-card__content :deep(p),
.post-card__shared-content :deep(p) {
  margin: 0 0 var(--space-2) 0;
}

.post-card__content :deep(p:last-child),
.post-card__shared-content :deep(p:last-child) {
  margin-bottom: 0;
}

.post-card__content :deep(h1),
.post-card__content :deep(h2),
.post-card__content :deep(h3) {
  font-weight: 700;
  margin: 0 0 var(--space-2) 0;
}

.post-card__content :deep(ul),
.post-card__content :deep(ol),
.post-card__shared-content :deep(ul),
.post-card__shared-content :deep(ol) {
  margin: 0 0 var(--space-2) 0;
  padding-left: 1.25rem;
}

.post-card__content :deep(a),
.post-card__shared-content :deep(a) {
  color: var(--color-info);
}

.post-card__content :deep(.mention),
.post-card__shared-content :deep(.mention) {
  color: var(--color-primary);
  font-weight: 600;
}

.post-card__shared {
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  padding: var(--space-3);
}

.post-card__shared-author {
  font-weight: 600;
  font-size: 0.875rem;
  color: var(--color-text);
}

.post-card__shared-content {
  font-size: 0.875rem;
  color: var(--color-text);
  margin-top: var(--space-1);
}

.post-card__attachments {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}

.post-card__attachment-image {
  max-width: 200px;
  max-height: 200px;
  border-radius: var(--radius-md);
  object-fit: cover;
}

.post-card__attachment-file {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  background: var(--color-surface-muted);
  border-radius: var(--radius-md);
  padding: var(--space-2) var(--space-3);
  font-size: 0.8125rem;
  color: var(--color-text);
}

.post-card__stats {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  box-shadow: 0 1px 0 var(--color-border);
  padding-bottom: var(--space-2);
}

.post-card__reaction-summary {
  display: flex;
  align-items: center;
  gap: var(--space-1);
}

.post-card__reaction-emoji {
  border: none;
  background: none;
  padding: 0;
  font-size: 0.9375rem;
  line-height: 1;
  cursor: pointer;
  transition: transform 0.16s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.post-card__reaction-emoji:hover {
  transform: scale(1.22);
}

.post-card__reaction-total {
  margin-left: var(--space-1);
  font-variant-numeric: tabular-nums;
}

.post-card__reaction-current-emoji {
  font-size: 1.125rem;
  line-height: 1;
  display: inline-block;
}

.post-card__reaction-current-emoji--pop {
  animation: reaction-face-pop 0.42s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.post-card__actions {
  display: flex;
  gap: var(--space-2);
  overflow: visible;
}

.post-card__action-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-1);
  border: none;
  background: none;
  color: var(--color-text-muted);
  cursor: pointer;
  padding: var(--space-2);
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  transition: background 0.15s ease, color 0.15s ease, transform 0.18s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.post-card__action-btn:hover {
  background: var(--color-surface-muted);
}

.post-card__action-btn:active {
  transform: scale(0.96);
}

.post-card__action-btn--active {
  color: var(--color-primary);
  font-weight: 600;
}

.post-card__action-btn--like {
  color: #1877f2;
}

.post-card__action-btn--love {
  color: #f33e58;
}

.post-card__action-btn--haha,
.post-card__action-btn--wow {
  color: #f7b125;
}

.post-card__action-btn--sad,
.post-card__action-btn--angry {
  color: #e9710f;
}

.post-card__action-btn--pop {
  animation: reaction-btn-pop 0.42s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.reaction-burst {
  position: fixed;
  z-index: 90;
  font-size: 1.75rem;
  line-height: 1;
  pointer-events: none;
  animation: reaction-burst-fly 0.72s ease-out forwards;
}

@keyframes reaction-face-pop {
  0% {
    transform: scale(0.55) rotate(-12deg);
  }

  55% {
    transform: scale(1.28) rotate(8deg);
  }

  100% {
    transform: scale(1) rotate(0);
  }
}

@keyframes reaction-btn-pop {
  0% {
    transform: scale(1);
  }

  40% {
    transform: scale(1.06);
  }

  100% {
    transform: scale(1);
  }
}

@keyframes reaction-burst-fly {
  0% {
    opacity: 1;
    transform: translate(-50%, -50%) scale(0.4);
  }

  35% {
    opacity: 1;
    transform: translate(calc(-50% + var(--burst-drift)), -72px) scale(1.28);
  }

  100% {
    opacity: 0;
    transform: translate(calc(-50% + var(--burst-drift) * 1.4), -132px) scale(0.75);
  }
}

@media (prefers-reduced-motion: reduce) {
  .post-card__action-btn,
  .post-card__reaction-emoji,
  .post-card__reaction-current-emoji {
    transition: none;
    animation: none;
  }

  .reaction-burst {
    display: none;
  }
}
</style>
