<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { sendTyping, touchViewing } from '../api/chatApi';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { showClientToast } from '@/lib/clientToast';
import {
  burstEmployeeMobileViewport,
  syncEmployeeMobileViewport,
} from '@/composables/useEmployeeMobileViewport';
import { isPwaStandalone } from '@/lib/pwaStandalone';
import {
  beginNativeFilePicker,
  endNativeFilePicker,
  PWA_NATIVE_PICKER_CLASS,
} from '@/lib/pwaVisualViewport';
import { warmStickerAssets } from '@modules/Social/resources/js/lib/socialStickers.js';
import { formatRelativeTime, formatTime } from '@/lib/formatTime';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import SocialAnimatedSticker from '@modules/Social/resources/js/components/SocialAnimatedSticker.vue';
import ChatLinkPreviewEmbed from '@modules/Social/resources/js/components/ChatLinkPreviewEmbed.vue';
import ChatAlbumDialog from './ChatAlbumDialog.vue';
import ChatAvatar from './ChatAvatar.vue';
import ChatMemberDialog from './ChatMemberDialog.vue';
import ChatPicker from './ChatPicker.vue';
import ChatPollDialog from './ChatPollDialog.vue';
import ChatReactionBurst from './ChatReactionBurst.vue';
import { CHAT_REACTIONS, reactionLabel } from '../lib/chatReactions';
import {
  BIRTHDAY_STICKER,
  CHAT_ACCENTS,
  CHAT_MOODS,
  CHAT_QUICK_REPLIES,
  moodById,
} from '../lib/chatPlay';
import { formatPeerMeta } from '../lib/chatPeerMeta';
import { ChatFileError, prepareChatFile } from '../lib/prepareChatFile';
import { useChatStore } from '../store/chatStore';

const store = useChatStore();
const auth = useAuthStore();

const MAX_FILES = 5;
const MAX_FILE_BYTES = 10 * 1024 * 1024;

const draft = ref('');
const files = ref([]);
const fileInput = ref(null);
const cameraInput = ref(null);
const photoInput = ref(null);
const lightbox = ref(null);
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
const membersOpen = ref(false);
const pollOpen = ref(false);
const albumOpen = ref(false);
const searchOpen = ref(false);
const searchQuery = ref('');
const searchHits = ref([]);
const readersFor = ref(null);
const mentionIds = ref([]);
const reactionPicker = ref(null);
const funSheet = ref(null);
const burstTick = ref(0);
const burstEmoji = ref('');
let holdFired = false;
let pointerMoved = false;
let tapMessageId = 0;
let tapStamp = 0;
/** PWA: không gắn cảm xúc trên menu; đè giữ bong bóng thì thanh mới hiện đè lên tin. */
const holdToReact = computed(() => {
  if (typeof document === 'undefined') return false;
  const root = document.documentElement;
  return isPwaStandalone()
    || root.classList.contains('pwa-standalone')
    || root.classList.contains('employee-mobile-pwa');
});
let reactionHoldTimer = 0;
let reactionHoldPoint = null;
const draftCaret = ref(0);
const mentionActive = ref(0);
const mentionDismissed = ref(false);
let typingSentAt = 0;
let typingStopTimer = null;
let localOutboxSeq = 0;

const otherUser = computed(() => store.activeConversation?.other_user ?? null);
const peerMetaLine = computed(() => formatPeerMeta(otherUser.value));
const isGroup = computed(() => store.activeConversation?.type === 'group');
const headerTitle = computed(() => {
  if (isGroup.value) return store.activeConversation?.title || 'Nhóm';
  return otherUser.value?.name || 'Trò chuyện';
});
const isOwner = computed(() => Boolean(store.activeConversation?.is_owner));
const groupAvatarUrl = computed(() => (isGroup.value ? store.activeConversation?.avatar_url : null) ?? null);
const peerMood = computed(() => (isGroup.value ? null : moodById(otherUser.value?.mood)));
const myMood = computed(() => moodById(store.activeConversation?.my_mood));
const streakCount = computed(() => (isGroup.value ? 0 : Number(store.activeConversation?.streak) || 0));
const groupAccent = computed(() => (isGroup.value ? store.activeConversation?.accent || '' : ''));
const birthdayNames = computed(() => {
  if (isGroup.value) {
    return (store.activeConversation?.members || [])
      .filter((user) => user.birthday_today)
      .map((user) => user.name)
      .filter(Boolean);
  }
  return otherUser.value?.birthday_today && otherUser.value?.name ? [otherUser.value.name] : [];
});
const showQuickReplies = computed(() => (
  !editing.value && draft.value.trim() === '' && files.value.length === 0 && !sending.value
));
const draftMentionChips = computed(() => (
  (store.activeConversation?.members || []).filter((user) => (
    user?.id
    && user.id !== auth.user?.id
    && user.name
    && draft.value.includes(`@${user.name}`)
  ))
));
const draftParts = computed(() => messageParts(draft.value));
const pinnedMessage = computed(() => store.activeConversation?.pinned_message ?? null);
const mentionMatch = computed(() => {
  if (!isGroup.value || editing.value) return null;
  const caret = Math.min(draftCaret.value, draft.value.length);
  const before = draft.value.slice(0, caret);
  const match = before.match(/(^|\s)@([^\n@]*)$/);
  if (!match) return null;
  const query = match[2];
  return { query, start: caret - query.length - 1, end: caret };
});
const mentionHits = computed(() => {
  if (!mentionMatch.value) return [];
  const query = mentionMatch.value.query.toLowerCase();
  return (store.activeConversation?.members || [])
    .filter((user) => user.id !== auth.user?.id && (user.name || '').toLowerCase().includes(query))
    .slice(0, 8);
});
const mentionOpen = computed(() => !mentionDismissed.value && mentionHits.value.length > 0);
watch(
  () => mentionMatch.value?.query ?? null,
  () => {
    mentionActive.value = 0;
    mentionDismissed.value = false;
  },
);
const memberLine = computed(() => {
  const count = store.activeConversation?.member_count
    || store.activeConversation?.members?.length
    || 0;
  return count ? `${count} thành viên` : 'Nhóm';
});
const typingUsers = computed(() => {
  const slot = store.typingByConversation[store.activeConversationId];
  return (slot?.users ?? []).filter((user) => user.until > Date.now());
});
const peerTyping = computed(() => typingUsers.value.length > 0);
const typingLabel = computed(() => {
  const names = typingUsers.value.map((user) => user.name).filter(Boolean);
  if (names.length === 0) return 'Đang nhập…';
  if (names.length === 1) return `${names[0]} đang nhập…`;
  if (names.length === 2) return `${names[0]} và ${names[1]} đang nhập…`;
  return `${names[0]} và ${names.length - 1} người đang nhập…`;
});
let viewingTimer = null;
const canSend = computed(() => {
  if (sending.value) return false;
  if (editing.value) return draft.value.trim() !== '';
  return draft.value.trim() !== '' || files.value.length > 0;
});
const activeOutbox = computed(() => (
  store.outbox.filter((item) => item.conversationId === store.activeConversationId)
));
const unseenBelow = ref(0);

