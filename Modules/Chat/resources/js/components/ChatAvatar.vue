<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
  src: { type: String, default: null },
  name: { type: String, default: '' },
  /** Đường kính (đơn vị CSS), vd. `2rem`. */
  size: { type: String, default: '2rem' },
});

const failed = ref(false);

watch(() => props.src, () => {
  failed.value = false;
});

const showImage = computed(() => Boolean(props.src) && !failed.value);
const initial = computed(() => props.name?.trim().charAt(0).toUpperCase() || '?');
</script>

<template>
  <img
    v-if="showImage"
    class="chat-avatar"
    :src="src"
    :alt="`Ảnh đại diện của ${name}`"
    :style="{ '--chat-avatar-size': size }"
    loading="lazy"
    @error="failed = true"
  />
  <span v-else class="chat-avatar chat-avatar--ph" :style="{ '--chat-avatar-size': size }" aria-hidden="true">
    {{ initial }}
  </span>
</template>

<style scoped>
.chat-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: var(--chat-avatar-size);
  height: var(--chat-avatar-size);
  border-radius: var(--radius-full);
  object-fit: cover;
}

.chat-avatar--ph {
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: calc(var(--chat-avatar-size) * 0.42);
  font-weight: 700;
  line-height: 1;
  user-select: none;
}
</style>
