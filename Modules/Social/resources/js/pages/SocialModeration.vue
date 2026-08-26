<script setup>
//
// Trang duyệt bài viết (toàn trường) — /manager/social/moderation.
// Hàng chờ bên trái, khung xem bài bên phải để Duyệt/Từ chối.
// Bất kỳ ai có quyền social.review đều vào được (route guard
// requiresPermission ở resources/js/router/index.js).
//
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import { showClientToast } from '@/lib/clientToast';
import SocialImageGrid from '../components/SocialImageGrid.vue';
import { sanitizeSocialHtml } from '../lib/sanitizeSocialHtml.js';
import { formatSocialTime } from '../lib/formatSocialTime.js';

const SCOPE_FILTERS = [
  { id: 'all', label: 'Tất cả' },
  { id: 'company', label: 'Bảng tin chung' },
  { id: 'department', label: 'Phòng ban' },
  { id: 'group', label: 'Nhóm' },
  { id: 'personal', label: 'Tường cá nhân' },
];

const posts = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const perPage = ref(20);
const loading = ref(false);
const selectedId = ref(null);
const acting = ref(false);
const rejecting = ref(false);
const rejectReason = ref('');
const search = ref('');
const scopeFilter = ref('all');
const rejectInput = ref(null);

const selected = computed(() => posts.value.find((post) => post.id === selectedId.value) || null);

const pageFrom = computed(() => {
  if (!posts.value.length) return 0;
  return (meta.value.current_page - 1) * perPage.value + 1;
});

const pageTo = computed(() => {
  if (!posts.value.length) return 0;
  return pageFrom.value + posts.value.length - 1;
});

const visiblePosts = computed(() => {
  const needle = search.value.trim().toLowerCase();
  const scope = scopeFilter.value;

  return posts.value.filter((post) => {
    if (scope !== 'all' && post.post_scope !== scope) return false;
    if (!needle) return true;
    const hay = [
      authorName(post),
      post.author?.department,
      excerpt(post.content, 400),
      postScopeLabel(post),
      post.poll?.title,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();
    return hay.includes(needle);
  });
});

const hasLocalFilter = computed(
  () => Boolean(search.value.trim()) || scopeFilter.value !== 'all',
);

function authorName(post) {
  if (!post) return '—';
  if (post.is_anonymous) return post.anonymous_name || 'Ẩn danh';
  return post.author?.name || '—';
}

function postScopeLabel(post) {
  if (!post) return '';
  if (post.post_scope === 'department') return `Tường phòng ban${post.department ? ` — ${post.department}` : ''}`;
  if (post.post_scope === 'personal') return `Tường cá nhân${post.wall_user ? ` — ${post.wall_user.name}` : ''}`;
  if (post.post_scope === 'group') return `Nhóm${post.group ? ` — ${post.group.name}` : ''}`;
  return 'Bảng tin chung';
}

function postScopeChip(post) {
  if (post.post_scope === 'department') return 'Phòng ban';
  if (post.post_scope === 'personal') return 'Cá nhân';
  if (post.post_scope === 'group') return 'Nhóm';
  return 'Chung';
}

function imageAttachments(post) {
  return (post?.attachments || []).filter((a) => a.type === 'image');
}

function fileAttachments(post) {
  return (post?.attachments || []).filter((a) => a.type !== 'image');
}

function excerpt(html, limit = 120) {
  const text = String(html || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
  return text.length > limit ? `${text.slice(0, limit)}…` : text || '(Không có nội dung chữ)';
}

function waitLabel(iso) {
  if (!iso) return '';
  const mins = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 60000));
  if (mins < 1) return 'Vừa xong';
  if (mins < 60) return `Chờ ${mins} phút`;
  const hours = Math.floor(mins / 60);
  if (hours < 24) return `Chờ ${hours} giờ`;
  const days = Math.floor(hours / 24);
  return `Chờ ${days} ngày`;
}

async function loadPending(page = 1) {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/social/moderation', {
      params: { page, per_page: perPage.value },
    });
    posts.value = data.posts ?? [];
    meta.value = {
      current_page: data.current_page ?? 1,
      last_page: data.last_page ?? 1,
      total: data.total ?? 0,
    };

    if (selectedId.value && !posts.value.some((post) => post.id === selectedId.value)) {
      selectedId.value = null;
    }
    if (!selectedId.value && posts.value.length > 0) {
      selectedId.value = posts.value[0].id;
    }
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được danh sách bài chờ duyệt.');
  } finally {
    loading.value = false;
  }
}

