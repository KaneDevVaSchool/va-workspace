<script setup>
//
// Menu chuột phải trên dòng dự án (chế độ danh sách). Bám mẫu 1Office:
// icon + nhãn, mục có chevron mở submenu, mục còn lại / lá submenu mở modal.
// Chi tiết mở panel sẵn có trên danh sách (trang riêng sẽ làm sau).
// Không có hồ sơ ký số (giai đoạn sau).
//
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  x: { type: Number, default: 0 },
  y: { type: Number, default: 0 },
  project: { type: Object, default: null },
  canDuplicate: { type: Boolean, default: false },
  statuses: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'action']);

const STATUS_MENU_ORDER = ['in_progress', 'completed', 'on_hold', 'cancelled', 'planning'];
const STATUS_MENU_LABELS = {
  in_progress: 'Đang thực hiện',
  completed: 'Hoàn thành',
  on_hold: 'Tạm dừng',
  cancelled: 'Hủy',
  planning: 'Đang chờ',
};
const STATUS_TONES = {
  in_progress: 'primary',
  completed: 'success',
  on_hold: 'gold',
  cancelled: 'umber',
  planning: 'tertiary',
};

const TASK_VARIANTS = [
  { key: 'normal', label: 'Thêm công việc thường' },
  { key: 'bulk', label: 'Thêm nhiều công việc thường' },
  { key: 'by_category', label: 'Thêm công việc theo danh mục' },
  { key: 'process', label: 'Thêm công việc quy trình' },
  { key: 'by_phase', label: 'Thêm công việc theo phase' },
];

const TIME_VARIANTS = [
  { key: 'planned', label: 'Thời gian dự án' },
  { key: 'actual', label: 'Thời gian thực tế' },
];

const menuRef = ref(null);
const pos = ref({ x: 0, y: 0 });
const openSub = ref(null);
const subFlip = ref({ left: false, up: false });
let subCloseTimer = 0;

const statusItems = computed(() => {
  const byValue = new Map((props.statuses || []).map((s) => [s.value, s]));
  return STATUS_MENU_ORDER.map((value) => ({
    value,
    label: STATUS_MENU_LABELS[value] || byValue.get(value)?.label || value,
    tone: STATUS_TONES[value] || 'neutral',
  }));
});

