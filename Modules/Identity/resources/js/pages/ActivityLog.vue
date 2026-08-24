<script setup>
import { onMounted, ref, watch } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import { formatDateTime } from '@/lib/formatTime';
import { showClientToast } from '@/lib/clientToast';
import { ACTIVITY_ACTIONS } from '../constants/activity.js';

const logs = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const query = ref('');
const action = ref('');
const loading = ref(false);

async function loadLogs(page = 1) {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/activity-logs', {
      params: {
        q: query.value.trim() || undefined,
        action: action.value || undefined,
        page,
      },
    });
    logs.value = data.logs ?? [];
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0 };
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không tải được nhật ký hoạt động.');
  } finally {
    loading.value = false;
  }
}

function goPage(page) {
  if (page < 1 || page > meta.value.last_page || page === meta.value.current_page) {
    return;
  }
  loadLogs(page);
}

watch(action, () => {
  loadLogs(1);
});

onMounted(() => {
  loadLogs(1);
});
</script>

<template>
  <section class="activity-page">
    <PageHeader
      title="Nhật ký hoạt động"
      icon="clock"
      description="Các thao tác gần đây trên hệ thống."
    />

    <div class="activity-page__toolbar">
      <div class="activity-page__field">
        <label class="activity-page__label" for="activity-q">Tìm kiếm</label>
        <input
          id="activity-q"
          v-model="query"
          type="search"
          class="activity-page__input"
          placeholder="Tên người, nội dung…"
          @keydown.enter="loadLogs(1)"
        />
      </div>
      <div class="activity-page__field">
        <label class="activity-page__label" for="activity-action">Loại thao tác</label>
        <select id="activity-action" v-model="action" class="activity-page__input">
          <option v-for="item in ACTIVITY_ACTIONS" :key="item.value || 'all'" :value="item.value">
            {{ item.label }}
          </option>
        </select>
      </div>
      <button type="button" class="activity-page__btn" @click="loadLogs(1)">Tìm</button>
    </div>

    <div class="activity-page__table-wrap">
      <table class="activity-page__table">
        <thead>
          <tr>
            <th>Thời gian</th>
            <th>Người thực hiện</th>
            <th>Việc đã làm</th>
            <th>Địa chỉ mạng</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="4" class="activity-page__empty">Đang tải…</td>
          </tr>
          <tr v-else-if="logs.length === 0">
            <td colspan="4" class="activity-page__empty">Chưa có hoạt động nào.</td>
          </tr>
          <tr v-for="log in logs" v-else :key="log.id">
            <td>{{ formatDateTime(log.created_at) }}</td>
            <td>
              <span>{{ log.actor_name || 'Hệ thống' }}</span>
              <span v-if="log.actor_email" class="activity-page__muted">{{ log.actor_email }}</span>
            </td>
            <td>{{ log.description }}</td>
            <td>{{ log.ip_address || '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta.last_page > 1" class="activity-page__pager">
      <button
        type="button"
        class="activity-page__btn activity-page__btn--ghost"
        :disabled="meta.current_page <= 1"
        @click="goPage(meta.current_page - 1)"
      >
        Trang trước
      </button>
      <span class="activity-page__page">
        Trang {{ meta.current_page }} / {{ meta.last_page }}
      </span>
      <button
        type="button"
        class="activity-page__btn activity-page__btn--ghost"
        :disabled="meta.current_page >= meta.last_page"
        @click="goPage(meta.current_page + 1)"
      >
        Trang sau
      </button>
    </div>
  </section>
</template>

<style scoped>
.activity-page {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
}

.activity-page__toolbar {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: var(--space-3);
  margin-bottom: var(--space-4);
}

.activity-page__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 12rem;
}

.activity-page__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.activity-page__input {
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
}

.activity-page__btn {
  height: 2.375rem;
  padding: 0 1rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.activity-page__btn:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.activity-page__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.activity-page__btn--ghost {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.activity-page__btn--ghost:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.activity-page__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.activity-page__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.activity-page__table thead th {
  position: sticky;
  top: 0;
  z-index: 1;
  padding: var(--space-3) var(--space-4);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-weight: 600;
  font-size: 0.75rem;
  letter-spacing: 0.02em;
  text-align: left;
  box-shadow: 0 1px 0 var(--color-border);
}

.activity-page__table tbody td {
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  vertical-align: top;
  box-shadow: 0 1px 0 var(--color-border);
}

.activity-page__table tbody td span {
  display: block;
}

.activity-page__empty {
  padding: var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
}

.activity-page__muted {
  margin-top: 0.125rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.activity-page__pager {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--space-3);
  padding-top: var(--space-3);
}

.activity-page__page {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

@media (max-width: 768px) {
  .activity-page {
    padding: var(--space-4);
  }

  .activity-page__toolbar {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
