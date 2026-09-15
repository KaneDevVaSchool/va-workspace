<script setup>
//
// manager/project/settings — Cài đặt dự án dùng chung toàn hệ thống:
// mẫu mã + bộ đếm, các quy tắc hoạt động, danh sách nhân sự được phép tạo
// dự án. Trang riêng (không phải modal) — chỉ admin/super_admin
// (project.manage_settings), route Vue guard bằng requiresPermission.
//
// Bố cục chia theo nhóm màu sắc (mã số, tiến độ, quy tắc, nhân sự) để người
// dùng ít rành công nghệ dễ định vị — mỗi nhóm 1 icon + 1 màu chủ đề riêng,
// vẫn dùng token theme (không hard-code hex), không badge/pill, field ngay hàng.
//
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import ProjectMemberPicker from '../components/ProjectMemberPicker.vue';

const DEFAULT_PATTERN = 'DA_{date,"m/Y"}_{count}';
const DEFAULT_COUNTER = 344;
const DEFAULT_TASK_PATTERN = 'CV_{date,"m/Y"}_{count}';
const DEFAULT_TASK_COUNTER = 1;

const RULES = [
  {
    key: 'auto_start_on_begin_date',
    icon: 'clock',
    title: 'Khi đến ngày bắt đầu thì trạng thái dự án tự động chuyển sang Đang thực hiện',
    example:
      'Khi bật cài đặt này thì, ví dụ: Dự án A có ngày bắt đầu là 15/03/2020, khi đến ngày 15/03/2020, dự án A sẽ tự động chuyển từ trạng thái Đang chờ sang Đang thực hiện',
  },
  {
    key: 'shift_task_dates_with_project',
    icon: 'gitBranch',
    title: 'Khi thời gian thực hiện dự án thay đổi thì thời gian công việc thay đổi theo',
    example:
      'Khi bật cài đặt này thì, ví dụ: Thời gian thực hiện dự án là 10/03/2020 - 20/03/2020, công việc X thuộc dự án A có thời gian thực hiện là 11/03/2020 - 15/03/2020. Khi dự án A được tịnh tiến 3 ngày, tức thời gian thực hiện là 13/03/2020 – 20/03/2020, thì thời gian công việc X cũng tịnh tiến thêm 3 ngày, tức là 14/03/2020 - 15/03/2020.',
    note: 'Chỉ áp dụng với công việc đang thực hiện, chờ thực hiện',
  },
  {
    key: 'hide_cross_tasks_from_assignees',
    icon: 'eyeOff',
    title: 'Không cho phép người thực hiện công việc xem chéo các công việc khác',
    example:
      'Khi bật cài đặt này thì, ví dụ: Dự án A gồm 2 công việc B và C, người thực hiện công việc B sẽ không được xem công việc C nếu người đó không phải là người thực hiện công việc C',
  },
  {
    key: 'hide_child_tasks_from_followers',
    icon: 'eyeOff',
    title: 'Không cho phép người theo dõi xem được các công việc con',
    example:
      'Khi bật cài đặt này thì, ví dụ: Dự án A gồm 2 công việc B và C, người theo dõi dự án A sẽ không được xem công việc B và C nếu người đó không phải là người theo dõi công việc B và C',
  },
  {
    key: 'constrain_task_dates_to_project',
    icon: 'calendar',
    title: 'Thời gian dự kiến thực hiện công việc phải nằm trong khoảng thời gian của dự án',
    example: 'Khi bật cài đặt này, thời gian dự kiến thực hiện công việc sẽ phải nằm trong khoảng thời gian của dự án.',
  },
];

const router = useRouter();

const loading = ref(true);
const saving = ref(false);

const general = reactive({
  code_pattern: DEFAULT_PATTERN,
  code_counter: DEFAULT_COUNTER,
  task_code_pattern: DEFAULT_TASK_PATTERN,
  task_code_counter: DEFAULT_TASK_COUNTER,
  auto_start_on_begin_date: false,
  default_progress_method: 'average',
  shift_task_dates_with_project: false,
  hide_cross_tasks_from_assignees: false,
  hide_child_tasks_from_followers: false,
  constrain_task_dates_to_project: false,
});

const allUsers = ref([]);
const allowlistUserIds = ref([]);
const progressMethodOptions = ref([]);

