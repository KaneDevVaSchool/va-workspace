<script setup>
//
// Tab "Testcase" — danh sách testcase kiểm thử thuộc dự án (tab tuỳ chọn,
// bật/tắt riêng theo dự án). Nghiệm thu 2 lượt: assignee tự chấm Check
// lần 1, người tạo testcase chấm Check lần 2 — Check lần 2 "Không đạt"
// tự động đặt lại Check lần 1 về "Chờ chạy" (ProjectTestCaseService::
// updateCheck2()). Không tự vẽ tiêu đề "Testcase"/nút "Thêm testcase" —
// đã có sẵn ở PageHeader ProjectDetail.vue (openCreate() expose ra
// ngoài qua ref, giữ nguyên contract này).
//
import { computed, defineAsyncComponent, onMounted, reactive, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import ProjectUserPicker from './ProjectUserPicker.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';

const FilePreviewDialog = defineAsyncComponent(() => import('./FilePreviewDialog.vue'));

const props = defineProps({
  project: { type: Object, required: true },
  tree: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false },
});

const emit = defineEmits(['count-changed']);

const auth = useAuthStore();

const CHECK_STATUS_DEFS = [
  { value: 'pending', label: 'Chờ chạy', tone: 'neutral' },
  { value: 'passed', label: 'Đạt', tone: 'success' },
  { value: 'failed', label: 'Không đạt', tone: 'danger' },
];

function checkMeta(value) {
  return CHECK_STATUS_DEFS.find((s) => s.value === value) || CHECK_STATUS_DEFS[0];
}

const testCases = ref([]);
const loading = ref(false);
const assignableUsers = ref([]);
const selected = ref(null);
const formOpen = ref(false);
const saving = ref(false);
const editingId = ref(null);
const check1Busy = ref(false);
const check2Busy = ref(false);
const attachmentUploading = ref(false);
const attachmentInput = ref(null);

const previewOpen = ref(false);
const previewFile = ref(null);
const linkPreviewOpen = ref(false);
const linkPreviewUrl = ref('');

const form = reactive({
  title: '',
  steps: '',
  expected_result: '',
  actual_result: '',
  link_url: '',
  assignee_id: '',
  phase_id: '',
});

const endpoint = computed(() => `/api/project/${props.project.id}/test-cases`);

// Danh sách giai đoạn (tasks.type=phase) của dự án — dùng để chọn khi
// tạo/sửa testcase và để nhóm bảng testcase theo giai đoạn, tổng hợp
// testcase theo module/tính năng thay vì liệt kê rời rạc.
const phases = computed(() => (props.tree || []).filter((node) => node.type === 'phase'));

const NO_PHASE_KEY = '__no_phase__';
const collapsedPhaseKeys = ref(new Set());

function toggleCollapsePhase(key) {
  const next = new Set(collapsedPhaseKeys.value);
  if (next.has(key)) next.delete(key);
  else next.add(key);
  collapsedPhaseKeys.value = next;
}

const phaseGroups = computed(() => {
  const groups = new Map();
  const ensure = (key, title) => {
    if (!groups.has(key)) groups.set(key, { key, title, tests: [] });
    return groups.get(key);
  };
  for (const tc of testCases.value) {
    const key = tc.phase ? `phase-${tc.phase.id}` : NO_PHASE_KEY;
    ensure(key, tc.phase ? tc.phase.title : 'Chưa gắn giai đoạn').tests.push(tc);
  }
  const out = Array.from(groups.values());
  out.sort((a, b) => {
    if (a.key === NO_PHASE_KEY) return 1;
    if (b.key === NO_PHASE_KEY) return -1;
    return 0;
  });
  return out;
});

async function load() {
  loading.value = true;
  try {
    const { data } = await window.axios.get(endpoint.value);
    testCases.value = data.test_cases || [];
    if (selected.value) {
      selected.value = testCases.value.find((tc) => tc.id === selected.value.id) || null;
    }
    emit('count-changed', testCases.value.length);
  } catch {
    testCases.value = [];
  } finally {
    loading.value = false;
  }
}

async function ensureAssignableUsers() {
  if (assignableUsers.value.length) return;
  try {
    const { data } = await window.axios.get('/api/project/assignable-users');
    assignableUsers.value = data.users ?? [];
  } catch {
    assignableUsers.value = [];
  }
}

