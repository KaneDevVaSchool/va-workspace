<script setup>
//
// Tab Đính kèm — file manager 2 cột theo mẫu 1Office:
// Tài liệu dự án (thư mục + tệp) | Đính kèm công việc.
// Lưới / danh sách, tạo thư mục, tải nhiều tệp, kéo-thả, đổi tên, xoá.
//
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { showClientToast } from '@/lib/clientToast';

const FilePreviewDialog = defineAsyncComponent(() => import('./FilePreviewDialog.vue'));

const props = defineProps({
  project: { type: Object, required: true },
  canEdit: { type: Boolean, default: false },
});

const emit = defineEmits(['changed']);

const router = useRouter();

const VIEW_KEY = 'va.project.documents.view';

const loading = ref(false);
const uploading = ref(false);
const saving = ref(false);
const currentFolderId = ref(null);
const folder = ref(null);
const ancestors = ref([]);
const folders = ref([]);
const files = ref([]);
const taskFiles = ref([]);
const taskLoading = ref(false);
const projectView = ref(localStorage.getItem(`${VIEW_KEY}.project`) === 'list' ? 'list' : 'grid');
const taskView = ref(localStorage.getItem(`${VIEW_KEY}.task`) === 'list' ? 'list' : 'grid');
const dragging = ref(false);
const fileInput = ref(null);
const projectQuery = ref('');
const taskQuery = ref('');

const formOpen = ref(false);
const formMode = ref('folder');
const formName = ref('');
const formDriveUrl = ref('');
const renameTarget = ref(null);

const confirmOpen = ref(false);
const confirmTarget = ref(null);

const menu = ref(null);
const menuOpen = ref(false);
const menuItem = ref(null);
const menuKind = ref('file');
const menuPos = ref({ x: 0, y: 0 });
const previewOpen = ref(false);
const previewFile = ref(null);
const previewFiles = ref([]);

const title = computed(() => folder.value?.name || 'Tài liệu dự án');
const parentLabel = computed(() => {
  if (!folder.value) return 'Tài liệu dự án (gốc)';
  const names = [...ancestors.value.map((a) => a.name), folder.value.name];
  return names.join(' / ');
});
const isEmptyProject = computed(() => !folders.value.length && !files.value.length);

const visibleFolders = computed(() => {
  const q = projectQuery.value.trim().toLowerCase();
  if (!q) return folders.value;
  return folders.value.filter((item) => String(item.name || '').toLowerCase().includes(q));
});

const visibleFiles = computed(() => {
  const q = projectQuery.value.trim().toLowerCase();
  if (!q) return files.value;
  return files.value.filter((item) => fileName(item).toLowerCase().includes(q));
});

const visibleTaskFiles = computed(() => {
  const q = taskQuery.value.trim().toLowerCase();
  if (!q) return taskFiles.value;
  return taskFiles.value.filter((item) => {
    const name = String(item.file_name || '').toLowerCase();
    const task = String(item.task?.title || '').toLowerCase();
    return name.includes(q) || task.includes(q);
  });
});

const noProjectMatch = computed(() => (
  Boolean(projectQuery.value.trim()) && !visibleFolders.value.length && !visibleFiles.value.length
));

const noTaskMatch = computed(() => Boolean(taskQuery.value.trim()) && !visibleTaskFiles.value.length);

watch(
  () => props.project?.id,
  () => {
    currentFolderId.value = null;
    loadDocuments();
    loadTaskFiles();
  },
);

onMounted(() => {
  loadDocuments();
  loadTaskFiles();
  document.addEventListener('mousedown', onDocMouseDown);
  document.addEventListener('keydown', onDocKeydown);
});

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onDocMouseDown);
  document.removeEventListener('keydown', onDocKeydown);
});

function onDocMouseDown(event) {
  if (menuOpen.value && menu.value && !menu.value.contains(event.target)) {
    closeMenu();
  }
}

function onDocKeydown(event) {
  if (event.key === 'Escape') {
    if (menuOpen.value) closeMenu();
    else if (formOpen.value && !saving.value) closeForm();
  }
}

async function loadDocuments() {
  if (!props.project?.id) return;
  loading.value = true;
  try {
    const params = {};
    if (currentFolderId.value) params.folder_id = currentFolderId.value;
    const { data } = await window.axios.get(`/api/project/${props.project.id}/documents`, { params });
    folder.value = data.folder || null;
    ancestors.value = data.ancestors || [];
    folders.value = data.folders || [];
    files.value = data.files || [];
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được tài liệu dự án.');
  } finally {
    loading.value = false;
  }
}

async function loadTaskFiles() {
  if (!props.project?.id) return;
  taskLoading.value = true;
  try {
    const { data } = await window.axios.get(`/api/project/${props.project.id}/task-attachments`);
    taskFiles.value = data.attachments || [];
  } catch {
    taskFiles.value = [];
  } finally {
    taskLoading.value = false;
  }
}

function openFolder(item) {
  currentFolderId.value = item.id;
  closeMenu();
  loadDocuments();
}

