<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { fetchFormTemplate, updateFormTemplate } from '../../api/donTuConfigApi.js';

const FIELD_TYPES = [
  { value: 'text', label: 'Văn bản ngắn' },
  { value: 'textarea', label: 'Văn bản dài' },
  { value: 'checkbox', label: 'Ô tích' },
  { value: 'radio', label: 'Chọn một' },
  { value: 'select', label: 'Danh sách chọn' },
  { value: 'date', label: 'Ngày' },
  { value: 'file', label: 'Tệp đính kèm' },
  { value: 'currency', label: 'Số tiền' },
  { value: 'signature', label: 'Ô ký / cam kết' },
];

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const canConfig = computed(
  () => auth.can('don_tu.manage_department') || auth.can('don_tu.*'),
);

const tab = ref('info');
const item = ref(null);
const loading = ref(false);
const saving = ref(false);

const info = ref({ name: '', description: '', scope: 'internal', status: 'draft' });
const fields = ref([]);

const fieldDialog = ref(false);
const fieldEditIndex = ref(-1);
const fieldForm = ref({
  key: '',
  label: '',
  type: 'text',
  required: false,
  options: '',
  commitment_text: '',
});

const templateId = computed(() => Number(route.params.id));

async function load() {
  if (!templateId.value) return;
  loading.value = true;
  try {
    item.value = await fetchFormTemplate(templateId.value);
    info.value = {
      name: item.value.name,
      description: item.value.description || '',
      scope: item.value.scope,
      status: item.value.status,
    };
    fields.value = [...(item.value.schema_json?.fields || [])];
  } catch (err) {
    showClientToast('error', err.response?.data?.message || 'Không tải mẫu biểu.');
    router.push({ name: 'manager.don-tu.forms' });
  } finally {
    loading.value = false;
  }
}

watch(() => route.params.id, load);

onMounted(() => {
  if (canConfig.value) load();
});

async function saveInfo() {
  saving.value = true;
  try {
    item.value = await updateFormTemplate(templateId.value, { ...info.value });
    showClientToast('success', 'Đã lưu thông tin.');
  } catch (err) {
    showClientToast('error', err.response?.data?.message || 'Không lưu được.');
  } finally {
    saving.value = false;
  }
}

async function saveFields() {
  saving.value = true;
  try {
    item.value = await updateFormTemplate(templateId.value, {
      schema_json: { fields: fields.value },
    });
    fields.value = [...(item.value.schema_json?.fields || [])];
    showClientToast('success', 'Đã lưu các ô trên phiếu.');
  } catch (err) {
    showClientToast('error', err.response?.data?.message || 'Không lưu được trường.');
  } finally {
    saving.value = false;
  }
}

function openFieldDialog(index = -1) {
  fieldEditIndex.value = index;
  if (index >= 0) {
    const f = fields.value[index];
    fieldForm.value = {
      key: f.key,
      label: f.label,
      type: f.type || 'text',
      required: !!f.required,
      options: (f.options || []).join('\n'),
      commitment_text: f.commitment_text || '',
    };
  } else {
    fieldForm.value = {
      key: '',
      label: '',
      type: 'text',
      required: false,
      options: '',
      commitment_text: '',
    };
  }
  fieldDialog.value = true;
}

function applyField() {
  const key = fieldForm.value.key.trim();
  if (!key) {
    showClientToast('error', 'Nhập mã ô.');
    return;
  }
  const payload = {
    key,
    label: fieldForm.value.label.trim() || key,
    type: fieldForm.value.type,
    required: fieldForm.value.required,
  };
  if (['select', 'radio'].includes(payload.type)) {
    payload.options = fieldForm.value.options
      .split('\n')
      .map((s) => s.trim())
      .filter(Boolean);
  }
  if (payload.type === 'signature') {
    payload.commitment_text = fieldForm.value.commitment_text.trim();
  }

  const next = [...fields.value];
  if (fieldEditIndex.value >= 0) {
    next[fieldEditIndex.value] = payload;
  } else {
    next.push(payload);
  }
  fields.value = next;
  fieldDialog.value = false;
}

