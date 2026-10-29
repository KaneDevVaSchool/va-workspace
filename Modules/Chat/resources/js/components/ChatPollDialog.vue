<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { CHAT_POLL_PRESETS } from '../lib/chatPlay';
import { useChatStore } from '../store/chatStore';

const MAX_OPTIONS = 6;

const props = defineProps({
  open: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);
const store = useChatStore();
const question = ref('');
const options = ref(['', '']);
const saving = ref(false);
const error = ref('');

const filledOptions = computed(() => options.value.map((item) => item.trim()).filter(Boolean));
const canSubmit = computed(
  () => !saving.value && question.value.trim() !== '' && filledOptions.value.length >= 2,
);

watch(
  () => props.open,
  (value) => {
    if (!value) {
      unbindPage();
      return;
    }
    question.value = '';
    options.value = ['', ''];
    error.value = '';
    bindPage();
  },
);

onBeforeUnmount(() => {
  unbindPage();
});

function bindPage() {
  document.addEventListener('keydown', onKeydown);
  document.body.style.overflow = 'hidden';
}

function unbindPage() {
  document.removeEventListener('keydown', onKeydown);
  document.body.style.overflow = '';
}

function close() {
  if (saving.value) return;
  emit('close');
}

function onKeydown(event) {
  if (event.key === 'Escape') close();
}

function applyPreset(preset) {
  question.value = preset.question;
  options.value = [...preset.options];
  error.value = '';
}

function setOption(index, value) {
  const next = [...options.value];
  next[index] = value;
  options.value = next;
}

function addOption() {
  if (options.value.length >= MAX_OPTIONS) return;
  options.value = [...options.value, ''];
}

function removeOption(index) {
  if (options.value.length <= 2) return;
  options.value = options.value.filter((_, i) => i !== index);
}

async function submit() {
  if (!canSubmit.value || saving.value) return;
  saving.value = true;
  error.value = '';
  try {
    await store.sendMessage(store.activeConversationId, {
      message: question.value.trim(),
      message_type: 'poll',
      poll_options: filledOptions.value,
    });
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
    <Transition name="chat-poll-fade">
      <div v-if="open" class="chat-poll" role="presentation" @mousedown.self="close">
        <div
          class="chat-poll__panel"
          role="dialog"
          aria-modal="true"
          aria-labelledby="chat-poll-title"
        >
          <div class="chat-poll__head">
            <span class="chat-poll__icon" aria-hidden="true">
              <AppIcon name="listChecks" :size="16" :stroke-width="1.75" />
            </span>
            <div class="chat-poll__head-copy">
              <h2 id="chat-poll-title" class="chat-poll__title">Tạo bình chọn</h2>
              <p class="chat-poll__sub">Mọi người trong cuộc trò chuyện có thể bỏ phiếu</p>
            </div>
            <button
              type="button"
              class="chat-poll__close"
              aria-label="Đóng"
              :disabled="saving"
              @click="close"
            >
              <AppIcon name="close" :size="16" />
            </button>
          </div>

          <div class="chat-poll__body hide-scrollbar">
            <div class="chat-poll__presets" aria-label="Mẫu bình chọn">
              <button
                v-for="preset in CHAT_POLL_PRESETS"
                :key="preset.label"
                type="button"
                class="chat-poll__preset"
                :disabled="saving"
                @click="applyPreset(preset)"
              >
                {{ preset.label }}
              </button>
            </div>
            <form id="chat-poll-form" class="chat-poll__form" @submit.prevent="submit">
              <label class="chat-poll__field chat-poll__field--question">
                <span class="chat-poll__label">
                  Câu hỏi <span class="chat-poll__required">*</span>
                </span>
                <input
                  v-model="question"
                  class="chat-poll__input"
                  maxlength="200"
                  type="text"
                  placeholder="VD: Họp team vào khung giờ nào?"
                  :disabled="saving"
                />
              </label>

              <section class="chat-poll__options" aria-label="Lựa chọn bình chọn">
                <div class="chat-poll__options-head">
                  <span class="chat-poll__label">
                    Lựa chọn <span class="chat-poll__required">*</span>
                  </span>
                  <span class="chat-poll__options-count">{{ options.length }}/{{ MAX_OPTIONS }}</span>
                </div>

                <div class="chat-poll__options-grid">
                  <div v-for="(option, index) in options" :key="index" class="chat-poll__option-row">
                    <span class="chat-poll__option-index" aria-hidden="true">{{ index + 1 }}</span>
                    <input
                      :value="option"
                      type="text"
                      maxlength="80"
                      class="chat-poll__input chat-poll__input--option"
                      :placeholder="`Lựa chọn ${index + 1}`"
                      :aria-label="`Lựa chọn ${index + 1}`"
                      :disabled="saving"
                      @input="setOption(index, $event.target.value)"
                    />
                    <button
                      v-if="options.length > 2"
                      type="button"
                      class="chat-poll__option-remove"
                      aria-label="Xoá lựa chọn"
                      :disabled="saving"
                      @click="removeOption(index)"
                    >
                      <AppIcon name="close" :size="12" />
                    </button>
                  </div>
                </div>

                <button
                  v-if="options.length < MAX_OPTIONS"
                  type="button"
                  class="chat-poll__add"
                  :disabled="saving"
                  @click="addOption"
                >
                  <AppIcon name="plus" :size="13" />
                  Thêm lựa chọn
                </button>
              </section>
            </form>
            <p v-if="error" class="chat-poll__error" role="alert">{{ error }}</p>
          </div>

          <div class="chat-poll__actions">
            <button type="button" class="chat-poll__btn chat-poll__btn--ghost" :disabled="saving" @click="close">
              Huỷ
            </button>
            <button
              type="submit"
              form="chat-poll-form"
              class="chat-poll__btn chat-poll__btn--primary"
              :disabled="!canSubmit"
            >
              {{ saving ? 'Đang tạo…' : 'Tạo bình chọn' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.chat-poll {
  position: fixed;
  inset: 0;
  z-index: 300;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding: 0;
  background: var(--color-sidebar-overlay);
}

.chat-poll__panel {
  display: flex;
  flex-direction: column;
  width: 100%;
  max-width: 32rem;
  max-height: min(88vh, 36rem);
  overflow: hidden;
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
  animation: chat-poll-sheet-in 220ms ease;
}

@keyframes chat-poll-sheet-in {
  from {
    transform: translateY(12px);
    opacity: 0.6;
  }
}

.chat-poll__head,
.chat-poll__actions {
  flex-shrink: 0;
}

.chat-poll__head {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  padding: 0.85rem var(--space-4) var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
}

.chat-poll__head::before {
  content: '';
  position: absolute;
  left: 50%;
  top: 0.45rem;
  width: 2.25rem;
  height: 0.25rem;
  margin-left: -1.125rem;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-border) 85%, var(--color-text-muted));
}

.chat-poll__icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  margin-top: 0.1rem;
  border-radius: var(--radius-sm);
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 18%, transparent);
}

.chat-poll__head-copy {
  flex: 1;
  min-width: 0;
}

.chat-poll__title {
  margin: 0;
  color: var(--color-text);
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.25;
}

.chat-poll__sub {
  margin: 0.2rem 0 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 500;
  line-height: 1.35;
}

.chat-poll__close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  flex-shrink: 0;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.chat-poll__close:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.chat-poll__close:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.chat-poll__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: var(--space-3) var(--space-4);
}

