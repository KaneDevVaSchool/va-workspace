<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { sendTyping, touchViewing } from '../api/chatApi';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { showClientToast } from '@/lib/clientToast';
import { formatRelativeTime, formatTime } from '@/lib/formatTime';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import SocialAnimatedSticker from '@modules/Social/resources/js/components/SocialAnimatedSticker.vue';
import ChatLinkPreviewEmbed from '@modules/Social/resources/js/components/ChatLinkPreviewEmbed.vue';
import ChatAlbumDialog from './ChatAlbumDialog.vue';
import ChatMemberDialog from './ChatMemberDialog.vue';
import ChatPicker from './ChatPicker.vue';
import ChatPollDialog from './ChatPollDialog.vue';
import { formatPeerMeta } from '../lib/chatPeerMeta';
import { ChatFileError, prepareChatFile } from '../lib/prepareChatFile';
import { useChatStore } from '../store/chatStore';

const store = useChatStore();
const auth = useAuthStore();

const QUICK_EMOJIS = ['👍', '❤️', '😂', '🎉', '🙏', '🔥'];
const MAX_FILES = 5;
const MAX_FILE_BYTES = 10 * 1024 * 1024;

const draft = ref('');
const files = ref([]);
const fileInput = ref(null);
const cameraInput = ref(null);
const photoInput = ref(null);
const lightbox = ref(null);
const sending = ref(false);
/** Xem trước tin đang gửi (bubble pending trước khi API trả về). */
const pendingOutgoing = ref(null);
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
const recording = ref(false);
const mentionIds = ref([]);
let typingSentAt = 0;
let typingStopTimer = null;
let voiceRecorder = null;
let voiceChunks = [];
let voiceStarted = 0;

const otherUser = computed(() => store.activeConversation?.other_user ?? null);
const peerMetaLine = computed(() => formatPeerMeta(otherUser.value));
const isGroup = computed(() => store.activeConversation?.type === 'group');
const headerTitle = computed(() => {
  if (isGroup.value) return store.activeConversation?.title || 'Nhóm';
  return otherUser.value?.name || 'Trò chuyện';
});
const isOwner = computed(() => Boolean(store.activeConversation?.is_owner));
const pinnedMessage = computed(() => store.activeConversation?.pinned_message ?? null);
const mentionQuery = computed(() => {
  if (!isGroup.value) return null;
  const match = draft.value.match(/(?:^|\s)@([^\n@]*)$/);
  return match ? match[1] : null;
});
const mentionHits = computed(() => {
  if (mentionQuery.value === null) return [];
  const query = mentionQuery.value.toLowerCase();
  return (store.activeConversation?.members || [])
    .filter((user) => user.id !== auth.user?.id && user.name.toLowerCase().includes(query))
    .slice(0, 6);
});
const memberLine = computed(() => {
  const members = store.activeConversation?.members ?? [];
  const count = store.activeConversation?.member_count || members.length;
  const names = members
    .filter((member) => member.id !== auth.user?.id)
    .map((member) => member.name)
    .filter(Boolean);
  const countLabel = count ? `${count} thành viên` : 'Nhóm';
  if (names.length === 0) return countLabel;
  const shown = names.slice(0, 3).join(', ');
  return names.length > 3 ? `${countLabel} · ${shown}…` : `${countLabel} · ${shown}`;
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
const canSend = computed(() => !sending.value && (draft.value.trim() !== '' || (!editing.value && files.value.length > 0)));

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
  stopTyping();
  clearInterval(viewingTimer);
  clearDraftFiles();
});

watch(
  () => store.activeMessages.length,
  () => {
    if (!editing.value) scrollToBottom();
  },
);

watch(typingLabel, (label) => {
  if (label) scrollToBottom();
});

watch(sending, (active) => {
  if (active && pendingOutgoing.value) scrollToBottom();
});

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

function coarsePointer() {
  return window.matchMedia('(pointer: coarse)').matches;
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
}

function onInputFocus() {
  pickerOpen.value = false;
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
  const next = draft.value.replace(/(^|\s)@[^\n@]*$/, `$1@${user.name} `);
  draft.value = next;
  if (!mentionIds.value.includes(user.id)) mentionIds.value = [...mentionIds.value, user.id];
}

function readLabel(message) {
  if (!isGroup.value || !isMine(message) || message.recalled_at) return '';
  const count = message.reads?.length || 0;
  if (!count) return '';
  const others = Math.max(1, (store.activeConversation?.member_count || 1) - 1);
  return `Đã xem ${count}/${others}`;
}

