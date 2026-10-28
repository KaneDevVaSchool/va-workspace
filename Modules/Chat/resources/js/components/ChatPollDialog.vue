<script setup>
import { computed, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useChatStore } from '../store/chatStore';

const props = defineProps({
  open: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);
const store = useChatStore();
const question = ref('');
const options = ref(['', '']);
const saving = ref(false);
const error = ref('');

const canSubmit = computed(() => question.value.trim() !== '' && options.value.filter((item) => item.trim()).length >= 2);

watch(() => props.open, (value) => {
  if (!value) return;
  question.value = '';
  options.value = ['', ''];
  error.value = '';
});

function close() {
  if (saving.value) return;
  emit('close');
}

async function submit() {
  if (!canSubmit.value || saving.value) return;
  saving.value = true;
  error.value = '';
  try {
    await store.sendMessage(store.activeConversationId, {
      message: question.value.trim(),
      message_type: 'poll',
      poll_options: options.value.map((item) => item.trim()).filter(Boolean),
    });
    saving.value = false;
    close();
  } catch (err) {
    error.value = err?.response?.data?.errors?.poll_options?.[0]
      || err?.response?.data?.errors?.message?.[0]
      || err?.response?.data?.message
      || 'Không tạo được bình chọn.';
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="chat-poll" role="presentation" @mousedown.self="close">
      <div class="chat-poll__panel" role="dialog" aria-modal="true" aria-labelledby="chat-poll-title">
        <div class="chat-poll__head">
          <h2 id="chat-poll-title">Bình chọn</h2>
          <button type="button" aria-label="Đóng" @click="close"><AppIcon name="close" :size="16" /></button>
        </div>
        <form class="chat-poll__grid" @submit.prevent="submit">
          <label class="chat-poll__wide">
            <span>Câu hỏi</span>
            <input v-model="question" maxlength="200" type="text" />
          </label>
          <label v-for="(option, index) in options" :key="index">
            <span>Lựa chọn {{ index + 1 }}</span>
            <input v-model="options[index]" maxlength="80" type="text" />
          </label>
          <button v-if="options.length < 6" type="button" @click="options = [...options, '']">Thêm lựa chọn</button>
          <p v-if="error">{{ error }}</p>
          <button type="submit" :disabled="!canSubmit">Tạo bình chọn</button>
        </form>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.chat-poll {
  position: fixed;
  inset: 0;
  z-index: 70;
  display: grid;
  place-items: center;
  padding: 1.25rem;
  background: color-mix(in srgb, var(--color-text) 28%, transparent);
}
.chat-poll__panel {
  width: min(40rem, calc(100vw - 2.5rem));
  max-height: calc(100vh - 2.5rem);
  overflow: hidden;
  border-radius: 16px;
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}
.chat-poll__head, .chat-poll__grid { padding: 0.9rem 1rem; }
.chat-poll__head { display: flex; align-items: center; justify-content: space-between; }
.chat-poll__head h2 { margin: 0; font-size: 1.05rem; }
.chat-poll__head button, .chat-poll__grid button {
  border: 0;
  background: transparent;
  color: var(--color-text);
  cursor: pointer;
}
.chat-poll__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.7rem;
}
.chat-poll__wide { grid-column: 1 / -1; }
.chat-poll__grid label { display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.82rem; }
.chat-poll__grid input {
  padding: 0.5rem 0.65rem;
  border: 1px solid var(--color-border);
  border-radius: 10px;
  background: var(--color-surface);
  color: var(--color-text);
}
.chat-poll__grid button[type="submit"] {
  grid-column: 1 / -1;
  padding: 0.55rem;
  border-radius: 10px;
  background: var(--color-primary);
  color: var(--color-on-primary, #fff);
}
@media (max-width: 720px) {
  .chat-poll__grid { grid-template-columns: 1fr; }
}
</style>
