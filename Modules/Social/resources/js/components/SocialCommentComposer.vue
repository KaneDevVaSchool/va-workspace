<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { MAX_ATTACHMENT_SIZE_BYTES, MAX_COMMENT_ATTACHMENTS } from '../constants/attachments.js';
import SocialEmojiPicker from './SocialEmojiPicker.vue';
import SocialPostEditor from './SocialPostEditor.vue';
import SocialUploadCards from './SocialUploadCards.vue';

const props = defineProps({
  postId: { type: Number, required: true },
  parentCommentId: { type: Number, default: null },
  mentionedUser: { type: Object, default: null },
  placeholder: { type: String, default: 'Viết bình luận...' },
  prompt: { type: String, default: 'Bạn có muốn bình luận?' },
  submitLabel: { type: String, default: 'Gửi' },
  autoExpand: { type: Boolean, default: false },
});

const emit = defineEmits(['submitted', 'close']);

const auth = useAuthStore();
const content = ref('');
const editorEmpty = ref(true);
const files = ref([]);
const gifAttachments = ref([]);
const submitting = ref(false);
const pickerOpen = ref(false);
const pickerPanel = ref('emoji');
const expanded = ref(props.autoExpand || Boolean(props.mentionedUser));
const fileInput = ref(null);
const editorRef = ref(null);
const pickerWrap = ref(null);

const avatarUrl = computed(() => auth.user?.avatar_url ?? null);
const authorName = computed(() => auth.user?.name ?? '');
const avatarInitial = computed(() => authorName.value.trim().charAt(0).toUpperCase() || '?');

onMounted(async () => {
  if (expanded.value) {
    await prepareEditor();
  }
});

async function prepareEditor() {
  await nextTick();
  if (props.mentionedUser) {
    editorRef.value?.insertMention(props.mentionedUser, { replaceQuery: false, suffix: ': ' });
  }
  editorRef.value?.focus();
}

async function expand() {
  expanded.value = true;
  await prepareEditor();
}

function resetDraft() {
  content.value = '';
  files.value = [];
  gifAttachments.value = [];
  pickerOpen.value = false;
}

function closeComposer() {
  resetDraft();
  if (props.autoExpand || props.mentionedUser) {
    emit('close');
    return;
  }
  expanded.value = false;
}

function closePrompt() {
  emit('close');
}

function insertEmoji(emoji) {
  editorRef.value?.insertContent(emoji);
}

function openPicker(panel) {
  if (pickerOpen.value && pickerPanel.value === panel) {
    pickerOpen.value = false;
    return;
  }
  pickerPanel.value = panel;
  pickerOpen.value = true;
}

async function insertSticker(sticker) {
  const sendImmediately = editorEmpty.value && files.value.length === 0 && gifAttachments.value.length === 0;
  editorRef.value?.insertSticker(sticker);
  pickerOpen.value = false;
  if (sendImmediately) {
    await nextTick();
    submit();
  }
}

function insertGif(descriptor) {
  if (!descriptor?.token) return;
  gifAttachments.value = [...gifAttachments.value, descriptor];
  pickerOpen.value = false;
}

function removeGif(index) {
  gifAttachments.value = gifAttachments.value.filter((_, i) => i !== index);
}

function onFilesChosen(event) {
  const chosen = Array.from(event.target.files ?? []);
  if (files.value.length + chosen.length > MAX_COMMENT_ATTACHMENTS) {
    showClientToast('error', `Chỉ được đính kèm tối đa ${MAX_COMMENT_ATTACHMENTS} tệp mỗi bình luận.`);
    event.target.value = '';
    return;
  }
  const oversized = chosen.filter((file) => file.size > MAX_ATTACHMENT_SIZE_BYTES);
  if (oversized.length > 0) {
    showClientToast('error', `${oversized.map((f) => f.name).join(', ')} vượt quá 10MB, vui lòng chọn tệp nhỏ hơn.`);
  }
  const accepted = chosen.filter((file) => file.size <= MAX_ATTACHMENT_SIZE_BYTES);
  files.value = [...files.value, ...accepted];
  event.target.value = '';
}

function removeFile(index) {
  files.value = files.value.filter((_, i) => i !== index);
}

function replaceFile(index, nextFile) {
  files.value = files.value.map((file, i) => (i === index ? nextFile : file));
}

function canSubmit() {
  return !editorEmpty.value || files.value.length > 0 || gifAttachments.value.length > 0;
}

