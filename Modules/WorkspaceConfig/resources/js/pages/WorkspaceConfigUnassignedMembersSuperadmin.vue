<script setup>
//
// superadmin/workspace-config/unassigned — danh sách nhân sự đọc từ
// MySQL VA-HRM. Phòng ban workspace gán tay trên panel chi tiết.
//
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import { showClientToast } from '@/lib/clientToast';
import { isTransientClientNetworkError } from '@/lib/networkError';
import { useDragScroll } from '@/composables/useDragScroll';
import {
  COLUMN_STORAGE_KEY,
  COLUMN_WIDTH_KEY,
  FILTER_STORAGE_KEY,
  HRM_STATUS_OPTIONS,
  UNASSIGNED_COLUMNS,
  UNASSIGNED_FILTERS,
  ZOOM_STORAGE_KEY,
  companyName,
  concurrentTitleText,
  departmentName,
  hrmStatusLabel,
  loadVisibility,
  memberKey,
  memberRolesText,
  orgUnitName,
  saveVisibility,
  teamName,
} from '../constants/unassignedMembers.js';

const CELL_PAD_X = 32;
const COL_EXTRA = 24;
const AVATAR_EXTRA = 48;
const ACTIONS_COL_PX = 108;
let measureCtx = null;
let wrapObserver = null;

const allMembers = ref([]);
const departmentOptions = ref([]);
const source = ref('workspace');
const loading = ref(false);
const selected = ref(null);
const departmentAssignId = ref('');
const departmentAssignSaving = ref(false);
const rowMenu = ref(null);

const query = ref('');
const orgUnit = ref('');
const departmentId = ref('');
const status = ref('');
const page = ref(1);
const perPage = ref(20);

const visibleColumns = reactive(loadVisibility(COLUMN_STORAGE_KEY, UNASSIGNED_COLUMNS));
const visibleFilters = reactive(loadVisibility(FILTER_STORAGE_KEY, UNASSIGNED_FILTERS));

const tableWrap = ref(null);
const resizing = ref(false);
const MIN_COL_PX = 72;

useDragScroll(tableWrap, { isBlocked: () => resizing.value });

const columnWidths = reactive(loadColumnWidths());
const tableZoom = ref(loadZoom());

const shownColumns = computed(() => UNASSIGNED_COLUMNS.filter((col) => visibleColumns[col.key]));
const tableColumnKeys = computed(() => [...shownColumns.value.map((col) => col.key), 'actions']);
const colSpan = computed(() => Math.max(tableColumnKeys.value.length, 1));

const pageDescription = computed(() =>
  source.value === 'hrm_database'
    ? 'Nhân sự lấy trực tiếp từ cơ sở dữ liệu VA-HRM. Gán phòng ban workspace để nhân sự vào được đúng không gian làm việc.'
    : 'Chưa cấu hình cơ sở dữ liệu HRM. Đang hiển thị tài khoản đã có trên workspace.',
);

const orgUnitOptions = computed(() => {
  const names = new Set();
  for (const member of allMembers.value) {
    const name = orgUnitName(member);
    if (name) names.add(name);
  }
  return [...names].sort((a, b) => a.localeCompare(b, 'vi'));
});

