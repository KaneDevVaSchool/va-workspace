<script setup>
import { computed, onBeforeUnmount, onMounted, ref, useId, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import AppIcon from "@/components/AppIcon.vue";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import PageHeader from "@/components/PageHeader.vue";
import { showClientToast } from "@/lib/clientToast";
import { useAuthStore } from "@modules/Identity/resources/js/stores/auth.js";
import SocialGroupFormModal from "../components/SocialGroupFormModal.vue";
import SocialGroupMembersPanel from "../components/SocialGroupMembersPanel.vue";
import SocialGroupRequestsPanel from "../components/SocialGroupRequestsPanel.vue";
import SocialPostCard from "../components/SocialPostCard.vue";
import SocialPostComposer from "../components/SocialPostComposer.vue";

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const groupId = computed(() => Number(route.params.id));
const group = ref(null);
const loadingGroup = ref(false);
const posts = ref([]);
const loadingPosts = ref(false);
const loadingMore = ref(false);
const page = ref(1);
const lastPage = ref(1);
const formOpen = ref(false);
const membersPanel = ref(null);
const requestsPanel = ref(null);
const manageOpen = ref(false);
const manageRoot = ref(null);
const confirmDeleteOpen = ref(false);
const deleting = ref(false);
const manageMenuId = useId();

const canView = computed(
    () => group.value?.is_member || group.value?.visibility === "public",
);
const canManageGroup = computed(() =>
    Boolean(group.value?.can_manage || group.value?.can_delete),
);
const manageHasMenu = computed(() =>
    Boolean(group.value?.can_manage && group.value?.can_delete),
);
const MASCOT = "/images/congnghe/brand/vas-white-mark.png";

async function loadGroup() {
    loadingGroup.value = true;
    try {
        const { data } = await window.axios.get(
            `/api/social/groups/${groupId.value}`,
        );
        group.value = data.group;
    } catch {
        showClientToast("error", "Không tìm thấy nhóm.");
        router.push({ name: "social.groups.index" });
    } finally {
        loadingGroup.value = false;
    }
}

async function loadPosts(targetPage = 1) {
    if (!canView.value) return;
    const isFirst = targetPage === 1;
    isFirst ? (loadingPosts.value = true) : (loadingMore.value = true);
    try {
        const { data } = await window.axios.get("/api/social/posts", {
            params: {
                page: targetPage,
                per_page: 10,
                post_scope: "group",
                group_id: groupId.value,
            },
        });
        posts.value = isFirst ? data.posts : [...posts.value, ...data.posts];
        page.value = data.current_page;
        lastPage.value = data.last_page;
    } catch {
        showClientToast("error", "Không thể tải bài viết của nhóm.");
    } finally {
        loadingPosts.value = false;
        loadingMore.value = false;
    }
}

function loadMore() {
    if (page.value < lastPage.value && !loadingMore.value) {
        loadPosts(page.value + 1);
    }
}

async function joinGroup() {
    try {
        const { data } = await window.axios.post(
            `/api/social/groups/${groupId.value}/join`,
        );
        group.value = data.group;
        if (data.status === "joined") {
            showClientToast("success", "Đã tham gia nhóm.");
            loadPosts(1);
        } else {
            showClientToast(
                "success",
                "Đã gửi yêu cầu tham gia, vui lòng chờ duyệt.",
            );
        }
    } catch (error) {
        showClientToast(
            "error",
            error?.response?.data?.message ?? "Không thể tham gia nhóm.",
        );
    }
}

async function acceptInvite() {
    if (!group.value?.pending_invite_id) return;
    try {
        const { data } = await window.axios.post(
            `/api/social/groups/invites/${group.value.pending_invite_id}/accept`,
        );
        group.value = data.group;
        showClientToast("success", "Đã tham gia nhóm.");
        loadPosts(1);
    } catch (error) {
        showClientToast(
            "error",
            error?.response?.data?.message ?? "Không thể chấp nhận lời mời.",
        );
    }
}

function joinButtonLabel() {
    if (!group.value) return "Tham gia";
    if (group.value.has_pending_invite) return "Chấp nhận lời mời";
    if (group.value.visibility === "private" && group.value.has_pending_request)
        return "Đã gửi yêu cầu";
    if (group.value.visibility === "private") return "Yêu cầu tham gia";
    return "Tham gia";
}

async function leaveGroup() {
    try {
        await window.axios.post(`/api/social/groups/${groupId.value}/leave`);
        showClientToast("success", "Đã rời nhóm.");
        router.push({ name: "social.groups.index" });
    } catch (error) {
        showClientToast(
            "error",
            error?.response?.data?.message ?? "Không thể rời nhóm.",
        );
    }
}

async function deleteGroup() {
    deleting.value = true;
    try {
        await window.axios.delete(`/api/social/groups/${groupId.value}`);
        confirmDeleteOpen.value = false;
        showClientToast("success", "Đã xoá nhóm.");
        router.push({ name: "social.groups.index" });
    } catch (error) {
        showClientToast(
            "error",
            error?.response?.data?.message ?? "Không thể xoá nhóm.",
        );
    } finally {
        deleting.value = false;
    }
}

function toggleManageMenu() {
    if (manageHasMenu.value) {
        manageOpen.value = !manageOpen.value;
        return;
    }
    if (group.value?.can_manage) {
        formOpen.value = true;
        return;
    }
    if (group.value?.can_delete) {
        confirmDeleteOpen.value = true;
    }
}

function openEditFromMenu() {
    manageOpen.value = false;
    formOpen.value = true;
}

function openDeleteFromMenu() {
    manageOpen.value = false;
    confirmDeleteOpen.value = true;
}

function handleManageClickOutside(event) {
    if (
        manageOpen.value &&
        manageRoot.value &&
        !manageRoot.value.contains(event.target)
    ) {
        manageOpen.value = false;
    }
}

function handleManageKeydown(event) {
    if (event.key === "Escape") manageOpen.value = false;
}

function onGroupSaved(updatedGroup) {
    group.value = updatedGroup;
    formOpen.value = false;
}

function onPosted(post) {
    posts.value = [post, ...posts.value];
}

function onUpdated(updatedPost) {
    posts.value = posts.value.map((p) =>
        p.id === updatedPost.id ? updatedPost : p,
    );
}

function onDeleted(postId) {
    posts.value = posts.value.filter((p) => p.id !== postId);
}

function onShared(post) {
    if (post.post_scope === "group" && post.group?.id === groupId.value) {
        posts.value = [post, ...posts.value];
    }
}

function goToGroups() {
    router.push({ name: "social.groups.index" });
}

function onMembersChanged() {
    loadGroup();
}

function onRequestApproved() {
    membersPanel.value?.load();
    loadGroup();
}

watch(groupId, () => {
    manageOpen.value = false;
    loadGroup().then(() => loadPosts(1));
});

onMounted(async () => {
    document.addEventListener("mousedown", handleManageClickOutside);
    document.addEventListener("keydown", handleManageKeydown);
    await loadGroup();
    await loadPosts(1);
});

onBeforeUnmount(() => {
    document.removeEventListener("mousedown", handleManageClickOutside);
    document.removeEventListener("keydown", handleManageKeydown);
});
</script>

<template>
    <section class="group-wall">
        <svg class="group-wall__wm-defs" aria-hidden="true" focusable="false">
            <filter
                id="group-wall-watermark-boost"
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
            class="group-wall__watermark"
            aria-hidden="true"
            :style="{ filter: 'url(#group-wall-watermark-boost)' }"
        />

        <PageHeader title="Nhóm" icon="users">
            <template #title>
                <span class="group-wall-head">
                    <button
                        type="button"
                        class="group-wall__back"
                        aria-label="Quay lại danh sách nhóm"
                        @click="goToGroups"
                    >
                        <AppIcon
                            name="chevronLeft"
                            :size="16"
                            :stroke-width="1.75"
                        />
                    </button>
                    <span class="group-wall-head-brand">
                        <AppIcon name="users" :size="16" />
                        Nhóm
                        <span class="group-wall-head-brand__sub">nội bộ</span>
                    </span>
                </span>
            </template>
            <template #actions>
                <button
                    v-if="group && !group.is_member"
                    type="button"
                    class="group-wall__header-btn group-wall__header-btn--primary"
                    :disabled="
                        group.visibility === 'private' &&
                        group.has_pending_request &&
                        !group.has_pending_invite
                    "
                    @click="
                        group.has_pending_invite ? acceptInvite() : joinGroup()
                    "
                >
                    {{ joinButtonLabel() }}
                </button>
                <button
                    v-if="group?.is_member && group.my_role !== 'owner'"
                    type="button"
                    class="group-wall__header-btn"
                    @click="leaveGroup"
                >
                    Rời nhóm
                </button>
                <div
                    v-if="canManageGroup"
                    ref="manageRoot"
                    class="group-wall__manage"
                >
                    <button
                        type="button"
                        class="group-wall__icon-btn"
                        :class="{ 'group-wall__icon-btn--open': manageOpen }"
                        aria-label="Quản lý nhóm"
                        :aria-expanded="manageHasMenu ? manageOpen : undefined"
                        :aria-haspopup="manageHasMenu ? 'menu' : undefined"
                        :aria-controls="
                            manageHasMenu ? manageMenuId : undefined
                        "
                        @click="toggleManageMenu"
                    >
                        <AppIcon
                            name="pencil"
                            :size="16"
                            :stroke-width="1.75"
                        />
                    </button>
                    <div
                        v-if="manageOpen && manageHasMenu"
                        :id="manageMenuId"
                        role="menu"
                        class="group-wall__manage-menu"
                    >
                        <button
                            type="button"
                            role="menuitem"
                            class="group-wall__manage-item"
                            @click="openEditFromMenu"
                        >
                            <AppIcon
                                name="pencil"
                                :size="15"
                                :stroke-width="1.75"
                            />
                            Sửa nhóm
                        </button>
                        <button
                            type="button"
                            role="menuitem"
                            class="group-wall__manage-item group-wall__manage-item--danger"
                            @click="openDeleteFromMenu"
                        >
                            <AppIcon
                                name="trash"
                                :size="15"
                                :stroke-width="1.75"
                            />
                            Xoá nhóm
                        </button>
                    </div>
                </div>
            </template>
        </PageHeader>

        <div v-if="loadingGroup" class="group-wall__loading">Đang tải...</div>

        <div v-else-if="!group" class="group-wall__empty">
            Không tìm thấy nhóm.
        </div>

        <div v-else-if="!canView" class="group-wall__locked">
            <span class="group-wall__locked-mark" aria-hidden="true">
                <img :src="MASCOT" alt="" />
            </span>
            <p class="group-wall__locked-title">Đây là nhóm bảo mật</p>
            <p class="group-wall__locked-desc">
                Người chưa phải thành viên không xem được bài viết và danh sách
                thành viên.
                {{
                    group.has_pending_invite
                        ? "Hãy chấp nhận lời mời để tham gia."
                        : "Gửi yêu cầu, quản trị viên sẽ duyệt."
                }}
            </p>
            <button
                type="button"
                class="group-wall__header-btn group-wall__header-btn--primary"
                :disabled="
                    group.has_pending_request && !group.has_pending_invite
                "
                @click="group.has_pending_invite ? acceptInvite() : joinGroup()"
            >
                {{ joinButtonLabel() }}
            </button>
        </div>

        <div v-else class="group-wall__body hide-scrollbar">
            <div class="group-wall__main hide-scrollbar">
                <div class="group-wall__hero">
                    <div class="group-wall__cover">
                        <img
                            v-if="group.cover_url"
                            :src="group.cover_url"
                            alt=""
                        />
                        <img
                            v-else
                            :src="MASCOT"
                            alt=""
                            class="group-wall__cover-mascot"
                        />
                    </div>
                    <div class="group-wall__hero-row">
                        <div class="group-wall__avatar" aria-hidden="true">
                            <img
                                v-if="group.avatar_url"
                                :src="group.avatar_url"
                                alt=""
                            />
                            <span v-else>{{
                                (group.name || "?").charAt(0).toUpperCase()
                            }}</span>
                        </div>
                        <div class="group-wall__identity">
                            <h2 class="group-wall__name">{{ group.name }}</h2>
                            <p class="group-wall__meta">
                                <span class="group-wall__badge">
                                    <AppIcon
                                        :name="
                                            group.visibility === 'private'
                                                ? 'lock'
                                                : 'globe'
                                        "
                                        :size="13"
                                        :stroke-width="1.75"
                                    />
                                    {{
                                        group.visibility === "private"
                                            ? "Nhóm bảo mật"
                                            : "Nhóm công khai"
                                    }}
                                </span>
                                <span
                                    class="group-wall__meta-sep"
                                    aria-hidden="true"
                                    >·</span
                                >
                                <span
                                    >{{ group.members_count }} thành viên</span
                                >
                            </p>
                        </div>
                    </div>
                </div>

                <SocialPostComposer
                    :author-avatar-url="auth.user?.avatar_url"
                    :author-name="auth.user?.name"
                    default-scope="group"
                    :group-id="groupId"
                    @posted="onPosted"
                />

                <div v-if="loadingPosts" class="group-wall__loading">
                    Đang tải bài viết...
                </div>

                <div v-else class="group-wall__list">
                    <SocialPostCard
                        v-for="post in posts"
                        :key="post.id"
                        :post="post"
                        post-scope="group"
                        @deleted="onDeleted"
                        @updated="onUpdated"
                        @shared="onShared"
                    />

                    <div v-if="posts.length === 0" class="group-wall__empty">
                        Chưa có bài viết nào trong nhóm.
                    </div>

                    <button
                        v-if="page < lastPage"
                        type="button"
                        class="group-wall__load-more"
                        :disabled="loadingMore"
                        @click="loadMore"
                    >
                        {{ loadingMore ? "Đang tải..." : "Xem thêm" }}
                    </button>
                </div>
            </div>

            <aside class="group-wall__rail hide-scrollbar">
                <SocialGroupRequestsPanel
                    v-if="group.can_manage"
                    ref="requestsPanel"
                    :group-id="groupId"
                    @approved="onRequestApproved"
                />
                <SocialGroupMembersPanel
                    ref="membersPanel"
                    :group-id="groupId"
                    :can-manage="group.can_manage"
                    :my-role="group.my_role"
                    @changed="onMembersChanged"
                />
            </aside>
        </div>

        <SocialGroupFormModal
            :open="formOpen"
            :group="group"
            @close="formOpen = false"
            @saved="onGroupSaved"
        />

        <ConfirmDialog
            v-model:open="confirmDeleteOpen"
            title="Xoá nhóm"
            :description="
                group
                    ? `Xoá «${group.name}»? Thành viên và bài viết trong nhóm sẽ không còn.`
                    : ''
            "
            confirm-label="Xoá nhóm"
            danger
            :loading="deleting"
            @confirm="deleteGroup"
        />
    </section>
</template>

<style scoped>
.group-wall {
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

.group-wall__wm-defs {
    position: absolute;
    width: 0;
    height: 0;
    overflow: hidden;
}

.group-wall__watermark {
    position: absolute;
    inset: 0;
    z-index: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    pointer-events: none;
    opacity: 0.045;
}

.group-wall > :not(.group-wall__watermark):not(.group-wall__wm-defs) {
    position: relative;
    z-index: 1;
}

.group-wall-head {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    width: 100%;
    min-width: 0;
}

.group-wall__back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    flex-shrink: 0;
    border: none;
    border-radius: var(--radius-md);
    background: var(--color-surface-muted);
    color: var(--color-text);
    cursor: pointer;
}

.group-wall__back:hover {
    color: var(--color-primary);
    background: var(--color-primary-surface);
}

.group-wall-head-brand {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
    color: var(--color-primary);
}

.group-wall-head-brand__sub {
    font-weight: 500;
    color: var(--color-primary-700);
    opacity: 0.78;
}

.group-wall__header-btn {
    height: 2rem;
    padding: 0 0.875rem;
    border: none;
    border-radius: var(--radius-sm);
    background: var(--color-surface-muted);
    color: var(--color-text);
    font-family: inherit;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: inset 0 0 0 1px var(--color-border);
}

.group-wall__icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border: none;
    border-radius: var(--radius-sm);
    background: transparent;
    color: var(--color-primary);
    cursor: pointer;
}

