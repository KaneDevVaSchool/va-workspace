import { computed, inject, ref } from 'vue';
import { fetchFormTemplates, fetchStatusSets, fetchWorkflows } from '../api/donTuConfigApi.js';

export const DON_TU_CONFIG_SUMMARY_KEY = 'donTuConfigSummary';

/** @typedef {{ total: number; active: number }} EntityCounts */

export const DON_TU_CONFIG_MODULES = [
  {
    key: 'sets',
    routeName: 'manager.don-tu.statuses',
    icon: 'layers',
    label: 'Trạng thái hồ sơ',
    tone: 'c1',
  },
  {
    key: 'forms',
    routeName: 'manager.don-tu.forms',
    icon: 'clipboardCheck',
    label: 'Mẫu biểu',
    tone: 'c2',
  },
  {
    key: 'workflows',
    routeName: 'manager.don-tu.workflows',
    icon: 'gitBranch',
    label: 'Quy trình',
    tone: 'c3',
  },
];

/** Năm màu theme hiển thị trên palette KPI (không pha). */
export const DON_TU_CONFIG_PALETTE = ['c1', 'c2', 'c3', 'c4', 'c5'];

const viNumber = (n) => new Intl.NumberFormat('vi-VN').format(n);

export function isDonTuModuleReady(key, counts) {
  return (counts?.total ?? 0) > 0;
}

export function donTuModuleDisplayValue(key, counts) {
  const total = counts?.total ?? 0;
  return total > 0 ? viNumber(total) : '—';
}

export function donTuModuleSubline(key, counts) {
  const total = counts?.total ?? 0;
  const active = counts?.active ?? 0;
  if (total <= 0) return undefined;
  if (key === 'forms' && active > 0 && active !== total) {
    return `${viNumber(active)} đang dùng`;
  }
  if (key === 'workflows' && active > 0) {
    if (active === total) return `${viNumber(active)} đang bật`;
    return `${viNumber(active)} / ${viNumber(total)} đang bật`;
  }
  if (key === 'sets' && total > 0) {
    return total === 1 ? '1 nhóm' : `${viNumber(total)} nhóm`;
  }
  return undefined;
}

/**
 * Thống kê hub cấu hình — dùng chung overview và thanh KPI.
 */
export function useDonTuConfigSummary() {
  const loading = ref(true);
  /** @type {import('vue').Ref<Record<string, EntityCounts>>} */
  const summary = ref({
    sets: { total: 0, active: 0 },
    forms: { total: 0, active: 0 },
    workflows: { total: 0, active: 0 },
  });

  const modules = DON_TU_CONFIG_MODULES;

  const readyCount = computed(() =>
    modules.filter((m) => isDonTuModuleReady(m.key, summary.value[m.key])).length,
  );

  const totalCount = computed(() => modules.length);

  const progressPct = computed(() =>
    totalCount.value > 0 ? Math.round((readyCount.value / totalCount.value) * 100) : 0,
  );

  const nextModuleKey = computed(() => {
    const next = modules.find((m) => !isDonTuModuleReady(m.key, summary.value[m.key]));
    return next?.key ?? null;
  });

  const allReady = computed(
    () => totalCount.value > 0 && readyCount.value === totalCount.value,
  );

  async function loadSummary() {
    loading.value = true;
    try {
      const [sets, forms, workflows] = await Promise.all([
        fetchStatusSets(),
        fetchFormTemplates(),
        fetchWorkflows(),
      ]);
      summary.value = {
        sets: {
          total: sets.length,
          active: sets.filter((s) => (s.status_count ?? 0) > 0).length,
        },
        forms: {
          total: forms.length,
          active: forms.filter((f) => f.status === 'active').length,
        },
        workflows: {
          total: workflows.length,
          active: workflows.filter((w) => w.is_active).length,
        },
      };
    } catch {
      /* caller may toast */
    } finally {
      loading.value = false;
    }
  }

  return {
    loading,
    summary,
    modules,
    readyCount,
    totalCount,
    progressPct,
    nextModuleKey,
    allReady,
    loadSummary,
  };
}

/** Dùng state hub từ DonTuLayout (nếu có) hoặc tạo cục bộ. */
export function useDonTuConfigSummaryContext() {
  return inject(DON_TU_CONFIG_SUMMARY_KEY, null) ?? useDonTuConfigSummary();
}
