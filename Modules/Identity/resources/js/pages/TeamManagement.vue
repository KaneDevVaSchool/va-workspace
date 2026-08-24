<script setup>
//
// manager/teams — CRUD nhóm (team) theo phòng ban. Chọn phòng ban trước
// (dropdown), sau đó xem/tạo/sửa/xoá nhóm thuộc phòng ban đó + gán trưởng
// nhóm (chỉ liệt kê nhân sự cùng phòng ban, đang active).
//
import { computed, onMounted, reactive, ref } from 'vue';
import { showClientToast } from '@/lib/clientToast';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import PageHeader from '@/components/PageHeader.vue';

const departments = ref([]);
const selectedDepartmentId = ref(null);
const teams = ref([]);
const departmentMembers = ref([]);
const isLoadingDepartments = ref(false);
const isLoadingTeams = ref(false);

const formOpen = ref(false);
const formMode = ref('create'); // 'create' | 'edit'
const formSaving = ref(false);
const form = reactive({ id: null, name: '', team_lead_id: '' });

const deleteTarget = ref(null);
const deleting = ref(false);

async function loadDepartments() {
  isLoadingDepartments.value = true;
  try {
    const { data } = await window.axios.get('/manager/departments');
    departments.value = data.departments ?? [];
    if (!selectedDepartmentId.value && departments.value.length > 0) {
      selectedDepartmentId.value = departments.value[0].id;
    }
  } catch {
    showClientToast('error', 'Không tải được danh sách phòng ban.');
  } finally {
    isLoadingDepartments.value = false;
  }
}

async function loadTeams() {
  if (!selectedDepartmentId.value) {
    teams.value = [];
    departmentMembers.value = [];
    return;
  }

  isLoadingTeams.value = true;
  try {
    const { data } = await window.axios.get('/manager/teams', {
      params: { department_id: selectedDepartmentId.value },
    });
    teams.value = data.teams ?? [];
    departmentMembers.value = data.department_members ?? [];
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không tải được danh sách nhóm.');
    teams.value = [];
    departmentMembers.value = [];
  } finally {
    isLoadingTeams.value = false;
  }
}

function onDepartmentChange() {
  loadTeams();
}

function openCreateForm() {
  formMode.value = 'create';
  form.id = null;
  form.name = '';
  form.team_lead_id = '';
  formOpen.value = true;
}

function openEditForm(team) {
  formMode.value = 'edit';
  form.id = team.id;
  form.name = team.name;
  form.team_lead_id = team.team_lead_id ?? '';
  formOpen.value = true;
}

function closeForm() {
  if (formSaving.value) return;
  formOpen.value = false;
}

async function submitForm() {
  if (!form.name.trim()) {
    showClientToast('error', 'Vui lòng nhập tên nhóm.');
    return;
  }

  formSaving.value = true;
  try {
    const payload = {
      name: form.name.trim(),
      team_lead_id: form.team_lead_id === '' ? null : Number(form.team_lead_id),
    };

    if (formMode.value === 'create') {
      await window.axios.post('/manager/teams', {
        ...payload,
        department_id: selectedDepartmentId.value,
      });
      showClientToast('success', 'Đã tạo nhóm mới.');
    } else {
      await window.axios.put(`/manager/teams/${form.id}`, payload);
      showClientToast('success', 'Đã cập nhật nhóm.');
    }

    formOpen.value = false;
    await loadTeams();
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không lưu được nhóm. Vui lòng thử lại.');
  } finally {
    formSaving.value = false;
  }
}

function requestDelete(team) {
  deleteTarget.value = team;
}

async function confirmDelete() {
  if (!deleteTarget.value) return;

  deleting.value = true;
  try {
    await window.axios.delete(`/manager/teams/${deleteTarget.value.id}`);
    showClientToast('success', 'Đã xoá nhóm.');
    deleteTarget.value = null;
    await loadTeams();
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không xoá được nhóm.');
  } finally {
    deleting.value = false;
  }
}

const selectedDepartmentLabel = computed(
  () => departments.value.find((d) => d.id === selectedDepartmentId.value)?.name ?? '',
);

const addTeamAction = computed(() => ({
  label: 'Thêm nhóm',
  icon: 'plus',
  disabled: !selectedDepartmentId.value,
  onClick: openCreateForm,
}));

onMounted(async () => {
  await loadDepartments();
  await loadTeams();
});
</script>

