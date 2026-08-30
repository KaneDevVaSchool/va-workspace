<script setup>
//
// Trang danh sách Dự án (Project module). Theo mẫu vàng data-table
// (ActivityLog.vue: TablePagesBar trên/dưới + cột kéo nắm mép + panel chi
// tiết đẩy ngang), có điều chỉnh theo yêu cầu riêng của trang này:
// - Ô tìm tên/mã trên PageHeader. Lọc theo nhãn là nút cạnh Cột trên TablePagesBar.
// - Nút "Thêm dự án" là dấu + cạnh title (PageHeader primaryAction).
// - Bảng nhóm theo Phòng ban thực hiện (executingDepartments()[0] —
//   executing_department, hoặc phòng ban đầu tiên trong scopes kiểu
//   "Phòng Ban" khi nhiều phòng ban cùng làm; fallback owner_department khi
//   không giao) — mỗi nhóm đóng/mở được. Cột "Phòng ban thực hiện" hiện đủ
//   TẤT CẢ phòng ban (executing_department + department trong scopes),
//   nhiều pill tự xuống hàng khi cột hẹp (xem executingDepartments()).
// - Đủ cột dữ liệu backend đã trả (present() trong ProjectService); hiện/ẩn
//   cột qua nút Cột trên TablePagesBar (slot #settings), không tách nút riêng.
// - Gắn/bỏ nhãn ngay tại dòng trong cột Tên dự án (dialog overlay, không cần vào trang sửa).
// - Import/Export Excel qua nút Dữ liệu trên PageHeader (giống tiêu chí đánh giá).
//
// Tạo/sửa dự án là 2 TRANG RIÊNG (ProjectCreate.vue / ProjectEdit.vue).
//
// Chế độ xem: "Danh sách" (bảng ở trên) hoặc "Kanban" (bảng theo cột, kéo-thả
// đổi trạng thái). Kanban có 5 cách nhóm cột:
// - "Theo trạng thái" (mặc định, giống mẫu 1Office) — mỗi cột là 1 trạng thái
//   dự án, kéo thẻ sang cột khác để đổi trạng thái (PUT /api/project/:id).
//   Tab Tất cả: đủ cột trạng thái. Tab một trạng thái (Đang thực hiện…): chỉ
//   cột đó, thẻ trải ngang cho kín hàng — không giữ cột trống của trạng thái khác.
//   Kéo thả dùng pointer (ghost + placeholder), không dùng HTML5 drag-and-drop
//   vì ghost mặc định của trình duyệt giật và không animate được.
// - "Theo người tham gia" — chọn 1 hay nhiều người (multi-select), mỗi người
//   chọn là 1 cột; 1 dự án có thể xuất hiện ở nhiều cột nếu nhiều người được
//   chọn cùng tham gia dự án đó. Không kéo-thả ở chế độ này (1 dự án nhiều
//   người tham gia — không có "cột đúng duy nhất" để thả vào).
// - "Theo tiến trình" — 3 cột cố định theo loại dự án: Nghiên cứu phát triển,
//   Vận hành cải tiến, Triển khai nghiệm thu. Kéo thẻ sang cột khác để đổi
//   loại dự án (PUT /api/project/:id { type }).
// - "Theo loại tiêu chí" — mọi loại dự án còn lại (trừ 3 loại tiến trình ở trên).
//   Kéo thẻ sang cột khác để đổi loại dự án, giống theo tiến trình.
// - "Theo phòng ban" — mỗi cột là 1 phòng ban thực hiện (fallback phòng ban
//   sở hữu). 1 dự án có thể lặp ở nhiều cột nếu nhiều phòng ban cùng làm.
//   Không kéo-thả.
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import DualProgressBar from '@/components/DualProgressBar.vue';
import { showClientToast } from '@/lib/clientToast';
import { computeExpectedProgress } from '@/lib/progress';
import { useDragScroll } from '@/composables/useDragScroll';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import ProjectLabelPicker from '../components/ProjectLabelPicker.vue';
import ProjectRowContextMenu from '../components/ProjectRowContextMenu.vue';
import ProjectQuickActionModals from '../components/ProjectQuickActionModals.vue';

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
  { key: 'member_count', label: 'Người tham gia' },
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
  { key: 'all', label: 'Tất cả', tone: 'primary' },
  { key: 'in_progress', label: 'Đang thực hiện', tone: 'info' },
  { key: 'completed', label: 'Hoàn thành', tone: 'success' },
  { key: 'on_hold', label: 'Tạm dừng', tone: 'gold' },
  { key: 'planning', label: 'Đang chờ', tone: 'warning' },
  { key: 'cancelled', label: 'Đã huỷ', tone: 'umber' },
  { key: 'following', label: 'Bạn theo dõi', tone: 'secondary' },
  { key: 'my_tasks', label: 'Bạn thực hiện', tone: 'tertiary' },
  { key: 'my_department', label: 'Phòng ban bạn', tone: 'violet' },
];

// Tab lọc trùng key trạng thái — khi kanban theo trạng thái, không giữ các
// cột trạng thái khác (trống), mà trải thẻ của tab đang chọn theo hàng ngang.
const STATUS_TAB_KEYS = ['planning', 'in_progress', 'on_hold', 'completed', 'cancelled'];

const MIN_COL_PX = 72;
const DESC_INITIAL_PX = 280;
const CELL_PAD_X = 32;
const COL_EXTRA = 24;
const LEAD_AVATAR_EXTRA = 42;
const MEMBER_AVATARS_MAX = 3;
const MEMBER_AVATAR_EXTRA = 42 + (MEMBER_AVATARS_MAX - 1) * 22 + 30;
const STATUS_DOT_EXTRA = 28;
const LABEL_CHIP_EXTRA = 36;
const LABEL_ADD_EXTRA = 22;
const COL_WIDTH_KEY = 'project-list-col-widths-v2';
const ZOOM_KEY = 'project-list-zoom';
const UNGROUPED_KEY = '__ungrouped__';
const THEME_TONES = [
  'primary',
  'secondary',
  'tertiary',
  'gold',
  'umber',
  'success',
  'warning',
  'info',
  'violet',
  'teal',
  'rose',
  'moss',
  'dusk',
  'pine',
];
let measureCtx = null;
let wrapObserver = null;

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
const filterLabelIds = ref([]);
const labelFilterQuery = ref('');

// ---------- Chế độ xem: Danh sách / Kanban ----------
const VIEW_MODE_KEY = 'project-list-view-mode';
const KANBAN_GROUP_KEY = 'project-list-kanban-group';
const KANBAN_MEMBERS_KEY = 'project-list-kanban-members';
const KANBAN_PER_PAGE = 500;
const KANBAN_GROUP_MODES = ['status', 'members', 'progress', 'other_types', 'department'];
const PROGRESS_TYPE_NAMES = ['Nghiên cứu phát triển', 'Vận hành cải tiến', 'Triển khai nghiệm thu'];
const PROGRESS_TYPE_TONES = ['info', 'gold', 'success'];

function loadViewMode() {
  try {
    const raw = localStorage.getItem(VIEW_MODE_KEY);
    if (raw === 'list' || raw === 'kanban') return raw;
  } catch {
    // Bỏ qua.
  }
  return 'list';
}

function loadKanbanGroup() {
  try {
    const raw = localStorage.getItem(KANBAN_GROUP_KEY);
    if (KANBAN_GROUP_MODES.includes(raw)) return raw;
  } catch {
    // Bỏ qua.
  }
  return 'status';
}

function loadKanbanMemberIds() {
  try {
    const raw = localStorage.getItem(KANBAN_MEMBERS_KEY);
    const parsed = raw ? JSON.parse(raw) : [];
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

const viewMode = ref(loadViewMode());
const kanbanGroupBy = ref(loadKanbanGroup());
const kanbanMemberIds = ref(loadKanbanMemberIds());
const kanbanMemberPickerOpen = ref(false);
const viewModeOpen = ref(false);
const kanbanStatusUpdating = ref(new Set());
const kanbanJustMovedId = ref(null);
const kanbanDrag = reactive({
  active: false,
  settling: false,
  projectId: null,
  fromKey: null,
  overKey: null,
  project: null,
  width: 0,
  height: 0,
  x: 0,
  y: 0,
});

const KANBAN_DRAG_THRESHOLD = 7;
let kanbanPointer = null;
let kanbanPendingX = 0;
let kanbanPendingY = 0;
let kanbanRaf = 0;
let kanbanScrollRaf = 0;
let kanbanJustMovedTimer = 0;

const isKanban = computed(() => viewMode.value === 'kanban');

const KANBAN_GROUP_LABELS = {
  status: 'Theo trạng thái',
  members: 'Theo người tham gia',
  progress: 'Theo tiến trình',
  other_types: 'Theo loại tiêu chí',
  department: 'Theo phòng ban',
};

const viewModeTriggerLabel = computed(() => {
  if (!isKanban.value) return 'Danh sách';
  return KANBAN_GROUP_LABELS[kanbanGroupBy.value] || 'Kanban';
});

function isProgressProjectType(type) {
  const key = String(type || '').trim().toLocaleLowerCase('vi');
  if (!key) return false;
  return PROGRESS_TYPE_NAMES.some((name) => name.toLocaleLowerCase('vi') === key);
}

function sameProjectType(a, b) {
  return String(a || '').trim().toLocaleLowerCase('vi') === String(b || '').trim().toLocaleLowerCase('vi');
}

// Danh sách người dùng để chọn cột "Theo người tham gia" — gộp từ members +
// lead của các dự án đang có trong trang (đủ dùng, không cần API riêng).
const allAssignableUsers = computed(() => {
  const map = new Map();
  for (const project of projects.value) {
    if (project.lead) map.set(project.lead.id, project.lead);
    for (const member of project.members || []) map.set(member.id, member);
  }
  return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name, 'vi'));
});

const kanbanSelectedUsers = computed(() =>
  kanbanMemberIds.value.map((id) => allAssignableUsers.value.find((u) => u.id === id)).filter(Boolean),
);

function setViewMode(mode) {
  viewModeOpen.value = false;
  if (viewMode.value === mode) return;
  viewMode.value = mode;
  try {
    localStorage.setItem(VIEW_MODE_KEY, mode);
  } catch {
    // Bỏ qua.
  }
  loadProjects(1);
}

function toggleViewModeMenu() {
  viewModeOpen.value = !viewModeOpen.value;
}

function closeViewModeMenu() {
  viewModeOpen.value = false;
}

function chooseKanbanGroup(mode) {
  const switching = viewMode.value !== 'kanban';
  if (kanbanGroupBy.value !== mode) {
    kanbanGroupBy.value = mode;
    try {
      localStorage.setItem(KANBAN_GROUP_KEY, mode);
    } catch {
      // Bỏ qua.
    }
  }
  if (switching) {
    viewMode.value = 'kanban';
    try {
      localStorage.setItem(VIEW_MODE_KEY, 'kanban');
    } catch {
      // Bỏ qua.
    }
    loadProjects(1);
  }
  closeViewModeMenu();
}

function saveKanbanMemberIds() {
  try {
    localStorage.setItem(KANBAN_MEMBERS_KEY, JSON.stringify(kanbanMemberIds.value));
  } catch {
    // Bỏ qua.
  }
}

function toggleKanbanMember(userId) {
  const next = kanbanMemberIds.value.includes(userId)
    ? kanbanMemberIds.value.filter((id) => id !== userId)
    : [...kanbanMemberIds.value, userId];
  kanbanMemberIds.value = next;
  saveKanbanMemberIds();
}

function removeKanbanMember(userId) {
  kanbanMemberIds.value = kanbanMemberIds.value.filter((id) => id !== userId);
  saveKanbanMemberIds();
}

function toggleKanbanMemberPicker() {
  kanbanMemberPickerOpen.value = !kanbanMemberPickerOpen.value;
}

function closeKanbanMemberPicker() {
  kanbanMemberPickerOpen.value = false;
}

// Cột kanban theo trạng thái — cố định theo đúng thứ tự ProjectEnums::STATUSES.
// Khi lọc một trạng thái cụ thể (không phải Tất cả / theo dõi / …), chỉ giữ
// cột đang có dự án rồi trải thẻ ngang cho kín khung, không để cột trống.
const kanbanStatusFill = computed(
  () => isKanban.value && kanbanGroupBy.value === 'status' && STATUS_TAB_KEYS.includes(activeTab.value),
);

const kanbanSpread = computed(() => isKanban.value && kanbanGroupBy.value === 'progress');

const kanbanStatusColumns = computed(() => {
  const cols = options.status.map((s) => ({
    key: s.value,
    dropKey: s.value,
    label: s.label,
    tone: statusTone(s.value),
    projects: projects.value.filter((p) => p.status === s.value),
  }));
  if (!kanbanStatusFill.value) return cols;
  return cols.filter((col) => col.projects.length > 0);
});

const isKanbanDragGroup = computed(
  () => kanbanGroupBy.value === 'status' || kanbanGroupBy.value === 'progress' || kanbanGroupBy.value === 'other_types',
);

const kanbanCardsMovable = computed(() => {
  if (!isKanbanDragGroup.value) return false;
  if (kanbanStatusFill.value && kanbanStatusColumns.value.length < 2) return false;
  if (kanbanGroupBy.value === 'other_types' && kanbanOtherTypeColumns.value.length < 2) return false;
  return true;
});

// Cột kanban theo người tham gia — 1 dự án có thể lặp lại ở nhiều cột.
const kanbanMemberColumns = computed(() =>
  kanbanSelectedUsers.value.map((user, index) => ({
    key: `user-${user.id}`,
    label: user.name,
    tone: THEME_TONES[index % THEME_TONES.length],
    user,
    projects: projects.value.filter(
      (p) => p.lead?.id === user.id || (p.members || []).some((m) => m.id === user.id),
    ),
  })),
);

// Cột kanban theo tiến trình — 3 loại dự án cố định, luôn hiện kể cả cột trống.
const kanbanProgressColumns = computed(() =>
  PROGRESS_TYPE_NAMES.map((name, index) => ({
    key: `type-${name}`,
    dropKey: name,
    label: name,
    tone: PROGRESS_TYPE_TONES[index],
    projects: projects.value.filter((p) => sameProjectType(p.type, name)),
  })),
);

// Cột kanban theo loại khác — mọi loại trong danh mục + loại đang có dữ liệu,
// trừ 3 loại tiến trình ở trên.
const kanbanOtherTypeColumns = computed(() => {
  const seen = new Set();
  const cols = [];
  const add = (name) => {
    const trimmed = String(name || '').trim();
    if (!trimmed || isProgressProjectType(trimmed)) return;
    const key = trimmed.toLocaleLowerCase('vi');
    if (seen.has(key)) return;
    seen.add(key);
    cols.push({
      key: `type-${trimmed}`,
      dropKey: trimmed,
      label: trimmed,
      projects: projects.value.filter((p) => sameProjectType(p.type, trimmed)),
    });
  };
  for (const opt of options.type) add(opt.label || opt.value);
  for (const project of projects.value) add(project.type);
  return cols
    .sort((a, b) => a.label.localeCompare(b.label, 'vi'))
    .map((col, index) => ({
      ...col,
      tone: THEME_TONES[index % THEME_TONES.length],
    }));
});

// Cột kanban theo phòng ban thực hiện. 1 dự án có thể lặp ở nhiều cột khi
// nhiều phòng ban cùng làm; không có phòng ban thực hiện thì dùng phòng ban
// sở hữu, rồi mới tới nhóm chưa xác định.
const kanbanDepartmentColumns = computed(() => {
  const groups = new Map();
  for (const project of projects.value) {
    const depts = executingDepartments(project);
    const targets = depts.length
      ? depts
      : project.owner_department
        ? [project.owner_department]
        : [{ id: null, name: 'Chưa xác định phòng ban' }];
    for (const dept of targets) {
      const key = dept.id ? `dept-${dept.id}` : UNGROUPED_KEY;
      if (!groups.has(key)) {
        groups.set(key, {
          key,
          label: dept.name || 'Chưa xác định phòng ban',
          tone: departmentTone(key),
          projects: [],
        });
      }
      groups.get(key).projects.push(project);
    }
  }
  return Array.from(groups.values())
    .sort((a, b) => {
      if (a.key === UNGROUPED_KEY) return 1;
      if (b.key === UNGROUPED_KEY) return -1;
      return a.label.localeCompare(b.label, 'vi');
    })
    .map((col, index) => ({
      ...col,
      tone: col.key === UNGROUPED_KEY ? 'neutral' : THEME_TONES[index % THEME_TONES.length],
    }));
});

const kanbanColumns = computed(() => {
  if (kanbanGroupBy.value === 'members') return kanbanMemberColumns.value;
  if (kanbanGroupBy.value === 'progress') return kanbanProgressColumns.value;
  if (kanbanGroupBy.value === 'other_types') return kanbanOtherTypeColumns.value;
  if (kanbanGroupBy.value === 'department') return kanbanDepartmentColumns.value;
  return kanbanStatusColumns.value;
});

function formatDateParts(value) {
  if (!value) return null;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return null;
  const dd = String(date.getDate()).padStart(2, '0');
  const mm = String(date.getMonth() + 1).padStart(2, '0');
  return { dd, mm, year: date.getFullYear() };
}

function projectDateRangeLabel(project) {
  const start = formatDateParts(project.start_date);
  const end = formatDateParts(project.end_date);
  if (!start && !end) return '';
  if (start && end) {
    const sameYear = start.year === end.year;
    const left = sameYear ? `${start.dd}/${start.mm}` : `${start.dd}/${start.mm}/${start.year}`;
    return `${left} – ${end.dd}/${end.mm}/${end.year}`;
  }
  const only = start || end;
  return `${only.dd}/${only.mm}/${only.year}`;
}

function kanbanCardPeople(project) {
  const map = new Map();
  if (project.lead) map.set(project.lead.id, project.lead);
  for (const member of project.members || []) map.set(member.id, member);
  return Array.from(map.values());
}

function kanbanCardImages(project) {
  return (project.attachments || []).filter((item) => item.kind === 'image' && item.file_url).slice(0, 3);
}

function kanbanCardDeptName(project) {
  const names = executingDepartments(project)
    .map((dept) => dept.name)
    .filter(Boolean);
  if (names.length) return names.join(', ');
  return project.owner_department?.name || '';
}

function kanbanProjectDropKey(project) {
  if (kanbanGroupBy.value === 'status') return project.status;
  return project.type;
}

function kanbanDropSlot(col) {
  return (
    isKanbanDragGroup.value &&
    kanbanDrag.active &&
    col.dropKey &&
    kanbanDrag.overKey === col.dropKey &&
    kanbanDrag.fromKey !== col.dropKey
  );
}

function prefersReducedMotion() {
  return Boolean(window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches);
}

