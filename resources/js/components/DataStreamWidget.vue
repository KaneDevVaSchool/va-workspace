<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick, getCurrentInstance, reactive } from 'vue';
import AppIcon from './AppIcon.vue';

const props = defineProps({
  tabs:    { type: Array,  required: true },
  tabData: { type: Object, required: true },
});

const emit = defineEmits(['btn-click']);

// ─── Tab state ────────────────────────────────────────────────────────────────
const activeTab = ref(props.tabs[0]?.id ?? '');
const current = computed(() => props.tabData[activeTab.value] ?? {});
const nodes   = computed(() => current.value.nodes ?? []);
const edges   = computed(() => current.value.edges ?? []);
const canvasW = computed(() => current.value.canvasW ?? 1400);
const canvasH = computed(() => current.value.canvasH ?? 720);

// ─── Zoom ─────────────────────────────────────────────────────────────────────
const MIN_ZOOM = 0.3;
const MAX_ZOOM = 1.5;
const zoom = ref(0.75);

function zoomIn()  { zoom.value = Math.min(MAX_ZOOM, Math.round((zoom.value + 0.1) * 10) / 10); }
function zoomOut() { zoom.value = Math.max(MIN_ZOOM, Math.round((zoom.value - 0.1) * 10) / 10); }

// ─── Drag nodes + pan canvas ──────────────────────────────────────────────────
const stageRef = ref(null);
const positions = reactive({});
const panX = ref(0);
const panY = ref(0);
const draggingId = ref(null);
const panning = ref(false);
const hasPanned = ref(false);
let stopPointer = null;

function posOf(node) {
  const stored = positions[node.id];
  return stored ?? { x: node.x ?? 0, y: node.y ?? 0 };
}

function resetPositions() {
  Object.keys(positions).forEach((key) => delete positions[key]);
}

function resetPan() {
  panX.value = 0;
  panY.value = 0;
  hasPanned.value = false;
}

function eventElement(target) {
  if (!target) return null;
  return target.nodeType === 1 ? target : target.parentElement;
}

function isInteractiveTarget(target) {
  return Boolean(eventElement(target)?.closest?.('button, a, input, select, textarea'));
}

function listenPointer(onMove, onEnd) {
  stopPointer?.();

  const opts = { capture: true };
  let lastKey = '';
  function move(event) {
    if (event.pointerType && event.pointerType !== 'mouse' && event.buttons === 0) {
      up();
      return;
    }
    const key = `${event.clientX},${event.clientY}`;
    if (key === lastKey) return;
    lastKey = key;
    onMove(event);
  }
  function up() {
    stopPointer?.();
    onEnd();
  }
  function teardown() {
    document.removeEventListener('pointermove', move, opts);
    document.removeEventListener('mousemove', move, opts);
    document.removeEventListener('pointerup', up, opts);
    document.removeEventListener('pointercancel', up, opts);
    document.removeEventListener('mouseup', up, opts);
    stopPointer = null;
  }

  document.addEventListener('pointermove', move, opts);
  document.addEventListener('mousemove', move, opts);
  document.addEventListener('pointerup', up, opts);
  document.addEventListener('pointercancel', up, opts);
  document.addEventListener('mouseup', up, opts);
  stopPointer = teardown;
}

function captureStage(event) {
  if (event.pointerId == null) return;
  const candidates = [event.currentTarget, stageRef.value];
  for (const el of candidates) {
    if (!el?.setPointerCapture) continue;
    try {
      el.setPointerCapture(event.pointerId);
      return;
    } catch {
      // Try the next candidate.
    }
  }
}

function startDrag(event, node) {
  if (event.button !== 0) return;
  if (isInteractiveTarget(event.target)) return;

  event.preventDefault();
  event.stopPropagation();
  captureStage(event);

  const origin = posOf(node);
  const startX = event.clientX;
  const startY = event.clientY;
  draggingId.value = node.id;

  listenPointer(
    (moveEvent) => {
      const dx = (moveEvent.clientX - startX) / zoom.value;
      const dy = (moveEvent.clientY - startY) / zoom.value;
      positions[node.id] = {
        x: Math.max(0, origin.x + dx),
        y: Math.max(0, origin.y + dy),
      };
    },
    () => {
      draggingId.value = null;
    },
  );
}

