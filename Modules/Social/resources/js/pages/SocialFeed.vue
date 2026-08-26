<script setup>
import { computed, nextTick, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import AppIcon from "@/components/AppIcon.vue";
import PageHeader from "@/components/PageHeader.vue";
import { showClientToast } from "@/lib/clientToast";
import { useAuthStore } from "@modules/Identity/resources/js/stores/auth.js";
import SocialBirthdayPanel from "../components/SocialBirthdayPanel.vue";
import SocialHashtagPanel from "../components/SocialHashtagPanel.vue";
import SocialPinnedPanel from "../components/SocialPinnedPanel.vue";
import SocialPostCard from "../components/SocialPostCard.vue";
import SocialPostComposer from "../components/SocialPostComposer.vue";
import SocialProfilePanel from "../components/SocialProfilePanel.vue";

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const posts = ref([]);
const loading = ref(false);
const loadingMore = ref(false);
const page = ref(1);
const lastPage = ref(1);
const pinnedPanel = ref(null);
const systemPanel = ref(null);
const hashtagPanel = ref(null);
const composer = ref(null);
const profilePanel = ref(null);
const searchQuery = ref("");
const feedScope = ref("all");
const postScope = ref("company");
const wallUserId = ref(null);
const wallProfile = ref(null);
const focusedPostId = ref(null);
const openCommentsPostId = ref(null);
const suppressFeedReload = ref(false);
const hashtagTotal = ref(0);

const activeHashtag = computed(() => {
    const raw = route.query.hashtag;
    const value = Array.isArray(raw) ? raw[0] : raw;
    if (typeof value !== "string") return "";
    return value.trim().replace(/^#/, "").toLowerCase();
});
const activeHashtagLabel = computed(() => {
    const fromPost = posts.value
        .flatMap((post) => post.hashtags ?? [])
        .find((tag) => tag.name === activeHashtag.value);
    return fromPost?.label || activeHashtag.value;
});

const departmentName = computed(() => auth.user?.department?.name ?? "");
const currentWallUserId = computed(
    () => wallUserId.value ?? auth.user?.id ?? null,
);
const wallUserName = computed(
    () => wallProfile.value?.user?.name ?? auth.user?.name ?? "",
);
const viewingPersonalWall = computed(() => postScope.value === "personal");

const feedScopes = [
    { id: "all", label: "Bảng tin" },
    { id: "mine", label: "Bài của tôi" },
    { id: "reacted", label: "Đã tương tác" },
];

const visiblePosts = computed(() => {
    const needle = searchQuery.value.trim().toLowerCase();
    if (!needle) return posts.value;

    return posts.value.filter((post) => {
        const hay = [
            post.content,
            post.author?.name,
            post.author?.department,
            post.shared_from?.content,
            post.shared_from?.author?.name,
            post.poll?.title,
            post.poll?.content,
            ...(post.poll?.options ?? []).map((option) => option.label),
        ]
            .filter(Boolean)
            .join(" ")
            .toLowerCase();

        return hay.includes(needle);
    });
});

const firstUnpinnedIndex = computed(() =>
    visiblePosts.value.findIndex((post) => !post.is_pinned),
);

function belongsToCurrentScope(post) {
    if (postScope.value === "personal") {
        const currentWall = currentWallUserId.value;
        if (
            post.post_scope !== "personal" ||
            post.wall_user?.id !== currentWall
        ) {
            return false;
        }
    } else if ((post.post_scope ?? "company") !== postScope.value) {
        return false;
    }
    if (feedScope.value === "mine") {
        return post.author?.id === auth.user?.id;
    }
    if (feedScope.value === "reacted") {
        return Boolean(post.my_reaction);
    }
    if (activeHashtag.value) {
        return (post.hashtags ?? []).some(
            (tag) => tag.name === activeHashtag.value,
        );
    }
    return true;
}

async function loadFeed(targetPage = 1) {
    const isFirstPage = targetPage === 1;
    isFirstPage ? (loading.value = true) : (loadingMore.value = true);

    try {
        const params = {
            page: targetPage,
            per_page: 10,
            scope: feedScope.value,
            post_scope: postScope.value,
        };
        if (postScope.value === "personal" && currentWallUserId.value) {
            params.wall_user_id = currentWallUserId.value;
        }
        if (activeHashtag.value) params.hashtag = activeHashtag.value;
        const { data } = await window.axios.get("/api/social/posts", {
            params,
        });

        posts.value = isFirstPage
            ? data.posts
            : [...posts.value, ...data.posts];
        page.value = data.current_page;
        lastPage.value = data.last_page;
        hashtagTotal.value = data.total ?? 0;
    } catch (error) {
        showClientToast("error", "Không thể tải bảng tin.");
    } finally {
        loading.value = false;
        loadingMore.value = false;
    }
}

function onScopeChange(scope) {
    if (feedScope.value === scope) {
        document
            .querySelector(".social-page__main")
            ?.scrollTo({ top: 0, behavior: "smooth" });
        return;
    }
    feedScope.value = scope;
}

function onTabChange(tab) {
    if (tab === "personal") {
        openPersonalWall(auth.user?.id);
        return;
    }
    if (postScope.value === tab && wallUserId.value === null) return;
    postScope.value = tab;
    wallUserId.value = null;
    wallProfile.value = null;
}

async function loadWallProfile(userId) {
    try {
        const { data } = await window.axios.get(`/api/social/walls/${userId}`);
        wallProfile.value = data;
    } catch {
        wallProfile.value = null;
        showClientToast("error", "Không thể mở tường cá nhân.");
    }
}

async function openPersonalWall(userId) {
    if (!userId) return;
    const switching =
        postScope.value !== "personal" || wallUserId.value !== userId;
    postScope.value = "personal";
    wallUserId.value = userId;
    await loadWallProfile(userId);
    if (!switching) {
        document
            .querySelector(".social-page__main")
            ?.scrollTo({ top: 0, behavior: "smooth" });
    }
}

function loadMore() {
    if (page.value < lastPage.value && !loadingMore.value) {
        loadFeed(page.value + 1);
    }
}

function refreshAnnouncementPanels() {
    pinnedPanel.value?.load();
    systemPanel.value?.load();
    hashtagPanel.value?.load();
}

function refreshProfileStats() {
    if (postScope.value === "personal" && currentWallUserId.value) {
        loadWallProfile(currentWallUserId.value).then(() =>
            profilePanel.value?.loadStats(),
        );
        return;
    }
    profilePanel.value?.loadStats();
}

function onPosted(post) {
    if (belongsToCurrentScope(post)) {
        posts.value = [post, ...posts.value];
    }
    refreshAnnouncementPanels();
    refreshProfileStats();
}

function onUpdated(updatedPost) {
    posts.value = posts.value.map((p) =>
        p.id === updatedPost.id ? updatedPost : p,
    );
    refreshAnnouncementPanels();
}

function onShared(post) {
    if (belongsToCurrentScope(post)) {
        posts.value = [post, ...posts.value];
    }
    refreshProfileStats();
}

function onDeleted(postId) {
    posts.value = posts.value.filter((p) => p.id !== postId);
    refreshAnnouncementPanels();
    refreshProfileStats();
}

function onPinned(updatedPost) {
    posts.value = posts.value.map((p) =>
        p.id === updatedPost.id ? updatedPost : p,
    );
    posts.value.sort((a, b) => Number(b.is_pinned) - Number(a.is_pinned));
    refreshAnnouncementPanels();
}

function onUnpinned(updatedPost) {
    posts.value = posts.value.map((p) =>
        p.id === updatedPost.id ? updatedPost : p,
    );
    refreshAnnouncementPanels();
}

async function scrollToPost(post) {
    const fullPost = post && typeof post === "object" ? post : null;
    const postId = fullPost?.id ?? post;
    if (!postId) return;

    if (
        fullPost?.pin_scope === "system" &&
        (postScope.value !== "company" || wallUserId.value !== null)
    ) {
        suppressFeedReload.value = true;
        postScope.value = "company";
        wallUserId.value = null;
        wallProfile.value = null;
        await loadFeed(1);
    }

    await nextTick();
    let el = document.getElementById(`social-post-${postId}`);
    if (!el && fullPost && !posts.value.some((item) => item.id === postId)) {
        posts.value = [fullPost, ...posts.value];
        await nextTick();
        el = document.getElementById(`social-post-${postId}`);
    }
    el?.scrollIntoView({ behavior: "smooth", block: "center" });
}

async function applyFocusedPost() {
    const postId = Number(route.query.post);
    if (!Number.isFinite(postId) || postId < 1) {
        focusedPostId.value = null;
        openCommentsPostId.value = null;
        return;
    }

    focusedPostId.value = postId;
    openCommentsPostId.value = postId;

    try {
        const { data } = await window.axios.get(`/api/social/posts/${postId}`);
        const post = data.post;
        if (!post) return;

        let switched = false;
        if (post.post_scope === "personal" && post.wall_user?.id) {
            if (
                postScope.value !== "personal" ||
                wallUserId.value !== post.wall_user.id
            ) {
                suppressFeedReload.value = true;
                postScope.value = "personal";
                wallUserId.value = post.wall_user.id;
                await loadWallProfile(post.wall_user.id);
                switched = true;
            }
        } else if (post.post_scope === "department") {
            if (postScope.value !== "department" || wallUserId.value !== null) {
                suppressFeedReload.value = true;
                postScope.value = "department";
                wallUserId.value = null;
                wallProfile.value = null;
                switched = true;
            }
        } else if (postScope.value !== "company" || wallUserId.value !== null) {
            suppressFeedReload.value = true;
            postScope.value = "company";
            wallUserId.value = null;
            wallProfile.value = null;
            switched = true;
        }

        if (switched) {
            await loadFeed(1);
        }

        if (!posts.value.some((item) => item.id === post.id)) {
            posts.value = [post, ...posts.value];
        }
        await scrollToPost(post);
    } catch {
        showClientToast("error", "Không tìm thấy bài viết được nhắc.");
    }
}

function setActiveHashtag(name) {
    const next = String(name || "")
        .replace(/^#/, "")
        .trim()
        .toLowerCase();
    const query = { ...route.query };
    if (next && next !== activeHashtag.value) {
        query.hashtag = next;
    } else {
        delete query.hashtag;
    }
    delete query.post;
    delete query.comment;
    router.replace({ query });
    document
        .querySelector(".social-page__main")
        ?.scrollTo({ top: 0, behavior: "smooth" });
}

function onOpenHashtag(name) {
    setActiveHashtag(name);
}

watch(feedScope, () => loadFeed(1));
watch(activeHashtag, () => loadFeed(1));
watch([postScope, wallUserId], () => {
    if (suppressFeedReload.value) {
        suppressFeedReload.value = false;
        return;
    }
    loadFeed(1);
});
watch(
    () => [route.query.post, route.query.comment],
    () => applyFocusedPost(),
);
onMounted(async () => {
    await loadFeed(1);
    await applyFocusedPost();
});
</script>

<template>
    <section class="social-page">
        <svg class="social-page__wm-defs" aria-hidden="true" focusable="false">
            <filter
                id="social-watermark-boost"
                color-interpolation-filters="sRGB"
            >
                <feColorMatrix
                    type="matrix"
                    values="0 0 0 0 0.604  0 0 0 0 0  0 0 0 0 0.212  0 0 0 20 0"
                />
            </filter>
        </svg>
        <img
            src="/images/background/background-logo.png"
            alt=""
            class="social-page__watermark"
            aria-hidden="true"
            :style="{ filter: 'url(#social-watermark-boost)' }"
        />

        <PageHeader title="Bảng tin nội bộ" icon="megaphone">
            <template #title>
                <span class="social-head">
                    <span class="social-head-brand">
                        <AppIcon name="megaphone" :size="16" />
                        Bảng tin
                        <span class="social-head-brand__sub">nội bộ</span>
                    </span>
                    <label class="social-head-search">
                        <AppIcon name="search" :size="16" />
                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Tìm bài viết..."
                            aria-label="Tìm bài viết, người đăng"
                        />
                    </label>
                </span>
            </template>
        </PageHeader>

        <div class="social-page__body hide-scrollbar">
            <aside
                class="social-page__rail social-page__rail--left hide-scrollbar"
            >
                <SocialProfilePanel
                    ref="profilePanel"
                    :scope="feedScope"
                    :post-scope="postScope"
                    :wall-profile="wallProfile"
                    @update:scope="onScopeChange"
                    @update:post-scope="onTabChange"
                    @open-wall="openPersonalWall"
                />
            </aside>

            <div class="social-page__main hide-scrollbar">
                <nav
                    class="social-page__scope-bar hide-scrollbar"
                    aria-label="Chọn tường"
                >
                    <button
                        type="button"
                        class="social-page__scope-btn"
                        :class="{
                            'social-page__scope-btn--active':
                                postScope === 'company',
                        }"
                        :aria-current="
                            postScope === 'company' ? 'page' : undefined
                        "
                        @click="onTabChange('company')"
                    >
                        Bảng tin
                    </button>
                    <button
                        v-if="departmentName"
                        type="button"
                        class="social-page__scope-btn"
                        :class="{
                            'social-page__scope-btn--active':
                                postScope === 'department',
                        }"
                        :aria-current="
                            postScope === 'department' ? 'page' : undefined
                        "
                        @click="onTabChange('department')"
                    >
                        Tường {{ departmentName }}
                    </button>
                    <button
                        type="button"
                        class="social-page__scope-btn"
                        :class="{
                            'social-page__scope-btn--active':
                                postScope === 'personal' &&
                                wallProfile?.is_own !== false,
                        }"
                        :aria-current="
                            postScope === 'personal' &&
                            wallProfile?.is_own !== false
                                ? 'page'
                                : undefined
                        "
                        @click="onTabChange('personal')"
                    >
                        Tường của tôi
                    </button>
                    <button
                        v-if="
                            viewingPersonalWall &&
                            wallProfile &&
                            !wallProfile.is_own
                        "
                        type="button"
                        class="social-page__scope-btn social-page__scope-btn--active"
                        aria-current="page"
                    >
                        Tường {{ wallUserName }}
                    </button>
                </nav>

                <nav
                    class="social-page__scope-bar hide-scrollbar"
                    aria-label="Lọc bảng tin"
                >
                    <button
                        v-for="item in feedScopes"
                        :key="item.id"
                        type="button"
                        class="social-page__scope-btn"
                        :class="{
                            'social-page__scope-btn--active':
                                feedScope === item.id,
                        }"
                        :aria-current="
                            feedScope === item.id ? 'page' : undefined
                        "
                        @click="onScopeChange(item.id)"
                    >
                        {{ item.label }}
                    </button>
                </nav>

                <SocialPostComposer
                    :key="`${postScope}-${currentWallUserId ?? 'none'}`"
                    ref="composer"
                    :author-avatar-url="auth.user?.avatar_url"
                    :author-name="auth.user?.name"
                    :default-scope="postScope"
                    :department-name="departmentName"
                    :wall-user-id="currentWallUserId"
                    :wall-user-name="wallUserName"
                    @posted="onPosted"
                />

                <div
                    v-if="activeHashtag"
                    class="social-page__hashtag-filter"
                >
                    <span class="social-page__hashtag-filter-icon" aria-hidden="true">
                        <AppIcon name="hash" :size="16" />
                    </span>
                    <p class="social-page__hashtag-filter-text">
                        Đang xem
                        <strong>#{{ activeHashtagLabel }}</strong>
                        <span v-if="!loading && hashtagTotal > 0">
                            · {{ hashtagTotal }} bài
                        </span>
                    </p>
                    <button
                        type="button"
                        class="social-page__hashtag-filter-clear"
                        @click="setActiveHashtag('')"
                    >
                        Bỏ lọc
                    </button>
                </div>

                <div v-if="loading" class="social-page__loading">
                    Đang tải bảng tin...
                </div>

                <div v-else class="social-page__list">
                    <template
                        v-for="(post, index) in visiblePosts"
                        :key="post.id"
                    >
                        <div
                            v-if="
                                index === firstUnpinnedIndex &&
                                firstUnpinnedIndex > 0
                            "
                            class="social-page__feed-split"
                            role="separator"
                        >
                            Bài viết mới
                        </div>
                        <div :id="`social-post-${post.id}`">
                            <SocialPostCard
                                :post="post"
                                :post-scope="postScope"
                                :department-name="departmentName"
                                :open-comments="openCommentsPostId === post.id"
                                :highlighted="focusedPostId === post.id"
                                @deleted="onDeleted"
                                @pinned="onPinned"
                                @unpinned="onUnpinned"
                                @shared="onShared"
                                @updated="onUpdated"
                                @open-wall="openPersonalWall"
                                @open-hashtag="onOpenHashtag"
                            />
                        </div>
                    </template>

                    <div
                        v-if="visiblePosts.length === 0"
                        class="social-page__empty"
                    >
                        <AppIcon name="megaphone" :size="32" />
                        <p v-if="searchQuery.trim()">
                            Không tìm thấy bài viết khớp với “{{
                                searchQuery.trim()
                            }}”.
                        </p>
                        <p v-else-if="activeHashtag">
                            Chưa có bài viết nào gắn #{{ activeHashtagLabel }}
                            trên tường này.
                        </p>
                        <p v-else-if="feedScope === 'mine'">
                            Bạn chưa đăng bài viết nào.
                        </p>
                        <p v-else-if="feedScope === 'reacted'">
                            Bạn chưa tương tác bài viết nào.
                        </p>
                        <p
                            v-else-if="
                                postScope === 'personal' &&
                                wallProfile &&
                                !wallProfile.is_own
                            "
                        >
                            Chưa có bài viết nào trên tường của
                            {{ wallUserName }}. Hãy là người đăng đầu tiên!
                        </p>
                        <p v-else-if="postScope === 'personal'">
                            Chưa có bài viết nào trên tường của bạn. Hãy là
                            người đăng đầu tiên!
                        </p>
                        <p v-else-if="postScope === 'department'">
                            Chưa có bài viết nào trên tường
                            {{ departmentName }}. Hãy là người đăng đầu tiên!
                        </p>
                        <p v-else>
                            Chưa có bài viết nào trên bảng tin. Hãy là người
                            đăng đầu tiên!
                        </p>
                    </div>

                    <button
                        v-if="page < lastPage && !searchQuery.trim()"
                        type="button"
                        class="social-page__load-more"
                        :disabled="loadingMore"
                        @click="loadMore"
                    >
                        {{ loadingMore ? "Đang tải..." : "Xem thêm" }}
                    </button>
                </div>
            </div>

            <aside
                class="social-page__rail social-page__rail--right hide-scrollbar"
            >
                <SocialHashtagPanel
                    ref="hashtagPanel"
                    :post-scope="postScope"
                    :wall-user-id="currentWallUserId"
                    :active-hashtag="activeHashtag"
                    @select="onOpenHashtag"
                />
                <SocialPinnedPanel
                    v-if="postScope !== 'personal'"
                    ref="pinnedPanel"
                    variant="company"
                    :department-scope="postScope === 'department'"
                    :department-name="departmentName"
                    @select="scrollToPost"
                />
                <SocialPinnedPanel
                    ref="systemPanel"
                    variant="system"
                    @select="scrollToPost"
                />
                <SocialBirthdayPanel v-if="postScope === 'company'" />
            </aside>
        </div>
    </section>
