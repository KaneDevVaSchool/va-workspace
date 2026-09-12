<script setup>
//
// Danh sách tài khoản dịch vụ (Credential module) — theo mẫu vàng
// Modules/Identity/resources/js/pages/ActivityLog.vue (skill data-table):
// filter → TablePagesBar trên → bảng kéo cột → TablePagesBar dưới → panel
// chi tiết đẩy ngang. Modal tạo/sửa theo mẫu vàng WorkspaceConfigEvaluation.vue
// (skill form-modal): lưới ngang 2 tab (Thông tin/Nâng cao), trong viewport.
//
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { formatDate } from '@/lib/formatTime';
import { showClientToast } from '@/lib/clientToast';
import { useDragScroll } from '@/composables/useDragScroll';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import {
  ACCOUNT_TYPES,
  STATUS_OPTIONS,
  STATUS_DOT_TONE,
  CREDENTIAL_COLUMNS,
  CREDENTIAL_FILTERS,
  COLUMN_STORAGE_KEY,
  COLUMN_WIDTH_KEY,
  FILTER_STORAGE_KEY,
  ZOOM_STORAGE_KEY,
  accountTypeLabel,
  statusLabel,
  loadVisibility,
  saveVisibility,
} from '../constants/credential.js';

const CELL_PAD_X = 32;
const COL_EXTRA = 24;
let measureCtx = null;
let wrapObserver = null;

const router = useRouter();
const auth = useAuthStore();

const credentials = ref([]);
const providers = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 20 });
const loading = ref(false);
const selected = ref(null);

const query = ref('');
const providerId = ref('');
const accountType = ref('');
const status = ref('');
const perPage = ref(20);

const visibleColumns = reactive(loadVisibility(COLUMN_STORAGE_KEY, CREDENTIAL_COLUMNS));
const visibleFilters = reactive(loadVisibility(FILTER_STORAGE_KEY, CREDENTIAL_FILTERS));

const tableWrap = ref(null);
const resizing = ref(false);
const MIN_COL_PX = 72;

useDragScroll(tableWrap, { isBlocked: () => resizing.value });

const columnWidths = reactive(loadColumnWidths());
const tableZoom = ref(loadZoom());

const canManage = computed(() => auth.can('credential.manage'));

const shownColumns = computed(() => CREDENTIAL_COLUMNS.filter((col) => visibleColumns[col.key]));
const colSpan = computed(() => Math.max(shownColumns.value.length, 1));

const expiringSoonCount = computed(
  () => credentials.value.filter((c) => c.status === 'expiring_soon' || c.status === 'renewing_soon').length,
);
const expiredCount = computed(() => credentials.value.filter((c) => c.status === 'expired').length);

const hasActiveFilters = computed(
  () =>
    Boolean(query.value.trim()) || Boolean(providerId.value) || Boolean(accountType.value) || Boolean(status.value),
);

const hasVisibleFilterFields = computed(() => CREDENTIAL_FILTERS.some((item) => visibleFilters[item.key]));

const tableWidthPx = computed(() => {
  const keys = shownColumns.value.map((col) => col.key);
  const sum = keys.reduce((total, key) => total + (Number(columnWidths[key]) || 0), 0);
  return sum > 0 ? `${sum}px` : '100%';
});

function currentFilterParams() {
  return {
    q: query.value.trim() || undefined,
    provider_id: providerId.value || undefined,
    account_type: accountType.value || undefined,
    status: status.value || undefined,
  };
}

async function loadProviders() {
  try {
    const { data } = await window.axios.get('/api/credential/providers');
    providers.value = data.providers ?? [];
  } catch {
    providers.value = [];
  }
}

async function loadCredentials(page = 1) {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/credential', {
      params: { ...currentFilterParams(), page, per_page: perPage.value },
    });
    credentials.value = data.credentials ?? [];
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 20 };

    if (selected.value && !credentials.value.some((c) => c.id === selected.value.id)) {
      selected.value = null;
    }
    nextTick(fitColumnsToContent);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được danh sách tài khoản.');
  } finally {
    loading.value = false;
  }
}

function goPage(page) {
  if (page < 1 || page > meta.value.last_page || page === meta.value.current_page) return;
  loadCredentials(page);
}

function clearFilters() {
  query.value = '';
  providerId.value = '';
  accountType.value = '';
  status.value = '';
  loadCredentials(1);
}

function inspect(credential) {
  router.push({ name: 'manager.credential.detail', params: { id: credential.id } });
}

function providerLabel(credential) {
  return credential.provider?.name || '—';
}

