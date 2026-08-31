<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { onBeforeRouteLeave, useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';

const STEPS = [
  { key: 'period', label: 'Kỳ báo cáo' },
  { key: 'scope', label: 'Phạm vi nhân sự' },
  { key: 'display', label: 'Cột và tiêu chí' },
  { key: 'viewers', label: 'Người được xem' },
  { key: 'confirm', label: 'Xem lại' },
];

const COLUMN_OPTIONS = [
  { key: 'start_score', label: 'Điểm khởi đầu' },
  { key: 'task_adjustment', label: 'Điểm công việc' },
  { key: 'bonus', label: 'Điểm cộng' },
  { key: 'penalty', label: 'Điểm trừ' },
  { key: 'final_score', label: 'Điểm cuối' },
  { key: 'classification', label: 'Xếp loại' },
];

const router = useRouter();

const stepIndex = ref(0);
const saving = ref(false);
const loadingOptions = ref(false);
const members = ref([]);
const criteria = ref([]);
const formErrors = ref({});

/** Đã tạo xong thì rời trang không cần hỏi lại. */
const submitted = ref(false);
const leaveConfirm = ref(null);

/** Tìm nhanh trong danh sách nhân sự — phòng ban đông thì cuộn tìm rất mệt. */
const memberQuery = ref('');
const viewerQuery = ref('');

/** Xem trước số liệu thật ở bước cuối. */
const preview = ref(null);
const previewLoading = ref(false);
const previewError = ref('');

const today = new Date();
const firstOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
const lastOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);

const form = reactive({
  title: `Báo cáo đánh giá nhân sự tháng ${String(today.getMonth() + 1).padStart(2, '0')}/${today.getFullYear()}`,
  period_type: 'month',
  period_from: firstOfMonth.toISOString().slice(0, 10),
  period_to: lastOfMonth.toISOString().slice(0, 10),
  filter_user_ids: [],
  viewer_user_ids: [],
  column_keys: ['start_score', 'bonus', 'penalty', 'final_score', 'classification'],
  criterion_ids: [],
});

const currentStep = computed(() => STEPS[stepIndex.value]);
const isLastStep = computed(() => stepIndex.value === STEPS.length - 1);

const canGoNext = computed(() => {
  if (currentStep.value.key === 'period') {
    return (
      Boolean(form.title.trim()) && Boolean(form.period_from) && Boolean(form.period_to) &&
      form.period_from <= form.period_to
    );
  }
  if (currentStep.value.key === 'display') {
    return form.column_keys.length > 0;
  }
  return true;
});

const scopeText = computed(() =>
  form.filter_user_ids.length === 0
    ? 'Toàn bộ nhân sự phòng ban'
    : `${form.filter_user_ids.length} nhân sự được chọn`,
);

const viewerText = computed(() =>
  form.viewer_user_ids.length === 0
    ? 'Chưa chia sẻ cho ai ngoài người quản lý phòng ban'
    : `${form.viewer_user_ids.length} người được xem`,
);

const criteriaText = computed(() =>
  form.criterion_ids.length === 0
    ? 'Hiện toàn bộ tiêu chí'
    : `${form.criterion_ids.length} tiêu chí được chọn`,
);

const columnText = computed(() =>
  COLUMN_OPTIONS.filter((col) => form.column_keys.includes(col.key))
    .map((col) => col.label)
    .join(', '),
);

function applyPeriodType(type) {
  form.period_type = type;
  const now = new Date();

  if (type === 'month') {
    form.period_from = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
    form.period_to = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10);
    return;
  }

  if (type === 'quarter') {
    const quarterStartMonth = Math.floor(now.getMonth() / 3) * 3;
    form.period_from = new Date(now.getFullYear(), quarterStartMonth, 1).toISOString().slice(0, 10);
    form.period_to = new Date(now.getFullYear(), quarterStartMonth + 3, 0).toISOString().slice(0, 10);
  }
}

function toggleInList(list, value) {
  const index = list.indexOf(value);
  if (index === -1) {
    list.push(value);
  } else {
    list.splice(index, 1);
  }
}

