<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import AppIcon from "@/components/AppIcon.vue";
import { showClientToast } from "@/lib/clientToast";
import { useChatStore } from "@modules/Chat/resources/js/store/chatStore";
import { useAuthStore } from "@modules/Identity/resources/js/stores/auth.js";
import SocialColleagueSearch from "./SocialColleagueSearch.vue";

const props = defineProps({
    scope: { type: String, default: "all" },
    postScope: { type: String, default: "company" },
    wallProfile: { type: Object, default: null },
    collapsed: { type: Boolean, default: false },
});

const emit = defineEmits(["update:scope", "update:postScope", "open-wall", "focus-search"]);

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const chat = useChatStore();
const colleagueSearch = ref(null);
const stats = ref({ posts_count: 0, reactions_received: 0, comments_count: 0 });
const statsLoaded = ref(false);
const emailCopied = ref(false);
let copiedTimer = null;

const viewingOther = computed(() =>
    Boolean(props.wallProfile && !props.wallProfile.is_own),
);
const profileUser = computed(() => props.wallProfile?.user ?? auth.user);
const displayName = computed(() => profileUser.value?.name ?? "Người dùng");
const firstName = computed(() => {
    const parts = displayName.value.trim().split(/\s+/).filter(Boolean);
    return parts[parts.length - 1] || displayName.value;
});
const initial = computed(
    () => displayName.value.trim().charAt(0).toUpperCase() || "?",
);
const departmentName = computed(() =>
    viewingOther.value
        ? (profileUser.value?.department ?? "")
        : (auth.user?.department?.name ?? ""),
);
const hasDepartment = computed(() => Boolean(auth.user?.department?.id));
const wallItems = computed(() => [
    {
        id: "company",
        icon: "megaphone",
        label: "Bảng tin chung",
    },
    ...(hasDepartment.value
        ? [
              {
                  id: "department",
                  icon: "building",
                  label: auth.user?.department?.name
                      ? `Tường ${auth.user.department.name}`
                      : "Tường phòng ban",
              },
          ]
        : []),
]);
const userEmail = computed(() =>
    viewingOther.value ? "" : (auth.user?.email ?? ""),
);
const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return "Chào buổi sáng";
    if (hour < 18) return "Chào buổi chiều";
    return "Chào buổi tối";
});

const statItems = computed(() => [
    {
        id: "mine",
        icon: "pencil",
        value: stats.value.posts_count,
        label: "Bài viết",
        scope: "mine",
    },
    {
        id: "reacted",
        icon: "heart",
        value: stats.value.reactions_received,
        label: "Tương tác",
        scope: "reacted",
    },
    {
        id: "comments",
        icon: "messageCircle",
        value: stats.value.comments_count,
        label: "Bình luận",
        scope: null,
    },
]);

function formatCount(value) {
    return Number(value || 0).toLocaleString("vi-VN");
}

function setScope(scope) {
    if (!scope || viewingOther.value) return;
    emit("update:scope", scope);
}

function messageThem() {
    const id = profileUser.value?.id;
    if (id) chat.openWithUser(id);
}

async function copyWallLink() {
    const id = profileUser.value?.id;
    if (!id) return;
    const href = router.resolve({
        name: "social.feed",
        query: { ...route.query, wall: String(id) },
    }).href;
    try {
        await navigator.clipboard.writeText(new URL(href, window.location.origin).toString());
        showClientToast("success", "Đã sao chép liên kết tường.");
    } catch {
        showClientToast("error", "Không thể sao chép liên kết.");
    }
}

function focusSearch() {
    colleagueSearch.value?.focus();
}

defineExpose({ loadStats, focusSearch });

function setPostScope(scope) {
    emit("update:postScope", scope);
}

function openOwnWall() {
    if (auth.user?.id) emit("open-wall", auth.user.id);
}

function openGroups() {
    router.push({ name: "social.groups.index" });
}

function isWallActive(itemId) {
    if (itemId === "personal") {
        return props.postScope === "personal" && !viewingOther.value;
    }
    return props.postScope === itemId;
}

