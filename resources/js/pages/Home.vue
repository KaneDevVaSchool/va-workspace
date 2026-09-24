<script setup>
import { computed, ref, watch } from 'vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import PageHeader from '../components/PageHeader.vue';
import AppIcon from '../components/AppIcon.vue';

const auth = useAuthStore();

const tabs = [
    { id: 'super_admin', label: 'Super Admin' },
    { id: 'admin', label: 'Admin' },
    { id: 'department_director', label: 'Trưởng phòng ban' },
    { id: 'member', label: 'Nhân viên' },
];

const ROLE_TAB = {
    super_admin: 'super_admin',
    admin: 'admin',
    department_director: 'department_director',
    deputy_department_director: 'department_director',
};

const flows = {
    super_admin: {
        title: 'Super Admin',
        lead: 'Cấp quyền, cấu hình hệ thống, rồi giám sát. Việc hàng ngày do Admin và trưởng phòng đảm nhận.',
        steps: [
            {
                icon: 'shield',
                title: 'Ma trận phân quyền',
                teaser: 'Việc chỉ Super Admin làm được',
                items: ['Gán vai trò cho từng người', 'Bật hoặc tắt quyền theo module', 'Khoá quyền nhạy cảm'],
                button: { label: 'Vào ma trận phân quyền', to: { name: 'superadmin.permissions' } },
            },
            {
                icon: 'building',
                title: 'Cấu hình từng phòng ban',
                teaser: 'Thiết lập ban đầu',
                items: ['Bật hoặc tắt menu riêng từng phòng', 'Xem thành viên mỗi phòng', 'Xem cách chấm điểm mỗi phòng'],
                button: { label: 'Cấu hình Workspace', to: { name: 'superadmin.workspace-config.overview' } },
            },
            {
                icon: 'layoutList',
                title: 'Menu toàn hệ thống',
                teaser: 'Áp dụng cho mọi phòng ban',
                items: ['Ẩn hoặc hiện mục menu', 'Đặt mặc định trước khi tuỳ chỉnh riêng'],
                button: { label: 'Ẩn hoặc hiện menu', to: { name: 'superadmin.workspace-config.global-menu' } },
            },
            {
                icon: 'search',
                title: 'Nhật ký hoạt động',
                teaser: 'Giám sát toàn hệ thống',
                result: true,
                items: ['Ai làm gì, lúc nào', 'Phát hiện thao tác bất thường'],
                button: { label: 'Xem nhật ký', to: { name: 'superadmin.activity' } },
            },
        ],
        aside: {
            title: 'Phần việc để lại cho người khác',
            teaser: 'Admin điều hành nghiệp vụ. Trưởng phòng tự quản phòng mình.',
            items: [
                { icon: 'settings', label: 'Admin điều hành nghiệp vụ hàng ngày' },
                { icon: 'building', label: 'Trưởng phòng tự quản lý phòng mình' },
                { icon: 'eye', label: 'Super Admin giám sát và cấp quyền' },
            ],
        },
        more: [
            { icon: 'shield', title: 'Vai trò trong hệ thống', teaser: 'Từ Super Admin đến Nhân viên' },
            { icon: 'building', title: 'Tổng hợp mọi phòng ban', teaser: 'Tình hình chung' },
            { icon: 'lock', title: 'Tài khoản dùng chung', teaser: 'Mật khẩu, tài khoản hạ tầng' },
        ],
    },
    admin: {
        title: 'Admin',
        lead: 'Điều hành nghiệp vụ toàn trường: thêm người, mở phòng ban, theo dõi việc và xem báo cáo.',
        steps: [
            {
                icon: 'users',
                title: 'Quản lý người dùng',
                teaser: 'Toàn hệ thống',
                items: ['Thêm người mới', 'Gắn vào phòng ban', 'Chọn vai trò phù hợp', 'Khoá hoặc xoá tài khoản'],
                button: { label: 'Danh sách người dùng', to: { name: 'manager.workspace-config.members' } },
            },
            {
                icon: 'building',
                title: 'Mở và cấu hình phòng ban',
                teaser: 'Thiết lập ban đầu',
                items: ['Cách chấm điểm của phòng', 'Menu riêng của phòng', 'Bổ nhiệm trưởng phòng'],
                button: { label: 'Cấu hình phòng ban', to: { name: 'manager.workspace-config.hub' } },
            },
            {
                icon: 'layers',
                title: 'Theo dõi dự án và công việc',
                teaser: 'Xuyên suốt các phòng ban',
                items: ['Tiến độ mọi dự án', 'Việc chuyển giao giữa phòng', 'Điểm KPI từng phòng'],
                button: { label: 'Xem dự án và công việc', to: { name: 'manager.project.index' } },
            },
            {
                icon: 'barChart',
                title: 'Báo cáo tổng hợp',
                teaser: 'Toàn hệ thống',
                result: true,
                items: ['Tỷ lệ hoàn thành theo phòng', 'Điểm chất lượng trung bình', 'Xu hướng hiệu suất'],
                button: { label: 'Xem báo cáo', to: { name: 'manager.reports.index' } },
            },
        ],
        aside: {
            title: 'Khi phát sinh',
            teaser: 'Admin xử lý thêm các việc này.',
            items: [
                { icon: 'shield', label: 'Xử lý bài đăng vi phạm' },
                { icon: 'lock', label: 'Cấp hoặc thu hồi tài khoản dùng chung' },
                { icon: 'messageCircle', label: 'Hỗ trợ trưởng phòng khi vướng' },
            ],
        },
        more: [
            { icon: 'lock', title: 'Tài khoản dùng chung', teaser: 'Mật khẩu hạ tầng' },
            { icon: 'megaphone', title: 'Duyệt bài viết', teaser: 'Bảng tin nội bộ' },
            { icon: 'activity', title: 'Nhật ký hoạt động', teaser: 'Thao tác gần đây' },
        ],
    },
    department_director: {
        title: 'Trưởng phòng ban',
        lead: 'Nhận việc, giao cho người trong phòng, chấm điểm, rồi gửi báo cáo lên trên.',
        steps: [
            {
                icon: 'settings',
                title: 'Thiết lập phòng ban',
                teaser: 'Trước khi giao việc',
                items: ['Tiêu chí đánh giá riêng', 'Bật hoặc tắt tính năng của phòng', 'Phó phòng, trưởng nhóm hỗ trợ'],
                button: { label: 'Cấu hình phòng ban', to: { name: 'manager.workspace-config.hub' } },
            },
            {
                icon: 'userPlus',
                title: 'Tạo và phân công',
                teaser: 'Cho nhân viên trong phòng',
                items: ['Tạo dự án hoặc công việc', 'Giao cho từng người', 'Đặt hạn hoàn thành', 'Chuyển việc sang phòng khác'],
                button: { label: 'Tạo công việc mới', to: { name: 'manager.project.tasks.create' } },
            },
            {
                icon: 'clipboardCheck',
                title: 'Theo dõi và chấm điểm',
                teaser: 'Việc của cả phòng',
                items: ['Tiến độ từng người', 'Chấm điểm chất lượng', 'Duyệt nhật ký ngày'],
                button: { label: 'Xem công việc phòng', to: { name: 'manager.project.index' } },
            },
            {
                icon: 'barChart',
                title: 'Báo cáo phòng ban',
                teaser: 'Gửi lên cấp trên',
                result: true,
                items: ['Tỷ lệ hoàn thành cả phòng', 'Điểm KPI trung bình', 'Chọn ai được xem báo cáo'],
                button: { label: 'Xem báo cáo', to: { name: 'manager.reports.index' } },
            },
        ],
        aside: {
            title: 'Khi phát sinh',
            teaser: 'Xử lý thêm trước khi tổng kết.',
            items: [
                { icon: 'messageCircle', label: 'Trao đổi, xử lý vướng mắc' },
                { icon: 'gitBranch', label: 'Nhận hoặc từ chối việc phòng khác chuyển tới' },
                { icon: 'clipboardCheck', label: 'Xác nhận kết quả trước khi tổng kết' },
            ],
        },
        more: [
            { icon: 'barChart', title: 'KPI phòng ban', teaser: 'Chỉ tiêu tháng này' },
            { icon: 'layers', title: 'Sơ đồ và lịch dự án', teaser: 'Theo tiến độ' },
            { icon: 'lock', title: 'Tài khoản dùng chung', teaser: 'Trong phạm vi phòng' },
        ],
    },
    member: {
        title: 'Nhân viên',
        lead: 'Nhận việc được giao, cập nhật tiến độ, báo khi vướng, rồi xem kết quả của mình.',
        steps: [
            {
                icon: 'users',
                title: 'Được giao việc',
                teaser: 'Từ trưởng nhóm hoặc trưởng phòng',
                items: ['Tên và mô tả công việc', 'Hạn hoàn thành', 'Mức độ quan trọng'],
            },
            {
                icon: 'fileText',
                title: 'Cập nhật tiến độ',
                teaser: 'Việc của tôi',
                items: ['Phần trăm đã xong', 'Thời gian thực tế đã làm', 'Đính kèm tài liệu', 'Nhật ký ngày'],
                button: { label: 'Nộp báo cáo tiến độ', to: { name: 'manager.project.tasks' } },
            },
            {
                icon: 'messageCircle',
                title: 'Báo vướng mắc',
                teaser: 'Với người giao việc',
                items: ['Bình luận trên việc', 'Báo khó khăn hoặc chậm tiến độ', 'Đề xuất chỉnh mô tả'],
            },
            {
                icon: 'star',
                title: 'Kết quả của tôi',
                teaser: 'Sau khi hoàn thành',
                result: true,
                items: ['Điểm chất lượng được chấm', 'Tỷ lệ hoàn thành đúng hạn', 'Điểm KPI tháng này'],
            },
        ],
        aside: {
            title: 'Việc khác trong ngày',
            teaser: 'Ngoài công việc được giao.',
            items: [
                { icon: 'megaphone', label: 'Đăng tin lên bảng tin nội bộ' },
                { icon: 'eye', label: 'Xem báo cáo được chia sẻ' },
                { icon: 'home', label: 'Xem tất cả việc của tôi' },
            ],
            button: { label: 'Việc của tôi', to: { name: 'manager.project.tasks' } },
        },
        more: [
            { icon: 'barChart', title: 'Điểm KPI', teaser: 'Điểm tháng này' },
            { icon: 'calendar', title: 'Nhật ký ngày', teaser: 'Ghi chép hàng ngày' },
            { icon: 'trendingUp', title: 'Tổng kết tuần', teaser: 'Kết quả 7 ngày qua' },
        ],
    },
};