function goPage(page) {
  if (page < 1 || page > meta.value.last_page || page === meta.value.current_page) return;
  loadPending(page);
}

function onPerPage(value) {
  perPage.value = value;
  loadPending(1);
}

function select(post) {
  selectedId.value = post.id;
  rejecting.value = false;
  rejectReason.value = '';
}

function clearSelection() {
  selectedId.value = null;
  rejecting.value = false;
  rejectReason.value = '';
}

function removeFromList(postId) {
  posts.value = posts.value.filter((post) => post.id !== postId);
  meta.value.total = Math.max(0, meta.value.total - 1);
  if (selectedId.value === postId) {
    const next = visiblePosts.value[0] ?? posts.value[0];
    selectedId.value = next?.id ?? null;
  }
  if (posts.value.length === 0 && meta.value.current_page > 1) {
    loadPending(meta.value.current_page - 1);
  }
}

async function approve(post) {
  if (acting.value || !post) return;
  acting.value = true;
  try {
    await window.axios.post(`/api/social/moderation/${post.id}/approve`);
    showClientToast('success', 'Đã duyệt bài viết. Bài đã hiển thị công khai.');
    removeFromList(post.id);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không duyệt được bài viết.');
  } finally {
    acting.value = false;
  }
}

async function openReject() {
  rejecting.value = true;
  rejectReason.value = '';
  await nextTick();
  rejectInput.value?.focus();
}

function cancelReject() {
  rejecting.value = false;
  rejectReason.value = '';
}

async function confirmReject(post) {
  if (acting.value || !post) return;
  acting.value = true;
  try {
    await window.axios.post(`/api/social/moderation/${post.id}/reject`, {
      reason: rejectReason.value.trim() || undefined,
    });
    showClientToast('success', 'Đã từ chối bài viết.');
    rejecting.value = false;
    rejectReason.value = '';
    removeFromList(post.id);
  } catch (error) {
    const message = error?.response?.data?.message
      || Object.values(error?.response?.data?.errors || {})[0]?.[0];
    showClientToast('error', message || 'Không từ chối được bài viết.');
  } finally {
    acting.value = false;
  }
}

function isTypingTarget(target) {
  const tag = target?.tagName;
  return tag === 'INPUT' || tag === 'TEXTAREA' || target?.isContentEditable;
}

function moveSelection(delta) {
  const list = visiblePosts.value;
  if (!list.length) return;
  const index = list.findIndex((post) => post.id === selectedId.value);
  const next = index < 0 ? 0 : Math.min(Math.max(index + delta, 0), list.length - 1);
  select(list[next]);
}

function onKeydown(event) {
  if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.altKey) return;
  if (isTypingTarget(event.target) && event.key !== 'Escape') return;

  if (event.key === 'Escape') {
    if (rejecting.value) {
      cancelReject();
      event.preventDefault();
    }
    return;
  }

  if (acting.value) return;

  if (event.key === 'j' || event.key === 'J' || event.key === 'ArrowDown') {
    moveSelection(1);
    event.preventDefault();
    return;
  }
  if (event.key === 'k' || event.key === 'K' || event.key === 'ArrowUp') {
    moveSelection(-1);
    event.preventDefault();
    return;
  }
  if (!selected.value) return;
  if (event.key === 'a' || event.key === 'A') {
    if (!rejecting.value) approve(selected.value);
    event.preventDefault();
    return;
  }
  if (event.key === 'r' || event.key === 'R') {
    if (!rejecting.value) openReject();
    event.preventDefault();
  }
}

