<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useDonTuConfigHub } from '../../composables/useDonTuConfigHub.js';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { useDragScroll } from '@/composables/useDragScroll.js';
import { showClientToast } from '@/lib/clientToast';
import { formatDonTuApiError } from '../../lib/donTuToast.js';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import DonTuStatusBadge from '../../components/DonTuStatusBadge.vue';
import { createStatus, createStatusSet, deleteStatusSet, fetchStatusSet, fetchStatusSets, updateStatusSet } from '../../api/donTuConfigApi.js';
import {
  ACTION_LABEL,
  ACTOR_LABEL,
  GROUP_KIND_LABEL,
  friendlySetCodeLabel,
  friendlySetName,
  guardHint,
  humanSetDescription,
  statusStepLine,
} from '../../constants/statusTone.js';

const auth = useAuthStore();
const configHub = useDonTuConfigHub();

const canConfig = computed(
  () =>
    auth.can('don_tu.manage_department') ||
    auth.can('don_tu.manage_system') ||
    auth.can('don_tu.*'),
);

const canWrite = computed(
  () => auth.can('don_tu.manage_department') || auth.can('don_tu.manage_system') || auth.can('don_tu.*'),
);

const sets = ref([]);
const setQuery = ref('');
const selectedId = ref(null);
const detail = ref(null);
const loadingList = ref(false);
const loadingDetail = ref(false);
const transitionQuery = ref('');
const focusCode = ref('');
const createOpen = ref(false);
const createSaving = ref(false);
const createForm = ref(emptyStatusForm());
const createSetOpen = ref(false);
const createSetSaving = ref(false);
const createSetForm = ref(emptySetForm());
const codeUnlocked = ref(false);
const codeInput = ref(null);
const templatePreview = ref(null);
const templatePreviewLoading = ref(false);
const previewCache = new Map();
let previewToken = 0;
const setMenu = ref(null);
const setMenuEl = ref(null);
const deleteOpen = ref(false);
const deleteTarget = ref(null);
const deleteSaving = ref(false);
const editSetOpen = ref(false);
const editSetSaving = ref(false);
const editStatusesLoading = ref(false);
const editSetForm = ref(emptyEditSetForm());
const editCodeUnlocked = ref(false);
const editCodeInput = ref(null);

const pipelineWrap = ref(null);
const transWrap = ref(null);
useDragScroll(pipelineWrap, { axis: 'x' });
useDragScroll(transWrap, { axis: 'x' });

const filteredSets = computed(() => {
  const q = setQuery.value.trim().toLowerCase();
  if (!q) return sets.value;
  return sets.value.filter(
    (s) =>
      friendlySetName(s.name, s.code).toLowerCase().includes(q) ||
      (s.name || '').toLowerCase().includes(q) ||
      (s.code || '').toLowerCase().includes(q),
  );
});

const showRail = computed(() => sets.value.length > 0);
const railCollapsed = ref(false);
const RAIL_COLLAPSE_KEY = 'don-tu.status-rail-collapsed';
const viewTab = ref('flow');
const caseLens = ref('all');
const barOpen = ref(false);
const stepDrawerOpen = ref(false);

const mainFlow = computed(() =>
  (detail.value?.statuses ?? []).filter((s) => s.group_kind === 'main').sort((a, b) => a.sort_order - b.sort_order),
);

const ZZ_STEP = 148;
const ZZ_H = 292;

const waveWidth = computed(() => Math.max(mainFlow.value.length, 1) * ZZ_STEP);

const waveD = computed(() => {
  const n = mainFlow.value.length;
  if (n < 2) return '';
  const yUp = 102;
  const yDown = 190;
  let d = '';
  for (let i = 0; i < n - 1; i += 1) {
    const x1 = i * ZZ_STEP + ZZ_STEP / 2;
    const x2 = (i + 1) * ZZ_STEP + ZZ_STEP / 2;
    const y1 = i % 2 === 0 ? yUp : yDown;
    const y2 = i % 2 === 0 ? yDown : yUp;
    const mid = (x1 + x2) / 2;
    d += i === 0 ? `M ${x1} ${y1}` : '';
    d += ` C ${mid} ${y1}, ${mid} ${y2}, ${x2} ${y2}`;
  }
  return d;
});
const conditional = computed(() =>
  (detail.value?.statuses ?? [])
    .filter((s) => s.group_kind === 'conditional')
    .sort((a, b) => a.sort_order - b.sort_order),
);
const terminal = computed(() =>
  (detail.value?.statuses ?? [])
    .filter((s) => s.group_kind === 'terminal')
    .sort((a, b) => a.sort_order - b.sort_order),
);

const FORWARD_ACTIONS = new Set([
  'SAVE_DRAFT',
  'SUBMIT',
  'ACCEPT',
  'APPROVE',
  'CLOSE',
  'SUPPLEMENT',
  'CONFIRM_PAYMENT',
]);
const STOP_ACTIONS = new Set(['REJECT', 'CANCEL', 'REQUEST_CHANGES']);

const trackStages = computed(() => mainFlow.value);

const successEnds = computed(() => terminal.value.filter((status) => status.terminal_kind !== 'failed'));

const cancelEnds = computed(() => terminal.value.filter((status) => status.terminal_kind === 'failed'));

function reachOf(ends) {
  const transitions = detail.value?.transitions ?? [];
  const seen = new Set(ends.map((status) => status.code));
  const queue = [...seen];
  while (queue.length) {
    const code = queue.shift();
    for (const tr of transitions) {
      if (tr.to_code === code && tr.from_code && tr.from_code !== 'NONE' && !seen.has(tr.from_code)) {
        seen.add(tr.from_code);
        queue.push(tr.from_code);
      }
    }
  }
  return seen;
}

const successReach = computed(() => reachOf(successEnds.value));
const cancelReach = computed(() => reachOf(cancelEnds.value));
const successMoveCount = computed(() => successEnds.value.reduce((sum, end) => sum + movesInto(end.code).length, 0));
const cancelMoveCount = computed(() => cancelEnds.value.reduce((sum, end) => sum + movesInto(end.code).length, 0));

function movesInto(code) {
  return (detail.value?.transitions ?? []).filter((tr) => tr.to_code === code);
}

function endingNodes(mine, statuses, toneWhenHit) {
  return statuses.map((status) => {
    const here = mine.filter((tr) => tr.to_code === status.code);
    return {
      code: status.code,
      label: status.label,
      tone: here.length ? toneWhenHit : 'off',
      actions: [...new Set(here.map((tr) => actionLabel(tr.action_key)))],
      from: [...new Set(here.map((tr) => statusLabel(tr.from_code)))],
    };
  });
}

const actorTracks = computed(() => {
  const q = transitionQuery.value.trim().toLowerCase();
  const transitions = detail.value?.transitions ?? [];
  const stages = trackStages.value;
  const order = ['requester', 'assignee', 'closure_actor', 'admin'];
  const actors = order.filter((key) => transitions.some((tr) => tr.actor_policy === key));
  for (const tr of transitions) {
    if (tr.actor_policy && !actors.includes(tr.actor_policy)) actors.push(tr.actor_policy);
  }
  return actors
    .map((actor) => {
      const mine = transitions.filter((tr) => tr.actor_policy === actor);
      const nodes = stages.map((stage) => {
        const here = mine.filter(
          (tr) => tr.from_code === stage.code || (tr.from_code === 'NONE' && tr.to_code === stage.code),
        );
        const keys = here.map((tr) => tr.action_key);
        let tone = 'off';
        if (keys.some((key) => FORWARD_ACTIONS.has(key))) tone = 'go';
        else if (keys.some((key) => STOP_ACTIONS.has(key))) tone = 'hold';
        else if (keys.length) tone = 'go';
        return {
          code: stage.code,
          label: stage.label,
          tone,
          actions: [...new Set(here.map((tr) => actionLabel(tr.action_key)))],
        };
      });
      const done = endingNodes(mine, successEnds.value, 'go');
      const stop = endingNodes(mine, cancelEnds.value, 'hold');
      const lens = caseLens.value;
      let pathNodes = nodes;
      if (lens === 'done' || lens === 'stop') {
        const reach = lens === 'done' ? successReach.value : cancelReach.value;
        const ends = new Set((lens === 'done' ? successEnds.value : cancelEnds.value).map((status) => status.code));
        pathNodes = nodes
          .filter((node) => reach.has(node.code) && !ends.has(node.code))
          .map((node) => {
            const here = mine.filter((tr) => tr.from_code === node.code);
            const hit = here.filter((tr) =>
              lens === 'done' ? reach.has(tr.to_code) && !STOP_ACTIONS.has(tr.action_key) : ends.has(tr.to_code),
            );
            return {
              ...node,
              tone: hit.length ? (lens === 'done' ? 'go' : 'hold') : 'off',
              actions: [...new Set(hit.map((tr) => actionLabel(tr.action_key)))],
            };
          })
          .filter((node) => node.tone !== 'off');
      }
      const doneShown = lens === 'stop' ? [] : done;
      const stopShown = lens === 'done' ? [] : stop;
      const touch = [...pathNodes, ...doneShown, ...stopShown].filter((node) => node.tone !== 'off').length;
      if (lens !== 'all' && touch === 0) return null;
      return {
        actor,
        label: actorLabel(actor),
        touch,
        nodes: pathNodes,
        done: doneShown,
        stop: stopShown,
      };
    })
    .filter(Boolean)
    .filter((row) => {
      if (!q) return true;
      const bits = [row.label];
      for (const node of [...row.nodes, ...row.done, ...row.stop]) {
        bits.push(node.label, ...node.actions, ...(node.from || []));
      }
      return bits.join(' ').toLowerCase().includes(q);
    });
});

const detailTitle = computed(() => friendlySetName(detail.value?.name, detail.value?.code));
const detailSummary = computed(() => {
  const text = humanSetDescription(detail.value?.description);
  if (!text) return '';
  return text.replace(/^(?:[A-ZĐ]{1,6}\.?\d+(?:\/[A-ZĐ]{1,6}\.?\d+)*\s*)+[—–-]\s*/i, '').trim();
});
const fromOptions = computed(() => [
  { code: 'NONE', label: 'Lúc chưa có hồ sơ' },
  ...(detail.value?.statuses ?? []).map((st) => ({ code: st.code, label: st.label })),
]);

async function loadSets() {
  loadingList.value = true;
  try {
    sets.value = await fetchStatusSets();
    if (!selectedId.value && sets.value.length) {
      selectedId.value = sets.value[0].id;
    }
    if (selectedId.value && !sets.value.some((s) => s.id === selectedId.value)) {
      selectedId.value = sets.value[0]?.id ?? null;
    }
  } catch (err) {
    showClientToast('error', err.response?.data?.message || 'Không tải được danh sách trạng thái.');
  } finally {
    loadingList.value = false;
  }
}

