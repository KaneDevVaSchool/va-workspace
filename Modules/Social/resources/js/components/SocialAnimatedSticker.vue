<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { mountStickerAnimation } from '../lib/socialStickers.js';

const props = defineProps({
  id: { type: String, required: true },
  emoji: { type: String, default: '' },
  hoverPlay: { type: Boolean, default: false },
});

const el = ref(null);
let stop = null;

onMounted(() => {
  stop = mountStickerAnimation(el.value, props.id, { hoverPlay: props.hoverPlay });
});

onBeforeUnmount(() => stop?.());
</script>

<template>
  <span ref="el" class="social-sticker social-sticker--picker" :data-sticker="id">{{ emoji }}</span>
</template>
