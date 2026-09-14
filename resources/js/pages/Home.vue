<script setup>
import PageHeader from "../components/PageHeader.vue";
import DataStreamWidget from "../components/DataStreamWidget.vue";
import { useRouter } from "vue-router";

const router = useRouter();

const tabs = [
    { id: "super_admin", label: "Super Admin" },
    { id: "admin", label: "Admin" },
    { id: "department_director", label: "Trưởng phòng ban" },
    { id: "member", label: "Nhân viên" },
];

// ── Tab 1: Super Admin ──────────────────────────────────────────────────────────
const superAdminData = {
    title: "Super Admin",
    description:
        "Quản trị toàn hệ thống — cấu hình phân quyền gốc, theo dõi mọi phòng ban, không can thiệp việc nghiệp vụ hàng ngày.",
    canvasW: 1750,
    canvasH: 920,
    nodes: [
        {
            id: "n1",
            type: "icon",
            x: 756,
            y: 0,
            w: 148,
            icon: "shield",
            label: "Super Admin",
        },

        {
            id: "n2",
            type: "card",
            x: 928,
            y: 7,
            w: 232,
            compact: true,
            items: [{ icon: "eye", label: "Nhìn xuyên suốt mọi phòng ban" }],
        },

        {
            id: "n3",
            type: "card",
            x: 0,
            y: 236,
            w: 380,
            icon: "shield",
            title: "Ma trận phân quyền",
            teaser: "Việc chỉ Super Admin làm được",
            items: [
                { icon: "users", label: "Gán vai trò cho từng người" },
                { icon: "sliders", label: "Bật/tắt quyền theo module" },
                { icon: "lock", label: "Khoá quyền nhạy cảm" },
            ],
            button: {
                label: "Vào ma trận phân quyền",
                to: { name: "superadmin.permissions" },
            },
        },

        {
            id: "n4",
            type: "card",
            x: 424,
            y: 165,
            w: 400,
            icon: "building",
            title: "Cấu hình từng phòng ban",
            teaser: "Thiết lập ban đầu",
            items: [
                { icon: "layoutList", label: "Bật/tắt menu riêng từng phòng" },
                { icon: "users", label: "Xem thành viên mỗi phòng ban" },
                { icon: "star", label: "Xem cách chấm điểm mỗi phòng" },
            ],
            button: {
                label: "Cấu hình Workspace",
                to: { name: "superadmin.workspace-config.overview" },
            },
        },

        {
            id: "n5",
            type: "card",
            x: 872,
            y: 165,
            w: 400,
            icon: "layoutList",
            title: "Menu toàn hệ thống",
            teaser: "Áp dụng cho mọi phòng ban",
            items: [
                { icon: "check", label: "Ẩn/hiện mục menu toàn hệ thống" },
                { icon: "sliders", label: "Đặt mặc định trước khi tuỳ chỉnh riêng" },
            ],
            button: {
                label: "Ẩn/hiện menu toàn hệ thống",
                to: { name: "superadmin.workspace-config.global-menu" },
            },
        },

        {
            id: "n6",
            type: "card",
            x: 1320,
            y: 165,
            w: 400,
            highlight: true,
            icon: "shield",
            title: "Nhật ký hoạt động",
            teaser: "Giám sát toàn hệ thống",
            items: [
                { icon: "search", label: "Ai làm gì, lúc nào" },
                { icon: "bell", label: "Phát hiện thao tác bất thường" },
            ],
            button: {
                label: "Xem nhật ký hoạt động",
                to: { name: "superadmin.activity" },
            },
        },

        {
            id: "n7",
            type: "icon",
            x: 94,
            y: 590,
            w: 208,
            icon: "shield",
            label: "Admin",
        },

        {
            id: "n8",
            type: "icon",
            x: 94,
            y: 727,
            w: 208,
            icon: "users",
            label: "Trưởng phòng ban",
        },

        {
            id: "n9",
            type: "card",
            x: 424,
            y: 531,
            w: 400,
            icon: "settings",
            title: "Không làm thay việc nghiệp vụ",
            teaser: "Giao lại cho Admin & Trưởng phòng",
            items: [
                { icon: "gitBranch", label: "Admin điều hành nghiệp vụ hàng ngày" },
                { icon: "building", label: "Trưởng phòng tự quản lý phòng mình" },
                { icon: "clipboardCheck", label: "Super Admin chỉ giám sát & cấp quyền" },
            ],
        },

        {
            id: "n10",
            type: "group",
            x: 872,
            y: 555,
            w: 472,
            label: "XEM THÊM",
            children: [
                {
                    id: "n11",
                    icon: "shield",
                    title: "Vai trò trong hệ thống",
                    teaser: "9 cấp bậc, từ Super Admin đến Nhân viên",
                },
                {
                    id: "n12",
                    icon: "building",
                    title: "Tổng hợp mọi phòng ban",
                    teaser: "Xem nhanh tình hình chung",
                },
                {
                    id: "n13",
                    icon: "lock",
                    title: "Bảo mật tài khoản dùng chung",
                    teaser: "Mật khẩu, tài khoản hạ tầng",
                },
            ],
        },
    ],
    edges: [
        { id: "e1", from: "n1", to: "n2" },
        {
            id: "e2",
            from: "n2",
            to: "n5",
            fromAnchor: "bottom",
            toAnchor: "top",
        },
        { id: "e3", from: "n3", to: "n4" },
        { id: "e4", from: "n4", to: "n5" },
        { id: "e5", from: "n5", to: "n6" },
        { id: "e6", from: "n7", to: "n9" },
        { id: "e7", from: "n8", to: "n9" },
        {
            id: "e8",
            from: "n6",
            to: "n10",
            fromAnchor: "bottom",
            toAnchor: "top",
        },
        {
            id: "e9",
            from: "n9",
            to: "n4",
            fromAnchor: "top",
            toAnchor: "bottom",
        },
    ],
};

