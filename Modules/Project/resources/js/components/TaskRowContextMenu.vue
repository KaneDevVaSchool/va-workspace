<script setup>
//
// Menu chuột phải trên dòng công việc. Bám mẫu 1Office + ProjectRowContextMenu:
// icon + nhãn, mục có chevron mở submenu, mục còn lại mở modal / trang.
// Không có hồ sơ ký số và biểu mẫu.
//
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { TASK_STATUS_LABELS, TASK_STATUS_TAB_KEYS, TASK_STATUS_TONES } from '../constants/task.js';

const props = defineProps({
  open: { type: Boolean, default: false },
  x: { type: Number, default: 0 },
  y: { type: Number, default: 0 },
  task: { type: Object, default: null },
  canEdit: { type: Boolean, default: false },
  canApprove: { type: Boolean, default: false },
  canDuplicate: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'action']);

const TIME_VARIANTS = [
  { key: 'planned', label: 'Thời gian kế hoạch' },
  { key: 'actual', label: 'Thời gian thực tế' },
];

const DOC_VARIANTS = [
  { key: 'computer', label: 'Tải từ máy tính' },
];

const menuRef = ref(null);
const pos = ref({ x: 0, y: 0 });
const openSub = ref(null);
const subFlip = ref({ left: false, up: false });
let subCloseTimer = 0;

const statusItems = computed(() =>
  TASK_STATUS_TAB_KEYS.map((value) => ({
    value,
    label: TASK_STATUS_LABELS[value] || value,
    tone: TASK_STATUS_TONES[value] || 'neutral',
  })),
);

const detailItems = computed(() => {
  const items = [
    { key: 'open', label: 'Xem chi tiết' },
    { key: 'blank', label: 'Mở tab mới' },
  ];
  if (props.canEdit) {
    items.push({ key: 'edit', label: 'Sửa công việc' });
  }
  return items;
});

function clampPos(x, y) {
  const el = menuRef.value;
  const w = el?.offsetWidth || 280;
  const h = el?.offsetHeight || 440;
  const pad = 8;
  return {
    x: Math.min(Math.max(pad, x), Math.max(pad, window.innerWidth - w - pad)),
    y: Math.min(Math.max(pad, y), Math.max(pad, window.innerHeight - h - pad)),
  };
}

function close() {
  openSub.value = null;
  emit('close');
}

function pick(type, extra = {}) {
  if (!props.task) return;
  emit('action', { type, task: props.task, ...extra });
  close();
}

function showSub(key) {
  window.clearTimeout(subCloseTimer);
  openSub.value = key;
  nextTick(() => {
    const menu = menuRef.value;
    const sub = menu?.querySelector(`[data-submenu="${key}"]`);
    if (!menu || !sub) return;
    const menuRect = menu.getBoundingClientRect();
    const subRect = sub.getBoundingClientRect();
    subFlip.value = {
      left: menuRect.right + subRect.width > window.innerWidth - 8,
      up: menuRect.top + (sub.offsetTop || 0) + subRect.height > window.innerHeight - 8,
    };
  });
}

function hideSubSoon() {
  window.clearTimeout(subCloseTimer);
  subCloseTimer = window.setTimeout(() => {
    openSub.value = null;
  }, 140);
}

function keepSub() {
  window.clearTimeout(subCloseTimer);
}

function onDocumentPointerDown(event) {
  if (!props.open) return;
  const el = menuRef.value;
  if (el && el.contains(event.target)) return;
  close();
}

function onKeydown(event) {
  if (event.key === 'Escape' && props.open) {
    event.preventDefault();
    close();
  }
}

function onViewportChange() {
  if (props.open) close();
}

watch(
  () => [props.open, props.x, props.y],
  ([isOpen]) => {
    if (!isOpen) {
      openSub.value = null;
      return;
    }
    pos.value = { x: props.x, y: props.y };
    nextTick(() => {
      pos.value = clampPos(props.x, props.y);
    });
  },
);

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      document.addEventListener('pointerdown', onDocumentPointerDown, true);
      document.addEventListener('keydown', onKeydown);
      window.addEventListener('resize', onViewportChange);
      window.addEventListener('scroll', onViewportChange, true);
    } else {
      document.removeEventListener('pointerdown', onDocumentPointerDown, true);
      document.removeEventListener('keydown', onKeydown);
      window.removeEventListener('resize', onViewportChange);
      window.removeEventListener('scroll', onViewportChange, true);
    }
  },
);

