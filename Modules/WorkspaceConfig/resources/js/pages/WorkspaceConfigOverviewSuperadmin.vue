<script setup>
//
// superadmin/workspace-config — tổng hợp workspace của TẤT CẢ phòng ban.
// Bảng theo mẫu ActivityLog: filter, 2 thanh trang, kéo cột, panel chi tiết
// đẩy ngang. Bấm dòng mở panel (không modal); vào trang chi tiết từ panel.
// director = trưởng đơn vị + email — tạm từ role department_director,
// cùng shape khi sau này load phòng ban từ API HRM.
//
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import { showClientToast } from '@/lib/clientToast';
import { useDragScroll } from '@/composables/useDragScroll';
import StatusBadge from '../components/StatusBadge.vue';
import {
  COLUMN_STORAGE_KEY,
  COLUMN_WIDTH_KEY,
  CONFIG_FILTER_OPTIONS,
  DIRECTOR_FILTER_OPTIONS,
  FILTER_STORAGE_KEY,
  OVERVIEW_COLUMNS,
  OVERVIEW_FILTERS,
  STATUS_FILTER_OPTIONS,
  ZOOM_STORAGE_KEY,
  departmentConfigLabel,
  departmentStatusLabel,
  directorEmail,
  directorName,
  loadVisibility,
  saveVisibility,
} from '../constants/overview.js';

const CELL_PAD_X = 32;
const COL_EXTRA = 24;
const DIRECTOR_AVATAR_EXTRA = 42;
const ACTION_COL_PX = 56;
const ACTION_MENU_WIDTH = 200;
let measureCtx = null;
let wrapObserver = null;

const router = useRouter();
const allDepartments = ref([]);
const loading = ref(false);
const selected = ref(null);
const openActionMenuId = ref(null);
const actionMenuPos = reactive({ top: 0, left: 0, flip: false });

const query = ref('');
const isActive = ref('');
const hasConfig = ref('');
const hasDirector = ref('');
const page = ref(1);
const perPage = ref(20);

const visibleColumns = reactive(loadVisibility(COLUMN_STORAGE_KEY, OVERVIEW_COLUMNS));
const visibleFilters = reactive(loadVisibility(FILTER_STORAGE_KEY, OVERVIEW_FILTERS));

const tableWrap = ref(null);
const resizing = ref(false);

useDragScroll(tableWrap, { isBlocked: () => resizing.value });
const MIN_COL_PX = 72;
const columnWidths = reactive(loadColumnWidths());
const tableZoom = ref(loadZoom());

const shownColumns = computed(() => OVERVIEW_COLUMNS.filter((col) => visibleColumns[col.key]));
const colSpan = computed(() => Math.max(shownColumns.value.length, 1) + 1);

const filteredDepartments = computed(() => {
  const q = query.value.trim().toLowerCase();
  return allDepartments.value.filter((department) => {
    if (q) {
      const hay = `${department.name ?? ''} ${department.code ?? ''} ${directorName(department)} ${directorEmail(department)}`.toLowerCase();
      if (!hay.includes(q)) return false;
    }
    if (isActive.value === 'yes' && !department.is_active) return false;
    if (isActive.value === 'no' && department.is_active) return false;
    if (hasConfig.value === 'yes' && !department.has_config) return false;
    if (hasConfig.value === 'no' && department.has_config) return false;
    if (hasDirector.value === 'yes' && !department.director) return false;
    if (hasDirector.value === 'no' && department.director) return false;
    return true;
  });
});

const lastPage = computed(() => Math.max(1, Math.ceil(filteredDepartments.value.length / perPage.value)));

const meta = computed(() => {
  const total = filteredDepartments.value.length;
  const current = Math.min(Math.max(page.value, 1), lastPage.value);
  const from = total === 0 ? 0 : (current - 1) * perPage.value + 1;
  const to = Math.min(current * perPage.value, total);
  return { current_page: current, last_page: lastPage.value, total, from, to, per_page: perPage.value };
});

