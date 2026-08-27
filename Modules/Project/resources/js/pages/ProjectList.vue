<script setup>
//
// Trang danh sách Dự án (Project module). Theo mẫu vàng data-table
// (ActivityLog.vue: TablePagesBar trên/dưới + cột kéo nắm mép + panel chi
// tiết đẩy ngang), có điều chỉnh theo yêu cầu riêng của trang này:
// - Không có toolbar filter (trang này chưa cần) — chỉ giữ ô tìm kiếm,
//   đặt ngay trên PageHeader.
// - Nút "Thêm dự án" là dấu + cạnh title (PageHeader primaryAction).
// - Bảng nhóm theo Phòng ban thực hiện (executing_department, fallback về
//   owner_department khi không giao) — mỗi nhóm đóng/mở được.
// - Đủ cột dữ liệu backend đã trả (present() trong ProjectService); hiện/ẩn
//   cột qua nút Cột trên TablePagesBar (slot #settings), không tách nút riêng.
// - Gắn/bỏ nhãn ngay tại dòng trong cột Tên dự án (không cần vào trang sửa).
//
// Tạo/sửa dự án là 2 TRANG RIÊNG (ProjectCreate.vue / ProjectEdit.vue).
//
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import { showClientToast } from '@/lib/clientToast';
import { useDragScroll } from '@/composables/useDragScroll';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import ProjectLabelPicker from '../components/ProjectLabelPicker.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

// Toàn bộ cột có dữ liệu backend (ProjectService::present()). `always: true`
// = cột luôn hiện, không cho ẩn (Tên dự án — cột định danh chính).
const ALL_COLUMNS = [
  { key: 'code', label: 'Mã dự án' },
  { key: 'name', label: 'Tên dự án', always: true },
  { key: 'type', label: 'Loại dự án' },
  { key: 'owner_department', label: 'Phòng ban sở hữu' },
  { key: 'executing_department', label: 'Phòng ban thực hiện' },
  { key: 'lead', label: 'Phụ trách chính' },
  { key: 'member_count', label: 'Số người thực hiện' },
  { key: 'status', label: 'Trạng thái' },
  { key: 'importance', label: 'Mức độ quan trọng' },
  { key: 'start_date', label: 'Ngày bắt đầu' },
  { key: 'end_date', label: 'Ngày kết thúc' },
  { key: 'duration_days', label: 'Số ngày thực hiện' },
  { key: 'progress_method', label: 'Cách tính tiến độ' },
  { key: 'progress', label: 'Tiến độ' },
  { key: 'evaluation_score', label: 'Điểm đánh giá' },
  { key: 'description', label: 'Mô tả' },
  { key: 'creator', label: 'Người tạo' },
  { key: 'created_at', label: 'Ngày tạo' },
  { key: 'is_following', label: 'Bạn theo dõi' },
];

const DEFAULT_VISIBLE_KEYS = [
  'code',
  'name',
  'type',
  'owner_department',
  'executing_department',
  'lead',
  'status',
  'importance',
  'start_date',
  'end_date',
  'progress',
  'evaluation_score',
];

const TABS = [
  { key: 'all', label: 'Tất cả' },
  { key: 'in_progress', label: 'Đang thực hiện' },
  { key: 'completed', label: 'Hoàn thành' },
  { key: 'on_hold', label: 'Tạm dừng' },
  { key: 'planning', label: 'Đang chờ' },
  { key: 'cancelled', label: 'Đã huỷ' },
  { key: 'following', label: 'Bạn theo dõi' },
  { key: 'my_tasks', label: 'Bạn thực hiện' },
  { key: 'my_department', label: 'Phòng ban bạn' },
];

const MIN_COL_PX = 96;
const UNGROUPED_KEY = '__ungrouped__';

const projects = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 20 });
const loading = ref(false);
const selected = ref(null);

const options = reactive({
  type: [],
  status: [],
  importance: [],
  progress_method: [],
  scope_type: [],
});
const departments = ref([]);
const allLabels = ref([]);

const query = ref('');
const perPage = ref(20);
const activeTab = ref('all');
const tabCounts = ref({});

const tableWrap = ref(null);
const resizing = ref(false);
useDragScroll(tableWrap, { isBlocked: () => resizing.value });
const columnWidths = reactive(loadColumnWidths());

const columnOrder = ref(loadColumnOrder());
const visibleColumnKeys = ref(loadVisibleColumnKeys());

const COLUMNS = computed(() => {
  const byKey = new Map(ALL_COLUMNS.map((c) => [c.key, c]));
  return columnOrder.value.map((key) => byKey.get(key)).filter(Boolean);
});

const visibleColumns = computed(() => COLUMNS.value.filter((c) => c.always || visibleColumnKeys.value.includes(c.key)));

// ---------- Gắn nhãn ngay tại dòng ----------
const labelPickerProjectId = ref(null);

// ---------- Group theo phòng ban thực hiện ----------
const collapsedGroups = ref(new Set(loadCollapsedGroups()));

const avatarInput = ref(null);
const galleryInput = ref(null);
const driveUrlInput = ref('');
const attachmentInput = ref(null);
const avatarUploading = ref(false);
const attachmentUploading = ref(false);

const canManageSettings = computed(() => auth.can('project.manage_settings'));

const colSpan = computed(() => Math.max(visibleColumns.value.length, 1));

const tableWidthPx = computed(() => {
  const sum = visibleColumns.value.reduce((total, col) => total + (Number(columnWidths[col.key]) || 0), 0);
  return sum > 0 ? `${sum}px` : '100%';
});

const groupedProjects = computed(() => {
  const groups = new Map();
  for (const project of projects.value) {
    const dept = project.executing_department || project.owner_department;
    const key = dept ? `dept-${dept.id}` : UNGROUPED_KEY;
    const label = dept ? dept.name : 'Chưa xác định phòng ban';
    if (!groups.has(key)) groups.set(key, { key, label, projects: [] });
    groups.get(key).projects.push(project);
  }
  return Array.from(groups.values()).sort((a, b) => a.label.localeCompare(b.label, 'vi'));
});

