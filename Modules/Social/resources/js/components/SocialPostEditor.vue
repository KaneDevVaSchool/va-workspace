<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { Editor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import TextStyle from '@tiptap/extension-text-style';
import Color from '@tiptap/extension-color';
import LinkExtension from '@tiptap/extension-link';
import TextAlign from '@tiptap/extension-text-align';
import Placeholder from '@tiptap/extension-placeholder';
import AppIcon from '@/components/AppIcon.vue';
import { FontSize } from '../lib/tiptapFontSize.js';
import { MentionNode } from '../lib/tiptapMention.js';
import SocialMentionPicker from './SocialMentionPicker.vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'Bạn đang nghĩ gì...' },
  compact: { type: Boolean, default: false },
  showClose: { type: Boolean, default: true },
  enableMentions: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'isEmpty', 'close']);
const instanceId = `se-${Math.random().toString(36).slice(2, 8)}`;

const mentionOpen = ref(false);
const mentionQuery = ref('');
const mentionUsers = ref([]);
const mentionIndex = ref(0);
let mentionTimer = null;
let editor = null;

function closeMention() {
  mentionOpen.value = false;
  mentionQuery.value = '';
  mentionUsers.value = [];
  mentionIndex.value = 0;
}

function fetchMentions(query) {
  clearTimeout(mentionTimer);
  mentionTimer = setTimeout(async () => {
    try {
      const { data } = await window.axios.get('/api/social/mentions', { params: { q: query } });
      mentionUsers.value = data.users ?? [];
      mentionIndex.value = 0;
    } catch {
      mentionUsers.value = [];
    }
  }, 180);
}

function detectMention() {
  if (!props.enableMentions || !editor) {
    closeMention();
    return;
  }
  const { from } = editor.state.selection;
  if (editor.isActive('mention')) {
    closeMention();
    return;
  }
  const text = editor.state.doc.textBetween(Math.max(0, from - 40), from, '\0');
  const match = text.match(/@([^\s@]*)$/);
  if (!match) {
    closeMention();
    return;
  }
  mentionQuery.value = match[1];
  mentionOpen.value = true;
  fetchMentions(match[1]);
}

function insertMention(user, { replaceQuery = true, suffix = ' ' } = {}) {
  if (!editor || !user) return;
  const chain = editor.chain().focus();
  if (replaceQuery) {
    const { from } = editor.state.selection;
    const text = editor.state.doc.textBetween(Math.max(0, from - 40), from, '\0');
    const match = text.match(/@([^\s@]*)$/);
    if (match) {
      chain.deleteRange({ from: from - match[0].length, to: from });
    }
  }
  chain
    .insertContent([
      { type: 'mention', attrs: { id: String(user.id), label: user.name } },
      { type: 'text', text: suffix },
    ])
    .run();
  closeMention();
}

function handleMentionKeydown(event) {
  if (!mentionOpen.value) return false;
  if (event.key === 'ArrowDown') {
    mentionIndex.value = Math.min(mentionIndex.value + 1, Math.max(mentionUsers.value.length - 1, 0));
    return true;
  }
  if (event.key === 'ArrowUp') {
    mentionIndex.value = Math.max(mentionIndex.value - 1, 0);
    return true;
  }
  if (event.key === 'Enter' || event.key === 'Tab') {
    const user = mentionUsers.value[mentionIndex.value];
    if (user) insertMention(user, { replaceQuery: true });
    return true;
  }
  if (event.key === 'Escape') {
    closeMention();
    return true;
  }
  return false;
}

const TEXT_COLORS = [
  { label: 'Mặc định', value: null },
  { label: 'Chữ thường', value: '#1a1a1a' },
  { label: 'Chữ nhạt', value: '#6b6b6f' },
  { label: 'Đỏ chính', value: '#9a0036' },
  { label: 'Hồng', value: '#ff5b7a' },
  { label: 'Cam', value: '#f97316' },
  { label: 'Vàng', value: '#eab308' },
  { label: 'Thành công', value: '#1a9c5c' },
  { label: 'Xanh lá', value: '#16a34a' },
  { label: 'Xanh ngọc', value: '#0d9488' },
  { label: 'Thông tin', value: '#2563eb' },
  { label: 'Tím', value: '#7c3aed' },
  { label: 'Hồng tím', value: '#c026d3' },
  { label: 'Cảnh báo', value: '#d98c00' },
  { label: 'Nguy hiểm', value: '#ff0043' },
];

