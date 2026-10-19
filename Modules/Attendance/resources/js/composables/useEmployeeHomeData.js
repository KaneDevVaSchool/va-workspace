import { computed, ref } from 'vue';
import { showClientToast } from '@/lib/clientToast';
import { useDashboardQuickUtilities } from '@modules/Dashboard/resources/js/composables/useDashboardQuickUtilities';
import { unwrapOverview, asList } from '@modules/Dashboard/resources/js/utils/dashboardPayload';
import { formatSocialTime } from '@modules/Social/resources/js/lib/formatSocialTime.js';
import { postDisplayHeading } from '@modules/Social/resources/js/lib/postContentSnippet.js';

export const EMPLOYEE_HOME_TASK_LIMIT = 6;
export const EMPLOYEE_HOME_FEED_LIMIT = 3;
export const EMPLOYEE_HOME_UTILITIES_LIMIT = 8;

function taskSortRank(task) {
  if (task.status === 'completed') return 4;
  if (task.is_overdue) return 0;
  if (task.end_date && isSameCalendarDay(task.end_date, new Date())) return 1;
  if (task.status === 'in_progress') return 2;
  return 3;
}

function isSameCalendarDay(value, date) {
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return false;
  return d.getFullYear() === date.getFullYear()
    && d.getMonth() === date.getMonth()
    && d.getDate() === date.getDate();
}

export function useEmployeeHomeData() {
  const { utilities, loading: utilitiesLoading, load: loadUtilities, openUtility } = useDashboardQuickUtilities();

  const overview = ref(null);
  const overviewLoading = ref(true);
  const feedPosts = ref([]);
  const feedLoading = ref(true);
  const refreshing = ref(false);

  const tasks = computed(() => {
    const rows = asList(overview.value?.tasks).filter((task) => task.status !== 'cancelled');
    return [...rows].sort((a, b) => taskSortRank(a) - taskSortRank(b));
  });

  const visibleTasks = computed(() => tasks.value.slice(0, EMPLOYEE_HOME_TASK_LIMIT));

  const hiddenTaskCount = computed(() => Math.max(0, tasks.value.length - EMPLOYEE_HOME_TASK_LIMIT));

  const doneCount = computed(() => tasks.value.filter((task) => task.status === 'completed').length);

  const taskProgressPercent = computed(() => {
    if (!tasks.value.length) return 0;
    return Math.round((doneCount.value / tasks.value.length) * 100);
  });

  const overdueCount = computed(() => tasks.value.filter((t) => t.is_overdue && t.status !== 'completed').length);

  async function loadOverview({ silent = false } = {}) {
    if (!silent) overviewLoading.value = true;
    try {
      const { data } = await window.axios.get('/api/dashboard/me/overview');
      overview.value = unwrapOverview(data);
    } catch (error) {
      overview.value = null;
      if (!silent) {
        showClientToast('error', error?.response?.data?.message || 'Không tải được công việc được giao.');
      }
    } finally {
      if (!silent) overviewLoading.value = false;
    }
  }

  async function loadFeedPosts({ silent = false } = {}) {
    if (!silent) feedLoading.value = true;
    try {
      const { data } = await window.axios.get('/api/social/posts', {
        params: {
          page: 1,
          per_page: EMPLOYEE_HOME_FEED_LIMIT,
          scope: 'all',
          post_scope: 'company',
        },
      });
      feedPosts.value = Array.isArray(data?.posts) ? data.posts.slice(0, EMPLOYEE_HOME_FEED_LIMIT) : [];
    } catch (error) {
      feedPosts.value = [];
      if (!silent) {
        showClientToast('error', error?.response?.data?.message || 'Không tải được thông báo bảng tin.');
      }
    } finally {
      if (!silent) feedLoading.value = false;
    }
  }

  async function loadUtilitiesQuiet() {
    await loadUtilities();
  }

  async function refreshAll({ silent = false } = {}) {
    refreshing.value = true;
    try {
      await Promise.all([
        loadOverview({ silent }),
        loadFeedPosts({ silent }),
        loadUtilities(),
      ]);
    } finally {
      refreshing.value = false;
    }
  }

  function postTitle(post) {
    return postDisplayHeading(post);
  }

  function postMeta(post) {
    return formatSocialTime(post?.created_at);
  }

  function postAuthor(post) {
    if (post?.is_anonymous) {
      return post?.anonymous_name?.trim() || 'Ẩn danh';
    }
    return post?.author?.name?.trim() || 'Bảng tin công ty';
  }

  function isPostNew(post) {
    return Boolean(post) && !post.viewed && !post.can_edit;
  }

  return {
    utilities,
    utilitiesLoading,
    openUtility,
    overview,
    overviewLoading,
    feedPosts,
    feedLoading,
    refreshing,
    tasks,
    visibleTasks,
    hiddenTaskCount,
    doneCount,
    taskProgressPercent,
    overdueCount,
    loadOverview,
    loadFeedPosts,
    loadUtilities: loadUtilitiesQuiet,
    refreshAll,
    postTitle,
    postMeta,
    postAuthor,
    isPostNew,
    isSameCalendarDay,
    taskSortRank,
  };
}

export { isSameCalendarDay, taskSortRank };