async function loadDetail(id) {
  if (!id) {
    detail.value = null;
    return;
  }
  loadingDetail.value = true;
  transitionQuery.value = '';
  focusCode.value = '';
  stepDrawerOpen.value = false;
  caseLens.value = 'all';
  try {
    detail.value = await fetchStatusSet(id);
  } catch (err) {
    showClientToast('error', err.response?.data?.message || 'Không tải được chi tiết trạng thái.');
    detail.value = null;
  } finally {
    loadingDetail.value = false;
  }
}

watch(selectedId, (id) => loadDetail(id));

function syncHeaderPrimary() {
  if (!configHub || !canWrite.value) {
    configHub?.clearPrimaryAction();
    return;
  }
  configHub.setPrimaryAction({
    label: 'Tạo bộ trạng thái',
    icon: 'plus',
    onClick: () => openCreateSet(),
  });
}

watch(canWrite, syncHeaderPrimary, { immediate: true });

function closeSetMenu() {
  setMenu.value = null;
}

function openSetMenu(event, set) {
  if (!canWrite.value) return;
  if (setMenu.value?.set?.id === set.id) {
    closeSetMenu();
    return;
  }
  const pad = 8;
  const menuW = 188;
  const menuH = 132;
  const rect = event.currentTarget?.getBoundingClientRect?.();
  const rawX = rect ? rect.right - menuW : event.clientX;
  const rawY = rect ? rect.bottom + 4 : event.clientY;
  setMenu.value = {
    set,
    x: Math.min(Math.max(pad, rawX), Math.max(pad, window.innerWidth - menuW - pad)),
    y: Math.min(Math.max(pad, rawY), Math.max(pad, window.innerHeight - menuH - pad)),
  };
}

async function addStatusToSet(set) {
  closeSetMenu();
  if (!set?.id) return;
  if (selectedId.value !== set.id || detail.value?.id !== set.id) {
    selectedId.value = set.id;
    await loadDetail(set.id);
  }
  openCreate();
}

function onDocPointerDown(event) {
  if (!setMenu.value) return;
  if (setMenuEl.value?.contains(event.target)) return;
  if (event.target?.closest?.('.dt-set-row__more')) return;
  closeSetMenu();
}

function onDocKeydown(event) {
  if (event.key === 'Escape') closeSetMenu();
}

function askDeleteSet(set) {
  closeSetMenu();
  if (set.is_system) {
    showClientToast('error', 'Bộ trạng thái hệ thống không xóa được.');
    return;
  }
  if ((set.workflow_count ?? 0) > 0) {
    showClientToast('error', 'Bộ đang được quy trình dùng. Gỡ quy trình trước khi xóa.');
    return;
  }
  deleteTarget.value = set;
  deleteOpen.value = true;
}

async function confirmDeleteSet() {
  const item = deleteTarget.value;
  if (!item || deleteSaving.value) return;
  deleteSaving.value = true;
  try {
    await deleteStatusSet(item.id);
    previewCache.delete(item.id);
    showClientToast('success', 'Đã xóa bộ trạng thái.');
    deleteOpen.value = false;
    deleteTarget.value = null;
    await loadSets();
  } catch (err) {
    showClientToast('error', formatDonTuApiError(err, 'Không xóa được bộ trạng thái.'));
  } finally {
    deleteSaving.value = false;
  }
}

onMounted(async () => {
  document.addEventListener('pointerdown', onDocPointerDown);
  document.addEventListener('keydown', onDocKeydown);
  window.addEventListener('scroll', closeSetMenu, true);
  try {
    railCollapsed.value = localStorage.getItem(RAIL_COLLAPSE_KEY) === '1';
  } catch {
    railCollapsed.value = false;
  }
  configHub?.registerReload(reloadPage);
  if (!canConfig.value) {
    return;
  }
  await loadSets();
});

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onDocPointerDown);
  document.removeEventListener('keydown', onDocKeydown);
  window.removeEventListener('scroll', closeSetMenu, true);
  configHub?.unregisterReload();
  configHub?.clearPrimaryAction();
});

function toggleRail() {
  railCollapsed.value = !railCollapsed.value;
  try {
    localStorage.setItem(RAIL_COLLAPSE_KEY, railCollapsed.value ? '1' : '0');
  } catch {
    /* giữ trạng thái trong phiên */
  }
}

async function reloadPage() {
  await loadSets();
  if (selectedId.value) {
    await loadDetail(selectedId.value);
  }
}

function actionLabel(key) {
  return ACTION_LABEL[key] || key;
}

function actorLabel(key) {
  return ACTOR_LABEL[key] || key;
}

function statusLabel(code) {
  if (!code || code === 'NONE') return 'Lúc chưa có hồ sơ';
  return detail.value?.statuses?.find((s) => s.code === code)?.label || code;
}

const drawerTone = computed(() => {
  const status = focusStatus.value;
  if (!status) return 'main';
  if (status.group_kind === 'terminal') return status.terminal_kind === 'failed' ? 'fail' : 'done';
  if (status.group_kind === 'conditional') return 'need';
  return 'main';
});

const drawerPlace = computed(() => {
  const list = orderedSteps.value;
  const index = list.findIndex((status) => status.code === focusCode.value);
  if (index < 0) return '';
  return `${index + 1}/${list.length}`;
});

const focusStatus = computed(
  () => detail.value?.statuses?.find((status) => status.code === focusCode.value) ?? null,
);

const focusInbound = computed(() =>
  (detail.value?.transitions ?? []).filter((tr) => tr.to_code === focusCode.value),
);

const focusOutbound = computed(() =>
  (detail.value?.transitions ?? []).filter((tr) => tr.from_code === focusCode.value),
);

const linkedCodes = computed(() => {
  if (!focusCode.value) return null;
  const codes = new Set([focusCode.value]);
  for (const tr of detail.value?.transitions ?? []) {
    if (tr.from_code === focusCode.value && tr.to_code) codes.add(tr.to_code);
    if (tr.to_code === focusCode.value && tr.from_code && tr.from_code !== 'NONE') codes.add(tr.from_code);
  }
  return codes;
});

function stepClass(code) {
  const linked = linkedCodes.value;
  if (linked) {
    return {
      'is-on': code === focusCode.value,
      'is-link': code !== focusCode.value && linked.has(code),
      'is-dim': !linked.has(code),
    };
  }
  if (caseLens.value === 'done') {
    const onPath = successReach.value.has(code);
    return { 'is-case-done': onPath, 'is-dim': !onPath };
  }
  if (caseLens.value === 'stop') {
    const onPath = cancelReach.value.has(code);
    return { 'is-case-stop': onPath, 'is-dim': !onPath };
  }
  return {};
}

function setCaseLens(next) {
  caseLens.value = next;
  focusCode.value = '';
  stepDrawerOpen.value = false;
}

function toggleFocus(code) {
  if (focusCode.value === code && stepDrawerOpen.value) {
    closeStepDrawer();
    return;
  }
  focusCode.value = code;
  stepDrawerOpen.value = true;
}

function closeStepDrawer() {
  stepDrawerOpen.value = false;
  focusCode.value = '';
}

function openRules() {
  viewTab.value = 'rules';
  stepDrawerOpen.value = false;
}

function scrollFlow(direction) {
  pipelineWrap.value?.scrollBy({ left: direction * ZZ_STEP * 2, behavior: 'smooth' });
}

const orderedSteps = computed(() => [...mainFlow.value, ...conditional.value, ...terminal.value]);

function moveFocus(delta) {
  const list = orderedSteps.value;
  if (!list.length) return;
  const index = list.findIndex((status) => status.code === focusCode.value);
  const nextIndex = index < 0 ? (delta > 0 ? 0 : list.length - 1) : index + delta;
  const next = list[(nextIndex + list.length) % list.length];
  focusCode.value = next.code;
}

function nextBmttCode(list) {
  let max = 0;
  for (const set of list || []) {
    const match = /^BMTT(\d+)$/i.exec(String(set.code || '').trim());
    if (match) max = Math.max(max, Number.parseInt(match[1], 10));
  }
  return `BMTT${String(max + 1).padStart(2, '0')}`;
}

function emptySetForm() {
  return {
    name: '',
    code: '',
    description: '',
    use_template: false,
    copy_from_id: '',
  };
}

function emptyEditSetForm() {
  return {
    id: null,
    name: '',
    code: '',
    description: '',
    is_system: false,
    statuses: [],
  };
}

function mapEditStatuses(statuses) {
  return (statuses ?? [])
    .slice()
    .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
    .map((status) => ({
      id: status.id,
      label: status.label || '',
      group_kind: status.group_kind || 'main',
      terminal_kind: status.terminal_kind === 'failed' ? 'failed' : 'success',
    }));
}

function emptyStatusForm() {
  return {
    label: '',
    group_kind: 'main',
    terminal_kind: 'success',
    from_code: '',
    action_key: 'APPROVE',
    actor_policy: 'assignee',
  };
}

function openCreate() {
  createForm.value = emptyStatusForm();
  createOpen.value = true;
}

const previewGroups = computed(() => {
  const statuses = templatePreview.value?.statuses ?? [];
  return ['main', 'conditional', 'terminal']
    .map((kind) => ({
      kind,
      label: GROUP_KIND_LABEL[kind],
      items: statuses
        .filter((status) => status.group_kind === kind)
        .sort((a, b) => a.sort_order - b.sort_order),
    }))
    .filter((group) => group.items.length);
});

const previewDescription = computed(() => humanSetDescription(templatePreview.value?.description));

const previewStats = computed(() => {
  if (!templatePreview.value) return null;
  return {
    statuses: templatePreview.value.statuses?.length ?? 0,
    transitions: templatePreview.value.transitions?.length ?? 0,
  };
});

const createSetFootSummary = computed(() => {
  const name = createSetForm.value.name.trim() || '—';
  const code = createSetForm.value.code.trim().toUpperCase() || '—';
  if (createSetForm.value.use_template) {
    const picked = sets.value.find((s) => String(s.id) === String(createSetForm.value.copy_from_id));
    if (!picked) {
      return `Bộ «${name}» · mã ${code} · chọn mẫu để sao chép trạng thái và quy tắc.`;
    }
    if (previewStats.value) {
      return `Bộ «${name}» · mã ${code} · ${previewStats.value.statuses} trạng thái, ${previewStats.value.transitions} quy tắc từ «${friendlySetName(picked.name, picked.code)}».`;
    }
    return `Bộ «${name}» · mã ${code} · sao chép từ «${friendlySetName(picked.name, picked.code)}».`;
  }
  return `Bộ «${name}» · mã ${code} · bắt đầu trống, thêm trạng thái sau khi tạo.`;
});

