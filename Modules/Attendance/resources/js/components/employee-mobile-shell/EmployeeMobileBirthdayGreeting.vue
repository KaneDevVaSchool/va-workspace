<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { EMPLOYEE_BIRTHDAY_GREETINGS } from '../../constants/employeeBirthdayGreetings.js';
import {
  markBirthdayGreetingDismissedToday,
  pickBirthdayGreetingVariantIndex,
} from '../../lib/employeeBirthdayGreeting.js';

const SPARK_COUNT = 12;
const LEAVE_MS = 320;
const BURST_COLORS = ['#ff4d6d', '#ffd166', '#ff8fab', '#4cc9f0', '#ffe66d', '#f72585', '#ffb703'];

const bursts = [
  { x: '8%', y: '12%' },
  { x: '86%', y: '10%' },
  { x: '16%', y: '38%' },
  { x: '88%', y: '42%' },
  { x: '22%', y: '78%' },
  { x: '78%', y: '74%' },
  { x: '50%', y: '6%' },
].map((spot, id) => ({
  id,
  style: {
    '--x': spot.x,
    '--y': spot.y,
    '--delay': `${(id * 0.32) % 1.8}s`,
    '--c': BURST_COLORS[id % BURST_COLORS.length],
  },
}));

const props = defineProps({
  people: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const leaving = ref(false);
let leaveTimer = 0;

const variantIndex = pickBirthdayGreetingVariantIndex(EMPLOYEE_BIRTHDAY_GREETINGS.length);

const greeting = computed(() => EMPLOYEE_BIRTHDAY_GREETINGS[variantIndex] ?? EMPLOYEE_BIRTHDAY_GREETINGS[0]);

const honorees = computed(() =>
  props.people.filter((person) => String(person?.name || '').trim() !== ''),
);

function prefersReducedMotion() {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function dismiss() {
  if (leaving.value) return;
  markBirthdayGreetingDismissedToday();
  if (prefersReducedMotion()) {
    emit('close');
    return;
  }
  leaving.value = true;
  leaveTimer = window.setTimeout(() => emit('close'), LEAVE_MS);
}

function onBackdropClick(event) {
  if (event.target === event.currentTarget) {
    dismiss();
  }
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    dismiss();
  }
}

onMounted(() => {
  document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown);
  window.clearTimeout(leaveTimer);
});
</script>

<template>
  <Teleport to="body">
    <div
      class="birthday-greeting"
      :class="{ 'birthday-greeting--leave': leaving }"
      role="dialog"
      aria-modal="true"
      aria-labelledby="birthday-greeting-title"
      @click="onBackdropClick"
    >
      <div class="birthday-greeting__fireworks" aria-hidden="true">
        <span
          v-for="burst in bursts"
          :key="burst.id"
          class="birthday-greeting__burst"
          :style="burst.style"
        >
          <i
            v-for="spark in SPARK_COUNT"
            :key="spark"
            class="birthday-greeting__spark"
            :style="{ '--i': spark }"
          />
        </span>
      </div>
      <div class="birthday-greeting__panel">
        <div class="birthday-greeting__art-wrap">
          <img
            class="birthday-greeting__art"
            :src="greeting.imageSrc"
            :alt="greeting.title"
            decoding="async"
          />
        </div>
        <div class="birthday-greeting__body">
          <h2 id="birthday-greeting-title" class="birthday-greeting__to">
            Hôm nay sinh nhật
          </h2>
          <ul class="birthday-greeting__people">
            <li
              v-for="person in honorees"
              :key="person.hrm_employee_uuid || person.name"
              class="birthday-greeting__person"
            >
              <span class="birthday-greeting__name">{{ person.name }}</span>
              <span v-if="person.department_name" class="birthday-greeting__dept">
                {{ person.department_name }}
              </span>
            </li>
          </ul>
        </div>
        <div class="birthday-greeting__actions">
          <button type="button" class="birthday-greeting__cta" @click="dismiss">
            Chúc mừng!
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
