<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import PageHeader from '../components/PageHeader.vue';
import AppIcon from '../components/AppIcon.vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';

const router = useRouter();
const auth = useAuthStore();

const concepts = [
  { term: 'Menu', meaning: 'Thanh công cụ bên trái. Mỗi mục chỉ hiện khi vai trò của bạn được cấp quyền tương ứng.' },
  { term: 'Super Admin', meaning: 'Người quản trị cao nhất, vận hành toàn hệ thống: phân quyền, menu, phòng ban và nhật ký.' },
  { term: 'Admin', meaning: 'Người quản trị nghiệp vụ toàn trường. Không đổi cấu hình gốc của hệ thống.' },
  { term: 'Trưởng phòng', meaning: 'Người quản lý một phòng ban: dự án, công việc, đánh giá, báo cáo và thành viên phòng.' },
  { term: 'Nhân viên', meaning: 'Người thực hiện công việc được giao, xem bảng tin và gửi ghi nhận của chính mình.' },
  { term: 'Dự án', meaning: 'Một việc lớn có ngày bắt đầu và ngày kết thúc. Công việc con phải nằm trong khoảng ngày này.' },
  { term: 'Công việc', meaning: 'Việc cụ thể trong dự án, có người làm, ngày bắt đầu, ngày kết thúc và độ khó.' },
  { term: 'Tiêu chí', meaning: 'Thang đánh giá của phòng ban. Phải cài đặt tiêu chí trước khi chấm đánh giá.' },
];

const roleGuides = [
  {
    id: 'super_admin',
    title: 'Super Admin',
    summary: 'Bắt đầu bằng việc gán phòng ban, kiểm tra menu và phân quyền trước khi các phòng tự vận hành.',
    steps: [
      { label: 'Gán phòng ban cho tài khoản mới', to: { name: 'superadmin.workspace-config.unassigned' } },
      { label: 'Kiểm tra menu hệ thống', to: { name: 'superadmin.workspace-config.global-menu' } },
      { label: 'Xem ma trận phân quyền', to: { name: 'superadmin.permissions' } },
      { label: 'Theo dõi nhật ký hoạt động', to: { name: 'superadmin.activity' } },
    ],
  },
  {
    id: 'department_director',
    title: 'Trưởng phòng / Trưởng bộ phận',
    summary: 'Xem phòng ban của mình, tạo dự án trước, sau đó giao công việc và cài tiêu chí đánh giá.',
    steps: [
      { label: 'Xem tổng quan phòng ban', to: { name: 'dashboard.department' } },
      { label: 'Tạo hoặc mở dự án', to: { name: 'manager.project.index' } },
      { label: 'Giao công việc cho thành viên', to: { name: 'manager.project.tasks' } },
      { label: 'Cài tiêu chí phòng ban', to: { name: 'manager.workspace-config.hub' } },
    ],
  },
  {
    id: 'member',
    title: 'Nhân viên',
    summary: 'Xem việc được giao trước, cập nhật tiến độ, rồi đọc bảng tin và gửi ghi nhận khi cần.',
    steps: [
      { label: 'Xem tổng quan của tôi', to: { name: 'dashboard.me' } },
      { label: 'Mở công việc được giao', to: { name: 'manager.project.tasks' } },
      { label: 'Đọc bảng tin', to: { name: 'social.feed' } },
      { label: 'Xem ghi nhận của tôi', to: { name: 'feature-requests.mine' } },
    ],
  },
];

const activeRoleId = computed(() => {
  const role = auth.activeRole || auth.user?.roles?.[0] || '';
  if (role === 'super_admin' || role === 'admin') return 'super_admin';
  if (['department_director', 'deputy_department_director', 'department_head', 'team_lead'].includes(role)) {
    return 'department_director';
  }
  return 'member';
});

const orderedGuides = computed(() => {
  const current = roleGuides.find((guide) => guide.id === activeRoleId.value);
  return current ? [current, ...roleGuides.filter((guide) => guide.id !== current.id)] : roleGuides;
});

function open(to) {
  if (router.hasRoute(to.name)) router.push(to);
}
</script>

<template>
  <section class="guide-page">
    <PageHeader
      title="Hướng dẫn sử dụng"
      icon="gitBranch"
      :breadcrumbs="[{ label: 'Hướng dẫn sử dụng' }]"
    />

    <div class="guide-page__body">
      <section class="guide-card" aria-labelledby="guide-concepts">
        <h2 id="guide-concepts">Các khái niệm trong ứng dụng</h2>
        <dl>
          <div v-for="item in concepts" :key="item.term">
            <dt>{{ item.term }}</dt>
            <dd>{{ item.meaning }}</dd>
          </div>
        </dl>
      </section>

      <section
        v-for="guide in orderedGuides"
        :key="guide.id"
        class="guide-card"
        :class="{ 'guide-card--current': guide.id === activeRoleId }"
      >
        <p v-if="guide.id === activeRoleId" class="guide-card__badge">Vai trò của bạn</p>
        <h2>{{ guide.title }}</h2>
        <p>{{ guide.summary }}</p>
        <ol>
          <li v-for="step in guide.steps" :key="step.label">
            <button type="button" @click="open(step.to)">
              <AppIcon name="arrowRight" :size="16" />
              {{ step.label }}
            </button>
          </li>
        </ol>
      </section>
    </div>
  </section>
</template>

<style scoped>
.guide-page {
  display: flex;
  flex-direction: column;
  min-height: 100%;
}

.guide-page__body {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(18rem, 1fr));
  gap: var(--space-4);
  padding: var(--space-4);
}

.guide-card {
  padding: var(--space-4);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}

.guide-card--current {
  box-shadow: inset 4px 0 0 var(--color-primary);
}

.guide-card h2 {
  margin: 0 0 var(--space-2);
  font-size: 1.05rem;
}

.guide-card p,
.guide-card dd {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.guide-card__badge {
  margin-bottom: var(--space-2);
  color: var(--color-primary);
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
}

.guide-card dl,
.guide-card ol {
  display: grid;
  gap: var(--space-3);
  margin: var(--space-3) 0 0;
  padding: 0;
}

.guide-card dt {
  font-weight: 700;
}

.guide-card ol {
  list-style: none;
  counter-reset: step;
}

.guide-card li {
  counter-increment: step;
}

.guide-card button {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  padding: var(--space-2) var(--space-3);
  color: var(--color-text);
  font: inherit;
  text-align: left;
  background: var(--color-surface-muted);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  cursor: pointer;
}

.guide-card button::before {
  content: counter(step);
  color: var(--color-primary);
  font-weight: 700;
}
</style>