function setCreateInitMode(useTemplate) {
  createSetForm.value.use_template = useTemplate;
  if (useTemplate && createSetForm.value.copy_from_id === '' && sets.value.length) {
    const preferred = sets.value.find((s) => s.is_system) || sets.value[0];
    createSetForm.value.copy_from_id = String(preferred.id);
  }
}

function previewTone(status) {
  if (status.group_kind === 'terminal') {
    return status.terminal_kind === 'failed' ? 'danger' : 'success';
  }
  if (status.group_kind === 'conditional') return 'info';
  return 'primary';
}

function openCreateSet() {
  createSetForm.value = {
    ...emptySetForm(),
    code: nextBmttCode(sets.value),
  };
  codeUnlocked.value = false;
  templatePreview.value = null;
  templatePreviewLoading.value = false;
  createSetOpen.value = true;
}

async function openEditSet(set) {
  closeSetMenu();
  if (!set?.id) return;
  const cached = detail.value?.id === set.id ? detail.value : null;
  editSetForm.value = {
    id: set.id,
    name: (cached?.name ?? set.name) || '',
    code: (cached?.code ?? set.code) || '',
    description: (cached?.description ?? set.description) || '',
    is_system: !!(cached?.is_system ?? set.is_system),
    statuses: cached ? mapEditStatuses(cached.statuses) : [],
  };
  editCodeUnlocked.value = false;
  editSetOpen.value = true;
  if (cached) return;
  editStatusesLoading.value = true;
  try {
    const item = await fetchStatusSet(set.id);
    if (!editSetOpen.value || editSetForm.value.id !== set.id) return;
    editSetForm.value = {
      id: item.id,
      name: item.name || '',
      code: item.code || '',
      description: item.description || '',
      is_system: !!item.is_system,
      statuses: mapEditStatuses(item.statuses),
    };
  } catch (err) {
    showClientToast('error', formatDonTuApiError(err, 'Không tải được trạng thái của bộ.'));
    editSetOpen.value = false;
  } finally {
    editStatusesLoading.value = false;
  }
}

function onEditStatusGroup(status) {
  if (status.group_kind === 'terminal' && status.terminal_kind !== 'success' && status.terminal_kind !== 'failed') {
    status.terminal_kind = 'success';
  }
}

function moveEditStatus(index, delta) {
  const next = index + delta;
  const list = editSetForm.value.statuses;
  if (next < 0 || next >= list.length) return;
  const copy = list.slice();
  const [row] = copy.splice(index, 1);
  copy.splice(next, 0, row);
  editSetForm.value.statuses = copy;
}

async function toggleEditCodeLock() {
  if (editSetForm.value.is_system) return;
  if (!editCodeUnlocked.value) {
    editCodeUnlocked.value = true;
    await nextTick();
    editCodeInput.value?.focus();
    editCodeInput.value?.select();
    return;
  }
  const code = editSetForm.value.code.trim().toUpperCase();
  editSetForm.value.code = code || editSetForm.value.code;
  editCodeUnlocked.value = false;
}

function onEditCodeInput(event) {
  editSetForm.value.code = event.target.value.toUpperCase();
}

async function submitEditSet() {
  if (!editSetForm.value.id) return;
  if (!editSetForm.value.name.trim()) {
    showClientToast('error', 'Nhập tên nhóm trạng thái.');
    return;
  }
  const code = editSetForm.value.code.trim().toUpperCase();
  if (editSetForm.value.statuses.some((status) => !status.label.trim())) {
    showClientToast('error', 'Nhập tên cho từng trạng thái.');
    return;
  }
  if (!editSetForm.value.is_system) {
    if (!/^[A-Z][A-Z0-9_]{1,63}$/.test(code)) {
      showClientToast('error', 'Mã bộ trạng thái chỉ gồm chữ, số và dấu gạch dưới.');
      return;
    }
    if (sets.value.some((set) => set.id !== editSetForm.value.id && String(set.code || '').toUpperCase() === code)) {
      showClientToast('error', 'Mã bộ trạng thái đã được dùng.');
      return;
    }
  }
  editSetSaving.value = true;
  try {
    const payload = {
      name: editSetForm.value.name.trim(),
      description: editSetForm.value.description.trim() || null,
      statuses: editSetForm.value.statuses.map((status, index) => ({
        id: status.id,
        label: status.label.trim(),
        group_kind: status.group_kind,
        terminal_kind: status.group_kind === 'terminal' ? status.terminal_kind : null,
        sort_order: index + 1,
      })),
    };
    if (!editSetForm.value.is_system) payload.code = code;
    const item = await updateStatusSet(editSetForm.value.id, payload);
    editSetOpen.value = false;
    await loadSets();
    if (selectedId.value === item.id) {
      detail.value = item;
    } else {
      selectedId.value = item.id;
    }
    showClientToast('success', 'Đã lưu bộ trạng thái.');
  } catch (err) {
    showClientToast('error', formatDonTuApiError(err, 'Không lưu được bộ trạng thái.'));
  } finally {
    editSetSaving.value = false;
  }
}

async function toggleCodeLock() {
  if (!codeUnlocked.value) {
    codeUnlocked.value = true;
    await nextTick();
    codeInput.value?.focus();
    codeInput.value?.select();
    return;
  }
  const code = createSetForm.value.code.trim().toUpperCase();
  createSetForm.value.code = code || nextBmttCode(sets.value);
  codeUnlocked.value = false;
}

function onCodeInput(event) {
  createSetForm.value.code = event.target.value.toUpperCase();
}

async function loadTemplatePreview() {
  const token = ++previewToken;
  const useTemplate = createSetForm.value.use_template;
  const rawId = createSetForm.value.copy_from_id;
  if (!createSetOpen.value || !useTemplate || rawId === '' || rawId == null) {
    templatePreview.value = null;
    templatePreviewLoading.value = false;
    return;
  }
  const id = Number(rawId);
  if (previewCache.has(id)) {
    templatePreview.value = previewCache.get(id);
    templatePreviewLoading.value = false;
    return;
  }
  templatePreviewLoading.value = true;
  templatePreview.value = null;
  try {
    const item = await fetchStatusSet(id);
    previewCache.set(id, item);
    if (token === previewToken) templatePreview.value = item;
  } catch (err) {
    if (token === previewToken) {
      templatePreview.value = null;
      showClientToast('error', err.response?.data?.message || 'Không tải được mẫu trạng thái.');
    }
  } finally {
    if (token === previewToken) templatePreviewLoading.value = false;
  }
}

watch(
  () => [createSetOpen.value, createSetForm.value.use_template, createSetForm.value.copy_from_id],
  () => {
    if (!createSetForm.value.use_template && createSetForm.value.copy_from_id !== '') {
      createSetForm.value.copy_from_id = '';
      return;
    }
    loadTemplatePreview();
  },
);

async function submitCreateSet() {
  if (!createSetForm.value.name.trim()) {
    showClientToast('error', 'Nhập tên nhóm trạng thái.');
    return;
  }
  const code = createSetForm.value.code.trim().toUpperCase();
  if (!/^[A-Z][A-Z0-9_]{1,63}$/.test(code)) {
    showClientToast('error', 'Mã bộ trạng thái chỉ gồm chữ, số và dấu gạch dưới.');
    return;
  }
  if (sets.value.some((set) => String(set.code || '').toUpperCase() === code)) {
    showClientToast('error', 'Mã bộ trạng thái đã được dùng.');
    return;
  }
  if (createSetForm.value.use_template && (createSetForm.value.copy_from_id === '' || createSetForm.value.copy_from_id == null)) {
    showClientToast('error', 'Chọn mẫu có sẵn.');
    return;
  }
  createSetSaving.value = true;
  try {
    const payload = {
      name: createSetForm.value.name.trim(),
      code,
      description: createSetForm.value.description.trim() || null,
    };
    if (createSetForm.value.use_template) {
      payload.copy_from_id = Number(createSetForm.value.copy_from_id);
    }
    const item = await createStatusSet(payload);
    createSetOpen.value = false;
    await loadSets();
    selectedId.value = item.id;
    detail.value = item;
    showClientToast('success', 'Đã tạo nhóm trạng thái.');
  } catch (err) {
    showClientToast('error', formatDonTuApiError(err, 'Không tạo được nhóm trạng thái.'));
  } finally {
    createSetSaving.value = false;
  }
}

async function submitCreate() {
  if (!selectedId.value) return;
  if (!createForm.value.label.trim()) {
    showClientToast('error', 'Nhập tên trạng thái.');
    return;
  }
  if (!createForm.value.from_code) {
    showClientToast('error', 'Chọn trạng thái phía trước.');
    return;
  }
  createSaving.value = true;
  try {
    const item = await createStatus(selectedId.value, {
      ...createForm.value,
      label: createForm.value.label.trim(),
      terminal_kind: createForm.value.group_kind === 'terminal' ? createForm.value.terminal_kind : null,
    });
    detail.value = item;
    const row = sets.value.find((s) => s.id === selectedId.value);
    if (row) row.status_count = item.statuses?.length ?? row.status_count;
    createOpen.value = false;
    showClientToast('success', 'Đã thêm trạng thái.');
  } catch (err) {
    showClientToast('error', formatDonTuApiError(err, 'Không thêm được trạng thái.'));
  } finally {
    createSaving.value = false;
  }
}
</script>