.chat-poll__presets {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-bottom: var(--space-3);
}

.chat-poll__preset {
  border: 0;
  border-radius: var(--radius-full);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-family: inherit;
  font-size: 0.75rem;
  padding: 0.28rem 0.65rem;
  cursor: pointer;
}

.chat-poll__form {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
}

.chat-poll__field--question {
  grid-column: 1 / -1;
}

.chat-poll__field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  min-width: 0;
}

.chat-poll__label {
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.chat-poll__required {
  color: var(--color-primary);
}

.chat-poll__input {
  width: 100%;
  min-width: 0;
  min-height: 2.375rem;
  padding: 0.45rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.875rem;
  line-height: 1.35;
}

.chat-poll__input:focus {
  outline: 2px solid color-mix(in srgb, var(--color-primary) 35%, transparent);
  outline-offset: 1px;
  border-color: var(--color-primary);
}

.chat-poll__input:disabled {
  opacity: 0.65;
  cursor: not-allowed;
  background: var(--color-surface-muted);
}

.chat-poll__options {
  grid-column: 1 / -1;
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
  padding: 0.75rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.chat-poll__options-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
}

.chat-poll__options-count {
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.chat-poll__options-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.45rem;
}

.chat-poll__option-row {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  min-width: 0;
}

.chat-poll__option-index {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.35rem;
  height: 1.35rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text-muted);
  font-size: 0.625rem;
  font-weight: 700;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.chat-poll__input--option {
  min-height: 2.125rem;
  padding: 0.35rem 0.55rem;
  font-size: 0.8125rem;
}

.chat-poll__option-remove {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  flex-shrink: 0;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text-muted);
  cursor: pointer;
}

.chat-poll__option-remove:hover:not(:disabled) {
  color: var(--color-text);
}

.chat-poll__add {
  align-self: flex-start;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  border: none;
  background: none;
  color: var(--color-primary);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  padding: 0;
  cursor: pointer;
}

.chat-poll__add:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.chat-poll__error {
  margin: var(--space-2) 0 0;
  color: var(--color-danger);
  font-size: 0.8125rem;
}

.chat-poll__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 -1px 0 var(--color-border);
}

.chat-poll__btn {
  min-height: 2.375rem;
  padding: 0.45rem 1rem;
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  font-family: inherit;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.chat-poll__btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.chat-poll__btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.chat-poll__btn--primary:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.chat-poll__btn--ghost {
  border-color: var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
}

.chat-poll__btn--ghost:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.chat-poll-fade-enter-active,
.chat-poll-fade-leave-active {
  transition: opacity 0.15s ease;
}

.chat-poll-fade-enter-from,
.chat-poll-fade-leave-to {
  opacity: 0;
}

@media (min-width: 640px) {
  .chat-poll {
    align-items: center;
    padding: var(--space-5);
  }

  .chat-poll__panel {
    max-width: 40rem;
    max-height: calc(100vh - 2.5rem);
    border-radius: var(--radius-lg);
    animation: none;
  }

  .chat-poll__head::before {
    display: none;
  }

  .chat-poll__head {
    padding-top: var(--space-3);
  }
}

@media (max-width: 520px) {
  .chat-poll__form,
  .chat-poll__options-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (prefers-reduced-motion: reduce) {
  .chat-poll__panel {
    animation: none;
  }

  .chat-poll-fade-enter-active,
  .chat-poll-fade-leave-active {
    transition: none;
  }
}
</style>
