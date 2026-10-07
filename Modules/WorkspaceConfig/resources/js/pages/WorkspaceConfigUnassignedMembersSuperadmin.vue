<script setup>
//
// superadmin/workspace-config/unassigned — nhân sự chưa gán phòng ban workspace;
// danh mục phòng ban đồng bộ HRM, tự gán theo danh mục khi đã có tài khoản.
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
  EMPLOYMENT_STATUS_OPTIONS,
  GENDER_OPTIONS,
  HRM_STATUS_OPTIONS,
  UNASSIGNED_COLUMNS,
  ZOOM_STORAGE_KEY,
  companyName,
  concurrentTitleText,
  departmentName,
  divisionName,
  hrmDepartmentName,
  employmentStatusLabel,
  formatHrmDate,
  genderLabel,
  hrmStatusLabel,
  loadVisibility,
  memberKey,
  memberRolesText,
  orgUnitName,
  saveVisibility,
  secondaryPlacement,
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
const division = ref('');
const company = ref('');
const workplace = ref('');
const gender = ref('');
const personnelType = ref('');
const employmentStatus = ref('');
const newThisMonth = ref(false);
const status = ref('');
const sortKey = ref('');
const sortDir = ref('asc');
const page = ref(1);
const perPage = ref(10);

const visibleColumns = reactive(loadVisibility(COLUMN_STORAGE_KEY, UNASSIGNED_COLUMNS));

const tableWrap = ref(null);
const resizing = ref(false);
const MIN_COL_PX = 72;

useDragScroll(tableWrap, { isBlocked: () => resizing.value });

const columnWidths = reactive(loadColumnWidths());
const tableZoom = ref(loadZoom());

const shownColumns = computed(() => UNASSIGNED_COLUMNS.filter((col) => visibleColumns[col.key]));
const tableColumnKeys = computed(() => [...shownColumns.value.map((col) => col.key), 'actions']);
const colSpan = computed(() => Math.max(tableColumnKeys.value.length, 1));

const orgUnitOptions = computed(() => {
  const names = new Set();
  for (const member of allMembers.value) {
    const name = hrmDepartmentName(member);
    if (name) names.add(name);
  }
  return [...names].sort((a, b) => a.localeCompare(b, 'vi'));
});

const divisionOptions = computed(() => {
  const names = new Set();
  for (const member of allMembers.value) {
    const name = divisionName(member);
    if (name) names.add(name);
  }
  return [...names].sort((a, b) => a.localeCompare(b, 'vi'));
});

function isNewThisMonth(member) {
  const hired = String(member?.hired_at || '');
  if (!/^\d{4}-\d{2}/.test(hired)) return false;
  const now = new Date();
  const month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  return hired.startsWith(month);
}

const summaryCards = computed(() => {
  const counts = { active: 0, on_leave: 0, terminated: 0, onboarding: 0, new_this_month: 0 };
  for (const member of allMembers.value) {
    if (member.status === 'active') counts.active += 1;
    else if (member.status === 'on_leave') counts.on_leave += 1;
    else if (member.status === 'terminated' || member.status === 'inactive') counts.terminated += 1;
    if (member.status === 'pending_confirmation' || member.status === 'processing') counts.onboarding += 1;
    if (isNewThisMonth(member)) counts.new_this_month += 1;
  }
  const fmt = (value) => value.toLocaleString('vi-VN');
  return [
    { key: 'total', label: 'Tổng hồ sơ', value: fmt(allMembers.value.length), tone: 'brand', icon: 'users', filter: '' },
    { key: 'active', label: 'Xác nhận xử lý', value: fmt(counts.active), tone: 'success', icon: 'check', filter: 'active' },
    { key: 'on_leave', label: 'Tạm nghỉ', value: fmt(counts.on_leave), tone: 'warning', icon: 'calendar', filter: 'on_leave' },
    { key: 'terminated', label: 'Đã nghỉ việc', value: fmt(counts.terminated), tone: 'danger', icon: 'close', filter: 'terminated' },
    { key: 'new_this_month', label: 'Tuyển mới tháng này', value: fmt(counts.new_this_month), tone: 'info', icon: 'userPlus', filter: 'new_this_month' },
    { key: 'onboarding', label: 'Onboarding', value: fmt(counts.onboarding), tone: 'violet', icon: 'clock', filter: 'onboarding' },
  ];
});