const activeRuleCount = computed(() => RULES.filter((rule) => general[rule.key]).length);

function clientPreview(pattern, counter) {
  if (!pattern) return '—';
  let result = String(pattern);
  result = result.replace(/\{date,"([^"]+)"\}/g, (_, format) => formatDateWithPhpFormat(new Date(), format));
  result = result.replace(/\{date:'([^']+)'\}/g, (_, format) => formatDateWithPhpFormat(new Date(), format));
  result = result.replace(/\{date:([^}]+)\}/g, (_, format) => formatDateWithPhpFormat(new Date(), format));
  result = result.replace(/\{count:(\d+)\}/g, (_, n) => String(counter).padStart(Number(n), '0'));
  result = result.replace(/\{count\}/g, String(counter));
  return result;
}

function formatDateWithPhpFormat(date, format) {
  const pad = (n) => String(n).padStart(2, '0');
  const map = {
    Y: String(date.getFullYear()),
    y: String(date.getFullYear()).slice(-2),
    m: pad(date.getMonth() + 1),
    n: String(date.getMonth() + 1),
    d: pad(date.getDate()),
    j: String(date.getDate()),
  };
  return format.replace(/[Yymndj]/g, (ch) => map[ch] ?? ch);
}

const nextCodePreview = computed(() =>
  clientPreview(general.code_pattern, Number(general.code_counter) || 0),
);

const nextTaskCodePreview = computed(() =>
  clientPreview(general.task_code_pattern, Number(general.task_code_counter) || 0),
);

function applyGeneral(data) {
  general.code_pattern = data.code_pattern ?? DEFAULT_PATTERN;
  general.code_counter = data.code_counter ?? DEFAULT_COUNTER;
  general.task_code_pattern = data.task_code_pattern ?? DEFAULT_TASK_PATTERN;
  general.task_code_counter = data.task_code_counter ?? DEFAULT_TASK_COUNTER;
  general.auto_start_on_begin_date = Boolean(data.auto_start_on_begin_date);
  general.default_progress_method = data.default_progress_method || 'average';
  general.shift_task_dates_with_project = Boolean(data.shift_task_dates_with_project);
  general.hide_cross_tasks_from_assignees = Boolean(data.hide_cross_tasks_from_assignees);
  general.hide_child_tasks_from_followers = Boolean(data.hide_child_tasks_from_followers);
  general.constrain_task_dates_to_project = Boolean(data.constrain_task_dates_to_project);
}

function toggleRule(key) {
  if (saving.value) return;
  general[key] = !general[key];
}

async function loadGeneral() {
  try {
    const { data } = await window.axios.get('/api/project/settings/general');
    applyGeneral(data);
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không tải được cài đặt dự án.');
  }
}

async function loadAllowlist() {
  try {
    const [usersRes, allowlistRes] = await Promise.all([
      window.axios.get('/api/project/assignable-users'),
      window.axios.get('/api/project/settings/creator-allowlist'),
    ]);
    allUsers.value = usersRes.data.users ?? [];
    const allowlistUsers = allowlistRes.data.users ?? [];
    allowlistUserIds.value = allowlistUsers.map((u) => u.id);
    const knownIds = new Set(allUsers.value.map((u) => u.id));
    allowlistUsers.forEach((u) => {
      if (!knownIds.has(u.id)) {
        allUsers.value.push(u);
        knownIds.add(u.id);
      }
    });
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không tải được danh sách nhân sự được phép tạo dự án.');
  }
}

