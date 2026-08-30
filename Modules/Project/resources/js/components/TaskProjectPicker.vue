<script setup>
import TaskSearchPicker from './TaskSearchPicker.vue';

defineProps({
  modelValue: { type: [String, Number], default: '' },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'update:item']);

async function loadOptions(q) {
  const { data } = await window.axios.get('/api/project/assignable-projects', {
    params: { q: q.trim() },
  });
  return data.projects ?? [];
}

async function loadById(id) {
  const { data } = await window.axios.get('/api/project/assignable-projects', {
    params: { id },
  });
  return (data.projects ?? [])[0] ?? null;
}

function itemLabel(project) {
  return project.name || '';
}

function itemCode(project) {
  return project.code || '';
}

function itemMeta(project) {
  return project.owner_department?.name || project.executing_department?.name || '';
}
</script>

<template>
  <TaskSearchPicker
    :model-value="modelValue"
    :disabled="disabled"
    search-label="Tìm dự án"
    placeholder="Tìm dự án"
    empty-text="Không tìm thấy dự án."
    remove-aria-label="Bỏ dự án"
    :load-options="loadOptions"
    :load-by-id="loadById"
    :item-label="itemLabel"
    :item-code="itemCode"
    :item-meta="itemMeta"
    @update:model-value="emit('update:modelValue', $event)"
    @update:item="emit('update:item', $event)"
  />
</template>