function resetForm() {
  form.title = '';
  form.steps = '';
  form.expected_result = '';
  form.actual_result = '';
  form.link_url = '';
  form.assignee_id = '';
  form.phase_id = '';
  editingId.value = null;
}

async function openCreate() {
  resetForm();
  await ensureAssignableUsers();
  formOpen.value = true;
}

async function openEdit(testCase) {
  resetForm();
  await ensureAssignableUsers();
  editingId.value = testCase.id;
  form.title = testCase.title;
  form.steps = testCase.steps || '';
  form.expected_result = testCase.expected_result || '';
  form.actual_result = testCase.actual_result || '';
  form.link_url = testCase.link_url || '';
  form.assignee_id = testCase.assignee?.id || '';
  form.phase_id = testCase.phase?.id || '';
  formOpen.value = true;
}

function closeForm() {
  formOpen.value = false;
}

function selectRow(testCase) {
  selected.value = selected.value?.id === testCase.id ? null : testCase;
}

async function submit() {
  const title = form.title.trim();
  if (!title) {
    showClientToast('error', 'Nhập tiêu đề testcase.');
    return;
  }
  saving.value = true;
  try {
    const payload = {
      title,
      steps: form.steps || null,
      expected_result: form.expected_result || null,
      actual_result: form.actual_result || null,
      link_url: form.link_url || null,
      assignee_id: form.assignee_id || null,
      phase_id: form.phase_id || null,
    };
    if (editingId.value) {
      const { data } = await window.axios.put(`/api/project/test-cases/${editingId.value}`, payload);
      testCases.value = testCases.value.map((tc) => (tc.id === data.test_case.id ? data.test_case : tc));
      if (selected.value?.id === data.test_case.id) selected.value = data.test_case;
      showClientToast('success', 'Đã cập nhật testcase.');
    } else {
      const { data } = await window.axios.post(endpoint.value, payload);
      testCases.value = [...testCases.value, data.test_case];
      emit('count-changed', testCases.value.length);
      showClientToast('success', 'Đã thêm testcase.');
    }
    formOpen.value = false;
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không lưu được testcase.');
  } finally {
    saving.value = false;
  }
}

async function removeTestCase(testCase) {
  if (!window.confirm(`Xoá testcase "${testCase.title}"?`)) return;
  try {
    await window.axios.delete(`/api/project/test-cases/${testCase.id}`);
    testCases.value = testCases.value.filter((tc) => tc.id !== testCase.id);
    if (selected.value?.id === testCase.id) selected.value = null;
    emit('count-changed', testCases.value.length);
    showClientToast('success', 'Đã xoá testcase.');
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không xoá được testcase.');
  }
}

function canCheck1(testCase) {
  return Boolean(testCase.assignee?.id) && testCase.assignee.id === auth.user?.id;
}

function canCheck2(testCase) {
  return Boolean(testCase.creator?.id) && testCase.creator.id === auth.user?.id;
}

function applyUpdatedTestCase(updated) {
  testCases.value = testCases.value.map((tc) => (tc.id === updated.id ? updated : tc));
  if (selected.value?.id === updated.id) selected.value = updated;
}

async function setCheck1(testCase, status) {
  if (check1Busy.value) return;
  check1Busy.value = true;
  try {
    const { data } = await window.axios.put(`/api/project/test-cases/${testCase.id}/check1`, { status });
    applyUpdatedTestCase(data.test_case);
    showClientToast('success', 'Đã cập nhật Check lần 1.');
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không cập nhật được Check lần 1.');
  } finally {
    check1Busy.value = false;
  }
}