async function saveAll() {
  saving.value = true;
  try {
    const [generalRes, allowlistRes] = await Promise.all([
      window.axios.put('/api/project/settings/general', {
        code_pattern: general.code_pattern,
        code_counter: Number(general.code_counter) || 0,
        task_code_pattern: general.task_code_pattern,
        task_code_counter: Number(general.task_code_counter) || 0,
        default_progress_method: general.default_progress_method,
        auto_start_on_begin_date: Boolean(general.auto_start_on_begin_date),
        shift_task_dates_with_project: Boolean(general.shift_task_dates_with_project),
        hide_cross_tasks_from_assignees: Boolean(general.hide_cross_tasks_from_assignees),
        hide_child_tasks_from_followers: Boolean(general.hide_child_tasks_from_followers),
        constrain_task_dates_to_project: Boolean(general.constrain_task_dates_to_project),
      }),
      window.axios.put('/api/project/settings/creator-allowlist', {
        user_ids: allowlistUserIds.value,
      }),
    ]);
    applyGeneral(generalRes.data);
    allowlistUserIds.value = (allowlistRes.data.users ?? []).map((u) => u.id);
    showClientToast('success', 'Đã lưu cài đặt dự án.');
  } catch (err) {
    const first = err?.response?.data?.errors
      ? Object.values(err.response.data.errors).flat()[0]
      : null;
    showClientToast('error', first || err?.response?.data?.message || 'Không lưu được cài đặt.');
  } finally {
    saving.value = false;
  }
}

function goBack() {
  router.push({ name: 'manager.project.index' });
}

onMounted(async () => {
  loading.value = true;
  try {
    const optionsRes = await window.axios.get('/api/project/options');
    progressMethodOptions.value = optionsRes.data.progress_method ?? [];
  } catch {
    progressMethodOptions.value = [];
  }
  await Promise.all([loadGeneral(), loadAllowlist()]);
  loading.value = false;
});
</script>

