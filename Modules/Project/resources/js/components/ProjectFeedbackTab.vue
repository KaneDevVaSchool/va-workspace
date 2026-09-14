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
const hoverRating = ref(0);
const form = reactive({ content: '', rating: '' });

const endpoint = computed(() => `/api/project/${props.project.id}/feedbacks`);

const avgRating = computed(() => {
  const rated = feedbacks.value.filter((f) => f.rating);
  if (!rated.length) return null;
  const avg = rated.reduce((sum, f) => sum + Number(f.rating), 0) / rated.length;
  return Math.round(avg * 10) / 10;
});

const RATING_HUES = { 1: 4, 2: 28, 3: 45, 4: 96, 5: 152 };

function ringStyle(rating) {
  const hue = RATING_HUES[Number(rating)] || 258;
  return {
    '--ring-a': `hsl(${hue} 85% 60%)`,
    '--ring-b': `hsl(${hue + 40} 85% 65%)`,
  };
}

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
  hoverRating.value = 0;
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
    <div class="fbt__aura" aria-hidden="true" />

    <header class="fbt__head">
      <span class="fbt__head-icon"><AppIcon name="messageCircle" :size="16" /></span>
      <h3 class="fbt__title">Phản hồi</h3>
      <span v-if="feedbacks.length" class="fbt__count">{{ feedbacks.length }}</span>
      <span v-if="avgRating" class="fbt__avg">
        <span class="fbt__avg-star">★</span>
        {{ avgRating }}<span class="fbt__avg-max">/5</span>
      </span>
    </header>

    <form class="fbt__form" :class="{ 'fbt__form--editing': editingId }" @submit.prevent="submit">
      <div class="fbt__field">
        <textarea
          ref="composerRef"
          v-model="form.content"
          class="fbt__textarea"
          rows="3"
          maxlength="5000"
          placeholder=" "
        />
        <label class="fbt__floating-label">{{ editingId ? 'Sửa phản hồi' : 'Nhận xét/đánh giá về kết quả dự án…' }}</label>
      </div>
      <div class="fbt__form-row">
        <div class="fbt__rating" role="radiogroup" aria-label="Đánh giá">
          <button
            v-for="n in 5"
            :key="n"
            type="button"
            class="fbt__star"
            :class="{ 'fbt__star--on': n <= (hoverRating || form.rating) }"
            role="radio"
            :aria-checked="Number(form.rating) === n"
            @mouseenter="hoverRating = n"
            @mouseleave="hoverRating = 0"
            @click="form.rating = (Number(form.rating) === n ? '' : n)"
          >★</button>
          <span class="fbt__rating-text">{{ form.rating ? `${form.rating}/5` : 'Chưa đánh giá' }}</span>
        </div>
        <div class="fbt__form-actions">
          <button v-if="editingId" type="button" class="fbt__btn" :disabled="saving" @click="cancelEdit">Huỷ</button>
          <button type="submit" class="fbt__btn fbt__btn--primary" :disabled="saving">
            <span v-if="saving" class="fbt__spinner" aria-hidden="true" />
            {{ saving ? 'Đang lưu…' : (editingId ? 'Cập nhật' : 'Gửi phản hồi') }}
          </button>
        </div>
      </div>
    </form>

    <p v-if="loading" class="fbt__empty">Đang tải phản hồi…</p>
    <div v-else-if="!feedbacks.length" class="fbt__empty fbt__empty--rich">
      <span class="fbt__empty-icon"><AppIcon name="messageCircle" :size="22" /></span>
      Dự án chưa có phản hồi nào. Hãy là người đầu tiên chia sẻ nhận xét!
    </div>

    <ul v-else class="fbt__list">
      <li
        v-for="feedback in feedbacks"
        :key="feedback.id"
        class="fbt__item"
        :class="{ 'fbt__item--mine': isMine(feedback) }"
      >
        <span class="fbt__avatar-ring" :style="ringStyle(feedback.rating)">
          <img
            v-if="feedback.author?.avatar_url"
            class="fbt__avatar"
            :src="feedback.author.avatar_url"
            :alt="`Ảnh đại diện của ${feedback.author.name}`"
          >
          <span v-else class="fbt__avatar fbt__avatar--placeholder">{{ feedback.author?.name?.charAt(0) || '?' }}</span>
        </span>
        <div class="fbt__item-body">
          <div class="fbt__item-head">
            <span class="fbt__author">{{ feedback.author?.name || '—' }}</span>
            <span v-if="isMine(feedback)" class="fbt__you">bạn</span>
            <span v-if="feedback.rating" class="fbt__stars" :aria-label="`Đánh giá ${feedback.rating}/5`">
              <span v-for="n in 5" :key="n" class="fbt__stars-i" :class="{ 'fbt__stars-i--on': n <= feedback.rating }">★</span>
            </span>
            <span class="fbt__when">{{ formatWhen(feedback.created_at) }}</span>
          </div>
          <p class="fbt__content">{{ feedback.content }}</p>
          <div v-if="isMine(feedback)" class="fbt__item-actions">
            <button type="button" class="fbt__link" @click="startEdit(feedback)">
              <AppIcon name="pencil" :size="12" /> Sửa
            </button>
            <button type="button" class="fbt__link fbt__link--danger" @click="removeFeedback(feedback)">
              <AppIcon name="trash2" :size="12" /> Xoá
            </button>
          </div>
        </div>
      </li>
    </ul>
  </section>