const headerMenu = ref(null);
const confirmCopy = computed(() => {
  if (confirmKind.value === 'recall') {
    return {
      title: 'Thu hồi tin nhắn',
      description: 'Tin nhắn sẽ biến mất với cả hai người. Thao tác không hoàn tác được.',
      confirmLabel: 'Thu hồi',
    };
  }
  if (confirmKind.value === 'clear') {
    return {
      title: 'Xoá lịch sử chat',
      description: 'Toàn bộ tin nhắn biến mất ở phía bạn. Người khác vẫn thấy nội dung.',
      confirmLabel: 'Xoá lịch sử',
    };
  }
  if (confirmKind.value === 'disband') {
    return {
      title: 'Giải tán nhóm',
      description: 'Nhóm và toàn bộ tin nhắn sẽ mất với mọi thành viên. Thao tác không hoàn tác được.',
      confirmLabel: 'Giải tán',
    };
  }
  if (confirmKind.value === 'leave') {
    return {
      title: 'Rời nhóm',
      description: 'Bạn sẽ không còn nhận tin nhắn từ nhóm này.',
      confirmLabel: 'Rời nhóm',
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

/**
 * Tin của người khác có avatar người gửi ở tin cuối của mỗi chuỗi liên tiếp
 * (các tin trên chỉ chừa chỗ trống), giống cách Zalo/Messenger gom tin.
 */
const avatarMessageIds = computed(() => {
  const ids = new Set();
  const rows = timeline.value;
  rows.forEach((row, index) => {
    if (row.kind !== 'message' || row.message.message_type === 'system' || isMine(row.message)) return;
    const next = rows[index + 1];
    const continues = next?.kind === 'message'
      && next.message.message_type !== 'system'
      && next.message.sender?.id === row.message.sender?.id;
    if (!continues) ids.add(row.message.id);
  });
  return ids;
});

function senderAvatarUrl(message) {
  if (message.sender?.avatar_url) return message.sender.avatar_url;
  const member = store.activeConversation?.members?.find((item) => item.id === message.sender?.id);
  if (member?.avatar_url) return member.avatar_url;
  if (!isGroup.value && otherUser.value?.id === message.sender?.id) {
    return otherUser.value.avatar_url ?? null;
  }
  return null;
}

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

const stickToBottom = ref(true);
const freshBubbleIds = ref(new Set());
let knownBubbleIds = new Set();
let bubbleMotionOn = false;
let programmaticScroll = false;
let programmaticScrollTimer = 0;

function distanceFromBottom(el) {
  return el.scrollHeight - el.scrollTop - el.clientHeight;
}

function motionReduced() {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function scrollToBottom(smooth = false) {
  nextTick(() => {
    const el = listRef.value;
    if (!el) return;
    const top = el.scrollHeight;
    if (smooth !== true || motionReduced()) {
      el.scrollTop = top;
      stickToBottom.value = true;
      return;
    }
    programmaticScroll = true;
    window.clearTimeout(programmaticScrollTimer);
    el.scrollTo({ top, behavior: 'smooth' });
    programmaticScrollTimer = window.setTimeout(() => {
      programmaticScroll = false;
      stickToBottom.value = distanceFromBottom(el) < 120;
    }, 380);
  });
}

function resetBubbleMotion() {
  bubbleMotionOn = false;
  knownBubbleIds = new Set();
  freshBubbleIds.value = new Set();
}

function noteFreshBubbles(markOnly = false) {
  const ids = store.activeMessages.map((message) => message.id);
  if (!bubbleMotionOn) {
    if (store.loadingMessages && ids.length === 0) return;
    knownBubbleIds = new Set(ids);
    freshBubbleIds.value = new Set();
    bubbleMotionOn = true;
    return;
  }
  const present = new Set(ids);
  const fresh = new Set();
  let changed = false;
  for (const id of freshBubbleIds.value) {
    if (present.has(id)) fresh.add(id);
    else changed = true;
  }
  for (const id of ids) {
    if (knownBubbleIds.has(id)) continue;
    knownBubbleIds.add(id);
    changed = true;
    if (!markOnly) fresh.add(id);
  }
  if (changed) freshBubbleIds.value = fresh;
}

onMounted(() => {
  noteFreshBubbles();
  scrollToBottom(false);
});
onBeforeUnmount(() => {
  window.clearTimeout(programmaticScrollTimer);
  stopTyping();
  clearInterval(viewingTimer);
  clearDraftFiles();
});

watch(
  () => `${store.activeConversationId}|${store.activeMessages.map((message) => message.id).join(',')}`,
  (current, previous) => {
    const conversationId = current.split('|')[0];
    const previousId = previous?.split('|')[0];
    const switched = conversationId !== previousId;
    const nextCount = store.activeMessages.length;
    const previousIds = previous?.split('|')[1]?.split(',').filter(Boolean) ?? [];
    const previousCount = previousIds.length;
    const currentIds = store.activeMessages.map((message) => String(message.id));
    const prepended = previousIds.length > 0
      && currentIds.length > previousIds.length
      && currentIds[currentIds.length - 1] === previousIds[previousIds.length - 1];
    if (switched) {
      stickToBottom.value = true;
      resetBubbleMotion();
    }
    noteFreshBubbles(prepended);
    if (editing.value || prepended) return;
    if (switched || previous == null) {
      unseenBelow.value = 0;
      scrollToBottom(false);
      return;
    }
    if (nextCount > previousCount && stickToBottom.value) {
      unseenBelow.value = 0;
      const gap = listRef.value ? distanceFromBottom(listRef.value) : 0;
      scrollToBottom(gap > 0 && gap < 520);
      return;
    }
    if (nextCount > previousCount) {
      unseenBelow.value += nextCount - previousCount;
    }
  },
);

watch(typingLabel, (label) => {
  if (!label || !stickToBottom.value) return;
  scrollToBottom(true);
});

watch(
  () => activeOutbox.value.length,
  (length, previous) => {
    if (length > (previous ?? 0) && stickToBottom.value) scrollToBottom(true);
  },
);

function jumpToLatest() {
  unseenBelow.value = 0;
  stickToBottom.value = true;
  scrollToBottom(true);
}

watch(
  () => store.activeConversationId,
  (id, previous) => {
    if (previous && typingSentAt) {
      typingSentAt = 0;
      window.clearTimeout(typingStopTimer);
      sendTyping(previous, false).catch(() => {});
    }
    draft.value = '';
    clearDraftFiles();
    unseenBelow.value = 0;
    lightbox.value = null;
    replyTo.value = null;
    editing.value = null;
    pickerOpen.value = false;
    menu.value = null;
    headerMenu.value = null;
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
  reactionPicker.value = null;
  const el = listRef.value;
  if (el && !programmaticScroll) {
    stickToBottom.value = distanceFromBottom(el) < 96;
    if (stickToBottom.value) unseenBelow.value = 0;
  }
  if (el && el.scrollTop < 40) loadOlder();
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

function showSender(message) {
  return isGroup.value && !isMine(message) && !message.recalled_at;
}

function showRead(message) {
  if (isGroup.value || !isMine(message) || message.recalled_at) return false;
  const lastMine = [...store.activeMessages].reverse().find((item) => isMine(item) && !item.recalled_at);
  if (!lastMine || lastMine.id !== message.id) return false;
  const readAt = store.activeConversation?.other_read_at;
  if (!readAt) return false;
  return new Date(readAt).getTime() >= new Date(message.created_at).getTime();
}

function pingTyping() {
  syncCaret();
  const id = store.activeConversationId;
  if (!id || editing.value) return;
  if (draft.value.trim() === '') {
    stopTyping();
    return;
  }
  const now = Date.now();
  if (now - typingSentAt >= 2000) {
    typingSentAt = now;
    sendTyping(id, true).catch(() => {});
  }
  window.clearTimeout(typingStopTimer);
  typingStopTimer = window.setTimeout(stopTyping, 2500);
}

function stopTyping() {
  window.clearTimeout(typingStopTimer);
  const id = store.activeConversationId;
  if (!typingSentAt || !id) {
    typingSentAt = 0;
    return;
  }
  typingSentAt = 0;
  sendTyping(id, false).catch(() => {});
}

function syncCaret() {
  const input = inputRef.value;
  draftCaret.value = input?.selectionStart ?? draft.value.length;
}

function syncFieldScroll(event) {
  const mirror = event.target?.previousElementSibling;
  if (mirror) mirror.scrollTop = event.target.scrollTop;
}

function coarsePointer() {
  return window.matchMedia('(pointer: coarse)').matches;
}

function attachInput(kind) {
  if (kind === 'camera') return cameraInput.value;
  if (kind === 'photo') return photoInput.value;
  return fileInput.value;
}

/** Dialog chọn tệp trên máy bàn chặn đến khi đóng; trên điện thoại thì trả về ngay. */
let attachPickerSettled = false;

/**
 * Mở hộp chọn từ đúng cú click của nút.
 * Không ghim shell trước input.click() — đổi layout lúc đó làm trình duyệt huỷ hộp thoại.
 * Máy bàn: click() chặn đến khi chọn xong, change/cancel đã chạy, không ghim lại.
 * Điện thoại: click() trả về ngay, ghim shell trước khi thư viện ảnh co viewport.
 */
function openAttachPicker(kind) {
  if (editing.value) return;
  const input = attachInput(kind);
  if (!(input instanceof HTMLInputElement)) return;
  input.value = '';
  attachPickerSettled = false;
  const started = performance.now();
  input.click();
  // Hộp thoại máy bàn chặn trong click() — change/cancel đã xử lý, hoặc người dùng đã đóng.
  if (attachPickerSettled || performance.now() - started > 300) return;
  beginNativeFilePicker();
}

function onAttachCancel() {
  attachPickerSettled = true;
  endNativeFilePicker();
}

function refocusComposerAfterAttach() {
  nextTick(() => {
    inputRef.value?.focus();
    scheduleViewportForKeyboard();
  });
}

function insertEmoji(emoji) {
  const input = inputRef.value;
  const inputFocused = input && document.activeElement === input;
  if (!input || (coarsePointer() && !inputFocused)) {
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
  inputRef.value?.blur();
  pickerPanel.value = panel;
  pickerOpen.value = true;
  if (panel === 'sticker') {
    warmStickerAssets();
  }
}

function scheduleViewportForKeyboard() {
  syncEmployeeMobileViewport();
  burstEmployeeMobileViewport(780);
}

function onInputFocus() {
  pickerOpen.value = false;
  stickToBottom.value = true;
  scheduleViewportForKeyboard();
  nextTick(() => scrollToBottom(false));
  window.requestAnimationFrame(() => scrollToBottom(false));
}

function syncViewportUnlessNativePicker() {
  if (document.documentElement.classList.contains(PWA_NATIVE_PICKER_CLASS)) return;
  syncEmployeeMobileViewport();
}

function onInputBlur() {
  window.setTimeout(syncViewportUnlessNativePicker, 120);
  window.setTimeout(syncViewportUnlessNativePicker, 360);
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
  clearDraftFiles();
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
  const showReactRow = canReact(message) && !holdToReact.value;
  const width = showReactRow ? 264 : 168;
  const menuHeight = showReactRow ? 248 : 168;
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
  headerMenu.value = null;
}

function openHeaderMenu(event) {
  const button = event.currentTarget;
  if (!(button instanceof HTMLElement)) return;
  if (headerMenu.value) {
    headerMenu.value = null;
    return;
  }
  menu.value = null;
  const rect = button.getBoundingClientRect();
  const width = 220;
  headerMenu.value = {
    top: rect.bottom + 6,
    left: Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8)),
  };
}

function askClearHistory() {
  headerMenu.value = null;
  confirmMessage.value = null;
  confirmKind.value = 'clear';
}

function askDisband() {
  headerMenu.value = null;
  confirmMessage.value = null;
  confirmKind.value = 'disband';
}

function memberName(id) {
  return (store.activeConversation?.members || []).find((user) => user.id === id)?.name || '';
}

function pickMention(user) {
  const hit = mentionMatch.value;
  if (!hit) return;
  const before = draft.value.slice(0, hit.start);
  const after = draft.value.slice(hit.end);
  const insert = `@${user.name} `;
  draft.value = `${before}${insert}${after}`;
  if (!mentionIds.value.includes(user.id)) mentionIds.value = [...mentionIds.value, user.id];
  const cursor = before.length + insert.length;
  mentionDismissed.value = true;
  nextTick(() => {
    const input = inputRef.value;
    input?.focus();
    input?.setSelectionRange(cursor, cursor);
    draftCaret.value = cursor;
  });
}

function insertMentionTrigger() {
  const input = inputRef.value;
  const caret = input?.selectionStart ?? draft.value.length;
  const before = draft.value.slice(0, caret);
  const after = draft.value.slice(input?.selectionEnd ?? caret);
  const needsSpace = before.length > 0 && !/\s$/.test(before);
  const insert = `${needsSpace ? ' ' : ''}@`;
  draft.value = `${before}${insert}${after}`;
  mentionDismissed.value = false;
  const cursor = before.length + insert.length;
  nextTick(() => {
    input?.focus();
    input?.setSelectionRange(cursor, cursor);
    draftCaret.value = cursor;
  });
}

function groupReadOthersCount() {
  return Math.max(0, (store.activeConversation?.member_count || 1) - 1);
}

function canShowReadReceipts(message) {
  return isGroup.value && isMine(message) && !message.recalled_at && groupReadOthersCount() > 0;
}

function readLabel(message) {
  if (!canShowReadReceipts(message)) return '';
  const count = message.reads?.length || 0;
  if (count < 1) return '';
  return `Đã xem ${count}/${groupReadOthersCount()}`;
}

function readFaces(message) {
  const members = store.activeConversation?.members || [];
  const byId = new Map(members.map((user) => [user.id, user]));
  return (message.reads || []).slice(0, 3).map((reader) => {
    const member = byId.get(reader.id);
    return {
      id: reader.id,
      name: reader.name || member?.name || 'Đồng nghiệp',
      avatar_url: reader.avatar_url ?? member?.avatar_url ?? null,
    };
  });
}

function showReaders(message) {
  readersFor.value = message;
}

const readerRows = computed(() => {
  if (!readersFor.value) return [];
  const members = store.activeConversation?.members || [];
  const byId = new Map(members.map((user) => [user.id, user]));
  return (readersFor.value.reads || []).map((reader) => {
    const member = byId.get(reader.id);
    return {
      id: reader.id,
      name: reader.name || member?.name || 'Đồng nghiệp',
      avatar_url: reader.avatar_url ?? member?.avatar_url ?? null,
    };
  });
});

const readerPendingRows = computed(() => {
  if (!readersFor.value) return [];
  const readIds = new Set((readersFor.value.reads || []).map((reader) => reader.id));
  return (store.activeConversation?.members || [])
    .filter((member) => member.id !== auth.user?.id && !readIds.has(member.id))
    .map((member) => ({
      id: member.id,
      name: member.name || 'Đồng nghiệp',
      avatar_url: member.avatar_url ?? null,
    }));
});

async function pinThis(message) {
  menu.value = null;
  await store.pinMessage(store.activeConversationId, message.id).catch((error) => {
    showClientToast('error', error?.response?.data?.message || 'Không ghim được tin.');
  });
}

async function runSearch() {
  const query = searchQuery.value.trim();
  if (query.length < 2) {
    searchHits.value = [];
    return;
  }
  searchHits.value = await store.searchMessages(store.activeConversationId, query).catch(() => []);
}

function jumpToHit(message) {
  const id = store.activeConversationId;
  const known = store.activeMessages.some((item) => item.id === message.id);
  if (!known && message.sender) {
    store.upsertMessages(id, [message]);
  }
  searchOpen.value = false;
  nextTick(() => {
    const node = document.getElementById(`chat-msg-${message.id}`);
    if (node) node.scrollIntoView({ block: 'center' });
    else showClientToast('info', 'Tin này nằm phía trên. Kéo lên để xem.');
  });
}

async function togglePinConversation() {
  headerMenu.value = null;
  const pinned = !store.activeConversation?.pinned_at;
  await store.pinConversation(store.activeConversationId, pinned);
}

async function toggleMute() {
  headerMenu.value = null;
  const hours = store.activeConversation?.is_muted ? null : 87600;
  await store.muteConversation(store.activeConversationId, hours);
}

async function chooseDisappear(seconds) {
  headerMenu.value = null;
  await store.setDisappear(store.activeConversationId, seconds);
}

function openMembers() {
  headerMenu.value = null;
  membersOpen.value = true;
}

function askLeaveGroup() {
  headerMenu.value = null;
  confirmKind.value = 'leave';
}

function openAlbum() {
  headerMenu.value = null;
  albumOpen.value = true;
}

function openPoll() {
  headerMenu.value = null;
  pollOpen.value = true;
}

function openSearch() {
  headerMenu.value = null;
  searchOpen.value = true;
  searchHits.value = [];
  searchQuery.value = '';
}

async function castVote(message, option) {
  await store.votePoll(store.activeConversationId, message.id, option.id).catch((error) => {
    showClientToast('error', error?.response?.data?.message || 'Không gửi được phiếu.');
  });
}

function pollTotalVotes(poll) {
  return (poll?.options ?? []).reduce((sum, opt) => sum + (opt.votes ?? 0), 0);
}

function pollOptionPercent(option, poll) {
  const total = pollTotalVotes(poll);
  if (total <= 0) return 0;
  return Math.round(((option.votes ?? 0) / total) * 100);
}

function pollVotesLabel(count) {
  if (count === 1) return '1 phiếu';
  return `${count} phiếu`;
}

function pollOptionSelected(option) {
  return (option.voter_ids || []).map(Number).includes(Number(auth.user?.id));
}

function onDocumentPointerDown(event) {
  const target = event.target;
  if (reactionPicker.value && (!(target instanceof Element) || !target.closest('.chat-react-bar'))) {
    reactionPicker.value = null;
  }
  if (!menu.value && !headerMenu.value) return;
  if (target instanceof Element && target.closest('.chat-menu, .chat-bubble__more, .chat-view__head-menu')) return;
  closeMenu();
}

function onDocumentKeydown(event) {
  if (event.key !== 'Escape') return;
  if (lightbox.value) {
    lightbox.value = null;
    return;
  }
  if (reactionPicker.value) {
    reactionPicker.value = null;
    return;
  }
  closeMenu();
}

function onNativeFilePickerEnd() {
  refocusComposerAfterAttach();
}

onMounted(() => {
  document.addEventListener('pointerdown', onDocumentPointerDown);
  document.addEventListener('keydown', onDocumentKeydown);
  window.addEventListener('va-native-file-picker-end', onNativeFilePickerEnd);
});

onBeforeUnmount(() => {
  cancelReactionHold();
  document.removeEventListener('pointerdown', onDocumentPointerDown);
  document.removeEventListener('keydown', onDocumentKeydown);
  window.removeEventListener('va-native-file-picker-end', onNativeFilePickerEnd);
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
  return (message.attachments ?? []).filter((file) => file.type !== 'image' && file.type !== 'audio');
}

function formatSize(bytes) {
  const size = Number(bytes) || 0;
  if (size < 1024) return `${size} B`;
  if (size < 1024 * 1024) return `${Math.round(size / 1024)} KB`;
  return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

function revokePreview(item) {
  if (item?.previewUrl) URL.revokeObjectURL(item.previewUrl);
}

function clearDraftFiles() {
  for (const item of files.value) revokePreview(item);
  files.value = [];
}

async function onFilesChosen(event) {
  attachPickerSettled = true;
  const input = event.target;
  const chosen = input instanceof HTMLInputElement ? Array.from(input.files ?? []) : [];
  if (input instanceof HTMLInputElement) input.value = '';
  endNativeFilePicker();
  if (editing.value || chosen.length === 0) return;

  const next = [...files.value];
  for (const raw of chosen) {
    if (next.length >= MAX_FILES) {
      showClientToast('error', `Chỉ được đính kèm tối đa ${MAX_FILES} tệp mỗi tin nhắn.`);
      break;
    }
    let file = raw;
    try {
      file = await prepareChatFile(raw);
    } catch (error) {
      showClientToast(
        'error',
        error instanceof ChatFileError ? error.message : 'Ảnh này không gửi được. Hãy chọn JPG hoặc PNG.',
      );
      continue;
    }
    if (file.size > MAX_FILE_BYTES) {
      showClientToast('error', `${file.name} vượt quá 10MB.`);
      continue;
    }
    const previewUrl = file.type.startsWith('image/') ? URL.createObjectURL(file) : '';
    next.push({ file, previewUrl });
  }
  files.value = next;
}

function removeFile(index) {
  revokePreview(files.value[index]);
  files.value = files.value.filter((_, i) => i !== index);
}

function openImage(file) {
  if (!file?.url) return;
  lightbox.value = { url: file.url, name: file.name || 'Ảnh' };
}

function queueOutgoing({ kind, text = '', outgoingFiles = [], previews = [], replyId = null, mentionIdsForSend = [], stickerId = null }) {
  const conversationId = store.activeConversationId;
  if (!conversationId) return;
  localOutboxSeq += 1;
  const payload = {
    message: text,
    reply_to_id: replyId,
  };
  if (kind === 'sticker') {
    payload.message_type = 'sticker';
    payload.sticker_id = stickerId;
  }
  if (mentionIdsForSend.length > 0) payload.mention_ids = mentionIdsForSend;
  if (outgoingFiles.length > 0) payload.files = outgoingFiles;
  store.enqueueOutbox({
    localId: `out-${Date.now()}-${localOutboxSeq}`,
    conversationId,
    senderId: auth.user?.id ?? null,
    kind,
    text,
    fileCount: outgoingFiles.length,
    stickerId,
    previews,
    startedAt: Date.now(),
    payload,
  });
  stickToBottom.value = true;
  unseenBelow.value = 0;
  nextTick(() => scrollToBottom(true));
}

function sendSticker(sticker) {
  if (editing.value || sending.value) return;
  const conversationId = store.activeConversationId;
  if (!conversationId) return;
  const replyId = replyTo.value?.id ?? null;
  replyTo.value = null;
  queueOutgoing({
    kind: 'sticker',
    text: sticker.emoji,
    replyId,
    stickerId: sticker.id,
  });
}

function send() {
  const text = draft.value.trim();
  if (sending.value || !store.activeConversationId) return;
  if (text === '' && (editing.value || files.value.length === 0)) return;

  if (editing.value) {
    sending.value = true;
    store.editMessage(store.activeConversationId, editing.value.id, text)
      .then(() => {
        editing.value = null;
        draft.value = '';
      })
      .catch((error) => {
        showClientToast('error', error?.response?.data?.message ?? 'Không gửi được tin nhắn.');
      })
      .finally(() => {
        sending.value = false;
      });
    return;
  }

  const outgoingFiles = files.value.map((item) => item.file);
  const previews = files.value.map((item) => item.previewUrl).filter(Boolean);
  const mentionIdsForSend = mentionIds.value.filter((id) => text.includes(memberName(id)));
  const replyId = replyTo.value?.id ?? null;
  draft.value = '';
  files.value = [];
  mentionIds.value = [];
  replyTo.value = null;
  queueOutgoing({
    kind: 'message',
    text,
    outgoingFiles,
    previews,
    replyId,
    mentionIdsForSend,
  });
}

async function confirmAction() {
  if (confirming.value) return;
  if (confirmKind.value === 'clear' || confirmKind.value === 'disband' || confirmKind.value === 'leave') {
    confirming.value = true;
    const kind = confirmKind.value;
    const conversationId = store.activeConversationId;
    try {
      if (kind === 'clear') {
        await store.clearHistory(conversationId);
      } else if (kind === 'disband') {
        await store.disbandGroup(conversationId);
      } else {
        await store.removeMember(conversationId, auth.user?.id);
        store.backToList();
      }
      confirmKind.value = null;
      confirmMessage.value = null;
    } catch (error) {
      showClientToast('error', error?.response?.data?.message ?? 'Không thực hiện được thao tác.');
    } finally {
      confirming.value = false;
    }
    return;
  }
  if (!confirmMessage.value) return;
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
  if (mentionOpen.value && mentionHits.value.length > 0) {
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      mentionActive.value = (mentionActive.value + 1) % mentionHits.value.length;
      return;
    }
    if (event.key === 'ArrowUp') {
      event.preventDefault();
      mentionActive.value = (mentionActive.value - 1 + mentionHits.value.length) % mentionHits.value.length;
      return;
    }
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      const user = mentionHits.value[mentionActive.value];
      if (user) pickMention(user);
      return;
    }
    if (event.key === 'Escape') {
      event.preventDefault();
      event.stopPropagation();
      mentionDismissed.value = true;
      return;
    }
  }
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault();
    send();
  }
}

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function messageParts(text) {
  const source = text || '';
  const names = (store.activeConversation?.members || [])
    .map((user) => user.name)
    .filter((name) => typeof name === 'string' && name.trim() !== '')
    .sort((a, b) => b.length - a.length);
  if (names.length === 0) return [{ mention: false, text: source }];
  const pattern = new RegExp(`@(?:${names.map(escapeRegExp).join('|')})`, 'g');
  const parts = [];
  let cursor = 0;
  for (const match of source.matchAll(pattern)) {
    const index = match.index ?? 0;
    if (index > cursor) parts.push({ mention: false, text: source.slice(cursor, index) });
    parts.push({ mention: true, text: match[0] });
    cursor = index + match[0].length;
  }
  if (cursor < source.length) parts.push({ mention: false, text: source.slice(cursor) });
  return parts.length ? parts : [{ mention: false, text: source }];
}

function reactionRows(message) {
  const mineId = auth.user?.id;
  const order = new Map(CHAT_REACTIONS.map((item, index) => [item.emoji, index]));
  return (message?.reactions || [])
    .filter((row) => row?.emoji && row.count > 0)
    .slice()
    .sort((a, b) => (order.get(a.emoji) ?? 99) - (order.get(b.emoji) ?? 99))
    .map((row) => {
      const mine = (row.user_ids || []).includes(mineId);
      const names = (row.users || []).map((user) => user.name).filter(Boolean);
      const who = names.length ? names.join(', ') : `${row.count} người`;
      const label = reactionLabel(row.emoji);
      return {
        emoji: row.emoji,
        count: row.count,
        mine,
        aria: mine ? `${label}, ${who}. Bỏ cảm xúc` : `${label}, ${who}. Thả cảm xúc`,
      };
    });
}

function myReaction(message) {
  const mineId = auth.user?.id;
  return (message?.reactions || []).find((row) => (row.user_ids || []).includes(mineId))?.emoji || '';
}

function canReact(message) {
  return Boolean(message) && !message.recalled_at && message.message_type !== 'system';
}

function cancelReactionHold() {
  window.clearTimeout(reactionHoldTimer);
  reactionHoldPoint = null;
}

function playBurst(emoji) {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  burstEmoji.value = emoji;
  burstTick.value += 1;
}

function onBubblePointerDown(message, event) {
  if (event.pointerType === 'mouse' && event.button !== 0) return;
  const target = event.target;
  if (target instanceof Element && target.closest('a, button, audio, input, textarea')) return;
  pointerMoved = false;
  const now = Date.now();
  if (canReact(message) && tapMessageId === message.id && now - tapStamp < 340) {
    tapMessageId = 0;
    tapStamp = 0;
    holdFired = false;
    cancelReactionHold();
    quickHeart(message);
    return;
  }
  if (!holdToReact.value || !canReact(message)) return;
  holdFired = false;
  reactionHoldPoint = { x: event.clientX, y: event.clientY };
  const bubble = event.currentTarget;
  window.clearTimeout(reactionHoldTimer);
  reactionHoldTimer = window.setTimeout(() => {
    holdFired = true;
    reactionHoldPoint = null;
    openReactionPicker(message, bubble);
  }, 420);
}

function onBubblePointerUp(message, event) {
  const moved = pointerMoved || (reactionHoldPoint
    ? Math.hypot(event.clientX - reactionHoldPoint.x, event.clientY - reactionHoldPoint.y) > 12
    : false);
  const held = holdFired;
  cancelReactionHold();
  if (held || moved || !canReact(message)) return;
  tapMessageId = message.id;
  tapStamp = Date.now();
}

function quickHeart(message) {
  if (!canReact(message)) return;
  if (myReaction(message) === '❤️') {
    playBurst('❤️');
    return;
  }
  toggleReaction(message, '❤️');
}

function senderMood(message) {
  const member = store.activeConversation?.members?.find((item) => item.id === message.sender?.id);
  return moodById(member?.mood);
}

function onBubblePointerMove(event) {
  if (!reactionHoldPoint) return;
  if (Math.hypot(event.clientX - reactionHoldPoint.x, event.clientY - reactionHoldPoint.y) > 12) {
    pointerMoved = true;
    cancelReactionHold();
  }
}

function onBubbleContextMenu(event) {
  if (!holdToReact.value) return;
  event.preventDefault();
}

function openReactionPicker(message, bubble) {
  if (!(bubble instanceof HTMLElement) || !canReact(message)) return;
  const rect = bubble.getBoundingClientRect();
  const width = 272;
  const barHeight = 52;
  const gap = 8;
  const left = Math.max(8, Math.min(rect.left + (rect.width - width) / 2, window.innerWidth - width - 8));
  let top = rect.top - barHeight - gap;
  if (top < 8) top = Math.min(rect.bottom + gap, window.innerHeight - barHeight - 8);
  reactionPicker.value = { id: message.id, message, top, left };
}

async function toggleReaction(message, emoji) {
  if (!canReact(message) || !store.activeConversationId) return;
  const removing = myReaction(message) === emoji;
  menu.value = null;
  reactionPicker.value = null;
  try {
    await store.reactToMessage(store.activeConversationId, message.id, emoji);
    if (!removing && (emoji === '❤️' || emoji === '😂')) playBurst(emoji);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không thả được cảm xúc.');
  }
}

function sendQuick(text) {
  if (editing.value || sending.value || !store.activeConversationId) return;
  const replyId = replyTo.value?.id ?? null;
  replyTo.value = null;
  draft.value = '';
  queueOutgoing({ kind: 'text', text, replyId });
}

function sendBirthdayWish() {
  if (editing.value || sending.value) return;
  sendSticker(BIRTHDAY_STICKER);
}

async function chooseMood(mood) {
  try {
    await store.setMood(mood);
    funSheet.value = null;
  } catch (error) {
    showClientToast('error', error?.response?.data?.errors?.mood?.[0] ?? 'Không lưu được tâm trạng.');
  }
}

async function chooseAccent(accent) {
  if (!store.activeConversationId) return;
  try {
    await store.setGroupAccent(store.activeConversationId, accent);
    funSheet.value = null;
  } catch (error) {
    showClientToast('error', error?.response?.data?.errors?.accent?.[0] ?? 'Không đổi được màu nhóm.');
  }
}

function openFun(kind) {
  headerMenu.value = null;
  funSheet.value = funSheet.value === kind ? null : kind;
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
    items.push({ key: 'pin', label: 'Ghim tin', icon: 'pin', run: () => pinThis(message) });
    if (isGroup.value && groupReadOthersCount() > 0) {
      items.push({ key: 'reads', label: 'Xem đã đọc', icon: 'users', run: () => showReaders(message) });
    }
    items.push({ key: 'recall', label: 'Thu hồi', icon: 'rotateCw', run: () => askRecall(message) });
  }
  items.push({ key: 'delete', label: 'Xoá', icon: 'trash', run: () => askDelete(message) });
  return items;
}
</script>

<template>
  <div class="chat-view" :data-accent="groupAccent || null">
    <div class="chat-view__head">
      <button type="button" class="chat-view__icon-btn" aria-label="Quay lại danh sách" @click="store.backToList()">
        <AppIcon name="chevronLeft" :size="18" :stroke-width="1.75" />
      </button>
      <button
        v-if="isGroup"
        type="button"
        class="chat-view__avatar-btn"
        :aria-label="isOwner ? 'Ảnh nhóm và thành viên' : 'Xem thành viên nhóm'"
        @click="openMembers"
      >
        <img
          v-if="groupAvatarUrl"
          class="chat-view__avatar"
          :src="groupAvatarUrl"
          :alt="`Ảnh nhóm ${headerTitle}`"
        />
        <span v-else class="chat-view__avatar chat-view__avatar--group" aria-hidden="true">
          <AppIcon name="users" :size="16" :stroke-width="1.75" />
        </span>
      </button>
      <img
        v-else-if="otherUser?.avatar_url"
        class="chat-view__avatar"
        :src="otherUser.avatar_url"
        :alt="`Ảnh đại diện của ${otherUser.name}`"
      />
      <span v-else class="chat-view__avatar chat-view__avatar--placeholder">{{ initial(otherUser?.name) }}</span>
      <span class="chat-view__who">
        <span class="chat-view__title">{{ headerTitle }}</span>
        <Transition name="chat-status" mode="out-in">
          <span
            v-if="peerTyping"
            key="typing"
            class="chat-view__dept chat-view__dept--active"
          >
            <span class="chat-typing" aria-hidden="true"><span /><span /><span /></span>
            {{ typingLabel }}
          </span>
          <span
            v-else-if="!isGroup && otherUser?.online"
            key="online"
            class="chat-view__dept chat-view__dept--active"
          >
            Đang hoạt động
          </span>
          <span
            v-else-if="!isGroup && otherUser?.last_seen_at"
            key="seen"
            class="chat-view__dept"
          >
            Truy cập {{ formatRelativeTime(otherUser.last_seen_at) }}
          </span>
          <span v-else-if="isGroup" key="group" class="chat-view__dept">{{ memberLine }}</span>
          <span v-else-if="peerMetaLine" key="meta" class="chat-view__dept">{{ peerMetaLine }}</span>
        </Transition>
        <span v-if="peerMood" class="chat-view__mood">{{ peerMood.emoji }} {{ peerMood.label }}</span>
      </span>
      <button
        type="button"
        class="chat-view__icon-btn chat-view__head-menu"
        aria-label="Thao tác"
        aria-haspopup="menu"
        :aria-expanded="headerMenu !== null"
        @click="openHeaderMenu"
      >
        <AppIcon name="moreVertical" :size="18" :stroke-width="1.75" />
      </button>
    </div>

    <div v-if="birthdayNames.length || streakCount >= 2 || funSheet" class="chat-fun-stack">
      <div v-if="birthdayNames.length" class="chat-bday">
        <span>🎂 Hôm nay sinh nhật {{ birthdayNames.join(', ') }}</span>
        <button type="button" @click="sendBirthdayWish">Gửi sticker chúc mừng</button>
      </div>
      <p v-if="streakCount >= 2" class="chat-streak">🔥 {{ streakCount }} ngày nhắn liên tiếp</p>
      <div v-if="funSheet === 'mood'" class="chat-fun" role="group" aria-label="Chọn tâm trạng">
        <button
          v-for="mood in CHAT_MOODS"
          :key="mood.id"
          type="button"
          class="chat-fun__chip"
          :class="{ 'chat-fun__chip--on': store.activeConversation?.my_mood === mood.id }"
          @click="chooseMood(mood.id)"
        >
          {{ mood.emoji }} {{ mood.label }}
        </button>
        <button v-if="myMood" type="button" class="chat-fun__chip" @click="chooseMood(null)">Bỏ</button>
      </div>
      <div v-else-if="funSheet === 'accent'" class="chat-fun" role="group" aria-label="Màu nhóm">
        <button type="button" class="chat-fun__chip" :class="{ 'chat-fun__chip--on': !groupAccent }" @click="chooseAccent(null)">
          Mặc định
        </button>
        <button
          v-for="accent in CHAT_ACCENTS"
          :key="accent.id"
          type="button"
          class="chat-fun__chip"
          :data-accent="accent.id"
          :class="{ 'chat-fun__chip--on': groupAccent === accent.id }"
          @click="chooseAccent(accent.id)"
        >
          {{ accent.label }}
        </button>
      </div>
    </div>

    <button
      v-if="pinnedMessage"
      type="button"
      class="chat-pin"
      @click="jumpToHit(pinnedMessage)"
    >
      <AppIcon name="pin" :size="14" :stroke-width="1.75" />
      <span>{{ pinnedMessage.sender_name ? `${pinnedMessage.sender_name}: ` : '' }}{{ pinnedMessage.message }}</span>
    </button>

    <form v-if="searchOpen" class="chat-search" @submit.prevent="runSearch">
      <input v-model="searchQuery" type="search" placeholder="Tìm trong cuộc trò chuyện" aria-label="Tìm trong cuộc trò chuyện" />
      <button type="submit">Tìm</button>
      <button type="button" aria-label="Đóng tìm kiếm" @click="searchOpen = false"><AppIcon name="close" :size="14" /></button>
      <div v-if="searchHits.length" class="chat-search__hits">
        <button v-for="hit in searchHits" :key="hit.id" type="button" @click="jumpToHit(hit)">
          {{ hit.message }}
        </button>
      </div>
    </form>

    <div ref="listRef" class="chat-view__messages" :aria-busy="store.loadingMessages && store.activeMessages.length === 0" @scroll="onScroll">
      <div
        v-if="store.loadingMessages && store.activeMessages.length === 0"
        class="chat-skeleton"
        aria-label="Đang tải tin nhắn"
      >
        <span class="chat-skeleton__row"><span class="chat-skeleton__bubble" /></span>
        <span class="chat-skeleton__row chat-skeleton__row--mine"><span class="chat-skeleton__bubble chat-skeleton__bubble--short" /></span>
        <span class="chat-skeleton__row"><span class="chat-skeleton__bubble chat-skeleton__bubble--long" /></span>
        <span class="chat-skeleton__row chat-skeleton__row--mine"><span class="chat-skeleton__bubble" /></span>
      </div>
      <p v-else-if="store.activeMessages.length === 0 && activeOutbox.length === 0" class="chat-view__empty">
        Chưa có tin nhắn. Hãy gửi lời chào, emoji hoặc sticker.
      </p>
      <template v-for="row in timeline" :key="row.kind === 'day' ? row.id : row.message.id">
        <div v-if="row.kind === 'day'" class="chat-day">{{ row.label }}</div>
        <div v-else-if="row.message.message_type === 'system'" class="chat-day">{{ row.message.message }}</div>
        <div
          v-else
          :id="`chat-msg-${row.message.id}`"
          class="chat-bubble-row"
          :class="{
            'chat-bubble-row--mine': isMine(row.message),
            'chat-bubble-row--animate': freshBubbleIds.has(row.message.id),
          }"
        >
          <span v-if="!isMine(row.message)" class="chat-bubble-row__avatar">
            <ChatAvatar
              v-if="avatarMessageIds.has(row.message.id)"
              :src="senderAvatarUrl(row.message)"
              :name="row.message.sender?.name || otherUser?.name || ''"
              size="1.75rem"
            />
          </span>
          <div
            class="chat-message-col"
            :class="{ 'chat-message-col--sticker': row.message.message_type === 'sticker' && !row.message.recalled_at }"
          >
          <div
            class="chat-bubble"
            :class="{
              'chat-bubble--mine': isMine(row.message),
              'chat-bubble--recalled': row.message.recalled_at,
              'chat-bubble--sticker': row.message.message_type === 'sticker' && !row.message.recalled_at,
              'chat-bubble--hold': holdToReact && canReact(row.message),
            }"
            @pointerdown="onBubblePointerDown(row.message, $event)"
            @pointermove="onBubblePointerMove"
            @pointerup="onBubblePointerUp(row.message, $event)"
            @pointercancel="cancelReactionHold"
            @contextmenu="onBubbleContextMenu"
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

            <span v-if="showSender(row.message)" class="chat-bubble__sender">
              <template v-if="senderMood(row.message)">{{ senderMood(row.message).emoji }} </template>{{ row.message.sender?.name }}
            </span>
            <span v-if="row.message.recalled_at" class="chat-bubble__recalled">
              <AppIcon name="rotateCw" :size="13" :stroke-width="1.75" />
              Tin nhắn đã được thu hồi
            </span>
            <span v-else-if="row.message.message_type === 'sticker' && row.message.sticker_id" class="chat-sticker">
              <SocialAnimatedSticker :id="row.message.sticker_id" :emoji="row.message.message || ''" />
            </span>
            <div v-else-if="row.message.message_type === 'poll' && row.message.poll" class="chat-poll-card">
              <div class="chat-poll-card__head">
                <span class="chat-poll-card__badge">
                  <AppIcon name="listChecks" :size="12" :stroke-width="2" />
                  Bình chọn
                </span>
                <span v-if="pollTotalVotes(row.message.poll) > 0" class="chat-poll-card__total">
                  {{ pollVotesLabel(pollTotalVotes(row.message.poll)) }}
                </span>
              </div>
              <p class="chat-poll-card__q">{{ row.message.poll.question }}</p>
              <div class="chat-poll-card__opts">
                <button
                  v-for="option in row.message.poll.options"
                  :key="option.id"
                  type="button"
                  class="chat-poll-card__opt"
                  :class="{ 'chat-poll-card__opt--on': pollOptionSelected(option) }"
                  @click="castVote(row.message, option)"
                >
                  <span
                    class="chat-poll-card__fill"
                    :style="{ width: `${pollOptionPercent(option, row.message.poll)}%` }"
                  />
                  <span class="chat-poll-card__row">
                    <span
                      class="chat-poll-card__mark"
                      :class="{ 'chat-poll-card__mark--on': pollOptionSelected(option) }"
                      aria-hidden="true"
                    >
                      <AppIcon v-if="pollOptionSelected(option)" name="check" :size="10" />
                    </span>
                    <span class="chat-poll-card__label">{{ option.label }}</span>
                    <span class="chat-poll-card__stat">
                      <span class="chat-poll-card__percent">{{ pollOptionPercent(option, row.message.poll) }}%</span>
                      <span v-if="option.votes > 0" class="chat-poll-card__votes">{{ pollVotesLabel(option.votes) }}</span>
                    </span>
                  </span>
                </button>
              </div>
            </div>
            <div
              v-else-if="row.message.message_type === 'voice'"
              class="chat-voice"
            >
              <AppIcon name="mic" :size="16" :stroke-width="1.75" />
              <span>{{ row.message.voice_duration || 1 }} giây</span>
              <audio :src="row.message.attachments?.[0]?.url" controls preload="none" />
            </div>
            <span v-else-if="row.message.message" class="chat-bubble__text">
              <template v-for="(part, partIndex) in messageParts(row.message.message)" :key="partIndex">
                <span v-if="part.mention" class="chat-mention">{{ part.text }}</span>
                <template v-else>{{ part.text }}</template>
              </template>
            </span>
            <div
              v-if="!row.message.recalled_at && imageFiles(row.message).length"
              class="chat-images"
              :class="{ 'chat-images--single': imageFiles(row.message).length === 1 }"
            >
              <button
                v-for="file in imageFiles(row.message)"
                :key="file.id"
                type="button"
                class="chat-images__link"
                :aria-label="`Xem ảnh ${file.name || ''}`"
                @click="openImage(file)"
              >
                <img :src="file.url" :alt="file.name || 'Ảnh'" />
              </button>
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
          <div
            v-if="reactionRows(row.message).length || readLabel(row.message)"
            class="chat-bubble-foot"
          >
            <div v-if="reactionRows(row.message).length" class="chat-reactions">
              <button
                v-for="chip in reactionRows(row.message)"
                :key="chip.emoji"
                type="button"
                class="chat-reactions__chip"
                :class="{ 'chat-reactions__chip--mine': chip.mine }"
                :aria-label="chip.aria"
                :aria-pressed="chip.mine"
                @click="toggleReaction(row.message, chip.emoji)"
              >
                <span class="chat-reactions__emoji" aria-hidden="true">{{ chip.emoji }}</span>
                <span class="chat-reactions__count">{{ chip.count }}</span>
              </button>
            </div>
            <button
              v-if="readLabel(row.message)"
              type="button"
              class="chat-read"
              :aria-label="readLabel(row.message)"
              @click="showReaders(row.message)"
            >
              <span class="chat-read__faces">
                <ChatAvatar
                  v-for="reader in readFaces(row.message)"
                  :key="reader.id"
                  class="chat-read__face"
                  :src="reader.avatar_url"
                  :name="reader.name"
                  size="1.05rem"
                />
              </span>
            </button>
          </div>
          <ChatLinkPreviewEmbed
            v-if="!row.message.recalled_at && row.message.message_type === 'text' && row.message.message"
            :text="row.message.message"
          />
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

      <div
        v-for="item in activeOutbox"
        :key="item.localId"
        class="chat-bubble-row chat-bubble-row--mine chat-bubble-row--pending"
      >
        <div class="chat-message-col">
          <div
            class="chat-bubble chat-bubble--mine"
            :class="{
              'chat-bubble--pending': item.status !== 'failed',
              'chat-bubble--failed': item.status === 'failed',
              'chat-bubble--sticker': item.kind === 'sticker',
            }"
          >
            <span v-if="item.kind === 'sticker' && item.stickerId" class="chat-sticker">
              <SocialAnimatedSticker :id="item.stickerId" :emoji="item.text || ''" />
            </span>
            <span v-else-if="item.text" class="chat-bubble__text">{{ item.text }}</span>
            <div
              v-if="item.previews?.length"
              class="chat-images"
              :class="{ 'chat-images--single': item.previews.length === 1 }"
            >
              <span v-for="url in item.previews" :key="url" class="chat-images__link">
                <img :src="url" alt="Ảnh đang gửi" />
              </span>
            </div>
            <span v-else-if="item.fileCount > 0" class="chat-bubble__pending-files">
              {{ item.fileCount > 1 ? `${item.fileCount} tệp đính kèm` : 'Tệp đính kèm' }}
            </span>
            <span class="chat-bubble__meta">
              <span v-if="item.status === 'failed'" class="chat-bubble__failed">
                <button type="button" @click="store.retryOutbox(item.localId)">Gửi lại</button>
                <button type="button" @click="store.removeOutbox(item.localId)">Xoá</button>
              </span>
              <span v-else class="chat-bubble__sending">
                <span class="chat-typing chat-typing--meta" aria-hidden="true"><span /><span /><span /></span>
                Đang gửi
              </span>
            </span>
          </div>
        </div>
      </div>

      <Transition name="chat-typing-row">
        <div v-if="peerTyping" key="typing-row" class="chat-bubble-row chat-bubble-row--typing" aria-live="polite">
          <div class="chat-bubble chat-bubble--typing">
            <span class="chat-typing" aria-hidden="true"><span /><span /><span /></span>
          </div>
        </div>
      </Transition>
    </div>

    <div class="chat-dock" :class="{ 'chat-dock--picker': pickerOpen }">
    <button
      v-if="unseenBelow > 0"
      type="button"
      class="chat-jump"
      @click="jumpToLatest"
    >
      {{ unseenBelow === 1 ? 'Tin mới' : `${unseenBelow} tin mới` }}
    </button>
    <div
      v-if="mentionOpen"
      id="chat-mention-list"
      class="chat-mentions hide-scrollbar"
      role="listbox"
      aria-label="Nhắc thành viên"
    >
      <button
        v-for="(user, index) in mentionHits"
        :id="`chat-mention-${user.id}`"
        :key="user.id"
        type="button"
        class="chat-mentions__item"
        :class="{ 'chat-mentions__item--on': index === mentionActive }"
        role="option"
        :aria-selected="index === mentionActive"
        @mousedown.prevent="pickMention(user)"
      >
        <ChatAvatar :src="user.avatar_url" :name="user.name" size="1.75rem" />
        <span class="chat-mentions__name">{{ user.name }}</span>
      </button>
    </div>
    <Transition name="chat-compose-banner">
      <div v-if="replyTo || editing" class="chat-compose-banner">
      <AppIcon :name="editing ? 'pencil' : 'reply'" :size="14" :stroke-width="1.75" />
      <span class="chat-compose-banner__copy">
        <span class="chat-compose-banner__label">{{
          editing ? (sending ? 'Đang lưu…' : 'Sửa tin nhắn') : `Trả lời ${replyTo?.sender?.name || ''}`
        }}</span>
        <span class="chat-compose-banner__text">{{ editing ? draft : quoteText(replyTo) }}</span>
      </span>
      <button type="button" class="chat-view__icon-btn" aria-label="Huỷ" @click="clearModes">
        <AppIcon name="close" :size="14" :stroke-width="1.75" />
      </button>
      </div>
    </Transition>

    <Transition name="chat-picker">
      <ChatPicker
        v-if="pickerOpen"
        :panel="pickerPanel"
        @update:panel="pickerPanel = $event"
        @pick-emoji="insertEmoji"
        @pick-sticker="sendSticker"
      />
    </Transition>

    <div class="chat-view__composer">
      <div v-if="showQuickReplies" class="chat-quick hide-scrollbar" aria-label="Trả lời nhanh">
        <button
          v-for="text in CHAT_QUICK_REPLIES"
          :key="text"
          type="button"
          class="chat-quick__chip"
          @click="sendQuick(text)"
        >
          {{ text }}
        </button>
      </div>
      <div class="chat-view__tools hide-scrollbar">
        <button
          type="button"
          class="chat-view__tool"
          :disabled="Boolean(editing)"
          aria-label="Chụp ảnh"
          @click="openAttachPicker('camera')"
        >
          <AppIcon name="camera" :size="17" :stroke-width="1.75" />
        </button>
        <button
          type="button"
          class="chat-view__tool"
          :disabled="Boolean(editing)"
          aria-label="Gửi ảnh"
          @click="openAttachPicker('photo')"
        >
          <AppIcon name="image" :size="17" :stroke-width="1.75" />
        </button>
        <button
          type="button"
          class="chat-view__tool"
          :disabled="Boolean(editing)"
          aria-label="Đính kèm tệp"
          @click="openAttachPicker('file')"
        >
          <AppIcon name="paperclip" :size="17" :stroke-width="1.75" />
        </button>
        <button
          type="button"
          class="chat-view__tool"
          :class="{ 'chat-view__tool--on': pickerOpen && pickerPanel === 'sticker' }"
          :disabled="Boolean(editing)"
          aria-label="Gửi sticker"
          @click="togglePicker('sticker')"
        >
          <AppIcon name="sticker" :size="17" :stroke-width="1.75" />
        </button>
        <button
          v-if="isGroup"
          type="button"
          class="chat-view__tool"
          :disabled="Boolean(editing)"
          aria-label="Nhắc thành viên"
          @click="insertMentionTrigger"
        >
          <span class="chat-view__at" aria-hidden="true">@</span>
        </button>
        <button
          type="button"
          class="chat-view__tool"
          :disabled="Boolean(editing)"
          aria-label="Tạo bình chọn"
          @click="pollOpen = true"
        >
          <AppIcon name="listChecks" :size="17" :stroke-width="1.75" />
        </button>
      </div>
      <input
        ref="cameraInput"
        class="chat-view__file-input"
        type="file"
        accept="image/*"
        capture="environment"
        tabindex="-1"
        aria-hidden="true"
        @cancel="onAttachCancel"
        @change="onFilesChosen"
      />
      <input
        ref="photoInput"
        class="chat-view__file-input"
        type="file"
        accept="image/*"
        multiple
        tabindex="-1"
        aria-hidden="true"
        @cancel="onAttachCancel"
        @change="onFilesChosen"
      />
      <input
        ref="fileInput"
        class="chat-view__file-input"
        type="file"
        multiple
        accept=".pdf,.doc,.docx,.xlsx,.xls,.ppt,.pptx,.txt,.csv,.zip,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/plain,text/csv,application/zip"
        tabindex="-1"
        aria-hidden="true"
        @cancel="onAttachCancel"
        @change="onFilesChosen"
      />
      <Transition name="chat-draft-files">
      <div v-if="files.length" class="chat-pending">
        <span
          v-for="(item, index) in files"
          :key="`${item.file.name}-${index}`"
          class="chat-pending__item"
          :class="{ 'chat-pending__item--image': item.previewUrl }"
        >
          <img v-if="item.previewUrl" class="chat-pending__thumb" :src="item.previewUrl" alt="" />
          <AppIcon v-else name="fileText" :size="14" :stroke-width="1.75" />
          <span class="chat-pending__name">{{ item.file.name }}</span>
          <button type="button" class="chat-pending__remove" :aria-label="`Bỏ ${item.file.name}`" @click="removeFile(index)">
            <AppIcon name="close" :size="12" :stroke-width="1.75" />
          </button>
        </span>
      </div>
      </Transition>
      <div class="chat-view__input-shell" :class="{ 'chat-view__input-shell--stack': draftMentionChips.length }">
        <div v-if="draftMentionChips.length" class="chat-compose-mentions" aria-label="Đang nhắc">
          <span v-for="user in draftMentionChips" :key="user.id" class="chat-compose-mentions__chip">
            <ChatAvatar :src="user.avatar_url" :name="user.name" size="1rem" />
            {{ user.name }}
          </span>
        </div>
        <div class="chat-view__input-row">
        <button
          type="button"
          class="chat-view__input-addon"
          :class="{ 'chat-view__input-addon--on': pickerOpen && pickerPanel === 'emoji' }"
          aria-label="Chèn emoji"
          @click="togglePicker('emoji')"
        >
          <AppIcon name="smile" :size="20" :stroke-width="1.75" />
        </button>
        <div class="chat-view__field">
          <div class="chat-view__field-mirror" aria-hidden="true">
            <template v-for="(part, partIndex) in draftParts" :key="partIndex">
              <span v-if="part.mention" class="chat-mention">{{ part.text }}</span>
              <template v-else>{{ part.text }}</template>
            </template>
            <br v-if="draft.endsWith('\n') || draft === ''" />
          </div>
          <textarea
            ref="inputRef"
            v-model="draft"
            class="chat-view__input"
            rows="1"
            enterkeyhint="send"
            role="combobox"
            aria-autocomplete="list"
            :aria-activedescendant="mentionOpen ? `chat-mention-${mentionHits[mentionActive]?.id}` : undefined"
            :aria-expanded="mentionOpen"
            aria-controls="chat-mention-list"
            :placeholder="editing ? 'Sửa nội dung…' : 'Nhập tin nhắn…'"
            :aria-label="editing ? 'Sửa tin nhắn' : `Nhập tin nhắn gửi ${headerTitle}`"
            @focus="onInputFocus"
            @blur="onInputBlur"
            @input="pingTyping"
            @scroll="syncFieldScroll"
            @click="syncCaret"
            @keyup="syncCaret"
            @select="syncCaret"
            @keydown="onKeydown"
          />
        </div>
        <button
          type="button"
          class="chat-view__send"
          :class="{ 'chat-view__send--busy': sending && editing, 'chat-view__send--ready': canSend && !sending }"
          :disabled="!canSend"
          :aria-label="sending && editing ? 'Đang gửi tin nhắn' : 'Gửi tin nhắn'"
          :aria-busy="sending && Boolean(editing)"
          @click="send"
        >
          <AppIcon v-if="sending && editing" name="refresh" :size="18" :stroke-width="1.75" class="chat-view__send-spin" />
          <AppIcon v-else :name="editing ? 'check' : 'send'" :size="18" :stroke-width="1.75" />
        </button>
      </div>
    </div>
    </div>
    </div>

    <Teleport to="body">
      <div
        v-if="lightbox"
        class="chat-lightbox"
        role="dialog"
        aria-modal="true"
        aria-label="Xem ảnh"
        @click.self="lightbox = null"
      >
        <button type="button" class="chat-lightbox__close" aria-label="Đóng ảnh" @click="lightbox = null">
          <AppIcon name="close" :size="18" :stroke-width="1.75" />
        </button>
        <img class="chat-lightbox__img" :src="lightbox.url" :alt="lightbox.name" />
      </div>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="headerMenu"
        class="chat-menu chat-menu--wide"
        role="menu"
        :style="{ top: `${headerMenu.top}px`, left: `${headerMenu.left}px` }"
      >
        <button type="button" class="chat-menu__item" role="menuitem" @click="openFun('mood')">
          {{ myMood ? `${myMood.emoji} Tâm trạng` : '✨ Tâm trạng' }}
        </button>
        <button v-if="isGroup && isOwner" type="button" class="chat-menu__item" role="menuitem" @click="openFun('accent')">
          Màu nhóm
        </button>
        <button type="button" class="chat-menu__item" role="menuitem" @click="askClearHistory">
          <AppIcon name="trash" :size="15" :stroke-width="1.75" />
          Xoá lịch sử
        </button>
        <button type="button" class="chat-menu__item" role="menuitem" @click="openSearch">
          <AppIcon name="search" :size="15" :stroke-width="1.75" />
          Tìm tin
        </button>
        <button type="button" class="chat-menu__item" role="menuitem" @click="openAlbum">
          <AppIcon name="image" :size="15" :stroke-width="1.75" />
          Ảnh và tệp
        </button>
        <button type="button" class="chat-menu__item" role="menuitem" @click="togglePinConversation">
          <AppIcon name="pin" :size="15" :stroke-width="1.75" />
          {{ store.activeConversation?.pinned_at ? 'Bỏ ghim' : 'Ghim hội thoại' }}
        </button>
        <button type="button" class="chat-menu__item" role="menuitem" @click="toggleMute">
          <AppIcon name="bell" :size="15" :stroke-width="1.75" />
          {{ store.activeConversation?.is_muted ? 'Bật thông báo' : 'Tắt thông báo' }}
        </button>
        <button type="button" class="chat-menu__item" role="menuitem" @click="chooseDisappear(null)">Tin tự xoá: tắt</button>
        <button type="button" class="chat-menu__item" role="menuitem" @click="chooseDisappear(86400)">Tin tự xoá: 24 giờ</button>
        <button type="button" class="chat-menu__item" role="menuitem" @click="chooseDisappear(604800)">Tin tự xoá: 7 ngày</button>
        <button v-if="isGroup" type="button" class="chat-menu__item" role="menuitem" @click="openMembers">
          <AppIcon name="users" :size="15" :stroke-width="1.75" />
          Thành viên
        </button>
        <button v-if="isGroup && isOwner" type="button" class="chat-menu__item" role="menuitem" @click="askDisband">
          <AppIcon name="users" :size="15" :stroke-width="1.75" />
          Giải tán nhóm
        </button>
        <button v-else-if="isGroup" type="button" class="chat-menu__item" role="menuitem" @click="askLeaveGroup">
          Rời nhóm
        </button>
      </div>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="menu"
        class="chat-menu"
        :class="{ 'chat-menu--react': canReact(menu.message) && !holdToReact }"
        role="menu"
        :style="{ top: `${menu.top}px`, left: `${menu.left}px` }"
      >
        <div
          v-if="canReact(menu.message) && !holdToReact"
          class="chat-menu__react"
          role="group"
          aria-label="Thả cảm xúc"
        >
          <button
            v-for="item in CHAT_REACTIONS"
            :key="item.emoji"
            type="button"
            class="chat-menu__emoji"
            :class="{ 'chat-menu__emoji--on': myReaction(menu.message) === item.emoji }"
            :aria-label="myReaction(menu.message) === item.emoji ? `Bỏ ${item.label}` : `Thả ${item.label}`"
            :aria-pressed="myReaction(menu.message) === item.emoji"
            @click="toggleReaction(menu.message, item.emoji)"
          >
            {{ item.emoji }}
          </button>
        </div>
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

    <Teleport to="body">
      <div
        v-if="reactionPicker"
        class="chat-react-bar"
        role="group"
        aria-label="Thả cảm xúc"
        :style="{ top: `${reactionPicker.top}px`, left: `${reactionPicker.left}px` }"
      >
        <button
          v-for="item in CHAT_REACTIONS"
          :key="item.emoji"
          type="button"
          class="chat-menu__emoji"
          :class="{ 'chat-menu__emoji--on': myReaction(reactionPicker.message) === item.emoji }"
          :aria-label="myReaction(reactionPicker.message) === item.emoji ? `Bỏ ${item.label}` : `Thả ${item.label}`"
          :aria-pressed="myReaction(reactionPicker.message) === item.emoji"
          @click="toggleReaction(reactionPicker.message, item.emoji)"
        >
          {{ item.emoji }}
        </button>
      </div>
    </Teleport>

    <ChatMemberDialog :open="membersOpen" @close="membersOpen = false" />
    <ChatPollDialog :open="pollOpen" @close="pollOpen = false" />
    <ChatAlbumDialog :open="albumOpen" @close="albumOpen = false" />
    <ChatReactionBurst :tick="burstTick" :emoji="burstEmoji" />
    <Teleport to="body">
      <div
        v-if="readersFor"
        class="chat-readers"
        role="presentation"
        @mousedown.self="readersFor = null"
      >
        <div class="chat-readers__panel" role="dialog" aria-modal="true" aria-labelledby="chat-readers-title">
          <div class="chat-readers__head">
            <h3 id="chat-readers-title" class="chat-readers__title">Đã xem</h3>
            <button type="button" class="chat-readers__close" aria-label="Đóng" @click="readersFor = null">
              <AppIcon name="close" :size="16" />
            </button>
          </div>
          <div class="chat-readers__body hide-scrollbar">
            <p class="chat-readers__section">Đã xem ({{ readerRows.length }})</p>
            <ul v-if="readerRows.length" class="chat-readers__list">
              <li v-for="reader in readerRows" :key="reader.id" class="chat-readers__row">
                <img
                  v-if="reader.avatar_url"
                  class="chat-readers__avatar"
                  :src="reader.avatar_url"
                  :alt="`Ảnh đại diện của ${reader.name}`"
                />
                <span v-else class="chat-readers__avatar chat-readers__avatar--ph">{{ initial(reader.name) }}</span>
                <span class="chat-readers__name">{{ reader.name }}</span>
              </li>
            </ul>
            <p v-else class="chat-readers__empty">Chưa có ai xem tin này.</p>
            <p v-if="readerPendingRows.length" class="chat-readers__section chat-readers__section--pending">
              Chưa xem ({{ readerPendingRows.length }})
            </p>
            <ul v-if="readerPendingRows.length" class="chat-readers__list">
              <li v-for="reader in readerPendingRows" :key="`pending-${reader.id}`" class="chat-readers__row chat-readers__row--pending">
                <img
                  v-if="reader.avatar_url"
                  class="chat-readers__avatar"
                  :src="reader.avatar_url"
                  :alt="`Ảnh đại diện của ${reader.name}`"
                />
                <span v-else class="chat-readers__avatar chat-readers__avatar--ph">{{ initial(reader.name) }}</span>
                <span class="chat-readers__name">{{ reader.name }}</span>
              </li>
            </ul>
          </div>
        </div>
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
  height: 100%;
  overflow: hidden;
  touch-action: manipulation;
  animation: chat-screen-in 180ms ease both;
  background:
    radial-gradient(
      ellipse 120% 80% at 50% -20%,
      color-mix(in srgb, var(--color-tertiary-100) 55%, transparent),
      transparent 55%
    ),
    radial-gradient(circle at 1px 1px, color-mix(in srgb, var(--color-border) 55%, transparent) 1px, transparent 0)
      0 0 / 16px 16px,
    color-mix(in srgb, var(--color-bg) 40%, var(--color-surface));
}

