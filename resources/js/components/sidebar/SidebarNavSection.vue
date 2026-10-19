<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '../AppIcon.vue';

const props = defineProps({
  sectionId: { type: String, required: true },
  label: { type: String, required: true },
  sectionIcon: { type: String, required: true },
  items: { type: Array, required: true },
  expanded: { type: Boolean, default: false },
  collapsed: { type: Boolean, default: false },
  isActive: { type: Function, required: true },
  itemLabel: { type: Function, required: true },
  badgeLabel: { type: Function, required: true },
});

const emit = defineEmits(['toggle', 'navigate']);

const flyoutOpen = ref(false);
const flyoutRef = ref(null);

const hasActiveChild = () => props.items.some((item) => props.isActive(item.name));

watch(
  () => props.collapsed,
  (value) => {
    if (!value) flyoutOpen.value = false;
  },
);

function onDocumentClick(event) {
  if (!flyoutOpen.value || !flyoutRef.value) return;
  if (!flyoutRef.value.contains(event.target)) {
    flyoutOpen.value = false;
  }
}

function onDocumentKeydown(event) {
  if (event.key === 'Escape') flyoutOpen.value = false;
}

watch(flyoutOpen, (open) => {
  if (open) {
    document.addEventListener('mousedown', onDocumentClick);
    document.addEventListener('keydown', onDocumentKeydown);
  } else {
    document.removeEventListener('mousedown', onDocumentClick);
    document.removeEventListener('keydown', onDocumentKeydown);
  }
});

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onDocumentClick);
  document.removeEventListener('keydown', onDocumentKeydown);
});

function onItemClick() {
  emit('navigate');
  flyoutOpen.value = false;
}
</script>

<template>
  <div
    v-if="collapsed"
    ref="flyoutRef"
    class="sidebar-flow-step sidebar-flow-step--rail"
    :data-tour="`sidebar-section-${sectionId}`"
  >
    <button
      type="button"
      class="sidebar-flow-step__rail-btn"
      :class="{ 'sidebar-flow-step__rail-btn--active': hasActiveChild() || flyoutOpen }"
      :aria-label="label"
      :aria-expanded="flyoutOpen"
      aria-haspopup="menu"
      @click="flyoutOpen = !flyoutOpen"
    >
      <AppIcon :name="sectionIcon" :size="18" />
      <span v-if="hasActiveChild()" class="sidebar-flow-step__rail-dot" aria-hidden="true" />
    </button>

    <div
      v-if="flyoutOpen"
      class="sidebar-flyout sidebar-flyout--flow"
      role="menu"
      :aria-label="label"
    >
      <div class="sidebar-flyout__head sidebar-flyout__head--flow">
        <span class="sidebar-flyout__title">{{ label }}</span>
      </div>
      <div class="sidebar-flyout__body sidebar-flyout__body--flow">
        <router-link
          v-for="item in items"
          :key="item.name"
          :to="{ name: item.name }"
          class="sidebar-nav-item sidebar-nav-item--link sidebar-nav-item--flyout"
          :class="{ 'sidebar-nav-item--active': isActive(item.name) }"
          role="menuitem"
          :aria-current="isActive(item.name) ? 'page' : null"
          @click="onItemClick"
        >
          <span class="sidebar-nav-item__icon sidebar-nav-item__icon--flyout">
            <AppIcon :name="item.icon" :size="15" />
          </span>
          <span class="sidebar-nav-item__text">{{ itemLabel(item) }}</span>
          <span v-if="badgeLabel(item)" class="sidebar-nav-item__badge">{{ badgeLabel(item) }}</span>
        </router-link>
      </div>
    </div>
  </div>

  <section
    v-else
    class="sidebar-flow-step"
    :class="{
      'sidebar-flow-step--open': expanded,
      'sidebar-flow-step--active': hasActiveChild(),
    }"
    :aria-labelledby="`nav-section-title-${sectionId}`"
    :data-tour="`sidebar-section-${sectionId}`"
  >
    <div class="sidebar-flow-step__content">
      <button
        type="button"
        :id="`nav-section-${sectionId}`"
        class="sidebar-flow-step__head"
        :aria-expanded="expanded"
        :aria-controls="`nav-section-panel-${sectionId}`"
        @click="emit('toggle')"
      >
        <span class="sidebar-flow-step__icon">
          <AppIcon :name="sectionIcon" :size="15" />
        </span>
        <span class="sidebar-flow-step__titles">
          <span :id="`nav-section-title-${sectionId}`" class="sidebar-flow-step__label">{{ label }}</span>
        </span>
        <span
          v-if="hasActiveChild() && !expanded"
          class="sidebar-flow-step__here"
          aria-hidden="true"
        />
        <span
          class="sidebar-flow-step__chevron"
          :class="{ 'sidebar-flow-step__chevron--open': expanded }"
          aria-hidden="true"
        >
          <AppIcon name="chevronDown" :size="14" />
        </span>
      </button>

      <div
        :id="`nav-section-panel-${sectionId}`"
        role="region"
        :aria-labelledby="`nav-section-title-${sectionId}`"
        class="sidebar-flow-step__panel"
        :class="{ 'sidebar-flow-step__panel--open': expanded }"
      >
        <div class="sidebar-flow-step__panel-inner">
          <ul class="sidebar-flow-step__items" role="list">
            <li v-for="item in items" :key="item.name">
              <router-link
                :to="{ name: item.name }"
                class="sidebar-nav-item sidebar-nav-item--link"
                :class="{ 'sidebar-nav-item--active': isActive(item.name) }"
                :aria-current="isActive(item.name) ? 'page' : null"
                @click="emit('navigate')"
              >
                <span class="sidebar-nav-item__icon">
                  <AppIcon :name="item.icon" :size="15" />
                </span>
                <span class="sidebar-nav-item__text">{{ itemLabel(item) }}</span>
                <span v-if="badgeLabel(item)" class="sidebar-nav-item__badge">{{ badgeLabel(item) }}</span>
              </router-link>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </section>
</template>