.group-wall__icon-btn:hover,
.group-wall__icon-btn--open {
    background: color-mix(in srgb, var(--color-primary) 8%, transparent);
}

.group-wall__icon-btn:focus-visible {
    outline: 2px solid var(--color-primary);
    outline-offset: 2px;
}

.group-wall__manage {
    position: relative;
}

.group-wall__manage-menu {
    position: absolute;
    z-index: 40;
    top: calc(100% + 0.375rem);
    right: 0;
    width: 11.5rem;
    overflow: hidden;
    padding: 0.375rem 0;
    border-radius: 12px;
    background: var(--color-surface);
    box-shadow:
        inset 0 0 0 1px var(--color-border),
        var(--shadow-lg);
}

.group-wall__manage-item {
    display: flex;
    width: 100%;
    align-items: center;
    gap: 0.625rem;
    padding: 0.5rem 0.75rem;
    border: none;
    background: transparent;
    color: var(--color-text);
    font-family: inherit;
    font-size: 0.8125rem;
    font-weight: 500;
    text-align: left;
    cursor: pointer;
}

.group-wall__manage-item:hover {
    background: color-mix(in srgb, var(--color-primary) 4%, transparent);
}

.group-wall__manage-item--danger {
    color: var(--color-danger, #dc2626);
}

.group-wall__header-btn:hover:not(:disabled) {
    background: var(--color-surface);
}

.group-wall__header-btn--primary {
    background: var(--color-primary);
    color: var(--color-on-primary);
    box-shadow: none;
}

.group-wall__header-btn--primary:hover:not(:disabled) {
    filter: brightness(0.95);
}

.group-wall__header-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.group-wall__loading,
.group-wall__empty {
    text-align: center;
    color: var(--color-text-muted);
    padding: var(--space-6);
}

.group-wall__locked {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: var(--space-2);
    text-align: center;
    color: var(--color-text-muted);
    padding: var(--space-8);
}

.group-wall__locked-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: var(--color-text);
}

