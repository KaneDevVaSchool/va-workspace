<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { showClientToast } from '@/lib/clientToast';
import { formatDateTime } from '@/lib/formatTime';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import {
  createWorkflow,
  deleteWorkflow,
  fetchStatusSets,
  fetchWorkflows,
  updateWorkflow,
} from '../../api/donTuConfigApi.js';

const auth = useAuthStore();
const router = useRouter();

const canConfig = computed(
  () => auth.can('don_tu.manage_department') || auth.can('don_tu.*'),
);

const items = ref([]);
const statusSets = ref([]);
const loading = ref(false);
const query = ref('');

const createOpen = ref(false);
const createSaving = ref(false);
const createForm = ref({ name: '', status_set_id: '', description: '' });

const deleteTarget = ref(null);
const deleteOpen = ref(false);

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase();
  if (!q) return items.value;
  return items.value.filter(
    (it) =>
      (it.name || '').toLowerCase().includes(q) ||
      (it.code || '').toLowerCase().includes(q),
  );
});

async function load() {
  loading.value = true;
  try {
    [items.value, statusSets.value] = await Promise.all([fetchWorkflows(), fetchStatusSets()]);
  } catch (err) {
    showClientToast(err.response?.data?.message || 'Không tải quy trình.', 'error');
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  if (canConfig.value) load();
});

function openCreate() {
  const defaultSet = statusSets.value.find((s) => s.code === 'STATUS_SET_DEFAULT') || statusSets.value[0];
  createForm.value = {
    name: '',
    status_set_id: defaultSet?.id ?? '',
    description: '',
  };
  createOpen.value = true;
}

async function submitCreate() {
  if (!createForm.value.name.trim() || !createForm.value.status_set_id) {
    showClientToast('Nhập tên và chọn bộ trạng thái.', 'error');
    return;
  }
  createSaving.value = true;
  try {
    const item = await createWorkflow({
      ...createForm.value,
      status_set_id: Number(createForm.value.status_set_id),
    });
    createOpen.value = false;
    showClientToast('Đã tạo quy trình.', 'success');
    await load();
    router.push({ name: 'manager.don-tu.workflow', params: { id: item.id } });
  } catch (err) {
    showClientToast(err.response?.data?.message || 'Không tạo được quy trình.', 'error');
  } finally {
    createSaving.value = false;
  }
}

async function toggleActive(item, event) {
  event.stopPropagation();
  try {
    await updateWorkflow(item.id, { is_active: !item.is_active });
    showClientToast(item.is_active ? 'Đã tắt quy trình.' : 'Đã bật quy trình.', 'success');
    await load();
  } catch (err) {
    showClientToast(err.response?.data?.message || 'Không đổi trạng thái.', 'error');
  }
}

function askDelete(item, event) {
  event.stopPropagation();
  deleteTarget.value = item;
  deleteOpen.value = true;
}

async function confirmDelete() {
  const item = deleteTarget.value;
  if (!item) return;
  try {
    await deleteWorkflow(item.id);
    showClientToast('Đã xóa quy trình.', 'success');
    deleteOpen.value = false;
    await load();
  } catch (err) {
    showClientToast(err.response?.data?.message || 'Không xóa được.', 'error');
  }
}
</script>

<template>
  <div v-if="!canConfig" class="dt-config-deny">Bạn không có quyền quản lý quy trình.</div>

  <div v-else class="dt-admin-list">
    <div class="dt-admin-list__toolbar">
      <input v-model="query" type="search" class="dt-admin-list__search" placeholder="Tìm quy trình…" />
      <button type="button" class="dt-admin-list__primary" @click="openCreate">
        <AppIcon name="plus" :size="16" />
        Thêm quy trình
      </button>
    </div>

    <div class="dt-admin-table-wrap hide-scrollbar">
      <table class="dt-admin-table">
        <thead>
          <tr>
            <th>Quy trình</th>
            <th>Bộ trạng thái</th>
            <th>Bước</th>
            <th>Hoạt động</th>
            <th>Cập nhật</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="6">Đang tải…</td>
          </tr>
          <tr v-else-if="!filtered.length">
            <td colspan="6">Chưa có quy trình.</td>
          </tr>
          <tr
            v-for="item in filtered"
            :key="item.id"
            class="dt-admin-table__row"
            @click="router.push({ name: 'manager.don-tu.workflow', params: { id: item.id } })"
          >
            <td>
              <span class="dt-admin-table__title">{{ item.name }}</span>
              <code class="dt-admin-table__code">{{ item.code }}</code>
            </td>
            <td>{{ item.status_set_name || item.status_set_code }}</td>
            <td>{{ item.step_count }}</td>
            <td>
              <button
                type="button"
                class="dt-toggle"
                :class="{ 'dt-toggle--on': item.is_active }"
                @click="toggleActive(item, $event)"
              >
                {{ item.is_active ? 'Đang bật' : 'Đang tắt' }}
              </button>
            </td>
            <td>{{ formatDateTime(item.updated_at) }}</td>
            <td @click.stop>
              <button
                v-if="!item.is_active"
                type="button"
                class="dt-row-menu__danger dt-link-btn"
                @click="askDelete(item, $event)"
              >
                Xóa
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div v-if="createOpen" class="dt-modal-backdrop" @click.self="createOpen = false">
        <div class="dt-modal" role="dialog">
          <header class="dt-modal__head">
            <h2>Thêm quy trình</h2>
            <button type="button" class="dt-modal__close" aria-label="Đóng" @click="createOpen = false">
              <AppIcon name="x" :size="18" />
            </button>
          </header>
          <div class="dt-modal__body dt-modal__grid">
            <label class="dt-field dt-field--span2">
              <span>Tên quy trình</span>
              <input v-model="createForm.name" type="text" />
            </label>
            <label class="dt-field dt-field--span2">
              <span>Bộ trạng thái</span>
              <select v-model="createForm.status_set_id">
                <option disabled value="">Chọn bộ…</option>
                <option v-for="set in statusSets" :key="set.id" :value="set.id">
                  {{ set.name }}
                </option>
              </select>
            </label>
            <label class="dt-field dt-field--span2">
              <span>Mô tả</span>
              <textarea v-model="createForm.description" rows="2" />
            </label>
          </div>
          <footer class="dt-modal__foot">
            <button type="button" class="dt-btn-ghost" @click="createOpen = false">Huỷ</button>
            <button type="button" class="dt-btn-primary" :disabled="createSaving" @click="submitCreate">
              Tạo và cấu hình
            </button>
          </footer>
        </div>
      </div>
    </Teleport>

    <ConfirmDialog
      v-model:open="deleteOpen"
      title="Xóa quy trình?"
      :description="deleteTarget ? `Xóa «${deleteTarget.name}»?` : ''"
      confirm-label="Xóa"
      danger
      @confirm="confirmDelete"
    />
  </div>
</template>

<style scoped>
@import './donTuAdminShared.css';

.dt-toggle {
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  border: none;
  font-size: 0.6875rem;
  font-weight: 700;
  cursor: pointer;
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.dt-toggle--on {
  background: color-mix(in srgb, var(--color-success) 16%, var(--color-surface));
  color: var(--color-success);
}

.dt-link-btn {
  border: none;
  background: transparent;
  cursor: pointer;
  font-size: 0.8125rem;
}
</style>