.chat-view__head {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3);
  color: var(--color-on-primary);
  background: linear-gradient(
    152deg,
    var(--color-tertiary-600) 0%,
    var(--color-tertiary-700) 48%,
    var(--color-tertiary-800) 100%
  );
  box-shadow: 0 4px 18px color-mix(in srgb, var(--color-tertiary-900) 18%, transparent);
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

.chat-view__send--busy {
  color: var(--color-primary);
  opacity: 0.65;
  cursor: wait;
}

.chat-view__icon-btn {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-sm);
  flex-shrink: 0;
}

.chat-view__head .chat-view__icon-btn {
  color: color-mix(in srgb, var(--color-on-primary) 88%, transparent);
}

.chat-view__head .chat-view__icon-btn:hover {
  background: color-mix(in srgb, var(--color-on-primary) 12%, transparent);
  color: var(--color-on-primary);
}

.chat-view__tool:hover,
.chat-bubble__more:hover {
  background: var(--color-surface-muted);
  color: var(--color-primary);
}

.chat-view__avatar-btn {
  display: flex;
  flex-shrink: 0;
  padding: 0;
  border: none;
  background: transparent;
  cursor: pointer;
  border-radius: var(--radius-full);
}

.chat-view__avatar-btn:focus-visible {
  outline: 2px solid var(--color-on-primary);
  outline-offset: 2px;
}

