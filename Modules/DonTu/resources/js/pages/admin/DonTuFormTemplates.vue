<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { showClientToast } from '@/lib/clientToast';
import { formatDateTime } from '@/lib/formatTime';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import {
  archiveFormTemplate,
  createFormTemplate,
  deleteFormTemplate,
  duplicateFormTemplate,
  fetchFormTemplates,
} from '../../api/donTuConfigApi.js';
import DonTuConfigHubCards from '../../components/DonTuConfigHubCards.vue';

const auth = useAuthStore();
const router = useRouter();

const canConfig = computed(
  () => auth.can('don_tu.manage_department') || auth.can('don_tu.*'),
);

const items = ref([]);
const loading = ref(false);
const query = ref('');

const createOpen = ref(false);
const createSaving = ref(false);
const createForm = ref({ name: '', scope: 'internal', description: '' });

const actionMenuId = ref(null);
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

const STATUS_LABEL = { draft: 'Nháp', active: 'Đang dùng', archived: 'Lưu trữ' };
const STATUS_TONE = { draft: 'muted', active: 'success', archived: 'muted' };
const SCOPE_LABEL = { internal: 'Cán bộ', external: 'Phụ huynh', both: 'Cán bộ và phụ huynh' };

async function load() {
  loading.value = true;
  try {
    items.value = await fetchFormTemplates();
  } catch (err) {
    showClientToast(err.response?.data?.message || 'Không tải được danh sách mẫu.', 'error');
  } finally {
    loading.value = false;
  }
}

function handleDocumentClick(event) {
  if (!actionMenuId.value) return;
  const menus = document.querySelectorAll('.dt-row-menu');
  for (const menu of menus) {
    if (menu.contains(event.target)) return;
  }
  if (event.target.closest?.('.dt-row-menu-btn')) return;
  actionMenuId.value = null;
}

function handleDocumentKeydown(event) {
  if (event.key === 'Escape' && actionMenuId.value) {
    actionMenuId.value = null;
  }
}

onMounted(() => {
  document.addEventListener('click', handleDocumentClick);
  document.addEventListener('keydown', handleDocumentKeydown);
  if (canConfig.value) load();
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleDocumentClick);
  document.removeEventListener('keydown', handleDocumentKeydown);
});

function openCreate() {
  createForm.value = { name: '', scope: 'internal', description: '' };
  createOpen.value = true;
}

async function submitCreate() {
  if (!createForm.value.name.trim()) {
    showClientToast('Nhập tên mẫu biểu.', 'error');
    return;
  }
  createSaving.value = true;
  try {
    const item = await createFormTemplate(createForm.value);
    createOpen.value = false;
    showClientToast('Đã tạo mẫu biểu.', 'success');
    await load();
    router.push({ name: 'manager.don-tu.form', params: { id: item.id } });
  } catch (err) {
    showClientToast(err.response?.data?.message || 'Không tạo được mẫu.', 'error');
  } finally {
    createSaving.value = false;
  }
}

function toggleActionMenu(id) {
  actionMenuId.value = actionMenuId.value === id ? null : id;
}

async function onDuplicate(item) {
  actionMenuId.value = null;
  try {
    const copy = await duplicateFormTemplate(item.id);
    showClientToast('Đã nhân bản mẫu.', 'success');
    await load();
    router.push({ name: 'manager.don-tu.form', params: { id: copy.id } });
  } catch (err) {
    showClientToast(err.response?.data?.message || 'Không nhân bản được.', 'error');
  }
}

async function onArchive(item) {
  actionMenuId.value = null;
  try {
    await archiveFormTemplate(item.id);
    showClientToast('Đã chuyển mẫu sang lưu trữ.', 'success');
    await load();
  } catch (err) {
    showClientToast(err.response?.data?.message || 'Không ẩn được mẫu.', 'error');
  }
}

function askDelete(item) {
  actionMenuId.value = null;
  deleteTarget.value = item;
  deleteOpen.value = true;
}

async function confirmDelete() {
  const item = deleteTarget.value;
  if (!item) return;
  try {
    await deleteFormTemplate(item.id);
    showClientToast('Đã xóa mẫu.', 'success');
    deleteOpen.value = false;
    await load();
  } catch (err) {
    showClientToast(err.response?.data?.message || 'Không xóa được mẫu.', 'error');
  }
}
</script>

