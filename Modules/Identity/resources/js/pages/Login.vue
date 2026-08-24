<script setup>
//
// Trang đăng nhập — chỉ Google Workspace SSO (không có form user/password).
// Bấm nút -> điều hướng full-page (window.location) tới /auth/google vì
// đây là OAuth redirect thật, không phải gọi API qua axios.
//
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

const errorMessage = computed(() => {
  const error = route.query.error;
  return typeof error === 'string' && error !== '' ? error : null;
});

function loginWithGoogle() {
  const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : null;
  const url = redirect ? `/auth/google?redirect=${encodeURIComponent(redirect)}` : '/auth/google';
  window.location.href = url;
}
</script>

<template>
  <div class="login">
    <div class="login__card">
      <div class="login__brand">
        <span class="login__brand-mark">VA</span>
        <h1 class="login__title">VA Workspace</h1>
      </div>

      <p class="login__subtitle">Đăng nhập bằng tài khoản Google Workspace của trường.</p>

      <p v-if="errorMessage" class="login__error" role="alert">
        {{ errorMessage }}
      </p>

      <button type="button" class="login__google-btn" @click="loginWithGoogle">
        <svg class="login__google-icon" viewBox="0 0 48 48" aria-hidden="true">
          <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3c-1.6 4.7-6.1 8-11.3 8-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.6 6.1 29.6 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.2-.1-2.3-.4-3.5z" />
          <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.6 15.9 18.9 13 24 13c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.6 6.1 29.6 4 24 4c-7.4 0-13.8 4.1-17.1 10.1z" />
          <path fill="#4CAF50" d="M24 44c5.5 0 10.4-1.9 14.3-5.1l-6.6-5.6c-2 1.5-4.6 2.3-7.7 2.3-5.2 0-9.6-3.3-11.2-7.9l-6.6 5.1C9.9 39.6 16.4 44 24 44z" />
          <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.1-4.1 5.4l6.6 5.6C41.5 36 44 30.5 44 24c0-1.2-.1-2.3-.4-3.5z" />
        </svg>
        Đăng nhập bằng Google
      </button>

      <p class="login__hint">Chỉ tài khoản email trường (@vaschools.edu.vn) mới đăng nhập được.</p>
    </div>
  </div>
</template>

<style scoped>
.login {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
  background: var(--color-surface-muted);
}

.login__card {
  width: 100%;
  max-width: 380px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  padding: var(--space-6);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-4);
  text-align: center;
}

.login__brand {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
}

.login__brand-mark {
  width: 48px;
  height: 48px;
  border-radius: var(--radius-full);
  background: var(--color-primary);
  color: var(--color-on-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 1.125rem;
}

.login__title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--color-text);
}

.login__subtitle {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.9375rem;
}

.login__error {
  width: 100%;
  margin: 0;
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-primary-surface);
  color: var(--color-danger);
  font-size: 0.875rem;
}

.login__google-btn {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.9375rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.15s ease, box-shadow 0.15s ease;
}

.login__google-btn:hover {
  background: var(--color-surface-muted);
  box-shadow: var(--shadow-sm);
}

.login__google-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

.login__hint {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

@media (max-width: 480px) {
  .login__card {
    padding: var(--space-5);
  }
}
</style>