async function copyEmail() {
    if (!userEmail.value) return;
    try {
        await navigator.clipboard.writeText(userEmail.value);
        emailCopied.value = true;
        if (copiedTimer) clearTimeout(copiedTimer);
        copiedTimer = setTimeout(() => {
            emailCopied.value = false;
        }, 1800);
        showClientToast("success", "Đã sao chép email.");
    } catch {
        showClientToast("error", "Không thể sao chép email.");
    }
}

async function loadStats() {
    if (viewingOther.value) {
        stats.value = {
            posts_count: props.wallProfile?.stats?.posts_count ?? 0,
            reactions_received:
                props.wallProfile?.stats?.reactions_received ?? 0,
            comments_count: props.wallProfile?.stats?.comments_count ?? 0,
        };
        statsLoaded.value = true;
        return;
    }
    try {
        const { data } = await window.axios.get("/api/social/me/stats");
        stats.value = {
            posts_count: data.posts_count ?? 0,
            reactions_received: data.reactions_received ?? 0,
            comments_count: data.comments_count ?? 0,
        };
    } catch {
        stats.value = {
            posts_count: 0,
            reactions_received: 0,
            comments_count: 0,
        };
    } finally {
        statsLoaded.value = true;
    }
}

onMounted(loadStats);
onBeforeUnmount(() => {
    if (copiedTimer) clearTimeout(copiedTimer);
});
watch(() => props.scope, loadStats);
watch(() => props.wallProfile, loadStats);
</script>

