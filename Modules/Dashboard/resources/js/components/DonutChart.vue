<script setup>
import ApexCharts from 'apexcharts';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
  labels: { type: Array, required: true }, // [{ value, label, color }]
  series: { type: Array, required: true }, // [number, ...] cùng thứ tự labels
  height: { type: Number, default: 220 },
  selected: { type: String, default: null }, // value đang được chọn (drill-down)
  emptyText: { type: String, default: 'Chưa có dữ liệu' },
});

const emit = defineEmits(['select']);

const el = ref(null);
let chart = null;

const hasData = () => props.series.some((v) => v > 0);

function buildOptions() {
  return {
    chart: {
      type: 'donut',
      height: props.height,
      fontFamily: 'inherit',
      events: {
        dataPointSelection(_event, _ctx, config) {
          const item = props.labels[config.dataPointIndex];
          if (item) emit('select', item.value);
        },
      },
    },
    labels: props.labels.map((l) => l.label),
    colors: props.labels.map((l) => l.color),
    dataLabels: { enabled: false },
    legend: { position: 'bottom', fontSize: '13px' },
    stroke: { width: 2 },
    states: {
      active: { filter: { type: 'darken', value: 0.85 } },
    },
    plotOptions: {
      pie: {
        donut: {
          size: '65%',
          labels: {
            show: true,
            total: { show: true, label: 'Tổng', fontSize: '13px' },
          },
        },
      },
    },
    tooltip: { y: { formatter: (v) => `${v}` } },
  };
}

function render() {
  if (!el.value) return;
  if (chart) {
    chart.destroy();
    chart = null;
  }
  if (!hasData()) return;

  chart = new ApexCharts(el.value, { ...buildOptions(), series: props.series });
  chart.render();
}

onMounted(() => nextTick(render));
onBeforeUnmount(() => chart?.destroy());
watch(() => [props.series, props.labels, props.selected], () => nextTick(render), { deep: true });
</script>

<template>
  <div class="donut-chart">
    <div v-if="!hasData()" class="donut-chart__empty">{{ emptyText }}</div>
    <div v-else ref="el"></div>
  </div>
</template>

<style scoped>
.donut-chart {
  width: 100%;
}

.donut-chart__empty {
  display: flex;
  align-items: center;
  justify-content: center;
  height: v-bind('height + "px"');
  color: var(--color-text-muted);
  font-size: 0.875rem;
}
</style>
