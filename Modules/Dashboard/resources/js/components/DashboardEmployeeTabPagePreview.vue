<script setup>
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import EmployeeAttendance from '@modules/Attendance/resources/js/pages/EmployeeAttendance.vue';
import { EMPLOYEE_PWA_TAB_PREVIEW } from '../constants/employeePwaPreview.js';
import { EMPLOYEE_HISTORY_UTILITIES } from '@modules/Attendance/resources/js/constants/employeeHistoryUtilities.js';
import {
  PREVIEW_CHAT_CONVERSATIONS,
  PREVIEW_FEED_POSTS,
  PREVIEW_LEAVE_BALANCE,
} from '../constants/employeePwaPreviewFixtures.js';
import DashboardEmployeeShellPreview from './DashboardEmployeeShellPreview.vue';

const props = defineProps({
  mode: { type: String, required: true },
});

const tabMeta = computed(() => EMPLOYEE_PWA_TAB_PREVIEW[props.mode] ?? null);

function formatDays(value) {
  return Number(value).toLocaleString('vi-VN', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  });
}

const leaveBalance = PREVIEW_LEAVE_BALANCE;

const historyUtilities = EMPLOYEE_HISTORY_UTILITIES;
const feedPosts = PREVIEW_FEED_POSTS;
const chatRows = PREVIEW_CHAT_CONVERSATIONS;

function chatInitial(name) {
  return name?.trim().charAt(0).toUpperCase() || '?';
}
</script>

