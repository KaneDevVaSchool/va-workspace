<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import CommentList from './CommentList.vue';

// Tab Thảo luận — redesign toàn diện: khung thảo luận chính bên trái (tìm
// kiếm + lọc + ghim + polling) + sidebar thành viên bên phải (lọc theo
// người + chấm báo "còn thảo luận chưa đọc"). Component này là NGUỒN CHÂN
// LÝ DUY NHẤT của danh sách bình luận (tự load/poll), CommentList chỉ nhận
// qua prop `comments` (chế độ "controlled", xem ghi chú trong CommentList.vue)
// và phát hành động ra ngoài — tránh 2 nơi cùng giữ 1 state.
//
// 4 tính năng mới (đã chốt qua AskUserQuestion):
// 1. Tìm kiếm/lọc — thuần frontend trên danh sách đã tải, không gọi API mới.
// 2. Ghim bình luận — CommentService::pin()/unpin(), quyền = can_edit dự án.
// 3. Polling ~15s (dừng khi tab ẩn/không active), merge theo id không thay
//    toàn bộ mảng để giữ vị trí cuộn — mẫu HeaderNotifications.vue.
// 4. Chấm "chưa đọc" theo thành viên — BE tính sẵn trong unread_by_author
//    (CommentService::listFor()), FE chỉ hiển thị.
const POLL_MS = 15000;

const props = defineProps({
  project: { type: Object, required: true },
  canCreate: { type: Boolean, default: true },
});

const emit = defineEmits(['count-changed']);

const endpoint = computed(() => `/api/project/${props.project.id}/comments`);
const canPin = computed(() => Boolean(props.project.can_edit));

const allComments = ref([]);
const unreadByAuthor = ref({});
const loading = ref(false);
const activeMemberId = ref(null);
const searchQuery = ref('');
const onlyWithAttachments = ref(false);

const threadRef = ref(null);
const newArrivalsCount = ref(0);
const stickToBottom = ref(true);

let pollTimer = null;
let markReadTimer = null;

const members = computed(() => props.project.members ?? []);
const commentsCount = computed(() => allComments.value.length);

/** Đếm số bình luận GỐC theo từng tác giả (không tính trả lời lồng bên trong). */
const countByAuthor = computed(() => {
  const map = new Map();
  for (const comment of allComments.value) {
    const id = comment.author?.id;
    if (id == null) continue;
    map.set(id, (map.get(id) ?? 0) + 1);
  }
  return map;
});

const memberRows = computed(() =>
  members.value.map((member) => ({
    ...member,
    commentCount: countByAuthor.value.get(member.id) ?? 0,
    unread: Boolean(unreadByAuthor.value[member.id]),
  })),
);

const activeMember = computed(() => members.value.find((m) => m.id === activeMemberId.value) ?? null);

const normalizedQuery = computed(() => searchQuery.value.trim().toLowerCase());
const hasActiveFilters = computed(
  () => activeMemberId.value != null || normalizedQuery.value !== '' || onlyWithAttachments.value,
);

function matchesSearch(comment) {
  if (normalizedQuery.value === '') return true;
  const haystack = `${comment.author?.name ?? ''} ${comment.content ?? ''}`.toLowerCase();
  return haystack.includes(normalizedQuery.value);
}

function matchesAttachment(comment) {
  if (!onlyWithAttachments.value) return true;
  return (comment.attachments ?? []).length > 0;
}

const filteredComments = computed(() =>
  allComments.value.filter(
    (comment) =>
      (activeMemberId.value == null || comment.author?.id === activeMemberId.value)
      && matchesSearch(comment)
      && matchesAttachment(comment),
  ),
);

function selectMember(memberId) {
  activeMemberId.value = activeMemberId.value === memberId ? null : memberId;
}

function clearMemberFilter() {
  activeMemberId.value = null;
}

function clearAllFilters() {
  activeMemberId.value = null;
  searchQuery.value = '';
  onlyWithAttachments.value = false;
}