onMounted(() => {
  loadPending(1);
  window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
  <section class="moderation-page">
    <PageHeader
      title="Duyệt bài viết"
      icon="listChecks"
      description="Bài viết mới trên bảng tin nội bộ chỉ hiển thị công khai sau khi được duyệt ở đây."
    >
      <template #actions>
        <button type="button" class="moderation-page__header-btn" :disabled="loading" @click="loadPending(meta.current_page)">
          <AppIcon name="refresh" :size="16" :class="{ 'moderation-page__spin': loading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

    <div class="moderation-page__body">
      <div class="moderation-page__queue">
        <div class="moderation-page__queue-head">
          <div class="moderation-page__queue-title">
            <span>Hàng chờ duyệt</span>
            <span class="moderation-page__count">{{ meta.total }}</span>
          </div>
          <label class="moderation-page__search">
            <AppIcon name="search" :size="15" />
            <input
              v-model="search"
              type="search"
              placeholder="Tìm tác giả, nội dung…"
            />
          </label>
          <div class="moderation-page__chips hide-scrollbar" role="tablist" aria-label="Lọc theo nơi đăng">
            <button
              v-for="item in SCOPE_FILTERS"
              :key="item.id"
              type="button"
              role="tab"
              class="moderation-page__chip"
              :class="{ 'moderation-page__chip--on': scopeFilter === item.id }"
              :aria-selected="scopeFilter === item.id"
              @click="scopeFilter = item.id"
            >
              {{ item.label }}
            </button>
          </div>
        </div>

        <div class="moderation-page__list hide-scrollbar" role="listbox" aria-label="Bài viết chờ duyệt">
          <div v-if="loading && posts.length === 0" class="moderation-page__empty">
            <AppIcon name="refresh" :size="20" class="moderation-page__spin" />
            <p>Đang tải hàng chờ…</p>
          </div>
          <div v-else-if="posts.length === 0" class="moderation-page__empty">
            <AppIcon name="check" :size="22" />
            <p>Không có bài viết nào đang chờ duyệt.</p>
            <span>Bảng tin đang thông thoáng.</span>
          </div>
          <div v-else-if="visiblePosts.length === 0" class="moderation-page__empty">
            <AppIcon name="search" :size="20" />
            <p>Không khớp bộ lọc trên trang này.</p>
            <span v-if="hasLocalFilter">Thử xoá tìm kiếm hoặc chọn “Tất cả”.</span>
          </div>

          <button
            v-for="post in visiblePosts"
            :key="post.id"
            type="button"
            role="option"
            class="moderation-page__item"
            :class="{ 'moderation-page__item--active': selectedId === post.id }"
            :aria-selected="selectedId === post.id"
            @click="select(post)"
          >
            <UserAvatarTip v-if="!post.is_anonymous" :user="post.author" label="Tác giả" />
            <span v-else class="moderation-page__anon-avatar" aria-hidden="true">
              <AppIcon name="users" :size="16" />
            </span>
            <span class="moderation-page__item-body">
              <span class="moderation-page__item-top">
                <span class="moderation-page__item-author">{{ authorName(post) }}</span>
                <span class="moderation-page__item-wait">{{ waitLabel(post.created_at) }}</span>
              </span>
              <span class="moderation-page__item-excerpt">{{ excerpt(post.content) }}</span>
              <span class="moderation-page__item-meta">
                <span class="moderation-page__meta-text">{{ postScopeChip(post) }}</span>
                <span v-if="post.is_anonymous" class="moderation-page__meta-text moderation-page__meta-text--muted">Ẩn danh</span>
                <span v-if="imageAttachments(post).length" class="moderation-page__meta-icon">
                  <AppIcon name="camera" :size="12" />
                  {{ imageAttachments(post).length }}
                </span>
                <span v-if="fileAttachments(post).length" class="moderation-page__meta-icon">
                  <AppIcon name="paperclip" :size="12" />
                  {{ fileAttachments(post).length }}
                </span>
                <span v-if="post.poll" class="moderation-page__meta-icon">
                  <AppIcon name="barChart" :size="12" />
                  Bình chọn
                </span>
              </span>
            </span>
          </button>
        </div>

        <TablePagesBar
          placement="bottom"
          paging-only
          :from="pageFrom"
          :to="pageTo"
          :total="meta.total"
          :page="meta.current_page"
          :last-page="meta.last_page"
          :per-page="perPage"
          @update:page="goPage"
          @update:per-page="onPerPage"
        />
      </div>

      <aside class="moderation-page__canvas" aria-label="Chi tiết bài viết">
        <div v-if="selected" class="moderation-page__canvas-inner">
          <div class="moderation-page__canvas-head">
            <h2 class="moderation-page__canvas-title">Chi tiết bài viết</h2>
            <button type="button" class="moderation-page__icon-btn" aria-label="Đóng" @click="clearSelection">
              <AppIcon name="close" :size="16" />
            </button>
          </div>

          <div class="moderation-page__scroll hide-scrollbar">
            <div class="moderation-page__lead">
              <span class="moderation-page__dot" />
              <div>
                <span class="moderation-page__lead-kicker">Đang chờ duyệt</span>
                <p class="moderation-page__lead-desc">{{ waitLabel(selected.created_at) }} · {{ formatSocialTime(selected.created_at) }}</p>
              </div>
            </div>

            <div class="moderation-page__rows">
              <div class="moderation-page__row">
                <span class="moderation-page__row-label">Tác giả</span>
                <span class="moderation-page__row-value moderation-page__row-actor">
                  <UserAvatarTip v-if="!selected.is_anonymous" :user="selected.author" label="Tác giả" />
                  <span>{{ authorName(selected) }}</span>
                </span>
              </div>
              <div v-if="!selected.is_anonymous && selected.author?.department" class="moderation-page__row">
                <span class="moderation-page__row-label">Phòng ban</span>
                <span class="moderation-page__row-value">{{ selected.author.department }}</span>
              </div>
              <div class="moderation-page__row">
                <span class="moderation-page__row-label">Đăng lên</span>
                <span class="moderation-page__row-value">{{ postScopeLabel(selected) }}</span>
              </div>
              <div class="moderation-page__row">
                <span class="moderation-page__row-label">Thời gian đăng</span>
                <span class="moderation-page__row-value">{{ formatSocialTime(selected.created_at) }}</span>
              </div>
            </div>

            <article class="moderation-page__post">
              <div class="moderation-page__content" v-html="sanitizeSocialHtml(selected.content)" />

              <SocialImageGrid v-if="imageAttachments(selected).length" :images="imageAttachments(selected)" compact />

              <ul v-if="fileAttachments(selected).length" class="moderation-page__files">
                <li v-for="(file, index) in fileAttachments(selected)" :key="index">
                  <a :href="file.url" target="_blank" rel="noopener" class="moderation-page__file-link">
                    <AppIcon name="paperclip" :size="14" />
                    {{ file.name }}
                  </a>
                </li>
              </ul>

              <div v-if="selected.poll" class="moderation-page__poll">
                <p class="moderation-page__poll-title">Bình chọn: {{ selected.poll.title || '(không tiêu đề)' }}</p>
                <p v-for="option in selected.poll.options" :key="option.id" class="moderation-page__poll-option">
                  {{ option.label }}
                </p>
              </div>
            </article>
          </div>

          <div class="moderation-page__footer">
            <div v-if="!rejecting" class="moderation-page__actions">
              <p class="moderation-page__hint">J / K chọn bài · A duyệt · R từ chối</p>
              <div class="moderation-page__action-btns">
                <button type="button" class="moderation-page__btn moderation-page__btn--danger" :disabled="acting" @click="openReject">
                  <AppIcon name="close" :size="16" />
                  Từ chối
                </button>
                <button type="button" class="moderation-page__btn moderation-page__btn--primary" :disabled="acting" @click="approve(selected)">
                  <AppIcon name="check" :size="16" />
                  {{ acting ? 'Đang duyệt…' : 'Duyệt bài' }}
                </button>
              </div>
            </div>

            <div v-else class="moderation-page__reject">
              <label class="moderation-page__label" for="reject-reason">
                Lý do từ chối
                <span>Không bắt buộc — tác giả không thấy lý do này trên bảng tin</span>
              </label>
              <textarea
                id="reject-reason"
                ref="rejectInput"
                v-model="rejectReason"
                class="moderation-page__textarea"
                rows="3"
                maxlength="500"
                placeholder="Ví dụ: nội dung chưa phù hợp với quy định nội bộ…"
              />
              <p class="moderation-page__char">{{ rejectReason.length }}/500</p>
              <div class="moderation-page__action-btns">
                <button type="button" class="moderation-page__btn moderation-page__btn--ghost" :disabled="acting" @click="cancelReject">
                  Huỷ
                </button>
                <button type="button" class="moderation-page__btn moderation-page__btn--danger" :disabled="acting" @click="confirmReject(selected)">
                  {{ acting ? 'Đang từ chối…' : 'Xác nhận từ chối' }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="moderation-page__empty moderation-page__empty--canvas">
          <AppIcon name="listChecks" :size="28" />
          <p>{{ posts.length ? 'Chọn một bài viết bên trái để duyệt.' : 'Không có bài nào cần duyệt.' }}</p>
          <span>Bài cũ nhất được đưa lên trước để không bị tồn đọng.</span>
        </div>
      </aside>
    </div>
  </section>
</template>

<style scoped>
.moderation-page {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-3) var(--space-5) var(--space-5);
  overflow: hidden;
}

.moderation-page__header-btn {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  height: 2rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 500;
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  cursor: pointer;
}

.moderation-page__header-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.moderation-page__header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.moderation-page__spin {
  animation: moderation-spin 0.8s linear infinite;
}

@keyframes moderation-spin {
  to { transform: rotate(360deg); }
}

.moderation-page__body {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-4);
  overflow: hidden;
}

.moderation-page__queue {
  flex-shrink: 0;
  width: 30rem;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
}

.moderation-page__queue-head {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-3) var(--space-2);
  box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 75%, transparent);
}