function cellText(credential, key) {
  if (key === 'name') return credential.name || '—';
  if (key === 'provider') return providerLabel(credential);
  if (key === 'account_type') return accountTypeLabel(credential.account_type);
  if (key === 'status') return statusLabel(credential.status);
  if (key === 'expires_at') return credential.expires_at ? formatDate(credential.expires_at) : '—';
  if (key === 'monthly_cost') {
    if (credential.cost_hidden || credential.monthly_cost == null) return '—';
    return `${Number(credential.monthly_cost).toLocaleString('vi-VN')} ${credential.currency || 'VND'}`;
  }
  if (key === 'server_name') return credential.server_name || '—';
  if (key === 'domain') return credential.domain || '—';
  if (key === 'creator_name') return credential.creator_name || '—';
  return '—';
}

function statusTone(value) {
  return STATUS_DOT_TONE[value] || 'neutral';
}

function loadZoom() {
  try {
    const raw = Number(localStorage.getItem(ZOOM_STORAGE_KEY));
    if (raw === 0.9 || raw === 1 || raw === 1.15) return raw;
  } catch {
    // Bỏ qua.
  }
  return 1;
}

function loadColumnWidths() {
  try {
    const raw = localStorage.getItem(COLUMN_WIDTH_KEY);
    const parsed = raw ? JSON.parse(raw) : {};
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) return parsed;
  } catch {
    // Bỏ qua nếu trình duyệt chặn localStorage.
  }
  return {};
}

function colWidthStyle(key) {
  const width = columnWidths[key];
  return width ? `${width}px` : undefined;
}

function measureText(text, font) {
  if (!measureCtx) {
    measureCtx = document.createElement('canvas').getContext('2d');
  }
  measureCtx.font = font;
  return measureCtx.measureText(String(text ?? '')).width;
}