function showReaders(message) {
  readersFor.value = message;
}

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
  const list = store.messagesByConversation[id] || [];
  const known = list.some((item) => item.id === message.id);
  if (!known && message.sender) {
    store.messagesByConversation[id] = [...list, message].sort((left, right) => left.id - right.id);
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

async function startVoice(event) {
  event.preventDefault();
  if (recording.value || sending.value || editing.value) return;
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    const mime = MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : '';
    voiceRecorder = new MediaRecorder(stream, mime ? { mimeType: mime } : undefined);
    voiceChunks = [];
    voiceRecorder.ondataavailable = (item) => {
      if (item.data?.size) voiceChunks.push(item.data);
    };
    voiceRecorder.onstop = () => finishVoice();
    voiceStarted = Date.now();
    voiceRecorder.start();
    recording.value = true;
  } catch {
    showClientToast('error', 'Không dùng được micro trên thiết bị này.');
  }
}

function stopVoice() {
  if (!voiceRecorder || voiceRecorder.state === 'inactive') return;
  voiceRecorder.stream?.getTracks().forEach((track) => track.stop());
  voiceRecorder.stop();
  recording.value = false;
}

async function finishVoice() {
  const duration = Math.round((Date.now() - voiceStarted) / 1000);
  if (duration < 1 || voiceChunks.length === 0) return;
  const type = voiceRecorder?.mimeType || 'audio/webm';
  const file = new File(voiceChunks, 'thoai.webm', { type });
  sending.value = true;
  try {
    await store.sendMessage(store.activeConversationId, {
      message_type: 'voice',
      voice_duration: Math.min(180, Math.max(1, duration)),
      files: [file],
    });
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không gửi được tin nhắn thoại.');
  } finally {
    sending.value = false;
  }
}

async function castVote(message, option) {
  await store.votePoll(store.activeConversationId, message.id, option.id).catch((error) => {
    showClientToast('error', error?.response?.data?.message || 'Không gửi được phiếu.');
  });
}

function onDocumentPointerDown(event) {
  if (!menu.value && !headerMenu.value) return;
  const target = event.target;
  if (target instanceof Element && target.closest('.chat-menu, .chat-bubble__more, .chat-view__head-menu')) return;
  closeMenu();
}

function onDocumentKeydown(event) {
  if (event.key !== 'Escape') return;
  if (lightbox.value) {
    lightbox.value = null;
    return;
  }
  closeMenu();
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
  const chosen = Array.from(event.target.files ?? []);
  event.target.value = '';
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
      showClientToast('error', error instanceof ChatFileError ? error.message : 'Ảnh này không gửi được. Hãy chọn JPG hoặc PNG.');
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

async function sendSticker(sticker) {
  if (sending.value || editing.value) return;
  pendingOutgoing.value = { kind: 'sticker', emoji: sticker.emoji, stickerId: sticker.id };
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
    pendingOutgoing.value = null;
  }
}

async function send() {
  const text = draft.value.trim();
  if (sending.value) return;
  if (text === '' && (editing.value || files.value.length === 0)) return;
  const outgoing = files.value.map((item) => item.file);
  const sentPreviews = files.value.map((item) => item.previewUrl).filter(Boolean);
  if (editing.value) {
    pendingOutgoing.value = { kind: 'edit' };
  } else {
    pendingOutgoing.value = {
      kind: 'message',
      text,
      fileCount: outgoing.length,
      hasImages: outgoing.some((file) => file.type.startsWith('image/')),
      previews: sentPreviews,
    };
  }
  sending.value = true;
  let delivered = false;
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
      files: outgoing,
      mention_ids: mentionIds.value.filter((id) => text.includes(memberName(id))),
    });
    delivered = true;
    draft.value = '';
    mentionIds.value = [];
    files.value = [];
    replyTo.value = null;
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không gửi được tin nhắn.');
  } finally {
    sending.value = false;
    pendingOutgoing.value = null;
    if (delivered) {
      nextTick(() => {
        for (const url of sentPreviews) URL.revokeObjectURL(url);
      });
    }
  }
}

