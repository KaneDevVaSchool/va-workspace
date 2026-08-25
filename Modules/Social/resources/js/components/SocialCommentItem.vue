<script setup>
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { REACTIONS, reactionByType } from '../constants/reactions.js';
import { formatSocialTime } from '../lib/formatSocialTime.js';
import { sanitizeSocialHtml } from '../lib/sanitizeSocialHtml.js';
import SocialCommentComposer from './SocialCommentComposer.vue';
import SocialReactionPicker from './SocialReactionPicker.vue';

defineOptions({ name: 'SocialCommentItem' });

const props = defineProps({
  comment: { type: Object, required: true },
  postId: { type: Number, required: true },
  canModerate: { type: Boolean, default: false },
  isReply: { type: Boolean, default: false },
});

const emit = defineEmits(['count-changed', 'deleted', 'replied']);

const auth = useAuthStore();
const replyOpen = ref(false);
const reacting = ref(false);
const localReplies = ref(props.comment.replies ?? []);
const localReactions = ref(props.comment.reactions ?? { like: 0, love: 0, haha: 0, wow: 0, sad: 0, angry: 0, total: 0 });
const localMyReaction = ref(props.comment.my_reaction ?? null);

const sanitizedContent = computed(() => sanitizeSocialHtml(props.comment.content));
const attachments = computed(() => props.comment.attachments ?? []);
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

async function setReaction(type) {
  if (reacting.value) return;
  reacting.value = true;
  try {
    const { data } = await window.axios.post(`/api/social/comments/${props.comment.id}/reactions`, { type });
    localReactions.value = data.reactions;
    localMyReaction.value = data.my_reaction;
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không thể thực hiện thao tác.');
  } finally {
    reacting.value = false;
  }
}

function onLikeButtonClick() {
  setReaction(localMyReaction.value ?? 'like');
}

function toggleReply() {
  replyOpen.value = !replyOpen.value;
}

function onReplySubmitted(data) {
  if (props.isReply) {
    emit('replied', data.comment);
  } else {
    localReplies.value = [...localReplies.value, data.comment];
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

async function remove() {
  try {
    const { data } = await window.axios.delete(`/api/social/comments/${props.comment.id}`);
    emit('deleted', props.comment.id);
    emit('count-changed', data.comments_count);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không thể xoá bình luận.');
  }
}
</script>

<template>
  <div class="comment-item" :class="{ 'comment-item--reply': isReply }">
    <div class="comment">
      <img
        v-if="comment.author.avatar_url"
        class="comment__avatar"
        :class="{ 'comment__avatar--small': isReply }"
        :src="comment.author.avatar_url"
        :alt="`Ảnh đại diện của ${comment.author.name}`"
      />
      <div v-else class="comment__avatar comment__avatar--placeholder" :class="{ 'comment__avatar--small': isReply }">
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
          <div v-if="sanitizedContent" class="comment__content" v-html="sanitizedContent"></div>

          <div v-if="attachments.length > 0" class="comment__attachments">
            <a
              v-for="(attachment, index) in attachments"
              :key="index"
              :href="attachment.url"
              target="_blank"
              rel="noopener"
              class="comment__attachment"
            >
              <img
                v-if="attachment.type === 'image'"
                :src="attachment.url"
                :alt="attachment.name"
                class="comment__attachment-image"
              />
              <span v-else class="comment__attachment-file">
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
              :class="{ 'comment__like--active': myReactionInfo }"
              @click="onLikeButtonClick"
            >
              {{ myReactionInfo?.label ?? 'Thích' }}
            </button>
          </SocialReactionPicker>
          <button type="button" class="comment__reply-toggle" @click="toggleReply">
            Trả lời
          </button>
          <div v-if="topReactions.length > 0" class="comment__reaction-summary">
            <span
              v-for="reaction in topReactions.slice(0, 3)"
              :key="reaction.type"
              class="comment__reaction-emoji"
            >{{ reaction.emoji }}</span>
            <span class="comment__reaction-total">{{ localReactions.total }}</span>
          </div>
        </div>
      </div>

      <button
        v-if="canDeleteComment(comment)"
        type="button"
        class="comment__delete"
        aria-label="Xoá bình luận"
        @click="remove"
      >
        <AppIcon name="trash" :size="14" />
      </button>
    </div>

    <div v-if="!isReply && localReplies.length > 0" class="comment-item__replies">
      <SocialCommentItem
        v-for="reply in localReplies"
        :key="reply.id"
        :comment="reply"
        :post-id="postId"
        :can-moderate="canModerate"
        is-reply
        @replied="onChildReplied"
        @deleted="onReplyDeleted"
        @count-changed="emit('count-changed', $event)"
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

.comment__content :deep(.mention) {
  color: var(--color-primary);
  font-weight: 600;
}

.comment__attachments {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
  margin-top: var(--space-2);
}

.comment__attachment-image {
  max-width: 160px;
  max-height: 160px;
  border-radius: var(--radius-md);
  object-fit: cover;
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
}

.comment__like:hover,
.comment__reply-toggle:hover {
  color: var(--color-primary);
}

.comment__like--active {
  color: var(--color-primary);
}

.comment__reaction-summary {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 2px;
  flex-shrink: 0;
}

.comment__reaction-emoji {
  font-size: 0.8125rem;
  line-height: 1;
}

.comment__reaction-total {
  margin-left: var(--space-1);
  font-size: 0.75rem;
  color: var(--color-text-muted);
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