onBeforeUnmount(() => {
  window.clearTimeout(subCloseTimer);
  document.removeEventListener('pointerdown', onDocumentPointerDown, true);
  document.removeEventListener('keydown', onKeydown);
  window.removeEventListener('resize', onViewportChange);
  window.removeEventListener('scroll', onViewportChange, true);
});
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open && task"
      ref="menuRef"
      class="task-ctx"
      role="menu"
      :style="{ left: `${pos.x}px`, top: `${pos.y}px` }"
      @contextmenu.prevent
      @mousedown.stop
    >
      <template v-if="canEdit">
        <div
          class="task-ctx__wrap"
          @mouseenter="showSub('status')"
          @mouseleave="hideSubSoon"
        >
          <button
            type="button"
            class="task-ctx__item"
            :class="{ 'task-ctx__item--open': openSub === 'status' }"
            role="menuitem"
            aria-haspopup="menu"
            :aria-expanded="openSub === 'status' ? 'true' : 'false'"
            @click="showSub('status')"
          >
            <span class="task-ctx__ico task-ctx__ico--primary">
              <AppIcon name="pauseCircle" :size="15" :stroke-width="1.75" />
            </span>
            <span>Cập nhật trạng thái</span>
            <AppIcon name="chevronRight" :size="14" class="task-ctx__chevron" />
          </button>
          <div
            v-show="openSub === 'status'"
            data-submenu="status"
            class="task-ctx__sub"
            :class="{ 'task-ctx__sub--left': subFlip.left, 'task-ctx__sub--up': subFlip.up }"
            role="menu"
            @mouseenter="keepSub"
            @mouseleave="hideSubSoon"
          >
            <button
              v-for="item in statusItems"
              :key="item.value"
              type="button"
              class="task-ctx__item task-ctx__item--plain"
              :class="[`task-ctx__item--${item.tone}`, { 'task-ctx__item--current': task.status === item.value }]"
              role="menuitem"
              @click="pick('status', { status: item.value })"
            >
              <span class="task-ctx__dot" :class="`task-ctx__dot--${item.tone}`" />
              <span>{{ item.label }}</span>
            </button>
          </div>
        </div>

        <button type="button" class="task-ctx__item" role="menuitem" @click="pick('members')" @mouseenter="openSub = null">
          <span class="task-ctx__ico task-ctx__ico--tertiary">
            <AppIcon name="userPlus" :size="15" :stroke-width="1.75" />
          </span>
          <span>Thêm người thực hiện</span>
        </button>

        <button type="button" class="task-ctx__item" role="menuitem" @click="pick('progress')" @mouseenter="openSub = null">
          <span class="task-ctx__ico task-ctx__ico--secondary">
            <AppIcon name="trendingUp" :size="15" :stroke-width="1.75" />
          </span>
          <span>Cập nhật tiến độ</span>
        </button>

        <div
          class="task-ctx__wrap"
          @mouseenter="showSub('time')"
          @mouseleave="hideSubSoon"
        >
          <button
            type="button"
            class="task-ctx__item"
            :class="{ 'task-ctx__item--open': openSub === 'time' }"
            role="menuitem"
            aria-haspopup="menu"
            :aria-expanded="openSub === 'time' ? 'true' : 'false'"
            @click="showSub('time')"
          >
            <span class="task-ctx__ico task-ctx__ico--info">
              <AppIcon name="calendar" :size="15" :stroke-width="1.75" />
            </span>
            <span>Cập nhật thời gian</span>
            <AppIcon name="chevronRight" :size="14" class="task-ctx__chevron" />
          </button>
          <div
            v-show="openSub === 'time'"
            data-submenu="time"
            class="task-ctx__sub"
            :class="{ 'task-ctx__sub--left': subFlip.left, 'task-ctx__sub--up': subFlip.up }"
            role="menu"
            @mouseenter="keepSub"
            @mouseleave="hideSubSoon"
          >
            <button
              v-for="item in TIME_VARIANTS"
              :key="item.key"
              type="button"
              class="task-ctx__item task-ctx__item--plain"
              role="menuitem"
              @click="pick('dates', { focus: item.key })"
            >
              <span>{{ item.label }}</span>
            </button>
          </div>
        </div>

        <button type="button" class="task-ctx__item" role="menuitem" @click="pick('move')" @mouseenter="openSub = null">
          <span class="task-ctx__ico task-ctx__ico--secondary">
            <AppIcon name="move" :size="15" :stroke-width="1.75" />
          </span>
          <span>Chuyển công việc</span>
        </button>
      </template>

      <span v-if="canEdit" class="task-ctx__sep" role="separator" />

      <button
        v-if="canApprove"
        type="button"
        class="task-ctx__item"
        role="menuitem"
        @click="pick('evaluate')"
        @mouseenter="openSub = null"
      >
        <span class="task-ctx__ico task-ctx__ico--gold">
          <AppIcon name="starFilled" :size="15" :stroke-width="1.75" />
        </span>
        <span>Đánh giá kết quả</span>
      </button>

      <div
        v-if="canEdit"
        class="task-ctx__wrap"
        @mouseenter="showSub('docs')"
        @mouseleave="hideSubSoon"
      >
        <button
          type="button"
          class="task-ctx__item"
          :class="{ 'task-ctx__item--open': openSub === 'docs' }"
          role="menuitem"
          aria-haspopup="menu"
          :aria-expanded="openSub === 'docs' ? 'true' : 'false'"
          @click="showSub('docs')"
        >
          <span class="task-ctx__ico task-ctx__ico--info">
            <AppIcon name="fileUp" :size="15" :stroke-width="1.75" />
          </span>
          <span>Thêm tài liệu</span>
          <AppIcon name="chevronRight" :size="14" class="task-ctx__chevron" />
        </button>
        <div
          v-show="openSub === 'docs'"
          data-submenu="docs"
          class="task-ctx__sub"
          :class="{ 'task-ctx__sub--left': subFlip.left, 'task-ctx__sub--up': subFlip.up }"
          role="menu"
          @mouseenter="keepSub"
          @mouseleave="hideSubSoon"
        >
          <button
            v-for="item in DOC_VARIANTS"
            :key="item.key"
            type="button"
            class="task-ctx__item task-ctx__item--plain"
            role="menuitem"
            @click="pick('documents', { variant: item.key })"
          >
            <span>{{ item.label }}</span>
          </button>
        </div>
      </div>

      <span v-if="canEdit || canApprove" class="task-ctx__sep" role="separator" />

      <button
        v-if="canDuplicate"
        type="button"
        class="task-ctx__item"
        role="menuitem"
        @click="pick('duplicate')"
        @mouseenter="openSub = null"
      >
        <span class="task-ctx__ico task-ctx__ico--success">
          <AppIcon name="copy" :size="15" :stroke-width="1.75" />
        </span>
        <span>Nhân bản công việc</span>
      </button>

      <div
        class="task-ctx__wrap"
        @mouseenter="showSub('details')"
        @mouseleave="hideSubSoon"
      >
        <button
          type="button"
          class="task-ctx__item"
          :class="{ 'task-ctx__item--open': openSub === 'details' }"
          role="menuitem"
          aria-haspopup="menu"
          :aria-expanded="openSub === 'details' ? 'true' : 'false'"
          @click="showSub('details')"
        >
          <span class="task-ctx__ico task-ctx__ico--tertiary">
            <AppIcon name="externalLink" :size="15" :stroke-width="1.75" />
          </span>
          <span>Chi tiết</span>
          <AppIcon name="chevronRight" :size="14" class="task-ctx__chevron" />
        </button>
        <div
          v-show="openSub === 'details'"
          data-submenu="details"
          class="task-ctx__sub"
          :class="{ 'task-ctx__sub--left': subFlip.left, 'task-ctx__sub--up': subFlip.up }"
          role="menu"
          @mouseenter="keepSub"
          @mouseleave="hideSubSoon"
        >
          <button
            v-for="item in detailItems"
            :key="item.key"
            type="button"
            class="task-ctx__item task-ctx__item--plain"
            role="menuitem"
            @click="pick('details', { variant: item.key })"
          >
            <span>{{ item.label }}</span>
          </button>
        </div>
      </div>

      <template v-if="canEdit">
        <span class="task-ctx__sep" role="separator" />

        <button type="button" class="task-ctx__item" role="menuitem" @click="pick('delete')" @mouseenter="openSub = null">
          <span class="task-ctx__ico task-ctx__ico--danger">
            <AppIcon name="trash" :size="15" :stroke-width="1.75" />
          </span>
          <span>Xoá công việc</span>
        </button>
      </template>
    </div>
  </Teleport>