<template>
    <div class="profile-panel" :class="{ 'profile-panel--collapsed': collapsed }">
        <section class="profile-card" :aria-label="`Hồ sơ ${displayName}`">
            <div v-if="!collapsed" class="profile-card__banner" aria-hidden="true">
                <span class="profile-card__banner-aurora" />
                <span
                    class="profile-card__banner-orb profile-card__banner-orb--a"
                />
                <span
                    class="profile-card__banner-orb profile-card__banner-orb--b"
                />
                <span
                    class="profile-card__banner-orb profile-card__banner-orb--c"
                />
                <span class="profile-card__banner-grid" />
                <span class="profile-card__banner-sheen" />
            </div>

            <div class="profile-card__body">
                <div class="profile-card__avatar-wrap">
                    <span
                        v-if="!collapsed"
                        class="profile-card__avatar-halo"
                        aria-hidden="true"
                    />
                    <img
                        v-if="profileUser?.avatar_url"
                        class="profile-card__avatar"
                        :src="profileUser.avatar_url"
                        :alt="`Ảnh đại diện của ${displayName}`"
                    />
                    <div
                        v-else
                        class="profile-card__avatar profile-card__avatar--placeholder"
                    >
                        {{ initial }}
                    </div>
                    <span
                        v-if="!viewingOther"
                        class="profile-card__presence"
                        aria-hidden="true"
                    />
                </div>

                <template v-if="!collapsed">
                    <p class="profile-card__hello">
                        <AppIcon
                            v-if="!viewingOther"
                            class="profile-card__hello-icon"
                            name="sparkles"
                            :size="12"
                        />
                        <span>
                            {{
                                viewingOther
                                    ? `Tường của ${firstName}`
                                    : `${greeting}, ${firstName}`
                            }}
                        </span>
                    </p>
                    <button
                        v-if="!viewingOther"
                        type="button"
                        class="profile-card__name profile-card__name-btn"
                        @click="openOwnWall"
                    >
                        <span class="profile-card__name-text">{{ displayName }}</span>
                    </button>
                    <h2 v-else class="profile-card__name">{{ displayName }}</h2>
                    <div v-if="viewingOther && !collapsed" class="profile-card__actions">
                        <button type="button" class="profile-card__action" @click="messageThem">
                            Nhắn tin
                        </button>
                        <button type="button" class="profile-card__action" @click="copyWallLink">
                            Sao chép liên kết
                        </button>
                        <button type="button" class="profile-card__action" @click="setPostScope('company')">
                            Về bảng tin
                        </button>
                    </div>

                    <button
                        v-if="departmentName && !viewingOther"
                        type="button"
                        class="profile-card__meta profile-card__meta--link"
                        :aria-current="
                            props.postScope === 'department' ? 'page' : undefined
                        "
                        @click="setPostScope('department')"
                    >
                        <AppIcon name="building" :size="14" />
                        <span>Tường {{ departmentName }}</span>
                        <AppIcon
                            class="profile-card__meta-arrow"
                            name="chevronRight"
                            :size="14"
                        />
                    </button>
                    <p v-else-if="departmentName" class="profile-card__meta">
                        <AppIcon name="building" :size="14" />
                        <span>{{ departmentName }}</span>
                    </p>

                    <button
                        v-if="userEmail"
                        type="button"
                        class="profile-card__email"
                        :class="{ 'profile-card__email--copied': emailCopied }"
                        :aria-label="`Sao chép email ${userEmail}`"
                        @click="copyEmail"
                    >
                        <AppIcon
                            :name="emailCopied ? 'check' : 'mail'"
                            :size="14"
                        />
                        <span>{{ emailCopied ? 'Đã sao chép' : userEmail }}</span>
                    </button>

                    <div class="profile-card__stats" aria-label="Thống kê bảng tin">
                        <component
                            :is="item.scope && !viewingOther ? 'button' : 'div'"
                            v-for="item in statItems"
                            :key="item.id"
                            :type="item.scope ? 'button' : undefined"
                            class="profile-card__stat"
                            :class="{
                                'profile-card__stat--active':
                                    item.scope && props.scope === item.scope,
                                'profile-card__stat--static': !item.scope,
                            }"
                            @click="setScope(item.scope)"
                        >
                            <span class="profile-card__stat-icon" aria-hidden="true">
                                <AppIcon :name="item.icon" :size="14" />
                            </span>
                            <span
                                class="profile-card__stat-value"
                                :class="{
                                    'profile-card__stat-value--loading':
                                        !statsLoaded,
                                }"
                            >
                                {{ statsLoaded ? formatCount(item.value) : '—' }}
                            </span>
                            <span class="profile-card__stat-label">
                                {{ item.label }}
                            </span>
                        </component>
                    </div>
                </template>
            </div>
        </section>

        <nav
            v-if="wallItems.length"
            class="profile-nav"
            aria-label="Chọn tường"
        >
            <p v-if="!collapsed" class="profile-nav__title">Bảng tin</p>
            <button
                v-for="item in wallItems"
                :key="item.id"
                type="button"
                class="profile-nav__btn"
                :class="{ 'profile-nav__btn--active': isWallActive(item.id) }"
                :aria-current="isWallActive(item.id) ? 'page' : undefined"
                :aria-label="collapsed ? item.label : null"
                @click="setPostScope(item.id)"
            >
                <span class="profile-nav__icon" aria-hidden="true">
                    <AppIcon :name="item.icon" :size="16" />
                </span>
                <span v-if="!collapsed" class="profile-nav__label">{{ item.label }}</span>
                <AppIcon
                    v-if="!collapsed"
                    class="profile-nav__chevron"
                    name="chevronRight"
                    :size="14"
                />
            </button>
        </nav>

        <nav class="profile-nav" aria-label="Không gian của tôi">
            <p v-if="!collapsed" class="profile-nav__title">Không gian của tôi</p>
            <button
                type="button"
                class="profile-nav__btn"
                :class="{
                    'profile-nav__btn--active': isWallActive('personal'),
                }"
                :aria-current="isWallActive('personal') ? 'page' : undefined"
                :aria-label="collapsed ? 'Tường của tôi' : null"
                @click="setPostScope('personal')"
            >
                <span class="profile-nav__icon" aria-hidden="true">
                    <AppIcon name="user" :size="16" />
                </span>
                <span v-if="!collapsed" class="profile-nav__label">Tường của tôi</span>
                <AppIcon
                    v-if="!collapsed"
                    class="profile-nav__chevron"
                    name="chevronRight"
                    :size="14"
                />
            </button>
            <button
                type="button"
                class="profile-nav__btn"
                :aria-label="collapsed ? 'Nhóm của tôi' : null"
                @click="openGroups"
            >
                <span class="profile-nav__icon" aria-hidden="true">
                    <AppIcon name="users" :size="16" />
                </span>
                <span v-if="!collapsed" class="profile-nav__label">Nhóm của tôi</span>
                <AppIcon
                    v-if="!collapsed"
                    class="profile-nav__chevron"
                    name="chevronRight"
                    :size="14"
                />
            </button>
            <button
                v-if="collapsed"
                type="button"
                class="profile-nav__btn"
                aria-label="Tìm đồng nghiệp"
                @click="emit('focus-search')"
            >
                <span class="profile-nav__icon" aria-hidden="true">
                    <AppIcon name="users" :size="16" />
                </span>
            </button>
            <SocialColleagueSearch
                v-else
                ref="colleagueSearch"
                @select="emit('open-wall', $event)"
            />
        </nav>
    </div>