const pageDepartments = computed(() => {
  const start = (meta.value.current_page - 1) * perPage.value;
  return filteredDepartments.value.slice(start, start + perPage.value);
});

const hasActiveFilters = computed(
  () =>
    Boolean(query.value.trim()) ||
    Boolean(isActive.value) ||
    Boolean(hasConfig.value) ||
    Boolean(hasDirector.value),
);

const hiddenActiveFilterLabels = computed(() =>
  OVERVIEW_FILTERS.filter((item) => !visibleFilters[item.key] && filterHasValue(item.key)).map(
    (item) => item.label,
  ),
);

const hasVisibleFilterFields = computed(() => OVERVIEW_FILTERS.some((item) => visibleFilters[item.key]));

const emptyTableMessage = computed(() =>
  hasActiveFilters.value ? 'Không có phòng ban khớp bộ lọc.' : 'Chưa có phòng ban nào.',
);

const stats = computed(() => {
  const rows = allDepartments.value;
  return {
    total: rows.length,
    active: rows.filter((department) => department.is_active).length,
    noDirector: rows.filter((department) => !department.director).length,
    noConfig: rows.filter((department) => !department.has_config).length,
  };
});

const tableWidthPx = computed(() => {
  const keys = shownColumns.value.map((col) => col.key);
  const sum = keys.reduce((total, key) => total + (Number(columnWidths[key]) || 0), 0) + ACTION_COL_PX;
  return sum > ACTION_COL_PX ? `${sum}px` : '100%';
});

function filterHasValue(key) {
  if (key === 'q') return Boolean(query.value.trim());
  if (key === 'is_active') return Boolean(isActive.value);
  if (key === 'has_config') return Boolean(hasConfig.value);
  if (key === 'has_director') return Boolean(hasDirector.value);
  return false;
}

async function loadDepartments() {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/workspace-config/overview');
    allDepartments.value = data.departments ?? [];
    if (selected.value && !allDepartments.value.some((department) => department.id === selected.value.id)) {
      selected.value = null;
    }
    nextTick(fitColumnsToContent);
  } catch {
    showClientToast('error', 'Không tải được danh sách phòng ban.');
  } finally {
    loading.value = false;
  }
}

function goPage(nextPage) {
  if (nextPage < 1 || nextPage > lastPage.value || nextPage === page.value) {
    return;
  }
  page.value = nextPage;
}

function clearFilters() {
  query.value = '';
  isActive.value = '';
  hasConfig.value = '';
  hasDirector.value = '';
  page.value = 1;
}

function inspect(department) {
  closeActionMenu();
  selected.value = department;
}

function directorUser(department) {
  if (!department?.director) return null;
  return {
    id: department.director.id,
    name: department.director.name,
    email: department.director.email,
    avatar_url: department.director.avatar_url || null,
  };
}

function leadTone(department) {
  if (!department?.is_active) return 'danger';
  if (!department.director) return 'warning';
  if (!department.has_config) return 'info';
  return 'success';
}

function leadCaption(department) {
  if (!department?.is_active) return 'Phòng ban ngừng hoạt động';
  if (!department.director) return 'Chưa gán trưởng đơn vị';
  if (!department.has_config) return 'Chưa có cấu hình workspace';
  return 'Workspace đã sẵn sàng';
}

function statIsActive(kind) {
  const onlyThis =
    !query.value.trim() &&
    (kind === 'active' ? isActive.value === 'yes' : !isActive.value) &&
    (kind === 'noConfig' ? hasConfig.value === 'no' : !hasConfig.value) &&
    (kind === 'noDirector' ? hasDirector.value === 'no' : !hasDirector.value);
  if (kind === 'total') return !hasActiveFilters.value;
  return onlyThis;
}

function applyStatFilter(kind) {
  if (kind !== 'total' && statIsActive(kind)) {
    clearFilters();
    return;
  }
  query.value = '';
  isActive.value = kind === 'active' ? 'yes' : '';
  hasConfig.value = kind === 'noConfig' ? 'no' : '';
  hasDirector.value = kind === 'noDirector' ? 'no' : '';
}