/** Bỏ dấu để tìm "nguyen" ra "Nguyễn". */
function searchable(text) {
  return String(text ?? '')
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/đ/gi, 'd')
    .toLowerCase();
}

function filterMembers(list, keyword) {
  const needle = searchable(keyword).trim();
  if (!needle) return list;
  return list.filter((member) => searchable(member.name).includes(needle));
}

const filteredMembers = computed(() => filterMembers(members.value, memberQuery.value));
const filteredViewers = computed(() => filterMembers(members.value, viewerQuery.value));

/** Chọn tất cả = liệt kê rõ từng người, khác với "để trống = toàn phòng ban". */
function selectAllMembers() {
  form.filter_user_ids = members.value.map((member) => member.id);
}

function clearMembers() {
  form.filter_user_ids = [];
}

function selectAllViewers() {
  form.viewer_user_ids = members.value.map((member) => member.id);
}

function clearViewers() {
  form.viewer_user_ids = [];
}

function selectAllCriteria() {
  form.criterion_ids = criteria.value.map((item) => item.id);
}

function clearCriteria() {
  form.criterion_ids = [];
}

function selectAllColumns() {
  form.column_keys = COLUMN_OPTIONS.map((col) => col.key);
}

function clearColumns() {
  // Giữ lại điểm cuối: bảng không còn cột nào thì không xem được gì.
  form.column_keys = ['final_score'];
}

function goNext() {
  if (!canGoNext.value) return;
  if (!isLastStep.value) {
    stepIndex.value += 1;
    if (isLastStep.value) {
      loadPreview();
    }
  }
}

function goBack() {
  if (stepIndex.value > 0) stepIndex.value -= 1;
}

/**
 * Xem trước bằng số liệu thật — bước cuối chỉ liệt kê lựa chọn thì người dùng
 * vẫn phải tạo xong mới biết báo cáo ra sao.
 */
async function loadPreview() {
  previewLoading.value = true;
  previewError.value = '';
  try {
    const { data } = await window.axios.post('/api/report/personnel-evaluation/preview', {
      period_from: form.period_from,
      period_to: form.period_to,
      filter_user_ids: form.filter_user_ids,
    });
    preview.value = data;
  } catch (error) {
    preview.value = null;
    previewError.value =
      error?.response?.data?.message ?? 'Chưa xem trước được số liệu. Bạn vẫn tạo báo cáo bình thường.';
  } finally {
    previewLoading.value = false;
  }
}

async function loadOptions() {
  loadingOptions.value = true;
  try {
    const { data } = await window.axios.get('/api/evaluation/events');
    members.value = data.members ?? [];
    criteria.value = data.criteria ?? [];
  } catch {
    members.value = [];
    criteria.value = [];
  } finally {
    loadingOptions.value = false;
  }
}

async function submit() {
  if (saving.value) return;

  saving.value = true;
  formErrors.value = {};
  try {
    const { data } = await window.axios.post('/api/report/personnel-evaluation', { ...form });
    submitted.value = true;
    showClientToast('success', 'Đã tạo báo cáo.');
    router.push({ name: 'manager.reports.show', params: { id: data.report.id } });
  } catch (error) {
    formErrors.value = error?.response?.data?.errors ?? {};
    showClientToast('error', error?.response?.data?.message ?? 'Không tạo được báo cáo.');
  } finally {
    saving.value = false;
  }
}

/**
 * Đã bước qua bước đầu, hoặc đã chọn gì đó, thì coi như có dữ liệu đang dở —
 * 5 bước mà lỡ bấm back là mất sạch.
 */
const isDirty = computed(
  () =>
    stepIndex.value > 0 ||
    form.filter_user_ids.length > 0 ||
    form.viewer_user_ids.length > 0 ||
    form.criterion_ids.length > 0,
);

function requestLeave() {
  if (!isDirty.value || submitted.value) {
    router.push({ name: 'manager.reports.index' });
    return;
  }
  leaveConfirm.value = { to: { name: 'manager.reports.index' } };
}