function startPan(event) {
  if (event.button !== 0) return;
  if (draggingId.value) return;
  if (isInteractiveTarget(event.target)) return;
  if (eventElement(event.target)?.closest?.('.dsw-node')) return;

  event.preventDefault();
  captureStage(event);

  const startX = event.clientX;
  const startY = event.clientY;
  const originX = panX.value;
  const originY = panY.value;
  panning.value = true;
  hasPanned.value = true;

  listenPointer(
    (moveEvent) => {
      panX.value = originX + (moveEvent.clientX - startX);
      panY.value = originY + (moveEvent.clientY - startY);
    },
    () => {
      panning.value = false;
    },
  );
}

// ─── Fullscreen ───────────────────────────────────────────────────────────────
const widgetRef     = ref(null);
const isFullscreen  = ref(false);

function toggleFullscreen() {
  if (!document.fullscreenElement) {
    widgetRef.value?.requestFullscreen?.();
  } else {
    document.exitFullscreen?.();
  }
}

function syncFullscreen() {
  isFullscreen.value = !!document.fullscreenElement;
}

// ─── Button click ─────────────────────────────────────────────────────────────
function handleBtnClick(btn) {
  emit('btn-click', { btn });
}

// ─── SVG arrow marker unique id ───────────────────────────────────────────────
const uid     = getCurrentInstance()?.uid ?? Math.random().toString(36).slice(2);
const arrowId = `dsw-arrow-${uid}`;

// ─── Node height measurement ──────────────────────────────────────────────────
const nodeElMap = {};
const measuredH = ref({});

function setNodeRef(id, el) {
  if (el) nodeElMap[id] = el; else delete nodeElMap[id];
}

function measureAll() {
  const map = {};
  for (const [id, el] of Object.entries(nodeElMap)) {
    if (el) map[id] = el.offsetHeight;
  }
  measuredH.value = map;
}

onMounted(() => {
  nextTick(measureAll);
  document.addEventListener('fullscreenchange', syncFullscreen);
});

onUnmounted(() => {
  document.removeEventListener('fullscreenchange', syncFullscreen);
  stopPointer?.();
  draggingId.value = null;
  panning.value = false;
});

watch(activeTab, () => {
  measuredH.value = {};
  resetPositions();
  resetPan();
  nextTick(measureAll);
});

// ─── Connector path ───────────────────────────────────────────────────────────
function estimateH(node) {
  if (!node) return 80;
  if (node.type === 'icon') return 76;
  if (node.type === 'card') {
    const head  = node.compact ? 0 : 50;
    const items = (node.items?.length ?? 0) * 25;
    const btn   = node.button ? 38 : 0;
    return head + items + btn + 16;
  }
  if (node.type === 'group') return 46 + (node.children?.length ?? 0) * 50;
  return 80;
}

function nodeRect(id) {
  const node = nodes.value.find(n => n.id === id);
  if (!node) return null;
  const pos = posOf(node);
  return {
    x: pos.x, y: pos.y,
    w: node.w ?? 220,
    h: measuredH.value[id] ?? node.h ?? estimateH(node),
  };
}

const liveCanvasW = computed(() => {
  let max = canvasW.value;
  for (const node of nodes.value) {
    const pos = posOf(node);
    max = Math.max(max, pos.x + (node.w ?? 220) + 24);
  }
  return max;
});

const liveCanvasH = computed(() => {
  let max = canvasH.value;
  for (const node of nodes.value) {
    const pos = posOf(node);
    const h = measuredH.value[node.id] ?? node.h ?? estimateH(node);
    max = Math.max(max, pos.y + h + 24);
  }
  return max;
});

const canvasWrapStyle = computed(() => {
  const w = liveCanvasW.value, h = liveCanvasH.value, z = zoom.value;
  return {
    position: 'relative',
    transform: `translate(${panX.value}px, ${panY.value}px) scale(${z})`,
    transformOrigin: 'top left',
    width:  w + 'px',
    height: h + 'px',
  };
});

function anchorPt(r, side) {
  switch (side) {
    case 'right':  return { x: r.x + r.w,    y: r.y + r.h / 2 };
    case 'left':   return { x: r.x,           y: r.y + r.h / 2 };
    case 'top':    return { x: r.x + r.w / 2, y: r.y };
    case 'bottom': return { x: r.x + r.w / 2, y: r.y + r.h };
    default:       return { x: r.x + r.w,     y: r.y + r.h / 2 };
  }
}

