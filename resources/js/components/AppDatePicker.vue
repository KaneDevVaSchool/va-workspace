<script setup>
//
// Field chọn ngày dùng chung toàn app: input hiển thị dd/mm/yyyy, bấm vào
// mở popup lịch tháng bằng tiếng Việt (chọn nhanh tháng/năm, nút "Hôm nay").
// v-model nhận/phát chuỗi ISO yyyy-mm-dd (giống input type="date") để không
// phải đổi format khi submit lên backend.
//
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  min: { type: String, default: '' },
  max: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: 'dd/mm/yyyy' },
  id: { type: String, default: '' },
  error: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const WEEKDAYS = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
const MONTH_NAMES = [
  'Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6',
  'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12',
];

const root = ref(null);
const open = ref(false);

function parseIso(value) {
  if (!value) return null;
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
  if (!m) return null;
  const date = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
  return Number.isNaN(date.getTime()) ? null : date;
}

function toIso(date) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

function sameDay(a, b) {
  return !!a && !!b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
}

const selected = computed(() => parseIso(props.modelValue));
const minDate = computed(() => parseIso(props.min));
const maxDate = computed(() => parseIso(props.max));

const displayText = computed(() => {
  const d = selected.value;
  if (!d) return '';
  return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
});

// Tháng đang xem trong popup — mặc định là tháng của ngày đã chọn, hoặc tháng hiện tại.
const viewYear = ref(new Date().getFullYear());
const viewMonth = ref(new Date().getMonth());

function syncViewToSelected() {
  const base = selected.value || new Date();
  viewYear.value = base.getFullYear();
  viewMonth.value = base.getMonth();
}

const calendarCells = computed(() => {
  const firstOfMonth = new Date(viewYear.value, viewMonth.value, 1);
  // Thứ 2 = 0 ... Chủ nhật = 6
  const startOffset = (firstOfMonth.getDay() + 6) % 7;
  const gridStart = new Date(viewYear.value, viewMonth.value, 1 - startOffset);

  const today = new Date();
  const cells = [];
  for (let i = 0; i < 42; i += 1) {
    const date = new Date(gridStart.getFullYear(), gridStart.getMonth(), gridStart.getDate() + i);
    const disabled = (minDate.value && date < minDate.value) || (maxDate.value && date > maxDate.value);
    cells.push({
      date,
      iso: toIso(date),
      inMonth: date.getMonth() === viewMonth.value,
      isToday: sameDay(date, today),
      isSelected: sameDay(date, selected.value),
      disabled,
    });
  }
  return cells;
});

function toggleOpen() {
  if (props.disabled) return;
  if (!open.value) syncViewToSelected();
  open.value = !open.value;
}

function closePopup() {
  open.value = false;
}

function pick(cell) {
  if (cell.disabled) return;
  emit('update:modelValue', cell.iso);
  closePopup();
}

function goToday() {
  const today = new Date();
  viewYear.value = today.getFullYear();
  viewMonth.value = today.getMonth();
}

function clearValue() {
  emit('update:modelValue', '');
  closePopup();
}

function prevMonth() {
  if (viewMonth.value === 0) {
    viewMonth.value = 11;
    viewYear.value -= 1;
  } else {
    viewMonth.value -= 1;
  }
}

function nextMonth() {
  if (viewMonth.value === 11) {
    viewMonth.value = 0;
    viewYear.value += 1;
  } else {
    viewMonth.value += 1;
  }
}

const yearOptions = computed(() => {
  const base = new Date().getFullYear();
  const years = [];
  for (let y = base - 6; y <= base + 6; y += 1) years.push(y);
  if (!years.includes(viewYear.value)) years.unshift(viewYear.value);
  return years.sort((a, b) => a - b);
});

function onClickOutside(event) {
  if (root.value && !root.value.contains(event.target)) closePopup();
}

watch(open, (isOpen) => {
  if (isOpen) {
    nextTick(() => document.addEventListener('mousedown', onClickOutside));
  } else {
    document.removeEventListener('mousedown', onClickOutside);
  }
});

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onClickOutside);
});
</script>