function confirmLeave() {
  const target = leaveConfirm.value;
  leaveConfirm.value = null;
  submitted.value = true; // bỏ chặn cho lần điều hướng này
  if (target?.next) {
    target.next();
    return;
  }
  router.push(target?.to ?? { name: 'manager.reports.index' });
}

function cancelLeave() {
  const next = leaveConfirm.value?.next;
  leaveConfirm.value = null;
  next?.(false);
}

onBeforeRouteLeave((to, from, next) => {
  if (!isDirty.value || submitted.value) {
    next();
    return;
  }
  leaveConfirm.value = { next };
});

onMounted(loadOptions);
</script>

<template>
  <section class="report-create">
    <PageHeader
      title="Tạo báo cáo đánh giá nhân sự"
      icon="barChart"
      description="Chọn kỳ, phạm vi nhân sự, cột hiển thị và người được xem, rồi lưu lại để mở lại về sau."
    >
      <template #actions>
        <button type="button" class="report-create__header-btn" @click="requestLeave">
          <AppIcon name="close" :size="16" />
          Thoát
        </button>
      </template>
    </PageHeader>

    <div class="report-create__steps">
      <span
        v-for="(step, index) in STEPS"
        :key="step.key"
        class="report-create__step"
        :class="{
          'report-create__step--current': index === stepIndex,
          'report-create__step--done': index < stepIndex,
        }"
      >
        Bước {{ index + 1 }}. {{ step.label }}
      </span>
    </div>

    <div class="report-create__body hide-scrollbar">
      <div v-if="currentStep.key === 'period'" class="report-create__grid">
        <div class="report-create__field report-create__span">
          <label class="report-create__label" for="create-title">Tên báo cáo</label>
          <input id="create-title" v-model="form.title" type="text" class="report-create__input" />
          <p v-if="formErrors.title" class="report-create__error">{{ formErrors.title[0] }}</p>
        </div>

        <div class="report-create__field">
          <label class="report-create__label" for="create-period-type">Kiểu kỳ</label>
          <select
            id="create-period-type"
            :value="form.period_type"
            class="report-create__input"
            @change="applyPeriodType($event.target.value)"
          >
            <option value="month">Theo tháng</option>
            <option value="quarter">Theo quý</option>
            <option value="custom">Tự chọn khoảng ngày</option>
          </select>
        </div>

        <div class="report-create__field">
          <label class="report-create__label" for="create-from">Từ ngày</label>
          <input id="create-from" v-model="form.period_from" type="date" class="report-create__input" />
          <p v-if="formErrors.period_from" class="report-create__error">{{ formErrors.period_from[0] }}</p>
        </div>

        <div class="report-create__field">
          <label class="report-create__label" for="create-to">Đến ngày</label>
          <input id="create-to" v-model="form.period_to" type="date" class="report-create__input" />
          <p v-if="formErrors.period_to" class="report-create__error">{{ formErrors.period_to[0] }}</p>
        </div>
      </div>

      <div v-else-if="currentStep.key === 'scope'" class="report-create__section">
        <p class="report-create__section-note">
          Không chọn ai thì báo cáo tính cho toàn bộ nhân sự đang hoạt động của phòng ban.
        </p>

        <div class="report-create__tools">
          <input
            v-model="memberQuery"
            type="search"
            class="report-create__input report-create__search"
            aria-label="Tìm nhân sự theo tên"
          />
          <button type="button" class="report-create__btn report-create__btn--ghost" @click="selectAllMembers">
            Chọn tất cả
          </button>
          <button type="button" class="report-create__btn report-create__btn--ghost" @click="clearMembers">
            Bỏ chọn tất cả
          </button>
          <span class="report-create__count">{{ scopeText }}</span>
        </div>

        <p v-if="loadingOptions" class="report-create__empty">Đang tải danh sách nhân sự…</p>
        <p v-else-if="members.length === 0" class="report-create__empty">
          Phòng ban chưa có nhân sự nào đang hoạt động.
        </p>
        <p v-else-if="filteredMembers.length === 0" class="report-create__empty">
          Không có nhân sự nào khớp với từ khoá đang tìm.
        </p>
        <div v-else class="report-create__checks">
          <label v-for="member in filteredMembers" :key="member.id" class="report-create__check">
            <input
              type="checkbox"
              :checked="form.filter_user_ids.includes(member.id)"
              @change="toggleInList(form.filter_user_ids, member.id)"
            />
            <span>{{ member.name }}</span>
          </label>
        </div>
      </div>

      <div v-else-if="currentStep.key === 'display'" class="report-create__section">
        <h3 class="report-create__section-title">Cột hiển thị trong bảng</h3>
        <div class="report-create__tools">
          <button type="button" class="report-create__btn report-create__btn--ghost" @click="selectAllColumns">
            Chọn tất cả
          </button>
          <button type="button" class="report-create__btn report-create__btn--ghost" @click="clearColumns">
            Chỉ giữ điểm cuối
          </button>
        </div>
        <div class="report-create__checks">
          <label v-for="col in COLUMN_OPTIONS" :key="col.key" class="report-create__check">
            <input
              type="checkbox"
              :checked="form.column_keys.includes(col.key)"
              @change="toggleInList(form.column_keys, col.key)"
            />
            <span>{{ col.label }}</span>
          </label>
        </div>

        <h3 class="report-create__section-title">Tiêu chí đưa vào phần chi tiết</h3>
        <p class="report-create__section-note">
          Không chọn tiêu chí nào thì phần chi tiết hiện toàn bộ ghi nhận.
        </p>
        <div v-if="criteria.length > 0" class="report-create__tools">
          <button type="button" class="report-create__btn report-create__btn--ghost" @click="selectAllCriteria">
            Chọn tất cả
          </button>
          <button type="button" class="report-create__btn report-create__btn--ghost" @click="clearCriteria">
            Bỏ chọn tất cả
          </button>
          <span class="report-create__count">{{ criteriaText }}</span>
        </div>
        <p v-if="criteria.length === 0" class="report-create__empty">
          Phòng ban chưa có tiêu chí cộng / trừ điểm theo hành vi nào.
        </p>
        <div v-else class="report-create__checks">
          <label v-for="item in criteria" :key="item.id" class="report-create__check">
            <input
              type="checkbox"
              :checked="form.criterion_ids.includes(item.id)"
              @change="toggleInList(form.criterion_ids, item.id)"
            />
            <span>
              {{ item.criterion_type_name ? `${item.criterion_type_name} — ` : '' }}{{ item.name }}
            </span>
          </label>
        </div>
      </div>

      <div v-else-if="currentStep.key === 'viewers'" class="report-create__section">
        <p class="report-create__section-note">
          Người quản lý phòng ban luôn xem được. Chọn thêm những người khác cần xem báo cáo này.
        </p>

        <div v-if="members.length > 0" class="report-create__tools">
          <input
            v-model="viewerQuery"
            type="search"
            class="report-create__input report-create__search"
            aria-label="Tìm người được xem theo tên"
          />
          <button type="button" class="report-create__btn report-create__btn--ghost" @click="selectAllViewers">
            Chọn tất cả
          </button>
          <button type="button" class="report-create__btn report-create__btn--ghost" @click="clearViewers">
            Bỏ chọn tất cả
          </button>
          <span class="report-create__count">{{ viewerText }}</span>
        </div>

        <p v-if="members.length === 0" class="report-create__empty">
          Phòng ban chưa có nhân sự nào đang hoạt động.
        </p>
        <p v-else-if="filteredViewers.length === 0" class="report-create__empty">
          Không có nhân sự nào khớp với từ khoá đang tìm.
        </p>
        <div v-else class="report-create__checks">
          <label v-for="member in filteredViewers" :key="member.id" class="report-create__check">
            <input
              type="checkbox"
              :checked="form.viewer_user_ids.includes(member.id)"
              @change="toggleInList(form.viewer_user_ids, member.id)"
            />
            <span>{{ member.name }}</span>
          </label>
        </div>
      </div>

      <div v-else class="report-create__rows">
        <div class="report-create__row">
          <span class="report-create__row-label">Tên báo cáo</span>
          <span class="report-create__row-value">{{ form.title }}</span>
        </div>
        <div class="report-create__row">
          <span class="report-create__row-label">Kỳ báo cáo</span>
          <span class="report-create__row-value">{{ form.period_from }} — {{ form.period_to }}</span>
        </div>
        <div class="report-create__row">
          <span class="report-create__row-label">Phạm vi nhân sự</span>
          <span class="report-create__row-value">{{ scopeText }}</span>
        </div>
        <div class="report-create__row">
          <span class="report-create__row-label">Cột hiển thị</span>
          <span class="report-create__row-value">{{ columnText }}</span>
        </div>
        <div class="report-create__row">
          <span class="report-create__row-label">Tiêu chí chi tiết</span>
          <span class="report-create__row-value">{{ criteriaText }}</span>
        </div>
        <div class="report-create__row">
          <span class="report-create__row-label">Người được xem</span>
          <span class="report-create__row-value">{{ viewerText }}</span>
        </div>

        <div class="report-create__preview">
          <h3 class="report-create__section-title">Xem trước số liệu</h3>

          <p v-if="previewLoading" class="report-create__empty">Đang tính thử số liệu…</p>
          <p v-else-if="previewError" class="report-create__empty">{{ previewError }}</p>
          <template v-else-if="preview">
            <p class="report-create__section-note">
              Tính thử theo phiên bản khung chấm điểm số {{ preview.version_no }}. Số liệu chính
              thức được tính lại khi bạn tạo báo cáo.
            </p>

            <div class="report-create__preview-stats">
              <span>Tổng nhân sự: {{ preview.summary.total_people }}</span>
              <span>Điểm trung bình: {{ preview.summary.average_score }}</span>
              <span>Cao nhất: {{ preview.summary.highest_score }}</span>
              <span>Thấp nhất: {{ preview.summary.lowest_score }}</span>
            </div>

            <p v-if="preview.summary.missing_total > 0" class="report-create__preview-warning">
              Có {{ preview.summary.missing_total }} công việc chưa đủ dữ liệu nên được tính như
              mức trung bình.
            </p>

            <div v-if="preview.rows.length" class="report-create__rows">
              <div
                v-for="row in preview.rows"
                :key="row.user_id"
                class="report-create__row"
              >
                <span class="report-create__row-label">{{ row.user_name }}</span>
                <span class="report-create__row-value">
                  {{ row.final_score }}
                  <template v-if="row.classification_label">
                    · {{ row.classification_label }}
                  </template>
                </span>
              </div>
            </div>
            <p v-else class="report-create__empty">
              Chưa có nhân sự nào trong phạm vi đã chọn.
            </p>
          </template>
        </div>
      </div>
    </div>

    <div class="report-create__foot">
      <button
        type="button"
        class="report-create__btn report-create__btn--ghost"
        :disabled="stepIndex === 0"
        @click="goBack"
      >
        Quay lại
      </button>
      <button
        v-if="!isLastStep"
        type="button"
        class="report-create__btn"
        :disabled="!canGoNext"
        @click="goNext"
      >
        Tiếp tục
      </button>
      <button v-else type="button" class="report-create__btn" :disabled="saving" @click="submit">
        {{ saving ? 'Đang tạo…' : 'Tạo báo cáo' }}
      </button>
    </div>

    <Teleport to="body">
      <div
        v-if="leaveConfirm"
        class="report-create__confirm"
        role="alertdialog"
        aria-modal="true"
        aria-label="Xác nhận rời trang"
      >
        <div class="report-create__confirm-backdrop" @click="cancelLeave" />
        <div class="report-create__confirm-panel">
          <h2 class="report-create__confirm-title">Rời khỏi trang tạo báo cáo?</h2>
          <p class="report-create__confirm-text">
            Những gì bạn đã chọn sẽ mất và phải làm lại từ bước đầu.
          </p>
          <div class="report-create__confirm-foot">
            <button
              type="button"
              class="report-create__btn report-create__btn--ghost"
              @click="cancelLeave"
            >
              Ở lại tiếp tục
            </button>
            <button type="button" class="report-create__btn" @click="confirmLeave">
              Rời đi và bỏ dữ liệu
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
.report-create {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
}

