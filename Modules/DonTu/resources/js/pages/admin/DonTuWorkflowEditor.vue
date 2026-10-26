<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import DonTuStatusBadge from '../../components/DonTuStatusBadge.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import {
  fetchFormTemplates,
  fetchWorkflow,
  updateWorkflow,
} from '../../api/donTuConfigApi.js';

const STEP_TYPES = [
  { value: 'receive', label: 'Tiếp nhận' },
  { value: 'approve', label: 'Phê duyệt' },
  { value: 'form_completion', label: 'Hoàn thiện phiếu bổ sung' },
  { value: 'payment', label: 'Chi trả / kế toán' },
  { value: 'closure', label: 'Xác nhận đóng hồ sơ' },
  { value: 'task', label: 'Tạo công việc' },
];

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const canConfig = computed(
  () => auth.can('don_tu.manage_department') || auth.can('don_tu.*'),
);

const tab = ref('meta');
const item = ref(null);
const formCatalog = ref([]);
const loading = ref(false);
const saving = ref(false);

const meta = ref({
  name: '',
  description: '',
  is_financial: false,
  is_active: false,
  initiator_policy: 'staff',
  closure_actor_policy: 'requester',
  closure_role: '',
});

const workflowForms = ref([]);
const steps = ref([]);

const stepDialog = ref(false);
const stepIndex = ref(-1);
const stepForm = ref({
  name: '',
  step_type: 'approve',
  status_group_code: 'PENDING_APPROVAL',
  assignee_policy: 'role',
  assignee_role: 'department_director',
  sla_hours: '',
  allow_reject: true,
  allow_request_changes: true,
  workspace_task: false,
  blocks_cancel: false,
  target_form_keys: '',
});

const workflowId = computed(() => Number(route.params.id));
const statusCodes = computed(() => item.value?.status_codes ?? []);

async function load() {
  if (!workflowId.value) return;
  loading.value = true;
  try {
    const [wf, forms] = await Promise.all([
      fetchWorkflow(workflowId.value),
      fetchFormTemplates(),
    ]);
    item.value = wf;
    formCatalog.value = forms.filter((f) => f.status !== 'archived');
    meta.value = {
      name: wf.name,
      description: wf.description || '',
      is_financial: wf.is_financial,
      is_active: wf.is_active,
      initiator_policy: wf.initiator_policy,
      closure_actor_policy: wf.closure_actor_policy,
      closure_role: wf.closure_role || '',
    };
    workflowForms.value = (wf.forms || []).map((f) => ({ ...f }));
    steps.value = (wf.steps || []).map((s) => ({ ...s }));
  } catch (err) {
    showClientToast(err.response?.data?.message || 'Không tải quy trình.', 'error');
    router.push({ name: 'manager.don-tu.workflows' });
  } finally {
    loading.value = false;
  }
}

watch(() => route.params.id, load);
onMounted(() => {
  if (canConfig.value) load();
});

async function saveMeta() {
  saving.value = true;
  try {
    item.value = await updateWorkflow(workflowId.value, { ...meta.value });
    showClientToast('Đã lưu thông tin quy trình.', 'success');
  } catch (err) {
    showClientToast(err.response?.data?.message || 'Không lưu được.', 'error');
  } finally {
    saving.value = false;
  }
}

function addFormRow() {
  const n = workflowForms.value.length + 1;
  workflowForms.value.push({
    form_key: `form_${n}`,
    form_template_id: formCatalog.value[0]?.id ?? '',
    sequence: n,
    is_start: n === 1,
  });
}

async function saveForms() {
  saving.value = true;
  try {
    item.value = await updateWorkflow(workflowId.value, {
      forms: workflowForms.value.map((f) => ({
        form_key: f.form_key,
        form_template_id: Number(f.form_template_id),
        sequence: Number(f.sequence),
        is_start: !!f.is_start,
      })),
    });
    workflowForms.value = item.value.forms || [];
    showClientToast('Đã lưu phiếu gắn quy trình.', 'success');
  } catch (err) {
    showClientToast(err.response?.data?.message || 'Không lưu phiếu.', 'error');
  } finally {
    saving.value = false;
  }
}