</template>

<style scoped>
.social-page {
    height: 100%;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
    padding: var(--space-2);
    overflow: hidden;
    position: relative;
    isolation: isolate;
    background: var(--color-surface-muted);
}

/* Cùng PNG watermark trang login. Ảnh nguồn alpha ~5% (đủ trên nền brand tối
   sau invert). Ở nền sáng: boost alpha + nhuộm primary-900, không invert. */
.social-page__wm-defs {
    position: absolute;
    width: 0;
    height: 0;
    overflow: hidden;
}

.social-page__watermark {
    position: absolute;
    inset: 0;
    z-index: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    transform: scale(1.05);
    pointer-events: none;
    opacity: 0.045;
}

.social-head {
    display: flex;
    align-items: center;
    gap: var(--space-4);
    width: 100%;
    min-width: 0;
}

.social-head-brand {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
    padding-right: var(--space-4);
    color: var(--color-primary);
    box-shadow: 1px 0 0 var(--color-border);
    animation: social-head-in 0.7s cubic-bezier(0.22, 1, 0.36, 1) backwards;
    transition: color 0.45s ease;
}

.social-head-brand__sub {
    font-weight: 500;
    color: var(--color-primary-700);
    opacity: 0.78;
}

.social-head-brand :deep(.app-icon) {
    animation: social-megaphone 3.4s ease-in-out 0.8s infinite;
    transform-origin: 30% 70%;
}