function waitMs(ms) {
  return new Promise((resolve) => window.setTimeout(resolve, ms));
}

function stopKanbanPointerListeners() {
  window.removeEventListener('pointermove', onKanbanPointerMove);
  window.removeEventListener('pointerup', onKanbanPointerUp);
  window.removeEventListener('pointercancel', onKanbanPointerCancel);
}

function resetKanbanDragFields() {
  kanbanDrag.active = false;
  kanbanDrag.settling = false;
  kanbanDrag.projectId = null;
  kanbanDrag.fromKey = null;
  kanbanDrag.overKey = null;
  kanbanDrag.project = null;
  kanbanDrag.width = 0;
  kanbanDrag.height = 0;
  kanbanDrag.x = 0;
  kanbanDrag.y = 0;
}

function clearKanbanDrag() {
  if (kanbanRaf) {
    cancelAnimationFrame(kanbanRaf);
    kanbanRaf = 0;
  }
  if (kanbanScrollRaf) {
    cancelAnimationFrame(kanbanScrollRaf);
    kanbanScrollRaf = 0;
  }
  stopKanbanPointerListeners();
  kanbanPointer = null;
  document.body.style.userSelect = '';
  document.body.style.cursor = '';
  document.body.classList.remove('proj-kanban-dragging');
  resetKanbanDragFields();
}

function readKanbanDropKey(clientX, clientY) {
  const el = document.elementFromPoint(clientX, clientY);
  const col = el?.closest?.('.proj-kanban__col');
  return col?.dataset?.dropKey || null;
}

function autoScrollKanban(clientX, clientY) {
  const wrap = kanbanWrap.value;
  if (!wrap) return;
  const rect = wrap.getBoundingClientRect();
  const edge = 56;
  if (clientX < rect.left + edge) wrap.scrollLeft -= 18;
  else if (clientX > rect.right - edge) wrap.scrollLeft += 18;

  const body = document.elementFromPoint(clientX, clientY)?.closest?.('.proj-kanban__col-body');
  if (!body) return;
  const bodyRect = body.getBoundingClientRect();
  if (clientY < bodyRect.top + 44) body.scrollTop -= 14;
  else if (clientY > bodyRect.bottom - 44) body.scrollTop += 14;
}

function runKanbanAutoScroll() {
  kanbanScrollRaf = 0;
  if (!kanbanDrag.active || kanbanDrag.settling) return;
  autoScrollKanban(kanbanPendingX, kanbanPendingY);
  kanbanScrollRaf = requestAnimationFrame(runKanbanAutoScroll);
}

function flushKanbanGhost() {
  kanbanRaf = 0;
  if (!kanbanDrag.active || kanbanDrag.settling) return;
  kanbanDrag.x = kanbanPendingX - (kanbanPointer?.grabX || 0);
  kanbanDrag.y = kanbanPendingY - (kanbanPointer?.grabY || 0);
  const over = readKanbanDropKey(kanbanPendingX, kanbanPendingY);
  if (over) kanbanDrag.overKey = over;
}

function startKanbanDrag(event) {
  const pointer = kanbanPointer;
  if (!pointer) return;
  const rect = pointer.cardEl.getBoundingClientRect();
  pointer.grabX = event.clientX - rect.left;
  pointer.grabY = event.clientY - rect.top;
  kanbanDrag.active = true;
  kanbanDrag.settling = false;
  kanbanDrag.projectId = pointer.project.id;
  kanbanDrag.fromKey = kanbanProjectDropKey(pointer.project);
  kanbanDrag.overKey = kanbanDrag.fromKey;
  kanbanDrag.project = pointer.project;
  kanbanDrag.width = rect.width;
  kanbanDrag.height = rect.height;
  kanbanDrag.x = rect.left;
  kanbanDrag.y = rect.top;
  document.body.style.userSelect = 'none';
  document.body.style.cursor = 'grabbing';
  document.body.classList.add('proj-kanban-dragging');
  event.preventDefault();
  if (!kanbanScrollRaf) kanbanScrollRaf = requestAnimationFrame(runKanbanAutoScroll);
}

function onKanbanCardPointerDown(event, project) {
  if (!isKanbanDragGroup.value) return;
  if (event.button !== 0) return;
  if (kanbanStatusUpdating.value.has(project.id)) return;
  if (event.target.closest('button, a, input, select, textarea, [role="menu"]')) return;
  if (kanbanDrag.active || kanbanDrag.settling) return;

  kanbanPointer = {
    project,
    cardEl: event.currentTarget,
    startX: event.clientX,
    startY: event.clientY,
    grabX: 0,
    grabY: 0,
  };
  kanbanPendingX = event.clientX;
  kanbanPendingY = event.clientY;
  window.addEventListener('pointermove', onKanbanPointerMove);
  window.addEventListener('pointerup', onKanbanPointerUp);
  window.addEventListener('pointercancel', onKanbanPointerCancel);
}

function onKanbanPointerMove(event) {
  if (!kanbanPointer) return;
  kanbanPendingX = event.clientX;
  kanbanPendingY = event.clientY;
  if (!kanbanDrag.active) {
    if (!kanbanCardsMovable.value) return;
    const dist = Math.hypot(event.clientX - kanbanPointer.startX, event.clientY - kanbanPointer.startY);
    if (dist < KANBAN_DRAG_THRESHOLD) return;
    startKanbanDrag(event);
  }
  if (!kanbanRaf) kanbanRaf = requestAnimationFrame(flushKanbanGhost);
}

async function settleKanbanGhost(rect) {
  if (!rect || prefersReducedMotion()) return;
  kanbanDrag.settling = true;
  kanbanDrag.x = rect.left;
  kanbanDrag.y = rect.top;
  await waitMs(220);
}

async function onKanbanPointerUp() {
  const pointer = kanbanPointer;
  const wasDragging = kanbanDrag.active;
  stopKanbanPointerListeners();

  if (!wasDragging) {
    kanbanPointer = null;
    if (pointer?.project) inspect(pointer.project);
    return;
  }

  const projectId = kanbanDrag.projectId;
  const fromKey = kanbanDrag.fromKey;
  const target = kanbanDrag.overKey;
  const sameColumn = !target || target === fromKey;

  if (sameColumn) {
    const origin = pointer?.cardEl?.getBoundingClientRect();
    await settleKanbanGhost(origin);
    if (!kanbanDrag.active) return;
    clearKanbanDrag();
    return;
  }

  const slot = kanbanWrap.value?.querySelector('.proj-kanban__placeholder');
  await settleKanbanGhost(slot?.getBoundingClientRect());
  if (!kanbanDrag.active) return;
  clearKanbanDrag();
  await commitKanbanDrop(projectId, target);
}

function onKanbanPointerCancel() {
  clearKanbanDrag();
}

function markKanbanJustMoved(projectId) {
  kanbanJustMovedId.value = projectId;
  window.clearTimeout(kanbanJustMovedTimer);
  kanbanJustMovedTimer = window.setTimeout(() => {
    if (kanbanJustMovedId.value === projectId) kanbanJustMovedId.value = null;
  }, 360);
}

async function moveProjectToStatus(projectId, statusKey) {
  if (!projectId || !statusKey) return;

  const project = projects.value.find((p) => p.id === projectId);
  if (!project || project.status === statusKey) return;

  const previousStatus = project.status;
  project.status = statusKey;
  markKanbanJustMoved(projectId);
  const busy = new Set(kanbanStatusUpdating.value);
  busy.add(projectId);
  kanbanStatusUpdating.value = busy;

  try {
    const { data } = await window.axios.put(`/api/project/${projectId}`, { status: statusKey });
    const index = projects.value.findIndex((p) => p.id === projectId);
    if (index !== -1) projects.value.splice(index, 1, data.project);
    if (selected.value?.id === projectId) selected.value = data.project;
    showClientToast('success', `Đã chuyển "${project.name}" sang ${statusLabel(statusKey)}.`);
  } catch (err) {
    project.status = previousStatus;
    showClientToast('error', err?.response?.data?.message || 'Không đổi được trạng thái dự án.');
  } finally {
    const next = new Set(kanbanStatusUpdating.value);
    next.delete(projectId);
    kanbanStatusUpdating.value = next;
  }
}

async function moveProjectToType(projectId, typeName) {
  if (!projectId || !typeName) return;

  const project = projects.value.find((p) => p.id === projectId);
  if (!project || sameProjectType(project.type, typeName)) return;

  const previousType = project.type;
  project.type = typeName;
  markKanbanJustMoved(projectId);
  const busy = new Set(kanbanStatusUpdating.value);
  busy.add(projectId);
  kanbanStatusUpdating.value = busy;

  try {
    const { data } = await window.axios.put(`/api/project/${projectId}`, { type: typeName });
    const index = projects.value.findIndex((p) => p.id === projectId);
    if (index !== -1) projects.value.splice(index, 1, data.project);
    if (selected.value?.id === projectId) selected.value = data.project;
    showClientToast('success', `Đã chuyển "${project.name}" sang ${typeName}.`);
  } catch (err) {
    project.type = previousType;
    showClientToast('error', err?.response?.data?.message || 'Không đổi được loại dự án.');
  } finally {
    const next = new Set(kanbanStatusUpdating.value);
    next.delete(projectId);
    kanbanStatusUpdating.value = next;
  }
}

async function commitKanbanDrop(projectId, targetKey) {
  if (kanbanGroupBy.value === 'status') {
    await moveProjectToStatus(projectId, targetKey);
    return;
  }
  if (kanbanGroupBy.value === 'progress' || kanbanGroupBy.value === 'other_types') {
    await moveProjectToType(projectId, targetKey);
  }
}

const kanbanGhostStyle = computed(() => {
  const rotate = kanbanDrag.settling ? 0 : 3.5;
  const scale = kanbanDrag.settling ? 1 : 1.04;
  return {
    width: `${kanbanDrag.width}px`,
    height: `${kanbanDrag.height}px`,
    transform: `translate3d(${Math.round(kanbanDrag.x)}px, ${Math.round(kanbanDrag.y)}px, 0) rotate(${rotate}deg) scale(${scale})`,
  };
});

const kanbanGhostTone = computed(() => {
  if (kanbanGroupBy.value === 'status') return statusTone(kanbanDrag.fromKey);
  const col = kanbanColumns.value.find((c) => c.dropKey === kanbanDrag.fromKey);
  return col?.tone || typeTone(kanbanDrag.fromKey) || 'primary';
});

const tableWrap = ref(null);
const kanbanWrap = ref(null);
const resizing = ref(false);
useDragScroll(tableWrap, { isBlocked: () => resizing.value, axis: 'x' });
useDragScroll(kanbanWrap, { axis: 'x', isBlocked: () => kanbanDrag.active });
const columnWidths = reactive(loadColumnWidths());
const tableZoom = ref(loadZoom());

const columnOrder = ref(loadColumnOrder());
const visibleColumnKeys = ref(loadVisibleColumnKeys());

const COLUMNS = computed(() => {
  const byKey = new Map(ALL_COLUMNS.map((c) => [c.key, c]));
  return columnOrder.value.map((key) => byKey.get(key)).filter(Boolean);
});

const visibleColumns = computed(() => COLUMNS.value.filter((c) => c.always || visibleColumnKeys.value.includes(c.key)));

// ---------- Gắn nhãn ngay tại dòng (dialog Teleport ra body) ----------
const labelPickerProjectId = ref(null);

const labelPickerProject = computed(() => {
  if (!labelPickerProjectId.value) return null;
  return projects.value.find((p) => p.id === labelPickerProjectId.value) || null;
});

// ---------- Xem đầy đủ người tham gia (dialog Teleport ra body) ----------
const memberPickerProjectId = ref(null);

const memberPickerProject = computed(() => {
  if (!memberPickerProjectId.value) return null;
  return projects.value.find((p) => p.id === memberPickerProjectId.value) || null;
});

function openMemberList(project) {
  memberPickerProjectId.value = memberPickerProjectId.value === project.id ? null : project.id;
}

function closeMemberList() {
  memberPickerProjectId.value = null;
}

// ---------- Menu chuột phải trên dòng (chế độ danh sách) ----------
const ctxMenu = reactive({ open: false, x: 0, y: 0, project: null });
const actionDialog = reactive({ kind: null, project: null, extra: {} });

function openRowContextMenu(event, project) {
  if (actionDialog.kind) return;
  event.preventDefault();
  event.stopPropagation();
  ctxMenu.open = true;
  ctxMenu.x = event.clientX;
  ctxMenu.y = event.clientY;
  ctxMenu.project = project;
}

function closeRowContextMenu() {
  ctxMenu.open = false;
}

function applyProject(updated) {
  if (!updated?.id) return;
  const index = projects.value.findIndex((p) => p.id === updated.id);
  if (index !== -1) projects.value.splice(index, 1, updated);
  if (selected.value?.id === updated.id) selected.value = updated;
  if (ctxMenu.project?.id === updated.id) ctxMenu.project = updated;
  if (actionDialog.project?.id === updated.id) actionDialog.project = updated;
}

function onRowContextAction({ type, project, status, variant, focus }) {
  closeRowContextMenu();
  if (!project || type === 'signature') return;
  if (type === 'status') {
    moveProjectToStatus(project.id, status);
    return;
  }
  if (type === 'edit') {
    openEditPage(project);
    return;
  }
  if (type === 'details') {
    inspect(project);
    return;
  }
  if (type === 'labels') {
    openLabelPicker(project);
    return;
  }
  actionDialog.kind = type;
  actionDialog.project = project;
  actionDialog.extra = { variant, focus };
}

function closeActionDialog() {
  actionDialog.kind = null;
  actionDialog.project = null;
  actionDialog.extra = {};
}

async function onProjectDuplicated() {
  closeActionDialog();
  await loadProjects(1);
}

// ---------- Group theo phòng ban thực hiện ----------
const collapsedGroups = ref(new Set(loadCollapsedGroups()));

const avatarInput = ref(null);
const galleryInput = ref(null);
const driveUrlInput = ref('');
const attachmentInput = ref(null);
const avatarUploading = ref(false);
const attachmentUploading = ref(false);

const canManageSettings = computed(() => auth.can('project.manage_settings'));
const canCreate = ref(false);

const exporting = ref(false);
const importDialogOpen = ref(false);
const importStep = ref('select');
const importFile = ref(null);
const importPreview = ref(null);
const importSelectedRows = ref(new Set());
const importResult = ref(null);
const previewing = ref(false);
const confirming = ref(false);
const editingRowKey = ref(null); // `${row}` của dòng preview đang sửa tại chỗ
const editingRowDraft = reactive({});
const resolvingRow = ref(false);

// ---------- Modal xuất Excel: chọn cột ----------
// key frontend (cột Excel) ↔ nhãn — khớp ProjectExcelExporter::COLUMNS (thứ tự
// không bắt buộc trùng, backend tự sắp theo COLUMNS; 'code' luôn xuất, không cho bỏ).
const EXPORT_COLUMNS = [
  { key: 'code', label: 'Mã dự án', always: true },
  { key: 'name', label: 'Tên dự án' },
  { key: 'type_label', label: 'Loại dự án' },
  { key: 'owner_department_name', label: 'Phòng ban sở hữu' },
  { key: 'executing_department_name', label: 'Phòng ban thực hiện' },
  { key: 'lead_email', label: 'Phụ trách chính (email)' },
  { key: 'member_emails', label: 'Người tham gia (email)' },
  { key: 'follower_emails', label: 'Người theo dõi (email)' },
  { key: 'label_names', label: 'Nhãn' },
  { key: 'status_label', label: 'Trạng thái' },
  { key: 'importance_label', label: 'Mức độ quan trọng' },
  { key: 'start_date', label: 'Ngày bắt đầu' },
  { key: 'end_date', label: 'Ngày kết thúc' },
  { key: 'progress_method_label', label: 'Cách tính tiến độ' },
  { key: 'progress', label: 'Tiến độ' },
  { key: 'evaluation_score', label: 'Điểm đánh giá' },
  { key: 'description', label: 'Mô tả' },
  { key: 'creator_name', label: 'Người tạo' },
  { key: 'created_at', label: 'Ngày tạo' },
];
const exportDialogOpen = ref(false);
const exportSelectedColumns = ref(new Set(EXPORT_COLUMNS.map((c) => c.key)));

// Chỉ người xem được nhiều phòng ban (toàn cục) mới cần nhóm hàng ngang theo
// Phòng ban thực hiện — người chỉ thấy dự án của phòng ban mình thì mọi dòng
// đều cùng 1 nhóm, nhóm lại chỉ gây rối chứ không giúp gì (xem yêu cầu người
// dùng: "phòng ban nào chỉ thấy của phòng ban đó thì không group hàng ngang").
const canViewAcrossDepartments = computed(() => auth.isSuperAdmin || auth.can('project.*'));

const colSpan = computed(() => Math.max(visibleColumns.value.length, 1));

const tableWidthPx = computed(() => {
  const sum = visibleColumns.value.reduce((total, col) => total + (Number(columnWidths[col.key]) || 0), 0);
  return sum > 0 ? `${sum}px` : '100%';
});

