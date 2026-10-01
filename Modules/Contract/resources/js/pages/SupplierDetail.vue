<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const supplier = ref(null);
const loading = ref(false);
const statusBusy = ref(false);
const activeTab = ref(route.query.tab || 'overview');
const statusDialog = reactive({ open: false, status: '', reason: '' });
const drawer = reactive({ open: false, document: null, mode: 'upload' });
const uploadForm = reactive({
  document_type_id: '',
  custom_name: '',
  document_number: '',
  issuer: '',
  issued_at: '',
  expires_at: '',
  no_expiry: true,
  uploader_note: '',
  file: null,
});
const reviewForm = reactive({ status: 'valid', review_note: '' });

const supplierId = computed(() => Number(route.params.id || 0));
const canManage = computed(() => auth.can('contract.manage_department') || auth.can('contract.*'));
const tabs = computed(() => [
  { key: 'overview', label: 'Tổng quan' },
  { key: 'documents', label: `Hồ sơ${supplier.value?.document_summary?.expiring_soon || supplier.value?.document_summary?.missing_required ? ' cảnh báo' : ''}` },
  { key: 'contacts', label: `Người liên hệ ${supplier.value?.contacts?.length || 0}` },
  { key: 'banks', label: `Tài khoản ngân hàng ${supplier.value?.bank_accounts?.length || 0}` },
  { key: 'contracts', label: `Hợp đồng ${supplier.value?.contracts?.length || 0}` },
  { key: 'history', label: 'Lịch sử' },
]);

function setTab(tab) {
  activeTab.value = tab;
  router.replace({ query: { ...route.query, tab } });
}

async function loadSupplier() {
  loading.value = true;
  try {
    const { data } = await window.axios.get(`/api/contract/suppliers/${supplierId.value}`);
    supplier.value = data.supplier;
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được chi tiết nhà cung cấp.');
  } finally {
    loading.value = false;
  }
}

function openStatusDialog(status) {
  Object.assign(statusDialog, { open: true, status, reason: '' });
}

async function changeStatus() {
  if (!statusDialog.reason.trim()) {
    showClientToast('error', 'Vui lòng nhập lý do đổi trạng thái.');
    return;
  }
  statusBusy.value = true;
  try {
    await window.axios.post(`/api/contract/suppliers/${supplierId.value}/status`, { status: statusDialog.status, reason: statusDialog.reason.trim() });
    showClientToast('success', 'Đã cập nhật trạng thái.');
    statusDialog.open = false;
    loadSupplier();
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không đổi được trạng thái.');
  } finally {
    statusBusy.value = false;
  }
}

function openUpload(document) {
  drawer.open = true;
  drawer.document = document;
  drawer.mode = document?.id ? 'review' : 'upload';
  Object.assign(uploadForm, {
    document_type_id: document?.document_type_id || '',
    custom_name: document?.document_type_id ? '' : document?.name || '',
    document_number: '',
    issuer: '',
    issued_at: '',
    expires_at: '',
    no_expiry: !document?.has_expiry,
    uploader_note: '',
    file: null,
  });
  Object.assign(reviewForm, { status: 'valid', review_note: '' });
}

function onFile(event) {
  uploadForm.file = event.target.files?.[0] || null;
}

