<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import SocialGroupFormModal from '../components/SocialGroupFormModal.vue';
import SocialGroupMembersPanel from '../components/SocialGroupMembersPanel.vue';
import SocialGroupRequestsPanel from '../components/SocialGroupRequestsPanel.vue';
import SocialPostCard from '../components/SocialPostCard.vue';
import SocialPostComposer from '../components/SocialPostComposer.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const groupId = computed(() => Number(route.params.id));
const group = ref(null);
const loadingGroup = ref(false);
const posts = ref([]);
const loadingPosts = ref(false);
const loadingMore = ref(false);
const page = ref(1);
const lastPage = ref(1);
const formOpen = ref(false);
const membersPanel = ref(null);
const requestsPanel = ref(null);

const canView = computed(() => group.value?.is_member || group.value?.visibility === 'public');

async function loadGroup() {
  loadingGroup.value = true;
  try {
    const { data } = await window.axios.get(`/api/social/groups/${groupId.value}`);
    group.value = data.group;
  } catch {
    showClientToast('error', 'Không tìm thấy nhóm.');
    router.push({ name: 'social.groups.index' });
  } finally {
    loadingGroup.value = false;
  }
}

async function loadPosts(targetPage = 1) {
  if (!canView.value) return;
  const isFirst = targetPage === 1;
  isFirst ? (loadingPosts.value = true) : (loadingMore.value = true);
  try {
    const { data } = await window.axios.get('/api/social/posts', {
      params: { page: targetPage, per_page: 10, post_scope: 'group', group_id: groupId.value },
    });
    posts.value = isFirst ? data.posts : [...posts.value, ...data.posts];
    page.value = data.current_page;
    lastPage.value = data.last_page;
  } catch {
    showClientToast('error', 'Không thể tải bài viết của nhóm.');
  } finally {
    loadingPosts.value = false;
    loadingMore.value = false;
  }
}

function loadMore() {
  if (page.value < lastPage.value && !loadingMore.value) {
    loadPosts(page.value + 1);
  }
}

async function joinGroup() {
  try {
    const { data } = await window.axios.post(`/api/social/groups/${groupId.value}/join`);
    group.value = data.group;
    if (data.status === 'joined') {
      showClientToast('success', 'Đã tham gia nhóm.');
      loadPosts(1);
    } else {
      showClientToast('success', 'Đã gửi yêu cầu tham gia, vui lòng chờ duyệt.');
    }
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không thể tham gia nhóm.');
  }
}

async function leaveGroup() {
  try {
    await window.axios.post(`/api/social/groups/${groupId.value}/leave`);
    showClientToast('success', 'Đã rời nhóm.');
    router.push({ name: 'social.groups.index' });
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không thể rời nhóm.');
  }
}

async function deleteGroup() {
  try {
    await window.axios.delete(`/api/social/groups/${groupId.value}`);
    showClientToast('success', 'Đã xoá nhóm.');
    router.push({ name: 'social.groups.index' });
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không thể xoá nhóm.');
  }
}

function onGroupSaved(updatedGroup) {
  group.value = updatedGroup;
  formOpen.value = false;
}

function onPosted(post) {
  posts.value = [post, ...posts.value];
}

function onUpdated(updatedPost) {
  posts.value = posts.value.map((p) => (p.id === updatedPost.id ? updatedPost : p));
}

function onDeleted(postId) {
  posts.value = posts.value.filter((p) => p.id !== postId);
}

function onMembersChanged() {
  loadGroup();
}

function onRequestApproved() {
  membersPanel.value?.load();
  loadGroup();
}

watch(groupId, () => {
  loadGroup().then(() => loadPosts(1));
});

onMounted(async () => {
  await loadGroup();
  await loadPosts(1);
});
</script>