async function setCheck2(testCase, status) {
  if (check2Busy.value) return;
  check2Busy.value = true;
  try {
    const { data } = await window.axios.put(`/api/project/test-cases/${testCase.id}/check2`, { status });
    applyUpdatedTestCase(data.test_case);
    showClientToast(
      'success',
      status === 'failed' ? 'Đã yêu cầu làm lại — Check lần 1 trở về Chờ chạy.' : 'Đã cập nhật Check lần 2.',
    );
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không cập nhật được Check lần 2.');
  } finally {
    check2Busy.value = false;
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
  fd.append('attachment', file);
  try {
    const { data } = await window.axios.post(`/api/project/test-cases/${selected.value.id}/attachment`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    applyUpdatedTestCase(data.test_case);
    showClientToast('success', 'Đã tải ảnh lên.');
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không tải lên được ảnh.');
  } finally {
    attachmentUploading.value = false;
  }
}

async function removeAttachment(testCase) {
  if (!window.confirm('Xoá ảnh đính kèm này?')) return;
  try {
    const { data } = await window.axios.delete(`/api/project/test-cases/${testCase.id}/attachment`);
    applyUpdatedTestCase(data.test_case);
    showClientToast('success', 'Đã xoá ảnh đính kèm.');
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không xoá được ảnh đính kèm.');
  }
}

function openImagePreview(testCase) {
  previewFile.value = {
    name: testCase.title,
    file_url: testCase.attachment_url,
    mime_type: 'image/*',
  };
  previewOpen.value = true;
}

function openLinkPreview(testCase) {
  linkPreviewUrl.value = testCase.link_url;
  linkPreviewOpen.value = true;
}

function closeLinkPreview() {
  linkPreviewOpen.value = false;
  linkPreviewUrl.value = '';
}

function formatDate(value) {
  if (!value) return '—';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleString('vi-VN');
}

onMounted(load);

defineExpose({ openCreate });
</script>

<template>
  <section class="tct">
    <div class="tct__main">
      <p v-if="loading" class="tct__empty">Đang tải testcase…</p>
      <p v-else-if="!testCases.length" class="tct__empty">
        Dự án chưa có testcase nào.
        <button v-if="canManage" type="button" class="tct__empty-add" @click="openCreate">
          <AppIcon name="plus" :size="14" />
          Thêm testcase
        </button>
      </p>

      <div v-else class="tct__table-wrap hide-scrollbar">
        <table class="tct__table">
          <thead>
            <tr>
              <th>Tiêu đề</th>
              <th>Người phụ trách</th>
              <th>Check lần 1</th>
              <th>Check lần 2</th>
              <th>Link / Ảnh</th>
              <th>Cập nhật lúc</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="group in phaseGroups" :key="group.key">
              <tr class="tct__group-row" @click="toggleCollapsePhase(group.key)">
                <td colspan="6">
                  <span class="tct__group-toggle">
                    <span class="tct__group-head">
                      <AppIcon
                        name="chevronRight"
                        :size="14"
                        class="tct__group-chevron"
                        :class="{ 'tct__group-chevron--open': !collapsedPhaseKeys.has(group.key) }"
                      />
                      <span class="tct__group-label">{{ group.title }}</span>
                    </span>
                    <span class="tct__group-count">{{ group.tests.length }} testcase</span>
                  </span>
                </td>
              </tr>
              <tr
                v-for="tc in group.tests"
                v-show="!collapsedPhaseKeys.has(group.key)"
                :key="tc.id"
                class="tct__row"
                :class="{ 'tct__row--active': selected?.id === tc.id }"
                @click="selectRow(tc)"
              >
                <td class="tct__cell-title">{{ tc.title }}</td>
                <td>{{ tc.assignee?.name || '—' }}</td>
                <td>
                  <span class="tct__status">
                    <span class="tct__status-dot" :class="`tct__status-dot--${checkMeta(tc.check1.status).tone}`" />
                    {{ checkMeta(tc.check1.status).label }}
                  </span>
                </td>
                <td>
                  <span class="tct__status">
                    <span class="tct__status-dot" :class="`tct__status-dot--${checkMeta(tc.check2.status).tone}`" />
                    {{ checkMeta(tc.check2.status).label }}
                  </span>
                </td>
                <td>
                  <span class="tct__quick-links">
                    <button
                      v-if="tc.link_url"
                      type="button"
                      class="tct__quick-link"
                      aria-label="Xem trước đường dẫn"
                      @click.stop="openLinkPreview(tc)"
                    >
                      <AppIcon name="link" :size="14" />
                    </button>
                    <button
                      v-if="tc.attachment_url"
                      type="button"
                      class="tct__quick-link"
                      aria-label="Xem ảnh đính kèm"
                      @click.stop="openImagePreview(tc)"
                    >
                      <AppIcon name="camera" :size="14" />
                    </button>
                  </span>
                </td>
                <td>{{ formatDate(tc.updated_at) }}</td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Panel chi tiết đẩy ngang -->
    <aside v-if="selected" class="tct__side">
      <header class="tct__side-head">
        <h4 class="tct__side-title">{{ selected.title }}</h4>
        <button type="button" class="tct__icon-btn" aria-label="Đóng chi tiết" @click="selected = null">
          <AppIcon name="close" :size="14" />
        </button>
      </header>

      <div class="tct__fields">
        <div class="tct__field-row">
          <span class="tct__field-label">Giai đoạn</span>
          <span class="tct__field-value">{{ selected.phase?.title || 'Chưa gắn giai đoạn' }}</span>
        </div>
        <div class="tct__field-row">
          <span class="tct__field-label">Người phụ trách</span>
          <span class="tct__field-value">{{ selected.assignee?.name || '—' }}</span>
        </div>
        <div class="tct__field-row">
          <span class="tct__field-label">Người tạo</span>
          <span class="tct__field-value">{{ selected.creator?.name || '—' }}</span>
        </div>
        <div class="tct__field-row">
          <span class="tct__field-label">Cập nhật lúc</span>
          <span class="tct__field-value">{{ formatDate(selected.updated_at) }}</span>
        </div>
      </div>

      <!-- Check lần 1 -->
      <div class="tct__check-block">
        <div class="tct__check-head">
          <h5 class="tct__block-title">Check lần 1 — người thực hiện</h5>
          <span class="tct__status">
            <span class="tct__status-dot" :class="`tct__status-dot--${checkMeta(selected.check1.status).tone}`" />
            {{ checkMeta(selected.check1.status).label }}
          </span>
        </div>
        <p v-if="selected.check1.by" class="tct__check-meta">
          {{ selected.check1.by.name }} · {{ formatDate(selected.check1.at) }}
        </p>
        <div v-if="canCheck1(selected)" class="tct__check-actions">
          <button
            type="button"
            class="tct__btn tct__btn--success"
            :disabled="check1Busy"
            @click="setCheck1(selected, 'passed')"
          >
            Đạt
          </button>
          <button
            type="button"
            class="tct__btn tct__btn--danger"
            :disabled="check1Busy"
            @click="setCheck1(selected, 'failed')"
          >
            Không đạt
          </button>
        </div>
      </div>

      <!-- Check lần 2 -->
      <div class="tct__check-block">
        <div class="tct__check-head">
          <h5 class="tct__block-title">Check lần 2 — người giao việc</h5>
          <span class="tct__status">
            <span class="tct__status-dot" :class="`tct__status-dot--${checkMeta(selected.check2.status).tone}`" />
            {{ checkMeta(selected.check2.status).label }}
          </span>
        </div>
        <p v-if="selected.check2.by" class="tct__check-meta">
          {{ selected.check2.by.name }} · {{ formatDate(selected.check2.at) }}
        </p>
        <p v-if="canCheck2(selected) && selected.check1.status !== 'passed'" class="tct__check-hint">
          Chờ Check lần 1 đạt mới nghiệm thu lần 2.
        </p>
        <div v-if="canCheck2(selected)" class="tct__check-actions">
          <button
            type="button"
            class="tct__btn tct__btn--success"
            :disabled="check2Busy || selected.check1.status !== 'passed'"
            @click="setCheck2(selected, 'passed')"
          >
            Đạt
          </button>
          <button
            type="button"
            class="tct__btn tct__btn--danger"
            :disabled="check2Busy || selected.check1.status !== 'passed'"
            @click="setCheck2(selected, 'failed')"
          >
            Không đạt
          </button>
        </div>
      </div>

      <div class="tct__block">
        <h5 class="tct__block-title">Bước thực hiện</h5>
        <p class="tct__block-body">{{ selected.steps || '—' }}</p>
      </div>
      <div class="tct__block">
        <h5 class="tct__block-title">Kết quả mong đợi</h5>
        <p class="tct__block-body">{{ selected.expected_result || '—' }}</p>
      </div>
      <div class="tct__block">
        <h5 class="tct__block-title">Kết quả thực tế</h5>
        <p class="tct__block-body">{{ selected.actual_result || '—' }}</p>
      </div>

      <div class="tct__block">
        <h5 class="tct__block-title">Đường dẫn tham khảo</h5>
        <button v-if="selected.link_url" type="button" class="tct__link-chip" @click="openLinkPreview(selected)">
          <AppIcon name="link" :size="13" />
          <span class="tct__link-text">{{ selected.link_url }}</span>
        </button>
        <p v-else class="tct__block-body">—</p>
      </div>

      <div class="tct__block">
        <h5 class="tct__block-title">Ảnh minh hoạ</h5>
        <div v-if="selected.attachment_url" class="tct__attachment-preview">
          <button type="button" class="tct__attachment-thumb" @click="openImagePreview(selected)">
            <img :src="selected.attachment_url" alt="">
          </button>
          <button v-if="canManage" type="button" class="tct__link" @click="removeAttachment(selected)">Xoá ảnh</button>
        </div>
        <button
          v-if="canManage && !selected.attachment_url"
          type="button"
          class="tct__btn"
          :disabled="attachmentUploading"
          @click="triggerAttachmentInput"
        >
          {{ attachmentUploading ? 'Đang tải…' : 'Chọn ảnh' }}
        </button>
        <input ref="attachmentInput" type="file" accept="image/*" class="tct__hidden-input" @change="onAttachmentChange">
      </div>

      <div v-if="canManage" class="tct__side-actions">
        <button type="button" class="tct__btn" @click="openEdit(selected)">Sửa</button>
        <button type="button" class="tct__btn tct__btn--danger" @click="removeTestCase(selected)">Xoá</button>
      </div>
    </aside>

    <!-- Form thêm/sửa testcase -->
    <Teleport to="body">
      <div v-if="formOpen" class="tct-modal" role="presentation" @mousedown.self="closeForm">
        <div class="tct-modal__panel" role="dialog" aria-modal="true">
          <header class="tct-modal__head">
            <h2 class="tct-modal__title">{{ editingId ? 'Sửa testcase' : 'Thêm testcase' }}</h2>
            <button type="button" class="tct__icon-btn" aria-label="Đóng" @click="closeForm">
              <AppIcon name="close" :size="16" />
            </button>
          </header>
          <form class="tct-modal__body hide-scrollbar" @submit.prevent="submit">
            <div class="tct-modal__grid">
              <label class="tct-modal__field tct-modal__field--full">
                <span class="tct-modal__label">Tiêu đề<span class="tct-modal__required">*</span></span>
                <input
                  v-model="form.title"
                  class="tct-modal__input"
                  maxlength="255"
                  placeholder="Ví dụ: Đăng nhập bằng tài khoản hợp lệ"
                  required
                >
              </label>
              <div class="tct-modal__field">
                <span class="tct-modal__label">Người phụ trách</span>
                <ProjectUserPicker
                  v-model="form.assignee_id"
                  :users="assignableUsers"
                  search-label="Tìm người phụ trách"
                  placeholder="Chọn người phụ trách"
                />
              </div>
              <label class="tct-modal__field">
                <span class="tct-modal__label">Giai đoạn</span>
                <select v-model="form.phase_id" class="tct-modal__input">
                  <option value="">Chưa gắn giai đoạn</option>
                  <option v-for="phase in phases" :key="phase.id" :value="phase.id">{{ phase.title }}</option>
                </select>
              </label>
              <label class="tct-modal__field">
                <span class="tct-modal__label">Đường dẫn tham khảo</span>
                <input
                  v-model="form.link_url"
                  type="url"
                  class="tct-modal__input"
                  maxlength="2048"
                  placeholder="https://..."
                >
              </label>
              <label class="tct-modal__field tct-modal__field--full">
                <span class="tct-modal__label">Bước thực hiện</span>
                <textarea
                  v-model="form.steps"
                  class="tct-modal__input tct-modal__textarea"
                  rows="3"
                  maxlength="5000"
                  placeholder="Ví dụ: 1. Mở trang đăng nhập&#10;2. Nhập tài khoản hợp lệ&#10;3. Bấm Đăng nhập"
                />
              </label>
              <label class="tct-modal__field">
                <span class="tct-modal__label">Kết quả mong đợi</span>
                <textarea
                  v-model="form.expected_result"
                  class="tct-modal__input tct-modal__textarea"
                  rows="3"
                  maxlength="5000"
                  placeholder="Ví dụ: Đăng nhập thành công, chuyển vào trang chủ"
                />
              </label>
              <label class="tct-modal__field">
                <span class="tct-modal__label">Kết quả thực tế</span>
                <textarea
                  v-model="form.actual_result"
                  class="tct-modal__input tct-modal__textarea"
                  rows="3"
                  maxlength="5000"
                  placeholder="Ghi lại kết quả sau khi chạy thử (tuỳ chọn)"
                />
              </label>
            </div>
          </form>
          <div class="tct-modal__actions">
            <button type="button" class="tct__btn" :disabled="saving" @click="closeForm">Huỷ bỏ</button>
            <button type="button" class="tct__btn tct__btn--primary" :disabled="saving" @click="submit">
              {{ saving ? 'Đang lưu…' : (editingId ? 'Cập nhật' : 'Thêm') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Preview ảnh (lightbox có sẵn) -->
    <FilePreviewDialog v-model:open="previewOpen" v-model:file="previewFile" :files="[]" />

    <!-- Preview link — iframe lớn, không dùng FilePreviewDialog (không hỗ trợ URL ngoài) -->
    <Teleport to="body">
      <div v-if="linkPreviewOpen" class="tct-link-preview" role="presentation" @mousedown.self="closeLinkPreview">
        <div class="tct-link-preview__panel" role="dialog" aria-modal="true">
          <header class="tct-link-preview__head">
            <span class="tct-link-preview__url">{{ linkPreviewUrl }}</span>
            <div class="tct-link-preview__actions">
              <a class="tct__btn" :href="linkPreviewUrl" target="_blank" rel="noopener">
                Mở trong tab mới
              </a>
              <button type="button" class="tct__icon-btn" aria-label="Đóng" @click="closeLinkPreview">
                <AppIcon name="close" :size="16" />
              </button>
            </div>
          </header>
          <iframe class="tct-link-preview__frame" :src="linkPreviewUrl" />
        </div>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
.tct {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--space-4);
}

.tct:has(.tct__side) {
  grid-template-columns: minmax(0, 1fr) 24rem;
}

.tct__main {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-5);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.tct__empty {
  margin: 0;
  padding: var(--space-6) 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-3);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  text-align: center;
}

.tct__empty-add {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  height: 2rem;
  padding: 0 0.875rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.tct__empty-add:hover {
  background: var(--color-primary-hover);
}

.tct__table-wrap {
  overflow-x: auto;
}

.tct__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.tct__table th {
  padding: 0.5rem 0.75rem;
  color: var(--color-text-muted);
  font-weight: 600;
  text-align: left;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.tct__table td {
  padding: 0.625rem 0.75rem;
  color: var(--color-text);
  box-shadow: 0 1px 0 var(--color-border);
}

.tct__cell-title {
  font-weight: 600;
}

.tct__group-row {
  cursor: pointer;
}

.tct__group-row td {
  position: relative;
  width: 100%;
  padding: var(--space-2) var(--space-4) var(--space-2) calc(var(--space-4) + 3px + var(--space-2)) !important;
  background: var(--color-surface-muted);
  color: var(--color-text);
  box-shadow: inset 0 -2px 0 var(--color-border) !important;
}

.tct__group-row td::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  background: var(--color-gold);
}

.tct__group-row:hover td {
  background: color-mix(in srgb, var(--color-gold) 6%, var(--color-surface));
}

.tct__group-toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  width: 100%;
  min-width: 0;
}

.tct__group-head {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-width: 0;
}

.tct__group-chevron {
  flex-shrink: 0;
  color: var(--color-gold);
  transition: transform 0.15s ease;
}

.tct__group-chevron--open {
  transform: rotate(90deg);
}

.tct__group-label {
  min-width: 0;
  overflow: hidden;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tct__group-count {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
}

.tct__row {
  cursor: pointer;
  transition: background 0.12s ease;
}

.tct__row:hover {
  background: var(--color-surface-muted);
}

.tct__row--active {
  background: var(--color-secondary-surface);
}

.tct__status {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  white-space: nowrap;
}

.tct__status-dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.tct__status-dot--success { background: var(--color-success); }
.tct__status-dot--danger { background: var(--color-danger); }
.tct__status-dot--neutral { background: var(--color-text-muted); }

.tct__quick-links {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}

.tct__quick-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  cursor: pointer;
}

.tct__quick-link:hover {
  background: var(--color-secondary-surface);
  color: var(--color-secondary-700);
}

.tct__side {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  max-height: 46rem;
  overflow-y: auto;
}

.tct__side-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  padding-bottom: var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
}

.tct__side-title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 700;
}

