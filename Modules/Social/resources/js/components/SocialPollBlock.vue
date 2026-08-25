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
const nowMs = ref(Date.now());

let tickTimer = null;

const selected = computed(() => new Set(props.poll.my_option_ids ?? []));
const endAtMs = computed(() => {
  if (!props.poll.ends_at) return null;
  const at = new Date(props.poll.ends_at).getTime();
  return Number.isFinite(at) ? at : null;
});
const remainingMs = computed(() => {
  if (endAtMs.value == null) return null;
  return Math.max(0, endAtMs.value - nowMs.value);
});
const isEnded = computed(() => {
  if (props.poll.is_ended) return true;
  return remainingMs.value === 0 && endAtMs.value != null;
});
const canVote = computed(() => props.poll.can_vote && !isEnded.value);
const canClose = computed(() => props.poll.can_close && !isEnded.value);
const showCountdown = computed(() => remainingMs.value != null && remainingMs.value > 0 && !props.poll.is_ended);
const urgency = computed(() => {
  const ms = remainingMs.value;
  if (ms == null || ms <= 0) return 'ended';
  if (ms < 5 * 60 * 1000) return 'critical';
  if (ms < 60 * 60 * 1000) return 'urgent';
  return 'live';
});
const countdown = computed(() => {
  if (remainingMs.value == null) return null;
  const total = Math.floor(remainingMs.value / 1000);
  const days = Math.floor(total / 86400);
  const hours = Math.floor((total % 86400) / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  const seconds = total % 60;
  const units = [];
  if (days > 0) units.push({ key: 'd', value: days, label: 'ngày', pad: days > 99 ? 3 : 2 });
  units.push(
    { key: 'h', value: hours, label: 'giờ', pad: 2 },
    { key: 'm', value: minutes, label: 'phút', pad: 2 },
    { key: 's', value: seconds, label: 'giây', pad: 2 },
  );
  return { days, hours, minutes, seconds, total, units };
});
const countdownAria = computed(() => {
  if (!countdown.value) return '';
  const { days, hours, minutes, seconds } = countdown.value;
  const parts = [];
  if (days > 0) parts.push(`${days} ngày`);
  if (hours > 0 || days > 0) parts.push(`${hours} giờ`);
  parts.push(`${minutes} phút`, `${seconds} giây`);
  return `Còn ${parts.join(' ')}`;
});
const totalLabel = computed(() => {
  const total = props.poll.total_votes;
  if (!props.poll.show_results || total == null) return null;
  return total === 1 ? '1 phiếu' : `${total} phiếu`;
});
const statusChip = computed(() => {
  if (!isEnded.value) return null;
  return props.poll.is_closed ? 'Đã đóng' : 'Đã hết hạn';
});
const deadlineExact = computed(() => {
  if (!props.poll.ends_at) return null;
  return formatSocialTime(props.poll.ends_at);
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
  if (canVote.value || !props.poll.show_results) return null;
  return 'Nhấn phương án để xem ai đã chọn';
});

function padUnit(value, width) {
  return String(value).padStart(width, '0');
}

function startTicker() {
  stopTicker();
  nowMs.value = Date.now();
  if (!showCountdown.value) return;
  tickTimer = window.setInterval(() => {
    nowMs.value = Date.now();
    if (!showCountdown.value) stopTicker();
  }, 1000);
}

function stopTicker() {
  if (tickTimer == null) return;
  window.clearInterval(tickTimer);
  tickTimer = null;
}

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
  if (canVote.value) {
    parts.push(selected.value.has(option.id) ? 'nhấn để bỏ chọn' : 'nhấn để bình chọn');
  } else if (props.poll.show_results) {
    parts.push('nhấn để xem người đã chọn');
  }
  return parts.join(', ');
}

function onOptionClick(option) {
  if (canVote.value) {
    vote(option.id);
    return;
  }
  openVoters(option);
}