const groupedProjects = computed(() => {
  if (!canViewAcrossDepartments.value) {
    return [{ key: UNGROUPED_KEY, label: '', projects: projects.value }];
  }

  const groups = new Map();
  for (const project of projects.value) {
    const dept = executingDepartments(project)[0] || project.owner_department;
    const key = dept ? `dept-${dept.id}` : UNGROUPED_KEY;
    const label = dept ? dept.name : 'Chưa xác định phòng ban';
    if (!groups.has(key)) {
      groups.set(key, { key, label, tone: departmentTone(key), projects: [] });
    }
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
  nextTick(fitColumnsToContent);
}

function loadZoom() {
  try {
    const raw = Number(localStorage.getItem(ZOOM_KEY));
    if (raw === 0.9 || raw === 1 || raw === 1.15) return raw;
  } catch {
    // Bỏ qua nếu trình duyệt chặn localStorage.
  }
  return 1;
}

function loadColumnWidths() {
  try {
    const raw = localStorage.getItem(COL_WIDTH_KEY);
    const parsed = raw ? JSON.parse(raw) : {};
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) return parsed;
  } catch {
    // Bỏ qua nếu trình duyệt chặn localStorage.
  }
  return {};
}

function saveColumnWidths() {
  try {
    localStorage.setItem(COL_WIDTH_KEY, JSON.stringify(columnWidths));
  } catch {
    // Bỏ qua.
  }
}

function colWidthStyle(key) {
  const width = columnWidths[key];
  return width ? `${width}px` : undefined;
}

function cellText(project, key) {
  if (key === 'code') return project.code || '—';
  if (key === 'name') return project.name || '—';
  if (key === 'type') return typeLabel(project.type);
  if (key === 'owner_department') return project.owner_department?.name || '—';
  if (key === 'executing_department') {
    const depts = executingDepartments(project);
    return depts.length ? depts.map((d) => d.name).reduce((a, b) => (a.length > b.length ? a : b)) : '—';
  }
  if (key === 'lead') return project.lead ? '' : '—';
  if (key === 'member_count') return String((project.members || []).length);
  if (key === 'status') return statusLabel(project.status);
  if (key === 'importance') return importanceLabel(project.importance);
  if (key === 'start_date') return formatDate(project.start_date);
  if (key === 'end_date') return formatDate(project.end_date);
  if (key === 'duration_days') return project.duration_days ? `${project.duration_days} ngày` : '—';
  if (key === 'progress_method') return progressMethodLabel(project.progress_method);
  if (key === 'progress') return project.progress_percent != null ? `${project.progress_percent}%` : '—';
  if (key === 'evaluation_score') return project.evaluation_score != null ? String(project.evaluation_score) : '—';
  if (key === 'description') return project.description || '—';
  if (key === 'creator') return project.creator ? '' : '—';
  if (key === 'created_at') return formatDate(project.created_at);
  if (key === 'is_following') return project.is_following ? 'Có' : '—';
  return '—';
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
  const table = tableWrap.value?.querySelector('.proj-page__table');
  return {
    header: fontOf(table?.querySelector('thead th'), '600 12px "Be Vietnam Pro", sans-serif'),
    cell: fontOf(table?.querySelector('tbody td'), '400 14px "Be Vietnam Pro", sans-serif'),
    nameTitle: fontOf(table?.querySelector('.proj-page__name-title'), '600 14px "Be Vietnam Pro", sans-serif'),
    chip: fontOf(table?.querySelector('.proj-label-picker__chip--sm'), '600 11px "Be Vietnam Pro", sans-serif'),
  };
}

function columnContentWidth(key, fonts) {
  const label = ALL_COLUMNS.find((col) => col.key === key)?.label ?? '';
  const valueFont = key === 'name' ? fonts.nameTitle : fonts.cell;
  let maxW = measureText(label, fonts.header);
  for (const project of projects.value) {
    maxW = Math.max(maxW, measureText(cellText(project, key), valueFont));
    if (key === 'name') {
      for (const item of project.labels || []) {
        maxW = Math.max(maxW, measureText(item.name, fonts.chip) + LABEL_CHIP_EXTRA + LABEL_ADD_EXTRA);
      }
    }
  }
  // Mô tả có thể rất dài — không đo full 1 dòng (cột sẽ khổng lồ), chặn ở
  // 1 mức khởi tạo hợp lý rồi để chữ tự xuống dòng (proj-page__td--wrap).
  // Người dùng vẫn kéo nắm mép để mở rộng thêm nếu muốn.
  if (key === 'description') maxW = Math.min(maxW, DESC_INITIAL_PX);
  let extra = 0;
  if (key === 'lead' || key === 'creator') extra = LEAD_AVATAR_EXTRA;
  if (key === 'member_count') extra = MEMBER_AVATAR_EXTRA;
  if (key === 'status' || key === 'importance') extra = STATUS_DOT_EXTRA;
  if (key === 'progress') extra = 56;
  if (key === 'name') extra = Math.max(extra, LABEL_ADD_EXTRA);
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
  const keys = visibleColumns.value.map((col) => col.key);
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
    ...(filterLabelIds.value.length ? { label_ids: filterLabelIds.value } : {}),
  };
}

const hasActiveFilters = computed(
  () => Boolean(query.value.trim()) || filterLabelIds.value.length > 0,
);

const labelFilterButtonLabel = computed(() =>
  filterLabelIds.value.length ? `Nhãn (${filterLabelIds.value.length})` : 'Nhãn',
);

const labelFilterMatches = computed(() => {
  const q = labelFilterQuery.value.trim().toLowerCase();
  if (!q) return allLabels.value;
  return allLabels.value.filter((label) => label.name.toLowerCase().includes(q));
});

function toggleFilterLabel(id) {
  const nid = Number(id);
  filterLabelIds.value = filterLabelIds.value.includes(nid)
    ? filterLabelIds.value.filter((item) => item !== nid)
    : [...filterLabelIds.value, nid];
  loadProjects(1);
}

function clearFilters() {
  query.value = '';
  filterLabelIds.value = [];
  loadProjects(1);
}

async function downloadFile(url, params, busyRef, fallbackFilename, successMsg, failMsg) {
  busyRef.value = true;
  try {
    const response = await window.axios.get(url, {
      params,
      paramsSerializer: { indexes: null },
      responseType: 'blob',
    });
    const blob = response.data;
    if (blob.type && blob.type.includes('json')) {
      const json = JSON.parse(await blob.text());
      throw new Error(json.message || failMsg);
    }

    const disposition = response.headers['content-disposition'] || '';
    const utfMatch = disposition.match(/filename\*=UTF-8''([^;]+)/i);
    const plainMatch = disposition.match(/filename="?([^"]+)"?/i);
    const filename = decodeURIComponent(utfMatch?.[1] || plainMatch?.[1] || fallbackFilename);

    const objectUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = objectUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(objectUrl);
    showClientToast('success', successMsg);
  } catch (err) {
    let message = err?.message;
    if (err?.response?.data instanceof Blob) {
      try {
        const json = JSON.parse(await err.response.data.text());
        message = json.message || Object.values(json.errors || {})[0]?.[0];
      } catch {
        message = failMsg;
      }
    } else {
      message = err?.response?.data?.message || message;
    }
    showClientToast('error', message || failMsg);
  } finally {
    busyRef.value = false;
  }
}

async function exportExcel(columnKeys = null) {
  await downloadFile(
    '/api/project/export',
    { ...currentFilterParams(), ...(columnKeys ? { columns: columnKeys } : {}) },
    exporting,
    'Du_an.xlsx',
    'Đã tải file Excel.',
    'Không xuất được file Excel.',
  );
}

// ---------- Modal xuất Excel: chọn cột ----------
function openExportDialog() {
  exportSelectedColumns.value = new Set(EXPORT_COLUMNS.map((c) => c.key));
  exportDialogOpen.value = true;
}

function closeExportDialog() {
  if (exporting.value) return;
  exportDialogOpen.value = false;
}

function toggleExportColumn(key) {
  const col = EXPORT_COLUMNS.find((c) => c.key === key);
  if (col?.always) return;
  const next = new Set(exportSelectedColumns.value);
  if (next.has(key)) next.delete(key);
  else next.add(key);
  exportSelectedColumns.value = next;
}

function selectAllExportColumns() {
  exportSelectedColumns.value = new Set(EXPORT_COLUMNS.map((c) => c.key));
}

function deselectAllExportColumns() {
  exportSelectedColumns.value = new Set(EXPORT_COLUMNS.filter((c) => c.always).map((c) => c.key));
}

async function submitExportDialog() {
  await exportExcel(Array.from(exportSelectedColumns.value));
  if (!exporting.value) exportDialogOpen.value = false;
}

function openImportDialog() {
  importFile.value = null;
  importPreview.value = null;
  importResult.value = null;
  importSelectedRows.value = new Set();
  importStep.value = 'select';
  editingRowKey.value = null;
  importDialogOpen.value = true;
}

function closeImportDialog() {
  if (previewing.value || confirming.value) return;
  importDialogOpen.value = false;
}

function onImportFileChange(event) {
  importFile.value = event.target.files?.[0] ?? null;
}

function backToSelect() {
  importStep.value = 'select';
  importPreview.value = null;
  editingRowKey.value = null;
}

function toggleRowSelected(rowNum) {
  const next = new Set(importSelectedRows.value);
  if (next.has(rowNum)) next.delete(rowNum);
  else next.add(rowNum);
  importSelectedRows.value = next;
}

async function runPreview() {
  if (!importFile.value) {
    showClientToast('error', 'Vui lòng chọn file Excel cần nhập.');
    return;
  }
  previewing.value = true;
  try {
    const formData = new FormData();
    formData.append('file', importFile.value);
    const { data } = await window.axios.post('/api/project/import/preview', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    importPreview.value = data;
    importSelectedRows.value = new Set((data.rows ?? []).filter((r) => r.status === 'valid').map((r) => r.row));
    importStep.value = 'preview';
  } catch (err) {
    if (err?.response?.status === 422) {
      showClientToast('error', err.response.data?.message || 'File không hợp lệ.');
    } else {
      showClientToast('error', err?.response?.data?.message || 'Không đọc được file Excel.');
    }
  } finally {
    previewing.value = false;
  }
}

// ---------- Sửa lỗi tại chỗ trong bảng xem trước ----------
function rowDraftFromData(row) {
  const d = row.data ?? {};
  return {
    code: row.code || '',
    name: d.name || '',
    type_input: typeLabel(d.type) === '—' ? '' : typeLabel(d.type),
    exec_dept_name: d.executing_department_name || '',
    lead_input: d.lead_name ? `${d.lead_name}` : '',
    members_input: (d.member_names || []).join('; '),
    followers_input: (d.follower_names || []).join('; '),
    labels_input: (d.label_names || []).join('; '),
    status_input: statusLabel(d.status) === '—' ? '' : statusLabel(d.status),
    importance_input: importanceLabel(d.importance) === '—' ? '' : importanceLabel(d.importance),
    start_input: d.start_date ? formatDate(d.start_date) : '',
    end_input: d.end_date ? formatDate(d.end_date) : '',
    progress_method_input: progressMethodLabel(d.progress_method) === '—' ? '' : progressMethodLabel(d.progress_method),
    description: d.description || '',
  };
}

function startEditRow(row) {
  editingRowKey.value = row.row;
  Object.assign(editingRowDraft, rowDraftFromData(row));
}

function cancelEditRow() {
  editingRowKey.value = null;
}

async function saveEditRow(row) {
  resolvingRow.value = true;
  try {
    const { data } = await window.axios.post('/api/project/import/resolve-row', { ...editingRowDraft });
    const rows = importPreview.value?.rows ?? [];
    const idx = rows.findIndex((r) => r.row === row.row);
    if (idx !== -1) {
      rows[idx] = { ...data, row: row.row };
      if (data.status === 'valid') {
        importSelectedRows.value = new Set([...importSelectedRows.value, row.row]);
      } else {
        const next = new Set(importSelectedRows.value);
        next.delete(row.row);
        importSelectedRows.value = next;
      }
    }
    editingRowKey.value = null;
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không sửa được dòng này.');
  } finally {
    resolvingRow.value = false;
  }
}

async function confirmImportRows() {
  const rows = (importPreview.value?.rows ?? [])
    .filter((r) => r.status === 'valid' && importSelectedRows.value.has(r.row))
    .map((r) => ({ ...r.data, action: r.action, project_id: r.project_id, provided_fields: r.provided_fields, row: r.row }));
  if (rows.length === 0) {
    showClientToast('error', 'Chưa chọn dòng hợp lệ nào để nhập.');
    return;
  }
  confirming.value = true;
  try {
    const { data } = await window.axios.post('/api/project/import/confirm', { rows });
    importResult.value = data;
    importStep.value = 'result';
    if (data.created?.length || data.updated?.length) {
      await loadProjects(1);
    }
    const createdCount = data.created?.length ?? 0;
    const updatedCount = data.updated?.length ?? 0;
    const errorCount = data.errors?.length ?? 0;
    if ((createdCount || updatedCount) && !errorCount) {
      showClientToast('success', `Đã tạo ${createdCount} dự án, cập nhật ${updatedCount} dự án.`);
    } else if (createdCount || updatedCount) {
      showClientToast('warning', `Đã tạo ${createdCount} dự án, cập nhật ${updatedCount} dự án, còn ${errorCount} dòng lỗi.`);
    } else {
      showClientToast('error', 'Không nhập được dự án nào, xem chi tiết lỗi bên dưới.');
    }
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không nhập được dự án.');
  } finally {
    confirming.value = false;
  }
}

const exportOptions = computed(() => {
  const options = [
    {
      key: 'excel',
      label: 'Xuất Excel',
      description: 'Chọn cột và xuất theo bộ lọc hiện tại.',
      onSelect: openExportDialog,
    },
  ];
  if (canCreate.value) {
    options.push({
      key: 'import',
      label: 'Nhập Excel',
      description: 'Tải file lên, xem trước rồi mới xác nhận nhập',
      separatorBefore: true,
      onSelect: openImportDialog,
    });
  }
  return options;
});

const exportBusyKey = computed(() => {
  if (exporting.value) return 'excel';
  if (previewing.value || confirming.value) return 'import';
  return undefined;
});

async function loadOptions() {
  try {
    const { data } = await window.axios.get('/api/project/options');
    options.type = data.type ?? [];
    options.status = data.status ?? [];
    options.importance = data.importance ?? [];
    options.progress_method = data.progress_method ?? [];
    options.scope_type = data.scope_type ?? [];
    canCreate.value = Boolean(data.can_create);
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
      params: { ...currentFilterParams(), page, per_page: isKanban.value ? KANBAN_PER_PAGE : perPage.value },
      paramsSerializer: { indexes: null },
    });
    projects.value = data.projects ?? [];
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 20 };
    tabCounts.value = data.tab_counts ?? {};

    if (selected.value) {
      const fresh = projects.value.find((p) => p.id === selected.value.id);
      selected.value = fresh || null;
    }
    nextTick(fitColumnsToContent);
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

function departmentTone(key) {
  if (!key || key === UNGROUPED_KEY) return 'neutral';
  let hash = 0;
  const text = String(key);
  for (let i = 0; i < text.length; i += 1) {
    hash = (hash * 31 + text.charCodeAt(i)) | 0;
  }
  return THEME_TONES[Math.abs(hash) % THEME_TONES.length];
}

function deptToneFromId(id) {
  return id ? departmentTone(`dept-${id}`) : 'neutral';
}

// Phòng ban thực hiện đầy đủ của 1 dự án — gộp executing_department (phòng
// được giao chính) với mọi department khai báo trong scopes (phạm vi kiểu
// "Phòng Ban", trường hợp nhiều phòng ban cùng làm chung 1 dự án), khử
// trùng lặp theo id. Không có cả 2 thì coi như chưa giao (mảng rỗng).
function executingDepartments(project) {
  const list = [];
  const seen = new Set();
  const add = (dept) => {
    if (!dept || seen.has(dept.id)) return;
    seen.add(dept.id);
    list.push(dept);
  };

  for (const dept of project.executing_departments || []) add(dept);
  add(project.executing_department);
  for (const scope of project.scopes || []) {
    if (scope.scope_type === 'department') add(scope.department);
  }

  return list;
}

function typeTone(value) {
  const key = String(value || '').trim().toLocaleLowerCase('vi');
  if (key === 'internal' || key === 'nội bộ') return 'tertiary';
  if (key === 'customer' || key === 'khách hàng') return 'secondary';
  if (key === 'infrastructure' || key === 'hạ tầng') return 'gold';
  if (key === 'research' || key === 'nghiên cứu') return 'info';
  if (key === 'other' || key === 'khác') return 'violet';
  if (key === 'nghiên cứu phát triển') return 'info';
  if (key === 'vận hành cải tiến') return 'gold';
  if (key === 'triển khai nghiệm thu') return 'success';
  return key ? departmentTone(`type-${key}`) : 'neutral';
}

function statusTone(value) {
  if (value === 'completed') return 'success';
  if (value === 'cancelled') return 'umber';
  if (value === 'on_hold') return 'gold';
  if (value === 'in_progress') return 'primary';
  if (value === 'planning') return 'tertiary';
  return 'neutral';
}

function importanceTone(value) {
  if (value === 'strategic' || value === 'critical') return 'danger';
  if (value === 'high_priority' || value === 'high') return 'gold';
  if (value === 'important' || value === 'medium') return 'tertiary';
  if (value === 'assist') return 'info';
  return 'neutral';
}

function progressTone(percent) {
  if (percent == null) return 'neutral';
  if (percent >= 80) return 'success';
  if (percent >= 50) return 'tertiary';
  if (percent >= 25) return 'gold';
  return 'warning';
}

function statusDotClass(value) {
  return `proj-page__dot--${statusTone(value)}`;
}

function importanceDotClass(value) {
  return `proj-page__dot--${importanceTone(value)}`;
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
  if (labelPickerProjectId.value === project.id) {
    closeLabelPicker();
    return;
  }
  labelPickerProjectId.value = project.id;
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
  if (actionDialog.kind) return;
  if (ctxMenu.open) {
    closeRowContextMenu();
    return;
  }
  if (kanbanDrag.active) {
    clearKanbanDrag();
    return;
  }
  if (importDialogOpen.value) {
    closeImportDialog();
    return;
  }
  if (labelPickerProjectId.value) closeLabelPicker();
  else if (memberPickerProjectId.value) closeMemberList();
  else if (viewModeOpen.value) closeViewModeMenu();
  else if (kanbanMemberPickerOpen.value) closeKanbanMemberPicker();
  else if (selected.value) closePanel();
}

function handleDocumentClickForPickers(event) {
  if (kanbanMemberPickerOpen.value) {
    const el = document.getElementById('kanban-member-picker-root');
    if (el && !el.contains(event.target)) closeKanbanMemberPicker();
  }
  if (viewModeOpen.value) {
    const el = document.getElementById('proj-view-mode-root');
    if (el && !el.contains(event.target)) closeViewModeMenu();
  }
}

watch(perPage, () => loadProjects(1));
watch(selected, () => nextTick(fitColumnsToContent));
watch(visibleColumns, () => nextTick(fitColumnsToContent));
watch(columnWidths, saveColumnWidths, { deep: true });
watch(tableZoom, (value) => {
  try {
    localStorage.setItem(ZOOM_KEY, String(value));
  } catch {
    // Bỏ qua.
  }
  nextTick(fitColumnsToContent);
});

onMounted(() => {
  document.addEventListener('keydown', handleDocumentKeydown);
  document.addEventListener('click', handleDocumentClickForPickers);
  const tabFromQuery = typeof route.query.tab === 'string' ? route.query.tab : '';
  if (TABS.some((t) => t.key === tabFromQuery)) {
    activeTab.value = tabFromQuery;
  }
  loadOptions();
  loadDepartments();
  loadLabels();
  loadProjects(1);
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
  document.removeEventListener('click', handleDocumentClickForPickers);
  wrapObserver?.disconnect();
  window.clearTimeout(kanbanJustMovedTimer);
  clearKanbanDrag();
});
</script>