// ── Tab 2: Admin ──────────────────────────────────────────────────────────────
const adminData = {
    title: "Admin",
    description:
        "Toàn quyền điều hành nghiệp vụ — thêm người, mở phòng ban mới, theo dõi mọi dự án và công việc, không cấu hình phân quyền gốc.",
    canvasW: 1750,
    canvasH: 920,
    nodes: [
        {
            id: "n1",
            type: "icon",
            x: 756,
            y: 0,
            w: 148,
            icon: "settings",
            label: "Admin",
        },

        {
            id: "n2",
            type: "card",
            x: 928,
            y: 7,
            w: 208,
            compact: true,
            items: [{ icon: "check", label: "Duyệt thay đổi lớn" }],
        },

        {
            id: "n3",
            type: "card",
            x: 0,
            y: 236,
            w: 380,
            icon: "users",
            title: "Quản lý người dùng",
            teaser: "Toàn hệ thống",
            items: [
                { icon: "userPlus", label: "Thêm người mới" },
                { icon: "building", label: "Gắn vào phòng ban" },
                { icon: "shield", label: "Chọn vai trò phù hợp" },
                { icon: "lock", label: "Khoá / xoá tài khoản" },
            ],
            button: {
                label: "Danh sách người dùng",
                to: { name: "manager.workspace-config.members" },
            },
        },

        {
            id: "n4",
            type: "card",
            x: 424,
            y: 165,
            w: 400,
            icon: "building",
            title: "Mở & cấu hình phòng ban",
            teaser: "Thiết lập ban đầu",
            items: [
                { icon: "star", label: "Cách chấm điểm của phòng" },
                { icon: "layoutList", label: "Menu riêng của phòng" },
                { icon: "users", label: "Bổ nhiệm trưởng phòng" },
            ],
            button: {
                label: "Cấu hình phòng ban",
                to: { name: "manager.workspace-config.hub" },
            },
        },

        {
            id: "n5",
            type: "card",
            x: 872,
            y: 165,
            w: 400,
            icon: "pieChart",
            title: "Theo dõi mọi dự án & công việc",
            teaser: "Xuyên suốt các phòng ban",
            items: [
                { icon: "layers", label: "Xem tiến độ mọi dự án" },
                { icon: "gitBranch", label: "Việc chuyển giao giữa phòng ban" },
                { icon: "barChart", label: "Điểm KPI từng phòng" },
            ],
            button: { label: "Xem dự án & công việc", to: { name: "manager.project.index" } },
        },

        {
            id: "n6",
            type: "card",
            x: 1320,
            y: 165,
            w: 400,
            highlight: true,
            icon: "barChart",
            title: "Báo cáo tổng hợp",
            teaser: "Toàn hệ thống",
            items: [
                { icon: "percent", label: "% hoàn thành theo phòng ban" },
                { icon: "star", label: "Điểm chất lượng trung bình" },
                { icon: "trendingUp", label: "Xu hướng hiệu suất" },
            ],
            button: { label: "Xem báo cáo", to: { name: "manager.reports.index" } },
        },

        {
            id: "n7",
            type: "icon",
            x: 94,
            y: 590,
            w: 208,
            icon: "users",
            label: "Trưởng phòng ban",
        },

        {
            id: "n8",
            type: "icon",
            x: 94,
            y: 727,
            w: 208,
            icon: "user",
            label: "Nhân viên",
        },

        {
            id: "n9",
            type: "card",
            x: 424,
            y: 531,
            w: 400,
            icon: "settings",
            title: "Việc thêm cần làm",
            teaser: "Admin hỗ trợ khi cần",
            items: [
                { icon: "shield", label: "Xử lý bài đăng vi phạm" },
                { icon: "lock", label: "Cấp/thu hồi tài khoản dùng chung" },
                { icon: "messageCircle", label: "Hỗ trợ trưởng phòng khi vướng mắc" },
                { icon: "refresh", label: "Đặt lại cấu hình khi cần" },
            ],
        },

        {
            id: "n10",
            type: "group",
            x: 872,
            y: 555,
            w: 472,
            label: "XEM THÊM",
            children: [
                {
                    id: "n11",
                    icon: "lock",
                    title: "Tài khoản dùng chung",
                    teaser: "Mật khẩu hạ tầng công ty",
                },
                {
                    id: "n12",
                    icon: "megaphone",
                    title: "Duyệt bài viết",
                    teaser: "Bảng tin nội bộ toàn trường",
                },
                {
                    id: "n13",
                    icon: "activity",
                    title: "Nhật ký hoạt động",
                    teaser: "Theo dõi thao tác gần đây",
                },
            ],
        },
    ],
    edges: [
        { id: "e1", from: "n1", to: "n2" },
        {
            id: "e2",
            from: "n2",
            to: "n5",
            fromAnchor: "bottom",
            toAnchor: "top",
        },
        { id: "e3", from: "n3", to: "n4" },
        { id: "e4", from: "n4", to: "n5" },
        { id: "e5", from: "n5", to: "n6" },
        { id: "e6", from: "n7", to: "n9" },
        { id: "e7", from: "n8", to: "n9" },
        {
            id: "e8",
            from: "n6",
            to: "n10",
            fromAnchor: "bottom",
            toAnchor: "top",
        },
        {
            id: "e9",
            from: "n9",
            to: "n4",
            fromAnchor: "top",
            toAnchor: "bottom",
        },
    ],
};

