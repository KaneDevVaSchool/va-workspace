<script setup>
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import AppIcon from './AppIcon.vue';

const props = defineProps({
  guide: { type: Object, required: true },
  tone: { type: String, default: 'primary' },
});

const open = ref(false);
const panel = ref(null);
const trigger = ref(null);

const titleId = useId();
const ariaLabel = computed(() => `Hướng dẫn: ${props.guide?.title || 'chi tiết'}`);

function toggle() {
  open.value = !open.value;
}

function close() {
  open.value = false;
}

function onKeydown(event) {
  if (event.key === 'Escape' && open.value) {
    event.preventDefault();
    close();
    trigger.value?.focus();
  }
}

function onDocPointer(event) {
  if (!open.value) return;
  const target = event.target;
  if (panel.value?.contains(target) || trigger.value?.contains(target)) return;
  close();
}

watch(open, async (isOpen) => {
  if (isOpen) {
    await nextTick();
    document.addEventListener('keydown', onKeydown);
    document.addEventListener('mousedown', onDocPointer);
    panel.value?.querySelector('.guide-help__close')?.focus();
    return;
  }
  document.removeEventListener('keydown', onKeydown);
  document.removeEventListener('mousedown', onDocPointer);
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown);
  document.removeEventListener('mousedown', onDocPointer);
});
</script>

<template>
  <span class="guide-help">
    <button
      ref="trigger"
      type="button"
      class="guide-help__btn"
      :class="`guide-help__btn--${tone}`"
      :aria-label="ariaLabel"
      :aria-expanded="open ? 'true' : 'false'"
      aria-haspopup="dialog"
      @click.stop="toggle"
    >
      <span aria-hidden="true">?</span>
    </button>

    <Teleport to="body">
      <div v-if="open" class="guide-help__overlay" @mousedown.self="close">
        <article
          ref="panel"
          class="guide-help__panel"
          :class="`guide-help__panel--${tone}`"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="titleId"
        >
          <header class="guide-help__head">
            <span class="guide-help__mark" aria-hidden="true">?</span>
            <div class="guide-help__head-copy">
              <p v-if="guide.kicker" class="guide-help__kicker">{{ guide.kicker }}</p>
              <h2 :id="titleId" class="guide-help__title">{{ guide.title }}</h2>
            </div>
            <button type="button" class="guide-help__close" aria-label="Đóng hướng dẫn" @click="close">
              <AppIcon name="close" :size="16" :stroke-width="1.75" />
            </button>
          </header>

          <div class="guide-help__body hide-scrollbar">
            <p class="guide-help__summary">{{ guide.summary }}</p>

            <ol v-if="guide.steps?.length" class="guide-help__steps">
              <li v-for="(step, index) in guide.steps" :key="step.title" class="guide-help__step">
                <span class="guide-help__num" aria-hidden="true">{{ index + 1 }}</span>
                <div class="guide-help__step-copy">
                  <h3 class="guide-help__step-title">{{ step.title }}</h3>
                  <p class="guide-help__step-body">{{ step.body }}</p>
                </div>
              </li>
            </ol>

            <section v-if="guide.example" class="guide-help__example">
              <h3 class="guide-help__example-title">{{ guide.example.title }}</h3>
              <p class="guide-help__example-body">{{ guide.example.body }}</p>
            </section>

            <ul v-if="guide.notes?.length" class="guide-help__notes">
              <li v-for="note in guide.notes" :key="note">{{ note }}</li>
            </ul>
          </div>
        </article>
      </div>
    </Teleport>
  </span>
</template>

<style scoped>
.guide-help {
  display: inline-flex;
  align-items: center;
  vertical-align: middle;
  margin-left: 0.3rem;
}

.guide-help__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.35rem;
  height: 1.35rem;
  padding: 0;
  border: none;
  border-radius: 999px;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-family: var(--font-family-base);
  font-size: 0.8rem;
  font-weight: 700;
  line-height: 1;
  cursor: pointer;
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 18%, transparent);
}

.guide-help__btn:hover,
.guide-help__btn:focus-visible {
  background: var(--color-primary);
  color: var(--color-on-primary);
  outline: none;
}

.guide-help__btn--secondary {
  background: var(--color-secondary-surface);
  color: var(--color-secondary);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-secondary) 18%, transparent);
}

