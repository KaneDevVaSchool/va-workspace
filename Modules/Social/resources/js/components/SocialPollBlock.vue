<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { formatSocialTime } from '../lib/formatSocialTime.js';
import SocialImageLightbox from './SocialImageLightbox.vue';

const props = defineProps({
  postId: { type: Number, required: true },
  poll: { type: Object, required: true },
});

const emit = defineEmits(['updated']);

const voting = ref(false);
const closing = ref(false);
const votersOpen = ref(false);
const votersLoading = ref(false);
const voters = ref([]);
const votersOptionId = ref(null);
const lightboxOpen = ref(false);

const selected = computed(() => new Set(props.poll.my_option_ids ?? []));
const totalLabel = computed(() => {
  const total = props.poll.total_votes;
  if (!props.poll.show_results || total == null) return null;
  return total === 1 ? '1 phiếu' : `${total} phiếu`;
});
const deadlineLabel = computed(() => {
  if (props.poll.is_ended) return props.poll.is_closed ? 'Đã đóng' : 'Đã hết hạn';
  if (props.poll.ends_at) return `Hết hạn ${formatSocialTime(props.poll.ends_at)}`;
  return null;
});
const leadingPercent = computed(() => {
  if (!props.poll.show_results) return 0;
  return Math.max(0, ...(props.poll.options ?? []).map((option) => option.percent ?? 0));
});
const votersTitle = computed(() => {
  const option = (props.poll.options ?? []).find((item) => item.id === votersOptionId.value);
  return option ? `Đã chọn: ${option.label}` : 'Người đã bình chọn';
});
const pollTitle = computed(() => props.poll.title || 'Bình chọn');
const pollContent = computed(() => (props.poll.content ?? '').trim());
const lightboxImages = computed(() => {
  if (!props.poll.image_url) return [];
  return [{ url: props.poll.image_url, name: pollTitle.value }];
});
const peekHint = computed(() => {
  if (props.poll.can_vote || !props.poll.show_results) return null;
  return 'Nhấn phương án để xem ai đã chọn';
});

function voteLabel(option) {
  if (option.votes_count == null) return null;
  return option.votes_count === 1 ? '1 phiếu' : `${option.votes_count} phiếu`;
}

function optionAria(option) {
  const parts = [option.label];
  if (selected.value.has(option.id)) parts.push('đã chọn');
  if (props.poll.show_results && option.percent != null) parts.push(`${option.percent}%`);
  const votes = voteLabel(option);
  if (votes) parts.push(votes);
  if (props.poll.can_vote) {
    parts.push(selected.value.has(option.id) ? 'nhấn để bỏ chọn' : 'nhấn để bình chọn');
  } else if (props.poll.show_results) {
    parts.push('nhấn để xem người đã chọn');
  }
  return parts.join(', ');
}

function onOptionClick(option) {
  if (props.poll.can_vote) {
    vote(option.id);
    return;
  }
  openVoters(option);
}

async function vote(optionId) {
  if (!props.poll.can_vote || voting.value) return;
  voting.value = true;
  try {
    const { data } = await window.axios.post(`/api/social/posts/${props.postId}/poll/votes`, {
      option_id: optionId,
    });
    emit('updated', data.poll);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không thể bình chọn.');
  } finally {
    voting.value = false;
  }
}

async function closePoll() {
  if (!props.poll.can_close || closing.value) return;
  closing.value = true;
  try {
    const { data } = await window.axios.post(`/api/social/posts/${props.postId}/poll/close`);
    emit('updated', data.poll);
    showClientToast('success', 'Đã đóng bình chọn.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không thể đóng bình chọn.');
  } finally {
    closing.value = false;
  }
}

async function openVoters(option) {
  if (!props.poll.show_results || option.votes_count == null) return;
  votersOptionId.value = option.id;
  votersOpen.value = true;
  votersLoading.value = true;
  document.addEventListener('keydown', onKey);
  document.body.style.overflow = 'hidden';
  try {
    const { data } = await window.axios.get(`/api/social/posts/${props.postId}/poll/votes`, {
      params: { option_id: option.id },
    });
    voters.value = data.users ?? [];
  } catch (error) {
    voters.value = [];
    showClientToast('error', error?.response?.data?.message ?? 'Không thể tải danh sách bình chọn.');
  } finally {
    votersLoading.value = false;
  }
}

