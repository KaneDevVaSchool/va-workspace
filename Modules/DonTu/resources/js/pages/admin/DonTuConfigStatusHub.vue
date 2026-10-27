<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import DonTuStatusContentHeader from '../../components/DonTuStatusContentHeader.vue';
import { useDonTuConfigHub } from '../../composables/useDonTuConfigHub.js';
import { fetchStatusSets } from '../../api/donTuConfigApi.js';

const auth = useAuthStore();
const configHub = useDonTuConfigHub();

const canConfig = computed(
  () =>
    auth.can('don_tu.manage_department') ||
    auth.can('don_tu.manage_system') ||
    auth.can('don_tu.*'),
);

const sets = ref([]);
const loading = ref(false);

const totalStatusCount = computed(() =>
  sets.value.reduce((sum, set) => sum + (set.status_count ?? 0), 0),
);

async function loadSets() {
  loading.value = true;
  try {
    sets.value = await fetchStatusSets();
  } catch {
    sets.value = [];
  } finally {
    loading.value = false;
  }
}

onMounted(async () => {
  configHub?.registerReload(loadSets);
  configHub?.setPrimaryAction({
    label: 'Quản lý trạng thái',
    icon: 'layers',
    to: { name: 'manager.don-tu.statuses' },
  });
  if (canConfig.value) {
    await loadSets();
  }
});

onBeforeUnmount(() => {
  configHub?.unregisterReload();
  configHub?.clearPrimaryAction();
});
</script>

<template>
  <div v-if="!canConfig" class="dt-config-deny">
    <AppIcon name="lock" :size="28" :stroke-width="1.75" />
    <p>Bạn cần quyền cấu hình đơn từ của phòng để xem trang này.</p>
  </div>

  <div v-else class="dt-config-status-hub">
    <DonTuStatusContentHeader
      :set-count="sets.length"
      :status-count="totalStatusCount"
      :loading="loading"
    />

    <router-link :to="{ name: 'manager.don-tu.statuses' }" class="dt-config-status-hub__entry">
      <span class="dt-config-status-hub__entry-icon" aria-hidden="true">
        <AppIcon name="layers" :size="22" :stroke-width="1.75" />
      </span>
      <span class="dt-config-status-hub__entry-copy">
        <span class="dt-config-status-hub__entry-title">Thiết lập trạng thái</span>
        <span class="dt-config-status-hub__entry-lead">
          Tạo bộ trạng thái, khai báo bước trên hồ sơ và quy tắc chuyển trạng thái.
        </span>
      </span>
      <span class="dt-config-status-hub__entry-cta" aria-hidden="true">
        Mở
        <AppIcon name="chevronRight" :size="16" :stroke-width="2" />
      </span>
    </router-link>
  </div>
</template>

<style scoped>
@import './donTuAdminShared.css';

.dt-config-status-hub {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  max-width: 40rem;
}

.dt-config-status-hub__entry {
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  color: inherit;
  text-decoration: none;
  transition: background 0.15s ease, box-shadow 0.15s ease;
}

.dt-config-status-hub__entry:hover {
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 24%, var(--color-border)), var(--shadow-sm);
}

.dt-config-status-hub__entry-icon {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-primary) 12%, var(--color-surface));
  color: var(--color-primary);
}

.dt-config-status-hub__entry-copy {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.dt-config-status-hub__entry-title {
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
}

.dt-config-status-hub__entry-lead {
  font-size: 0.8125rem;
  line-height: 1.45;
  color: var(--color-text-muted);
}

.dt-config-status-hub__entry-cta {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  margin-top: 0.125rem;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-primary);
}
</style>
