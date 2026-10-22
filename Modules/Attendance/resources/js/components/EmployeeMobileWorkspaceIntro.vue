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
