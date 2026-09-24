<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { sendTyping, touchViewing } from '../api/chatApi';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { showClientToast } from '@/lib/clientToast';
import { formatTime } from '@/lib/formatTime';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import SocialAnimatedSticker from '@modules/Social/resources/js/components/SocialAnimatedSticker.vue';
import ChatPicker from './ChatPicker.vue';
import { useChatStore } from '../store/chatStore';

const store = useChatStore();
const auth = useAuthStore();

const QUICK_EMOJIS = ['👍', '❤️', '😂', '🎉', '🙏', '🔥'];
const MAX_FILES = 5;
const MAX_FILE_BYTES = 10 * 1024 * 1024;

const draft = ref('');
const files = ref([]);
const fileInput = ref(null);
const sending = ref(false);
const listRef = ref(null);
const inputRef = ref(null);
const pickerOpen = ref(false);
const pickerPanel = ref('emoji');
const replyTo = ref(null);
const editing = ref(null);
const menu = ref(null);
const confirmKind = ref(null);
const confirmMessage = ref(null);
const confirming = ref(false);

const otherUser = computed(() => store.activeConversation?.other_user ?? null);
const peerTyping = computed(() => {
  const until = store.typingByConversation[store.activeConversationId];
  return Boolean(until && until > Date.now());
});
let typingTimer = null;
let viewingTimer = null;
const canSend = computed(() => !sending.value && (draft.value.trim() !== '' || (!editing.value && files.value.length > 0)));

const confirmCopy = computed(() => {
  if (confirmKind.value === 'recall') {
    return {
      title: 'Thu hồi tin nhắn',
      description: 'Tin nhắn sẽ biến mất với cả hai người. Thao tác không hoàn tác được.',
      confirmLabel: 'Thu hồi',
    };
  }
  return {
    title: 'Xoá tin nhắn',
    description: 'Tin nhắn chỉ biến mất ở phía bạn. Người kia vẫn thấy nội dung.',
    confirmLabel: 'Xoá',
  };
});

const timeline = computed(() => {
  const rows = [];
  let lastDay = '';
  for (const message of store.activeMessages) {
    const day = dayKey(message.created_at);
    if (day !== lastDay) {
      rows.push({ kind: 'day', id: `day-${day}`, label: dayLabel(message.created_at) });
      lastDay = day;
    }
    rows.push({ kind: 'message', id: message.id, message });
  }
  return rows;
});

