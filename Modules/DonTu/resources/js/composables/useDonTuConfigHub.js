import { inject } from 'vue';

export const DON_TU_CONFIG_HUB_KEY = 'donTuConfigHub';

/** Hub cấu hình Đơn từ (DonTuLayout) — đăng ký nút + / làm mới lên PageHeader. */
export function useDonTuConfigHub() {
  return inject(DON_TU_CONFIG_HUB_KEY, null);
}