function goBack() {
  currentFolderId.value = folder.value?.parent_id ?? null;
  loadDocuments();
}

function goAncestor(item) {
  currentFolderId.value = item.id;
  loadDocuments();
}

function goRoot() {
  currentFolderId.value = null;
  loadDocuments();
}

function setProjectView(mode) {
  projectView.value = mode;
  localStorage.setItem(`${VIEW_KEY}.project`, mode);
}

function setTaskView(mode) {
  taskView.value = mode;
  localStorage.setItem(`${VIEW_KEY}.task`, mode);
}

function triggerUpload() {
  fileInput.value?.click();
}

async function onFileChange(event) {
  const picked = Array.from(event.target.files || []);
  event.target.value = '';
  await uploadFiles(picked);
}

async function uploadFiles(picked) {
  if (!picked.length || !props.canEdit) return;
  uploading.value = true;
  try {
    for (const file of picked) {
      const fd = new FormData();
      fd.append('file', file);
      if (currentFolderId.value) fd.append('folder_id', String(currentFolderId.value));
      await window.axios.post(`/api/project/${props.project.id}/attachments`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
    }
    showClientToast('success', picked.length === 1 ? 'Đã tải tệp lên.' : `Đã tải ${picked.length} tệp lên.`);
    await loadDocuments();
    emit('changed');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải lên được tệp.');
  } finally {
    uploading.value = false;
  }
}

function onDragOver(event) {
  if (!props.canEdit) return;
  event.preventDefault();
  dragging.value = true;
}

function onDragLeave(event) {
  if (event.currentTarget.contains(event.relatedTarget)) return;
  dragging.value = false;
}

function onDrop(event) {
  event.preventDefault();
  dragging.value = false;
  uploadFiles(Array.from(event.dataTransfer?.files || []));
}

function openCreateFolder() {
  formMode.value = 'folder';
  formName.value = '';
  formDriveUrl.value = '';
  renameTarget.value = null;
  formOpen.value = true;
}

function openAddLink() {
  formMode.value = 'link';
  formName.value = '';
  formDriveUrl.value = '';
  renameTarget.value = null;
  formOpen.value = true;
}

function openRename(kind, item) {
  formMode.value = kind === 'folder' ? 'rename-folder' : 'rename-file';
  formName.value = kind === 'folder' ? item.name : (item.original_name || item.url || '');
  formDriveUrl.value = '';
  renameTarget.value = { kind, item };
  formOpen.value = true;
  closeMenu();
}

function closeForm() {
  if (saving.value) return;
  formOpen.value = false;
  renameTarget.value = null;
}

const formTitle = computed(() => {
  if (formMode.value === 'link') return 'Thêm link Drive';
  if (formMode.value === 'rename-folder') return 'Đổi tên thư mục';
  if (formMode.value === 'rename-file') return 'Đổi tên tệp';
  return 'Tạo thư mục';
});

const formSubmitLabel = computed(() => {
  if (formMode.value === 'link') return 'Thêm link';
  if (formMode.value.startsWith('rename')) return 'Lưu';
  return 'Tạo thư mục';
});

async function submitForm() {
  if (saving.value) return;
  saving.value = true;
  try {
    if (formMode.value === 'folder') {
      const { data } = await window.axios.post(`/api/project/${props.project.id}/folders`, {
        name: formName.value.trim(),
        parent_id: currentFolderId.value,
      });
      folders.value = [...folders.value, data.folder].sort((a, b) => a.name.localeCompare(b.name, 'vi'));
      showClientToast('success', 'Đã tạo thư mục.');
    } else if (formMode.value === 'link') {
      const url = formDriveUrl.value.trim();
      try {
        new URL(url);
      } catch {
        showClientToast('error', 'Link Google Drive không hợp lệ.');
        return;
      }
      const payload = { url };
      if (currentFolderId.value) payload.folder_id = currentFolderId.value;
      const { data } = await window.axios.post(`/api/project/${props.project.id}/attachments`, payload);
      files.value = [data.attachment, ...files.value];
      showClientToast('success', 'Đã thêm link Google Drive.');
      emit('changed');
    } else if (formMode.value === 'rename-folder' && renameTarget.value) {
      const { data } = await window.axios.put(
        `/api/project/${props.project.id}/folders/${renameTarget.value.item.id}`,
        { name: formName.value.trim() },
      );
      folders.value = folders.value.map((item) => (item.id === data.folder.id ? data.folder : item));
      showClientToast('success', 'Đã đổi tên thư mục.');
    } else if (formMode.value === 'rename-file' && renameTarget.value) {
      const { data } = await window.axios.put(
        `/api/project/${props.project.id}/attachments/${renameTarget.value.item.id}`,
        { original_name: formName.value.trim() },
      );
      files.value = files.value.map((item) => (item.id === data.attachment.id ? data.attachment : item));
      showClientToast('success', 'Đã đổi tên tệp.');
      emit('changed');
    }
    formOpen.value = false;
    renameTarget.value = null;
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không lưu được.');
  } finally {
    saving.value = false;
  }
}

function askDelete(kind, item) {
  confirmTarget.value = { kind, item };
  confirmOpen.value = true;
  closeMenu();
}

async function confirmDelete() {
  const target = confirmTarget.value;
  if (!target) return;
  saving.value = true;
  try {
    if (target.kind === 'folder') {
      await window.axios.delete(`/api/project/${props.project.id}/folders/${target.item.id}`);
      folders.value = folders.value.filter((item) => item.id !== target.item.id);
      showClientToast('success', 'Đã xoá thư mục.');
    } else {
      await window.axios.delete(`/api/project/${props.project.id}/attachments/${target.item.id}`);
      files.value = files.value.filter((item) => item.id !== target.item.id);
      showClientToast('success', 'Đã xoá tệp đính kèm.');
      emit('changed');
    }
    confirmOpen.value = false;
    confirmTarget.value = null;
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không xoá được.');
  } finally {
    saving.value = false;
  }
}

async function toggleMenu(event, kind, item) {
  event.preventDefault();
  event.stopPropagation();
  if (menuOpen.value && menuItem.value === item && menuKind.value === kind) {
    closeMenu();
    return;
  }
  menuKind.value = kind;
  menuItem.value = item;
  menuOpen.value = true;
  menuPos.value = { x: event.clientX, y: event.clientY };
  await nextTick();
  const el = menu.value;
  const pad = 8;
  const w = el?.offsetWidth || 176;
  const h = el?.offsetHeight || 160;
  menuPos.value = {
    x: Math.min(Math.max(pad, event.clientX), Math.max(pad, window.innerWidth - w - pad)),
    y: Math.min(Math.max(pad, event.clientY), Math.max(pad, window.innerHeight - h - pad)),
  };
}

function closeMenu() {
  menuOpen.value = false;
  menuItem.value = null;
}

function previewPayload(item) {
  return {
    id: item.id,
    name: fileName(item),
    original_name: item.original_name,
    file_name: item.file_name,
    file_url: item.file_url,
    url: item.url,
    kind: item.kind,
    mime_type: item.mime_type,
  };
}

function isPreviewable(item) {
  return Boolean(item && item.kind !== 'drive_link' && (item.file_url || item.url));
}

function openPreview(item, list = []) {
  closeMenu();
  if (!item) return;
  if (item.kind === 'drive_link' && item.url) {
    window.open(item.url, '_blank', 'noopener');
    return;
  }
  previewFiles.value = (list || []).filter(isPreviewable).map(previewPayload);
  previewFile.value = previewPayload(item);
  previewOpen.value = true;
}

function openItem(kind, item) {
  closeMenu();
  if (kind === 'folder') {
    openFolder(item);
    return;
  }
  openPreview(item, visibleFiles.value);
}

function openExternal(item) {
  closeMenu();
  const href = item.kind === 'drive_link' ? item.url : item.file_url;
  if (href) window.open(href, '_blank', 'noopener');
}

function downloadItem(item) {
  closeMenu();
  const href = item.kind === 'drive_link' ? item.url : item.file_url;
  if (!href) return;
  const link = document.createElement('a');
  link.href = href;
  link.download = item.original_name || '';
  link.target = '_blank';
  link.rel = 'noopener';
  document.body.appendChild(link);
  link.click();
  link.remove();
}

function goTask(taskId) {
  if (!taskId) return;
  router.push({ name: 'manager.project.tasks.detail', params: { id: taskId } });
}

function openTaskFile(item) {
  if (item.file_url) {
    openPreview(item, visibleTaskFiles.value);
    return;
  }
  goTask(item.task?.id);
}

function fileName(item) {
  return item.original_name || item.file_name || item.url || 'Tệp';
}

function fileExt(name) {
  const match = String(name || '').match(/\.([a-z0-9]+)$/i);
  return match ? match[1].toUpperCase() : 'FILE';
}

function fileTone(name, kind) {
  if (kind === 'drive_link') return 'info';
  const ext = fileExt(name).toLowerCase();
  if (['zip', 'rar', '7z'].includes(ext)) return 'gold';
  if (['pdf'].includes(ext)) return 'danger';
  if (['doc', 'docx'].includes(ext)) return 'info';
  if (['xls', 'xlsx', 'csv'].includes(ext)) return 'success';
  if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].includes(ext)) return 'tertiary';
  if (['ppt', 'pptx'].includes(ext)) return 'warning';
  return 'neutral';
}