function isGroupCollapsed(key) {
  return collapsedGroups.value.has(key);
}

function toggleGroup(key) {
  const next = new Set(collapsedGroups.value);
  if (next.has(key)) next.delete(key);
  else next.add(key);
  collapsedGroups.value = next;
  saveCollapsedGroups();
}

function loadCollapsedGroups() {
  try {
    const raw = localStorage.getItem('project-list-collapsed-groups');
    const parsed = raw ? JSON.parse(raw) : [];
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

function saveCollapsedGroups() {
  try {
    localStorage.setItem('project-list-collapsed-groups', JSON.stringify(Array.from(collapsedGroups.value)));
  } catch {
    // Bỏ qua nếu trình duyệt chặn localStorage.
  }
}

function loadColumnOrder() {
  try {
    const raw = localStorage.getItem('project-list-col-order');
    const parsed = raw ? JSON.parse(raw) : null;
    if (Array.isArray(parsed) && parsed.length) {
      const known = new Set(ALL_COLUMNS.map((c) => c.key));
      const cleaned = parsed.filter((k) => known.has(k));
      // Thêm cột mới (nếu code thêm cột sau này) vào cuối, tránh mất cột.
      for (const c of ALL_COLUMNS) {
        if (!cleaned.includes(c.key)) cleaned.push(c.key);
      }
      return cleaned;
    }
  } catch {
    // Bỏ qua.
  }
  return ALL_COLUMNS.map((c) => c.key);
}

function loadVisibleColumnKeys() {
  try {
    const raw = localStorage.getItem('project-list-visible-cols');
    const parsed = raw ? JSON.parse(raw) : null;
    if (Array.isArray(parsed)) return parsed;
  } catch {
    // Bỏ qua.
  }
  return DEFAULT_VISIBLE_KEYS.slice();
}

function saveVisibleColumnKeys() {
  try {
    localStorage.setItem('project-list-visible-cols', JSON.stringify(visibleColumnKeys.value));
  } catch {
    // Bỏ qua.
  }
}

function onColumnToggle(key, checked) {
  const col = ALL_COLUMNS.find((c) => c.key === key);
  if (col?.always) return;

  if (!checked) {
    const remaining = visibleColumns.value.filter((c) => c.key !== key).length;
    if (remaining < 1) {
      showClientToast('warning', 'Cần giữ ít nhất một cột trên bảng.');
      return;
    }
    visibleColumnKeys.value = visibleColumnKeys.value.filter((k) => k !== key);
  } else if (!visibleColumnKeys.value.includes(key)) {
    visibleColumnKeys.value = [...visibleColumnKeys.value, key];
  }
  saveVisibleColumnKeys();
}

function loadColumnWidths() {
  try {
    const raw = localStorage.getItem('project-list-col-widths');
    const parsed = raw ? JSON.parse(raw) : {};
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) return parsed;
  } catch {
    // Bỏ qua nếu trình duyệt chặn localStorage.
  }
  return {};
}

function saveColumnWidths() {
  try {
    localStorage.setItem('project-list-col-widths', JSON.stringify(columnWidths));
  } catch {
    // Bỏ qua.
  }
}

function colWidthStyle(key) {
  const width = columnWidths[key];
  return width ? `${width}px` : undefined;
}

function fitColumnsDefault() {
  const wrap = tableWrap.value;
  if (!wrap) return;
  const cols = visibleColumns.value;
  const evenWidth = Math.max(MIN_COL_PX, Math.floor(wrap.clientWidth / cols.length));
  cols.forEach((col) => {
    if (!columnWidths[col.key]) columnWidths[col.key] = evenWidth;
  });
}

function startResize(event, key) {
  const keys = visibleColumns.value.map((c) => c.key);
  const index = keys.indexOf(key);
  const neighbor = keys[index + 1] ?? keys[index - 1];
  if (!neighbor || neighbor === key) return;

  const towardNext = keys.indexOf(neighbor) > index;
  const startX = event.clientX;
  const startA = Number(columnWidths[key]) || MIN_COL_PX;
  const startB = Number(columnWidths[neighbor]) || MIN_COL_PX;
  const pair = startA + startB;

  resizing.value = true;

  function onMove(moveEvent) {
    const delta = (moveEvent.clientX - startX) * (towardNext ? 1 : -1);
    let nextA = Math.round(startA + delta);
    nextA = Math.min(Math.max(nextA, MIN_COL_PX), pair - MIN_COL_PX);
    columnWidths[key] = nextA;
    columnWidths[neighbor] = pair - nextA;
  }

  function onUp() {
    resizing.value = false;
    saveColumnWidths();
    window.removeEventListener('mousemove', onMove);
    window.removeEventListener('mouseup', onUp);
  }

  window.addEventListener('mousemove', onMove);
  window.addEventListener('mouseup', onUp);
}

function currentFilterParams() {
  return {
    q: query.value.trim() || undefined,
    tab: activeTab.value !== 'all' ? activeTab.value : undefined,
  };
}

async function loadOptions() {
  try {
    const { data } = await window.axios.get('/api/project/options');
    options.type = data.type ?? [];
    options.status = data.status ?? [];
    options.importance = data.importance ?? [];
    options.progress_method = data.progress_method ?? [];
    options.scope_type = data.scope_type ?? [];
  } catch {
    showClientToast('error', 'Không tải được danh mục dự án.');
  }
}

async function loadDepartments() {
  try {
    const { data } = await window.axios.get('/manager/departments');
    departments.value = data.departments ?? [];
  } catch {
    departments.value = [];
  }
}

async function loadLabels() {
  try {
    const { data } = await window.axios.get('/api/project/labels');
    allLabels.value = data.labels ?? [];
  } catch {
    allLabels.value = [];
  }
}

async function loadProjects(page = 1) {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/project', {
      params: { ...currentFilterParams(), page, per_page: perPage.value },
    });
    projects.value = data.projects ?? [];
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 20 };
    tabCounts.value = data.tab_counts ?? {};

    if (selected.value) {
      const fresh = projects.value.find((p) => p.id === selected.value.id);
      selected.value = fresh || null;
    }
    nextTick(fitColumnsDefault);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được danh sách dự án.');
  } finally {
    loading.value = false;
  }
}

