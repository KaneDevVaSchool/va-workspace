<script setup>
//
// Màn giới thiệu VA Workspace — lần đầu (và xem lại) trên shell mobile / PWA.
//
import AppIcon from '@/components/AppIcon.vue';

const MASCOT_SRC = '/images/congnghe/brand/vas-mascot-wave.png';

const HIGHLIGHTS = [
  { icon: 'home', label: 'Trang chủ & tiện ích nhanh' },
  { icon: 'clock', label: 'Chấm công mọi lúc' },
  { icon: 'megaphone', label: 'Bảng tin nội bộ' },
  { icon: 'calendar', label: 'Nghỉ phép & lịch sử' },
];

const emit = defineEmits(['start']);

function onStart() {
  emit('start');
}
</script>

<template>
  <div class="ws-intro" role="dialog" aria-modal="true" aria-labelledby="ws-intro-title">
    <div class="ws-intro__panel">
      <p class="ws-intro__kicker">VA Workspace</p>
      <h2 id="ws-intro-title" class="ws-intro__title">Chào mừng bạn!</h2>
      <p class="ws-intro__lead">
        Ứng dụng làm việc trên điện thoại — gọn trong túi, dùng được cả khi cài như app (PWA).
      </p>

      <ul class="ws-intro__highlights">
        <li v-for="item in HIGHLIGHTS" :key="item.label">
          <span class="ws-intro__highlight-icon" aria-hidden="true">
            <AppIcon :name="item.icon" :size="18" :stroke-width="1.85" />
          </span>
          <span>{{ item.label }}</span>
        </li>
      </ul>

      <div class="ws-intro__mascot-wrap">
        <img
          class="ws-intro__mascot"
          :src="MASCOT_SRC"
          width="280"
          height="280"
          alt="Kỳ lân VA chào mừng bạn đến Workspace"
        />
      </div>

      <button type="button" class="ws-intro__cta" @click="onStart">
        Bắt đầu khám phá
        <AppIcon name="chevronRight" :size="18" />
      </button>
    </div>
  </div>
</template>

<style scoped>
.ws-intro {
  position: fixed;
  inset: 0;
  z-index: 120;
  display: flex;
  flex-direction: column;
  padding:
    max(var(--space-3), var(--employee-safe-top, env(safe-area-inset-top, 0px)))
    max(var(--space-3), var(--employee-safe-right, env(safe-area-inset-right, 0px)))
    max(var(--space-3), var(--employee-safe-bottom, env(safe-area-inset-bottom, 0px)))
    max(var(--space-3), var(--employee-safe-left, env(safe-area-inset-left, 0px)));
  background: linear-gradient(
    180deg,
    color-mix(in srgb, var(--color-tertiary-200) 72%, white) 0%,
    color-mix(in srgb, var(--color-tertiary-50) 90%, white) 46%,
    color-mix(in srgb, var(--color-tertiary-50) 35%, white) 100%
  );
  color: var(--color-text);
}

.ws-intro__panel {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  max-width: 26rem;
  width: 100%;
  margin: 0 auto;
}

.ws-intro__kicker {
  margin: 0 0 var(--space-2);
  color: var(--color-tertiary-600);
  font-size: 0.8125rem;
  font-weight: 700;
}

.ws-intro__title {
  margin: 0;
  color: var(--color-tertiary-900);
  font-size: clamp(1.65rem, 5vw, 2rem);
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 1.15;
}

.ws-intro__lead {
  margin: var(--space-2) 0 0;
  color: var(--color-text-muted);
  font-size: 0.92rem;
  line-height: 1.45;
  max-width: 22rem;
}

.ws-intro__highlights {
  list-style: none;
  margin: var(--space-4) 0 0;
  padding: 0;
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  text-align: left;
}

.ws-intro__highlights li {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: 0 8px 22px color-mix(in srgb, var(--color-tertiary-900) 7%, transparent);
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-tertiary-800);
}

.ws-intro__highlight-icon {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-tertiary-100) 85%, white);
  color: var(--color-tertiary-700);
}

.ws-intro__mascot-wrap {
  flex: 1;
  min-height: 0;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  width: 100%;
  margin-top: var(--space-3);
  pointer-events: none;
}

.ws-intro__mascot {
  width: min(72vw, 17.5rem);
  height: auto;
  object-fit: contain;
  filter: drop-shadow(0 12px 28px color-mix(in srgb, var(--color-tertiary-900) 12%, transparent));
}

.ws-intro__cta {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  width: 100%;
  margin-top: var(--space-3);
  padding: var(--space-3) var(--space-4);
  border: 0;
  border-radius: var(--radius-full);
  background: var(--color-tertiary-600);
  color: #fff;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 10px 28px color-mix(in srgb, var(--color-tertiary-700) 35%, transparent);
}

.ws-intro__cta:focus-visible {
  outline: 2px solid var(--color-tertiary-500);
  outline-offset: 2px;
}
</style>
