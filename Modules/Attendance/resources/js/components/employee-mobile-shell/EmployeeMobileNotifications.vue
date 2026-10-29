<script setup>
//
// Chuông thông báo trên shell mobile / PWA. Bấm mở tấm hộp thư kiểu
// Messenger: ảnh tròn, tên đậm, lời nhắn, mốc giờ, chấm chưa đọc.
//
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { useWebPush } from '@/composables/useWebPush';
import { useEmployeeNotificationInbox } from '../../composables/useEmployeeNotificationInbox.js';
import {
  notificationTimeLabel,
  presentEmployeeNotification,
} from '../../lib/employeeNotificationPresentation.js';

const route = useRoute();
const router = useRouter();
const bellRef = ref(null);
const sheetRef = ref(null);
const brokenAvatars = ref(new Set());
const markingAll = ref(false);

const {
  items,
  unreadCount,
  loading,
  loadingMore,
  error,
  panelOpen,
  page,
  lastPage,
  openPanel,
  closePanel,
  refreshList,
  loadMore,
  markOneRead,
  markAllRead,
} = useEmployeeNotificationInbox();

const {
  permission,
  pushReady,
  pushSupported,
  iosNeedsInstall,
  configured,
  vapidChecked,
  enabling,
  lastError,
  enablePush,
  disablePush,
} = useWebPush();

const badgeLabel = computed(() => {
  if (unreadCount.value <= 0) return '';
  return unreadCount.value > 99 ? '99+' : String(unreadCount.value);
});

const rows = computed(() => items.value.map((item) => ({
  ...item,
  view: presentEmployeeNotification(item),
  timeLabel: notificationTimeLabel(item.created_at),
})));

const hasMore = computed(() => page.value < lastPage.value);

const showPushRow = computed(() => pushSupported.value || iosNeedsInstall.value);

const canTogglePush = computed(() => {
  if (!pushSupported.value || enabling.value) return false;
  if (pushReady.value) return true;
  return configured.value && permission.value !== 'denied';
});

const pushToggleLabel = computed(() => {
  if (iosNeedsInstall.value) return 'Thêm app vào Màn hình chính để nhận thông báo trên máy này';
  if (permission.value === 'denied') return 'Trình duyệt đang chặn thông báo trên máy này';
  if (vapidChecked.value && !configured.value) return 'Máy chủ chưa cấu hình thông báo đẩy';
  return pushReady.value ? 'Tắt thông báo trên máy này' : 'Bật thông báo trên máy này';
});

const bellLabel = computed(() => (
  unreadCount.value > 0
    ? `Thông báo, ${unreadCount.value} chưa đọc`
    : 'Thông báo'
));

let alive = true;

function onEscape(event) {
  if (event.key === 'Escape' && panelOpen.value) closePanel();
}

watch(panelOpen, async (open) => {
  if (!open) {
    window.removeEventListener('keydown', onEscape);
    if (alive && bellRef.value?.isConnected) bellRef.value.focus();
    return;
  }
  window.addEventListener('keydown', onEscape);
  await nextTick();
  sheetRef.value?.focus();
});

watch(() => route.fullPath, () => {
  if (panelOpen.value) closePanel();
});

watch(error, (message) => {
  if (message && items.value.length > 0) {
    showClientToast('error', message);
  }
});

onBeforeUnmount(() => {
  alive = false;
  window.removeEventListener('keydown', onEscape);
  if (panelOpen.value) closePanel();
});

function onAvatarError(id) {
  brokenAvatars.value = new Set([...brokenAvatars.value, id]);
}

function showAvatar(row) {
  return Boolean(row.view.avatarUrl) && !brokenAvatars.value.has(row.id);
}

function onListScroll(event) {
  const el = event.currentTarget;
  if (!el || !hasMore.value) return;
  if (el.scrollHeight - el.scrollTop - el.clientHeight < 120) {
    loadMore();
  }
}

async function openItem(item) {
  closePanel();
  if (!item.read_at) {
    try {
      await markOneRead(item.id);
    } catch {
      // Vẫn mở đích của thông báo.
    }
  }
  if (!item.url) return;
  if (/^https?:\/\//i.test(item.url)) {
    window.open(item.url, '_blank', 'noopener');
    return;
  }
  router.push(item.url);
}

async function onMarkAll() {
  if (markingAll.value || unreadCount.value <= 0) return;
  markingAll.value = true;
  try {
    await markAllRead();
  } catch {
    showClientToast('error', 'Không đánh dấu được đã đọc.');
  } finally {
    markingAll.value = false;
  }
}

async function onTogglePush() {
  if (!canTogglePush.value) return;
  if (pushReady.value) {
    const ok = await disablePush();
    showClientToast(
      ok ? 'success' : 'error',
      ok ? 'Đã tắt thông báo trên máy này.' : (lastError.value || 'Không tắt được thông báo.'),
    );
    return;
  }
  const ok = await enablePush();
  showClientToast(
    ok ? 'success' : 'error',
    ok ? 'Đã bật thông báo trên máy này.' : (lastError.value || 'Không bật được thông báo.'),
  );
}

function togglePanel() {
  if (panelOpen.value) {
    closePanel();
    return;
  }
  openPanel();
}
</script>

