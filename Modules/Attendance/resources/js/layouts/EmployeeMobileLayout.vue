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
import EmployeeChatHeaderToolbar from '@modules/Chat/resources/js/components/EmployeeChatHeaderToolbar.vue';
import { useChatRealtime } from '@/composables/useChatRealtime';
import { setAppBadgeCount } from '@/lib/appBadge';
import EmployeeMobileHeader from '../components/employee-mobile-shell/EmployeeMobileHeader.vue';
import EmployeeMobileTabbar from '../components/employee-mobile-shell/EmployeeMobileTabbar.vue';
import EmployeeMobileInstallBanner from '../components/employee-mobile-shell/EmployeeMobileInstallBanner.vue';
import EmployeeMobileOfflineStrip from '../components/employee-mobile-shell/EmployeeMobileOfflineStrip.vue';
import EmployeeMobileWorkspaceIntro from '../components/employee-mobile-shell/EmployeeMobileWorkspaceIntro.vue';
import EmployeeMobileBirthdayGreeting from '../components/employee-mobile-shell/EmployeeMobileBirthdayGreeting.vue';
import { useEmployeeBirthdayGreeting } from '../composables/useEmployeeBirthdayGreeting.js';
import { markWorkspaceIntroSeen } from '../lib/employeeMobileWorkspaceIntro';
import { EMPLOYEE_MOBILE_TABS } from '../constants/employeeMobileTabs.js';
import {
  EMPLOYEE_HISTORY_UTILITIES,
  isHistoryUtilityId,
} from '../constants/employeeHistoryUtilities.js';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const chatStore = useChatStore();
useChatRealtime();
const contentRef = ref(null);
const homeSearchQuery = ref('');
const feedSearchQuery = ref('');
let homeRefreshHandler = null;
let feedRefreshHandler = null;
let pageRefreshHandler = null;
const headerRefreshing = ref(false);
const chatListQuery = ref('');
const chatGroupOpen = ref(false);

provide('employeeHomeSearch', homeSearchQuery);
provide('employeeFeedSearch', feedSearchQuery);
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
provide('employeeChatListQuery', chatListQuery);
provide('employeeChatGroupOpen', chatGroupOpen);

/** Màn chào mascot chỉ ở boot HTML (mobile); overlay chỉ khi xem lại thủ công. */
const showWorkspaceIntro = ref(false);
const {
  people: birthdayPeople,
  visible: showBirthdayGreeting,
  hide: dismissBirthdayGreeting,
} = useEmployeeBirthdayGreeting();

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
const { canInstall, showIosGuide, promptInstall, dismiss } = usePwaInstall();
const { shellMode, isMobilePwa } = useEmployeeMobileShell();
const tabbarComponentRef = ref(null);
const tabbarNavEl = computed(() => tabbarComponentRef.value?.navEl ?? null);
useEmployeeTabbarDock(tabbarNavEl);
useEmployeeMobileViewport();

function onTabReselect() {
  const el = contentRef.value;
  if (!el) return;
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (el.scrollTop > 12) {
    el.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
    return;
  }
  onHeaderRefresh();
}

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

const isHomeRoute = computed(() => route.name === 'dashboard.me');
const isFeedRoute = computed(() => route.name === 'employee.feed');

const headerSearchQuery = computed({
  get() {
    return isFeedRoute.value ? feedSearchQuery.value : homeSearchQuery.value;
  },
  set(value) {
    if (isFeedRoute.value) {
      feedSearchQuery.value = value;
    } else {
      homeSearchQuery.value = value;
    }
  },
});

const headerSearchPlaceholder = computed(() =>
  isFeedRoute.value ? 'Tìm bài viết, đồng nghiệp...' : 'Tìm tiện ích nhanh...',
);

const historyRequestsSubView = computed(() => {
  if (route.name !== 'employee.requests') return '';
  const view = String(route.query.view || '');
  return isHistoryUtilityId(view) ? view : '';
});

const pageHeaderTitle = computed(() => {
  if (isHomeRoute.value) {
    const tab = EMPLOYEE_MOBILE_TABS.find((t) => t.name === 'dashboard.me');
    return tab?.label ?? 'Trang chủ';
  }
  if (historyRequestsSubView.value) {
    const item = EMPLOYEE_HISTORY_UTILITIES.find((u) => u.id === historyRequestsSubView.value);
    return item?.label ?? 'Lịch sử';
  }
  const title = route.meta?.title;
  return typeof title === 'string' ? title.trim() : '';
});

/** Trang con không nằm trong thanh tab: PWA không có nút Back của trình duyệt nên cần nút quay lại ở header. */
const SUBPAGE_ROUTE_NAMES = new Set(['employee.profile', 'employee.contacts', 'employee.utilities']);