function closeVoters() {
  const wasOpen = votersOpen.value;
  votersOpen.value = false;
  voters.value = [];
  votersOptionId.value = null;
  document.removeEventListener('keydown', onKey);
  if (wasOpen) {
    document.body.style.overflow = '';
  }
}

function onKey(event) {
  if (event.key === 'Escape') {
    event.preventDefault();
    closeVoters();
  }
}

watch(
  () => props.poll.id,
  () => closeVoters(),
);

onBeforeUnmount(closeVoters);
</script>

<template>
  <section
    class="poll"
    :class="{ 'poll--ended': poll.is_ended }"
    :aria-label="poll.is_ended ? 'Bình chọn đã kết thúc' : 'Bình chọn'"
    :aria-busy="voting || closing"
  >
    <div class="poll__layout" :class="{ 'poll__layout--solo': !poll.image_url }">
      <div class="poll__copy">
        <div class="poll__kicker">
          <span class="poll__badge">
            <AppIcon name="listChecks" :size="13" :stroke-width="2" />
            Bình chọn
          </span>
          <span v-if="totalLabel" class="poll__chip">
            <AppIcon name="users" :size="12" />
            {{ totalLabel }}
          </span>
          <span v-if="poll.allow_multiple" class="poll__chip">Chọn nhiều</span>
          <span
            v-if="deadlineLabel"
            class="poll__chip"
            :class="{
              'poll__chip--warn': poll.is_ended && !poll.is_closed,
              'poll__chip--closed': poll.is_closed,
            }"
          >
            <AppIcon name="clock" :size="12" />
            {{ deadlineLabel }}
          </span>
        </div>
        <h3 class="poll__title">{{ pollTitle }}</h3>
        <p v-if="pollContent" class="poll__content">{{ pollContent }}</p>
      </div>

      <button
        v-if="poll.image_url"
        type="button"
        class="poll__media"
        :aria-label="`Phóng to ảnh: ${pollTitle}`"
        @click="lightboxOpen = true"
      >
        <img :src="poll.image_url" :alt="pollTitle" class="poll__image" />
      </button>
    </div>

    <div class="poll__options">
      <button
        v-for="option in poll.options"
        :key="option.id"
        type="button"
        class="poll__option"
        :class="{
          'poll__option--selected': selected.has(option.id),
          'poll__option--results': poll.show_results,
          'poll__option--lead': poll.show_results && leadingPercent > 0 && option.percent === leadingPercent,
          'poll__option--peek': !poll.can_vote && poll.show_results,
        }"
        :disabled="voting"
        :aria-pressed="selected.has(option.id)"
        :aria-label="optionAria(option)"
        @click="onOptionClick(option)"
      >
        <span
          v-if="poll.show_results"
          class="poll__fill"
          :class="{ 'poll__fill--on': selected.has(option.id) }"
          :style="{ width: `${option.percent ?? 0}%` }"
        />
        <span class="poll__row">
          <span
            class="poll__mark"
            :class="{
              'poll__mark--on': selected.has(option.id),
              'poll__mark--multi': poll.allow_multiple,
            }"
            aria-hidden="true"
          >
            <AppIcon v-if="selected.has(option.id)" name="check" :size="12" />
          </span>
          <span class="poll__label">{{ option.label }}</span>
          <span
            v-if="poll.show_results && option.percent != null"
            class="poll__stat"
            @click.stop="openVoters(option)"
          >
            <span class="poll__percent">{{ option.percent }}%</span>
            <span v-if="voteLabel(option)" class="poll__votes">{{ voteLabel(option) }}</span>
          </span>
        </span>
      </button>
    </div>

    <div v-if="peekHint || poll.can_close" class="poll__foot">
      <p v-if="peekHint" class="poll__hint">{{ peekHint }}</p>
      <button
        v-if="poll.can_close"
        type="button"
        class="poll__close"
        :disabled="closing"
        @click="closePoll"
      >
        {{ closing ? 'Đang đóng...' : 'Đóng bình chọn' }}
      </button>
    </div>
  </section>

  <SocialImageLightbox
    :open="lightboxOpen"
    :images="lightboxImages"
    :index="0"
    @close="lightboxOpen = false"
  />

  <Teleport to="body">
    <Transition name="poll-voters-fade">
      <div
        v-if="votersOpen"
        class="poll-voters"
        role="presentation"
        @mousedown.self="closeVoters"
      >
        <div class="poll-voters__panel" role="dialog" aria-modal="true" :aria-label="votersTitle">
          <header class="poll-voters__header">
            <h2 class="poll-voters__title">{{ votersTitle }}</h2>
            <button type="button" class="poll-voters__close" aria-label="Đóng" @click="closeVoters">
              <AppIcon name="close" :size="18" />
            </button>
          </header>
          <div class="poll-voters__body hide-scrollbar">
            <p v-if="votersLoading" class="poll-voters__empty">Đang tải...</p>
            <p v-else-if="voters.length === 0" class="poll-voters__empty">Chưa có ai chọn phương án này.</p>
            <ul v-else class="poll-voters__people">
              <li v-for="item in voters" :key="item.user.id" class="poll-voters__person">
                <img
                  v-if="item.user.avatar_url"
                  class="poll-voters__avatar"
                  :src="item.user.avatar_url"
                  :alt="`Ảnh đại diện của ${item.user.name}`"
                />
                <div v-else class="poll-voters__avatar poll-voters__avatar--placeholder">
                  {{ item.user.name?.charAt(0) ?? '?' }}
                </div>
                <div class="poll-voters__info">
                  <div class="poll-voters__name">{{ item.user.name }}</div>
                  <div v-if="item.user.department" class="poll-voters__dept">{{ item.user.department }}</div>
                </div>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.poll {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  min-width: 0;
  padding: var(--space-4);
  padding-left: calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.poll::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-primary);
}

