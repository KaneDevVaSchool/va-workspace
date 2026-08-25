<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { REACTIONS, reactionByType } from '../constants/reactions.js';
import { formatSocialTime } from '../lib/formatSocialTime.js';
import { applyOptimistic, cloneReactions, useReactionAction } from '../lib/useReactionAction.js';
import { sanitizeSocialHtml } from '../lib/sanitizeSocialHtml.js';
import { mentionUserIdFromEvent } from '../lib/mentionClick.js';
import SocialCommentList from './SocialCommentList.vue';
import SocialImageGrid from './SocialImageGrid.vue';
import SocialPollBlock from './SocialPollBlock.vue';
import SocialPostEditor from './SocialPostEditor.vue';
import SocialReactionBursts from './SocialReactionBursts.vue';
import SocialReactionList from './SocialReactionList.vue';
import SocialReactionPicker from './SocialReactionPicker.vue';
import SocialShareDialog from './SocialShareDialog.vue';

const props = defineProps({
  post: { type: Object, required: true },
  postScope: { type: String, default: 'company' },
  departmentName: { type: String, default: '' },
  openComments: { type: Boolean, default: false },
  highlighted: { type: Boolean, default: false },
});

const sanitizedContent = computed(() => sanitizeSocialHtml(props.post.content));
const sanitizedSharedContent = computed(() => sanitizeSocialHtml(props.post.shared_from?.content));

const emit = defineEmits(['deleted', 'pinned', 'unpinned', 'shared', 'updated', 'open-wall']);

const showComments = ref(props.openComments);
const shareOpen = ref(false);
const editing = ref(false);
const savingEdit = ref(false);
const draftContent = ref('');
const editorEmpty = ref(true);
const editorRef = ref(null);
const showHistory = ref(false);
const historyLoading = ref(false);
const historyVersions = ref([]);
const reactionListOpen = ref(false);
const reactionListType = ref(null);
const localReactions = ref(cloneReactions(props.post.reactions));
const localMyReaction = ref(props.post.my_reaction);
const localCommentsCount = ref(props.post.comments_count);
const localPoll = ref(props.post.poll ?? null);
const { popping, bursts, playFeedback, nextGen, isLatest } = useReactionAction();

watch(() => props.post.poll, (poll) => {
  localPoll.value = poll ?? null;
});

const topReactions = computed(() =>
  REACTIONS
    .map((r) => ({ ...r, count: localReactions.value[r.type] ?? 0 }))
    .filter((r) => r.count > 0)
    .sort((a, b) => b.count - a.count),
);

const myReactionInfo = computed(() => reactionByType(localMyReaction.value));
const isSystemPin = computed(() => props.post.pin_scope === 'system');
const pinnedLabel = computed(() => {
  if (isSystemPin.value) return 'Thông báo quan trọng';
  if (props.post.post_scope === 'department') return 'Tin nổi bật của phòng ban';
  return 'Thông báo công ty';
});
const pinnedIcon = computed(() => (isSystemPin.value ? 'shield' : 'megaphone'));
const postedOnOtherWall = computed(() => (
  props.post.post_scope === 'personal'
  && props.post.wall_user
  && props.post.wall_user.id !== props.post.author?.id
));
const imageAttachments = computed(() =>
  (props.post.attachments ?? []).filter((item) => item.type === 'image'),
);
const fileAttachments = computed(() =>
  (props.post.attachments ?? []).filter((item) => item.type !== 'image'),
);

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

function openReactionList(type = null) {
  reactionListType.value = type;
  reactionListOpen.value = true;
}

watch(() => props.openComments, (open) => {
  if (open) showComments.value = true;
});

function onMentionClick(event) {
  const userId = mentionUserIdFromEvent(event);
  if (userId) openWall(userId);
}

function onShared(post) {
  emit('shared', post);
}