function goPage(page) {
  if (page < 1 || page > meta.value.last_page || page === meta.value.current_page) return;
  loadProjects(page);
}

function tabCount(key) {
  return tabCounts.value[key] ?? 0;
}

function selectTab(key) {
  if (activeTab.value === key) return;
  activeTab.value = key;
  router.replace({ query: { ...route.query, tab: key === 'all' ? undefined : key } });
  loadProjects(1);
}

function typeLabel(value) {
  return options.type.find((o) => o.value === value)?.label || value || '—';
}

function statusLabel(value) {
  return options.status.find((o) => o.value === value)?.label || value || '—';
}

function importanceLabel(value) {
  return options.importance.find((o) => o.value === value)?.label || value || '—';
}

function progressMethodLabel(value) {
  return options.progress_method.find((o) => o.value === value)?.label || value || '—';
}

function statusDotClass(value) {
  if (value === 'completed') return 'proj-page__dot--success';
  if (value === 'cancelled') return 'proj-page__dot--danger';
  if (value === 'on_hold') return 'proj-page__dot--gold';
  if (value === 'in_progress') return 'proj-page__dot--tertiary';
  if (value === 'planning') return 'proj-page__dot--secondary';
  return 'proj-page__dot--neutral';
}

function importanceDotClass(value) {
  if (value === 'critical') return 'proj-page__dot--danger';
  if (value === 'high') return 'proj-page__dot--gold';
  if (value === 'low') return 'proj-page__dot--neutral';
  return 'proj-page__dot--tertiary';
}

function formatDate(value) {
  if (!value) return '—';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleDateString('vi-VN');
}

function inspect(project) {
  selected.value = project;
}

function closePanel() {
  selected.value = null;
}

function openCreatePage() {
  router.push({ name: 'manager.project.create' });
}

function openSettings() {
  router.push({ name: 'manager.project.settings' });
}

const followBusy = ref(false);

async function toggleFollow() {
  if (!selected.value || followBusy.value) return;
  followBusy.value = true;
  const project = selected.value;
  try {
    if (project.is_following) {
      const { data } = await window.axios.delete(`/api/project/${project.id}/follow`);
      project.is_following = data.is_following;
    } else {
      const { data } = await window.axios.post(`/api/project/${project.id}/follow`);
      project.is_following = data.is_following;
    }
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không cập nhật được theo dõi dự án.');
  } finally {
    followBusy.value = false;
  }
}

function openEditPage(project) {
  router.push({ name: 'manager.project.edit', params: { id: project.id } });
}

async function deleteProject(project) {
  if (!window.confirm(`Xoá dự án "${project.name}"? Thao tác này không thể hoàn tác.`)) return;
  try {
    await window.axios.delete(`/api/project/${project.id}`);
    showClientToast('success', 'Đã xoá dự án.');
    if (selected.value?.id === project.id) selected.value = null;
    await loadProjects(meta.value.current_page);
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không xoá được dự án.');
  }
}

// ---------- Gắn/bỏ nhãn ngay tại dòng (không cần vào trang Sửa) ----------

function onLabelCreated(label) {
  if (!allLabels.value.some((l) => l.id === label.id)) {
    allLabels.value.push(label);
  }
}

function openLabelPicker(project) {
  labelPickerProjectId.value = labelPickerProjectId.value === project.id ? null : project.id;
}

function closeLabelPicker() {
  labelPickerProjectId.value = null;
}

async function setProjectLabels(project, labelIds) {
  const previous = (project.labels || []).map((l) => l.id);
  try {
    const { data } = await window.axios.put(`/api/project/${project.id}`, { label_ids: labelIds });
    const index = projects.value.findIndex((p) => p.id === project.id);
    if (index !== -1) projects.value.splice(index, 1, data.project);
    if (selected.value?.id === project.id) selected.value = data.project;
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không cập nhật được nhãn dự án.');
    project.labels = previous.map((id) => allLabels.value.find((l) => l.id === id)).filter(Boolean);
  }
}

function triggerAvatarInput() {
  avatarInput.value?.click();
}

async function onAvatarChange(event) {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file || !selected.value) return;

  avatarUploading.value = true;
  const fd = new FormData();
  fd.append('avatar', file);
  try {
    const { data } = await window.axios.post(`/api/project/${selected.value.id}/avatar`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    selected.value = data.project;
    const index = projects.value.findIndex((p) => p.id === data.project.id);
    if (index !== -1) projects.value.splice(index, 1, data.project);
    showClientToast('success', 'Đã cập nhật ảnh đại diện.');
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không tải lên được ảnh đại diện.');
  } finally {
    avatarUploading.value = false;
  }
}

function triggerGalleryInput() {
  galleryInput.value?.click();
}

