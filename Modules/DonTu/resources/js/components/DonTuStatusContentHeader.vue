<script setup>
import AppIcon from '@/components/AppIcon.vue';

defineProps({
  setCount: { type: Number, default: 0 },
  statusCount: { type: Number, default: 0 },
  loading: { type: Boolean, default: false },
});
</script>

<template>
  <header class="dt-status-content-header dt-config-hub-theme" aria-labelledby="dt-status-content-title">
    <svg class="dt-config-wm-defs" aria-hidden="true" focusable="false">
      <filter id="dt-status-content-wm-boost" color-interpolation-filters="sRGB">
        <feColorMatrix type="matrix" values="0 0 0 0 0.604  0 0 0 0 0  0 0 0 0 0.212  0 0 0 20 0" />
      </filter>
    </svg>
    <img
      src="/images/background/background-logo.png"
      alt=""
      class="dt-config-watermark dt-status-content-header__wm"
      aria-hidden="true"
      :style="{ filter: 'url(#dt-status-content-wm-boost)' }"
    />

    <div class="dt-status-content-header__main">
      <span class="dt-status-content-header__icon" aria-hidden="true">
        <AppIcon name="layers" :size="22" :stroke-width="1.75" />
      </span>
      <div class="dt-status-content-header__copy">
        <p class="dt-status-content-header__eyebrow">Cấu hình đơn từ</p>
        <h2 id="dt-status-content-title" class="dt-status-content-header__title">Trạng thái và bộ trạng thái</h2>
        <p class="dt-status-content-header__lead">
          Khai báo bộ trạng thái và từng bước hồ sơ đi qua trước khi vận hành đơn.
        </p>
      </div>
    </div>

    <dl class="dt-status-content-header__stats" aria-label="Thống kê nhanh">
      <div class="dt-status-content-header__stat">
        <dt>Bộ trạng thái</dt>
        <dd>{{ loading ? '…' : setCount }}</dd>
      </div>
      <div class="dt-status-content-header__stat">
        <dt>Trạng thái (tất cả bộ)</dt>
        <dd>{{ loading ? '…' : statusCount }}</dd>
      </div>
    </dl>
  </header>
</template>

<style scoped>
@import '../pages/admin/donTuAdminShared.css';
</style>