async function submit() {
  if (!canSubmit()) return;

  submitting.value = true;
  try {
    const form = new FormData();
    if (!editorEmpty.value) form.append('content', content.value);
    if (props.parentCommentId) form.append('parent_comment_id', String(props.parentCommentId));
    if (props.mentionedUser?.id) form.append('mentioned_user_id', String(props.mentionedUser.id));
    files.value.forEach((file) => form.append('attachments[]', file));
    gifAttachments.value.forEach((gif) => form.append('gif_attachments[]', gif.token));

    const { data } = await window.axios.post(`/api/social/posts/${props.postId}/comments`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    emit('submitted', data);
    resetDraft();
    if (!props.autoExpand && !props.mentionedUser) {
      expanded.value = false;
    } else if (props.mentionedUser) {
      await nextTick();
      editorRef.value?.insertMention(props.mentionedUser, { replaceQuery: false, suffix: ': ' });
    }
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không thể gửi bình luận.');
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <div class="comment-composer">
    <div v-if="!expanded" class="comment-composer__prompt-row">
      <img
        v-if="avatarUrl"
        class="comment-composer__avatar"
        :src="avatarUrl"
        :alt="`Ảnh đại diện của ${authorName}`"
      />
      <div v-else class="comment-composer__avatar comment-composer__avatar--placeholder">
        {{ avatarInitial }}
      </div>

      <button type="button" class="comment-composer__prompt" @click="expand">
        {{ prompt }}
      </button>

      <button
        type="button"
        class="comment-composer__prompt-close"
        aria-label="Tắt khung bình luận"
        @click="closePrompt"
      >
        <AppIcon name="close" :size="16" />
      </button>
    </div>

    <template v-else>
      <SocialPostEditor
        ref="editorRef"
        v-model="content"
        compact
        enable-mentions
        :placeholder="placeholder"
        @is-empty="editorEmpty = $event"
        @close="closeComposer"
      />

      <SocialUploadCards
        v-if="files.length > 0"
        compact
        :files="files"
        @remove="removeFile"
        @replace="replaceFile"
      />

      <div v-if="gifAttachments.length > 0" class="comment-composer__gifs">
        <article v-for="(gif, index) in gifAttachments" :key="gif.token" class="comment-composer__gif-tile">
          <img :src="gif.previewUrl" alt="GIF đã chọn" />
          <button
            type="button"
            class="comment-composer__gif-remove"
            aria-label="Bỏ GIF này"
            @click="removeGif(index)"
          >
            <AppIcon name="close" :size="12" />
          </button>
        </article>
      </div>

      <div class="comment-composer__actions">
        <div class="comment-composer__actions-left">
          <div ref="pickerWrap" class="comment-composer__emoji-wrap">
            <button
              type="button"
              class="comment-composer__tool"
              :class="{ 'comment-composer__tool--on': pickerOpen && pickerPanel === 'emoji' }"
              aria-label="Chèn emoji"
              @click="openPicker('emoji')"
            >
              😊
              <span>Emoji</span>
            </button>
            <button
              type="button"
              class="comment-composer__tool"
              :class="{ 'comment-composer__tool--on': pickerOpen && pickerPanel === 'sticker' }"
              aria-label="Chèn sticker động"
              @click="openPicker('sticker')"
            >
              <AppIcon name="sticker" :size="16" />
              <span>Sticker</span>
            </button>
            <button
              type="button"
              class="comment-composer__tool"
              :class="{ 'comment-composer__tool--on': pickerOpen && pickerPanel === 'gif' }"
              aria-label="Tìm GIF"
              @click="openPicker('gif')"
            >
              <AppIcon name="gifImage" :size="16" />
              <span>GIF</span>
            </button>
            <SocialEmojiPicker
              v-if="pickerOpen"
              :anchor="pickerWrap"
              :panel="pickerPanel"
              @update:panel="pickerPanel = $event"
              @pick="insertEmoji"
              @pick-sticker="insertSticker"
              @pick-gif="insertGif"
              @close="pickerOpen = false"
            />
          </div>

          <button type="button" class="comment-composer__tool" @click="fileInput?.click()">
            <AppIcon name="paperclip" :size="16" />
            <span>Ảnh/File</span>
          </button>
          <input
            ref="fileInput"
            type="file"
            multiple
            class="comment-composer__file-input"
            accept="image/*,.pdf,.doc,.docx,.xlsx,.xls"
            @change="onFilesChosen"
          />
        </div>

        <button
          type="button"
          class="comment-composer__submit"
          :disabled="submitting || !canSubmit()"
          @click="submit"
        >
          {{ submitting ? 'Đang gửi...' : submitLabel }}
        </button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.comment-composer {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-width: 0;
  overflow: visible;
}

.comment-composer__prompt-row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.comment-composer__avatar {
  width: 32px;
  height: 32px;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
}

.comment-composer__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 0.8125rem;
  font-weight: 600;
}

.comment-composer__prompt {
  flex: 1;
  min-width: 0;
  border: none;
  padding: var(--space-2) var(--space-4);
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-family: inherit;
  font-size: 0.875rem;
  text-align: left;
  cursor: pointer;
}

.comment-composer__prompt:hover {
  background: color-mix(in srgb, var(--color-surface-muted) 80%, var(--color-border));
}

.comment-composer__prompt-close {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  border: none;
  background: none;
  color: var(--color-text-muted);
  border-radius: var(--radius-full);
  cursor: pointer;
}

.comment-composer__prompt-close:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.comment-composer__gifs {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}

.comment-composer__gif-tile {
  position: relative;
  width: 5rem;
  height: 5rem;
  border-radius: var(--radius-md);
  overflow: hidden;
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.comment-composer__gif-tile img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.comment-composer__gif-remove {
  position: absolute;
  top: 0.25rem;
  right: 0.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.25rem;
  height: 1.25rem;
  border: none;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, #000000 58%, transparent);
  color: #fff;
  cursor: pointer;
}

.comment-composer__actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.comment-composer__actions-left {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  min-width: 0;
}

.comment-composer__emoji-wrap {
  display: flex;
  align-items: center;
  flex-wrap: nowrap;
  gap: var(--space-1);
  position: relative;
  flex-shrink: 0;
}

.comment-composer__tool {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  border: none;
  background: none;
  color: var(--color-text-muted);
  cursor: pointer;
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  padding: var(--space-1) var(--space-2);
  border-radius: var(--radius-md);
}

.comment-composer__tool:hover {
  background: var(--color-surface-muted);
}

.comment-composer__tool--on {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.comment-composer__file-input {
  display: none;
}

.comment-composer__submit {
  border: none;
  background: var(--color-primary);
  color: var(--color-on-primary);
  padding: var(--space-2) var(--space-4);
  border-radius: var(--radius-md);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
  flex-shrink: 0;
}

.comment-composer__submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 480px) {
  .comment-composer__tool span {
    display: none;
  }
}
</style>