function openDepartment(department) {
  closeActionMenu();
  router.push({
    name: 'superadmin.workspace-config.department-detail',
    params: { departmentId: department.id },
  });
}

function toggleActionMenu(id, event) {
  if (openActionMenuId.value === id) {
    openActionMenuId.value = null;
    return;
  }
  const rect = event.currentTarget.getBoundingClientRect();
  const flip = window.innerHeight - rect.bottom < 132;
  actionMenuPos.top = flip ? rect.top - 4 : rect.bottom + 4;
  actionMenuPos.left = Math.max(8, rect.right - ACTION_MENU_WIDTH);
  actionMenuPos.flip = flip;
  openActionMenuId.value = id;
}

function closeActionMenu() {
  openActionMenuId.value = null;
}

function handleDocumentClick(event) {
  if (!openActionMenuId.value) return;
  if (event.target?.closest?.('.wc-overview__actions, .wc-overview__action-menu')) return;
  closeActionMenu();
}

function handleTableScroll() {
  if (openActionMenuId.value) closeActionMenu();
}

function cellText(department, key) {
  if (key === 'name') return department.name || '—';
  if (key === 'is_active') return departmentStatusLabel(department.is_active);
  if (key === 'has_config') return departmentConfigLabel(department.has_config);
  if (key === 'director') return directorName(department) || 'Chưa gán trưởng đơn vị';
  if (key === 'member_count') return String(department.member_count ?? 0);
  if (key === 'code') return department.code || '—';
  if (key === 'criteria_count') return String(department.criteria_count ?? 0);
  if (key === 'id') return String(department.id ?? '—');
  return '—';
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
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
      return parsed;
    }
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
  const table = tableWrap.value?.querySelector('.wc-overview__table');
  return {
    header: fontOf(table?.querySelector('thead th'), '600 12px "Be Vietnam Pro", sans-serif'),
    cell: fontOf(table?.querySelector('tbody td'), '400 14px "Be Vietnam Pro", sans-serif'),
    muted: fontOf(table?.querySelector('.wc-overview__muted'), '400 12px "Be Vietnam Pro", sans-serif'),
  };
}

function columnContentWidth(key, fonts) {
  const label = OVERVIEW_COLUMNS.find((col) => col.key === key)?.label ?? '';
  let maxW = measureText(label, fonts.header);
  for (const department of pageDepartments.value) {
    if (key === 'director') {
      maxW = Math.max(maxW, measureText(cellText(department, 'director'), fonts.cell));
      if (directorEmail(department)) {
        maxW = Math.max(maxW, measureText(directorEmail(department), fonts.muted));
      }
    } else {
      maxW = Math.max(maxW, measureText(cellText(department, key), fonts.cell));
    }
  }
  if (key === 'is_active' || key === 'has_config') {
    maxW += 14;
  }
  const extra = key === 'director' ? DIRECTOR_AVATAR_EXTRA : 0;
  return Math.max(MIN_COL_PX, Math.ceil(maxW + CELL_PAD_X + COL_EXTRA + extra));
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

  const next = distributeExtraWidth(measured, keys, Math.max(0, wrap.clientWidth - ACTION_COL_PX));
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
    const remaining = OVERVIEW_COLUMNS.filter((col) => visibleColumns[col.key] && col.key !== key).length;
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

function handleDocumentKeydown(event) {
  if (event.key !== 'Escape') return;
  if (openActionMenuId.value) {
    closeActionMenu();
    return;
  }
  if (selected.value) {
    selected.value = null;
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
watch(selected, () => nextTick(fitColumnsToContent));
watch(shownColumns, () => nextTick(fitColumnsToContent));
watch(pageDepartments, () => nextTick(fitColumnsToContent));

watch([query, isActive, hasConfig, hasDirector, perPage], () => {
  page.value = 1;
});

watch(filteredDepartments, (rows) => {
  if (selected.value && !rows.some((department) => department.id === selected.value.id)) {
    selected.value = null;
  }
  if (page.value > lastPage.value) {
    page.value = lastPage.value;
  }
});

onMounted(() => {
  document.addEventListener('keydown', handleDocumentKeydown);
  document.addEventListener('click', handleDocumentClick);
  loadDepartments();
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
      tableWrap.value.addEventListener('scroll', handleTableScroll, { passive: true });
    }
  });
  document.fonts?.ready?.then(() => nextTick(fitColumnsToContent));
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleDocumentKeydown);
  document.removeEventListener('click', handleDocumentClick);
  tableWrap.value?.removeEventListener('scroll', handleTableScroll);
  wrapObserver?.disconnect();
});
</script>