async function vote(optionId) {
  if (!canVote.value || voting.value) return;
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
  if (!canClose.value || closing.value) return;
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

watch(
  () => [props.poll.ends_at, props.poll.is_ended, props.poll.id],
  () => startTicker(),
  { immediate: true },
);

onBeforeUnmount(() => {
  stopTicker();
  closeVoters();
});
</script>

<template>
  <section
    class="poll"
    :class="{
      'poll--ended': isEnded,
      'poll--urgent': showCountdown && urgency === 'urgent',
      'poll--critical': showCountdown && urgency === 'critical',
    }"
    :aria-label="isEnded ? 'Bình chọn đã kết thúc' : 'Bình chọn'"
    :aria-busy="voting || closing"
  >
    <div class="poll__head">
      <div class="poll__kicker">
        <span class="poll__badge">
          <AppIcon name="listChecks" :size="13" :stroke-width="2" />
          Bình chọn
        </span>
        <span v-if="poll.allow_multiple" class="poll__chip poll__chip--info">Chọn nhiều</span>
        <span v-if="totalLabel" class="poll__chip poll__chip--votes">
          <AppIcon name="users" :size="12" />
          {{ totalLabel }}
        </span>
        <span
          v-if="statusChip"
          class="poll__chip"
          :class="poll.is_closed ? 'poll__chip--closed' : 'poll__chip--warn'"
        >
          <AppIcon name="clock" :size="12" />
          {{ statusChip }}
        </span>
      </div>
      <h3 class="poll__title">{{ pollTitle }}</h3>
      <p v-if="pollContent" class="poll__content">{{ pollContent }}</p>
    </div>

    <div
      v-if="showCountdown && countdown"
      class="poll__timer"
      role="timer"
      :aria-label="countdownAria"
    >
      <div class="poll__timer-icon" aria-hidden="true">
        <svg class="poll__ring" viewBox="0 0 36 36">
          <circle class="poll__ring-track" cx="18" cy="18" r="15" />
          <circle class="poll__ring-value" cx="18" cy="18" r="15" />
        </svg>
        <AppIcon name="clock" :size="14" />
      </div>
      <div class="poll__timer-copy">
        <span class="poll__timer-kicker">Còn lại</span>
        <span v-if="deadlineExact" class="poll__timer-until">Hết hạn {{ deadlineExact }}</span>
      </div>
      <div class="poll__units">
        <template v-for="(unit, index) in countdown.units" :key="unit.key">
          <div class="poll__unit">
            <div class="poll__flip">
              <Transition name="poll-tick">
                <span :key="`${unit.key}-${unit.value}`" class="poll__num">{{ padUnit(unit.value, unit.pad) }}</span>
              </Transition>
            </div>
            <span class="poll__unit-label">{{ unit.label }}</span>
          </div>
          <span v-if="index < countdown.units.length - 1" class="poll__colon" aria-hidden="true">:</span>
        </template>
      </div>
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
          'poll__option--peek': !canVote && poll.show_results,
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

    <div v-if="peekHint || canClose" class="poll__foot">
      <p v-if="peekHint" class="poll__hint">{{ peekHint }}</p>
      <button
        v-if="canClose"
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
  --poll-accent: var(--color-primary);
  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  min-width: 0;
  padding: var(--space-4);
  padding-left: calc(var(--space-2) + 3px + var(--space-3));
  border-radius: var(--radius-lg);
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
  background: var(--poll-accent);
}

.poll--urgent {
  --poll-accent: var(--color-warning);
}

.poll--critical {
  --poll-accent: var(--color-danger);
}

.poll--ended {
  --poll-accent: var(--color-border);
}

.poll--ended .poll__media {
  opacity: 0.88;
}

.poll__head {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-width: 0;
}

.poll__kicker {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem;
}

.poll__badge,
.poll__chip {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  min-height: 1.55rem;
  padding: 0 0.65rem;
  border-radius: var(--radius-full);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  line-height: 1;
  white-space: nowrap;
}

.poll__badge {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.poll__chip {
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.poll__chip--info {
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
}

.poll__chip--votes {
  background: var(--color-primary-surface);
  color: var(--color-primary);
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
  font-size: 1.125rem;
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

.poll__timer {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-3);
  min-width: 0;
  padding: 0.7rem 0.85rem;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--poll-accent) 8%, var(--color-surface-muted));
}

.poll__timer-icon {
  position: relative;
  display: grid;
  place-items: center;
  width: 2.25rem;
  height: 2.25rem;
  flex-shrink: 0;
  color: var(--poll-accent);
}

.poll__ring {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  transform: rotate(-90deg);
}

.poll__ring-track,
.poll__ring-value {
  fill: none;
  stroke-width: 2.5;
}

.poll__ring-track {
  stroke: color-mix(in srgb, var(--poll-accent) 22%, var(--color-surface));
}

.poll__ring-value {
  stroke: var(--poll-accent);
  stroke-linecap: round;
  stroke-dasharray: 94.2;
  stroke-dashoffset: 0;
  animation: poll-ring 1s linear infinite;
}

.poll__timer-copy {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  min-width: 0;
  flex: 1 1 7rem;
}