</template>

<style scoped>
/*
 * Thiết kế riêng cho khối Phản hồi — cố tình vượt ra ngoài token theme mặc
 * định của dự án (yêu cầu rõ ràng từ người dùng): bảng màu gradient riêng,
 * glassmorphism, motion. Không dùng làm mẫu cho các component khác.
 */
.fbt {
  --fbt-ink: #1a1033;
  --fbt-ink-soft: #6b6285;
  --fbt-violet: #7c3aed;
  --fbt-violet-2: #6366f1;
  --fbt-pink: #ec4899;
  --fbt-amber: #f59e0b;
  --fbt-surface: #ffffff;
  --fbt-surface-2: #faf8ff;
  --fbt-line: rgba(124, 58, 237, 0.14);

  position: relative;
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
  padding: 1.5rem 1.6rem 1.7rem;
  overflow: hidden;
  border-radius: 1.5rem;
  background: var(--fbt-surface);
  box-shadow:
    0 1px 2px rgba(26, 16, 51, 0.04),
    0 18px 40px -22px rgba(99, 51, 220, 0.35),
    inset 0 0 0 1px var(--fbt-line);
  font-family: 'Be Vietnam Pro', system-ui, sans-serif;
  color: var(--fbt-ink);
}

.fbt__aura {
  position: absolute;
  inset: -40% -20% auto auto;
  width: 22rem;
  height: 22rem;
  background: radial-gradient(circle, rgba(124, 58, 237, 0.16), rgba(236, 72, 153, 0.08) 45%, transparent 70%);
  pointer-events: none;
  z-index: 0;
}

.fbt > :not(.fbt__aura) {
  position: relative;
  z-index: 1;
}

.fbt__head {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding-bottom: 1rem;
  box-shadow: 0 1px 0 var(--fbt-line);
}

.fbt__head-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.1rem;
  height: 2.1rem;
  border-radius: 0.85rem;
  background: linear-gradient(135deg, var(--fbt-violet), var(--fbt-violet-2));
  color: #fff;
  box-shadow: 0 6px 14px -6px rgba(124, 58, 237, 0.65);
}

.fbt__title {
  margin: 0;
  font-size: 1.02rem;
  font-weight: 800;
  letter-spacing: -0.01em;
}

.fbt__count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.4rem;
  height: 1.4rem;
  padding: 0 0.45rem;
  border-radius: 999px;
  background: linear-gradient(135deg, var(--fbt-violet), var(--fbt-violet-2));
  color: #fff;
  font-size: 0.7rem;
  font-weight: 800;
}