function mergeComments(nextList) {
  const existingIds = new Set(allComments.value.map((c) => c.id));
  const arrivals = nextList.filter((c) => !existingIds.has(c.id));

  // Thay toàn bộ nội dung (kể cả reply/reaction cập nhật) nhưng giữ nguyên
  // tham chiếu mảng theo id đã có để Vue diff hiệu quả — đơn giản nhất là
  // gán lại toàn bộ, vì API luôn trả full danh sách comment gốc + replies.
  allComments.value = nextList;

  if (arrivals.length > 0 && !stickToBottom.value) {
    newArrivalsCount.value += arrivals.length;
  }
}

async function load({ silent = false } = {}) {
  if (!silent) loading.value = true;
  try {
    const { data } = await window.axios.get(endpoint.value);
    mergeComments(data.comments);
    unreadByAuthor.value = data.unread_by_author ?? {};
    if (!silent) emit('count-changed', data.comments.length);
  } finally {
    if (!silent) loading.value = false;
  }
}

function onSubmitted(data) {
  allComments.value = [...allComments.value, data.comment];
  emit('count-changed', data.comments_count);
  scrollToBottom();
}

function onDeleted(commentId) {
  allComments.value = allComments.value.filter((c) => c.id !== commentId);
  emit('count-changed', allComments.value.length);
}

function onCountChanged(count) {
  emit('count-changed', count);
}

function onPinChanged(updatedComment) {
  if (!updatedComment) return;
  allComments.value = allComments.value.map((c) => (c.id === updatedComment.id ? updatedComment : c));
}

function onThreadScroll() {
  const el = threadRef.value;
  if (!el) return;
  const distanceFromBottom = el.scrollHeight - el.scrollTop - el.clientHeight;
  stickToBottom.value = distanceFromBottom < 80;
  if (stickToBottom.value) newArrivalsCount.value = 0;
}

async function scrollToBottom() {
  newArrivalsCount.value = 0;
  stickToBottom.value = true;
  await nextTick();
  const el = threadRef.value;
  if (el) el.scrollTop = el.scrollHeight;
}

function startPoll() {
  stopPoll();
  pollTimer = window.setInterval(() => load({ silent: true }), POLL_MS);
}

function stopPoll() {
  if (pollTimer) {
    window.clearInterval(pollTimer);
    pollTimer = null;
  }
}

function handleVisibilityChange() {
  if (document.hidden) {
    stopPoll();
  } else {
    load({ silent: true });
    startPoll();
  }
}

/** Đánh dấu đã đọc khi tab Thảo luận thật sự đang mở (component chỉ mounted khi active) — debounce nhẹ để tránh gọi khi chỉ lướt qua. */
function scheduleMarkRead() {
  if (markReadTimer) window.clearTimeout(markReadTimer);
  markReadTimer = window.setTimeout(async () => {
    try {
      await window.axios.post(`/api/project/${props.project.id}/comments/mark-read`);
      unreadByAuthor.value = {};
    } catch {
      // im lặng — không làm hỏng trải nghiệm đọc thảo luận
    }
  }, 1500);
}

watch(commentsCount, () => {
  if (stickToBottom.value) scrollToBottom();
});

onMounted(async () => {
  await load();
  await scrollToBottom();
  startPoll();
  scheduleMarkRead();
  document.addEventListener('visibilitychange', handleVisibilityChange);
});

onBeforeUnmount(() => {
  stopPoll();
  if (markReadTimer) window.clearTimeout(markReadTimer);
  document.removeEventListener('visibilitychange', handleVisibilityChange);
});
</script>