<template>
  <section class="proj-page">
    <PageHeader
      title="Dự án"
      icon="layers"
      description="Quản lý danh sách dự án của tổ chức."
      :primary-action="{ label: 'Thêm dự án', icon: 'plus', to: { name: 'manager.project.create' } }"
      export-label="Dữ liệu"
      :export-options="exportOptions"
      :export-busy-key="exportBusyKey"
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
        <div class="proj-tabs-row">
          <div id="proj-view-mode-root" class="proj-view-mode">
            <button
              type="button"
              class="proj-view-mode__trigger"
              aria-haspopup="menu"
              :aria-expanded="viewModeOpen"
              aria-label="Chế độ xem"
              @click.stop="toggleViewModeMenu"
            >
              <AppIcon :name="isKanban ? 'layoutGrid' : 'layoutList'" :size="15" />
              <span>{{ viewModeTriggerLabel }}</span>
              <AppIcon name="chevronDown" :size="14" />
            </button>
            <div v-if="viewModeOpen" class="proj-view-mode__menu" role="menu" @click.stop>
              <button
                type="button"
                class="proj-view-mode__item"
                :class="{ 'proj-view-mode__item--on': !isKanban }"
                role="menuitem"
                @click="setViewMode('list')"
              >
                <AppIcon name="layoutList" :size="15" />
                <span>Danh sách</span>
                <AppIcon v-if="!isKanban" name="check" :size="14" />
              </button>
              <p class="proj-view-mode__group">Kanban</p>
              <button
                type="button"
                class="proj-view-mode__item"
                :class="{ 'proj-view-mode__item--on': isKanban && kanbanGroupBy === 'status' }"
                role="menuitem"
                @click="chooseKanbanGroup('status')"
              >
                <AppIcon name="layoutGrid" :size="15" />
                <span>Theo trạng thái</span>
                <AppIcon v-if="isKanban && kanbanGroupBy === 'status'" name="check" :size="14" />
              </button>
              <button
                type="button"
                class="proj-view-mode__item"
                :class="{ 'proj-view-mode__item--on': isKanban && kanbanGroupBy === 'members' }"
                role="menuitem"
                @click="chooseKanbanGroup('members')"
              >
                <AppIcon name="users" :size="15" />
                <span>Theo người tham gia</span>
                <AppIcon v-if="isKanban && kanbanGroupBy === 'members'" name="check" :size="14" />
              </button>
              <button
                type="button"
                class="proj-view-mode__item"
                :class="{ 'proj-view-mode__item--on': isKanban && kanbanGroupBy === 'progress' }"
                role="menuitem"
                @click="chooseKanbanGroup('progress')"
              >
                <AppIcon name="gitBranch" :size="15" />
                <span>Theo tiến trình</span>
                <AppIcon v-if="isKanban && kanbanGroupBy === 'progress'" name="check" :size="14" />
              </button>
              <button
                type="button"
                class="proj-view-mode__item"
                :class="{ 'proj-view-mode__item--on': isKanban && kanbanGroupBy === 'other_types' }"
                role="menuitem"
                @click="chooseKanbanGroup('other_types')"
              >
                <AppIcon name="layers" :size="15" />
                <span>Theo loại tiêu chí</span>
                <AppIcon v-if="isKanban && kanbanGroupBy === 'other_types'" name="check" :size="14" />
              </button>
              <button
                type="button"
                class="proj-view-mode__item"
                :class="{ 'proj-view-mode__item--on': isKanban && kanbanGroupBy === 'department' }"
                role="menuitem"
                @click="chooseKanbanGroup('department')"
              >
                <AppIcon name="building" :size="15" />
                <span>Theo phòng ban</span>
                <AppIcon v-if="isKanban && kanbanGroupBy === 'department'" name="check" :size="14" />
              </button>
            </div>
          </div>

          <nav class="proj-tabs hide-scrollbar" aria-label="Lọc nhanh dự án">
            <button
              v-for="tab in TABS"
              :key="tab.key"
              type="button"
              class="proj-tabs__item"
              :class="[`proj-tabs__item--${tab.tone}`, { 'proj-tabs__item--active': activeTab === tab.key }]"
              @click="selectTab(tab.key)"
            >
              <span class="proj-tabs__label">{{ tab.label }}</span>
              <span class="proj-tabs__count">{{ tabCount(tab.key) }}</span>
            </button>
          </nav>
        </div>

        <div v-if="isKanban && kanbanGroupBy === 'members'" class="proj-view-bar">
          <div id="kanban-member-picker-root" class="proj-kanban-members">
            <button
              type="button"
              class="proj-kanban-members__trigger"
              :aria-expanded="kanbanMemberPickerOpen"
              @click.stop="toggleKanbanMemberPicker"
            >
              <AppIcon name="userPlus" :size="15" />
              Chọn người tham gia
              <span v-if="kanbanSelectedUsers.length" class="proj-kanban-members__count">{{ kanbanSelectedUsers.length }}</span>
            </button>

            <div v-if="kanbanMemberPickerOpen" class="proj-kanban-members__dropdown hide-scrollbar" @click.stop>
              <label v-for="user in allAssignableUsers" :key="user.id" class="proj-kanban-members__option">
                <input
                  type="checkbox"
                  :checked="kanbanMemberIds.includes(user.id)"
                  @change="toggleKanbanMember(user.id)"
                />
                <span>{{ user.name }}</span>
              </label>
              <p v-if="!allAssignableUsers.length" class="proj-kanban-members__empty">Chưa có người tham gia nào trong danh sách dự án.</p>
            </div>

            <span v-if="kanbanSelectedUsers.length" class="proj-kanban-members__chips">
              <span v-for="user in kanbanSelectedUsers" :key="user.id" class="proj-kanban-members__chip">
                {{ user.name }}
                <button type="button" aria-label="Bỏ chọn người này" @click="removeKanbanMember(user.id)">
                  <AppIcon name="close" :size="10" />
                </button>
              </span>
            </span>
          </div>
        </div>

        <TablePagesBar
          v-if="!isKanban"
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
          extra-menu-icon="bookmark"
          extra-menu-title="Nhãn"
          :extra-menu-label="labelFilterButtonLabel"
          :extra-menu-active="filterLabelIds.length > 0"
          @search="loadProjects(1)"
          @clear-filters="clearFilters"
          @update:page="goPage"
          @update:per-page="perPage = $event"
          @update:zoom="tableZoom = $event"
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
          <template #extra>
            <label class="proj-page__label-filter-search">
              <AppIcon name="search" :size="14" />
              <input v-model="labelFilterQuery" type="search" placeholder="Tìm nhãn…" />
            </label>
            <label
              v-for="label in labelFilterMatches"
              :key="label.id"
              class="proj-page__label-filter-option"
            >
              <input
                type="checkbox"
                :checked="filterLabelIds.includes(label.id)"
                @change="toggleFilterLabel(label.id)"
              />
              <span class="proj-label-picker__dot" :class="`proj-label-picker__dot--${label.color}`" />
              <span>{{ label.name }}</span>
            </label>
            <p v-if="!labelFilterMatches.length" class="proj-page__label-filter-empty">
              {{ allLabels.length ? 'Không tìm thấy nhãn khớp.' : 'Chưa có nhãn nào.' }}
            </p>
          </template>
        </TablePagesBar>

        <div
          v-if="!isKanban"
          ref="tableWrap"
          class="proj-page__table-wrap hide-scrollbar"
          :class="{ 'proj-page__table-wrap--resizing': resizing }"
          :style="{ '--table-zoom': tableZoom }"
        >
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
                <td :colspan="colSpan" class="proj-page__empty">
                  {{ hasActiveFilters ? 'Không tìm thấy dự án khớp bộ lọc.' : 'Chưa có dự án nào.' }}
                </td>
              </tr>
              <template v-for="group in groupedProjects" v-else :key="group.key">
                <tr
                  v-if="canViewAcrossDepartments"
                  class="proj-page__group-row"
                  :class="`proj-page__group-row--${group.tone}`"
                  @click="toggleGroup(group.key)"
                >
                  <td :colspan="colSpan">
                    <span class="proj-page__group-toggle">
                      <span class="proj-page__group-head">
                        <AppIcon
                          name="chevronRight"
                          :size="14"
                          class="proj-page__group-chevron"
                          :class="{ 'proj-page__group-chevron--open': !isGroupCollapsed(group.key) }"
                        />
                        <span class="proj-page__group-label">{{ group.label }}</span>
                      </span>
                      <span class="proj-page__group-count">{{ group.projects.length }} dự án</span>
                    </span>
                  </td>
                </tr>
                <tr
                  v-for="project in group.projects"
                  v-show="!canViewAcrossDepartments || !isGroupCollapsed(group.key)"
                  :key="project.id"
                  class="proj-page__data-row"
                  :class="[
                    canViewAcrossDepartments ? `proj-page__data-row--${group.tone}` : null,
                    {
                      'proj-page__row--active': selected?.id === project.id,
                      'proj-page__row--ctx': ctxMenu.open && ctxMenu.project?.id === project.id,
                    },
                  ]"
                  @dblclick="inspect(project)"
                  @contextmenu="openRowContextMenu($event, project)"
                >
                  <td
                    v-for="col in visibleColumns"
                    :key="col.key"
                    :class="{
                      'proj-page__td--name': col.key === 'name',
                      'proj-page__td--wrap': col.key === 'executing_department' || col.key === 'description',
                      'proj-page__td--avatar': col.key === 'lead' || col.key === 'member_count',
                    }"
                  >
                    <span v-if="col.key === 'code'" class="proj-page__pill proj-page__pill--code">{{ project.code }}</span>

                    <span v-else-if="col.key === 'name'" class="proj-page__name-cell">
                      <span class="proj-page__name-title">{{ project.name }}</span>
                      <span class="proj-page__name-labels">
                        <span
                          v-for="label in project.labels || []"
                          :key="label.id"
                          class="proj-label-picker__chip proj-label-picker__chip--sm"
                          :class="`proj-label-picker__chip--${label.color}`"
                          @click.stop="openLabelPicker(project)"
                        >
                          <span class="proj-label-picker__dot" :class="`proj-label-picker__dot--${label.color}`" />
                          <span>{{ label.name }}</span>
                        </span>
                        <button
                          type="button"
                          class="proj-page__label-add"
                          aria-label="Gắn nhãn cho dự án này"
                          @click.stop="openLabelPicker(project)"
                        >
                          <AppIcon name="plus" :size="11" />
                        </button>
                      </span>
                    </span>

                    <span v-else-if="col.key === 'type'" class="proj-page__pill" :class="`proj-page__pill--${typeTone(project.type)}`">
                      {{ typeLabel(project.type) }}
                    </span>
                    <span
                      v-else-if="col.key === 'owner_department'"
                      class="proj-page__pill"
                      :class="`proj-page__pill--${deptToneFromId(project.owner_department?.id)}`"
                    >
                      {{ project.owner_department?.name || '—' }}
                    </span>
                    <span v-else-if="col.key === 'executing_department'" class="proj-page__pill-wrap">
                      <template v-if="executingDepartments(project).length">
                        <span
                          v-for="dept in executingDepartments(project)"
                          :key="dept.id"
                          class="proj-page__pill"
                          :class="`proj-page__pill--${deptToneFromId(dept.id)}`"
                        >
                          {{ dept.name }}
                        </span>
                      </template>
                      <span v-else>—</span>
                    </span>

                    <span v-else-if="col.key === 'lead'">
                      <UserAvatarTip v-if="project.lead" :user="project.lead" label="Phụ trách chính" />
                      <span v-else>—</span>
                    </span>

                    <span v-else-if="col.key === 'member_count'" class="proj-page__members-cell">
                      <span v-if="(project.members || []).length" class="proj-page__avatar-stack">
                        <UserAvatarTip
                          v-for="member in project.members.slice(0, MEMBER_AVATARS_MAX)"
                          :key="member.id"
                          :user="member"
                          label="Người tham gia"
                        />
                        <button
                          v-if="project.members.length > MEMBER_AVATARS_MAX"
                          type="button"
                          class="proj-page__avatar-more"
                          :aria-label="`Xem đủ ${project.members.length} người tham gia`"
                          @click.stop="openMemberList(project)"
                        >
                          +{{ project.members.length - MEMBER_AVATARS_MAX }}
                        </button>
                      </span>
                      <span v-else>—</span>
                    </span>

                    <span v-else-if="col.key === 'status'" class="proj-page__pill" :class="`proj-page__pill--${statusTone(project.status)}`">
                      <span class="proj-page__dot" :class="statusDotClass(project.status)" />
                      {{ statusLabel(project.status) }}
                    </span>

                    <span v-else-if="col.key === 'importance'" class="proj-page__pill" :class="`proj-page__pill--${importanceTone(project.importance)}`">
                      <span class="proj-page__dot" :class="importanceDotClass(project.importance)" />
                      {{ importanceLabel(project.importance) }}
                    </span>

                    <span v-else-if="col.key === 'start_date'" class="proj-page__pill proj-page__pill--date">{{ formatDate(project.start_date) }}</span>
                    <span v-else-if="col.key === 'end_date'" class="proj-page__pill proj-page__pill--date">{{ formatDate(project.end_date) }}</span>
                    <span v-else-if="col.key === 'duration_days'" class="proj-page__pill proj-page__pill--neutral">
                      {{ project.duration_days ? `${project.duration_days} ngày` : '—' }}
                    </span>
                    <span v-else-if="col.key === 'progress_method'" class="proj-page__pill proj-page__pill--info">
                      {{ progressMethodLabel(project.progress_method) }}
                    </span>
                    <span v-else-if="col.key === 'progress'" class="proj-page__progress-cell">
                      <DualProgressBar
                        v-if="project.progress_percent != null"
                        :actual="project.progress_percent"
                        :expected="computeExpectedProgress(project.start_date, project.end_date)"
                        size="sm"
                      />
                      <span v-else>—</span>
                    </span>
                    <span
                      v-else-if="col.key === 'evaluation_score'"
                      class="proj-page__pill"
                      :class="project.evaluation_score != null ? 'proj-page__pill--tertiary' : 'proj-page__pill--neutral'"
                    >
                      {{ project.evaluation_score != null ? project.evaluation_score : '—' }}
                    </span>
                    <template v-else-if="col.key === 'description'">{{ project.description || '—' }}</template>
                    <span v-else-if="col.key === 'creator'">
                      <UserAvatarTip v-if="project.creator" :user="project.creator" label="Người tạo" />
                      <span v-else>—</span>
                    </span>
                    <span v-else-if="col.key === 'created_at'" class="proj-page__pill proj-page__pill--date">{{ formatDate(project.created_at) }}</span>
                    <span
                      v-else-if="col.key === 'is_following'"
                      class="proj-page__pill"
                      :class="project.is_following ? 'proj-page__pill--success' : 'proj-page__pill--neutral'"
                    >
                      {{ project.is_following ? 'Có' : '—' }}
                    </span>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <TablePagesBar
          v-if="!isKanban"
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

        <div
          v-if="isKanban"
          ref="kanbanWrap"
          class="proj-kanban hide-scrollbar"
          :class="{
            'proj-kanban--dragging': kanbanDrag.active,
            'proj-kanban--fill': kanbanStatusFill,
            'proj-kanban--spread': kanbanSpread,
          }"
        >
          <p v-if="kanbanGroupBy === 'members' && !kanbanSelectedUsers.length" class="proj-kanban__hint">
            Chọn ít nhất một người tham gia ở trên để xem theo cột.
          </p>
          <p v-else-if="kanbanStatusFill && !kanbanStatusColumns.length" class="proj-kanban__hint">
            Không có dự án nào.
          </p>
          <p v-else-if="kanbanGroupBy === 'other_types' && !kanbanColumns.length" class="proj-kanban__hint">
            Không có loại tiêu chí nào ngoài nhóm tiến trình.
          </p>
          <p v-else-if="!kanbanColumns.length" class="proj-kanban__hint">
            Không có dự án nào.
          </p>
          <div
            v-for="col in kanbanColumns"
            :key="col.key"
            class="proj-kanban__col"
            :class="[
              `proj-kanban__col--${col.tone || 'primary'}`,
              { 'proj-kanban__col--drop': isKanbanDragGroup && kanbanDrag.active && kanbanDrag.overKey === col.dropKey },
            ]"
            :data-drop-key="isKanbanDragGroup ? col.dropKey : undefined"
          >
            <header class="proj-kanban__col-head">
              <div class="proj-kanban__col-head-main">
                <UserAvatarTip v-if="col.user" :user="col.user" label="Người tham gia" />
                <span class="proj-kanban__col-title">{{ col.label }}</span>
              </div>
              <span class="proj-kanban__col-count">{{ col.projects.length }}</span>
            </header>

            <div class="proj-kanban__col-body hide-scrollbar">
              <div
                v-if="kanbanDropSlot(col)"
                class="proj-kanban__placeholder"
                :style="{ height: `${kanbanDrag.height}px` }"
              />

              <article
                v-for="project in col.projects"
                :key="project.id"
                class="proj-kanban__card"
                :class="{
                  'proj-kanban__card--movable': kanbanCardsMovable,
                  'proj-kanban__card--slot': kanbanDrag.active && kanbanDrag.projectId === project.id,
                  'proj-kanban__card--enter': kanbanJustMovedId === project.id,
                }"
                data-no-drag-scroll
                @pointerdown="onKanbanCardPointerDown($event, project)"
                @click="!isKanbanDragGroup && inspect(project)"
              >
                <header class="proj-kanban__card-head">
                  <span v-if="project.code" class="proj-kanban__card-code">{{ project.code }}</span>
                  <span v-if="project.type" class="proj-kanban__card-type">{{ typeLabel(project.type) }}</span>
                </header>

                <h3 class="proj-kanban__card-title">{{ project.name }}</h3>

                <div v-if="(project.labels || []).length || (project.importance && project.importance !== 'support' && project.importance !== 'low')" class="proj-kanban__card-labels">
                  <span
                    v-if="project.importance && project.importance !== 'support' && project.importance !== 'low'"
                    class="proj-kanban__importance"
                    :class="`proj-kanban__importance--${importanceTone(project.importance)}`"
                  >
                    {{ importanceLabel(project.importance) }}
                  </span>
                  <span
                    v-for="label in project.labels"
                    :key="label.id"
                    class="proj-label-picker__chip proj-label-picker__chip--sm"
                    :class="`proj-label-picker__chip--${label.color}`"
                  >
                    <span class="proj-label-picker__dot" :class="`proj-label-picker__dot--${label.color}`" />
                    <span>{{ label.name }}</span>
                  </span>
                </div>

                <p v-if="project.description" class="proj-kanban__card-desc">{{ project.description }}</p>

                <dl
                  v-if="kanbanCardDeptName(project) || project.lead?.name || projectDateRangeLabel(project)"
                  class="proj-kanban__card-facts"
                >
                  <div v-if="kanbanCardDeptName(project)" class="proj-kanban__card-fact">
                    <dt>Phòng ban</dt>
                    <dd>{{ kanbanCardDeptName(project) }}</dd>
                  </div>
                  <div v-if="project.lead?.name" class="proj-kanban__card-fact">
                    <dt>Phụ trách</dt>
                    <dd>{{ project.lead.name }}</dd>
                  </div>
                  <div v-if="projectDateRangeLabel(project)" class="proj-kanban__card-fact">
                    <dt>Thời hạn</dt>
                    <dd>{{ projectDateRangeLabel(project) }}</dd>
                  </div>
                </dl>

                <div v-if="kanbanCardImages(project).length" class="proj-kanban__card-thumbs">
                  <img
                    v-for="image in kanbanCardImages(project)"
                    :key="image.id"
                    :src="image.file_url"
                    alt=""
                    class="proj-kanban__card-thumb"
                  />
                </div>

                <div v-if="project.progress_percent != null" class="proj-kanban__card-progress">
                  <div class="proj-kanban__card-progress-head">
                    <span class="proj-kanban__card-progress-label">Tiến độ</span>
                    <span class="proj-kanban__card-progress-value">{{ project.progress_percent }}%</span>
                  </div>
                  <span class="proj-page__mini-track">
                    <span
                      class="proj-page__mini-fill"
                      :class="`proj-page__mini-fill--${progressTone(project.progress_percent)}`"
                      :style="{ width: `${project.progress_percent}%` }"
                    />
                  </span>
                </div>

                <footer class="proj-kanban__card-foot">
                  <span class="proj-kanban__card-avatars proj-page__avatar-stack">
                    <UserAvatarTip
                      v-for="person in kanbanCardPeople(project).slice(0, MEMBER_AVATARS_MAX)"
                      :key="person.id"
                      :user="person"
                      label="Người tham gia"
                    />
                    <span
                      v-if="kanbanCardPeople(project).length > MEMBER_AVATARS_MAX"
                      class="proj-page__avatar-more"
                      role="button"
                      tabindex="0"
                      aria-label="Xem người tham gia"
                      @pointerdown.stop
                      @click.stop="openMemberList(project)"
                    >
                      +{{ kanbanCardPeople(project).length - MEMBER_AVATARS_MAX }}
                    </span>
                  </span>
                  <span class="proj-kanban__card-meta">
                    <span v-if="(project.attachments || []).length" class="proj-kanban__card-stat">
                      <AppIcon name="paperclip" :size="12" />
                      {{ project.attachments.length }}
                    </span>
                    <span class="proj-kanban__card-stat">
                      <AppIcon name="user" :size="12" />
                      {{ kanbanCardPeople(project).length }}
                    </span>
                  </span>
                </footer>

                <span v-if="kanbanCardsMovable" class="proj-kanban__card-grip" aria-hidden="true">
                  <AppIcon name="gripVertical" :size="14" />
                </span>

                <div v-if="kanbanStatusUpdating.has(project.id)" class="proj-kanban__card-busy">
                  <AppIcon name="refresh" :size="14" class="proj-page__spin" />
                </div>
              </article>

              <p v-if="!col.projects.length && !kanbanDropSlot(col)" class="proj-kanban__col-empty">Không có dự án nào.</p>
            </div>
          </div>
        </div>
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
          <div v-if="executingDepartments(selected).length" class="proj-page__row">
            <span class="proj-page__row-label">Phòng ban thực hiện</span>
            <span class="proj-page__row-value">{{ executingDepartments(selected).map((d) => d.name).join(', ') }}</span>
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
            <span class="proj-page__row-value">{{ selected.lead ? `${selected.lead.name}${selected.lead.department?.name ? ' — ' + selected.lead.department.name : ''}` : '—' }}</span>
          </div>
          <div class="proj-page__row">
            <span class="proj-page__row-label">Phòng ban phụ trách</span>
            <span class="proj-page__row-value">{{ selected.lead_department?.name || selected.lead?.department?.name || '—' }}</span>
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
            <DualProgressBar
              :actual="selected.progress_percent"
              :expected="computeExpectedProgress(selected.start_date, selected.end_date)"
              size="md"
            />
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
            <span
              v-for="label in selected.labels"
              :key="label.id"
              class="proj-label-picker__chip"
              :class="`proj-label-picker__chip--${label.color}`"
            >
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

    <Teleport to="body">
      <div
        v-if="kanbanDrag.active && kanbanDrag.project"
        class="proj-kanban__ghost"
        :class="{
          'proj-kanban__ghost--live': !kanbanDrag.settling,
          'proj-kanban__ghost--settle': kanbanDrag.settling,
          [`proj-kanban__ghost--${kanbanGhostTone}`]: true,
        }"
        :style="kanbanGhostStyle"
      >
        <header class="proj-kanban__card-head">
          <span v-if="kanbanDrag.project.code" class="proj-kanban__card-code">{{ kanbanDrag.project.code }}</span>
          <span v-if="kanbanDrag.project.type" class="proj-kanban__card-type">{{ typeLabel(kanbanDrag.project.type) }}</span>
        </header>
        <h3 class="proj-kanban__card-title">{{ kanbanDrag.project.name }}</h3>
        <div
          v-if="(kanbanDrag.project.labels || []).length || (kanbanDrag.project.importance && kanbanDrag.project.importance !== 'support' && kanbanDrag.project.importance !== 'low')"
          class="proj-kanban__card-labels"
        >
          <span
            v-if="kanbanDrag.project.importance && kanbanDrag.project.importance !== 'support' && kanbanDrag.project.importance !== 'low'"
            class="proj-kanban__importance"
            :class="`proj-kanban__importance--${importanceTone(kanbanDrag.project.importance)}`"
          >
            {{ importanceLabel(kanbanDrag.project.importance) }}
          </span>
          <span
            v-for="label in kanbanDrag.project.labels"
            :key="label.id"
            class="proj-label-picker__chip proj-label-picker__chip--sm"
            :class="`proj-label-picker__chip--${label.color}`"
          >
            <span class="proj-label-picker__dot" :class="`proj-label-picker__dot--${label.color}`" />
            <span>{{ label.name }}</span>
          </span>
        </div>
        <p v-if="kanbanDrag.project.description" class="proj-kanban__card-desc">{{ kanbanDrag.project.description }}</p>
        <dl
          v-if="kanbanCardDeptName(kanbanDrag.project) || kanbanDrag.project.lead?.name || projectDateRangeLabel(kanbanDrag.project)"
          class="proj-kanban__card-facts"
        >
          <div v-if="kanbanCardDeptName(kanbanDrag.project)" class="proj-kanban__card-fact">
            <dt>Phòng ban</dt>
            <dd>{{ kanbanCardDeptName(kanbanDrag.project) }}</dd>
          </div>
          <div v-if="kanbanDrag.project.lead?.name" class="proj-kanban__card-fact">
            <dt>Phụ trách</dt>
            <dd>{{ kanbanDrag.project.lead.name }}</dd>
          </div>
          <div v-if="projectDateRangeLabel(kanbanDrag.project)" class="proj-kanban__card-fact">
            <dt>Thời hạn</dt>
            <dd>{{ projectDateRangeLabel(kanbanDrag.project) }}</dd>
          </div>
        </dl>
        <div v-if="kanbanDrag.project.progress_percent != null" class="proj-kanban__card-progress">
          <div class="proj-kanban__card-progress-head">
            <span class="proj-kanban__card-progress-label">Tiến độ</span>
            <span class="proj-kanban__card-progress-value">{{ kanbanDrag.project.progress_percent }}%</span>
          </div>
          <span class="proj-page__mini-track">
            <span
              class="proj-page__mini-fill"
              :class="`proj-page__mini-fill--${progressTone(kanbanDrag.project.progress_percent)}`"
              :style="{ width: `${kanbanDrag.project.progress_percent}%` }"
            />
          </span>
        </div>
        <footer class="proj-kanban__card-foot">
          <span class="proj-kanban__card-meta">
            <span v-if="(kanbanDrag.project.attachments || []).length" class="proj-kanban__card-stat">
              <AppIcon name="paperclip" :size="12" />
              {{ kanbanDrag.project.attachments.length }}
            </span>
          </span>
        </footer>
      </div>
    </Teleport>

    <Teleport to="body">
      <Transition name="proj-dialog-fade">
        <div
          v-if="labelPickerProject"
          class="proj-page__dialog"
          role="presentation"
          @mousedown.self="closeLabelPicker"
        >
          <div
            class="proj-page__dialog-panel proj-page__dialog-panel--labels"
            role="dialog"
            aria-modal="true"
            aria-labelledby="proj-label-form-title"
          >
            <div class="proj-page__dialog-head">
              <span class="proj-page__dialog-icon" aria-hidden="true">
                <AppIcon name="bookmark" :size="18" :stroke-width="1.75" />
              </span>
              <div class="proj-page__dialog-head-copy">
                <h2 id="proj-label-form-title" class="proj-page__dialog-title">Gắn nhãn</h2>
                <p class="proj-page__dialog-sub">{{ labelPickerProject.name }}</p>
              </div>
              <button type="button" class="proj-page__dialog-close" aria-label="Đóng" @click="closeLabelPicker">
                <AppIcon name="close" :size="16" />
              </button>
            </div>

            <div class="proj-page__dialog-body">
              <ProjectLabelPicker
                class="proj-page__dialog-picker"
                :model-value="(labelPickerProject.labels || []).map((l) => l.id)"
                :labels="allLabels"
                placeholder="Tìm hoặc tạo nhãn…"
                autofocus
                always-open
                @update:model-value="setProjectLabels(labelPickerProject, $event)"
                @created="onLabelCreated"
              />
            </div>

            <div class="proj-page__dialog-actions">
              <button type="button" class="proj-page__dialog-btn proj-page__dialog-btn--primary" @click="closeLabelPicker">
                Xong
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <Teleport to="body">
      <Transition name="proj-dialog-fade">
        <div
          v-if="memberPickerProject"
          class="proj-page__dialog"
          role="presentation"
          @mousedown.self="closeMemberList"
        >
          <div
            class="proj-page__dialog-panel proj-page__dialog-panel--sm"
            role="dialog"
            aria-modal="true"
            aria-labelledby="proj-member-list-title"
          >
            <div class="proj-page__dialog-head">
              <span class="proj-page__dialog-icon" aria-hidden="true">
                <AppIcon name="users" :size="22" :stroke-width="1.75" />
              </span>
              <div class="proj-page__dialog-head-copy">
                <h2 id="proj-member-list-title" class="proj-page__dialog-title">Người tham gia</h2>
                <p class="proj-page__dialog-sub">{{ memberPickerProject.name }}</p>
              </div>
              <button type="button" class="proj-page__dialog-close" aria-label="Đóng" @click="closeMemberList">
                <AppIcon name="close" :size="16" />
              </button>
            </div>

            <div class="proj-page__dialog-body hide-scrollbar">
              <ul class="proj-page__dialog-members">
                <li v-for="member in memberPickerProject.members || []" :key="member.id" class="proj-page__member-row">
                  <UserAvatarTip :user="member" label="Người tham gia" />
                  <span class="proj-page__member-info">
                    <span class="proj-page__member-name">{{ member.name }}</span>
                    <span v-if="member.department?.name" class="proj-page__member-dept">{{ member.department.name }}</span>
                  </span>
                </li>
                <li v-if="!(memberPickerProject.members || []).length" class="proj-page__member-empty">
                  Chưa có người tham gia nào.
                </li>
              </ul>
            </div>

            <div class="proj-page__dialog-actions">
              <button type="button" class="proj-page__dialog-btn proj-page__dialog-btn--primary" @click="closeMemberList">
                Đóng
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <Teleport to="body">
      <Transition name="proj-dialog-fade">
        <div
          v-if="importDialogOpen"
          class="proj-page__dialog"
          role="presentation"
          @mousedown.self="closeImportDialog"
        >
          <div
            class="proj-page__dialog-panel"
            :class="importStep === 'preview' ? 'proj-page__dialog-panel--preview' : 'proj-page__dialog-panel--import'"
            role="dialog"
            aria-modal="true"
            aria-labelledby="proj-import-title"
          >
            <div class="proj-page__dialog-head">
              <span class="proj-page__dialog-icon" aria-hidden="true">
                <AppIcon name="fileUp" :size="22" :stroke-width="1.75" />
              </span>
              <div class="proj-page__dialog-head-copy">
                <h2 id="proj-import-title" class="proj-page__dialog-title">Nhập dự án từ Excel</h2>
              </div>
              <button
                type="button"
                class="proj-page__dialog-close"
                aria-label="Đóng"
                :disabled="previewing || confirming"
                @click="closeImportDialog"
              >
                <AppIcon name="close" :size="16" />
              </button>
            </div>

            <template v-if="importStep === 'select'">
              <div class="proj-page__dialog-body">
                <p class="proj-page__import-hint">
                  Chọn file Excel (.xlsx) đúng theo cấu trúc cột đã xuất. Để trống Mã dự án để tạo dự án mới (khi đó Tên dự án và Loại dự án là bắt buộc). Điền đúng Mã dự án đã có để cập nhật dự án đó — ô nào để trống khi cập nhật sẽ giữ nguyên giá trị cũ, không bị xoá. Người phụ trách / tham gia / theo dõi nhập bằng email, nhãn phải được tạo sẵn.
                  <button type="button" class="proj-page__import-template" :disabled="exporting" @click="exportExcel()">
                    Tải file mẫu (xuất danh sách hiện tại, đủ cột)
                  </button>
                </p>
                <div class="proj-page__dialog-field">
                  <label class="proj-page__dialog-label" for="proj-import-file">
                    File Excel
                    <span class="proj-page__dialog-req" aria-hidden="true">*</span>
                  </label>
                  <input
                    id="proj-import-file"
                    type="file"
                    accept=".xlsx"
                    class="proj-page__import-file"
                    :disabled="previewing"
                    @change="onImportFileChange"
                  />
                </div>
              </div>
              <div class="proj-page__dialog-actions">
                <button type="button" class="proj-page__dialog-btn proj-page__dialog-btn--ghost" :disabled="previewing" @click="closeImportDialog">
                  Đóng
                </button>
                <button
                  type="button"
                  class="proj-page__dialog-btn proj-page__dialog-btn--primary"
                  :disabled="previewing || !importFile"
                  @click="runPreview"
                >
                  {{ previewing ? 'Đang đọc file…' : 'Xem trước' }}
                </button>
              </div>
            </template>

            <template v-else-if="importStep === 'preview'">
              <div class="proj-page__dialog-body proj-page__dialog-body--preview">
                <div class="proj-page__import-summary">
                  <span class="proj-page__import-dot proj-page__import-dot--ok" />
                  {{ importSelectedRows.size }}/{{ importPreview?.rows?.length ?? 0 }}
                  dòng hợp lệ được chọn để nhập. Nhấn vào dòng lỗi để sửa trực tiếp.
                </div>
                <div class="proj-page__preview-table-wrap hide-scrollbar">
                  <table class="proj-page__preview-table">
                    <thead>
                      <tr>
                        <th class="proj-page__preview-th proj-page__preview-th--check"></th>
                        <th class="proj-page__preview-th">Dòng</th>
                        <th class="proj-page__preview-th">Hành động</th>
                        <th class="proj-page__preview-th">Mã dự án</th>
                        <th class="proj-page__preview-th">Tên dự án</th>
                        <th class="proj-page__preview-th">Loại</th>
                        <th class="proj-page__preview-th">Phòng ban thực hiện</th>
                        <th class="proj-page__preview-th">Phụ trách chính</th>
                        <th class="proj-page__preview-th">Người tham gia</th>
                        <th class="proj-page__preview-th">Người theo dõi</th>
                        <th class="proj-page__preview-th">Nhãn</th>
                        <th class="proj-page__preview-th">Trạng thái dự án</th>
                        <th class="proj-page__preview-th">Mức độ quan trọng</th>
                        <th class="proj-page__preview-th">Ngày bắt đầu</th>
                        <th class="proj-page__preview-th">Ngày kết thúc</th>
                        <th class="proj-page__preview-th">Cách tính tiến độ</th>
                        <th class="proj-page__preview-th">Mô tả</th>
                        <th class="proj-page__preview-th">Trạng thái dòng</th>
                        <th class="proj-page__preview-th">Ghi chú</th>
                      </tr>
                    </thead>
                    <tbody>
                      <template v-for="row in importPreview?.rows ?? []" :key="row.row">
                        <tr
                          v-if="editingRowKey !== row.row"
                          class="proj-page__preview-row"
                          :class="{ 'proj-page__preview-row--invalid': row.status !== 'valid' }"
                          @dblclick="startEditRow(row)"
                        >
                          <td class="proj-page__preview-td proj-page__preview-td--check">
                            <input
                              v-if="row.status === 'valid'"
                              type="checkbox"
                              :checked="importSelectedRows.has(row.row)"
                              @change="toggleRowSelected(row.row)"
                            />
                          </td>
                          <td class="proj-page__preview-td">{{ row.row }}</td>
                          <td class="proj-page__preview-td">
                            {{ row.action === 'update' ? `Cập nhật dự án ${row.code || ''}` : 'Tạo mới' }}
                          </td>
                          <td class="proj-page__preview-td">{{ row.code || '—' }}</td>
                          <td class="proj-page__preview-td">{{ row.data?.name || '—' }}</td>
                          <td class="proj-page__preview-td">{{ typeLabel(row.data?.type) }}</td>
                          <td class="proj-page__preview-td">{{ row.data?.executing_department_name || '—' }}</td>
                          <td class="proj-page__preview-td">{{ row.data?.lead_name || '—' }}</td>
                          <td class="proj-page__preview-td">{{ (row.data?.member_names || []).join('; ') || '—' }}</td>
                          <td class="proj-page__preview-td">{{ (row.data?.follower_names || []).join('; ') || '—' }}</td>
                          <td class="proj-page__preview-td">{{ (row.data?.label_names || []).join('; ') || '—' }}</td>
                          <td class="proj-page__preview-td">{{ statusLabel(row.data?.status) }}</td>
                          <td class="proj-page__preview-td">{{ importanceLabel(row.data?.importance) }}</td>
                          <td class="proj-page__preview-td">{{ formatDate(row.data?.start_date) }}</td>
                          <td class="proj-page__preview-td">{{ formatDate(row.data?.end_date) }}</td>
                          <td class="proj-page__preview-td">{{ progressMethodLabel(row.data?.progress_method) }}</td>
                          <td class="proj-page__preview-td proj-page__preview-td--desc">{{ row.data?.description || '—' }}</td>
                          <td class="proj-page__preview-td">
                            <span class="proj-page__import-summary">
                              <span
                                class="proj-page__import-dot"
                                :class="row.status === 'valid' ? 'proj-page__import-dot--ok' : 'proj-page__import-dot--error'"
                              />
                              {{ row.status === 'valid' ? 'Hợp lệ' : 'Không hợp lệ' }}
                            </span>
                          </td>
                          <td class="proj-page__preview-td proj-page__preview-td--issues">
                            <p v-for="(issue, idx) in row.issues" :key="idx" class="proj-page__preview-issue">
                              {{ issue.message }}
                            </p>
                            <button
                              v-if="row.status !== 'valid'"
                              type="button"
                              class="proj-page__import-template"
                              @click="startEditRow(row)"
                            >
                              Sửa dòng này
                            </button>
                          </td>
                        </tr>
                        <tr v-else class="proj-page__preview-row proj-page__preview-row--editing">
                          <td class="proj-page__preview-td" colspan="19">
                            <div class="proj-page__edit-grid">
                              <label class="proj-page__edit-field">
                                <span>Mã dự án</span>
                                <input v-model="editingRowDraft.code" type="text" />
                              </label>
                              <label class="proj-page__edit-field">
                                <span>Tên dự án</span>
                                <input v-model="editingRowDraft.name" type="text" />
                              </label>
                              <label class="proj-page__edit-field">
                                <span>Loại dự án</span>
                                <input v-model="editingRowDraft.type_input" type="text" list="proj-import-type-options" />
                              </label>
                              <label class="proj-page__edit-field">
                                <span>Phòng ban thực hiện</span>
                                <input v-model="editingRowDraft.exec_dept_name" type="text" />
                              </label>
                              <label class="proj-page__edit-field">
                                <span>Phụ trách chính (email)</span>
                                <input v-model="editingRowDraft.lead_input" type="text" />
                              </label>
                              <label class="proj-page__edit-field">
                                <span>Người tham gia (email; …)</span>
                                <input v-model="editingRowDraft.members_input" type="text" />
                              </label>
                              <label class="proj-page__edit-field">
                                <span>Người theo dõi (email; …)</span>
                                <input v-model="editingRowDraft.followers_input" type="text" />
                              </label>
                              <label class="proj-page__edit-field">
                                <span>Nhãn (tên; …)</span>
                                <input v-model="editingRowDraft.labels_input" type="text" />
                              </label>
                              <label class="proj-page__edit-field">
                                <span>Trạng thái</span>
                                <input v-model="editingRowDraft.status_input" type="text" list="proj-import-status-options" />
                              </label>
                              <label class="proj-page__edit-field">
                                <span>Mức độ quan trọng</span>
                                <input v-model="editingRowDraft.importance_input" type="text" list="proj-import-importance-options" />
                              </label>
                              <label class="proj-page__edit-field">
                                <span>Ngày bắt đầu</span>
                                <input v-model="editingRowDraft.start_input" type="text" placeholder="dd/mm/yyyy" />
                              </label>
                              <label class="proj-page__edit-field">
                                <span>Ngày kết thúc</span>
                                <input v-model="editingRowDraft.end_input" type="text" placeholder="dd/mm/yyyy" />
                              </label>
                              <label class="proj-page__edit-field">
                                <span>Cách tính tiến độ</span>
                                <input v-model="editingRowDraft.progress_method_input" type="text" list="proj-import-method-options" />
                              </label>
                              <label class="proj-page__edit-field proj-page__edit-field--full">
                                <span>Mô tả</span>
                                <input v-model="editingRowDraft.description" type="text" />
                              </label>
                            </div>
                            <div class="proj-page__edit-actions">
                              <button type="button" class="proj-page__dialog-btn proj-page__dialog-btn--ghost" :disabled="resolvingRow" @click="cancelEditRow">
                                Huỷ
                              </button>
                              <button
                                type="button"
                                class="proj-page__dialog-btn proj-page__dialog-btn--primary"
                                :disabled="resolvingRow"
                                @click="saveEditRow(row)"
                              >
                                {{ resolvingRow ? 'Đang kiểm tra…' : 'Lưu và kiểm tra lại' }}
                              </button>
                            </div>
                          </td>
                        </tr>
                      </template>
                    </tbody>
                  </table>
                  <datalist id="proj-import-type-options">
                    <option v-for="o in options.type" :key="o.value" :value="o.label" />
                  </datalist>
                  <datalist id="proj-import-status-options">
                    <option v-for="o in options.status" :key="o.value" :value="o.label" />
                  </datalist>
                  <datalist id="proj-import-importance-options">
                    <option v-for="o in options.importance" :key="o.value" :value="o.label" />
                  </datalist>
                  <datalist id="proj-import-method-options">
                    <option v-for="o in options.progress_method" :key="o.value" :value="o.label" />
                  </datalist>
                </div>
              </div>
              <div class="proj-page__dialog-actions">
                <button type="button" class="proj-page__dialog-btn proj-page__dialog-btn--ghost" :disabled="confirming" @click="backToSelect">
                  Quay lại
                </button>
                <button
                  type="button"
                  class="proj-page__dialog-btn proj-page__dialog-btn--primary"
                  :disabled="confirming || importSelectedRows.size === 0"
                  @click="confirmImportRows"
                >
                  {{ confirming ? 'Đang nhập…' : `Xác nhận nhập (${importSelectedRows.size})` }}
                </button>
              </div>
            </template>

            <template v-else>
              <div class="proj-page__dialog-body">
                <div v-if="importResult" class="proj-page__import-result">
                  <div class="proj-page__import-summary">
                    <span class="proj-page__import-dot proj-page__import-dot--ok" />
                    Đã tạo {{ importResult.created.length }} dự án, cập nhật {{ importResult.updated?.length ?? 0 }} dự án
                    <template v-if="importResult.errors.length">
                      , còn {{ importResult.errors.length }} dòng lỗi
                    </template>.
                  </div>
                  <ul v-if="importResult.errors.length" class="proj-page__import-errors">
                    <li v-for="err in importResult.errors" :key="err.row" class="proj-page__import-error">
                      <span class="proj-page__import-dot proj-page__import-dot--error" />
                      Dòng {{ err.row }}: {{ err.message }}
                    </li>
                  </ul>
                </div>
              </div>
              <div class="proj-page__dialog-actions">
                <button type="button" class="proj-page__dialog-btn proj-page__dialog-btn--primary" @click="closeImportDialog">
                  Đóng
                </button>
              </div>
            </template>
          </div>
        </div>
      </Transition>
    </Teleport>

    <Teleport to="body">
      <Transition name="proj-dialog-fade">
        <div
          v-if="exportDialogOpen"
          class="proj-page__dialog"
          role="presentation"
          @mousedown.self="closeExportDialog"
        >
          <div
            class="proj-page__dialog-panel proj-page__dialog-panel--import"
            role="dialog"
            aria-modal="true"
            aria-labelledby="proj-export-title"
          >
            <div class="proj-page__dialog-head">
              <span class="proj-page__dialog-icon" aria-hidden="true">
                <AppIcon name="fileDown" :size="22" :stroke-width="1.75" />
              </span>
              <div class="proj-page__dialog-head-copy">
                <h2 id="proj-export-title" class="proj-page__dialog-title">Xuất dự án ra Excel</h2>
              </div>
              <button
                type="button"
                class="proj-page__dialog-close"
                aria-label="Đóng"
                :disabled="exporting"
                @click="closeExportDialog"
              >
                <AppIcon name="close" :size="16" />
              </button>
            </div>

            <div class="proj-page__dialog-body">
              <p class="proj-page__import-hint">
                Xuất theo đúng bộ lọc đang xem trên trang. Chọn cột cần xuất — Mã dự án luôn được xuất.
              </p>
              <div class="proj-page__export-toolbar">
                <button type="button" class="proj-page__import-template" @click="selectAllExportColumns">Chọn tất cả</button>
                <button type="button" class="proj-page__import-template" @click="deselectAllExportColumns">Bỏ chọn tất cả</button>
              </div>
              <div class="proj-page__export-grid">
                <label
                  v-for="col in EXPORT_COLUMNS"
                  :key="col.key"
                  class="proj-page__export-col"
                  :class="{ 'proj-page__export-col--disabled': col.always }"
                >
                  <input
                    type="checkbox"
                    :checked="exportSelectedColumns.has(col.key)"
                    :disabled="col.always"
                    @change="toggleExportColumn(col.key)"
                  />
                  <span>{{ col.label }}</span>
                </label>
              </div>
            </div>
            <div class="proj-page__dialog-actions">
              <button type="button" class="proj-page__dialog-btn proj-page__dialog-btn--ghost" :disabled="exporting" @click="closeExportDialog">
                Đóng
              </button>
              <button
                type="button"
                class="proj-page__dialog-btn proj-page__dialog-btn--primary"
                :disabled="exporting || exportSelectedColumns.size === 0"
                @click="submitExportDialog"
              >
                {{ exporting ? 'Đang xuất…' : `Xuất Excel (${exportSelectedColumns.size} cột)` }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <ProjectRowContextMenu
      :open="ctxMenu.open"
      :x="ctxMenu.x"
      :y="ctxMenu.y"
      :project="ctxMenu.project"
      :can-duplicate="canCreate"
      :statuses="options.status"
      @close="closeRowContextMenu"
      @action="onRowContextAction"
    />

    <ProjectQuickActionModals
      :kind="actionDialog.kind"
      :project="actionDialog.project"
      :extra="actionDialog.extra"
      @close="closeActionDialog"
      @updated="applyProject"
      @duplicated="onProjectDuplicated"
    />
  </section>
</template>

<style scoped>
.proj-page {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: 0 var(--space-5) var(--space-3);
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

.proj-tabs-row {
  position: relative;
  z-index: 12;
  flex-shrink: 0;
  display: flex;
  align-items: stretch;
  min-width: 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.proj-view-mode {
  position: relative;
  flex-shrink: 0;
  display: flex;
  align-items: stretch;
}

.proj-view-mode__trigger {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.5625rem 0.75rem 0.5rem;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
  box-shadow: 1px 0 0 var(--color-border);
}

.proj-view-mode__trigger:hover,
.proj-view-mode__trigger[aria-expanded='true'] {
  color: var(--color-primary);
  background: var(--color-primary-surface);
}

.proj-view-mode__menu {
  position: absolute;
  z-index: 20;
  top: calc(100% + 0.25rem);
  left: 0;
  display: flex;
  flex-direction: column;
  min-width: 17.5rem;
  padding: var(--space-1);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
}

.proj-view-mode__group {
  margin: 0.25rem 0 0.125rem;
  padding: 0.25rem 0.625rem;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.proj-view-mode__item {
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
  font-weight: 600;
  text-align: left;
  cursor: pointer;
}

.proj-view-mode__item span {
  flex: 1;
}

.proj-view-mode__item:hover {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.proj-view-mode__item--on {
  color: var(--color-primary);
  background: var(--color-primary-surface);
}

.proj-tabs {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: stretch;
  gap: 0.125rem;
  overflow-x: auto;
}

.proj-tabs__item {
  --tab-accent: var(--color-primary);
  --tab-bg: var(--color-primary-surface);
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.5625rem 0.75rem 0.5rem;
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
  color: var(--tab-accent);
  background: var(--tab-bg);
}

.proj-tabs__item--active {
  color: var(--tab-accent);
  background: var(--tab-bg);
  box-shadow: 0 2px 0 var(--tab-accent);
}

.proj-tabs__count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.25rem;
  height: 1.125rem;
  padding: 0 0.3125rem;
  border-radius: var(--radius-full);
  background: var(--tab-bg);
  color: var(--tab-accent);
  font-size: 0.6875rem;
  font-weight: 700;
  line-height: 1;
}

.proj-tabs__item--active .proj-tabs__count {
  background: var(--tab-accent);
  color: #fff;
}

.proj-tabs__item--primary {
  --tab-accent: var(--color-primary);
  --tab-bg: var(--color-primary-surface);
}

.proj-tabs__item--success {
  --tab-accent: var(--color-success);
  --tab-bg: var(--color-success-tint-bg);
}

.proj-tabs__item--info {
  --tab-accent: var(--color-info);
  --tab-bg: var(--color-info-tint-bg);
}

.proj-tabs__item--warning {
  --tab-accent: var(--color-warning);
  --tab-bg: var(--color-warning-tint-bg);
}

.proj-tabs__item--danger {
  --tab-accent: var(--color-danger);
  --tab-bg: var(--color-danger-tint-bg);
}

.proj-tabs__item--gold {
  --tab-accent: var(--color-gold-600);
  --tab-bg: var(--color-gold-surface);
}

.proj-tabs__item--secondary {
  --tab-accent: var(--color-secondary);
  --tab-bg: var(--color-secondary-surface);
}

.proj-tabs__item--tertiary {
  --tab-accent: var(--color-tertiary);
  --tab-bg: var(--color-tertiary-surface);
}

.proj-tabs__item--violet {
  --tab-accent: #7c3aed;
  --tab-bg: #f3e8ff;
}

.proj-tabs__item--umber {
  --tab-accent: var(--color-umber);
  --tab-bg: var(--color-umber-surface);
}

/* ---------- Chuyển đổi chế độ xem (Danh sách / Kanban) ---------- */

.proj-view-bar {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2) 0;
}

.proj-page__label-filter-search {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  margin-bottom: 0.25rem;
  padding: 0.375rem 0.5rem;
  border-radius: var(--radius-sm);
  color: var(--color-text-muted);
  background: var(--color-surface-muted, var(--color-bg));
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.proj-page__label-filter-search input {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
}

.proj-page__label-filter-search input:focus {
  outline: none;
}

.proj-page__label-filter-option {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0.5rem;
  border-radius: var(--radius-sm);
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.proj-page__label-filter-option:hover {
  background: var(--color-primary-surface);
}

.proj-page__label-filter-empty {
  margin: 0;
  padding: 0.375rem 0.5rem;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

/* ---------- Chọn người tham gia (multi-select) cho Kanban theo người ---------- */

.proj-kanban-members {
  position: relative;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
}

.proj-kanban-members__trigger {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.375rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.proj-kanban-members__trigger:hover {
  border-color: var(--color-primary-200);
}

.proj-kanban-members__count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.125rem;
  height: 1.125rem;
  padding: 0 0.3125rem;
  border-radius: var(--radius-full);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-size: 0.6875rem;
  font-weight: 700;
}

.proj-kanban-members__dropdown {
  position: absolute;
  z-index: 20;
  top: calc(100% + 0.375rem);
  left: 0;
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 15rem;
  max-height: 16rem;
  overflow-y: auto;
  padding: var(--space-2);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
}

.proj-kanban-members__option {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0.5rem;
  border-radius: var(--radius-sm);
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.proj-kanban-members__option:hover {
  background: var(--color-primary-surface);
}

.proj-kanban-members__empty {
  margin: 0;
  padding: 0.375rem 0.5rem;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.proj-kanban-members__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
}

.proj-kanban-members__chip {
  display: inline-flex;
  align-items: center;
  gap: 0.3125rem;
  padding: 0.25rem 0.375rem 0.25rem 0.625rem;
  border: 1px solid var(--color-primary);
  border-radius: var(--radius-full);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 0.75rem;
  font-weight: 600;
}

.proj-kanban-members__chip button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1rem;
  height: 1rem;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.proj-kanban-members__chip button:hover {
  background: color-mix(in srgb, var(--color-primary) 15%, transparent);
}

/* ---------- Bảng Kanban ---------- */

.proj-kanban {
  flex: 1;
  min-width: 0;
  min-height: 0;
  display: flex;
  align-items: stretch;
  gap: var(--space-3);
  padding: var(--space-3) 0 var(--space-2);
  overflow-x: auto;
  overflow-y: hidden;
}

.proj-kanban--dragging {
  cursor: grabbing;
}

/* Lọc 1 trạng thái: ẩn cột trống, trải thẻ theo hàng ngang cho kín khung. */
.proj-kanban--fill {
  overflow-x: hidden;
  overflow-y: hidden;
}

.proj-kanban--fill .proj-kanban__col {
  flex: 1 1 0;
  width: auto;
  max-width: none;
}

.proj-kanban--fill .proj-kanban__col-body {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(20rem, 100%), 1fr));
  align-content: start;
  overflow-x: hidden;
  overflow-y: auto;
}

.proj-kanban--fill .proj-kanban__card {
  height: 100%;
  min-width: 0;
}

/* Theo tiến trình: 3 cột giãn đều kín khung, thẻ vẫn xếp dọc trong từng cột. */
.proj-kanban--spread {
  overflow-x: hidden;
}

.proj-kanban--spread .proj-kanban__col {
  flex: 1 1 0;
  width: auto;
  min-width: 0;
  max-width: none;
}

.proj-kanban__col {
  --col-accent: var(--color-primary);
  --col-head: var(--color-primary);
  --col-on: var(--color-on-primary);
  --col-well: var(--color-primary-surface);
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  width: 22rem;
  min-width: 0;
  min-height: 0;
  height: 100%;
  overflow: hidden;
  border-radius: var(--radius-md);
  background: var(--col-well);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--col-accent) 18%, var(--color-border));
  transition:
    box-shadow 0.18s ease,
    transform 0.18s ease,
    background 0.18s ease;
}

.proj-kanban__col--drop {
  background: color-mix(in srgb, var(--col-well) 82%, var(--color-surface));
  box-shadow:
    inset 0 0 0 2px var(--col-accent),
    0 12px 28px color-mix(in srgb, var(--col-accent) 18%, transparent);
  transform: translateY(-2px);
}

.proj-kanban__col--primary {
  --col-accent: var(--color-primary);
  --col-head: var(--color-primary);
  --col-on: var(--color-on-primary);
  --col-well: var(--color-primary-surface);
}

.proj-kanban__col--secondary {
  --col-accent: var(--color-secondary);
  --col-head: var(--color-secondary);
  --col-on: var(--color-on-secondary);
  --col-well: var(--color-secondary-surface);
}

.proj-kanban__col--tertiary {
  --col-accent: var(--color-tertiary);
  --col-head: var(--color-tertiary);
  --col-on: var(--color-on-tertiary);
  --col-well: var(--color-tertiary-surface);
}

.proj-kanban__col--gold {
  --col-accent: var(--color-gold-600);
  --col-head: var(--color-gold-600);
  --col-on: var(--color-on-gold);
  --col-well: var(--color-gold-surface);
}

.proj-kanban__col--success {
  --col-accent: var(--color-success);
  --col-head: var(--color-success);
  --col-on: #ffffff;
  --col-well: var(--color-success-tint-bg);
}

.proj-kanban__col--danger {
  --col-accent: var(--color-danger);
  --col-head: var(--color-danger);
  --col-on: var(--color-on-primary);
  --col-well: var(--color-danger-tint-bg);
}

.proj-kanban__col--warning {
  --col-accent: var(--color-warning);
  --col-head: color-mix(in srgb, var(--color-warning) 82%, var(--color-text));
  --col-on: #ffffff;
  --col-well: var(--color-warning-tint-bg);
}

.proj-kanban__col--info {
  --col-accent: var(--color-info);
  --col-head: var(--color-info);
  --col-on: #ffffff;
  --col-well: var(--color-info-tint-bg);
}

.proj-kanban__col--violet {
  --col-accent: color-mix(in srgb, var(--color-tertiary) 58%, var(--color-primary));
  --col-head: color-mix(in srgb, var(--color-tertiary) 58%, var(--color-primary));
  --col-on: #ffffff;
  --col-well: color-mix(in srgb, var(--color-tertiary-surface) 65%, var(--color-primary-surface));
}

.proj-kanban__col--teal {
  --col-accent: color-mix(in srgb, var(--color-secondary) 62%, var(--color-tertiary));
  --col-head: color-mix(in srgb, var(--color-secondary) 62%, var(--color-tertiary));
  --col-on: #ffffff;
  --col-well: color-mix(in srgb, var(--color-secondary-surface) 62%, var(--color-tertiary-surface));
}

.proj-kanban__col--rose {
  --col-accent: color-mix(in srgb, var(--color-primary) 55%, var(--color-gold));
  --col-head: color-mix(in srgb, var(--color-primary) 55%, var(--color-gold));
  --col-on: #ffffff;
  --col-well: color-mix(in srgb, var(--color-primary-surface) 62%, var(--color-gold-surface));
}

.proj-kanban__col--moss {
  --col-accent: color-mix(in srgb, var(--color-secondary) 58%, var(--color-gold-700));
  --col-head: color-mix(in srgb, var(--color-secondary) 58%, var(--color-gold-700));
  --col-on: #ffffff;
  --col-well: color-mix(in srgb, var(--color-secondary-surface) 62%, var(--color-gold-surface));
}

.proj-kanban__col--dusk {
  --col-accent: color-mix(in srgb, var(--color-tertiary) 68%, var(--color-umber));
  --col-head: color-mix(in srgb, var(--color-tertiary) 68%, var(--color-umber));
  --col-on: #ffffff;
  --col-well: color-mix(in srgb, var(--color-tertiary-surface) 62%, var(--color-umber-surface));
}

.proj-kanban__col--pine {
  --col-accent: color-mix(in srgb, var(--color-success) 68%, var(--color-secondary-800));
  --col-head: color-mix(in srgb, var(--color-success) 68%, var(--color-secondary-800));
  --col-on: #ffffff;
  --col-well: color-mix(in srgb, var(--color-success-tint-bg) 70%, var(--color-secondary-surface));
}

.proj-kanban__col--umber {
  --col-accent: var(--color-umber);
  --col-head: var(--color-umber);
  --col-on: var(--color-on-umber);
  --col-well: var(--color-umber-surface);
}

.proj-kanban__col--neutral {
  --col-accent: var(--color-text-muted);
  --col-head: var(--color-text-muted);
  --col-on: #ffffff;
  --col-well: var(--color-surface-muted);
}

.proj-kanban__col-head {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  min-width: 0;
  padding: 0.875rem var(--space-4);
  overflow: visible;
  background: var(--col-head);
  color: var(--col-on);
}

.proj-kanban__col-head-main {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
}

.proj-kanban__col-head-main :deep(.user-avatar-tip) {
  flex-shrink: 0;
  outline: 2px solid color-mix(in srgb, var(--col-on) 28%, transparent);
  border-radius: var(--radius-full);
}

.proj-kanban__col-title {
  min-width: 0;
  overflow: hidden;
  color: var(--col-on);
  font-size: 0.875rem;
  font-weight: 400;
  letter-spacing: 0.01em;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.proj-kanban__col-count {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  min-width: 1.5rem;
  height: 1.5rem;
  padding: 0 0.5rem;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--col-on) 18%, transparent);
  color: var(--col-on);
  font-size: 0.75rem;
  font-weight: 400;
}

.proj-kanban__col-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  min-height: 0;
  overflow-x: hidden;
  overflow-y: auto;
  padding: var(--space-3);
  padding-bottom: var(--space-4);
}

.proj-kanban__col-empty {
  margin: var(--space-3) 0 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  text-align: center;
}

.proj-kanban__hint {
  flex-shrink: 0;
  align-self: center;
  margin: var(--space-6) auto;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.proj-kanban__placeholder {
  flex-shrink: 0;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--col-accent) 14%, var(--color-surface));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--col-accent) 32%, transparent);
  animation: proj-kanban-slot 0.85s ease-in-out infinite alternate;
}