function tabForUser(user) {
    if (!user) return 'member';
    const active = user.active_role;
    if (active && ROLE_TAB[active]) return ROLE_TAB[active];
    if (active) return 'member';
    const roles = Array.isArray(user.roles) ? user.roles : [];
    for (const code of ['super_admin', 'admin', 'department_director', 'deputy_department_director']) {
        if (roles.includes(code)) return ROLE_TAB[code];
    }
    return 'member';
}

const chosen = ref(false);
const activeTab = ref('member');

watch(
    () => auth.user,
    (user) => {
        if (chosen.value) return;
        activeTab.value = tabForUser(user);
    },
    { immediate: true },
);

const current = computed(() => flows[activeTab.value]);
const mine = computed(() => tabForUser(auth.user));

function selectTab(id) {
    chosen.value = true;
    activeTab.value = id;
}
</script>

<template>
    <section class="guide">
        <PageHeader
            title="Quy trình"
            icon="gitBranch"
            description="Làm lần lượt theo vai trò. Mỗi ô là một bước, mũi tên chỉ bước tiếp theo."
            :breadcrumbs="[{ label: 'Quy trình' }]"
        />

        <div class="guide__body">
            <div class="guide__tabs hide-scrollbar" role="tablist" aria-label="Vai trò">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    role="tab"
                    class="guide__tab"
                    :class="{ 'guide__tab--active': activeTab === tab.id }"
                    :aria-selected="activeTab === tab.id"
                    @click="selectTab(tab.id)"
                >
                    {{ tab.label }}
                    <span v-if="mine === tab.id" class="guide__you">Bạn</span>
                </button>
            </div>

            <header class="guide__lead">
                <h2>{{ current.title }}</h2>
                <p>{{ current.lead }}</p>
            </header>

            <ol class="rail" :aria-label="`Luồng ${current.title}`">
                <li
                    v-for="(step, index) in current.steps"
                    :key="step.title"
                    class="rail__stop"
                    :class="{ 'rail__stop--result': step.result }"
                >
                    <div class="rail__track" aria-hidden="true">
                        <span class="rail__dot">{{ index + 1 }}</span>
                        <span v-if="index < current.steps.length - 1" class="rail__arrow">
                            <AppIcon name="arrowRight" :size="16" :stroke-width="2" />
                        </span>
                    </div>

                    <component
                        :is="step.button ? 'router-link' : 'article'"
                        class="node"
                        :class="{ 'node--result': step.result, 'node--link': step.button }"
                        :to="step.button?.to"
                    >
                        <div class="node__head">
                            <span class="node__icon" aria-hidden="true">
                                <AppIcon :name="step.icon" :size="16" :stroke-width="1.75" />
                            </span>
                            <span class="node__copy">
                                <span class="node__title">{{ step.title }}</span>
                                <em class="node__teaser">{{ step.teaser }}</em>
                            </span>
                        </div>
                        <ul class="node__items">
                            <li v-for="item in step.items" :key="item">{{ item }}</li>
                        </ul>
                        <span v-if="step.button" class="node__go">
                            {{ step.button.label }}
                            <AppIcon name="arrowRight" :size="14" :stroke-width="2" />
                        </span>
                    </component>
                </li>
            </ol>

            <div class="guide__extra">
                <section class="extra" aria-labelledby="guide-aside-title">
                    <h3 id="guide-aside-title">{{ current.aside.title }}</h3>
                    <p>{{ current.aside.teaser }}</p>
                    <ul>
                        <li v-for="item in current.aside.items" :key="item.label">
                            <AppIcon :name="item.icon" :size="14" :stroke-width="1.75" />
                            <span>{{ item.label }}</span>
                        </li>
                    </ul>
                    <router-link v-if="current.aside.button" class="extra__go" :to="current.aside.button.to">
                        {{ current.aside.button.label }}
                        <AppIcon name="arrowRight" :size="14" :stroke-width="2" />
                    </router-link>
                </section>

                <section class="extra" aria-labelledby="guide-more-title">
                    <h3 id="guide-more-title">Xem thêm</h3>
                    <p>Các mục liên quan, mở từ menu bên trái khi cần.</p>
                    <ul class="more">
                        <li v-for="item in current.more" :key="item.title">
                            <span class="more__icon" aria-hidden="true">
                                <AppIcon :name="item.icon" :size="14" :stroke-width="1.75" />
                            </span>
                            <span>
                                <strong>{{ item.title }}</strong>
                                <em>{{ item.teaser }}</em>
                            </span>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </section>
