<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import SocialGroupFormModal from '../components/SocialGroupFormModal.vue';

const auth = useAuthStore();
const router = useRouter();

const tab = ref('mine');
const groups = ref([]);
const myRequests = ref([]);
const loading = ref(false);
const query = ref('');
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const perPage = ref(12);
const formOpen = ref(false);

const tabs = [
  { id: 'mine', label: 'Nhóm của tôi' },
  { id: 'discover', label: 'Khám phá nhóm' },
  { id: 'requests', label: 'Yêu cầu của tôi' },
];

async function loadGroups(targetPage = 1) {
  loading.value = true;
  try {
    if (tab.value === 'requests') {
      const { data } = await window.axios.get('/api/social/groups/mine/requests', {
        params: { page: targetPage, per_page: perPage.value },
      });
      myRequests.value = data.requests;
      page.value = data.current_page;
      lastPage.value = data.last_page;
      total.value = data.total;
      return;
    }

    const { data } = await window.axios.get('/api/social/groups', {
      params: { page: targetPage, per_page: perPage.value, tab: tab.value, q: query.value || undefined },
    });
    groups.value = data.groups;
    page.value = data.current_page;
    lastPage.value = data.last_page;
    total.value = data.total;
  } catch {
    showClientToast('error', 'Không thể tải danh sách nhóm.');
  } finally {
    loading.value = false;
  }
}

function setTab(id) {
  if (tab.value === id) return;
  tab.value = id;
  page.value = 1;
  loadGroups(1);
}

function openGroup(groupId) {
  router.push({ name: 'social.groups.show', params: { id: groupId } });
}

function actionLabel(group) {
  if (group.is_member) return 'Xem nhóm';
  if (group.visibility === 'private') {
    return group.has_pending_request ? 'Đã gửi yêu cầu' : 'Yêu cầu tham gia';
  }
  return 'Tham gia';
}

async function onGroupAction(group) {
  if (group.is_member) {
    openGroup(group.id);
    return;
  }
  if (group.visibility === 'private' && group.has_pending_request) return;

  try {
    const { data } = await window.axios.post(`/api/social/groups/${group.id}/join`);
    if (data.status === 'joined') {
      showClientToast('success', `Đã tham gia nhóm "${group.name}".`);
    } else {
      showClientToast('success', `Đã gửi yêu cầu tham gia nhóm "${group.name}".`);
    }
    groups.value = groups.value.map((g) => (g.id === group.id ? data.group : g));
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không thể thực hiện thao tác.');
  }
}

async function cancelRequest(request) {
  try {
    await window.axios.delete(`/api/social/groups/requests/${request.id}`);
    myRequests.value = myRequests.value.filter((r) => r.id !== request.id);
    showClientToast('success', 'Đã huỷ yêu cầu tham gia.');
  } catch {
    showClientToast('error', 'Không thể huỷ yêu cầu.');
  }
}

function onGroupSaved(group) {
  formOpen.value = false;
  router.push({ name: 'social.groups.show', params: { id: group.id } });
}

const emptyMessage = computed(() => {
  if (tab.value === 'requests') return 'Bạn chưa gửi yêu cầu tham gia nhóm nào.';
  if (tab.value === 'mine') return 'Bạn chưa tham gia nhóm nào. Hãy tạo nhóm mới hoặc khám phá nhóm công khai.';
  return 'Chưa có nhóm nào phù hợp.';
});

watch(query, () => {
  page.value = 1;
});

onMounted(() => loadGroups(1));
</script>