.chat-view__avatar {
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
  display: block;
}

.chat-view__avatar--placeholder,
.chat-view__avatar--group {
  display: flex;
  align-items: center;
  justify-content: center;
  background: color-mix(in srgb, var(--color-on-primary) 18%, transparent);
  color: var(--color-on-primary);
  font-size: 0.75rem;
  font-weight: 700;
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-on-primary) 25%, transparent);
}

.chat-view__who {
  min-width: 0;
  flex: 1;
  display: flex;
  flex-direction: column;
}

.chat-view__title {
  color: var(--color-on-primary);
  font-size: 0.9375rem;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  letter-spacing: -0.01em;
}

.chat-view__dept {
  display: inline-flex;
  align-items: center;
  color: color-mix(in srgb, var(--color-on-primary) 78%, transparent);
  font-size: 0.6875rem;
}

.chat-view__dept--active {
  color: color-mix(in srgb, var(--color-on-primary) 92%, transparent);
  font-style: italic;
}

.chat-view__messages {
  flex: 1 1 auto;
  min-height: 0;
  overflow-x: hidden;
  overflow-y: auto;
  overflow-anchor: none;
  overscroll-behavior: contain;
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

.chat-bubble-row--animate {
  animation: chat-bubble-in 280ms ease both;
}

.chat-bubble-row--mine {
  flex-direction: row-reverse;
}

.chat-bubble-row__avatar {
  display: flex;
  flex-shrink: 0;
  width: 1.75rem;
  margin-right: 0.3rem;
}

.chat-message-col {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  max-width: 88%;
  min-width: 0;
}

.chat-bubble-row--mine .chat-message-col {
  align-items: flex-end;
}

.chat-bubble {
  max-width: 100%;
  width: fit-content;
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
  background: linear-gradient(
    145deg,
    color-mix(in srgb, var(--color-primary) 14%, var(--color-surface)),
    var(--color-primary-surface)
  );
  box-shadow: 0 2px 10px color-mix(in srgb, var(--color-primary) 10%, transparent);
}

.chat-bubble--hold {
  -webkit-touch-callout: none;
  -webkit-user-select: none;
  user-select: none;
}

.chat-bubble--pending {
  opacity: 0.92;
  transition: opacity 220ms ease;
}

.chat-bubble__sending {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-style: italic;
  color: var(--color-primary);
}

.chat-bubble--failed {
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-danger) 45%, var(--color-border));
}