</template>

<style scoped>
.guide {
    min-height: 100%;
    display: flex;
    flex-direction: column;
    padding: var(--space-2) var(--space-3) var(--space-5);
    gap: var(--space-3);
}

.guide__body {
    display: flex;
    flex-direction: column;
    gap: var(--space-4);
    min-width: 0;
}

.guide__tabs {
    display: flex;
    gap: var(--space-2);
    overflow-x: auto;
}

.guide__tab {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    flex-shrink: 0;
    padding: 8px 14px;
    border: none;
    border-radius: var(--radius-full);
    background: var(--color-surface-muted);
    box-shadow: inset 0 0 0 1px var(--color-border);
    color: var(--color-text-muted);
    font: 600 13px/1 var(--font-family-base);
    cursor: pointer;
}

.guide__tab--active {
    background: var(--color-primary);
    color: var(--color-on-primary);
    box-shadow: none;
}

.guide__you {
    padding: 2px 6px;
    border-radius: var(--radius-full);
    background: var(--color-primary-surface);
    color: var(--color-primary);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.02em;
}

.guide__tab--active .guide__you {
    background: color-mix(in srgb, var(--color-on-primary) 18%, transparent);
    color: var(--color-on-primary);
}

.guide__lead h2 {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: var(--color-text);
}