<template>
  <section class="proj-settings">
    <PageHeader title="Cài đặt dự án" icon="settings">
      <template #actions>
        <button type="button" class="proj-settings__header-btn" @click="goBack">
          <AppIcon name="chevronLeft" :size="16" />
          Về danh sách dự án
        </button>
      </template>
    </PageHeader>

    <div v-if="loading" class="proj-settings__loading">
      <span class="proj-settings__spinner" aria-hidden="true" />
      Đang tải cài đặt…
    </div>

    <template v-else>
      <div class="proj-settings__body hide-scrollbar">
        <!-- Nhóm: Mã số -->
        <section class="proj-settings__group proj-settings__group--primary">
          <header class="proj-settings__group-head">
            <span class="proj-settings__group-icon">
              <AppIcon name="hash" :size="18" />
            </span>
            <div class="proj-settings__group-copy">
              <h2 class="proj-settings__group-title">Mã số tự động</h2>
              <p class="proj-settings__group-desc">Quy tắc đặt mã cho dự án và công việc mới, sinh tự động theo mẫu.</p>
            </div>
          </header>

          <div class="proj-settings__group-body">
            <article class="proj-settings__item proj-settings__item--block">
              <div class="proj-settings__item-copy">
                <h3 class="proj-settings__item-title">Mã dự án</h3>
              </div>
              <div class="proj-settings__code">
                <div class="proj-settings__field proj-settings__field--pattern">
                  <label class="proj-settings__label" for="proj-settings-pattern">Mẫu mã</label>
                  <input
                    id="proj-settings-pattern"
                    v-model="general.code_pattern"
                    type="text"
                    class="proj-settings__input"
                    placeholder='DA_{date,"m/Y"}_{count}'
                    spellcheck="false"
                    autocomplete="off"
                    :disabled="saving"
                  />
                </div>
                <div class="proj-settings__field">
                  <label class="proj-settings__label" for="proj-settings-counter">Bộ đếm</label>
                  <input
                    id="proj-settings-counter"
                    v-model="general.code_counter"
                    type="number"
                    min="0"
                    step="1"
                    class="proj-settings__input"
                    placeholder="344"
                    :disabled="saving"
                  />
                </div>
                <div class="proj-settings__field">
                  <span class="proj-settings__label">Mã tiếp theo</span>
                  <span class="proj-settings__preview">{{ nextCodePreview }}</span>
                </div>
              </div>
            </article>

            <article class="proj-settings__item proj-settings__item--block">
              <div class="proj-settings__item-copy">
                <h3 class="proj-settings__item-title">Mã công việc</h3>
              </div>
              <div class="proj-settings__code">
                <div class="proj-settings__field proj-settings__field--pattern">
                  <label class="proj-settings__label" for="proj-settings-task-pattern">Mẫu mã</label>
                  <input
                    id="proj-settings-task-pattern"
                    v-model="general.task_code_pattern"
                    type="text"
                    class="proj-settings__input"
                    placeholder='CV_{date,"m/Y"}_{count}'
                    spellcheck="false"
                    autocomplete="off"
                    :disabled="saving"
                  />
                </div>
                <div class="proj-settings__field">
                  <label class="proj-settings__label" for="proj-settings-task-counter">Bộ đếm</label>
                  <input
                    id="proj-settings-task-counter"
                    v-model="general.task_code_counter"
                    type="number"
                    min="0"
                    step="1"
                    class="proj-settings__input"
                    placeholder="1"
                    :disabled="saving"
                  />
                </div>
                <div class="proj-settings__field">
                  <span class="proj-settings__label">Mã tiếp theo</span>
                  <span class="proj-settings__preview">{{ nextTaskCodePreview }}</span>
                </div>
              </div>
            </article>
          </div>
        </section>

        <!-- Nhóm: Tiến độ -->
        <section class="proj-settings__group proj-settings__group--secondary">
          <header class="proj-settings__group-head">
            <span class="proj-settings__group-icon">
              <AppIcon name="percent" :size="18" />
            </span>
            <div class="proj-settings__group-copy">
              <h2 class="proj-settings__group-title">Tiến độ dự án</h2>
              <p class="proj-settings__group-desc">Cách tính phần trăm hoàn thành mặc định khi tạo dự án mới.</p>
            </div>
          </header>

          <div class="proj-settings__group-body">
            <article class="proj-settings__item proj-settings__item--block">
              <div class="proj-settings__field">
                <label class="proj-settings__label" for="proj-settings-progress">Cách tính mặc định</label>
                <select
                  id="proj-settings-progress"
                  v-model="general.default_progress_method"
                  class="proj-settings__input"
                  :disabled="saving"
                >
                  <option v-for="opt in progressMethodOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </div>
              <p v-if="progressMethodOptions.find((o) => o.value === general.default_progress_method)?.description" class="proj-settings__item-example">
                {{ progressMethodOptions.find((o) => o.value === general.default_progress_method)?.description }}
              </p>
              <p class="proj-settings__item-example">
                Áp dụng mặc định cho dự án mới. Sau khi tạo dự án, có thể đổi riêng tại trang sửa dự án.
              </p>
            </article>
          </div>
        </section>

        <!-- Nhóm: Quy tắc hoạt động -->
        <section class="proj-settings__group proj-settings__group--info">
          <header class="proj-settings__group-head">
            <span class="proj-settings__group-icon">
              <AppIcon name="sliders" :size="18" />
            </span>
            <div class="proj-settings__group-copy">
              <h2 class="proj-settings__group-title">Quy tắc hoạt động</h2>
              <p class="proj-settings__group-desc">Bật/tắt các hành vi tự động áp dụng cho mọi dự án.</p>
            </div>
            <span class="proj-settings__group-count">{{ activeRuleCount }}/{{ RULES.length }} đang bật</span>
          </header>

          <div class="proj-settings__group-body">
            <article v-for="rule in RULES" :key="rule.key" class="proj-settings__rule">
              <span class="proj-settings__rule-icon" :class="{ 'proj-settings__rule-icon--on': general[rule.key] }">
                <AppIcon :name="rule.icon" :size="16" />
              </span>
              <div class="proj-settings__item-copy">
                <h3 :id="`proj-settings-rule-${rule.key}`" class="proj-settings__item-title">{{ rule.title }}</h3>
                <p v-if="rule.note" class="proj-settings__item-example">{{ rule.note }}</p>
                <p class="proj-settings__item-example">{{ rule.example }}</p>
              </div>
              <button
                type="button"
                class="proj-settings__switch"
                :class="{ 'proj-settings__switch--on': general[rule.key] }"
                role="switch"
                :aria-checked="general[rule.key] ? 'true' : 'false'"
                :aria-labelledby="`proj-settings-rule-${rule.key}`"
                :disabled="saving"
                @click="toggleRule(rule.key)"
              >
                <span class="proj-settings__switch-thumb" aria-hidden="true" />
              </button>
            </article>
          </div>
        </section>

        <!-- Nhóm: Nhân sự -->
        <section class="proj-settings__group proj-settings__group--warning">
          <header class="proj-settings__group-head">
            <span class="proj-settings__group-icon">
              <AppIcon name="users" :size="18" />
            </span>
            <div class="proj-settings__group-copy">
              <h2 class="proj-settings__group-title">Quyền tạo dự án</h2>
              <p class="proj-settings__group-desc">
                Nếu doanh nghiệp bạn không muốn có nhiều nhân viên có quyền tạo mới dự án, bạn có thể chọn nhân sự trong
                cài đặt này.
              </p>
            </div>
            <span class="proj-settings__group-count">
              {{ allowlistUserIds.length ? `${allowlistUserIds.length} người` : 'Mọi người' }}
            </span>
          </header>

          <div class="proj-settings__group-body">
            <article class="proj-settings__item proj-settings__item--block">
              <div class="proj-settings__allowlist">
                <ProjectMemberPicker
                  v-model="allowlistUserIds"
                  :users="allUsers"
                  :disabled="saving"
                  tone="gold"
                  placeholder="Gõ tên hoặc email để thêm nhân sự…"
                  :empty-text="''"
                />
                <p v-if="!allowlistUserIds.length" class="proj-settings__item-example">
                  Chưa chọn ai — hiện tại mọi nhân sự đều có thể tạo dự án mới.
                </p>
              </div>
            </article>
          </div>
        </section>
      </div>

      <div class="proj-settings__actions">
        <button type="button" class="proj-settings__btn proj-settings__btn--ghost" :disabled="saving" @click="goBack">
          Huỷ
        </button>
        <button type="button" class="proj-settings__btn" :disabled="saving" @click="saveAll">
          <AppIcon v-if="!saving" name="check" :size="16" />
          {{ saving ? 'Đang lưu…' : 'Lưu cài đặt' }}
        </button>
      </div>
    </template>
  </section>