</template>

<style scoped>
.task-ctx,
.task-ctx__sub {
  z-index: 1400;
  min-width: 17.75rem;
  padding: 0.375rem 0;
  border-radius: 0.5rem;
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.task-ctx {
  position: fixed;
  user-select: none;
}

.task-ctx__wrap {
  position: relative;
}

.task-ctx__sub {
  position: absolute;
  top: 0;
  left: calc(100% - 0.25rem);
  z-index: 1401;
  min-width: 16.5rem;
}

.task-ctx__sub--left {
  left: auto;
  right: calc(100% - 0.25rem);
}

.task-ctx__sub--up {
  top: auto;
  bottom: 0;
}

.task-ctx__item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  margin: 0;
  padding: 0.5rem 0.875rem;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 500;
  text-align: left;
  cursor: pointer;
}

.task-ctx__item:hover,
.task-ctx__item--open {
  background: var(--color-surface-muted);
}

.task-ctx__item--plain {
  font-weight: 400;
}

.task-ctx__item--current {
  font-weight: 600;
  background: color-mix(in srgb, var(--color-primary) 8%, var(--color-surface));
}

.task-ctx__item--current.task-ctx__item--success {
  color: var(--color-success-tint-fg);
  background: var(--color-success-tint-bg);
}

.task-ctx__item--current.task-ctx__item--gold {
  color: var(--color-gold-800);
  background: var(--color-gold-surface);
}