<template>
  <div v-if="!canConfig" class="dt-config-deny">
    <AppIcon name="lock" :size="28" :stroke-width="1.75" />
    <p>Bạn cần quyền cấu hình đơn từ của phòng để xem trang này.</p>
  </div>

  <div v-else class="dt-setup">
    <div v-if="!loadingList && !sets.length" class="dt-status-page__empty-panel">
      <AppIcon name="layers" :size="32" :stroke-width="1.75" />
      <h2 class="dt-status-page__empty-title">Chưa có bộ trạng thái</h2>
      <p class="dt-status-page__empty-lead">
        Bộ trạng thái quy định hồ sơ đơn đi qua những bước nào — tạo bộ đầu tiên để khai báo trạng thái.
      </p>
      <button v-if="canWrite" type="button" class="dt-btn-primary" @click="openCreateSet">Tạo bộ trạng thái</button>
    </div>

    <div
      v-else
      class="dt-setup__layout"
      :class="{
        'dt-setup__layout--solo': !showRail,
        'dt-setup__layout--rail-collapsed': showRail && railCollapsed,
      }"
    >
      <aside v-if="showRail" class="dt-set-rail" :class="{ 'dt-set-rail--collapsed': railCollapsed }">
        <div class="dt-set-rail__top">
          <h3 v-show="!railCollapsed" class="dt-set-rail__title">Bộ trạng thái</h3>
          <button v-if="canWrite && !railCollapsed" type="button" class="dt-link-btn dt-set-rail__create" @click="openCreateSet">
            Tạo mới
          </button>
          <button
            type="button"
            class="dt-set-rail__toggle"
            :aria-expanded="!railCollapsed"
            :aria-label="railCollapsed ? 'Mở danh sách bộ trạng thái' : 'Thu gọn danh sách bộ trạng thái'"
            @click="toggleRail"
          >
            <AppIcon :name="railCollapsed ? 'chevronRight' : 'chevronLeft'" :size="16" :stroke-width="2" />
          </button>
        </div>

        <template v-if="!railCollapsed">
        <label class="dt-set-rail__search">
          <AppIcon name="search" :size="16" :stroke-width="1.75" aria-hidden="true" />
          <input
            v-model="setQuery"
            type="search"
            placeholder="Tìm…"
            aria-label="Tìm bộ trạng thái"
          />
        </label>

        <ul v-if="!loadingList" class="dt-set-rail__list hide-scrollbar">
          <li v-if="!filteredSets.length" class="dt-set-rail__empty">
            <p>Không có kết quả.</p>
          </li>
          <li v-for="set in filteredSets" :key="set.id" class="dt-set-row" :class="{ 'dt-set-row--active': selectedId === set.id }">
            <button
              type="button"
              class="dt-set-pick"
              :class="{ 'dt-set-pick--active': selectedId === set.id }"
              :aria-current="selectedId === set.id ? 'true' : undefined"
              @click="selectedId = set.id"
              @contextmenu.prevent="openSetMenu($event, set)"
            >
              <span class="dt-set-pick__main">
                <span class="dt-set-pick__name">{{ friendlySetName(set.name, set.code) }}</span>
                <span v-if="friendlySetCodeLabel(set.code)" class="dt-set-pick__sub">
                  {{ friendlySetCodeLabel(set.code) }}
                </span>
              </span>
              <span class="dt-set-pick__count">{{ set.status_count }} bước</span>
            </button>
            <button
              v-if="canWrite"
              type="button"
              class="dt-set-row__more"
              aria-label="Thao tác"
              :aria-expanded="setMenu?.set?.id === set.id"
              @click.stop="openSetMenu($event, set)"
            >
              <AppIcon name="moreVertical" :size="16" :stroke-width="1.75" />
            </button>
          </li>
        </ul>
        <p v-else class="dt-set-rail__loading">Đang tải…</p>
        </template>
      </aside>

      <div v-if="loadingDetail" class="dt-status-page__loading dt-setup__main">
        Đang tải…
      </div>

      <div v-else-if="detail" class="dt-setup__main">
        <section class="dt-setup__group">
          <header class="dt-setup__bar">
            <button
              type="button"
              class="dt-setup__bar-toggle"
              :aria-expanded="barOpen"
              :aria-label="barOpen ? 'Thu gọn tóm tắt bộ' : 'Mở tóm tắt bộ'"
              @click="barOpen = !barOpen"
            >
              <AppIcon :name="barOpen ? 'chevronDown' : 'chevronRight'" :size="16" :stroke-width="2" aria-hidden="true" />
              <span class="dt-setup__hero-title">{{ detailTitle }}</span>
            </button>
            <div class="dt-setup__seg dt-setup__seg--view" role="tablist" aria-label="Xem thiết lập">
              <button
                type="button"
                role="tab"
                class="dt-setup__seg-btn"
                :class="{ 'dt-setup__seg-btn--on': viewTab === 'flow' }"
                :aria-selected="viewTab === 'flow'"
                @click="viewTab = 'flow'"
              >
                <AppIcon name="gitBranch" :size="15" :stroke-width="1.75" aria-hidden="true" />
                Sơ đồ các bước
              </button>
              <button
                type="button"
                role="tab"
                class="dt-setup__seg-btn"
                :class="{ 'dt-setup__seg-btn--on': viewTab === 'rules' }"
                :aria-selected="viewTab === 'rules'"
                @click="viewTab = 'rules'"
              >
                <AppIcon name="users" :size="15" :stroke-width="1.75" aria-hidden="true" />
                Người phụ trách
              </button>
            </div>
          </header>
          <div v-show="barOpen" class="dt-setup__bar-more">
            <p class="dt-setup__metrics" aria-label="Thống kê bộ trạng thái">
              <span class="dt-setup__metric">
                <span class="dt-setup__metric-num">{{ detail.statuses?.length ?? 0 }}</span>
                bước
              </span>
              <span class="dt-setup__metric-dot" aria-hidden="true" />
              <span class="dt-setup__metric">
                <span class="dt-setup__metric-num">{{ detail.transitions?.length ?? 0 }}</span>
                lần chuyển
              </span>
            </p>
            <p v-if="detailSummary" class="dt-setup__bar-summary">{{ detailSummary }}</p>
          </div>

          <div v-show="viewTab === 'flow'" class="dt-setup__group-body">
          <p v-if="!(detail.statuses?.length)" class="dt-status-page__empty">Chưa có trạng thái.</p>
          <div v-else class="dt-flow-board" @keydown.left.prevent="moveFocus(-1)" @keydown.right.prevent="moveFocus(1)">
            <div class="dt-case-switch" role="tablist" aria-label="Hướng kết thúc">
              <button type="button" role="tab" class="dt-case-switch__btn" :class="{ 'is-on': caseLens === 'all' }" :aria-selected="caseLens === 'all'" @click="setCaseLens('all')">
                Cả hai hướng
              </button>
              <button type="button" role="tab" class="dt-case-switch__btn is-done" :class="{ 'is-on': caseLens === 'done' }" :aria-selected="caseLens === 'done'" @click="setCaseLens('done')">
                Khi thành công
                <span>{{ successMoveCount }}</span>
              </button>
              <button type="button" role="tab" class="dt-case-switch__btn is-stop" :class="{ 'is-on': caseLens === 'stop' }" :aria-selected="caseLens === 'stop'" @click="setCaseLens('stop')">
                Khi hủy
                <span>{{ cancelMoveCount }}</span>
              </button>
            </div>
            <div v-if="mainFlow.length" class="dt-zz-wrap">
              <button
                v-if="mainFlow.length > 4"
                type="button"
                class="dt-zz-nav dt-zz-nav--prev"
                aria-label="Xem bước phía trước"
                @click="scrollFlow(-1)"
              >
                <AppIcon name="chevronLeft" :size="18" :stroke-width="2" />
              </button>
              <div ref="pipelineWrap" class="dt-zz hide-scrollbar">
              <ol class="dt-zz__track" :style="{ width: `${waveWidth}px`, height: `${ZZ_H}px` }">
                <svg
                  v-if="waveD"
                  class="dt-zz__wave"
                  :width="waveWidth"
                  :height="ZZ_H"
                  :viewBox="`0 0 ${waveWidth} ${ZZ_H}`"
                  aria-hidden="true"
                >
                  <defs>
                    <linearGradient :id="`dt-zz-stroke-${detail.id}`" x1="0" y1="0" x2="1" y2="0">
                      <stop offset="0%" stop-color="var(--color-primary)" />
                      <stop offset="100%" stop-color="var(--color-secondary)" />
                    </linearGradient>
                  </defs>
                  <path :d="waveD" class="dt-zz__wave-soft" />
                  <path :d="waveD" class="dt-zz__wave-line" :stroke="`url(#dt-zz-stroke-${detail.id})`" />
                </svg>
                <li
                  v-for="(st, idx) in mainFlow"
                  :key="st.code"
                  class="dt-zz__step"
                  :class="[idx % 2 === 0 ? 'is-up' : 'is-down', stepClass(st.code)]"
                >
                  <button
                    type="button"
                    class="dt-zz__hit"
                    :aria-pressed="focusCode === st.code"
                    @click="toggleFocus(st.code)"
                  >
                    <span class="dt-zz__copy">
                      <span class="dt-zz__title">{{ st.label }}</span>
                      <span v-if="statusStepLine(st.code)" class="dt-zz__sub">{{ statusStepLine(st.code) }}</span>
                    </span>
                    <span class="dt-zz__node">
                      {{ String(idx + 1).padStart(2, '0') }}
                    </span>
                  </button>
                </li>
              </ol>
              </div>
              <button
                v-if="mainFlow.length > 4"
                type="button"
                class="dt-zz-nav dt-zz-nav--next"
                aria-label="Xem bước phía sau"
                @click="scrollFlow(1)"
              >
                <AppIcon name="chevronRight" :size="18" :stroke-width="2" />
              </button>
            </div>

            <div v-if="successEnds.length || cancelEnds.length" class="dt-ends" :class="{ 'dt-ends--solo': caseLens !== 'all' }">
              <section v-if="caseLens !== 'stop'" class="dt-ends__case dt-ends__case--done">
                <h3>Khi thành công</h3>
                <p v-if="!successEnds.length" class="dt-ends__empty">Chưa có bước thành công.</p>
                <article v-for="end in successEnds" :key="end.code" class="dt-ends__card">
                  <button type="button" class="dt-ends__name" @click="toggleFocus(end.code)">{{ end.label }}</button>
                  <ul v-if="movesInto(end.code).length" class="dt-end-moves">
                    <li v-for="tr in movesInto(end.code)" :key="`ok-${tr.id}`">
                      <button type="button" class="dt-end-move" @click="toggleFocus(tr.from_code === 'NONE' ? end.code : tr.from_code)">
                        <span class="dt-end-move__who" :class="`is-${tr.actor_policy}`">{{ actorLabel(tr.actor_policy) }}</span>
                        <span class="dt-end-move__do">{{ actionLabel(tr.action_key) }}</span>
                        <span class="dt-end-move__from">từ {{ statusLabel(tr.from_code) }}</span>
                      </button>
                    </li>
                  </ul>
                  <p v-else class="dt-ends__empty">Chưa có lối vào bước này.</p>
                </article>
              </section>
              <section v-if="caseLens !== 'done'" class="dt-ends__case dt-ends__case--fail">
                <h3>Khi hủy</h3>
                <p v-if="!cancelEnds.length" class="dt-ends__empty">Chưa có bước hủy.</p>
                <article v-for="end in cancelEnds" :key="end.code" class="dt-ends__card">
                  <button type="button" class="dt-ends__name" @click="toggleFocus(end.code)">{{ end.label }}</button>
                  <ul v-if="movesInto(end.code).length" class="dt-end-moves">
                    <li v-for="tr in movesInto(end.code)" :key="`no-${tr.id}`">
                      <button type="button" class="dt-end-move" @click="toggleFocus(tr.from_code === 'NONE' ? end.code : tr.from_code)">
                        <span class="dt-end-move__who" :class="`is-${tr.actor_policy}`">{{ actorLabel(tr.actor_policy) }}</span>
                        <span class="dt-end-move__do">{{ actionLabel(tr.action_key) }}</span>
                        <span class="dt-end-move__from">từ {{ statusLabel(tr.from_code) }}</span>
                      </button>
                    </li>
                  </ul>
                  <p v-else class="dt-ends__empty">Chưa có lối vào bước này.</p>
                </article>
              </section>
            </div>

          </div>
          </div>

          <div v-show="viewTab === 'rules'" class="dt-setup__group-body dt-track-wrap">
            <header class="dt-track__head">
              <div class="dt-track__head-copy">
                <h3 class="dt-track__title">Người phụ trách từng bước</h3>
                <ul class="dt-track__legend">
                  <li><i class="is-go" /> Được đi tiếp</li>
                  <li><i class="is-hold" /> Được dừng</li>
                  <li><i class="is-off" /> Không việc</li>
                </ul>
              </div>
              <div class="dt-track__tools">
                <div class="dt-case-switch" role="tablist" aria-label="Hướng kết thúc">
                  <button type="button" role="tab" class="dt-case-switch__btn" :class="{ 'is-on': caseLens === 'all' }" :aria-selected="caseLens === 'all'" @click="setCaseLens('all')">
                    Cả hai hướng
                  </button>
                  <button type="button" role="tab" class="dt-case-switch__btn is-done" :class="{ 'is-on': caseLens === 'done' }" :aria-selected="caseLens === 'done'" @click="setCaseLens('done')">
                    Khi thành công
                  </button>
                  <button type="button" role="tab" class="dt-case-switch__btn is-stop" :class="{ 'is-on': caseLens === 'stop' }" :aria-selected="caseLens === 'stop'" @click="setCaseLens('stop')">
                    Khi hủy
                  </button>
                </div>
                <button v-if="focusCode" type="button" class="dt-link-btn" @click="focusCode = ''">
                  Xem mọi bước
                </button>
                <label class="dt-track__search">
                  <AppIcon name="search" :size="16" :stroke-width="1.75" aria-hidden="true" />
                  <input
                    v-model="transitionQuery"
                    type="search"
                    placeholder="Tìm vai hoặc bước"
                    aria-label="Tìm vai hoặc bước"
                  />
                </label>
              </div>
            </header>
            <div ref="transWrap" class="dt-track hide-scrollbar">
              <p v-if="!actorTracks.length" class="dt-status-page__empty">
                {{ caseLens === 'all' ? 'Không có vai nào khớp.' : 'Không có vai nào làm hướng này.' }}
              </p>
              <article v-for="row in actorTracks" :key="row.actor" class="dt-track__row">
                <div class="dt-track__who">
                  <span class="dt-track__avatar" aria-hidden="true">
                    <AppIcon name="user" :size="18" :stroke-width="1.75" />
                  </span>
                  <span class="dt-track__who-copy">
                    <span class="dt-track__name">{{ row.label }}</span>
                    <span class="dt-track__meta">Làm ở {{ row.touch }} bước</span>
                  </span>
                </div>
                <div class="dt-track__line" :style="{ '--dt-steps': row.nodes.length }">
                  <button
                    v-for="node in row.nodes"
                    :key="`${row.actor}-${node.code}`"
                    type="button"
                    class="dt-track__node"
                    :class="{ 'is-dim': focusCode && focusCode !== node.code, 'is-on': focusCode === node.code }"
                    @click="toggleFocus(node.code)"
                  >
                    <span class="dt-track__dot" :class="`is-${node.tone}`" aria-hidden="true">
                      <AppIcon v-if="node.tone === 'go'" name="check" :size="14" :stroke-width="2.5" />
                    </span>
                    <span class="dt-track__cap">{{ node.label }}</span>
                    <span v-if="node.actions.length" class="dt-track__act">{{ node.actions.join(' · ') }}</span>
                  </button>
                  <div v-if="caseLens !== 'stop'" class="dt-track__case dt-track__case--done">
                    <span class="dt-track__case-kicker">Khi thành công</span>
                    <p v-if="!row.done.length" class="dt-track__case-empty">Chưa có</p>
                    <button
                      v-for="node in row.done"
                      :key="`${row.actor}-done-${node.code}`"
                      type="button"
                      class="dt-track__node"
                      :class="{ 'is-dim': focusCode && focusCode !== node.code, 'is-on': focusCode === node.code }"
                      @click="toggleFocus(node.code)"
                    >
                      <span class="dt-track__dot" :class="`is-${node.tone}`" aria-hidden="true">
                        <AppIcon v-if="node.tone === 'go'" name="check" :size="14" :stroke-width="2.5" />
                      </span>
                      <span class="dt-track__cap">{{ node.label }}</span>
                      <span v-if="node.actions.length" class="dt-track__act">{{ node.actions.join(' · ') }}</span>
                      <span v-if="node.from.length" class="dt-track__from">Từ {{ node.from.join(', ') }}</span>
                    </button>
                  </div>
                  <div v-if="caseLens !== 'done'" class="dt-track__case dt-track__case--fail">
                    <span class="dt-track__case-kicker">Khi hủy</span>
                    <p v-if="!row.stop.length" class="dt-track__case-empty">Chưa có</p>
                    <button
                      v-for="node in row.stop"
                      :key="`${row.actor}-stop-${node.code}`"
                      type="button"
                      class="dt-track__node"
                      :class="{ 'is-dim': focusCode && focusCode !== node.code, 'is-on': focusCode === node.code }"
                      @click="toggleFocus(node.code)"
                    >
                      <span class="dt-track__dot" :class="`is-${node.tone}`" aria-hidden="true">
                        <AppIcon v-if="node.tone === 'hold'" name="close" :size="12" :stroke-width="2.5" />
                      </span>
                      <span class="dt-track__cap">{{ node.label }}</span>
                      <span v-if="node.actions.length" class="dt-track__act">{{ node.actions.join(' · ') }}</span>
                      <span v-if="node.from.length" class="dt-track__from">Từ {{ node.from.join(', ') }}</span>
                    </button>
                  </div>
                </div>
              </article>
            </div>
          </div>
        </section>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="stepDrawerOpen && focusStatus" class="dt-drawer-backdrop" @click.self="closeStepDrawer">
        <aside
          class="dt-drawer"
          :class="`dt-drawer--${drawerTone}`"
          role="dialog"
          aria-modal="true"
          aria-labelledby="dt-step-drawer-title"
          @keydown.escape="closeStepDrawer"
        >
          <div class="dt-drawer__palette" aria-hidden="true">
            <span /><span /><span /><span />
          </div>
          <header class="dt-drawer__head">
            <span class="dt-drawer__mark" aria-hidden="true">{{ drawerPlace }}</span>
            <div class="dt-drawer__head-copy">
              <p class="dt-drawer__kicker">{{ GROUP_KIND_LABEL[focusStatus.group_kind] || 'Trạng thái' }}</p>
              <h4 id="dt-step-drawer-title" class="dt-drawer__title">{{ focusStatus.label }}</h4>
            </div>
            <button type="button" class="dt-modal__close" aria-label="Đóng" @click="closeStepDrawer">
              <AppIcon name="close" :size="18" />
            </button>
          </header>
          <div class="dt-drawer__nav">
            <button type="button" class="dt-drawer__nav-btn" @click="moveFocus(-1)">
              <AppIcon name="chevronLeft" :size="16" :stroke-width="2" aria-hidden="true" />
              Bước trước
            </button>
            <button type="button" class="dt-drawer__nav-btn" @click="moveFocus(1)">
              Bước sau
              <AppIcon name="chevronRight" :size="16" :stroke-width="2" aria-hidden="true" />
            </button>
          </div>
          <div class="dt-drawer__body hide-scrollbar">
            <section class="dt-lane dt-lane--in">
              <header class="dt-lane__head">
                <h5>Đi vào</h5>
                <span class="dt-lane__count">{{ focusInbound.length }}</span>
              </header>
              <p v-if="!focusInbound.length" class="dt-lane__empty">Không có lối vào</p>
              <article v-for="tr in focusInbound" :key="`in-${tr.id}`" class="dt-move dt-move--in">
                <span class="dt-move__where">Từ {{ statusLabel(tr.from_code) }}</span>
                <span class="dt-move__action">{{ actionLabel(tr.action_key) }}</span>
                <span class="dt-move__who" :class="`is-${tr.actor_policy}`">{{ actorLabel(tr.actor_policy) }}</span>
                <span v-if="guardHint(tr.guard_json)" class="dt-move__guard">{{ guardHint(tr.guard_json) }}</span>
              </article>
            </section>
            <section class="dt-lane dt-lane--out">
              <header class="dt-lane__head">
                <h5>Đi ra</h5>
                <span class="dt-lane__count">{{ focusOutbound.length }}</span>
              </header>
              <p v-if="!focusOutbound.length" class="dt-lane__empty">Không có lối ra</p>
              <article v-for="tr in focusOutbound" :key="`out-${tr.id}`" class="dt-move dt-move--out">
                <span class="dt-move__where">Sang {{ statusLabel(tr.to_code) }}</span>
                <span class="dt-move__action">{{ actionLabel(tr.action_key) }}</span>
                <span class="dt-move__who" :class="`is-${tr.actor_policy}`">{{ actorLabel(tr.actor_policy) }}</span>
                <span v-if="guardHint(tr.guard_json)" class="dt-move__guard">{{ guardHint(tr.guard_json) }}</span>
              </article>
            </section>
          </div>
        </aside>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="createSetOpen" class="dt-modal-backdrop" @click.self="createSetOpen = false">
        <div
          class="dt-modal dt-modal--fill"
          role="dialog"
          aria-modal="true"
          aria-labelledby="dt-set-create-title"
          @keydown.escape="createSetOpen = false"
        >
          <header class="dt-modal__head dt-modal__head--rich">
            <span class="dt-modal__mark" aria-hidden="true">
              <AppIcon name="layers" :size="22" :stroke-width="1.75" />
            </span>
            <div class="dt-modal__head-copy">
              <p class="dt-modal__head-kicker">Bước 1 · Thiết lập đơn từ</p>
              <h2 id="dt-set-create-title">Tạo bộ trạng thái</h2>
            </div>
            <button type="button" class="dt-modal__close" aria-label="Đóng" @click="createSetOpen = false">
              <AppIcon name="close" :size="18" />
            </button>
          </header>
          <div class="dt-modal__body dt-set-create">
            <div class="dt-set-create__fields hide-scrollbar">
              <section class="dt-form-section" aria-labelledby="dt-set-section-info">
                <header class="dt-form-section__head">
                  <span class="dt-form-section__step" aria-hidden="true">1</span>
                  <h3 id="dt-set-section-info" class="dt-form-section__title">Thông tin bộ</h3>
                </header>
                <div class="dt-form-section__grid">
                  <label class="dt-field">
                    <span>Tên bộ trạng thái<span class="dt-req" aria-hidden="true">*</span></span>
                    <input
                      v-model="createSetForm.name"
                      type="text"
                      required
                      autocomplete="off"
                      placeholder="Ví dụ: Quy trình nghỉ việc"
                    />
                  </label>
                  <label class="dt-field">
                    <span>Mã bộ trạng thái<span class="dt-req" aria-hidden="true">*</span></span>
                    <span class="dt-code-lock" :class="{ 'dt-code-lock--open': codeUnlocked }">
                      <input
                        ref="codeInput"
                        :value="createSetForm.code"
                        type="text"
                        required
                        maxlength="64"
                        spellcheck="false"
                        autocomplete="off"
                        :readonly="!codeUnlocked"
                        placeholder="BMTT03"
                        @input="onCodeInput"
                      />
                      <button
                        type="button"
                        class="dt-code-lock__btn"
                        :aria-label="codeUnlocked ? 'Khóa mã' : 'Mở khóa để sửa mã'"
                        :aria-pressed="codeUnlocked"
                        @click="toggleCodeLock"
                      >
                        <AppIcon :name="codeUnlocked ? 'unlock' : 'lock'" :size="16" :stroke-width="1.75" />
                      </button>
                    </span>
                  </label>
                  <label class="dt-field dt-field--span2">
                    <span>Mô tả</span>
                    <textarea
                      v-model="createSetForm.description"
                      rows="3"
                      placeholder="Ghi loại đơn hoặc phòng ban sẽ dùng bộ này"
                    />
                  </label>
                </div>
              </section>

              <section class="dt-form-section dt-form-section--compact" aria-labelledby="dt-set-section-init">
                <header class="dt-form-section__head dt-form-section__head--inline">
                  <span class="dt-form-section__step" aria-hidden="true">2</span>
                  <h3 id="dt-set-section-init" class="dt-form-section__title">Nội dung khởi tạo</h3>
                </header>
                <div class="dt-init-compact">
                  <div class="dt-setup__seg dt-init-compact__seg" role="radiogroup" aria-label="Cách khởi tạo trạng thái">
                    <button
                      type="button"
                      role="radio"
                      class="dt-setup__seg-btn"
                      :class="{ 'dt-setup__seg-btn--on': !createSetForm.use_template }"
                      :aria-checked="!createSetForm.use_template"
                      @click="setCreateInitMode(false)"
                    >
                      Bắt đầu trống
                    </button>
                    <button
                      type="button"
                      role="radio"
                      class="dt-setup__seg-btn"
                      :class="{ 'dt-setup__seg-btn--on': createSetForm.use_template }"
                      :aria-checked="createSetForm.use_template"
                      :disabled="!sets.length"
                      @click="setCreateInitMode(true)"
                    >
                      Sao chép mẫu
                    </button>
                  </div>
                  <label v-if="createSetForm.use_template" class="dt-field dt-init-compact__select">
                    <span class="dt-sr-only">Chọn mẫu</span>
                    <select v-model="createSetForm.copy_from_id">
                      <option disabled value="">Chọn bộ mẫu…</option>
                      <option v-for="set in sets" :key="set.id" :value="String(set.id)">
                        {{ friendlySetName(set.name, set.code) }} · {{ set.status_count }} trạng thái
                      </option>
                    </select>
                  </label>
                </div>
              </section>
            </div>
            <aside class="dt-set-preview" aria-label="Kết quả dự kiến">
              <header class="dt-set-preview__head">
                <h3>Kết quả dự kiến</h3>
                <p v-if="createSetForm.use_template && templatePreview">
                  {{ friendlySetName(templatePreview.name, templatePreview.code) }}
                </p>
                <p v-else-if="!createSetForm.use_template">Bộ trống sau khi tạo</p>
              </header>
              <div v-if="previewStats && createSetForm.use_template" class="dt-set-preview__stats">
                <span>{{ previewStats.statuses }} trạng thái</span>
                <span>{{ previewStats.transitions }} quy tắc</span>
              </div>
              <div class="dt-set-preview__body hide-scrollbar">
                <p v-if="templatePreviewLoading" class="dt-set-preview__empty">Đang tải…</p>
                <div v-else-if="!createSetForm.use_template" class="dt-set-preview__plan">
                  <ol class="dt-set-preview__steps">
                    <li>Tạo bộ với tên và mã đã nhập</li>
                    <li>Mở bộ vừa tạo rồi thêm trạng thái</li>
                    <li>Gắn bộ vào quy trình duyệt</li>
                  </ol>
                </div>
                <p v-else-if="!createSetForm.copy_from_id" class="dt-set-preview__empty">Chọn mẫu bên trái</p>
                <p v-else-if="!previewGroups.length" class="dt-set-preview__empty">Mẫu này chưa có trạng thái.</p>
                <div v-else class="dt-set-preview__groups">
                  <p v-if="previewDescription" class="dt-set-preview__desc">{{ previewDescription }}</p>
                  <section v-for="group in previewGroups" :key="group.kind" class="dt-set-preview__group">
                    <h4>{{ group.label }}</h4>
                    <ul>
                      <li
                        v-for="status in group.items"
                        :key="status.code"
                        class="dt-set-preview__item"
                        :class="`dt-set-preview__item--${previewTone(status)}`"
                      >
                        <DonTuStatusBadge :code="status.code" :label="status.label" />
                      </li>
                    </ul>
                  </section>
                </div>
              </div>
            </aside>
          </div>
          <footer class="dt-modal__foot dt-modal__foot--split">
            <p class="dt-modal__foot-summary">{{ createSetFootSummary }}</p>
            <div class="dt-modal__foot-actions">
              <button type="button" class="dt-btn-ghost" @click="createSetOpen = false">Huỷ</button>
              <button type="button" class="dt-btn-primary" :disabled="createSetSaving" @click="submitCreateSet">
                {{ createSetSaving ? 'Đang lưu…' : 'Tạo bộ' }}
              </button>
            </div>
          </footer>
        </div>
      </div>

      <div v-if="createOpen" class="dt-modal-backdrop" @click.self="createOpen = false">
        <div
          class="dt-modal dt-modal--wide dt-modal--status-create"
          role="dialog"
          aria-modal="true"
          aria-labelledby="dt-status-create-title"
          @keydown.escape="createOpen = false"
        >
          <header class="dt-modal__head dt-modal__head--rich">
            <span class="dt-modal__mark" aria-hidden="true">
              <AppIcon name="plusCircle" :size="22" :stroke-width="1.75" />
            </span>
            <div class="dt-modal__head-copy">
              <p class="dt-modal__head-kicker">{{ detailTitle }}</p>
              <h2 id="dt-status-create-title">Thêm trạng thái</h2>
            </div>
            <button type="button" class="dt-modal__close" aria-label="Đóng" @click="createOpen = false">
              <AppIcon name="close" :size="18" />
            </button>
          </header>
          <div class="dt-modal__body dt-status-create hide-scrollbar">
            <section class="dt-form-section" aria-labelledby="dt-status-section-identity">
              <header class="dt-form-section__head">
                <span class="dt-form-section__step" aria-hidden="true">1</span>
                <h3 id="dt-status-section-identity" class="dt-form-section__title">Tên và vị trí</h3>
              </header>
              <div class="dt-form-section__grid">
                <label class="dt-field">
                  <span>Tên trạng thái<span class="dt-req" aria-hidden="true">*</span></span>
                  <input
                    v-model="createForm.label"
                    type="text"
                    required
                    autocomplete="off"
                    placeholder="Ví dụ: Chờ trưởng phòng duyệt"
                  />
                </label>
                <label class="dt-field">
                  <span>Nhóm trên luồng<span class="dt-req" aria-hidden="true">*</span></span>
                  <select v-model="createForm.group_kind" required>
                    <option v-for="(label, key) in GROUP_KIND_LABEL" :key="key" :value="key">{{ label }}</option>
                  </select>
                </label>
                <label v-if="createForm.group_kind === 'terminal'" class="dt-field dt-field--span2">
                  <span>Kết quả cuối<span class="dt-req" aria-hidden="true">*</span></span>
                  <select v-model="createForm.terminal_kind" required>
                    <option value="success">Xong việc</option>
                    <option value="failed">Không xong</option>
                  </select>
                </label>
              </div>
            </section>
            <section class="dt-form-section" aria-labelledby="dt-status-section-rule">
              <header class="dt-form-section__head">
                <span class="dt-form-section__step" aria-hidden="true">2</span>
                <h3 id="dt-status-section-rule" class="dt-form-section__title">Quy tắc chuyển vào trạng thái này</h3>
              </header>
              <div class="dt-form-section__grid">
                <label class="dt-field">
                  <span>Từ trạng thái<span class="dt-req" aria-hidden="true">*</span></span>
                  <select v-model="createForm.from_code" required>
                    <option disabled value="">Chọn bước trước…</option>
                    <option v-for="opt in fromOptions" :key="opt.code" :value="opt.code">{{ opt.label }}</option>
                  </select>
                </label>
                <label class="dt-field">
                  <span>Thao tác<span class="dt-req" aria-hidden="true">*</span></span>
                  <select v-model="createForm.action_key" required>
                    <option v-for="(label, key) in ACTION_LABEL" :key="key" :value="key">{{ label }}</option>
                  </select>
                </label>
                <label class="dt-field dt-field--span2">
                  <span>Người được thực hiện<span class="dt-req" aria-hidden="true">*</span></span>
                  <select v-model="createForm.actor_policy" required>
                    <option v-for="(label, key) in ACTOR_LABEL" :key="key" :value="key">{{ label }}</option>
                  </select>
                </label>
              </div>
            </section>
          </div>
          <footer class="dt-modal__foot">
            <button type="button" class="dt-btn-ghost" @click="createOpen = false">Huỷ</button>
            <button type="button" class="dt-btn-primary" :disabled="createSaving" @click="submitCreate">
              {{ createSaving ? 'Đang lưu…' : 'Thêm trạng thái' }}
            </button>
          </footer>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="editSetOpen" class="dt-modal-backdrop" @click.self="editSetOpen = false">
        <div
          class="dt-modal dt-modal--fill"
          role="dialog"
          aria-modal="true"
          aria-labelledby="dt-set-edit-title"
          @keydown.escape="editSetOpen = false"
        >
          <header class="dt-modal__head dt-modal__head--rich">
            <span class="dt-modal__mark" aria-hidden="true">
              <AppIcon name="pencil" :size="22" :stroke-width="1.75" />
            </span>
            <div class="dt-modal__head-copy">
              <p class="dt-modal__head-kicker">Bộ trạng thái</p>
              <h2 id="dt-set-edit-title">Sửa bộ trạng thái</h2>
            </div>
            <button type="button" class="dt-modal__close" aria-label="Đóng" @click="editSetOpen = false">
              <AppIcon name="close" :size="18" />
            </button>
          </header>
          <div class="dt-modal__body dt-set-create">
            <div class="dt-set-create__fields hide-scrollbar">
            <section class="dt-form-section" aria-labelledby="dt-set-edit-section">
              <header class="dt-form-section__head">
                <span class="dt-form-section__step" aria-hidden="true">1</span>
                <h3 id="dt-set-edit-section" class="dt-form-section__title">Thông tin bộ</h3>
              </header>
              <div class="dt-form-section__grid">
                <label class="dt-field">
                  <span>Tên bộ trạng thái<span class="dt-req" aria-hidden="true">*</span></span>
                  <input
                    v-model="editSetForm.name"
                    type="text"
                    required
                    autocomplete="off"
                    placeholder="Ví dụ: Quy trình nghỉ việc"
                  />
                </label>
                <label class="dt-field">
                  <span>Mã bộ trạng thái<span class="dt-req" aria-hidden="true">*</span></span>
                  <span class="dt-code-lock" :class="{ 'dt-code-lock--open': editCodeUnlocked }">
                    <input
                      ref="editCodeInput"
                      :value="editSetForm.code"
                      type="text"
                      required
                      maxlength="64"
                      spellcheck="false"
                      autocomplete="off"
                      :readonly="editSetForm.is_system || !editCodeUnlocked"
                      placeholder="BMTT03"
                      @input="onEditCodeInput"
                    />
                    <button
                      v-if="!editSetForm.is_system"
                      type="button"
                      class="dt-code-lock__btn"
                      :aria-label="editCodeUnlocked ? 'Khóa mã' : 'Mở khóa để sửa mã'"
                      :aria-pressed="editCodeUnlocked"
                      @click="toggleEditCodeLock"
                    >
                      <AppIcon :name="editCodeUnlocked ? 'unlock' : 'lock'" :size="16" :stroke-width="1.75" />
                    </button>
                  </span>
                </label>
                <label class="dt-field dt-field--span2">
                  <span>Mô tả</span>
                  <textarea
                    v-model="editSetForm.description"
                    rows="3"
                    placeholder="Ghi loại đơn hoặc phòng ban sẽ dùng bộ này"
                  />
                </label>
              </div>
            </section>
            </div>
            <aside class="dt-status-editor" aria-labelledby="dt-set-edit-statuses">
              <header class="dt-status-editor__head">
                <h3 id="dt-set-edit-statuses">Trạng thái trong bộ</h3>
                <span>{{ editSetForm.statuses.length }} bước</span>
              </header>
              <p v-if="editStatusesLoading" class="dt-status-editor__empty">Đang tải trạng thái…</p>
              <p v-else-if="!editSetForm.statuses.length" class="dt-status-editor__empty">Bộ này chưa có trạng thái.</p>
              <ol v-else class="dt-status-editor__list hide-scrollbar">
                <li v-for="(status, idx) in editSetForm.statuses" :key="status.id" class="dt-status-editor__row">
                  <span class="dt-status-editor__ord">{{ String(idx + 1).padStart(2, '0') }}</span>
                  <div class="dt-status-editor__fields">
                    <label class="dt-field">
                      <span>Tên trạng thái<span class="dt-req" aria-hidden="true">*</span></span>
                      <input v-model="status.label" type="text" required autocomplete="off" />
                    </label>
                    <label class="dt-field">
                      <span>Chỗ trên đường đi</span>
                      <select v-model="status.group_kind" @change="onEditStatusGroup(status)">
                        <option v-for="(label, key) in GROUP_KIND_LABEL" :key="key" :value="key">{{ label }}</option>
                      </select>
                    </label>
                    <label v-if="status.group_kind === 'terminal'" class="dt-field dt-field--span2">
                      <span>Kết quả cuối</span>
                      <select v-model="status.terminal_kind">
                        <option value="success">Xong việc</option>
                        <option value="failed">Không xong</option>
                      </select>
                    </label>
                  </div>
                  <div class="dt-status-editor__move">
                    <button
                      type="button"
                      class="is-up"
                      aria-label="Đưa lên"
                      :disabled="idx === 0"
                      @click="moveEditStatus(idx, -1)"
                    >
                      <AppIcon name="chevronDown" :size="16" :stroke-width="2" />
                    </button>
                    <button
                      type="button"
                      aria-label="Đưa xuống"
                      :disabled="idx === editSetForm.statuses.length - 1"
                      @click="moveEditStatus(idx, 1)"
                    >
                      <AppIcon name="chevronDown" :size="16" :stroke-width="2" />
                    </button>
                  </div>
                </li>
              </ol>
            </aside>
          </div>
          <footer class="dt-modal__foot">
            <button type="button" class="dt-btn-ghost" @click="editSetOpen = false">Huỷ</button>
            <button type="button" class="dt-btn-primary" :disabled="editSetSaving || editStatusesLoading" @click="submitEditSet">
              {{ editSetSaving ? 'Đang lưu…' : 'Lưu' }}
            </button>
          </footer>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="setMenu"
        ref="setMenuEl"
        class="dt-set-ctx"
        role="menu"
        :style="{ left: `${setMenu.x}px`, top: `${setMenu.y}px` }"
        @contextmenu.prevent
      >
        <button type="button" role="menuitem" @click="addStatusToSet(setMenu.set)">
          <AppIcon name="plus" :size="16" :stroke-width="1.75" aria-hidden="true" />
          Thêm trạng thái
        </button>
        <button type="button" role="menuitem" @click="openEditSet(setMenu.set)">
          <AppIcon name="pencil" :size="16" :stroke-width="1.75" aria-hidden="true" />
          Sửa
        </button>
        <button type="button" role="menuitem" class="is-danger" @click="askDeleteSet(setMenu.set)">
          <AppIcon name="trash2" :size="16" :stroke-width="1.75" aria-hidden="true" />
          Xóa
        </button>
      </div>
    </Teleport>

    <ConfirmDialog
      v-model:open="deleteOpen"
      title="Xóa bộ trạng thái?"
      :description="deleteTarget ? `Xóa vĩnh viễn «${friendlySetName(deleteTarget.name, deleteTarget.code)}» và các bước trong bộ?` : ''"
      confirm-label="Xóa"
      danger
      :loading="deleteSaving"
      @confirm="confirmDeleteSet"
    />
  </div>