.poll__timer-kicker {
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--poll-accent);
}

.poll__timer-until {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.poll__units {
  display: flex;
  align-items: flex-start;
  gap: 0.35rem;
  margin-left: auto;
}

.poll__unit {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.2rem;
}

.poll__flip {
  position: relative;
  display: grid;
  place-items: center;
  overflow: hidden;
  min-width: 2.15rem;
  height: 1.85rem;
  padding: 0 0.35rem;
  border-radius: 0.4rem;
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  color: var(--color-text);
}

.poll--critical .poll__flip {
  color: var(--color-danger);
  animation: poll-pulse 1s ease-in-out infinite;
}

.poll__num {
  font-variant-numeric: tabular-nums;
  font-size: 1.05rem;
  font-weight: 800;
  letter-spacing: 0.02em;
  line-height: 1;
}

.poll__unit-label {
  font-size: 0.625rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.poll__colon {
  display: flex;
  align-items: center;
  height: 1.85rem;
  font-size: 1rem;
  font-weight: 800;
  line-height: 1;
  color: var(--poll-accent);
}

.poll-tick-enter-active,
.poll-tick-leave-active {
  transition: transform 0.28s ease, opacity 0.28s ease;
}

.poll-tick-leave-active {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
}

.poll-tick-enter-from {
  transform: translateY(-55%);
  opacity: 0;
}

.poll-tick-leave-to {
  transform: translateY(55%);
  opacity: 0;
}

@keyframes poll-ring {
  from {
    stroke-dashoffset: 0;
  }
  to {
    stroke-dashoffset: 94.2;
  }
}

@keyframes poll-pulse {
  0%,
  100% {
    box-shadow: var(--shadow-sm);
  }
  50% {
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-danger) 18%, transparent);
  }
}

.poll__media {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-height: 14rem;
  margin: 0;
  padding: var(--space-2);
  overflow: hidden;
  border: none;
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
  box-shadow: var(--shadow-sm);
  cursor: zoom-in;
}

.poll__media:hover .poll__image {
  transform: scale(1.02);
}

.poll__media:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.poll__image {
  display: block;
  width: auto;
  max-width: 100%;
  max-height: 24rem;
  object-fit: contain;
  object-position: center;
  border-radius: var(--radius-md);
  transition: transform 0.25s ease;
}

.poll__options {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  width: 100%;
}

.poll__option {
  position: relative;
  display: block;
  width: 100%;
  overflow: hidden;
  padding: 0;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: inherit;
  text-align: left;
  cursor: pointer;
  transition: box-shadow 0.15s ease, background 0.15s ease;
}

.poll__option:hover:not(:disabled) {
  box-shadow: var(--shadow-md);
  background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface-muted));
}

.poll__option--selected {
  background: var(--color-primary-surface);
  box-shadow: 0 0 0 1.5px var(--color-primary), var(--shadow-sm);
}

.poll__option--selected:hover:not(:disabled) {
  box-shadow: 0 0 0 1.5px var(--color-primary), var(--shadow-md);
}

.poll__option--lead:not(.poll__option--selected) {
  box-shadow: 0 0 0 1px color-mix(in srgb, var(--color-primary) 28%, var(--color-border)), var(--shadow-sm);
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
  background: color-mix(in srgb, var(--color-primary) 12%, var(--color-surface-muted));
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
  gap: var(--space-3);
  min-height: 3rem;
  padding: 0.7rem 0.9rem;
}

.poll__mark {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.25rem;
  height: 1.25rem;
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
  font-size: 0.9375rem;
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
  padding: 0 0.85rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.poll__close:hover:not(:disabled) {
  color: var(--color-danger-tint-fg);
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
  .poll {
    padding: var(--space-3);
    padding-left: calc(var(--space-2) + 3px + var(--space-3));
    gap: var(--space-3);
  }

  .poll__timer {
    gap: var(--space-2);
  }

  .poll__units {
    margin-left: 0;
    width: 100%;
    justify-content: flex-end;
  }

  .poll__flip {
    min-width: 1.95rem;
    height: 1.7rem;
  }

  .poll__num {
    font-size: 0.9375rem;
  }

  .poll__media {
    min-height: 10rem;
  }

  .poll__image {
    max-height: 18rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .poll__fill,
  .poll__image,
  .poll-tick-enter-active,
  .poll-tick-leave-active {
    transition: none;
  }

  .poll__ring-value,
  .poll--critical .poll__flip {
    animation: none;
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