<template>
  <section class="disc">
    <div class="disc__main">
      <header class="disc__main-head">
        <span class="disc__main-title-wrap">
          <span class="disc__section-icon"><AppIcon name="messageCircle" :size="14" /></span>
          <h3 class="disc__main-title">Thảo luận</h3>
          <span v-if="commentsCount > 0" class="disc__main-count">{{ commentsCount }}</span>
        </span>

        <div class="disc__toolbar">
          <label class="disc__search" for="disc-search-input">
            <AppIcon name="search" :size="14" />
            <span class="disc__search-label">Tìm kiếm</span>
            <input
              id="disc-search-input"
              v-model="searchQuery"
              type="search"
              class="disc__search-input"
              placeholder="Nguyễn Văn A, chi phí..."
            />
          </label>

          <label class="disc__attach-toggle">
            <input v-model="onlyWithAttachments" type="checkbox" />
            <span>Chỉ có đính kèm</span>
          </label>
        </div>
      </header>

      <div v-if="hasActiveFilters" class="disc__filter-row">
        <button v-if="activeMember" type="button" class="disc__filter-chip" @click="clearMemberFilter">
          <span>Người: {{ activeMember.name }}</span>
          <AppIcon name="close" :size="12" />
        </button>
        <span class="disc__filter-result">{{ filteredComments.length }} kết quả</span>
        <button type="button" class="disc__filter-clear" @click="clearAllFilters">Bỏ hết lọc</button>
      </div>

      <div v-if="loading" class="disc__loading">Đang tải thảo luận...</div>

      <div v-else ref="threadRef" class="disc__thread hide-scrollbar" @scroll="onThreadScroll">
        <CommentList
          endpoint-base="/api/project"
          :commentable-id="project.id"
          :can-create="canCreate"
          :comments="filteredComments"
          :can-pin="canPin"
          @submitted="onSubmitted"
          @deleted="onDeleted"
          @count-changed="onCountChanged"
          @pin-changed="onPinChanged"
        />
      </div>

      <button v-if="newArrivalsCount > 0" type="button" class="disc__new-arrivals" @click="scrollToBottom">
        Có {{ newArrivalsCount }} bình luận mới — bấm để xem
      </button>
    </div>

    <aside class="disc__side">
      <header class="disc__side-head">
        <AppIcon name="users" :size="14" />
        <h4 class="disc__side-title">Thành viên dự án</h4>
      </header>

      <div class="disc__member-list hide-scrollbar">
        <button
          type="button"
          class="disc__member"
          :class="{ 'disc__member--active': activeMemberId == null }"
          @click="clearMemberFilter"
        >
          <span class="disc__member-avatar disc__member-avatar--all">
            <AppIcon name="users" :size="14" />
          </span>
          <span class="disc__member-name">Tất cả mọi người</span>
          <span class="disc__member-count">{{ commentsCount }}</span>
        </button>

        <button
          v-for="member in memberRows"
          :key="member.id"
          type="button"
          class="disc__member"
          :class="{ 'disc__member--active': activeMemberId === member.id }"
          @click="selectMember(member.id)"
        >
          <span class="disc__member-avatar-wrap">
            <img
              v-if="member.avatar_url"
              class="disc__member-avatar"
              :src="member.avatar_url"
              :alt="`Ảnh đại diện của ${member.name}`"
            />
            <span v-else class="disc__member-avatar disc__member-avatar--placeholder">
              {{ member.name?.charAt(0) ?? '?' }}
            </span>
          </span>
          <span class="disc__member-copy">
            <span class="disc__member-name">
              <span v-if="member.unread" class="disc__member-unread-dot" aria-hidden="true"></span>
              {{ member.name }}
            </span>
            <span v-if="member.department?.name" class="disc__member-dept">{{ member.department.name }}</span>
          </span>
          <span v-if="member.commentCount > 0" class="disc__member-count">{{ member.commentCount }}</span>
        </button>

        <p v-if="memberRows.length === 0" class="disc__member-empty">Dự án chưa có thành viên nào.</p>
      </div>
    </aside>
  </section>
</template>

<style scoped>
.disc {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 17rem;
  align-items: start;
  gap: var(--space-4);
}

/* ---- Khung thảo luận chính ---- */
.disc__main {
  position: relative;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-5);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.disc__main-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding-bottom: var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
}

.disc__main-title-wrap {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-width: 0;
  flex-shrink: 0;
}

.disc__section-icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-sm);
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
}

.disc__main-title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 700;
  white-space: nowrap;
}

.disc__main-count {
  flex-shrink: 0;
  min-width: 1.25rem;
  height: 1.25rem;
  padding: 0 0.3125rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
}

.disc__toolbar {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  flex-wrap: wrap;
  min-width: 0;
  flex: 1;
  justify-content: flex-end;
}

.disc__search {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  min-width: 0;
  width: 13rem;
  padding: 0.375rem var(--space-2);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.disc__search:focus-within {
  box-shadow: inset 0 0 0 1px var(--color-primary);
  color: var(--color-text);
}

.disc__search-label {
  flex-shrink: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  white-space: nowrap;
}

.disc__search-input {
  flex: 1;
  min-width: 0;
  border: none;
  background: none;
  color: inherit;
  font-family: inherit;
  font-size: 0.8125rem;
}

.disc__search-input:focus {
  outline: none;
}

.disc__attach-toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  cursor: pointer;
}