.fbt__avg {
  display: inline-flex;
  align-items: baseline;
  gap: 0.2rem;
  margin-left: auto;
  padding: 0.3rem 0.7rem;
  border-radius: 999px;
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.16), rgba(236, 72, 153, 0.12));
  color: #b45309;
  font-size: 0.85rem;
  font-weight: 800;
}

.fbt__avg-star {
  color: var(--fbt-amber);
  font-size: 0.85rem;
}

.fbt__avg-max {
  color: var(--fbt-ink-soft);
  font-weight: 600;
  font-size: 0.7rem;
}

/* ---- Form ---- */
.fbt__form {
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
  padding: 1.1rem 1.2rem;
  border-radius: 1.15rem;
  background: linear-gradient(180deg, var(--fbt-surface-2), #fff);
  box-shadow: inset 0 0 0 1px var(--fbt-line);
  transition: box-shadow 0.25s ease;
}

.fbt__form:focus-within {
  box-shadow: inset 0 0 0 1.5px var(--fbt-violet), 0 0 0 4px rgba(124, 58, 237, 0.12);
}

.fbt__form--editing {
  box-shadow: inset 0 0 0 1.5px var(--fbt-pink), 0 0 0 4px rgba(236, 72, 153, 0.12);
}

.fbt__field {
  position: relative;
}

.fbt__textarea {
  width: 100%;
  min-width: 0;
  padding: 1.1rem 0.9rem 0.5rem;
  border: none;
  border-radius: 0.85rem;
  background: #fff;
  color: var(--fbt-ink);
  font-family: inherit;
  font-size: 0.9rem;
  line-height: 1.5;
  resize: vertical;
  box-shadow: inset 0 0 0 1px var(--fbt-line);
  transition: box-shadow 0.2s ease;
}

.fbt__textarea:focus {
  outline: none;
  box-shadow: inset 0 0 0 1.5px var(--fbt-violet-2);
}

.fbt__floating-label {
  position: absolute;
  top: 0.65rem;
  left: 0.95rem;
  color: var(--fbt-ink-soft);
  font-size: 0.78rem;
  font-weight: 600;
  pointer-events: none;
  transition: all 0.15s ease;
}

.fbt__textarea:focus + .fbt__floating-label,
.fbt__textarea:not(:placeholder-shown) + .fbt__floating-label {
  top: -0.55rem;
  left: 0.7rem;
  padding: 0 0.35rem;
  background: #fff;
  color: var(--fbt-violet);
  font-size: 0.68rem;
}

.fbt__form-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.fbt__rating {
  display: flex;
  align-items: center;
  gap: 0.15rem;
}

.fbt__star {
  border: none;
  background: none;
  padding: 0.1rem;
  color: #d8d3ea;
  font-size: 1.35rem;
  line-height: 1;
  cursor: pointer;
  transition: transform 0.12s ease, color 0.12s ease;
}

.fbt__star:hover {
  transform: scale(1.2);
}

.fbt__star--on {
  color: var(--fbt-amber);
  text-shadow: 0 2px 8px rgba(245, 158, 11, 0.45);
}

.fbt__rating-text {
  margin-left: 0.5rem;
  color: var(--fbt-ink-soft);
  font-size: 0.78rem;
  font-weight: 600;
}

.fbt__form-actions {
  display: flex;
  gap: 0.6rem;
}

.fbt__btn {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  height: 2.3rem;
  padding: 0 1.1rem;
  border: none;
  border-radius: 0.75rem;
  background: #fff;
  color: var(--fbt-ink-soft);
  font-family: inherit;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  box-shadow: inset 0 0 0 1px var(--fbt-line);
  transition: transform 0.12s ease, box-shadow 0.12s ease;
}

.fbt__btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: inset 0 0 0 1px rgba(124, 58, 237, 0.35);
}