<template>
  <section class="groups-page">
    <PageHeader
      title="Nhóm"
      icon="users"
      description="Tạo nhóm bảo mật hoặc công khai, cùng trao đổi với đồng nghiệp"
      :primary-action="{ label: 'Tạo nhóm mới', icon: 'plus', onClick: () => (formOpen = true) }"
    />

    <div class="groups-page__body hide-scrollbar">
      <nav class="groups-page__tabs" aria-label="Chọn danh mục nhóm">
        <button
          v-for="item in tabs"
          :key="item.id"
          type="button"
          class="groups-page__tab"
          :class="{ 'groups-page__tab--active': tab === item.id }"
          :aria-current="tab === item.id ? 'page' : undefined"
          @click="setTab(item.id)"
        >
          {{ item.label }}
        </button>
      </nav>

      <TablePagesBar
        v-if="tab !== 'requests'"
        placement="top"
        :from="total ? (page - 1) * perPage + 1 : 0"
        :to="Math.min(page * perPage, total)"
        :total="total"
        :page="page"
        :last-page="lastPage"
        :per-page="perPage"
        show-search
        @search="loadGroups(1)"
        @update:page="loadGroups($event)"
        @update:per-page="perPage = $event; loadGroups(1)"
      >
        <template #filters>
          <label class="groups-page__search-field">
            <span>Tìm kiếm</span>
            <input
              v-model="query"
              type="search"
              placeholder="Tên nhóm..."
              @keydown.enter="loadGroups(1)"
            />
          </label>
        </template>
      </TablePagesBar>

      <div v-if="loading" class="groups-page__loading">Đang tải...</div>

      <template v-else-if="tab === 'requests'">
        <ul v-if="myRequests.length" class="groups-page__requests">
          <li v-for="request in myRequests" :key="request.id" class="groups-page__request">
            <div class="groups-page__request-info">
              <p class="groups-page__request-name">{{ request.group?.name }}</p>
              <p v-if="request.message" class="groups-page__request-message">{{ request.message }}</p>
            </div>
            <button type="button" class="groups-page__cancel-btn" @click="cancelRequest(request)">
              Huỷ yêu cầu
            </button>
          </li>
        </ul>
        <div v-else class="groups-page__empty">
          <AppIcon name="userPlus" :size="32" />
          <p>{{ emptyMessage }}</p>
        </div>
      </template>

      <template v-else>
        <div v-if="groups.length" class="groups-page__grid">
          <article v-for="group in groups" :key="group.id" class="group-card">
            <div class="group-card__cover">
              <img v-if="group.cover_url" :src="group.cover_url" alt="" />
              <AppIcon v-else name="users" :size="28" />
            </div>
            <div class="group-card__body">
              <button type="button" class="group-card__name" @click="openGroup(group.id)">
                {{ group.name }}
              </button>
              <p class="group-card__visibility">
                <span
                  class="group-card__dot"
                  :class="group.visibility === 'private' ? 'group-card__dot--private' : 'group-card__dot--public'"
                />
                {{ group.visibility === 'private' ? 'Nhóm bảo mật' : 'Nhóm công khai' }}
                · {{ group.members_count }} thành viên
              </p>
              <p v-if="group.description" class="group-card__desc">{{ group.description }}</p>
            </div>
            <button
              type="button"
              class="group-card__action"
              :class="{ 'group-card__action--muted': group.visibility === 'private' && group.has_pending_request }"
              :disabled="group.visibility === 'private' && group.has_pending_request"
              @click="onGroupAction(group)"
            >
              {{ actionLabel(group) }}
            </button>
          </article>
        </div>
        <div v-else class="groups-page__empty">
          <AppIcon name="users" :size="32" />
          <p>{{ emptyMessage }}</p>
        </div>
      </template>

      <TablePagesBar
        v-if="tab !== 'requests' && groups.length"
        placement="bottom"
        paging-only
        :from="total ? (page - 1) * perPage + 1 : 0"
        :to="Math.min(page * perPage, total)"
        :total="total"
        :page="page"
        :last-page="lastPage"
        :per-page="perPage"
        @update:page="loadGroups($event)"
        @update:per-page="perPage = $event; loadGroups(1)"
      />
    </div>

    <SocialGroupFormModal :open="formOpen" @close="formOpen = false" @saved="onGroupSaved" />
  </section>
</template>

<style scoped>
.groups-page {
  height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-2);
  overflow: hidden;
}

.groups-page__body {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  overflow-y: auto;
}

.groups-page__tabs {
  display: flex;
  gap: var(--space-1);
  flex-shrink: 0;
  padding: var(--space-1);
  background: var(--color-surface);
  border-radius: var(--radius-full);
  box-shadow: var(--shadow-sm);
  overflow-x: auto;
}

.groups-page__tab {
  flex: 1 0 auto;
  border: none;
  background: none;
  color: var(--color-text-muted);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-full);
  cursor: pointer;
  white-space: nowrap;
}

.groups-page__tab:hover {
  color: var(--color-primary);
  background: var(--color-primary-surface);
}

.groups-page__tab--active {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.groups-page__search-field {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.groups-page__search-field input {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 0.375rem 0.625rem;
  font-family: inherit;
  font-size: 0.8125rem;
  color: var(--color-text);
  background: var(--color-surface);
}

.groups-page__loading,
.groups-page__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-3);
  text-align: center;
  color: var(--color-text-muted);
  padding: var(--space-8) var(--space-6);
  background: var(--color-surface);
  border-radius: var(--radius-lg);
}

.groups-page__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr));
  gap: var(--space-3);
}

.group-card {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.group-card__cover {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 6rem;
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.group-card__cover img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.group-card__body {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  padding: 0 var(--space-3);
}

.group-card__name {
  border: none;
  background: none;
  padding: 0;
  text-align: left;
  font-family: inherit;
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
  cursor: pointer;
}

.group-card__name:hover {
  color: var(--color-primary);
}

.group-card__visibility {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  margin: 0;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.group-card__dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  flex-shrink: 0;
}

.group-card__dot--public {
  background: var(--color-success);
}

.group-card__dot--private {
  background: var(--color-warning);
}

.group-card__desc {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.group-card__action {
  margin: 0 var(--space-3) var(--space-3);
  height: 2rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.group-card__action:hover:not(:disabled) {
  filter: brightness(0.95);
}

.group-card__action--muted,
.group-card__action:disabled {
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  cursor: not-allowed;
}

.groups-page__requests {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.groups-page__request {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-3);
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
}

.groups-page__request-info {
  min-width: 0;
}

.groups-page__request-name {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--color-text);
}

.groups-page__request-message {
  margin: 2px 0 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.groups-page__cancel-btn {
  flex-shrink: 0;
  height: 2rem;
  padding: 0 0.875rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.groups-page__cancel-btn:hover {
  background: var(--color-surface);
}

@media (max-width: 480px) {
  .groups-page {
    padding: var(--space-2);
  }

  .groups-page__grid {
    grid-template-columns: 1fr;
  }
}
</style>
