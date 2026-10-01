<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import { showClientToast } from '@/lib/clientToast';

const route = useRoute();
const router = useRouter();
const supplierId = computed(() => Number(route.params.id || 0));
const isEdit = computed(() => supplierId.value > 0);
const loading = ref(false);
const saving = ref(false);
const duplicate = ref(null);
const options = ref({ types: [], groups: [], departments: [], owners: [], next_code: '' });

const form = reactive({
  code: '',
  name: '',
  short_name: '',
  tax_code: '',
  supplier_type_id: '',
  supplier_group_id: '',
  legal_name: '',
  trade_name: '',
  representative_name: '',
  representative_title: '',
  registered_address_line: '',
  registered_ward: '',
  registered_province: '',
  transaction_address_same_as_registered: true,
  transaction_address_line: '',
  transaction_ward: '',
  transaction_province: '',
  phone: '',
  email: '',
  department_id: '',
  owner_user_id: '',
  contacts: [],
  bank_accounts: [],
});

const filteredGroups = computed(() => {
  if (!form.supplier_type_id) return options.value.groups || [];
  return (options.value.groups || []).filter((group) => Number(group.supplier_type_id) === Number(form.supplier_type_id));
});

function addContact() {
  form.contacts.push({ contact_type: '', full_name: '', position: '', department: '', email: '', phone: '', is_primary: form.contacts.length === 0 });
}

function removeContact(index) {
  form.contacts.splice(index, 1);
}

function addBankAccount() {
  form.bank_accounts.push({ bank_name: '', branch: '', account_holder: '', account_number: '', currency: 'VND', is_default: form.bank_accounts.length === 0, notes: '' });
}

function removeBankAccount(index) {
  form.bank_accounts.splice(index, 1);
}

function setPrimaryContact(index) {
  form.contacts.forEach((item, i) => { item.is_primary = i === index; });
}

function setDefaultBank(index) {
  form.bank_accounts.forEach((item, i) => { item.is_default = i === index; });
}

async function loadOptions() {
  const { data } = await window.axios.get('/api/contract/suppliers/options');
  options.value = data;
  if (!isEdit.value) form.code = data.next_code || '';
}

async function loadSupplier() {
  if (!isEdit.value) return;
  loading.value = true;
  try {
    const { data } = await window.axios.get(`/api/contract/suppliers/${supplierId.value}`);
    const supplier = data.supplier;
    Object.keys(form).forEach((key) => {
      if (key in supplier) form[key] = supplier[key] ?? (Array.isArray(form[key]) ? [] : '');
    });
    form.contacts = supplier.contacts?.length ? supplier.contacts.map((item) => ({ ...item })) : [];
    form.bank_accounts = supplier.bank_accounts?.length ? supplier.bank_accounts.map((item) => ({ ...item })) : [];
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được nhà cung cấp.');
  } finally {
    loading.value = false;
  }
}

async function checkTaxCode() {
  duplicate.value = null;
  if (!form.tax_code) return;
  try {
    const { data } = await window.axios.get('/api/contract/suppliers/check-tax-code', {
      params: { tax_code: form.tax_code, ignore_id: isEdit.value ? supplierId.value : undefined },
    });
    duplicate.value = data.duplicate || null;
  } catch {
    // Duplicate check is advisory; save endpoint validates again.
  }
}

function payload() {
  return {
    ...form,
    supplier_type_id: form.supplier_type_id || null,
    supplier_group_id: form.supplier_group_id || null,
    department_id: form.department_id || null,
    owner_user_id: form.owner_user_id || null,
    contacts: form.contacts,
    bank_accounts: form.bank_accounts,
  };
}

async function save(goDocuments = false) {
  saving.value = true;
  try {
    const response = isEdit.value
      ? await window.axios.put(`/api/contract/suppliers/${supplierId.value}`, payload())
      : await window.axios.post('/api/contract/suppliers', payload());
    const id = response.data.supplier.id;
    showClientToast('success', isEdit.value ? 'Đã cập nhật nhà cung cấp.' : 'Đã tạo nhà cung cấp.');
    router.push({ name: 'manager.contract.suppliers.show', params: { id }, query: goDocuments ? { tab: 'documents' } : {} });
  } catch (error) {
    const dup = error?.response?.data?.duplicate;
    if (dup) duplicate.value = dup;
    showClientToast('error', error?.response?.data?.message || 'Không lưu được nhà cung cấp.');
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  await loadOptions();
  if (!form.contacts.length) addContact();
  if (!form.bank_accounts.length) addBankAccount();
  await loadSupplier();
});
</script>

