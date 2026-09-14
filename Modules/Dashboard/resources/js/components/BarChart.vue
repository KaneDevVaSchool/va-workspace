<script setup>
import ApexCharts from 'apexcharts';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * Bar chart dùng chung — hỗ trợ ngang/dọc, 1 hoặc nhiều series, click cột để
 * lọc (drill-down). Dùng cho: Department Performance, Overdue/Work Aging,
 * Workload theo người, Employee Progress, Project/Task Activity Timeline.
 */
const props = defineProps({
  categories: { type: Array, required: true }, // label trục
  series: { type: Array, required: true }, // [{ name, data: [...] }]
  horizontal: { type: Boolean, default: false },
  colors: { type: Array, default: () => [] },
  height: { type: Number, default: 260 },
  stacked: { type: Boolean, default: false },
  valueSuffix: { type: String, default: '' },
  clickableCategories: { type: Array, default: null }, // value thật cho mỗi category, dùng khi label hiển thị khác value lọc
  emptyText: { type: String, default: 'Chưa có dữ liệu' },
});

const emit = defineEmits(['select']);

const el = ref(null);
let chart = null;

const hasData = () => (props.series ?? []).some((s) => (s.data ?? []).some((v) => v > 0));

function buildOptions() {
  const textMuted = getComputedStyle(document.documentElement).getPropertyValue('--color-text-muted').trim();
  const categories = Array.isArray(props.categories) ? props.categories : [];

  return {
    chart: {
      type: 'bar',
      height: props.height,
      fontFamily: 'inherit',
      stacked: props.stacked,
      toolbar: { show: false },
      events: {
        dataPointSelection(_event, _ctx, config) {
          const values = props.clickableCategories ?? categories;
          const value = values[config.dataPointIndex];
          if (value !== undefined) emit('select', value);
        },
      },
    },
    plotOptions: {
      bar: {
        horizontal: props.horizontal,
        borderRadius: 4,
        columnWidth: '55%',
        barHeight: '65%',
      },
    },
    colors: props.colors.length ? props.colors : undefined,
    dataLabels: { enabled: false },
    xaxis: {
      type: 'category',
      categories,
      labels: { style: { colors: textMuted, fontSize: '12px' } },
    },
    yaxis: {
      labels: { style: { colors: textMuted, fontSize: '12px' } },
    },
    legend: { show: (props.series ?? []).length > 1, position: 'top', fontSize: '13px' },
    grid: { borderColor: getComputedStyle(document.documentElement).getPropertyValue('--color-border').trim() },
    tooltip: { y: { formatter: (v) => `${v}${props.valueSuffix}` } },
  };
}

function render() {
  if (!el.value) return;
  if (chart) {
    chart.destroy();
    chart = null;
  }
  if (!hasData()) return;

  chart = new ApexCharts(el.value, { ...buildOptions(), series: props.series ?? [] });
  chart.render();
}

onMounted(() => nextTick(render));
onBeforeUnmount(() => chart?.destroy());
watch(() => [props.series, props.categories, props.colors], () => nextTick(render), { deep: true });
</script>

<template>
  <div class="bar-chart">
    <div v-if="!hasData()" class="bar-chart__empty">{{ emptyText }}</div>
    <div v-else ref="el"></div>
  </div>
</template>

<style scoped>
.bar-chart {
  width: 100%;
}

.bar-chart__empty {
  display: flex;
  align-items: center;
  justify-content: center;
  height: v-bind('height + "px"');
  color: var(--color-text-muted);
  font-size: 0.875rem;
}
</style>