<template>
  <section class="wc-overview">
    <PageHeader
      title="Cấu hình Workspace"
      subtitle="Tổng hợp workspace mọi phòng ban"
      icon="building"
      :breadcrumbs="[
        { label: 'Trang chủ', to: { name: 'home' } },
        { label: 'Cấu hình Workspace' },
      ]"
    >
      <template #actions>
        <RouterLink class="wc-overview__header-btn" :to="{ name: 'superadmin.workspace-config.global-menu' }">
          <AppIcon name="eyeOff" :size="16" />
          Menu toàn hệ thống
        </RouterLink>
        <button type="button" class="wc-overview__header-btn" :disabled="loading" @click="loadDepartments">
          <AppIcon name="refresh" :size="16" :class="{ 'wc-overview__spin': loading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

    <div class="wc-overview__stats" aria-label="Tóm tắt phòng ban">
      <button
        type="button"
        class="wc-overview__stat wc-overview__stat--info"
        :class="{ 'wc-overview__stat--on': statIsActive('total') }"
        @click="applyStatFilter('total')"
      >
        <span class="wc-overview__stat-value">{{ stats.total }}</span>
        <span class="wc-overview__stat-label">Tổng phòng ban</span>
      </button>
      <button
        type="button"
        class="wc-overview__stat wc-overview__stat--success"
        :class="{ 'wc-overview__stat--on': statIsActive('active') }"
        @click="applyStatFilter('active')"
      >
        <span class="wc-overview__stat-value">{{ stats.active }}</span>
        <span class="wc-overview__stat-label">Đang hoạt động</span>
      </button>
      <button
        type="button"
        class="wc-overview__stat wc-overview__stat--warning"
        :class="{ 'wc-overview__stat--on': statIsActive('noDirector') }"
        @click="applyStatFilter('noDirector')"
      >
        <span class="wc-overview__stat-value">{{ stats.noDirector }}</span>
        <span class="wc-overview__stat-label">Chưa gán trưởng</span>
      </button>
      <button
        type="button"
        class="wc-overview__stat wc-overview__stat--danger"
        :class="{ 'wc-overview__stat--on': statIsActive('noConfig') }"
        @click="applyStatFilter('noConfig')"
      >
        <span class="wc-overview__stat-value">{{ stats.noConfig }}</span>
        <span class="wc-overview__stat-label">Chưa có cấu hình</span>
      </button>
    </div>

    <div class="wc-overview__body">
      <div class="wc-overview__main">
        <div v-if="hasVisibleFilterFields" class="wc-overview__toolbar">
          <div class="wc-overview__filters">
            <div v-if="visibleFilters.q" class="wc-overview__field">
              <label class="wc-overview__label" for="wc-overview-q">Tìm kiếm</label>
              <input
                id="wc-overview-q"
                v-model="query"
                type="search"
                class="wc-overview__input"
                placeholder="Phòng ban, trưởng đơn vị, email…"
                @keydown.enter="page = 1"
              />
            </div>

            <div v-if="visibleFilters.is_active" class="wc-overview__field">
              <label class="wc-overview__label" for="wc-overview-status">Trạng thái</label>
              <select id="wc-overview-status" v-model="isActive" class="wc-overview__input">
                <option v-for="item in STATUS_FILTER_OPTIONS" :key="item.value || 'all-status'" :value="item.value">
                  {{ item.label }}
                </option>
              </select>
            </div>

            <div v-if="visibleFilters.has_config" class="wc-overview__field">
              <label class="wc-overview__label" for="wc-overview-config">Cấu hình</label>
              <select id="wc-overview-config" v-model="hasConfig" class="wc-overview__input">
                <option v-for="item in CONFIG_FILTER_OPTIONS" :key="item.value || 'all-config'" :value="item.value">
                  {{ item.label }}
                </option>
              </select>
            </div>

            <div v-if="visibleFilters.has_director" class="wc-overview__field">
              <label class="wc-overview__label" for="wc-overview-director">Quản lý phòng ban</label>
              <select id="wc-overview-director" v-model="hasDirector" class="wc-overview__input">
                <option v-for="item in DIRECTOR_FILTER_OPTIONS" :key="item.value || 'all'" :value="item.value">
                  {{ item.label }}
                </option>
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
          @search="page = 1"
          @clear-filters="clearFilters"
          @update:page="goPage"
          @update:per-page="perPage = $event"
          @update:zoom="tableZoom = $event"
        >
          <template #filters>
            <label v-for="item in OVERVIEW_FILTERS" :key="item.key" class="wc-overview__check">
              <input
                type="checkbox"
                :checked="visibleFilters[item.key]"
                @change="onFilterToggle(item.key, $event.target.checked)"
              />
              <span>{{ item.label }}</span>
            </label>
          </template>
          <template #settings>
            <label v-for="col in OVERVIEW_COLUMNS" :key="col.key" class="wc-overview__check">
              <input
                type="checkbox"
                :checked="visibleColumns[col.key]"
                @change="onColumnToggle(col.key, $event.target.checked)"
              />
              <span>{{ col.label }}</span>
            </label>
          </template>
        </TablePagesBar>

        <p v-if="hiddenActiveFilterLabels.length" class="wc-overview__note">
          Đang lọc thêm theo: {{ hiddenActiveFilterLabels.join(', ') }} (bộ lọc đang ẩn).
        </p>

        <div
          ref="tableWrap"
          class="wc-overview__table-wrap hide-scrollbar"
          :class="{ 'wc-overview__table-wrap--resizing': resizing }"
          :style="{ '--table-zoom': tableZoom }"
        >
          <table class="wc-overview__table" :style="{ width: tableWidthPx }">
            <colgroup>
              <col
                v-for="col in shownColumns"
                :key="col.key"
                :style="{ width: colWidthStyle(col.key) }"
              />
              <col :style="{ width: `${ACTION_COL_PX}px` }" />
            </colgroup>
            <thead>
              <tr>
                <th v-for="col in shownColumns" :key="col.key">
                  <span>{{ col.label }}</span>
                  <button
                    type="button"
                    class="wc-overview__resize"
                    aria-label="Kéo để đổi độ rộng cột"
                    @click.stop
                    @mousedown.stop.prevent="startResize($event, col.key)"
                  />
                </th>
                <th class="wc-overview__th-action">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td :colspan="colSpan" class="wc-overview__empty">Đang tải…</td>
              </tr>
              <tr v-else-if="pageDepartments.length === 0">
                <td :colspan="colSpan" class="wc-overview__empty">
                  {{ emptyTableMessage }}
                </td>
              </tr>
              <tr
                v-for="department in pageDepartments"
                v-else
                :key="department.id"
                :class="{ 'wc-overview__row--active': selected?.id === department.id }"
                @click="inspect(department)"
              >
                <td v-for="col in shownColumns" :key="col.key">
                  <template v-if="col.key === 'director'">
                    <span v-if="department.director" class="wc-overview__person">
                      <UserAvatarTip :user="directorUser(department)" label="Trưởng đơn vị" />
                      <span class="wc-overview__person-text">
                        <span>{{ department.director.name }}</span>
                        <span v-if="department.director.email" class="wc-overview__muted">
                          {{ department.director.email }}
                        </span>
                      </span>
                    </span>
                    <span v-else class="wc-overview__muted">Chưa gán trưởng đơn vị</span>
                  </template>
                  <template v-else-if="col.key === 'is_active'">
                    <StatusBadge
                      :on="department.is_active"
                      :label="departmentStatusLabel(department.is_active)"
                    />
                  </template>
                  <template v-else-if="col.key === 'has_config'">
                    <StatusBadge
                      :on="department.has_config"
                      :label="departmentConfigLabel(department.has_config)"
                    />
                  </template>
                  <span v-else>{{ cellText(department, col.key) }}</span>
                </td>
                <td class="wc-overview__td-action" @click.stop>
                  <span class="wc-overview__actions">
                    <button
                      type="button"
                      class="wc-overview__action-trigger"
                      :class="{ 'wc-overview__action-trigger--open': openActionMenuId === department.id }"
                      aria-haspopup="menu"
                      :aria-expanded="openActionMenuId === department.id"
                      aria-label="Thao tác"
                      @click="toggleActionMenu(department.id, $event)"
                    >
                      <AppIcon name="moreVertical" :size="16" :stroke-width="1.75" />
                    </button>
                    <Teleport to="body">
                      <div
                        v-if="openActionMenuId === department.id"
                        class="wc-overview__action-menu"
                        :class="{ 'wc-overview__action-menu--flip': actionMenuPos.flip }"
                        role="menu"
                        aria-label="Thao tác phòng ban"
                        :style="{ top: actionMenuPos.top + 'px', left: actionMenuPos.left + 'px' }"
                      >
                        <button
                          type="button"
                          role="menuitem"
                          class="wc-overview__action-item"
                          @click="inspect(department)"
                        >
                          <AppIcon name="info" :size="15" />
                          <span>Xem chi tiết</span>
                        </button>
                        <button
                          type="button"
                          role="menuitem"
                          class="wc-overview__action-item"
                          @click="openDepartment(department)"
                        >
                          <AppIcon name="externalLink" :size="15" />
                          <span>Xem workspace phòng ban</span>
                        </button>
                      </div>
                    </Teleport>
                  </span>
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

      <aside v-if="selected" class="wc-overview__side" aria-label="Chi tiết phòng ban">
        <div class="wc-overview__side-head">
          <h2 class="wc-overview__side-title">Chi tiết phòng ban</h2>
          <button type="button" class="wc-overview__icon-btn" aria-label="Đóng" @click="selected = null">
            <AppIcon name="close" :size="16" />
          </button>
        </div>

        <div class="wc-overview__side-lead" :class="`wc-overview__side-lead--${leadTone(selected)}`">
          <span class="wc-overview__dot" :class="`wc-overview__dot--${leadTone(selected)}`" />
          <div>
            <span class="wc-overview__side-lead-action">{{ leadCaption(selected) }}</span>
            <p class="wc-overview__side-lead-desc">{{ selected.name }}</p>
          </div>
        </div>

        <div class="wc-overview__rows">
          <div class="wc-overview__row">
            <span class="wc-overview__row-label">Mã phòng ban</span>
            <span class="wc-overview__row-value">{{ selected.code || '—' }}</span>
          </div>
          <div class="wc-overview__row">
            <span class="wc-overview__row-label">Trạng thái</span>
            <span class="wc-overview__row-value">
              <StatusBadge
                :on="selected.is_active"
                :label="departmentStatusLabel(selected.is_active)"
              />
            </span>
          </div>
          <div class="wc-overview__row">
            <span class="wc-overview__row-label">Cấu hình</span>
            <span class="wc-overview__row-value">
              <StatusBadge
                :on="selected.has_config"
                :label="departmentConfigLabel(selected.has_config)"
              />
            </span>
          </div>
          <div class="wc-overview__row">
            <span class="wc-overview__row-label">Trưởng đơn vị</span>
            <span class="wc-overview__row-value wc-overview__row-actor">
              <UserAvatarTip v-if="selected.director" :user="directorUser(selected)" label="Trưởng đơn vị" />
              <span>{{ selected.director?.name || 'Chưa gán' }}</span>
            </span>
          </div>
          <div class="wc-overview__row">
            <span class="wc-overview__row-label">Email liên hệ</span>
            <span class="wc-overview__row-value">{{ selected.director?.email || '—' }}</span>
          </div>
          <div class="wc-overview__row">
            <span class="wc-overview__row-label">Số thành viên</span>
            <span class="wc-overview__row-value">{{ selected.member_count ?? 0 }}</span>
          </div>
          <div class="wc-overview__row">
            <span class="wc-overview__row-label">Số tiêu chí đánh giá</span>
            <span class="wc-overview__row-value">{{ selected.criteria_count ?? 0 }}</span>
          </div>
          <div class="wc-overview__row">
            <span class="wc-overview__row-label">Mã bản ghi</span>
            <span class="wc-overview__row-value">{{ selected.id }}</span>
          </div>
        </div>

        <RouterLink
          class="wc-overview__side-link"
          :to="{ name: 'superadmin.workspace-config.department-detail', params: { departmentId: selected.id } }"
        >
          Xem workspace phòng ban
          <AppIcon name="chevronRight" :size="16" />
        </RouterLink>
      </aside>
    </div>
  </section>
