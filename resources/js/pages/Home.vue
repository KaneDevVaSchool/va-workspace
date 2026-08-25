<script setup>
import PageHeader from '../components/PageHeader.vue';
import DataStreamWidget from '../components/DataStreamWidget.vue';
import { useRouter } from 'vue-router';

const router = useRouter();

const tabs = [
  { id: 'work',      label: 'Công việc' },
  { id: 'project',   label: 'Dự án' },
  { id: 'workspace', label: 'Cấu hình' },
];

// ── Tab 1: Công việc ──────────────────────────────────────────────────────────
const workData = {
  title: 'Công việc',
  description: 'Giao việc, theo dõi tiến độ, chấm điểm và xem kết quả tổng hợp — tất cả trong một luồng liên tục.',
  canvasW: 1480,
  canvasH: 780,
  nodes: [
    { id: 'n1', type: 'icon', x: 640, y: 0, w: 124,
      icon: 'users', label: 'Người quản lý' },

    { id: 'n2', type: 'card', x: 784, y: 6, w: 176, compact: true,
      items: [{ icon: 'clipboardCheck', label: 'Xem đánh giá' }] },

    { id: 'n3', type: 'card', x: 0, y: 200, w: 320,
      icon: 'settings', title: 'Cài đặt công việc', teaser: 'Quản trị viên',
      items: [
        { icon: 'star',        label: 'Thang điểm chất lượng' },
        { icon: 'clock',       label: 'Quy định giờ công' },
        { icon: 'sliders',     label: 'Tiêu chí nghiệm thu' },
      ],
      button: { label: 'Cài đặt chấm điểm', to: { name: 'manager.workspace-config.hub' } } },

    { id: 'n4', type: 'card', x: 360, y: 140, w: 340,
      icon: 'users', title: 'Tạo mới công việc', teaser: 'Trưởng nhóm giao',
      items: [
        { icon: 'layoutList',  label: 'Tên & mô tả công việc' },
        { icon: 'clock',       label: 'Hạn hoàn thành' },
        { icon: 'users',       label: 'Người theo dõi' },
        { icon: 'userPlus',    label: 'Người thực hiện' },
      ],
      button: { label: 'Tạo công việc mới' } },

    { id: 'n5', type: 'card', x: 740, y: 140, w: 340,
      icon: 'pieChart', title: 'Báo cáo tiến độ', teaser: 'Người được giao',
      items: [
        { icon: 'trendingUp',  label: '% tiến độ đã xong' },
        { icon: 'star',        label: 'Điểm chất lượng' },
        { icon: 'clock',       label: 'Thời gian thực tế' },
        { icon: 'fileText',    label: 'Nhật ký ngày' },
      ],
      button: { label: 'Nộp báo cáo tiến độ' } },

    { id: 'n6', type: 'card', x: 1120, y: 140, w: 340, highlight: true,
      icon: 'barChart', title: 'Bảng tổng kết', teaser: 'Tất cả mọi người',
      items: [
        { icon: 'percent',     label: '% hoàn thành' },
        { icon: 'clock',       label: 'Tổng giờ đã làm' },
        { icon: 'star',        label: 'Điểm chất lượng trung bình' },
      ],
      button: { label: 'Xem bảng tổng kết' } },

    { id: 'n7', type: 'icon', x: 80, y: 500, w: 176,
      icon: 'users', label: 'Người thực hiện' },

    { id: 'n8', type: 'icon', x: 80, y: 616, w: 176,
      icon: 'users', label: 'Người giao việc' },

    { id: 'n9', type: 'card', x: 360, y: 450, w: 340,
      icon: 'settings', title: 'Việc thêm cần làm', teaser: 'Người thực hiện',
      items: [
        { icon: 'messageCircle', label: 'Trao đổi, bình luận' },
        { icon: 'paperclip',     label: 'Đính kèm tài liệu' },
        { icon: 'shield',        label: 'Báo vướng mắc' },
        { icon: 'pencil',        label: 'Cập nhật mô tả' },
        { icon: 'gitBranch',     label: 'Chuyển cho phòng khác' },
      ],
      button: { label: 'Việc của tôi' } },

    { id: 'n10', type: 'group', x: 740, y: 470, w: 400,
      label: 'XEM THÊM',
      children: [
        { id: 'n11', icon: 'barChart',   title: 'Điểm KPI',         teaser: 'Điểm tháng này của tôi' },
        { id: 'n12', icon: 'calendar',   title: 'Nhật ký ngày',     teaser: 'Ghi chép hàng ngày' },
        { id: 'n13', icon: 'trendingUp', title: 'Tổng kết tuần',    teaser: 'Kết quả 7 ngày qua' },
      ] },
  ],
  edges: [
    { id: 'e1', from: 'n1', to: 'n2' },
    { id: 'e2', from: 'n2', to: 'n5', fromAnchor: 'bottom', toAnchor: 'top' },
    { id: 'e3', from: 'n3', to: 'n4' },
    { id: 'e4', from: 'n4', to: 'n5' },
    { id: 'e5', from: 'n5', to: 'n6' },
    { id: 'e6', from: 'n7', to: 'n9' },
    { id: 'e7', from: 'n8', to: 'n9' },
    { id: 'e8', from: 'n6', to: 'n10', fromAnchor: 'bottom', toAnchor: 'top' },
    { id: 'e9', from: 'n9', to: 'n4',  fromAnchor: 'top',    toAnchor: 'bottom' },
  ],
};