// ── Tab 3: Trưởng phòng ban ─────────────────────────────────────────────────────
const departmentDirectorData = {
    title: "Trưởng phòng ban",
    description:
        "Điều hành trọn vẹn 1 phòng ban — nhận việc từ trên giao xuống, phân công cho nhân sự, chấm điểm và tổng hợp báo cáo.",
    canvasW: 1750,
    canvasH: 920,
    nodes: [
        {
            id: "n1",
            type: "icon",
            x: 756,
            y: 0,
            w: 148,
            icon: "users",
            label: "Trưởng phòng ban",
        },

        {
            id: "n2",
            type: "card",
            x: 928,
            y: 7,
            w: 232,
            compact: true,
            items: [{ icon: "gitBranch", label: "Nhận việc liên phòng ban" }],
        },

        {
            id: "n3",
            type: "card",
            x: 0,
            y: 236,
            w: 380,
            icon: "settings",
            title: "Thiết lập phòng ban",
            teaser: "Trước khi giao việc",
            items: [
                { icon: "star", label: "Tiêu chí đánh giá riêng" },
                { icon: "sliders", label: "Bật/tắt tính năng riêng phòng" },
                { icon: "users", label: "Phó phòng, trưởng nhóm hỗ trợ" },
            ],
            button: {
                label: "Cấu hình phòng ban",
                to: { name: "manager.workspace-config.hub" },
            },
        },

        {
            id: "n4",
            type: "card",
            x: 424,
            y: 165,
            w: 400,
            icon: "layoutList",
            title: "Tạo & phân công công việc",
            teaser: "Cho nhân viên trong phòng",
            items: [
                { icon: "layoutList", label: "Tạo dự án / công việc mới" },
                { icon: "userPlus", label: "Giao cho từng nhân viên" },
                { icon: "clock", label: "Đặt hạn hoàn thành" },
                { icon: "gitBranch", label: "Chuyển việc sang phòng khác" },
            ],
            button: { label: "Tạo công việc mới", to: { name: "manager.project.tasks.create" } },
        },

        {
            id: "n5",
            type: "card",
            x: 872,
            y: 165,
            w: 400,
            icon: "clipboardCheck",
            title: "Theo dõi & chấm điểm",
            teaser: "Việc của cả phòng",
            items: [
                { icon: "trendingUp", label: "% tiến độ từng người" },
                { icon: "star", label: "Chấm điểm chất lượng" },
                { icon: "fileText", label: "Duyệt nhật ký ngày" },
            ],
            button: { label: "Xem công việc phòng ban", to: { name: "manager.project.index" } },
        },

        {
            id: "n6",
            type: "card",
            x: 1320,
            y: 165,
            w: 400,
            highlight: true,
            icon: "barChart",
            title: "Báo cáo phòng ban",
            teaser: "Gửi lên cấp trên",
            items: [
                { icon: "percent", label: "% hoàn thành cả phòng" },
                { icon: "star", label: "Điểm KPI trung bình" },
                { icon: "users", label: "Chọn ai được xem báo cáo" },
            ],
            button: { label: "Xem báo cáo", to: { name: "manager.reports.index" } },
        },

        {
            id: "n7",
            type: "icon",
            x: 94,
            y: 590,
            w: 208,
            icon: "user",
            label: "Nhân viên",
        },

        {
            id: "n8",
            type: "icon",
            x: 94,
            y: 727,
            w: 208,
            icon: "shield",
            label: "Admin",
        },

        {
            id: "n9",
            type: "card",
            x: 424,
            y: 531,
            w: 400,
            icon: "settings",
            title: "Việc thêm cần làm",
            teaser: "Trưởng phòng ban",
            items: [
                { icon: "messageCircle", label: "Trao đổi, xử lý vướng mắc" },
                { icon: "gitBranch", label: "Nhận / từ chối việc phòng khác chuyển tới" },
                { icon: "clipboardCheck", label: "Xác nhận kết quả trước khi tổng kết" },
                { icon: "shield", label: "Duyệt bài đăng vi phạm trong phòng" },
            ],
        },

        {
            id: "n10",
            type: "group",
            x: 872,
            y: 555,
            w: 472,
            label: "XEM THÊM",
            children: [
                {
                    id: "n11",
                    icon: "barChart",
                    title: "KPI phòng ban",
                    teaser: "Chỉ tiêu tháng này",
                },
                {
                    id: "n12",
                    icon: "layers",
                    title: "Sơ đồ & lịch biểu dự án",
                    teaser: "Kéo thả theo tiến độ",
                },
                {
                    id: "n13",
                    icon: "lock",
                    title: "Tài khoản dùng chung",
                    teaser: "Trong phạm vi phòng ban",
                },
            ],
        },
    ],
    edges: [
        { id: "e1", from: "n1", to: "n2" },
        {
            id: "e2",
            from: "n2",
            to: "n5",
            fromAnchor: "bottom",
            toAnchor: "top",
        },
        { id: "e3", from: "n3", to: "n4" },
        { id: "e4", from: "n4", to: "n5" },
        { id: "e5", from: "n5", to: "n6" },
        { id: "e6", from: "n7", to: "n9" },
        { id: "e7", from: "n8", to: "n9" },
        {
            id: "e8",
            from: "n6",
            to: "n10",
            fromAnchor: "bottom",
            toAnchor: "top",
        },
        {
            id: "e9",
            from: "n9",
            to: "n4",
            fromAnchor: "top",
            toAnchor: "bottom",
        },
    ],
};