function autoAnchors(fr, tr) {
  const dx = (tr.x + tr.w / 2) - (fr.x + fr.w / 2);
  const dy = (tr.y + tr.h / 2) - (fr.y + fr.h / 2);
  if (Math.abs(dx) >= Math.abs(dy)) {
    return { fa: dx >= 0 ? 'right' : 'left', ta: dx >= 0 ? 'left' : 'right' };
  }
  return { fa: dy >= 0 ? 'bottom' : 'top', ta: dy >= 0 ? 'top' : 'bottom' };
}

function pathD(edge) {
  const fr = nodeRect(edge.from);
  const tr = nodeRect(edge.to);
  if (!fr || !tr) return '';
  let fa = edge.fromAnchor, ta = edge.toAnchor;
  if (!fa || !ta) {
    const auto = autoAnchors(fr, tr);
    fa = fa || auto.fa;
    ta = ta || auto.ta;
  }
  const f = anchorPt(fr, fa);
  const t = anchorPt(tr, ta);
  const NEAR = 14;
  if (Math.abs(f.y - t.y) <= NEAR && (fa === 'right' || fa === 'left')) return `M${f.x},${f.y} L${t.x},${t.y}`;
  if (Math.abs(f.x - t.x) <= NEAR && (fa === 'top'   || fa === 'bottom')) return `M${f.x},${f.y} L${t.x},${t.y}`;
  if (fa === 'right' || fa === 'left') {
    const mx = f.x + (t.x - f.x) / 2;
    return `M${f.x},${f.y} L${mx},${f.y} L${mx},${t.y} L${t.x},${t.y}`;
  }
  const my = f.y + (t.y - f.y) / 2;
  return `M${f.x},${f.y} L${f.x},${my} L${t.x},${my} L${t.x},${t.y}`;
}
</script>

