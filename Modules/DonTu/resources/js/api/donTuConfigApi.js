export async function fetchStatusSets() {
  const { data } = await window.axios.get('/api/don-tu/status-sets');
  return data.items ?? [];
}

export async function createStatusSet(payload) {
  const { data } = await window.axios.post('/api/don-tu/status-sets', payload);
  return data.item;
}

export async function fetchStatusSet(id) {
  const { data } = await window.axios.get(`/api/don-tu/status-sets/${id}`);
  return data.item;
}

export async function createStatus(setId, payload) {
  const { data } = await window.axios.post(`/api/don-tu/status-sets/${setId}/statuses`, payload);
  return data.item;
}

export async function fetchFormTemplates() {
  const { data } = await window.axios.get('/api/don-tu/form-templates');
  return data.items ?? [];
}

export async function fetchFormTemplate(id) {
  const { data } = await window.axios.get(`/api/don-tu/form-templates/${id}`);
  return data.item;
}

export async function createFormTemplate(payload) {
  const { data } = await window.axios.post('/api/don-tu/form-templates', payload);
  return data.item;
}

export async function updateFormTemplate(id, payload) {
  const { data } = await window.axios.patch(`/api/don-tu/form-templates/${id}`, payload);
  return data.item;
}

export async function duplicateFormTemplate(id) {
  const { data } = await window.axios.post(`/api/don-tu/form-templates/${id}/duplicate`);
  return data.item;
}

export async function archiveFormTemplate(id) {
  const { data } = await window.axios.post(`/api/don-tu/form-templates/${id}/archive`);
  return data.item;
}

export async function deleteFormTemplate(id) {
  await window.axios.delete(`/api/don-tu/form-templates/${id}`);
}

export async function fetchWorkflows() {
  const { data } = await window.axios.get('/api/don-tu/workflows');
  return data.items ?? [];
}

export async function fetchWorkflow(id) {
  const { data } = await window.axios.get(`/api/don-tu/workflows/${id}`);
  return data.item;
}

export async function createWorkflow(payload) {
  const { data } = await window.axios.post('/api/don-tu/workflows', payload);
  return data.item;
}

export async function updateWorkflow(id, payload) {
  const { data } = await window.axios.patch(`/api/don-tu/workflows/${id}`, payload);
  return data.item;
}

export async function deleteWorkflow(id) {
  await window.axios.delete(`/api/don-tu/workflows/${id}`);
}