<template>
  <DashboardEmployeeShellPreview
    v-if="tabMeta"
    :active-route="tabMeta.activeRoute"
    :page-title="tabMeta.title"
  >
    <div v-if="mode === 'mobile-feed'" class="pwa-feed-preview">
      <div class="pwa-feed-preview__toolbar">
        <label class="pwa-feed-preview__search">
          <AppIcon name="search" :size="18" />
          <span>Tìm bài viết, đồng nghiệp...</span>
        </label>
        <span class="pwa-feed-preview__icon-btn" aria-hidden="true">
          <AppIcon name="users" :size="20" :stroke-width="1.8" />
        </span>
      </div>
      <div class="pwa-feed-preview__segments" role="tablist" aria-hidden="true">
        <span class="pwa-feed-preview__segment pwa-feed-preview__segment--active">Bảng tin</span>
        <span class="pwa-feed-preview__segment">Phòng Công nghệ</span>
        <span class="pwa-feed-preview__segment">Tường của tôi</span>
      </div>
      <div class="pwa-feed-preview__composer" aria-hidden="true">
        <span class="pwa-feed-preview__composer-avatar">N</span>
        <span class="pwa-feed-preview__composer-placeholder">Bạn đang nghĩ gì?</span>
      </div>
      <article
        v-for="post in feedPosts"
        :key="post.id"
        class="pwa-feed-preview__post"
        :class="{ 'pwa-feed-preview__post--pinned': post.pinned }"
      >
        <div class="pwa-feed-preview__post-head">
          <span class="pwa-feed-preview__post-avatar">
            {{ post.author.trim().charAt(0) }}
          </span>
          <div class="pwa-feed-preview__post-meta">
            <p class="pwa-feed-preview__post-author">
              {{ post.author }}
              <span v-if="post.pinned" class="pwa-feed-preview__pin">Ghim</span>
            </p>
            <p v-if="post.dept" class="pwa-feed-preview__post-dept">{{ post.dept }}</p>
            <p class="pwa-feed-preview__post-time">{{ post.time }}</p>
          </div>
        </div>
        <p class="pwa-feed-preview__post-body">{{ post.body }}</p>
        <div class="pwa-feed-preview__post-actions" aria-hidden="true">
          <span><AppIcon name="heart" :size="16" /> Thích</span>
          <span><AppIcon name="messageCircle" :size="16" /> Bình luận</span>
        </div>
      </article>
    </div>

    <div v-else-if="mode === 'mobile-attendance'" class="pwa-attendance-preview">
      <EmployeeAttendance />
    </div>

    <div v-else-if="mode === 'mobile-leave'" class="pwa-leave-preview">
      <section class="pwa-leave-preview__hero">
        <div class="pwa-leave-preview__hero-head">
          <span class="pwa-leave-preview__badge" aria-hidden="true">
            <AppIcon name="calendar" :size="22" :stroke-width="1.8" />
          </span>
          <p class="pwa-leave-preview__hero-label">Số ngày phép còn lại</p>
        </div>
        <p class="pwa-leave-preview__hero-value">
          <span class="pwa-leave-preview__number">{{ formatDays(leaveBalance.remaining) }}</span>
          <span class="pwa-leave-preview__unit">ngày</span>
        </p>
        <div class="pwa-leave-preview__split">
          <div>
            <p class="pwa-leave-preview__split-label">Đã sử dụng</p>
            <p class="pwa-leave-preview__split-value">{{ formatDays(leaveBalance.used) }} ngày</p>
          </div>
          <div>
            <p class="pwa-leave-preview__split-label">Phép thâm niên</p>
            <p class="pwa-leave-preview__split-value">{{ formatDays(leaveBalance.seniority) }} ngày</p>
          </div>
        </div>
      </section>

      <section class="pwa-leave-preview__panel">
        <h2 class="pwa-leave-preview__panel-title">Tổng quan phép năm</h2>
        <dl class="pwa-leave-preview__stats">
          <div>
            <dt>Tổng quỹ phát sinh</dt>
            <dd>{{ formatDays(leaveBalance.totalFund) }} ngày</dd>
          </div>
          <div>
            <dt>Còn lại</dt>
            <dd>{{ formatDays(leaveBalance.remaining) }} ngày</dd>
          </div>
          <div>
            <dt>Đang chờ duyệt</dt>
            <dd>{{ formatDays(leaveBalance.pending) }} ngày</dd>
          </div>
        </dl>
        <div class="pwa-leave-preview__progress" aria-hidden="true">
          <div class="pwa-leave-preview__progress-head">
            <span>Tiến độ sử dụng trong năm</span>
            <span>{{ formatDays(leaveBalance.usagePercent) }}%</span>
          </div>
          <div class="pwa-leave-preview__progress-track">
            <div
              class="pwa-leave-preview__progress-fill"
              :style="{ width: `${Math.min(100, leaveBalance.usagePercent)}%` }"
            />
          </div>
        </div>
      </section>

      <button type="button" class="pwa-leave-preview__create" tabindex="-1">
        <AppIcon name="plus" :size="20" :stroke-width="2" />
        <span>Tạo đơn xin nghỉ phép</span>
      </button>
    </div>

    <div v-else-if="mode === 'mobile-requests'" class="pwa-requests-preview">
      <ul class="pwa-requests-preview__grid">
        <li v-for="item in historyUtilities" :key="item.id">
          <div class="pwa-requests-preview__tile" :class="`pwa-requests-preview__tile--${item.tone}`">
            <span class="pwa-requests-preview__icon" :class="`pwa-requests-preview__icon--${item.tone}`">
              <AppIcon :name="item.icon" :size="20" :stroke-width="1.75" />
            </span>
            <span class="pwa-requests-preview__label">{{ item.label }}</span>
          </div>
        </li>
      </ul>
    </div>

    <div v-else-if="mode === 'mobile-chat'" class="pwa-chat-preview">
      <label class="pwa-chat-preview__search">
        <AppIcon name="search" :size="16" />
        <span>Tìm đồng nghiệp để nhắn tin</span>
      </label>
      <div class="pwa-chat-preview__list">
        <div
          v-for="row in chatRows"
          :key="row.id"
          class="pwa-chat-preview__item"
          :class="{ 'pwa-chat-preview__item--unread': row.unread > 0 }"
        >
          <span class="pwa-chat-preview__avatar">{{ chatInitial(row.name) }}</span>
          <span class="pwa-chat-preview__copy">
            <span class="pwa-chat-preview__row-top">
              <span class="pwa-chat-preview__name">{{ row.name }}</span>
              <span class="pwa-chat-preview__time">{{ row.time }}</span>
            </span>
            <span class="pwa-chat-preview__dept">{{ row.dept }}</span>
            <span class="pwa-chat-preview__excerpt">{{ row.excerpt }}</span>
          </span>
          <span v-if="row.unread > 0" class="pwa-chat-preview__unread">{{ row.unread }}</span>
        </div>
      </div>
    </div>
  </DashboardEmployeeShellPreview>