<template>
  <div ref="widgetRef" class="dsw" :class="{ 'dsw--fs': isFullscreen }">

    <!-- ── Tabs ──────────────────────────────────────────────────────── -->
    <div class="dsw__tabs" role="tablist">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        role="tab"
        type="button"
        class="dsw__tab"
        :class="{ 'dsw__tab--active': activeTab === tab.id }"
        :aria-selected="activeTab === tab.id"
        @click="activeTab = tab.id"
      >{{ tab.label }}</button>
    </div>

    <!-- ── Info bar + controls ───────────────────────────────────────── -->
    <div class="dsw__bar">
      <div v-if="current.title" class="dsw__bar-text">
        <span class="dsw__bar-title">{{ current.title }}</span>
        <span v-if="current.description" class="dsw__bar-desc">{{ current.description }}</span>
      </div>
      <div class="dsw__controls">
        <button type="button" class="dsw__ctrl"
          :aria-label="isFullscreen ? 'Thoát toàn màn hình' : 'Xem toàn màn hình'"
          @click="toggleFullscreen">
          <AppIcon :name="isFullscreen ? 'minimize' : 'fullscreen'" :size="13" :stroke-width="1.75" />
        </button>
        <button type="button" class="dsw__ctrl" aria-label="Phóng to" @click="zoomIn">
          <AppIcon name="zoomIn" :size="13" :stroke-width="1.75" />
        </button>
        <button type="button" class="dsw__ctrl" aria-label="Thu nhỏ" @click="zoomOut">
          <AppIcon name="zoomOut" :size="13" :stroke-width="1.75" />
        </button>
        <span class="dsw__zoom-pct">{{ Math.round(zoom * 100) }}%</span>
      </div>
    </div>

    <!-- ── Canvas stage ──────────────────────────────────────────────── -->
    <div
      ref="stageRef"
      class="dsw__stage hide-scrollbar"
      :class="{ 'dsw__stage--panning': panning, 'dsw__stage--dragging': draggingId }"
      @pointerdown="startPan"
    >
      <div v-if="!hasPanned" class="dsw__pan-hint">
        <AppIcon name="move" :size="13" :stroke-width="1.75" />
        <span>Nắm và kéo vào khoảng trống để di chuyển sơ đồ</span>
      </div>

      <div class="dsw__canvas-wrap" :style="canvasWrapStyle">

        <!-- SVG connectors -->
        <svg class="dsw__svg" :width="liveCanvasW" :height="liveCanvasH" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <marker :id="arrowId" markerWidth="6.5" markerHeight="6.5"
              refX="6" refY="3.25" viewBox="0 0 6.5 6.5" orient="auto" fill="var(--color-border-strong)">
              <polygon points="0,0 6.5,3.25 0,6.5" />
            </marker>
          </defs>
          <path
            v-for="edge in edges" :key="edge.id"
            :d="pathD(edge)" fill="none" stroke="var(--color-border-strong)"
            stroke-width="1.4" stroke-linejoin="round"
            :marker-end="`url(#${arrowId})`"
          />
        </svg>

        <!-- ── Nodes ─────────────────────────────────────────────────── -->
        <template v-for="node in nodes" :key="node.id">

          <!-- Icon / actor node -->
          <div
            v-if="node.type === 'icon'"
            :ref="el => setNodeRef(node.id, el)"
            class="dsw-node dsw-node--icon"
            :class="{ 'dsw-node--dragging': draggingId === node.id }"
            :style="{ left: posOf(node).x + 'px', top: posOf(node).y + 'px', width: node.w + 'px' }"
            @pointerdown.stop="startDrag($event, node)"
          >
            <div class="dsw-box-icon">
              <AppIcon :name="node.icon || 'users'" :size="20" :stroke-width="1.75" />
            </div>
            <span class="dsw-icon-label">{{ node.label }}</span>
          </div>

          <!-- Card node -->
          <div
            v-else-if="node.type === 'card'"
            :ref="el => setNodeRef(node.id, el)"
            class="dsw-node dsw-node--card"
            :class="{ 'dsw-node--hl': node.highlight, 'dsw-node--dragging': draggingId === node.id }"
            :style="{ left: posOf(node).x + 'px', top: posOf(node).y + 'px', width: node.w + 'px' }"
            @pointerdown.stop="startDrag($event, node)"
          >
            <!-- Header -->
            <div v-if="!node.compact && node.title" class="dsw-card-head">
              <div v-if="node.icon" class="dsw-card-icon">
                <AppIcon :name="node.icon" :size="14" :stroke-width="1.75" />
              </div>
              <div class="dsw-card-hc">
                <div class="dsw-card-title">{{ node.title }}</div>
                <div v-if="node.teaser" class="dsw-card-teaser">
                  <AppIcon name="users" :size="10" :stroke-width="1.75" />
                  <em>{{ node.teaser }}</em>
                </div>
              </div>
            </div>

            <!-- Body -->
            <div class="dsw-card-body">
              <div
                v-for="item in node.items" :key="item.label"
                class="dsw-card-item"
              >
                <AppIcon :name="item.icon || 'check'" :size="11" :stroke-width="1.75" />
                <span>{{ item.label }}</span>
              </div>
              <button
                v-if="node.button"
                type="button"
                class="dsw-card-btn"
                @click="handleBtnClick(node.button)"
              >
                <AppIcon name="plus" :size="11" :stroke-width="2" />
                {{ node.button.label }}
              </button>
            </div>
          </div>

          <!-- Group node -->
          <div
            v-else-if="node.type === 'group'"
            :ref="el => setNodeRef(node.id, el)"
            class="dsw-node dsw-node--group"
            :class="{ 'dsw-node--dragging': draggingId === node.id }"
            :style="{ left: posOf(node).x + 'px', top: posOf(node).y + 'px', width: node.w + 'px' }"
            @pointerdown.stop="startDrag($event, node)"
          >
            <div class="dsw-group-label">{{ node.label }}</div>
            <div class="dsw-group-body">
              <div v-for="child in node.children" :key="child.id" class="dsw-group-child">
                <div v-if="child.icon" class="dsw-card-icon dsw-card-icon--sm">
                  <AppIcon :name="child.icon" :size="12" :stroke-width="1.75" />
                </div>
                <div class="dsw-card-hc">
                  <div class="dsw-card-title">{{ child.title }}</div>
                  <div v-if="child.teaser" class="dsw-card-teaser"><em>{{ child.teaser }}</em></div>
                </div>
              </div>
            </div>
          </div>

        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ── Shell ────────────────────────────────────────────────────────────────── */
.dsw {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-md);
  overflow: hidden;
  font-family: var(--font-family-base);
  display: flex;
  flex-direction: column;
}

/* fullscreen — Fullscreen API fills viewport automatically; we just fix layout */
.dsw:fullscreen,
.dsw:-webkit-full-screen {
  border-radius: 0;
}

