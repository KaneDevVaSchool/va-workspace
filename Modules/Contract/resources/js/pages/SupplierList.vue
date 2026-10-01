<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import { useDragScroll } from '@/composables/useDragScroll';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import {
  COLUMN_STORAGE_KEY,
  FILTER_STORAGE_KEY,
  SUPPLIER_COLUMNS,
  SUPPLIER_FILTERS,
  loadVisibility,
  loadZoom,
  saveVisibility,
  saveZoom,
} from '../constants/supplier.js';

const router = useRouter();
const auth = useAuthStore();

const suppliers = ref([]);
const options = ref({ types: [], groups: [], departments: [], owners: [], supplier_statuses: [], document_statuses: [], contract_statuses: [] });
const meta = ref({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 20 });
const counts = ref({ all: 0 });
const loading = ref(false);
const selected = ref(null);
const actionOpen = ref(null);
const deleteDialog = reactive({ open: false, row: null, loading: false });
const statusDialog = reactive({ open: false, row: null, status: '', reason: '', loading: false });
const tableWrap = ref(null);
const resizing = ref(false);
const tableZoom = ref(loadZoom());

const filters = reactive({
  q: '',
  status: '',
  supplier_type_id: '',
  supplier_group_id: '',
  department_id: '',
  owner_user_id: '',
  document_status: '',
  contract_status: '',
});
const perPage = ref(20);
const visibleColumns = reactive(loadVisibility(COLUMN_STORAGE_KEY, SUPPLIER_COLUMNS));
const visibleFilters = reactive(loadVisibility(FILTER_STORAGE_KEY, SUPPLIER_FILTERS));
const columnWidths = reactive(Object.fromEntries(SUPPLIER_COLUMNS.map((col) => [col.key, col.key === 'supplier' ? 280 : col.key === 'actions' ? 72 : 150])));

useDragScroll(tableWrap, { isBlocked: () => resizing.value });

const canManage = computed(() => auth.can('contract.manage_department') || auth.can('contract.*'));
const shownColumns = computed(() => SUPPLIER_COLUMNS.filter((col) => visibleColumns[col.key]));
const tableWidthPx = computed(() => `${shownColumns.value.reduce((sum, col) => sum + (columnWidths[col.key] || 140), 0)}px`);
const hasVisibleFilterFields = computed(() => SUPPLIER_FILTERS.some((item) => visibleFilters[item.key]));
const filtersActive = computed(() => Object.values(filters).some(Boolean));
const filteredGroups = computed(() => {
  if (!filters.supplier_type_id) return options.value.groups || [];
  return (options.value.groups || []).filter((group) => Number(group.supplier_type_id) === Number(filters.supplier_type_id));
});

const statusTabs = computed(() => [
  { value: '', label: 'Tất cả', count: counts.value.all || 0 },
  ...(options.value.supplier_statuses || []).map((item) => ({ ...item, count: counts.value[item.value] || 0 })),
]);

function filterParams() {
  const params = {};
  for (const [key, value] of Object.entries(filters)) {
    if (value !== '') params[key] = value;
  }
  return params;
}

async function loadOptions() {
  const { data } = await window.axios.get('/api/contract/suppliers/options');
  options.value = data;
}

async function loadSuppliers(page = 1) {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/contract/suppliers', {
      params: { ...filterParams(), per_page: perPage.value, page },
    });
    suppliers.value = data.suppliers || [];
    meta.value = data.meta || meta.value;
    counts.value = data.status_counts || {};
    if (selected.value && !suppliers.value.some((item) => item.id === selected.value.id)) selected.value = null;
    await nextTick();
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được danh sách nhà cung cấp.');
  } finally {
    loading.value = false;
  }
}

function setStatus(value) {
  filters.status = value;
  loadSuppliers(1);
}

function clearFilters() {
  Object.keys(filters).forEach((key) => { filters[key] = ''; });
  loadSuppliers(1);
}