</template>

<style scoped>
.profile-panel {
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
}

/* ---------- Thẻ hồ sơ ---------- */

.profile-card {
    position: relative;
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow:
        var(--shadow-sm),
        0 8px 24px -14px color-mix(in srgb, var(--color-primary) 45%, transparent);
    transition:
        transform 0.35s cubic-bezier(0.22, 1, 0.36, 1),
        box-shadow 0.35s ease;
    animation: profile-rise 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
}

.profile-card:hover {
    transform: translateY(-2px);
    box-shadow:
        var(--shadow-sm),
        0 16px 34px -16px color-mix(in srgb, var(--color-primary) 60%, transparent);
}

/* ---------- Banner động ---------- */

.profile-card__banner {
    position: relative;
    height: 5.5rem;
    overflow: hidden;
    background: linear-gradient(
        135deg,
        var(--color-primary-900) 0%,
        var(--color-primary-700) 46%,
        var(--color-primary-500) 100%
    );
}

.profile-card__banner-aurora {
    position: absolute;
    inset: -40%;
    background:
        radial-gradient(
            38% 52% at 20% 30%,
            color-mix(in srgb, var(--color-secondary-400) 55%, transparent) 0%,
            transparent 70%
        ),
        radial-gradient(
            42% 48% at 78% 68%,
            color-mix(in srgb, var(--color-gold-400) 48%, transparent) 0%,
            transparent 72%
        );
    filter: blur(6px);
    opacity: 0.85;
    animation: profile-aurora 14s ease-in-out infinite;
}

.profile-card__banner-grid {
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        -24deg,
        transparent,
        transparent 11px,
        color-mix(in srgb, var(--color-on-primary) 10%, transparent) 11px,
        color-mix(in srgb, var(--color-on-primary) 10%, transparent) 12px
    );
    opacity: 0.55;
    animation: profile-grid-drift 18s linear infinite;
}

.profile-card__banner-sheen {
    position: absolute;
    top: 0;
    bottom: 0;
    left: -60%;
    width: 45%;
    background: linear-gradient(
        100deg,
        transparent 0%,
        color-mix(in srgb, var(--color-on-primary) 26%, transparent) 50%,
        transparent 100%
    );
    transform: skewX(-18deg);
    animation: profile-sheen 6.5s ease-in-out infinite;
}

.profile-card__banner-orb {
    position: absolute;
    border-radius: var(--radius-full);
    background: color-mix(in srgb, var(--color-on-primary) 20%, transparent);
    animation: profile-float 9s ease-in-out infinite;
}

.profile-card__banner-orb--a {
    width: 5.5rem;
    height: 5.5rem;
    top: -2.2rem;
    right: -1.1rem;
}

.profile-card__banner-orb--b {
    width: 3.25rem;
    height: 3.25rem;
    bottom: -1.4rem;
    left: 1.25rem;
    background: color-mix(in srgb, var(--color-on-primary) 14%, transparent);
    animation-delay: -3s;
    animation-duration: 11s;
}