.moderation-page__queue-title {
  display: flex;
  align-items: baseline;
  gap: 0.375rem;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 700;
}

.moderation-page__count {
  color: var(--color-warning-tint-fg);
  font-size: 0.8125rem;
  font-weight: 700;
}

.moderation-page__search {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: var(--space-2);
  height: 2.125rem;
  padding: 0 0.75rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.moderation-page__search:focus-within {
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1px var(--color-primary);
}

.moderation-page__search input {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  outline: none;
}

.moderation-page__chips {
  display: flex;
  flex-wrap: nowrap;
  gap: var(--space-1);
  overflow-x: auto;
  scroll-snap-type: x proximity;
}

.moderation-page__chip {
  flex-shrink: 0;
  scroll-snap-align: start;
  height: 1.625rem;
  padding: 0 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
}

.moderation-page__chip:hover {
  color: var(--color-text);
}

.moderation-page__chip--on {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.moderation-page__list {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-3);
}

.moderation-page__empty {
  margin: auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-6) var(--space-4);
  text-align: center;
  color: var(--color-text-muted);
}

.moderation-page__empty p {
  margin: 0;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
}

.moderation-page__empty span {
  font-size: 0.75rem;
}

.moderation-page__empty--canvas {
  height: 100%;
  justify-content: center;
}

