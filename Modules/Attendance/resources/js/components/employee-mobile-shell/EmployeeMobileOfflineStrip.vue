<script setup>
//
// Thanh báo trạng thái kết nối trong shell mobile — hiện khi máy mất mạng, hoặc khi
// đang dùng bản lưu cũ của tài khoản vì máy chủ chưa trả lời (xem auth.offlineSnapshot).
// Chấm màu + chữ thường, không dùng badge. CSS: resources/css/employee-mobile-shell/base.css
// (.employee-shell__install, .offline-strip*).
//
import { computed } from 'vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { useOnlineStatus } from '@/composables/useOnlineStatus';

const auth = useAuthStore();
const { online } = useOnlineStatus();

const visible = computed(() => !online.value || auth.offlineSnapshot);

const message = computed(() => (online.value
  ? 'Chưa kết nối được máy chủ. Bạn đang xem dữ liệu đã lưu.'
  : 'Đang không có mạng. Bạn đang xem dữ liệu đã lưu.'));
</script>

<template>
  <div v-if="visible" class="employee-shell__install">
    <p class="offline-strip" role="status">
      <span class="offline-strip__dot" aria-hidden="true" />
      <span class="offline-strip__text">{{ message }}</span>
    </p>
  </div>
</template>