function clampPos(x, y) {
  const el = menuRef.value;
  const w = el?.offsetWidth || 280;
  const h = el?.offsetHeight || 520;
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
  if (!props.project) return;
  emit('action', { type, project: props.project, ...extra });
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
      v-if="open && project"
      ref="menuRef"
      class="proj-ctx"
      role="menu"
      :style="{ left: `${pos.x}px`, top: `${pos.y}px` }"
      @contextmenu.prevent
      @mousedown.stop
    >
      <div
        class="proj-ctx__wrap"
        @mouseenter="showSub('status')"
        @mouseleave="hideSubSoon"
      >
        <button
          type="button"
          class="proj-ctx__item"
          :class="{ 'proj-ctx__item--open': openSub === 'status' }"
          role="menuitem"
          aria-haspopup="menu"
          :aria-expanded="openSub === 'status' ? 'true' : 'false'"
          @click="showSub('status')"
        >
          <span class="proj-ctx__ico proj-ctx__ico--primary">
            <AppIcon name="pauseCircle" :size="15" :stroke-width="1.75" />
          </span>
          <span>Cập nhật trạng thái</span>
          <AppIcon name="chevronRight" :size="14" class="proj-ctx__chevron" />
        </button>
        <div
          v-show="openSub === 'status'"
          data-submenu="status"
          class="proj-ctx__sub"
          :class="{ 'proj-ctx__sub--left': subFlip.left, 'proj-ctx__sub--up': subFlip.up }"
          role="menu"
          @mouseenter="keepSub"
          @mouseleave="hideSubSoon"
        >
          <button
            v-for="item in statusItems"
            :key="item.value"
            type="button"
            class="proj-ctx__item proj-ctx__item--plain"
            :class="[`proj-ctx__item--${item.tone}`, { 'proj-ctx__item--current': project.status === item.value }]"
            role="menuitem"
            @click="pick('status', { status: item.value })"
          >
            <span class="proj-ctx__dot" :class="`proj-ctx__dot--${item.tone}`" />
            <span>{{ item.label }}</span>
          </button>
        </div>
      </div>

      <button type="button" class="proj-ctx__item" role="menuitem" @click="pick('members')" @mouseenter="openSub = null">
        <span class="proj-ctx__ico proj-ctx__ico--tertiary">
          <AppIcon name="userPlus" :size="15" :stroke-width="1.75" />
        </span>
        <span>Thêm người tham gia dự án</span>
      </button>

      <button type="button" class="proj-ctx__item" role="menuitem" @click="pick('category')" @mouseenter="openSub = null">
        <span class="proj-ctx__ico proj-ctx__ico--gold">
          <AppIcon name="listChecks" :size="15" :stroke-width="1.75" />
        </span>
        <span>Thêm danh mục công việc</span>
      </button>

      <div
        class="proj-ctx__wrap"
        @mouseenter="showSub('task')"
        @mouseleave="hideSubSoon"
      >
        <button
          type="button"
          class="proj-ctx__item"
          :class="{ 'proj-ctx__item--open': openSub === 'task' }"
          role="menuitem"
          aria-haspopup="menu"
          :aria-expanded="openSub === 'task' ? 'true' : 'false'"
          @click="showSub('task')"
        >
          <span class="proj-ctx__ico proj-ctx__ico--info">
            <AppIcon name="plus" :size="15" :stroke-width="1.75" />
          </span>
          <span>Thêm công việc</span>
          <AppIcon name="chevronRight" :size="14" class="proj-ctx__chevron" />
        </button>
        <div
          v-show="openSub === 'task'"
          data-submenu="task"
          class="proj-ctx__sub"
          :class="{ 'proj-ctx__sub--left': subFlip.left, 'proj-ctx__sub--up': subFlip.up }"
          role="menu"
          @mouseenter="keepSub"
          @mouseleave="hideSubSoon"
        >
          <button
            v-for="item in TASK_VARIANTS"
            :key="item.key"
            type="button"
            class="proj-ctx__item proj-ctx__item--plain"
            role="menuitem"
            @click="pick('task', { variant: item.key })"
          >
            <span>{{ item.label }}</span>
          </button>
        </div>
      </div>

      <button type="button" class="proj-ctx__item" role="menuitem" @click="pick('phase')" @mouseenter="openSub = null">
        <span class="proj-ctx__ico proj-ctx__ico--secondary">
          <AppIcon name="flag" :size="15" :stroke-width="1.75" />
        </span>
        <span>Thêm phase</span>
      </button>

      <button type="button" class="proj-ctx__item" role="menuitem" @click="pick('baseline')" @mouseenter="openSub = null">
        <span class="proj-ctx__ico proj-ctx__ico--warning">
          <AppIcon name="flag" :size="15" :stroke-width="1.75" />
        </span>
        <span>Chốt baseline</span>
      </button>

      <div
        class="proj-ctx__wrap"
        @mouseenter="showSub('time')"
        @mouseleave="hideSubSoon"
      >
        <button
          type="button"
          class="proj-ctx__item"
          :class="{ 'proj-ctx__item--open': openSub === 'time' }"
          role="menuitem"
          aria-haspopup="menu"
          :aria-expanded="openSub === 'time' ? 'true' : 'false'"
          @click="showSub('time')"
        >
          <span class="proj-ctx__ico proj-ctx__ico--info">
            <AppIcon name="calendar" :size="15" :stroke-width="1.75" />
          </span>
          <span>Cập nhật thời gian</span>
          <AppIcon name="chevronRight" :size="14" class="proj-ctx__chevron" />
        </button>
        <div
          v-show="openSub === 'time'"
          data-submenu="time"
          class="proj-ctx__sub"
          :class="{ 'proj-ctx__sub--left': subFlip.left, 'proj-ctx__sub--up': subFlip.up }"
          role="menu"
          @mouseenter="keepSub"
          @mouseleave="hideSubSoon"
        >
          <button
            v-for="item in TIME_VARIANTS"
            :key="item.key"
            type="button"
            class="proj-ctx__item proj-ctx__item--plain"
            role="menuitem"
            @click="pick('dates', { focus: item.key })"
          >
            <span>{{ item.label }}</span>
          </button>
        </div>
      </div>

      <button type="button" class="proj-ctx__item" role="menuitem" @click="pick('description')" @mouseenter="openSub = null">
        <span class="proj-ctx__ico proj-ctx__ico--violet">
          <AppIcon name="fileText" :size="15" :stroke-width="1.75" />
        </span>
        <span>Cập nhật mô tả dự án</span>
      </button>

      <span class="proj-ctx__sep" role="separator" />

      <button type="button" class="proj-ctx__item" role="menuitem" @click="pick('duplicate')" @mouseenter="openSub = null">
        <span class="proj-ctx__ico proj-ctx__ico--success">
          <AppIcon name="copy" :size="15" :stroke-width="1.75" />
        </span>
        <span>Nhân bản dự án</span>
      </button>

      <button type="button" class="proj-ctx__item" role="menuitem" @click="pick('edit')" @mouseenter="openSub = null">
        <span class="proj-ctx__ico proj-ctx__ico--primary">
          <AppIcon name="pencil" :size="15" :stroke-width="1.75" />
        </span>
        <span>Sửa</span>
      </button>

      <button type="button" class="proj-ctx__item" role="menuitem" @click="pick('details')" @mouseenter="openSub = null">
        <span class="proj-ctx__ico proj-ctx__ico--tertiary">
          <AppIcon name="externalLink" :size="15" :stroke-width="1.75" />
        </span>
        <span>Chi tiết</span>
        <AppIcon name="chevronRight" :size="14" class="proj-ctx__chevron" />
      </button>

      <button type="button" class="proj-ctx__item" role="menuitem" @click="pick('labels')" @mouseenter="openSub = null">
        <span class="proj-ctx__ico proj-ctx__ico--gold">
          <AppIcon name="tag" :size="15" :stroke-width="1.75" />
        </span>
        <span>Nhãn</span>
      </button>
    </div>
  </Teleport>
</template>

<style scoped>
.proj-ctx,
.proj-ctx__sub {
  z-index: 1400;
  min-width: 17.75rem;
  padding: 0.375rem 0;
  border-radius: 0.5rem;
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.proj-ctx {
  position: fixed;
  user-select: none;
}

.proj-ctx__wrap {
  position: relative;
}

.proj-ctx__sub {
  position: absolute;
  top: 0;
  left: calc(100% - 0.25rem);
  z-index: 1401;
  min-width: 16.5rem;
}

.proj-ctx__sub--left {
  left: auto;
  right: calc(100% - 0.25rem);
}

.proj-ctx__sub--up {
  top: auto;
  bottom: 0;
}

.proj-ctx__item {
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

.proj-ctx__item:hover,
.proj-ctx__item--open {
  background: var(--color-surface-muted);
}

.proj-ctx__item--plain {
  font-weight: 400;
}

.proj-ctx__item--current {
  font-weight: 600;
  background: color-mix(in srgb, var(--color-primary) 8%, var(--color-surface));
}

.proj-ctx__item--current.proj-ctx__item--success {
  color: var(--color-success-tint-fg);
  background: var(--color-success-tint-bg);
}

.proj-ctx__item--current.proj-ctx__item--gold {
  color: var(--color-gold-800);
  background: var(--color-gold-surface);
}

.proj-ctx__item--current.proj-ctx__item--umber {
  color: var(--color-umber-tint-fg);
  background: var(--color-umber-tint-bg);
}

.proj-ctx__item--current.proj-ctx__item--tertiary {
  color: var(--color-tertiary-800);
  background: var(--color-tertiary-surface);
}

.proj-ctx__item--current.proj-ctx__item--primary {
  color: var(--color-primary-900);
  background: var(--color-primary-surface);
}

.proj-ctx__item > span:not(.proj-ctx__ico):not(.proj-ctx__dot) {
  flex: 1;
  min-width: 0;
}

.proj-ctx__ico {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-sm);
}

.proj-ctx__ico--primary {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.proj-ctx__ico--tertiary {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.proj-ctx__ico--gold {
  background: var(--color-gold-surface);
  color: var(--color-gold-700);
}

.proj-ctx__ico--info {
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
}

.proj-ctx__ico--secondary {
  background: var(--color-secondary-surface);
  color: var(--color-secondary-700);
}

.proj-ctx__ico--warning {
  background: var(--color-warning-tint-bg);
  color: var(--color-warning-tint-fg);
}

.proj-ctx__ico--violet {
  background: color-mix(in srgb, var(--color-tertiary-surface) 55%, var(--color-primary-surface));
  color: color-mix(in srgb, var(--color-tertiary) 55%, var(--color-primary));
}

.proj-ctx__ico--success {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
}

.proj-ctx__dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.proj-ctx__dot--primary {
  background: var(--color-primary);
}

.proj-ctx__dot--success {
  background: var(--color-success);
}

.proj-ctx__dot--gold {
  background: var(--color-gold);
}

.proj-ctx__dot--umber {
  background: var(--color-umber);
}

.proj-ctx__dot--tertiary {
  background: var(--color-tertiary);
}

.proj-ctx__chevron {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.proj-ctx__sep {
  display: block;
  height: 1px;
  margin: 0.375rem 0.75rem;
  background: var(--color-border);
}
</style>