.proj-kanban__card {
  position: relative;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  gap: 0.625rem;
  min-width: 0;
  min-height: 14.5rem;
  padding: var(--space-4);
  padding-left: calc(var(--space-2) + 3px + var(--space-4));
  overflow: visible;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  cursor: pointer;
  transition:
    box-shadow 0.16s ease,
    transform 0.16s ease;
}

.proj-kanban__card::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--col-accent);
}

.proj-kanban__card:hover {
  box-shadow: var(--shadow-md);
  transform: translateY(-1px);
}

.proj-kanban__card--movable {
  touch-action: none;
  cursor: grab;
}

.proj-kanban--dragging .proj-kanban__card--movable {
  cursor: grabbing;
}

.proj-kanban--dragging .proj-kanban__card:hover {
  box-shadow: var(--shadow-sm);
  transform: none;
}

.proj-kanban__card--slot {
  overflow: hidden;
  background: color-mix(in srgb, var(--col-accent) 12%, var(--color-surface-muted));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--col-accent) 26%, transparent);
  cursor: grabbing;
}

.proj-kanban__card--slot:hover {
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--col-accent) 26%, transparent);
  transform: none;
}

.proj-kanban__card--slot > *:not(.proj-kanban__card-busy) {
  visibility: hidden;
}

.proj-kanban__card--slot::before {
  opacity: 0;
}