async function onGalleryChange(event) {
  const files = Array.from(event.target.files || []);
  event.target.value = '';
  if (!files.length || !selected.value) return;

  attachmentUploading.value = true;
  try {
    for (const file of files) {
      const fd = new FormData();
      fd.append('file', file);
      const { data } = await window.axios.post(`/api/project/${selected.value.id}/attachments`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      selected.value.attachments = [...(selected.value.attachments || []), data.attachment];
    }
    showClientToast('success', 'Đã thêm ảnh vào thư viện.');
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không tải lên được ảnh.');
  } finally {
    attachmentUploading.value = false;
  }
}

function triggerAttachmentInput() {
  attachmentInput.value?.click();
}

async function onAttachmentChange(event) {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file || !selected.value) return;

  attachmentUploading.value = true;
  const fd = new FormData();
  fd.append('file', file);
  try {
    const { data } = await window.axios.post(`/api/project/${selected.value.id}/attachments`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    selected.value.attachments = [...(selected.value.attachments || []), data.attachment];
    showClientToast('success', 'Đã tải file lên.');
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không tải lên được file.');
  } finally {
    attachmentUploading.value = false;
  }
}

async function addDriveLink() {
  const url = driveUrlInput.value.trim();
  if (!url || !selected.value) return;
  try {
    new URL(url);
  } catch {
    showClientToast('error', 'Link Google Drive không hợp lệ.');
    return;
  }

  attachmentUploading.value = true;
  try {
    const { data } = await window.axios.post(`/api/project/${selected.value.id}/attachments`, { url });
    selected.value.attachments = [...(selected.value.attachments || []), data.attachment];
    driveUrlInput.value = '';
    showClientToast('success', 'Đã thêm link Google Drive.');
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không thêm được link.');
  } finally {
    attachmentUploading.value = false;
  }
}

async function removeAttachment(attachment) {
  if (!selected.value) return;
  if (!window.confirm('Xoá tệp đính kèm này?')) return;
  try {
    await window.axios.delete(`/api/project/${selected.value.id}/attachments/${attachment.id}`);
    selected.value.attachments = (selected.value.attachments || []).filter((a) => a.id !== attachment.id);
    showClientToast('success', 'Đã xoá tệp đính kèm.');
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không xoá được tệp đính kèm.');
  }
}

function openLightbox(url) {
  window.open(url, '_blank', 'noopener');
}

function formatSize(bytes) {
  if (!bytes) return '';
  const kb = bytes / 1024;
  if (kb < 1024) return `${kb.toFixed(0)} KB`;
  return `${(kb / 1024).toFixed(1)} MB`;
}

const galleryImages = computed(() => (selected.value?.attachments || []).filter((a) => a.kind === 'image'));
const fileAttachments = computed(() => (selected.value?.attachments || []).filter((a) => a.kind !== 'image'));

function handleDocumentKeydown(event) {
  if (event.key !== 'Escape') return;
  if (labelPickerProjectId.value) closeLabelPicker();
  else if (selected.value) closePanel();
}

watch(perPage, () => loadProjects(1));

onMounted(() => {
  document.addEventListener('keydown', handleDocumentKeydown);
  const tabFromQuery = typeof route.query.tab === 'string' ? route.query.tab : '';
  if (TABS.some((t) => t.key === tabFromQuery)) {
    activeTab.value = tabFromQuery;
  }
  loadOptions();
  loadDepartments();
  loadLabels();
  loadProjects(1);
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleDocumentKeydown);
});
</script>