function fontOf(el, fallback) {
  if (!el) return fallback;
  const style = getComputedStyle(el);
  return `${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
}

function readTableFonts() {
  const table = tableWrap.value?.querySelector('.credential-page__table');
  return {
    header: fontOf(table?.querySelector('thead th'), '600 12px "Be Vietnam Pro", sans-serif'),
    cell: fontOf(table?.querySelector('tbody td'), '400 14px "Be Vietnam Pro", sans-serif'),
  };
}

function columnContentWidth(key, fonts) {
  const label = CREDENTIAL_COLUMNS.find((col) => col.key === key)?.label ?? '';
  let maxW = measureText(label, fonts.header);
  for (const credential of credentials.value) {
    maxW = Math.max(maxW, measureText(cellText(credential, key), fonts.cell));
  }
  return Math.max(MIN_COL_PX, Math.ceil(maxW + CELL_PAD_X + COL_EXTRA));
}

function distributeExtraWidth(widths, keys, available) {
  const sum = keys.reduce((total, key) => total + widths[key], 0);
  if (sum <= 0 || available <= sum) return widths;

  const extra = available - sum;
  const next = { ...widths };
  let used = 0;
  keys.forEach((key, index) => {
    if (index === keys.length - 1) {
      next[key] = available - used;
      return;
    }
    next[key] = widths[key] + Math.floor((widths[key] / sum) * extra);
    used += next[key];
  });
  return next;
}

function fitColumnsToContent() {
  const wrap = tableWrap.value;
  const keys = shownColumns.value.map((col) => col.key);
  if (!wrap || keys.length === 0 || resizing.value) return;

  const fonts = readTableFonts();
  const measured = {};
  for (const key of keys) {
    measured[key] = columnContentWidth(key, fonts);
  }

  const next = distributeExtraWidth(measured, keys, wrap.clientWidth);
  for (const key of keys) {
    columnWidths[key] = next[key];
  }
}

function startResize(event, key) {
  const keys = shownColumns.value.map((col) => col.key);
  const index = keys.indexOf(key);
  if (index < 0) return;

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
    window.removeEventListener('mousemove', onMove);
    window.removeEventListener('mouseup', onUp);
  }

  window.addEventListener('mousemove', onMove);
  window.addEventListener('mouseup', onUp);
}

function onColumnToggle(key, checked) {
  if (!checked) {
    const remaining = CREDENTIAL_COLUMNS.filter((col) => visibleColumns[col.key] && col.key !== key).length;
    if (remaining < 1) {
      showClientToast('warning', 'Cần giữ ít nhất một cột trên bảng.');
      return;
    }
  }
  visibleColumns[key] = checked;
}

function onFilterToggle(key, checked) {
  visibleFilters[key] = checked;
}

// ---------- Modal tạo/sửa (skill form-modal) ----------
const dialogOpen = ref(false);
const dialogMode = ref('create'); // 'create' | 'edit'
const dialogTab = ref('info'); // 'info' | 'advanced'
const formSaving = ref(false);
const editingId = ref(null);

const form = reactive(emptyForm());

function emptyForm() {
  return {
    name: '',
    provider_id: '',
    account_type: 'user',
    username: '',
    email: '',
    password: '',
    is_google_login: false,
    google_account_owner: '',
    server_name: '',
    vps_cluster: '',
    domain: '',
    database_name: '',
    is_root_account: false,
    is_iam_account: false,
    notes: '',
    purchased_at: '',
    expires_at: '',
    monthly_cost: '',
    cost_hidden: false,
    currency: 'VND',
  };
}

const selectedProviderCategory = computed(() => {
  const provider = providers.value.find((p) => p.id === Number(form.provider_id));
  return provider?.category || '';
});

const showInfraFields = computed(() => {
  const category = selectedProviderCategory.value;
  return category === 'Hạ tầng server' || category === 'Hạ tầng cloud' || category === 'Cơ sở dữ liệu' || category === 'Tên miền';
});

function openCreateDialog() {
  Object.assign(form, emptyForm());
  dialogMode.value = 'create';
  dialogTab.value = 'info';
  editingId.value = null;
  dialogOpen.value = true;
}

function openEditDialog(credential) {
  Object.assign(form, {
    name: credential.name || '',
    provider_id: credential.provider?.id || '',
    account_type: credential.account_type || 'user',
    username: credential.username || '',
    email: credential.email || '',
    password: '',
    is_google_login: Boolean(credential.is_google_login),
    google_account_owner: credential.google_account_owner || '',
    server_name: credential.server_name || '',
    vps_cluster: credential.vps_cluster || '',
    domain: credential.domain || '',
    database_name: credential.database_name || '',
    is_root_account: Boolean(credential.is_root_account),
    is_iam_account: Boolean(credential.is_iam_account),
    notes: credential.notes || '',
    purchased_at: credential.purchased_at || '',
    expires_at: credential.expires_at || '',
    monthly_cost: credential.monthly_cost ?? '',
    cost_hidden: Boolean(credential.cost_hidden),
    currency: credential.currency || 'VND',
  });
  dialogMode.value = 'edit';
  dialogTab.value = 'info';
  editingId.value = credential.id;
  dialogOpen.value = true;
}

function closeDialog() {
  if (formSaving.value) return;
  dialogOpen.value = false;
}

async function submitForm() {
  if (!form.name.trim()) {
    showClientToast('error', 'Vui lòng nhập tên tài khoản.');
    return;
  }

  formSaving.value = true;
  try {
    const payload = { ...form };
    if (payload.provider_id === '') payload.provider_id = null;
    if (payload.monthly_cost === '') payload.monthly_cost = null;
    if (payload.purchased_at === '') payload.purchased_at = null;
    if (payload.expires_at === '') payload.expires_at = null;
    if (dialogMode.value === 'edit' && payload.password === '') delete payload.password;

    if (dialogMode.value === 'create') {
      await window.axios.post('/api/credential', payload);
      showClientToast('success', 'Đã tạo tài khoản.');
    } else {
      await window.axios.put(`/api/credential/${editingId.value}`, payload);
      showClientToast('success', 'Đã cập nhật tài khoản.');
    }
    dialogOpen.value = false;
    await loadCredentials(meta.value.current_page);
  } catch (error) {
    const message = error?.response?.data?.message || Object.values(error?.response?.data?.errors || {})[0]?.[0];
    showClientToast('error', message || 'Không lưu được tài khoản.');
  } finally {
    formSaving.value = false;
  }
}

// ---------- Xoá ----------
const deleteTarget = ref(null);
const deleting = ref(false);

function askDelete(credential) {
  deleteTarget.value = credential;
}

async function confirmDelete() {
  if (!deleteTarget.value) return;
  deleting.value = true;
  try {
    await window.axios.delete(`/api/credential/${deleteTarget.value.id}`);
    showClientToast('success', 'Đã xoá tài khoản.');
    deleteTarget.value = null;
    await loadCredentials(meta.value.current_page);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không xoá được tài khoản.');
  } finally {
    deleting.value = false;
  }
}

// ---------- Menu thao tác theo dòng ----------
const openMenuId = ref(null);

function toggleRowMenu(id) {
  openMenuId.value = openMenuId.value === id ? null : id;
}

function closeRowMenu() {
  openMenuId.value = null;
}

function onDocumentClick(event) {
  if (openMenuId.value === null) return;
  if (!event.target.closest('.credential-page__row-menu')) {
    closeRowMenu();
  }
}

function handleDocumentKeydown(event) {
  if (event.key === 'Escape') {
    closeRowMenu();
    if (dialogOpen.value) closeDialog();
  }
}

watch(visibleColumns, (value) => saveVisibility(COLUMN_STORAGE_KEY, value), { deep: true });
watch(visibleFilters, (value) => saveVisibility(FILTER_STORAGE_KEY, value), { deep: true });
watch(columnWidths, (value) => saveVisibility(COLUMN_WIDTH_KEY, value), { deep: true });
watch(tableZoom, (value) => {
  try {
    localStorage.setItem(ZOOM_STORAGE_KEY, String(value));
  } catch {
    // Bỏ qua.
  }
  nextTick(fitColumnsToContent);
});
watch(shownColumns, () => nextTick(fitColumnsToContent));
watch([providerId, accountType, status, perPage], () => loadCredentials(1));

onMounted(() => {
  document.addEventListener('mousedown', onDocumentClick);
  document.addEventListener('keydown', handleDocumentKeydown);
  loadProviders();
  loadCredentials(1);
  nextTick(() => {
    fitColumnsToContent();
    if (tableWrap.value) {
      let lastWrapWidth = tableWrap.value.clientWidth;
      wrapObserver = new ResizeObserver((entries) => {
        const width = Math.round(entries[0]?.contentRect?.width || 0);
        if (!width || width === lastWrapWidth || resizing.value) return;
        lastWrapWidth = width;
        fitColumnsToContent();
      });
      wrapObserver.observe(tableWrap.value);
    }
  });
  document.fonts?.ready?.then(() => nextTick(fitColumnsToContent));
});

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onDocumentClick);
  document.removeEventListener('keydown', handleDocumentKeydown);
  wrapObserver?.disconnect();
});
</script>

<template>
  <section class="credential-page">
    <PageHeader
      title="Quản lý tài khoản"
      description="Tài khoản dịch vụ dùng trong công ty — Google, Canva, Cursor, Claude, AWS, VPS, database, IAM, domain..."
    >
      <template #title>
        <span class="credential-page__title">
          <AppIcon name="lock" :size="16" />
          Quản lý tài khoản
          <button
            type="button"
            class="credential-page__title-icon-btn"
            aria-label="Làm mới danh sách"
            :disabled="loading"
            @click="loadCredentials(meta.current_page)"
          >
            <AppIcon name="refresh" :size="15" :class="{ 'credential-page__spin': loading }" />
          </button>
          <button
            v-if="canManage"
            type="button"
            class="credential-page__title-icon-btn credential-page__title-icon-btn--primary"
            aria-label="Thêm tài khoản"
            @click="openCreateDialog"
          >
            <AppIcon name="plus" :size="16" />
          </button>
        </span>
      </template>
    </PageHeader>

    <div class="credential-page__body">
      <div class="credential-page__main">
        <div v-if="!loading && credentials.length > 0" class="credential-page__summary">
          <span class="credential-page__summary-item">
            <span class="credential-page__dot credential-page__dot--neutral" />
            {{ meta.total || 0 }} tài khoản
          </span>
          <span v-if="expiringSoonCount > 0" class="credential-page__summary-item">
            <span class="credential-page__dot credential-page__dot--warning" />
            {{ expiringSoonCount }} sắp/chuẩn bị hết hạn
          </span>
          <span v-if="expiredCount > 0" class="credential-page__summary-item">
            <span class="credential-page__dot credential-page__dot--danger" />
            {{ expiredCount }} đã hết hạn
          </span>
        </div>

        <div v-if="hasVisibleFilterFields" class="credential-page__toolbar">
          <div class="credential-page__filters">
            <div v-if="visibleFilters.q" class="credential-page__field">
              <label class="credential-page__label" for="credential-q">Tìm kiếm</label>
              <input
                id="credential-q"
                v-model="query"
                type="search"
                class="credential-page__input"
                placeholder="Vd. Canva Pro, admin@vaschools.edu.vn"
                @keydown.enter="loadCredentials(1)"
              />
            </div>

            <div v-if="visibleFilters.provider_id" class="credential-page__field">
              <label class="credential-page__label" for="credential-provider">Nhà cung cấp</label>
              <select id="credential-provider" v-model="providerId" class="credential-page__input">
                <option value="">Tất cả nhà cung cấp</option>
                <option v-for="p in providers" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>

            <div v-if="visibleFilters.account_type" class="credential-page__field">
              <label class="credential-page__label" for="credential-type">Loại tài khoản</label>
              <select id="credential-type" v-model="accountType" class="credential-page__input">
                <option value="">Tất cả loại</option>
                <option v-for="item in ACCOUNT_TYPES" :key="item.value" :value="item.value">{{ item.label }}</option>
              </select>
            </div>

            <div v-if="visibleFilters.status" class="credential-page__field">
              <label class="credential-page__label" for="credential-status">Trạng thái</label>
              <select id="credential-status" v-model="status" class="credential-page__input">
                <option v-for="item in STATUS_OPTIONS" :key="item.value || 'all'" :value="item.value">{{ item.label }}</option>
              </select>
            </div>
          </div>
        </div>

        <TablePagesBar
          placement="top"
          :from="meta.from || 0"
          :to="meta.to || 0"
          :total="meta.total || 0"
          :page="meta.current_page || 1"
          :last-page="meta.last_page || 1"
          :per-page="perPage"
          :zoom="tableZoom"
          show-search
          :show-clear-filters="hasActiveFilters"
          :filters-active="hasActiveFilters"
          @search="loadCredentials(1)"
          @clear-filters="clearFilters"
          @update:page="goPage"
          @update:per-page="perPage = $event"
          @update:zoom="tableZoom = $event"
        >
          <template #filters>
            <label v-for="item in CREDENTIAL_FILTERS" :key="item.key" class="credential-page__check">
              <input type="checkbox" :checked="visibleFilters[item.key]" @change="onFilterToggle(item.key, $event.target.checked)" />
              <span>{{ item.label }}</span>
            </label>
          </template>
          <template #settings>
            <label v-for="col in CREDENTIAL_COLUMNS" :key="col.key" class="credential-page__check">
              <input type="checkbox" :checked="visibleColumns[col.key]" @change="onColumnToggle(col.key, $event.target.checked)" />
              <span>{{ col.label }}</span>
            </label>
          </template>
        </TablePagesBar>

        <div
          ref="tableWrap"
          class="credential-page__table-wrap hide-scrollbar"
          :class="{ 'credential-page__table-wrap--resizing': resizing }"
          :style="{ '--table-zoom': tableZoom }"
        >
          <table class="credential-page__table" :style="{ width: tableWidthPx }">
            <colgroup>
              <col v-for="col in shownColumns" :key="col.key" :style="{ width: colWidthStyle(col.key) }" />
              <col style="width: 3rem" />
            </colgroup>
            <thead>
              <tr>
                <th v-for="col in shownColumns" :key="col.key">
                  <span>{{ col.label }}</span>
                  <button
                    type="button"
                    class="credential-page__resize"
                    aria-label="Kéo để đổi độ rộng cột"
                    @click.stop
                    @mousedown.stop.prevent="startResize($event, col.key)"
                  />
                </th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td :colspan="colSpan + 1" class="credential-page__empty">Đang tải…</td>
              </tr>
              <tr v-else-if="credentials.length === 0">
                <td :colspan="colSpan + 1" class="credential-page__empty">Chưa có tài khoản nào.</td>
              </tr>
              <tr v-for="credential in credentials" v-else :key="credential.id" @click="inspect(credential)">
                <td v-for="col in shownColumns" :key="col.key">
                  <template v-if="col.key === 'status'">
                    <span class="credential-page__status">
                      <span class="credential-page__dot" :class="`credential-page__dot--${statusTone(credential.status)}`" />
                      {{ statusLabel(credential.status) }}
                    </span>
                  </template>
                  <span v-else class="credential-page__cell">{{ cellText(credential, col.key) }}</span>
                </td>
                <td class="credential-page__row-menu" @click.stop>
                  <button
                    type="button"
                    class="credential-page__menu-trigger"
                    aria-label="Thao tác"
                    aria-haspopup="menu"
                    :aria-expanded="openMenuId === credential.id"
                    @click="toggleRowMenu(credential.id)"
                  >
                    <AppIcon name="gripVertical" :size="16" />
                  </button>
                  <div v-if="openMenuId === credential.id" class="credential-page__menu" role="menu">
                    <button type="button" role="menuitem" class="credential-page__menu-item" @click="inspect(credential); closeRowMenu()">
                      <AppIcon name="eye" :size="15" />
                      Xem chi tiết
                    </button>
                    <button
                      v-if="canManage"
                      type="button"
                      role="menuitem"
                      class="credential-page__menu-item"
                      @click="openEditDialog(credential); closeRowMenu()"
                    >
                      <AppIcon name="pencil" :size="15" />
                      Sửa
                    </button>
                    <button
                      v-if="canManage"
                      type="button"
                      role="menuitem"
                      class="credential-page__menu-item credential-page__menu-item--danger"
                      @click="askDelete(credential); closeRowMenu()"
                    >
                      <AppIcon name="trash" :size="15" />
                      Xoá
                    </button>
                  </div>
                </td>
              </tr>
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
    </div>

    <!-- Modal tạo/sửa (skill form-modal) -->
    <Teleport to="body">
      <Transition name="credential-dialog-fade">
        <div v-if="dialogOpen" class="credential-page__dialog" role="presentation" @mousedown.self="closeDialog">
          <div class="credential-page__dialog-panel" role="dialog" aria-modal="true" aria-labelledby="credential-form-title">
            <div class="credential-page__dialog-head">
              <span class="credential-page__dialog-icon" aria-hidden="true">
                <AppIcon name="lock" :size="22" :stroke-width="1.75" />
              </span>
              <h2 id="credential-form-title" class="credential-page__dialog-title">
                {{ dialogMode === 'create' ? 'Thêm tài khoản' : 'Sửa tài khoản' }}
              </h2>
              <button type="button" class="credential-page__dialog-close" aria-label="Đóng" :disabled="formSaving" @click="closeDialog">
                <AppIcon name="close" :size="16" />
              </button>
            </div>

            <div class="credential-page__dialog-tabs" role="tablist" aria-label="Thông tin tài khoản">
              <button
                type="button"
                class="credential-page__dialog-tab"
                :class="{ 'credential-page__dialog-tab--active': dialogTab === 'info' }"
                role="tab"
                :aria-selected="dialogTab === 'info' ? 'true' : 'false'"
                @click="dialogTab = 'info'"
              >
                Thông tin
              </button>
              <button
                type="button"
                class="credential-page__dialog-tab"
                :class="{ 'credential-page__dialog-tab--active': dialogTab === 'advanced' }"
                role="tab"
                :aria-selected="dialogTab === 'advanced' ? 'true' : 'false'"
                @click="dialogTab = 'advanced'"
              >
                Nâng cao
              </button>
            </div>

            <div class="credential-page__dialog-body hide-scrollbar">
              <form v-if="dialogTab === 'info'" class="credential-page__form-info" @submit.prevent="submitForm">
                <div class="credential-page__form-field">
                  <label class="credential-page__label" for="cred-name">Tên tài khoản</label>
                  <input id="cred-name" v-model="form.name" type="text" class="credential-page__input" placeholder="Canva Pro - Marketing" />
                </div>
                <div class="credential-page__form-field">
                  <label class="credential-page__label" for="cred-provider">Nhà cung cấp</label>
                  <select id="cred-provider" v-model="form.provider_id" class="credential-page__input">
                    <option value="">Chưa chọn</option>
                    <option v-for="p in providers" :key="p.id" :value="p.id">{{ p.name }}</option>
                  </select>
                </div>
                <div class="credential-page__form-field">
                  <label class="credential-page__label" for="cred-type">Loại tài khoản</label>
                  <select id="cred-type" v-model="form.account_type" class="credential-page__input">
                    <option v-for="item in ACCOUNT_TYPES" :key="item.value" :value="item.value">{{ item.label }}</option>
                  </select>
                </div>

                <div class="credential-page__form-field">
                  <label class="credential-page__label" for="cred-username">Tên đăng nhập</label>
                  <input id="cred-username" v-model="form.username" type="text" class="credential-page__input" placeholder="Vd. admin.marketing" />
                </div>
                <div class="credential-page__form-field">
                  <label class="credential-page__label" for="cred-email">Email</label>
                  <input id="cred-email" v-model="form.email" type="email" class="credential-page__input" placeholder="admin@vaschools.edu.vn" />
                </div>
                <div class="credential-page__form-field">
                  <label class="credential-page__label" for="cred-password">Mật khẩu</label>
                  <input
                    id="cred-password"
                    v-model="form.password"
                    type="text"
                    class="credential-page__input"
                    :placeholder="dialogMode === 'edit' ? 'Để trống nếu không đổi' : 'Nhập mật khẩu'"
                  />
                </div>

                <div class="credential-page__form-field credential-page__form-field--span2">
                  <label class="credential-page__check">
                    <input type="checkbox" v-model="form.is_google_login" />
                    <span>Đăng nhập bằng tài khoản Google công ty</span>
                  </label>
                </div>
                <div v-if="form.is_google_login" class="credential-page__form-field">
                  <label class="credential-page__label" for="cred-google-owner">Google này thuộc về ai</label>
                  <input id="cred-google-owner" v-model="form.google_account_owner" type="text" class="credential-page__input" placeholder="Vd. Phòng Công nghệ" />
                </div>

                <template v-if="showInfraFields">
                  <div class="credential-page__form-field">
                    <label class="credential-page__label" for="cred-server">Tên server</label>
                    <input id="cred-server" v-model="form.server_name" type="text" class="credential-page__input" placeholder="Vd. srv-app-01" />
                  </div>
                  <div class="credential-page__form-field">
                    <label class="credential-page__label" for="cred-cluster">Cụm VPS</label>
                    <input id="cred-cluster" v-model="form.vps_cluster" type="text" class="credential-page__input" placeholder="Vd. Cluster HCM-01" />
                  </div>
                  <div class="credential-page__form-field">
                    <label class="credential-page__label" for="cred-domain">Domain</label>
                    <input id="cred-domain" v-model="form.domain" type="text" class="credential-page__input" placeholder="vaschools.edu.vn" />
                  </div>
                  <div class="credential-page__form-field">
                    <label class="credential-page__label" for="cred-database">Tên database</label>
                    <input id="cred-database" v-model="form.database_name" type="text" class="credential-page__input" placeholder="Vd. va_workspace_prod" />
                  </div>
                  <div class="credential-page__form-field">
                    <label class="credential-page__check">
                      <input type="checkbox" v-model="form.is_root_account" />
                      <span>Tài khoản root</span>
                    </label>
                  </div>
                  <div class="credential-page__form-field">
                    <label class="credential-page__check">
                      <input type="checkbox" v-model="form.is_iam_account" />
                      <span>Tài khoản IAM</span>
                    </label>
                  </div>
                </template>

                <div class="credential-page__form-field credential-page__form-field--span3">
                  <label class="credential-page__label" for="cred-notes">Ghi chú</label>
                  <textarea
                    id="cred-notes"
                    v-model="form.notes"
                    class="credential-page__input credential-page__textarea"
                    rows="3"
                    placeholder="Vd. Dùng cho chiến dịch marketing quý 3"
                  />
                </div>
              </form>

              <form v-else class="credential-page__form-info credential-page__form-info--advanced" @submit.prevent="submitForm">
                <div class="credential-page__form-field">
                  <label class="credential-page__label" for="cred-purchased">Ngày mua</label>
                  <input id="cred-purchased" v-model="form.purchased_at" type="date" class="credential-page__input" placeholder="dd/mm/yyyy" />
                </div>
                <div class="credential-page__form-field">
                  <label class="credential-page__label" for="cred-expires">Ngày hết hạn</label>
                  <input id="cred-expires" v-model="form.expires_at" type="date" class="credential-page__input" placeholder="dd/mm/yyyy" />
                </div>
                <div class="credential-page__form-field">
                  <label class="credential-page__label" for="cred-cost">Chi phí / tháng</label>
                  <input id="cred-cost" v-model="form.monthly_cost" type="number" min="0" step="1000" class="credential-page__input" placeholder="Vd. 500000" />
                </div>
                <div class="credential-page__form-field">
                  <label class="credential-page__check">
                    <input type="checkbox" v-model="form.cost_hidden" />
                    <span>Ẩn chi phí trên danh sách</span>
                  </label>
                </div>
              </form>
            </div>

            <div class="credential-page__dialog-actions">
              <button type="button" class="credential-page__dialog-btn credential-page__dialog-btn--ghost" :disabled="formSaving" @click="closeDialog">
                Huỷ
              </button>
              <button type="button" class="credential-page__dialog-btn credential-page__dialog-btn--primary" :disabled="formSaving" @click="submitForm">
                {{ formSaving ? 'Đang lưu…' : 'Lưu' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <ConfirmDialog
      :open="Boolean(deleteTarget)"
      title="Xoá tài khoản?"
      :description="`Tài khoản “${deleteTarget?.name || ''}” sẽ bị xoá khỏi danh sách.`"
      confirm-label="Xoá"
      danger
      :loading="deleting"
      @update:open="(v) => { if (!v) deleteTarget = null; }"
      @confirm="confirmDelete"
    />
  </section>
</template>

<style scoped>
.credential-page {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
}

.credential-page__title {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.credential-page__title-icon-btn {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  margin-left: 0.125rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.credential-page__title-icon-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-primary);
}

.credential-page__title-icon-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.credential-page__title-icon-btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.credential-page__title-icon-btn--primary:hover:not(:disabled) {
  background: var(--color-primary-hover);
  color: var(--color-on-primary);
}

.credential-page__summary {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-4);
  margin-top: var(--space-3);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.credential-page__summary-item {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.credential-page__dot--neutral {
  background: var(--color-text-muted);
}

.credential-page__spin {
  animation: credential-spin 0.8s linear infinite;
}

@keyframes credential-spin {
  to {
    transform: rotate(360deg);
  }
}

.credential-page__body {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-4);
  overflow: hidden;
}

.credential-page__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.credential-page__toolbar {
  position: relative;
  z-index: 6;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin: var(--space-3) 0;
}

.credential-page__filters {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--space-3);
  width: 100%;
}

.credential-page__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
  width: 100%;
}

.credential-page__field--span2 {
  grid-column: span 2;
}

.credential-page__field--span3 {
  grid-column: span 3;
}

.credential-page__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.credential-page__input {
  width: 100%;
  min-width: 0;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  transition: box-shadow 0.15s ease, border-color 0.15s ease;
}

.credential-page__input::placeholder {
  color: var(--color-text-muted);
  opacity: 0.75;
}

.credential-page__input:focus-visible,
.credential-page__input:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 12%, transparent);
}

.credential-page__textarea {
  resize: vertical;
  min-height: 4.5rem;
}

.credential-page__check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.credential-page__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.credential-page__table-wrap--resizing {
  cursor: col-resize;
  user-select: none;
}

.credential-page__table {
  min-width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: calc(0.875rem * var(--table-zoom, 1));
}

.credential-page__table thead th {
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

.credential-page__resize {
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

.credential-page__resize::after {
  content: '';
  position: absolute;
  top: 25%;
  right: 2px;
  width: 2px;
  height: 50%;
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.credential-page__resize:hover::after {
  background: var(--color-primary);
}

.credential-page__table thead th {
  position: relative;
}

.credential-page__table tbody td {
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  vertical-align: middle;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.credential-page__table tbody tr {
  cursor: pointer;
  transition: background-color 0.12s ease;
}

.credential-page__table tbody tr:hover td {
  background: var(--color-surface-muted);
}

.credential-page__table tbody tr:nth-child(even) td {
  background: color-mix(in srgb, var(--color-surface-muted) 45%, var(--color-surface));
}

.credential-page__table tbody tr:nth-child(even):hover td {
  background: var(--color-surface-muted);
}

.credential-page__cell {
  display: block;
  white-space: nowrap;
}

.credential-page__status {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.credential-page__dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.credential-page__dot--success {
  background: var(--color-success);
}

.credential-page__dot--info {
  background: var(--color-info);
}

.credential-page__dot--warning {
  background: var(--color-warning);
}

.credential-page__dot--danger {
  background: var(--color-danger);
}

.credential-page__empty {
  padding: var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
  white-space: normal;
}

.credential-page__row-menu {
  position: relative;
  text-align: center;
}

.credential-page__menu-trigger {
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

.credential-page__menu-trigger:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.credential-page__menu {
  position: absolute;
  right: 0;
  top: calc(100% + 0.25rem);
  z-index: 20;
  width: 11rem;
  padding: var(--space-2);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-lg);
}

.credential-page__menu-item {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 0.5rem;
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

.credential-page__menu-item:hover {
  background: var(--color-surface-muted);
}

.credential-page__menu-item--danger {
  color: var(--color-danger);
}

/* ---------- Modal tạo/sửa ---------- */
.credential-page__dialog {
  position: fixed;
  inset: 0;
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.credential-page__dialog-panel {
  width: min(64rem, calc(100vw - 2.5rem));
  height: min(40rem, calc(100vh - 2.5rem));
  max-height: calc(100vh - 2.5rem);
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  padding: 1.5rem 1.75rem 1.25rem;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.credential-page__dialog-head {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.credential-page__dialog-icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 15%, transparent);
}

.credential-page__dialog-title {
  flex: 1;
  margin: 0;
  color: var(--color-text);
  font-size: 1.25rem;
  font-weight: 700;
}

.credential-page__dialog-close {
  display: inline-flex;
  flex-shrink: 0;
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

.credential-page__dialog-close:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.credential-page__dialog-tabs {
  flex-shrink: 0;
  display: flex;
  gap: var(--space-2);
  box-shadow: 0 1px 0 var(--color-border);
}

.credential-page__dialog-tab {
  padding: var(--space-2) var(--space-3);
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 600;
  box-shadow: 0 2px 0 transparent;
  cursor: pointer;
}

.credential-page__dialog-tab--active {
  color: var(--color-primary);
  box-shadow: 0 2px 0 var(--color-primary);
}

.credential-page__dialog-body {
  flex: 1;
  min-height: 0;
  overflow: auto;
}

.credential-page__form-info {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-3) var(--space-4);
  align-items: start;
}

.credential-page__form-info--advanced {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.credential-page__form-field {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  min-width: 0;
}

.credential-page__dialog-actions {
  flex-shrink: 0;
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

.credential-page__dialog-btn {
  padding: 0.625rem 1.25rem;
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.credential-page__dialog-btn--ghost {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.credential-page__dialog-btn--ghost:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.credential-page__dialog-btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.credential-page__dialog-btn--primary:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.credential-page__dialog-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.credential-dialog-fade-enter-active {
  transition: opacity 0.18s ease;
}

.credential-dialog-fade-enter-active .credential-page__dialog-panel {
  transition: opacity 0.18s ease, transform 0.18s cubic-bezier(0.22, 1, 0.36, 1);
}

.credential-dialog-fade-leave-active {
  transition: opacity 0.12s ease;
}

.credential-dialog-fade-enter-from,
.credential-dialog-fade-leave-to {
  opacity: 0;
}

.credential-dialog-fade-enter-from .credential-page__dialog-panel {
  opacity: 0;
  transform: translateY(0.5rem) scale(0.98);
}

@media (max-width: 1024px) {
  .credential-page__filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .credential-page__filters {
    grid-template-columns: minmax(0, 1fr);
  }

  .credential-page__dialog {
    padding: var(--space-4);
    align-items: flex-end;
  }

  .credential-page__dialog-panel {
    width: 100%;
    height: min(94vh, calc(100vh - 1.25rem));
    max-height: min(94vh, calc(100vh - 1.25rem));
    padding: var(--space-4);
  }

  .credential-page__form-info,
  .credential-page__form-info--advanced {
    grid-template-columns: minmax(0, 1fr);
  }

  .credential-page__field--span2,
  .credential-page__field--span3 {
    grid-column: span 1;
  }
}
</style>
