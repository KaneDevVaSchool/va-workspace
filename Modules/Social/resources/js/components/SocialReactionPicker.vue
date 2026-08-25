<script setup>
import { nextTick, onBeforeUnmount, ref } from 'vue';
import { REACTIONS } from '../constants/reactions.js';

const props = defineProps({
  myReaction: { type: String, default: null },
  compact: { type: Boolean, default: false },
});

const emit = defineEmits(['pick']);

const open = ref(false);
const placement = ref('above');
const root = ref(null);
const popup = ref(null);
const popupStyle = ref({});

let openTimer = null;
let closeTimer = null;
let suppressClick = false;
let pageBound = false;

function clearTimers() {
  clearTimeout(openTimer);
  clearTimeout(closeTimer);
  openTimer = null;
  closeTimer = null;
}

function placePopup() {
  const el = root.value;
  if (!el) return;

  const rect = el.getBoundingClientRect();
  const gap = 10;
  const estimatedWidth = 360;
  const maxLeft = window.innerWidth - estimatedWidth - 8;
  const left = Math.max(8, Math.min(rect.left, maxLeft));

  if (rect.top < 72) {
    placement.value = 'below';
    popupStyle.value = {
      top: `${rect.bottom + gap}px`,
      bottom: 'auto',
      left: `${left}px`,
    };
    return;
  }

  placement.value = 'above';
  popupStyle.value = {
    top: 'auto',
    bottom: `${window.innerHeight - rect.top + gap}px`,
    left: `${left}px`,
  };
}

function bindPageListeners() {
  if (pageBound) return;
  pageBound = true;
  document.addEventListener('pointerdown', onDocumentPointerDown, true);
  document.addEventListener('keydown', onKeydown);
  window.addEventListener('scroll', onReposition, true);
  window.addEventListener('resize', onReposition);
}

function unbindPageListeners() {
  if (!pageBound) return;
  pageBound = false;
  document.removeEventListener('pointerdown', onDocumentPointerDown, true);
  document.removeEventListener('keydown', onKeydown);
  window.removeEventListener('scroll', onReposition, true);
  window.removeEventListener('resize', onReposition);
}

function showPicker() {
  clearTimers();
  open.value = true;
  nextTick(() => {
    placePopup();
    bindPageListeners();
  });
}

function hidePicker() {
  clearTimers();
  open.value = false;
  unbindPageListeners();
}

function scheduleOpen() {
  clearTimeout(closeTimer);
  closeTimer = null;
  if (open.value) {
    placePopup();
    return;
  }
  clearTimeout(openTimer);
  openTimer = setTimeout(showPicker, 180);
}

function scheduleClose() {
  clearTimeout(openTimer);
  openTimer = null;
  clearTimeout(closeTimer);
  closeTimer = setTimeout(hidePicker, 320);
}

function pick(type, event) {
  event.preventDefault();
  event.stopPropagation();
  emit('pick', type, event);

  const swallowClick = (nextEvent) => {
    nextEvent.preventDefault();
    nextEvent.stopPropagation();
    hidePicker();
  };
  window.addEventListener('click', swallowClick, { once: true, capture: true });
  setTimeout(hidePicker, 160);
}

function onTriggerClick(event) {
  if (!suppressClick) return;
  event.preventDefault();
  event.stopPropagation();
  suppressClick = false;
}

function onTouchStart() {
  clearTimeout(openTimer);
  openTimer = setTimeout(() => {
    suppressClick = true;
    showPicker();
  }, 380);
}

function onTouchEnd() {
  clearTimeout(openTimer);
  openTimer = null;
}

function onContextMenu(event) {
  event.preventDefault();
  suppressClick = true;
  showPicker();
}

function onDocumentPointerDown(event) {
  const target = event.target;
  if (!(target instanceof Node)) return;
  if (root.value?.contains(target) || popup.value?.contains(target)) return;
  hidePicker();
}

function onKeydown(event) {
  if (event.key === 'Escape') hidePicker();
}

function onReposition() {
  if (open.value) placePopup();
}

onBeforeUnmount(() => {
  clearTimers();
  unbindPageListeners();
});
</script>

