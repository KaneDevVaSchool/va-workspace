<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import CommentComposer from './CommentComposer.vue';
import CommentItem from './CommentItem.vue';

// Rút gọn từ Modules/Social/resources/js/components/SocialCommentList.vue:
// bỏ canModerate/open-hashtag; nhận endpointBase + commentableId để tự build
// URL (Task và Project dùng 2 đường dẫn API khác nhau), thay vì hard-code
// postId như bản Social.
//
// 2 CHẾ ĐỘ:
// - "Tự tải" (mặc định, TaskDetail.vue dùng): không truyền prop `comments`,
//   component tự gọi API khi mounted — giữ nguyên hành vi gốc.
// - "Controlled" (ProjectDiscussionTab.vue dùng): cha truyền `comments` (đã
//   tải + lọc + polling sẵn ở cha) → component CHỈ hiển thị, submit/xoá/ghim
//   phát ra ngoài qua event thay vì tự sửa state nội bộ — cha là nguồn chân
//   lý duy nhất, tránh 2 nơi cùng giữ 1 danh sách (đã bỏ track riêng ở đây).
const props = defineProps({
  /** '/api/project/tasks' hoặc '/api/project' — chưa gồm /{id}/comments. */
  endpointBase: { type: String, required: true },
  commentableId: { type: Number, required: true },
  /** Ẩn khung soạn nếu user không có quyền tạo (task.create/project.view). */
  canCreate: { type: Boolean, default: true },
  /** Chỉ hiện bình luận gốc của tác giả này. null = hiện tất cả. */
  filterAuthorId: { type: Number, default: null },
  /** Chế độ controlled: truyền mảng comment đã tải sẵn từ cha (xem ghi chú trên). */
  comments: { type: Array, default: null },
  /** Cho phép ghim (chỉ Project, chỉ người có quyền sửa dự án — cha tự tính). */
  canPin: { type: Boolean, default: false },
  /** stack = danh sách dọc (mặc định). sidebar = cột phải TaskDetail: list cuộn, composer dính đáy. */
  layout: { type: String, default: 'stack' },
});

const emit = defineEmits(['count-changed', 'comments-loaded', 'submitted', 'deleted', 'pin-changed']);

const controlled = computed(() => props.comments !== null);

const localComments = ref([]);
const loading = ref(false);
const composerKey = ref(0);
const threadRef = ref(null);
const isSidebar = computed(() => props.layout === 'sidebar');

const endpoint = computed(() => `${props.endpointBase}/${props.commentableId}/comments`);
const mentionsEndpoint = '/api/project/comments/mentions';

const sourceComments = computed(() => (controlled.value ? props.comments : localComments.value));

const visibleComments = computed(() => {
  if (props.filterAuthorId == null) return sourceComments.value;
  return sourceComments.value.filter((comment) => comment.author?.id === props.filterAuthorId);
});

async function load() {
  if (controlled.value) return;
  loading.value = true;
  try {
    const { data } = await window.axios.get(endpoint.value);
    localComments.value = data.comments;
    emit('comments-loaded', localComments.value);
  } finally {
    loading.value = false;
    if (isSidebar.value) {
      await nextTick();
      scrollToBottom();
    }
  }
}

function scrollToBottom() {
  const el = threadRef.value;
  if (!el) return;
  el.scrollTop = el.scrollHeight;
}

function onSubmitted(data) {
  composerKey.value += 1;
  if (controlled.value) {
    emit('submitted', data);
    return;
  }
  localComments.value = [...localComments.value, data.comment];
  emit('comments-loaded', localComments.value);
  emit('count-changed', data.comments_count);
  if (isSidebar.value) nextTick(scrollToBottom);
}

function onItemDeleted(commentId) {
  if (controlled.value) {
    emit('deleted', commentId);
    return;
  }
  localComments.value = localComments.value.filter((c) => c.id !== commentId);
  emit('comments-loaded', localComments.value);
}

function onCountChanged(count) {
  emit('count-changed', count);
}

function onPinChanged(comment) {
  emit('pin-changed', comment);
}

onMounted(load);
</script>

<template>
  <div class="comments" :class="{ 'comments--sidebar': isSidebar }">
    <div
      v-if="visibleComments.length > 0"
      ref="threadRef"
      class="comments__list"
      :class="{ 'comments__list--scroll': isSidebar }"
    >
      <CommentItem
        v-for="comment in visibleComments"
        :key="comment.id"
        :comment="comment"
        :endpoint="endpoint"
        :mentions-endpoint="mentionsEndpoint"
        :can-pin="canPin"
        @deleted="onItemDeleted"
        @count-changed="onCountChanged"
        @pin-changed="onPinChanged"
      />
    </div>

    <p v-else-if="!loading" class="comments__empty">Không có thảo luận nào.</p>

    <div v-if="loading" class="comments__loading">Đang tải thảo luận...</div>

    <CommentComposer
      v-if="canCreate && filterAuthorId == null"
      :key="composerKey"
      :endpoint="endpoint"
      :mentions-endpoint="mentionsEndpoint"
      auto-expand
      prompt="Bạn có muốn thảo luận?"
      placeholder="Viết thảo luận..."
      @submitted="onSubmitted"
    />
  </div>
</template>

<style scoped>
.comments {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  overflow: visible;
}

.comments--sidebar {
  flex: 1;
  min-height: 0;
  overflow: visible;
}

.comments--sidebar :deep(.comment-composer) {
  flex-shrink: 0;
}

.comments__list {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.comments__list--scroll {
  flex: 1;
  min-height: 0;
  overflow-x: hidden;
  overflow-y: auto;
  padding-right: 0.125rem;
  scrollbar-width: none;
}

.comments__list--scroll::-webkit-scrollbar {
  display: none;
  width: 0;
  height: 0;
}

.comments--sidebar .comments__empty,
.comments--sidebar .comments__loading {
  flex: 1;
  min-height: 0;
}

.comments__empty {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.comments__loading {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}
</style>