function openCreate() {
  router.push({ name: 'manager.contract.suppliers.create' });
}

function openDetail(row) {
  router.push({ name: 'manager.contract.suppliers.show', params: { id: row.id } });
}

function openEdit(row) {
  router.push({ name: 'manager.contract.suppliers.edit', params: { id: row.id } });
}

function askDeleteSupplier(row) {
  actionOpen.value = null;
  deleteDialog.row = row;
  deleteDialog.open = true;
}

async function confirmDeleteSupplier() {
  if (!deleteDialog.row) return;
  deleteDialog.loading = true;
  try {
    await window.axios.delete(`/api/contract/suppliers/${deleteDialog.row.id}`);
    showClientToast('success', 'Đã xoá nhà cung cấp.');
    deleteDialog.open = false;
    loadSuppliers(meta.value.current_page);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không xoá được nhà cung cấp.');
  } finally {
    deleteDialog.loading = false;
  }
}

function openStatusDialog(row, status) {
  actionOpen.value = null;
  Object.assign(statusDialog, { open: true, row, status, reason: '', loading: false });
}

async function confirmStatusChange() {
  if (!statusDialog.row || !statusDialog.reason.trim()) {
    showClientToast('error', 'Vui lòng nhập lý do đổi trạng thái.');
    return;
  }
  statusDialog.loading = true;
  try {
    await window.axios.post(`/api/contract/suppliers/${statusDialog.row.id}/status`, { status: statusDialog.status, reason: statusDialog.reason.trim() });
    showClientToast('success', 'Đã cập nhật trạng thái nhà cung cấp.');
    statusDialog.open = false;
    loadSuppliers(meta.value.current_page);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không đổi được trạng thái.');
  } finally {
    statusDialog.loading = false;
  }
}

function cellText(row, key) {
  if (key === 'code') return row.code;
  if (key === 'supplier') return `${row.name || ''} ${row.short_name || ''}`;
  if (key === 'tax_code') return row.formatted_tax_code || row.tax_code || '';
  if (key === 'type_group') return [row.type_name, row.group_name].filter(Boolean).join(' · ');
  if (key === 'owner') return row.owner?.name || '';
  if (key === 'documents') return row.document_summary?.label || '';
  if (key === 'active_contracts') return String(row.active_contracts_count ?? 0);
  if (key === 'status') return row.status_label || '';
  return '';
}

function onResizeStart(event, key) {
  resizing.value = true;
  const startX = event.clientX;
  const startWidth = columnWidths[key] || 140;
  const onMove = (moveEvent) => {
    columnWidths[key] = Math.max(80, startWidth + moveEvent.clientX - startX);
  };
  const onUp = () => {
    resizing.value = false;
    document.removeEventListener('mousemove', onMove);
    document.removeEventListener('mouseup', onUp);
  };
  document.addEventListener('mousemove', onMove);
  document.addEventListener('mouseup', onUp);
}

function onDocumentPointerDown(event) {
  if (!actionOpen.value) return;
  if (!event.target.closest?.('.supplier-list__actions')) actionOpen.value = null;
}

function onDocumentKeydown(event) {
  if (event.key === 'Escape') actionOpen.value = null;
}

watch(tableZoom, saveZoom);
watch(visibleColumns, (value) => saveVisibility(COLUMN_STORAGE_KEY, value), { deep: true });
watch(visibleFilters, (value) => saveVisibility(FILTER_STORAGE_KEY, value), { deep: true });

onMounted(async () => {
  document.addEventListener('pointerdown', onDocumentPointerDown, true);
  document.addEventListener('keydown', onDocumentKeydown);
  await loadOptions();
  await loadSuppliers(1);
});

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onDocumentPointerDown, true);
  document.removeEventListener('keydown', onDocumentKeydown);
});
</script>