.dsw:fullscreen .dsw__stage,
.dsw:-webkit-full-screen .dsw__stage {
  flex: 1;
  min-height: 0;
}

/* ── Tab bar ──────────────────────────────────────────────────────────────── */
.dsw__tabs {
  display: flex;
  padding: 0 16px;
  box-shadow: 0 1px 0 var(--color-border);
  flex-shrink: 0;
}

.dsw__tab {
  padding: 9px 14px;
  border: none;
  background: none;
  font-family: inherit;
  font-size: 13px;
  font-weight: 500;
  color: var(--color-text-muted);
  cursor: pointer;
  position: relative;
  transition: color 150ms;
}

.dsw__tab:hover { color: var(--color-text); }

.dsw__tab--active { color: var(--color-primary); }

.dsw__tab--active::after {
  content: '';
  position: absolute;
  bottom: -1px; left: 14px; right: 14px;
  height: 2px;
  border-radius: 2px 2px 0 0;
  background: var(--color-primary);
}

/* ── Info bar ─────────────────────────────────────────────────────────────── */
.dsw__bar {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 16px;
  box-shadow: 0 1px 0 var(--color-border);
  flex-shrink: 0;
}

.dsw__bar-text {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: baseline;
  gap: 10px;
  flex-wrap: wrap;
}

.dsw__bar-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-text);
  flex-shrink: 0;
}

.dsw__bar-desc {
  font-size: 11.5px;
  color: var(--color-text-muted);
  line-height: 1.5;
}

/* ── Controls ─────────────────────────────────────────────────────────────── */
.dsw__controls {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  background: var(--color-surface-muted);
  border-radius: var(--radius-sm);
  padding: 2px;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.dsw__ctrl {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border: none;
  background: none;
  border-radius: calc(var(--radius-sm) - 2px);
  color: var(--color-text-muted);
  cursor: pointer;
  transition: background 120ms, color 120ms;
}

.dsw__ctrl:hover {
  background: var(--color-surface);
  color: var(--color-text);
}

.dsw__zoom-pct {
  font-size: 10.5px;
  font-weight: 600;
  color: var(--color-text-muted);
  min-width: 30px;
  text-align: center;
  user-select: none;
  padding: 0 2px;
}

/* ── Stage ────────────────────────────────────────────────────────────────── */
.dsw__stage {
  position: relative;
  overflow: hidden;
  min-height: 360px;
  padding: 12px;
  background: var(--color-surface-muted);
  touch-action: none;
  user-select: none;
  cursor: grab;
  overscroll-behavior: none;
}

.dsw__stage--panning,
.dsw__stage--dragging {
  cursor: grabbing;
}

/* ── Pan hint ─────────────────────────────────────────────────────────────── */
.dsw__pan-hint {
  position: absolute;
  z-index: 5;
  top: 10px;
  right: 10px;
  left: 10px;
  max-width: max-content;
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 5px 10px;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  color: var(--color-text-muted);
  font-size: 11px;
  line-height: 1.3;
  pointer-events: none;
  user-select: none;
}

@media (max-width: 480px) {
  .dsw__pan-hint span { display: none; }
  .dsw__pan-hint { padding: 6px; }
}

.dsw__pan-hint svg { flex-shrink: 0; color: var(--color-primary); }

/* ── Canvas wrap ──────────────────────────────────────────────────────────── */
.dsw__canvas-wrap {
  display: block;
  min-width: 100%;
  min-height: 100%;
  will-change: transform;
  cursor: grab;
}

.dsw__stage--panning .dsw__canvas-wrap {
  cursor: grabbing;
}

/* ── SVG ──────────────────────────────────────────────────────────────────── */
.dsw__svg {
  position: absolute;
  top: 0; left: 0;
  pointer-events: none;
  overflow: visible;
}

/* ── Base node ────────────────────────────────────────────────────────────── */
.dsw-node {
  position: absolute;
  cursor: grab;
  touch-action: none;
  user-select: none;
}

.dsw-node--dragging {
  z-index: 4;
  cursor: grabbing;
}

.dsw-node--dragging.dsw-node--card,
.dsw-node--dragging.dsw-node--group {
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.12);
}

.dsw-node .dsw-card-btn {
  cursor: pointer;
}

/* ── Icon node ────────────────────────────────────────────────────────────── */
.dsw-node--icon {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  text-align: center;
}

