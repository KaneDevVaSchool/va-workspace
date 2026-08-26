<script setup>
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { REACTIONS, reactionByType } from '../constants/reactions.js';
import { formatSocialTime } from '../lib/formatSocialTime.js';
import { applyOptimistic, cloneReactions, useReactionAction } from '../lib/useReactionAction.js';
import { sanitizeSocialHtml } from '../lib/sanitizeSocialHtml.js';
import { vSocialStickers } from '../lib/socialStickers.js';
import { mentionUserIdFromEvent } from '../lib/mentionClick.js';
import { hashtagFromEvent } from '../lib/hashtagClick.js';
import SocialCommentComposer from './SocialCommentComposer.vue';
import SocialImageGrid from './SocialImageGrid.vue';
import SocialReactionBursts from './SocialReactionBursts.vue';
import SocialReactionList from './SocialReactionList.vue';
import SocialReactionPicker from './SocialReactionPicker.vue';

defineOptions({ name: 'SocialCommentItem' });

const MAX_REPLY_DEPTH = 2;

const props = defineProps({
  comment: { type: Object, required: true },
  postId: { type: Number, required: true },
  canModerate: { type: Boolean, default: false },
  depth: { type: Number, default: 0 },
});

const emit = defineEmits(['count-changed', 'deleted', 'replied', 'open-wall', 'open-hashtag']);

const auth = useAuthStore();
const replyOpen = ref(false);
const confirmDeleteOpen = ref(false);
const deleting = ref(false);
const localReplies = ref(props.comment.replies ?? []);
const localReactions = ref(cloneReactions(props.comment.reactions));
const localMyReaction = ref(props.comment.my_reaction ?? null);
const { popping, bursts, playFeedback, nextGen, isLatest } = useReactionAction();
const reactionListOpen = ref(false);
const reactionListType = ref(null);
const canNestReplies = computed(() => props.depth < MAX_REPLY_DEPTH);
const deleteDescription = computed(() =>
  localReplies.value.length > 0
    ? 'Bình luận này và các trả lời bên trong sẽ bị xoá. Thao tác không thể hoàn tác.'
    : 'Bạn có chắc muốn xoá bình luận này? Thao tác không thể hoàn tác.',
);

const sanitizedContent = computed(() => sanitizeSocialHtml(props.comment.content));
const attachments = computed(() => props.comment.attachments ?? []);
const imageAttachments = computed(() => attachments.value.filter((item) => item.type === 'image'));
const fileAttachments = computed(() => attachments.value.filter((item) => item.type !== 'image'));
const topReactions = computed(() =>
  REACTIONS
    .map((r) => ({ ...r, count: localReactions.value[r.type] ?? 0 }))
    .filter((r) => r.count > 0)
    .sort((a, b) => b.count - a.count),
);
const myReactionInfo = computed(() => reactionByType(localMyReaction.value));

function canDeleteComment(comment) {
  return comment.author.id === auth.user?.id || props.canModerate;
}

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
    const { data } = await window.axios.post(`/api/social/comments/${props.comment.id}/reactions`, { type });
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

function openReactionList(type = null) {
  reactionListType.value = type;
  reactionListOpen.value = true;
}

function toggleReply() {
  replyOpen.value = !replyOpen.value;
}

function onContentClick(event) {
  const tag = hashtagFromEvent(event);
  if (tag) {
    emit('open-hashtag', tag);
    return;
  }
  const userId = mentionUserIdFromEvent(event);
  if (userId) emit('open-wall', userId);
}

function onReplySubmitted(data) {
  if (canNestReplies.value) {
    localReplies.value = [...localReplies.value, data.comment];
  } else {
    emit('replied', data.comment);
  }
  replyOpen.value = false;
  emit('count-changed', data.comments_count);
}

function onChildReplied(comment) {
  localReplies.value = [...localReplies.value, comment];
}

function onReplyDeleted(commentId) {
  localReplies.value = localReplies.value.filter((r) => r.id !== commentId);
}

function askRemove() {
  confirmDeleteOpen.value = true;
}

async function confirmRemove() {
  if (deleting.value) return;
  deleting.value = true;
  try {
    const { data } = await window.axios.delete(`/api/social/comments/${props.comment.id}`);
    confirmDeleteOpen.value = false;
    emit('deleted', props.comment.id);
    emit('count-changed', data.comments_count);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không thể xoá bình luận.');
  } finally {
    deleting.value = false;
  }
}
</script>