<template>
  <PageHeader
    title="Nhà cung cấp"
    :subtitle="`${meta.total || 0} nhà cung cấp`"
  >
    <template #actions>
      <button type="button" class="supplier-list__ghost" @click="loadSuppliers(meta.current_page)">
        <AppIcon name="refresh" :size="16" />
        <span>Làm mới</span>
      </button>
      <button v-if="canManage" type="button" class="supplier-list__primary" @click="openCreate">
        <AppIcon name="plus" :size="16" />
        <span>Thêm nhà cung cấp</span>
      </button>
    </template>
  </PageHeader>

  <section class="supplier-list">
    <div class="supplier-list__main">
      <div class="supplier-list__tabs" aria-label="Trạng thái nhà cung cấp">
        <button
          v-for="tab in statusTabs"
          :key="tab.value || 'all'"
          type="button"
          class="supplier-list__tab"
          :class="{ 'supplier-list__tab--active': filters.status === tab.value }"
          @click="setStatus(tab.value)"
        >
          <span>{{ tab.label }}</span>
          <strong>{{ tab.count }}</strong>
        </button>
      </div>

      <div v-if="hasVisibleFilterFields" class="supplier-list__filters">
        <input
          v-if="visibleFilters.q"
          v-model="filters.q"
          type="search"
          placeholder="Tìm theo tên, mã số thuế, mã NCC..."
          @keyup.enter="loadSuppliers(1)"
        />
        <select v-if="visibleFilters.supplier_type_id" v-model="filters.supplier_type_id" @change="filters.supplier_group_id = ''">
          <option value="">Loại: Tất cả</option>
          <option v-for="item in options.types" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>
        <select v-if="visibleFilters.supplier_group_id" v-model="filters.supplier_group_id">
          <option value="">Nhóm: Tất cả</option>
          <option v-for="item in filteredGroups" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>
        <select v-if="visibleFilters.department_id" v-model="filters.department_id">
          <option value="">Đơn vị quản lý: Tất cả</option>
          <option v-for="item in options.departments" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>
        <select v-if="visibleFilters.owner_user_id" v-model="filters.owner_user_id">
          <option value="">Người phụ trách: Tất cả</option>
          <option v-for="item in options.owners" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>
        <select v-if="visibleFilters.document_status" v-model="filters.document_status">
          <option value="">Hồ sơ: Tất cả</option>
          <option v-for="item in options.document_statuses" :key="item.value" :value="item.value">{{ item.label }}</option>
        </select>
        <select v-if="visibleFilters.contract_status" v-model="filters.contract_status">
          <option value="">Hợp đồng: Tất cả</option>
          <option v-for="item in options.contract_statuses" :key="item.value" :value="item.value">{{ item.label }}</option>
        </select>
      </div>

      <TablePagesBar
        placement="top"
        show-search
        :show-clear-filters="filtersActive"
        :filters-active="filtersActive"
        :from="meta.from"
        :to="meta.to"
        :total="meta.total"
        :page="meta.current_page"
        :last-page="meta.last_page"
        :per-page="perPage"
        :zoom="tableZoom"
        @search="loadSuppliers(1)"
        @clear-filters="clearFilters"
        @update:page="loadSuppliers"
        @update:per-page="(value) => { perPage = value; loadSuppliers(1); }"
        @update:zoom="(value) => { tableZoom = value; }"
      >
        <template #filters>
          <label v-for="item in SUPPLIER_FILTERS" :key="item.key" class="supplier-list__check">
            <input v-model="visibleFilters[item.key]" type="checkbox" />
            <span>{{ item.label }}</span>
          </label>
        </template>
        <template #settings>
          <label v-for="item in SUPPLIER_COLUMNS.filter((col) => col.key !== 'actions')" :key="item.key" class="supplier-list__check">
            <input v-model="visibleColumns[item.key]" type="checkbox" />
            <span>{{ item.label }}</span>
          </label>
        </template>
      </TablePagesBar>

      <div ref="tableWrap" class="supplier-list__table-wrap hide-scrollbar">
        <table class="supplier-list__table" :style="{ minWidth: tableWidthPx, fontSize: `${tableZoom}rem` }">
          <colgroup>
            <col v-for="column in shownColumns" :key="column.key" :style="{ width: `${columnWidths[column.key]}px` }" />
          </colgroup>
          <thead>
            <tr>
              <th v-for="column in shownColumns" :key="column.key">
                <span>{{ column.label }}</span>
                <button type="button" class="supplier-list__resize" aria-label="Kéo để đổi độ rộng cột" @mousedown.prevent="onResizeStart($event, column.key)" />
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td :colspan="shownColumns.length">Đang tải dữ liệu...</td>
            </tr>
            <tr v-else-if="suppliers.length === 0">
              <td :colspan="shownColumns.length">Chưa có nhà cung cấp phù hợp.</td>
            </tr>
            <template v-else>
              <tr
                v-for="row in suppliers"
                :key="row.id"
                :class="{ 'supplier-list__row--selected': selected?.id === row.id }"
                @click="selected = row"
                @dblclick="openDetail(row)"
              >
                <td v-for="column in shownColumns" :key="column.key">
                  <template v-if="column.key === 'supplier'">
                    <div class="supplier-list__name">
                      <strong>{{ row.name }}</strong>
                      <small>{{ row.short_name || '—' }}</small>
                    </div>
                  </template>
                  <template v-else-if="column.key === 'documents'">
                    <span class="supplier-list__pill" :class="{ 'supplier-list__pill--warn': row.document_summary?.missing_required || row.document_summary?.expiring_soon }">
                      {{ row.document_summary?.label || 'Đầy đủ' }}
                    </span>
                  </template>
                  <template v-else-if="column.key === 'status'">
                    <span class="supplier-list__status">{{ row.status_label }}</span>
                  </template>
                  <template v-else-if="column.key === 'actions'">
                    <div class="supplier-list__actions" @click.stop>
                      <button type="button" aria-label="Thao tác" class="supplier-list__action-trigger" @click="actionOpen = actionOpen === row.id ? null : row.id">
                        <AppIcon name="moreVertical" :size="18" />
                      </button>
                      <div v-if="actionOpen === row.id" class="supplier-list__menu" role="menu">
                        <button type="button" role="menuitem" @click="openDetail(row)">Xem chi tiết</button>
                        <button v-if="row.can_manage" type="button" role="menuitem" @click="openEdit(row)">Sửa thông tin</button>
                        <button v-if="row.can_manage && row.status === 'active'" type="button" role="menuitem" @click="openStatusDialog(row, 'suspended')">Tạm ngưng hợp tác...</button>
                        <button v-if="row.can_manage && row.status === 'suspended'" type="button" role="menuitem" @click="openStatusDialog(row, 'active')">Khôi phục hợp tác...</button>
                        <button v-if="row.can_manage && row.status !== 'terminated'" type="button" role="menuitem" @click="openStatusDialog(row, 'terminated')">Ngừng hợp tác...</button>
                        <button v-if="row.can_delete" type="button" role="menuitem" class="supplier-list__danger" @click="askDeleteSupplier(row)">Xóa</button>
                      </div>
                    </div>
                  </template>
                  <template v-else>
                    {{ cellText(row, column.key) || '—' }}
                  </template>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <TablePagesBar
        placement="bottom"
        paging-only
        :from="meta.from"
        :to="meta.to"
        :total="meta.total"
        :page="meta.current_page"
        :last-page="meta.last_page"
        :per-page="perPage"
        @update:page="loadSuppliers"
        @update:per-page="(value) => { perPage = value; loadSuppliers(1); }"
      />
    </div>

    <aside v-if="selected" class="supplier-list__side">
      <button type="button" class="supplier-list__side-close" aria-label="Đóng chi tiết" @click="selected = null">
        <AppIcon name="close" :size="16" />
      </button>
      <h2>{{ selected.name }}</h2>
      <p>{{ selected.code }} · {{ selected.formatted_tax_code || 'chưa có MST' }}</p>
      <div class="supplier-list__detail-row"><span>Trạng thái NCC</span><strong>{{ selected.status_label }}</strong></div>
      <div class="supplier-list__detail-row"><span>Hồ sơ</span><strong>{{ selected.document_summary?.label }}</strong></div>
      <div class="supplier-list__detail-row"><span>HĐ hiệu lực</span><strong>{{ selected.active_contracts_count }}</strong></div>
      <div class="supplier-list__detail-row"><span>Đơn vị quản lý</span><strong>{{ selected.department_name || '—' }}</strong></div>
      <div class="supplier-list__detail-row"><span>Phụ trách</span><strong>{{ selected.owner?.name || '—' }}</strong></div>
      <button type="button" class="supplier-list__primary supplier-list__side-action" @click="openDetail(selected)">Mở hồ sơ nhà cung cấp</button>
    </aside>

    <ConfirmDialog
      v-model:open="deleteDialog.open"
      title="Xoá nhà cung cấp"
      :description="deleteDialog.row ? `Chỉ xoá được NCC nháp chưa phát sinh hợp đồng. Xác nhận xoá ${deleteDialog.row.code}?` : ''"
      confirm-label="Xoá"
      danger
      :loading="deleteDialog.loading"
      @confirm="confirmDeleteSupplier"
    />

    <Teleport to="body">
      <div v-if="statusDialog.open" class="supplier-list__status-dialog" role="presentation" @mousedown.self="statusDialog.open = false">
        <div class="supplier-list__status-panel" role="dialog" aria-modal="true" aria-label="Đổi trạng thái nhà cung cấp">
          <h2>Đổi trạng thái nhà cung cấp</h2>
          <p>{{ statusDialog.row?.code }} · bắt buộc ghi lý do.</p>
          <textarea v-model="statusDialog.reason" rows="4" placeholder="Nhập lý do..." />
          <div>
            <button type="button" class="supplier-list__ghost" :disabled="statusDialog.loading" @click="statusDialog.open = false">Huỷ</button>
            <button type="button" class="supplier-list__primary" :disabled="statusDialog.loading" @click="confirmStatusChange">Lưu trạng thái</button>
          </div>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