<template>
  <PageHeader
    :title="isEdit ? 'Sửa nhà cung cấp' : 'Thêm nhà cung cấp'"
    subtitle="Trường có dấu * là bắt buộc"
  >
    <template #actions>
      <button type="button" class="supplier-form__ghost" @click="router.back()">Hủy</button>
      <button type="button" class="supplier-form__ghost" :disabled="saving" @click="save(false)">Lưu nháp</button>
      <button type="button" class="supplier-form__primary" :disabled="saving" @click="save(true)">Lưu & tải hồ sơ</button>
    </template>
  </PageHeader>

  <form class="supplier-form" @submit.prevent="save(false)">
    <div v-if="loading" class="supplier-form__panel">Đang tải dữ liệu...</div>

    <section v-else class="supplier-form__panel">
      <div class="supplier-form__section-title">
        <AppIcon name="building" :size="18" />
        <h2>Định danh</h2>
      </div>
      <div class="supplier-form__grid">
        <label>
          <span>Mã nhà cung cấp</span>
          <input v-model="form.code" type="text" disabled />
        </label>
        <label>
          <span>Tên nhà cung cấp *</span>
          <input v-model="form.name" type="text" required />
        </label>
        <label>
          <span>Tên viết tắt</span>
          <input v-model="form.short_name" type="text" />
        </label>
        <label>
          <span>Mã số thuế</span>
          <input v-model="form.tax_code" type="text" @blur="checkTaxCode" />
        </label>
        <div v-if="duplicate" class="supplier-form__duplicate">
          MST đã thuộc {{ duplicate.code }} · {{ duplicate.name }}.
          <button type="button" @click="router.push({ name: 'manager.contract.suppliers.show', params: { id: duplicate.id } })">Mở NCC này</button>
        </div>
        <label>
          <span>Loại nhà cung cấp</span>
          <select v-model="form.supplier_type_id" @change="form.supplier_group_id = ''">
            <option value="">Chọn loại</option>
            <option v-for="item in options.types" :key="item.id" :value="item.id">{{ item.name }}</option>
          </select>
        </label>
        <label>
          <span>Nhóm nhà cung cấp</span>
          <select v-model="form.supplier_group_id">
            <option value="">Chọn nhóm</option>
            <option v-for="item in filteredGroups" :key="item.id" :value="item.id">{{ item.name }}</option>
          </select>
        </label>
      </div>

      <div class="supplier-form__section-title">
        <AppIcon name="fileText" :size="18" />
        <h2>Thông tin doanh nghiệp</h2>
      </div>
      <div class="supplier-form__grid">
        <label>
          <span>Tên pháp lý</span>
          <input v-model="form.legal_name" type="text" />
        </label>
        <label>
          <span>Tên giao dịch</span>
          <input v-model="form.trade_name" type="text" />
        </label>
        <label>
          <span>Người đại diện</span>
          <input v-model="form.representative_name" type="text" />
        </label>
        <label>
          <span>Chức vụ người đại diện</span>
          <input v-model="form.representative_title" type="text" />
        </label>
        <label class="supplier-form__wide">
          <span>Địa chỉ trụ sở</span>
          <input v-model="form.registered_address_line" type="text" />
        </label>
        <label>
          <span>Phường/Xã</span>
          <input v-model="form.registered_ward" type="text" />
        </label>
        <label>
          <span>Tỉnh/Thành phố</span>
          <input v-model="form.registered_province" type="text" />
        </label>
        <label>
          <span>Điện thoại</span>
          <input v-model="form.phone" type="text" />
        </label>
        <label>
          <span>Email</span>
          <input v-model="form.email" type="email" />
        </label>
        <label class="supplier-form__inline">
          <input v-model="form.transaction_address_same_as_registered" type="checkbox" />
          <span>Địa chỉ giao dịch giống địa chỉ trụ sở</span>
        </label>
        <template v-if="!form.transaction_address_same_as_registered">
          <label class="supplier-form__wide">
            <span>Địa chỉ giao dịch</span>
            <input v-model="form.transaction_address_line" type="text" />
          </label>
          <label>
            <span>Phường/Xã giao dịch</span>
            <input v-model="form.transaction_ward" type="text" />
          </label>
          <label>
            <span>Tỉnh/Thành phố giao dịch</span>
            <input v-model="form.transaction_province" type="text" />
          </label>
        </template>
      </div>

      <div class="supplier-form__section-title">
        <AppIcon name="user" :size="18" />
        <h2>Phụ trách</h2>
      </div>
      <div class="supplier-form__grid">
        <label>
          <span>Đơn vị quản lý</span>
          <select v-model="form.department_id">
            <option value="">Theo người tạo</option>
            <option v-for="item in options.departments" :key="item.id" :value="item.id">{{ item.name }}</option>
          </select>
        </label>
        <label>
          <span>Người phụ trách</span>
          <select v-model="form.owner_user_id">
            <option value="">Theo người tạo</option>
            <option v-for="item in options.owners" :key="item.id" :value="item.id">{{ item.name }}</option>
          </select>
        </label>
      </div>

      <div class="supplier-form__section-title">
        <AppIcon name="users" :size="18" />
        <h2>Người liên hệ</h2>
        <button type="button" @click="addContact">Thêm người liên hệ</button>
      </div>
      <div class="supplier-form__mini-table">
        <div class="supplier-form__mini-head supplier-form__contacts-grid">
          <span>Loại liên hệ</span><span>Họ tên</span><span>Chức vụ</span><span>Bộ phận</span><span>Email</span><span>Điện thoại</span><span>Chính</span><span></span>
        </div>
        <div v-for="(contact, index) in form.contacts" :key="index" class="supplier-form__contacts-grid">
          <input v-model="contact.contact_type" type="text" />
          <input v-model="contact.full_name" type="text" />
          <input v-model="contact.position" type="text" />
          <input v-model="contact.department" type="text" />
          <input v-model="contact.email" type="email" />
          <input v-model="contact.phone" type="text" />
          <input :checked="contact.is_primary" type="radio" name="primary-contact" @change="setPrimaryContact(index)" />
          <button type="button" aria-label="Xoá người liên hệ" @click="removeContact(index)">×</button>
        </div>
      </div>

      <div class="supplier-form__section-title">
        <AppIcon name="dollarSign" :size="18" />
        <h2>Tài khoản ngân hàng</h2>
        <button type="button" @click="addBankAccount">Thêm tài khoản</button>
      </div>
      <div class="supplier-form__mini-table">
        <div class="supplier-form__mini-head supplier-form__banks-grid">
          <span>Ngân hàng</span><span>Chi nhánh</span><span>Chủ tài khoản</span><span>Số tài khoản</span><span>Tiền tệ</span><span>Mặc định</span><span></span>
        </div>
        <div v-for="(account, index) in form.bank_accounts" :key="index" class="supplier-form__banks-grid">
          <input v-model="account.bank_name" type="text" />
          <input v-model="account.branch" type="text" />
          <input v-model="account.account_holder" type="text" />
          <input v-model="account.account_number" type="text" />
          <input v-model="account.currency" type="text" />
          <input :checked="account.is_default" type="radio" name="default-bank" @change="setDefaultBank(index)" />
          <button type="button" aria-label="Xoá tài khoản" @click="removeBankAccount(index)">×</button>
        </div>
      </div>
    </section>
  </form>