<template>
  <div v-if="!canConfig" class="dt-config-deny">Bạn không có quyền quản lý mẫu biểu.</div>

  <div v-else class="dt-admin-list">
    <DonTuConfigHubCards />

    <div class="dt-admin-list__toolbar">
      <input v-model="query" type="search" class="dt-admin-list__search" placeholder="Tìm theo tên mẫu" aria-label="Tìm mẫu biểu" />
      <button type="button" class="dt-admin-list__primary" @click="openCreate">
        <AppIcon name="plus" :size="16" :stroke-width="2" />
        Thêm mẫu biểu
      </button>
    </div>

    <div class="dt-admin-table-wrap hide-scrollbar">
      <table class="dt-admin-table">
        <thead>
          <tr>
            <th>Mẫu biểu</th>
            <th>Ai được dùng</th>
            <th>Trạng thái</th>
            <th>Trường</th>
            <th>Cập nhật</th>
            <th class="dt-admin-table__actions-col" />
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="6">Đang tải…</td>
          </tr>
          <tr v-else-if="!filtered.length">
            <td colspan="6">Chưa có mẫu biểu. Bấm «Thêm mẫu biểu» để bắt đầu.</td>
          </tr>
          <tr
            v-for="item in filtered"
            :key="item.id"
            class="dt-admin-table__row"
            @click="router.push({ name: 'manager.don-tu.form', params: { id: item.id } })"
          >
            <td>
              <span class="dt-admin-table__title">{{ item.name }}</span>
            </td>
            <td>{{ SCOPE_LABEL[item.scope] || item.scope }}</td>
            <td>
              <span
                class="dt-pill"
                :class="`dt-pill--${STATUS_TONE[item.status] || 'muted'}`"
              >
                {{ STATUS_LABEL[item.status] || item.status }}
              </span>
            </td>
            <td>{{ item.field_count }}</td>
            <td>{{ formatDateTime(item.updated_at) }}</td>
            <td class="dt-admin-table__actions-col" @click.stop>
              <button
                type="button"
                class="dt-row-menu-btn"
                aria-label="Thao tác"
                @click.stop="toggleActionMenu(item.id)"
              >
                <AppIcon name="moreVertical" :size="18" :stroke-width="2" />
              </button>
              <div v-if="actionMenuId === item.id" class="dt-row-menu" role="menu">
                <button type="button" role="menuitem" @click="onDuplicate(item)">Nhân bản</button>
                <button
                  v-if="item.status !== 'archived'"
                  type="button"
                  role="menuitem"
                  @click="onArchive(item)"
                >
                  Ẩn / lưu trữ
                </button>
                <button
                  v-if="item.status === 'draft'"
                  type="button"
                  role="menuitem"
                  class="dt-row-menu__danger"
                  @click="askDelete(item)"
                >
                  Xóa
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div v-if="createOpen" class="dt-modal-backdrop" @click.self="createOpen = false">
        <div class="dt-modal" role="dialog" aria-labelledby="dt-form-create-title">
          <header class="dt-modal__head">
            <h2 id="dt-form-create-title">Thêm mẫu biểu</h2>
            <button type="button" class="dt-modal__close" aria-label="Đóng" @click="createOpen = false">
              <AppIcon name="x" :size="18" />
            </button>
          </header>
          <div class="dt-modal__body dt-modal__grid">
            <label class="dt-field">
              <span>Tên mẫu<span class="dt-req" aria-hidden="true">*</span></span>
              <input v-model="createForm.name" type="text" required autocomplete="off" placeholder="Ví dụ: Đơn xin nghỉ phép" />
            </label>
            <label class="dt-field">
              <span>Ai được dùng<span class="dt-req" aria-hidden="true">*</span></span>
              <select v-model="createForm.scope" required>
                <option value="internal">Cán bộ trong trường</option>
                <option value="external">Phụ huynh</option>
                <option value="both">Cả cán bộ và phụ huynh</option>
              </select>
            </label>
            <label class="dt-field dt-field--span2">
              <span>Ghi chú</span>
              <textarea v-model="createForm.description" rows="2" placeholder="Ghi ngắn mẫu này dùng khi nào" />
            </label>
          </div>
          <footer class="dt-modal__foot">
            <button type="button" class="dt-btn-ghost" @click="createOpen = false">Huỷ</button>
            <button type="button" class="dt-btn-primary" :disabled="createSaving" @click="submitCreate">
              {{ createSaving ? 'Đang lưu…' : 'Tạo và soạn mẫu' }}
            </button>
          </footer>
        </div>
      </div>
    </Teleport>

    <ConfirmDialog
      v-model:open="deleteOpen"
      title="Xóa mẫu nháp?"
      :description="deleteTarget ? `Xóa vĩnh viễn «${deleteTarget.name}»?` : ''"
      confirm-label="Xóa"
      danger
      @confirm="confirmDelete"
    />
  </div>
</template>

<style scoped>
@import './donTuAdminShared.css';
</style>