const FONT_SIZES = [
  { label: 'Rất nhỏ', value: '12px' },
  { label: 'Nhỏ', value: '14px' },
  { label: 'Vừa', value: null },
  { label: 'Hơi lớn', value: '20px' },
  { label: 'Lớn', value: '24px' },
  { label: 'Rất lớn', value: '32px' },
];

const FONT_SIZE_MIN = 8;
const FONT_SIZE_MAX = 48;

const colorPickerOpen = ref(false);
const sizePickerOpen = ref(false);
const linkPickerOpen = ref(false);
const linkUrl = ref('');
const customColor = ref('#9a0036');
const customFontSize = ref(16);
const root = ref(null);

editor = new Editor({
  content: props.modelValue,
  extensions: [
    StarterKit.configure({ heading: { levels: [2, 3] } }),
    TextStyle,
    Color,
    FontSize,
    MentionNode,
    TextAlign.configure({ types: ['heading', 'paragraph'] }),
    LinkExtension.configure({
      openOnClick: false,
      autolink: true,
      protocols: ['http', 'https', 'mailto'],
      validate: (href) => /^(https?:|mailto:)/.test(href),
    }),
    Placeholder.configure({ placeholder: props.placeholder }),
  ],
  editorProps: {
    attributes: { class: 'post-editor__content' },
    handleKeyDown: (_view, event) => handleMentionKeydown(event),
  },
  onUpdate: ({ editor: e }) => {
    emit('update:modelValue', e.getHTML());
    emit('isEmpty', e.isEmpty);
    detectMention();
  },
  onSelectionUpdate: () => {
    detectMention();
  },
});

emit('isEmpty', editor.isEmpty);

watch(
  () => props.modelValue,
  (value) => {
    if (value === editor.getHTML()) return;
    editor.commands.setContent(value ?? '', false);
  },
);

function insertContent(text) {
  editor.chain().focus().insertContent(text).run();
}

function focusEditor() {
  editor.chain().focus().run();
}

defineExpose({ insertContent, insertMention, focus: focusEditor });

function toggleColorPicker() {
  sizePickerOpen.value = false;
  linkPickerOpen.value = false;
  colorPickerOpen.value = !colorPickerOpen.value;
}

function pickColor(value) {
  if (value) {
    editor.chain().focus().setColor(value).run();
  } else {
    editor.chain().focus().unsetColor().run();
  }
  colorPickerOpen.value = false;
}

function pickCustomColor() {
  editor.chain().focus().setColor(customColor.value).run();
  colorPickerOpen.value = false;
}

function toggleSizePicker() {
  colorPickerOpen.value = false;
  linkPickerOpen.value = false;
  sizePickerOpen.value = !sizePickerOpen.value;
}

function pickFontSize(value) {
  if (value) {
    editor.chain().focus().setFontSize(value).run();
  } else {
    editor.chain().focus().unsetFontSize().run();
  }
  sizePickerOpen.value = false;
}

function applyCustomFontSize() {
  const size = Math.min(Math.max(Math.round(Number(customFontSize.value) || 16), FONT_SIZE_MIN), FONT_SIZE_MAX);
  customFontSize.value = size;
  editor.chain().focus().setFontSize(`${size}px`).run();
  sizePickerOpen.value = false;
}

function toggleLinkPicker() {
  colorPickerOpen.value = false;
  sizePickerOpen.value = false;
  if (!linkPickerOpen.value) {
    linkUrl.value = editor.getAttributes('link').href ?? '';
  }
  linkPickerOpen.value = !linkPickerOpen.value;
}

function applyLink() {
  const url = linkUrl.value.trim();
  if (!url) {
    linkPickerOpen.value = false;
    return;
  }
  if (!/^https?:\/\//.test(url)) {
    editor.chain().focus().setLink({ href: `https://${url}` }).run();
  } else {
    editor.chain().focus().setLink({ href: url }).run();
  }
  linkUrl.value = '';
  linkPickerOpen.value = false;
}

function removeLink() {
  editor.chain().focus().unsetLink().run();
  linkPickerOpen.value = false;
}

function onDocumentClick(event) {
  if (root.value && !root.value.contains(event.target)) {
    colorPickerOpen.value = false;
    sizePickerOpen.value = false;
    linkPickerOpen.value = false;
    closeMention();
  }
}

