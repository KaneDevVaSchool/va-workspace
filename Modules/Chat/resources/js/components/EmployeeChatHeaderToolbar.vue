<script setup>
//
// Ô tìm trên header PWA. Header có overflow/clip-path nên không thể thả danh sách kết quả
// từ đây — từ khoá chỉ được đẩy qua `listQuery`, ChatConversationList hiện đồng nghiệp
// và hội thoại khớp ở vùng nội dung bên dưới.
//
import AppIcon from '@/components/AppIcon.vue';

const listQuery = defineModel('listQuery', { type: String, default: '' });

const emit = defineEmits(['create-group']);

function onCreateGroup() {
  emit('create-group');
}
</script>

<template>
  <div class="employee-chat-toolbar">
    <label class="employee-shell__search employee-shell__search--elevated employee-chat-toolbar__search">
      <span class="employee-shell__search-leading" aria-hidden="true">
        <AppIcon name="search" :size="18" class="employee-shell__search-icon" />
      </span>
      <input
        v-model="listQuery"
        type="text"
        inputmode="search"
        class="employee-shell__search-input"
        placeholder="Tìm hội thoại, đồng nghiệp…"
        autocomplete="off"
        enterkeyhint="search"
        aria-label="Tìm hội thoại hoặc đồng nghiệp"
      />
      <button
        v-if="listQuery.trim() !== ''"
        type="button"
        class="employee-chat-toolbar__clear"
        aria-label="Xoá từ khoá"
        @click="listQuery = ''"
      >
        <AppIcon name="close" :size="14" :stroke-width="1.75" />
      </button>
      <button
        type="button"
        class="employee-chat-toolbar__group"
        aria-label="Tạo nhóm"
        @click="onCreateGroup"
      >
        <AppIcon name="userPlus" :size="18" :stroke-width="1.75" />
      </button>
    </label>
  </div>
</template>

<style scoped>
.employee-chat-toolbar {
  position: relative;
  z-index: 2;
  margin-top: 0.35rem;
}

.employee-chat-toolbar__search {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  height: 2.75rem;
  margin-top: 0;
  padding: 0 0.35rem 0 1rem;
}

.employee-chat-toolbar__clear,
.employee-chat-toolbar__group {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border: none;
  background: transparent;
  cursor: pointer;
  font-family: inherit;
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
}

.employee-chat-toolbar__clear {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-full);
  color: var(--color-text-muted);
  background: var(--color-surface-muted);
}

.employee-chat-toolbar__group {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-full);
  color: var(--color-primary);
  background: var(--color-primary-surface);
}
</style>
