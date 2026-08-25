<script setup>
//
// superadmin/workspace-config/departments/:departmentId — workspace của
// 1 phòng ban, mở từ WorkspaceConfigOverviewSuperadmin. Bảng theo mẫu
// ActivityLog: filter, 2 thanh trang, kéo cột, panel chi tiết đẩy ngang.
// Chỉ xem — super_admin không sửa thay department_director.
//
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import { showClientToast } from '@/lib/clientToast';
import StatusBadge from '../components/StatusBadge.vue';
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
  menuVisibilityLabel,
  saveVisibility,
} from '../constants/departmentDetail.js';

const CELL_PAD_X = 32;
const COL_EXTRA = 24;
const AVATAR_EXTRA = 40;
let measureCtx = null;
let wrapObserver = null;

const route = useRoute();
const department = ref(null);
const allMembers = ref([]);
const sidebarMenus = ref([]);
const evaluationCriteria = ref([]);
const loading = ref(false);
const selected = ref(null);
const brokenAvatarIds = ref(new Set());

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
const MIN_COL_PX = 72;
const columnWidths = reactive(loadColumnWidths());
const tableZoom = ref(loadZoom());

const shownColumns = computed(() => DETAIL_COLUMNS.filter((col) => visibleColumns[col.key]));
const colSpan = computed(() => Math.max(shownColumns.value.length, 1));

const directorSubtitle = computed(() => {
  const director = department.value?.director;
  if (!director?.name) return '';
  return director.email ? `${director.name} · ${director.email}` : director.name;
});

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

async function loadDetail() {
  loading.value = true;
  try {
    const { data } = await window.axios.get(
      `/api/workspace-config/departments/${route.params.departmentId}`,
    );
    department.value = data.department;
    allMembers.value = data.members ?? [];
    sidebarMenus.value = data.sidebar_menus ?? [];
    evaluationCriteria.value = data.evaluation_criteria ?? [];
    if (selected.value && !allMembers.value.some((member) => member.id === selected.value.id)) {
      selected.value = null;
    }
    nextTick(fitColumnsToContent);
  } catch (error) {
    department.value = null;
    allMembers.value = [];
    sidebarMenus.value = [];
    evaluationCriteria.value = [];
    selected.value = null;
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không tải được chi tiết phòng ban.');
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
  teamId.value = '';
  status.value = '';
  role.value = '';
  page.value = 1;
}

function inspect(member) {
  selected.value = member;
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

watch(filteredMembers, (rows) => {
  if (selected.value && !rows.some((member) => member.id === selected.value.id)) {
    selected.value = null;
  }
  if (page.value > lastPage.value) {
    page.value = lastPage.value;
  }
});

watch(
  () => route.params.departmentId,
  () => {
    selected.value = null;
    page.value = 1;
    loadDetail();
  },
);

onMounted(() => {
  document.addEventListener('keydown', handleDocumentKeydown);
  loadDetail();
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
  wrapObserver?.disconnect();
});
</script>

<template>
  <section class="wc-detail">
    <PageHeader
      :title="department ? department.name : 'Chi tiết phòng ban'"
      :subtitle="directorSubtitle"
      icon="building"
      :breadcrumbs="[
        { label: 'Trang chủ', to: { name: 'home' } },
        { label: 'Cấu hình Workspace', to: { name: 'superadmin.workspace-config.overview' } },
        { label: department ? department.name : '' },
      ]"
    >
      <template #actions>
        <button type="button" class="wc-detail__header-btn" :disabled="loading" @click="loadDetail">
          <AppIcon name="refresh" :size="16" :class="{ 'wc-detail__spin': loading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

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
          <div class="wc-detail__row">
            <span class="wc-detail__row-label">Nhóm</span>
            <span class="wc-detail__row-value">{{ selected.team?.name || 'Chưa thuộc nhóm nào' }}</span>
          </div>
          <div class="wc-detail__row">
            <span class="wc-detail__row-label">Vai trò</span>
            <span class="wc-detail__row-value">{{ memberRolesText(selected) }}</span>
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

        <template v-if="sidebarMenus.length">
          <h3 class="wc-detail__side-subtitle">Menu hiển thị</h3>
          <div class="wc-detail__rows">
            <div v-for="menu in sidebarMenus" :key="menu.menu_key" class="wc-detail__row">
              <span class="wc-detail__row-label">{{ menu.label }}</span>
              <span class="wc-detail__row-value">
                <StatusBadge
                  :on="menu.is_visible"
                  :label="menuVisibilityLabel(menu.is_visible)"
                />
              </span>
            </div>
          </div>
        </template>

        <template v-if="evaluationCriteria.length">
          <h3 class="wc-detail__side-subtitle">Tiêu chí đánh giá</h3>
          <div class="wc-detail__rows">
            <div
              v-for="criterion in evaluationCriteria"
              :key="criterion.id"
              class="wc-detail__row wc-detail__row--eval"
            >
              <span class="wc-detail__row-label">
                {{ criterion.name }}
                <span class="wc-detail__eval-type">
                  {{ criterion.criterion_type?.name ? `${criterion.criterion_type.name} · ` : '' }}{{ criterion.type === 'scale' ? 'Thang điểm' : 'Cộng/trừ' }}
                  <template v-if="criterion.criterion_type?.code"> · {{ criterion.criterion_type.code }}</template>
                </span>
              </span>
              <span class="wc-detail__row-value">
                {{ criterion.level_count }} mức · max {{ criterion.max_score }}đ
                <StatusBadge
                  :on="criterion.is_active"
                  :label="criterion.is_active ? 'Dùng' : 'Tắt'"
                />
              </span>
            </div>
          </div>
        </template>
        <p v-else-if="!loading" class="wc-detail__eval-empty">
          Phòng ban chưa có tiêu chí đánh giá nào.
        </p>
      </aside>
    </div>
  </section>
</template>

<style scoped>
.wc-detail {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
}

.wc-detail__header-btn {
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

.wc-detail__header-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.wc-detail__header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.wc-detail__spin {
  animation: wc-detail-spin 0.8s linear infinite;
}

@keyframes wc-detail-spin {
  to {
    transform: rotate(360deg);
  }
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
  margin: var(--space-3) 0;
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
  width: 20rem;
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

.wc-detail__side-subtitle {
  margin: var(--space-5) 0 var(--space-2);
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 700;
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

.wc-detail__row-value {
  color: var(--color-text);
  font-weight: 600;
  text-align: right;
  overflow-wrap: anywhere;
}

.wc-detail__row--eval .wc-detail__row-label {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.wc-detail__eval-type {
  font-size: 0.6875rem;
  font-weight: 400;
  color: var(--color-text-muted);
}

.wc-detail__row--eval .wc-detail__row-value {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.25rem;
  font-size: 0.8125rem;
}

.wc-detail__eval-empty {
  margin: var(--space-3) 0 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
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

@media (max-width: 768px) {
  .wc-detail {
    padding: var(--space-4);
  }
}

@media (max-width: 480px) {
  .wc-detail {
    padding: var(--space-3);
  }

  .wc-detail__filters {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (prefers-reduced-motion: reduce) {
  .wc-detail__spin {
    animation: none;
  }
}
</style>