</template>

<style scoped>
.wc-overview {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
}

.wc-overview__header-btn {
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
  text-decoration: none;
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  cursor: pointer;
}

.wc-overview__header-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.wc-overview__header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.wc-overview__spin {
  animation: wc-overview-spin 0.8s linear infinite;
}

@keyframes wc-overview-spin {
  to {
    transform: rotate(360deg);
  }
}

.wc-overview__stats {
  flex-shrink: 0;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--space-3);
  margin: var(--space-3) 0 0;
}

.wc-overview__stat {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.125rem;
  min-width: 0;
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-2));
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  text-align: left;
  box-shadow: var(--shadow-sm);
  cursor: pointer;
}

.wc-overview__stat::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.wc-overview__stat--success::before {
  background: var(--color-success);
}

.wc-overview__stat--warning::before {
  background: var(--color-warning);
}

.wc-overview__stat--danger::before {
  background: var(--color-danger);
}

.wc-overview__stat--info::before {
  background: var(--color-info);
}

.wc-overview__stat:hover,
.wc-overview__stat--on {
  background: var(--color-surface-muted);
}

.wc-overview__stat-value {
  color: var(--color-text);
  font-size: 1.25rem;
  font-weight: 700;
  line-height: 1.2;
}

.wc-overview__stat-label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.wc-overview__body {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-4);
  overflow: hidden;
}