.social-head-brand:hover :deep(.app-icon) {
    animation-play-state: paused;
    transform: rotate(-12deg) scale(1.08);
}

.social-head-search {
    display: flex;
    flex: 0 0 20rem;
    align-items: center;
    gap: 0.5rem;
    width: 20rem;
    height: 2rem;
    padding: 0 0.75rem;
    border-radius: var(--radius-full);
    background: var(--color-surface);
    color: var(--color-text-muted);
    box-shadow: inset 0 0 0 1px var(--color-border);
    transform-origin: left center;
    animation: social-search-in 1.35s cubic-bezier(0.22, 1, 0.36, 1) 0.28s
        backwards;
    transition:
        box-shadow 0.65s ease,
        color 0.65s ease,
        background 0.65s ease,
        transform 0.65s ease;
}

.social-head-search :deep(.app-icon) {
    transition:
        transform 0.7s ease,
        color 0.65s ease;
}

.social-head-search:hover {
    box-shadow: inset 0 0 0 1px
        color-mix(in srgb, var(--color-primary) 35%, var(--color-border));
}

.social-head-search:focus-within {
    color: var(--color-primary);
    background: color-mix(
        in srgb,
        var(--color-primary) 5%,
        var(--color-surface)
    );
    box-shadow:
        inset 0 0 0 1px var(--color-primary),
        0 0 0 4px color-mix(in srgb, var(--color-primary) 12%, transparent);
}