function moveField(index, dir) {
  const j = index + dir;
  if (j < 0 || j >= fields.value.length) return;
  const next = [...fields.value];
  [next[index], next[j]] = [next[j], next[index]];
  fields.value = next;
}

function removeField(index) {
  fields.value = fields.value.filter((_, i) => i !== index);
}

function typeLabel(t) {
  return FIELD_TYPES.find((x) => x.value === t)?.label || t;
}
</script>

<template>
  <div v-if="!canConfig" class="dt-config-deny">Không có quyền cấu hình mẫu biểu.</div>
  <div v-else-if="loading" class="dt-config-deny">Đang tải…</div>

  <div v-else-if="item" class="dt-editor">
    <nav class="dt-editor__tabs" aria-label="Tab cấu hình mẫu">
      <button type="button" :class="{ 'dt-editor__tab--on': tab === 'info' }" @click="tab = 'info'">
        Thông tin
      </button>
      <button type="button" :class="{ 'dt-editor__tab--on': tab === 'fields' }" @click="tab = 'fields'">
        Ô trên phiếu ({{ fields.length }})
      </button>
      <button type="button" :class="{ 'dt-editor__tab--on': tab === 'print' }" @click="tab = 'print'">
        In ấn
      </button>
    </nav>

    <section v-show="tab === 'info'" class="dt-editor__panel">
      <div class="dt-modal__grid">
        <label class="dt-field">
          <span>Tên mẫu<span class="dt-req" aria-hidden="true">*</span></span>
          <input v-model="info.name" type="text" required placeholder="Ví dụ: Đơn xin nghỉ phép" />
        </label>
        <label class="dt-field">
          <span>Ai được dùng<span class="dt-req" aria-hidden="true">*</span></span>
          <select v-model="info.scope" required>
            <option value="internal">Cán bộ trong trường</option>
            <option value="external">Phụ huynh</option>
            <option value="both">Cả cán bộ và phụ huynh</option>
          </select>
        </label>
        <label class="dt-field">
          <span>Tình trạng<span class="dt-req" aria-hidden="true">*</span></span>
          <select v-model="info.status" required>
            <option value="draft">Nháp</option>
            <option value="active">Đang dùng</option>
            <option value="archived">Cất lưu trữ</option>
          </select>
        </label>
        <label class="dt-field dt-field--span2">
          <span>Ghi chú</span>
          <textarea v-model="info.description" rows="3" placeholder="Ghi ngắn mẫu này dùng khi nào" />
        </label>
      </div>
      <p class="dt-editor__meta">Đã lưu lần {{ item.version }}</p>
      <button type="button" class="dt-btn-primary" :disabled="saving" @click="saveInfo">Lưu thông tin</button>
    </section>

    <section v-show="tab === 'fields'" class="dt-editor__panel">
      <div class="dt-editor__panel-head">
        <button type="button" class="dt-admin-list__primary" @click="openFieldDialog(-1)">
          <AppIcon name="plus" :size="16" />
          Thêm ô
        </button>
      </div>
      <ul v-if="fields.length" class="dt-field-list">
        <li v-for="(f, idx) in fields" :key="f.key + idx" class="dt-field-row">
          <div class="dt-field-row__main">
            <span class="dt-field-row__label">{{ f.label }}</span>
            <code>{{ f.key }}</code>
            <span class="dt-pill dt-pill--muted">{{ typeLabel(f.type) }}</span>
            <span v-if="f.required" class="dt-pill dt-pill--warning">Bắt buộc</span>
          </div>
          <div class="dt-field-row__actions">
            <button type="button" aria-label="Lên" @click="moveField(idx, -1)">↑</button>
            <button type="button" aria-label="Xuống" @click="moveField(idx, 1)">↓</button>
            <button type="button" @click="openFieldDialog(idx)">Sửa</button>
            <button type="button" class="dt-row-menu__danger" @click="removeField(idx)">Xóa</button>
          </div>
        </li>
      </ul>
      <p v-else class="dt-config-deny">Chưa có ô nào trên phiếu.</p>
      <button type="button" class="dt-btn-primary" :disabled="saving" @click="saveFields">Lưu các ô</button>
    </section>

    <section v-show="tab === 'print'" class="dt-editor__panel dt-editor__panel--muted">
      <p>Phần in chưa mở.</p>
    </section>

    <Teleport to="body">
      <div v-if="fieldDialog" class="dt-modal-backdrop" @click.self="fieldDialog = false">
        <div class="dt-modal" role="dialog">
          <header class="dt-modal__head">
            <h2>{{ fieldEditIndex >= 0 ? 'Sửa ô trên phiếu' : 'Thêm ô trên phiếu' }}</h2>
            <button type="button" class="dt-modal__close" aria-label="Đóng" @click="fieldDialog = false">
              <AppIcon name="x" :size="18" />
            </button>
          </header>
          <div class="dt-modal__body dt-modal__grid">
            <label class="dt-field">
              <span>Mã ô<span class="dt-req" aria-hidden="true">*</span></span>
              <input v-model="fieldForm.key" type="text" required placeholder="Ví dụ: ly_do_nghi" />
            </label>
            <label class="dt-field">
              <span>Tên hiển thị<span class="dt-req" aria-hidden="true">*</span></span>
              <input v-model="fieldForm.label" type="text" required placeholder="Ví dụ: Lý do nghỉ" />
            </label>
            <label class="dt-field">
              <span>Kiểu ô<span class="dt-req" aria-hidden="true">*</span></span>
              <select v-model="fieldForm.type" required>
                <option v-for="opt in FIELD_TYPES" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </label>
            <label class="dt-field dt-field--check">
              <input v-model="fieldForm.required" type="checkbox" />
              <span>Bắt buộc khi nộp</span>
            </label>
            <label
              v-if="['select', 'radio'].includes(fieldForm.type)"
              class="dt-field dt-field--span2"
            >
              <span>Các lựa chọn<span class="dt-req" aria-hidden="true">*</span></span>
              <textarea v-model="fieldForm.options" rows="3" required placeholder="Mỗi dòng một lựa chọn" />
            </label>
            <label v-if="fieldForm.type === 'signature'" class="dt-field dt-field--span2">
              <span>Câu cam kết<span class="dt-req" aria-hidden="true">*</span></span>
              <textarea v-model="fieldForm.commitment_text" rows="2" required placeholder="Ví dụ: Tôi cam kết thông tin trên là đúng" />
            </label>
          </div>
          <footer class="dt-modal__foot">
            <button type="button" class="dt-btn-ghost" @click="fieldDialog = false">Huỷ</button>
            <button type="button" class="dt-btn-primary" @click="applyField">Áp dụng</button>
          </footer>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