async function confirmAction() {
  if (confirming.value) return;
  if (confirmKind.value === 'clear' || confirmKind.value === 'disband') {
    confirming.value = true;
    const kind = confirmKind.value;
    const conversationId = store.activeConversationId;
    try {
      if (kind === 'clear') {
        await store.clearHistory(conversationId);
      } else {
        await store.disbandGroup(conversationId);
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
    items.push({ key: 'pin', label: 'Ghim tin', icon: 'pin', run: () => pinThis(message) });
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
      <span v-if="isGroup" class="chat-view__avatar chat-view__avatar--group" aria-hidden="true">
        <AppIcon name="users" :size="16" :stroke-width="1.75" />
      </span>
      <img
        v-else-if="otherUser?.avatar_url"
        class="chat-view__avatar"
        :src="otherUser.avatar_url"
        :alt="`Ảnh đại diện của ${otherUser.name}`"
      />
      <span v-else class="chat-view__avatar chat-view__avatar--placeholder">{{ initial(otherUser?.name) }}</span>
      <span class="chat-view__who">
        <span class="chat-view__title">{{ headerTitle }}</span>
        <span v-if="peerTyping" class="chat-view__dept chat-view__dept--active">
          <span class="chat-typing" aria-hidden="true"><span /><span /><span /></span>
          {{ typingLabel }}
        </span>
        <span v-else-if="sending" class="chat-view__dept chat-view__dept--active">Đang gửi…</span>
        <span v-else-if="!isGroup && otherUser?.online" class="chat-view__dept chat-view__dept--active">Đang hoạt động</span>
        <span v-else-if="!isGroup && otherUser?.last_seen_at" class="chat-view__dept">Truy cập {{ formatRelativeTime(otherUser.last_seen_at) }}</span>
        <span v-else-if="isGroup" class="chat-view__dept">{{ memberLine }}</span>
        <span v-else-if="peerMetaLine" class="chat-view__dept">{{ peerMetaLine }}</span>
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

    <div ref="listRef" class="chat-view__messages" @scroll="onScroll">
      <p v-if="store.loadingMessages && store.activeMessages.length === 0" class="chat-view__empty">Đang tải tin nhắn…</p>
      <p v-else-if="store.activeMessages.length === 0" class="chat-view__empty">
        Chưa có tin nhắn. Hãy gửi lời chào, emoji hoặc sticker.
      </p>
      <template v-for="row in timeline" :key="row.kind === 'day' ? row.id : row.message.id">
        <div v-if="row.kind === 'day'" class="chat-day">{{ row.label }}</div>
        <div v-else-if="row.message.message_type === 'system'" class="chat-day">{{ row.message.message }}</div>
        <div
          v-else
          :id="`chat-msg-${row.message.id}`"
          class="chat-bubble-row chat-bubble-row--animate"
          :class="{ 'chat-bubble-row--mine': isMine(row.message) }"
        >
          <div class="chat-message-col">
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

            <span v-if="showSender(row.message)" class="chat-bubble__sender">{{ row.message.sender?.name }}</span>
            <span v-if="row.message.recalled_at" class="chat-bubble__recalled">
              <AppIcon name="rotateCw" :size="13" :stroke-width="1.75" />
              Tin nhắn đã được thu hồi
            </span>
            <span v-else-if="row.message.message_type === 'sticker' && row.message.sticker_id" class="chat-sticker">
              <SocialAnimatedSticker :id="row.message.sticker_id" :emoji="row.message.message || ''" />
            </span>
            <div v-else-if="row.message.message_type === 'poll' && row.message.poll" class="chat-poll-card">
              <p class="chat-poll-card__q">{{ row.message.poll.question }}</p>
              <button
                v-for="option in row.message.poll.options"
                :key="option.id"
                type="button"
                class="chat-poll-card__opt"
                :class="{ 'chat-poll-card__opt--on': (option.voter_ids || []).map(Number).includes(Number(auth.user?.id)) }"
                @click="castVote(row.message, option)"
              >
                <span>{{ option.label }}</span>
                <span>{{ option.votes }}</span>
              </button>
            </div>
            <div
              v-else-if="row.message.message_type === 'voice'"
              class="chat-voice"
            >
              <AppIcon name="mic" :size="16" :stroke-width="1.75" />
              <span>{{ row.message.voice_duration || 1 }} giây</span>
              <audio :src="row.message.attachments?.[0]?.url" controls preload="none" />
            </div>
            <span v-else-if="row.message.message" class="chat-bubble__text">{{ row.message.message }}</span>
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
              <button v-if="readLabel(row.message)" type="button" class="chat-read" @click="showReaders(row.message)">
                {{ readLabel(row.message) }}
              </button>
              <span v-else-if="showRead(row.message)">Đã xem</span>
            </span>
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
        v-if="pendingOutgoing?.kind === 'message' || pendingOutgoing?.kind === 'sticker'"
        class="chat-bubble-row chat-bubble-row--mine"
        aria-live="polite"
      >
        <div class="chat-message-col">
        <div
          class="chat-bubble chat-bubble--mine chat-bubble--pending"
          :class="{ 'chat-bubble--sticker': pendingOutgoing.kind === 'sticker' }"
        >
          <span
            v-if="pendingOutgoing.kind === 'sticker' && pendingOutgoing.stickerId"
            class="chat-sticker"
          >
            <SocialAnimatedSticker
              :id="pendingOutgoing.stickerId"
              :emoji="pendingOutgoing.emoji || ''"
            />
          </span>
          <span v-else-if="pendingOutgoing.text" class="chat-bubble__text">{{ pendingOutgoing.text }}</span>
          <div
            v-if="pendingOutgoing.previews?.length"
            class="chat-images"
            :class="{ 'chat-images--single': pendingOutgoing.previews.length === 1 }"
          >
            <span v-for="url in pendingOutgoing.previews" :key="url" class="chat-images__link">
              <img :src="url" alt="Ảnh đang gửi" />
            </span>
          </div>
          <span
            v-if="pendingOutgoing.kind === 'message' && pendingOutgoing.fileCount > 0 && !pendingOutgoing.previews?.length"
            class="chat-bubble__pending-files"
          >
            {{
              pendingOutgoing.fileCount > 1
                ? `${pendingOutgoing.fileCount} tệp đính kèm`
                : pendingOutgoing.hasImages
                  ? 'Ảnh'
                  : 'Tệp đính kèm'
            }}
          </span>
          <span class="chat-bubble__meta">
            <span class="chat-bubble__sending">Đang gửi…</span>
          </span>
        </div>
        <ChatLinkPreviewEmbed
          v-if="pendingOutgoing.kind === 'message' && pendingOutgoing.text"
          :text="pendingOutgoing.text"
        />
        </div>
      </div>

      <div v-if="peerTyping" class="chat-bubble-row" aria-live="polite">
        <div class="chat-bubble chat-bubble--typing">
          <span class="chat-typing" aria-hidden="true"><span /><span /><span /></span>
          <span>{{ typingLabel }}</span>
        </div>
      </div>
    </div>

    <div class="chat-dock">
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
      <div class="chat-view__tools hide-scrollbar">
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
          aria-label="Chụp ảnh"
          @click="cameraInput?.click()"
        >
          <AppIcon name="camera" :size="18" :stroke-width="1.75" />
        </button>
        <button
          type="button"
          class="chat-view__tool"
          :disabled="Boolean(editing)"
          aria-label="Gửi ảnh"
          @click="photoInput?.click()"
        >
          <AppIcon name="image" :size="18" :stroke-width="1.75" />
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
          ref="cameraInput"
          class="chat-view__file-input"
          type="file"
          accept="image/*"
          capture="environment"
          @change="onFilesChosen"
        />
        <input
          ref="photoInput"
          class="chat-view__file-input"
          type="file"
          accept="image/*"
          multiple
          @change="onFilesChosen"
        />
        <input
          ref="fileInput"
          class="chat-view__file-input"
          type="file"
          multiple
          accept=".pdf,.doc,.docx,.xlsx,.xls,.ppt,.pptx,.txt,.csv,.zip,image/*"
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
          v-if="isGroup"
          type="button"
          class="chat-view__tool"
          :disabled="Boolean(editing)"
          aria-label="Tạo bình chọn"
          @click="pollOpen = true"
        >
          <AppIcon name="listChecks" :size="18" :stroke-width="1.75" />
        </button>
        <button
          type="button"
          class="chat-view__tool"
          :class="{ 'chat-view__tool--on': recording }"
          :disabled="Boolean(editing)"
          aria-label="Giữ để ghi âm"
          @pointerdown="startVoice"
          @pointerup="stopVoice"
          @pointerleave="recording && stopVoice()"
        >
          <AppIcon name="mic" :size="18" :stroke-width="1.75" />
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
      <div v-if="mentionHits.length" class="chat-mentions">
        <button v-for="user in mentionHits" :key="user.id" type="button" @click="pickMention(user)">
          {{ user.name }}
        </button>
      </div>
      <div class="chat-view__input-row">
        <textarea
          ref="inputRef"
          v-model="draft"
          class="chat-view__input"
          rows="1"
          enterkeyhint="send"
          :placeholder="editing ? 'Sửa nội dung…' : 'Nhập tin nhắn...'"
          :aria-label="editing ? 'Sửa tin nhắn' : `Nhập tin nhắn gửi ${headerTitle}`"
          @focus="onInputFocus"
          @input="pingTyping"
          @keydown="onKeydown"
        />
        <button
          type="button"
          class="chat-view__send"
          :class="{ 'chat-view__send--busy': sending, 'chat-view__send--ready': canSend && !sending }"
          :disabled="!canSend"
          :aria-label="sending ? 'Đang gửi tin nhắn' : 'Gửi tin nhắn'"
          :aria-busy="sending"
          @click="send"
        >
          <AppIcon :name="editing ? 'check' : 'send'" :size="18" :stroke-width="1.75" />
        </button>
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
        <button v-else-if="isGroup" type="button" class="chat-menu__item" role="menuitem" @click="openMembers">
          Rời nhóm
        </button>
      </div>
    </Teleport>

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

    <ChatMemberDialog :open="membersOpen" @close="membersOpen = false" />
    <ChatPollDialog :open="pollOpen" @close="pollOpen = false" />
    <ChatAlbumDialog :open="albumOpen" @close="albumOpen = false" />
    <div v-if="readersFor" class="chat-readers" role="dialog" aria-label="Người đã xem">
      <p>Đã xem</p>
      <p v-for="reader in readersFor.reads || []" :key="reader.id">{{ reader.name }}</p>
      <button type="button" @click="readersFor = null">Đóng</button>
    </div>

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
  padding-top: calc(var(--space-3) + env(safe-area-inset-top, 0px));
  color: var(--color-on-primary);
  background:
    linear-gradient(
      152deg,
      var(--color-tertiary-600) 0%,
      var(--color-tertiary-700) 48%,
      var(--color-tertiary-800) 100%
    );
  background-repeat: no-repeat;
  background-size: 100% calc(100% + env(safe-area-inset-top, 0px));
  background-position: top;
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

.chat-view__avatar {
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
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

.chat-message-col {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  max-width: 78%;
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

.chat-bubble--pending {
  opacity: 0.82;
}

.chat-bubble__sending {
  font-style: italic;
  color: var(--color-primary);
}

.chat-bubble__pending-files {
  font-size: 0.75rem;
  color: var(--color-text-muted);
  font-style: italic;
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

.chat-bubble__sender {
  color: var(--color-primary);
  font-size: 0.6875rem;
  font-weight: 700;
}

.chat-bubble--typing {
  flex-direction: row;
  align-items: center;
  gap: 0.4rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-style: italic;
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

.chat-dock {
  flex: 0 0 auto;
  display: flex;
  flex-direction: column;
  min-height: 0;
  max-height: min(58%, 26rem);
  overflow: hidden;
  background: color-mix(in srgb, var(--color-surface) 96%, transparent);
  box-shadow: 0 -1px 0 var(--color-border);
}

.chat-view__composer {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding: var(--space-2) var(--space-3) calc(var(--space-3) + env(safe-area-inset-bottom, 0px));
  background: color-mix(in srgb, var(--color-surface) 92%, transparent);
  backdrop-filter: blur(10px);
}

.chat-view__tools {
  display: flex;
  align-items: center;
  flex-wrap: nowrap;
  gap: 0.1rem;
  overflow-x: auto;
  scrollbar-width: none;
  touch-action: manipulation;
}

.chat-view__tools::-webkit-scrollbar {
  display: none;
}

.chat-view__tool {
  width: 1.85rem;
  height: 1.85rem;
  flex: 0 0 1.85rem;
  border-radius: var(--radius-sm);
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
}

.chat-view__tool:active,
.chat-view__quick:active {
  transform: none;
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
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 1.75rem;
  border: none;
  background: transparent;
  width: 1.75rem;
  height: 1.75rem;
  overflow: hidden;
  padding: 0;
  border-radius: var(--radius-sm);
  cursor: pointer;
  font-size: 1rem;
  line-height: 1;
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
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
  font-size: 16px;
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
  background: linear-gradient(145deg, var(--color-primary), var(--color-tertiary-600));
  color: var(--color-on-primary);
  transition: transform 140ms ease, opacity 140ms ease;
}

.chat-view__send--ready {
  animation: chat-send-pulse 2.2s ease-in-out infinite;
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
  top: max(var(--space-3), env(safe-area-inset-top, 0px));
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
  display: flex;
  flex-direction: column;
  margin-bottom: 0.35rem;
}

.chat-poll-card {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  min-width: 11rem;
}

.chat-poll-card__opt,
.chat-voice,
.chat-read,
.chat-mentions button,
.chat-search button {
  border: 0;
  background: transparent;
  color: var(--color-text);
  cursor: pointer;
}

.chat-poll-card__opt,
.chat-voice {
  display: flex;
  justify-content: space-between;
  gap: 0.5rem;
}

.chat-poll-card__opt--on {
  color: var(--color-primary);
}

.chat-readers {
  position: fixed;
  right: 1rem;
  bottom: 5rem;
  z-index: 70;
  width: 14rem;
  padding: 0.7rem;
  border-radius: 12px;
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
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