async function submitUpload() {
  const form = new FormData();
  Object.entries(uploadForm).forEach(([key, value]) => {
    if (value !== null && value !== '') form.append(key, value);
  });
  try {
    await window.axios.post(`/api/contract/suppliers/${supplierId.value}/documents`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    showClientToast('success', 'Đã tải hồ sơ.');
    drawer.open = false;
    loadSupplier();
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được hồ sơ.');
  }
}

async function submitReview() {
  if (!drawer.document?.id) return;
  try {
    await window.axios.put(`/api/contract/suppliers/${supplierId.value}/documents/${drawer.document.id}/review`, reviewForm);
    showClientToast('success', 'Đã lưu kết quả kiểm tra.');
    drawer.open = false;
    loadSupplier();
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không lưu được kết quả kiểm tra.');
  }
}

watch(() => route.query.tab, (tab) => {
  activeTab.value = tab || 'overview';
});

onMounted(loadSupplier);
</script>

<template>
  <PageHeader
    :title="supplier?.name || 'Nhà cung cấp'"
    :subtitle="supplier ? `${supplier.code} · MST ${supplier.formatted_tax_code || 'chưa có'} · ${[supplier.type_name, supplier.group_name].filter(Boolean).join(' › ')}` : 'Đang tải...'"
  >
    <template #actions>
      <button type="button" class="supplier-detail__ghost" @click="router.push({ name: 'manager.contract.suppliers.index' })">Danh sách</button>
      <button v-if="canManage && supplier" type="button" class="supplier-detail__ghost" @click="router.push({ name: 'manager.contract.suppliers.edit', params: { id: supplier.id } })">Sửa</button>
      <button v-if="canManage && supplier?.status === 'active'" type="button" class="supplier-detail__ghost" :disabled="statusBusy" @click="openStatusDialog('suspended')">Tạm ngưng</button>
      <button v-if="canManage && supplier?.status === 'suspended'" type="button" class="supplier-detail__primary" :disabled="statusBusy" @click="openStatusDialog('active')">Khôi phục</button>
      <button v-if="canManage && supplier && supplier.status !== 'terminated'" type="button" class="supplier-detail__ghost" :disabled="statusBusy" @click="openStatusDialog('terminated')">Ngừng hợp tác</button>
    </template>
  </PageHeader>

  <main class="supplier-detail">
    <section v-if="loading || !supplier" class="supplier-detail__panel">Đang tải dữ liệu...</section>
    <template v-else>
      <section class="supplier-detail__hero">
        <div class="supplier-detail__avatar">{{ (supplier.short_name || supplier.name || 'N').slice(0, 2).toUpperCase() }}</div>
        <div>
          <h1>{{ supplier.name }}</h1>
          <p>{{ supplier.code }} · {{ supplier.status_label }}</p>
        </div>
        <div class="supplier-detail__badges">
          <span>{{ supplier.status_label }}</span>
          <span>Hồ sơ: {{ supplier.document_summary?.label }}</span>
          <span>HĐ hiệu lực: {{ supplier.active_contracts_count }}</span>
        </div>
      </section>

      <nav class="supplier-detail__tabs" aria-label="Tab hồ sơ nhà cung cấp">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          :class="{ 'supplier-detail__tab--active': activeTab === tab.key }"
          @click="setTab(tab.key)"
        >
          {{ tab.label }}
        </button>
      </nav>

      <section v-if="activeTab === 'overview'" class="supplier-detail__grid">
        <article class="supplier-detail__panel">
          <h2>Thông tin doanh nghiệp</h2>
          <div class="supplier-detail__row"><span>Tên pháp lý</span><strong>{{ supplier.legal_name || '—' }}</strong></div>
          <div class="supplier-detail__row"><span>Tên giao dịch</span><strong>{{ supplier.trade_name || '—' }}</strong></div>
          <div class="supplier-detail__row"><span>Người đại diện</span><strong>{{ [supplier.representative_name, supplier.representative_title].filter(Boolean).join(' · ') || '—' }}</strong></div>
          <div class="supplier-detail__row"><span>Địa chỉ trụ sở</span><strong>{{ [supplier.registered_address_line, supplier.registered_ward, supplier.registered_province].filter(Boolean).join(', ') || '—' }}</strong></div>
          <div class="supplier-detail__row"><span>Điện thoại · Email</span><strong>{{ [supplier.phone, supplier.email].filter(Boolean).join(' · ') || '—' }}</strong></div>
        </article>
        <article class="supplier-detail__panel">
          <h2>Phụ trách</h2>
          <div class="supplier-detail__row"><span>Đơn vị quản lý</span><strong>{{ supplier.department_name || '—' }}</strong></div>
          <div class="supplier-detail__row"><span>Người phụ trách</span><strong>{{ supplier.owner?.name || '—' }}</strong></div>
          <div class="supplier-detail__row"><span>Hợp tác từ</span><strong>{{ supplier.cooperation_started_at || '—' }}</strong></div>
          <div class="supplier-detail__row"><span>Xác nhận bởi</span><strong>{{ supplier.confirmed_by_name || '—' }}</strong></div>
        </article>
        <article class="supplier-detail__panel supplier-detail__wide">
          <h2>Cần chú ý</h2>
          <p v-if="!supplier.attention_items?.length" class="supplier-detail__muted">Không có cảnh báo trong phạm vi 30 ngày.</p>
          <button v-for="item in supplier.attention_items" :key="`${item.type}-${item.label}`" type="button" class="supplier-detail__attention" @click="setTab(item.type === 'document' ? 'documents' : 'contracts')">
            <span>{{ item.label }}</span>
            <strong>{{ item.days_left === null ? item.description : `${item.days_left} ngày` }}</strong>
          </button>
        </article>
      </section>

      <section v-else-if="activeTab === 'documents'" class="supplier-detail__panel">
        <div class="supplier-detail__panel-head">
          <h2>Hồ sơ bắt buộc hợp lệ: {{ supplier.document_summary?.required_valid }} / {{ supplier.document_summary?.required_total }}</h2>
          <button v-if="canManage" type="button" class="supplier-detail__primary" @click="openUpload(null)">Thêm hồ sơ khác</button>
        </div>
        <div class="supplier-detail__documents">
          <div class="supplier-detail__doc-head">
            <span>Loại hồ sơ</span><span>Yêu cầu</span><span>Phiên bản</span><span>Ngày hết hạn</span><span>Trạng thái</span><span></span>
          </div>
          <div v-for="doc in supplier.documents" :key="doc.document_type_id" class="supplier-detail__doc-row">
            <span>
              <strong>{{ doc.name }}</strong>
              <small>{{ doc.group_label }}</small>
            </span>
            <span>{{ doc.requirement === 'required' ? 'Bắt buộc' : 'Tùy chọn' }}</span>
            <span>v{{ doc.current_version || '—' }}</span>
            <span>{{ doc.expires_at || 'Không thời hạn' }} <em v-if="doc.days_left !== null">· {{ doc.days_left }} ngày</em></span>
            <span>{{ doc.status_label }}</span>
            <button v-if="canManage" type="button" @click="openUpload(doc)">{{ doc.id ? 'Kiểm tra' : 'Tải lên' }}</button>
          </div>
        </div>
      </section>

      <section v-else-if="activeTab === 'contacts'" class="supplier-detail__panel">
        <h2>Người liên hệ</h2>
        <div v-for="contact in supplier.contacts" :key="contact.id" class="supplier-detail__card-row">
          <strong>{{ contact.full_name }}</strong>
          <span>{{ [contact.contact_type, contact.position, contact.department].filter(Boolean).join(' · ') }}</span>
          <span>{{ [contact.email, contact.phone].filter(Boolean).join(' · ') }}</span>
        </div>
      </section>

      <section v-else-if="activeTab === 'banks'" class="supplier-detail__panel">
        <h2>Tài khoản ngân hàng</h2>
        <div v-for="account in supplier.bank_accounts" :key="account.id" class="supplier-detail__card-row">
          <strong>{{ account.bank_name }} · {{ account.account_number }}</strong>
          <span>{{ account.account_holder }} · {{ account.branch || '—' }} · {{ account.currency }}</span>
          <span v-if="account.is_default">Mặc định</span>
        </div>
      </section>

      <section v-else-if="activeTab === 'contracts'" class="supplier-detail__panel">
        <h2>Hợp đồng gần đây</h2>
        <div v-for="contract in supplier.contracts" :key="contract.id" class="supplier-detail__card-row">
          <strong>{{ contract.code }} · {{ contract.title }}</strong>
          <span>{{ contract.status_label }} · hết hạn {{ contract.ends_at || '—' }}</span>
        </div>
        <p v-if="!supplier.contracts?.length" class="supplier-detail__muted">Chưa có hợp đồng.</p>
      </section>

      <section v-else class="supplier-detail__panel">
        <h2>Lịch sử</h2>
        <p class="supplier-detail__muted">Lịch sử chi tiết dùng bảng `contract_audit_logs`; màn tổng hợp SCR-16 sẽ dựng sau khi hoàn tất lát cắt NCC.</p>
      </section>
    </template>

    <aside v-if="drawer.open" class="supplier-detail__drawer">
      <button type="button" class="supplier-detail__drawer-close" aria-label="Đóng" @click="drawer.open = false">
        <AppIcon name="close" :size="16" />
      </button>
      <h2>{{ drawer.document?.name || 'Hồ sơ bổ sung' }}</h2>
      <p>{{ supplier.name }} · {{ drawer.document?.group_label || 'Khác' }}</p>

      <div class="supplier-detail__drawer-grid">
        <label v-if="!drawer.document?.document_type_id">
          <span>Tên hồ sơ</span>
          <input v-model="uploadForm.custom_name" type="text" />
        </label>
        <label>
          <span>Số / ký hiệu</span>
          <input v-model="uploadForm.document_number" type="text" />
        </label>
        <label>
          <span>Đơn vị lập</span>
          <input v-model="uploadForm.issuer" type="text" />
        </label>
        <label>
          <span>Ngày văn bản</span>
          <input v-model="uploadForm.issued_at" type="date" />
        </label>
        <label>
          <span>Ngày hết hạn</span>
          <input v-model="uploadForm.expires_at" type="date" :disabled="uploadForm.no_expiry" />
        </label>
        <label class="supplier-detail__inline">
          <input v-model="uploadForm.no_expiry" type="checkbox" />
          <span>Không thời hạn</span>
        </label>
        <label class="supplier-detail__wide">
          <span>Tệp</span>
          <input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="onFile" />
        </label>
        <label class="supplier-detail__wide">
          <span>Ghi chú của người tải</span>
          <textarea v-model="uploadForm.uploader_note" rows="3" />
        </label>
      </div>

      <div v-if="drawer.document?.id" class="supplier-detail__review">
        <h3>Kết quả kiểm tra</h3>
        <label><input v-model="reviewForm.status" type="radio" value="valid" /> Hợp lệ</label>
        <label><input v-model="reviewForm.status" type="radio" value="needs_supplement" /> Không hợp lệ · yêu cầu bổ sung</label>
        <label><input v-model="reviewForm.status" type="radio" value="invalid" /> Không hợp lệ</label>
        <textarea v-model="reviewForm.review_note" rows="3" placeholder="Lý do / nội dung cần bổ sung" />
      </div>

      <div class="supplier-detail__drawer-actions">
        <button type="button" class="supplier-detail__ghost" @click="drawer.open = false">Hủy</button>
        <button type="button" class="supplier-detail__ghost" @click="submitUpload">Tải phiên bản mới</button>
        <button v-if="drawer.document?.id" type="button" class="supplier-detail__primary" @click="submitReview">Lưu kết quả</button>
      </div>
    </aside>

    <Teleport to="body">
      <div v-if="statusDialog.open" class="supplier-detail__status-dialog" role="presentation" @mousedown.self="statusDialog.open = false">
        <div class="supplier-detail__status-panel" role="dialog" aria-modal="true" aria-label="Đổi trạng thái nhà cung cấp">
          <h2>Đổi trạng thái nhà cung cấp</h2>
          <p>{{ supplier?.code }} · bắt buộc ghi lý do.</p>
          <textarea v-model="statusDialog.reason" rows="4" placeholder="Nhập lý do..." />
          <div>
            <button type="button" class="supplier-detail__ghost" :disabled="statusBusy" @click="statusDialog.open = false">Huỷ</button>
            <button type="button" class="supplier-detail__primary" :disabled="statusBusy" @click="changeStatus">Lưu trạng thái</button>
          </div>
        </div>
      </div>
    </Teleport>
  </main>
</template>

<style scoped>
.supplier-detail {
  position: relative;
  min-height: calc(100vh - 7rem);
  padding: var(--space-4);
  background: var(--color-bg);
}

.supplier-detail__hero,
.supplier-detail__panel,
.supplier-detail__tabs {
  max-width: 92rem;
  margin: 0 auto var(--space-4);
  border-radius: var(--radius-xl);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.supplier-detail__hero {
  display: flex;
  align-items: center;
  gap: var(--space-4);
  padding: var(--space-5);
}

.supplier-detail__avatar {
  display: grid;
  place-items: center;
  width: 4rem;
  height: 4rem;
  border-radius: var(--radius-xl);
  background: color-mix(in srgb, var(--color-primary) 12%, var(--color-surface));
  color: var(--color-primary);
  font-weight: 700;
}

.supplier-detail__hero h1 {
  margin: 0 0 0.25rem;
  font-size: 1.35rem;
}

.supplier-detail__hero p {
  margin: 0;
  color: var(--color-text-muted);
}

.supplier-detail__badges {
  margin-left: auto;
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: var(--space-2);
}

.supplier-detail__badges span {
  border-radius: var(--radius-full);
  padding: 0.35rem 0.65rem;
  background: var(--color-surface-muted);
}

.supplier-detail__tabs {
  display: flex;
  flex-wrap: wrap;
  padding: var(--space-2);
}

.supplier-detail__tabs button {
  border: 0;
  border-radius: var(--radius-md);
  padding: 0.65rem 0.9rem;
  background: transparent;
  color: var(--color-text);
  cursor: pointer;
}

.supplier-detail__tab--active {
  color: var(--color-primary) !important;
  background: color-mix(in srgb, var(--color-primary) 10%, var(--color-surface)) !important;
}

.supplier-detail__grid {
  max-width: 92rem;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-4);
}

.supplier-detail__panel {
  padding: var(--space-5);
}

.supplier-detail__wide {
  grid-column: 1 / -1;
}

.supplier-detail__panel h2 {
  margin: 0 0 var(--space-3);
  font-size: 1rem;
}

.supplier-detail__panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
}