.chat-bubble__failed {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
}

.chat-bubble__failed button {
  border: none;
  background: transparent;
  padding: 0;
  color: var(--color-danger);
  font: inherit;
  font-size: 0.6875rem;
  font-weight: 700;
  cursor: pointer;
}

.chat-jump {
  align-self: center;
  margin: 0.35rem auto 0;
  padding: 0.3rem 0.75rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-primary);
  box-shadow: 0 4px 14px color-mix(in srgb, var(--color-tertiary-900) 12%, transparent);
  font: inherit;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
}

.chat-skeleton {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  padding: 0.4rem 0;
}

.chat-skeleton__row {
  display: flex;
}

.chat-skeleton__row--mine {
  justify-content: flex-end;
}

.chat-skeleton__bubble {
  width: 46%;
  height: 2.4rem;
  border-radius: 14px;
  background: linear-gradient(
    90deg,
    var(--color-surface-muted) 0%,
    color-mix(in srgb, var(--color-primary) 10%, var(--color-surface-muted)) 50%,
    var(--color-surface-muted) 100%
  );
  background-size: 200% 100%;
  animation: chat-shimmer 1.4s ease-in-out infinite;
}

.chat-skeleton__bubble--short {
  width: 28%;
}

.chat-skeleton__bubble--long {
  width: 62%;
}

