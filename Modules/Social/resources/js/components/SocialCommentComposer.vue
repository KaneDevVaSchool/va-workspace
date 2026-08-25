<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import SocialEmojiPicker from './SocialEmojiPicker.vue';
import SocialPostEditor from './SocialPostEditor.vue';

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
const submitting = ref(false);
const emojiOpen = ref(false);
const expanded = ref(props.autoExpand || Boolean(props.mentionedUser));
const fileInput = ref(null);
const editorRef = ref(null);
const emojiWrap = ref(null);

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
  emojiOpen.value = false;
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

function onFilesChosen(event) {
  const chosen = Array.from(event.target.files ?? []);
  if (files.value.length + chosen.length > 5) {
    showClientToast('error', 'Chỉ được đính kèm tối đa 5 tệp mỗi bình luận.');
    return;
  }
  files.value = [...files.value, ...chosen];
  event.target.value = '';
}

function removeFile(index) {
  files.value = files.value.filter((_, i) => i !== index);
}

function isImageFile(file) {
  return file.type.startsWith('image/');
}

function canSubmit() {
  return !editorEmpty.value || files.value.length > 0;
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

      <div v-if="files.length > 0" class="comment-composer__files">
        <div v-for="(file, index) in files" :key="index" class="comment-composer__file-chip">
          <AppIcon :name="isImageFile(file) ? 'fileText' : 'paperclip'" :size="14" />
          <span class="comment-composer__file-name">{{ file.name }}</span>
          <button
            type="button"
            class="comment-composer__file-remove"
            aria-label="Bỏ tệp đính kèm"
            @click="removeFile(index)"
          >
            <AppIcon name="close" :size="14" />
          </button>
        </div>
      </div>

      <div class="comment-composer__actions">
        <div class="comment-composer__actions-left">
          <div ref="emojiWrap" class="comment-composer__emoji-wrap">
            <button
              type="button"
              class="comment-composer__tool"
              aria-label="Chèn emoji"
              @click="emojiOpen = !emojiOpen"
            >
              😊
              <span>Emoji</span>
            </button>
            <SocialEmojiPicker
              v-if="emojiOpen"
              :anchor="emojiWrap"
              @pick="insertEmoji"
              @close="emojiOpen = false"
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

.comment-composer__files {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}

.comment-composer__file-chip {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  background: var(--color-surface-muted);
  border-radius: var(--radius-md);
  padding: var(--space-1) var(--space-2);
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.comment-composer__file-name {
  max-width: 10rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.comment-composer__file-remove {
  display: flex;
  align-items: center;
  border: none;
  background: none;
  cursor: pointer;
  color: var(--color-text-muted);
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
  position: relative;
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