</template>

<style scoped>
.supplier-form {
  min-height: calc(100vh - 7rem);
  padding: var(--space-4);
  background: var(--color-bg);
}

.supplier-form__panel {
  display: grid;
  gap: var(--space-5);
  max-width: 92rem;
  margin: 0 auto;
  padding: var(--space-5);
  border-radius: var(--radius-xl);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.supplier-form__section-title {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding-top: var(--space-2);
  box-shadow: 0 -1px 0 color-mix(in srgb, var(--color-border) 70%, transparent);
}

.supplier-form__section-title:first-child {
  padding-top: 0;
  box-shadow: none;
}

.supplier-form__section-title h2 {
  flex: 1;
  margin: 0;
  font-size: 1rem;
}

.supplier-form__section-title button,
.supplier-form__ghost,
.supplier-form__primary {
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

.supplier-form__primary {
  border-color: var(--color-primary);
  background: var(--color-primary);
  color: var(--color-primary-contrast);
}

.supplier-form__grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-4);
}

.supplier-form label {
  display: grid;
  gap: 0.35rem;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.supplier-form input,
.supplier-form select {
  min-height: 2.5rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 0 var(--space-3);
  background: var(--color-surface);
  color: var(--color-text);
}

.supplier-form__wide,
.supplier-form__duplicate {
  grid-column: 1 / -1;
}

.supplier-form__duplicate {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  border-radius: var(--radius-md);
  padding: var(--space-3);
  background: color-mix(in srgb, var(--color-warning) 12%, var(--color-surface));
  color: var(--color-text);
}

.supplier-form__inline {
  display: flex !important;
  grid-column: 1 / -1;
  grid-template-columns: auto 1fr;
  align-items: center;
}

.supplier-form__inline input {
  min-height: auto;
}

.supplier-form__mini-table {
  display: grid;
  gap: var(--space-2);
  overflow-x: auto;
}

.supplier-form__contacts-grid,
.supplier-form__banks-grid {
  display: grid;
  gap: var(--space-2);
  min-width: 72rem;
  align-items: center;
}

.supplier-form__contacts-grid {
  grid-template-columns: 1fr 1.2fr 1fr 1fr 1.3fr 1fr 4rem 3rem;
}

.supplier-form__banks-grid {
  grid-template-columns: 1.2fr 1fr 1.5fr 1.2fr 0.7fr 5rem 3rem;
}

.supplier-form__mini-head {
  color: var(--color-text-muted);
  font-size: 0.8rem;
}

.supplier-form__mini-table button {
  min-height: 2.25rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  cursor: pointer;
}

@media (max-width: 64rem) {
  .supplier-form__grid {
    grid-template-columns: 1fr;
  }
}
</style>