.guide__lead p {
    margin: 4px 0 0;
    max-width: 62ch;
    color: var(--color-text-muted);
    font-size: 14px;
    line-height: 1.5;
}

.rail {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin: 0;
    padding: var(--space-4);
    list-style: none;
    background: var(--color-surface-muted);
    border-radius: var(--radius-lg);
    box-shadow: inset 0 0 0 1px var(--color-border);
}

.rail__track {
    position: relative;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
}

.rail__track::before {
    content: '';
    position: absolute;
    top: 50%;
    left: -9px;
    right: -9px;
    height: 2px;
    background: var(--color-primary-200);
    transform: translateY(-50%);
}

.rail__stop:first-child .rail__track::before {
    left: 50%;
}

.rail__stop:last-child .rail__track::before {
    right: 50%;
}

.rail__dot {
    position: relative;
    z-index: 1;
    width: 32px;
    height: 32px;
    display: grid;
    place-items: center;
    border-radius: var(--radius-full);
    background: var(--color-primary);
    color: var(--color-on-primary);
    font-size: 13px;
    font-weight: 700;
    box-shadow: 0 0 0 4px var(--color-surface-muted);
}

.rail__stop--result .rail__dot {
    background: var(--color-primary-900);
}

.rail__arrow {
    position: absolute;
    z-index: 1;
    right: -17px;
    display: flex;
    color: var(--color-primary);
    background: var(--color-surface-muted);
}