</template>

<style scoped>
.pwa-attendance-preview {
  padding: var(--space-4);
  pointer-events: none;
}

.pwa-feed-preview {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4) var(--space-6);
}

.pwa-feed-preview__toolbar {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.pwa-feed-preview__search {
  flex: 1;
  display: flex;
  align-items: center;
  gap: var(--space-2);
  height: 2.5rem;
  padding: 0 var(--space-3);
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.pwa-feed-preview__icon-btn {
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  color: var(--color-text-muted);
}

.pwa-feed-preview__segments {
  display: flex;
  gap: var(--space-2);
  overflow: hidden;
}

.pwa-feed-preview__segment {
  flex-shrink: 0;
  padding: 0.375rem 0.75rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.pwa-feed-preview__segment--active {
  background: var(--color-primary);
  color: #fff;
}

.pwa-feed-preview__composer {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.pwa-feed-preview__composer-avatar {
  display: grid;
  place-items: center;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-full);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 0.875rem;
  font-weight: 700;
}

.pwa-feed-preview__composer-placeholder {
  font-size: 0.875rem;
  color: var(--color-text-muted);
}

.pwa-feed-preview__post {
  padding: var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.pwa-feed-preview__post--pinned {
  box-shadow:
    inset 3px 0 0 var(--color-gold),
    var(--shadow-sm);
}

.pwa-feed-preview__post-head {
  display: flex;
  gap: var(--space-3);
  margin-bottom: var(--space-2);
}

.pwa-feed-preview__post-avatar {
  display: grid;
  place-items: center;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-full);
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
  font-weight: 700;
}

.pwa-feed-preview__post-author {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.pwa-feed-preview__pin {
  padding: 0.1rem 0.4rem;
  border-radius: var(--radius-full);
  background: var(--color-gold-surface);
  color: var(--color-gold);
  font-size: 0.625rem;
  font-weight: 700;
  text-transform: uppercase;
}

.pwa-feed-preview__post-dept,
.pwa-feed-preview__post-time {
  margin: 0;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.pwa-feed-preview__post-body {
  margin: 0 0 var(--space-2);
  font-size: 0.875rem;
  line-height: 1.45;
  color: var(--color-text);
}

.pwa-feed-preview__post-actions {
  display: flex;
  gap: var(--space-4);
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.pwa-feed-preview__post-actions span {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}

.pwa-leave-preview {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  padding: var(--space-4);
}

.pwa-leave-preview__hero {
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
  color: #fff;
}

.pwa-leave-preview__hero-head {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.pwa-leave-preview__badge {
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-md);
  background: rgba(255, 255, 255, 0.18);
}

.pwa-leave-preview__hero-label {
  margin: 0;
  font-size: 0.8125rem;
  opacity: 0.92;
}

.pwa-leave-preview__hero-value {
  margin: var(--space-3) 0;
  display: flex;
  align-items: baseline;
  gap: var(--space-2);
}

.pwa-leave-preview__number {
  font-size: 2.25rem;
  font-weight: 800;
  line-height: 1;
}

.pwa-leave-preview__unit {
  font-size: 1rem;
  font-weight: 600;
  opacity: 0.9;
}

.pwa-leave-preview__split {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-3);
}

.pwa-leave-preview__split-label {
  margin: 0;
  font-size: 0.6875rem;
  opacity: 0.85;
}

.pwa-leave-preview__split-value {
  margin: 0.15rem 0 0;
  font-size: 0.9375rem;
  font-weight: 700;
}

.pwa-leave-preview__panel {
  padding: var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.pwa-leave-preview__panel-title {
  margin: 0 0 var(--space-3);
  font-size: 0.9375rem;
  font-weight: 700;
}

.pwa-leave-preview__stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-2);
  margin: 0 0 var(--space-3);
}

.pwa-leave-preview__stats dt {
  margin: 0;
  font-size: 0.6875rem;
  color: var(--color-text-muted);
}

.pwa-leave-preview__stats dd {
  margin: 0.15rem 0 0;
  font-size: 0.8125rem;
  font-weight: 700;
}

.pwa-leave-preview__progress-head {
  display: flex;
  justify-content: space-between;
  font-size: 0.75rem;
  color: var(--color-text-muted);
  margin-bottom: var(--space-2);
}

.pwa-leave-preview__progress-track {
  height: 0.375rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  overflow: hidden;
}

.pwa-leave-preview__progress-fill {
  height: 100%;
  border-radius: var(--radius-full);
  background: var(--color-primary);
}

.pwa-leave-preview__create {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  width: 100%;
  height: 2.75rem;
  border: none;
  border-radius: var(--radius-lg);
  background: var(--color-primary);
  color: #fff;
  font-family: inherit;
  font-size: 0.9375rem;
  font-weight: 700;
  pointer-events: none;
}

.pwa-requests-preview {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4);
}

.pwa-requests-preview__grid {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.pwa-requests-preview__tile {
  position: relative;
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: var(--space-2);
  min-height: 3.25rem;
  padding: var(--space-3) var(--space-3) var(--space-3)
    calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.pwa-requests-preview__tile::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.pwa-requests-preview__tile--primary::before {
  background: var(--color-primary);
}

.pwa-requests-preview__tile--tertiary::before {
  background: var(--color-tertiary);
}

.pwa-requests-preview__icon {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-md);
}

.pwa-requests-preview__icon--primary {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.pwa-requests-preview__icon--tertiary {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.pwa-requests-preview__label {
  flex: 1;
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.3;
  color: var(--color-text);
}

.pwa-chat-preview {
  display: flex;
  flex-direction: column;
  min-height: 12rem;
  margin: calc(-1 * var(--space-3));
  background: var(--color-surface);
}

.pwa-chat-preview__search {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin: var(--space-3);
  padding: 0 var(--space-3);
  height: 2.25rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.pwa-chat-preview__list {
  flex: 1;
  border-top: 1px solid var(--color-border);
}

.pwa-chat-preview__item {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  padding: 0.75rem var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
}

.pwa-chat-preview__item--unread {
  background: var(--color-primary-surface);
}

.pwa-chat-preview__avatar {
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-full);
  background: var(--color-secondary-surface);
  color: var(--color-secondary);
  font-weight: 700;
  flex-shrink: 0;
}

.pwa-chat-preview__copy {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.pwa-chat-preview__row-top {
  display: flex;
  justify-content: space-between;
  gap: var(--space-2);
}

.pwa-chat-preview__name {
  font-size: 0.875rem;
  font-weight: 700;
}

.pwa-chat-preview__time {
  font-size: 0.6875rem;
  color: var(--color-text-muted);
}

.pwa-chat-preview__dept {
  font-size: 0.6875rem;
  color: var(--color-text-muted);
}

.pwa-chat-preview__excerpt {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pwa-chat-preview__unread {
  flex-shrink: 0;
  min-width: 1.25rem;
  height: 1.25rem;
  padding: 0 0.35rem;
  border-radius: var(--radius-full);
  background: var(--color-primary);
  color: #fff;
  font-size: 0.6875rem;
  font-weight: 700;
  display: grid;
  place-items: center;
}
</style>