.moderation-page__item {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-2));
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  text-align: left;
  cursor: pointer;
}

.moderation-page__item::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-warning);
}

.moderation-page__item:hover {
  background: var(--color-surface-muted);
}

.moderation-page__item--active {
  background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface));
}

.moderation-page__item--active::before {
  background: var(--color-primary);
}

.moderation-page__anon-avatar {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.moderation-page__item-body {
  min-width: 0;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.moderation-page__item-top {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
}

.moderation-page__item-author {
  min-width: 0;
  color: var(--color-text);
  font-weight: 600;
  font-size: 0.875rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.moderation-page__item-wait {
  flex-shrink: 0;
  color: var(--color-warning-tint-fg);
  font-size: 0.6875rem;
  font-weight: 600;
}

.moderation-page__item-excerpt {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  color: var(--color-text);
  font-size: 0.8125rem;
  line-height: 1.4;
}

.moderation-page__item-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem;
}

.moderation-page__meta-text {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  color: var(--color-info-tint-fg);
  font-size: 0.6875rem;
  font-weight: 600;
}

.moderation-page__meta-text::before {
  content: '';
  width: 0.375rem;
  height: 0.375rem;
  border-radius: var(--radius-full);
  background: currentColor;
}

.moderation-page__meta-text--muted {
  color: var(--color-text-muted);
}

.moderation-page__meta-icon {
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
}

.moderation-page__canvas {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--color-surface-muted);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
}

.moderation-page__canvas-inner {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

.moderation-page__canvas-head {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 75%, transparent);
}

.moderation-page__canvas-title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 700;
}

.moderation-page__icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.moderation-page__icon-btn:hover {
  background: var(--color-surface);
}

.moderation-page__scroll {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: var(--space-4);
}