.supplier-list {
  height: calc(100vh - 8.5rem);
  min-height: 0;
  display: flex;
  gap: var(--space-4);
  padding: var(--space-4);
  background: var(--color-bg);
}

.supplier-list__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-sm);
}

.supplier-list__tabs,
.supplier-list__filters {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 75%, transparent);
}

.supplier-list__tab {
  display: inline-flex;
  gap: var(--space-2);
  align-items: center;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-full);
  padding: 0.45rem 0.75rem;
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
}

.supplier-list__tab--active {
  border-color: var(--color-primary);
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 8%, var(--color-surface));
}

.supplier-list__filters input,
.supplier-list__filters select {
  min-height: 2.25rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 0 var(--space-3);
  background: var(--color-surface);
  color: var(--color-text);
}

.supplier-list__filters input {
  flex: 1 1 18rem;
}

.supplier-list__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
}

.supplier-list__table {
  width: 100%;
  table-layout: fixed;
  border-collapse: separate;
  border-spacing: 0;
}

.supplier-list__table th,
.supplier-list__table td {
  position: relative;
  padding: 0.75rem 1rem;
  text-align: left;
  white-space: nowrap;
  vertical-align: middle;
  box-shadow: 0 1px 0 var(--color-border);
}

.supplier-list__table th {
  color: var(--color-text-muted);
  font-weight: 600;
  background: var(--color-surface-muted);
}