.disc__attach-toggle input {
  accent-color: var(--color-primary);
  cursor: pointer;
}

.disc__filter-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
}

.disc__filter-chip {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  border: none;
  padding: var(--space-1) var(--space-3);
  border-radius: var(--radius-full);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-family: inherit;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.disc__filter-chip:hover {
  background: color-mix(in srgb, var(--color-primary-surface) 70%, var(--color-primary));
}

.disc__filter-result {
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.disc__filter-clear {
  margin-left: auto;
  border: none;
  background: none;
  color: var(--color-text-muted);
  font-family: inherit;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
}

.disc__filter-clear:hover {
  color: var(--color-primary);
}

.disc__thread {
  min-width: 0;
  max-height: 42rem;
  overflow-y: auto;
  scroll-behavior: smooth;
}

.disc__loading {
  padding: var(--space-4) 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.disc__new-arrivals {
  position: sticky;
  bottom: 0;
  left: 0;
  align-self: center;
  border: none;
  padding: var(--space-2) var(--space-4);
  border-radius: var(--radius-full);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: inherit;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  box-shadow: var(--shadow-md);
  animation: disc-arrival-in 0.28s cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes disc-arrival-in {
  from {
    opacity: 0;
    transform: translateY(6px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* ---- Sidebar thành viên ---- */
.disc__side {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  max-height: 46rem;
}

.disc__side-head {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex-shrink: 0;
  color: var(--color-text-muted);
  padding-bottom: var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
}

.disc__side-title {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-text);
}

.disc__member-list {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-height: 0;
  overflow-y: auto;
}

.disc__member {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  border: none;
  background: none;
  padding: var(--space-2);
  border-radius: var(--radius-md);
  cursor: pointer;
  text-align: left;
  font-family: inherit;
  color: var(--color-text);
  transition: background 0.15s ease;
}

.disc__member:hover {
  background: var(--color-surface-muted);
}

.disc__member--active {
  background: var(--color-primary-surface);
}

.disc__member-avatar-wrap {
  flex-shrink: 0;
}

.disc__member-avatar {
  flex-shrink: 0;
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-full);
  object-fit: cover;
}

.disc__member-avatar--placeholder,
.disc__member-avatar--all {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-weight: 600;
}

.disc__member--active .disc__member-avatar--all {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.disc__member-copy {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.05rem;
}

.disc__member-name {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.8125rem;
  font-weight: 600;
}

.disc__member-unread-dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-secondary);
}

.disc__member--active .disc__member-name {
  color: var(--color-primary);
}

.disc__member-dept {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.6875rem;
  color: var(--color-text-muted);
}

.disc__member-count {
  flex-shrink: 0;
  min-width: 1.25rem;
  height: 1.25rem;
  padding: 0 0.3125rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
}

.disc__member-empty {
  margin: 0;
  padding: var(--space-2);
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

@media (prefers-reduced-motion: reduce) {
  .disc__new-arrivals {
    animation: none;
  }

  .disc__thread {
    scroll-behavior: auto;
  }
}

@media (max-width: 1280px) {
  .disc {
    grid-template-columns: 1fr;
  }

  .disc__side {
    order: -1;
    max-height: none;
  }

  .disc__member-list {
    flex-direction: row;
    flex-wrap: nowrap;
    overflow-x: auto;
    overflow-y: visible;
    padding-bottom: var(--space-1);
  }

  .disc__member {
    flex-shrink: 0;
    width: auto;
  }

  .disc__member-copy {
    min-width: 6rem;
  }
}

@media (max-width: 768px) {
  .disc__main,
  .disc__side {
    padding: var(--space-3);
  }

  .disc__main-head {
    flex-direction: column;
    align-items: stretch;
  }

  .disc__toolbar {
    justify-content: stretch;
  }

  .disc__search {
    width: auto;
    flex: 1;
  }

  .disc__thread {
    max-height: none;
  }
}

@media (max-width: 480px) {
  .disc__member-copy {
    min-width: 5rem;
  }

  .disc__member-dept {
    display: none;
  }
}
</style>