document.addEventListener('click', onDocumentClick, true);

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick, true);
  clearTimeout(mentionTimer);
  editor.destroy();
});
</script>

<template>
  <div ref="root" class="post-editor" :class="{ 'post-editor--compact': compact }">
    <div class="post-editor__toolbar" role="toolbar" aria-label="Định dạng văn bản">
      <button
        type="button"
        class="post-editor__btn"
        :class="{ 'post-editor__btn--active': editor.isActive('bold') }"
        aria-label="In đậm"
        @click="editor.chain().focus().toggleBold().run()"
      >
        <AppIcon name="bold" :size="16" />
      </button>
      <button
        type="button"
        class="post-editor__btn"
        :class="{ 'post-editor__btn--active': editor.isActive('italic') }"
        aria-label="In nghiêng"
        @click="editor.chain().focus().toggleItalic().run()"
      >
        <AppIcon name="italic" :size="16" />
      </button>
      <button
        type="button"
        class="post-editor__btn"
        :class="{ 'post-editor__btn--active': editor.isActive('heading', { level: 2 }) }"
        aria-label="Tiêu đề"
        @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
      >
        <AppIcon name="heading" :size="16" />
      </button>

      <div class="post-editor__divider"></div>

      <button
        type="button"
        class="post-editor__btn"
        :class="{ 'post-editor__btn--active': editor.isActive('bulletList') }"
        aria-label="Danh sách chấm đầu dòng"
        @click="editor.chain().focus().toggleBulletList().run()"
      >
        <AppIcon name="listBullet" :size="16" />
      </button>
      <button
        type="button"
        class="post-editor__btn"
        :class="{ 'post-editor__btn--active': editor.isActive('orderedList') }"
        aria-label="Danh sách đánh số"
        @click="editor.chain().focus().toggleOrderedList().run()"
      >
        <AppIcon name="listNumbered" :size="16" />
      </button>

      <div class="post-editor__divider"></div>

      <button
        type="button"
        class="post-editor__btn"
        :class="{ 'post-editor__btn--active': editor.isActive({ textAlign: 'left' }) }"
        aria-label="Canh trái"
        @click="editor.chain().focus().setTextAlign('left').run()"
      >
        <AppIcon name="alignLeft" :size="16" />
      </button>
      <button
        type="button"
        class="post-editor__btn"
        :class="{ 'post-editor__btn--active': editor.isActive({ textAlign: 'center' }) }"
        aria-label="Canh giữa"
        @click="editor.chain().focus().setTextAlign('center').run()"
      >
        <AppIcon name="alignCenter" :size="16" />
      </button>
      <button
        type="button"
        class="post-editor__btn"
        :class="{ 'post-editor__btn--active': editor.isActive({ textAlign: 'right' }) }"
        aria-label="Canh phải"
        @click="editor.chain().focus().setTextAlign('right').run()"
      >
        <AppIcon name="alignRight" :size="16" />
      </button>

      <div class="post-editor__divider"></div>

      <div class="post-editor__popover-wrap">
        <button
          type="button"
          class="post-editor__btn"
          aria-label="Cỡ chữ"
          @click="toggleSizePicker"
        >
          <AppIcon name="heading" :size="16" />
        </button>
        <div v-if="sizePickerOpen" class="post-editor__popover post-editor__popover--sizes">
          <button
            v-for="size in FONT_SIZES"
            :key="size.label"
            type="button"
            class="post-editor__size-option"
            @click="pickFontSize(size.value)"
          >
            {{ size.label }}
          </button>
          <label class="post-editor__custom-size">
            <span>Cỡ chữ (px)</span>
            <div class="post-editor__custom-size-row">
              <input
                v-model.number="customFontSize"
                type="number"
                :min="FONT_SIZE_MIN"
                :max="FONT_SIZE_MAX"
                class="post-editor__custom-size-input"
                @keydown.enter.prevent="applyCustomFontSize"
              />
              <button type="button" class="post-editor__custom-size-apply" @click="applyCustomFontSize">
                Áp dụng
              </button>
            </div>
          </label>
        </div>
      </div>

      <div class="post-editor__popover-wrap">
        <button
          type="button"
          class="post-editor__btn"
          aria-label="Màu chữ"
          @click="toggleColorPicker"
        >
          <AppIcon name="textColor" :size="16" />
        </button>
        <div v-if="colorPickerOpen" class="post-editor__popover post-editor__popover--colors">
          <button
            type="button"
            class="post-editor__color-option post-editor__color-option--default"
            @click="pickColor(null)"
          >
            <span class="post-editor__color-dot" style="background: var(--color-text-muted)"></span>
            <span>Mặc định</span>
          </button>
          <div class="post-editor__color-grid">
            <button
              v-for="color in TEXT_COLORS.filter((c) => c.value)"
              :key="color.label"
              type="button"
              class="post-editor__color-swatch"
              :style="{ background: color.value }"
              :aria-label="color.label"
              @click="pickColor(color.value)"
            ></button>
          </div>
          <label class="post-editor__custom-color">
            <input v-model="customColor" type="color" class="post-editor__custom-color-input" />
            <span>Tuỳ chỉnh...</span>
            <button type="button" class="post-editor__custom-color-apply" @click="pickCustomColor">
              Áp dụng
            </button>
          </label>
        </div>
      </div>

      <div class="post-editor__popover-wrap">
        <button
          type="button"
          class="post-editor__btn"
          :class="{ 'post-editor__btn--active': editor.isActive('link') }"
          aria-label="Chèn liên kết"
          @click="toggleLinkPicker"
        >
          <AppIcon name="link" :size="16" />
        </button>
        <div v-if="linkPickerOpen" class="post-editor__popover post-editor__popover--link">
          <label class="post-editor__link-label" :for="`${instanceId}-link`">Địa chỉ liên kết</label>
          <input
            :id="`${instanceId}-link`"
            v-model="linkUrl"
            type="text"
            class="post-editor__link-input"
            placeholder="vidu.com"
            @keydown.enter.prevent="applyLink"
          />
          <div class="post-editor__link-actions">
            <button
              v-if="editor.isActive('link')"
              type="button"
              class="post-editor__link-remove"
              @click="removeLink"
            >
              Gỡ liên kết
            </button>
            <button type="button" class="post-editor__link-apply" @click="applyLink">Áp dụng</button>
          </div>
        </div>
      </div>

      <button
        v-if="showClose"
        type="button"
        class="post-editor__btn post-editor__btn--close"
        :aria-label="compact ? 'Đóng khung bình luận' : 'Đóng khung soạn bài'"
        @click="emit('close')"
      >
        <AppIcon name="close" :size="16" />
      </button>
    </div>

    <div class="post-editor__body-wrap">
      <label class="post-editor__label" :for="`${instanceId}-input`">
        {{ compact ? 'Nội dung bình luận' : 'Nội dung bài viết' }}
      </label>
      <EditorContent :id="`${instanceId}-input`" :editor="editor" class="post-editor__body" />
      <SocialMentionPicker
        v-if="mentionOpen && enableMentions"
        :users="mentionUsers"
        :active-index="mentionIndex"
        :query="mentionQuery"
        @pick="insertMention($event, { replaceQuery: true })"
      />
    </div>
  </div>