</template>

<style scoped>
@import './donTuAdminShared.css';

.dt-set-ctx {
  position: fixed;
  z-index: 400;
  min-width: 9.5rem;
  padding: var(--space-1);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  box-shadow: var(--shadow-md);
}

.dt-set-ctx button {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  padding: 0.45rem 0.65rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text);
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.dt-set-ctx button.is-danger {
  color: var(--color-danger);
}

.dt-set-ctx button:hover {
  background: var(--color-surface-muted);
}

.dt-config-deny {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-6);
  color: var(--color-text-muted);
  text-align: center;
}

.dt-status-page__toolbar {
  margin-bottom: var(--space-3);
}

.dt-status-page__empty-panel {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-6) var(--space-4);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  text-align: center;
  color: var(--color-text-muted);
}

.dt-status-page__empty-title {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 500;
  color: var(--color-text);
}

.dt-status-page__empty-lead {
  margin: 0;
  max-width: 28rem;
  font-size: 0.875rem;
  line-height: 1.55;
}

.dt-setup__rail-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.dt-setup__rail-add {
  font-size: 0.75rem;
}

.dt-setup__callout {
  margin: 0 0 var(--space-3);
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-md);
  background: var(--color-info-tint-bg);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-info) 25%, var(--color-border));
  font-size: 0.8125rem;
  line-height: 1.5;
  color: var(--color-text);
}

