<script setup>
import { computed } from 'vue';
import { toneOfStatusCode } from '../constants/statusTone.js';

const props = defineProps({
  code: { type: String, default: '' },
  label: { type: String, default: '' },
  compact: { type: Boolean, default: false },
  tableChip: { type: Boolean, default: false },
  showCode: { type: Boolean, default: false },
});

const tone = computed(() => toneOfStatusCode(props.code));
</script>

<template>
  <span
    class="dt-status-badge"
    :class="[
      `dt-status-badge--${tone}`,
      { 'dt-status-badge--compact': compact, 'dt-status-badge--table-chip': tableChip },
    ]"
  >
    <span v-if="showCode && code" class="dt-status-badge__code">{{ code }}</span>
    <span class="dt-status-badge__label">{{ label || code }}</span>
  </span>
</template>

<style scoped>
.dt-status-badge {
  display: inline-flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.125rem;
  padding: 0.35rem 0.65rem;
  border-radius: var(--radius-sm);
  font-size: 0.8125rem;
  line-height: 1.25;
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--badge-fg) 22%, transparent);
  background: color-mix(in srgb, var(--badge-fg) 10%, var(--color-surface));
  color: var(--color-text);
  --badge-fg: var(--color-info);
}

.dt-status-badge--compact {
  flex-direction: row;
  align-items: center;
  gap: var(--space-2);
}

.dt-status-badge--table-chip {
  justify-content: center;
  box-sizing: border-box;
  min-width: 9.5rem;
  width: 100%;
  max-width: 14rem;
  padding: 0.45rem 0.75rem;
  text-align: center;
}

.dt-status-badge--table-chip .dt-status-badge__label {
  line-height: 1.35;
}

.dt-status-badge__code {
  font-size: 0.625rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--badge-fg);
}

.dt-status-badge__label {
  font-weight: 500;
}

.dt-status-badge--muted {
  --badge-fg: var(--color-text-muted);
}
.dt-status-badge--info {
  --badge-fg: var(--color-info);
}
.dt-status-badge--warning {
  --badge-fg: #c27803;
}
.dt-status-badge--purple {
  --badge-fg: #7c3aed;
}
.dt-status-badge--accent {
  --badge-fg: #0d9488;
}
.dt-status-badge--primary {
  --badge-fg: var(--color-primary);
}
.dt-status-badge--success {
  --badge-fg: var(--color-success);
}
.dt-status-badge--danger {
  --badge-fg: var(--color-danger);
}
</style>