.tct__icon-btn {
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

.tct__icon-btn:hover {
  background: var(--color-surface-muted);
}

.tct__fields {
  display: flex;
  flex-direction: column;
}

.tct__field-row {
  display: flex;
  justify-content: space-between;
  gap: var(--space-3);
  padding: 0.5rem 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.tct__field-row:last-child {
  box-shadow: none;
}

.tct__field-label {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.tct__field-value {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 500;
  text-align: right;
}

.tct__check-block {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.tct__check-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.tct__check-meta {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.tct__check-hint {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-style: italic;
}

.tct__check-actions {
  display: flex;
  gap: var(--space-2);
}

.tct__block {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.tct__block-title {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.tct__block-body {
  margin: 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  line-height: 1.5;
  white-space: pre-wrap;
}

.tct__link-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  max-width: 100%;
  padding: 0.375rem 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.tct__link-text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tct__attachment-preview {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.tct__attachment-thumb {
  padding: 0;
  border: none;
  border-radius: var(--radius-md);
  overflow: hidden;
  cursor: pointer;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.tct__attachment-thumb img {
  display: block;
  width: 5rem;
  height: 5rem;
  object-fit: cover;
}

.tct__hidden-input {
  display: none;
}

.tct__side-actions {
  display: flex;
  gap: var(--space-2);
}

.tct__btn {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  height: 2rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
}

.tct__btn:hover:not(:disabled) {
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.tct__btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.tct__btn--primary:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.tct__btn--success {
  color: var(--color-success);
  background: var(--color-success-tint-bg);
}

.tct__btn--danger {
  color: var(--color-danger-tint-fg);
  background: var(--color-danger-tint-bg);
}

.tct__btn:disabled {
  opacity: 0.6;
  cursor: default;
}

.tct__link {
  border: none;
  padding: 0;
  background: none;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.tct__link:hover {
  color: var(--color-danger-tint-fg);
}

/* ---- Modal thêm/sửa ---- */
.tct-modal {
  position: fixed;
  inset: 0;
  z-index: 1300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.tct-modal__panel {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  width: min(52rem, calc(100vw - 2.5rem));
  max-height: calc(100vh - 2.5rem);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.tct-modal__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.25rem;
  box-shadow: 0 1px 0 var(--color-border);
}

.tct-modal__title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 700;
}

.tct-modal__body {
  flex: 1;
  min-height: 0;
  padding: 1.25rem;
  overflow: auto;
}

.tct-modal__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-4);
}

.tct-modal__field {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.375rem;
}

.tct-modal__field--full {
  grid-column: 1 / -1;
}

.tct-modal__label {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
}

.tct-modal__required {
  margin-left: 0.125rem;
  color: var(--color-danger);
}

.tct-modal__input {
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

.tct-modal__input:focus {
  outline: 2px solid color-mix(in srgb, var(--color-primary) 35%, transparent);
  outline-offset: 1px;
}

.tct-modal__textarea {
  resize: vertical;
  min-height: 4.5rem;
}

.tct-modal__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  padding: 0.875rem 1.25rem 1.125rem;
  box-shadow: 0 -1px 0 var(--color-border);
}

/* ---- Preview link (iframe) ---- */
.tct-link-preview {
  position: fixed;
  inset: 0;
  z-index: 1400;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.tct-link-preview__panel {
  display: flex;
  flex-direction: column;
  width: min(72rem, calc(100vw - 2.5rem));
  height: calc(100vh - 2.5rem);
  border-radius: var(--radius-lg);
  overflow: hidden;
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.tct-link-preview__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: 0.75rem 1rem;
  box-shadow: 0 1px 0 var(--color-border);
}

.tct-link-preview__url {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.tct-link-preview__actions {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: var(--space-2);
}

.tct-link-preview__frame {
  flex: 1;
  min-height: 0;
  border: none;
  background: var(--color-surface-muted);
}

@media (max-width: 768px) {
  .tct:has(.tct__side) {
    grid-template-columns: minmax(0, 1fr);
  }

  .tct-modal__grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