.social-head-search:focus-within :deep(.app-icon) {
    transform: scale(1.12);
    color: var(--color-primary);
}

.social-head-search input {
    flex: 1;
    min-width: 0;
    border: none;
    background: transparent;
    color: var(--color-text);
    font-family: inherit;
    font-size: 0.8125rem;
    font-weight: 500;
    outline: none;
    appearance: none;
    transition: color 0.55s ease;
}

.social-head-search input::placeholder {
    color: var(--color-text-muted);
    transition:
        opacity 0.7s ease,
        letter-spacing 0.7s ease;
}

.social-head-search:focus-within input::placeholder {
    opacity: 0.45;
    letter-spacing: 0.02em;
}

@keyframes social-head-in {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes social-search-in {
    from {
        opacity: 0;
        width: 12rem;
        flex-basis: 12rem;
        transform: translateY(8px) scaleX(0.82);
    }

    to {
        opacity: 1;
        width: 20rem;
        flex-basis: 20rem;
        transform: translateY(0) scaleX(1);
    }
}

@keyframes social-megaphone {
    0%,
    100% {
        transform: rotate(0);
    }

    18% {
        transform: rotate(-14deg);
    }

    36% {
        transform: rotate(10deg);
    }

    54% {
        transform: rotate(-7deg);
    }

    72% {
        transform: rotate(4deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .social-head-brand,
    .social-head-search,
    .social-head-brand :deep(.app-icon),
    .social-head-search :deep(.app-icon) {
        animation: none;
        transition: none;
    }
}

.social-page__body {
    position: relative;
    z-index: 1;
    flex: 1;
    min-height: 0;
    display: grid;
    grid-template-columns: 1fr;
    grid-template-areas:
        "main"
        "right";
    gap: var(--space-3);
    width: 100%;
    overflow: hidden;
}

.social-page__rail {
    min-width: 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
    overflow-y: auto;
}

.social-page__rail--left {
    display: none;
    grid-area: left;
}

.social-page__rail--right {
    grid-area: right;
}

.social-page__main {
    grid-area: main;
    min-width: 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
    overflow-y: auto;
}

.social-page__hashtag-filter {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    flex-shrink: 0;
    padding: var(--space-2) var(--space-3);
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    position: relative;
}

.social-page__hashtag-filter::before {
    content: "";
    position: absolute;
    top: var(--space-2);
    bottom: var(--space-2);
    left: var(--space-2);
    width: 3px;
    border-radius: 0;
    background: var(--color-primary);
}

.social-page__hashtag-filter-icon {
    display: flex;
    color: var(--color-primary);
    margin-left: var(--space-2);
}

.social-page__hashtag-filter-text {
    flex: 1;
    min-width: 0;
    margin: 0;
    font-size: 0.8125rem;
    color: var(--color-text);
}

.social-page__hashtag-filter-text strong {
    color: var(--color-primary);
}

.social-page__hashtag-filter-clear {
    flex-shrink: 0;
    border: none;
    background: var(--color-surface-muted);
    color: var(--color-text);
    font-family: inherit;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.3rem 0.7rem;
    border-radius: var(--radius-full);
    cursor: pointer;
}

.social-page__scope-bar {
    display: flex;
    gap: var(--space-1);
    flex-shrink: 0;
    padding: var(--space-1);
    background: var(--color-surface);
    border-radius: var(--radius-full);
    box-shadow: var(--shadow-sm);
    overflow-x: auto;
}

.social-page__scope-btn {
    flex: 1 0 auto;
    border: none;
    background: none;
    color: var(--color-text-muted);
    font-family: inherit;
    font-size: 0.8125rem;
    font-weight: 600;
    padding: var(--space-2) var(--space-3);
    border-radius: var(--radius-full);
    cursor: pointer;
    white-space: nowrap;
}

.social-page__scope-btn:hover {
    color: var(--color-primary);
    background: var(--color-primary-surface);
}

.social-page__scope-btn--active {
    background: var(--color-primary);
    color: var(--color-on-primary);
}

.social-page__list {
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
}

.social-page__feed-split {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-1) 0;
    color: var(--color-text-muted);
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.social-page__feed-split::before,
.social-page__feed-split::after {
    content: "";
    flex: 1;
    height: 1px;
    background: var(--color-border);
}

.social-page__loading {
    text-align: center;
    color: var(--color-text-muted);
    padding: var(--space-6);
}

.social-page__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--space-3);
    text-align: center;
    color: var(--color-text-muted);
    padding: var(--space-8) var(--space-6);
}

.social-page__load-more {
    align-self: center;
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    color: var(--color-text);
    padding: var(--space-2) var(--space-4);
    border-radius: var(--radius-md);
    cursor: pointer;
    font-size: 0.875rem;
}

.social-page__load-more:hover {
    background: var(--color-surface-muted);
}

.social-page__load-more:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

@media (min-width: 769px) {
    .social-page__body {
        grid-template-columns: minmax(0, 1fr) 16rem;
        grid-template-areas: "main right";
    }
}

@media (min-width: 1280px) {
    .social-page__body {
        grid-template-columns: 16.5rem minmax(0, 1fr) 17rem;
        grid-template-areas: "left main right";
    }

    .social-page__rail--left {
        display: flex;
    }

    .social-page__scope-bar {
        display: none;
    }
}

@media (max-width: 768px) {
    .social-page__body {
        overflow-y: auto;
        overflow-x: hidden;
    }

    .social-page__main,
    .social-page__rail--right {
        overflow-y: visible;
    }

    .social-head-search {
        flex: 1 1 auto;
        width: auto;
        max-width: 22rem;
    }
}

@media (max-width: 480px) {
    .social-page {
        padding: var(--space-2);
    }

    .social-head {
        gap: var(--space-2);
    }

    .social-head-brand {
        padding-right: var(--space-2);
    }

    .social-head-search {
        max-width: none;
    }
}
</style>