const filteredMembers = computed(() => {
  const needle = query.value.trim().toLowerCase();
  return allMembers.value.filter((member) => {
    if (status.value && member.status !== status.value) return false;
    if (orgUnit.value === 'none' && orgUnitName(member)) return false;
    if (orgUnit.value && orgUnit.value !== 'none' && orgUnitName(member) !== orgUnit.value) return false;
    if (departmentId.value === 'none' && member.department) return false;
    if (
      departmentId.value &&
      departmentId.value !== 'none' &&
      String(member.department?.id ?? '') !== departmentId.value
    ) {
      return false;
    }
    if (!needle) return true;
    const haystack = [
      member.name,
      member.email,
      member.employee_code,
      member.job_title_name,
      member.job_position_level,
      member.manager_display_name,
      orgUnitName(member),
      departmentName(member),
      companyName(member),
      teamName(member),
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();
    return haystack.includes(needle);
  });
});

const lastPage = computed(() => Math.max(1, Math.ceil(filteredMembers.value.length / perPage.value)));

const pageRows = computed(() => {
  const start = (page.value - 1) * perPage.value;
  return filteredMembers.value.slice(start, start + perPage.value);
});

const meta = computed(() => {
  const total = filteredMembers.value.length;
  if (total === 0) {
    return { from: 0, to: 0, total: 0 };
  }
  const from = (page.value - 1) * perPage.value + 1;
  return { from, to: Math.min(page.value * perPage.value, total), total };
});

const hasActiveFilters = computed(
  () => Boolean(query.value.trim()) || Boolean(orgUnit.value) || Boolean(departmentId.value) || Boolean(status.value),
);

const hasVisibleFilterFields = computed(() => UNASSIGNED_FILTERS.some((item) => visibleFilters[item.key]));

const hiddenActiveFilterLabels = computed(() =>
  UNASSIGNED_FILTERS.filter((item) => !visibleFilters[item.key] && filterHasValue(item.key)).map((item) => item.label),
);

const tableWidthPx = computed(() => {
  const sum = tableColumnKeys.value.reduce((total, key) => total + (Number(columnWidths[key]) || 0), 0);
  return sum > 0 ? `${sum}px` : '100%';
});

const departmentAssignReady = computed(() => {
  if (!selected.value || departmentAssignId.value === '' || !selected.value.email) return false;
  return String(selected.value.department?.id ?? '') !== String(departmentAssignId.value);
});

function filterHasValue(key) {
  if (key === 'q') return Boolean(query.value.trim());
  if (key === 'org_unit') return Boolean(orgUnit.value);
  if (key === 'department_id') return Boolean(departmentId.value);
  if (key === 'status') return Boolean(status.value);
  return false;
}

function avatarUser(member) {
  if (!member) return null;
  const inactive = member.status === 'terminated' || member.status === 'suspended' || member.status === 'inactive';
  return {
    id: member.id || member.hrm_employee_uuid,
    name: member.name,
    email: member.email,
    avatar_url: member.avatar_url,
    status: inactive ? 'inactive' : 'active',
    department: member.department || (member.org_unit ? { name: member.org_unit.name } : null),
  };
}

function statusTone(value) {
  if (value === 'active' || value === 'on_leave') return 'success';
  if (value === 'terminated' || value === 'suspended' || value === 'inactive') return 'danger';
  return 'info';
}

function cellText(member, key) {
  if (key === 'person') return member.name || '—';
  if (key === 'employee_code') return member.employee_code || '—';
  if (key === 'job_title') return member.job_title_name || '—';
  if (key === 'position_level') return member.job_position_level || '—';
  if (key === 'company') return companyName(member) || '—';
  if (key === 'manager') return member.manager_display_name || '—';
  if (key === 'org_unit') return orgUnitName(member) || 'Chưa có đơn vị';
  if (key === 'department') return departmentName(member) || 'Chưa gán phòng ban';
  if (key === 'concurrent') return concurrentTitleText(member) || '—';
  if (key === 'team') return teamName(member) || '—';
  if (key === 'roles') return memberRolesText(member);
  if (key === 'status') return hrmStatusLabel(member.status);
  return '—';
}

function sameMember(left, right) {
  if (!left || !right) return false;
  if (left.hrm_employee_uuid && right.hrm_employee_uuid) {
    return left.hrm_employee_uuid === right.hrm_employee_uuid;
  }
  return left.id != null && left.id === right.id;
}

function inspect(member) {
  selected.value = member;
  rowMenu.value = null;
}

function clearFilters() {
  query.value = '';
  orgUnit.value = '';
  departmentId.value = '';
  status.value = '';
  page.value = 1;
}

function goPage(nextPage) {
  if (nextPage < 1 || nextPage > lastPage.value || nextPage === page.value) return;
  page.value = nextPage;
}

async function load(options = {}) {
  const { silent = false } = options;
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/workspace-config/members/by-department');
    allMembers.value = data.members ?? [];
    departmentOptions.value = data.department_options ?? [];
    source.value = data.source ?? 'workspace';
    if (selected.value && !allMembers.value.some((member) => sameMember(member, selected.value))) {
      selected.value = null;
    } else if (selected.value) {
      selected.value = allMembers.value.find((member) => sameMember(member, selected.value)) ?? null;
    }
    nextTick(fitColumnsToContent);
  } catch (error) {
    if (isTransientClientNetworkError(error)) return;
    if (!silent) {
      showClientToast('error', error?.response?.data?.message || 'Không tải được danh sách nhân sự.');
    }
  } finally {
    loading.value = false;
  }
}