// ── Tab 2: Dự án ──────────────────────────────────────────────────────────────
const projectData = {
  title: 'Dự án',
  description: 'Tạo dự án, phân công việc theo sơ đồ cây, theo dõi lịch biểu và xem kết quả — giao việc được cho cả phòng khác.',
  canvasW: 1480,
  canvasH: 780,
  nodes: [
    { id: 'n1', type: 'icon', x: 640, y: 0, w: 124,
      icon: 'target', label: 'Người phụ trách' },

    { id: 'n2', type: 'card', x: 784, y: 6, w: 196, compact: true,
      items: [{ icon: 'star', label: 'Theo dõi chỉ tiêu lớn' }] },

    { id: 'n3', type: 'card', x: 0, y: 200, w: 320,
      icon: 'settings', title: 'Thiết lập dự án', teaser: 'Quản trị viên',
      items: [
        { icon: 'sliders',    label: 'Cách tính % tiến độ' },
        { icon: 'calendar',   label: 'Cài sprint / giai đoạn' },
        { icon: 'users',      label: 'Vai trò trong dự án' },
      ],
      button: { label: 'Cài đặt dự án', to: { name: 'manager.workspace-config.hub' } } },

    { id: 'n4', type: 'card', x: 360, y: 140, w: 340,
      icon: 'layoutList', title: 'Tạo dự án mới', teaser: 'Người phụ trách',
      items: [
        { icon: 'star',       label: 'Gắn vào mục tiêu lớn hơn' },
        { icon: 'calendar',   label: 'Sprint / Giai đoạn' },
        { icon: 'users',      label: 'Thêm thành viên vào dự án' },
        { icon: 'clock',      label: 'Ngày bắt đầu & kết thúc' },
      ],
      button: { label: 'Tạo dự án mới' } },

    { id: 'n5', type: 'card', x: 740, y: 140, w: 340,
      icon: 'layers', title: 'Sơ đồ & Lịch biểu', teaser: 'Trưởng nhóm · Thành viên',
      items: [
        { icon: 'layoutList',  label: 'Việc con trong việc lớn' },
        { icon: 'calendar',    label: 'Kéo thả lịch biểu (Gantt)' },
        { icon: 'clock',       label: 'Ghi nhận giờ công' },
        { icon: 'gitBranch',   label: 'Chuyển việc cho phòng khác' },
      ],
      button: { label: 'Mở sơ đồ & lịch biểu' } },

    { id: 'n6', type: 'card', x: 1120, y: 140, w: 340, highlight: true,
      icon: 'pieChart', title: 'Báo cáo dự án', teaser: 'Tất cả thành viên',
      items: [
        { icon: 'percent',     label: '% công việc đã xong' },
        { icon: 'dollarSign',  label: 'Chi phí & Ngân sách' },
        { icon: 'trendingUp',  label: 'Ai đang làm tốt nhất' },
      ],
      button: { label: 'Xem báo cáo dự án' } },

    { id: 'n7', type: 'icon', x: 80, y: 500, w: 176,
      icon: 'users', label: 'Trưởng nhóm' },

    { id: 'n8', type: 'icon', x: 80, y: 616, w: 176,
      icon: 'users', label: 'Thành viên' },

    { id: 'n9', type: 'card', x: 360, y: 450, w: 340,
      icon: 'settings', title: 'Việc thêm cần làm', teaser: 'Thành viên dự án',
      items: [
        { icon: 'check',         label: 'Kiểm thử & đảm bảo chất lượng' },
        { icon: 'messageCircle', label: 'Trao đổi & báo vướng mắc' },
        { icon: 'fileDown',      label: 'Tài liệu dự án' },
        { icon: 'messageCircle', label: 'Phản hồi sau hoàn thành' },
        { icon: 'home',          label: 'Xem tất cả việc của tôi' },
      ],
      button: { label: 'Việc của tôi' } },

    { id: 'n10', type: 'group', x: 740, y: 470, w: 400,
      label: 'LIÊN KẾT',
      children: [
        { id: 'n11', icon: 'dollarSign', title: 'Chi phí dự án',     teaser: 'Tài chính & dòng tiền' },
        { id: 'n12', icon: 'fileDown',   title: 'Tài liệu đính kèm', teaser: 'File, biểu mẫu' },
        { id: 'n13', icon: 'layers',     title: 'Vật tư cần dùng',   teaser: 'Dự toán theo phòng ban' },
      ] },
  ],
  edges: [
    { id: 'e1', from: 'n1', to: 'n2' },
    { id: 'e2', from: 'n2', to: 'n5', fromAnchor: 'bottom', toAnchor: 'top' },
    { id: 'e3', from: 'n3', to: 'n4' },
    { id: 'e4', from: 'n4', to: 'n5' },
    { id: 'e5', from: 'n5', to: 'n6' },
    { id: 'e6', from: 'n7', to: 'n9' },
    { id: 'e7', from: 'n8', to: 'n9' },
    { id: 'e8', from: 'n6', to: 'n10', fromAnchor: 'bottom', toAnchor: 'top' },
    { id: 'e9', from: 'n9', to: 'n4',  fromAnchor: 'top',    toAnchor: 'bottom' },
  ],
};