.moderation-page__lead {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  margin: 0 0 var(--space-4);
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.moderation-page__lead::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-warning);
}

.moderation-page__dot {
  flex-shrink: 0;
  margin-top: 0.375rem;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-warning);
}

.moderation-page__lead-kicker {
  display: block;
  margin-bottom: var(--space-1);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.moderation-page__lead-desc {
  margin: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  line-height: 1.45;
}

.moderation-page__rows {
  display: flex;
  flex-direction: column;
  margin-bottom: var(--space-4);
}

.moderation-page__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.moderation-page__row:last-child {
  box-shadow: none;
}

.moderation-page__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.moderation-page__row-label::after {
  content: ':';
}

.moderation-page__row-value {
  color: var(--color-text);
  font-style: italic;
  font-weight: 400;
  text-align: right;
  overflow-wrap: anywhere;
}

.moderation-page__row-actor {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
  font-style: italic;
}

.moderation-page__post {
  padding: var(--space-4);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.moderation-page__content {
  color: var(--color-text);
  font-size: 0.9375rem;
  line-height: 1.6;
  overflow-wrap: anywhere;
}

.moderation-page__content :deep(p) {
  margin: 0 0 var(--space-2);
}

.moderation-page__content :deep(p:last-child) {
  margin-bottom: 0;
}

.moderation-page__files {
  list-style: none;
  margin: var(--space-3) 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.moderation-page__file-link {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  color: var(--color-primary);
  font-size: 0.8125rem;
  text-decoration: none;
}

.moderation-page__file-link:hover {
  text-decoration: underline;
}

.moderation-page__poll {
  margin-top: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.moderation-page__poll-title {
  margin: 0 0 var(--space-2);
  color: var(--color-text);
  font-weight: 600;
  font-size: 0.8125rem;
}

.moderation-page__poll-option {
  margin: 0;
  padding: var(--space-1) 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  box-shadow: 0 1px 0 var(--color-border);
}

.moderation-page__poll-option:last-child {
  box-shadow: none;
}

.moderation-page__footer {
  flex-shrink: 0;
  padding: var(--space-3) var(--space-4) var(--space-4);
  background: var(--color-surface);
  box-shadow: 0 -1px 0 color-mix(in srgb, var(--color-border) 75%, transparent);
}

.moderation-page__actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
}

.moderation-page__hint {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.moderation-page__action-btns {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  margin-left: auto;
}

.moderation-page__btn {
  height: 2.375rem;
  padding: 0 1rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.moderation-page__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.moderation-page__btn--primary {
  background: var(--color-success);
  color: var(--color-on-primary);
  box-shadow: none;
}

.moderation-page__btn--primary:hover:not(:disabled) {
  background: var(--color-success-tint-fg);
}

.moderation-page__btn--danger {
  color: var(--color-danger);
}

.moderation-page__btn--danger:hover:not(:disabled) {
  background: color-mix(in srgb, var(--color-danger) 10%, var(--color-surface));
}

.moderation-page__btn--ghost:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.moderation-page__reject {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.moderation-page__label {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
}

.moderation-page__label span {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 400;
}

.moderation-page__textarea {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  resize: vertical;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.moderation-page__textarea:focus {
  outline: none;
  box-shadow: inset 0 0 0 1px var(--color-primary);
}

.moderation-page__char {
  margin: 0;
  align-self: flex-end;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
}

@media (max-width: 1024px) {
  .moderation-page__body {
    flex-direction: column;
  }

  .moderation-page__queue {
    width: 100%;
    flex: 1;
    min-height: 0;
  }

  .moderation-page__canvas {
    width: 100%;
    max-height: 52%;
  }

  .moderation-page__hint {
    display: none;
  }
}

@media (max-width: 768px) {
  .moderation-page {
    padding: var(--space-4);
  }

  .moderation-page__queue {
    width: 100%;
  }
}

@media (max-width: 480px) {
  .moderation-page {
    padding: var(--space-3);
  }

  .moderation-page__actions {
    flex-direction: column;
    align-items: stretch;
  }

  .moderation-page__action-btns {
    width: 100%;
  }

  .moderation-page__btn {
    flex: 1;
  }
}

@media (prefers-reduced-motion: reduce) {
  .moderation-page__spin {
    animation: none;
  }
}
</style>