.dsw-box-icon {
  width: 48px;
  height: 48px;
  border-radius: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-surface);
  color: var(--color-primary);
  box-shadow: 0 0 0 1.5px var(--color-border), 0 2px 6px rgba(0,0,0,.07);
}

.dsw-icon-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--color-text-muted);
  line-height: 1.3;
}

/* ── Card node ────────────────────────────────────────────────────────────── */
.dsw-node--card {
  background: var(--color-surface);
  border-radius: var(--radius-md);
  box-shadow: 0 0 0 1px var(--color-border), 0 2px 6px rgba(0,0,0,.05);
  overflow: hidden;
}

.dsw-node--hl {
  background: var(--color-primary-surface);
  box-shadow: 0 0 0 1.5px var(--color-primary-200), 0 2px 6px rgba(0,0,0,.05);
}

/* Card header */
.dsw-card-head {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 8px 10px;
  box-shadow: 0 1px 0 var(--color-border);
}

.dsw-node--hl .dsw-card-head { box-shadow: 0 1px 0 var(--color-primary-200); }

.dsw-card-icon {
  flex-shrink: 0;
  width: 26px;
  height: 26px;
  border-radius: 7px;
  background: var(--color-primary);
  color: var(--color-on-primary);
  display: flex;
  align-items: center;
  justify-content: center;
}

.dsw-card-icon--sm { width: 22px; height: 22px; border-radius: 5px; }

.dsw-node--hl .dsw-card-icon { background: var(--color-primary-700); }

.dsw-card-hc { flex: 1; min-width: 0; }

.dsw-card-title {
  font-size: 11.5px;
  font-weight: 600;
  color: var(--color-text);
  line-height: 1.3;
}

.dsw-card-teaser {
  display: flex;
  align-items: center;
  gap: 3px;
  margin-top: 2px;
  font-size: 10px;
  color: var(--color-text-muted);
}

.dsw-card-teaser em { font-style: italic; }

/* Card body */
.dsw-card-body {
  padding: 6px 10px 8px;
  display: flex;
  flex-direction: column;
  gap: 0;
}

.dsw-card-item {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  color: var(--color-text-muted);
  padding: 2.5px 0;
}

.dsw-card-item svg {
  flex-shrink: 0;
  color: var(--color-primary);
  opacity: .7;
}

/* Interactive button — visually a row CTA */
.dsw-card-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 4px 8px;
  margin-top: 5px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
  background: transparent;
  color: var(--color-text);
  font: 500 10.5px/1 var(--font-family-base);
  cursor: pointer;
  transition: background 120ms, border-color 120ms;
  width: 100%;
}

.dsw-card-btn:hover {
  background: var(--color-surface-muted);
  border-color: var(--color-primary-200);
  color: var(--color-primary);
}

.dsw-node--hl .dsw-card-btn { border-color: var(--color-primary-200); }
.dsw-node--hl .dsw-card-btn:hover { background: var(--color-primary-surface); }

/* ── Group node ───────────────────────────────────────────────────────────── */
.dsw-node--group {
  padding: 22px 8px 8px;
  position: relative;
  border-radius: var(--radius-md);
  /* 4-side dashed via gradient strips — matches reference ds-type-dashed */
  background-image:
    repeating-linear-gradient(0deg,   var(--color-border-strong) 0,var(--color-border-strong) 6px, transparent 6px, transparent 12px),
    repeating-linear-gradient(90deg,  var(--color-border-strong) 0,var(--color-border-strong) 6px, transparent 6px, transparent 12px),
    repeating-linear-gradient(180deg, var(--color-border-strong) 0,var(--color-border-strong) 6px, transparent 6px, transparent 12px),
    repeating-linear-gradient(270deg, var(--color-border-strong) 0,var(--color-border-strong) 6px, transparent 6px, transparent 12px);
  background-size: 1px 100%, 100% 1px, 1px 100%, 100% 1px;
  background-position: 0 0, 0 0, 100% 0, 0 100%;
  background-repeat: no-repeat;
}

.dsw-group-label {
  position: absolute;
  top: -11px;
  left: 10px;
  background: var(--color-primary);
  color: var(--color-on-primary);
  padding: 2px 9px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.06em;
  border-radius: var(--radius-sm);
}

.dsw-group-body {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.dsw-group-child {
  display: flex;
  align-items: flex-start;
  gap: 7px;
  padding: 7px 9px;
  background: var(--color-surface);
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
}
</style>