// ── Tab 3: Cấu hình Workspace ─────────────────────────────────────────────────
const workspaceData = {
  title: 'Cấu hình Workspace',
  description: 'Thiết lập toàn bộ hệ thống — thêm người, phân quyền, bật/tắt tính năng từng phòng ban — không cần biết kỹ thuật.',
  canvasW: 1480,
  canvasH: 780,
  nodes: [
    { id: 'n1', type: 'icon', x: 640, y: 0, w: 124,
      icon: 'settings', label: 'Quản trị viên' },

    { id: 'n2', type: 'card', x: 784, y: 6, w: 176, compact: true,
      items: [{ icon: 'check', label: 'Duyệt thay đổi' }] },

    { id: 'n3', type: 'card', x: 0, y: 200, w: 320,
      icon: 'settings', title: 'Cài đặt chung', teaser: 'Quản trị viên',
      items: [
        { icon: 'star',        label: 'Tên & logo workspace' },
        { icon: 'link',        label: 'Địa chỉ truy cập' },
        { icon: 'calendar',    label: 'Múi giờ & ngôn ngữ' },
      ],
      button: { label: 'Vào cài đặt chung', to: { name: 'manager.workspace-config.hub' } } },

    { id: 'n4', type: 'card', x: 360, y: 140, w: 340,
      icon: 'users', title: 'Quản lý người dùng', teaser: 'Quản trị viên',
      items: [
        { icon: 'userPlus',    label: 'Thêm người mới' },
        { icon: 'building',    label: 'Gắn vào phòng ban' },
        { icon: 'shield',      label: 'Chọn vai trò phù hợp' },
        { icon: 'lock',        label: 'Khoá / xoá tài khoản' },
      ],
      button: { label: 'Danh sách người dùng', to: { name: 'manager.workspace-config.members' } } },

    { id: 'n5', type: 'card', x: 740, y: 140, w: 340,
      icon: 'layoutList', title: 'Menu & Màn hình', teaser: 'Quản trị viên · Trưởng phòng',
      items: [
        { icon: 'check',       label: 'Bật / tắt từng mục trong menu' },
        { icon: 'building',    label: 'Mỗi phòng thấy menu riêng' },
        { icon: 'sliders',     label: 'Thay đổi thứ tự hiển thị' },
      ],
      button: { label: 'Cài đặt menu', to: { name: 'manager.workspace-config.sidebar' } } },

    { id: 'n6', type: 'card', x: 1120, y: 140, w: 340, highlight: true,
      icon: 'shield', title: 'Nhật ký & Bảo mật', teaser: 'Quản trị viên',
      items: [
        { icon: 'search',      label: 'Ai làm gì, lúc nào' },
        { icon: 'lock',        label: 'Đăng nhập gần đây' },
        { icon: 'bell',        label: 'Cảnh báo bất thường' },
      ],
      button: { label: 'Xem nhật ký hoạt động', to: { name: 'superadmin.activity' } } },

    { id: 'n7', type: 'icon', x: 80, y: 500, w: 176,
      icon: 'users', label: 'Trưởng phòng' },

    { id: 'n8', type: 'icon', x: 80, y: 616, w: 176,
      icon: 'users', label: 'Nhân viên' },

    { id: 'n9', type: 'card', x: 360, y: 450, w: 340,
      icon: 'settings', title: 'Tuỳ chỉnh theo phòng ban', teaser: 'Trưởng phòng',
      items: [
        { icon: 'sliders',       label: 'Bật/tắt tính năng riêng phòng' },
        { icon: 'star',          label: 'Cài cách chấm điểm task' },
        { icon: 'barChart',      label: 'Đặt chỉ tiêu KPI tháng' },
        { icon: 'bell',          label: 'Cài thông báo nội bộ' },
        { icon: 'refresh',       label: 'Đặt lại về mặc định' },
      ],
      button: { label: 'Tuỳ chỉnh phòng ban', to: { name: 'manager.workspace-config.hub' } } },

    { id: 'n10', type: 'group', x: 740, y: 470, w: 400,
      label: 'CÔNG CỤ HỖ TRỢ',
      children: [
        { id: 'n11', icon: 'shield',   title: 'Phân quyền chi tiết',  teaser: 'Ai được làm gì' },
        { id: 'n12', icon: 'star',     title: 'Cài chấm điểm task',   teaser: 'Điểm tự động khi xong' },
        { id: 'n13', icon: 'barChart', title: 'KPI theo phòng ban',   teaser: 'Chỉ tiêu tháng này' },
      ] },
  ],
  edges: [
    { id: 'e1', from: 'n1', to: 'n2' },
    { id: 'e2', from: 'n2', to: 'n5', fromAnchor: 'bottom', toAnchor: 'top' },
    { id: 'e3', from: 'n3', to: 'n4' },
    { id: 'e4', from: 'n4', to: 'n5' },
    { id: 'e5', from: 'n5', to: 'n6' },
    { id: 'e6', from: 'n7', to: 'n9' },
    { id: 'e7', from: 'n8', to: 'n9' },
    { id: 'e8', from: 'n6', to: 'n10', fromAnchor: 'bottom', toAnchor: 'top' },
    { id: 'e9', from: 'n9', to: 'n4',  fromAnchor: 'top',    toAnchor: 'bottom' },
  ],
};

const tabData = { work: workData, project: projectData, workspace: workspaceData };

function handleBtnClick({ btn }) {
  if (btn?.to) router.push(btn.to);
}
</script>

<template>
  <section class="home">
    <PageHeader
      title="Tổng quan"
      icon="dashboard"
      description="VA Workspace — Nền tảng quản lý công việc, hiệu suất & KPI đa phòng ban."
      :breadcrumbs="[{ label: 'Tổng quan' }]"
    />
    <div class="home__content">
      <DataStreamWidget :tabs="tabs" :tab-data="tabData" @btn-click="handleBtnClick" />
    </div>
  </section>
</template>

<style scoped>
.home {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-2) var(--space-3) var(--space-3);
  overflow: hidden;
}

.home__content {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

.home__content :deep(.dsw) {
  flex: 1;
  min-height: 0;
}

@media (max-width: 768px) {
  .home { padding: var(--space-2); }
}
</style>