</template>

<style scoped>
.post-editor {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.post-editor__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-1);
  padding: var(--space-1);
  background: var(--color-surface-muted);
  border-radius: var(--radius-md);
}

.post-editor__btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border: none;
  background: none;
  color: var(--color-text-muted);
  cursor: pointer;
  border-radius: var(--radius-md);
  flex-shrink: 0;
}

.post-editor__btn:hover {
  background: var(--color-surface);
}

.post-editor__btn--active {
  color: var(--color-primary);
  background: var(--color-primary-surface);
}

.post-editor__btn--close {
  margin-left: auto;
}

.post-editor__btn--close:hover {
  color: var(--color-danger);
  background: var(--color-danger-tint-bg);
}

.post-editor__divider {
  width: 1px;
  height: 20px;
  background: var(--color-border);
  flex-shrink: 0;
}

.post-editor__popover-wrap {
  position: relative;
}

.post-editor__popover {
  position: absolute;
  top: calc(100% + var(--space-2));
  left: 0;
  z-index: 30;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-lg);
  padding: var(--space-2);
}

.post-editor__popover--sizes {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 120px;
}

.post-editor__size-option {
  border: none;
  background: none;
  cursor: pointer;
  padding: var(--space-1) var(--space-2);
  border-radius: var(--radius-md);
  font-size: 0.8125rem;
  color: var(--color-text);
  text-align: left;
}

.post-editor__size-option:hover {
  background: var(--color-surface-muted);
}

