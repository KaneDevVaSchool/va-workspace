<script setup>
import { onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';

const props = defineProps({
  groupId: { type: Number, required: true },
  canManage: { type: Boolean, default: false },
  myRole: { type: String, default: null },
});

const emit = defineEmits(['changed']);

const members = ref([]);
const loading = ref(false);

async function load() {
  loading.value = true;
  try {
    const { data } = await window.axios.get(`/api/social/groups/${props.groupId}/members`, {
      params: { per_page: 50 },
    });
    members.value = data.members;
  } catch {
    showClientToast('error', 'Không thể tải danh sách thành viên.');
  } finally {
    loading.value = false;
  }
}

function roleLabel(role) {
  if (role === 'owner') return 'Chủ nhóm';
  if (role === 'admin') return 'Quản trị viên';
  return 'Thành viên';
}

async function removeMember(member) {
  try {
    await window.axios.delete(`/api/social/groups/${props.groupId}/members/${member.user_id}`);
    members.value = members.value.filter((m) => m.user_id !== member.user_id);
    showClientToast('success', `Đã xoá ${member.name} khỏi nhóm.`);
    emit('changed');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không thể xoá thành viên.');
  }
}

async function toggleAdmin(member) {
  const nextRole = member.role === 'admin' ? 'member' : 'admin';
  try {
    await window.axios.put(`/api/social/groups/${props.groupId}/members/${member.user_id}/role`, { role: nextRole });
    member.role = nextRole;
    showClientToast('success', 'Đã cập nhật vai trò thành viên.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không thể đổi vai trò.');
  }
}

defineExpose({ load });
onMounted(load);
</script>

<template>
  <section class="group-members-panel">
    <h3 class="group-members-panel__title">
      <AppIcon name="users" :size="16" />
      Thành viên
    </h3>

    <div v-if="loading" class="group-members-panel__loading">Đang tải...</div>

    <ul v-else class="group-members-panel__list hide-scrollbar">
      <li v-for="member in members" :key="member.user_id" class="group-members-panel__item">
        <img v-if="member.avatar_url" :src="member.avatar_url" class="group-members-panel__avatar" alt="" />
        <div v-else class="group-members-panel__avatar group-members-panel__avatar--placeholder">
          {{ (member.name || '?').charAt(0).toUpperCase() }}
        </div>
        <div class="group-members-panel__info">
          <p class="group-members-panel__name">{{ member.name }}</p>
          <p class="group-members-panel__role">{{ roleLabel(member.role) }}</p>
        </div>
        <div v-if="canManage && member.role !== 'owner'" class="group-members-panel__actions">
          <button
            type="button"
            class="group-members-panel__action-btn"
            aria-label="Đổi vai trò quản trị viên"
            @click="toggleAdmin(member)"
          >
            <AppIcon :name="member.role === 'admin' ? 'unlock' : 'shield'" :size="14" />
          </button>
          <button
            type="button"
            class="group-members-panel__action-btn"
            aria-label="Xoá khỏi nhóm"
            @click="removeMember(member)"
          >
            <AppIcon name="close" :size="14" />
          </button>
        </div>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.group-members-panel {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: var(--space-3);
  box-shadow: var(--shadow-sm);
}

.group-members-panel__title {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-text);
}

.group-members-panel__loading {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.group-members-panel__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  max-height: 20rem;
  overflow-y: auto;
}

.group-members-panel__item {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.group-members-panel__avatar {
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
}

.group-members-panel__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 0.75rem;
  font-weight: 700;
}

.group-members-panel__info {
  min-width: 0;
  flex: 1;
}

.group-members-panel__name {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.group-members-panel__role {
  margin: 0;
  font-size: 0.6875rem;
  color: var(--color-text-muted);
}

.group-members-panel__actions {
  display: flex;
  gap: var(--space-1);
  flex-shrink: 0;
}

.group-members-panel__action-btn {
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

.group-members-panel__action-btn:hover {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}
</style>