<template>
  <button
    ref="bellRef"
    type="button"
    class="employee-shell__icon-btn employee-notices__bell"
    :class="{ 'employee-shell__icon-btn--active': panelOpen }"
    aria-haspopup="dialog"
    :aria-expanded="panelOpen"
    :aria-label="bellLabel"
    @click="togglePanel"
  >
    <AppIcon name="bell" :size="20" :stroke-width="1.8" />
    <span
      v-if="badgeLabel"
      :key="badgeLabel"
      class="employee-shell__chat-badge"
    >{{ badgeLabel }}</span>
  </button>

  <Teleport to="body">
    <Transition name="employee-notices">
      <div
        v-if="panelOpen"
        class="employee-notices"
        @click.self="closePanel"
      >
        <div
          ref="sheetRef"
          class="employee-notices__sheet"
          role="dialog"
          aria-modal="true"
          aria-labelledby="employee-notices-title"
          tabindex="-1"
          @keydown.esc="closePanel"
        >
          <div class="employee-notices__grabber" aria-hidden="true"></div>
          <header class="employee-notices__head">
            <div class="employee-notices__heading">
              <div class="employee-notices__title-row">
                <h2 id="employee-notices-title" class="employee-notices__title">Thông báo</h2>
                <button
                  v-if="showPushRow"
                  type="button"
                  class="employee-notices__switch"
                  :class="{ 'employee-notices__switch--on': pushReady }"
                  role="switch"
                  :aria-checked="pushReady"
                  :aria-label="pushToggleLabel"
                  :disabled="!canTogglePush"
                  :aria-busy="enabling"
                  @click="onTogglePush"
                >
                  <span class="employee-notices__switch-thumb"></span>
                </button>
              </div>
              <p v-if="unreadCount > 0" class="employee-notices__sub">{{ unreadCount }} chưa đọc</p>
              <p v-else-if="rows.length > 0" class="employee-notices__sub">Bạn đã xem hết</p>
            </div>
            <div class="employee-notices__head-actions">
              <button
                v-if="unreadCount > 0"
                type="button"
                class="employee-notices__read-all"
                :disabled="markingAll"
                @click="onMarkAll"
              >
                Đã đọc
              </button>
              <button
                type="button"
                class="employee-notices__close"
                aria-label="Đóng thông báo"
                @click="closePanel"
              >
                <AppIcon name="close" :size="18" :stroke-width="2" />
              </button>
            </div>
          </header>

          <div
            class="employee-notices__list hide-scrollbar"
            :aria-busy="loading"
            @scroll="onListScroll"
          >
            <template v-if="loading && rows.length === 0">
              <div
                v-for="n in 4"
                :key="`skel-${n}`"
                class="employee-notices__skel"
                aria-hidden="true"
              ></div>
            </template>

            <div v-else-if="error && rows.length === 0" class="employee-notices__empty">
              <span class="employee-notices__empty-mark" aria-hidden="true">
                <AppIcon name="bell" :size="26" :stroke-width="1.7" />
              </span>
              <p class="employee-notices__empty-title">Không tải được thông báo</p>
              <button type="button" class="employee-notices__retry" @click="refreshList">
                Thử lại
              </button>
            </div>

            <div v-else-if="rows.length === 0" class="employee-notices__empty">
              <span class="employee-notices__empty-mark" aria-hidden="true">
                <AppIcon name="bell" :size="26" :stroke-width="1.7" />
              </span>
              <p class="employee-notices__empty-title">Chưa có thông báo</p>
              <p class="employee-notices__empty-text">
                Lượt thích, bình luận và tin nhắn sẽ hiện tại đây.
              </p>
            </div>

            <template v-else>
              <button
                v-for="row in rows"
                :key="row.id"
                type="button"
                class="employee-notices__item"
                :class="{ 'employee-notices__item--unread': !row.read_at }"
                @click="openItem(row)"
              >
                <span
                  v-if="!row.read_at"
                  class="employee-notices__sr"
                >Chưa đọc.</span>
                <span class="employee-notices__avatar-wrap">
                  <img
                    v-if="showAvatar(row)"
                    class="employee-notices__avatar"
                    :src="row.view.avatarUrl"
                    alt=""
                    @error="onAvatarError(row.id)"
                  />
                  <span
                    v-else-if="!row.view.system"
                    class="employee-notices__avatar employee-notices__avatar--initial"
                  >{{ row.view.initial }}</span>
                  <span
                    v-else
                    class="employee-notices__avatar employee-notices__avatar--glyph"
                    :data-tone="row.view.tone"
                  >
                    <AppIcon :name="row.view.glyph" :size="22" :stroke-width="1.8" />
                  </span>
                  <span
                    v-if="!row.view.system"
                    class="employee-notices__glyph"
                    :data-tone="row.view.tone"
                  >
                    <AppIcon :name="row.view.glyph" :size="11" :stroke-width="2.4" />
                  </span>
                </span>

                <span class="employee-notices__copy">
                  <span class="employee-notices__line">
                    <span class="employee-notices__name">{{ row.view.lead }}</span><span
                      v-if="row.view.inline"
                      class="employee-notices__action"
                    >{{ row.view.inline }}</span>
                  </span>
                  <span v-if="row.view.preview" class="employee-notices__preview">{{ row.view.preview }}</span>
                  <span v-if="row.timeLabel" class="employee-notices__time">{{ row.timeLabel }}</span>
                </span>

                <span
                  class="employee-notices__dot"
                  :class="{ 'employee-notices__dot--on': !row.read_at }"
                  aria-hidden="true"
                ></span>
              </button>

              <p v-if="loadingMore" class="employee-notices__more">Đang tải thêm…</p>
            </template>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