.post-editor__custom-size {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  box-shadow: 0 -1px 0 var(--color-border);
  padding-top: var(--space-2);
  margin-top: var(--space-1);
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.post-editor__custom-size-row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.post-editor__custom-size-input {
  width: 100%;
  min-width: 0;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: var(--space-1) var(--space-2);
  font-size: 0.8125rem;
  color: var(--color-text);
  background: var(--color-surface-muted);
}

.post-editor__custom-size-apply {
  flex-shrink: 0;
  border: none;
  background: var(--color-primary);
  color: var(--color-on-primary);
  padding: var(--space-1) var(--space-3);
  border-radius: var(--radius-md);
  font-size: 0.75rem;
  cursor: pointer;
}

.post-editor__popover--colors {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-width: 200px;
}

.post-editor__color-option {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  border: none;
  background: none;
  cursor: pointer;
  padding: var(--space-1) var(--space-2);
  border-radius: var(--radius-md);
  font-size: 0.8125rem;
  color: var(--color-text);
  text-align: left;
}

.post-editor__color-option:hover {
  background: var(--color-surface-muted);
}

.post-editor__color-dot {
  width: 0.75rem;
  height: 0.75rem;
  border-radius: var(--radius-full);
  flex-shrink: 0;
}

.post-editor__color-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: var(--space-1);
}

.post-editor__color-swatch {
  width: 1.5rem;
  height: 1.5rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  cursor: pointer;
  padding: 0;
}

.post-editor__custom-color {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  box-shadow: 0 -1px 0 var(--color-border);
  padding-top: var(--space-2);
  font-size: 0.8125rem;
  color: var(--color-text);
}

.post-editor__custom-color-input {
  width: 1.75rem;
  height: 1.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 0;
  cursor: pointer;
  background: none;
}

.post-editor__custom-color-apply {
  margin-left: auto;
  border: none;
  background: var(--color-primary);
  color: var(--color-on-primary);
  padding: var(--space-1) var(--space-3);
  border-radius: var(--radius-md);
  font-size: 0.75rem;
  cursor: pointer;
}

.post-editor__popover--link {
  width: 220px;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.post-editor__link-label {
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.post-editor__link-input {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: var(--space-2);
  font-size: 0.875rem;
  color: var(--color-text);
  background: var(--color-surface-muted);
}

.post-editor__link-actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

.post-editor__link-remove {
  border: none;
  background: none;
  color: var(--color-danger);
  cursor: pointer;
  font-size: 0.8125rem;
}

.post-editor__link-apply {
  border: none;
  background: var(--color-primary);
  color: var(--color-on-primary);
  padding: var(--space-1) var(--space-3);
  border-radius: var(--radius-md);
  font-size: 0.8125rem;
  cursor: pointer;
}

.post-editor__label {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
}

.post-editor__body-wrap {
  position: relative;
}

.post-editor__body {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  min-height: 8rem;
  max-height: 16rem;
  overflow-y: auto;
}

.post-editor--compact .post-editor__body {
  min-height: 4.5rem;
  max-height: 12rem;
}

.post-editor--compact .post-editor__body :deep(.post-editor__content) {
  font-size: 0.875rem;
  padding: var(--space-2) var(--space-3);
}

.post-editor__body :deep(.mention) {
  color: var(--color-primary);
  font-weight: 600;
}

.post-editor__body :deep(.post-editor__content) {
  padding: var(--space-3);
  outline: none;
  color: var(--color-text);
  font-family: inherit;
  font-size: 1rem;
}

.post-editor__body :deep(p) {
  margin: 0 0 var(--space-2) 0;
}

.post-editor__body :deep(p:last-child) {
  margin-bottom: 0;
}

.post-editor__body :deep(h2) {
  font-size: 1.25rem;
  font-weight: 700;
  margin: 0 0 var(--space-2) 0;
}

.post-editor__body :deep(h3) {
  font-size: 1.0625rem;
  font-weight: 700;
  margin: 0 0 var(--space-2) 0;
}

.post-editor__body :deep(ul),
.post-editor__body :deep(ol) {
  margin: 0 0 var(--space-2) 0;
  padding-left: 1.25rem;
}

.post-editor__body :deep(a) {
  color: var(--color-info);
}

.post-editor__body :deep(.is-editor-empty:first-child::before) {
  content: attr(data-placeholder);
  color: var(--color-text-muted);
  float: left;
  height: 0;
  pointer-events: none;
}

@media (max-width: 480px) {
  .post-editor__toolbar {
    gap: 0;
  }
}
</style>