.group-wall__locked-desc {
    margin: 0 0 var(--space-2);
    max-width: 24rem;
}

.group-wall__body {
    flex: 1;
    min-height: 0;
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--space-3);
    overflow: hidden;
}

.group-wall__main {
    min-width: 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
    overflow-y: auto;
}

.group-wall__identity {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    gap: 0.25rem;
    padding: 0.35rem 0 0.15rem;
}

.group-wall__name {
    margin: 0;
    min-width: 0;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    color: var(--color-text);
    font-size: 1.3125rem;
    font-weight: 800;
    line-height: 1.2;
    letter-spacing: -0.02em;
}

.group-wall__meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.375rem;
    margin: 0;
    font-size: 0.8125rem;
    color: var(--color-text-muted);
}

.group-wall__badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3125rem;
    color: var(--color-text-muted);
}

.group-wall__meta-sep {
    opacity: 0.55;
}

.group-wall__locked-mark {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 6.5rem;
    height: 6.5rem;
    border-radius: var(--radius-full);
    background: linear-gradient(
        135deg,
        var(--color-primary-800),
        var(--color-primary)
    );
    box-shadow: var(--shadow-md);
}

.group-wall__locked-mark img {
    width: 4.25rem;
    height: auto;
}

.group-wall__hero {
    overflow: hidden;
    border-radius: var(--radius-lg);
    background: var(--color-surface);
    box-shadow: var(--shadow-sm);
}