<template>
  <div class="comment-item" :class="{ 'comment-item--reply': depth > 0 }">
    <div class="comment">
      <img
        v-if="comment.author.avatar_url"
        class="comment__avatar"
        :class="{ 'comment__avatar--small': depth > 0 }"
        :src="comment.author.avatar_url"
        :alt="`Ảnh đại diện của ${comment.author.name}`"
      />
      <div v-else class="comment__avatar comment__avatar--placeholder" :class="{ 'comment__avatar--small': depth > 0 }">
        {{ comment.author.name?.charAt(0) ?? '?' }}
      </div>

      <div class="comment__body">
        <div class="comment__bubble">
          <div class="comment__head">
            <span class="comment__author">{{ comment.author.name }}</span>
            <time class="comment__time" :datetime="comment.created_at">
              {{ formatSocialTime(comment.created_at) }}
            </time>
          </div>
          <div
            v-if="sanitizedContent"
            class="comment__content"
            v-html="sanitizedContent"
            v-social-stickers
            @click="onContentClick"
          ></div>

          <div v-if="imageAttachments.length > 0" class="comment__images">
            <SocialImageGrid compact :images="imageAttachments" />
          </div>

          <div v-if="fileAttachments.length > 0" class="comment__attachments">
            <a
              v-for="(attachment, index) in fileAttachments"
              :key="index"
              :href="attachment.url"
              target="_blank"
              rel="noopener"
              class="comment__attachment"
            >
              <span class="comment__attachment-file">
                <AppIcon name="fileText" :size="14" />
                {{ attachment.name }}
              </span>
            </a>
          </div>
        </div>

        <div class="comment__meta-row">
          <SocialReactionPicker compact :my-reaction="localMyReaction" @pick="setReaction">
            <button
              type="button"
              class="comment__like"
              :class="[
                { 'comment__like--active': myReactionInfo, 'comment__like--pop': popping },
                myReactionInfo ? `comment__like--${myReactionInfo.type}` : null,
              ]"
              :aria-label="myReactionInfo?.label ?? 'Thích'"
              @click="onLikeButtonClick"
            >
              <span v-if="myReactionInfo" class="comment__like-emoji">{{ myReactionInfo.emoji }}</span>
              <span v-else>Thích</span>
            </button>
          </SocialReactionPicker>
          <button type="button" class="comment__reply-toggle" @click="toggleReply">
            Trả lời
          </button>
          <div v-if="topReactions.length > 0" class="comment__reaction-summary">
            <button
              v-for="reaction in topReactions"
              :key="reaction.type"
              type="button"
              class="comment__reaction-chip"
              :aria-label="`${reaction.label}: ${reaction.count} người`"
              @click="openReactionList(reaction.type)"
            >
              <span class="comment__reaction-emoji">{{ reaction.emoji }}</span>
              <span class="comment__reaction-count">{{ reaction.count }}</span>
            </button>
          </div>
        </div>
      </div>

      <button
        v-if="canDeleteComment(comment)"
        type="button"
        class="comment__delete"
        aria-label="Xoá bình luận"
        @click="askRemove"
      >
        <AppIcon name="trash" :size="14" />
      </button>
    </div>

    <div v-if="canNestReplies && localReplies.length > 0" class="comment-item__replies">
      <SocialCommentItem
        v-for="reply in localReplies"
        :key="reply.id"
        :comment="reply"
        :post-id="postId"
        :can-moderate="canModerate"
        :depth="depth + 1"
        @replied="onChildReplied"
        @deleted="onReplyDeleted"
        @count-changed="emit('count-changed', $event)"
        @open-wall="emit('open-wall', $event)"
        @open-hashtag="emit('open-hashtag', $event)"
      />
    </div>

    <div v-if="replyOpen" class="comment-item__reply-form">
      <SocialCommentComposer
        :post-id="postId"
        :parent-comment-id="comment.id"
        :mentioned-user="comment.author"
        :placeholder="`Trả lời ${comment.author.name}...`"
        submit-label="Gửi trả lời"
        auto-expand
        @submitted="onReplySubmitted"
        @close="replyOpen = false"
      />
    </div>

    <SocialReactionBursts :bursts="bursts" />

    <ConfirmDialog
      v-model:open="confirmDeleteOpen"
      title="Xoá bình luận"
      :description="deleteDescription"
      confirm-label="Xoá"
      danger
      :loading="deleting"
      @confirm="confirmRemove"
    />

    <SocialReactionList
      :open="reactionListOpen"
      :endpoint="`/api/social/comments/${comment.id}/reactions`"
      :initial-type="reactionListType"
      @close="reactionListOpen = false"
    />
  </div>
