<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useDualProgressTip } from '@/composables/useDualProgressTip';

const props = defineProps({
  actual: { type: Number, default: null },
  expected: { type: Number, default: null },
  size: { type: String, default: 'sm' }, // 'sm' (ô bảng) | 'md' (panel chi tiết)
});

const { isOpen, open, close } = useDualProgressTip();
const rootRef = ref(null);
const cardRef = ref(null);
const cardStyle = ref({});

const clamp = (value) => Math.max(0, Math.min(100, value));

function placeCard() {
  const root = rootRef.value;
  const card = cardRef.value;
  if (!root || !card) return;

  const rect = root.getBoundingClientRect();
  const cardRect = card.getBoundingClientRect();
  const gap = 8;
  const vw = window.innerWidth;
  const vh = window.innerHeight;

  let left = rect.left;
  if (left + cardRect.width > vw - 8) {
    left = Math.max(8, rect.right - cardRect.width);
  }
  if (left < 8) left = 8;

  let top = rect.bottom + gap;
  if (top + cardRect.height > vh - 8 && rect.top - gap - cardRect.height > 8) {
    top = rect.top - gap - cardRect.height;
  }

  cardStyle.value = {
    top: `${Math.round(top)}px`,
    left: `${Math.round(left)}px`,
  };
}

async function show() {
  if (props.expected == null) return;
  open();
  await nextTick();
  placeCard();
}

function hide() {
  close();
}

function onReposition() {
  if (isOpen.value) placeCard();
}

function bind() {
  window.addEventListener('resize', onReposition);
  window.addEventListener('scroll', onReposition, true);
}

function unbind() {
  window.removeEventListener('resize', onReposition);
  window.removeEventListener('scroll', onReposition, true);
}

watch(isOpen, (open_) => {
  unbind();
  if (open_) bind();
}, { flush: 'post' });

onBeforeUnmount(() => {
  unbind();
  close();
});
</script>

<template>
  <span
    ref="rootRef"
    class="dual-progress"
    :class="`dual-progress--${size}`"
    tabindex="0"
    role="img"
    :aria-label="expected != null
      ? `Tiến độ thực tế ${actual ?? 0}%, tiến độ dự kiến ${expected}%`
      : `Tiến độ thực tế ${actual ?? 0}%`"
    @mouseenter="show"
    @mouseleave="hide"
    @focus="show"
    @blur="hide"
  >
    <span class="dual-progress__track">
      <span
        v-if="expected != null"
        class="dual-progress__fill dual-progress__fill--expected"
        :style="{ width: `${clamp(expected)}%` }"
      />
      <span
        v-if="actual != null"
        class="dual-progress__fill dual-progress__fill--actual"
        :style="{ width: `${clamp(actual)}%` }"
      />
    </span>
    <span class="dual-progress__value">{{ actual != null ? `${actual}%` : '—' }}</span>

    <Teleport to="body">
      <div
        v-if="isOpen && expected != null"
        ref="cardRef"
        class="dual-progress__card"
        role="tooltip"
        :style="cardStyle"
      >
        <span class="dual-progress__card-row">
          <span class="dual-progress__dot dual-progress__dot--actual" />
          Tiến độ thực tế: {{ actual != null ? `${actual}%` : '—' }}
        </span>
        <span class="dual-progress__card-row">
          <span class="dual-progress__dot dual-progress__dot--expected" />
          Tiến độ dự kiến: {{ expected }}%
        </span>
      </div>
    </Teleport>
  </span>
</template>

<style scoped>
.dual-progress {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  outline: none;
}

.dual-progress:focus-visible .dual-progress__track {
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    0 0 0 2px color-mix(in srgb, var(--color-primary) 35%, transparent);
}

.dual-progress__track {
  position: relative;
  display: block;
  height: 0.375rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  overflow: hidden;
}

.dual-progress--sm .dual-progress__track {
  width: 3.75rem;
}

.dual-progress--md .dual-progress__track {
  flex: 1;
  min-width: 6rem;
  max-width: 8rem;
}

.dual-progress__fill {
  display: block;
  height: 100%;
  border-radius: var(--radius-full);
}

.dual-progress__fill--expected {
  position: absolute;
  inset: 0 auto 0 0;
  background: var(--color-gold-400);
}

.dual-progress__fill--actual {
  position: relative;
  background: var(--color-tertiary-500);
}

.dual-progress__value {
  flex-shrink: 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
}

.dual-progress__card {
  position: fixed;
  z-index: 80;
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  min-width: 12rem;
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
  color: var(--color-text);
  font-size: 0.8125rem;
  pointer-events: none;
}

.dual-progress__card-row {
  display: inline-flex;
  align-items: center;
  gap: 0.4375rem;
  white-space: nowrap;
}

.dual-progress__dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
}

.dual-progress__dot--actual {
  background: var(--color-tertiary-500);
}

.dual-progress__dot--expected {
  background: var(--color-gold-400);
}
</style>
