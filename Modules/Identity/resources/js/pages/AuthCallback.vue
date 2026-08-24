<script setup>
//
// Trang trung gian: Google callback (backend) redirect về đây sau khi
// Auth::login() thành công. Gọi /api/me để nạp user vào store rồi điều
// hướng vào app — SPA không tự biết đã đăng nhập chỉ từ query string.
//
import { onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { showClientToast } from '@/lib/clientToast';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

onMounted(async () => {
  if (route.query.status !== 'ok') {
    router.replace({ name: 'login', query: { error: 'Đăng nhập thất bại. Vui lòng thử lại.' } });
    return;
  }

  try {
    await auth.fetchMe();
  } catch {
    router.replace({ name: 'login', query: { error: 'Không thể xác thực phiên đăng nhập.' } });
    return;
  }

  if (!auth.isAuthenticated) {
    router.replace({ name: 'login', query: { error: 'Không thể xác thực phiên đăng nhập.' } });
    return;
  }

  const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/';
  await router.replace(redirect);
  // Hiện toast sau khi đã điều hướng vào app (ToastHost mount ở App.vue,
  // sống xuyên suốt route change nên vẫn nhận được event dù AuthCallback
  // đã unmount).
  const name = auth.user?.name;
  showClientToast('success', name ? `Xin chào, ${name}!` : 'Đăng nhập thành công.');
});
</script>

<template>
  <div class="auth-callback">
    <p class="auth-callback__text">Đang đăng nhập...</p>
  </div>
</template>

<style scoped>
.auth-callback {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.auth-callback__text {
  color: var(--color-text-muted);
  font-size: 0.9375rem;
}
</style>