.report-create__header-btn {
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

.report-create__header-btn:hover {
  background: var(--color-surface-muted);
}

.report-create__steps {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-4);
  margin: var(--space-3) 0;
  padding-bottom: var(--space-2);
  box-shadow: 0 1px 0 var(--color-border);
}

.report-create__step {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.report-create__step--current {
  color: var(--color-primary);
  font-weight: 600;
}

.report-create__step--done {
  color: var(--color-text);
}

.report-create__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
}

.report-create__grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-4);
}

.report-create__span {
  grid-column: 1 / -1;
}

.report-create__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
}

.report-create__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.report-create__input {
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

.report-create__error {
  margin: 0;
  color: var(--color-primary);
  font-size: 0.75rem;
}

.report-create__section {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.report-create__section-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
}

.report-create__section-title {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 600;
}

.report-create__section-note {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

/* Hàng công cụ: ô tìm + nút chọn nhanh + số đang chọn. */
.report-create__tools {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
}

.report-create__search {
  width: auto;
  min-width: 14rem;
  flex: 1 1 14rem;
}

.report-create__count {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.report-create__checks {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-2);
}

.report-create__check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.report-create__empty {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.report-create__rows {
  display: flex;
  flex-direction: column;
}

.report-create__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.report-create__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.report-create__row-label::after {
  content: ':';
}

.report-create__row-value {
  color: var(--color-text);
  font-style: italic;
  text-align: right;
  overflow-wrap: anywhere;
}

.report-create__foot {
  flex-shrink: 0;
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  margin-top: var(--space-3);
}

.report-create__btn {
  height: 2.375rem;
  padding: 0 1rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.report-create__btn:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.report-create__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.report-create__btn--ghost {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.report-create__btn--ghost:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

/* Xem trước ở bước cuối — số liệu thật, tách khỏi phần liệt kê lựa chọn. */
.report-create__preview {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin-top: var(--space-4);
  padding-top: var(--space-4);
  box-shadow: 0 -1px 0 var(--color-border) inset;
}

.report-create__preview-stats {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-4);
  color: var(--color-text);
  font-size: 0.8125rem;
  font-variant-numeric: tabular-nums;
}

.report-create__preview-warning {
  margin: 0;
  padding: var(--space-2) var(--space-3);
  border: 1px solid var(--color-warning-tint-border);
  border-radius: var(--radius-md);
  background: var(--color-warning-tint-bg);
  color: var(--color-warning-tint-fg);
  font-size: 0.8125rem;
}

.report-create__confirm {
  position: fixed;
  inset: 0;
  z-index: 90;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
}

.report-create__confirm-backdrop {
  position: absolute;
  inset: 0;
  background: color-mix(in srgb, var(--color-text) 45%, transparent);
}

.report-create__confirm-panel {
  position: relative;
  z-index: 1;
  width: min(28rem, 100%);
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.report-create__confirm-title {
  margin: 0 0 var(--space-2);
  font-size: 1rem;
  font-weight: 600;
}

.report-create__confirm-text {
  margin: 0 0 var(--space-4);
  color: var(--color-text-muted);
  font-size: 0.875rem;
  line-height: 1.5;
}

.report-create__confirm-foot {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: var(--space-2);
}

@media (max-width: 768px) {
  .report-create__grid,
  .report-create__checks {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 480px) {
  .report-create {
    padding: var(--space-3);
  }

  .report-create__grid,
  .report-create__checks {
    grid-template-columns: 1fr;
  }
}
</style>
