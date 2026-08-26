<script setup>
import { useRouter } from "vue-router";

const router = useRouter();

function goHome() {
    router.push({ name: "home" });
}

function goBack() {
    if (window.history.length > 1) {
        router.back();
    } else {
        goHome();
    }
}
</script>

<template>
    <section class="notfound">
        <img
            class="notfound__mark notfound__mark--tl"
            src="/images/congnghe/brand/vas-white-mark.png"
            alt=""
            aria-hidden="true"
        />
        <img
            class="notfound__mark notfound__mark--br"
            src="/images/congnghe/brand/vas-white-mark.png"
            alt=""
            aria-hidden="true"
        />

        <div class="notfound__content">
            <img
                class="notfound__art"
                src="/images/congnghe/brand/404.png"
                alt="404 - Trang không tìm thấy"
            />

            <div class="notfound__actions">
                <button type="button" class="notfound__btn notfound__btn--ghost" @click="goBack">
                    Quay lại
                </button>
                <button type="button" class="notfound__btn notfound__btn--primary" @click="goHome">
                    Về trang tổng quan
                </button>
            </div>
        </div>
    </section>
</template>

<style scoped>
.notfound {
    position: relative;
    height: 100%;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: var(--color-primary-900);
    padding: var(--space-5);
}

/* Hoạ tiết trang trí: 2 logo VAS trắng lớn, mờ, đặt ở góc — không lặp kín nền */
.notfound__mark {
    position: absolute;
    width: min(38vw, 420px);
    height: auto;
    opacity: 0.07;
    pointer-events: none;
    user-select: none;
}

.notfound__mark--tl {
    top: -6%;
    left: -6%;
    transform: rotate(-8deg);
}

.notfound__mark--br {
    bottom: -10%;
    right: -6%;
    transform: rotate(10deg);
}

@media (max-width: 768px) {
    .notfound__mark {
        width: min(55vw, 320px);
    }
}

.notfound::before {
    content: "";
    position: absolute;
    inset: 0;
    background: radial-gradient(
        60% 60% at 50% 38%,
        color-mix(in srgb, var(--color-primary-700) 55%, transparent) 0%,
        transparent 70%
    );
    pointer-events: none;
}

.notfound__content {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--space-6);
    max-width: 90vw;
}

.notfound__art {
    width: min(420px, 78vw);
    height: auto;
    filter: drop-shadow(0 20px 40px rgba(0, 0, 0, 0.35));
}

.notfound__actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: var(--space-3);
}

.notfound__btn {
    font-family: var(--font-family-base);
    font-size: 0.95rem;
    font-weight: 600;
    padding: var(--space-3) var(--space-6);
    border-radius: var(--radius-full);
    border: 2px solid transparent;
    cursor: pointer;
    transition: transform 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
}

.notfound__btn--primary {
    background: var(--color-on-primary);
    color: var(--color-primary-900);
    box-shadow: var(--shadow-lg);
}

.notfound__btn--primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 16px 36px rgba(0, 0, 0, 0.3);
}

.notfound__btn--ghost {
    background: transparent;
    color: var(--color-on-primary);
    border-color: color-mix(in srgb, var(--color-on-primary) 45%, transparent);
}

.notfound__btn--ghost:hover {
    background: color-mix(in srgb, var(--color-on-primary) 12%, transparent);
}

@media (max-width: 768px) {
    .notfound {
        padding: var(--space-4);
    }

    .notfound__content {
        gap: var(--space-5);
    }
}

@media (max-width: 480px) {
    .notfound__btn {
        flex: 1 1 auto;
        text-align: center;
        padding: var(--space-3) var(--space-4);
    }
}
</style>