function fileIcon(name, kind) {
  if (kind === 'drive_link') return 'link';
  const ext = fileExt(name).toLowerCase();
  if (['xls', 'xlsx', 'csv'].includes(ext)) return 'fileSpreadsheet';
  if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].includes(ext)) return 'camera';
  if (['zip', 'rar', '7z'].includes(ext)) return 'sticker';
  return 'fileText';
}

function isImage(item) {
  if (item.kind === 'image') return Boolean(item.file_url);
  const mime = String(item.mime_type || '');
  if (mime.startsWith('image/')) return Boolean(item.file_url);
  return ['PNG', 'JPG', 'JPEG', 'GIF', 'WEBP', 'SVG'].includes(fileExt(fileName(item)));
}

function formatSize(bytes) {
  if (!bytes) return '';
  if (bytes < 1024) return `${bytes} B`;
  const kb = bytes / 1024;
  if (kb < 1024) return `${kb < 10 ? kb.toFixed(1) : Math.round(kb)} KB`;
  const mb = kb / 1024;
  return `${mb < 10 ? mb.toFixed(2) : mb.toFixed(1)} MB`;
}

function formatDocDate(iso) {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  const pad = (n) => String(n).padStart(2, '0');
  return `${pad(date.getHours())}:${pad(date.getMinutes())} ${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()}`;
}