.proj-kanban__card--enter {
  animation: proj-kanban-pop 0.32s cubic-bezier(0.22, 1, 0.36, 1);
}

.proj-kanban__card-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
  min-width: 0;
  padding-right: 1.125rem;
}

.proj-kanban__card-code {
  min-width: 0;
  overflow: hidden;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 400;
  letter-spacing: 0.04em;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.proj-kanban__card-type {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-style: italic;
  font-weight: 400;
}

.proj-kanban__card-title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 400;
  line-height: 1.4;
  overflow-wrap: anywhere;
}

.proj-kanban__card-labels {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
  min-width: 0;
}

.proj-kanban__card-labels .proj-label-picker__chip {
  max-width: 100%;
  overflow: hidden;
  font-weight: 400;
  text-overflow: ellipsis;
}

.proj-kanban__importance {
  display: inline-flex;
  align-items: center;
  max-width: 100%;
  padding: 0.125rem 0.5rem;
  overflow: hidden;
  border-radius: var(--radius-full);
  font-size: 0.6875rem;
  font-weight: 400;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.proj-kanban__importance--danger {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}

.proj-kanban__importance--gold {
  background: var(--color-gold-surface);
  color: var(--color-gold-700);
}

.proj-kanban__importance--tertiary {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.proj-kanban__importance--info {
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
}

.proj-kanban__importance--neutral {
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.proj-kanban__card-desc {
  margin: 0;
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 400;
  line-height: 1.45;
  overflow-wrap: anywhere;
}

.proj-kanban__card-facts {
  display: flex;
  flex-direction: column;
  margin: 0;
  min-width: 0;
}

.proj-kanban__card-fact {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-3);
  min-width: 0;
  padding: 0.375rem 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.proj-kanban__card-fact:last-child {
  box-shadow: none;
}

.proj-kanban__card-fact dt {
  flex-shrink: 0;
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 400;
}

.proj-kanban__card-fact dt::after {
  content: ':';
}

.proj-kanban__card-fact dd {
  margin: 0;
  min-width: 0;
  overflow: hidden;
  color: var(--color-text);
  font-size: 0.75rem;
  font-style: italic;
  font-weight: 400;
  text-align: right;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.proj-kanban__card-thumbs {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.25rem;
}

.proj-kanban__card-thumb {
  display: block;
  width: 100%;
  height: 3.5rem;
  object-fit: cover;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
}

.proj-kanban__card-progress {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  min-width: 0;
}

.proj-kanban__card-progress-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
}

.proj-kanban__card-progress-label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 400;
}

.proj-kanban__card-progress-label::after {
  content: ':';
}

.proj-kanban__card-progress .proj-page__mini-track {
  width: 100%;
  height: 0.375rem;
}

.proj-kanban__card-progress-value {
  flex-shrink: 0;
  color: var(--color-text);
  font-size: 0.75rem;
  font-style: italic;
  font-weight: 400;
}

.proj-kanban__card-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  min-width: 0;
  margin-top: auto;
  padding-top: 0.625rem;
  overflow: visible;
  box-shadow: 0 -1px 0 var(--color-border);
}

.proj-kanban__card-avatars {
  display: flex;
  flex-shrink: 0;
  min-width: max-content;
  align-items: center;
  padding: 2px;
  overflow: visible;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.proj-kanban__card-meta {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: 0.625rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 400;
  white-space: nowrap;
}

.proj-kanban__card-dates,
.proj-kanban__card-stat {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}

.proj-kanban__card-grip {
  position: absolute;
  top: var(--space-2);
  right: var(--space-2);
  color: var(--color-text-muted);
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.16s ease;
}

.proj-kanban__card--movable:hover .proj-kanban__card-grip {
  opacity: 0.5;
}

.proj-kanban__card-busy {
  position: absolute;
  top: var(--space-2);
  right: var(--space-2);
  z-index: 1;
  display: inline-flex;
  color: var(--color-primary);
}

.proj-kanban__ghost {
  position: fixed;
  top: 0;
  left: 0;
  z-index: 80;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
  min-height: 14.5rem;
  padding: var(--space-4);
  padding-left: calc(var(--space-2) + 3px + var(--space-4));
  overflow: visible;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow:
    var(--shadow-lg),
    0 18px 40px color-mix(in srgb, var(--col-accent, var(--color-primary)) 18%, transparent);
  pointer-events: none;
  transform-origin: top left;
  will-change: transform;
}

.proj-kanban__ghost::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--col-accent, var(--color-primary));
}