.supplier-list__table tbody tr {
  cursor: pointer;
}

.supplier-list__table tbody tr:hover,
.supplier-list__row--selected {
  background: color-mix(in srgb, var(--color-primary) 6%, transparent);
}

.supplier-list__resize {
  position: absolute;
  top: 0;
  right: 0;
  width: 0.5rem;
  height: 100%;
  border: 0;
  background: transparent;
  cursor: col-resize;
}

.supplier-list__resize::after {
  content: '';
  position: absolute;
  top: 25%;
  right: 0.2rem;
  width: 1px;
  height: 50%;
  background: var(--color-border);
}

.supplier-list__name {
  display: grid;
  gap: 0.15rem;
}

.supplier-list__name small {
  color: var(--color-text-muted);
}

.supplier-list__pill,
.supplier-list__status {
  display: inline-flex;
  border-radius: var(--radius-full);
  padding: 0.25rem 0.55rem;
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.supplier-list__pill--warn {
  color: var(--color-warning);
  background: color-mix(in srgb, var(--color-warning) 12%, var(--color-surface));
}

.supplier-list__actions {
  position: relative;
  display: inline-flex;
}

.supplier-list__action-trigger,
.supplier-list__ghost,
.supplier-list__primary,
.supplier-list__side-close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  min-height: 2.25rem;
  padding: 0 var(--space-3);
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
}