function truncateName(name, max = 22) {
  const text = String(name || '');
  if (text.length <= max) return text;
  return `${text.slice(0, max - 1)}…`;
}

const confirmTitle = computed(() => (
  confirmTarget.value?.kind === 'folder' ? 'Xoá thư mục' : 'Xoá tệp đính kèm'
));
const confirmDescription = computed(() => {
  const target = confirmTarget.value;
  if (!target) return '';
  if (target.kind === 'folder') {
    return `Xoá thư mục “${target.item.name}” và toàn bộ tệp bên trong? Thao tác này không hoàn tác được.`;
  }
  return `Xoá “${fileName(target.item)}”? Thao tác này không hoàn tác được.`;
});

defineExpose({ triggerUpload, openCreateFolder, openAddLink, uploading });
</script>

<template>
  <div class="docs">
    <section
      class="docs__pane"
      :class="{ 'docs__pane--drop': dragging }"
      @dragover="onDragOver"
      @dragleave="onDragLeave"
      @drop="onDrop"
    >
      <header class="docs__head">
        <div class="docs__title-wrap">
          <button
            v-if="folder"
            type="button"
            class="docs__back"
            aria-label="Quay lại thư mục trước"
            @click="goBack"
          >
            <AppIcon name="chevronLeft" :size="16" />
          </button>
          <h3 class="docs__title">{{ title }}</h3>
        </div>
        <div class="docs__actions docs__actions--always">
          <button
            type="button"
            class="docs__icon-btn"
            :aria-label="projectView === 'grid' ? 'Hiển thị dạng danh sách' : 'Hiển thị dạng lưới'"
            @click="setProjectView(projectView === 'grid' ? 'list' : 'grid')"
          >
            <AppIcon :name="projectView === 'grid' ? 'layoutList' : 'layoutGrid'" :size="16" :stroke-width="1.75" />
          </button>
        </div>
        <input ref="fileInput" type="file" multiple class="docs__hidden" @change="onFileChange" />
      </header>

      <nav v-if="folder" class="docs__crumb" aria-label="Đường dẫn thư mục">
        <button type="button" class="docs__crumb-btn" @click="goRoot">Tài liệu dự án</button>
        <template v-for="item in ancestors" :key="item.id">
          <span class="docs__crumb-sep">/</span>
          <button type="button" class="docs__crumb-btn" @click="goAncestor(item)">{{ item.name }}</button>
        </template>
        <span class="docs__crumb-sep">/</span>
        <span class="docs__crumb-now">{{ folder.name }}</span>
      </nav>

      <div class="docs__toolbar">
        <label class="docs__search">
          <AppIcon name="search" :size="15" :stroke-width="1.75" />
          <input
            v-model="projectQuery"
            type="search"
            class="docs__search-input"
            placeholder="Tìm thư mục hoặc tệp…"
          >
        </label>
      </div>

      <div class="docs__body hide-scrollbar">
        <p v-if="loading" class="docs__status">Đang tải tài liệu…</p>
        <div v-else-if="isEmptyProject" class="docs__empty">
          <span class="docs__empty-icon"><AppIcon name="folder" :size="28" :stroke-width="1.5" /></span>
          <span class="docs__empty-title">Chưa có tài liệu dự án</span>
          <span class="docs__empty-hint">
            {{ canEdit ? 'Tạo thư mục để sắp xếp, hoặc kéo tệp vào khung này để tải lên.' : 'Dự án này chưa có thư mục hay tệp nào.' }}
          </span>
          <div v-if="canEdit" class="docs__empty-actions">
            <button type="button" class="docs__btn docs__btn--ghost" @click="openCreateFolder">Tạo thư mục</button>
            <button type="button" class="docs__btn" :disabled="uploading" @click="triggerUpload">Tải tệp lên</button>
          </div>
        </div>
        <div v-else-if="noProjectMatch" class="docs__empty">
          <span class="docs__empty-icon"><AppIcon name="search" :size="24" :stroke-width="1.5" /></span>
          <span class="docs__empty-title">Không tìm thấy kết quả</span>
          <span class="docs__empty-hint">Thử từ khóa khác, hoặc xóa ô tìm để xem toàn bộ tài liệu.</span>
        </div>
        <template v-else>
          <div class="docs__block">
            <h4 class="docs__block-title">Thư mục</h4>
            <div v-if="visibleFolders.length" class="docs__folders">
              <article
                v-for="item in visibleFolders"
                :key="`f-${item.id}`"
                class="docs__folder"
                @click="openFolder(item)"
                @contextmenu="toggleMenu($event, 'folder', item)"
              >
                <span class="docs__folder-mark" aria-hidden="true" />
                <div class="docs__copy">
                  <span class="docs__name">{{ item.name }}</span>
                  <span class="docs__sub">
                    <span>{{ formatDocDate(item.created_at) }}</span>
                    <span v-if="item.size_bytes">{{ formatSize(item.size_bytes) }}</span>
                  </span>
                </div>
                <button
                  v-if="canEdit"
                  type="button"
                  class="docs__more"
                  aria-label="Thao tác"
                  @click="toggleMenu($event, 'folder', item)"
                >
                  <AppIcon name="moreVertical" :size="16" />
                </button>
              </article>
            </div>
            <p v-else class="docs__placeholder">
              {{ projectQuery.trim() ? 'Không có thư mục khớp từ khóa.' : 'Chưa có thư mục trong đây.' }}
              <button v-if="canEdit && !projectQuery.trim()" type="button" class="docs__placeholder-link" @click="openCreateFolder">Tạo thư mục</button>
            </p>
          </div>

          <div class="docs__block">
            <h4 class="docs__block-title">Tệp</h4>
            <div v-if="visibleFiles.length" class="docs__grid" :class="`docs__grid--${projectView}`">
              <article
                v-for="item in visibleFiles"
                :key="`a-${item.id}`"
                class="docs__card"
                @click="openItem('file', item)"
                @contextmenu="toggleMenu($event, 'file', item)"
              >
                <div class="docs__thumb" :class="`docs__thumb--${fileTone(fileName(item), item.kind)}`">
                  <img v-if="isImage(item)" :src="item.file_url" alt="" class="docs__preview" />
                  <span v-else class="docs__ext">{{ item.kind === 'drive_link' ? 'URL' : fileExt(fileName(item)) }}</span>
                </div>
                <div class="docs__meta">
                  <span class="docs__type" :class="`docs__type--${fileTone(fileName(item), item.kind)}`">
                    <AppIcon :name="fileIcon(fileName(item), item.kind)" :size="14" :stroke-width="1.75" />
                  </span>
                  <div class="docs__copy">
                    <span class="docs__name">{{ truncateName(fileName(item)) }}</span>
                    <span class="docs__sub">
                      <span>{{ formatDocDate(item.created_at) }}</span>
                      <span v-if="item.size_bytes">{{ formatSize(item.size_bytes) }}</span>
                    </span>
                  </div>
                  <button
                    type="button"
                    class="docs__more"
                    aria-label="Thao tác"
                    @click="toggleMenu($event, 'file', item)"
                  >
                    <AppIcon name="moreVertical" :size="16" />
                  </button>
                </div>
              </article>
            </div>
            <p v-else class="docs__placeholder">
              {{ projectQuery.trim() ? 'Không có tệp khớp từ khóa.' : 'Chưa có tệp trong đây. Kéo tệp vào khung hoặc bấm Tải tệp.' }}
              <button v-if="canEdit && !projectQuery.trim()" type="button" class="docs__placeholder-link" @click="triggerUpload">Tải tệp</button>
            </p>
          </div>
        </template>
      </div>
      <div v-if="dragging && canEdit" class="docs__drop-hint">Thả tệp vào đây để tải lên</div>
    </section>

    <section class="docs__pane docs__pane--side">
      <header class="docs__head">
        <h3 class="docs__title">Đính kèm công việc</h3>
        <div class="docs__actions docs__actions--always">
          <button
            type="button"
            class="docs__icon-btn"
            :aria-label="taskView === 'grid' ? 'Hiển thị dạng danh sách' : 'Hiển thị dạng lưới'"
            @click="setTaskView(taskView === 'grid' ? 'list' : 'grid')"
          >
            <AppIcon :name="taskView === 'grid' ? 'layoutList' : 'layoutGrid'" :size="16" :stroke-width="1.75" />
          </button>
        </div>
      </header>
      <div class="docs__toolbar">
        <label class="docs__search">
          <AppIcon name="search" :size="15" :stroke-width="1.75" />
          <input
            v-model="taskQuery"
            type="search"
            class="docs__search-input"
            placeholder="Tìm tệp hoặc công việc…"
          >
        </label>
      </div>
      <div class="docs__body hide-scrollbar">
        <p v-if="taskLoading" class="docs__status">Đang tải đính kèm công việc…</p>
        <div v-else-if="!taskFiles.length" class="docs__empty">
          <span class="docs__empty-icon"><AppIcon name="paperclip" :size="28" :stroke-width="1.5" /></span>
          <span class="docs__empty-title">Không có đính kèm công việc</span>
          <span class="docs__empty-hint">Tệp tải lên từ từng công việc sẽ xuất hiện ở đây.</span>
        </div>
        <div v-else-if="noTaskMatch" class="docs__empty">
          <span class="docs__empty-icon"><AppIcon name="search" :size="24" :stroke-width="1.5" /></span>
          <span class="docs__empty-title">Không tìm thấy kết quả</span>
          <span class="docs__empty-hint">Thử tên tệp hoặc tên công việc khác.</span>
        </div>
        <div v-else class="docs__grid" :class="`docs__grid--${taskView}`">
          <article
            v-for="item in visibleTaskFiles"
            :key="`t-${item.id}`"
            class="docs__card"
            @click="openTaskFile(item)"
          >
            <div class="docs__thumb" :class="`docs__thumb--${fileTone(item.file_name)}`">
              <span class="docs__ext">{{ fileExt(item.file_name) }}</span>
            </div>
            <div class="docs__meta">
              <span class="docs__type" :class="`docs__type--${fileTone(item.file_name)}`">
                <AppIcon :name="fileIcon(item.file_name)" :size="14" :stroke-width="1.75" />
              </span>
              <div class="docs__copy">
                <span class="docs__name">{{ truncateName(item.file_name) }}</span>
                <span class="docs__sub">
                  <button
                    v-if="item.task"
                    type="button"
                    class="docs__task-link"
                    @click.stop="goTask(item.task.id)"
                  >{{ item.task.title }}</button>
                  <span>{{ formatDocDate(item.created_at) }}</span>
                  <span v-if="item.file_size">{{ formatSize(item.file_size) }}</span>
                </span>
              </div>
            </div>
          </article>
        </div>
      </div>
    </section>

    <Teleport to="body">
      <div
        v-if="menuOpen && menuItem"
        ref="menu"
        class="docs__menu"
        role="menu"
        :style="{ left: `${menuPos.x}px`, top: `${menuPos.y}px` }"
      >
        <button type="button" class="docs__menu-item" role="menuitem" @click="openItem(menuKind, menuItem)">
          <AppIcon :name="menuKind === 'folder' ? 'folder' : 'eye'" :size="14" :stroke-width="1.75" />
          {{ menuKind === 'folder' ? 'Mở thư mục' : 'Xem trước' }}
        </button>
        <button
          v-if="menuKind === 'file'"
          type="button"
          class="docs__menu-item"
          role="menuitem"
          @click="downloadItem(menuItem)"
        >
          <AppIcon name="download" :size="14" :stroke-width="1.75" />
          Tải xuống
        </button>
        <button
          v-if="menuKind === 'file'"
          type="button"
          class="docs__menu-item"
          role="menuitem"
          @click="openExternal(menuItem)"
        >
          <AppIcon name="externalLink" :size="14" :stroke-width="1.75" />
          Mở tab mới
        </button>
        <button
          v-if="canEdit"
          type="button"
          class="docs__menu-item"
          role="menuitem"
          @click="openRename(menuKind, menuItem)"
        >
          <AppIcon name="pencil" :size="14" :stroke-width="1.75" />
          Đổi tên
        </button>
        <button
          v-if="canEdit"
          type="button"
          class="docs__menu-item docs__menu-item--danger"
          role="menuitem"
          @click="askDelete(menuKind, menuItem)"
        >
          <AppIcon name="trash" :size="14" :stroke-width="1.75" />
          Xoá
        </button>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="formOpen" class="docs__dialog" role="presentation" @mousedown.self="closeForm">
        <div
          class="docs__dialog-panel"
          role="dialog"
          aria-modal="true"
          aria-labelledby="docs-form-title"
        >
          <div class="docs__dialog-head">
            <span class="docs__dialog-icon" aria-hidden="true">
              <AppIcon
                :name="formMode === 'link' ? 'link' : formMode.startsWith('rename') ? 'pencil' : 'folderPlus'"
                :size="22"
                :stroke-width="1.75"
              />
            </span>
            <h2 id="docs-form-title" class="docs__dialog-title">{{ formTitle }}</h2>
            <button type="button" class="docs__icon-btn" aria-label="Đóng" :disabled="saving" @click="closeForm">
              <AppIcon name="close" :size="16" />
            </button>
          </div>
          <div class="docs__dialog-body hide-scrollbar">
            <form class="docs__form" @submit.prevent="submitForm">
              <label v-if="formMode !== 'link'" class="docs__field" :class="{ 'docs__field--full': formMode !== 'folder' }">
                <span class="docs__label">{{ formMode === 'rename-file' ? 'Tên tệp' : 'Tên thư mục' }}</span>
                <input
                  v-model="formName"
                  type="text"
                  class="docs__input"
                  maxlength="255"
                  required
                  :placeholder="formMode === 'rename-file' ? 'Ví dụ: hop-dong.pdf' : 'Ví dụ: Hợp đồng'"
                >
              </label>
              <label v-if="formMode === 'folder'" class="docs__field">
                <span class="docs__label">Thư mục cha</span>
                <input :value="parentLabel" type="text" class="docs__input" readonly tabindex="-1" />
              </label>
              <label v-if="formMode === 'link'" class="docs__field docs__field--full">
                <span class="docs__label">Link Google Drive</span>
                <input v-model="formDriveUrl" type="url" class="docs__input" placeholder="https://drive.google.com/…" required />
              </label>
            </form>
          </div>
          <div class="docs__dialog-actions">
            <button type="button" class="docs__btn docs__btn--ghost" :disabled="saving" @click="closeForm">Huỷ</button>
            <button type="button" class="docs__btn" :disabled="saving" @click="submitForm">
              {{ saving ? 'Đang lưu…' : formSubmitLabel }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <ConfirmDialog
      :open="confirmOpen"
      :title="confirmTitle"
      :description="confirmDescription"
      confirm-label="Xoá"
      danger
      :loading="saving"
      @update:open="confirmOpen = $event"
      @confirm="confirmDelete"
    />

    <FilePreviewDialog
      v-model:open="previewOpen"
      :file="previewFile"
      :files="previewFiles"
      @update:file="previewFile = $event"
    />
  </div>
</template>

<style scoped>
.docs {
  display: grid;
  grid-template-columns: minmax(0, 8fr) minmax(16rem, 4fr);
  align-items: stretch;
  min-height: 38rem;
  height: calc(100vh - 10.5rem);
  gap: var(--space-4);
}

.docs__pane {
  position: relative;
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
  height: 100%;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.docs__pane--drop {
  box-shadow: inset 0 0 0 2px var(--color-primary);
}

.docs__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  flex-shrink: 0;
  min-height: 3.25rem;
  padding: 0.875rem 1rem 0.875rem 1rem;
}

.docs__title-wrap {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-width: 0;
}

.docs__title {
  margin: 0;
  color: var(--color-text);
  font-size: 1rem;
  font-weight: 650;
}

.docs__back {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text);
  cursor: pointer;
}

