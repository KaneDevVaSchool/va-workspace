<script setup>
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { MAX_POST_ATTACHMENTS, VISIBLE_IMAGE_LIMIT } from '../constants/attachments.js';
import SocialEmojiPicker from './SocialEmojiPicker.vue';
import SocialImageGrid from './SocialImageGrid.vue';
import SocialPollDialog from './SocialPollDialog.vue';
import SocialPostEditor from './SocialPostEditor.vue';

const props = defineProps({
  authorAvatarUrl: { type: String, default: null },
  authorName: { type: String, default: '' },
  defaultScope: { type: String, default: 'company' },
  departmentName: { type: String, default: '' },
  wallUserId: { type: Number, default: null },
  wallUserName: { type: String, default: '' },
  groupId: { type: Number, default: null },
});

const emit = defineEmits(['posted']);

const auth = useAuthStore();
const content = ref('');
const editorEmpty = ref(true);
const files = ref([]);
const previewUrls = ref([]);
const submitting = ref(false);
const expanded = ref(false);
const fileInput = ref(null);
const editorRef = ref(null);
const emojiPickerOpen = ref(false);
const emojiWrap = ref(null);
const asSystemAnnouncement = ref(false);
const postScope = ref(props.defaultScope);
const pollDialogOpen = ref(false);
const isDepartmentWall = props.defaultScope === 'department';
const isPersonalWall = props.defaultScope === 'personal';
const composerPlaceholder = computed(() => {
  if (isPersonalWall && props.wallUserName && props.wallUserId !== auth.user?.id) {
    return `Viết gì đó lên tường của ${props.wallUserName}...`;
  }
  return 'Bạn đang nghĩ gì?';
});

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

function onPollClick() {
  pollDialogOpen.value = true;
}

function onPollPosted(post) {
  pollDialogOpen.value = false;
  emit('posted', post);
}

function insertEmoji(emoji) {
  editorRef.value?.insertContent(emoji);
}

function revokePreviews() {
  previewUrls.value.forEach((url) => URL.revokeObjectURL(url));
  previewUrls.value = [];
}

function closeComposer() {
  content.value = '';
  files.value = [];
  revokePreviews();
  emojiPickerOpen.value = false;
  asSystemAnnouncement.value = false;
  postScope.value = props.defaultScope;
  expanded.value = false;
}

function toggleDepartmentScope() {
  postScope.value = postScope.value === 'department' ? 'company' : 'department';
  if (postScope.value === 'department') asSystemAnnouncement.value = false;
}

function onFilesChosen(event) {
  const chosen = Array.from(event.target.files ?? []);
  if (files.value.length + chosen.length > MAX_POST_ATTACHMENTS) {
    showClientToast('error', `Chỉ được đính kèm tối đa ${MAX_POST_ATTACHMENTS} tệp mỗi bài viết.`);
    return;
  }
  files.value = [...files.value, ...chosen];
  previewUrls.value = [
    ...previewUrls.value,
    ...chosen.map((file) => (isImageFile(file) ? URL.createObjectURL(file) : '')),
  ];
  event.target.value = '';
}

function removeFile(index) {
  const url = previewUrls.value[index];
  if (url) URL.revokeObjectURL(url);
  files.value = files.value.filter((_, i) => i !== index);
  previewUrls.value = previewUrls.value.filter((_, i) => i !== index);
}

function isImageFile(file) {
  return file.type.startsWith('image/');
}

const imagePreviews = computed(() =>
  files.value
    .map((file, index) => ({ file, index, url: previewUrls.value[index], name: file.name }))
    .filter((item) => item.url),
);

const extraImagePreviews = computed(() => imagePreviews.value.slice(VISIBLE_IMAGE_LIMIT));

const otherFiles = computed(() =>
  files.value
    .map((file, index) => ({ file, index, name: file.name }))
    .filter((item) => !isImageFile(item.file)),
);

function removeImagePreview(imageIndex) {
  const item = imagePreviews.value[imageIndex];
  if (item) removeFile(item.index);
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
    if (asSystemAnnouncement.value) form.append('as_system_announcement', '1');
    form.append('post_scope', postScope.value);
    if (postScope.value === 'personal' && props.wallUserId) {
      form.append('wall_user_id', String(props.wallUserId));
    }
    if (postScope.value === 'group' && props.groupId) {
      form.append('group_id', String(props.groupId));
    }
    files.value.forEach((file) => form.append('attachments[]', file));

    const { data } = await window.axios.post('/api/social/posts', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    emit('posted', data.post);
    content.value = '';
    files.value = [];
    revokePreviews();
    emojiPickerOpen.value = false;
    asSystemAnnouncement.value = false;
    postScope.value = props.defaultScope;
    expanded.value = false;
    let successMessage = 'Đã đăng bài viết.';
    if (data.post?.pin_scope === 'system') {
      successMessage = 'Đã đăng thông báo quan trọng.';
    } else if (data.post?.post_scope === 'department') {
      successMessage = 'Đã đăng bài viết lên tường phòng ban.';
    } else if (data.post?.post_scope === 'personal') {
      successMessage = data.post?.wall_user?.id === auth.user?.id
        ? 'Đã đăng bài viết lên tường của bạn.'
        : `Đã đăng bài viết lên tường của ${data.post?.wall_user?.name ?? 'đồng nghiệp'}.`;
    } else if (data.post?.post_scope === 'group') {
      successMessage = `Đã đăng bài viết lên tường nhóm ${data.post?.group?.name ?? ''}.`.trim();
    }
    showClientToast('success', successMessage);
  } catch (error) {
    const message = error?.response?.data?.message ?? 'Không thể đăng bài viết.';
    showClientToast('error', message);
  } finally {
    submitting.value = false;
  }
}

