<script setup>
//
// Tab "Phản hồi" — phản hồi/đánh giá tổng thể kết quả dự án (tab tuỳ chọn,
// bật/tắt riêng theo dự án). Khác Thảo luận: list phẳng, không thread/
// reaction/mention, chỉ tác giả sửa/xoá được phản hồi của chính mình.
//
import { computed, onMounted, reactive, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';

const props = defineProps({
  project: { type: Object, required: true },
});

const emit = defineEmits(['count-changed']);

const auth = useAuthStore();

const feedbacks = ref([]);
const loading = ref(false);
const saving = ref(false);
const editingId = ref(null);
const form = reactive({ content: '', rating: '' });

const endpoint = computed(() => `/api/project/${props.project.id}/feedbacks`);

async function load() {
  loading.value = true;
  try {
    const { data } = await window.axios.get(endpoint.value);
    feedbacks.value = data.feedbacks || [];
    emit('count-changed', feedbacks.value.length);
  } catch {
    feedbacks.value = [];
  } finally {
    loading.value = false;
  }
}

function isMine(feedback) {
  return feedback.author?.id === auth.user?.id;
}

function startEdit(feedback) {
  editingId.value = feedback.id;
  form.content = feedback.content;
  form.rating = feedback.rating || '';
}

function cancelEdit() {
  editingId.value = null;
  form.content = '';
  form.rating = '';
}

async function submit() {
  const content = form.content.trim();
  if (!content) {
    showClientToast('error', 'Nhập nội dung phản hồi.');
    return;
  }
  saving.value = true;
  try {
    const payload = { content, rating: form.rating || null };
    if (editingId.value) {
      const { data } = await window.axios.put(`/api/project/feedbacks/${editingId.value}`, payload);
      feedbacks.value = feedbacks.value.map((f) => (f.id === data.feedback.id ? data.feedback : f));
      showClientToast('success', 'Đã cập nhật phản hồi.');
    } else {
      const { data } = await window.axios.post(endpoint.value, payload);
      feedbacks.value = [data.feedback, ...feedbacks.value];
      emit('count-changed', feedbacks.value.length);
      showClientToast('success', 'Đã gửi phản hồi.');
    }
    cancelEdit();
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không lưu được phản hồi.');
  } finally {
    saving.value = false;
  }
}

async function removeFeedback(feedback) {
  if (!window.confirm('Xoá phản hồi này?')) return;
  try {
    await window.axios.delete(`/api/project/feedbacks/${feedback.id}`);
    feedbacks.value = feedbacks.value.filter((f) => f.id !== feedback.id);
    emit('count-changed', feedbacks.value.length);
    showClientToast('success', 'Đã xoá phản hồi.');
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không xoá được phản hồi.');
  }
}

function formatWhen(value) {
  if (!value) return '';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '';
  return d.toLocaleString('vi-VN');
}

const composerRef = ref(null);

function focusComposer() {
  composerRef.value?.focus();
  composerRef.value?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

onMounted(load);

defineExpose({ focusComposer });
</script>

<template>
  <section class="fbt">
    <header class="fbt__head">
      <span class="fbt__head-icon"><AppIcon name="messageCircle" :size="14" /></span>
      <h3 class="fbt__title">Phản hồi</h3>
      <span v-if="feedbacks.length" class="fbt__count">{{ feedbacks.length }}</span>
    </header>

    <form class="fbt__form" @submit.prevent="submit">
      <label class="fbt__field">
        <span class="fbt__label">{{ editingId ? 'Sửa phản hồi' : 'Gửi phản hồi mới' }}</span>
        <textarea
          ref="composerRef"
          v-model="form.content"
          class="fbt__textarea"
          rows="3"
          maxlength="5000"
          placeholder="Nhận xét/đánh giá về kết quả dự án…"
        />
      </label>
      <div class="fbt__form-row">
        <label class="fbt__rating">
          <span>Đánh giá</span>
          <select v-model="form.rating" class="fbt__select">
            <option value="">Không đánh giá</option>
            <option v-for="n in 5" :key="n" :value="n">{{ n }}/5</option>
          </select>
        </label>
        <div class="fbt__form-actions">
          <button v-if="editingId" type="button" class="fbt__btn" :disabled="saving" @click="cancelEdit">Huỷ</button>
          <button type="submit" class="fbt__btn fbt__btn--primary" :disabled="saving">
            {{ saving ? 'Đang lưu…' : (editingId ? 'Cập nhật' : 'Gửi') }}
          </button>
        </div>
      </div>
    </form>

    <p v-if="loading" class="fbt__empty">Đang tải phản hồi…</p>
    <p v-else-if="!feedbacks.length" class="fbt__empty">Dự án chưa có phản hồi nào.</p>

    <ul v-else class="fbt__list">
      <li v-for="feedback in feedbacks" :key="feedback.id" class="fbt__item">
        <img
          v-if="feedback.author?.avatar_url"
          class="fbt__avatar"
          :src="feedback.author.avatar_url"
          :alt="`Ảnh đại diện của ${feedback.author.name}`"
        >
        <span v-else class="fbt__avatar fbt__avatar--placeholder">{{ feedback.author?.name?.charAt(0) || '?' }}</span>
        <div class="fbt__item-body">
          <div class="fbt__item-head">
            <span class="fbt__author">{{ feedback.author?.name || '—' }}</span>
            <span v-if="feedback.rating" class="fbt__stars">Đánh giá {{ feedback.rating }}/5</span>
            <span class="fbt__when">{{ formatWhen(feedback.created_at) }}</span>
          </div>
          <p class="fbt__content">{{ feedback.content }}</p>
          <div v-if="isMine(feedback)" class="fbt__item-actions">
            <button type="button" class="fbt__link" @click="startEdit(feedback)">Sửa</button>
            <button type="button" class="fbt__link fbt__link--danger" @click="removeFeedback(feedback)">Xoá</button>
          </div>
        </div>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.fbt {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-5);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.fbt__head {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding-bottom: var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
}

.fbt__head-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-sm);
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
}

