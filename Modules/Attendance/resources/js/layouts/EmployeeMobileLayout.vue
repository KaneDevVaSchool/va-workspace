<script setup>
//
// Shell mobile dùng chung cho MỌI tài khoản trên viewport ≤768px — KHÔNG
// dùng AppSidebar/AppHeader. Header ngang tối giản (lời chào) + bottom tab
// bar cố định 5 mục. Dùng cho nhóm route meta.employeeMobile khi
// useEmployeeMobileView() = true.
//
// CSS: resources/css/employee-mobile-shell.css
//   .employee-shell--browser / --pwa  → header
//   .employee-tabbar--shell (flex footer trong shell) → bottom nav
//
import { computed, onMounted, provide, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { usePullToRefresh } from '@/composables/usePullToRefresh';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { usePwaInstall } from '@/composables/usePwaInstall';
import { useEmployeeMobileShell } from '@/composables/useEmployeeMobileShell';
import { useEmployeeTabbarDock } from '@/composables/useEmployeeTabbarDock';
import {
  syncEmployeeMobileViewport,
  useEmployeeMobileViewport,
} from '@/composables/useEmployeeMobileViewport';
import AppIcon from '@/components/AppIcon.vue';
import { useChatStore } from '@modules/Chat/resources/js/store/chatStore';
import { useChatRealtime } from '@/composables/useChatRealtime';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const chatStore = useChatStore();
useChatRealtime();
const contentRef = ref(null);
const homeSearchQuery = ref('');
let homeRefreshHandler = null;
let feedRefreshHandler = null;
let pageRefreshHandler = null;
const headerRefreshing = ref(false);

provide('employeeHomeSearch', homeSearchQuery);
provide('employeeHomeRefreshRegister', (handler) => {
  homeRefreshHandler = handler;
  return () => {
    if (homeRefreshHandler === handler) homeRefreshHandler = null;
  };
});
provide('employeeFeedRefreshRegister', (handler) => {
  feedRefreshHandler = handler;
  return () => {
    if (feedRefreshHandler === handler) feedRefreshHandler = null;
  };
});
provide('employeePageRefreshRegister', (handler) => {
  pageRefreshHandler = handler;
  return () => {
    if (pageRefreshHandler === handler) pageRefreshHandler = null;
  };
});

function resolvePageRefreshHandler() {
  if (route.name === 'dashboard.me' && homeRefreshHandler) return homeRefreshHandler;
  if (route.name === 'employee.feed' && feedRefreshHandler) return feedRefreshHandler;
  return pageRefreshHandler;
}

async function onHeaderRefresh() {
  if (headerRefreshing.value) return;
  const handler = resolvePageRefreshHandler();
  headerRefreshing.value = true;
  try {
    if (handler) {
      await handler();
    } else {
      window.location.reload();
    }
  } finally {
    headerRefreshing.value = false;
  }
}

const { pullY, refreshing: pullRefreshing } = usePullToRefresh(
  contentRef,
  async () => {
    if (route.name === 'dashboard.me' && homeRefreshHandler) {
      await homeRefreshHandler();
      return;
    }
    if (route.name === 'employee.feed' && feedRefreshHandler) {
      await feedRefreshHandler();
    }
  },
  {
    enabled: () =>
      (route.name === 'dashboard.me' && Boolean(homeRefreshHandler))
      || (route.name === 'employee.feed' && Boolean(feedRefreshHandler)),
  },
);

const pullIndicatorStyle = computed(() => ({
  height: `${Math.max(0, pullY.value)}px`,
  opacity: pullY.value > 4 ? Math.min(1, pullY.value / 56) : 0,
}));
const { canInstall, promptInstall, dismiss } = usePwaInstall();
const { shellMode, isMobilePwa } = useEmployeeMobileShell();
const tabbarRef = ref(null);
useEmployeeTabbarDock(tabbarRef);
useEmployeeMobileViewport();

const TABS = [
  { name: 'dashboard.me', icon: 'home', label: 'Trang chủ' },
  { name: 'employee.feed', icon: 'megaphone', label: 'Bảng tin' },
  { name: 'employee.attendance', icon: 'clock', label: 'Chấm công' },
  { name: 'employee.leave', icon: 'calendar', label: 'Nghỉ phép' },
  { name: 'employee.requests', icon: 'clipboardCheck', label: 'Lịch sử' },
];

const greeting = computed(() => {
  const hour = new Date().getHours();
  if (hour < 11) return 'Chào buổi sáng';
  if (hour < 14) return 'Chào buổi trưa';
  if (hour < 18) return 'Chào buổi chiều';
  return 'Chào buổi tối';
});

const firstName = computed(() => {
  const name = auth.user?.name || '';
  const parts = name.trim().split(/\s+/);
  return parts[parts.length - 1] || name;
});

const displayName = computed(() => auth.user?.name?.trim() || '');
const departmentName = computed(() => auth.user?.department?.name?.trim() || '');
const jobTitleName = computed(() => auth.user?.job_title_name?.trim() || '');

const avatarBroken = ref(false);
const hasAvatarPhoto = computed(
  () => Boolean(auth.user?.avatar_url) && !avatarBroken.value,
);
const avatarInitial = computed(() => {
  const name = displayName.value || auth.user?.email || '?';
  return name.trim().charAt(0).toUpperCase() || '?';
});

const isHomeHeader = computed(() => route.name === 'dashboard.me');

const pageHeaderTitle = computed(() => {
  if (isHomeHeader.value) return '';
  const title = route.meta?.title;
  return typeof title === 'string' ? title.trim() : '';
});

const isChatRoute = computed(() => route.name === 'employee.chat');
const hideHeaderForChatThread = computed(
  () => isChatRoute.value && chatStore.activeView === 'conversation',
);

const chatUnreadLabel = computed(() => {
  if (chatStore.unreadTotal <= 0) return '';
  return chatStore.unreadTotal > 99 ? '99+' : String(chatStore.unreadTotal);
});

const chatHeaderAria = computed(() =>
  chatStore.unreadTotal > 0
    ? `Trò chuyện, ${chatStore.unreadTotal} tin nhắn chưa đọc`
    : 'Trò chuyện',
);

onMounted(() => {
  if (auth.isAuthenticated) {
    auth.fetchMe();
    chatStore.fetchUnreadTotal().catch(() => {});
    chatStore.fetchConversations().catch(() => {});
  }
});

watch(isMobilePwa, () => {
  syncEmployeeMobileViewport();
});

watch(
  () => route.name,
  (name) => {
    if (name !== 'dashboard.me') {
      homeSearchQuery.value = '';
    }
  },
);

const todayLabel = computed(() => new Intl.DateTimeFormat('vi-VN', {
  weekday: 'long',
  day: '2-digit',
  month: '2-digit',
}).format(new Date()));

const isProfileRoute = computed(() => route.name === 'employee.profile');

function goToProfile() {
  if (isProfileRoute.value) return;
  router.push({ name: 'employee.profile' });
}

function goBackFromSubpage() {
  if (window.history.length > 1) {
    router.back();
    return;
  }
  router.push({ name: 'dashboard.me' });
}
</script>

<template>
  <div
    class="employee-shell"
    :class="[`employee-shell--${shellMode}`, 'employee-shell--unified']"
  >
    <header
      v-if="!hideHeaderForChatThread"
      class="employee-shell__header employee-shell__header--brand"
      :class="{
        'employee-shell__header--home': isHomeHeader,
        'employee-shell__header--subpage': isProfileRoute,
      }"
    >
      <div class="employee-shell__header-deco" aria-hidden="true"></div>
      <div class="employee-shell__header-top">
        <div class="employee-shell__header-lead">
          <button
            v-if="isProfileRoute"
            type="button"
            class="employee-shell__brand-mark employee-shell__back-btn"
            aria-label="Quay lại"
            @click="goBackFromSubpage"
          >
            <AppIcon name="chevronLeft" :size="20" :stroke-width="2" />
          </button>
          <button
            v-else
            type="button"
            class="employee-shell__brand-mark"
            aria-label="Mở hồ sơ cá nhân"
            @click="goToProfile"
          >
            <img
              src="/images/pwa/icon-192.png"
              alt=""
              class="employee-shell__brand-logo employee-shell__brand-logo--pwa"
              width="40"
              height="40"
            />
          </button>
          <div class="employee-shell__greeting">
          <template v-if="isHomeHeader">
            <p class="employee-shell__kicker">{{ greeting }}</p>
            <h1 class="employee-shell__hello">
              {{ firstName || displayName || 'bạn' }}
            </h1>
            <div class="employee-shell__meta employee-shell__meta--reserve employee-shell__meta--home">
              <span class="employee-shell__date">{{ todayLabel }}</span>
              <span v-if="departmentName || jobTitleName" class="employee-shell__meta-sep" aria-hidden="true">·</span>
              <span v-if="departmentName" class="employee-shell__meta-extra">{{ departmentName }}</span>
              <span v-else-if="jobTitleName" class="employee-shell__meta-extra">{{ jobTitleName }}</span>
            </div>
          </template>
          <template v-else-if="isProfileRoute">
            <h1 class="employee-shell__hello employee-shell__hello--page">
              Hồ sơ cá nhân
            </h1>
          </template>
          <template v-else>
            <p class="employee-shell__kicker">VA Workspace</p>
            <h1 class="employee-shell__hello employee-shell__hello--page">
              {{ pageHeaderTitle || displayName || 'Nhân sự' }}
            </h1>
            <div
              v-if="pageHeaderTitle"
              class="employee-shell__meta employee-shell__meta--reserve"
            >
              <span
                v-if="departmentName || jobTitleName"
                class="employee-shell__meta-extra"
              >{{ departmentName || jobTitleName }}</span>
            </div>
            <p v-else-if="!pageHeaderTitle && departmentName" class="employee-shell__date">{{ departmentName }}</p>
            <p v-else-if="!pageHeaderTitle && jobTitleName" class="employee-shell__date">{{ jobTitleName }}</p>
          </template>
          </div>
        </div>
        <div class="employee-shell__header-actions">
          <button
            type="button"
            class="employee-shell__icon-btn"
            :disabled="headerRefreshing"
            aria-label="Làm mới trang"
            @click="onHeaderRefresh"
          >
            <AppIcon
              name="refresh"
              :size="20"
              :stroke-width="1.8"
              :class="{ 'employee-shell__icon-btn-spin': headerRefreshing }"
            />
          </button>
          <button
            type="button"
            class="employee-shell__icon-btn employee-shell__icon-btn--chat"
            :aria-label="chatHeaderAria"
            @click="router.push({ name: 'employee.chat' })"
          >
            <AppIcon name="messageCircle" :size="20" :stroke-width="1.8" />
            <span v-if="chatUnreadLabel" class="employee-shell__chat-badge">{{ chatUnreadLabel }}</span>
          </button>
          <button
            v-if="!isProfileRoute"
            type="button"
            class="employee-shell__avatar employee-shell__avatar-btn"
            :class="{ 'employee-shell__avatar--home': isHomeHeader }"
            aria-label="Mở hồ sơ cá nhân"
            @click="goToProfile"
          >
            <img
              v-if="hasAvatarPhoto"
              class="employee-shell__avatar-img"
              :src="auth.user.avatar_url"
              alt=""
              @error="avatarBroken = true"
            />
            <span v-else class="employee-shell__avatar-initial">{{ avatarInitial }}</span>
          </button>
        </div>
      </div>

      <label v-if="route.name === 'dashboard.me'" class="employee-shell__search employee-shell__search--elevated">
        <span class="employee-shell__search-leading" aria-hidden="true">
          <AppIcon name="search" :size="18" class="employee-shell__search-icon" />
        </span>
        <input
          v-model="homeSearchQuery"
          type="search"
          class="employee-shell__search-input"
          placeholder="Tìm tiện ích nhanh..."
          autocomplete="off"
          enterkeyhint="search"
        />
      </label>
    </header>

    <div v-if="canInstall" class="employee-shell__install">
      <div class="install-banner">
        <span class="install-banner__icon" aria-hidden="true">
          <AppIcon name="download" :size="18" :stroke-width="1.9" />
        </span>
        <span class="install-banner__text">
          <span class="install-banner__title">Cài ứng dụng vào máy</span>
          <span class="install-banner__hint">Mở nhanh hơn, dùng như một app riêng</span>
        </span>
        <button type="button" class="install-banner__action" @click="promptInstall">Cài đặt</button>
        <button type="button" class="install-banner__close" aria-label="Đóng gợi ý cài đặt" @click="dismiss">
          <AppIcon name="close" :size="14" />
        </button>
      </div>
    </div>

    <main
      ref="contentRef"
      class="employee-shell__content employee-shell__content--unified"
      :class="{ 'employee-shell__content--chat': isChatRoute }"
    >
      <div
        v-if="route.name === 'dashboard.me' || route.name === 'employee.feed'"
        class="employee-shell__pull"
        :style="pullIndicatorStyle"
        aria-hidden="true"
      >
        <span
          class="employee-shell__pull-icon"
          :class="{ 'employee-shell__pull-icon--spin': pullRefreshing }"
        >
          <AppIcon name="refresh" :size="18" />
        </span>
        <span class="employee-shell__pull-label">
          {{ pullRefreshing ? 'Đang làm mới…' : 'Kéo để làm mới' }}
        </span>
      </div>
      <slot />
    </main>

    <nav
      v-if="!hideHeaderForChatThread"
      ref="tabbarRef"
      class="employee-tabbar employee-tabbar--shell"
      :class="isMobilePwa ? 'employee-tabbar--pwa' : 'employee-tabbar--browser'"
      aria-label="Điều hướng chính"
    >
      <div class="employee-tabbar__bar">
        <router-link
          v-for="tab in TABS"
          :key="tab.name"
          :to="{ name: tab.name }"
          class="employee-tabbar__item"
          :class="{ 'employee-tabbar__item--active': route.name === tab.name }"
        >
          <span class="employee-tabbar__icon">
            <AppIcon
              :name="tab.icon"
              :size="20"
              :stroke-width="1.9"
            />
          </span>
          <span class="employee-tabbar__label">{{ tab.label }}</span>
        </router-link>
      </div>
    </nav>
  </div>
</template>
