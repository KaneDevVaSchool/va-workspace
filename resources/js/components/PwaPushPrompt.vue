<script setup>
//
// Gợi ý đăng ký Web Push (thông báo hệ thống iOS/Android qua service worker),
// không liên quan toast/popup trong app hay hộp thư thông báo nội bộ.
// Nút "Cho phép" mở hộp thoại quyền của điện thoại rồi lưu subscription lên server.
//
import { computed, onMounted, ref, watch } from 'vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { isPwaStandalone } from '../lib/pwaStandalone';
import { useWebPush } from '../composables/useWebPush';
import AppIcon from './AppIcon.vue';

const auth = useAuthStore();

const SESSION_DISMISS_KEY = 'va-pwa-push-prompt-dismissed';

const open = ref(false);

const {
  permission,
  pushReady,
  pushSupported,
  configured,
  vapidChecked,
  enabling,
  lastError,
  enablePush,
  showSystemNotification,
} = useWebPush();

const canOffer = computed(() => {
  if (!auth.isAuthenticated) return false;
  if (!isPwaStandalone()) return false;
  if (!pushSupported.value || !vapidChecked.value || !configured.value) return false;
  if (pushReady.value || permission.value === 'denied') return false;
  try {
    return sessionStorage.getItem(SESSION_DISMISS_KEY) !== '1';
  } catch {
    return true;
  }
});

function tryOpen() {
  if (canOffer.value) {
    open.value = true;
  }
}

onMounted(() => {
  window.setTimeout(tryOpen, 400);
});

watch(canOffer, (ok) => {
  if (ok && !open.value) {
    tryOpen();
  }
});

function dismissForSession() {
  try {
    sessionStorage.setItem(SESSION_DISMISS_KEY, '1');
  } catch {
    // localStorage/sessionStorage có thể bị chặn
  }
  open.value = false;
}

async function onEnable() {
  const ok = await enablePush();
  if (ok) {
    open.value = false;
    await showSystemNotification({
      title: 'VA Workspace',
      body: 'Đã bật thông báo trên điện thoại. Tin mới sẽ hiện cả khi bạn không mở app.',
      tag: 'va-push-enabled',
    });
    return;
  }
  if (permission.value === 'denied') {
    open.value = false;
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="pwa-push-sheet">
      <div
        v-if="open"
        class="pwa-push-prompt"
        role="presentation"
        @mousedown.self="dismissForSession"
      >
        <div
          class="pwa-push-prompt__sheet"
          role="dialog"
          aria-modal="true"
          aria-labelledby="pwa-push-title"
          aria-describedby="pwa-push-desc"
        >
          <div class="pwa-push-prompt__grab" aria-hidden="true" />

          <div class="pwa-push-prompt__icon-wrap" aria-hidden="true">
            <AppIcon name="bell" :size="26" :stroke-width="1.75" />
          </div>

          <h2 id="pwa-push-title" class="pwa-push-prompt__title">
            Nhận thông báo trên điện thoại?
          </h2>
          <p id="pwa-push-desc" class="pwa-push-prompt__desc">
            Tin nhắn và cập nhật hiện trên màn hình khóa và Trung tâm thông báo của máy
            (giống Zalo, Messenger) — không phải popup trong app.
          </p>
          <p class="pwa-push-prompt__hint">
            Bấm bên dưới, điện thoại sẽ hỏi quyền thông báo của hệ thống.
          </p>

          <p v-if="lastError" class="pwa-push-prompt__error" role="alert">
            {{ lastError }}
          </p>

          <div class="pwa-push-prompt__actions">
            <button
              type="button"
              class="pwa-push-prompt__btn pwa-push-prompt__btn--primary"
              :disabled="enabling"
              @click="onEnable"
            >
              {{ enabling ? 'Đang đăng ký…' : 'Cho phép trên điện thoại' }}
            </button>
            <button
              type="button"
              class="pwa-push-prompt__btn pwa-push-prompt__btn--ghost"
              :disabled="enabling"
              @click="dismissForSession"
            >
              Để sau
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.pwa-push-prompt {
  position: fixed;
  inset: 0;
  z-index: 1900;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding: var(--space-4);
  padding-bottom: calc(var(--space-4) + var(--toast-shell-bottom-inset, 0px));
  background: color-mix(in srgb, var(--color-text) 28%, transparent);
  backdrop-filter: blur(4px);
}

.pwa-push-prompt__sheet {
  width: 100%;
  max-width: 24rem;
  padding: var(--space-3) var(--space-4) var(--space-5);
  border-radius: var(--radius-xl);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
  text-align: center;
}

.pwa-push-prompt__grab {
  width: 2.5rem;
  height: 0.25rem;
  margin: 0 auto var(--space-3);
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.pwa-push-prompt__icon-wrap {
  display: grid;
  place-items: center;
  width: 3.25rem;
  height: 3.25rem;
  margin: 0 auto var(--space-3);
  border-radius: var(--radius-full);
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.pwa-push-prompt__title {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 700;
  line-height: 1.35;
  color: var(--color-text);
}

.pwa-push-prompt__desc {
  margin: var(--space-2) 0 0;
  font-size: 0.875rem;
  line-height: 1.5;
  color: var(--color-text-muted);
}

.pwa-push-prompt__hint {
  margin: var(--space-3) 0 0;
  font-size: 0.75rem;
  line-height: 1.45;
  color: var(--color-text-muted);
  opacity: 0.9;
}

.pwa-push-prompt__error {
  margin: var(--space-3) 0 0;
  font-size: 0.8125rem;
  line-height: 1.45;
  color: var(--color-danger);
}

.pwa-push-prompt__actions {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin-top: var(--space-4);
}

.pwa-push-prompt__btn {
  width: 100%;
  padding: 0.75rem var(--space-4);
  border: none;
  border-radius: var(--radius-md);
  font-family: inherit;
  font-size: 0.9375rem;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.15s ease, background-color 0.15s ease;
}

.pwa-push-prompt__btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.pwa-push-prompt__btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.pwa-push-prompt__btn--ghost {
  background: transparent;
  color: var(--color-text-muted);
}

.pwa-push-sheet-enter-active,
.pwa-push-sheet-leave-active {
  transition: opacity 0.22s ease;
}

.pwa-push-sheet-enter-active .pwa-push-prompt__sheet,
.pwa-push-sheet-leave-active .pwa-push-prompt__sheet {
  transition: transform 0.26s cubic-bezier(0.32, 0.72, 0, 1);
}

.pwa-push-sheet-enter-from,
.pwa-push-sheet-leave-to {
  opacity: 0;
}

.pwa-push-sheet-enter-from .pwa-push-prompt__sheet,
.pwa-push-sheet-leave-to .pwa-push-prompt__sheet {
  transform: translateY(100%);
}

@media (min-width: 769px) {
  .pwa-push-prompt {
    align-items: center;
    padding-bottom: var(--space-4);
  }

  .pwa-push-prompt__grab {
    display: none;
  }
}
</style>
