<script setup>
defineProps({
  name: { type: String, required: true },
  size: { type: [Number, String], default: 22 },
});
</script>

<template>
  <svg
    class="att-glyph"
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    aria-hidden="true"
  >
    <template v-if="name === 'fingerprint'">
      <path d="M4.2 9.4a8 8 0 0 1 15.4 2.4c0 2.5-.45 4.8-1.1 6.6" />
      <path d="M6.4 12.2c.35 3.5 1.35 6.2 2.5 8" />
      <path d="M7.1 10.4a5 5 0 0 1 9.8 0c0 3.6-1 6.4-1.9 8.4" />
      <path d="M9.3 10.5a2.8 2.8 0 0 1 5.4.2c0 2.3-.55 4.3-1.1 5.8" />
      <path d="M12 10.4v3.4" />
    </template>

    <template v-else-if="name === 'wifi'">
      <path d="M5 12.4a10 10 0 0 1 14 0" />
      <path d="M8.1 15.3a5.6 5.6 0 0 1 7.8 0" />
      <path d="M12 18.6h.01" />
    </template>

    <template v-else-if="name === 'qr'">
      <rect x="3.2" y="3.2" width="7" height="7" rx="1.3" />
      <rect x="13.8" y="3.2" width="7" height="7" rx="1.3" />
      <rect x="3.2" y="13.8" width="7" height="7" rx="1.3" />
      <path class="is-fill" d="M6.1 6.1h1.5v1.5H6.1zM16.6 6.1h1.5v1.5h-1.5zM6.1 16.6h1.5v1.5H6.1z" />
      <path d="M14 14h2.6v2.6H14zM18.4 14H21M14 18.4h2.2V21M18.4 18.4H21V21" />
    </template>

    <template v-else-if="name === 'keyboard'">
      <rect x="3" y="5" width="18" height="14" rx="2" />
      <path d="M7 9h.01M12 9h.01M17 9h.01M7 13h.01M12 13h.01M17 13h.01M8.5 16.5h7" />
    </template>

    <template v-else-if="name === 'camera'">
      <path d="M4 8.5h3l1.4-2h7.2L17 8.5h3a1.5 1.5 0 0 1 1.5 1.5v8A1.5 1.5 0 0 1 20 19.5H4A1.5 1.5 0 0 1 2.5 18V10A1.5 1.5 0 0 1 4 8.5z" />
      <circle cx="12" cy="13.5" r="3.1" />
    </template>

    <template v-else-if="name === 'pin'">
      <path d="M12 21.2s6.4-5.7 6.4-10.2a6.4 6.4 0 1 0-12.8 0c0 4.5 6.4 10.2 6.4 10.2z" />
      <circle cx="12" cy="11" r="2.1" />
    </template>

    <template v-else-if="name === 'grid'">
      <rect x="4" y="4" width="6.2" height="6.2" rx="1.4" />
      <rect x="13.8" y="4" width="6.2" height="6.2" rx="1.4" />
      <rect x="4" y="13.8" width="6.2" height="6.2" rx="1.4" />
      <rect x="13.8" y="13.8" width="6.2" height="6.2" rx="1.4" />
    </template>

    <template v-else-if="name === 'sun'">
      <circle cx="12" cy="12" r="3.6" />
      <path d="M12 2.6v2.1M12 19.3v2.1M2.6 12h2.1M19.3 12h2.1M5 5l1.5 1.5M17.5 17.5 19 19M19 5l-1.5 1.5M6.5 17.5 5 19" />
    </template>

    <template v-else-if="name === 'cloud'">
      <path d="M7.2 17.5h9.2a3.6 3.6 0 0 0 .4-7.2 4.8 4.8 0 0 0-9.2-1.2A3.3 3.3 0 0 0 7.2 17.5z" />
    </template>

    <template v-else-if="name === 'rain'">
      <path d="M7 14.5h9a3.3 3.3 0 0 0 .3-6.6 4.4 4.4 0 0 0-8.4-1.1A3 3 0 0 0 7 14.5z" />
      <path d="M8.5 17.2 7.6 20M12 17.2l-.9 2.8M15.5 17.2 14.6 20" />
    </template>

    <template v-else-if="name === 'signal'">
      <path d="M5 16.5v2.2M9.2 13.2v5.5M13.4 9.6v9.1M17.6 6v12.7" />
    </template>

    <template v-else-if="name === 'check'">
      <circle cx="12" cy="12" r="8.2" />
      <path d="M8.2 12.2 10.8 14.8 15.8 9.4" />
    </template>
  </svg>
</template>

<style scoped>
.att-glyph {
  display: block;
  flex-shrink: 0;
}

.att-glyph :deep(path),
.att-glyph :deep(rect),
.att-glyph :deep(circle) {
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.att-glyph :deep(circle[r='2.1']),
.att-glyph :deep(.is-fill) {
  fill: currentColor;
  stroke: none;
}
</style>
