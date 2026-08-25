<script setup>
import { NodeViewWrapper } from '@tiptap/vue-3';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { mountStickerAnimation } from '../lib/socialStickers.js';

const props = defineProps({
  node: { type: Object, required: true },
  selected: { type: Boolean, default: false },
});

const host = ref(null);
let stop = null;

function bind() {
  stop?.();
  stop = mountStickerAnimation(host.value, props.node.attrs.id);
}

onMounted(bind);
watch(() => props.node.attrs.id, bind);
onBeforeUnmount(() => stop?.());
</script>

<template>
  <NodeViewWrapper
    as="span"
    class="social-sticker"
    :class="{ 'social-sticker--selected': selected }"
    :data-sticker="node.attrs.id"
  >
    <span ref="host" class="social-sticker__host">{{ node.attrs.emoji }}</span>
  </NodeViewWrapper>
</template>