<template>
  <section class="proj-page">
    <PageHeader
      title="Dự án"
      icon="layers"
      description="Quản lý danh sách dự án của tổ chức."
      :primary-action="{ label: 'Thêm dự án', icon: 'plus', to: { name: 'manager.project.create' } }"
    >
      <template #actions>
        <div class="proj-page__header-search">
          <AppIcon name="search" :size="15" />
          <input
            v-model="query"
            type="search"
            placeholder="Tìm theo tên hoặc mã dự án…"
            @keydown.enter="loadProjects(1)"
          />
        </div>
        <button type="button" class="proj-page__header-btn" :disabled="loading" @click="loadProjects(meta.current_page)">
          <AppIcon name="refresh" :size="16" :class="{ 'proj-page__spin': loading }" />
          Làm mới
        </button>
        <button v-if="canManageSettings" type="button" class="proj-page__header-btn" @click="openSettings">
          <AppIcon name="settings" :size="16" />
          Cài đặt
        </button>
      </template>
    </PageHeader>

    <div class="proj-page__body">
      <div class="proj-page__main">
        <nav class="proj-tabs hide-scrollbar" aria-label="Lọc nhanh dự án">
          <button
            v-for="tab in TABS"
            :key="tab.key"
            type="button"
            class="proj-tabs__item"
            :class="{ 'proj-tabs__item--active': activeTab === tab.key }"
            @click="selectTab(tab.key)"
          >
            {{ tab.label }} ({{ tabCount(tab.key) }})
          </button>
        </nav>

        <TablePagesBar
          placement="top"
          :from="meta.from || 0"
          :to="meta.to || 0"
          :total="meta.total || 0"
          :page="meta.current_page || 1"
          :last-page="meta.last_page || 1"
          :per-page="perPage"
          @update:page="goPage"
          @update:per-page="perPage = $event"
        >
          <template #settings>
            <label v-for="col in COLUMNS" :key="col.key" class="proj-page__check">
              <input
                type="checkbox"
                :checked="col.always || visibleColumnKeys.includes(col.key)"
                :disabled="col.always"
                @change="onColumnToggle(col.key, $event.target.checked)"
              />
              <span>{{ col.label }}</span>
            </label>
          </template>
        </TablePagesBar>

        <div ref="tableWrap" class="proj-page__table-wrap hide-scrollbar" :class="{ 'proj-page__table-wrap--resizing': resizing }">
          <table class="proj-page__table" :style="{ width: tableWidthPx }">
            <colgroup>
              <col v-for="col in visibleColumns" :key="col.key" :style="{ width: colWidthStyle(col.key) }" />
            </colgroup>
            <thead>
              <tr>
                <th v-for="col in visibleColumns" :key="col.key">
                  <span>{{ col.label }}</span>
                  <button
                    type="button"
                    class="proj-page__resize"
                    aria-label="Kéo để đổi độ rộng cột"
                    @click.stop
                    @mousedown.stop.prevent="startResize($event, col.key)"
                  />
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td :colspan="colSpan" class="proj-page__empty">Đang tải…</td>
              </tr>
              <tr v-else-if="projects.length === 0">
                <td :colspan="colSpan" class="proj-page__empty">Chưa có dự án nào.</td>
              </tr>
              <template v-for="group in groupedProjects" v-else :key="group.key">
                <tr class="proj-page__group-row" @click="toggleGroup(group.key)">
                  <td :colspan="colSpan">
                    <span class="proj-page__group-toggle">
                      <AppIcon
                        name="chevronRight"
                        :size="14"
                        class="proj-page__group-chevron"
                        :class="{ 'proj-page__group-chevron--open': !isGroupCollapsed(group.key) }"
                      />
                      <span class="proj-page__group-label">{{ group.label }}</span>
                      <span class="proj-page__group-count">{{ group.projects.length }} dự án</span>
                    </span>
                  </td>
                </tr>
                <tr
                  v-for="project in group.projects"
                  v-show="!isGroupCollapsed(group.key)"
                  :key="project.id"
                  :class="{ 'proj-page__row--active': selected?.id === project.id }"
                  @dblclick="inspect(project)"
                >
                  <td v-for="col in visibleColumns" :key="col.key">
                    <template v-if="col.key === 'code'">{{ project.code }}</template>

                    <span v-else-if="col.key === 'name'" class="proj-page__name-cell">
                      <span>{{ project.name }}</span>
                      <span class="proj-page__name-labels">
                        <span
                          v-for="label in project.labels || []"
                          :key="label.id"
                          class="proj-label-picker__chip proj-label-picker__chip--sm"
                        >
                          <span class="proj-label-picker__dot" :class="`proj-label-picker__dot--${label.color}`" />
                          <span>{{ label.name }}</span>
                        </span>
                        <span class="proj-page__label-add-wrap">
                          <button
                            type="button"
                            class="proj-page__label-add"
                            aria-label="Gắn nhãn cho dự án này"
                            @click.stop="openLabelPicker(project)"
                          >
                            <AppIcon name="plus" :size="11" />
                          </button>
                          <div v-if="labelPickerProjectId === project.id" class="proj-page__label-pop" @click.stop>
                            <ProjectLabelPicker
                              :model-value="(project.labels || []).map((l) => l.id)"
                              :labels="allLabels"
                              @update:model-value="setProjectLabels(project, $event)"
                              @created="onLabelCreated"
                            />
                            <button type="button" class="proj-page__label-pop-close" @click="closeLabelPicker">Xong</button>
                          </div>
                        </span>
                      </span>
                    </span>

                    <template v-else-if="col.key === 'type'">{{ typeLabel(project.type) }}</template>
                    <template v-else-if="col.key === 'owner_department'">{{ project.owner_department?.name || '—' }}</template>
                    <template v-else-if="col.key === 'executing_department'">{{ project.executing_department?.name || '—' }}</template>

                    <span v-else-if="col.key === 'lead'">
                      <span v-if="project.lead" class="proj-page__person">
                        <UserAvatarTip :user="project.lead" label="Phụ trách chính" />
                        <span>{{ project.lead.name }}</span>
                      </span>
                      <span v-else>—</span>
                    </span>

                    <template v-else-if="col.key === 'member_count'">{{ (project.members || []).length }}</template>

                    <span v-else-if="col.key === 'status'" class="proj-page__status">
                      <span class="proj-page__dot" :class="statusDotClass(project.status)" />
                      {{ statusLabel(project.status) }}
                    </span>

                    <span v-else-if="col.key === 'importance'" class="proj-page__status">
                      <span class="proj-page__dot" :class="importanceDotClass(project.importance)" />
                      {{ importanceLabel(project.importance) }}
                    </span>

                    <template v-else-if="col.key === 'start_date'">{{ formatDate(project.start_date) }}</template>
                    <template v-else-if="col.key === 'end_date'">{{ formatDate(project.end_date) }}</template>
                    <template v-else-if="col.key === 'duration_days'">{{ project.duration_days ? `${project.duration_days} ngày` : '—' }}</template>
                    <template v-else-if="col.key === 'progress_method'">{{ progressMethodLabel(project.progress_method) }}</template>
                    <template v-else-if="col.key === 'progress'">{{ project.progress_percent != null ? `${project.progress_percent}%` : '—' }}</template>
                    <template v-else-if="col.key === 'evaluation_score'">{{ project.evaluation_score != null ? project.evaluation_score : '—' }}</template>
                    <template v-else-if="col.key === 'description'">{{ project.description || '—' }}</template>
                    <template v-else-if="col.key === 'creator'">{{ project.creator?.name || '—' }}</template>
                    <template v-else-if="col.key === 'created_at'">{{ formatDate(project.created_at) }}</template>
                    <template v-else-if="col.key === 'is_following'">{{ project.is_following ? 'Có' : '—' }}</template>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <TablePagesBar
          placement="bottom"
          paging-only
          :from="meta.from || 0"
          :to="meta.to || 0"
          :total="meta.total || 0"
          :page="meta.current_page || 1"
          :last-page="meta.last_page || 1"
          :per-page="perPage"
          @update:page="goPage"
          @update:per-page="perPage = $event"
        />
      </div>

      <aside v-if="selected" class="proj-page__side" aria-label="Chi tiết dự án">
        <div class="proj-page__side-head">
          <h2 class="proj-page__side-title">Chi tiết dự án</h2>
          <div class="proj-page__side-actions">
            <button
              type="button"
              class="proj-page__btn proj-page__btn--ghost proj-page__follow-btn"
              :class="{ 'proj-page__follow-btn--active': selected.is_following }"
              :disabled="followBusy"
              @click="toggleFollow"
            >
              <AppIcon name="bell" :size="14" />
              {{ selected.is_following ? 'Đang theo dõi' : 'Theo dõi' }}
            </button>
            <button type="button" class="proj-page__icon-btn" aria-label="Sửa dự án" @click="openEditPage(selected)">
              <AppIcon name="pencil" :size="16" />
            </button>
            <button type="button" class="proj-page__icon-btn" aria-label="Xoá dự án" @click="deleteProject(selected)">
              <AppIcon name="trash" :size="16" />
            </button>
            <button type="button" class="proj-page__icon-btn" aria-label="Đóng" @click="closePanel">
              <AppIcon name="close" :size="16" />
            </button>
          </div>
        </div>

        <div class="proj-page__avatar-block">
          <div class="proj-page__avatar" @click="triggerAvatarInput">
            <img v-if="selected.avatar_url" :src="selected.avatar_url" alt="" class="proj-page__avatar-img" />
            <AppIcon v-else name="layers" :size="28" />
          </div>
          <button type="button" class="proj-page__btn proj-page__btn--ghost" :disabled="avatarUploading" @click="triggerAvatarInput">
            {{ avatarUploading ? 'Đang tải…' : 'Đổi ảnh đại diện' }}
          </button>
          <input ref="avatarInput" type="file" accept="image/*" class="proj-page__hidden-input" @change="onAvatarChange" />
        </div>

        <div class="proj-page__rows">
          <div class="proj-page__row">
            <span class="proj-page__row-label">Mã dự án</span>
            <span class="proj-page__row-value">{{ selected.code }}</span>
          </div>
          <div class="proj-page__row">
            <span class="proj-page__row-label">Loại dự án</span>
            <span class="proj-page__row-value">{{ typeLabel(selected.type) }}</span>
          </div>
          <div class="proj-page__row">
            <span class="proj-page__row-label">Phòng ban sở hữu</span>
            <span class="proj-page__row-value">{{ selected.owner_department?.name || '—' }}</span>
          </div>
          <div v-if="selected.executing_department" class="proj-page__row">
            <span class="proj-page__row-label">Phòng ban thực hiện</span>
            <span class="proj-page__row-value">{{ selected.executing_department.name }}</span>
          </div>
          <div class="proj-page__row">
            <span class="proj-page__row-label">Trạng thái</span>
            <span class="proj-page__row-value proj-page__row-value--status">
              <span class="proj-page__dot" :class="statusDotClass(selected.status)" />
              {{ statusLabel(selected.status) }}
            </span>
          </div>
          <div class="proj-page__row">
            <span class="proj-page__row-label">Mức độ quan trọng</span>
            <span class="proj-page__row-value proj-page__row-value--status">
              <span class="proj-page__dot" :class="importanceDotClass(selected.importance)" />
              {{ importanceLabel(selected.importance) }}
            </span>
          </div>
          <div class="proj-page__row">
            <span class="proj-page__row-label">Phụ trách chính</span>
            <span class="proj-page__row-value">{{ selected.lead?.name || '—' }}</span>
          </div>
          <div class="proj-page__row">
            <span class="proj-page__row-label">Ngày bắt đầu</span>
            <span class="proj-page__row-value">{{ formatDate(selected.start_date) }}</span>
          </div>
          <div class="proj-page__row">
            <span class="proj-page__row-label">Ngày kết thúc</span>
            <span class="proj-page__row-value">{{ formatDate(selected.end_date) }}</span>
          </div>
          <div v-if="selected.duration_days" class="proj-page__row">
            <span class="proj-page__row-label">Số ngày thực hiện</span>
            <span class="proj-page__row-value">{{ selected.duration_days }} ngày</span>
          </div>
          <div v-if="selected.progress_percent != null" class="proj-page__row proj-page__row--progress">
            <span class="proj-page__row-label">Tiến độ</span>
            <span class="proj-page__progress">
              <span class="proj-page__progress-track">
                <span class="proj-page__progress-fill" :style="{ width: `${selected.progress_percent}%` }" />
              </span>
              <span class="proj-page__row-value">{{ selected.progress_percent }}%</span>
            </span>
          </div>
          <div class="proj-page__row">
            <span class="proj-page__row-label">Điểm đánh giá</span>
            <span class="proj-page__row-value">{{ selected.evaluation_score != null ? selected.evaluation_score : '—' }}</span>
          </div>
          <div v-if="selected.description" class="proj-page__row">
            <span class="proj-page__row-label">Mô tả</span>
            <span class="proj-page__row-value">{{ selected.description }}</span>
          </div>
          <div class="proj-page__row">
            <span class="proj-page__row-label">Người tạo</span>
            <span class="proj-page__row-value">{{ selected.creator?.name || '—' }}</span>
          </div>
        </div>

        <div v-if="(selected.scopes || []).length" class="proj-page__section">
          <h3 class="proj-page__section-title">Phạm vi triển khai</h3>
          <div class="proj-page__rows">
            <div v-for="scope in selected.scopes" :key="scope.id" class="proj-page__row">
              <span class="proj-page__row-label">
                {{ scope.scope_type === 'department' ? scope.department?.name || 'Phòng ban' : (options.scope_type.find((o) => o.value === scope.scope_type)?.label || scope.scope_type) }}
              </span>
              <span class="proj-page__row-value">{{ scope.weight_percent }}%</span>
            </div>
          </div>
        </div>

        <div v-if="(selected.members || []).length" class="proj-page__section">
          <h3 class="proj-page__section-title">Người thực hiện</h3>
          <div class="proj-page__member-list">
            <span v-for="member in selected.members" :key="member.id" class="proj-page__member-chip">
              <UserAvatarTip :user="member" label="Người thực hiện" />
              <span>{{ member.name }}</span>
            </span>
          </div>
        </div>

        <div v-if="(selected.labels || []).length" class="proj-page__section">
          <h3 class="proj-page__section-title">Nhãn</h3>
          <div class="proj-page__member-list">
            <span v-for="label in selected.labels" :key="label.id" class="proj-label-picker__chip">
              <span class="proj-label-picker__dot" :class="`proj-label-picker__dot--${label.color}`" />
              <span>{{ label.name }}</span>
            </span>
          </div>
        </div>

        <div v-if="galleryImages.length" class="proj-page__section">
          <div class="proj-page__section-head">
            <h3 class="proj-page__section-title">Thư viện ảnh</h3>
            <button type="button" class="proj-page__link-btn" :disabled="attachmentUploading" @click="triggerGalleryInput">
              + Thêm ảnh
            </button>
          </div>
          <div class="proj-page__gallery">
            <button
              v-for="img in galleryImages"
              :key="img.id"
              type="button"
              class="proj-page__gallery-item"
              @click="openLightbox(img.file_url)"
            >
              <img :src="img.file_url" alt="" />
              <span class="proj-page__gallery-remove" @click.stop="removeAttachment(img)">
                <AppIcon name="close" :size="12" />
              </span>
            </button>
          </div>
        </div>
        <div v-else class="proj-page__section">
          <div class="proj-page__section-head">
            <h3 class="proj-page__section-title">Thư viện ảnh</h3>
            <button type="button" class="proj-page__link-btn" :disabled="attachmentUploading" @click="triggerGalleryInput">
              + Thêm ảnh
            </button>
          </div>
        </div>
        <input ref="galleryInput" type="file" accept="image/*" multiple class="proj-page__hidden-input" @change="onGalleryChange" />

        <div class="proj-page__section">
          <h3 class="proj-page__section-title">Tệp đính kèm</h3>
          <div v-if="fileAttachments.length" class="proj-page__attachment-list">
            <div v-for="att in fileAttachments" :key="att.id" class="proj-page__attachment">
              <AppIcon :name="att.kind === 'drive_link' ? 'link' : 'fileText'" :size="16" />
              <a
                v-if="att.kind === 'drive_link'"
                :href="att.url"
                target="_blank"
                rel="noopener"
                class="proj-page__attachment-name"
              >
                {{ att.url }}
              </a>
              <a v-else :href="att.file_url" target="_blank" rel="noopener" class="proj-page__attachment-name">
                {{ att.original_name }}
              </a>
              <span v-if="att.size_bytes" class="proj-page__attachment-size">{{ formatSize(att.size_bytes) }}</span>
              <button type="button" class="proj-page__icon-btn" aria-label="Xoá tệp đính kèm" @click="removeAttachment(att)">
                <AppIcon name="trash" :size="14" />
              </button>
            </div>
          </div>

          <div class="proj-page__attachment-add">
            <input v-model="driveUrlInput" type="text" class="proj-page__input" placeholder="https://drive.google.com/…" />
            <button type="button" class="proj-page__btn proj-page__btn--ghost" :disabled="attachmentUploading" @click="addDriveLink">
              Thêm link
            </button>
          </div>
          <button type="button" class="proj-page__btn proj-page__btn--ghost" :disabled="attachmentUploading" @click="triggerAttachmentInput">
            Tải file lên
          </button>
          <input ref="attachmentInput" type="file" class="proj-page__hidden-input" @change="onAttachmentChange" />
        </div>
      </aside>
    </div>

  </section>
