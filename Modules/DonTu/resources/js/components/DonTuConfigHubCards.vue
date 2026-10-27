<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import { fetchFormTemplates, fetchStatusSets, fetchWorkflows } from '../api/donTuConfigApi.js';

const route = useRoute();

const loading = ref(true);
const stats = ref({
  sets: 0,
  forms: 0,
  formsActive: 0,
  workflows: 0,
  workflowsActive: 0,
});

const cards = computed(() => [
  {
    name: 'manager.don-tu.statuses',
    icon: 'layers',
    title: 'Trạng thái hồ sơ',
    stat: stats.value.sets,
    statLabel: 'bộ',
  },
  {
    name: 'manager.don-tu.forms',
    icon: 'clipboardCheck',
    title: 'Mẫu biểu',
    stat: stats.value.forms,
    statLabel: stats.value.formsActive ? `${stats.value.formsActive} đang dùng` : 'mẫu',
  },
  {
    name: 'manager.don-tu.workflows',
    icon: 'gitBranch',
    title: 'Quy trình',
    stat: stats.value.workflows,
    statLabel: stats.value.workflowsActive ? `${stats.value.workflowsActive} đang bật` : 'quy trình',
  },
]);

onMounted(async () => {
  try {
    const [sets, forms, workflows] = await Promise.all([
      fetchStatusSets(),
      fetchFormTemplates(),
      fetchWorkflows(),
    ]);
    stats.value = {
      sets: sets.length,
      forms: forms.length,
      formsActive: forms.filter((f) => f.status === 'active').length,
      workflows: workflows.length,
      workflowsActive: workflows.filter((w) => w.is_active).length,
    };
  } catch {
    /* parent page may toast */
  } finally {
    loading.value = false;
  }
});

function isActive(name) {
  if (route.name === name) {
    return true;
  }
  if (route.name === 'manager.don-tu.form' && name === 'manager.don-tu.forms') {
    return true;
  }
  if (route.name === 'manager.don-tu.workflow' && name === 'manager.don-tu.workflows') {
    return true;
  }
  return false;
}
</script>

<template>
  <nav class="dt-config-hub" aria-label="Lối tắt cấu hình đơn từ">
    <router-link
      v-for="card in cards"
      :key="card.name"
      :to="{ name: card.name }"
      class="dt-config-hub__card"
      :class="{ 'dt-config-hub__card--active': isActive(card.name) }"
      :aria-current="isActive(card.name) ? 'page' : undefined"
    >
      <span class="dt-config-hub__icon" aria-hidden="true">
        <AppIcon :name="card.icon" :size="20" :stroke-width="1.75" />
      </span>
      <span class="dt-config-hub__body">
        <span class="dt-config-hub__title">{{ card.title }}</span>
      </span>
      <span class="dt-config-hub__stat">
        <span class="dt-config-hub__stat-num">{{ loading ? '…' : card.stat }}</span>
        <span class="dt-config-hub__stat-label">{{ card.statLabel }}</span>
      </span>
    </router-link>
  </nav>
</template>

<style scoped>
@import '../pages/admin/donTuAdminShared.css';
</style>