const brandBack = computed(
  () => SUBPAGE_ROUTE_NAMES.has(route.name) || Boolean(historyRequestsSubView.value),
);

const isChatRoute = computed(() => route.name === 'employee.chat');
const hideHeaderForChatThread = computed(
  () => isChatRoute.value && chatStore.activeView === 'conversation',
);

const showChatListToolbar = computed(
  () => isChatRoute.value && chatStore.activeView === 'list',
);

/** Chỉ ẩn tab bar khi đang trong một hội thoại chat (màn full). Hồ sơ vẫn hiện menu dưới. */
const hideTabbar = computed(() => hideHeaderForChatThread.value);

const chatUnreadLabel = computed(() => {
  if (chatStore.unreadTotal <= 0) return '';
  return chatStore.unreadTotal > 99 ? '99+' : String(chatStore.unreadTotal);
});

// Số tin chưa đọc hiện trên icon app ở màn hình chính (shell mobile không có chuông thông báo như desktop).
watch(() => chatStore.unreadTotal, (count) => setAppBadgeCount(count), { immediate: true });

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

// Vùng nội dung giữ nguyên (không remount) giữa các trang nên vị trí cuộn của trang trước bị mang sang
// trang sau. Đổi trang → về đầu; (cùng path chỉ đổi query thì giữ nguyên vị trí).
watch(
  () => route.path,
  () => {
    contentRef.value?.scrollTo({ top: 0, behavior: 'auto' });
  },
  { flush: 'post' },
);

watch(
  () => route.name,
  (name) => {
    if (name !== 'dashboard.me') {
      homeSearchQuery.value = '';
    }
    if (name !== 'employee.feed') {
      feedSearchQuery.value = '';
    }
    if (name !== 'employee.chat') {
      chatListQuery.value = '';
      chatGroupOpen.value = false;
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
  if (historyRequestsSubView.value) {
    router.replace({ name: 'employee.requests' });
    return;
  }
  // `history.length` đếm cả các trang ngoài app (hoặc rỗng khi mở từ thông báo/shortcut) nên không tin được;
  // vue-router lưu trang trước đó trong history.state.back — null nghĩa là đây là trang đầu của phiên.
  if (window.history.state?.back) {
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

  <EmployeeMobileBirthdayGreeting
    v-if="showBirthdayGreeting && !showWorkspaceIntro"
    :people="birthdayPeople"
    @close="dismissBirthdayGreeting"
  />

  <div
    class="employee-shell"
    :class="[`employee-shell--${shellMode}`, 'employee-shell--unified']"
  >
    <EmployeeMobileHeader
      v-if="!hideHeaderForChatThread"
      :is-profile="isProfileRoute"
      :brand-back="brandBack"
      :greeting="greeting"
      :first-name="firstName"
      :display-name="displayName"
      :department-name="departmentName"
      :job-title-name="jobTitleName"
      :today-label="todayLabel"
      :page-title="pageHeaderTitle"
      :is-attendance-route="isAttendanceRoute"
      :hide-page-meta="isHomeRoute"
      :show-search="isHomeRoute || isFeedRoute"
      :search-placeholder="headerSearchPlaceholder"
      v-model:search-query="headerSearchQuery"
      :header-refreshing="headerRefreshing"
      :chat-unread-label="chatUnreadLabel"
      :chat-header-aria="chatHeaderAria"
      :hide-chat-shortcut="isChatRoute"
      :has-avatar-photo="hasAvatarPhoto"
      :avatar-initial="avatarInitial"
      :avatar-url="auth.user?.avatar_url"
      @back="goBackFromSubpage"
      @open-profile="goToProfile"
      @refresh="onHeaderRefresh"
      @open-chat="router.push({ name: 'employee.chat' })"
      @avatar-error="avatarBroken = true"
    >
      <template v-if="showChatListToolbar" #toolbar>
        <EmployeeChatHeaderToolbar
          v-model:list-query="chatListQuery"
          @create-group="chatGroupOpen = true"
        />
      </template>
    </EmployeeMobileHeader>

    <EmployeeMobileOfflineStrip />

    <EmployeeMobileInstallBanner
      v-if="canInstall"
      @install="promptInstall"
      @dismiss="dismiss"
    />
    <EmployeeMobileInstallBanner
      v-else-if="showIosGuide"
      ios
      @dismiss="dismiss"
    />

    <main
      ref="contentRef"
      class="employee-shell__content employee-shell__content--unified hide-scrollbar"
      :class="{
        'employee-shell__content--chat': isChatRoute,
        'employee-shell__content--chat-thread': hideHeaderForChatThread,
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
      :tabs="EMPLOYEE_MOBILE_TABS"
      :active-name="route.name"
      :shell-page-key="route.path"
      :is-mobile-pwa="isMobilePwa"
      @reselect="onTabReselect"
    />
  </div>
</template>