.guide-help__btn--secondary:hover,
.guide-help__btn--secondary:focus-visible {
  background: var(--color-secondary);
  color: var(--color-on-secondary);
}

.guide-help__btn--gold {
  background: var(--color-gold-50);
  color: var(--color-gold);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-gold) 22%, transparent);
}

.guide-help__btn--gold:hover,
.guide-help__btn--gold:focus-visible {
  background: var(--color-gold);
  color: #fff;
}

.guide-help__btn--info {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-tertiary) 18%, transparent);
}

.guide-help__btn--info:hover,
.guide-help__btn--info:focus-visible {
  background: var(--color-tertiary);
  color: var(--color-on-tertiary);
}

.guide-help__overlay {
  position: fixed;
  inset: 0;
  z-index: 450;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.guide-help__panel {
  position: relative;
  display: flex;
  flex-direction: column;
  width: min(42rem, calc(100vw - 2.5rem));
  max-height: calc(100vh - 2.5rem);
  overflow: hidden;
  padding-left: calc(var(--space-2) + 3px);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.guide-help__panel::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-primary);
}

.guide-help__panel--secondary::before { background: var(--color-secondary); }
.guide-help__panel--gold::before { background: var(--color-gold); }
.guide-help__panel--info::before { background: var(--color-tertiary); }

.guide-help__head {
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  flex-shrink: 0;
  padding: var(--space-5) var(--space-5) var(--space-4);
}

.guide-help__mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 999px;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 1.125rem;
  font-weight: 700;
}

.guide-help__panel--secondary .guide-help__mark {
  background: var(--color-secondary-surface);
  color: var(--color-secondary);
}

.guide-help__panel--gold .guide-help__mark {
  background: var(--color-gold-50);
  color: var(--color-gold);
}

.guide-help__panel--info .guide-help__mark {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.guide-help__head-copy {
  min-width: 0;
  flex: 1;
}

.guide-help__kicker {
  margin: 0 0 0.2rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.guide-help__title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.125rem;
  font-weight: 650;
  line-height: 1.3;
}

.guide-help__close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 2rem;
  height: 2rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-md);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.guide-help__close:hover,
.guide-help__close:focus-visible {
  background: var(--color-surface-muted);
  color: var(--color-text);
  outline: none;
}

.guide-help__body {
  flex: 1;
  min-height: 0;
  padding: 0 var(--space-5) var(--space-5);
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.guide-help__summary {
  margin: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  line-height: 1.55;
}

.guide-help__steps {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.guide-help__step {
  position: relative;
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: var(--space-3);
  padding: var(--space-3);
  padding-left: calc(var(--space-2) + 3px + var(--space-3));
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: var(--shadow-sm);
}

.guide-help__step::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.guide-help__num {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 999px;
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-size: 0.75rem;
  font-weight: 700;
}

.guide-help__step-title {
  margin: 0 0 0.2rem;
  font-size: 0.9375rem;
  font-weight: 650;
}

.guide-help__step-body {
  margin: 0;
  color: var(--color-text);
  font-size: 0.875rem;
  line-height: 1.5;
}

.guide-help__example {
  position: relative;
  padding: var(--space-4);
  padding-left: calc(var(--space-2) + 3px + var(--space-4));
  border-radius: var(--radius-md);
  background: var(--color-gold-50);
  box-shadow: var(--shadow-sm);
}

.guide-help__example::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-gold);
}

.guide-help__example-title,
.guide-help__example-body {
  margin: 0;
}

.guide-help__example-title {
  margin-bottom: 0.35rem;
  font-size: 0.8125rem;
  font-weight: 650;
}

.guide-help__example-body {
  font-size: 0.875rem;
  line-height: 1.5;
}

.guide-help__notes {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.guide-help__notes li {
  position: relative;
  padding-left: 1rem;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.45;
}

.guide-help__notes li::before {
  content: '';
  position: absolute;
  top: 0.45rem;
  left: 0;
  width: 0.35rem;
  height: 0.35rem;
  border-radius: 999px;
  background: var(--color-secondary);
}

@media (max-width: 640px) {
  .guide-help__overlay {
    padding: var(--space-3);
    align-items: flex-end;
  }

  .guide-help__panel {
    width: calc(100vw - 1.5rem);
    max-height: calc(100vh - 1.5rem);
  }
}
</style>