function patchMember(member) {
  const index = allMembers.value.findIndex((item) => sameMember(item, member));
  if (index >= 0) allMembers.value[index] = member;
  if (sameMember(selected.value, member)) selected.value = member;
}

async function saveMemberDepartment() {
  if (!selected.value || !departmentAssignReady.value) return;

  departmentAssignSaving.value = true;
  try {
    const member = selected.value;
    const url = member.hrm_employee_uuid
      ? `/api/workspace-config/members/hrm/${member.hrm_employee_uuid}/department`
      : `/api/workspace-config/members/${member.id}/department`;
    const { data } = await window.axios.put(url, {
      department_id: Number(departmentAssignId.value),
    });
    patchMember(data.member);
    showClientToast('success', `Đã gán ${data.member.name} vào phòng ban ${data.member.department?.name ?? ''}.`);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không gán được phòng ban. Vui lòng thử lại.');
  } finally {
    departmentAssignSaving.value = false;
  }
}

function toggleRowMenu(event, member) {
  const key = memberKey(member);
  if (rowMenu.value?.key === key) {
    rowMenu.value = null;
    return;
  }
  const rect = event.currentTarget.getBoundingClientRect();
  const width = 196;
  const height = 44;
  const openUp = rect.bottom + height > window.innerHeight - 8;
  rowMenu.value = {
    key,
    member,
    top: openUp ? rect.top - height - 4 : rect.bottom + 4,
    left: Math.max(8, rect.right - width),
  };
}

function assignFromMenu() {
  const member = rowMenu.value?.member;
  rowMenu.value = null;
  if (member) inspect(member);
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
    // Bỏ qua.
  }
  return {};
}

function colWidthStyle(key) {
  const width = columnWidths[key];
  return width ? `${width}px` : undefined;
}

function measureText(text, font) {
  if (!measureCtx) measureCtx = document.createElement('canvas').getContext('2d');
  measureCtx.font = font;
  return measureCtx.measureText(String(text ?? '')).width;
}