<template>
  <div ref="root" class="app-date">
    <button
      :id="id"
      type="button"
      class="app-date__input"
      :class="{ 'app-date__input--error': error, 'app-date__input--empty': !displayText }"
      :disabled="disabled"
      @click="toggleOpen"
    >
      <AppIcon name="calendar" :size="15" :stroke-width="1.75" class="app-date__icon" />
      <span class="app-date__text">{{ displayText || placeholder }}</span>
    </button>

    <div v-if="open" class="app-date__popup" role="dialog" aria-label="Chọn ngày">
      <div class="app-date__nav">
        <button type="button" class="app-date__nav-btn" aria-label="Tháng trước" @click="prevMonth">
          <AppIcon name="chevronLeft" :size="15" :stroke-width="2" />
        </button>

        <div class="app-date__nav-selects">
          <select v-model.number="viewMonth" class="app-date__select" aria-label="Chọn tháng">
            <option v-for="(name, idx) in MONTH_NAMES" :key="idx" :value="idx">{{ name }}</option>
          </select>
          <select v-model.number="viewYear" class="app-date__select" aria-label="Chọn năm">
            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>

        <button type="button" class="app-date__nav-btn" aria-label="Tháng sau" @click="nextMonth">
          <AppIcon name="chevronRight" :size="15" :stroke-width="2" />
        </button>
      </div>

      <div class="app-date__weekdays">
        <span v-for="w in WEEKDAYS" :key="w">{{ w }}</span>
      </div>

      <div class="app-date__grid">
        <button
          v-for="cell in calendarCells"
          :key="cell.iso"
          type="button"
          class="app-date__cell"
          :class="{
            'app-date__cell--out': !cell.inMonth,
            'app-date__cell--today': cell.isToday,
            'app-date__cell--selected': cell.isSelected,
          }"
          :disabled="cell.disabled"
          @click="pick(cell)"
        >
          {{ cell.date.getDate() }}
        </button>
      </div>

      <div class="app-date__footer">
        <button type="button" class="app-date__link" @click="goToday">Hôm nay</button>
        <button v-if="modelValue" type="button" class="app-date__link app-date__link--muted" @click="clearValue">Bỏ chọn</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.app-date {
  position: relative;
  min-width: 0;
}

.app-date__input {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.12s ease, box-shadow 0.12s ease;
}

.app-date__input:hover:not(:disabled) {
  border-color: var(--color-border-strong);
}

.app-date__input:focus-visible {
  border-color: var(--color-primary-300);
  outline: 2px solid var(--color-primary-200);
  outline-offset: 1px;
}

.app-date__input--error {
  box-shadow: 0 0 0 1px var(--color-danger);
}

.app-date__input--empty .app-date__text {
  color: var(--color-text-muted);
}

.app-date__input:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.app-date__icon {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.app-date__text {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.app-date__popup {
  position: absolute;
  z-index: 20;
  top: calc(100% + 0.375rem);
  left: 0;
  width: 17.5rem;
  padding: var(--space-3);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-md, 0 8px 24px rgba(0, 0, 0, 0.12));
}

.app-date__nav {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin-bottom: var(--space-2);
}

.app-date__nav-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-md);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
  transition: background-color 0.12s ease, color 0.12s ease;
}

.app-date__nav-btn:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.app-date__nav-selects {
  display: flex;
  flex: 1;
  min-width: 0;
  gap: var(--space-1);
}

.app-date__select {
  flex: 1;
  min-width: 0;
  padding: 0.25rem 0.375rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
}

.app-date__weekdays {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  margin-bottom: 0.25rem;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
  text-align: center;
  text-transform: uppercase;
}

.app-date__grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 2px;
}

.app-date__cell {
  display: flex;
  align-items: center;
  justify-content: center;
  aspect-ratio: 1;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  cursor: pointer;
  transition: background-color 0.12s ease, color 0.12s ease;
}

.app-date__cell:hover:not(:disabled) {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.app-date__cell--out {
  color: var(--color-text-muted);
  opacity: 0.55;
}

.app-date__cell--today {
  box-shadow: inset 0 0 0 1px var(--color-primary-300);
  font-weight: 700;
}

.app-date__cell--selected {
  background: var(--color-primary);
  color: #fff;
  font-weight: 700;
}

.app-date__cell--selected:hover:not(:disabled) {
  background: var(--color-primary);
  color: #fff;
}

.app-date__cell:disabled {
  color: var(--color-text-muted);
  opacity: 0.35;
  cursor: not-allowed;
}

.app-date__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  margin-top: var(--space-2);
  padding-top: var(--space-2);
  box-shadow: 0 -1px 0 var(--color-border);
}

.app-date__link {
  padding: 0.25rem 0.5rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-primary);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
}

.app-date__link:hover {
  background: var(--color-primary-surface);
}

.app-date__link--muted {
  color: var(--color-text-muted);
}

.app-date__link--muted:hover {
  background: var(--color-surface-muted);
}

@media (max-width: 480px) {
  .app-date__popup {
    width: 16rem;
  }
}
</style>