function openStepDialog(index = -1) {
  stepIndex.value = index;
  if (index >= 0) {
    const s = steps.value[index];
    stepForm.value = {
      name: s.name,
      step_type: s.step_type,
      status_group_code: s.status_group_code,
      assignee_policy: s.assignee_policy,
      assignee_role: s.assignee_role || 'department_director',
      sla_hours: s.sla_hours ?? '',
      allow_reject: s.allow_reject,
      allow_request_changes: s.allow_request_changes,
      workspace_task: s.workspace_task,
      blocks_cancel: s.blocks_cancel,
      target_form_keys: (s.target_form_keys || []).join(', '),
    };
  } else {
    stepForm.value = {
      name: '',
      step_type: 'approve',
      status_group_code: 'PENDING_APPROVAL',
      assignee_policy: 'role',
      assignee_role: 'department_director',
      sla_hours: '',
      allow_reject: true,
      allow_request_changes: true,
      workspace_task: false,
      blocks_cancel: false,
      target_form_keys: '',
    };
  }
  stepDialog.value = true;
}

function applyStepLocal() {
  const payload = {
    name: stepForm.value.name.trim() || 'Bước duyệt',
    step_type: stepForm.value.step_type,
    status_group_code: stepForm.value.status_group_code,
    assignee_policy: stepForm.value.assignee_policy,
    assignee_role: stepForm.value.assignee_role,
    sla_hours: stepForm.value.sla_hours === '' ? null : Number(stepForm.value.sla_hours),
    allow_reject: stepForm.value.allow_reject,
    allow_request_changes: stepForm.value.allow_request_changes,
    workspace_task: stepForm.value.workspace_task,
    blocks_cancel: stepForm.value.blocks_cancel,
    target_form_keys: stepForm.value.target_form_keys
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean),
    sort_order: stepIndex.value >= 0 ? steps.value[stepIndex.value].sort_order : steps.value.length + 1,
  };

  const next = [...steps.value];
  if (stepIndex.value >= 0) {
    next[stepIndex.value] = { ...next[stepIndex.value], ...payload };
  } else {
    next.push(payload);
  }
  steps.value = next.map((s, i) => ({ ...s, sort_order: i + 1 }));
  stepDialog.value = false;
}

async function saveSteps() {
  saving.value = true;
  try {
    item.value = await updateWorkflow(workflowId.value, { steps: steps.value });
    steps.value = item.value.steps || [];
    showClientToast('Đã lưu ma trận bước duyệt.', 'success');
  } catch (err) {
    showClientToast(err.response?.data?.message || 'Không lưu bước.', 'error');
  } finally {
    saving.value = false;
  }
}

function moveStep(i, dir) {
  const j = i + dir;
  if (j < 0 || j >= steps.value.length) return;
  const next = [...steps.value];
  [next[i], next[j]] = [next[j], next[i]];
  steps.value = next.map((s, idx) => ({ ...s, sort_order: idx + 1 }));
}

function removeStep(i) {
  steps.value = steps.value.filter((_, idx) => idx !== i).map((s, idx) => ({ ...s, sort_order: idx + 1 }));
}

function stepTypeLabel(t) {
  return STEP_TYPES.find((x) => x.value === t)?.label || t;
}
</script>

