import { onMounted, ref } from 'vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { isBirthdayGreetingDismissedToday } from '../lib/employeeBirthdayGreeting.js';

/** Lời chúc sinh nhật hôm nay — mọi nhân viên đều thấy cùng danh sách HRM. */
export function useEmployeeBirthdayGreeting() {
  const auth = useAuthStore();
  const people = ref([]);
  const visible = ref(false);

  async function load() {
    if (!auth.isAuthenticated || isBirthdayGreetingDismissedToday()) {
      return;
    }

    try {
      const { data } = await window.axios.get('/api/dashboard/me/birthday-greeting');
      const list = Array.isArray(data?.people)
        ? data.people.filter((person) => person?.name)
        : [];
      if (list.length > 0) {
        people.value = list;
        visible.value = true;
      }
    } catch {
      /* HRM tạm không đọc được — bỏ qua */
    }
  }

  function hide() {
    visible.value = false;
  }

  onMounted(load);

  return { people, visible, hide };
}