.profile-card__banner-orb--c {
    width: 1.5rem;
    height: 1.5rem;
    top: 1.1rem;
    left: 38%;
    background: color-mix(in srgb, var(--color-gold-200) 55%, transparent);
    animation-delay: -6s;
    animation-duration: 8s;
}

/* ---------- Thân thẻ ---------- */

.profile-card__body {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0 var(--space-3) var(--space-4);
    text-align: center;
}

.profile-panel--collapsed .profile-card__body {
    padding: var(--space-2);
}

/* ---------- Ảnh đại diện ---------- */

.profile-card__avatar-wrap {
    position: relative;
    z-index: 1;
    margin-top: -2.75rem;
    border-radius: var(--radius-full);
}

.profile-panel--collapsed .profile-card__avatar-wrap {
    margin-top: 0;
}

.profile-card__avatar-halo {
    position: absolute;
    inset: -5px;
    border-radius: var(--radius-full);
    background: conic-gradient(
        from 0deg,
        var(--color-primary-500),
        var(--color-gold-400),
        var(--color-secondary-400),
        var(--color-primary-500)
    );
    opacity: 0;
    transform: scale(0.94);
    transition:
        opacity 0.35s ease,
        transform 0.35s ease;
    animation: profile-spin 7s linear infinite;
}

.profile-card:hover .profile-card__avatar-halo {
    opacity: 0.85;
    transform: scale(1);
}

.profile-card__avatar {
    position: relative;
    display: block;
    width: 5.25rem;
    height: 5.25rem;
    border-radius: var(--radius-full);
    object-fit: cover;
    box-shadow:
        0 0 0 4px var(--color-surface),
        var(--shadow-md);
    transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1);
}

.profile-card:hover .profile-card__avatar {
    transform: scale(1.04);
}

.profile-panel--collapsed .profile-card__avatar {
    width: 2.25rem;
    height: 2.25rem;
    box-shadow:
        0 0 0 2px var(--color-surface),
        var(--shadow-sm);
}

.profile-card__avatar--placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(
        140deg,
        var(--color-primary-100),
        var(--color-primary-50)
    );
    color: var(--color-primary);
    font-size: 1.75rem;
    font-weight: 800;
}

.profile-card__presence {
    position: absolute;
    right: 0.25rem;
    bottom: 0.25rem;
    z-index: 2;
    width: 0.875rem;
    height: 0.875rem;
    border-radius: var(--radius-full);
    background: var(--color-success);
    box-shadow: 0 0 0 3px var(--color-surface);
}

.profile-card__presence::after {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: var(--radius-full);
    background: var(--color-success);
    animation: profile-pulse 2.4s ease-out infinite;
}

.profile-panel--collapsed .profile-card__presence {
    right: -1px;
    bottom: -1px;
    width: 0.625rem;
    height: 0.625rem;
    box-shadow: 0 0 0 2px var(--color-surface);
}

/* ---------- Lời chào + tên ---------- */

.profile-card__hello {
    display: inline-flex;
    align-items: center;
    gap: var(--space-1);
    margin: var(--space-3) 0 0;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--color-text-muted);
    animation: profile-fade-up 0.45s 0.05s ease both;
}

.profile-card__hello-icon {
    color: var(--color-gold-500);
    animation: profile-twinkle 3.2s ease-in-out infinite;
}

.profile-card__actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.35rem;
    margin-top: 0.35rem;
}

.profile-card__action {
    border: none;
    background: var(--color-primary-surface);
    color: var(--color-primary);
    font-family: inherit;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.3rem 0.6rem;
    border-radius: var(--radius-full);
    cursor: pointer;
}

.profile-card__action:hover {
    background: var(--color-primary);
    color: var(--color-on-primary);
}

.profile-card__name {
    margin: 2px 0 0;
    font-size: 1.0625rem;
    font-weight: 800;
    line-height: 1.3;
    color: var(--color-text);
    word-break: break-word;
    animation: profile-fade-up 0.45s 0.1s ease both;
}

.profile-card__name-btn {
    border: none;
    background: none;
    padding: 0;
    font-family: inherit;
    cursor: pointer;
}

