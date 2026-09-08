<script setup>
//
// Xem trước tệp fullscreen sát viewport: PDF (PDF.js), Office
// (Word/Excel/PowerPoint qua Office Online), ảnh, văn bản.
//
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import * as pdfjsLib from 'pdfjs-dist';
import PdfJsWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?worker&inline';
import AppIcon from '@/components/AppIcon.vue';

pdfjsLib.GlobalWorkerOptions.workerSrc = `${window.location.origin}/pdfjs-worker.mjs`;

const ZOOM_PRESETS = [
  { value: 'auto', label: 'Tự động chọn kích thước' },
  { value: 'page-actual', label: 'Kích thước thực' },
  { value: 'page-fit', label: 'Vừa chiều cao' },
  { value: 'page-width', label: 'Vừa chiều rộng' },
  { value: '0.5', label: '50%' },
  { value: '0.75', label: '75%' },
  { value: '1', label: '100%' },
  { value: '1.25', label: '125%' },
  { value: '1.5', label: '150%' },
  { value: '2', label: '200%' },
  { value: '3', label: '300%' },
  { value: '4', label: '400%' },
];

const props = defineProps({
  open: { type: Boolean, default: false },
  file: { type: Object, default: null },
  files: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:open', 'update:file']);

const loading = ref(false);
const error = ref('');
const sidebarOpen = ref(true);
const sidebarTab = ref('thumbs');
const findOpen = ref(false);
const findQuery = ref('');
const findCase = ref(false);
const findWhole = ref(false);
const findIndex = ref(-1);
const findHits = ref([]);
const pageInput = ref(1);
const zoomMode = ref('auto');
const rotation = ref(0);
const imageScale = ref(1);
const officeReady = ref(false);
const pages = ref([]);
const thumbs = ref([]);
const outline = ref([]);
const viewerEl = ref(null);
const pageEls = new Map();

let pdfDoc = null;
let pdfWorker = null;
let renderToken = 0;
let findTimer = 0;

const fileName = computed(() => props.file?.name || props.file?.original_name || props.file?.file_name || 'Tệp');
const fileUrl = computed(() => toAbsoluteUrl(props.file?.file_url || props.file?.url || ''));
const previewKind = computed(() => classifyPreview(props.file));
const officeEmbedSrc = computed(() => {
  if (!fileUrl.value) return '';
  return `https://view.officeapps.live.com/op/embed.aspx?src=${encodeURIComponent(fileUrl.value)}&ui=en-US&rs=en-US`;
});
const isGalleryKind = computed(() => previewKind.value === 'office' || previewKind.value === 'image');
const fileIndex = computed(() => props.files.findIndex((item) => fileKey(item) === fileKey(props.file)));
const canFilePrev = computed(() => fileIndex.value > 0);
const canFileNext = computed(() => fileIndex.value >= 0 && fileIndex.value < props.files.length - 1);
const pageCount = computed(() => pages.value.length);
const currentPage = computed(() => Math.min(Math.max(1, Number(pageInput.value) || 1), pageCount.value || 1));
const canPrev = computed(() => currentPage.value > 1);
const canNext = computed(() => currentPage.value < pageCount.value);
const findLabel = computed(() => {
  if (!findHits.value.length) return findQuery.value.trim() ? 'Không tìm thấy' : '';
  return `${findIndex.value + 1} / ${findHits.value.length}`;
});

watch(
  () => [props.open, props.file],
  ([isOpen]) => {
    if (isOpen && props.file) openPreview();
    else closePreview(false);
  },
);

onBeforeUnmount(() => closePreview(false));

function fileKey(item) {
  if (!item) return '';
  if (item.id != null) return `${item.id}:${item.file_url || item.url || ''}`;
  return item.file_url || item.url || item.name || '';
}

function shiftFile(dir) {
  const next = props.files[fileIndex.value + dir];
  if (next) emit('update:file', next);
}

function toAbsoluteUrl(url) {
  if (!url) return '';
  try {
    return new URL(url, window.location.origin).href;
  } catch {
    return url;
  }
}

function classifyPreview(file) {
  if (!file) return 'none';
  if (file.kind === 'drive_link') return 'link';
  const mime = String(file.mime_type || file.mime || '').toLowerCase();
  const name = String(file.original_name || file.file_name || file.name || '');
  const ext = (name.match(/\.([a-z0-9]+)$/i)?.[1] || '').toLowerCase();
  if (file.kind === 'image' || mime.startsWith('image/') || ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].includes(ext)) {
    return 'image';
  }
  if (mime === 'application/pdf' || ext === 'pdf') return 'pdf';
  if (
    ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp'].includes(ext)
    || mime.includes('officedocument')
    || mime.includes('msword')
    || mime.includes('ms-excel')
    || mime.includes('ms-powerpoint')
    || mime.includes('opendocument')
  ) {
    return 'office';
  }
  if (mime.startsWith('text/') || ['txt', 'csv', 'md', 'log'].includes(ext)) return 'text';
  return 'other';
}

function close(force = true) {
  if (force) emit('update:open', false);
}

async function openPreview() {
  error.value = '';
  loading.value = previewKind.value === 'pdf' || previewKind.value === 'office';
  officeReady.value = false;
  sidebarOpen.value = previewKind.value === 'pdf';
  sidebarTab.value = 'thumbs';
  findOpen.value = false;
  findQuery.value = '';
  findHits.value = [];
  findIndex.value = -1;
  pageInput.value = 1;
  zoomMode.value = 'auto';
  rotation.value = 0;
  imageScale.value = 1;
  pages.value = [];
  thumbs.value = [];
  outline.value = [];
  await nextTick();
  document.body.style.overflow = 'hidden';
  document.addEventListener('keydown', onKeydown);

  try {
    if (previewKind.value === 'pdf') await loadPdf();
    else if (previewKind.value !== 'office') loading.value = false;
  } catch (err) {
    error.value = err?.message || 'Không xem trước được tệp này.';
    loading.value = false;
  }
}

function closePreview(emitClose = true) {
  document.removeEventListener('keydown', onKeydown);
  document.body.style.overflow = '';
  window.clearTimeout(findTimer);
  renderToken += 1;
  if (pdfDoc) {
    pdfDoc.destroy();
    pdfDoc = null;
  }
  if (pdfWorker) {
    pdfWorker.destroy();
    pdfWorker = null;
  }
  pages.value = [];
  thumbs.value = [];
  outline.value = [];
  pageEls.clear();
  officeReady.value = false;
  if (emitClose) emit('update:open', false);
}

function onKeydown(event) {
  if (!props.open) return;
  if (event.key === 'Escape') {
    event.preventDefault();
    close();
    return;
  }
  const tag = event.target?.tagName;
  if (tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA') return;
  if (isGalleryKind.value && (event.key === 'ArrowLeft' || event.key === 'ArrowRight')) {
    event.preventDefault();
    shiftFile(event.key === 'ArrowLeft' ? -1 : 1);
    return;
  }
  if (previewKind.value !== 'pdf') return;
  if (event.key === 'ArrowLeft' || event.key === 'PageUp') {
    event.preventDefault();
    goPage(currentPage.value - 1);
  } else if (event.key === 'ArrowRight' || event.key === 'PageDown') {
    event.preventDefault();
    goPage(currentPage.value + 1);
  } else if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'f') {
    event.preventDefault();
    toggleFind(true);
  } else if ((event.ctrlKey || event.metaKey) && (event.key === '+' || event.key === '=')) {
    event.preventDefault();
    bumpZoom(1);
  } else if ((event.ctrlKey || event.metaKey) && event.key === '-') {
    event.preventDefault();
    bumpZoom(-1);
  }
}

function onOfficeLoad() {
  officeReady.value = true;
  loading.value = false;
}

async function loadPdf() {
  const token = ++renderToken;
  if (pdfWorker) {
    pdfWorker.destroy();
    pdfWorker = null;
  }
  let task;
  try {
    pdfWorker = new pdfjsLib.PDFWorker({ port: new PdfJsWorker() });
    task = pdfjsLib.getDocument({ url: fileUrl.value, worker: pdfWorker });
  } catch {
    pdfjsLib.GlobalWorkerOptions.workerSrc = `${window.location.origin}/pdfjs-worker.mjs`;
    task = pdfjsLib.getDocument({ url: fileUrl.value });
  }
  pdfDoc = await task.promise;
  if (token !== renderToken) return;

  const count = pdfDoc.numPages;
  pages.value = Array.from({ length: count }, (_, i) => ({
    num: i + 1,
    width: 0,
    height: 0,
    text: '',
  }));
  pageInput.value = 1;

  try {
    const tree = await pdfDoc.getOutline();
    outline.value = flattenOutline(tree);
  } catch {
    outline.value = [];
  }

  loading.value = false;
  await nextTick();
  await nextTick();
  await renderAllPages(token);
  await renderThumbs(token);
}

function flattenOutline(items, depth = 0) {
  if (!Array.isArray(items)) return [];
  const out = [];
  for (const item of items) {
    out.push({ title: item.title || 'Mục', dest: item.dest, depth });
    if (item.items?.length) out.push(...flattenOutline(item.items, depth + 1));
  }
  return out;
}

function pageScale(page, container) {
  const base = page.getViewport({ scale: 1, rotation: rotation.value });
  const availW = Math.max(320, (container?.clientWidth || 800) - 48);
  const availH = Math.max(240, (container?.clientHeight || 600) - 32);
  if (zoomMode.value === 'auto' || zoomMode.value === 'page-width') return availW / base.width;
  if (zoomMode.value === 'page-fit') return Math.min(availW / base.width, availH / base.height);
  if (zoomMode.value === 'page-actual') return 1;
  const n = Number(zoomMode.value);
  return Number.isFinite(n) && n > 0 ? n : 1;
}

async function renderAllPages(token) {
  if (!pdfDoc || token !== renderToken) return;
  const container = viewerEl.value;
  for (let i = 1; i <= pdfDoc.numPages; i += 1) {
    if (token !== renderToken) return;
    await renderPage(i, token, container);
  }
}

async function renderPage(num, token, container) {
  if (!pdfDoc || token !== renderToken) return;
  const page = await pdfDoc.getPage(num);
  if (token !== renderToken) return;
  const scale = pageScale(page, container);
  const viewport = page.getViewport({ scale, rotation: rotation.value });
  const canvas = pageEls.get(num);
  if (!canvas) return;
  const ctx = canvas.getContext('2d', { alpha: false });
  canvas.width = Math.floor(viewport.width);
  canvas.height = Math.floor(viewport.height);
  canvas.style.width = `${viewport.width}px`;
  canvas.style.height = `${viewport.height}px`;
  pages.value[num - 1] = {
    ...pages.value[num - 1],
    width: viewport.width,
    height: viewport.height,
  };
  await page.render({ canvasContext: ctx, viewport }).promise;
  if (!pages.value[num - 1].text) {
    const content = await page.getTextContent();
    pages.value[num - 1].text = content.items.map((item) => item.str).join(' ');
  }
}

async function renderThumbs(token) {
  if (!pdfDoc || token !== renderToken) return;
  const next = [];
  for (let i = 1; i <= pdfDoc.numPages; i += 1) {
    if (token !== renderToken) return;
    const page = await pdfDoc.getPage(i);
    const viewport = page.getViewport({ scale: 0.18, rotation: rotation.value });
    const canvas = document.createElement('canvas');
    canvas.width = Math.floor(viewport.width);
    canvas.height = Math.floor(viewport.height);
    await page.render({ canvasContext: canvas.getContext('2d', { alpha: false }), viewport }).promise;
    next.push({ num: i, src: canvas.toDataURL('image/jpeg', 0.72) });
  }
  thumbs.value = next;
}

function setPageEl(num, el) {
  if (el) pageEls.set(num, el);
  else pageEls.delete(num);
}

function goPage(num) {
  const next = Math.min(Math.max(1, Number(num) || 1), pageCount.value || 1);
  pageInput.value = next;
  const canvas = pageEls.get(next);
  canvas?.scrollIntoView({ block: 'start' });
}

function onPageInput() {
  goPage(pageInput.value);
}

function onViewerScroll() {
  if (!viewerEl.value || !pages.value.length) return;
  const top = viewerEl.value.scrollTop + 80;
  let seen = 1;
  for (const page of pages.value) {
    const el = pageEls.get(page.num);
    if (!el) continue;
    if (el.offsetTop <= top) seen = page.num;
  }
  pageInput.value = seen;
}

async function setZoom(mode) {
  zoomMode.value = mode;
  if (previewKind.value === 'image') {
    if (mode === 'auto' || mode === 'page-fit' || mode === 'page-width') imageScale.value = 1;
    else imageScale.value = Number(mode) || 1;
    return;
  }
  const token = ++renderToken;
  await nextTick();
  await renderAllPages(token);
  goPage(currentPage.value);
}

function bumpZoom(dir) {
  const steps = [0.5, 0.75, 1, 1.25, 1.5, 2, 3, 4];
  if (previewKind.value === 'image') {
    imageScale.value = Math.min(4, Math.max(0.25, Number((imageScale.value + dir * 0.25).toFixed(2))));
    return;
  }
  const current = Number(zoomMode.value);
  const base = Number.isFinite(current) ? current : 1;
  const idx = steps.reduce((best, step, i) => (
    Math.abs(step - base) < Math.abs(steps[best] - base) ? i : best
  ), 0);
  const next = steps[Math.min(steps.length - 1, Math.max(0, idx + dir))];
  setZoom(String(next));
}

async function rotate(dir) {
  rotation.value = (rotation.value + dir + 360) % 360;
  const token = ++renderToken;
  await nextTick();
  await renderAllPages(token);
  await renderThumbs(token);
}

function toggleFind(force) {
  findOpen.value = force ?? !findOpen.value;
  if (findOpen.value) nextTick(() => document.getElementById('file-preview-find')?.focus());
}

function runFind() {
  window.clearTimeout(findTimer);
  findTimer = window.setTimeout(() => {
    const q = findQuery.value.trim();
    if (!q) {
      findHits.value = [];
      findIndex.value = -1;
      return;
    }
    const needle = findCase.value ? q : q.toLowerCase();
    const hits = [];
    pages.value.forEach((page) => {
      const hay = findCase.value ? page.text : page.text.toLowerCase();
      if (!hay) return;
      if (findWhole.value) {
        const re = new RegExp(`(^|\\W)${escapeReg(needle)}(\\W|$)`, findCase.value ? '' : 'i');
        if (re.test(page.text)) hits.push(page.num);
      } else if (hay.includes(needle)) {
        hits.push(page.num);
      }
    });
    findHits.value = hits;
    findIndex.value = hits.length ? 0 : -1;
    if (hits.length) goPage(hits[0]);
  }, 160);
}

function escapeReg(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function findStep(dir) {
  if (!findHits.value.length) return;
  const next = (findIndex.value + dir + findHits.value.length) % findHits.value.length;
  findIndex.value = next;
  goPage(findHits.value[next]);
}

async function goOutline(item) {
  if (!pdfDoc || !item?.dest) return;
  try {
    const dest = typeof item.dest === 'string' ? await pdfDoc.getDestination(item.dest) : item.dest;
    if (!dest?.[0]) return;
    const num = (await pdfDoc.getPageIndex(dest[0])) + 1;
    goPage(num);
  } catch {
    // Mục lục không nhảy được thì bỏ qua.
  }
}

function download() {
  if (!fileUrl.value) return;
  const link = document.createElement('a');
  link.href = fileUrl.value;
  link.download = fileName.value;
  link.target = '_blank';
  link.rel = 'noopener';
  document.body.appendChild(link);
  link.click();
  link.remove();
}

function openExternal() {
  if (fileUrl.value) window.open(fileUrl.value, '_blank', 'noopener');
}

function printFile() {
  if (!fileUrl.value) return;
  const win = window.open(fileUrl.value, '_blank', 'noopener');
  win?.addEventListener('load', () => win.print());
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open && file"
      class="pv"
      role="dialog"
      aria-modal="true"
      aria-labelledby="file-preview-title"
    >
        <header class="pv__head">
          <span class="pv__icon" aria-hidden="true">
            <AppIcon name="fileText" :size="20" :stroke-width="1.75" />
          </span>
          <h2 id="file-preview-title" class="pv__title">{{ fileName }}</h2>
          <button type="button" class="pv__tool" aria-label="Tải xuống" @click="download">
            <AppIcon name="download" :size="16" :stroke-width="1.75" />
            <span>Tải xuống</span>
          </button>
          <button v-if="previewKind === 'pdf'" type="button" class="pv__tool" aria-label="In" @click="printFile">
            <AppIcon name="fileText" :size="16" :stroke-width="1.75" />
            <span>In</span>
          </button>
          <button type="button" class="pv__icon-btn" aria-label="Đóng" @click="close()">
            <AppIcon name="close" :size="16" />
          </button>
        </header>

        <div class="pv__show">
          <button
            v-if="canFilePrev"
            type="button"
            class="pv__caret pv__caret--prev"
            aria-label="Tệp trước"
            @click="shiftFile(-1)"
          >
            <AppIcon name="chevronLeft" :size="28" :stroke-width="1.75" />
          </button>

          <div class="pv__contents">
            <span v-if="previewKind === 'office' && loading && !officeReady" class="pv__spin" aria-hidden="true" />

            <iframe
              v-if="previewKind === 'office'"
              :key="officeEmbedSrc"
              name="frameEditor"
              class="pv__iframe"
              frameborder="no"
              scrolling="auto"
              :src="officeEmbedSrc"
              aria-label="Xem tài liệu Office"
              @load="onOfficeLoad"
            />

            <div v-else-if="previewKind === 'image'" class="pv__image-box">
              <img :src="fileUrl" alt="">
            </div>

            <template v-else-if="previewKind === 'pdf'">
              <div class="pv__toolbar">
                <div class="pv__toolbar-left">
                  <button
                    type="button"
                    class="pv__icon-btn"
                    :aria-label="sidebarOpen ? 'Ẩn thanh lề' : 'Hiện thanh lề'"
                    :aria-pressed="sidebarOpen ? 'true' : 'false'"
                    @click="sidebarOpen = !sidebarOpen"
                  >
                    <AppIcon name="layoutList" :size="16" :stroke-width="1.75" />
                  </button>
                  <button
                    type="button"
                    class="pv__icon-btn"
                    :aria-label="findOpen ? 'Đóng tìm kiếm' : 'Tìm trong tài liệu'"
                    :aria-pressed="findOpen ? 'true' : 'false'"
                    @click="toggleFind()"
                  >
                    <AppIcon name="search" :size="16" :stroke-width="1.75" />
                  </button>
                  <button type="button" class="pv__icon-btn" aria-label="Trang trước" :disabled="!canPrev" @click="goPage(currentPage - 1)">
                    <AppIcon name="chevronLeft" :size="16" />
                  </button>
                  <button type="button" class="pv__icon-btn" aria-label="Trang sau" :disabled="!canNext" @click="goPage(currentPage + 1)">
                    <AppIcon name="chevronRight" :size="16" />
                  </button>
                  <label class="pv__page">
                    <input v-model.number="pageInput" type="number" min="1" :max="pageCount || 1" class="pv__page-input" @change="onPageInput">
                    <span>trên {{ pageCount || '—' }}</span>
                  </label>
                </div>
                <div class="pv__toolbar-mid">
                  <button type="button" class="pv__icon-btn" aria-label="Thu nhỏ" @click="bumpZoom(-1)">
                    <AppIcon name="zoomOut" :size="16" :stroke-width="1.75" />
                  </button>
                  <button type="button" class="pv__icon-btn" aria-label="Phóng to" @click="bumpZoom(1)">
                    <AppIcon name="zoomIn" :size="16" :stroke-width="1.75" />
                  </button>
                  <select v-model="zoomMode" class="pv__zoom" aria-label="Thu phóng" @change="setZoom(zoomMode)">
                    <option v-for="opt in ZOOM_PRESETS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                  </select>
                </div>
                <div class="pv__toolbar-right">
                  <button type="button" class="pv__icon-btn" aria-label="Xoay ngược chiều kim đồng hồ" @click="rotate(-90)">
                    <AppIcon name="refresh" :size="16" :stroke-width="1.75" />
                  </button>
                </div>
              </div>
              <div v-if="findOpen" class="pv__find">
                <input
                  id="file-preview-find"
                  v-model="findQuery"
                  type="search"
                  class="pv__find-input"
                  placeholder="Tìm trong tài liệu…"
                  @input="runFind"
                >
                <button type="button" class="pv__icon-btn" aria-label="Kết quả trước" :disabled="!findHits.length" @click="findStep(-1)">
                  <AppIcon name="chevronLeft" :size="16" />
                </button>
                <button type="button" class="pv__icon-btn" aria-label="Kết quả sau" :disabled="!findHits.length" @click="findStep(1)">
                  <AppIcon name="chevronRight" :size="16" />
                </button>
                <label class="pv__check">
                  <input v-model="findCase" type="checkbox" @change="runFind">
                  Phân biệt hoa, thường
                </label>
                <label class="pv__check">
                  <input v-model="findWhole" type="checkbox" @change="runFind">
                  Toàn bộ từ
                </label>
                <span class="pv__find-count">{{ findLabel }}</span>
              </div>
              <div class="pv__body">
                <aside v-if="sidebarOpen" class="pv__side hide-scrollbar">
                  <div class="pv__side-tabs" role="tablist" aria-label="Thanh lề tài liệu">
                    <button
                      type="button"
                      class="pv__side-tab"
                      :class="{ 'pv__side-tab--on': sidebarTab === 'thumbs' }"
                      role="tab"
                      :aria-selected="sidebarTab === 'thumbs' ? 'true' : 'false'"
                      @click="sidebarTab = 'thumbs'"
                    >
                      Ảnh thu nhỏ
                    </button>
                    <button
                      type="button"
                      class="pv__side-tab"
                      :class="{ 'pv__side-tab--on': sidebarTab === 'outline' }"
                      role="tab"
                      :aria-selected="sidebarTab === 'outline' ? 'true' : 'false'"
                      :disabled="!outline.length"
                      @click="sidebarTab = 'outline'"
                    >
                      Bản phác
                    </button>
                  </div>
                  <div v-if="sidebarTab === 'thumbs'" class="pv__thumbs hide-scrollbar">
                    <button
                      v-for="thumb in thumbs"
                      :key="thumb.num"
                      type="button"
                      class="pv__thumb"
                      :class="{ 'pv__thumb--on': thumb.num === currentPage }"
                      :aria-label="`Trang ${thumb.num}`"
                      @click="goPage(thumb.num)"
                    >
                      <img :src="thumb.src" alt="">
                      <span>{{ thumb.num }}</span>
                    </button>
                    <p v-if="!thumbs.length && !loading" class="pv__muted">Đang tạo ảnh thu nhỏ…</p>
                  </div>
                  <div v-else class="pv__outline hide-scrollbar">
                    <button
                      v-for="(item, idx) in outline"
                      :key="idx"
                      type="button"
                      class="pv__outline-item"
                      :style="{ paddingLeft: `${0.75 + item.depth * 0.75}rem` }"
                      @click="goOutline(item)"
                    >
                      {{ item.title }}
                    </button>
                  </div>
                </aside>
                <div ref="viewerEl" class="pv__viewer hide-scrollbar" @scroll="onViewerScroll">
                  <p v-if="loading" class="pv__status">Đang tải xem trước…</p>
                  <p v-else-if="error" class="pv__status">{{ error }}</p>
                  <template v-else>
                    <div
                      v-for="page in pages"
                      :key="page.num"
                      class="pv__page"
                      :class="{ 'pv__page--on': page.num === currentPage }"
                    >
                      <canvas :ref="(el) => setPageEl(page.num, el)" />
                    </div>
                  </template>
                </div>
              </div>
            </template>

            <iframe
              v-else-if="previewKind === 'text'"
              :src="fileUrl"
              class="pv__iframe"
              aria-label="Nội dung văn bản"
            />

            <div v-else class="pv__fallback">
              <span class="pv__empty-icon"><AppIcon name="fileText" :size="28" :stroke-width="1.5" /></span>
              <p class="pv__empty-title">Không xem trước được định dạng này</p>
              <p class="pv__empty-hint">Tải xuống hoặc mở trong tab mới để xem tệp.</p>
              <div class="pv__empty-actions">
                <button type="button" class="pv__btn pv__btn--ghost" @click="openExternal">Mở tab mới</button>
                <button type="button" class="pv__btn" @click="download">Tải xuống</button>
              </div>
            </div>
          </div>

          <button
            v-if="canFileNext"
            type="button"
            class="pv__caret pv__caret--next"
            aria-label="Tệp sau"
            @click="shiftFile(1)"
          >
            <AppIcon name="chevronRight" :size="28" :stroke-width="1.75" />
          </button>
        </div>
    </div>
  </Teleport>
</template>

<style scoped>
.pv {
  /* html { zoom: var(--app-scale) } làm 100vw/100vh chỉ phủ ~90% viewport —
     chia lại cho --app-scale để preview sát mép cửa sổ. */
  position: fixed;
  top: 0;
  left: 0;
  z-index: 1000;
  width: calc(100vw / var(--app-scale, 1));
  height: calc(100dvh / var(--app-scale, 1));
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--color-surface);
}

.pv__show {
  position: relative;
  flex: 1;
  min-width: 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
  background: var(--color-surface-muted);
}

.pv__caret {
  position: absolute;
  top: 50%;
  z-index: 3;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  padding: 0;
  border: none;
  border-radius: 999px;
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: var(--shadow-md);
  cursor: pointer;
  transform: translateY(-50%);
}

.pv__caret--prev {
  left: 0.75rem;
}

.pv__caret--next {
  right: 0.75rem;
}

.pv__caret:hover {
  background: var(--color-surface);
  color: var(--color-primary);
}

.pv__contents {
  position: relative;
  flex: 1;
  min-width: 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--color-surface);
}

.pv__iframe {
  width: 100%;
  height: 100%;
  margin-top: 0;
  border: none;
  background: var(--color-surface);
}

.pv__image-box {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.pv__image-box img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

.pv__spin {
  position: absolute;
  top: 50%;
  left: 50%;
  width: 2rem;
  height: 2rem;
  margin: -1rem 0 0 -1rem;
  border: 2px solid var(--color-border);
  border-top-color: var(--color-primary);
  border-radius: 999px;
  animation: pv-spin 0.7s linear infinite;
}

@keyframes pv-spin {
  to { transform: rotate(360deg); }
}

.pv__head,
.pv__toolbar,
.pv__find {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  gap: var(--space-2);
}

.pv__head {
  padding: 0.75rem 1rem;
  box-shadow: 0 1px 0 var(--color-border);
}

.pv__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  color: var(--color-primary);
}

.pv__title {
  flex: 1;
  min-width: 0;
  margin: 0;
  overflow: hidden;
  color: var(--color-text);
  font-size: 1rem;
  font-weight: 700;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.pv__toolbar {
  justify-content: space-between;
  padding: 0.4rem 0.75rem;
  background: var(--color-surface-muted);
  box-shadow: 0 1px 0 var(--color-border);
}

.pv__toolbar-left,
.pv__toolbar-mid,
.pv__toolbar-right,
.pv__image-tools {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.pv__tool,
.pv__icon-btn,
.pv__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  cursor: pointer;
}

.pv__tool {
  height: 2rem;
  padding: 0 0.65rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.pv__tool:hover,
.pv__icon-btn:hover:not(:disabled) {
  background: var(--color-surface);
}

.pv__icon-btn {
  width: 1.85rem;
  height: 1.85rem;
  padding: 0;
  color: var(--color-text-muted);
}

.pv__icon-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.pv__page {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin-left: 0.35rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.pv__page-input,
.pv__zoom,
.pv__find-input {
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  color: var(--color-text);
  font-family: var(--font-family-base);
}

.pv__page-input {
  width: 3.25rem;
  height: 1.75rem;
  text-align: center;
}

.pv__zoom {
  height: 1.75rem;
  max-width: 12rem;
  padding: 0 0.4rem;
  font-size: 0.75rem;
}

.pv__find {
  flex-wrap: wrap;
  padding: 0.5rem 0.75rem;
  background: var(--color-surface);
  box-shadow: 0 1px 0 var(--color-border);
}

.pv__find-input {
  width: min(18rem, 100%);
  height: 2rem;
  padding: 0 0.7rem;
  font-size: 0.8125rem;
}

.pv__find-input::placeholder {
  color: var(--color-text-muted);
}

.pv__check {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  color: var(--color-text);
  font-size: 0.75rem;
}

.pv__find-count {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.pv__body {
  display: flex;
  flex: 1;
  min-height: 0;
}

.pv__side {
  width: 11.5rem;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  background: var(--color-surface-muted);
  box-shadow: 1px 0 0 var(--color-border);
}

.pv__side-tabs {
  display: flex;
  flex-shrink: 0;
}

.pv__side-tab {
  flex: 1;
  height: 2.25rem;
  padding: 0 0.4rem;
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.6875rem;
  font-weight: 650;
  cursor: pointer;
}

.pv__side-tab--on {
  color: var(--color-text);
  box-shadow: inset 0 -2px 0 var(--color-primary);
}

.pv__side-tab:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.pv__thumbs,
.pv__outline {
  flex: 1;
  min-height: 0;
  overflow: auto;
  padding: 0.6rem;
}

.pv__thumb {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.3rem;
  width: 100%;
  margin-bottom: 0.65rem;
  padding: 0.35rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  cursor: pointer;
}

.pv__thumb img {
  width: 100%;
  height: auto;
  background: var(--color-surface-muted);
}

.pv__thumb--on {
  box-shadow: inset 0 0 0 2px var(--color-primary);
  color: var(--color-text);
}

.pv__outline-item {
  display: block;
  width: 100%;
  padding: 0.4rem 0.5rem;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  text-align: left;
  cursor: pointer;
}

.pv__outline-item:hover {
  background: var(--color-surface);
}

.pv__viewer {
  flex: 1;
  min-width: 0;
  overflow: auto;
  padding: 1rem;
  background: color-mix(in srgb, var(--color-surface-muted) 80%, var(--color-border));
}

.pv__viewer--fill {
  padding: 0;
  overflow: hidden;
}

.pv__office {
  position: relative;
  width: 100%;
  height: 100%;
  min-height: 0;
}

.pv__page {
  display: flex;
  justify-content: center;
  margin: 0 auto 1rem;
}

.pv__page canvas {
  display: block;
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.pv__image-wrap {
  min-height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.pv__image {
  max-width: 100%;
  max-height: calc(100vh - 12rem);
  object-fit: contain;
  transform-origin: center center;
  box-shadow: var(--shadow-md);
}

.pv__frame {
  width: 100%;
  height: 100%;
  min-height: 100%;
  border: none;
  background: var(--color-surface);
}

.pv__status,
.pv__fallback,
.pv__muted {
  margin: 0;
  padding: 2.5rem 1rem;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  text-align: center;
}

.pv__status--overlay {
  position: absolute;
  inset: 0;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-surface);
}

.pv__fallback {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
}

.pv__empty-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 3.5rem;
  height: 3.5rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text-muted);
}

.pv__empty-title {
  margin: 0;
  color: var(--color-text);
  font-weight: 650;
}

.pv__empty-hint {
  margin: 0;
}

.pv__empty-actions {
  display: flex;
  gap: var(--space-2);
  margin-top: var(--space-2);
}

.pv__btn {
  height: 2rem;
  padding: 0 0.85rem;
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-size: 0.8125rem;
  font-weight: 600;
}

.pv__btn--ghost {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

@media (max-width: 768px) {
  .pv__side {
    width: 7.5rem;
  }

  .pv__tool span {
    display: none;
  }
}
</style>