function fontOf(el, fallback) {
  if (!el) return fallback;
  const style = getComputedStyle(el);
  return `${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
}

function readTableFonts() {
  const table = tableWrap.value?.querySelector('.roster-page__table');
  return {
    header: fontOf(table?.querySelector('thead th'), '600 12px "Be Vietnam Pro", sans-serif'),
    cell: fontOf(table?.querySelector('tbody td'), '400 14px "Be Vietnam Pro", sans-serif'),
    muted: fontOf(table?.querySelector('.roster-page__muted'), '400 12px "Be Vietnam Pro", sans-serif'),
  };
}

function columnContentWidth(key, fonts) {
  if (key === 'actions') return ACTIONS_COL_PX;
  const label = UNASSIGNED_COLUMNS.find((col) => col.key === key)?.label ?? '';
  let maxW = measureText(label, fonts.header);
  for (const member of pageRows.value) {
    if (key === 'person') {
      maxW = Math.max(maxW, measureText(cellText(member, 'person'), fonts.cell));
      if (member.email) maxW = Math.max(maxW, measureText(member.email, fonts.muted));
    } else {
      maxW = Math.max(maxW, measureText(cellText(member, key), fonts.cell));
    }
  }
  const extra = key === 'person' ? AVATAR_EXTRA : 0;
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
  const keys = tableColumnKeys.value;
  if (!wrap || keys.length === 0 || resizing.value) return;
  const fonts = readTableFonts();
  const measured = {};
  for (const key of keys) measured[key] = columnContentWidth(key, fonts);
  const next = distributeExtraWidth(measured, keys, wrap.clientWidth);
  for (const key of keys) columnWidths[key] = next[key];
}

function startResize(event, key) {
  const keys = tableColumnKeys.value;
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
    const remaining = UNASSIGNED_COLUMNS.filter((col) => visibleColumns[col.key] && col.key !== key).length;
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

function handleDocumentClick(event) {
  if (!rowMenu.value) return;
  if (event.target?.closest?.('[data-row-menu]')) return;
  rowMenu.value = null;
}

function handleDocumentKeydown(event) {
  if (event.key !== 'Escape') return;
  if (rowMenu.value) {
    rowMenu.value = null;
    return;
  }
  selected.value = null;
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
watch(selected, (member) => {
  departmentAssignId.value = member?.department?.id ? String(member.department.id) : '';
  nextTick(fitColumnsToContent);
});
watch(shownColumns, () => nextTick(fitColumnsToContent));
watch([query, orgUnit, departmentId, status, perPage], () => {
  page.value = 1;
});
watch(lastPage, (value) => {
  if (page.value > value) page.value = value;
});

onMounted(() => {
  document.addEventListener('click', handleDocumentClick);
  document.addEventListener('keydown', handleDocumentKeydown);
  load();
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
  document.removeEventListener('click', handleDocumentClick);
  document.removeEventListener('keydown', handleDocumentKeydown);
  wrapObserver?.disconnect();
});
</script>

<template>
  <section class="roster-page">
    <PageHeader title="Nhân sự workspace" icon="users" :description="pageDescription">
      <template #actions>
        <button type="button" class="roster-page__header-btn" :disabled="loading" @click="load()">
          <AppIcon name="refresh" :size="16" :class="{ 'roster-page__spin': loading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

    <div class="roster-page__body">
      <div class="roster-page__main">
        <div v-if="hasVisibleFilterFields" class="roster-page__toolbar">
          <div class="roster-page__filters">
            <div v-if="visibleFilters.q" class="roster-page__field">
              <label class="roster-page__label" for="roster-q">Tìm kiếm</label>
              <input
                id="roster-q"
                v-model="query"
                type="search"
                class="roster-page__input"
                placeholder="Họ tên, email, mã nhân viên…"
              />
            </div>
            <div v-if="visibleFilters.org_unit" class="roster-page__field">
              <label class="roster-page__label" for="roster-org">Đơn vị HRM</label>
              <select id="roster-org" v-model="orgUnit" class="roster-page__input">
                <option value="">Tất cả đơn vị</option>
                <option value="none">Chưa có đơn vị</option>
                <option v-for="name in orgUnitOptions" :key="name" :value="name">{{ name }}</option>
              </select>
            </div>
            <div v-if="visibleFilters.department_id" class="roster-page__field">
              <label class="roster-page__label" for="roster-dept">Phòng ban workspace</label>
              <select id="roster-dept" v-model="departmentId" class="roster-page__input">
                <option value="">Tất cả phòng ban</option>
                <option value="none">Chưa gán phòng ban</option>
                <option v-for="item in departmentOptions" :key="item.id" :value="String(item.id)">
                  {{ item.name }}
                </option>
              </select>
            </div>
            <div v-if="visibleFilters.status" class="roster-page__field">
              <label class="roster-page__label" for="roster-status">Trạng thái</label>
              <select id="roster-status" v-model="status" class="roster-page__input">
                <option v-for="item in HRM_STATUS_OPTIONS" :key="item.value || 'all'" :value="item.value">
                  {{ item.label }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <TablePagesBar
          placement="top"
          :from="meta.from"
          :to="meta.to"
          :total="meta.total"
          :page="page"
          :last-page="lastPage"
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
            <label v-for="item in UNASSIGNED_FILTERS" :key="item.key" class="roster-page__check">
              <input
                type="checkbox"
                :checked="visibleFilters[item.key]"
                @change="onFilterToggle(item.key, $event.target.checked)"
              />
              <span>{{ item.label }}</span>
            </label>
          </template>
          <template #settings>
            <label v-for="col in UNASSIGNED_COLUMNS" :key="col.key" class="roster-page__check">
              <input
                type="checkbox"
                :checked="visibleColumns[col.key]"
                @change="onColumnToggle(col.key, $event.target.checked)"
              />
              <span>{{ col.label }}</span>
            </label>
          </template>
        </TablePagesBar>

        <p v-if="hiddenActiveFilterLabels.length" class="roster-page__note">
          Đang lọc thêm theo: {{ hiddenActiveFilterLabels.join(', ') }} (bộ lọc đang ẩn).
        </p>

        <div
          ref="tableWrap"
          class="roster-page__table-wrap hide-scrollbar"
          :class="{ 'roster-page__table-wrap--resizing': resizing }"
          :style="{ '--table-zoom': tableZoom }"
        >
          <table class="roster-page__table" :style="{ width: tableWidthPx }">
            <colgroup>
              <col v-for="key in tableColumnKeys" :key="key" :style="{ width: colWidthStyle(key) }" />
            </colgroup>
            <thead>
              <tr>
                <th v-for="col in shownColumns" :key="col.key">
                  <span>{{ col.label }}</span>
                  <button
                    type="button"
                    class="roster-page__resize"
                    aria-label="Kéo để đổi độ rộng cột"
                    @click.stop
                    @mousedown.stop.prevent="startResize($event, col.key)"
                  />
                </th>
                <th>
                  <span>Thao tác</span>
                  <button
                    type="button"
                    class="roster-page__resize"
                    aria-label="Kéo để đổi độ rộng cột"
                    @click.stop
                    @mousedown.stop.prevent="startResize($event, 'actions')"
                  />
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td :colspan="colSpan" class="roster-page__empty">Đang tải…</td>
              </tr>
              <tr v-else-if="pageRows.length === 0">
                <td :colspan="colSpan" class="roster-page__empty">Chưa có nhân sự nào.</td>
              </tr>
              <tr
                v-for="member in pageRows"
                v-else
                :key="memberKey(member)"
                :class="{ 'roster-page__row--active': sameMember(selected, member) }"
                @click="inspect(member)"
              >
                <td v-for="col in shownColumns" :key="col.key">
                  <template v-if="col.key === 'person'">
                    <span class="roster-page__person">
                      <UserAvatarTip :user="avatarUser(member)" label="Nhân sự" />
                      <span class="roster-page__person-text">
                        <span>{{ cellText(member, 'person') }}</span>
                        <span v-if="member.email" class="roster-page__muted">{{ member.email }}</span>
                      </span>
                    </span>
                  </template>
                  <span v-else class="roster-page__cell">{{ cellText(member, col.key) }}</span>
                </td>
                <td @click.stop>
                  <button
                    type="button"
                    class="roster-page__menu-btn"
                    data-row-menu
                    aria-label="Thao tác"
                    :aria-expanded="rowMenu?.key === memberKey(member)"
                    @click="toggleRowMenu($event, member)"
                  >
                    <AppIcon name="moreVertical" :size="18" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <TablePagesBar
          placement="bottom"
          paging-only
          :from="meta.from"
          :to="meta.to"
          :total="meta.total"
          :page="page"
          :last-page="lastPage"
          :per-page="perPage"
          @update:page="goPage"
          @update:per-page="perPage = $event"
        />
      </div>

      <aside v-if="selected" class="roster-page__side" aria-label="Chi tiết nhân sự">
        <div class="roster-page__side-head">
          <h2 class="roster-page__side-title">Chi tiết nhân sự</h2>
          <button type="button" class="roster-page__icon-btn" aria-label="Đóng" @click="selected = null">
            <AppIcon name="close" :size="16" />
          </button>
        </div>

        <div class="roster-page__side-lead" :class="`roster-page__side-lead--${statusTone(selected.status)}`">
          <UserAvatarTip :user="avatarUser(selected)" label="Nhân sự" />
          <div>
            <span class="roster-page__side-lead-action">{{ hrmStatusLabel(selected.status) }}</span>
            <p class="roster-page__side-lead-desc">{{ selected.name || '—' }}</p>
          </div>
        </div>

        <div class="roster-page__rows">
          <div class="roster-page__row">
            <span class="roster-page__row-label">Mã nhân viên</span>
            <span class="roster-page__row-value">{{ selected.employee_code || '—' }}</span>
          </div>
          <div class="roster-page__row">
            <span class="roster-page__row-label">Email công ty</span>
            <span class="roster-page__row-value">{{ selected.email || '—' }}</span>
          </div>
          <div class="roster-page__row">
            <span class="roster-page__row-label">Điện thoại</span>
            <span class="roster-page__row-value">{{ selected.phone || '—' }}</span>
          </div>
          <div class="roster-page__row">
            <span class="roster-page__row-label">Chức danh</span>
            <span class="roster-page__row-value">{{ selected.job_title_name || '—' }}</span>
          </div>
          <div class="roster-page__row">
            <span class="roster-page__row-label">Cấp bậc</span>
            <span class="roster-page__row-value">{{ selected.job_position_level || '—' }}</span>
          </div>
          <div class="roster-page__row">
            <span class="roster-page__row-label">Pháp nhân</span>
            <span class="roster-page__row-value">{{ companyName(selected) || '—' }}</span>
          </div>
          <div class="roster-page__row">
            <span class="roster-page__row-label">Đơn vị HRM</span>
            <span class="roster-page__row-value">{{ orgUnitName(selected) || 'Chưa có đơn vị' }}</span>
          </div>
          <div class="roster-page__row">
            <span class="roster-page__row-label">Cấp trên</span>
            <span class="roster-page__row-value">{{ selected.manager_display_name || '—' }}</span>
          </div>
          <div class="roster-page__row">
            <span class="roster-page__row-label">Kiêm nhiệm</span>
            <span class="roster-page__row-value">{{ concurrentTitleText(selected) || '—' }}</span>
          </div>
          <div class="roster-page__row">
            <span class="roster-page__row-label">Phòng ban workspace</span>
            <span class="roster-page__row-value">{{ departmentName(selected) || 'Chưa gán phòng ban' }}</span>
          </div>
          <div class="roster-page__row">
            <span class="roster-page__row-label">Nhóm</span>
            <span class="roster-page__row-value">{{ teamName(selected) || '—' }}</span>
          </div>
          <div class="roster-page__row">
            <span class="roster-page__row-label">Vai trò</span>
            <span class="roster-page__row-value">{{ memberRolesText(selected) }}</span>
          </div>
          <div class="roster-page__row">
            <span class="roster-page__row-label">Tài khoản workspace</span>
            <span class="roster-page__row-value">{{ selected.has_workspace_account === false ? 'Chưa có' : 'Đã có' }}</span>
          </div>
        </div>

        <form class="roster-page__assign" @submit.prevent="saveMemberDepartment">
          <label class="roster-page__label" for="roster-assign-dept">Gán phòng ban workspace</label>
          <select id="roster-assign-dept" v-model="departmentAssignId" class="roster-page__input">
            <option value="">Chọn phòng ban</option>
            <option v-for="item in departmentOptions" :key="item.id" :value="String(item.id)">
              {{ item.name }}
            </option>
          </select>
          <p v-if="!selected.email" class="roster-page__note">
            Nhân sự này chưa có email công ty nên chưa tạo được tài khoản workspace.
          </p>
          <button type="submit" class="roster-page__btn" :disabled="!departmentAssignReady || departmentAssignSaving">
            {{ departmentAssignSaving ? 'Đang gán…' : 'Lưu phòng ban' }}
          </button>
        </form>
      </aside>
    </div>

    <Teleport to="body">
      <div
        v-if="rowMenu"
        class="roster-page__menu"
        data-row-menu
        role="menu"
        :style="{ top: `${rowMenu.top}px`, left: `${rowMenu.left}px` }"
      >
        <button type="button" class="roster-page__menu-item" role="menuitem" @click="assignFromMenu">
          <AppIcon name="building" :size="16" />
          Gán phòng ban
        </button>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
.roster-page {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
}

.roster-page__header-btn {
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

.roster-page__header-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.roster-page__header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.roster-page__spin {
  animation: roster-spin 0.8s linear infinite;
}

@keyframes roster-spin {
  to { transform: rotate(360deg); }
}

.roster-page__body {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-4);
  overflow: hidden;
}

.roster-page__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.roster-page__toolbar {
  position: relative;
  z-index: 6;
  flex-shrink: 0;
  margin: var(--space-3) 0;
}

.roster-page__filters {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--space-3);
}

.roster-page__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
}

.roster-page__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.roster-page__input {
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

.roster-page__check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.roster-page__note {
  flex-shrink: 0;
  margin: 0 0 var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.roster-page__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.roster-page__table-wrap--resizing {
  cursor: col-resize;
  user-select: none;
}

.roster-page__table {
  min-width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: calc(0.875rem * var(--table-zoom, 1));
}

.roster-page__table thead th {
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

.roster-page__resize {
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

.roster-page__resize::after {
  content: '';
  position: absolute;
  top: 25%;
  right: 2px;
  width: 2px;
  height: 50%;
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.roster-page__resize:hover::after,
.roster-page__table-wrap--resizing .roster-page__resize:hover::after {
  background: var(--color-primary);
}

.roster-page__table tbody td {
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  vertical-align: middle;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.roster-page__table tbody tr {
  cursor: pointer;
}

.roster-page__table tbody tr:hover td {
  background: var(--color-surface-muted);
}

.roster-page__row--active td {
  background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface));
}

.roster-page__cell {
  display: block;
  white-space: nowrap;
}

.roster-page__person {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  min-width: 0;
}

.roster-page__person-text {
  display: flex;
  min-width: 0;
  flex-direction: column;
}

.roster-page__person-text span {
  display: block;
  white-space: nowrap;
}

.roster-page__muted {
  margin-top: 0.125rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.roster-page__empty {
  padding: var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
  white-space: normal;
}

.roster-page__menu-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.roster-page__menu-btn:hover,
.roster-page__menu-btn[aria-expanded='true'] {
  background: var(--color-surface);
  color: var(--color-text);
}

.roster-page__menu {
  position: fixed;
  z-index: 80;
  min-width: 12.25rem;
  padding: 0.25rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg), inset 0 0 0 1px var(--color-border);
}

.roster-page__menu-item {
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
  font-size: 0.875rem;
  text-align: left;
  cursor: pointer;
}

.roster-page__menu-item:hover {
  background: var(--color-surface-muted);
}

.roster-page__side {
  flex-shrink: 0;
  width: 28rem;
  overflow-y: auto;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.roster-page__side-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.roster-page__side-title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 700;
}

.roster-page__icon-btn {
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

.roster-page__icon-btn:hover {
  background: var(--color-surface);
}

.roster-page__side-lead {
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

.roster-page__side-lead::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.roster-page__side-lead--success::before { background: var(--color-success); }
.roster-page__side-lead--danger::before { background: var(--color-danger); }
.roster-page__side-lead--info::before { background: var(--color-info); }

.roster-page__side-lead-action {
  display: block;
  margin-bottom: var(--space-1);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.roster-page__side-lead-desc {
  margin: 0;
  color: var(--color-text);
  font-weight: 600;
  font-size: 0.9375rem;
  line-height: 1.45;
}

.roster-page__rows {
  display: flex;
  flex-direction: column;
}

.roster-page__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.roster-page__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.roster-page__row-label::after {
  content: ':';
}

.roster-page__row-value {
  color: var(--color-text);
  font-style: italic;
  text-align: right;
  overflow-wrap: anywhere;
}

.roster-page__assign {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin-top: var(--space-4);
}

.roster-page__btn {
  height: 2.375rem;
  padding: 0 1rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.roster-page__btn:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.roster-page__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 1024px) {
  .roster-page__body { flex-direction: column; }
  .roster-page__side { width: 100%; max-height: 42%; }
  .roster-page__table-wrap { min-height: 16rem; }
  .roster-page__filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 768px) {
  .roster-page { padding: var(--space-4); }
}

@media (max-width: 480px) {
  .roster-page { padding: var(--space-3); }
  .roster-page__filters { grid-template-columns: minmax(0, 1fr); }
}

@media (prefers-reduced-motion: reduce) {
  .roster-page__spin { animation: none; }
}
</style>