.profile-card__name-text {
    position: relative;
    display: inline-block;
    padding-bottom: 3px;
    background-image: linear-gradient(
        90deg,
        var(--color-primary),
        var(--color-primary-500)
    );
    background-repeat: no-repeat;
    background-position: 0 100%;
    background-size: 0 2px;
    transition:
        background-size 0.35s cubic-bezier(0.22, 1, 0.36, 1),
        color 0.2s ease;
}

.profile-card__name-btn:hover .profile-card__name-text,
.profile-card__name-btn:focus-visible .profile-card__name-text {
    background-size: 100% 2px;
    color: var(--color-primary);
}

/* ---------- Dòng phòng ban / email ---------- */

.profile-card__meta,
.profile-card__email {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-1);
    max-width: 100%;
    margin-top: var(--space-2);
    padding: 3px var(--space-2);
    border-radius: var(--radius-full);
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--color-text-muted);
    animation: profile-fade-up 0.45s 0.15s ease both;
}

.profile-card__meta--link,
.profile-card__email {
    border: none;
    background: transparent;
    font-family: inherit;
    cursor: pointer;
    transition:
        color 0.2s ease,
        background 0.25s ease;
}

.profile-card__meta--link:hover,
.profile-card__meta--link[aria-current="page"],
.profile-card__email:hover {
    color: var(--color-primary);
    background: var(--color-primary-surface);
}

.profile-card__meta-arrow {
    opacity: 0;
    transform: translateX(-4px);
    transition:
        opacity 0.25s ease,
        transform 0.25s ease;
}

.profile-card__meta--link:hover .profile-card__meta-arrow,
.profile-card__meta--link:focus-visible .profile-card__meta-arrow {
    opacity: 1;
    transform: translateX(0);
}

.profile-card__meta span,
.profile-card__email span {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.profile-card__email {
    animation-delay: 0.2s;
}

.profile-card__email--copied {
    color: var(--color-success-tint-fg);
    background: var(--color-success-tint-bg);
}

.profile-card__email--copied :deep(.app-icon) {
    animation: profile-pop 0.4s cubic-bezier(0.22, 1.6, 0.36, 1) both;
}

/* ---------- Thống kê ---------- */

.profile-card__stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: var(--space-1);
    width: 100%;
    margin-top: var(--space-4);
    padding-top: var(--space-3);
    box-shadow: 0 -1px 0 var(--color-border);
    animation: profile-fade-up 0.45s 0.25s ease both;
}

.profile-card__stat {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    min-width: 0;
    border: none;
    background: none;
    padding: var(--space-2) var(--space-1);
    border-radius: var(--radius-md);
    font-family: inherit;
    cursor: pointer;
    overflow: hidden;
    transition:
        background 0.25s ease,
        transform 0.25s cubic-bezier(0.22, 1, 0.36, 1);
}

.profile-card__stat:hover {
    background: var(--color-surface-muted);
    transform: translateY(-2px);
}

.profile-card__stat:active {
    transform: translateY(0);
}

.profile-card__stat--active {
    background: var(--color-primary-surface);
}

.profile-card__stat--active::after {
    content: "";
    position: absolute;
    bottom: 4px;
    left: 50%;
    width: 1.25rem;
    height: 2px;
    border-radius: var(--radius-full);
    background: var(--color-primary);
    transform: translateX(-50%) scaleX(0);
    transform-origin: center;
    animation: profile-underline 0.35s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}

.profile-card__stat--static {
    cursor: default;
}

.profile-card__stat--static:hover {
    background: none;
    transform: none;
}

.profile-card__stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: var(--radius-full);
    background: var(--color-surface-muted);
    color: var(--color-text-muted);
    transition:
        background 0.25s ease,
        color 0.25s ease,
        transform 0.35s cubic-bezier(0.22, 1.4, 0.36, 1);
}

.profile-card__stat:hover .profile-card__stat-icon,
.profile-card__stat--active .profile-card__stat-icon {
    background: color-mix(
        in srgb,
        var(--color-primary) 12%,
        var(--color-surface)
    );
    color: var(--color-primary);
    transform: scale(1.1);
}