</template>

<style scoped>
.proj-page {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
}

.proj-page__header-btn {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  height: 2rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 500;
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  cursor: pointer;
}

.proj-page__header-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.proj-page__header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.proj-page__header-search {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  height: 2rem;
  padding: 0 0.75rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.proj-page__header-search input {
  width: 15rem;
  max-width: 40vw;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
}

.proj-page__header-search input:focus {
  outline: none;
}

.proj-page__spin {
  animation: proj-spin 0.8s linear infinite;
}

@keyframes proj-spin {
  to {
    transform: rotate(360deg);
  }
}

.proj-page__body {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-4);
  overflow: hidden;
}

.proj-page__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.proj-tabs {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: var(--space-1);
  margin-top: var(--space-3);
  overflow-x: auto;
}

.proj-tabs__item {
  flex-shrink: 0;
  padding: 0.5rem 0.875rem;
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
}

.proj-tabs__item:hover {
  color: var(--color-text);
}

.proj-tabs__item--active {
  color: var(--color-primary);
  box-shadow: 0 2px 0 var(--color-primary);
}

.proj-page__name-cell {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  white-space: normal;
}

.proj-page__name-labels {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.25rem;
}

.proj-page__label-add-wrap {
  position: relative;
  display: inline-flex;
}

.proj-page__label-add {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.125rem;
  height: 1.125rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  cursor: pointer;
}