function openWall(userId) {
  if (!userId) return;
  emit('open-wall', userId);
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

function onPollUpdated(poll) {
  localPoll.value = poll;
  emit('updated', { ...props.post, poll });
}

function onCommentsCountChanged(count) {
  localCommentsCount.value = count;
}

async function startEdit() {
  showHistory.value = false;
  draftContent.value = props.post.content ?? '';
  editorEmpty.value = !draftContent.value;
  editing.value = true;
  await nextTick();
  editorRef.value?.focus();
}

function cancelEdit() {
  editing.value = false;
  draftContent.value = '';
}

async function toggleHistory() {
  if (showHistory.value) {
    showHistory.value = false;
    return;
  }

  showHistory.value = true;
  if (historyVersions.value.length > 0) return;

  historyLoading.value = true;
  try {
    const { data } = await window.axios.get(`/api/social/posts/${props.post.id}/revisions`);
    historyVersions.value = data.versions ?? [];
  } catch (error) {
    showHistory.value = false;
    showClientToast('error', error?.response?.data?.message ?? 'Không thể tải lịch sử chỉnh sửa.');
  } finally {
    historyLoading.value = false;
  }
}

async function saveEdit() {
  if (editorEmpty.value && !localPoll.value) {
    showClientToast('error', 'Nội dung bài viết không được để trống.');
    return;
  }
  if (savingEdit.value) return;
  savingEdit.value = true;
  try {
    const { data } = await window.axios.put(`/api/social/posts/${props.post.id}`, {
      content: draftContent.value,
    });
    emit('updated', data.post);
    editing.value = false;
    showHistory.value = false;
    historyVersions.value = [];
    showClientToast('success', 'Đã cập nhật bài viết.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không thể sửa bài viết.');
  } finally {
    savingEdit.value = false;
  }
}
</script>

<template>
  <article
    class="post-card"
    :class="{
      'post-card--pinned': post.is_pinned,
      'post-card--pinned-system': post.is_pinned && isSystemPin,
      'post-card--pinned-company': post.is_pinned && !isSystemPin,
      'post-card--focused': highlighted,
    }"
  >
    <div v-if="post.is_pinned" class="post-card__pinned-badge">
      <span class="post-card__pinned-icon" aria-hidden="true">
        <AppIcon :name="pinnedIcon" :size="14" />
      </span>
      <span class="post-card__pinned-copy">
        <span class="post-card__pinned-label">{{ pinnedLabel }}</span>
        <span v-if="post.pinned_by" class="post-card__pinned-by">Ghim bởi {{ post.pinned_by }}</span>
      </span>
    </div>

    <div class="post-card__header">
        <button
          type="button"
          class="post-card__avatar-btn"
          :aria-label="`Xem tường của ${post.author.name}`"
          @click="openWall(post.author.id)"
        >
          <img
            v-if="post.author.avatar_url"
            class="post-card__avatar"
            :src="post.author.avatar_url"
            :alt="`Ảnh đại diện của ${post.author.name}`"
          />
          <div v-else class="post-card__avatar post-card__avatar--placeholder">
            {{ post.author.name?.charAt(0) ?? '?' }}
          </div>
        </button>

      <div class="post-card__author-info">
        <div class="post-card__author-name">
          <button type="button" class="post-card__author-link" @click="openWall(post.author.id)">
            {{ post.author.name }}
          </button>
          <template v-if="postedOnOtherWall">
            <span class="post-card__wall-sep">·</span>
            <button type="button" class="post-card__author-link post-card__author-link--muted" @click="openWall(post.wall_user.id)">
              tường {{ post.wall_user.name }}
            </button>
          </template>
        </div>
        <div v-if="post.author.department" class="post-card__meta">
          {{ post.author.department }}
        </div>
      </div>

      <div class="post-card__when">
        <time class="post-card__time" :datetime="post.created_at">
          {{ formatSocialTime(post.created_at) }}
        </time>
        <button
          v-if="post.is_edited"
          type="button"
          class="post-card__edited"
          :aria-expanded="showHistory"
          aria-label="Xem lịch sử chỉnh sửa"
          @click="toggleHistory"
        >
          Đã chỉnh sửa
        </button>
      </div>

      <div class="post-card__header-actions">
        <button
          v-if="post.can_edit && !editing"
          type="button"
          class="post-card__icon-btn"
          aria-label="Sửa bài viết"
          @click="startEdit"
        >
          <AppIcon name="pencil" :size="16" />
        </button>
        <button
          v-if="post.can_pin"
          type="button"
          class="post-card__icon-btn"
          :class="{ 'post-card__icon-btn--pinned': post.is_pinned }"
          :aria-label="post.is_pinned
            ? (isSystemPin ? 'Gỡ khỏi thông báo quan trọng' : (post.post_scope === 'department' ? 'Gỡ khỏi tin nổi bật của phòng ban' : 'Gỡ khỏi thông báo công ty'))
            : 'Đưa bài viết lên đầu trang'"
          @click="togglePin"
        >
          <AppIcon name="bookmark" :size="16" />
        </button>
        <button
          v-if="post.can_delete && !editing"
          type="button"
          class="post-card__icon-btn"
          aria-label="Xoá bài viết"
          @click="remove"
        >
          <AppIcon name="trash" :size="16" />
        </button>
      </div>
    </div>

    <div v-if="editing" class="post-card__edit">
      <SocialPostEditor
        ref="editorRef"
        v-model="draftContent"
        placeholder="Chỉnh sửa bài viết..."
        enable-mentions
        @is-empty="editorEmpty = $event"
        @close="cancelEdit"
      />
      <div class="post-card__edit-actions">
        <button type="button" class="post-card__edit-cancel" :disabled="savingEdit" @click="cancelEdit">
          Huỷ
        </button>
        <button type="button" class="post-card__edit-save" :disabled="savingEdit" @click="saveEdit">
          {{ savingEdit ? 'Đang lưu...' : 'Lưu' }}
        </button>
      </div>
    </div>

    <div
      v-else-if="post.content"
      class="post-card__content"
      v-html="sanitizedContent"
      @click="onMentionClick"
    ></div>

    <section
      v-if="showHistory && !editing"
      class="post-card__history"
      aria-label="Lịch sử chỉnh sửa"
    >
      <h3 class="post-card__history-title">
        <AppIcon name="clock" :size="16" />
        Lịch sử chỉnh sửa
      </h3>
      <p v-if="historyLoading" class="post-card__history-empty">Đang tải lịch sử...</p>
      <ol v-else class="post-card__history-list">
        <li v-for="version in historyVersions" :key="version.id ?? 'current'" class="post-card__history-item">
          <div class="post-card__history-meta">
            <span class="post-card__history-label">
              {{ version.is_current ? 'Hiện tại' : 'Phiên bản trước' }}
            </span>
            <time :datetime="version.published_at">{{ formatSocialTime(version.published_at) }}</time>
          </div>
          <div
            v-if="version.content"
            class="post-card__history-content"
            v-html="sanitizeSocialHtml(version.content)"
          ></div>
          <p v-else class="post-card__history-empty">Không có nội dung.</p>
        </li>
      </ol>
    </section>

    <div v-if="post.shared_from" class="post-card__shared">
      <button
        type="button"
        class="post-card__shared-author post-card__author-link"
        @click="openWall(post.shared_from.author.id)"
      >
        {{ post.shared_from.author.name }}
      </button>
      <div
        v-if="post.shared_from.content"
        class="post-card__shared-content"
        v-html="sanitizedSharedContent"
        @click="onMentionClick"
      ></div>
    </div>

    <div v-if="imageAttachments.length > 0" class="post-card__images">
      <SocialImageGrid :images="imageAttachments" />
    </div>

    <div v-if="fileAttachments.length > 0" class="post-card__attachments">
      <a
        v-for="(attachment, index) in fileAttachments"
        :key="index"
        :href="attachment.url"
        target="_blank"
        rel="noopener"
        class="post-card__attachment"
      >
        <span class="post-card__attachment-file">
          <AppIcon name="fileText" :size="16" />
          {{ attachment.name }}
        </span>
      </a>
    </div>

    <SocialPollBlock
      v-if="localPoll"
      :post-id="post.id"
      :poll="localPoll"
      @updated="onPollUpdated"
    />

    <div v-if="topReactions.length > 0 || localCommentsCount > 0" class="post-card__stats">
      <div v-if="topReactions.length > 0" class="post-card__reaction-summary">
        <button
          v-for="reaction in topReactions"
          :key="reaction.type"
          type="button"
          class="post-card__reaction-chip"
          :aria-label="`${reaction.label}: ${reaction.count} người`"
          @click="openReactionList(reaction.type)"
        >
          <span class="post-card__reaction-emoji">{{ reaction.emoji }}</span>
          <span class="post-card__reaction-count">{{ reaction.count }}</span>
        </button>
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
      <button type="button" class="post-card__action-btn" @click="shareOpen = true">
        <AppIcon name="share" :size="18" />
        <span>Chia sẻ</span>
      </button>
    </div>

    <SocialCommentList
      v-if="showComments"
      :post-id="post.id"
      :can-moderate="post.can_delete"
      @count-changed="onCommentsCountChanged"
      @close="showComments = false"
      @open-wall="openWall"
    />

    <SocialReactionBursts :bursts="bursts" />

    <SocialReactionList
      :open="reactionListOpen"
      :endpoint="`/api/social/posts/${post.id}/reactions`"
      :initial-type="reactionListType"
      @close="reactionListOpen = false"
    />

    <SocialShareDialog
      :open="shareOpen"
      :post-id="post.id"
      :department-name="departmentName"
      :default-scope="postScope"
      :default-wall-user="postScope === 'personal' ? (post.wall_user ?? post.author) : null"
      @close="shareOpen = false"
      @shared="onShared"
    />
  </article>
