import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { showClientToast } from '@/lib/clientToast';

export function navigateQuickUtility(router, item) {
  if (!item || item.link_type === 'none' || !item.link_target) {
    return false;
  }
  if (item.link_type === 'route') {
    router.push({ name: item.link_target }).catch(() => {
      showClientToast('error', 'Không mở được liên kết này.');
    });
    return true;
  }
  if (item.link_type === 'url') {
    window.open(item.link_target, '_blank', 'noopener,noreferrer');
    return true;
  }
  return false;
}

export function useDashboardQuickUtilities() {
  const utilities = ref([]);
  const loading = ref(false);
  const router = useRouter();

  async function load() {
    loading.value = true;
    try {
      const { data } = await window.axios.get('/api/dashboard/me/quick-utilities');
      utilities.value = Array.isArray(data?.utilities) ? data.utilities : [];
    } catch (error) {
      utilities.value = [];
      showClientToast('error', error?.response?.data?.message || 'Không tải được tiện ích nhanh.');
    } finally {
      loading.value = false;
    }
  }

  function openUtility(item) {
    if (!navigateQuickUtility(router, item)) {
      showClientToast('info', 'Tiện ích này chưa được cấu hình liên kết.');
    }
  }

  return { utilities, loading, load, openUtility };
}