<template>
  <div v-if="!canConfig" class="dt-config-deny">Không có quyền cấu hình quy trình.</div>
  <div v-else-if="loading" class="dt-config-deny">Đang tải…</div>

  <div v-else-if="item" class="dt-editor">
    <div class="dt-wf-head">
      <DonTuStatusBadge code="PENDING_HANDLING" label="Preview luồng" compact />
      <p class="dt-wf-head__code">Mã quy trình: <code>{{ item.code }}</code></p>
    </div>

    <nav class="dt-editor__tabs">
      <button type="button" :class="{ 'dt-editor__tab--on': tab === 'meta' }" @click="tab = 'meta'">
        Thông tin
      </button>
      <button type="button" :class="{ 'dt-editor__tab--on': tab === 'forms' }" @click="tab = 'forms'">
        Phiếu gắn
      </button>
      <button type="button" :class="{ 'dt-editor__tab--on': tab === 'steps' }" @click="tab = 'steps'">
        Bước duyệt ({{ steps.length }})
      </button>
    </nav>

    <section v-show="tab === 'meta'" class="dt-editor__panel">
      <div class="dt-modal__grid">
        <label class="dt-field dt-field--span2">
          <span>Tên quy trình</span>
          <input v-model="meta.name" type="text" />
        </label>
        <label class="dt-field">
          <span>Người được nộp</span>
          <select v-model="meta.initiator_policy">
            <option value="staff">Cán bộ nội bộ</option>
            <option value="parent">Phụ huynh</option>
            <option value="both">Cả hai</option>
          </select>
        </label>
        <label class="dt-field dt-field--check">
          <input v-model="meta.is_active" type="checkbox" />
          <span>Quy trình đang bật (hiện trên cổng nộp đơn)</span>
        </label>
        <label class="dt-field dt-field--span2">
          <span>Mô tả</span>
          <textarea v-model="meta.description" rows="2" />
        </label>
        <label class="dt-field dt-field--check">
          <input v-model="meta.is_financial" type="checkbox" />
          <span>Có bước chi trả / kế toán</span>
        </label>
      </div>
      <button type="button" class="dt-btn-primary" :disabled="saving" @click="saveMeta">Lưu thông tin</button>
    </section>

    <section v-show="tab === 'forms'" class="dt-editor__panel">
      <button type="button" class="dt-admin-list__primary" @click="addFormRow">Thêm phiếu</button>
      <div v-for="(row, idx) in workflowForms" :key="idx" class="dt-wf-form-row">
        <label class="dt-field">
          <span>form_key</span>
          <input v-model="row.form_key" type="text" />
        </label>
        <label class="dt-field dt-field--grow">
          <span>Mẫu biểu</span>
          <select v-model="row.form_template_id">
            <option v-for="f in formCatalog" :key="f.id" :value="f.id">{{ f.name }}</option>
          </select>
        </label>
        <label class="dt-field dt-field--check">
          <input v-model="row.is_start" type="checkbox" />
          <span>Phiếu khởi tạo</span>
        </label>
      </div>
      <button type="button" class="dt-btn-primary" :disabled="saving" @click="saveForms">Lưu phiếu</button>
    </section>

    <section v-show="tab === 'steps'" class="dt-editor__panel">
      <div class="dt-editor__panel-head">
        <p>Mỗi bước gắn <code>status_group_code</code> trong bộ trạng thái của quy trình.</p>
        <button type="button" class="dt-admin-list__primary" @click="openStepDialog(-1)">Thêm bước</button>
      </div>
      <ol v-if="steps.length" class="dt-step-list">
        <li v-for="(st, idx) in steps" :key="idx" class="dt-step-card">
          <span class="dt-step-card__order">{{ idx + 1 }}</span>
          <div class="dt-step-card__body">
            <strong>{{ st.name }}</strong>
            <span class="dt-pill dt-pill--muted">{{ stepTypeLabel(st.step_type) }}</span>
            <DonTuStatusBadge compact :code="st.status_group_code" :label="st.status_group_code" />
          </div>
          <div class="dt-field-row__actions">
            <button type="button" @click="moveStep(idx, -1)">↑</button>
            <button type="button" @click="moveStep(idx, 1)">↓</button>
            <button type="button" @click="openStepDialog(idx)">Sửa</button>
            <button type="button" class="dt-row-menu__danger" @click="removeStep(idx)">Xóa</button>
          </div>
        </li>
      </ol>
      <button type="button" class="dt-btn-primary" :disabled="saving" @click="saveSteps">Lưu bước duyệt</button>
    </section>

    <Teleport to="body">
      <div v-if="stepDialog" class="dt-modal-backdrop" @click.self="stepDialog = false">
        <div class="dt-modal dt-modal--wide" role="dialog">
          <header class="dt-modal__head">
            <h2>{{ stepIndex >= 0 ? 'Sửa bước' : 'Thêm bước duyệt' }}</h2>
            <button type="button" class="dt-modal__close" aria-label="Đóng" @click="stepDialog = false">
              <AppIcon name="x" :size="18" />
            </button>
          </header>
          <div class="dt-modal__body dt-modal__grid">
            <label class="dt-field dt-field--span2">
              <span>Tên bước</span>
              <input v-model="stepForm.name" type="text" />
            </label>
            <label class="dt-field">
              <span>Loại bước</span>
              <select v-model="stepForm.step_type">
                <option v-for="opt in STEP_TYPES" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </label>
            <label class="dt-field">
              <span>Nhóm trạng thái</span>
              <select v-model="stepForm.status_group_code">
                <option v-for="code in statusCodes" :key="code" :value="code">{{ code }}</option>
              </select>
            </label>
            <label class="dt-field">
              <span>Vai trò xử lý</span>
              <input v-model="stepForm.assignee_role" type="text" />
            </label>
            <label class="dt-field">
              <span>SLA (giờ)</span>
              <input v-model="stepForm.sla_hours" type="number" min="0" />
            </label>
            <label class="dt-field dt-field--span2">
              <span>Phiếu áp dụng (form_key, cách nhau dấu phẩy)</span>
              <input v-model="stepForm.target_form_keys" type="text" placeholder="form_1" />
            </label>
            <label class="dt-field dt-field--check">
              <input v-model="stepForm.allow_reject" type="checkbox" />
              <span>Cho phép từ chối</span>
            </label>
            <label class="dt-field dt-field--check">
              <input v-model="stepForm.allow_request_changes" type="checkbox" />
              <span>Yêu cầu bổ sung</span>
            </label>
            <label class="dt-field dt-field--check">
              <input v-model="stepForm.workspace_task" type="checkbox" />
              <span>Tạo công việc</span>
            </label>
            <label class="dt-field dt-field--check">
              <input v-model="stepForm.blocks_cancel" type="checkbox" />
              <span>Chặn hủy hồ sơ sau bước này</span>
            </label>
          </div>
          <footer class="dt-modal__foot">
            <button type="button" class="dt-btn-ghost" @click="stepDialog = false">Huỷ</button>
            <button type="button" class="dt-btn-primary" @click="applyStepLocal">Áp dụng</button>
          </footer>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