function dayKey(iso) {
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  return `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
}

function dayLabel(iso) {
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  const today = new Date();
  const yesterday = new Date();
  yesterday.setDate(today.getDate() - 1);
  if (dayKey(iso) === dayKey(today.toISOString())) return 'Hôm nay';
  if (dayKey(iso) === dayKey(yesterday.toISOString())) return 'Hôm qua';
  return date.toLocaleDateString('vi-VN', { day: 'numeric', month: 'long' });
}

function scrollToBottom() {
  nextTick(() => {
    if (listRef.value) listRef.value.scrollTop = listRef.value.scrollHeight;
  });
}

onMounted(scrollToBottom);
onBeforeUnmount(() => {
  clearTimeout(typingTimer);
  clearInterval(viewingTimer);
});

watch(
  () => store.activeMessages.length,
  () => {
    if (!editing.value) scrollToBottom();
  },
);

watch(
  () => store.activeConversationId,
  (id) => {
    draft.value = '';
    files.value = [];
    replyTo.value = null;
    editing.value = null;
    pickerOpen.value = false;
    menu.value = null;
    clearInterval(viewingTimer);
    viewingTimer = null;
    if (!id) return;
    touchViewing(id).catch(() => {});
    viewingTimer = window.setInterval(() => touchViewing(id).catch(() => {}), 20000);
  },
  { immediate: true },
);

watch(draft, () => pingTyping());

function onScroll() {
  if (listRef.value && listRef.value.scrollTop < 40) loadOlder();
}

async function loadOlder() {
  const messages = store.activeMessages;
  if (messages.length === 0 || !store.hasMoreByConversation[store.activeConversationId]) return;
  const el = listRef.value;
  const previousHeight = el?.scrollHeight ?? 0;
  await store.fetchMessages(store.activeConversationId, messages[0].id);
  nextTick(() => {
    if (el) el.scrollTop = el.scrollHeight - previousHeight;
  });
}

function isMine(message) {
  return message.sender?.id === auth.user?.id;
}

function showRead(message) {
  if (!isMine(message) || message.recalled_at) return false;
  const lastMine = [...store.activeMessages].reverse().find((item) => isMine(item) && !item.recalled_at);
  if (!lastMine || lastMine.id !== message.id) return false;
  const readAt = store.activeConversation?.other_read_at;
  if (!readAt) return false;
  return new Date(readAt).getTime() >= new Date(message.created_at).getTime();
}

function pingTyping() {
  const id = store.activeConversationId;
  if (!id || editing.value) return;
  clearTimeout(typingTimer);
  typingTimer = window.setTimeout(() => {
    if (draft.value.trim() === '') return;
    sendTyping(id).catch(() => {});
  }, 400);
}

function insertEmoji(emoji) {
  const input = inputRef.value;
  if (!input) {
    draft.value += emoji;
    return;
  }
  const start = input.selectionStart ?? draft.value.length;
  const end = input.selectionEnd ?? draft.value.length;
  draft.value = `${draft.value.slice(0, start)}${emoji}${draft.value.slice(end)}`;
  nextTick(() => {
    input.focus();
    const cursor = start + emoji.length;
    input.setSelectionRange(cursor, cursor);
  });
}

function togglePicker(panel) {
  if (pickerOpen.value && pickerPanel.value === panel) {
    pickerOpen.value = false;
    return;
  }
  pickerPanel.value = panel;
  pickerOpen.value = true;
}

function startReply(message) {
  if (message.recalled_at) return;
  editing.value = null;
  replyTo.value = message;
  menu.value = null;
  nextTick(() => inputRef.value?.focus());
}

function startEdit(message) {
  if (!isMine(message) || message.recalled_at || message.message_type !== 'text') return;
  replyTo.value = null;
  editing.value = message;
  files.value = [];
  draft.value = message.message || '';
  pickerOpen.value = false;
  menu.value = null;
  nextTick(() => inputRef.value?.focus());
}

function scrollToQuoted(id) {
  document.getElementById(`chat-msg-${id}`)?.scrollIntoView({ block: 'center', behavior: 'smooth' });
}

function clearModes() {
  const wasEditing = Boolean(editing.value);
  editing.value = null;
  replyTo.value = null;
  if (wasEditing) draft.value = '';
}

function askRecall(message) {
  menu.value = null;
  confirmMessage.value = message;
  confirmKind.value = 'recall';
}

function askDelete(message) {
  menu.value = null;
  confirmMessage.value = message;
  confirmKind.value = 'delete';
}

function openMenu(message, event) {
  const button = event.currentTarget;
  if (!(button instanceof HTMLElement)) return;
  if (menu.value?.id === message.id) {
    menu.value = null;
    return;
  }
  const rect = button.getBoundingClientRect();
  const width = 168;
  const menuHeight = 168;
  const left = Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8));
  const below = rect.bottom + 6;
  const top = below + menuHeight > window.innerHeight ? Math.max(8, rect.top - menuHeight - 6) : below;
  menu.value = {
    id: message.id,
    message,
    top,
    left,
  };
}

function closeMenu() {
  menu.value = null;
}

function onDocumentPointerDown(event) {
  if (!menu.value) return;
  const target = event.target;
  if (target instanceof Element && target.closest('.chat-menu, .chat-bubble__more')) return;
  closeMenu();
}

function onDocumentKeydown(event) {
  if (event.key === 'Escape') closeMenu();
}

onMounted(() => {
  document.addEventListener('pointerdown', onDocumentPointerDown);
  document.addEventListener('keydown', onDocumentKeydown);
});

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onDocumentPointerDown);
  document.removeEventListener('keydown', onDocumentKeydown);
});

function quoteText(message) {
  if (!message) return '';
  if (message.recalled_at || message.recalled) return 'Tin nhắn đã được thu hồi';
  if (message.message_type === 'sticker') return 'Sticker';
  if ((message.message || '').trim()) return message.message;
  return fileLabel(message.attachments);
}

function fileLabel(attachments) {
  const list = attachments ?? [];
  if (list.length === 0) return '';
  if (list.every((file) => file.type === 'image')) return list.length > 1 ? `${list.length} ảnh` : 'Ảnh';
  return list.length > 1 ? `${list.length} tệp đính kèm` : 'Tệp đính kèm';
}

function imageFiles(message) {
  return (message.attachments ?? []).filter((file) => file.type === 'image');
}

function docFiles(message) {
  return (message.attachments ?? []).filter((file) => file.type !== 'image');
}

function formatSize(bytes) {
  const size = Number(bytes) || 0;
  if (size < 1024) return `${size} B`;
  if (size < 1024 * 1024) return `${Math.round(size / 1024)} KB`;
  return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

function onFilesChosen(event) {
  const chosen = Array.from(event.target.files ?? []);
  event.target.value = '';
  if (editing.value) return;
  const next = [...files.value];
  for (const file of chosen) {
    if (next.length >= MAX_FILES) {
      showClientToast('error', `Chỉ được đính kèm tối đa ${MAX_FILES} tệp mỗi tin nhắn.`);
      break;
    }
    if (file.size > MAX_FILE_BYTES) {
      showClientToast('error', `${file.name} vượt quá 10MB.`);
      continue;
    }
    next.push(file);
  }
  files.value = next;
}

function removeFile(index) {
  files.value = files.value.filter((_, i) => i !== index);
}

async function sendSticker(sticker) {
  if (sending.value || editing.value) return;
  sending.value = true;
  try {
    await store.sendMessage(store.activeConversationId, {
      message: sticker.emoji,
      message_type: 'sticker',
      sticker_id: sticker.id,
      reply_to_id: replyTo.value?.id ?? null,
    });
    replyTo.value = null;
    pickerOpen.value = false;
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không gửi được sticker.');
  } finally {
    sending.value = false;
  }
}

async function send() {
  const text = draft.value.trim();
  if (sending.value) return;
  if (text === '' && (editing.value || files.value.length === 0)) return;
  sending.value = true;
  try {
    if (editing.value) {
      await store.editMessage(store.activeConversationId, editing.value.id, text);
      editing.value = null;
      draft.value = '';
      return;
    }
    await store.sendMessage(store.activeConversationId, {
      message: text,
      reply_to_id: replyTo.value?.id ?? null,
      files: files.value,
    });
    draft.value = '';
    files.value = [];
    replyTo.value = null;
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không gửi được tin nhắn.');
  } finally {
    sending.value = false;
  }
}

async function confirmAction() {
  if (!confirmMessage.value || confirming.value) return;
  confirming.value = true;
  const message = confirmMessage.value;
  const kind = confirmKind.value;
  try {
    if (kind === 'recall') {
      await store.recallMessage(store.activeConversationId, message.id);
    } else {
      await store.hideMessage(store.activeConversationId, message.id);
    }
    if (editing.value?.id === message.id) clearModes();
    if (replyTo.value?.id === message.id) replyTo.value = null;
    confirmKind.value = null;
    confirmMessage.value = null;
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không thực hiện được thao tác.');
  } finally {
    confirming.value = false;
  }
}

function onKeydown(event) {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault();
    send();
  }
}

function initial(name) {
  return name?.trim().charAt(0).toUpperCase() || '?';
}

function menuItems(message) {
  const mine = isMine(message);
  const recalled = Boolean(message.recalled_at);
  const items = [];
  if (!recalled) {
    items.push({ key: 'reply', label: 'Trả lời', icon: 'reply', run: () => startReply(message) });
  }
  if (mine && !recalled && message.message_type === 'text' && (message.message || '').trim() !== '') {
    items.push({ key: 'edit', label: 'Sửa', icon: 'pencil', run: () => startEdit(message) });
  }
  if (mine && !recalled) {
    items.push({ key: 'recall', label: 'Thu hồi', icon: 'rotateCw', run: () => askRecall(message) });
  }
  items.push({ key: 'delete', label: 'Xoá', icon: 'trash', run: () => askDelete(message) });
  return items;
}
</script>

<template>
  <div class="chat-view">
    <div class="chat-view__head">
      <button type="button" class="chat-view__icon-btn" aria-label="Quay lại danh sách" @click="store.backToList()">
        <AppIcon name="chevronLeft" :size="18" :stroke-width="1.75" />
      </button>
      <img
        v-if="otherUser?.avatar_url"
        class="chat-view__avatar"
        :src="otherUser.avatar_url"
        :alt="`Ảnh đại diện của ${otherUser.name}`"
      />
      <span v-else class="chat-view__avatar chat-view__avatar--placeholder">{{ initial(otherUser?.name) }}</span>
      <span class="chat-view__who">
        <span class="chat-view__title">{{ otherUser?.name || 'Trò chuyện' }}</span>
        <span v-if="peerTyping" class="chat-view__dept">Đang nhập…</span>
        <span v-else-if="otherUser?.department" class="chat-view__dept">{{ otherUser.department }}</span>
      </span>
    </div>

    <div ref="listRef" class="chat-view__messages" @scroll="onScroll">
      <p v-if="store.loadingMessages && store.activeMessages.length === 0" class="chat-view__empty">Đang tải tin nhắn…</p>
      <p v-else-if="store.activeMessages.length === 0" class="chat-view__empty">
        Chưa có tin nhắn. Hãy gửi lời chào, emoji hoặc sticker.
      </p>
      <template v-for="row in timeline" :key="row.kind === 'day' ? row.id : row.message.id">
        <div v-if="row.kind === 'day'" class="chat-day">{{ row.label }}</div>
        <div
          v-else
          :id="`chat-msg-${row.message.id}`"
          class="chat-bubble-row"
          :class="{ 'chat-bubble-row--mine': isMine(row.message) }"
        >
          <div
            class="chat-bubble"
            :class="{
              'chat-bubble--mine': isMine(row.message),
              'chat-bubble--recalled': row.message.recalled_at,
              'chat-bubble--sticker': row.message.message_type === 'sticker' && !row.message.recalled_at,
            }"
          >
            <button
              v-if="row.message.reply_to"
              type="button"
              class="chat-quote"
              @click="scrollToQuoted(row.message.reply_to.id)"
            >
              <AppIcon name="reply" :size="12" :stroke-width="1.75" />
              <span class="chat-quote__body">
                <span class="chat-quote__name">{{ row.message.reply_to.sender_name || 'Tin nhắn' }}</span>
                <span class="chat-quote__text">{{ quoteText(row.message.reply_to) }}</span>
              </span>
            </button>

            <span v-if="row.message.recalled_at" class="chat-bubble__recalled">
              <AppIcon name="rotateCw" :size="13" :stroke-width="1.75" />
              Tin nhắn đã được thu hồi
            </span>
            <span v-else-if="row.message.message_type === 'sticker' && row.message.sticker_id" class="chat-sticker">
              <SocialAnimatedSticker :id="row.message.sticker_id" :emoji="row.message.message || ''" />
            </span>
            <span v-else-if="row.message.message" class="chat-bubble__text">{{ row.message.message }}</span>
            <div v-if="!row.message.recalled_at && imageFiles(row.message).length" class="chat-images">
              <a
                v-for="file in imageFiles(row.message)"
                :key="file.id"
                class="chat-images__link"
                :href="file.url"
                target="_blank"
                rel="noopener"
              >
                <img :src="file.url" :alt="file.name" />
              </a>
            </div>
            <div v-if="!row.message.recalled_at && docFiles(row.message).length" class="chat-docs">
              <a
                v-for="file in docFiles(row.message)"
                :key="file.id"
                class="chat-doc"
                :href="file.url"
                target="_blank"
                rel="noopener"
              >
                <AppIcon name="fileText" :size="16" :stroke-width="1.75" />
                <span class="chat-doc__copy">
                  <span class="chat-doc__name">{{ file.name }}</span>
                  <span class="chat-doc__size">{{ formatSize(file.size) }}</span>
                </span>
              </a>
            </div>

            <span class="chat-bubble__meta">
              <span v-if="row.message.edited_at && !row.message.recalled_at">Đã sửa</span>
              <span>{{ formatTime(row.message.created_at) }}</span>
              <span v-if="showRead(row.message)">Đã xem</span>
            </span>
          </div>

          <button
            type="button"
            class="chat-bubble__more"
            aria-label="Thao tác"
            aria-haspopup="menu"
            :aria-expanded="menu?.id === row.message.id"
            @click="openMenu(row.message, $event)"
          >
            <AppIcon name="moreVertical" :size="16" />
          </button>
        </div>
      </template>
    </div>

    <div v-if="replyTo || editing" class="chat-compose-banner">
      <AppIcon :name="editing ? 'pencil' : 'reply'" :size="14" :stroke-width="1.75" />
      <span class="chat-compose-banner__copy">
        <span class="chat-compose-banner__label">{{ editing ? 'Sửa tin nhắn' : `Trả lời ${replyTo?.sender?.name || ''}` }}</span>
        <span class="chat-compose-banner__text">{{ editing ? draft : quoteText(replyTo) }}</span>
      </span>
      <button type="button" class="chat-view__icon-btn" aria-label="Huỷ" @click="clearModes">
        <AppIcon name="close" :size="14" :stroke-width="1.75" />
      </button>
    </div>

    <ChatPicker
      v-if="pickerOpen"
      :panel="pickerPanel"
      @update:panel="pickerPanel = $event"
      @pick-emoji="insertEmoji"
      @pick-sticker="sendSticker"
    />

    <div class="chat-view__composer">
      <div class="chat-view__tools">
        <button
          type="button"
          class="chat-view__tool"
          :class="{ 'chat-view__tool--on': pickerOpen && pickerPanel === 'emoji' }"
          aria-label="Chèn emoji"
          @click="togglePicker('emoji')"
        >
          <AppIcon name="smile" :size="18" :stroke-width="1.75" />
        </button>
        <button
          type="button"
          class="chat-view__tool"
          :disabled="Boolean(editing)"
          aria-label="Đính kèm tệp"
          @click="fileInput?.click()"
        >
          <AppIcon name="paperclip" :size="18" :stroke-width="1.75" />
        </button>
        <input
          ref="fileInput"
          class="chat-view__file-input"
          type="file"
          multiple
          accept="image/*,.pdf,.doc,.docx,.xlsx,.xls,.ppt,.pptx,.txt,.csv,.zip"
          @change="onFilesChosen"
        />
        <button
          type="button"
          class="chat-view__tool"
          :class="{ 'chat-view__tool--on': pickerOpen && pickerPanel === 'sticker' }"
          :disabled="Boolean(editing)"
          aria-label="Gửi sticker"
          @click="togglePicker('sticker')"
        >
          <AppIcon name="sticker" :size="18" :stroke-width="1.75" />
        </button>
        <button
          v-for="emoji in QUICK_EMOJIS"
          :key="emoji"
          type="button"
          class="chat-view__quick"
          :aria-label="`Chèn ${emoji}`"
          @click="insertEmoji(emoji)"
        >
          {{ emoji }}
        </button>
      </div>
      <div v-if="files.length" class="chat-pending">
        <span v-for="(file, index) in files" :key="`${file.name}-${index}`" class="chat-pending__item">
          <AppIcon :name="file.type.startsWith('image/') ? 'camera' : 'fileText'" :size="14" :stroke-width="1.75" />
          <span class="chat-pending__name">{{ file.name }}</span>
          <button type="button" class="chat-pending__remove" :aria-label="`Bỏ ${file.name}`" @click="removeFile(index)">
            <AppIcon name="close" :size="12" :stroke-width="1.75" />
          </button>
        </span>
      </div>
      <div class="chat-view__input-row">
        <textarea
          ref="inputRef"
          v-model="draft"
          class="chat-view__input"
          rows="1"
          :placeholder="editing ? 'Sửa nội dung…' : 'Nhập tin nhắn...'"
          :aria-label="editing ? 'Sửa tin nhắn' : `Nhập tin nhắn gửi ${otherUser?.name || ''}`"
          @keydown="onKeydown"
        />
        <button type="button" class="chat-view__send" :disabled="!canSend" aria-label="Gửi tin nhắn" @click="send">
          <AppIcon :name="editing ? 'check' : 'send'" :size="18" :stroke-width="1.75" />
        </button>
      </div>
    </div>

    <Teleport to="body">
      <div
        v-if="menu"
        class="chat-menu"
        role="menu"
        :style="{ top: `${menu.top}px`, left: `${menu.left}px` }"
      >
        <button
          v-for="item in menuItems(menu.message)"
          :key="item.key"
          type="button"
          class="chat-menu__item"
          role="menuitem"
          @click="item.run()"
        >
          <AppIcon :name="item.icon" :size="15" :stroke-width="1.75" />
          {{ item.label }}
        </button>
      </div>
    </Teleport>

    <ConfirmDialog
      :open="confirmKind !== null"
      :title="confirmCopy.title"
      :description="confirmCopy.description"
      :confirm-label="confirmCopy.confirmLabel"
      danger
      :loading="confirming"
      @update:open="(open) => { if (!open) confirmKind = null; }"
      @confirm="confirmAction"
    />
  </div>
</template>

<style scoped>
.chat-view {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  background:
    radial-gradient(circle at 1px 1px, color-mix(in srgb, var(--color-border) 70%, transparent) 1px, transparent 0) 0 0 / 14px 14px,
    var(--color-surface);
}

.chat-view__head {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3);
  background: var(--color-surface);
  box-shadow: 0 1px 0 var(--color-border);
}

.chat-view__icon-btn,
.chat-bubble__more,
.chat-view__tool,
.chat-view__send {
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.chat-view__icon-btn {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-sm);
  flex-shrink: 0;
}

.chat-view__icon-btn:hover,
.chat-view__tool:hover,
.chat-bubble__more:hover {
  background: var(--color-surface-muted);
  color: var(--color-primary);
}

.chat-view__avatar {
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
}

.chat-view__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 0.75rem;
  font-weight: 700;
}

.chat-view__who {
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.chat-view__title {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chat-view__dept {
  color: var(--color-text-muted);
  font-size: 0.6875rem;
}

.chat-view__messages {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding: var(--space-3);
}

.chat-view__empty {
  margin: auto 0;
  padding: var(--space-6) var(--space-4);
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.chat-day {
  align-self: center;
  margin: 0.35rem 0;
  padding: 0.15rem 0.55rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 600;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.chat-bubble-row {
  display: flex;
  align-items: flex-end;
  justify-content: flex-start;
  gap: 0.15rem;
}

.chat-bubble-row--mine {
  flex-direction: row-reverse;
}

.chat-bubble {
  max-width: 78%;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  padding: 0.45rem 0.7rem 0.35rem;
  border-radius: 14px 14px 14px 4px;
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.chat-bubble--mine {
  border-radius: 14px 14px 4px 14px;
  background: var(--color-primary-surface);
  box-shadow: none;
}

.chat-bubble--sticker {
  background: transparent;
  box-shadow: none;
  padding: 0;
}

.chat-bubble--recalled {
  background: var(--color-surface-muted);
  box-shadow: none;
}

.chat-bubble__text {
  font-size: 0.8125rem;
  white-space: pre-wrap;
  word-break: break-word;
}

.chat-bubble__recalled {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-style: italic;
}

.chat-bubble__meta {
  display: flex;
  justify-content: flex-end;
  gap: 0.35rem;
  color: var(--color-text-muted);
  font-size: 0.625rem;
}

.chat-sticker {
  display: block;
  width: 6.5rem;
  height: 6.5rem;
}

.chat-sticker :deep(.social-sticker) {
  width: 6.5rem;
  height: 6.5rem;
}

.chat-quote {
  display: flex;
  align-items: flex-start;
  gap: 0.35rem;
  width: 100%;
  margin-bottom: 0.15rem;
  padding: 0.3rem 0.4rem;
  border: none;
  border-radius: 8px;
  background: color-mix(in srgb, var(--color-primary) 8%, var(--color-surface));
  color: var(--color-text);
  text-align: left;
  cursor: pointer;
  font-family: inherit;
}

.chat-quote__body {
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.chat-quote__name {
  color: var(--color-primary);
  font-size: 0.6875rem;
  font-weight: 700;
}

.chat-quote__text {
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 12rem;
}

.chat-bubble__more {
  width: 1.5rem;
  height: 1.5rem;
  border-radius: var(--radius-full);
  opacity: 0;
  flex-shrink: 0;
}

.chat-bubble-row:hover .chat-bubble__more,
.chat-bubble__more[aria-expanded='true'] {
  opacity: 1;
}

@media (hover: none) {
  .chat-bubble__more {
    opacity: 1;
  }
}

.chat-compose-banner {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin: 0 var(--space-3) var(--space-2);
  padding: 0.4rem 0.55rem;
  border-radius: 10px;
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.chat-compose-banner__copy {
  min-width: 0;
  flex: 1;
  display: flex;
  flex-direction: column;
}

.chat-compose-banner__label {
  font-size: 0.6875rem;
  font-weight: 700;
}

.chat-compose-banner__text {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chat-view__composer {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding: var(--space-2) var(--space-3) var(--space-3);
  background: var(--color-surface);
  box-shadow: 0 -1px 0 var(--color-border);
}

.chat-view__tools {
  display: flex;
  align-items: center;
  gap: 0.1rem;
}

.chat-view__tool {
  width: 1.85rem;
  height: 1.85rem;
  border-radius: var(--radius-sm);
}

.chat-view__tool--on {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.chat-view__tool:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.chat-view__quick {
  border: none;
  background: transparent;
  width: 1.7rem;
  height: 1.7rem;
  border-radius: var(--radius-sm);
  cursor: pointer;
  font-size: 0.95rem;
  line-height: 1;
}

.chat-view__quick:hover {
  background: var(--color-surface-muted);
}

.chat-view__file-input {
  display: none;
}

.chat-pending {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.chat-pending__item {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  max-width: 100%;
  padding: 0.2rem 0.35rem 0.2rem 0.45rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-size: 0.75rem;
}

.chat-pending__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 10rem;
}

.chat-pending__remove {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.1rem;
  height: 1.1rem;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.chat-images {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 7.5rem));
  gap: 0.25rem;
  margin-top: 0.25rem;
}

.chat-images__link {
  display: block;
  border-radius: 8px;
  overflow: hidden;
}

.chat-images__link img {
  display: block;
  width: 100%;
  height: 6.5rem;
  object-fit: cover;
}

.chat-docs {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  margin-top: 0.25rem;
}

.chat-doc {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  min-width: 0;
  padding: 0.35rem 0.45rem;
  border-radius: 8px;
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
  text-decoration: none;
}

.chat-doc__copy {
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.chat-doc__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 11rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.chat-doc__size {
  color: var(--color-text-muted);
  font-size: 0.625rem;
}

.chat-view__input-row {
  display: flex;
  align-items: flex-end;
  gap: var(--space-2);
}

.chat-view__input {
  flex: 1;
  min-width: 0;
  max-height: 6rem;
  resize: none;
  border: none;
  border-radius: 12px;
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  padding: 0.55rem 0.75rem;
}

.chat-view__input:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: -1px;
}

.chat-view__send {
  flex-shrink: 0;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-full);
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.chat-view__send:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>

<style>
.chat-menu {
  position: fixed;
  z-index: 80;
  width: 10.5rem;
  padding: 0.25rem;
  border-radius: 10px;
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
}

.chat-menu__item {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  width: 100%;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.8125rem;
  text-align: left;
  padding: 0.4rem 0.5rem;
  cursor: pointer;
}

.chat-menu__item:hover {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}
</style>