</template>

<style scoped>
.comment-item {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.comment {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
}

.comment__avatar {
  width: 28px;
  height: 28px;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
}

.comment__avatar--small {
  width: 24px;
  height: 24px;
}

.comment__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 0.75rem;
  font-weight: 600;
}

.comment__body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  overflow: visible;
}

.comment__bubble {
  background: var(--color-surface-muted);
  border-radius: var(--radius-md);
  padding: var(--space-2) var(--space-3);
}

.comment__head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
}

.comment__author {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text);
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.comment__time {
  margin-left: auto;
  flex-shrink: 0;
  font-size: 0.75rem;
  color: var(--color-text-muted);
  white-space: nowrap;
}

.comment__content {
  font-size: 0.875rem;
  color: var(--color-text);
  word-break: break-word;
}

.comment__content :deep(p) {
  margin: 0 0 var(--space-1) 0;
}

.comment__content :deep(p:last-child) {
  margin-bottom: 0;
}

.comment__content :deep(h2),
.comment__content :deep(h3) {
  font-weight: 700;
  margin: 0 0 var(--space-1) 0;
}

.comment__content :deep(ul),
.comment__content :deep(ol) {
  margin: 0 0 var(--space-1) 0;
  padding-left: 1.25rem;
}

.comment__content :deep(a) {
  color: var(--color-info);
}

.comment__content :deep(.mention),
.comment__content :deep(.hashtag) {
  color: var(--color-primary);
  font-weight: 600;
  cursor: pointer;
}

.comment__attachments {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
  margin-top: var(--space-2);
}

.comment__images {
  margin-top: var(--space-2);
  min-width: 0;
}

.comment__attachment-file {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  background: var(--color-surface);
  border-radius: var(--radius-md);
  padding: var(--space-1) var(--space-2);
  font-size: 0.75rem;
  color: var(--color-text);
}

.comment__meta-row {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: 0 var(--space-2);
  overflow: visible;
}

.comment__like,
.comment__reply-toggle {
  border: none;
  background: none;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: color 0.15s ease, transform 0.18s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.comment__like:hover,
.comment__reply-toggle:hover {
  color: var(--color-primary);
}

.comment__like:active {
  transform: scale(0.94);
}

.comment__like--active {
  color: var(--color-primary);
}

.comment__like--like {
  color: #1877f2;
}

.comment__like--love {
  color: #f33e58;
}

.comment__like--haha,
.comment__like--wow {
  color: #f7b125;
}

.comment__like--sad,
.comment__like--angry {
  color: #e9710f;
}

.comment__like--pop {
  animation: comment-like-pop 0.42s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.comment__like-emoji {
  font-size: 1rem;
  line-height: 1;
  display: inline-block;
}

.comment__like--pop .comment__like-emoji {
  animation: comment-like-face 0.42s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes comment-like-pop {
  0% {
    transform: scale(1);
  }

  40% {
    transform: scale(1.08);
  }

  100% {
    transform: scale(1);
  }
}

@keyframes comment-like-face {
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

@media (prefers-reduced-motion: reduce) {
  .comment__like,
  .comment__like-emoji {
    animation: none;
    transition: none;
  }
}

.comment__reaction-summary {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 2px;
  flex-shrink: 0;
}

.comment__reaction-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.15rem;
  border: none;
  background: var(--color-surface);
  padding: 0.1rem 0.35rem 0.1rem 0.2rem;
  border-radius: var(--radius-full);
  color: var(--color-text-muted);
  font-family: inherit;
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1;
  cursor: pointer;
}

.comment__reaction-chip:hover {
  background: var(--color-primary-surface);
}

.comment__reaction-emoji {
  font-size: 0.8125rem;
  line-height: 1;
}

.comment__reaction-count {
  font-variant-numeric: tabular-nums;
}

.comment__delete {
  border: none;
  background: none;
  color: var(--color-text-muted);
  cursor: pointer;
  padding: var(--space-1);
  flex-shrink: 0;
}

.comment-item__replies {
  padding-left: var(--space-6);
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.comment-item__reply-form {
  padding-left: var(--space-6);
  min-width: 0;
}
</style>