.chat-bubble__pending-files {
  font-size: 0.75rem;
  color: var(--color-text-muted);
  font-style: italic;
}

.chat-bubble--sticker {
  align-items: flex-end;
  gap: 0.2rem;
  background: transparent;
  box-shadow: none;
  padding: 0;
}

.chat-bubble--sticker .chat-bubble__meta {
  margin-top: 0.1rem;
  padding: 0.05rem 0.4rem;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-surface) 92%, transparent);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.chat-bubble--recalled {
  background: var(--color-surface-muted);
  box-shadow: none;
}

.chat-bubble__sender {
  color: var(--color-primary);
  font-size: 0.6875rem;
  font-weight: 700;
}

.chat-bubble-row--typing {
  animation: none;
}

.chat-bubble--typing {
  flex-direction: row;
  align-items: center;
  justify-content: center;
  min-width: 2.75rem;
  padding: 0.55rem 0.75rem;
  color: var(--color-text-muted);
}

.chat-bubble__text {
  font-size: 0.8125rem;
  white-space: pre-wrap;
  word-break: break-word;
}

.chat-mention {
  font-weight: 700;
  color: var(--color-primary);
}

.chat-bubble--mine .chat-mention {
  color: var(--color-tertiary-800);
}

.chat-bubble-foot {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.35rem;
  max-width: 100%;
  margin-top: 0.15rem;
}