const companyOptions = computed(() => {
  const names = new Set();
  for (const member of allMembers.value) {
    const name = companyName(member);
    if (name) names.add(name);
  }
  return [...names].sort((a, b) => a.localeCompare(b, 'vi'));
});

const workplaceOptions = computed(() => {
  const names = new Set();
  for (const member of allMembers.value) {
    if (member.workplace) names.add(member.workplace);
  }
  return [...names].sort((a, b) => a.localeCompare(b, 'vi'));
});

const filteredMembers = computed(() => {
  const needle = query.value.trim().toLowerCase();
  const rows = allMembers.value.filter((member) => {
    if (newThisMonth.value && !isNewThisMonth(member)) return false;
    if (status.value === 'onboarding') {
      if (member.status !== 'pending_confirmation' && member.status !== 'processing') return false;
    } else if (status.value === 'terminated') {
      if (member.status !== 'terminated' && member.status !== 'inactive') return false;
    } else if (status.value && member.status !== status.value) return false;
    if (employmentStatus.value && member.employment_status !== employmentStatus.value) return false;
    if (personnelType.value && member.personnel_type !== personnelType.value) return false;
    if (gender.value && member.gender !== gender.value) return false;
    if (company.value === 'none' && companyName(member)) return false;
    if (company.value && company.value !== 'none' && companyName(member) !== company.value) return false;
    if (orgUnit.value && hrmDepartmentName(member) !== orgUnit.value) return false;
    if (division.value && divisionName(member) !== division.value) return false;
    if (workplace.value && member.workplace !== workplace.value) return false;
    if (!needle) return true;
    const haystack = [
      member.name,
      member.email,
      member.employee_code,
      member.job_title_name,
      member.job_position_level,
      member.manager_display_name,
      member.employee_code,
      member.phone,
      member.workplace,
      member.personnel_type,
      orgUnitName(member),
      hrmDepartmentName(member),
      divisionName(member),
      departmentName(member),
      secondaryPlacement(member)?.department_name,
      secondaryPlacement(member)?.division_name,
      companyName(member),
      secondaryPlacement(member)?.job_title_name,
      secondaryPlacement(member)?.company_name,
      teamName(member),
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();
    return haystack.includes(needle);
  });
  if (!sortKey.value) return rows;
  const dir = sortDir.value === 'desc' ? -1 : 1;
  return [...rows].sort((left, right) => dir * cellText(left, sortKey.value).localeCompare(cellText(right, sortKey.value), 'vi'));
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
  () => Boolean(query.value.trim())
    || Boolean(orgUnit.value)
    || Boolean(division.value)
    || Boolean(company.value)
    || Boolean(workplace.value)
    || Boolean(gender.value)
    || Boolean(personnelType.value)
    || Boolean(employmentStatus.value)
    || Boolean(status.value)
    || newThisMonth.value,
);

const tableWidthPx = computed(() => {
  const sum = tableColumnKeys.value.reduce((total, key) => total + (Number(columnWidths[key]) || 0), 0);
  return sum > 0 ? `${sum}px` : '100%';
});

const departmentAssignReady = computed(() => {
  if (!selected.value || departmentAssignId.value === '' || !selected.value.email) return false;
  return String(selected.value.department?.id ?? '') !== String(departmentAssignId.value);
});

function kpiOn(card) {
  if (card.filter === 'new_this_month') return newThisMonth.value;
  if (card.filter === '') return !status.value && !newThisMonth.value;
  return status.value === card.filter;
}

function applySummary(filter) {
  page.value = 1;
  if (filter === 'new_this_month') {
    newThisMonth.value = !newThisMonth.value;
    return;
  }
  status.value = status.value === filter ? '' : filter;
}

function statusClass(value) {
  if (value === 'inactive') return 'terminated';
  if (value === 'pending_confirmation' || value === 'processing' || value === 'on_leave' || value === 'suspended' || value === 'terminated') {
    return value;
  }
  return 'active';
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

function blank(value) {
  return value ? String(value) : 'Chưa cập nhật';
}

function cellText(member, key) {
  const second = secondaryPlacement(member);
  if (key === 'person') return member.name || 'Chưa cập nhật';
  if (key === 'timekeeping_code' || key === 'employee_code') return blank(member.employee_code);
  if (key === 'job_title') return blank(member.job_title_name);
  if (key === 'job_title_2') return blank(second?.job_title_name);
  if (key === 'position_level') return blank(member.job_position_level);
  if (key === 'company') return blank(companyName(member));
  if (key === 'company_2') return blank(second?.company_name);
  if (key === 'manager') return blank(member.manager_display_name);
  if (key === 'org_unit') return blank(orgUnitName(member));
  if (key === 'department') return blank(hrmDepartmentName(member));
  if (key === 'division') return blank(divisionName(member));
  if (key === 'department_2') return blank(second?.department_name);
  if (key === 'division_2') return blank(second?.division_name);
  if (key === 'workspace_department') return blank(departmentName(member));
  if (key === 'phone') return blank(member.phone);
  if (key === 'email') return blank(member.email);
  if (key === 'hired_at') return formatHrmDate(member.hired_at);
  if (key === 'actual_start_date') return formatHrmDate(member.actual_start_date);
  if (key === 'gender') return genderLabel(member.gender);
  if (key === 'workplace') return blank(member.workplace);
  if (key === 'personnel_type') return blank(member.personnel_type);
  if (key === 'employment_status') return employmentStatusLabel(member.employment_status);
  if (key === 'concurrent') return concurrentTitleText(member) || 'Chưa cập nhật';
  if (key === 'team') return blank(teamName(member));
  if (key === 'roles') return memberRolesText(member);
  if (key === 'status') return hrmStatusLabel(member.status);
  return 'Chưa cập nhật';
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
  division.value = '';
  company.value = '';
  workplace.value = '';
  gender.value = '';
  personnelType.value = '';
  employmentStatus.value = '';
  newThisMonth.value = false;
  status.value = '';
  page.value = 1;
}

function toggleSort(key) {
  page.value = 1;
  if (sortKey.value !== key) {
    sortKey.value = key;
    sortDir.value = 'asc';
    return;
  }
  if (sortDir.value === 'asc') {
    sortDir.value = 'desc';
    return;
  }
  sortKey.value = '';
  sortDir.value = 'asc';
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
    const rows = data.unassigned ?? data.members ?? [];
    allMembers.value = Array.isArray(rows) ? rows : [];
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
    person: fontOf(table?.querySelector('.roster-page__person-name'), '600 15px "Be Vietnam Pro", sans-serif'),
    status: fontOf(table?.querySelector('.roster-page__status'), '500 13px "Be Vietnam Pro", sans-serif'),
    muted: fontOf(table?.querySelector('.roster-page__muted'), '400 12px "Be Vietnam Pro", sans-serif'),
  };
}

function columnContentWidth(key, fonts) {
  if (key === 'actions') return ACTIONS_COL_PX;
  const label = UNASSIGNED_COLUMNS.find((col) => col.key === key)?.label ?? 'Thao tác';
  let maxW = measureText(String(label).toLocaleUpperCase('vi'), fonts.header);
  const rows = allMembers.value.length ? allMembers.value : pageRows.value;
  for (const member of rows) {
    if (key === 'person') {
      maxW = Math.max(maxW, measureText(cellText(member, 'person'), fonts.person));
      if (member.employee_code) maxW = Math.max(maxW, measureText(member.employee_code, fonts.muted));
    } else if (key !== 'actions') {
      const font = key === 'status' ? fonts.status : fonts.cell;
      maxW = Math.max(maxW, measureText(cellText(member, key), font));
    }
  }
  const extra = key === 'person' ? AVATAR_EXTRA : key === 'status' ? 22 : 0;
  return Math.max(MIN_COL_PX, Math.ceil(maxW + CELL_PAD_X + COL_EXTRA + extra));
}

function fitColumnsToContent() {
  const wrap = tableWrap.value;
  const keys = tableColumnKeys.value;
  if (!wrap || keys.length === 0 || resizing.value) return;
  const fonts = readTableFonts();
  for (const key of keys) columnWidths[key] = columnContentWidth(key, fonts);
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
  if (member?.department?.id != null) {
    departmentAssignId.value = String(member.department.id);
  } else if (member?.suggested_department?.id != null) {
    departmentAssignId.value = String(member.suggested_department.id);
  } else {
    departmentAssignId.value = '';
  }
  nextTick(fitColumnsToContent);
});
watch(shownColumns, () => nextTick(fitColumnsToContent));
watch([query, orgUnit, division, company, workplace, gender, personnelType, employmentStatus, status, newThisMonth, perPage], () => {
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
    <PageHeader
      title="Nhân viên"
      icon="users"
      :breadcrumbs="[{ label: 'Trang chủ' }, { label: 'Nhân sự' }, { label: 'Danh sách nhân viên' }]"
    >
      <template #actions>
        <button type="button" class="roster-page__header-btn" :disabled="loading" @click="load()">
          <AppIcon name="refresh" :size="16" :class="{ 'roster-page__spin': loading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

    <div class="roster-page__summary" aria-label="Thống kê tổng quan nhân viên">
      <p class="roster-page__summary-title">Bức tranh nhân sự</p>
      <div class="roster-page__summary-grid">
        <button
          v-for="card in summaryCards"
          :key="card.key"
          type="button"
          class="roster-page__kpi"
          :class="[`roster-page__kpi--${card.tone}`, { 'roster-page__kpi--on': kpiOn(card) }]"
          @click="applySummary(card.filter)"
        >
          <span class="roster-page__kpi-icon" aria-hidden="true">
            <AppIcon :name="card.icon" :size="16" />
          </span>
          <span class="roster-page__kpi-copy">
            <span class="roster-page__kpi-label">{{ card.label }}</span>
            <span class="roster-page__kpi-value">{{ card.value }}</span>
          </span>
        </button>
      </div>
    </div>

    <div class="roster-page__body">
      <div class="roster-page__main">
        <div class="roster-page__toolbar">
          <label class="roster-page__search">
            <AppIcon name="search" :size="16" />
            <input v-model="query" type="search" placeholder="Tìm tên, mã, email…" />
          </label>
        </div>

        <TablePagesBar
          placement="top"
          :from="meta.from"
          :to="meta.to"
          :total="meta.total"
          :page="page"
          :last-page="lastPage"
          :per-page="perPage"
          :per-page-options="[10, 20, 50, 100]"
          :zoom="tableZoom"
          show-search
          filters-menu-wide
          filters-menu-title="Bộ lọc"
          :show-clear-filters="hasActiveFilters"
          :filters-active="hasActiveFilters"
          @search="page = 1"
          @clear-filters="clearFilters"
          @update:page="goPage"
          @update:per-page="perPage = $event"
          @update:zoom="tableZoom = $event"
        >
          <template #filters>
            <div class="roster-page__filter-menu">
              <div class="roster-page__field">
                <label class="roster-page__label" for="roster-status">Trạng thái</label>
                <select id="roster-status" v-model="status" class="roster-page__input">
                  <option v-for="item in HRM_STATUS_OPTIONS" :key="item.value || 'all'" :value="item.value">
                    {{ item.label }}
                  </option>
                </select>
              </div>
              <div class="roster-page__field">
                <label class="roster-page__label" for="roster-employment">Trạng thái nhân sự</label>
                <select id="roster-employment" v-model="employmentStatus" class="roster-page__input">
                  <option v-for="item in EMPLOYMENT_STATUS_OPTIONS" :key="item.value || 'all'" :value="item.value">
                    {{ item.label }}
                  </option>
                </select>
              </div>
              <div class="roster-page__field">
                <label class="roster-page__label" for="roster-personnel">Phân loại</label>
                <select id="roster-personnel" v-model="personnelType" class="roster-page__input">
                  <option value="">Phân loại</option>
                  <option value="Cơ hữu">Cơ hữu</option>
                  <option value="Dịch vụ">Dịch vụ</option>
                </select>
              </div>
              <div class="roster-page__field">
                <label class="roster-page__label" for="roster-company">Công ty</label>
                <select id="roster-company" v-model="company" class="roster-page__input">
                  <option value="">Tất cả công ty</option>
                  <option value="none">Chưa gắn công ty</option>
                  <option v-for="name in companyOptions" :key="name" :value="name">{{ name }}</option>
                </select>
              </div>
              <div class="roster-page__field">
                <label class="roster-page__label" for="roster-org">Phòng ban</label>
                <select id="roster-org" v-model="orgUnit" class="roster-page__input">
                  <option value="">Tất cả phòng ban</option>
                  <option v-for="name in orgUnitOptions" :key="name" :value="name">{{ name }}</option>
                </select>
              </div>
              <div class="roster-page__field">
                <label class="roster-page__label" for="roster-division">Bộ phận</label>
                <select id="roster-division" v-model="division" class="roster-page__input">
                  <option value="">Tất cả bộ phận</option>
                  <option v-for="name in divisionOptions" :key="name" :value="name">{{ name }}</option>
                </select>
              </div>
              <div class="roster-page__field">
                <label class="roster-page__label" for="roster-workplace">Cơ sở</label>
                <select id="roster-workplace" v-model="workplace" class="roster-page__input">
                  <option value="">Tất cả cơ sở</option>
                  <option v-for="name in workplaceOptions" :key="name" :value="name">{{ name }}</option>
                </select>
              </div>
              <div class="roster-page__field">
                <label class="roster-page__label" for="roster-gender">Giới tính</label>
                <select id="roster-gender" v-model="gender" class="roster-page__input">
                  <option v-for="item in GENDER_OPTIONS" :key="item.value || 'all'" :value="item.value">
                    {{ item.label }}
                  </option>
                </select>
              </div>
            </div>
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
                  <button
                    v-if="col.key === 'person' || col.key === 'timekeeping_code' || col.key === 'manager'"
                    type="button"
                    class="roster-page__sort"
                    @click.stop="toggleSort(col.key)"
                  >
                    {{ col.label }}
                    <span v-if="sortKey === col.key" class="roster-page__sort-mark">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                  </button>
                  <span v-else>{{ col.label }}</span>
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
                <td :colspan="colSpan" class="roster-page__empty">
                  <span class="roster-page__empty-icon" aria-hidden="true">
                    <AppIcon name="users" :size="22" />
                  </span>
                  <span class="roster-page__empty-title">
                    {{ allMembers.length === 0 ? 'Chưa có hồ sơ nhân viên' : 'Không có kết quả phù hợp' }}
                  </span>
                  <span class="roster-page__empty-desc">
                    {{
                      allMembers.length === 0
                        ? 'Danh sách nhân sự lấy từ VA-HRM sẽ hiện ở đây.'
                        : 'Thử xóa từ khóa hoặc đặt lại các bộ lọc đang áp dụng.'
                    }}
                  </span>
                  <button v-if="hasActiveFilters" type="button" class="roster-page__empty-btn" @click="clearFilters">
                    Đặt lại bộ lọc
                  </button>
                </td>
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
                        <span class="roster-page__person-name">{{ cellText(member, 'person') }}</span>
                        <span v-if="member.employee_code" class="roster-page__muted">{{ member.employee_code }}</span>
                      </span>
                    </span>
                  </template>
                  <span
                    v-else-if="col.key === 'status'"
                    class="roster-page__status"
                    :class="`roster-page__status--${statusClass(member.status)}`"
                  >
                    {{ cellText(member, col.key) }}
                  </span>
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
          :per-page-options="[10, 20, 50, 100]"
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
            <span class="roster-page__row-value">{{ selected.job_title_name || 'Chưa cập nhật' }}</span>
          </div>
          <div class="roster-page__row">
            <span class="roster-page__row-label">Chức danh 2</span>
            <span class="roster-page__row-value">{{ secondaryPlacement(selected)?.job_title_name || 'Chưa cập nhật' }}</span>
          </div>
          <div class="roster-page__row">
            <span class="roster-page__row-label">Pháp nhân 2</span>
            <span class="roster-page__row-value">{{ secondaryPlacement(selected)?.company_name || 'Chưa cập nhật' }}</span>
          </div>
          <div class="roster-page__row">
            <span class="roster-page__row-label">Phòng ban 2</span>
            <span class="roster-page__row-value">{{ secondaryPlacement(selected)?.department_name || 'Chưa cập nhật' }}</span>
          </div>
          <div class="roster-page__row">
            <span class="roster-page__row-label">Bộ phận 2</span>
            <span class="roster-page__row-value">{{ secondaryPlacement(selected)?.division_name || 'Chưa cập nhật' }}</span>
          </div>
          <div class="roster-page__row">
            <span class="roster-page__row-label">Giới tính</span>
            <span class="roster-page__row-value">{{ genderLabel(selected.gender) }}</span>
          </div>
          <div class="roster-page__row">
            <span class="roster-page__row-label">Ngày vào làm</span>
            <span class="roster-page__row-value">{{ formatHrmDate(selected.hired_at) }}</span>
          </div>
          <div class="roster-page__row">
            <span class="roster-page__row-label">Trạng thái nhân sự</span>
            <span class="roster-page__row-value">{{ employmentStatusLabel(selected.employment_status) }}</span>
          </div>
          <div class="roster-page__row">
            <span class="roster-page__row-label">Cơ sở</span>
            <span class="roster-page__row-value">{{ selected.workplace || 'Chưa cập nhật' }}</span>
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
            <span class="roster-page__row-label">Phòng ban</span>
            <span class="roster-page__row-value">{{ hrmDepartmentName(selected) || 'Chưa cập nhật' }}</span>
          </div>
          <div class="roster-page__row">
            <span class="roster-page__row-label">Bộ phận</span>
            <span class="roster-page__row-value">{{ divisionName(selected) || 'Chưa cập nhật' }}</span>
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

.roster-page__search {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
  padding: 0 0.75rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.roster-page__search input {
  flex: 1;
  min-width: 0;
  height: 2.25rem;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  outline: none;
}

.roster-page__filter-menu {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.roster-page__sort {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0;
  border: none;
  background: transparent;
  color: inherit;
  font: inherit;
  letter-spacing: inherit;
  text-transform: inherit;
  cursor: pointer;
}

.roster-page__sort-mark {
  font-size: 0.75rem;
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

.roster-page__summary {
  flex-shrink: 0;
  margin-bottom: var(--space-3);
}

.roster-page__summary-title {
  margin: 0 0 var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.roster-page__summary-grid {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 0.625rem;
}

.roster-page__kpi {
  position: relative;
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-width: 0;
  padding: 0.625rem 0.75rem 0.625rem calc(var(--space-2) + 3px + var(--space-2));
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  font-family: var(--font-family-base);
  text-align: left;
  cursor: pointer;
}

.roster-page__kpi::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.roster-page__kpi--brand::before { background: var(--color-primary); }
.roster-page__kpi--success::before { background: var(--color-success); }
.roster-page__kpi--warning::before { background: var(--color-warning); }
.roster-page__kpi--danger::before { background: var(--color-danger); }
.roster-page__kpi--umber::before { background: var(--color-umber); }
.roster-page__kpi--info::before { background: var(--color-info); }

.roster-page__kpi--on {
  background: var(--color-primary-surface);
}

.roster-page__kpi-icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.roster-page__kpi--brand .roster-page__kpi-icon {
  background: var(--color-primary-surface-strong);
  color: var(--color-primary);
}

.roster-page__kpi--success .roster-page__kpi-icon {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
}

.roster-page__kpi--warning .roster-page__kpi-icon {
  background: var(--color-warning-tint-bg);
  color: var(--color-warning-tint-fg);
}

.roster-page__kpi--danger .roster-page__kpi-icon {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}

.roster-page__kpi--umber .roster-page__kpi-icon {
  background: var(--color-umber-tint-bg);
  color: var(--color-umber-tint-fg);
}

.roster-page__kpi--info .roster-page__kpi-icon,
.roster-page__kpi--violet .roster-page__kpi-icon {
  background: var(--color-tertiary-100);
  color: var(--color-tertiary-800);
}

.roster-page__kpi--violet::before { background: var(--color-tertiary); }

.roster-page__kpi-copy {
  display: flex;
  min-width: 0;
  flex-direction: column;
}

.roster-page__kpi-label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.2;
  white-space: nowrap;
}

.roster-page__kpi-value {
  color: var(--color-text);
  font-size: 1.125rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  line-height: 1.2;
}

.roster-page__table {
  width: max-content;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: calc(0.9375rem * var(--table-zoom, 1));
}

.roster-page__table thead th {
  position: sticky;
  top: 0;
  z-index: 1;
  padding: 0.625rem var(--space-4);
  background: color-mix(in srgb, var(--color-surface-muted) 65%, var(--color-surface));
  color: var(--color-primary);
  font-weight: 600;
  font-size: 0.75rem;
  letter-spacing: 0.04em;
  text-align: left;
  text-transform: uppercase;
  white-space: nowrap;
  box-shadow: inset 0 -1px 0 var(--color-border);
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
  padding: 0.625rem var(--space-4);
  color: var(--color-text-muted);
  vertical-align: middle;
  white-space: nowrap;
  box-shadow: inset 0 -1px 0 color-mix(in srgb, var(--color-border) 55%, transparent);
}

.roster-page__table tbody tr {
  cursor: pointer;
}

.roster-page__table tbody tr:hover td {
  background: var(--color-info-tint-bg);
}

.roster-page__row--active td,
.roster-page__row--active:hover td {
  background: var(--color-primary-surface);
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

.roster-page__person-name {
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 600;
}

.roster-page__muted {
  margin-top: 0.125rem;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.roster-page__status {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.625rem;
  border-radius: var(--radius-sm);
  font-size: 0.8125rem;
  font-weight: 500;
  line-height: 1.2;
  white-space: nowrap;
}

.roster-page__status--active {
  background: var(--color-gold-100);
  color: var(--color-gold-800);
}

.roster-page__status--on_leave {
  background: var(--color-warning-tint-bg);
  color: var(--color-warning-tint-fg);
}

.roster-page__status--pending_confirmation {
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
}

.roster-page__status--processing {
  background: var(--color-tertiary-100);
  color: var(--color-tertiary-800);
}

.roster-page__status--suspended {
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.roster-page__status--terminated {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}

.roster-page__empty {
  padding: var(--space-6) var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
  white-space: normal;
}

.roster-page__empty-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 3rem;
  height: 3rem;
  margin-bottom: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.roster-page__empty-title,
.roster-page__empty-desc {
  display: block;
}

.roster-page__empty-title {
  color: var(--color-text);
  font-weight: 600;
}

.roster-page__empty-desc {
  margin-top: var(--space-1);
  font-size: 0.875rem;
}

.roster-page__empty-btn {
  margin-top: var(--space-4);
  height: 2.25rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 600;
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
}

.roster-page__empty-btn:hover {
  background: var(--color-surface-muted);
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
  .roster-page__summary-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

@media (max-width: 768px) {
  .roster-page { padding: var(--space-4); }
}

@media (max-width: 480px) {
  .roster-page { padding: var(--space-3); }
  .roster-page__summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (prefers-reduced-motion: reduce) {
  .roster-page__spin { animation: none; }
}
</style>
