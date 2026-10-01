<script setup>
//
// Trang đăng nhập — 2 lối SSO, không có form user/password.
// Bấm nút -> điều hướng full-page (window.location) tới /auth/{hrm,google}/redirect
// vì đây là OAuth-like redirect thật, không phải gọi API qua axios.
//
// Vì sao có cả nút Google trực tiếp: /sso/authorize bên HRM nằm sau middleware
// auth, nên khi HRM còn session nó phát JWT ngay cho tài khoản đang đăng nhập
// mà không hiện màn hình chọn tài khoản. Muốn đổi sang tài khoản khác thì đi
// đường Google trực tiếp (có prompt=select_account), xem GoogleAuthController.
//
// Bố cục port từ va-hrm (resources/js/Pages/Auth/Login.tsx — React/Tailwind)
// sang CSS thuần theo theme.css của dự án này (không dùng Tailwind, xem
// .claude/CLAUDE.md mục 1/6/11).
//
import { onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { showClientToast } from "@/lib/clientToast";

const route = useRoute();
const router = useRouter();

// Lỗi từ HrmSsoController (redirect ?error=...) hiện qua toast thay vì
// khối alert tĩnh trong card — xem resources/js/components/ToastHost.vue.
// Xoá "error" khỏi URL ngay sau khi hiện để F5/back không hiện lại toast cũ.
onMounted(() => {
    const error = route.query.error;
    if (typeof error === "string" && error !== "") {
        showClientToast("error", error);
        const { error: _omit, ...rest } = route.query;
        router.replace({ query: rest });
    }
});

function ssoUrl(base, extra = {}) {
    const params = new URLSearchParams();

    const redirect =
        typeof route.query.redirect === "string" ? route.query.redirect : null;
    if (redirect) {
        params.set("redirect", redirect);
    }

    for (const [key, value] of Object.entries(extra)) {
        params.set(key, value);
    }

    const query = params.toString();
    return query === "" ? base : `${base}?${query}`;
}

function loginWithHrmSso() {
    window.location.href = ssoUrl("/auth/hrm/redirect");
}

// switch=1: nếu còn session Workspace cũ thì controller đăng xuất trước rồi
// mới sang Google, nếu không Google sẽ trả về đúng tài khoản cũ.
function loginWithGoogle() {
    window.location.href = ssoUrl("/auth/google/redirect", { switch: "1" });
}
</script>

<template>
    <div class="login">
        <img
            src="/images/background/background-logo.png"
            alt=""
            class="login__watermark"
            aria-hidden="true"
        />
        <div class="login__scrim" aria-hidden="true"></div>

        <div class="login__container">
            <header class="login__header">
                <img
                    src="/images/logo-2.png"
                    alt="Vietnam America Schools — Trường học của sự lắng nghe"
                    class="login__logo"
                />
                <p class="login__system-name">
                    Hệ thống Quản trị &amp; Làm việc liên phòng ban
                </p>
            </header>

            <div class="login__card">
                <h1 class="login__title">Đăng nhập</h1>

                <p class="login__subtitle">
                    Đăng nhập thông qua tài khoản mail do nhà trường cung cấp
                </p>

                <div class="login__actions">
                    <button
                        type="button"
                        class="login__btn login__btn--primary"
                        @click="loginWithHrmSso"
                    >
                        <img
                            src="/images/google.png"
                            alt=""
                            class="login__btn-icon"
                        />
                        <span>Đăng nhập qua VA-HRM</span>
                    </button>

                    <button
                        type="button"
                        class="login__btn"
                        @click="loginWithGoogle"
                    >
                        <img
                            src="/images/google.png"
                            alt=""
                            class="login__btn-icon"
                        />
                        <span>Chọn tài khoản Google khác</span>
                    </button>

                    <p class="login__hint">
                        Chọn cách thứ hai nếu hệ thống đang tự đăng nhập vào
                        một tài khoản mà bạn không muốn dùng.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.login {
    /* Trang login luôn có giao diện sáng cố định (không đổi theo dark mode
     hệ thống) — khớp bản gốc va-hrm dùng màu Tailwind cứng (bg-white,
     text-slate-900...). Khai báo token riêng ở đây thay vì dùng thẳng
     --color-text/--color-surface (đổi theo prefers-color-scheme), để card
     trắng không bị chữ/nút tối màu-trên-tối khi hệ điều hành ở dark mode. */
    --login-card-bg: #ffffff;
    --login-text: #1a1a1a;
    --login-text-muted: #6b6b6f;
    --login-border: #e5e5e8;

    position: relative;
    height: 100%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--space-5) var(--space-4);
    background: var(--color-primary-900);
}

/* Watermark: PNG tối trên nền trong suốt → invert thành họa tiết sáng mờ
   trên nền brand, giống hệt hiệu ứng "brightness-0 invert contrast-150
   opacity-100" của bản Tailwind gốc (va-hrm) viết bằng filter CSS thuần.
   Độ mờ của họa tiết đến từ chính ảnh nguồn, không giảm thêm opacity ở đây. */
.login__watermark {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    transform: scale(1.05);
    filter: brightness(0) invert(1) contrast(1.5);
    pointer-events: none;
}

.login__scrim {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        to bottom,
        color-mix(in srgb, var(--color-primary-900) 5%, transparent),
        transparent,
        color-mix(in srgb, var(--color-primary-900) 20%, transparent)
    );
    pointer-events: none;
}

.login__container {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 28rem;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.login__header {
    margin-bottom: var(--space-6);
    padding: 0 var(--space-4);
}

.login__logo {
    display: block;
    width: 100%;
    max-width: min(100%, 300px);
    height: auto;
    object-fit: contain;
    filter: drop-shadow(var(--shadow-sm));
}

.login__system-name {
    margin: var(--space-3) 0 0;
    text-align: center;
    color: #ffffff;
    font-size: 1rem;
    font-weight: 600;
    text-shadow: var(--shadow-sm);
}

.login__card {
    width: 100%;
    background: var(--login-card-bg);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    padding: var(--space-6) var(--space-5);
    text-align: center;
}

.login__title {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--login-text);
}

.login__subtitle {
    margin: var(--space-3) auto 0;
    max-width: 20rem;
    color: var(--login-text-muted);
    font-size: 0.875rem;
    line-height: 1.5;
}

.login__actions {
    margin-top: var(--space-6);
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: var(--space-3);
}

.login__btn {
    width: 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-2);
    padding: var(--space-3) var(--space-4);
    border: 1px solid var(--login-border);
    border-radius: var(--radius-md);
    background: var(--login-card-bg);
    box-shadow: var(--shadow-sm);
    color: var(--login-text);
    font-family: inherit;
    font-size: 0.9375rem;
    font-weight: 600;
    line-height: 1.4;
    cursor: pointer;
    transition:
        box-shadow 0.15s ease,
        background-color 0.15s ease;
}

.login__btn:hover {
    box-shadow: var(--shadow-md);
    background: var(--color-primary-50);
}

.login__btn--primary {
    border-color: var(--color-primary-900);
    box-shadow: var(--shadow-md);
}

.login__btn-icon {
    display: block;
    width: 1.25rem;
    height: 1.25rem;
    flex-shrink: 0;
}

.login__hint {
    margin: var(--space-1) 0 0;
    color: var(--login-text-muted);
    font-size: 0.8125rem;
    line-height: 1.5;
    text-align: center;
}

@media (max-width: 480px) {
    .login__card {
        padding: var(--space-5) var(--space-4);
    }

    .login__logo {
        max-width: 240px;
    }
}

@media (min-width: 768px) {
    .login__logo {
        max-width: 320px;
    }
}
</style>