// ── Tab 4: Nhân viên ─────────────────────────────────────────────────────────────
const memberData = {
    title: "Nhân viên",
    description:
        "Nhận việc được giao, cập nhật tiến độ hàng ngày, trao đổi khi vướng mắc và xem lại kết quả của chính mình.",
    canvasW: 1750,
    canvasH: 920,
    nodes: [
        {
            id: "n1",
            type: "icon",
            x: 756,
            y: 0,
            w: 148,
            icon: "user",
            label: "Nhân viên",
        },

        {
            id: "n2",
            type: "card",
            x: 928,
            y: 7,
            w: 208,
            compact: true,
            items: [{ icon: "bell", label: "Nhận thông báo việc mới" }],
        },

        {
            id: "n3",
            type: "card",
            x: 0,
            y: 236,
            w: 380,
            icon: "users",
            title: "Được giao việc",
            teaser: "Trưởng nhóm / Trưởng phòng",
            items: [
                { icon: "layoutList", label: "Tên & mô tả công việc" },
                { icon: "clock", label: "Hạn hoàn thành" },
                { icon: "star", label: "Mức độ quan trọng" },
            ],
        },

        {
            id: "n4",
            type: "card",
            x: 424,
            y: 165,
            w: 400,
            icon: "fileText",
            title: "Cập nhật tiến độ hàng ngày",
            teaser: "Việc của tôi",
            items: [
                { icon: "trendingUp", label: "% tiến độ đã xong" },
                { icon: "clock", label: "Thời gian thực tế đã làm" },
                { icon: "paperclip", label: "Đính kèm tài liệu, hình ảnh" },
                { icon: "fileText", label: "Nhật ký ngày" },
            ],
            button: { label: "Nộp báo cáo tiến độ", to: { name: "manager.project.tasks" } },
        },

        {
            id: "n5",
            type: "card",
            x: 872,
            y: 165,
            w: 400,
            icon: "messageCircle",
            title: "Trao đổi & báo vướng mắc",
            teaser: "Với người giao việc",
            items: [
                { icon: "messageCircle", label: "Bình luận trực tiếp trên việc" },
                { icon: "shield", label: "Báo cáo khó khăn, chậm tiến độ" },
                { icon: "pencil", label: "Đề xuất chỉnh sửa mô tả" },
            ],
        },

        {
            id: "n6",
            type: "card",
            x: 1320,
            y: 165,
            w: 400,
            highlight: true,
            icon: "star",
            title: "Kết quả của tôi",
            teaser: "Sau khi hoàn thành",
            items: [
                { icon: "star", label: "Điểm chất lượng được chấm" },
                { icon: "percent", label: "% hoàn thành đúng hạn" },
                { icon: "barChart", label: "Điểm KPI tháng này" },
            ],
        },

        {
            id: "n7",
            type: "icon",
            x: 94,
            y: 590,
            w: 208,
            icon: "users",
            label: "Trưởng phòng ban",
        },

        {
            id: "n8",
            type: "icon",
            x: 94,
            y: 727,
            w: 208,
            icon: "users",
            label: "Đồng nghiệp",
        },

        {
            id: "n9",
            type: "card",
            x: 424,
            y: 531,
            w: 400,
            icon: "settings",
            title: "Việc thêm cần làm",
            teaser: "Nhân viên",
            items: [
                { icon: "megaphone", label: "Đăng tin lên bảng tin nội bộ" },
                { icon: "eye", label: "Xem báo cáo được chia sẻ" },
                { icon: "home", label: "Xem tất cả việc của tôi" },
            ],
            button: { label: "Việc của tôi", to: { name: "manager.project.tasks" } },
        },

        {
            id: "n10",
            type: "group",
            x: 872,
            y: 555,
            w: 472,
            label: "XEM THÊM",
            children: [
                {
                    id: "n11",
                    icon: "barChart",
                    title: "Điểm KPI",
                    teaser: "Điểm tháng này của tôi",
                },
                {
                    id: "n12",
                    icon: "calendar",
                    title: "Nhật ký ngày",
                    teaser: "Ghi chép hàng ngày",
                },
                {
                    id: "n13",
                    icon: "trendingUp",
                    title: "Tổng kết tuần",
                    teaser: "Kết quả 7 ngày qua",
                },
            ],
        },
    ],
    edges: [
        { id: "e1", from: "n1", to: "n2" },
        {
            id: "e2",
            from: "n2",
            to: "n5",
            fromAnchor: "bottom",
            toAnchor: "top",
        },
        { id: "e3", from: "n3", to: "n4" },
        { id: "e4", from: "n4", to: "n5" },
        { id: "e5", from: "n5", to: "n6" },
        { id: "e6", from: "n7", to: "n9" },
        { id: "e7", from: "n8", to: "n9" },
        {
            id: "e8",
            from: "n6",
            to: "n10",
            fromAnchor: "bottom",
            toAnchor: "top",
        },
        {
            id: "e9",
            from: "n9",
            to: "n4",
            fromAnchor: "top",
            toAnchor: "bottom",
        },
    ],
};

const tabData = {
    super_admin: superAdminData,
    admin: adminData,
    department_director: departmentDirectorData,
    member: memberData,
};

function handleBtnClick({ btn }) {
    if (btn?.to) router.push(btn.to);
}
</script>

<template>
    <section class="home">
        <PageHeader
            title="Quy trình"
            icon="gitBranch"
            description="VA Workspace — Luồng công việc theo từng vai trò, từ Super Admin đến Nhân viên."
            :breadcrumbs="[{ label: 'Quy trình' }]"
        />
        <div class="home__content">
            <DataStreamWidget
                :tabs="tabs"
                :tab-data="tabData"
                @btn-click="handleBtnClick"
            />
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
    .home {
        padding: var(--space-2);
    }
}
</style>