<template>
  <div
    ref="root"
    class="reaction-picker"
    :class="{ 'reaction-picker--compact': props.compact }"
    @mouseenter="scheduleOpen"
    @mouseleave="scheduleClose"
    @click.capture="onTriggerClick"
    @touchstart.passive="onTouchStart"
    @touchend="onTouchEnd"
    @touchcancel="onTouchEnd"
    @contextmenu="onContextMenu"
  >
    <Teleport to="body">
      <Transition name="reaction-popup">
        <div
          v-if="open"
          ref="popup"
          class="reaction-picker__popup"
          :class="{ 'reaction-picker__popup--below': placement === 'below' }"
          :style="popupStyle"
          role="menu"
          aria-label="Chọn cảm xúc"
          @mouseenter="scheduleOpen"
          @mouseleave="scheduleClose"
        >
          <button
            v-for="(reaction, index) in REACTIONS"
            :key="reaction.type"
            type="button"
            class="reaction-picker__option"
            :class="{ 'reaction-picker__option--active': myReaction === reaction.type }"
            :style="{ animationDelay: `${40 + index * 28}ms` }"
            :aria-label="reaction.label"
            role="menuitem"
            @pointerdown.prevent.stop="pick(reaction.type, $event)"
            @click.prevent.stop
          >
            <span class="reaction-picker__emoji">{{ reaction.emoji }}</span>
            <span class="reaction-picker__label">{{ reaction.label }}</span>
          </button>
        </div>
      </Transition>
    </Teleport>

    <slot />
  </div>
</template>

<style scoped>
.reaction-picker {
  position: relative;
  display: flex;
  flex: 1;
  user-select: none;
  -webkit-user-select: none;
  -webkit-touch-callout: none;
}

.reaction-picker--compact {
  flex: 0 0 auto;
}

.reaction-picker__popup {
  position: fixed;
  z-index: 80;
  display: flex;
  gap: 2px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-full);
  padding: 6px 8px 8px;
  box-shadow: var(--shadow-md);
  pointer-events: auto;
  transform-origin: 28px 100%;
  overflow: visible;
}

.reaction-picker__popup--below {
  transform-origin: 28px 0%;
}

.reaction-picker__popup::before {
  content: '';
  position: absolute;
  left: 12px;
  right: 12px;
  height: 16px;
}

.reaction-picker__popup:not(.reaction-picker__popup--below)::before {
  top: 100%;
}

.reaction-picker__popup--below::before {
  bottom: 100%;
}

.reaction-picker__option {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  border: none;
  background: none;
  cursor: pointer;
  padding: 6px 8px;
  border-radius: var(--radius-lg);
  line-height: 1;
  color: var(--color-text-muted);
  pointer-events: auto;
  animation: reaction-option-in 0.32s cubic-bezier(0.34, 1.56, 0.64, 1) backwards;
  transition: transform 0.16s cubic-bezier(0.34, 1.56, 0.64, 1), background 0.15s ease;
}

.reaction-picker__option:active {
  transform: translateY(-6px) scale(1.08);
}

.reaction-picker__option--active {
  background: var(--color-primary-surface);
}

.reaction-picker__emoji {
  font-size: 1.625rem;
  line-height: 1;
  pointer-events: none;
  display: block;
  transition: transform 0.16s cubic-bezier(0.34, 1.56, 0.64, 1);
  filter: drop-shadow(0 1px 0 rgb(0 0 0 / 0.04));
}

@media (hover: hover) and (pointer: fine) {
  .reaction-picker__option:hover {
    background: transparent;
    transform: translateY(-10px) scale(1.22);
  }

  .reaction-picker__option:hover .reaction-picker__emoji {
    transform: scale(1.12);
    filter: drop-shadow(0 8px 10px rgb(0 0 0 / 0.16));
  }

  .reaction-picker__option--active:hover {
    background: var(--color-primary-surface);
  }
}

.reaction-picker__label {
  font-size: 0.625rem;
  font-weight: 600;
  white-space: nowrap;
  pointer-events: none;
}

.reaction-popup-enter-active {
  transition: opacity 0.16s ease, transform 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.reaction-popup-leave-active {
  transition: opacity 0.12s ease, transform 0.12s ease;
  pointer-events: none;
}

.reaction-popup-enter-from {
  opacity: 0;
  transform: scale(0.55);
}

.reaction-popup-leave-to {
  opacity: 0;
  transform: scale(0.82);
}

@keyframes reaction-option-in {
  from {
    opacity: 0;
    transform: scale(0.35) translateY(10px);
  }

  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

@media (max-width: 480px) {
  .reaction-picker__popup {
    max-width: calc(100vw - 16px);
    overflow-x: auto;
  }

  .reaction-picker__option {
    padding: 6px;
  }

  .reaction-picker__label {
    display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .reaction-picker__option,
  .reaction-picker__emoji,
  .reaction-popup-enter-active,
  .reaction-popup-leave-active {
    animation: none;
    transition: none;
  }

  .reaction-picker__option:hover,
  .reaction-picker__option:hover .reaction-picker__emoji {
    transform: none;
  }
}
</style>
