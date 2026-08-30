<script setup>
import { watch } from 'vue';
import TaskSearchPicker from './TaskSearchPicker.vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  projectId: { type: [String, Number], default: '' },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'update:item']);

watch(
  () => props.projectId,
  (next, prev) => {
    if (String(next ?? '') === String(prev ?? '')) return;
    if (props.modelValue !== '' && props.modelValue != null) {
      emit('update:modelValue', '');
      emit('update:item', null);
    }
  },
);

function parentParams(extra = {}) {
  const params = { ...extra };
  if (props.projectId !== '' && props.projectId != null) {
    params.project_id = props.projectId;
  }
  return params;
}

async function loadOptions(q) {
  const { data } = await window.axios.get('/api/project/assignable-parent-tasks', {
    params: parentParams({ q: q.trim() }),
  });
  return data.tasks ?? [];
}

async function loadById(id) {
  const { data } = await window.axios.get('/api/project/assignable-parent-tasks', {
    params: parentParams({ id }),
  });
  return (data.tasks ?? [])[0] ?? null;
}

function itemLabel(task) {
  return task.title || '';
}

function itemCode(task) {
  return task.code || '';
}

function itemMeta(task) {
  if (task.project?.name) return task.project.name;
  return 'Công việc thường xuyên';
}
</script>

<template>
  <TaskSearchPicker
    :model-value="modelValue"
    :disabled="disabled"
    search-label="Tìm công việc cha"
    placeholder="Tìm công việc cha"
    empty-text="Không tìm thấy công việc cha."
    remove-aria-label="Bỏ công việc cha"
    :load-options="loadOptions"
    :load-by-id="loadById"
    :item-label="itemLabel"
    :item-code="itemCode"
    :item-meta="itemMeta"
    @update:model-value="emit('update:modelValue', $event)"
    @update:item="emit('update:item', $event)"
  />
</template>