@import './donTuAdminShared.css';

.dt-modal--wide {
  width: min(52rem, calc(100vw - 2.5rem));
}

.dt-wf-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-3);
  margin-bottom: var(--space-4);
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-info) 8%, var(--color-surface));
}

.dt-wf-head__code {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.dt-wf-form-row {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
  align-items: flex-end;
  margin: var(--space-4) 0;
  padding: var(--space-3);
  background: var(--color-surface-muted);
  border-radius: var(--radius-sm);
}

.dt-field--grow {
  flex: 1;
  min-width: 12rem;
}

.dt-step-list {
  list-style: none;
  margin: 0 0 var(--space-4);
  padding: 0;
}

.dt-step-card {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  margin-bottom: var(--space-2);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.dt-step-card__order {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 999px;
  font-weight: 700;
  font-size: 0.8125rem;
  background: var(--color-primary);
  color: #fff;
}

.dt-step-card__body {
  flex: 1;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
}

.dt-editor__tabs {
  display: flex;
  gap: var(--space-1);
  margin-bottom: var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.dt-editor__tabs button {
  padding: 0.5rem 0.85rem;
  border: none;
  border-radius: var(--radius-sm) var(--radius-sm) 0 0;
  background: transparent;
  font-weight: 600;
  font-size: 0.875rem;
  color: var(--color-text-muted);
  cursor: pointer;
}

.dt-editor__tab--on {
  color: var(--color-primary) !important;
  background: var(--color-surface) !important;
  box-shadow: inset 0 2px 0 var(--color-primary);
}

.dt-editor__panel {
  padding: var(--space-5);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.dt-editor__panel-head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: var(--space-3);
  margin-bottom: var(--space-4);
}

.dt-field-row__actions button {
  padding: 0.25rem 0.45rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  font-size: 0.75rem;
  cursor: pointer;
}

.dt-field--check {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: var(--space-2);
}
</style>