.proj-page__label-add:hover {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.proj-page__label-pop {
  position: absolute;
  z-index: 20;
  top: calc(100% + 0.375rem);
  left: 0;
  width: 18rem;
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-md, 0 8px 24px rgba(0, 0, 0, 0.12));
  cursor: default;
}

.proj-page__label-pop-close {
  width: 100%;
  margin-top: var(--space-2);
  padding: 0.375rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.proj-page__label-pop-close:hover {
  background: var(--color-border);
}

.proj-page__group-row {
  cursor: pointer;
}

.proj-page__group-row td {
  padding: var(--space-2) var(--space-4) !important;
  background: var(--color-surface-muted);
  box-shadow: 0 1px 0 var(--color-border) !important;
}

.proj-page__group-row:hover td {
  background: var(--color-border);
}

.proj-page__group-toggle {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  white-space: nowrap;
}

.proj-page__group-chevron {
  flex-shrink: 0;
  color: var(--color-text-muted);
  transition: transform 0.15s ease;
}

.proj-page__group-chevron--open {
  transform: rotate(90deg);
}

.proj-page__group-label {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 700;
}

.proj-page__group-count {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.proj-page__follow-btn {
  padding: 0.375rem 0.625rem;
  font-size: 0.75rem;
}

.proj-page__follow-btn--active {
  background: var(--color-secondary-surface);
  color: var(--color-secondary);
}

/* Chip nhãn dùng lại trong cột "Tên dự án" và panel chi tiết — cấu trúc
   giống ProjectLabelPicker.vue (scoped riêng, không lộ ra ngoài) nên khai
   báo tối thiểu lại ở đây cho 2 nơi dùng ngoài component đó. */
.proj-label-picker__chip {
  display: inline-flex;
  align-items: center;
  gap: 0.4375rem;
  padding: 0.3125rem 0.5rem 0.3125rem 0.625rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
  white-space: nowrap;
}

.proj-label-picker__chip--sm {
  padding: 0.125rem 0.4375rem 0.125rem 0.5rem;
  font-size: 0.75rem;
}

.proj-label-picker__dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
}

.proj-label-picker__dot--primary {
  background: var(--color-primary);
}

.proj-label-picker__dot--success {
  background: var(--color-success);
}

.proj-label-picker__dot--info {
  background: var(--color-info);
}

.proj-label-picker__dot--warning {
  background: var(--color-warning, var(--color-primary));
}

.proj-label-picker__dot--danger {
  background: var(--color-danger);
}

.proj-page__input {
  width: 100%;
  min-width: 0;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
}

.proj-page__input:focus {
  outline: 2px solid var(--color-primary-200);
  outline-offset: 1px;
}

.proj-page__input--error {
  box-shadow: 0 0 0 1px var(--color-danger);
}

.proj-page__input:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.proj-page__textarea {
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 0.875rem;
  font-family: var(--font-family-base);
  resize: vertical;
}

.proj-page__btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.5rem 1rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-primary);
  color: var(--color-on-primary, #fff);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.proj-page__btn:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.proj-page__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.proj-page__btn--ghost {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.proj-page__btn--ghost:hover:not(:disabled) {
  background: var(--color-border);
}

