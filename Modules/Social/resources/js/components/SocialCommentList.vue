<script setup>
import { onMounted, ref } from 'vue';
import SocialCommentComposer from './SocialCommentComposer.vue';
import SocialCommentItem from './SocialCommentItem.vue';

const props = defineProps({
  postId: { type: Number, required: true },
  canModerate: { type: Boolean, default: false },
});

const emit = defineEmits(['count-changed', 'close', 'open-wall', 'open-hashtag']);

const comments = ref([]);
const loading = ref(false);
const composerKey = ref(0);

async function load() {
  loading.value = true;
  try {
    const { data } = await window.axios.get(`/api/social/posts/${props.postId}/comments`);
    comments.value = data.comments;
  } finally {
    loading.value = false;
  }
}

function onSubmitted(data) {
  comments.value = [...comments.value, data.comment];
  composerKey.value += 1;
  emit('count-changed', data.comments_count);
}

function onItemDeleted(commentId) {
  comments.value = comments.value.filter((c) => c.id !== commentId);
}

function onCountChanged(count) {
  emit('count-changed', count);
}

onMounted(load);
</script>

<template>
  <div class="comments">
    <div v-if="comments.length > 0" class="comments__list">
      <SocialCommentItem
        v-for="comment in comments"
        :key="comment.id"
        :comment="comment"
        :post-id="postId"
        :can-moderate="canModerate"
        @deleted="onItemDeleted"
        @count-changed="onCountChanged"
        @open-wall="emit('open-wall', $event)"
        @open-hashtag="emit('open-hashtag', $event)"
      />
    </div>

    <div v-if="loading" class="comments__loading">Đang tải bình luận...</div>

    <SocialCommentComposer
      :key="composerKey"
      :post-id="postId"
      prompt="Bạn có muốn bình luận?"
      placeholder="Viết bình luận..."
      @submitted="onSubmitted"
      @close="emit('close')"
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

.comments__list {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.comments__loading {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}
</style>