.profile-card__stat-value {
    font-size: 1.0625rem;
    font-weight: 800;
    line-height: 1.2;
    color: var(--color-primary);
    font-variant-numeric: tabular-nums;
}

.profile-card__stat-value--loading {
    color: var(--color-text-muted);
    animation: profile-breathe 1.4s ease-in-out infinite;
}

.profile-card__stat-label {
    font-size: 0.625rem;
    font-weight: 600;
    letter-spacing: 0.01em;
    color: var(--color-text-muted);
}

/* ---------- Điều hướng ---------- */

.profile-nav {
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    padding: var(--space-2);
    display: flex;
    flex-direction: column;
    gap: 2px;
    box-shadow: var(--shadow-sm);
    animation: profile-rise 0.5s 0.08s cubic-bezier(0.22, 1, 0.36, 1) both;
}

.profile-nav__title {
    margin: var(--space-1) var(--space-2) var(--space-1);
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--color-text-muted);
}

.profile-nav__btn {
    position: relative;
    display: flex;
    align-items: center;
    gap: var(--space-2);
    width: 100%;
    border: none;
    background: none;
    color: var(--color-text);
    font-family: inherit;
    text-align: left;
    padding: var(--space-2);
    padding-left: calc(var(--space-2) + 3px + var(--space-2));
    border-radius: var(--radius-md);
    cursor: pointer;
    overflow: hidden;
    transition:
        background 0.25s ease,
        color 0.25s ease,
        transform 0.25s cubic-bezier(0.22, 1, 0.36, 1);
}

.profile-panel--collapsed .profile-nav__btn {
    justify-content: center;
    padding-left: var(--space-2);
}

/* Thanh dấu bên trái: trượt vào khi hover / đang chọn */
.profile-nav__btn::before {
    content: "";
    position: absolute;
    top: var(--space-2);
    bottom: var(--space-2);
    left: var(--space-2);
    width: 3px;
    border-radius: var(--radius-full);
    background: var(--color-primary);
    transform: scaleY(0);
    transform-origin: center;
    transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1);
}

.profile-nav__btn:hover {
    background: var(--color-surface-muted);
    transform: translateX(2px);
}

.profile-nav__btn:hover::before {
    transform: scaleY(0.5);
}

.profile-nav__btn--active {
    background: var(--color-primary-surface);
    color: var(--color-primary);
}

.profile-nav__btn--active::before {
    transform: scaleY(1);
}

.profile-nav__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    flex-shrink: 0;
    border-radius: var(--radius-md);
    background: var(--color-surface-muted);
    color: var(--color-text-muted);
    transition:
        background 0.25s ease,
        color 0.25s ease,
        transform 0.4s cubic-bezier(0.22, 1.4, 0.36, 1);
}

.profile-nav__btn:hover .profile-nav__icon {
    transform: scale(1.08) rotate(-4deg);
}

.profile-nav__btn--active .profile-nav__icon {
    background: color-mix(
        in srgb,
        var(--color-primary) 14%,
        var(--color-surface)
    );
    color: var(--color-primary);
}

