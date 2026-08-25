<script setup>
import { onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';

const props = defineProps({
  groupId: { type: Number, required: true },
});

const emit = defineEmits(['approved']);

const requests = ref([]);
const loading = ref(false);

async function load() {
  loading.value = true;
  try {
    const { data } = await window.axios.get(`/api/social/groups/${props.groupId}/requests`, {
      params: { per_page: 20 },
    });
    requests.value = data.requests;
  } catch {
    showClientToast('error', 'Không thể tải yêu cầu tham gia.');
  } finally {
    loading.value = false;
  }
}

async function approve(request) {
  try {
    await window.axios.post(`/api/social/groups/${props.groupId}/requests/${request.id}/approve`);
    requests.value = requests.value.filter((r) => r.id !== request.id);
    showClientToast('success', `Đã duyệt yêu cầu của ${request.user?.name}.`);
    emit('approved');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không thể duyệt yêu cầu.');
  }
}

async function reject(request) {
  try {
    await window.axios.post(`/api/social/groups/${props.groupId}/requests/${request.id}/reject`);
    requests.value = requests.value.filter((r) => r.id !== request.id);
    showClientToast('success', `Đã từ chối yêu cầu của ${request.user?.name}.`);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không thể từ chối yêu cầu.');
  }
}

defineExpose({ load });
onMounted(load);
</script>

<template>
  <section v-if="loading || requests.length" class="group-requests-panel">
    <h3 class="group-requests-panel__title">
      <AppIcon name="userPlus" :size="16" />
      Yêu cầu tham gia
    </h3>

    <div v-if="loading" class="group-requests-panel__loading">Đang tải...</div>

    <ul v-else class="group-requests-panel__list">
      <li v-for="request in requests" :key="request.id" class="group-requests-panel__item">
        <div class="group-requests-panel__info">
          <p class="group-requests-panel__name">{{ request.user?.name }}</p>
          <p v-if="request.message" class="group-requests-panel__message">{{ request.message }}</p>
        </div>
        <div class="group-requests-panel__actions">
          <button
            type="button"
            class="group-requests-panel__btn group-requests-panel__btn--approve"
            @click="approve(request)"
          >
            Duyệt
          </button>
          <button
            type="button"
            class="group-requests-panel__btn"
            @click="reject(request)"
          >
            Từ chối
          </button>
        </div>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.group-requests-panel {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: var(--space-3);
  box-shadow: var(--shadow-sm);
}

.group-requests-panel__title {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-text);
}

.group-requests-panel__loading {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.group-requests-panel__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.group-requests-panel__item {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding-top: var(--space-2);
  box-shadow: 0 -1px 0 var(--color-border);
}

.group-requests-panel__item:first-child {
  padding-top: 0;
  box-shadow: none;
}

.group-requests-panel__name {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text);
}

.group-requests-panel__message {
  margin: 2px 0 0;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.group-requests-panel__actions {
  display: flex;
  gap: var(--space-2);
}

.group-requests-panel__btn {
  flex: 1;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.group-requests-panel__btn:hover {
  background: var(--color-surface);
}

.group-requests-panel__btn--approve {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.group-requests-panel__btn--approve:hover {
  filter: brightness(0.95);
}
</style>