<template>
  <section class="team-mgmt">
    <PageHeader
      title="Quản lý nhóm"
      icon="users"
      description="Tạo, sửa, xoá nhóm trong phòng ban và gán trưởng nhóm. Team là dữ liệu do Workspace tự quản lý, không đồng bộ từ hệ thống nhân sự."
      :breadcrumbs="[
        { label: 'Trang chủ', to: { name: 'home' } },
        { label: 'Quản lý nhóm' },
      ]"
      :primary-action="addTeamAction"
    />

    <div class="team-mgmt__toolbar">
      <div class="team-mgmt__field">
        <label class="team-mgmt__label" for="team-department">Phòng ban</label>
        <select
          id="team-department"
          v-model="selectedDepartmentId"
          class="team-mgmt__select"
          :disabled="isLoadingDepartments"
          @change="onDepartmentChange"
        >
          <option v-for="dept in departments" :key="dept.id" :value="dept.id">
            {{ dept.name }}
          </option>
        </select>
      </div>
    </div>

    <div class="team-mgmt__table-wrap">
      <table class="team-mgmt__table">
        <thead>
          <tr>
            <th>Tên nhóm</th>
            <th>Trưởng nhóm</th>
            <th class="team-mgmt__col-actions">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="isLoadingTeams">
            <td colspan="3" class="team-mgmt__empty">Đang tải…</td>
          </tr>
          <tr v-else-if="teams.length === 0">
            <td colspan="3" class="team-mgmt__empty">
              Chưa có nhóm nào trong {{ selectedDepartmentLabel || 'phòng ban này' }}.
            </td>
          </tr>
          <tr v-for="team in teams" v-else :key="team.id">
            <td>{{ team.name }}</td>
            <td>
              <span v-if="team.team_lead">{{ team.team_lead.name }}</span>
              <span v-else class="team-mgmt__muted">Chưa gán</span>
            </td>
            <td class="team-mgmt__col-actions">
              <button type="button" class="team-mgmt__btn team-mgmt__btn--ghost" @click="openEditForm(team)">
                Sửa
              </button>
              <button
                type="button"
                class="team-mgmt__btn team-mgmt__btn--danger-ghost"
                @click="requestDelete(team)"
              >
                Xoá
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div v-if="formOpen" class="team-form" role="presentation" @mousedown.self="closeForm">
        <div class="team-form__panel" role="dialog" aria-modal="true">
          <h2 class="team-form__title">
            {{ formMode === 'create' ? 'Thêm nhóm mới' : 'Sửa nhóm' }}
          </h2>

          <div class="team-form__field">
            <label class="team-form__label" for="team-name">Tên nhóm</label>
            <input id="team-name" v-model="form.name" type="text" class="team-form__input" />
          </div>

          <div class="team-form__field">
            <label class="team-form__label" for="team-lead">Trưởng nhóm</label>
            <select id="team-lead" v-model="form.team_lead_id" class="team-form__input">
              <option value="">Chưa gán</option>
              <option v-for="member in departmentMembers" :key="member.id" :value="member.id">
                {{ member.name }}
              </option>
            </select>
          </div>

          <div class="team-form__actions">
            <button
              type="button"
              class="team-mgmt__btn team-mgmt__btn--ghost"
              :disabled="formSaving"
              @click="closeForm"
            >
              Huỷ
            </button>
            <button
              type="button"
              class="team-mgmt__btn team-mgmt__btn--primary"
              :disabled="formSaving"
              @click="submitForm"
            >
              {{ formSaving ? 'Đang lưu…' : 'Lưu' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <ConfirmDialog
      :open="deleteTarget !== null"
      title="Xác nhận xoá nhóm"
      :description="`Bạn có chắc muốn xoá nhóm &quot;${deleteTarget?.name ?? ''}&quot;? Thao tác này không thể hoàn tác.`"
      confirm-label="Xoá nhóm"
      danger
      :loading="deleting"
      @update:open="(v) => { if (!v) deleteTarget = null; }"
      @confirm="confirmDelete"
    />
  </section>
</template>

<style scoped>
.team-mgmt {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
}

.team-mgmt__toolbar {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: var(--space-3);
  margin-bottom: var(--space-4);
}

.team-mgmt__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 12rem;
}

.team-mgmt__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.team-mgmt__select,
.team-form__input {
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
}

.team-mgmt__btn {
  padding: 0.5rem 1rem;
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.team-mgmt__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.team-mgmt__btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.team-mgmt__btn--primary:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.team-mgmt__btn--ghost {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
}

.team-mgmt__btn--ghost:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.team-mgmt__btn--danger-ghost {
  border: 1px solid var(--color-danger-tint-border);
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}

.team-mgmt__btn--danger-ghost:hover:not(:disabled) {
  background: var(--color-primary-100);
}

.team-mgmt__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.team-mgmt__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.team-mgmt__table thead th {
  position: sticky;
  top: 0;
  z-index: 1;
  padding: var(--space-3) var(--space-4);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-weight: 600;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  text-align: left;
  box-shadow: 0 1px 0 var(--color-border);
}

.team-mgmt__table tbody td {
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  box-shadow: 0 1px 0 var(--color-border);
}

.team-mgmt__col-actions {
  display: flex;
  gap: var(--space-2);
  white-space: nowrap;
}

.team-mgmt__empty {
  padding: var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
}

.team-mgmt__muted {
  color: var(--color-text-muted);
}

.team-form {
  position: fixed;
  inset: 0;
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
  background: color-mix(in srgb, #000000 45%, transparent);
}

.team-form__panel {
  width: 100%;
  max-width: 26rem;
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
  padding: var(--space-5);
}

.team-form__title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 700;
}

.team-form__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.team-form__label {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-weight: 600;
}

.team-form__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

@media (max-width: 768px) {
  .team-mgmt {
    padding: var(--space-4);
  }

  .team-mgmt__toolbar {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
