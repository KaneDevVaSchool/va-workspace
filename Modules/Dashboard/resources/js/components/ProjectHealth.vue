<script setup>
const props = defineProps({
  good: { type: Number, default: 0 },
  warning: { type: Number, default: 0 },
  risk: { type: Number, default: 0 },
  selected: { type: String, default: null },
});

const emit = defineEmits(['select']);

function toggle(key) {
  emit('select', props.selected === key ? null : key);
}
</script>

<template>
  <div class="project-health">
    <button
      type="button"
      class="project-health__item project-health__item--good"
      :class="{ 'project-health__item--active': selected === 'good' }"
      @click="toggle('good')"
    >
      <span class="project-health__dot" aria-hidden="true"></span>
      <span class="project-health__value">{{ good }}</span>
      <span class="project-health__label">Tốt</span>
    </button>
    <button
      type="button"
      class="project-health__item project-health__item--warning"
      :class="{ 'project-health__item--active': selected === 'warning' }"
      @click="toggle('warning')"
    >
      <span class="project-health__dot" aria-hidden="true"></span>
      <span class="project-health__value">{{ warning }}</span>
      <span class="project-health__label">Cần chú ý</span>
    </button>
    <button
      type="button"
      class="project-health__item project-health__item--risk"
      :class="{ 'project-health__item--active': selected === 'risk' }"
      @click="toggle('risk')"
    >
      <span class="project-health__dot" aria-hidden="true"></span>
      <span class="project-health__value">{{ risk }}</span>
      <span class="project-health__label">Nguy cơ</span>
    </button>
  </div>
</template>

<style scoped>
.project-health {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-3);
  align-content: start;
}

.project-health__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--space-1);
  padding: var(--space-4) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  cursor: pointer;
  font-family: inherit;
  transition: box-shadow 0.15s ease;
}

.project-health__item:hover {
  box-shadow: var(--shadow-sm);
}

.project-health__item--active {
  outline: 2px solid var(--color-primary);
  outline-offset: -2px;
}

.project-health__dot {
  width: 0.625rem;
  height: 0.625rem;
  border-radius: var(--radius-full);
}

.project-health__item--good .project-health__dot { background: var(--color-success); }
.project-health__item--warning .project-health__dot { background: var(--color-warning); }
.project-health__item--risk .project-health__dot { background: var(--color-danger); }

.project-health__value {
  font-size: 1.375rem;
  font-weight: 700;
  color: var(--color-text);
}

.project-health__label {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

@media (max-width: 480px) {
  .project-health {
    grid-template-columns: 1fr;
  }
}
</style>
