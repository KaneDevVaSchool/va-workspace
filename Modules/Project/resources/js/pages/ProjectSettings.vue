<script setup>
//
// manager/project/settings — Cài đặt dự án dùng chung toàn hệ thống:
// mẫu mã + bộ đếm, các quy tắc hoạt động, danh sách nhân sự được phép tạo
// dự án. Trang riêng (không phải modal) — chỉ admin/super_admin
// (project.manage_settings), route Vue guard bằng requiresPermission.
//
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import ProjectMemberPicker from '../components/ProjectMemberPicker.vue';

const DEFAULT_PATTERN = 'DA_{date,"m/Y"}_{count}';
const DEFAULT_COUNTER = 344;

const RULES = [
  {
    key: 'auto_start_on_begin_date',
    title: 'Khi đến ngày bắt đầu thì trạng thái dự án tự động chuyển sang Đang thực hiện',
    example:
      'Khi bật cài đặt này thì, ví dụ: Dự án A có ngày bắt đầu là 15/03/2020, khi đến ngày 15/03/2020, dự án A sẽ tự động chuyển từ trạng thái Đang chờ sang Đang thực hiện',
  },
  {
    key: 'shift_task_dates_with_project',
    title: 'Khi thời gian thực hiện dự án thay đổi thì thời gian công việc thay đổi theo',
    example:
      'Khi bật cài đặt này thì, ví dụ: Thời gian thực hiện dự án là 10/03/2020 - 20/03/2020, công việc X thuộc dự án A có thời gian thực hiện là 11/03/2020 - 15/03/2020. Khi dự án A được tịnh tiến 3 ngày, tức thời gian thực hiện là 13/03/2020 – 20/03/2020, thì thời gian công việc X cũng tịnh tiến thêm 3 ngày, tức là 14/03/2020 - 15/03/2020',
  },
  {
    key: 'hide_cross_tasks_from_assignees',
    title: 'Không cho phép người thực hiện xem được công việc chéo thuộc cùng một dự án',
    example:
      'Khi bật cài đặt này thì, ví dụ: Dự án A gồm 2 công việc B và C, người thực hiện công việc B sẽ không được xem công việc C nếu người đó không phải là người thực hiện công việc C',
  },
  {
    key: 'hide_child_tasks_from_followers',
    title: 'Không cho phép người theo dõi xem được các công việc con thuộc dự án',
    example:
      'Khi bật cài đặt này thì, ví dụ: Dự án A gồm 2 công việc B và C, người theo dõi dự án A sẽ không được xem công việc B và C nếu người đó không phải là người theo dõi công việc B và C',
  },
  {
    key: 'constrain_task_dates_to_project',
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
  auto_start_on_begin_date: false,
  shift_task_dates_with_project: false,
  hide_cross_tasks_from_assignees: false,
  hide_child_tasks_from_followers: false,
  constrain_task_dates_to_project: false,
});

const allUsers = ref([]);
const allowlistUserIds = ref([]);

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

function applyGeneral(data) {
  general.code_pattern = data.code_pattern ?? DEFAULT_PATTERN;
  general.code_counter = data.code_counter ?? DEFAULT_COUNTER;
  general.auto_start_on_begin_date = Boolean(data.auto_start_on_begin_date);
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

    <div v-if="loading" class="proj-settings__loading">Đang tải cài đặt…</div>

    <template v-else>
      <div class="proj-settings__body hide-scrollbar">
        <section class="proj-settings__card">
          <article class="proj-settings__item proj-settings__item--block">
            <div class="proj-settings__item-copy">
              <h2 class="proj-settings__item-title">Mã dự án</h2>
            </div>
            <div class="proj-settings__code">
              <div class="proj-settings__field proj-settings__field--pattern">
                <label class="proj-settings__label" for="proj-settings-pattern">Mẫu mã</label>
                <input
                  id="proj-settings-pattern"
                  v-model="general.code_pattern"
                  type="text"
                  class="proj-settings__input"
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
                  :disabled="saving"
                />
              </div>
              <div class="proj-settings__field">
                <span class="proj-settings__label">Mã tiếp theo</span>
                <span class="proj-settings__preview">{{ nextCodePreview }}</span>
              </div>
            </div>
          </article>

          <article v-for="rule in RULES" :key="rule.key" class="proj-settings__item">
            <div class="proj-settings__item-copy">
              <h2 :id="`proj-settings-rule-${rule.key}`" class="proj-settings__item-title">{{ rule.title }}</h2>
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

          <article class="proj-settings__item proj-settings__item--block">
            <div class="proj-settings__item-copy">
              <h2 class="proj-settings__item-title">Danh sách nhân sự có quyền tạo mới dự án</h2>
              <p class="proj-settings__item-example">
                Nếu doanh nghiệp bạn không muốn có nhiều nhân viên có quyền tạo mới dự án, bạn có thể chọn nhân sự trong
                cài đặt này
              </p>
            </div>
            <div class="proj-settings__allowlist">
              <ProjectMemberPicker
                v-model="allowlistUserIds"
                :users="allUsers"
                :disabled="saving"
                placeholder="Gõ tên hoặc email để thêm nhân sự…"
                :empty-text="''"
              />
            </div>
          </article>
        </section>
      </div>

      <div class="proj-settings__actions">
        <button type="button" class="proj-settings__btn proj-settings__btn--ghost" :disabled="saving" @click="goBack">
          Huỷ
        </button>
        <button type="button" class="proj-settings__btn" :disabled="saving" @click="saveAll">
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
  color: var(--color-text-muted);
}

.proj-settings__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  margin-top: var(--space-4);
}

.proj-settings__card {
  display: flex;
  flex-direction: column;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.proj-settings__item {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 2.75rem;
  align-items: start;
  column-gap: var(--space-5);
  row-gap: var(--space-3);
  padding: var(--space-5);
  box-shadow: 0 1px 0 var(--color-border);
}

.proj-settings__item--block {
  grid-template-columns: minmax(0, 1fr);
}

.proj-settings__item--block .proj-settings__code,
.proj-settings__item--block .proj-settings__allowlist {
  grid-column: 1;
}

.proj-settings__item:last-child {
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
  grid-column: 1 / -1;
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
  grid-column: 1 / -1;
  min-width: 0;
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
  color: var(--color-on-primary, #fff);
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
}

@media (max-width: 720px) {
  .proj-settings__code {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  }

  .proj-settings__field--pattern {
    grid-column: 1 / -1;
  }
}

@media (max-width: 480px) {
  .proj-settings {
    padding: var(--space-3);
  }

  .proj-settings__item {
    padding: var(--space-4);
    column-gap: var(--space-3);
  }
}
</style>