.supplier-list__primary {
  border-color: var(--color-primary);
  background: var(--color-primary);
  color: var(--color-primary-contrast);
}

.supplier-list__menu {
  position: absolute;
  right: 0;
  top: calc(100% + 0.4rem);
  z-index: 20;
  min-width: 13rem;
  display: grid;
  padding: var(--space-2);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.supplier-list__menu button {
  border: 0;
  border-radius: var(--radius-md);
  padding: 0.55rem 0.65rem;
  background: transparent;
  color: var(--color-text);
  text-align: left;
  cursor: pointer;
}

.supplier-list__menu button:hover {
  background: var(--color-surface-muted);
}

.supplier-list__danger {
  color: var(--color-danger) !important;
}

.supplier-list__check {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-width: 12rem;
  padding: 0.25rem 0;
}

.supplier-list__side {
  position: relative;
  flex: 0 0 28rem;
  max-width: 28rem;
  min-width: 0;
  overflow: hidden auto;
  padding: var(--space-5);
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-sm);
}

.supplier-list__side h2 {
  margin: 0 2rem 0.25rem 0;
  font-size: 1.15rem;
}

.supplier-list__side p {
  margin: 0 0 var(--space-4);
  color: var(--color-text-muted);
}

.supplier-list__side-close {
  position: absolute;
  top: var(--space-3);
  right: var(--space-3);
  width: 2rem;
  min-height: 2rem;
  padding: 0;
}

.supplier-list__detail-row {
  display: flex;
  justify-content: space-between;
  gap: var(--space-3);
  padding: 0.75rem 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.supplier-list__detail-row span {
  color: var(--color-text-muted);
}

.supplier-list__detail-row span::after {
  content: ':';
}

.supplier-list__detail-row strong {
  text-align: right;
  font-style: italic;
  font-weight: 400;
}

.supplier-list__side-action {
  width: 100%;
  margin-top: var(--space-4);
}

.supplier-list__status-dialog {
  position: fixed;
  inset: 0;
  z-index: 410;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
  background: color-mix(in srgb, #000000 45%, transparent);
}

.supplier-list__status-panel {
  width: min(30rem, 100%);
  display: grid;
  gap: var(--space-3);
  border-radius: var(--radius-lg);
  padding: var(--space-5);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.supplier-list__status-panel h2,
.supplier-list__status-panel p {
  margin: 0;
}

.supplier-list__status-panel p {
  color: var(--color-text-muted);
}

.supplier-list__status-panel textarea {
  width: 100%;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: var(--space-3);
  background: var(--color-surface);
  color: var(--color-text);
}

.supplier-list__status-panel div {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

@media (max-width: 64rem) {
  .supplier-list {
    flex-direction: column;
    height: auto;
  }

  .supplier-list__side {
    flex-basis: auto;
    max-width: none;
    max-height: 42vh;
  }
}
</style>