.chat-bubble-row--mine .chat-bubble-foot {
  justify-content: flex-end;
}

.chat-message-col--sticker .chat-bubble-foot {
  margin-top: 0.35rem;
}

.chat-reactions {
  position: relative;
  z-index: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 0.2rem;
  margin-top: 0;
  padding-inline: 0;
}

.chat-bubble-row--mine .chat-reactions {
  justify-content: flex-end;
}

.chat-reactions__chip {
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
  min-height: 1.5rem;
  padding: 0.05rem 0.45rem 0.05rem 0.3rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow:
    0 3px 10px color-mix(in srgb, var(--color-tertiary-900) 12%, transparent),
    inset 0 0 0 1px color-mix(in srgb, var(--color-border) 75%, transparent);
  cursor: pointer;
  font-family: inherit;
  -webkit-tap-highlight-color: transparent;
}

.chat-reactions__chip:active {
  transform: scale(0.96);
}

.chat-reactions__chip--mine {
  background: var(--color-primary-surface);
  box-shadow:
    0 3px 10px color-mix(in srgb, var(--color-primary) 16%, transparent),
    inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 40%, var(--color-border));
}

.chat-reactions__emoji {
  font-size: 0.95rem;
  line-height: 1;
}

.chat-reactions__count {
  font-size: 0.6875rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
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
  flex-wrap: wrap;
  justify-content: flex-end;
  align-items: center;
  gap: 0.35rem;
  color: var(--color-text-muted);
  font-size: 0.625rem;
}

.chat-sticker {
  display: block;
  width: 6.5rem;
  height: 6.5rem;
  overflow: hidden;
}