@import './donTuAdminShared.css';

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
  padding: var(--space-4);
  border-radius: var(--radius-md);
  background: linear-gradient(
    145deg,
    color-mix(in srgb, var(--color-primary) 6%, var(--color-surface)) 0%,
    var(--color-surface) 46%,
    color-mix(in srgb, var(--color-info) 7%, var(--color-surface)) 100%
  );
  box-shadow: var(--shadow-sm);
}

.dt-editor__panel--muted {
  color: var(--color-text-muted);
}

.dt-editor__meta {
  margin: var(--space-4) 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.dt-editor__panel-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  margin-bottom: var(--space-4);
}

.dt-field-list {
  list-style: none;
  margin: 0 0 var(--space-4);
  padding: 0;
}

.dt-field-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-sm);
  background: linear-gradient(
    180deg,
    color-mix(in srgb, var(--color-primary) 5%, var(--color-surface)),
    color-mix(in srgb, var(--color-info) 5%, var(--color-surface))
  );
  margin-bottom: var(--space-2);
}

.dt-field-row__main {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
}

.dt-field-row__label {
  font-weight: 600;
}

.dt-field-row__actions {
  display: flex;
  flex-shrink: 0;
  gap: var(--space-1);
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
  flex-direction: row;
  align-items: center;
  gap: var(--space-2);
}
</style>
