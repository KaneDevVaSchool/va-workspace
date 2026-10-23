<script setup>
//
// Shell mobile dùng chung cho MỌI tài khoản trên viewport ≤768px — KHÔNG
// dùng AppSidebar/AppHeader. Điều phối state (route, auth, chat, viewport)
// và render các component con trong components/employee-mobile-shell/:
//   EmployeeMobileHeader.vue        — header (chào/tiêu đề trang)
//   EmployeeMobileTabbar.vue         — bottom tab bar
//   EmployeeMobileInstallBanner.vue  — gợi ý cài PWA
//   EmployeeMobileWorkspaceIntro.vue — màn giới thiệu lần đầu
//
// CSS khung + skin trong resources/css/employee-mobile-shell/: base.css,
//   browser.css, pwa.css (+ pwa-ios.css/pwa-android.css), theme.css, safe-area.css
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
import EmployeeMobileHeader from '../components/employee-mobile-shell/EmployeeMobileHeader.vue';
import EmployeeMobileTabbar from '../components/employee-mobile-shell/EmployeeMobileTabbar.vue';
import EmployeeMobileInstallBanner from '../components/employee-mobile-shell/EmployeeMobileInstallBanner.vue';
import EmployeeMobileWorkspaceIntro from '../components/employee-mobile-shell/EmployeeMobileWorkspaceIntro.vue';
import {
  hasSeenWorkspaceIntro,
  markWorkspaceIntroSeen,
} from '../lib/employeeMobileWorkspaceIntro';

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

const showWorkspaceIntro = ref(!hasSeenWorkspaceIntro());

function dismissWorkspaceIntro() {
  markWorkspaceIntroSeen();
  showWorkspaceIntro.value = false;
}

function replayWorkspaceIntro() {
  showWorkspaceIntro.value = true;
}

provide('employeeWorkspaceIntroReplay', replayWorkspaceIntro);

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
const tabbarComponentRef = ref(null);
const tabbarNavEl = computed(() => tabbarComponentRef.value?.navEl ?? null);
useEmployeeTabbarDock(tabbarNavEl);
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

/** Subpage full-screen (hồ sơ): không bottom nav — tránh lộ vùng đáy PWA iOS. */
const hideTabbar = computed(
  () => hideHeaderForChatThread.value || isProfileRoute.value,
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
const isAttendanceRoute = computed(() => route.name === 'employee.attendance');

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
  <EmployeeMobileWorkspaceIntro
    v-if="showWorkspaceIntro"
    @start="dismissWorkspaceIntro"
  />

  <div
    class="employee-shell"
    :class="[`employee-shell--${shellMode}`, 'employee-shell--unified']"
  >
    <EmployeeMobileHeader
      v-if="!hideHeaderForChatThread"
      :is-home="isHomeHeader"
      :is-profile="isProfileRoute"
      :greeting="greeting"
      :first-name="firstName"
      :display-name="displayName"
      :department-name="departmentName"
      :job-title-name="jobTitleName"
      :today-label="todayLabel"
      :page-title="pageHeaderTitle"
      :is-attendance-route="isAttendanceRoute"
      :show-search="route.name === 'dashboard.me'"
      v-model:search-query="homeSearchQuery"
      :header-refreshing="headerRefreshing"
      :chat-unread-label="chatUnreadLabel"
      :chat-header-aria="chatHeaderAria"
      :has-avatar-photo="hasAvatarPhoto"
      :avatar-initial="avatarInitial"
      :avatar-url="auth.user?.avatar_url"
      @back="goBackFromSubpage"
      @open-profile="goToProfile"
      @refresh="onHeaderRefresh"
      @open-chat="router.push({ name: 'employee.chat' })"
      @avatar-error="avatarBroken = true"
    />

    <EmployeeMobileInstallBanner
      v-if="canInstall"
      @install="promptInstall"
      @dismiss="dismiss"
    />

    <main
      ref="contentRef"
      class="employee-shell__content employee-shell__content--unified"
      :class="{
        'employee-shell__content--chat': isChatRoute,
        'employee-shell__content--attendance': isAttendanceRoute,
        'employee-shell__content--profile': isProfileRoute,
      }"
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

    <EmployeeMobileTabbar
      v-if="!hideTabbar"
      ref="tabbarComponentRef"
      :tabs="TABS"
      :active-name="route.name"
      :is-mobile-pwa="isMobilePwa"
    />
  </div>
</template>