.docs__back:hover {
  background: var(--color-surface-muted);
}

.docs__actions {
  display: flex;
  align-items: center;
  gap: var(--space-1);
}

.docs__text-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  height: 1.75rem;
  padding: 0 0.55rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.docs__text-btn:hover:not(:disabled) {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.docs__text-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.docs__toolbar {
  flex-shrink: 0;
  padding: 0 1rem 0.75rem;
}

.docs__search {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 2.25rem;
  padding: 0 0.75rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.docs__search-input {
  flex: 1;
  min-width: 0;
  height: 2.25rem;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
}

.docs__search-input::placeholder {
  color: var(--color-text-muted);
}

.docs__icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.docs__icon-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.docs__icon-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.docs__crumb {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.25rem;
  flex-shrink: 0;
  padding: 0 1rem 0.5rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.docs__crumb-btn,
.docs__crumb-now {
  padding: 0;
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  font: inherit;
}

.docs__crumb-btn {
  cursor: pointer;
}

.docs__crumb-btn:hover {
  color: var(--color-primary);
}

.docs__crumb-now {
  color: var(--color-text);
}

.docs__body {
  flex: 1;
  min-height: 0;
  padding: 0 1rem 1rem;
  overflow: auto;
}

.docs__block + .docs__block {
  margin-top: var(--space-4);
}

.docs__block-title {
  margin: 0 0 var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 650;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.docs__grid {
  display: grid;
  gap: var(--space-3);
}

.docs__folders {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(14.5rem, 1fr));
  gap: 0.5rem;
}

.docs__folder {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  min-width: 0;
  padding: 0.45rem 0.55rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
}

.docs__folder:hover {
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-gold) 35%, var(--color-border));
}

.docs__folder .docs__name {
  font-weight: 600;
}

.docs__folder .docs__more {
  opacity: 1;
}

.docs__grid--grid {
  grid-template-columns: repeat(auto-fill, minmax(11.5rem, 1fr));
}

.docs__grid--list {
  grid-template-columns: minmax(0, 1fr);
}

.docs__card {
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow: hidden;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
}

.docs__grid--list .docs__card {
  flex-direction: row;
  align-items: center;
}

.docs__card:hover {
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 35%, var(--color-border));
}