</template>

<style scoped>
.proj-settings {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
}

.proj-settings__header-btn {
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

.proj-settings__header-btn:hover {
  background: var(--color-surface-muted);
}

.proj-settings__loading {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  color: var(--color-text-muted);
}

.proj-settings__spinner {
  width: 1rem;
  height: 1rem;
  border-radius: var(--radius-full);
  background: conic-gradient(var(--color-primary) 0deg 90deg, transparent 90deg 360deg);
  -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #000 calc(100% - 2px));
  mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #000 calc(100% - 2px));
  animation: proj-settings-spin 0.7s linear infinite;
}

@keyframes proj-settings-spin {
  to {
    transform: rotate(360deg);
  }
}

.proj-settings__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  margin-top: var(--space-4);
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

/* ---- Nhóm màu sắc ---- */

.proj-settings__group {
  display: flex;
  flex-direction: column;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  overflow: hidden;
}

.proj-settings__group-head {
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-5);
  background: var(--proj-group-surface);
  box-shadow: 0 1px 0 var(--color-border);
}

.proj-settings__group-icon {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-md);
  background: var(--proj-group-icon-bg);
  color: var(--proj-group-icon-fg);
}

.proj-settings__group-copy {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.proj-settings__group-title {
  margin: 0;
  color: var(--color-text);
  font-size: 1rem;
  font-weight: 700;
}

.proj-settings__group-desc {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.5;
}

.proj-settings__group-count {
  flex-shrink: 0;
  align-self: center;
  color: var(--proj-group-icon-fg);
  font-size: 0.8125rem;
  font-weight: 600;
  white-space: nowrap;
}

.proj-settings__group--primary {
  --proj-group-surface: var(--color-primary-surface);
  --proj-group-icon-bg: var(--color-primary-100);
  --proj-group-icon-fg: var(--color-primary-700);
}

.proj-settings__group--secondary {
  --proj-group-surface: var(--color-secondary-50);
  --proj-group-icon-bg: var(--color-secondary-100);
  --proj-group-icon-fg: var(--color-secondary-700);
}

.proj-settings__group--info {
  --proj-group-surface: var(--color-info-tint-bg);
  --proj-group-icon-bg: var(--color-info-tint-border);
  --proj-group-icon-fg: var(--color-info-tint-fg);
}

.proj-settings__group--warning {
  --proj-group-surface: var(--color-warning-tint-bg);
  --proj-group-icon-bg: var(--color-warning-tint-border);
  --proj-group-icon-fg: var(--color-warning-tint-fg);
}

.proj-settings__group-body {
  display: flex;
  flex-direction: column;
}

/* ---- Item chung (mã số, tiến độ, nhân sự) ---- */

.proj-settings__item {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--space-2);
  padding: var(--space-5);
  box-shadow: 0 1px 0 var(--color-border);
}