defineExpose({ expand });

onBeforeUnmount(() => {
  revokePreviews();
});
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
        {{ composerPlaceholder }}
      </button>

      <div v-else class="composer__author">
        <div class="composer__author-name">{{ authorName }}</div>
      </div>
    </div>

    <template v-if="expanded">
      <SocialPostEditor
        ref="editorRef"
        v-model="content"
        :placeholder="composerPlaceholder"
        enable-mentions
        @is-empty="editorEmpty = $event"
        @close="closeComposer"
      />

      <div v-if="imagePreviews.length > 0" class="composer__images">
        <SocialImageGrid
          compact
          removable
          :images="imagePreviews"
          @remove="removeImagePreview"
        />
      </div>

      <div v-if="otherFiles.length > 0 || extraImagePreviews.length > 0" class="composer__files">
        <div v-for="item in extraImagePreviews" :key="`img-${item.index}`" class="composer__file-chip">
          <AppIcon name="fileText" :size="14" />
          <span class="composer__file-name">{{ item.name }}</span>
          <button
            type="button"
            class="composer__file-remove"
            aria-label="Bỏ tệp đính kèm"
            @click="removeFile(item.index)"
          >
            <AppIcon name="close" :size="14" />
          </button>
        </div>
        <div v-for="item in otherFiles" :key="item.index" class="composer__file-chip">
          <AppIcon name="paperclip" :size="14" />
          <span class="composer__file-name">{{ item.name }}</span>
          <button
            type="button"
            class="composer__file-remove"
            aria-label="Bỏ tệp đính kèm"
            @click="removeFile(item.index)"
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

        <button
          type="button"
          class="composer__attach-btn"
          :class="{ 'composer__attach-btn--on': pollDialogOpen }"
          :aria-pressed="pollDialogOpen"
          @click="onPollClick"
        >
          <AppIcon name="listChecks" :size="18" />
          <span>Bình chọn</span>
        </button>

        <button
          v-if="expanded && !isDepartmentWall && !isPersonalWall && departmentName"
          type="button"
          class="composer__scope-toggle"
          :class="{ 'composer__scope-toggle--on': postScope === 'department' }"
          :aria-pressed="postScope === 'department'"
          @click="toggleDepartmentScope"
        >
          <AppIcon name="building" :size="16" />
          <span>{{ postScope === 'department' ? `Chỉ phòng ban ${departmentName}` : 'Đăng cho phòng ban' }}</span>
        </button>

        <button
          v-if="expanded && auth.showSuperAdminNav && postScope === 'company' && !isPersonalWall"
          type="button"
          class="composer__system-toggle"
          :class="{ 'composer__system-toggle--on': asSystemAnnouncement }"
          :aria-pressed="asSystemAnnouncement"
          @click="asSystemAnnouncement = !asSystemAnnouncement"
        >
          <AppIcon name="shield" :size="16" />
          <span>Đăng thông báo quan trọng</span>
        </button>
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

  <SocialPollDialog
    :open="pollDialogOpen"
    :default-scope="postScope"
    :department-name="departmentName"
    :wall-user-id="wallUserId"
    :wall-user-name="wallUserName"
    @close="pollDialogOpen = false"
    @posted="onPollPosted"
  />
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

.composer__images {
  min-width: 0;
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
  flex-wrap: wrap;
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

.composer__attach-btn--on {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.composer__scope-toggle {
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
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-full);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.composer__scope-toggle:hover {
  background: var(--color-surface-muted);
}

.composer__scope-toggle--on {
  background: var(--color-primary-surface);
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1px var(--color-primary);
}

.composer__system-toggle {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  border: none;
  background: none;
  color: var(--color-info-tint-fg);
  cursor: pointer;
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-full);
  box-shadow: inset 0 0 0 1px var(--color-info-tint-border);
}

.composer__system-toggle:hover {
  background: var(--color-info-tint-bg);
}

.composer__system-toggle--on {
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
  box-shadow: inset 0 0 0 1px var(--color-info);
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