.docs__thumb {
  display: flex;
  align-items: center;
  justify-content: center;
  aspect-ratio: 16 / 10;
  background: var(--color-surface-muted);
  overflow: hidden;
}

.docs__grid--list .docs__thumb {
  width: 4.5rem;
  height: 3.25rem;
  aspect-ratio: auto;
  flex-shrink: 0;
}

.docs__folder-mark {
  flex-shrink: 0;
  width: 1.75rem;
  height: 1.3rem;
  border-radius: 0 0.2rem 0.2rem 0.2rem;
  background: linear-gradient(180deg, var(--color-gold-300), var(--color-gold-500));
}

.docs__folder-mark::before {
  content: '';
  display: block;
  width: 0.7rem;
  height: 0.28rem;
  margin: -0.2rem 0 0 0;
  border-radius: 0.18rem 0.18rem 0 0;
  background: var(--color-gold-400);
}

.docs__thumb--danger { background: var(--color-danger-tint-bg); color: var(--color-danger-tint-fg); }
.docs__thumb--success { background: var(--color-success-tint-bg); color: var(--color-success-tint-fg); }
.docs__thumb--info { background: var(--color-info-tint-bg); color: var(--color-info-tint-fg); }
.docs__thumb--tertiary { background: var(--color-tertiary-50); color: var(--color-tertiary-700); }
.docs__thumb--gold { background: var(--color-gold-50); color: var(--color-gold-800); }
.docs__thumb--warning { background: var(--color-warning-tint-bg); color: var(--color-warning-tint-fg); }
.docs__thumb--neutral { background: var(--color-surface-muted); color: var(--color-text-muted); }

