<script setup>
//
// Dropdown chọn scope: Toàn hệ thống / Phòng ban / Team. Team lọc theo
// Phòng ban đã chọn — chọn PB trước mới hiện danh sách Team thuộc phòng đó.
//
import { computed, ref, watch } from 'vue';
import { showClientToast } from '@/lib/clientToast';

const props = defineProps({
  modelValue: { type: Object, required: true }, // { type, id }
});
const emit = defineEmits(['update:modelValue']);

const scopeType = ref(props.modelValue.type);
const departmentId = ref(null); // dùng để lọc team, và làm scope_id khi type=department
const teamId = ref(null);

const departments = ref([]);
const teams = ref([]);
const loadingTeams = ref(false);

async function loadDepartments() {
  try {
    const { data } = await window.axios.get('/manager/departments');
    departments.value = data.departments ?? [];
  } catch {
    showClientToast('error', 'Không tải được danh sách phòng ban.');
  }
}

async function loadTeams() {
  if (!departmentId.value) {
    teams.value = [];
    return;
  }
  loadingTeams.value = true;
  try {
    const { data } = await window.axios.get('/manager/teams', {
      params: { department_id: departmentId.value },
    });
    teams.value = data.teams ?? [];
  } catch {
    teams.value = [];
  } finally {
    loadingTeams.value = false;
  }
}

function emitChange() {
  if (scopeType.value === 'global') {
    emit('update:modelValue', { type: 'global', id: null });
  } else if (scopeType.value === 'department') {
    emit('update:modelValue', { type: 'department', id: departmentId.value });
  } else if (scopeType.value === 'team') {
    emit('update:modelValue', { type: 'team', id: teamId.value });
  }
}

function onScopeTypeChange() {
  if (scopeType.value === 'team' && departmentId.value) {
    loadTeams();
  }
  emitChange();
}

function onDepartmentChange() {
  teamId.value = null;
  if (scopeType.value === 'team') {
    loadTeams();
  }
  emitChange();
}

function onTeamChange() {
  emitChange();
}

watch(teams, () => {
  // Nếu team đang chọn không còn trong danh sách (đổi phòng ban) → reset
  if (teamId.value && !teams.value.some((t) => t.id === teamId.value)) {
    teamId.value = null;
    emitChange();
  }
});

loadDepartments();

const showDepartmentSelect = computed(() => scopeType.value === 'department' || scopeType.value === 'team');
const showTeamSelect = computed(() => scopeType.value === 'team');
</script>

<template>
  <div class="scope-filter">
    <div class="scope-filter__field">
      <label class="scope-filter__label" for="scope-type">Phạm vi</label>
      <select id="scope-type" v-model="scopeType" class="scope-filter__select" @change="onScopeTypeChange">
        <option value="global">Toàn hệ thống</option>
        <option value="department">Phòng ban</option>
        <option value="team">Nhóm (team)</option>
      </select>
    </div>

    <div v-if="showDepartmentSelect" class="scope-filter__field">
      <label class="scope-filter__label" for="scope-department">Phòng ban</label>
      <select
        id="scope-department"
        v-model="departmentId"
        class="scope-filter__select"
        @change="onDepartmentChange"
      >
        <option :value="null" disabled>Chọn phòng ban</option>
        <option v-for="dept in departments" :key="dept.id" :value="dept.id">
          {{ dept.name }}
        </option>
      </select>
    </div>

    <div v-if="showTeamSelect" class="scope-filter__field">
      <label class="scope-filter__label" for="scope-team">Team</label>
      <select
        id="scope-team"
        v-model="teamId"
        class="scope-filter__select"
        :disabled="!departmentId || loadingTeams"
        @change="onTeamChange"
      >
        <option :value="null" disabled>{{ departmentId ? 'Chọn team' : 'Chọn phòng ban trước' }}</option>
        <option v-for="team in teams" :key="team.id" :value="team.id">
          {{ team.name }}
        </option>
      </select>
    </div>
  </div>
</template>

<style scoped>
.scope-filter {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
}

.scope-filter__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 10rem;
}

.scope-filter__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.scope-filter__select {
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
}

.scope-filter__select:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 480px) {
  .scope-filter {
    flex-direction: column;
  }
}
</style>