</template>

<style scoped>
.post-card {
  position: relative;
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

.post-card--pinned {
  padding-left: calc(var(--space-4) + var(--space-2) + 3px);
  box-shadow: var(--shadow-md);
}

.post-card--pinned::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-warning);
}

.post-card--pinned-system::before {
  background: var(--color-info);
}

.post-card__pinned-badge {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-width: 0;
  padding: var(--space-2) var(--space-3) var(--space-2) var(--space-2);
  border-radius: var(--radius-md);
  background: var(--color-warning-tint-bg);
  color: var(--color-warning-tint-fg);
  box-shadow: inset 0 0 0 1px var(--color-warning-tint-border);
}

.post-card--pinned-system .post-card__pinned-badge {
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
  box-shadow: inset 0 0 0 1px var(--color-info-tint-border);
}

.post-card__pinned-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  flex-shrink: 0;
  border-radius: var(--radius-full);
  background: var(--color-warning);
  color: var(--color-on-primary);
}

.post-card--pinned-system .post-card__pinned-icon {
  background: var(--color-info);
}

.post-card--pinned-company .post-card__pinned-icon :deep(.app-icon) {
  animation: post-pin-megaphone 3.2s ease-in-out 0.4s infinite;
  transform-origin: 30% 70%;
}

.post-card__pinned-copy {
  display: flex;
  flex-direction: column;
  min-width: 0;
  gap: 0.05rem;
}