.fbt__title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 700;
}

.fbt__count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.25rem;
  height: 1.25rem;
  padding: 0 0.375rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
}

.fbt__form {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.fbt__field {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.fbt__label {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
}

.fbt__textarea {
  width: 100%;
  min-width: 0;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  resize: vertical;
}

.fbt__form-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
}

.fbt__rating {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.fbt__select {
  padding: 0.375rem 0.5rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
}

.fbt__form-actions {
  display: flex;
  gap: var(--space-2);
}

.fbt__btn {
  display: inline-flex;
  align-items: center;
  height: 2rem;
  padding: 0 0.875rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.fbt__btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
  box-shadow: none;
}

.fbt__btn--primary:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.fbt__btn:disabled {
  opacity: 0.6;
  cursor: default;
}

.fbt__empty {
  margin: 0;
  padding: var(--space-4) 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  text-align: center;
}

.fbt__list {
  display: flex;
  flex-direction: column;
  gap: 0;
  margin: 0;
  padding: 0;
  list-style: none;
}

.fbt__item {
  display: flex;
  gap: var(--space-3);
  padding: var(--space-3) 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.fbt__item:last-child {
  box-shadow: none;
}

.fbt__avatar {
  flex-shrink: 0;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-full);
  object-fit: cover;
}

.fbt__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.875rem;
  font-weight: 600;
}

.fbt__item-body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.fbt__item-head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.5rem;
}

.fbt__author {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 700;
}

.fbt__stars {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.fbt__when {
  margin-left: auto;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.fbt__content {
  margin: 0;
  color: var(--color-text);
  font-size: 0.875rem;
  line-height: 1.5;
  white-space: pre-wrap;
}

.fbt__item-actions {
  display: flex;
  gap: var(--space-3);
}

.fbt__link {
  border: none;
  padding: 0;
  background: none;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.fbt__link:hover {
  color: var(--color-primary);
}

.fbt__link--danger:hover {
  color: var(--color-danger-tint-fg);
}

@media (max-width: 768px) {
  .fbt__form-row {
    flex-direction: column;
    align-items: stretch;
  }

  .fbt__form-actions {
    justify-content: flex-end;
  }
}
</style>