.poll--ended::before {
  background: var(--color-border-strong);
}

.poll__layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 9.5rem;
  gap: var(--space-3);
  align-items: start;
}

.poll__layout--solo {
  grid-template-columns: minmax(0, 1fr);
}

.poll__copy {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-width: 0;
}

.poll__kicker {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-1);
}

.poll__badge,
.poll__chip {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  min-height: 1.5rem;
  padding: 0 0.5rem;
  border-radius: var(--radius-full);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.01em;
  line-height: 1;
  white-space: nowrap;
}

.poll__badge {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.poll__chip {
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.poll__chip--warn {
  background: var(--color-warning-tint-bg);
  color: var(--color-warning-tint-fg);
}

.poll__chip--closed {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}

.poll__title {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 700;
  line-height: 1.35;
  color: var(--color-text);
}

.poll__content {
  margin: 0;
  font-size: 0.875rem;
  line-height: 1.55;
  color: var(--color-text-muted);
  white-space: pre-wrap;
}

.poll__media {
  display: block;
  width: 100%;
  min-width: 0;
  padding: 0;
  overflow: hidden;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  cursor: zoom-in;
}

.poll__media:hover .poll__image {
  transform: scale(1.03);
}

.poll__media:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.poll__image {
  display: block;
  width: 100%;
  aspect-ratio: 4 / 3;
  object-fit: cover;
  transition: transform 0.25s ease;
}

.poll__options {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.poll__option {
  position: relative;
  display: block;
  width: 100%;
  overflow: hidden;
  padding: 0;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  color: var(--color-text);
  font-family: inherit;
  text-align: left;
  cursor: pointer;
  transition: box-shadow 0.15s ease, background 0.15s ease;
}

.poll__option:hover:not(:disabled) {
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 35%, var(--color-border));
  background: color-mix(in srgb, var(--color-primary) 4%, var(--color-surface));
}

.poll__option--selected {
  box-shadow: inset 0 0 0 1.5px var(--color-primary);
  background: var(--color-primary-surface);
}

.poll__option--selected:hover:not(:disabled) {
  box-shadow: inset 0 0 0 1.5px var(--color-primary);
}

.poll__option--lead:not(.poll__option--selected) {
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 28%, var(--color-border));
}

.poll__option:disabled {
  cursor: wait;
}

.poll__option--peek:disabled {
  cursor: default;
}

.poll__option:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.poll__fill {
  position: absolute;
  inset: 0 auto 0 0;
  background: color-mix(in srgb, var(--color-primary) 10%, var(--color-surface-muted));
  transition: width 0.45s ease;
  pointer-events: none;
}

.poll__fill--on {
  background: color-mix(in srgb, var(--color-primary) 22%, var(--color-surface));
}

.poll__option--lead .poll__fill:not(.poll__fill--on) {
  background: color-mix(in srgb, var(--color-primary) 16%, var(--color-surface-muted));
}

.poll__row {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-height: 2.75rem;
  padding: 0.55rem 0.75rem;
}

.poll__mark {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.125rem;
  height: 1.125rem;
  flex-shrink: 0;
  border-radius: var(--radius-full);
  box-shadow: inset 0 0 0 1.5px var(--color-border);
  background: var(--color-surface);
}

.poll__mark--multi {
  border-radius: 0.25rem;
}

.poll__mark--on {
  background: var(--color-primary);
  color: var(--color-on-primary);
  box-shadow: none;
}

.poll__label {
  flex: 1;
  min-width: 0;
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.35;
  word-break: break-word;
}

.poll__stat {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  flex-shrink: 0;
  gap: 0.05rem;
  min-width: 3.25rem;
  cursor: pointer;
}

.poll__percent {
  font-variant-numeric: tabular-nums;
  font-size: 0.875rem;
  font-weight: 700;
  line-height: 1.2;
  color: var(--color-primary);
}

.poll__votes {
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.poll__stat:hover .poll__percent,
.poll__stat:hover .poll__votes {
  text-decoration: underline;
}

.poll__foot {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2) var(--space-3);
}

.poll__hint {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.poll__close {
  margin-left: auto;
  min-height: 2rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  color: var(--color-text-muted);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.poll__close:hover:not(:disabled) {
  color: var(--color-danger-tint-fg);
  box-shadow: inset 0 0 0 1px var(--color-danger-tint-border);
  background: var(--color-danger-tint-bg);
}

.poll__close:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.poll__close:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 640px) {
  .poll__layout {
    grid-template-columns: minmax(0, 1fr);
  }

  .poll__image {
    aspect-ratio: 16 / 9;
  }
}

@media (prefers-reduced-motion: reduce) {
  .poll__fill,
  .poll__image {
    transition: none;
  }
}

.poll-voters {
  position: fixed;
  inset: 0;
  z-index: 310;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
  background: var(--color-sidebar-overlay);
}

.poll-voters__panel {
  width: min(26rem, calc(100vw - 2rem));
  height: min(32rem, calc(100vh - 2.5rem));
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.poll-voters__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  flex-shrink: 0;
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.poll-voters__title {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 700;
  color: var(--color-text);
}

.poll-voters__close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text);
  cursor: pointer;
}

.poll-voters__close:hover {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.poll-voters__close:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.poll-voters__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

.poll-voters__empty {
  margin: 0;
  padding: var(--space-5);
  text-align: center;
  font-size: 0.875rem;
  color: var(--color-text-muted);
}

.poll-voters__people {
  list-style: none;
  margin: 0;
  padding: var(--space-2) 0;
}

.poll-voters__person {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2) var(--space-4);
}

.poll-voters__person:hover {
  background: var(--color-surface-muted);
}

.poll-voters__avatar {
  width: 40px;
  height: 40px;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
}

.poll-voters__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-weight: 600;
}

.poll-voters__info {
  min-width: 0;
}

.poll-voters__name {
  font-weight: 600;
  color: var(--color-text);
}

.poll-voters__dept {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.poll-voters-fade-enter-active,
.poll-voters-fade-leave-active {
  transition: opacity 0.15s ease;
}

.poll-voters-fade-enter-from,
.poll-voters-fade-leave-to {
  opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
  .poll-voters-fade-enter-active,
  .poll-voters-fade-leave-active {
    transition: none;
  }
}
</style>