.post-card__pinned-label {
  font-size: 0.8125rem;
  font-weight: 700;
  line-height: 1.3;
}

.post-card__pinned-by {
  font-size: 0.6875rem;
  font-weight: 500;
  line-height: 1.3;
  opacity: 0.82;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.post-card__header {
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
}

.post-card__avatar-btn {
  border: none;
  background: none;
  padding: 0;
  cursor: pointer;
  flex-shrink: 0;
  border-radius: var(--radius-full);
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
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.35rem;
  font-weight: 600;
  color: var(--color-text);
}

.post-card__author-link {
  border: none;
  background: none;
  padding: 0;
  font: inherit;
  color: inherit;
  cursor: pointer;
  text-align: left;
}

.post-card__author-link:hover {
  color: var(--color-primary);
  text-decoration: underline;
}

.post-card__author-link--muted {
  font-weight: 600;
  color: var(--color-text-muted);
}

.post-card__wall-sep {
  color: var(--color-text-muted);
  font-weight: 500;
}

.post-card__meta {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.post-card__time {
  flex-shrink: 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  white-space: nowrap;
}

.post-card__when {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.125rem;
  margin-left: auto;
  flex-shrink: 0;
  padding-top: 0.2em;
}

.post-card__edited {
  border: none;
  background: none;
  padding: 0;
  color: var(--color-info);
  font-family: inherit;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.post-card__edited:hover {
  text-decoration: underline;
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

.post-card__icon-btn--pinned {
  color: var(--color-warning-tint-fg);
  background: var(--color-warning-tint-bg);
}

.post-card__icon-btn--pinned :deep(.app-icon path) {
  fill: currentColor;
}

.post-card--pinned-system .post-card__icon-btn--pinned {
  color: var(--color-info-tint-fg);
  background: var(--color-info-tint-bg);
}

.post-card__icon-btn--pinned:hover {
  background: var(--color-warning-tint-bg);
  color: var(--color-warning-tint-fg);
}

.post-card--pinned-system .post-card__icon-btn--pinned:hover {
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
}

.post-card__edit {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.post-card__edit-actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

.post-card__edit-cancel {
  border: none;
  background: none;
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.875rem;
  font-weight: 600;
  padding: var(--space-2) var(--space-4);
  border-radius: var(--radius-md);
  cursor: pointer;
}

.post-card__edit-cancel:hover {
  background: var(--color-surface-muted);
}

.post-card__edit-save {
  border: none;
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: inherit;
  font-size: 0.875rem;
  font-weight: 600;
  padding: var(--space-2) var(--space-4);
  border-radius: var(--radius-md);
  cursor: pointer;
}

.post-card__edit-save:hover {
  background: var(--color-primary-hover);
}

.post-card__edit-save:disabled,
.post-card__edit-cancel:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.post-card__content {
  color: var(--color-text);
  word-break: break-word;
}

.post-card__history {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.post-card__history-title {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin: 0;
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--color-text);
}

.post-card__history-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.post-card__history-item {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.post-card__history-meta {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.post-card__history-label {
  font-weight: 700;
  color: var(--color-text);
}

.post-card__history-content {
  color: var(--color-text);
  font-size: 0.875rem;
  word-break: break-word;
}

.post-card__history-empty {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.post-card__content :deep(p),
.post-card__shared-content :deep(p),
.post-card__history-content :deep(p) {
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
  cursor: pointer;
}

.post-card--focused {
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary) 45%, transparent);
}

.post-card__shared {
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  padding: var(--space-3);
}

.post-card__shared-author {
  display: inline;
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

.post-card__images {
  min-width: 0;
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

.post-card__reaction-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
  border: none;
  background: var(--color-surface-muted);
  padding: 0.15rem 0.45rem 0.15rem 0.3rem;
  border-radius: var(--radius-full);
  color: var(--color-text-muted);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1;
  cursor: pointer;
  transition: transform 0.16s cubic-bezier(0.34, 1.56, 0.64, 1), background 0.15s ease;
}

.post-card__reaction-chip:hover {
  background: var(--color-primary-surface);
  transform: scale(1.06);
}

.post-card__reaction-emoji {
  font-size: 0.9375rem;
  line-height: 1;
}

.post-card__reaction-count {
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

@keyframes post-pin-megaphone {
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

@media (prefers-reduced-motion: reduce) {
  .post-card__action-btn,
  .post-card__reaction-chip,
  .post-card__reaction-emoji,
  .post-card__reaction-current-emoji,
  .post-card--pinned-company .post-card__pinned-icon :deep(.app-icon) {
    transition: none;
    animation: none;
  }
}
</style>