.proj-page__link-btn {
  border: none;
  background: transparent;
  color: var(--color-primary);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.proj-page__link-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.proj-page__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.proj-page__table-wrap--resizing {
  cursor: col-resize;
  user-select: none;
}

.proj-page__table {
  min-width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.proj-page__table thead th {
  position: sticky;
  top: 0;
  z-index: 1;
  padding: var(--space-3) var(--space-4);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-weight: 600;
  font-size: 0.75rem;
  letter-spacing: 0.02em;
  text-align: left;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.proj-page__resize {
  position: absolute;
  top: 0;
  right: 0;
  z-index: 2;
  width: 0.5rem;
  height: 100%;
  padding: 0;
  border: none;
  background: transparent;
  cursor: col-resize;
}

.proj-page__resize::after {
  content: '';
  position: absolute;
  top: 25%;
  right: 2px;
  width: 2px;
  height: 50%;
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.proj-page__resize:hover::after {
  background: var(--color-primary);
}

.proj-page__table thead th {
  position: relative;
}

.proj-page__table tbody td {
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  vertical-align: middle;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.proj-page__table tbody tr {
  cursor: pointer;
}

.proj-page__table tbody tr:hover td {
  background: var(--color-surface-muted);
}

.proj-page__row--active td {
  background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface));
}

.proj-page__empty {
  padding: var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
  white-space: normal;
}

.proj-page__person {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.proj-page__status {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.proj-page__dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.proj-page__dot--success {
  background: var(--color-success);
}

.proj-page__dot--danger {
  background: var(--color-danger);
}

.proj-page__dot--info {
  background: var(--color-info);
}

.proj-page__dot--warning {
  background: var(--color-warning, var(--color-primary));
}

.proj-page__dot--tertiary {
  background: var(--color-tertiary);
}

.proj-page__dot--gold {
  background: var(--color-gold-600);
}

.proj-page__dot--secondary {
  background: var(--color-secondary);
}

.proj-page__dot--neutral {
  background: var(--color-text-muted);
}

.proj-page__side {
  flex-shrink: 0;
  width: 28rem;
  overflow-y: auto;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.proj-page__side-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.proj-page__side-title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 700;
}

.proj-page__side-actions {
  display: flex;
  align-items: center;
  gap: var(--space-1);
}

.proj-page__icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.proj-page__icon-btn:hover {
  background: var(--color-surface);
}

.proj-page__avatar-block {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
  margin: var(--space-4) 0;
}

.proj-page__avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 5rem;
  height: 5rem;
  border-radius: var(--radius-lg);
  background: linear-gradient(135deg, var(--color-primary-surface), var(--color-tertiary-surface));
  box-shadow: inset 0 0 0 1px var(--color-border);
  color: var(--color-primary);
  cursor: pointer;
  overflow: hidden;
}

.proj-page__avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.proj-page__hidden-input {
  display: none;
}

.proj-page__rows {
  display: flex;
  flex-direction: column;
}

.proj-page__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.proj-page__row:last-child {
  box-shadow: none;
}

.proj-page__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.proj-page__row-label::after {
  content: ':';
}

.proj-page__row-value {
  color: var(--color-text);
  font-style: italic;
  text-align: right;
  overflow-wrap: anywhere;
}

.proj-page__row-value--status {
  display: inline-flex;
  align-items: center;
  gap: 0.4375rem;
}

.proj-page__row--progress {
  align-items: center;
}

.proj-page__progress {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex: 1;
  min-width: 0;
  justify-content: flex-end;
}

.proj-page__progress-track {
  flex: 1;
  max-width: 8rem;
  height: 0.375rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  overflow: hidden;
}

.proj-page__progress-fill {
  display: block;
  height: 100%;
  border-radius: var(--radius-full);
  background: linear-gradient(90deg, var(--color-tertiary), var(--color-secondary));
}

.proj-page__section {
  margin-top: var(--space-4);
  padding-top: var(--space-4);
  box-shadow: 0 -1px 0 var(--color-border);
}

.proj-page__section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.proj-page__section-title {
  margin: 0 0 var(--space-2);
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 700;
}

.proj-page__member-list {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}

.proj-page__member-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.3125rem 0.625rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  font-size: 0.8125rem;
  color: var(--color-text);
}

.proj-page__gallery {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-2);
}

.proj-page__gallery-item {
  position: relative;
  aspect-ratio: 1;
  padding: 0;
  border: none;
  border-radius: var(--radius-md);
  overflow: hidden;
  cursor: pointer;
  background: var(--color-surface);
}

.proj-page__gallery-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.proj-page__gallery-remove {
  position: absolute;
  top: 0.25rem;
  right: 0.25rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.25rem;
  height: 1.25rem;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, black 50%, transparent);
  color: white;
}

.proj-page__attachment-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  margin-bottom: var(--space-2);
}

.proj-page__attachment {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.proj-page__attachment-name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--color-text);
}

.proj-page__attachment-size {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.proj-page__attachment-add {
  display: flex;
  gap: var(--space-2);
  margin-bottom: var(--space-2);
}

.proj-page__check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.proj-page__check:has(input:disabled) {
  opacity: 0.7;
  cursor: default;
}

/* ── responsive ─────────────────────────────────────────────────────── */

@media (max-width: 1279px) {
  .proj-page__body {
    flex-direction: column;
  }

  .proj-page__side {
    width: 100%;
    max-height: 42%;
  }

  .proj-page__table-wrap {
    min-height: 16rem;
  }
}

@media (max-width: 768px) {
  .proj-page {
    padding: var(--space-4);
  }

  .proj-page__header-search input {
    width: 10rem;
  }
}

@media (max-width: 480px) {
  .proj-page {
    padding: var(--space-3);
  }

  .proj-page__header-search input {
    width: 8rem;
  }

  .proj-page__gallery {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (prefers-reduced-motion: reduce) {
  .proj-page__spin {
    animation: none;
  }
}
</style>
