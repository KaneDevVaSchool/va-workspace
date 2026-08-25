<script setup>
import { nextTick, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import SocialEmojiPicker from './SocialEmojiPicker.vue';
import SocialPostEditor from './SocialPostEditor.vue';

const props = defineProps({
  authorAvatarUrl: { type: String, default: null },
  authorName: { type: String, default: '' },
});

const emit = defineEmits(['posted']);

const content = ref('');
const editorEmpty = ref(true);
const files = ref([]);
const submitting = ref(false);
const expanded = ref(false);
const fileInput = ref(null);
const editorRef = ref(null);
const emojiPickerOpen = ref(false);
const emojiWrap = ref(null);

const avatarInitial = () => props.authorName?.trim().charAt(0).toUpperCase() || '?';

async function expand(options = {}) {
  expanded.value = true;
  await nextTick();
  if (options.openFiles) fileInput.value?.click();
  if (options.openEmoji) emojiPickerOpen.value = true;
}

function onAttachClick() {
  if (!expanded.value) {
    expand({ openFiles: true });
    return;
  }
  fileInput.value?.click();
}

function onEmojiClick() {
  if (!expanded.value) {
    expand({ openEmoji: true });
    return;
  }
  emojiPickerOpen.value = !emojiPickerOpen.value;
}

function insertEmoji(emoji) {
  editorRef.value?.insertContent(emoji);
}

function closeComposer() {
  content.value = '';
  files.value = [];
  emojiPickerOpen.value = false;
  expanded.value = false;
}

function onFilesChosen(event) {
  const chosen = Array.from(event.target.files ?? []);
  if (files.value.length + chosen.length > 5) {
    showClientToast('error', 'Chỉ được đính kèm tối đa 5 tệp mỗi bài viết.');
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

async function submit() {
  if (editorEmpty.value && files.value.length === 0) {
    showClientToast('error', 'Bài viết phải có nội dung hoặc ít nhất 1 tệp đính kèm.');
    return;
  }

  submitting.value = true;
  try {
    const form = new FormData();
    if (!editorEmpty.value) form.append('content', content.value);
    files.value.forEach((file) => form.append('attachments[]', file));

    const { data } = await window.axios.post('/api/social/posts', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    emit('posted', data.post);
    content.value = '';
    files.value = [];
    emojiPickerOpen.value = false;
    expanded.value = false;
    showClientToast('success', 'Đã đăng bài viết.');
  } catch (error) {
    const message = error?.response?.data?.message ?? 'Không thể đăng bài viết.';
    showClientToast('error', message);
  } finally {
    submitting.value = false;
  }
}

defineExpose({ expand });
</script>

<template>
  <div
    id="social-composer"
    class="composer"
    :class="{ 'composer--expanded': expanded }"
  >
    <div class="composer__row">
      <img
        v-if="authorAvatarUrl"
        class="composer__avatar"
        :src="authorAvatarUrl"
        :alt="`Ảnh đại diện của ${authorName}`"
      />
      <div v-else class="composer__avatar composer__avatar--placeholder">
        {{ avatarInitial() }}
      </div>

      <button
        v-if="!expanded"
        type="button"
        class="composer__prompt"
        @click="expand()"
      >
        Bạn đang nghĩ gì?
      </button>

      <div v-else class="composer__author">
        <div class="composer__author-name">{{ authorName }}</div>
      </div>
    </div>

    <template v-if="expanded">
      <SocialPostEditor
        ref="editorRef"
        v-model="content"
        placeholder="Bạn đang nghĩ gì?"
        enable-mentions
        @is-empty="editorEmpty = $event"
        @close="closeComposer"
      />

      <div v-if="files.length > 0" class="composer__files">
        <div v-for="(file, index) in files" :key="index" class="composer__file-chip">
          <AppIcon :name="isImageFile(file) ? 'fileText' : 'paperclip'" :size="14" />
          <span class="composer__file-name">{{ file.name }}</span>
          <button
            type="button"
            class="composer__file-remove"
            aria-label="Bỏ tệp đính kèm"
            @click="removeFile(index)"
          >
            <AppIcon name="close" :size="14" />
          </button>
        </div>
      </div>
    </template>

    <div class="composer__actions">
      <div class="composer__actions-left">
        <button type="button" class="composer__attach-btn" @click="onAttachClick">
          <AppIcon name="paperclip" :size="18" />
          <span>Ảnh/File</span>
        </button>
        <input
          ref="fileInput"
          type="file"
          multiple
          class="composer__file-input"
          accept="image/*,.pdf,.doc,.docx,.xlsx,.xls"
          @change="onFilesChosen"
        />

        <div ref="emojiWrap" class="composer__emoji-wrap">
          <button
            type="button"
            class="composer__attach-btn"
            aria-label="Chèn emoji"
            @click="onEmojiClick"
          >
            <span class="composer__emoji-icon">😊</span>
            <span>Emoji</span>
          </button>
          <SocialEmojiPicker
            v-if="emojiPickerOpen"
            :anchor="emojiWrap"
            @pick="insertEmoji"
            @close="emojiPickerOpen = false"
          />
        </div>
      </div>

      <button
        v-if="expanded"
        type="button"
        class="composer__submit-btn"
        :disabled="submitting"
        @click="submit"
      >
        {{ submitting ? 'Đang đăng...' : 'Đăng bài' }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.composer {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  width: 100%;
  flex-shrink: 0;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  box-shadow: var(--shadow-sm);
  overflow: visible;
}

.composer__row {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.composer__avatar {
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
}

.composer__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-weight: 700;
}

.composer__prompt {
  flex: 1;
  min-width: 0;
  border: none;
  padding: var(--space-2) var(--space-4);
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-family: inherit;
  font-size: 0.9375rem;
  text-align: left;
  cursor: pointer;
}

.composer__prompt:hover {
  background: color-mix(in srgb, var(--color-surface-muted) 80%, var(--color-border));
}

.composer__author-name {
  font-weight: 600;
  color: var(--color-text);
}

.composer__files {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}

.composer__file-chip {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  background: var(--color-surface-muted);
  border-radius: var(--radius-md);
  padding: var(--space-1) var(--space-2);
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.composer__file-name {
  max-width: 12rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.composer__file-remove {
  display: flex;
  align-items: center;
  border: none;
  background: none;
  cursor: pointer;
  color: var(--color-text-muted);
}

.composer__actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  box-shadow: 0 -1px 0 var(--color-border);
  padding-top: var(--space-3);
}

.composer__actions-left {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  min-width: 0;
}

.composer__emoji-wrap {
  position: relative;
}

.composer__emoji-icon {
  font-size: 1.125rem;
  line-height: 1;
}

.composer__attach-btn {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  border: none;
  background: none;
  color: var(--color-text-muted);
  cursor: pointer;
  font-family: inherit;
  font-size: 0.875rem;
  font-weight: 600;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
}

.composer__attach-btn:hover {
  background: var(--color-surface-muted);
}

.composer__file-input {
  display: none;
}

.composer__submit-btn {
  border: none;
  background: var(--color-primary);
  color: var(--color-on-primary);
  padding: var(--space-2) var(--space-5);
  border-radius: var(--radius-md);
  font-family: inherit;
  font-size: 0.9375rem;
  font-weight: 600;
  cursor: pointer;
  flex-shrink: 0;
}

.composer__submit-btn:hover {
  background: var(--color-primary-hover);
}

.composer__submit-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