.wc-overview__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.wc-overview__toolbar {
  position: relative;
  z-index: 6;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin: var(--space-2) 0 var(--space-3);
}

.wc-overview__filters {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
  width: 100%;
}

@media (min-width: 1280px) {
  .wc-overview__filters {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

.wc-overview__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
  width: 100%;
}

.wc-overview__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.wc-overview__input {
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

.wc-overview__input:focus {
  outline: 2px solid color-mix(in srgb, var(--color-primary) 35%, transparent);
  outline-offset: 1px;
}

.wc-overview__check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.wc-overview__note {
  flex-shrink: 0;
  margin: 0 0 var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.wc-overview__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.wc-overview__table-wrap--resizing {
  cursor: col-resize;
  user-select: none;
}

.wc-overview__table {
  min-width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: calc(0.875rem * var(--table-zoom, 1));
}

.wc-overview__table thead th {
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

.wc-overview__resize {
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

.wc-overview__resize::after {
  content: '';
  position: absolute;
  top: 25%;
  right: 2px;
  width: 2px;
  height: 50%;
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.wc-overview__resize:hover::after,
.wc-overview__table-wrap--resizing .wc-overview__resize:hover::after {
  background: var(--color-primary);
}

.wc-overview__table tbody td {
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  vertical-align: middle;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.wc-overview__table tbody tr {
  cursor: pointer;
}

.wc-overview__table tbody tr:hover td {
  background: var(--color-surface-muted);
}

.wc-overview__row--active td {
  background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface));
}

.wc-overview__table tbody td span {
  display: block;
  white-space: nowrap;
}

.wc-overview__table tbody td :deep(.status-mark) {
  display: inline-flex;
}

.wc-overview__empty {
  padding: var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
  white-space: normal;
}

.wc-overview__muted {
  margin-top: 0.125rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.wc-overview__table tbody td .wc-overview__person,
.wc-overview__table tbody td .wc-overview__actions {
  display: flex;
}

.wc-overview__person {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  min-width: 0;
}

.wc-overview__person-text {
  display: flex;
  min-width: 0;
  flex-direction: column;
}

.wc-overview__th-action,
.wc-overview__td-action {
  text-align: center;
}

.wc-overview__actions {
  position: relative;
  display: inline-flex;
}

.wc-overview__action-trigger {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-md);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.wc-overview__action-trigger:hover,
.wc-overview__action-trigger--open {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.wc-overview__action-menu {
  position: fixed;
  z-index: 1200;
  width: 12.5rem;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  padding: var(--space-1);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
  text-align: left;
}

.wc-overview__action-menu--flip {
  transform: translateY(-100%);
}

.wc-overview__action-item {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  padding: 0.5rem 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 500;
  text-align: left;
  cursor: pointer;
}

.wc-overview__action-item:hover {
  background: var(--color-surface-muted);
}

.wc-overview__side {
  flex-shrink: 0;
  width: 28rem;
  overflow-y: auto;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.wc-overview__side-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.wc-overview__side-title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 700;
}

.wc-overview__icon-btn {
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

.wc-overview__icon-btn:hover {
  background: var(--color-surface);
}

.wc-overview__side-lead {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  margin: var(--space-3) 0 var(--space-4);
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.wc-overview__side-lead::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.wc-overview__side-lead--success::before {
  background: var(--color-success);
}

.wc-overview__side-lead--danger::before {
  background: var(--color-danger);
}

.wc-overview__side-lead--info::before {
  background: var(--color-info);
}

.wc-overview__side-lead--warning::before {
  background: var(--color-warning);
}

.wc-overview__side-lead-action {
  display: block;
  margin-bottom: var(--space-1);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.wc-overview__side-lead-desc {
  margin: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 1.45;
}

.wc-overview__dot {
  flex-shrink: 0;
  margin-top: 0.375rem;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.wc-overview__dot--success {
  background: var(--color-success);
}

.wc-overview__dot--danger {
  background: var(--color-danger);
}

.wc-overview__dot--info {
  background: var(--color-info);
}

.wc-overview__dot--warning {
  background: var(--color-warning);
}

.wc-overview__rows {
  display: flex;
  flex-direction: column;
}

.wc-overview__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.wc-overview__row:last-child {
  box-shadow: none;
}

.wc-overview__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.wc-overview__row-label::after {
  content: ':';
}

.wc-overview__row-value {
  color: var(--color-text);
  font-style: italic;
  font-weight: 400;
  text-align: right;
  overflow-wrap: anywhere;
}

.wc-overview__row-value :deep(.status-mark) {
  font-style: normal;
}

.wc-overview__row-actor {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
}

.wc-overview__side-link {
  display: inline-flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  width: 100%;
  margin-top: var(--space-4);
  padding: 0.5rem 0.75rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  text-decoration: none;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.wc-overview__side-link:hover {
  background: var(--color-surface-muted);
}

@media (max-width: 1024px) {
  .wc-overview__stats {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .wc-overview__body {
    flex-direction: column;
  }

  .wc-overview__side {
    width: 100%;
    max-height: 42%;
  }

  .wc-overview__table-wrap {
    min-height: 16rem;
  }
}

@media (max-width: 768px) {
  .wc-overview {
    padding: var(--space-4);
  }

  .wc-overview__filters {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 480px) {
  .wc-overview {
    padding: var(--space-3);
  }
}

@media (prefers-reduced-motion: reduce) {
  .wc-overview__spin {
    animation: none;
  }
}
</style>