.dt-set-card__code {
  display: block;
  margin-top: 0.15rem;
  font-size: 0.6875rem;
  letter-spacing: 0.04em;
  color: var(--color-text-muted);
}

.dt-status-page__body {
  display: grid;
  grid-template-columns: minmax(13rem, 16rem) minmax(0, 1fr);
  gap: var(--space-3);
  align-items: start;
}

.dt-status-page__body--solo {
  grid-template-columns: minmax(0, 1fr);
}

.dt-status-page__set-search,
.dt-trans-table__search {
  width: 100%;
  padding: 0.4rem 0.6rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font: inherit;
  font-size: 0.8125rem;
  background: var(--color-surface);
}

.dt-status-page__set-search::placeholder,
.dt-trans-table__search::placeholder {
  color: var(--color-text-muted);
  opacity: 0.8;
}

.dt-status-page__set-search {
  margin-bottom: var(--space-2);
}

.dt-status-page__set-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.dt-set-card {
  position: relative;
  width: 100%;
  text-align: left;
  padding: var(--space-2) var(--space-3) var(--space-2) calc(var(--space-2) + 3px + var(--space-2));
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
  font: inherit;
  color: var(--color-text);
}

.dt-set-card::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.dt-set-card--active::before {
  background: var(--color-primary);
}

