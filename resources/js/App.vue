<script setup>
//
// Root component. Layout tổng dùng flex + overflow nội bộ để tránh
// scroll toàn trang (quy tắc responsive + hạn chế scroll).
//
// Trang cần đăng nhập (meta.requiresAuth) được bọc trong AppLayout (sidebar
// + topbar); trang guest (login, callback...) render trực tiếp, không sidebar.
// Riêng route đánh dấu meta.employeeMobile (trang chủ "của tôi" + bảng tin/
// chấm công/nghỉ phép/lịch sử) đổi sang EmployeeMobileLayout (bottom nav) khi
// đang ở viewport ≤768px — áp dụng cho MỌI tài khoản, không riêng nhân viên
// thường (xem useEmployeeMobileView.js). Desktop (>768px) luôn dùng AppLayout.
//
import { onBeforeUnmount, onMounted, watch } from "vue";
import { useRoute } from "vue-router";
import AppLayout from "./components/AppLayout.vue";
import ToastHost from "./components/ToastHost.vue";
import PwaPushPrompt from "./components/PwaPushPrompt.vue";
import PwaUpdatePrompt from "./components/PwaUpdatePrompt.vue";
import EmployeeMobileLayout from "@modules/Attendance/resources/js/layouts/EmployeeMobileLayout.vue";
import { useEmployeeMobileView } from "./composables/useEmployeeMobileView";
import { syncEmployeeMobileViewport } from "./composables/useEmployeeMobileViewport";
import {
    isPwaStandalone,
    PWA_DISPLAY_MODE_MEDIA,
    setPwaShellSurface,
} from "./lib/pwaStandalone";
import { syncEmployeeSafeAreaInsets } from "./lib/employeeSafeArea";
import { syncEmployeeDeviceOsClass } from "./lib/employeeDeviceOs";
import { onAppResume } from "./lib/appResume";
import { useAuthStore } from "@modules/Identity/resources/js/stores/auth.js";

const route = useRoute();
const auth = useAuthStore();
const { isEmployeeMobile } = useEmployeeMobileView();

// Đang dùng bản lưu cũ của tài khoản (mở app lúc offline / server chưa trả lời):
// có mạng lại hoặc quay lại app thì lấy dữ liệu thật để quyền và menu không bị lỗi thời.
const stopResumeRefresh = onAppResume(() => {
    if (auth.offlineSnapshot) {
        auth.fetchMe({ force: true }).catch(() => {});
    }
});
onBeforeUnmount(stopResumeRefresh);

function syncPwaShellSurface() {
    const root = document.documentElement;
    const employeeTabShell = Boolean(
        route.meta.requiresAuth &&
        route.meta.employeeMobile &&
        isEmployeeMobile.value,
    );
    root.style.setProperty(
        "--toast-shell-bottom-inset",
        employeeTabShell ? "var(--employee-tabbar-block-size)" : "0px",
    );

    root.classList.toggle(
        "employee-mobile-browser",
        employeeTabShell && !isPwaStandalone(),
    );
    root.classList.toggle(
        "employee-mobile-pwa",
        employeeTabShell && isPwaStandalone(),
    );
    syncEmployeeDeviceOsClass();
    syncEmployeeSafeAreaInsets();
    if (employeeTabShell) {
        syncEmployeeMobileViewport();
    }

    if (route.meta.requiresAuth) {
        if (employeeTabShell) {
            setPwaShellSurface("employee");
        } else {
            setPwaShellSurface("app");
        }
        return;
    }
    setPwaShellSurface("guest");
}

watch([() => route.fullPath, isEmployeeMobile], syncPwaShellSurface, {
    immediate: true,
});

let pwaDisplayModeMq;
let onPwaDisplayModeChange;

onMounted(() => {
    if (
        typeof window === "undefined" ||
        typeof window.matchMedia !== "function"
    )
        return;
    pwaDisplayModeMq = window.matchMedia(PWA_DISPLAY_MODE_MEDIA);
    onPwaDisplayModeChange = () => syncPwaShellSurface();
    if (typeof pwaDisplayModeMq.addEventListener === "function") {
        pwaDisplayModeMq.addEventListener("change", onPwaDisplayModeChange);
    } else if (typeof pwaDisplayModeMq.addListener === "function") {
        pwaDisplayModeMq.addListener(onPwaDisplayModeChange);
    }
});

onBeforeUnmount(() => {
    if (!pwaDisplayModeMq || !onPwaDisplayModeChange) return;
    if (typeof pwaDisplayModeMq.removeEventListener === "function") {
        pwaDisplayModeMq.removeEventListener("change", onPwaDisplayModeChange);
    } else if (typeof pwaDisplayModeMq.removeListener === "function") {
        pwaDisplayModeMq.removeListener(onPwaDisplayModeChange);
    }
});
</script>

<template>
    <div class="app-shell">
        <router-view v-slot="{ Component, route }">
            <EmployeeMobileLayout
                v-if="
                    route.meta.requiresAuth &&
                    route.meta.employeeMobile &&
                    isEmployeeMobile
                "
                class="app-shell__auth-content"
            >
                <component :is="Component" />
            </EmployeeMobileLayout>
            <AppLayout
                v-else-if="route.meta.requiresAuth"
                class="app-shell__auth-content"
            >
                <component :is="Component" />
            </AppLayout>
            <div v-else class="app-shell__content app-shell__content--guest">
                <component :is="Component" />
            </div>
        </router-view>
        <ToastHost />
        <PwaPushPrompt />
        <PwaUpdatePrompt />
    </div>
</template>

<style>
/* Khác với .app-shell__content (overflow-y: auto, dùng cho trang guest),
   trang có AppLayout tự quản lý scroll ở vùng nội dung bên trong sidebar
   nên vùng bọc ngoài chỉ cần chiếm hết chỗ còn lại, không tự cuộn. */
.app-shell__auth-content {
    flex: 1;
    min-height: 0;
    overflow: hidden;
}
</style>
