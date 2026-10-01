export const SUPPLIER_COLUMNS = [
  { key: 'code', label: 'Mã', defaultOn: true },
  { key: 'supplier', label: 'Nhà cung cấp', defaultOn: true },
  { key: 'tax_code', label: 'Mã số thuế', defaultOn: true },
  { key: 'type_group', label: 'Loại · Nhóm', defaultOn: true },
  { key: 'owner', label: 'Phụ trách', defaultOn: true },
  { key: 'documents', label: 'Hồ sơ', defaultOn: true },
  { key: 'active_contracts', label: 'HĐ hiệu lực', defaultOn: true },
  { key: 'status', label: 'Trạng thái NCC', defaultOn: true },
  { key: 'actions', label: '', defaultOn: true },
];

export const SUPPLIER_FILTERS = [
  { key: 'q', label: 'Tìm kiếm', defaultOn: true },
  { key: 'supplier_type_id', label: 'Loại', defaultOn: true },
  { key: 'supplier_group_id', label: 'Nhóm', defaultOn: true },
  { key: 'department_id', label: 'Đơn vị quản lý', defaultOn: true },
  { key: 'owner_user_id', label: 'Người phụ trách', defaultOn: true },
  { key: 'document_status', label: 'Hồ sơ', defaultOn: true },
  { key: 'contract_status', label: 'Hợp đồng', defaultOn: true },
];

export const COLUMN_STORAGE_KEY = 'va-contract-supplier-columns-v1';
export const FILTER_STORAGE_KEY = 'va-contract-supplier-filters-v1';
export const ZOOM_STORAGE_KEY = 'va-contract-supplier-zoom-v1';

export function loadVisibility(storageKey, items) {
  const defaults = {};
  for (const item of items) defaults[item.key] = item.defaultOn;
  try {
    const parsed = JSON.parse(localStorage.getItem(storageKey) || '{}');
    return Object.fromEntries(items.map((item) => [item.key, typeof parsed[item.key] === 'boolean' ? parsed[item.key] : defaults[item.key]]));
  } catch {
    return defaults;
  }
}

export function saveVisibility(storageKey, value) {
  try {
    localStorage.setItem(storageKey, JSON.stringify(value));
  } catch {
    // localStorage can be blocked by browser settings.
  }
}

export function loadZoom() {
  const value = Number(localStorage.getItem(ZOOM_STORAGE_KEY) || 1);
  return [0.9, 1, 1.15].includes(value) ? value : 1;
}

export function saveZoom(value) {
  try {
    localStorage.setItem(ZOOM_STORAGE_KEY, String(value));
  } catch {
    // Ignore storage errors.
  }
}