.proj-kanban__ghost--primary {
  --col-accent: var(--color-primary);
}
.proj-kanban__ghost--secondary {
  --col-accent: var(--color-secondary);
}
.proj-kanban__ghost--tertiary {
  --col-accent: var(--color-tertiary);
}
.proj-kanban__ghost--gold {
  --col-accent: var(--color-gold-600);
}
.proj-kanban__ghost--success {
  --col-accent: var(--color-success);
}
.proj-kanban__ghost--danger {
  --col-accent: var(--color-danger);
}
.proj-kanban__ghost--warning {
  --col-accent: var(--color-warning);
}
.proj-kanban__ghost--info {
  --col-accent: var(--color-info);
}
.proj-kanban__ghost--violet {
  --col-accent: color-mix(in srgb, var(--color-tertiary) 58%, var(--color-primary));
}
.proj-kanban__ghost--teal {
  --col-accent: color-mix(in srgb, var(--color-secondary) 62%, var(--color-tertiary));
}
.proj-kanban__ghost--rose {
  --col-accent: color-mix(in srgb, var(--color-primary) 55%, var(--color-gold));
}
.proj-kanban__ghost--moss {
  --col-accent: color-mix(in srgb, var(--color-secondary) 58%, var(--color-gold-700));
}
.proj-kanban__ghost--dusk {
  --col-accent: color-mix(in srgb, var(--color-tertiary) 68%, var(--color-umber));
}
.proj-kanban__ghost--pine {
  --col-accent: color-mix(in srgb, var(--color-success) 68%, var(--color-secondary-800));
}
.proj-kanban__ghost--umber {
  --col-accent: var(--color-umber);
}

.proj-kanban__ghost--live {
  opacity: 0.94;
}

.proj-kanban__ghost--settle {
  opacity: 1;
  transition: transform 0.22s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.22s ease;
}

@keyframes proj-kanban-slot {
  from {
    background: color-mix(in srgb, var(--col-accent) 10%, var(--color-surface));
  }
  to {
    background: color-mix(in srgb, var(--col-accent) 22%, var(--color-surface));
  }
}

@keyframes proj-kanban-pop {
  from {
    opacity: 0.4;
    transform: translateY(10px) scale(0.96);
  }
  to {
    opacity: 1;
    transform: none;
  }
}

:global(body.proj-kanban-dragging) {
  cursor: grabbing;
}

.proj-page__name-cell {
  display: flex;
  flex-direction: column;
  gap: 0.3125rem;
  white-space: normal;
}

.proj-page__name-title {
  display: block;
  font-weight: 600;
  line-height: 1.35;
  overflow-wrap: anywhere;
}

.proj-page__name-labels {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.3125rem;
}

.proj-page__label-add {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.25rem;
  height: 1.25rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text-muted);
  box-shadow: inset 0 0 0 1px var(--color-border-strong);
  cursor: pointer;
}

.proj-page__label-add:hover {
  background: var(--color-primary-surface);
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1px var(--color-primary);
}

.proj-page__dialog {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.proj-page__dialog-panel {
  width: min(54rem, calc(100vw - 2.5rem));
  height: auto;
  max-width: calc(100vw - 2.5rem);
  max-height: calc(100vh - 2.5rem);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: 1rem 1.25rem 1rem;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.proj-page__dialog-panel--labels {
  width: min(22.5rem, calc(100vw - 2.5rem));
  height: auto;
  max-width: calc(100vw - 2.5rem);
  max-height: calc(100vh - 2.5rem);
  gap: var(--space-2);
  padding: 0.75rem 0.875rem 0.875rem;
}

.proj-page__dialog-panel--labels .proj-page__dialog-head {
  gap: var(--space-2);
  padding-bottom: var(--space-2);
}

.proj-page__dialog-panel--labels .proj-page__dialog-icon {
  width: 1.75rem;
  height: 1.75rem;
}

.proj-page__dialog-panel--labels .proj-page__dialog-title {
  font-size: 0.9375rem;
}

.proj-page__dialog-panel--labels .proj-page__dialog-sub {
  font-size: 0.75rem;
}

.proj-page__dialog-panel--labels .proj-page__dialog-actions {
  padding-top: var(--space-1);
}

.proj-page__dialog-panel--sm {
  width: min(24rem, calc(100vw - 2.5rem));
  height: auto;
  max-height: calc((100vh - 2.5rem) * 0.7);
}

.proj-page__dialog-head,
.proj-page__dialog-actions {
  position: relative;
  z-index: 1;
  flex-shrink: 0;
  background: var(--color-surface);
}

.proj-page__dialog-head {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
  padding-bottom: var(--space-3);
}

.proj-page__dialog-icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 15%, transparent);
}

.proj-page__dialog-head-copy {
  flex: 1;
  min-width: 0;
}

.proj-page__dialog-title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.125rem;
  font-weight: 700;
  line-height: 1.35;
}

