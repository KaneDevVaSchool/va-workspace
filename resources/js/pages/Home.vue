<script setup>
import { computed, ref, watch } from 'vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
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
        lead: 'Làm lần lượt từ trên xuống: cấp quyền, cấu hình, rồi giám sát. Việc hàng ngày để Admin và trưởng phòng làm.',
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
            title="Hướng dẫn"
            icon="gitBranch"
            description="Chọn vai trò, đọc từ bước 1 xuống. Mỗi bước nói việc cần làm và chỗ mở trong hệ thống."
            :breadcrumbs="[{ label: 'Hướng dẫn' }]"
        />

        <div class="guide__body">
            <div class="guide__roles" role="tablist" aria-label="Vai trò">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    role="tab"
                    class="guide__role"
                    :class="{ 'guide__role--active': activeTab === tab.id }"
                    :aria-selected="activeTab === tab.id"
                    @click="selectTab(tab.id)"
                >
                    {{ tab.label }}
                    <span v-if="mine === tab.id" class="guide__you">của bạn</span>
                </button>
            </div>

            <header class="guide__lead">
                <h2>{{ current.title }}</h2>
                <p>{{ current.lead }}</p>
            </header>

            <ol class="steps" :aria-label="`Hướng dẫn ${current.title}`">
                <li v-for="(step, index) in current.steps" :key="step.title" class="step">
                    <span class="step__num" aria-hidden="true">{{ index + 1 }}</span>
                    <div class="step__body">
                        <h3>{{ step.title }}</h3>
                        <p>{{ step.teaser }}</p>
                        <ul>
                            <li v-for="item in step.items" :key="item">{{ item }}</li>
                        </ul>
                        <router-link v-if="step.button" class="step__go" :to="step.button.to">
                            {{ step.button.label }}
                        </router-link>
                    </div>
                </li>
            </ol>

            <div class="guide__extra">
                <section class="note" aria-labelledby="guide-aside-title">
                    <h3 id="guide-aside-title">{{ current.aside.title }}</h3>
                    <p>{{ current.aside.teaser }}</p>
                    <ul>
                        <li v-for="item in current.aside.items" :key="item.label">{{ item.label }}</li>
                    </ul>
                    <router-link v-if="current.aside.button" class="step__go" :to="current.aside.button.to">
                        {{ current.aside.button.label }}
                    </router-link>
                </section>

                <section class="note" aria-labelledby="guide-more-title">
                    <h3 id="guide-more-title">Mở từ menu khi cần</h3>
                    <p>Các mục này không nằm trong bốn bước. Vào từ menu bên trái.</p>
                    <ul>
                        <li v-for="item in current.more" :key="item.title">
                            <strong>{{ item.title }}.</strong> {{ item.teaser }}
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
    padding: var(--space-2) var(--space-3) var(--space-6);
    gap: var(--space-4);
    color: var(--color-text);
}

.guide__body {
    display: flex;
    flex-direction: column;
    gap: var(--space-4);
    min-width: 0;
}

.guide__roles {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-4);
    box-shadow: 0 1px 0 var(--color-border);
}

.guide__role {
    margin: 0;
    padding: 0 0 var(--space-2);
    border: none;
    border-radius: 0;
    background: transparent;
    color: var(--color-text-muted);
    font: 600 14px/1.3 var(--font-family-base);
    cursor: pointer;
    box-shadow: inset 0 -2px 0 transparent;
}

.guide__role--active {
    color: var(--color-text);
    box-shadow: inset 0 -2px 0 var(--color-text);
}

.guide__you {
    margin-left: 6px;
    font-size: 12px;
    font-weight: 500;
    color: var(--color-text-muted);
}

.guide__lead h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: var(--color-text);
}

.guide__lead p {
    margin: 6px 0 0;
    max-width: 68ch;
    color: var(--color-text-muted);
    font-size: 15px;
    line-height: 1.55;
}

.steps {
    display: flex;
    flex-direction: column;
    margin: 0;
    padding: 0;
    list-style: none;
}

.step {
    display: grid;
    grid-template-columns: 2rem minmax(0, 1fr);
    gap: var(--space-3);
    padding: var(--space-4) 0;
    box-shadow: 0 1px 0 var(--color-border);
}

.step__num {
    font-size: 15px;
    font-weight: 700;
    line-height: 1.4;
    color: var(--color-text-muted);
}

.step__body h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    line-height: 1.4;
    color: var(--color-text);
}

.step__body p {
    margin: 4px 0 0;
    max-width: 68ch;
    font-size: 14px;
    line-height: 1.55;
    color: var(--color-text);
}

.step__body ul {
    margin: 8px 0 0;
    padding-left: 1.1rem;
    color: var(--color-text-muted);
    font-size: 14px;
    line-height: 1.5;
}

.step__body li + li {
    margin-top: 4px;
}

.step__go {
    display: inline-block;
    margin-top: 10px;
    color: var(--color-text);
    font-size: 14px;
    font-weight: 650;
    text-decoration: underline;
    text-underline-offset: 3px;
}

.step__go:hover {
    color: var(--color-text-muted);
}

.guide__extra {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: var(--space-6);
    margin-top: var(--space-2);
}

.note h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: var(--color-text);
}

.note > p {
    margin: 4px 0 10px;
    font-size: 14px;
    line-height: 1.5;
    color: var(--color-text-muted);
}

.note ul {
    margin: 0;
    padding-left: 1.1rem;
    color: var(--color-text);
    font-size: 14px;
    line-height: 1.5;
}

.note li + li {
    margin-top: 6px;
}

@media (max-width: 720px) {
    .guide {
        padding: var(--space-2);
    }

    .guide__extra {
        grid-template-columns: 1fr;
        gap: var(--space-5);
    }
}
</style>
