<script setup>
//
// Danh sách đơn đã gửi (nghỉ phép + giải trình chấm công) — mock data, UI only.
// Trạng thái hiển thị bằng chữ thường + chấm màu (không badge/pill, mục 14
// CLAUDE.md). Tap vào 1 dòng mở bottom sheet chi tiết.
//
import { ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const STATUS_META = {
  pending: { label: 'Đang chờ duyệt', color: 'var(--color-warning)' },
  approved: { label: 'Đã duyệt', color: 'var(--color-success)' },
  rejected: { label: 'Từ chối', color: 'var(--color-primary)' },
};

const requests = [
  {
    id: 1,
    kind: 'Nghỉ phép năm',
    submittedAt: '08/10/2026',
    range: '12/10 - 13/10/2026',
    reason: 'Việc gia đình',
    status: 'pending',
    approver: 'Chưa có người duyệt',
  },
  {
    id: 2,
    kind: 'Giải trình chấm công',
    submittedAt: '05/10/2026',
    range: 'Thiếu chấm công ra ngày 04/10',
    reason: 'Quên bấm chấm công ra, đã ở lại làm đến 18:30',
    status: 'approved',
    approver: 'Trưởng phòng Công nghệ',
  },
  {
    id: 3,
    kind: 'Nghỉ ốm',
    submittedAt: '28/09/2026',
    range: '29/09/2026',
    reason: 'Sốt, có giấy khám bệnh',
    status: 'approved',
    approver: 'Trưởng phòng Công nghệ',
  },
  {
    id: 4,
    kind: 'Nghỉ việc riêng',
    submittedAt: '15/09/2026',
    range: '16/09/2026',
    reason: 'Giải quyết giấy tờ cá nhân',
    status: 'rejected',
    approver: 'Trưởng phòng Công nghệ',
  },
];

const selected = ref(null);

function openDetail(request) {
  selected.value = request;
}

function closeDetail() {
  selected.value = null;
}
</script>

<template>
  <div class="requests">
    <ul class="requests-list">
      <li v-for="req in requests" :key="req.id">
        <button type="button" class="request-row" @click="openDetail(req)">
          <span class="request-row__main">
            <span class="request-row__kind">{{ req.kind }}</span>
            <span class="request-row__range">{{ req.range }}</span>
          </span>
          <span class="request-row__status">
            <span class="request-row__dot" :style="{ background: STATUS_META[req.status].color }" aria-hidden="true"></span>
            {{ STATUS_META[req.status].label }}
          </span>
        </button>
      </li>
    </ul>

    <Teleport to="body">
      <div v-if="selected" class="sheet-overlay" @click.self="closeDetail">
        <div class="sheet" role="dialog" aria-modal="true" :aria-label="selected.kind">
          <div class="sheet__handle" aria-hidden="true"></div>

          <div class="sheet__header">
            <h2>{{ selected.kind }}</h2>
            <button type="button" class="sheet__close" aria-label="Đóng" @click="closeDetail">
              <AppIcon name="close" :size="18" />
            </button>
          </div>

          <div class="sheet__field">
            <span class="sheet__label">Trạng thái</span>
            <span class="sheet__value sheet__value--status">
              <span class="request-row__dot" :style="{ background: STATUS_META[selected.status].color }" aria-hidden="true"></span>
              {{ STATUS_META[selected.status].label }}
            </span>
          </div>
          <div class="sheet__field">
            <span class="sheet__label">Ngày gửi</span>
            <span class="sheet__value">{{ selected.submittedAt }}</span>
          </div>
          <div class="sheet__field">
            <span class="sheet__label">Thời gian</span>
            <span class="sheet__value">{{ selected.range }}</span>
          </div>
          <div class="sheet__field">
            <span class="sheet__label">Lý do</span>
            <span class="sheet__value">{{ selected.reason }}</span>
          </div>
          <div class="sheet__field">
            <span class="sheet__label">Người duyệt</span>
            <span class="sheet__value">{{ selected.approver }}</span>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.requests-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.request-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  width: 100%;
  padding: var(--space-3) var(--space-4);
  border: none;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  text-align: left;
  cursor: pointer;
  font-family: inherit;
}

.request-row__main {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.request-row__kind {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-text);
}

.request-row__range {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.request-row__status {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  font-size: 0.8125rem;
  color: var(--color-text);
}

.request-row__dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  flex-shrink: 0;
}

.sheet-overlay {
  position: fixed;
  inset: 0;
  z-index: 60;
  display: flex;
  align-items: flex-end;
  background: var(--color-sidebar-overlay);
}

.sheet {
  width: 100%;
  max-height: 80vh;
  overflow-y: auto;
  padding: var(--space-2) var(--space-4) var(--space-4);
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.sheet__handle {
  width: 2.5rem;
  height: 4px;
  margin: var(--space-2) auto var(--space-3);
  border-radius: var(--radius-full);
  background: var(--color-border-strong);
}

.sheet__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-3);
}

.sheet__header h2 {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 700;
  color: var(--color-text);
}

.sheet__close {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  cursor: pointer;
}

.sheet__field {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.sheet__field:last-child {
  box-shadow: none;
}

.sheet__label {
  flex-shrink: 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.sheet__value {
  text-align: right;
  font-size: 0.875rem;
  color: var(--color-text);
}

.sheet__value--status {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
}
</style>