.docs__preview {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.docs__ext {
  font-size: 0.8125rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.docs__placeholder {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.35rem 0.5rem;
  margin: 0;
  padding: 0.75rem 0.15rem;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-style: italic;
}

.docs__placeholder-link {
  padding: 0;
  border: none;
  background: transparent;
  color: var(--color-primary);
  font: inherit;
  font-style: normal;
  font-weight: 600;
  cursor: pointer;
}

.docs__drop-hint {
  position: absolute;
  inset: 0.5rem;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-primary-surface) 88%, transparent);
  color: var(--color-primary);
  font-size: 0.875rem;
  font-weight: 650;
  pointer-events: none;
}

.docs__meta {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  padding: 0.625rem 0.75rem 0.75rem;
}

.docs__grid--list .docs__meta {
  flex: 1;
  align-items: center;
  padding: 0.5rem 0.75rem;
}

.docs__type {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.docs__type--folder { background: var(--color-gold-50); color: var(--color-gold-700); }
.docs__type--danger { background: var(--color-danger-tint-bg); color: var(--color-danger-tint-fg); }
.docs__type--success { background: var(--color-success-tint-bg); color: var(--color-success-tint-fg); }
.docs__type--info { background: var(--color-info-tint-bg); color: var(--color-info-tint-fg); }
.docs__type--tertiary { background: var(--color-tertiary-50); color: var(--color-tertiary-700); }
.docs__type--gold { background: var(--color-gold-50); color: var(--color-gold-800); }
.docs__type--warning { background: var(--color-warning-tint-bg); color: var(--color-warning-tint-fg); }

.docs__copy {
  flex: 1;
  min-width: 0;
}

.docs__name {
  display: block;
  overflow: hidden;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
  white-space: nowrap;
}

.docs__sub {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.15rem;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
}

.docs__task-link {
  padding: 0;
  border: none;
  background: transparent;
  color: var(--color-primary);
  font: inherit;
  cursor: pointer;
}

.docs__more {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  opacity: 0;
  cursor: pointer;
}

.docs__card .docs__more,
.docs__folder .docs__more,
.docs__more:focus-visible {
  opacity: 1;
}

.docs__status,
.docs__empty {
  margin: 0;
  padding: 2.5rem 1rem;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  text-align: center;
}

.docs__empty {
  display: flex;
  flex: 1;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  min-height: 16rem;
}

.docs__empty-title {
  color: var(--color-text);
  font-weight: 650;
}

.docs__empty-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: var(--space-2);
  margin-top: var(--space-2);
}