.chat-sticker :deep(.social-sticker) {
  width: 6.5rem;
  height: 6.5rem;
  overflow: hidden;
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

.chat-dock {
  position: relative;
  z-index: 2;
  flex: 0 0 auto;
  display: flex;
  flex-direction: column;
  min-height: 0;
  overflow: visible;
  background: var(--color-surface);
  box-shadow: 0 -4px 18px color-mix(in srgb, var(--color-tertiary-900) 6%, transparent);
}

.chat-view__composer {
  position: relative;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  padding: var(--space-2) var(--space-3) var(--space-3);
  background: var(--color-surface);
}

.chat-view__tools {
  display: flex;
  align-items: center;
  flex-wrap: nowrap;
  gap: 0.15rem;
  overflow-x: auto;
  scrollbar-width: none;
  touch-action: manipulation;
  padding: 0 0.1rem;
}

.chat-view__tools::-webkit-scrollbar {
  display: none;
}

.chat-view__tool {
  width: 2rem;
  height: 2rem;
  flex: 0 0 2rem;
  border-radius: var(--radius-full);
  color: var(--color-text-muted);
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
}

.chat-view__tool:active {
  transform: scale(0.94);
}

.chat-view__tool--on {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.chat-view__tool:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.chat-view__at {
  font-size: 0.95rem;
  font-weight: 700;
  line-height: 1;
}

.chat-view__file-input {
  position: fixed;
  top: 0;
  left: 0;
  width: 1px;
  height: 1px;
  margin: 0;
  padding: 0;
  overflow: hidden;
  opacity: 0;
  border: 0;
}

.chat-draft-files-enter-active,
.chat-draft-files-leave-active {
  overflow: hidden;
  transition:
    opacity 160ms ease,
    transform 180ms ease,
    max-height 180ms ease;
}

.chat-draft-files-enter-from,
.chat-draft-files-leave-to {
  opacity: 0;
  max-height: 0;
  transform: translateY(8px);
}

.chat-draft-files-enter-to,
.chat-draft-files-leave-from {
  max-height: 8rem;
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

.chat-pending__item--image {
  padding-left: 0.2rem;
}

.chat-pending__thumb {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 6px;
  object-fit: cover;
  flex-shrink: 0;
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

.chat-images--single {
  grid-template-columns: minmax(0, 14rem);
}

.chat-images__link {
  display: block;
  border: none;
  padding: 0;
  border-radius: 8px;
  overflow: hidden;
  background: transparent;
  cursor: pointer;
  font: inherit;
}

.chat-images__link img {
  display: block;
  width: 100%;
  height: 6.5rem;
  object-fit: cover;
}

.chat-images--single .chat-images__link img {
  height: auto;
  max-height: 16rem;
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

.chat-view__input-shell {
  display: flex;
  align-items: flex-end;
  gap: 0.25rem;
  padding: 0.3rem 0.35rem 0.3rem 0.25rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  transition: box-shadow 180ms ease;
}

.chat-view__input-shell--stack {
  flex-direction: column;
  align-items: stretch;
  gap: 0.2rem;
  border-radius: 16px;
  padding-top: 0.4rem;
}

.chat-view__input-row {
  display: flex;
  align-items: flex-end;
  gap: 0.25rem;
  width: 100%;
  min-width: 0;
}

.chat-compose-mentions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem;
  padding: 0 0.35rem 0.15rem 0.45rem;
}

.chat-compose-mentions__chip {
  display: inline-flex;
  align-items: center;
  gap: 0.28rem;
  max-width: 100%;
  padding: 0.1rem 0.45rem 0.1rem 0.12rem;
  border-radius: var(--radius-full);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 0.75rem;
  font-weight: 700;
}

.chat-view__input-shell:focus-within {
  box-shadow:
    inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 45%, var(--color-border)),
    0 4px 14px color-mix(in srgb, var(--color-primary) 8%, transparent);
}

.chat-view__input-addon {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 2.25rem;
  height: 2.25rem;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.chat-view__input-addon--on {
  color: var(--color-primary);
  background: var(--color-primary-surface);
}

.chat-view__field {
  position: relative;
  flex: 1;
  min-width: 0;
  min-height: 2.25rem;
  max-height: 6rem;
}

.chat-view__field-mirror {
  min-height: 2.25rem;
  max-height: 6rem;
  overflow: auto;
  padding: 0.5rem 0.15rem;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 16px;
  line-height: 1.35;
  white-space: pre-wrap;
  word-break: break-word;
  pointer-events: none;
  scrollbar-width: none;
}

.chat-view__field-mirror::-webkit-scrollbar {
  display: none;
}

.chat-view__input {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  max-height: none;
  margin: 0;
  resize: none;
  border: none;
  border-radius: 0;
  background: transparent;
  color: transparent;
  caret-color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 16px;
  line-height: 1.35;
  padding: 0.5rem 0.15rem;
  overflow: auto;
}

.chat-view__input::placeholder {
  color: var(--color-text-muted);
}

.chat-view__input:focus {
  outline: none;
}

.chat-view__send {
  flex-shrink: 0;
  width: 2.35rem;
  height: 2.35rem;
  border-radius: var(--radius-full);
  background: linear-gradient(145deg, var(--color-primary), var(--color-tertiary-600));
  color: var(--color-on-primary);
  transition:
    transform 160ms ease,
    opacity 160ms ease,
    box-shadow 160ms ease;
}

.chat-view__send--ready {
  box-shadow: 0 4px 12px color-mix(in srgb, var(--color-primary) 28%, transparent);
}

.chat-view__send-spin {
  animation: chat-send-spin 0.75s linear infinite;
}

@keyframes chat-send-spin {
  to {
    transform: rotate(360deg);
  }
}

.chat-view__send:active:not(:disabled) {
  transform: scale(0.94);
}

.chat-view__send:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  animation: none;
}

</style>

<style>
.chat-lightbox {
  position: fixed;
  inset: 0;
  z-index: 320;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
  background: color-mix(in srgb, var(--color-tertiary-900) 72%, transparent);
}

.chat-lightbox__img {
  max-width: min(100%, 48rem);
  max-height: calc(100vh - 5rem);
  object-fit: contain;
  border-radius: var(--radius-md);
  background: var(--color-surface);
}

.chat-lightbox__close {
  position: absolute;
  top: var(--space-3);
  right: var(--space-3);
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
}

.chat-menu {
  position: fixed;
  z-index: 80;
  width: 10.5rem;
  max-height: 70vh;
  overflow: auto;
  padding: 0.25rem;
  border-radius: 10px;
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
}

.chat-menu--wide {
  width: 14rem;
}

.chat-menu--react {
  width: 16.5rem;
}

.chat-menu__react {
  display: flex;
  gap: 0.12rem;
  margin: 0.15rem 0.2rem 0.4rem;
  padding: 0.2rem 0.25rem;
  border-radius: 999px;
  background: var(--color-surface-muted);
}

.chat-menu__emoji {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 1 1 0;
  height: 2.15rem;
  border: none;
  border-radius: 999px;
  background: transparent;
  font-size: 1.28rem;
  line-height: 1;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
  transition:
    transform 140ms ease,
    background 140ms ease;
}

.chat-menu__emoji--on,
.chat-menu__emoji:hover {
  background: var(--color-primary-surface);
}

.chat-menu__emoji:active {
  transform: scale(1.12);
}

.chat-react-bar {
  position: fixed;
  z-index: 90;
  display: flex;
  align-items: center;
  gap: 0.15rem;
  width: 17rem;
  padding: 0.3rem 0.4rem;
  border-radius: 999px;
  background: var(--color-surface);
  box-shadow:
    0 12px 28px color-mix(in srgb, var(--color-tertiary-900) 18%, transparent),
    inset 0 0 0 1px color-mix(in srgb, var(--color-border) 80%, transparent);
  animation: chat-react-pop 180ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

.chat-react-bar .chat-menu__emoji {
  position: relative;
  height: 2.5rem;
  font-size: 1.45rem;
}

.chat-react-bar .chat-menu__emoji--on {
  transform: translateY(-3px) scale(1.08);
  box-shadow: inset 0 0 0 1.5px color-mix(in srgb, var(--color-primary) 55%, transparent);
}

.chat-react-bar .chat-menu__emoji--on::after {
  content: '';
  position: absolute;
  left: 50%;
  bottom: 0.12rem;
  width: 0.28rem;
  height: 0.28rem;
  border-radius: 999px;
  background: var(--color-primary);
  transform: translateX(-50%);
}

.chat-react-bar .chat-menu__emoji--on:active {
  transform: translateY(-1px) scale(1.02);
}

@keyframes chat-react-pop {
  from {
    opacity: 0;
    transform: translateY(6px) scale(0.92);
  }
  to {
    opacity: 1;
    transform: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .chat-react-bar,
  .chat-menu__emoji,
  .chat-reactions__chip {
    animation: none;
    transition: none;
  }
}

.chat-pin,
.chat-search {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin: 0 0.6rem 0.4rem;
  padding: 0.4rem 0.55rem;
  border: 0;
  border-radius: 10px;
  background: var(--color-primary-surface, var(--color-surface));
  color: var(--color-text);
  text-align: left;
}

.chat-search {
  flex-wrap: wrap;
}

.chat-search input {
  flex: 1;
  min-width: 8rem;
  border: 0;
  background: transparent;
  color: inherit;
}

.chat-search__hits {
  display: flex;
  flex-direction: column;
  width: 100%;
}

.chat-mentions {
  position: absolute;
  left: var(--space-3);
  right: var(--space-3);
  bottom: calc(100% + 0.35rem);
  z-index: 8;
  display: flex;
  flex-direction: column;
  max-height: min(16rem, 42dvh);
  overflow: auto;
  padding: 0.3rem;
  border-radius: 12px;
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    0 12px 32px color-mix(in srgb, var(--color-tertiary-900) 16%, transparent);
}

.chat-mentions__item {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  width: 100%;
  min-height: 2.5rem;
  padding: 0.3rem 0.45rem;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: var(--color-text);
  text-align: left;
  cursor: pointer;
  font-family: inherit;
}

.chat-mentions__item--on,
.chat-mentions__item:hover {
  background: var(--color-primary-surface);
}

.chat-mentions__name {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.8125rem;
  font-weight: 600;
}

.chat-poll-card {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  min-width: min(100%, 16rem);
  max-width: 20rem;
  padding: 0.55rem 0.6rem;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 14%, var(--color-border));
}

.chat-bubble--mine .chat-poll-card {
  background: color-mix(in srgb, var(--color-on-primary) 12%, transparent);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-on-primary) 22%, transparent);
}

.chat-poll-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.chat-poll-card__badge {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.625rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-primary);
}

.chat-bubble--mine .chat-poll-card__badge {
  color: inherit;
  opacity: 0.92;
}

.chat-poll-card__total {
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.chat-bubble--mine .chat-poll-card__total {
  color: inherit;
  opacity: 0.78;
}

.chat-poll-card__q {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 700;
  line-height: 1.35;
  color: var(--color-text);
}

.chat-bubble--mine .chat-poll-card__q {
  color: inherit;
}

.chat-poll-card__opts {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.chat-poll-card__opt {
  position: relative;
  display: block;
  width: 100%;
  overflow: hidden;
  padding: 0;
  border: none;
  border-radius: calc(var(--radius-md) - 2px);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: inherit;
  text-align: left;
  cursor: pointer;
  transition: background 0.15s ease;
}

.chat-bubble--mine .chat-poll-card__opt {
  background: color-mix(in srgb, var(--color-on-primary) 10%, transparent);
  color: inherit;
}

.chat-poll-card__opt:hover {
  background: color-mix(in srgb, var(--color-primary) 10%, var(--color-surface-muted));
}

.chat-bubble--mine .chat-poll-card__opt:hover {
  background: color-mix(in srgb, var(--color-on-primary) 18%, transparent);
}

.chat-poll-card__opt--on {
  background: var(--color-primary-surface);
}

.chat-bubble--mine .chat-poll-card__opt--on {
  background: color-mix(in srgb, var(--color-on-primary) 22%, transparent);
}

.chat-poll-card__fill {
  position: absolute;
  inset: 0 auto 0 0;
  background: color-mix(in srgb, var(--color-primary) 14%, var(--color-surface-muted));
  transition: width 0.35s ease;
  pointer-events: none;
}

.chat-bubble--mine .chat-poll-card__fill {
  background: color-mix(in srgb, var(--color-on-primary) 16%, transparent);
}

.chat-poll-card__opt--on .chat-poll-card__fill {
  background: color-mix(in srgb, var(--color-primary) 22%, var(--color-surface));
}

.chat-poll-card__row {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  min-height: 2rem;
  padding: 0.35rem 0.45rem;
}

.chat-poll-card__mark {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 0.85rem;
  height: 0.85rem;
  flex-shrink: 0;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-border) 55%, var(--color-surface));
}

.chat-poll-card__mark--on {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.chat-bubble--mine .chat-poll-card__mark--on {
  background: var(--color-surface);
  color: var(--color-primary);
}

.chat-poll-card__label {
  flex: 1;
  min-width: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.3;
  word-break: break-word;
}

.chat-poll-card__stat {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  flex-shrink: 0;
  gap: 0.05rem;
  min-width: 2.5rem;
}

.chat-poll-card__percent {
  font-variant-numeric: tabular-nums;
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--color-primary);
}

.chat-bubble--mine .chat-poll-card__percent {
  color: inherit;
}

.chat-poll-card__votes {
  font-size: 0.625rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.chat-bubble--mine .chat-poll-card__votes {
  color: inherit;
  opacity: 0.75;
}

.chat-voice,
.chat-read,
.chat-search button {
  border: 0;
  background: transparent;
  color: var(--color-text);
  cursor: pointer;
}

.chat-read {
  align-self: center;
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.15rem;
  min-height: 1.15rem;
  margin-top: 0;
  padding: 0;
  line-height: 0;
  color: var(--color-primary);
}

.chat-read :deep(.app-icon) {
  width: 14px;
  height: 14px;
  max-width: none;
}

.chat-read__faces {
  display: inline-flex;
  align-items: center;
}

.chat-read__face {
  box-shadow: 0 0 0 1.5px var(--color-surface);
}

.chat-read__face + .chat-read__face {
  margin-left: -0.28rem;
}

.chat-voice {
  display: flex;
  justify-content: space-between;
  gap: 0.5rem;
}

.chat-readers {
  position: fixed;
  inset: 0;
  z-index: 280;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding: 0;
  background: var(--color-sidebar-overlay);
}

.chat-readers__panel {
  width: min(26rem, 100%);
  max-height: min(72vh, 28rem);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.chat-readers__head {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.chat-readers__title {
  flex: 1;
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
}

.chat-readers__close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.chat-readers__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 0 var(--space-3) var(--space-3);
}

.chat-readers__section {
  margin: var(--space-2) 0 0.35rem;
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.chat-readers__section--pending {
  margin-top: var(--space-3);
}

.chat-readers__list {
  margin: 0;
  padding: 0;
  list-style: none;
}

.chat-readers__row--pending .chat-readers__name {
  color: var(--color-text-muted);
  font-style: italic;
}

.chat-readers__row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.45rem 0.35rem;
}

.chat-readers__avatar {
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
}

.chat-readers__avatar--ph {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 0.75rem;
  font-weight: 700;
}

.chat-readers__name {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text);
}

.chat-readers__empty {
  margin: 0;
  padding: var(--space-4);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

@media (min-width: 640px) {
  .chat-readers {
    align-items: center;
    padding: var(--space-5);
  }

  .chat-readers__panel {
    border-radius: var(--radius-lg);
  }
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

.chat-view__mood {
  color: color-mix(in srgb, var(--color-on-primary) 92%, transparent);
  font-size: 0.6875rem;
  font-style: italic;
}

.chat-fun-stack {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding: 0.45rem 0.7rem 0;
  flex-shrink: 0;
}

.chat-bday,
.chat-streak,
.chat-fun {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.35rem;
}

.chat-bday,
.chat-streak {
  margin: 0;
  color: var(--color-text);
  font-size: 0.75rem;
}

.chat-bday button,
.chat-fun__chip,
.chat-quick__chip {
  border: 0;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
  font-family: inherit;
  font-size: 0.75rem;
  padding: 0.28rem 0.65rem;
  cursor: pointer;
}

.chat-fun__chip--on,
.chat-bday button {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.chat-quick {
  display: flex;
  gap: 0.35rem;
  overflow-x: auto;
  padding-bottom: 0.35rem;
}

.chat-quick__chip {
  flex-shrink: 0;
}

.chat-view[data-accent='coral'] { --chat-fun: #c23b4a; }
.chat-view[data-accent='gold'] { --chat-fun: #a16207; }
.chat-view[data-accent='teal'] { --chat-fun: #0f766e; }
.chat-view[data-accent='violet'] { --chat-fun: #6d28d9; }
.chat-view[data-accent='leaf'] { --chat-fun: #3f6212; }

.chat-view[data-accent] .chat-bubble--mine {
  background: linear-gradient(
    145deg,
    color-mix(in srgb, var(--chat-fun) 18%, var(--color-surface)),
    var(--color-surface)
  );
  box-shadow: 0 2px 10px color-mix(in srgb, var(--chat-fun) 16%, transparent);
}
</style>