.proj-settings__group-body > .proj-settings__item:last-child {
  box-shadow: none;
}

.proj-settings__item-copy {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-width: 0;
}

.proj-settings__item-title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 700;
  line-height: 1.4;
  overflow-wrap: break-word;
}

.proj-settings__item-example {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-style: italic;
  line-height: 1.55;
  overflow-wrap: break-word;
}

.proj-settings__code {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(8.5rem, 0.7fr) minmax(10rem, 1fr);
  gap: var(--space-3);
  align-items: end;
}

.proj-settings__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
}

.proj-settings__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.01em;
  white-space: nowrap;
}

.proj-settings__input,
.proj-settings__preview {
  height: 2.5rem;
  min-width: 0;
  padding: 0 0.75rem;
  border-radius: var(--radius-md);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
}

.proj-settings__input {
  width: 100%;
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
}

.proj-settings__input:focus {
  outline: 2px solid var(--color-primary-200);
  outline-offset: 1px;
}

.proj-settings__input:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.proj-settings__preview {
  display: flex;
  align-items: center;
  overflow: hidden;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
  text-overflow: ellipsis;
  box-shadow: inset 0 0 0 1px var(--color-primary-200);
}

/* ---- Quy tắc (rule row) ---- */

.proj-settings__rule {
  display: grid;
  grid-template-columns: 2.25rem minmax(0, 1fr) 2.75rem;
  align-items: start;
  column-gap: var(--space-4);
  row-gap: var(--space-2);
  padding: var(--space-5);
  box-shadow: 0 1px 0 var(--color-border);
}

.proj-settings__group-body > .proj-settings__rule:last-child {
  box-shadow: none;
}

.proj-settings__rule-icon {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  transition: background-color 0.15s ease, color 0.15s ease;
}

.proj-settings__rule-icon--on {
  background: var(--color-info-tint-border);
  color: var(--color-info-tint-fg);
}

.proj-settings__switch {
  position: relative;
  flex-shrink: 0;
  align-self: start;
  margin-top: 0.125rem;
  width: 2.75rem;
  height: 1.5rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-text-muted) 35%, var(--color-surface-muted));
  cursor: pointer;
}

.proj-settings__switch--on {
  background: var(--color-primary);
}

.proj-settings__switch:hover:not(:disabled) {
  filter: brightness(0.96);
}

.proj-settings__switch:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.proj-settings__switch-thumb {
  position: absolute;
  top: 0.125rem;
  left: 0.125rem;
  width: 1.25rem;
  height: 1.25rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  transition: transform 0.15s ease;
}

.proj-settings__switch--on .proj-settings__switch-thumb {
  transform: translateX(1.25rem);
}

.proj-settings__allowlist {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.proj-settings__actions {
  flex-shrink: 0;
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  margin-top: var(--space-4);
  padding-top: var(--space-4);
  box-shadow: 0 -1px 0 var(--color-border);
}

.proj-settings__btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  height: 2.25rem;
  padding: 0 1rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.proj-settings__btn:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.proj-settings__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.proj-settings__btn--ghost {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.proj-settings__btn--ghost:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

@media (prefers-reduced-motion: reduce) {
  .proj-settings__switch-thumb {
    transition: none;
  }

  .proj-settings__spinner {
    animation: none;
  }
}

@media (max-width: 720px) {
  .proj-settings__code {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  }

  .proj-settings__field--pattern {
    grid-column: 1 / -1;
  }

  .proj-settings__group-head {
    flex-wrap: wrap;
  }

  .proj-settings__group-count {
    margin-left: calc(2.5rem + var(--space-3));
  }
}

@media (max-width: 480px) {
  .proj-settings {
    padding: var(--space-3);
  }

  .proj-settings__group-head,
  .proj-settings__item,
  .proj-settings__rule {
    padding: var(--space-4);
  }

  .proj-settings__rule {
    grid-template-columns: minmax(0, 1fr) 2.75rem;
  }

  .proj-settings__rule-icon {
    display: none;
  }

  .proj-settings__group-count {
    margin-left: 0;
  }
}
</style>