.supplier-detail__row {
  display: flex;
  justify-content: space-between;
  gap: var(--space-4);
  padding: 0.8rem 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.supplier-detail__row span {
  color: var(--color-text-muted);
}

.supplier-detail__row span::after {
  content: ':';
}

.supplier-detail__row strong {
  text-align: right;
  font-style: italic;
  font-weight: 400;
}

.supplier-detail__attention,
.supplier-detail__card-row {
  width: 100%;
  display: grid;
  gap: 0.2rem;
  border: 0;
  border-radius: var(--radius-lg);
  padding: var(--space-3);
  background: var(--color-surface-muted);
  color: var(--color-text);
  text-align: left;
  margin-top: var(--space-2);
}

.supplier-detail__attention {
  grid-template-columns: 1fr auto;
  cursor: pointer;
}

.supplier-detail__muted,
.supplier-detail__card-row span {
  color: var(--color-text-muted);
}

.supplier-detail__documents {
  display: grid;
  gap: var(--space-2);
  overflow-x: auto;
}

.supplier-detail__doc-head,
.supplier-detail__doc-row {
  display: grid;
  grid-template-columns: 2fr 0.8fr 0.7fr 1.3fr 1fr 6rem;
  gap: var(--space-3);
  align-items: center;
  min-width: 62rem;
}

.supplier-detail__doc-head {
  color: var(--color-text-muted);
  font-size: 0.82rem;
}

.supplier-detail__doc-row {
  padding: var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.supplier-detail__doc-row strong,
.supplier-detail__doc-row small {
  display: block;
}

.supplier-detail__doc-row small,
.supplier-detail__doc-row em {
  color: var(--color-text-muted);
  font-style: normal;
}

.supplier-detail__ghost,
.supplier-detail__primary,
.supplier-detail__doc-row button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 2.25rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 0 var(--space-3);
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
}

.supplier-detail__primary {
  border-color: var(--color-primary);
  background: var(--color-primary);
  color: var(--color-primary-contrast);
}

.supplier-detail__drawer {
  position: fixed;
  top: var(--space-4);
  right: var(--space-4);
  bottom: var(--space-4);
  z-index: 60;
  width: min(36rem, calc(100vw - 2rem));
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  overflow: hidden auto;
  padding: var(--space-5);
  border-radius: var(--radius-xl);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.supplier-detail__drawer h2,
.supplier-detail__drawer p {
  margin: 0;
}

.supplier-detail__drawer p {
  color: var(--color-text-muted);
}

.supplier-detail__drawer-close {
  position: absolute;
  top: var(--space-3);
  right: var(--space-3);
  width: 2rem;
  height: 2rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  cursor: pointer;
}

.supplier-detail__drawer-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
}

.supplier-detail__drawer label {
  display: grid;
  gap: 0.35rem;
  color: var(--color-text-muted);
}

.supplier-detail__drawer input,
.supplier-detail__drawer textarea {
  min-height: 2.35rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 0.5rem var(--space-3);
  background: var(--color-surface);
  color: var(--color-text);
}

.supplier-detail__inline {
  display: flex !important;
  align-items: center;
}

.supplier-detail__review {
  display: grid;
  gap: var(--space-2);
  padding: var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.supplier-detail__review h3 {
  margin: 0;
  font-size: 1rem;
}

.supplier-detail__drawer-actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  margin-top: auto;
}

.supplier-detail__status-dialog {
  position: fixed;
  inset: 0;
  z-index: 410;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
  background: color-mix(in srgb, #000000 45%, transparent);
}

.supplier-detail__status-panel {
  width: min(30rem, 100%);
  display: grid;
  gap: var(--space-3);
  border-radius: var(--radius-lg);
  padding: var(--space-5);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.supplier-detail__status-panel h2,
.supplier-detail__status-panel p {
  margin: 0;
}

.supplier-detail__status-panel p {
  color: var(--color-text-muted);
}

.supplier-detail__status-panel textarea {
  width: 100%;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: var(--space-3);
  background: var(--color-surface);
  color: var(--color-text);
}

.supplier-detail__status-panel div {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

@media (max-width: 64rem) {
  .supplier-detail__grid,
  .supplier-detail__drawer-grid {
    grid-template-columns: 1fr;
  }

  .supplier-detail__badges {
    margin-left: 0;
    justify-content: flex-start;
  }

  .supplier-detail__hero {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