.profile-nav__label {
    min-width: 0;
    flex: 1;
    font-size: 0.8125rem;
    font-weight: 700;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.profile-nav__chevron {
    flex-shrink: 0;
    color: var(--color-text-muted);
    opacity: 0;
    transform: translateX(-6px);
    transition:
        opacity 0.25s ease,
        transform 0.25s cubic-bezier(0.22, 1, 0.36, 1);
}

.profile-nav__btn:hover .profile-nav__chevron,
.profile-nav__btn:focus-visible .profile-nav__chevron,
.profile-nav__btn--active .profile-nav__chevron {
    opacity: 1;
    transform: translateX(0);
}

.profile-nav__btn--active .profile-nav__chevron {
    color: var(--color-primary);
}

/* ---------- Focus ---------- */

.profile-nav__btn:focus-visible,
.profile-card__stat:focus-visible,
.profile-card__email:focus-visible,
.profile-card__meta--link:focus-visible,
.profile-card__name-btn:focus-visible {
    outline: 2px solid var(--color-primary);
    outline-offset: 2px;
}

/* ---------- Keyframes ---------- */

@keyframes profile-rise {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes profile-fade-up {
    from {
        opacity: 0;
        transform: translateY(6px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes profile-aurora {
    0%,
    100% {
        transform: translate3d(0, 0, 0) scale(1);
    }

    33% {
        transform: translate3d(6%, -4%, 0) scale(1.12);
    }

    66% {
        transform: translate3d(-5%, 5%, 0) scale(1.05);
    }
}

@keyframes profile-grid-drift {
    from {
        background-position: 0 0;
    }

    to {
        background-position: 48px 0;
    }
}

@keyframes profile-sheen {
    0%,
    62% {
        left: -60%;
    }

    100% {
        left: 130%;
    }
}

@keyframes profile-float {
    0%,
    100% {
        transform: translate3d(0, 0, 0);
    }

    50% {
        transform: translate3d(0, 7px, 0);
    }
}

@keyframes profile-spin {
    from {
        transform: rotate(0) scale(1);
    }

    to {
        transform: rotate(360deg) scale(1);
    }
}

@keyframes profile-pulse {
    0% {
        opacity: 0.55;
        transform: scale(1);
    }

    70%,
    100% {
        opacity: 0;
        transform: scale(2.3);
    }
}

@keyframes profile-twinkle {
    0%,
    100% {
        opacity: 1;
        transform: scale(1) rotate(0);
    }

    45% {
        opacity: 0.55;
        transform: scale(0.85) rotate(-12deg);
    }
}

@keyframes profile-pop {
    from {
        transform: scale(0.4);
    }

    to {
        transform: scale(1);
    }
}

@keyframes profile-underline {
    to {
        transform: translateX(-50%) scaleX(1);
    }
}

@keyframes profile-breathe {
    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: 0.4;
    }
}

/* ---------- Responsive ---------- */

@media (max-width: 768px) {
    .profile-card__banner {
        height: 4.5rem;
    }

    .profile-card__avatar {
        width: 4.5rem;
        height: 4.5rem;
    }

    .profile-card__avatar-wrap {
        margin-top: -2.25rem;
    }
}

@media (max-width: 480px) {
    .profile-card__body {
        padding: 0 var(--space-2) var(--space-3);
    }

    .profile-card__stats {
        margin-top: var(--space-3);
    }

    .profile-nav__btn {
        padding-left: calc(var(--space-2) + 3px + var(--space-2));
    }
}

/* ---------- Giảm chuyển động ---------- */

@media (prefers-reduced-motion: reduce) {
    .profile-card,
    .profile-nav,
    .profile-card__hello,
    .profile-card__name,
    .profile-card__meta,
    .profile-card__email,
    .profile-card__stats,
    .profile-card__banner-aurora,
    .profile-card__banner-grid,
    .profile-card__banner-sheen,
    .profile-card__banner-orb,
    .profile-card__avatar-halo,
    .profile-card__hello-icon,
    .profile-card__presence::after,
    .profile-card__stat--active::after,
    .profile-card__stat-value--loading,
    .profile-card__email--copied :deep(.app-icon) {
        animation: none;
    }

    .profile-card,
    .profile-card__avatar,
    .profile-card__avatar-halo,
    .profile-nav__btn,
    .profile-nav__btn::before,
    .profile-nav__icon,
    .profile-nav__chevron,
    .profile-card__email,
    .profile-card__meta--link,
    .profile-card__meta-arrow,
    .profile-card__name-text,
    .profile-card__stat,
    .profile-card__stat-icon {
        transition: none;
    }

    .profile-card:hover,
    .profile-card__stat:hover,
    .profile-nav__btn:hover {
        transform: none;
    }

    .profile-card__stat--active::after {
        transform: translateX(-50%) scaleX(1);
    }

    .profile-nav__btn--active::before {
        transform: scaleY(1);
    }
}
</style>