.dt-set-card--active {
  background: var(--color-primary-100);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 35%, var(--color-border));
}

.dt-set-card__name {
  display: block;
  font-weight: 500;
  font-size: 0.875rem;
  line-height: 1.35;
}

.dt-set-card__meta {
  display: block;
  margin-top: 0.125rem;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.dt-set-card__tag {
  display: inline-block;
  margin-top: var(--space-1);
  padding: 0.1rem 0.45rem;
  border-radius: var(--radius-sm);
  font-size: 0.6875rem;
  font-weight: 600;
  background: var(--color-info-tint-bg);
  color: var(--color-info);
}

.dt-status-page__detail-head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-2) var(--space-4);
  margin-bottom: var(--space-3);
}

.dt-status-page__detail-title {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 700;
}

.dt-status-page__detail-desc {
  margin: var(--space-1) 0 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.dt-status-page__stats {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2) var(--space-3);
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.dt-status-page__stats .dt-set-card__tag {
  margin-top: 0;
}

.dt-link-btn {
  border: none;
  background: transparent;
  padding: 0;
  font: inherit;
  font-size: 0.8125rem;
  color: var(--color-primary);
  cursor: pointer;
  text-decoration: underline;
  text-underline-offset: 2px;
}

.dt-panel {
  margin-bottom: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.dt-panel__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2) var(--space-3);
  margin-bottom: var(--space-2);
}

.dt-panel__title {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 600;
}