<template>
  <section class="group-wall">
    <PageHeader
      :title="group?.name ?? 'Nhóm'"
      icon="users"
      :description="group?.description || ''"
    >
      <template #actions>
        <button
          v-if="group && !group.is_member"
          type="button"
          class="group-wall__header-btn group-wall__header-btn--primary"
          :disabled="group.visibility === 'private' && group.has_pending_request"
          @click="joinGroup"
        >
          {{ group.visibility === 'private' && group.has_pending_request ? 'Đã gửi yêu cầu' : (group.visibility === 'private' ? 'Yêu cầu tham gia' : 'Tham gia') }}
        </button>
        <button
          v-if="group?.can_manage"
          type="button"
          class="group-wall__header-btn"
          @click="formOpen = true"
        >
          Sửa nhóm
        </button>
        <button
          v-if="group?.is_member && group.my_role !== 'owner'"
          type="button"
          class="group-wall__header-btn"
          @click="leaveGroup"
        >
          Rời nhóm
        </button>
        <button
          v-if="group?.can_delete"
          type="button"
          class="group-wall__header-btn group-wall__header-btn--danger"
          @click="deleteGroup"
        >
          Xoá nhóm
        </button>
      </template>
    </PageHeader>

    <div v-if="loadingGroup" class="group-wall__loading">Đang tải...</div>

    <div v-else-if="!group" class="group-wall__empty">Không tìm thấy nhóm.</div>

    <div v-else-if="!canView" class="group-wall__locked">
      <AppIcon name="lock" :size="32" />
      <p class="group-wall__locked-title">Đây là nhóm bảo mật</p>
      <p class="group-wall__locked-desc">{{ group.description || 'Bạn cần tham gia nhóm để xem bài viết và thành viên.' }}</p>
      <button
        type="button"
        class="group-wall__header-btn group-wall__header-btn--primary"
        :disabled="group.has_pending_request"
        @click="joinGroup"
      >
        {{ group.has_pending_request ? 'Đã gửi yêu cầu' : 'Yêu cầu tham gia' }}
      </button>
    </div>

    <div v-else class="group-wall__body hide-scrollbar">
      <div class="group-wall__main hide-scrollbar">
        <p class="group-wall__meta">
          <span
            class="group-wall__dot"
            :class="group.visibility === 'private' ? 'group-wall__dot--private' : 'group-wall__dot--public'"
          />
          {{ group.visibility === 'private' ? 'Nhóm bảo mật' : 'Nhóm công khai' }}
          · {{ group.members_count }} thành viên
        </p>

        <SocialPostComposer
          :author-avatar-url="auth.user?.avatar_url"
          :author-name="auth.user?.name"
          default-scope="group"
          :group-id="groupId"
          @posted="onPosted"
        />

        <div v-if="loadingPosts" class="group-wall__loading">Đang tải bài viết...</div>

        <div v-else class="group-wall__list">
          <SocialPostCard
            v-for="post in posts"
            :key="post.id"
            :post="post"
            post-scope="group"
            @deleted="onDeleted"
            @updated="onUpdated"
          />

          <div v-if="posts.length === 0" class="group-wall__empty">
            Chưa có bài viết nào trong nhóm. Hãy là người đăng đầu tiên!
          </div>

          <button
            v-if="page < lastPage"
            type="button"
            class="group-wall__load-more"
            :disabled="loadingMore"
            @click="loadMore"
          >
            {{ loadingMore ? 'Đang tải...' : 'Xem thêm' }}
          </button>
        </div>
      </div>

      <aside class="group-wall__rail hide-scrollbar">
        <SocialGroupRequestsPanel
          v-if="group.can_manage"
          ref="requestsPanel"
          :group-id="groupId"
          @approved="onRequestApproved"
        />
        <SocialGroupMembersPanel
          ref="membersPanel"
          :group-id="groupId"
          :can-manage="group.can_manage"
          :my-role="group.my_role"
          @changed="onMembersChanged"
        />
      </aside>
    </div>

    <SocialGroupFormModal :open="formOpen" :group="group" @close="formOpen = false" @saved="onGroupSaved" />
  </section>
</template>

<style scoped>
.group-wall {
  height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-2);
  overflow: hidden;
}

.group-wall__header-btn {
  height: 2rem;
  padding: 0 0.875rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.group-wall__header-btn:hover:not(:disabled) {
  background: var(--color-surface);
}

.group-wall__header-btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
  box-shadow: none;
}

.group-wall__header-btn--primary:hover:not(:disabled) {
  filter: brightness(0.95);
}

.group-wall__header-btn--danger {
  color: var(--color-danger, #dc2626);
}

.group-wall__header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.group-wall__loading,
.group-wall__empty {
  text-align: center;
  color: var(--color-text-muted);
  padding: var(--space-6);
}

.group-wall__locked {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  text-align: center;
  color: var(--color-text-muted);
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: var(--space-8);
}

.group-wall__locked-title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-text);
}

.group-wall__locked-desc {
  margin: 0 0 var(--space-2);
  max-width: 24rem;
}

.group-wall__body {
  flex: 1;
  min-height: 0;
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--space-3);
  overflow: hidden;
}

.group-wall__main {
  min-width: 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  overflow-y: auto;
}

.group-wall__meta {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  margin: 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.group-wall__dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  flex-shrink: 0;
}

.group-wall__dot--public {
  background: var(--color-success);
}

.group-wall__dot--private {
  background: var(--color-warning);
}

.group-wall__list {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.group-wall__load-more {
  align-self: center;
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
  padding: var(--space-2) var(--space-4);
  border-radius: var(--radius-md);
  cursor: pointer;
  font-size: 0.875rem;
}

.group-wall__load-more:hover {
  background: var(--color-surface-muted);
}

.group-wall__load-more:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.group-wall__rail {
  min-width: 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  overflow-y: auto;
}

@media (min-width: 769px) {
  .group-wall__body {
    grid-template-columns: minmax(0, 1fr) 18rem;
  }
}
</style>