.docs__empty-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 3.5rem;
  height: 3.5rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.docs__empty-hint {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.docs__hidden {
  display: none;
}

.docs__menu {
  position: fixed;
  z-index: 320;
  min-width: 11rem;
  padding: 0.25rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.docs__menu-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.5rem 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  text-align: left;
  cursor: pointer;
}

.docs__menu-item:hover {
  background: var(--color-surface-muted);
}

.docs__menu-item--danger {
  color: var(--color-danger);
}

.docs__dialog {
  position: fixed;
  inset: 0;
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.docs__dialog-panel {
  width: min(40rem, calc(100vw - 2.5rem));
  max-height: calc(100vh - 2.5rem);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.docs__dialog-head,
.docs__dialog-actions {
  flex-shrink: 0;
}

.docs__dialog-head {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: 1.25rem 1.5rem 1rem;
  box-shadow: 0 1px 0 var(--color-border);
}

.docs__dialog-icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  color: var(--color-primary);
}

.docs__dialog-title {
  flex: 1;
  min-width: 0;
  margin: 0;
  color: var(--color-text);
  font-size: 1.125rem;
  font-weight: 700;
}

.docs__dialog-body {
  flex: 1;
  min-height: 0;
  padding: 1.25rem 1.5rem;
}

.docs__form {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-4);
}

.docs__field {
  display: grid;
  gap: 0.375rem;
}

.docs__field--full {
  grid-column: 1 / -1;
}

.docs__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.docs__input {
  width: 100%;
  height: 2.25rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
}

.docs__input::placeholder {
  color: var(--color-text-muted);
}

.docs__dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  padding: 0.875rem 1.5rem 1.25rem;
  box-shadow: 0 -1px 0 var(--color-border);
}

.docs__btn {
  display: inline-flex;
  align-items: center;
  height: 2rem;
  padding: 0 0.875rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.docs__btn--ghost {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.docs__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 960px) {
  .docs {
    grid-template-columns: minmax(0, 1fr);
  }

  .docs {
    height: auto;
    min-height: 0;
  }

  .docs__pane,
  .docs__pane--side {
    height: auto;
    min-height: 24rem;
  }
}

@media (max-width: 640px) {
  .docs__form {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