.dt-panel > .dt-panel__title {
  margin-bottom: var(--space-2);
}

.dt-workflow-strip__list {
  display: flex;
  flex-wrap: nowrap;
  gap: var(--space-2);
  overflow-x: auto;
  margin: 0;
  padding: 0;
  list-style: none;
}

.dt-workflow-chip {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.35rem 0.65rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
  font: inherit;
  white-space: nowrap;
}

.dt-workflow-chip__name {
  font-weight: 500;
  font-size: 0.8125rem;
}

.dt-pill--compact {
  font-size: 0.625rem;
}

.dt-workflow-strip__link {
  display: inline-flex;
  align-items: center;
  gap: 0.15rem;
  margin-left: auto;
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--color-primary);
  text-decoration: none;
}

.dt-trans-table__search {
  flex: 1;
  min-width: 12rem;
  max-width: 22rem;
  margin-left: auto;
}

.dt-trans-table__scroll {
  overflow-x: auto;
  cursor: grab;
}

.dt-trans-table__scroll:active {
  cursor: grabbing;
}

.dt-trans-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.dt-trans-table th {
  text-align: left;
  padding: 0.55rem 0.75rem;
  font-weight: 600;
  font-size: 0.6875rem;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: color-mix(in srgb, var(--color-text) 65%, var(--color-text-muted));
  background: color-mix(in srgb, var(--color-primary) 8%, var(--color-surface-muted));
  box-shadow: 0 1px 0 color-mix(in srgb, var(--color-primary) 18%, var(--color-border));
}

.dt-trans-table--rules th:first-child {
  border-radius: var(--radius-sm) 0 0 0;
}

.dt-trans-table--rules th:last-child {
  border-radius: 0 var(--radius-sm) 0 0;
}

.dt-trans-table td {
  padding: 0.65rem 0.75rem;
  vertical-align: middle;
  box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 60%, transparent);
}

.dt-trans-table--rules .dt-trans-table__cell--action {
  position: relative;
}

.dt-trans-table--rules .dt-trans-table__cell--action::before,
.dt-trans-table--rules .dt-trans-table__cell--action::after {
  position: absolute;
  top: 50%;
  font-size: 0.875rem;
  font-weight: 600;
  color: color-mix(in srgb, var(--color-primary) 55%, var(--color-text-muted));
  transform: translateY(-50%);
  pointer-events: none;
}

.dt-trans-table--rules .dt-trans-table__cell--action::before {
  content: '→';
  left: -0.35rem;
}

.dt-trans-table--rules .dt-trans-table__cell--action::after {
  content: '→';
  right: -0.35rem;
}

.dt-trans-rules {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.dt-trans-rules .dt-trans-table__scroll {
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-primary) 3%, var(--color-surface));
  box-shadow:
    var(--shadow-sm),
    inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 14%, var(--color-border));
}

.dt-trans-rules__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2) var(--space-3);
}

.dt-trans-table--rules {
  table-layout: fixed;
}

.dt-trans-col--status {
  width: 22%;
}

.dt-trans-col--action {
  width: 26%;
}

.dt-trans-col--actor {
  width: 30%;
}

.dt-trans-table__row:hover {
  background: color-mix(in srgb, var(--color-primary) 7%, var(--color-surface));
}

.dt-trans-table--rules .dt-trans-table__row:nth-child(even) {
  background: color-mix(in srgb, var(--color-primary) 3%, var(--color-surface));
}

.dt-trans-table--rules .dt-trans-table__row:nth-child(even):hover {
  background: color-mix(in srgb, var(--color-primary) 9%, var(--color-surface));
}

.dt-trans-table__cell--status,
.dt-trans-table__cell--action {
  vertical-align: top;
}

.dt-trans-table__status,
.dt-trans-table__action {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 0.35rem;
  max-width: 14rem;
}

.dt-trans-table__chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  min-width: 9.5rem;
  width: 100%;
  max-width: 14rem;
  padding: 0.45rem 0.75rem;
  border-radius: var(--radius-sm);
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.35;
  text-align: center;
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-text-muted) 35%, var(--color-border));
  background: color-mix(in srgb, var(--color-text-muted) 12%, var(--color-surface));
  color: var(--color-text);
}

.dt-action-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  min-width: 9.5rem;
  width: 100%;
  max-width: 14rem;
  padding: 0.5rem 0.75rem;
  border-radius: var(--radius-sm);
  font-size: 0.8125rem;
  font-weight: 700;
  line-height: 1.35;
  text-align: center;
  color: var(--color-primary-700);
  background: color-mix(in srgb, var(--color-primary) 18%, var(--color-surface));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 42%, var(--color-border));
}

.dt-guard {
  display: block;
  padding: 0.25rem 0.5rem;
  border-radius: var(--radius-sm);
  font-size: 0.75rem;
  line-height: 1.4;
  color: var(--color-text-muted);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-border) 80%, transparent);
}

.dt-trans-actor {
  display: inline-flex;
  align-items: center;
  max-width: 100%;
  padding: 0.45rem 0.75rem;
  border-radius: var(--radius-sm);
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.35;
  color: var(--actor-fg, var(--color-text));
  background: color-mix(in srgb, var(--actor-fg, var(--color-text-muted)) 16%, var(--color-surface));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--actor-fg, var(--color-border)) 38%, var(--color-border));
}

.dt-trans-actor--requester {
  --actor-fg: var(--color-info);
}

.dt-trans-actor--assignee {
  --actor-fg: #c27803;
}

.dt-trans-actor--closure_actor {
  --actor-fg: var(--color-success);
}

.dt-trans-actor--admin {
  --actor-fg: var(--color-primary);
}

.dt-status-page__loading,
.dt-status-page__empty {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.dt-modal--fill .dt-modal__close,
.dt-modal--status-create .dt-modal__close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-sm);
}

.dt-modal--fill .dt-modal__close:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.dt-modal.dt-modal--fill .dt-modal__body.dt-set-create {
  display: grid;
  grid-template-columns: minmax(0, 1.15fr) minmax(20rem, 0.85fr);
  gap: var(--space-5);
  overflow: hidden;
}

.dt-set-create__fields {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  min-width: 0;
  min-height: 0;
  height: 100%;
  overflow: auto;
}

.dt-status-create {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.dt-modal--status-create {
  width: min(56rem, calc(100vw - 2.5rem));
  max-height: calc(100vh - 2.5rem);
}

.dt-field .dt-code-lock {
  display: flex;
  align-items: stretch;
  min-height: 2.25rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  overflow: hidden;
}

.dt-field .dt-code-lock--open {
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 45%, var(--color-border));
}

.dt-field .dt-code-lock input {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  letter-spacing: 0.06em;
}

.dt-field .dt-code-lock input[readonly] {
  color: var(--color-text);
}

.dt-code-lock__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
  box-shadow: inset 1px 0 0 var(--color-border);
}

.dt-code-lock--open .dt-code-lock__btn {
  color: var(--color-primary);
}

.dt-code-lock__btn:hover {
  background: color-mix(in srgb, var(--color-primary) 8%, transparent);
}

.dt-set-preview {
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  overflow: hidden;
}

.dt-set-preview__head {
  flex-shrink: 0;
  padding: var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.dt-set-preview__head h3 {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 500;
}

.dt-set-preview__head p,
.dt-set-preview__desc {
  margin: 0.25rem 0 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  line-height: 1.45;
}

.dt-set-preview__stats {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
  padding: 0 var(--space-4) var(--space-3);
  font-size: 0.75rem;
  color: var(--color-primary-700);
}

.dt-set-preview__stats span {
  padding: 0.2rem 0.55rem;
  border-radius: var(--radius-sm);
  background: var(--color-primary-100);
}

.dt-set-preview__plan {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
}

.dt-set-preview__steps {
  margin: 0;
  padding-left: 1.25rem;
  max-width: 20rem;
  font-size: 0.875rem;
  line-height: 1.55;
  color: var(--color-text);
}

.dt-set-preview__steps li + li {
  margin-top: var(--space-2);
}

.dt-set-preview__body {
  flex: 1;
  min-height: 0;
  overflow: auto;
  padding: var(--space-4);
}

.dt-set-preview__empty {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.dt-set-preview__groups {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.dt-set-preview__group h4 {
  margin: 0 0 var(--space-2);
  font-size: 0.75rem;
  font-weight: 500;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.dt-set-preview__group ul {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.dt-set-preview__item {
  position: relative;
  padding: var(--space-2) var(--space-3) var(--space-2) calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.dt-set-preview__item::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-primary);
}

.dt-set-preview__item--info::before {
  background: var(--color-info);
}

.dt-set-preview__item--success::before {
  background: var(--color-success);
}

.dt-set-preview__item--danger::before {
  background: var(--color-danger);
}

.dt-status-editor {
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
  height: 100%;
  padding: var(--space-4);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.dt-status-editor__head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-3);
  flex-shrink: 0;
}

.dt-status-editor__head h3 {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 500;
}

.dt-status-editor__head span,
.dt-status-editor__empty {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.dt-status-editor__empty {
  margin-top: var(--space-4);
}

.dt-status-editor__list {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin: var(--space-3) 0 0;
  padding: 0;
  list-style: none;
  min-height: 0;
  overflow: auto;
}

.dt-status-editor__row {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  gap: var(--space-2);
  align-items: start;
  padding: var(--space-3);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.dt-status-editor__ord {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.75rem;
  height: 1.75rem;
  margin-top: 1.55rem;
  border-radius: 999px;
  background: var(--color-primary-100);
  color: var(--color-primary-700);
  font-size: 0.75rem;
  font-weight: 600;
}

.dt-status-editor__fields {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(8.5rem, 0.8fr);
  gap: var(--space-2);
  min-width: 0;
}

.dt-status-editor__fields .dt-field--span2 {
  grid-column: 1 / -1;
}

.dt-status-editor__move {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  margin-top: 1.45rem;
}

.dt-status-editor__move button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text-muted);
  cursor: pointer;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.dt-status-editor__move button.is-up :deep(.app-icon) {
  transform: rotate(180deg);
}

.dt-status-editor__move button:hover:not(:disabled) {
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 40%, var(--color-border));
}

.dt-status-editor__move button:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

@media (max-width: 800px) {
  .dt-modal.dt-modal--fill .dt-modal__body.dt-set-create {
    grid-template-columns: minmax(0, 1fr);
    overflow: auto;
  }

  .dt-set-preview,
  .dt-status-editor {
    min-height: 18rem;
  }

  .dt-status-editor__fields {
    grid-template-columns: minmax(0, 1fr);
  }

  .dt-form-section__grid {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 900px) {
  .dt-status-page__body {
    grid-template-columns: 1fr;
  }
}
</style>
