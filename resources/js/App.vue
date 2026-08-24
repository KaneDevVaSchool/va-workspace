<script setup>
//
// Root component. Layout tổng dùng flex + overflow nội bộ để tránh
// scroll toàn trang (quy tắc responsive + hạn chế scroll).
//
</script>

<template>
  <div class="app-shell">
    <div class="app-shell__content">
      <router-view />
    </div>
  </div>
</template>