.group-wall__cover {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 9.75rem;
    overflow: hidden;
    background: linear-gradient(
        135deg,
        var(--color-primary-800) 0%,
        var(--color-primary) 52%,
        var(--color-primary-400) 100%
    );
}

.group-wall__cover img:not(.group-wall__cover-mascot) {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.group-wall__cover-mascot {
    width: 6.5rem;
    height: auto;
    opacity: 0.9;
    transform: translate(16%, 12%);
}

.group-wall__hero-row {
    display: flex;
    align-items: flex-end;
    gap: var(--space-4);
    padding: 0 var(--space-4) var(--space-4);
    min-height: 4.5rem;
}

.group-wall__avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 6.75rem;
    height: 6.75rem;
    margin-top: -3.5rem;
    overflow: hidden;
    flex-shrink: 0;
    border-radius: var(--radius-full);
    background: var(--color-primary-surface);
    color: var(--color-primary);
    font-size: 1.75rem;
    font-weight: 800;
    box-shadow:
        0 0 0 4px var(--color-surface),
        var(--shadow-md);
}

.group-wall__avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.group-wall__list {
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
}

.group-wall__load-more {
    align-self: center;
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    color: var(--color-text);
    padding: var(--space-2) var(--space-4);
    border-radius: var(--radius-md);
    cursor: pointer;
    font-size: 0.875rem;
}

.group-wall__load-more:hover {
    background: var(--color-surface-muted);
}

.group-wall__load-more:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.group-wall__rail {
    min-width: 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
    overflow-y: auto;
}

@media (min-width: 769px) {
    .group-wall__body {
        grid-template-columns: minmax(0, 1fr) 21.5rem;
    }
}

@media (max-width: 640px) {
    .group-wall__cover {
        height: 7.5rem;
    }

    .group-wall__avatar {
        width: 5.25rem;
        height: 5.25rem;
        margin-top: -2.5rem;
        font-size: 1.375rem;
    }

    .group-wall__name {
        font-size: 1.125rem;
    }
}
</style>
