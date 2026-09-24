<script setup>
import { computed, ref, watch } from 'vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import AppIcon from '../components/AppIcon.vue';
import PageHeader from '../components/PageHeader.vue';

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
        lead: 'Làm lần lượt: cấp quyền, cấu hình, rồi giám sát. Việc hàng ngày để Admin và trưởng phòng làm.',
        steps: [
            {
                icon: 'shield',
                title: 'Cấp quyền cho từng người',
                teaser: 'Mở ma trận, gán vai trò, bật quyền theo module, khoá quyền nhạy cảm.',
                items: ['Chỉ Super Admin làm được bước này', 'Làm xong rồi mới cấu hình phòng ban'],
                button: { label: 'Mở ma trận phân quyền', to: { name: 'superadmin.permissions' } },
            },
            {
                icon: 'building',
                title: 'Cấu hình từng phòng ban',
                teaser: 'Bật menu riêng, xem thành viên và cách chấm điểm của từng phòng.',
                items: ['Làm sau khi đã cấp quyền'],
                button: { label: 'Mở cấu hình Workspace', to: { name: 'superadmin.workspace-config.overview' } },
            },
            {
                icon: 'layoutList',
                title: 'Đặt menu cho cả hệ thống',
                teaser: 'Ẩn hoặc hiện mục menu. Đây là mặc định, trước khi từng phòng tự chỉnh.',
                items: ['Áp dụng cho mọi phòng ban'],
                button: { label: 'Ẩn hoặc hiện menu', to: { name: 'superadmin.workspace-config.global-menu' } },
            },
            {
                icon: 'search',
                title: 'Xem ai đã làm gì',
                teaser: 'Mở nhật ký để biết người nào thao tác lúc nào, và phát hiện việc bất thường.',
                result: true,
                items: ['Làm thường xuyên sau khi hệ thống đã chạy'],
                button: { label: 'Mở nhật ký', to: { name: 'superadmin.activity' } },
            },
        ],
        aside: {
            title: 'Việc không làm ở vai trò này',
            teaser: 'Giao lại cho đúng người, đừng ôm hết.',
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
        lead: 'Làm lần lượt: thêm người, mở phòng ban, theo dõi việc, rồi xem báo cáo.',
        steps: [
            {
                icon: 'users',
                title: 'Thêm và sắp người',
                teaser: 'Thêm tài khoản, gắn phòng ban, chọn vai trò. Khoá hoặc xoá khi người nghỉ.',
                items: ['Làm trước khi giao việc'],
                button: { label: 'Mở danh sách người dùng', to: { name: 'manager.workspace-config.members' } },
            },
            {
                icon: 'building',
                title: 'Mở phòng ban',
                teaser: 'Chọn cách chấm điểm, menu riêng, rồi bổ nhiệm trưởng phòng.',
                items: ['Làm khi có phòng mới hoặc đổi trưởng phòng'],
                button: { label: 'Mở cấu hình phòng ban', to: { name: 'manager.workspace-config.hub' } },
            },
            {
                icon: 'layers',
                title: 'Theo dõi việc đang chạy',
                teaser: 'Xem tiến độ mọi dự án, việc chuyển giữa các phòng, và điểm KPI từng phòng.',
                items: ['Làm trong tuần, không chờ cuối tháng'],
                button: { label: 'Mở dự án và công việc', to: { name: 'manager.project.index' } },
            },
            {
                icon: 'barChart',
                title: 'Đọc báo cáo tổng hợp',
                teaser: 'Xem tỷ lệ hoàn thành, điểm chất lượng và xu hướng của cả trường.',
                result: true,
                items: ['Làm sau khi các phòng đã cập nhật việc'],
                button: { label: 'Mở báo cáo', to: { name: 'manager.reports.index' } },
            },
        ],
        aside: {
            title: 'Khi có việc phát sinh',
            teaser: 'Xử lý các việc này ngoài bốn bước trên.',
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
        lead: 'Làm lần lượt: thiết lập phòng, giao việc, chấm điểm, rồi gửi báo cáo lên trên.',
        steps: [
            {
                icon: 'settings',
                title: 'Thiết lập phòng trước',
                teaser: 'Chọn tiêu chí đánh giá, bật tính năng của phòng, chỉ định phó phòng và trưởng nhóm.',
                items: ['Làm một lần, rồi chỉ sửa khi đổi quy ước'],
                button: { label: 'Mở cấu hình phòng ban', to: { name: 'manager.workspace-config.hub' } },
            },
            {
                icon: 'userPlus',
                title: 'Tạo việc và giao người',
                teaser: 'Tạo dự án hoặc công việc, chọn người làm, đặt hạn. Việc của phòng khác thì chuyển giao.',
                items: ['Giao rõ người và hạn trước khi nhân viên bắt đầu'],
                button: { label: 'Tạo công việc mới', to: { name: 'manager.project.tasks.create' } },
            },
            {
                icon: 'clipboardCheck',
                title: 'Theo dõi rồi chấm điểm',
                teaser: 'Xem tiến độ từng người, duyệt nhật ký ngày, chấm điểm chất lượng khi việc xong.',
                items: ['Chấm sau khi nhân viên đã cập nhật tiến độ'],
                button: { label: 'Mở công việc của phòng', to: { name: 'manager.project.index' } },
            },
            {
                icon: 'barChart',
                title: 'Gửi báo cáo lên trên',
                teaser: 'Xem tỷ lệ hoàn thành và điểm KPI của phòng, rồi chọn ai được xem báo cáo.',
                result: true,
                items: ['Làm khi đã chấm điểm xong'],
                button: { label: 'Mở báo cáo', to: { name: 'manager.reports.index' } },
            },
        ],
        aside: {
            title: 'Khi có việc phát sinh',
            teaser: 'Xử lý các việc này trước khi gửi báo cáo.',
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
        lead: 'Làm lần lượt: đọc việc được giao, cập nhật tiến độ, báo khi vướng, rồi xem kết quả.',
        steps: [
            {
                icon: 'users',
                title: 'Đọc việc được giao',
                teaser: 'Mở việc từ trưởng nhóm hoặc trưởng phòng. Đọc tên, mô tả, hạn và mức độ quan trọng trước khi làm.',
                items: ['Chưa rõ mô tả thì hỏi lại trước khi bắt đầu'],
            },
            {
                icon: 'fileText',
                title: 'Cập nhật tiến độ',
                teaser: 'Ghi phần trăm đã xong, thời gian thực tế, đính kèm tài liệu và nhật ký ngày.',
                items: ['Cập nhật trong ngày, đừng để cuối hạn'],
                button: { label: 'Mở việc của tôi', to: { name: 'manager.project.tasks' } },
            },
            {
                icon: 'messageCircle',
                title: 'Báo khi vướng',
                teaser: 'Bình luận trên đúng việc đó: khó khăn, chậm tiến độ, hoặc đề xuất sửa mô tả.',
                items: ['Báo sớm cho người giao việc, đừng chờ bị hỏi'],
            },
            {
                icon: 'star',
                title: 'Xem kết quả của mình',
                teaser: 'Sau khi xong, xem điểm chất lượng, tỷ lệ đúng hạn và điểm KPI tháng này.',
                result: true,
                items: ['Chỉ xem được kết quả của chính mình'],
            },
        ],
        aside: {
            title: 'Việc khác trong ngày',
            teaser: 'Làm thêm khi đã cập nhật việc được giao.',
            items: [
                { icon: 'megaphone', label: 'Đăng tin lên bảng tin nội bộ' },
                { icon: 'eye', label: 'Xem báo cáo được chia sẻ' },
                { icon: 'home', label: 'Xem tất cả việc của tôi' },
            ],
            button: { label: 'Mở việc của tôi', to: { name: 'manager.project.tasks' } },
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

const STEP_TONE = ['primary', 'tertiary', 'secondary', 'gold'];

const ROLE_ICON = {
    super_admin: 'shield',
    admin: 'settings',
    department_director: 'building',
    member: 'user',
};

const current = computed(() => flows[activeTab.value]);
const mine = computed(() => tabForUser(auth.user));

function selectTab(id) {
    chosen.value = true;
    activeTab.value = id;
}

function tone(index) {
    return STEP_TONE[index] ?? 'primary';
}
</script>

<template>
    <section class="map">
        <PageHeader
            title="Hướng dẫn"
            icon="gitBranch"
            description="Chọn vai trò. Đọc các bước nối với nhau, từ bước 1 đến kết quả."
            :breadcrumbs="[{ label: 'Hướng dẫn' }]"
        />

        <div class="map__canvas">
            <div class="roles" role="tablist" aria-label="Vai trò">
                <template v-for="(tab, index) in tabs" :key="tab.id">
                    <button
                        type="button"
                        role="tab"
                        class="role"
                        :class="{ 'role--active': activeTab === tab.id }"
                        :aria-selected="activeTab === tab.id"
                        @click="selectTab(tab.id)"
                    >
                        <span class="role__mark" aria-hidden="true">
                            <AppIcon :name="ROLE_ICON[tab.id]" :size="16" :stroke-width="1.75" />
                        </span>
                        <span class="role__copy">
                            <span class="role__label">{{ tab.label }}</span>
                            <span v-if="mine === tab.id" class="role__you">
                                <span class="role__dot" aria-hidden="true"></span>
                                của bạn
                            </span>
                        </span>
                    </button>
                    <span v-if="index < tabs.length - 1" class="role-wire" aria-hidden="true"></span>
                </template>
            </div>

            <header class="map__lead">
                <h2>{{ current.title }}</h2>
                <p>{{ current.lead }}</p>
            </header>

            <div class="flow" role="list" :aria-label="`Hướng dẫn ${current.title}`">
                <article
                    v-for="(step, index) in current.steps"
                    :key="step.title"
                    class="node"
                    :class="[`node--${tone(index)}`, { 'node--result': step.result }]"
                    role="listitem"
                >
                    <span v-if="index > 0" class="port port--in" aria-hidden="true"></span>
                    <span v-if="index < current.steps.length - 1" class="port port--out" aria-hidden="true"></span>
                    <span v-if="index < current.steps.length - 1" class="wire" aria-hidden="true">
                        <span class="wire__line"></span>
                        <AppIcon name="chevronRight" :size="14" :stroke-width="2" />
                    </span>

                    <div class="node__top">
                        <span class="node__icon" aria-hidden="true">
                            <AppIcon :name="step.icon" :size="18" :stroke-width="1.75" />
                        </span>
                        <span class="node__step">{{ step.result ? 'Kết quả' : `Bước ${index + 1}` }}</span>
                    </div>
                    <h3>{{ step.title }}</h3>
                    <p>{{ step.teaser }}</p>
                    <ul>
                        <li v-for="item in step.items" :key="item">{{ item }}</li>
                    </ul>
                    <router-link v-if="step.button" class="node__go" :to="step.button.to">
                        {{ step.button.label }}
                        <AppIcon name="arrowRight" :size="14" :stroke-width="2" />
                    </router-link>
                </article>
            </div>

            <div class="fork">
                <article class="branch">
                    <span class="port port--top" aria-hidden="true"></span>
                    <div class="node__top">
                        <span class="node__icon node__icon--muted" aria-hidden="true">
                            <AppIcon name="gitBranch" :size="18" :stroke-width="1.75" />
                        </span>
                        <span class="node__step">Nhánh phụ</span>
                    </div>
                    <h3>{{ current.aside.title }}</h3>
                    <p>{{ current.aside.teaser }}</p>
                    <ul class="branch__list">
                        <li v-for="item in current.aside.items" :key="item.label">
                            <AppIcon :name="item.icon" :size="15" :stroke-width="1.75" />
                            <span>{{ item.label }}</span>
                        </li>
                    </ul>
                    <router-link v-if="current.aside.button" class="node__go" :to="current.aside.button.to">
                        {{ current.aside.button.label }}
                        <AppIcon name="arrowRight" :size="14" :stroke-width="2" />
                    </router-link>
                </article>

                <div class="leaves">
                    <p class="leaves__label">Mở từ menu khi cần</p>
                    <div class="leaves__row">
                        <article v-for="item in current.more" :key="item.title" class="leaf">
                            <span class="port port--top" aria-hidden="true"></span>
                            <span class="leaf__icon" aria-hidden="true">
                                <AppIcon :name="item.icon" :size="16" :stroke-width="1.75" />
                            </span>
                            <h3>{{ item.title }}</h3>
                            <p>{{ item.teaser }}</p>
                        </article>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.map {
    min-height: 100%;
    display: flex;
    flex-direction: column;
    padding: var(--space-2) var(--space-3) var(--space-6);
    color: var(--color-text);
}

.map__canvas {
    display: flex;
    flex-direction: column;
    gap: var(--space-5);
    min-width: 0;
    padding: var(--space-5);
    border-radius: var(--radius-lg);
    background-color: var(--color-surface-muted);
    background-image: radial-gradient(circle, var(--color-border) 1.1px, transparent 1.2px);
    background-size: 18px 18px;
}

.roles {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-2) var(--space-3);
}

.role {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    margin: 0;
    padding: var(--space-2) var(--space-3);
    border: none;
    border-radius: var(--radius-md);
    background: var(--color-surface);
    box-shadow: var(--shadow-sm);
    color: var(--color-text-muted);
    font: 600 14px/1.2 var(--font-family-base);
    text-align: left;
    cursor: pointer;
}

.role:hover {
    color: var(--color-text);
    box-shadow: var(--shadow-md);
}

.role--active {
    color: var(--color-text);
    box-shadow: var(--shadow-md), 0 0 0 2px var(--color-primary-200);
}

.role:focus-visible,
.node__go:focus-visible {
    outline: 2px solid var(--color-primary);
    outline-offset: 2px;
}

.role__mark,
.node__icon,
.leaf__icon {
    display: grid;
    place-items: center;
    flex-shrink: 0;
}

.role__mark {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: var(--radius-sm);
    background: var(--color-surface-muted);
    color: var(--color-text-muted);
}

.role--active .role__mark {
    background: var(--color-primary-surface);
    color: var(--color-primary);
}

.role__copy {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.role__you {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 500;
    color: var(--color-text-muted);
}

.role__dot {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: var(--radius-full);
    background: var(--color-primary);
}

.role-wire {
    width: 1.5rem;
    height: 2px;
    flex: 0 0 auto;
    border-radius: var(--radius-full);
    background: var(--color-border-strong);
}

.map__lead h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    letter-spacing: -0.01em;
}

.map__lead p {
    margin: 6px 0 0;
    max-width: 72ch;
    color: var(--color-text-muted);
    font-size: 15px;
    line-height: 1.55;
}

.flow {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    column-gap: 2.25rem;
    row-gap: var(--space-5);
    margin: 0;
    padding: var(--space-2) 6px 0;
}

.node,
.branch,
.leaf {
    position: relative;
    min-width: 0;
    background: var(--color-surface);
    box-shadow: var(--shadow-md);
}

.node,
.branch {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: var(--space-4);
    border-radius: var(--radius-lg);
}

.node {
    transition: box-shadow 180ms ease;
}

.node:hover,
.branch:hover,
.leaf:hover {
    box-shadow: var(--shadow-lg);
}

.node__top {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    min-height: 2.25rem;
}

.node__icon {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: var(--radius-md);
}

.node--primary .node__icon {
    background: var(--color-primary-surface);
    color: var(--color-primary);
}

.node--tertiary .node__icon {
    background: var(--color-tertiary-surface);
    color: var(--color-tertiary);
}

.node--secondary .node__icon {
    background: var(--color-secondary-surface);
    color: var(--color-secondary);
}

.node--gold .node__icon,
.node--result .node__icon {
    background: var(--color-gold-surface);
    color: var(--color-gold-700);
}

.node__icon--muted {
    background: var(--color-surface-muted);
    color: var(--color-text);
}

.node__step {
    font-size: 12px;
    font-weight: 650;
    letter-spacing: 0.01em;
    color: var(--color-text-muted);
}

.node h3,
.branch h3,
.leaf h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    line-height: 1.35;
    color: var(--color-text);
}

.node p,
.branch > p,
.leaf p {
    margin: 0;
    font-size: 13px;
    line-height: 1.5;
    color: var(--color-text-muted);
}

.node ul {
    margin: 0;
    padding-left: 1.05rem;
    color: var(--color-text);
    font-size: 13px;
    line-height: 1.45;
}

.node li + li {
    margin-top: 4px;
}

.node__go {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: auto;
    padding-top: var(--space-1);
    color: var(--color-text);
    font-size: 13px;
    font-weight: 650;
    text-decoration: underline;
    text-underline-offset: 3px;
}

.node__go:hover {
    color: var(--color-primary);
}

.port {
    position: absolute;
    z-index: 3;
    width: 9px;
    height: 9px;
    border-radius: var(--radius-full);
    background: var(--color-surface);
    box-shadow: 0 0 0 2px var(--color-border-strong);
}

.port--in,
.port--out {
    top: calc(var(--space-4) + 1.125rem - 4.5px);
}

.port--in {
    left: -5px;
}

.port--out {
    right: -5px;
}

.port--top {
    top: -5px;
    left: calc(50% - 4.5px);
}

.wire {
    position: absolute;
    z-index: 2;
    top: calc(var(--space-4) + 1.125rem - 7px);
    left: calc(100% + 4px);
    display: flex;
    align-items: center;
    width: calc(2.25rem - 8px);
    color: var(--color-text-muted);
    pointer-events: none;
}

.wire__line {
    flex: 1;
    height: 2px;
    border-radius: var(--radius-full);
    background: var(--color-border-strong);
}

.fork {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
    gap: var(--space-4);
    margin-top: var(--space-2);
    padding-top: var(--space-6);
}

.fork::before {
    content: '';
    position: absolute;
    top: 0;
    left: 6%;
    right: 6%;
    height: 2px;
    border-radius: var(--radius-full);
    background: var(--color-border-strong);
}

.fork::after {
    content: '';
    position: absolute;
    top: -1.15rem;
    left: calc(50% - 1px);
    width: 2px;
    height: 1.15rem;
    border-radius: var(--radius-full);
    background: var(--color-border-strong);
}

.branch__list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.branch__list li {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    color: var(--color-text);
    font-size: 14px;
    line-height: 1.45;
}

.branch__list :deep(.app-icon) {
    margin-top: 2px;
    color: var(--color-text-muted);
}

.leaves {
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
    min-width: 0;
}

.leaves__label {
    margin: 0;
    font-size: 13px;
    font-weight: 650;
    color: var(--color-text-muted);
}

.leaves__row {
    position: relative;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: var(--space-3);
    padding-top: var(--space-4);
}

.leaves__row::before {
    content: '';
    position: absolute;
    top: 0.45rem;
    left: 16%;
    right: 16%;
    height: 2px;
    border-radius: var(--radius-full);
    background: var(--color-border-strong);
}

.leaf {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: var(--space-3);
    border-radius: var(--radius-md);
    transition: box-shadow 180ms ease;
}

.leaf::after {
    content: '';
    position: absolute;
    top: calc(var(--space-4) * -1 + 0.45rem);
    left: calc(50% - 1px);
    width: 2px;
    height: calc(var(--space-4) - 0.45rem);
    background: var(--color-border-strong);
}

.leaf__icon {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: var(--radius-sm);
    background: var(--color-surface-muted);
    color: var(--color-text);
}

@media (max-width: 1180px) {
    .flow {
        grid-template-columns: 1fr 1fr;
        padding-right: 0;
        padding-left: 0;
    }

    .wire,
    .port--in,
    .port--out {
        display: none;
    }
}

@media (max-width: 900px) {
    .fork {
        grid-template-columns: 1fr;
    }

    .fork::before,
    .fork::after,
    .leaves__row::before,
    .leaf::after,
    .port--top {
        display: none;
    }

    .leaves__row {
        grid-template-columns: 1fr;
        padding-top: 0;
    }

    .role-wire {
        display: none;
    }
}

@media (max-width: 680px) {
    .map {
        padding: var(--space-2);
    }

    .map__canvas {
        padding: var(--space-3);
        gap: var(--space-4);
    }

    .flow {
        grid-template-columns: 1fr;
        row-gap: var(--space-4);
    }

    .wire {
        top: auto;
        bottom: calc(var(--space-4) * -1);
        left: 1.2rem;
        display: flex;
        flex-direction: column;
        width: auto;
        height: var(--space-4);
    }

    .wire__line {
        width: 2px;
        height: auto;
        flex: 1;
    }

    .wire :deep(.app-icon) {
        transform: rotate(90deg);
    }

    .port--in,
    .port--out {
        display: none;
    }
}
</style>