.proj-page__dialog-sub {
  margin: 0.125rem 0 0;
  overflow: hidden;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-style: italic;
  line-height: 1.4;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.proj-page__dialog-close {
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

.proj-page__dialog-close:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.proj-page__dialog-body {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
  min-height: 0;
  overflow: hidden;
}

.proj-page__dialog-picker {
  flex: 0 1 auto;
  min-width: 0;
  min-height: 0;
}

.proj-page__dialog-members {
  display: flex;
  flex-direction: column;
  min-height: 0;
  margin: 0;
  padding: 0;
  max-height: min(22rem, calc((100vh - 2.5rem) * 0.45));
  list-style: none;
  overflow-y: auto;
}

.proj-page__member-row {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.proj-page__member-row:last-child {
  box-shadow: none;
}

.proj-page__member-info {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.125rem;
}

.proj-page__member-name {
  overflow: hidden;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.proj-page__member-dept {
  overflow: hidden;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.proj-page__member-empty {
  padding: var(--space-4) 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
  text-align: center;
}

.proj-page__dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

.proj-page__dialog-btn {
  padding: 0.5rem 1rem;
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.proj-page__dialog-btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.proj-page__dialog-btn--primary:hover {
  background: var(--color-primary-hover);
}

.proj-page__dialog-btn--ghost {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
}

.proj-page__dialog-btn--ghost:hover {
  background: var(--color-surface-muted);
}

.proj-page__dialog-btn:disabled {
  opacity: 0.6;
  cursor: default;
}

.proj-page__dialog-panel--import {
  width: min(40rem, calc(100vw - 2.5rem));
  height: auto;
  max-height: calc(100vh - 2.5rem);
}

.proj-page__dialog-panel--preview {
  width: min(90rem, calc(100vw - 2.5rem));
  height: calc(100vh - 2.5rem);
  max-height: calc(100vh - 2.5rem);
}

.proj-page__dialog-field {
  display: grid;
  grid-template-columns: 7.5rem minmax(0, 1fr);
  column-gap: 0.875rem;
  row-gap: 0.375rem;
  align-items: start;
  min-width: 0;
}

.proj-page__dialog-label {
  padding-top: 0.65rem;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.3;
}

.proj-page__dialog-req {
  color: var(--color-primary);
}

.proj-page__import-hint {
  margin: 0 0 var(--space-3);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.5;
}

.proj-page__import-template {
  display: inline;
  margin: 0;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--color-primary);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  text-decoration: underline;
  cursor: pointer;
}

.proj-page__import-template:disabled {
  opacity: 0.6;
  cursor: default;
}

.proj-page__import-file {
  width: 100%;
  padding: 0.4375rem 0.625rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
}

.proj-page__import-result {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.proj-page__import-summary {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
}

.proj-page__import-errors {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  margin: 0;
  padding: 0;
  max-height: 12rem;
  overflow-y: auto;
  list-style: none;
}

.proj-page__import-error {
  display: flex;
  align-items: flex-start;
  gap: 0.375rem;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.4;
}

.proj-page__import-dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  margin-top: 0.375rem;
  border-radius: var(--radius-full);
}

.proj-page__import-dot--ok {
  background: var(--color-success);
}

.proj-page__import-dot--error {
  background: var(--color-danger, #dc2626);
}

.proj-page__dialog-body--preview {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  min-height: 0;
  overflow: hidden;
}

.proj-page__preview-table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border-radius: var(--radius-md);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.proj-page__preview-table {
  width: 100%;
  min-width: 96rem;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.proj-page__preview-th {
  position: sticky;
  top: 0;
  z-index: 1;
  padding: var(--space-2) var(--space-3);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  text-align: left;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.proj-page__preview-th--check {
  width: 2rem;
}

.proj-page__preview-td {
  padding: var(--space-2) var(--space-3);
  vertical-align: top;
  box-shadow: 0 1px 0 var(--color-border);
}

.proj-page__preview-td--check {
  text-align: center;
}

.proj-page__preview-td--issues {
  min-width: 16rem;
}

.proj-page__preview-row--invalid {
  opacity: 0.7;
}

.proj-page__preview-issue {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.4;
}

.proj-page__preview-td--desc {
  min-width: 12rem;
  max-width: 18rem;
}

.proj-page__preview-row:not(.proj-page__preview-row--editing) {
  cursor: pointer;
}

.proj-page__preview-row--editing {
  background: color-mix(in srgb, var(--color-primary) 4%, var(--color-surface));
}

.proj-page__edit-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-2) var(--space-3);
  padding: var(--space-2) 0;
}

.proj-page__edit-field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.proj-page__edit-field--full {
  grid-column: 1 / -1;
}

.proj-page__edit-field input {
  padding: 0.375rem 0.5rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 400;
}

.proj-page__edit-actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  padding-top: var(--space-2);
}

.proj-page__export-toolbar {
  display: flex;
  gap: var(--space-3);
  margin-bottom: var(--space-2);
}

.proj-page__export-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-2) var(--space-3);
}

.proj-page__export-col {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0.5rem;
  border-radius: var(--radius-sm);
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.proj-page__export-col:hover {
  background: var(--color-surface-muted);
}

.proj-page__export-col--disabled {
  color: var(--color-text-muted);
  cursor: default;
}

.proj-page__export-col--disabled:hover {
  background: transparent;
}

.proj-page__preview-issue + .proj-page__preview-issue {
  margin-top: 0.125rem;
}

.proj-dialog-fade-enter-active,
.proj-dialog-fade-leave-active {
  transition: opacity 0.15s ease;
}

.proj-dialog-fade-enter-from,
.proj-dialog-fade-leave-to {
  opacity: 0;
}

.proj-page__group-row {
  cursor: pointer;
}

.proj-page__group-row td {
  position: relative;
  width: 100%;
  overflow: visible;
  padding: var(--space-2) var(--space-4) var(--space-2) calc(var(--space-4) + 3px + var(--space-2)) !important;
  background: var(--color-surface);
  color: var(--group-fg, var(--color-text));
  box-shadow: inset 0 -2px 0 var(--group-accent, var(--color-border)) !important;
}

.proj-page__group-row td::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--group-accent, var(--color-border));
}

.proj-page__group-row:hover td {
  background: color-mix(in srgb, var(--group-accent, var(--color-text-muted)) 6%, var(--color-surface));
}

.proj-page__group-row--primary {
  --group-fg: var(--color-primary);
  --group-accent: var(--color-primary);
}
.proj-page__group-row--secondary {
  --group-fg: var(--color-secondary);
  --group-accent: var(--color-secondary);
}
.proj-page__group-row--tertiary {
  --group-fg: var(--color-tertiary);
  --group-accent: var(--color-tertiary);
}
.proj-page__group-row--gold {
  --group-fg: var(--color-gold);
  --group-accent: var(--color-gold);
}
.proj-page__group-row--info {
  --group-fg: var(--color-info);
  --group-accent: var(--color-info);
}
.proj-page__group-row--warning {
  --group-fg: var(--color-warning);
  --group-accent: var(--color-warning);
}
.proj-page__group-row--success {
  --group-fg: var(--color-success);
  --group-accent: var(--color-success);
}
.proj-page__group-row--violet {
  --group-fg: color-mix(in srgb, var(--color-tertiary) 58%, var(--color-primary));
  --group-accent: color-mix(in srgb, var(--color-tertiary) 58%, var(--color-primary));
}
.proj-page__group-row--teal {
  --group-fg: color-mix(in srgb, var(--color-secondary) 62%, var(--color-tertiary));
  --group-accent: color-mix(in srgb, var(--color-secondary) 62%, var(--color-tertiary));
}
.proj-page__group-row--rose {
  --group-fg: color-mix(in srgb, var(--color-primary) 55%, var(--color-gold));
  --group-accent: color-mix(in srgb, var(--color-primary) 55%, var(--color-gold));
}
.proj-page__group-row--moss {
  --group-fg: color-mix(in srgb, var(--color-secondary) 58%, var(--color-gold-700));
  --group-accent: color-mix(in srgb, var(--color-secondary) 58%, var(--color-gold-700));
}
.proj-page__group-row--dusk {
  --group-fg: color-mix(in srgb, var(--color-tertiary) 68%, var(--color-umber));
  --group-accent: color-mix(in srgb, var(--color-tertiary) 68%, var(--color-umber));
}
.proj-page__group-row--pine {
  --group-fg: color-mix(in srgb, var(--color-success) 68%, var(--color-secondary-800));
  --group-accent: color-mix(in srgb, var(--color-success) 68%, var(--color-secondary-800));
}
.proj-page__group-row--umber {
  --group-fg: var(--color-umber);
  --group-accent: var(--color-umber);
}
.proj-page__group-row--neutral {
  --group-fg: var(--color-text);
  --group-accent: var(--color-text-muted);
}

.proj-page__group-toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  width: 100%;
  min-width: 0;
}

.proj-page__group-head {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-width: 0;
}

.proj-page__group-chevron {
  flex-shrink: 0;
  color: var(--group-accent, var(--color-text-muted));
  transition: transform 0.15s ease;
}

.proj-page__group-chevron--open {
  transform: rotate(90deg);
}

.proj-page__group-label {
  min-width: 0;
  overflow: hidden;
  color: inherit;
  font-size: calc(0.8125rem * var(--table-zoom, 1));
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.proj-page__group-count {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  margin-left: auto;
  height: calc(1.125rem * var(--table-zoom, 1));
  padding: 0 0.4375rem;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--group-accent, var(--color-text-muted)) 14%, var(--color-surface));
  color: var(--group-accent, var(--color-text-muted));
  font-size: calc(0.6875rem * var(--table-zoom, 1));
  font-weight: 700;
  white-space: nowrap;
}

.proj-page__data-row--primary td {
  background: color-mix(in srgb, var(--color-primary) 4%, var(--color-surface));
}
.proj-page__data-row--secondary td {
  background: color-mix(in srgb, var(--color-secondary) 4%, var(--color-surface));
}
.proj-page__data-row--tertiary td {
  background: color-mix(in srgb, var(--color-tertiary) 4%, var(--color-surface));
}
.proj-page__data-row--gold td {
  background: color-mix(in srgb, var(--color-gold) 5%, var(--color-surface));
}
.proj-page__data-row--info td {
  background: color-mix(in srgb, var(--color-info) 4%, var(--color-surface));
}
.proj-page__data-row--warning td {
  background: color-mix(in srgb, var(--color-warning) 5%, var(--color-surface));
}
.proj-page__data-row--success td {
  background: color-mix(in srgb, var(--color-success) 4%, var(--color-surface));
}
.proj-page__data-row--violet td {
  background: color-mix(in srgb, color-mix(in srgb, var(--color-tertiary) 58%, var(--color-primary)) 4%, var(--color-surface));
}
.proj-page__data-row--teal td {
  background: color-mix(in srgb, color-mix(in srgb, var(--color-secondary) 62%, var(--color-tertiary)) 4%, var(--color-surface));
}
.proj-page__data-row--rose td {
  background: color-mix(in srgb, color-mix(in srgb, var(--color-primary) 55%, var(--color-gold)) 5%, var(--color-surface));
}
.proj-page__data-row--moss td {
  background: color-mix(in srgb, color-mix(in srgb, var(--color-secondary) 58%, var(--color-gold-700)) 5%, var(--color-surface));
}
.proj-page__data-row--dusk td {
  background: color-mix(in srgb, color-mix(in srgb, var(--color-tertiary) 68%, var(--color-umber)) 4%, var(--color-surface));
}
.proj-page__data-row--pine td {
  background: color-mix(in srgb, color-mix(in srgb, var(--color-success) 68%, var(--color-secondary-800)) 4%, var(--color-surface));
}
.proj-page__data-row--umber td {
  background: color-mix(in srgb, var(--color-umber) 5%, var(--color-surface));
}
.proj-page__data-row--neutral td {
  background: var(--color-surface);
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
  gap: 0.375rem;
  max-width: 100%;
  padding: 0.1875rem 0.625rem;
  border: none;
  border-radius: 0;
  background: var(--chip-bg, var(--color-surface-muted));
  color: var(--chip-fg, var(--color-text));
  font-size: calc(0.75rem * var(--table-zoom, 1));
  font-weight: 600;
  white-space: nowrap;
}

.proj-page__name-labels .proj-label-picker__chip {
  cursor: pointer;
}

.proj-label-picker__chip--sm {
  padding: 0.0625rem 0.5rem;
  font-size: calc(0.6875rem * var(--table-zoom, 1));
}

.proj-label-picker__chip--primary {
  --chip-bg: var(--color-primary-50);
  --chip-fg: var(--color-primary-900);
  --chip-line: var(--color-primary-400);
}
.proj-label-picker__chip--secondary {
  --chip-bg: var(--color-secondary-50);
  --chip-fg: var(--color-secondary-800);
  --chip-line: var(--color-secondary-400);
}
.proj-label-picker__chip--tertiary {
  --chip-bg: var(--color-tertiary-50);
  --chip-fg: var(--color-tertiary-800);
  --chip-line: var(--color-tertiary-400);
}
.proj-label-picker__chip--gold {
  --chip-bg: var(--color-gold-50);
  --chip-fg: var(--color-gold-800);
  --chip-line: var(--color-gold-400);
}
.proj-label-picker__chip--success {
  --chip-bg: var(--color-success-tint-bg);
  --chip-fg: var(--color-success-tint-fg);
  --chip-line: var(--color-success);
}
.proj-label-picker__chip--info {
  --chip-bg: var(--color-info-tint-bg);
  --chip-fg: var(--color-info-tint-fg);
  --chip-line: var(--color-info);
}
.proj-label-picker__chip--warning {
  --chip-bg: var(--color-warning-tint-bg);
  --chip-fg: var(--color-warning-tint-fg);
  --chip-line: var(--color-warning);
}
.proj-label-picker__chip--danger {
  --chip-bg: var(--color-danger-tint-bg);
  --chip-fg: var(--color-danger-tint-fg);
  --chip-line: var(--color-danger);
}
.proj-label-picker__chip--violet {
  --chip-bg: #f3e8ff;
  --chip-fg: #5b21b6;
  --chip-line: #7c3aed;
}

.proj-label-picker__dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--chip-line, var(--color-text-muted));
}

.proj-label-picker__dot--primary {
  background: var(--color-primary);
}
.proj-label-picker__dot--secondary {
  background: var(--color-secondary);
}
.proj-label-picker__dot--tertiary {
  background: var(--color-tertiary);
}
.proj-label-picker__dot--gold {
  background: var(--color-gold);
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
.proj-label-picker__dot--violet {
  background: #7c3aed;
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
  font-size: calc(0.875rem * var(--table-zoom, 1));
}

.proj-page__table thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  overflow: hidden;
  padding: var(--space-3) var(--space-4);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-weight: 600;
  font-size: calc(0.75rem * var(--table-zoom, 1));
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

.proj-page__table tbody td {
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  vertical-align: middle;
  white-space: nowrap;
  overflow: hidden;
  box-shadow: inset 0 -1px 0 var(--color-border);
}

.proj-page__td--name {
  overflow: visible;
  white-space: normal;
  vertical-align: top;
}

.proj-page__td--avatar {
  overflow: visible;
}

.proj-page__td--wrap {
  overflow: visible;
  white-space: normal;
  vertical-align: top;
}

.proj-page__pill-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
  max-width: 100%;
}

.proj-page__table tbody tr {
  cursor: pointer;
}

.proj-page__table tbody tr.proj-page__data-row:hover td {
  filter: brightness(0.97);
}

.proj-page__row--active td {
  background: color-mix(in srgb, var(--color-primary) 8%, var(--color-surface)) !important;
}

.proj-page__row--ctx td {
  background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface)) !important;
}

.proj-page__pill {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  max-width: 100%;
  padding: 0.1875rem 0.5625rem;
  border-radius: 0;
  background: var(--pill-bg, var(--color-surface-muted));
  color: var(--pill-fg, var(--color-text));
  font-size: calc(0.75rem * var(--table-zoom, 1));
  font-weight: 600;
  line-height: 1.3;
}

.proj-page__pill--primary {
  --pill-bg: var(--color-primary-50);
  --pill-fg: var(--color-primary-900);
}
.proj-page__pill--secondary {
  --pill-bg: var(--color-secondary-50);
  --pill-fg: var(--color-secondary-800);
}
.proj-page__pill--tertiary {
  --pill-bg: var(--color-tertiary-50);
  --pill-fg: var(--color-tertiary-800);
}
.proj-page__pill--gold {
  --pill-bg: var(--color-gold-50);
  --pill-fg: var(--color-gold-800);
}
.proj-page__pill--success {
  --pill-bg: var(--color-success-tint-bg);
  --pill-fg: var(--color-success-tint-fg);
}
.proj-page__pill--info {
  --pill-bg: var(--color-info-tint-bg);
  --pill-fg: var(--color-info-tint-fg);
}
.proj-page__pill--warning {
  --pill-bg: var(--color-warning-tint-bg);
  --pill-fg: var(--color-warning-tint-fg);
}
.proj-page__pill--danger {
  --pill-bg: var(--color-danger-tint-bg);
  --pill-fg: var(--color-danger-tint-fg);
}
.proj-page__pill--violet {
  --pill-bg: color-mix(in srgb, var(--color-tertiary-surface) 65%, var(--color-primary-surface));
  --pill-fg: color-mix(in srgb, var(--color-tertiary) 58%, var(--color-primary));
}
.proj-page__pill--teal {
  --pill-bg: color-mix(in srgb, var(--color-secondary-surface) 62%, var(--color-tertiary-surface));
  --pill-fg: color-mix(in srgb, var(--color-secondary) 62%, var(--color-tertiary-800));
}
.proj-page__pill--rose {
  --pill-bg: color-mix(in srgb, var(--color-primary-surface) 62%, var(--color-gold-surface));
  --pill-fg: color-mix(in srgb, var(--color-primary) 55%, var(--color-gold-800));
}
.proj-page__pill--moss {
  --pill-bg: color-mix(in srgb, var(--color-secondary-surface) 62%, var(--color-gold-surface));
  --pill-fg: color-mix(in srgb, var(--color-secondary-800) 55%, var(--color-gold-800));
}
.proj-page__pill--dusk {
  --pill-bg: color-mix(in srgb, var(--color-tertiary-surface) 62%, var(--color-umber-surface));
  --pill-fg: color-mix(in srgb, var(--color-tertiary-800) 68%, var(--color-umber));
}
.proj-page__pill--pine {
  --pill-bg: color-mix(in srgb, var(--color-success-tint-bg) 70%, var(--color-secondary-surface));
  --pill-fg: color-mix(in srgb, var(--color-success-tint-fg) 68%, var(--color-secondary-800));
}
.proj-page__pill--umber {
  --pill-bg: var(--color-umber-surface);
  --pill-fg: var(--color-umber-700);
}
.proj-page__pill--neutral,
.proj-page__pill--code,
.proj-page__pill--date {
  --pill-bg: var(--color-surface-muted);
  --pill-fg: var(--color-text);
}

.proj-page__pill--code {
  font-variant-numeric: tabular-nums;
}

.proj-page__progress-cell {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.proj-page__mini-track {
  display: block;
  width: 3.25rem;
  height: 0.375rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  overflow: hidden;
}

.proj-page__mini-fill {
  display: block;
  height: 100%;
  border-radius: var(--radius-full);
  background: var(--color-tertiary);
}

.proj-page__mini-fill--success {
  background: var(--color-success);
}
.proj-page__mini-fill--tertiary {
  background: var(--color-tertiary);
}
.proj-page__mini-fill--gold {
  background: var(--color-gold);
}
.proj-page__mini-fill--warning {
  background: var(--color-warning);
}
.proj-page__mini-fill--neutral {
  background: var(--color-text-muted);
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

.proj-page__members-cell {
  display: inline-flex;
  align-items: center;
  color: var(--color-text-muted);
}

.proj-page__avatar-stack {
  display: inline-flex;
  align-items: center;
  height: 2.25rem;
  padding: 2px;
  overflow: visible;
}

.proj-page__avatar-stack > :deep(.user-avatar-tip) {
  margin-left: -0.5rem;
  outline: 2px solid var(--color-surface);
  border-radius: var(--radius-full);
}

.proj-page__avatar-stack > :deep(.user-avatar-tip):first-child {
  margin-left: 0;
}

.proj-page__avatar-more {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  margin-left: -0.5rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  outline: 2px solid var(--color-surface);
  font-size: 0.6875rem;
  font-weight: 700;
  cursor: pointer;
}

.proj-page__avatar-more:hover,
.proj-page__avatar-more:focus-visible {
  background: var(--color-primary);
  color: var(--color-on-primary);
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

.proj-page__dot--primary {
  background: var(--color-primary);
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

.proj-page__dot--violet {
  background: #7c3aed;
}

.proj-page__dot--umber {
  background: var(--color-umber);
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
    padding: 0 var(--space-4) var(--space-3);
  }

  .proj-page__header-search input {
    width: 10rem;
  }

  .proj-kanban__col {
    width: 16rem;
  }

  .proj-page__export-grid,
  .proj-page__edit-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .proj-page__dialog {
    padding: var(--space-4);
  }

  .proj-page__dialog-panel {
    width: calc(100vw - 2rem);
    max-width: calc(100vw - 2rem);
    padding: var(--space-3);
  }

  .proj-page__dialog-panel--labels {
    width: calc(100vw - 2rem);
    height: auto;
    max-width: calc(100vw - 2rem);
    max-height: calc(100vh - 2rem);
  }

  .proj-page__dialog-panel--import,
  .proj-page__dialog-panel--preview {
    width: calc(100vw - 2rem);
    max-width: calc(100vw - 2rem);
    height: calc(100vh - 2rem);
    max-height: calc(100vh - 2rem);
  }

  .proj-page__dialog-field {
    grid-template-columns: 1fr;
  }

  .proj-page__export-grid,
  .proj-page__edit-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .proj-page {
    padding: 0 var(--space-3) var(--space-2);
  }

  .proj-page__header-search input {
    width: 8rem;
  }

  .proj-page__gallery {
    grid-template-columns: repeat(2, 1fr);
  }

  .proj-kanban__col {
    width: 13.5rem;
  }

  .proj-view-bar {
    flex-direction: column;
    align-items: flex-start;
  }
}

@media (prefers-reduced-motion: reduce) {
  .proj-page__spin {
    animation: none;
  }

  .proj-dialog-fade-enter-active,
  .proj-dialog-fade-leave-active {
    transition: none;
  }

  .proj-kanban__col,
  .proj-kanban__card,
  .proj-kanban__ghost,
  .proj-kanban__placeholder,
  .proj-kanban__card-grip {
    transition: none;
    animation: none;
  }

  .proj-kanban__card--enter {
    animation: none;
  }
}
</style>