.fbt__btn--primary {
  background: linear-gradient(135deg, var(--fbt-violet), var(--fbt-pink));
  color: #fff;
  box-shadow: 0 8px 18px -8px rgba(124, 58, 237, 0.65);
}

.fbt__btn--primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 12px 22px -8px rgba(124, 58, 237, 0.75);
}

.fbt__btn:disabled {
  opacity: 0.6;
  cursor: default;
  transform: none;
}

.fbt__spinner {
  width: 0.8rem;
  height: 0.8rem;
  border-radius: 50%;
  border: 2px solid rgba(255, 255, 255, 0.5);
  border-top-color: #fff;
  animation: fbt-spin 0.7s linear infinite;
}

@keyframes fbt-spin {
  to { transform: rotate(360deg); }
}

/* ---- Empty state ---- */
.fbt__empty {
  margin: 0;
  padding: 1.5rem 0;
  color: var(--fbt-ink-soft);
  font-size: 0.85rem;
  text-align: center;
}

.fbt__empty--rich {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.6rem;
}

.fbt__empty-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.6rem;
  height: 2.6rem;
  border-radius: 50%;
  background: linear-gradient(135deg, rgba(124, 58, 237, 0.12), rgba(236, 72, 153, 0.1));
  color: var(--fbt-violet);
}

/* ---- List ---- */
.fbt__list {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.fbt__item {
  display: flex;
  gap: 0.85rem;
  padding: 0.95rem 0.75rem;
  border-radius: 1rem;
  transition: background 0.15s ease;
}

.fbt__item:hover {
  background: var(--fbt-surface-2);
}

.fbt__item--mine {
  background: linear-gradient(90deg, rgba(124, 58, 237, 0.05), transparent 60%);
}

.fbt__avatar-ring {
  flex-shrink: 0;
  display: inline-flex;
  padding: 2px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--ring-a, #c7c3e0), var(--ring-b, #c7c3e0));
}

.fbt__avatar {
  width: 2.4rem;
  height: 2.4rem;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid #fff;
}

.fbt__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--fbt-surface-2);
  color: var(--fbt-violet);
  font-size: 0.9rem;
  font-weight: 800;
}

.fbt__item-body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.fbt__item-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.fbt__author {
  font-size: 0.86rem;
  font-weight: 800;
}

.fbt__you {
  padding: 0.05rem 0.45rem;
  border-radius: 999px;
  background: rgba(124, 58, 237, 0.12);
  color: var(--fbt-violet);
  font-size: 0.65rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.fbt__stars {
  display: inline-flex;
  gap: 0.05rem;
}

.fbt__stars-i {
  color: #e2ddf0;
  font-size: 0.75rem;
}

.fbt__stars-i--on {
  color: var(--fbt-amber);
}

.fbt__when {
  margin-left: auto;
  color: var(--fbt-ink-soft);
  font-size: 0.72rem;
}

.fbt__content {
  margin: 0;
  color: var(--fbt-ink);
  font-size: 0.88rem;
  line-height: 1.55;
  white-space: pre-wrap;
}

.fbt__item-actions {
  display: flex;
  gap: 1rem;
}

.fbt__link {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  border: none;
  padding: 0;
  background: none;
  color: var(--fbt-ink-soft);
  font-family: inherit;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
  transition: color 0.12s ease;
}

.fbt__link:hover {
  color: var(--fbt-violet);
}

.fbt__link--danger:hover {
  color: #e11d48;
}

@media (max-width: 768px) {
  .fbt {
    padding: 1.2rem 1.1rem 1.4rem;
    border-radius: 1.1rem;
  }

  .fbt__form-row {
    flex-direction: column;
    align-items: stretch;
  }

  .fbt__rating {
    justify-content: center;
  }

  .fbt__form-actions {
    justify-content: flex-end;
  }
}

@media (max-width: 480px) {
  .fbt__avg {
    display: none;
  }

  .fbt__item {
    padding: 0.85rem 0.4rem;
  }
}
</style>
