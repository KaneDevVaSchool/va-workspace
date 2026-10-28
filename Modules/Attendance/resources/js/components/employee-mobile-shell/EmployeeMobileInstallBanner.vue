<script setup>
//
// Gợi ý cài PWA vào máy — hiện trong shell mobile khi usePwaInstall()
// báo canInstall = true (Android/Chrome: có nút "Cài đặt") hoặc showIosGuide = true
// (iPhone/iPad: không có hộp thoại cài tự động nên chỉ hướng dẫn bằng chữ).
// CSS: resources/css/employee-mobile-shell/base.css (.employee-shell__install, .install-banner*).
//
import AppIcon from '@/components/AppIcon.vue';

defineProps({
  /** Hướng dẫn thủ công cho iPhone/iPad thay cho nút "Cài đặt". */
  ios: { type: Boolean, default: false },
});

defineEmits(['install', 'dismiss']);
</script>

<template>
  <div class="employee-shell__install">
    <div class="install-banner">
      <span class="install-banner__icon" aria-hidden="true">
        <AppIcon name="download" :size="18" :stroke-width="1.9" />
      </span>
      <span class="install-banner__text">
        <span class="install-banner__title">{{ ios ? 'Thêm vào Màn hình chính' : 'Cài ứng dụng vào máy' }}</span>
        <span class="install-banner__hint" :class="{ 'install-banner__hint--wrap': ios }">
          {{ ios
            ? 'Bấm nút Chia sẻ của Safari, rồi chọn “Thêm vào Màn hình chính”'
            : 'Mở nhanh hơn, dùng như một app riêng' }}
        </span>
      </span>
      <button v-if="!ios" type="button" class="install-banner__action" @click="$emit('install')">Cài đặt</button>
      <button type="button" class="install-banner__close" aria-label="Đóng gợi ý cài đặt" @click="$emit('dismiss')">
        <AppIcon name="close" :size="14" />
      </button>
    </div>
  </div>
</template>
