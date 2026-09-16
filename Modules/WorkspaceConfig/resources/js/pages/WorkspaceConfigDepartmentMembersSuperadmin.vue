<script setup>
//
// superadmin/workspace-config/departments/:departmentId/members — tab
// "Thành viên" trong hub chi tiết phòng ban. Bảng theo mẫu ActivityLog:
// filter, 2 thanh trang, kéo cột, panel chi tiết đẩy ngang. Dữ liệu lấy từ
// WorkspaceConfigDepartmentDetailHub qua inject, không tự gọi API riêng.
// Panel chi tiết chỉ hiện thông tin của thành viên đang chọn — menu hiển thị
// và tiêu chí đánh giá là cấu hình của cả phòng ban nên nằm ở 2 tab riêng.
// Super_admin được gán vai trò thay department_director ở đây (nút "Gán vai
// trò" trên header, đăng ký qua hub.setPrimaryAction) — xem
// WorkspaceConfigMemberController::assignRoleForDepartment().
//
import { computed, inject, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import { showClientToast } from '@/lib/clientToast';
import { useDragScroll } from '@/composables/useDragScroll';
import StatusBadge from '../components/StatusBadge.vue';
import WorkspaceConfigPicker from '../components/WorkspaceConfigPicker.vue';
import {
  COLUMN_STORAGE_KEY,
  COLUMN_WIDTH_KEY,
  DETAIL_COLUMNS,
  DETAIL_FILTERS,
  FALLBACK_AVATAR_SRC,
  FALLBACK_AVATAR_SRCSET,
  FILTER_STORAGE_KEY,
  MEMBER_STATUS_OPTIONS,
  ZOOM_STORAGE_KEY,
  loadVisibility,
  memberRoles,
  memberRolesText,
  memberStatusLabel,
  saveVisibility,
} from '../constants/departmentDetail.js';

const CELL_PAD_X = 32;
const COL_EXTRA = 24;
const AVATAR_EXTRA = 40;
let measureCtx = null;
let wrapObserver = null;

const route = useRoute();
const hub = inject('workspaceConfigDeptDetailHub', null);
const allMembers = computed(() => hub?.members?.value ?? []);
const loading = computed(() => hub?.loading?.value ?? false);
const assignableRoles = computed(() => hub?.assignableRoles?.value ?? []);

const selected = ref(null);
const brokenAvatarIds = ref(new Set());

const departmentOptions = ref([]);
const departmentAssignId = ref('');
const departmentAssignSaving = ref(false);

const roleDialogOpen = ref(false);
const roleDialogTab = ref('create');
const roleFormSaving = ref(false);
const roleForm = reactive({ user_id: '', role_code: '' });
const roleListFilter = ref('');

const assignableRoleCodes = computed(() => new Set(assignableRoles.value.map((item) => item.code)));

function isRoleEligible(member) {
  if (member.status !== 'active') return false;
  return memberRoles(member).every((item) => !item.code || assignableRoleCodes.value.has(item.code));
}

function hasAssignableRole(member) {
  return memberRoles(member).some((item) => assignableRoleCodes.value.has(item.code));
}

function sortMembers(members) {
  return members.slice().sort((a, b) => (a.name || '').localeCompare(b.name || '', 'vi'));
}

const assignCandidates = computed(() =>
  sortMembers(allMembers.value.filter((member) => isRoleEligible(member) && !hasAssignableRole(member))),
);

const editRoleCandidates = computed(() =>
  sortMembers(allMembers.value.filter((member) => isRoleEligible(member) && hasAssignableRole(member))),
);

const roleTabCandidates = computed(() =>
  roleDialogTab.value === 'edit' ? editRoleCandidates.value : assignCandidates.value,
);

function toMemberPickerItems(members) {
  return members.map((member) => ({
    id: member.id,
    label: member.name,
    sublabel: member.email || '',
    meta: memberRolesText(member) === '—' ? member.team?.name || '' : memberRolesText(member),
    avatar_url: member.avatar_url,
  }));
}

const rolePickerItems = computed(() => toMemberPickerItems(roleTabCandidates.value));

function matchesRoleFilter(item) {
  const q = roleListFilter.value.trim().toLowerCase();
  if (!q) return true;
  return `${item.label ?? ''} ${item.sublabel ?? ''} ${item.meta ?? ''}`.toLowerCase().includes(q);
}

const visibleRoleItems = computed(() => rolePickerItems.value.filter(matchesRoleFilter));

const selectedRoleMember = computed(() =>
  allMembers.value.find((member) => member.id === Number(roleForm.user_id)) ?? null,
);

const selectedRoleOption = computed(
  () => assignableRoles.value.find((item) => item.code === roleForm.role_code) ?? null,
);

function currentAssignableRole(member) {
  const match = memberRoles(member).find((item) => assignableRoleCodes.value.has(item.code));
  return match?.code ?? '';
}

const currentRoleOfSelected = computed(() => {
  const member = selectedRoleMember.value;
  if (!member) return null;
  return memberRoles(member).find((item) => assignableRoleCodes.value.has(item.code)) ?? null;
});

const roleUnchanged = computed(
  () =>
    roleDialogTab.value === 'edit' &&
    Boolean(currentRoleOfSelected.value?.code) &&
    currentRoleOfSelected.value.code === roleForm.role_code,
);

const roleDialogTitle = computed(() => (roleDialogTab.value === 'edit' ? 'Sửa vai trò' : 'Gán vai trò'));

const roleSubmitLabel = computed(() => {
  if (roleFormSaving.value) return 'Đang lưu…';
  return roleDialogTab.value === 'edit' ? 'Lưu vai trò' : 'Gán vai trò';
});

const roleEmptyMessage = computed(() =>
  roleDialogTab.value === 'edit'
    ? 'Chưa có thành viên nào có vai trò phòng ban để sửa. Gán vai trò trước ở tab Gán.'
    : 'Không có thành viên nào có thể gán vai trò. Chỉ gán được cho nhân sự đang hoạt động, chưa có vai trò phòng ban.',
);

function focusRoleDialog() {
  if (roleDialogTab.value === 'edit') return;
  document.getElementById('wc-dept-role-user')?.focus();
}

function openRoleDialog() {
  const selectedId = selected.value?.id;
  const inEdit = selectedId && editRoleCandidates.value.some((member) => member.id === selectedId);
  const inAssign = selectedId && assignCandidates.value.some((member) => member.id === selectedId);

  if (inEdit) {
    roleDialogTab.value = 'edit';
  } else if (inAssign) {
    roleDialogTab.value = 'create';
  } else if (assignCandidates.value.length === 0 && editRoleCandidates.value.length > 0) {
    roleDialogTab.value = 'edit';
  } else {
    roleDialogTab.value = 'create';
  }

  const seed = inEdit || inAssign ? selected.value : null;
  roleListFilter.value = '';
  roleForm.user_id = seed ? seed.id : '';
  roleForm.role_code = seed && inEdit ? currentAssignableRole(seed) : '';
  roleDialogOpen.value = true;
  nextTick(focusRoleDialog);
}

function setRoleDialogTab(tab) {
  if (roleFormSaving.value || roleDialogTab.value === tab) return;
  roleDialogTab.value = tab;
  roleListFilter.value = '';
  roleForm.user_id = '';
  roleForm.role_code = '';
  nextTick(focusRoleDialog);
}

function closeRoleDialog() {
  if (roleFormSaving.value) return;
  roleDialogOpen.value = false;
}

async function submitRoleForm() {
  if (roleForm.user_id === '' || roleForm.user_id == null) {
    showClientToast('error', 'Vui lòng chọn thành viên.');
    return;
  }
  if (!roleForm.role_code) {
    showClientToast('error', 'Vui lòng chọn vai trò.');
    return;
  }
  if (roleUnchanged.value) {
    showClientToast('warning', 'Vai trò mới trùng với vai trò hiện tại.');
    return;
  }

  roleFormSaving.value = true;
  try {
    const { data } = await window.axios.post(
      `/api/workspace-config/departments/${route.params.departmentId}/members/roles`,
      { user_id: Number(roleForm.user_id), role_code: roleForm.role_code },
    );
    const member = data.member;
    const index = allMembers.value.findIndex((item) => item.id === member.id);
    if (index >= 0) allMembers.value[index] = member;
    if (selected.value?.id === member.id) selected.value = member;

    const memberName = member?.name || 'thành viên';
    const roleName = memberRolesText(member);
    roleDialogOpen.value = false;
    showClientToast(
      'success',
      roleDialogTab.value === 'edit'
        ? `Đã đổi vai trò của ${memberName} thành ${roleName}.`
        : `Đã gán vai trò ${roleName} cho ${memberName}.`,
    );
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không lưu được vai trò. Vui lòng thử lại.');
  } finally {
    roleFormSaving.value = false;
  }
}

function registerPrimaryAction() {
  hub?.setPrimaryAction?.({
    label: 'Gán vai trò',
    icon: 'userPlus',
    onClick: openRoleDialog,
  });
}

const departmentAssignUnchanged = computed(() => {
  if (!selected.value) return true;
  const current = selected.value.department?.id ?? '';
  const next = departmentAssignId.value === '' ? '' : Number(departmentAssignId.value);
  return current === next;
});

async function loadDepartmentOptions() {
  try {
    const { data } = await window.axios.get('/api/workspace-config/overview');
    departmentOptions.value = (data.departments ?? []).map((item) => ({ id: item.id, name: item.name }));
  } catch {
    // Không chặn trang nếu tải danh sách phòng ban thất bại — chỉ ảnh hưởng dropdown chuyển phòng ban.
  }
}

async function saveMemberDepartment() {
  if (!selected.value || departmentAssignUnchanged.value || departmentAssignId.value === '') return;

  departmentAssignSaving.value = true;
  try {
    const { data } = await window.axios.put(
      `/api/workspace-config/members/${selected.value.id}/department`,
      { department_id: Number(departmentAssignId.value) },
    );
    const member = data.member;
    if (Number(route.params.departmentId) === member.department?.id) {
      const index = allMembers.value.findIndex((item) => item.id === member.id);
      if (index >= 0) allMembers.value[index] = member;
      selected.value = member;
    } else {
      // Chuyển sang phòng ban khác — không còn thuộc danh sách đang xem.
      selected.value = null;
      const index = allMembers.value.findIndex((item) => item.id === member.id);
      if (index >= 0) allMembers.value.splice(index, 1);
    }
    showClientToast('success', `Đã chuyển "${member.name}" sang phòng ban "${member.department?.name ?? ''}".`);
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không chuyển được phòng ban. Vui lòng thử lại.');
  } finally {
    departmentAssignSaving.value = false;
  }
}

const query = ref('');
const teamId = ref('');
const status = ref('');
const role = ref('');
const page = ref(1);
const perPage = ref(20);

const visibleColumns = reactive(loadVisibility(COLUMN_STORAGE_KEY, DETAIL_COLUMNS));
const visibleFilters = reactive(loadVisibility(FILTER_STORAGE_KEY, DETAIL_FILTERS));

const tableWrap = ref(null);
const resizing = ref(false);

useDragScroll(tableWrap, { isBlocked: () => resizing.value });
const MIN_COL_PX = 72;
const columnWidths = reactive(loadColumnWidths());
const tableZoom = ref(loadZoom());

const shownColumns = computed(() => DETAIL_COLUMNS.filter((col) => visibleColumns[col.key]));
const colSpan = computed(() => Math.max(shownColumns.value.length, 1));

const teamOptions = computed(() => {
  const seen = new Map();
  for (const member of allMembers.value) {
    if (member.team && !seen.has(member.team.id)) {
      seen.set(member.team.id, member.team.name);
    }
  }
  return [...seen.entries()]
    .map(([id, name]) => ({ value: String(id), label: name }))
    .sort((a, b) => a.label.localeCompare(b.label, 'vi'));
});

const roleOptions = computed(() => {
  const seen = new Map();
  for (const member of allMembers.value) {
    for (const item of memberRoles(member)) {
      if (item.code && !seen.has(item.code)) {
        seen.set(item.code, item.name);
      }
    }
  }
  return [...seen.entries()]
    .map(([code, name]) => ({ value: code, label: name }))
    .sort((a, b) => a.label.localeCompare(b.label, 'vi'));
});

const filteredMembers = computed(() => {
  const q = query.value.trim().toLowerCase();
  return allMembers.value.filter((member) => {
    if (q) {
      const hay = `${member.name ?? ''} ${member.email ?? ''}`.toLowerCase();
      if (!hay.includes(q)) return false;
    }
    if (teamId.value === 'none') {
      if (member.team) return false;
    } else if (teamId.value && String(member.team?.id) !== teamId.value) {
      return false;
    }
    if (status.value === 'active' && member.status !== 'active') return false;
    if (status.value === 'inactive' && member.status === 'active') return false;
    if (role.value && !memberRoles(member).some((item) => item.code === role.value)) return false;
    return true;
  });
});

const lastPage = computed(() => Math.max(1, Math.ceil(filteredMembers.value.length / perPage.value)));

const meta = computed(() => {
  const total = filteredMembers.value.length;
  const current = Math.min(Math.max(page.value, 1), lastPage.value);
  const from = total === 0 ? 0 : (current - 1) * perPage.value + 1;
  const to = Math.min(current * perPage.value, total);
  return { current_page: current, last_page: lastPage.value, total, from, to, per_page: perPage.value };
});

const pageMembers = computed(() => {
  const start = (meta.value.current_page - 1) * perPage.value;
  return filteredMembers.value.slice(start, start + perPage.value);
});

const hasActiveFilters = computed(
  () =>
    Boolean(query.value.trim()) ||
    Boolean(teamId.value) ||
    Boolean(status.value) ||
    Boolean(role.value),
);

const hiddenActiveFilterLabels = computed(() =>
  DETAIL_FILTERS.filter((item) => !visibleFilters[item.key] && filterHasValue(item.key)).map(
    (item) => item.label,
  ),
);

const hasVisibleFilterFields = computed(() => DETAIL_FILTERS.some((item) => visibleFilters[item.key]));

const emptyTableMessage = computed(() => {
  if (!hasActiveFilters.value) return 'Phòng ban chưa có thành viên nào.';
  if (teamId.value && teamId.value !== 'none' && !query.value.trim() && !status.value && !role.value) {
    const name = teamOptions.value.find((item) => item.value === teamId.value)?.label;
    return name ? `Nhóm "${name}" chưa có thành viên nào.` : 'Không có thành viên khớp bộ lọc.';
  }
  return 'Không có thành viên khớp bộ lọc.';
});

const tableWidthPx = computed(() => {
  const keys = shownColumns.value.map((col) => col.key);
  const sum = keys.reduce((total, key) => total + (Number(columnWidths[key]) || 0), 0);
  return sum > 0 ? `${sum}px` : '100%';
});

function filterHasValue(key) {
  if (key === 'q') return Boolean(query.value.trim());
  if (key === 'team_id') return Boolean(teamId.value);
  if (key === 'status') return Boolean(status.value);
  if (key === 'role') return Boolean(role.value);
  return false;
}

function goPage(nextPage) {
  if (nextPage < 1 || nextPage > lastPage.value || nextPage === page.value) {
    return;
  }
  page.value = nextPage;
}

function clearFilters() {
  query.value = '';
  teamId.value = '';
  status.value = '';
  role.value = '';
  page.value = 1;
}

function inspect(member) {
  selected.value = member;
  departmentAssignId.value = member?.department?.id != null ? String(member.department.id) : '';
}

function usesPhoto(member) {
  return Boolean(member?.avatar_url) && !brokenAvatarIds.value.has(member.id);
}

function onAvatarError(id) {
  if (brokenAvatarIds.value.has(id)) return;
  const next = new Set(brokenAvatarIds.value);
  next.add(id);
  brokenAvatarIds.value = next;
}

function cellText(member, key) {
  if (key === 'person') return member.name || '—';
  if (key === 'team') return member.team?.name || 'Chưa thuộc nhóm nào';
  if (key === 'status') return memberStatusLabel(member.status);
  if (key === 'roles') return memberRolesText(member);
  if (key === 'id') return String(member.id ?? '—');
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
  const table = tableWrap.value?.querySelector('.wc-detail__table');
  return {
    header: fontOf(table?.querySelector('thead th'), '600 12px "Be Vietnam Pro", sans-serif'),
    cell: fontOf(table?.querySelector('tbody td'), '400 14px "Be Vietnam Pro", sans-serif'),
    muted: fontOf(table?.querySelector('.wc-detail__muted'), '400 12px "Be Vietnam Pro", sans-serif'),
  };
}

function columnContentWidth(key, fonts) {
  const label = DETAIL_COLUMNS.find((col) => col.key === key)?.label ?? '';
  let maxW = measureText(label, fonts.header);
  for (const member of pageMembers.value) {
    if (key === 'person') {
      maxW = Math.max(maxW, measureText(cellText(member, 'person'), fonts.cell));
      if (member.email) {
        maxW = Math.max(maxW, measureText(member.email, fonts.muted));
      }
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
    const remaining = DETAIL_COLUMNS.filter((col) => visibleColumns[col.key] && col.key !== key).length;
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
  if (roleDialogOpen.value) {
    closeRoleDialog();
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
watch(pageMembers, () => nextTick(fitColumnsToContent));

watch([query, teamId, status, role, perPage], () => {
  page.value = 1;
});

watch(allMembers, () => {
  if (selected.value && !allMembers.value.some((member) => member.id === selected.value.id)) {
    selected.value = null;
  }
  nextTick(fitColumnsToContent);
});

watch(filteredMembers, (rows) => {
  if (selected.value && !rows.some((member) => member.id === selected.value.id)) {
    selected.value = null;
  }
  if (page.value > lastPage.value) {
    page.value = lastPage.value;
  }
});

onMounted(() => {
  document.addEventListener('keydown', handleDocumentKeydown);
  loadDepartmentOptions();
  registerPrimaryAction();
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
  document.removeEventListener('keydown', handleDocumentKeydown);
  hub?.clearPrimaryAction?.();
  wrapObserver?.disconnect();
});
</script>

<template>
  <div class="wc-detail">
    <div class="wc-detail__body">
      <div class="wc-detail__main">
        <div v-if="hasVisibleFilterFields" class="wc-detail__toolbar">
          <div class="wc-detail__filters">
            <div v-if="visibleFilters.q" class="wc-detail__field">
              <label class="wc-detail__label" for="wc-detail-q">Tìm kiếm</label>
              <input
                id="wc-detail-q"
                v-model="query"
                type="search"
                class="wc-detail__input"
                placeholder="Họ tên, email…"
                @keydown.enter="page = 1"
              />
            </div>

            <div v-if="visibleFilters.team_id" class="wc-detail__field">
              <label class="wc-detail__label" for="wc-detail-team">Nhóm</label>
              <select id="wc-detail-team" v-model="teamId" class="wc-detail__input">
                <option value="">Tất cả nhóm</option>
                <option value="none">Chưa thuộc nhóm nào</option>
                <option v-for="item in teamOptions" :key="item.value" :value="item.value">
                  {{ item.label }}
                </option>
              </select>
            </div>

            <div v-if="visibleFilters.status" class="wc-detail__field">
              <label class="wc-detail__label" for="wc-detail-status">Trạng thái</label>
              <select id="wc-detail-status" v-model="status" class="wc-detail__input">
                <option v-for="item in MEMBER_STATUS_OPTIONS" :key="item.value || 'all'" :value="item.value">
                  {{ item.label }}
                </option>
              </select>
            </div>

            <div v-if="visibleFilters.role" class="wc-detail__field">
              <label class="wc-detail__label" for="wc-detail-role">Vai trò</label>
              <select id="wc-detail-role" v-model="role" class="wc-detail__input">
                <option value="">Tất cả vai trò</option>
                <option v-for="item in roleOptions" :key="item.value" :value="item.value">
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
            <label v-for="item in DETAIL_FILTERS" :key="item.key" class="wc-detail__check">
              <input
                type="checkbox"
                :checked="visibleFilters[item.key]"
                @change="onFilterToggle(item.key, $event.target.checked)"
              />
              <span>{{ item.label }}</span>
            </label>
          </template>
          <template #settings>
            <label v-for="col in DETAIL_COLUMNS" :key="col.key" class="wc-detail__check">
              <input
                type="checkbox"
                :checked="visibleColumns[col.key]"
                @change="onColumnToggle(col.key, $event.target.checked)"
              />
              <span>{{ col.label }}</span>
            </label>
          </template>
        </TablePagesBar>

        <p v-if="hiddenActiveFilterLabels.length" class="wc-detail__note">
          Đang lọc thêm theo: {{ hiddenActiveFilterLabels.join(', ') }} (bộ lọc đang ẩn).
        </p>

        <div
          ref="tableWrap"
          class="wc-detail__table-wrap hide-scrollbar"
          :class="{ 'wc-detail__table-wrap--resizing': resizing }"
          :style="{ '--table-zoom': tableZoom }"
        >
          <table class="wc-detail__table" :style="{ width: tableWidthPx }">
            <colgroup>
              <col
                v-for="col in shownColumns"
                :key="col.key"
                :style="{ width: colWidthStyle(col.key) }"
              />
            </colgroup>
            <thead>
              <tr>
                <th v-for="col in shownColumns" :key="col.key">
                  <span>{{ col.label }}</span>
                  <button
                    type="button"
                    class="wc-detail__resize"
                    aria-label="Kéo để đổi độ rộng cột"
                    @click.stop
                    @mousedown.stop.prevent="startResize($event, col.key)"
                  />
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td :colspan="colSpan" class="wc-detail__empty">Đang tải…</td>
              </tr>
              <tr v-else-if="pageMembers.length === 0">
                <td :colspan="colSpan" class="wc-detail__empty">
                  {{ emptyTableMessage }}
                </td>
              </tr>
              <tr
                v-for="member in pageMembers"
                v-else
                :key="member.id"
                :class="{ 'wc-detail__row--active': selected?.id === member.id }"
                @click="inspect(member)"
              >
                <td v-for="col in shownColumns" :key="col.key">
                  <template v-if="col.key === 'person'">
                    <span class="wc-detail__person">
                      <span class="wc-detail__avatar" aria-hidden="true">
                        <img
                          v-if="usesPhoto(member)"
                          :src="member.avatar_url"
                          alt=""
                          class="wc-detail__avatar-img"
                          referrerpolicy="no-referrer"
                          @error="onAvatarError(member.id)"
                        />
                        <img
                          v-else
                          :src="FALLBACK_AVATAR_SRC"
                          :srcset="FALLBACK_AVATAR_SRCSET"
                          alt=""
                          class="wc-detail__avatar-fallback"
                        />
                      </span>
                      <span class="wc-detail__person-text">
                        <span>{{ member.name }}</span>
                        <span v-if="member.email" class="wc-detail__muted">{{ member.email }}</span>
                      </span>
                    </span>
                  </template>
                  <template v-else-if="col.key === 'status'">
                    <StatusBadge
                      :on="member.status === 'active'"
                      :label="memberStatusLabel(member.status)"
                    />
                  </template>
                  <template v-else-if="col.key === 'team'">
                    <span v-if="member.team">{{ member.team.name }}</span>
                    <span v-else class="wc-detail__muted">Chưa thuộc nhóm nào</span>
                  </template>
                  <span v-else>{{ cellText(member, col.key) }}</span>
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

      <aside v-if="selected" class="wc-detail__side" aria-label="Chi tiết thành viên">
        <div class="wc-detail__side-head">
          <h2 class="wc-detail__side-title">Chi tiết thành viên</h2>
          <button type="button" class="wc-detail__icon-btn" aria-label="Đóng" @click="selected = null">
            <AppIcon name="close" :size="16" />
          </button>
        </div>

        <div class="wc-detail__side-person">
          <span class="wc-detail__avatar wc-detail__avatar--lg" aria-hidden="true">
            <img
              v-if="usesPhoto(selected)"
              :src="selected.avatar_url"
              alt=""
              class="wc-detail__avatar-img"
              referrerpolicy="no-referrer"
              @error="onAvatarError(selected.id)"
            />
            <img
              v-else
              :src="FALLBACK_AVATAR_SRC"
              :srcset="FALLBACK_AVATAR_SRCSET"
              alt=""
              class="wc-detail__avatar-fallback"
            />
          </span>
          <p class="wc-detail__side-lead">{{ selected.name }}</p>
        </div>

        <div class="wc-detail__rows">
          <div class="wc-detail__row">
            <span class="wc-detail__row-label">Email</span>
            <span class="wc-detail__row-value">{{ selected.email || '—' }}</span>
          </div>
          <div class="wc-detail__row wc-detail__row--dept">
            <span class="wc-detail__row-label wc-detail__row-label--dept">Phòng ban</span>
            <div class="wc-detail__row-dept">
              <select
                id="wc-detail-dept-assign"
                v-model="departmentAssignId"
                class="wc-detail__input wc-detail__input--side"
                :disabled="departmentAssignSaving"
              >
                <option value="" disabled>Chọn phòng ban</option>
                <option v-for="item in departmentOptions" :key="item.id" :value="String(item.id)">
                  {{ item.name }}
                </option>
              </select>
              <button
                type="button"
                class="wc-detail__side-btn"
                :disabled="departmentAssignSaving || departmentAssignUnchanged"
                @click="saveMemberDepartment"
              >
                {{ departmentAssignSaving ? 'Đang lưu…' : 'Chuyển' }}
              </button>
            </div>
          </div>
          <div class="wc-detail__row">
            <span class="wc-detail__row-label">Nhóm</span>
            <span class="wc-detail__row-value">{{ selected.team?.name || 'Chưa thuộc nhóm nào' }}</span>
          </div>
          <div class="wc-detail__row wc-detail__row--dept">
            <span class="wc-detail__row-label wc-detail__row-label--dept">Vai trò</span>
            <div class="wc-detail__row-dept">
              <span class="wc-detail__input wc-detail__input--side wc-detail__role-readonly">
                {{ memberRolesText(selected) }}
              </span>
              <button
                type="button"
                class="wc-detail__side-btn"
                @click="openRoleDialog"
              >
                {{ hasAssignableRole(selected) ? 'Sửa' : 'Gán' }}
              </button>
            </div>
          </div>
          <div class="wc-detail__row">
            <span class="wc-detail__row-label">Trạng thái</span>
            <span class="wc-detail__row-value">
              <StatusBadge
                :on="selected.status === 'active'"
                :label="memberStatusLabel(selected.status)"
              />
            </span>
          </div>
          <div class="wc-detail__row">
            <span class="wc-detail__row-label">Mã thành viên</span>
            <span class="wc-detail__row-value">{{ selected.id }}</span>
          </div>
        </div>
      </aside>
    </div>

    <Teleport to="body">
      <Transition name="wc-dialog-fade">
        <div
          v-if="roleDialogOpen"
          class="wc-dialog"
          role="presentation"
          @mousedown.self="closeRoleDialog"
        >
          <div
            class="wc-dialog__panel"
            role="dialog"
            aria-modal="true"
            aria-labelledby="wc-dept-role-form-title"
          >
            <div class="wc-dialog__head">
              <span class="wc-dialog__icon" aria-hidden="true">
                <AppIcon :name="roleDialogTab === 'edit' ? 'pencil' : 'shield'" :size="22" :stroke-width="1.75" />
              </span>
              <div class="wc-dialog__head-copy">
                <h2 id="wc-dept-role-form-title" class="wc-dialog__title">{{ roleDialogTitle }}</h2>
              </div>
              <button
                type="button"
                class="wc-dialog__close"
                aria-label="Đóng"
                :disabled="roleFormSaving"
                @click="closeRoleDialog"
              >
                <AppIcon name="close" :size="16" />
              </button>
            </div>

            <div class="wc-dialog__tabs" role="tablist" aria-label="Gán hoặc sửa vai trò">
              <button
                type="button"
                class="wc-dialog__tab"
                :class="{ 'wc-dialog__tab--active': roleDialogTab === 'create' }"
                role="tab"
                :aria-selected="roleDialogTab === 'create' ? 'true' : 'false'"
                :disabled="roleFormSaving"
                @click="setRoleDialogTab('create')"
              >
                Gán
              </button>
              <button
                type="button"
                class="wc-dialog__tab"
                :class="{ 'wc-dialog__tab--active': roleDialogTab === 'edit' }"
                role="tab"
                :aria-selected="roleDialogTab === 'edit' ? 'true' : 'false'"
                :disabled="roleFormSaving"
                @click="setRoleDialogTab('edit')"
              >
                Sửa
              </button>
            </div>

            <div class="wc-dialog__body" :class="{ 'wc-dialog__body--edit': roleDialogTab === 'edit' }">
              <div v-if="roleDialogTab === 'edit'" class="wc-dialog__list-panel">
                <label class="wc-dialog__label" for="wc-dept-role-list-q">Thành viên</label>
                <input
                  id="wc-dept-role-list-q"
                  v-model="roleListFilter"
                  type="search"
                  class="wc-dialog__input"
                  placeholder="Lọc theo tên hoặc email…"
                  autocomplete="off"
                  :disabled="roleFormSaving || editRoleCandidates.length === 0"
                />
                <ul class="wc-dialog__list hide-scrollbar" role="listbox" aria-label="Thành viên có vai trò">
                  <li v-if="editRoleCandidates.length === 0" class="wc-dialog__list-empty">
                    {{ roleEmptyMessage }}
                  </li>
                  <li v-else-if="visibleRoleItems.length === 0" class="wc-dialog__list-empty">
                    Không tìm thấy thành viên khớp.
                  </li>
                  <li
                    v-for="item in visibleRoleItems"
                    :key="item.id"
                    class="wc-dialog__list-item"
                    :class="{ 'wc-dialog__list-item--active': String(roleForm.user_id) === String(item.id) }"
                    role="option"
                    :aria-selected="String(roleForm.user_id) === String(item.id) ? 'true' : 'false'"
                    @click="roleForm.user_id = item.id"
                  >
                    <span class="wc-detail__avatar" aria-hidden="true">
                      <img
                        v-if="usesPhoto(item)"
                        :src="item.avatar_url"
                        alt=""
                        class="wc-detail__avatar-img"
                        referrerpolicy="no-referrer"
                        @error="onAvatarError(item.id)"
                      />
                      <img
                        v-else
                        :src="FALLBACK_AVATAR_SRC"
                        :srcset="FALLBACK_AVATAR_SRCSET"
                        alt=""
                        class="wc-detail__avatar-fallback"
                      />
                    </span>
                    <span class="wc-dialog__list-copy">
                      <span class="wc-dialog__list-name">{{ item.label }}</span>
                      <span v-if="item.sublabel" class="wc-dialog__list-sub">{{ item.sublabel }}</span>
                    </span>
                    <span v-if="item.meta" class="wc-dialog__list-meta">{{ item.meta }}</span>
                  </li>
                </ul>
              </div>

              <div class="wc-dialog__stack">
                <div v-if="roleDialogTab === 'create'" class="wc-dialog__field">
                  <label class="wc-dialog__label" for="wc-dept-role-user">
                    Thành viên <span class="wc-dialog__req" aria-hidden="true">*</span>
                  </label>
                  <WorkspaceConfigPicker
                    id="wc-dept-role-user"
                    :key="`role-user-${roleDialogTab}`"
                    v-model="roleForm.user_id"
                    :items="rolePickerItems"
                    placeholder="Gõ tên hoặc email để tìm…"
                    empty-text="Không tìm thấy thành viên khớp."
                    show-avatar
                    :disabled="roleFormSaving || assignCandidates.length === 0"
                  />
                </div>

                <p v-if="roleDialogTab === 'create' && assignCandidates.length === 0" class="wc-dialog__empty">
                  {{ roleEmptyMessage }}
                </p>

                <div class="wc-dialog__field">
                  <span class="wc-dialog__label" id="wc-dept-role-code-label">
                    {{ roleDialogTab === 'edit' ? 'Vai trò mới' : 'Vai trò' }}
                    <span class="wc-dialog__req" aria-hidden="true">*</span>
                  </span>
                  <div class="wc-dialog__roles" role="listbox" aria-labelledby="wc-dept-role-code-label">
                    <button
                      v-for="item in assignableRoles"
                      :key="item.code"
                      type="button"
                      class="wc-dialog__role"
                      :class="{ 'wc-dialog__role--active': roleForm.role_code === item.code }"
                      role="option"
                      :aria-selected="roleForm.role_code === item.code ? 'true' : 'false'"
                      :disabled="roleFormSaving || !roleForm.user_id"
                      @click="roleForm.role_code = item.code"
                    >
                      <span class="wc-dialog__role-name">{{ item.name }}</span>
                      <span v-if="item.description" class="wc-dialog__role-desc">{{ item.description }}</span>
                    </button>
                  </div>
                </div>

                <p
                  v-if="selectedRoleMember && selectedRoleOption && !roleUnchanged"
                  class="wc-dialog__summary"
                >
                  <template v-if="roleDialogTab === 'edit'">
                    Đổi vai trò của <strong>{{ selectedRoleMember.name }}</strong>
                    từ {{ currentRoleOfSelected?.name || '—' }}
                    sang {{ selectedRoleOption.name }}.
                  </template>
                  <template v-else>
                    Gán vai trò <strong>{{ selectedRoleOption.name }}</strong>
                    cho {{ selectedRoleMember.name }}.
                  </template>
                </p>
              </div>
            </div>

            <div class="wc-dialog__actions">
              <button type="button" class="wc-dialog__btn wc-dialog__btn--ghost" :disabled="roleFormSaving" @click="closeRoleDialog">
                Huỷ
              </button>
              <button
                type="button"
                class="wc-dialog__btn wc-dialog__btn--primary"
                :disabled="
                  roleFormSaving ||
                  roleTabCandidates.length === 0 ||
                  !roleForm.user_id ||
                  !roleForm.role_code ||
                  roleUnchanged
                "
                @click="submitRoleForm"
              >
                {{ roleSubmitLabel }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.wc-detail {
  height: 100%;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.wc-detail__body {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-4);
  overflow: hidden;
}

.wc-detail__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.wc-detail__toolbar {
  position: relative;
  z-index: 6;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin: 0 0 var(--space-3);
}

.wc-detail__filters {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--space-3);
  width: 100%;
}

.wc-detail__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
  width: 100%;
}

.wc-detail__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.wc-detail__input {
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

.wc-detail__check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.wc-detail__note {
  flex-shrink: 0;
  margin: 0 0 var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.wc-detail__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.wc-detail__table-wrap--resizing {
  cursor: col-resize;
  user-select: none;
}

.wc-detail__table {
  min-width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: calc(0.875rem * var(--table-zoom, 1));
}

.wc-detail__table thead th {
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

.wc-detail__resize {
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

.wc-detail__resize::after {
  content: '';
  position: absolute;
  top: 25%;
  right: 2px;
  width: 2px;
  height: 50%;
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.wc-detail__resize:hover::after,
.wc-detail__table-wrap--resizing .wc-detail__resize:hover::after {
  background: var(--color-primary);
}

.wc-detail__table tbody td {
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  vertical-align: middle;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.wc-detail__table tbody tr {
  cursor: pointer;
}

.wc-detail__table tbody tr:hover td {
  background: var(--color-surface-muted);
}

.wc-detail__row--active td {
  background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface));
}

.wc-detail__empty {
  padding: var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
  white-space: normal;
}

.wc-detail__person {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  min-width: 0;
}

.wc-detail__person-text {
  display: flex;
  min-width: 0;
  flex-direction: column;
}

.wc-detail__person-text span {
  display: block;
  white-space: nowrap;
}

.wc-detail__avatar {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 2rem;
  height: 2rem;
  overflow: hidden;
  border-radius: var(--radius-full);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-size: 0.6875rem;
  font-weight: 700;
  line-height: 1;
}

.wc-detail__avatar--lg {
  width: 2.75rem;
  height: 2.75rem;
  font-size: 0.875rem;
}

.wc-detail__avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.wc-detail__avatar-fallback {
  box-sizing: border-box;
  width: 100%;
  height: 100%;
  padding: 4%;
  object-fit: contain;
}

.wc-detail__muted {
  margin-top: 0.125rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.wc-detail__side {
  flex-shrink: 0;
  width: 28rem;
  overflow-y: auto;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
}

.wc-detail__side-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.wc-detail__side-title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 700;
}

.wc-detail__icon-btn {
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

.wc-detail__icon-btn:hover {
  background: var(--color-surface-muted);
}

.wc-detail__side-person {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  margin: var(--space-3) 0 var(--space-4);
}

.wc-detail__side-lead {
  margin: 0;
  color: var(--color-text);
  font-weight: 600;
  font-size: 0.9375rem;
  line-height: 1.45;
}

.wc-detail__rows {
  display: flex;
  flex-direction: column;
}

.wc-detail__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.wc-detail__row:last-child {
  box-shadow: none;
}

.wc-detail__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.wc-detail__row-label::after {
  content: ':';
}

.wc-detail__row-value {
  color: var(--color-text);
  font-style: italic;
  text-align: right;
  overflow-wrap: anywhere;
}

.wc-detail__row--dept {
  flex-direction: column;
  align-items: stretch;
  gap: var(--space-2);
}

.wc-detail__row-label--dept {
  flex-shrink: initial;
}

.wc-detail__row-dept {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.wc-detail__input--side {
  flex: 1;
  min-width: 0;
  font-size: 0.8125rem;
}

.wc-detail__role-readonly {
  display: flex;
  align-items: center;
  color: var(--color-text);
  background: var(--color-surface-muted);
}

.wc-detail__side-btn {
  flex-shrink: 0;
  padding: 0.375rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.wc-detail__side-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.wc-detail__side-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

@media (max-width: 1024px) {
  .wc-detail__body {
    flex-direction: column;
  }

  .wc-detail__side {
    width: 100%;
    max-height: 42%;
  }

  .wc-detail__table-wrap {
    min-height: 16rem;
  }

  .wc-detail__filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 480px) {
  .wc-detail__filters {
    grid-template-columns: minmax(0, 1fr);
  }
}

.wc-dialog {
  position: fixed;
  inset: 0;
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.wc-dialog__panel {
  width: min(48rem, 100%);
  max-height: min(46rem, calc(100vh - 2.5rem));
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  padding: 1.5rem 1.75rem 1.25rem;
  overflow: auto;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.wc-dialog__head {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.wc-dialog__icon {
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

.wc-dialog__head-copy {
  flex: 1;
  min-width: 0;
}

.wc-dialog__title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.25rem;
  font-weight: 700;
  line-height: 1.35;
}

.wc-dialog__close {
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

.wc-dialog__close:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.wc-dialog__close:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.wc-dialog__tabs {
  display: flex;
  gap: var(--space-2);
  box-shadow: 0 1px 0 var(--color-border);
}

.wc-dialog__tab {
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

.wc-dialog__tab--active {
  color: var(--color-primary);
  box-shadow: 0 2px 0 var(--color-primary);
}

.wc-dialog__tab:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.wc-dialog__body {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  min-width: 0;
}

.wc-dialog__body--edit {
  display: grid;
  grid-template-columns: minmax(16rem, 18.5rem) minmax(0, 1fr);
  gap: var(--space-5);
  align-items: start;
}

.wc-dialog__list-panel {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-width: 0;
}

.wc-dialog__list {
  flex: 1;
  min-height: 12rem;
  max-height: 22rem;
  overflow: auto;
  margin: 0;
  padding: 0.25rem;
  list-style: none;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}

.wc-dialog__list-empty {
  padding: 0.875rem 0.75rem;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.5;
}

.wc-dialog__list-item {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.5rem 0.625rem;
  border-radius: var(--radius-sm);
  cursor: pointer;
}

.wc-dialog__list-item:hover {
  background: var(--color-surface-muted);
}

.wc-dialog__list-item--active {
  background: color-mix(in srgb, var(--color-primary) 8%, var(--color-surface));
  box-shadow: inset 3px 0 0 var(--color-primary);
}

.wc-dialog__list-copy {
  display: flex;
  min-width: 0;
  flex: 1;
  flex-direction: column;
}

.wc-dialog__list-name,
.wc-dialog__list-sub,
.wc-dialog__list-meta {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wc-dialog__list-name {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.35;
}

.wc-dialog__list-sub,
.wc-dialog__list-meta {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.35;
}

.wc-dialog__list-meta {
  flex-shrink: 0;
  max-width: 6.5rem;
  font-weight: 600;
  text-align: right;
}

.wc-dialog__stack {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  min-width: 0;
}

.wc-dialog__field {
  display: grid;
  grid-template-columns: 7.5rem minmax(0, 1fr);
  column-gap: 0.875rem;
  row-gap: 0.375rem;
  align-items: start;
  min-width: 0;
}

.wc-dialog__label {
  padding-top: 0.65rem;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.3;
}

.wc-dialog__field > :not(.wc-dialog__label) {
  min-width: 0;
}

.wc-dialog__req {
  color: var(--color-primary);
}

.wc-dialog__input {
  width: 100%;
  min-width: 0;
  min-height: 2.75rem;
  padding: 0.625rem 0.875rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.9375rem;
}

.wc-dialog__input:focus {
  outline: 2px solid color-mix(in srgb, var(--color-primary) 35%, transparent);
  outline-offset: 1px;
  border-color: var(--color-primary);
}

.wc-dialog__input::placeholder {
  color: var(--color-text-muted);
}

.wc-dialog__input:disabled {
  opacity: 0.65;
  cursor: not-allowed;
  background: var(--color-surface-muted);
}

.wc-dialog__roles {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.wc-dialog__role {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.125rem;
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  text-align: left;
  cursor: pointer;
}

.wc-dialog__role:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.wc-dialog__role--active {
  border-color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 8%, var(--color-surface));
  box-shadow: inset 0 0 0 1px var(--color-primary);
}

.wc-dialog__role:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.wc-dialog__role-name {
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 700;
}

.wc-dialog__role-desc {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.45;
}

.wc-dialog__empty,
.wc-dialog__summary {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.5;
}

.wc-dialog__stack > .wc-dialog__empty,
.wc-dialog__stack > .wc-dialog__summary {
  margin-left: calc(7.5rem + 0.875rem);
}

.wc-dialog__list-panel > .wc-dialog__label {
  padding-top: 0;
}

.wc-dialog__summary strong {
  color: var(--color-text);
  font-weight: 700;
}

.wc-dialog__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

.wc-dialog__btn {
  padding: 0.625rem 1.25rem;
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.wc-dialog__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.wc-dialog__btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.wc-dialog__btn--primary:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.wc-dialog__btn--ghost {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
}

.wc-dialog__btn--ghost:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.wc-dialog-fade-enter-active,
.wc-dialog-fade-leave-active {
  transition: opacity 0.15s ease;
}

.wc-dialog-fade-enter-from,
.wc-dialog-fade-leave-to {
  opacity: 0;
}

@media (max-width: 640px) {
  .wc-dialog {
    padding: var(--space-4);
    align-items: flex-end;
  }

  .wc-dialog__panel {
    max-width: 100%;
    max-height: min(92vh, 46rem);
    padding: var(--space-4);
  }

  .wc-dialog__body--edit {
    grid-template-columns: minmax(0, 1fr);
  }

  .wc-dialog__field {
    grid-template-columns: minmax(0, 1fr);
  }

  .wc-dialog__label {
    padding-top: 0;
  }

  .wc-dialog__stack > .wc-dialog__empty,
  .wc-dialog__stack > .wc-dialog__summary {
    margin-left: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .wc-dialog-fade-enter-active,
  .wc-dialog-fade-leave-active {
    transition: none;
  }
}
</style>