.node {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-height: 100%;
    padding: var(--space-4);
    border-radius: var(--radius-md);
    background: var(--color-surface);
    box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
    color: inherit;
    text-decoration: none;
}

.node--result {
    padding-left: calc(var(--space-2) + 3px + var(--space-3));
    background: var(--color-primary-surface);
    box-shadow: inset 0 0 0 1px var(--color-primary-200), var(--shadow-sm);
}

.node--result::before {
    content: '';
    position: absolute;
    top: var(--space-2);
    bottom: var(--space-2);
    left: var(--space-2);
    width: 3px;
    border-radius: 0;
    background: var(--color-primary);
}

.node--link:hover {
    box-shadow: inset 0 0 0 1px var(--color-primary-200), var(--shadow-md);
}

.node--result.node--link:hover {
    box-shadow: inset 0 0 0 1px var(--color-primary-300), var(--shadow-md);
}

.node__head {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.node__icon {
    flex-shrink: 0;
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    border-radius: 8px;
    background: var(--color-primary);
    color: var(--color-on-primary);
}

.node--result .node__icon {
    background: var(--color-primary-900);
}

.node__copy {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.node__title {
    font-size: 15px;
    font-weight: 650;
    line-height: 1.3;
    color: var(--color-text);
}

.node__teaser {
    font-size: 12.5px;
    font-style: italic;
    color: var(--color-text-muted);
}

.node__items {
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.node__items li {
    position: relative;
    padding-left: 14px;
    font-size: 13px;
    line-height: 1.4;
    color: var(--color-text-muted);
}

.node__items li::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0.55em;
    width: 5px;
    height: 5px;
    border-radius: var(--radius-full);
    background: var(--color-primary-300);
}

.node__go,
.extra__go {
    margin-top: auto;
    display: inline-flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 8px 10px;
    border-radius: var(--radius-sm);
    background: var(--color-primary-surface);
    box-shadow: inset 0 0 0 1px var(--color-primary-200);
    color: var(--color-primary);
    font-size: 13px;
    font-weight: 650;
    text-decoration: none;
}

.node--result .node__go {
    background: var(--color-surface);
}

.node--link:hover .node__go,
.extra__go:hover {
    background: var(--color-primary);
    color: var(--color-on-primary);
    box-shadow: none;
}

.guide__extra {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr);
    gap: var(--space-3);
}

.extra {
    padding: var(--space-4);
    border-radius: var(--radius-md);
    background: var(--color-surface);
    box-shadow: inset 0 0 0 1px var(--color-border);
}

.extra h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: var(--color-text);
}

.extra > p {
    margin: 4px 0 12px;
    font-size: 13px;
    color: var(--color-text-muted);
}

.extra ul {
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.extra li {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 13.5px;
    line-height: 1.4;
    color: var(--color-text);
}

.extra li svg {
    flex-shrink: 0;
    margin-top: 2px;
    color: var(--color-primary);
}

.extra__go {
    margin-top: 14px;
    width: 100%;
}

.more li {
    align-items: center;
    padding: 8px 10px;
    border-radius: var(--radius-sm);
    background: var(--color-surface-muted);
}

.more__icon {
    width: 28px;
    height: 28px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    border-radius: 7px;
    background: var(--color-primary);
    color: var(--color-on-primary);
}

.more strong {
    display: block;
    font-size: 13.5px;
    font-weight: 650;
}

.more em {
    display: block;
    margin-top: 1px;
    font-size: 12px;
    font-style: italic;
    font-weight: 400;
    color: var(--color-text-muted);
}

@media (max-width: 1100px) {
    .rail {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        row-gap: 22px;
    }

    .rail__stop:nth-child(odd) .rail__track::before {
        left: 50%;
    }

    .rail__stop:nth-child(even) .rail__track::before {
        right: 50%;
    }

    .rail__stop:nth-child(even) .rail__arrow {
        display: none;
    }
}

@media (max-width: 720px) {
    .guide {
        padding: var(--space-2);
    }

    .rail,
    .guide__extra {
        grid-template-columns: 1fr;
    }

    .rail {
        gap: 0;
    }

    .rail__stop {
        padding-bottom: 18px;
    }

    .rail__stop:nth-child(n) .rail__track::before {
        left: 50%;
        right: auto;
        top: 16px;
        bottom: -18px;
        width: 2px;
        height: auto;
        transform: translateX(-50%);
    }

    .rail__stop:last-child .rail__track::before {
        display: none;
    }

    .rail__arrow {
        display: none;
    }
}
</style>