.task-ctx__item--current.task-ctx__item--umber {
  color: var(--color-umber-tint-fg);
  background: var(--color-umber-tint-bg);
}

.task-ctx__item--current.task-ctx__item--tertiary {
  color: var(--color-tertiary-800);
  background: var(--color-tertiary-surface);
}

.task-ctx__item--current.task-ctx__item--primary {
  color: var(--color-primary-900);
  background: var(--color-primary-surface);
}

.task-ctx__item > span:not(.task-ctx__ico):not(.task-ctx__dot) {
  flex: 1;
  min-width: 0;
}

.task-ctx__ico {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-sm);
}

.task-ctx__ico--primary {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.task-ctx__ico--tertiary {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.task-ctx__ico--gold {
  background: var(--color-gold-surface);
  color: var(--color-gold-700);
}

.task-ctx__ico--info {
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
}

.task-ctx__ico--secondary {
  background: var(--color-secondary-surface);
  color: var(--color-secondary-700);
}

.task-ctx__ico--success {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
}

.task-ctx__ico--danger {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}

.task-ctx__dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.task-ctx__dot--primary {
  background: var(--color-primary);
}

.task-ctx__dot--success {
  background: var(--color-success);
}

.task-ctx__dot--gold {
  background: var(--color-gold);
}

.task-ctx__dot--umber {
  background: var(--color-umber);
}

.task-ctx__dot--tertiary {
  background: var(--color-tertiary);
}

.task-ctx__chevron {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.task-ctx__sep {
  display: block;
  height: 1px;
  margin: 0.375rem 0.75rem;
  background: var(--color-border);
}
</style>
