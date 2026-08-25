<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import AppIcon from "@/components/AppIcon.vue";
import PageHeader from "@/components/PageHeader.vue";
import { showClientToast } from "@/lib/clientToast";
import { useAuthStore } from "@modules/Identity/resources/js/stores/auth.js";
import SocialGroupFormModal from "../components/SocialGroupFormModal.vue";

const MASCOT = "/images/congnghe/brand/vas-white-mark.png";

const auth = useAuthStore();
const router = useRouter();

const tab = ref("mine");
const groups = ref([]);
const myRequests = ref([]);
const loading = ref(false);
const query = ref("");
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const perPage = ref(12);
const formOpen = ref(false);

const tabs = [
    { id: "mine", label: "Nhóm của tôi", icon: "users" },
    { id: "discover", label: "Khám phá nhóm", icon: "zoomIn" },
    { id: "requests", label: "Lời mời & yêu cầu", icon: "userPlus" },
];

async function loadGroups(targetPage = 1) {
    loading.value = true;
    try {
        if (tab.value === "requests") {
            const { data } = await window.axios.get(
                "/api/social/groups/mine/requests",
                {
                    params: { page: targetPage, per_page: perPage.value },
                },
            );
            myRequests.value = data.requests;
            page.value = data.current_page;
            lastPage.value = data.last_page;
            total.value = data.total;
            return;
        }

        const { data } = await window.axios.get("/api/social/groups", {
            params: {
                page: targetPage,
                per_page: perPage.value,
                tab: tab.value,
                q: query.value || undefined,
            },
        });
        groups.value = data.groups;
        page.value = data.current_page;
        lastPage.value = data.last_page;
        total.value = data.total;
    } catch {
        showClientToast("error", "Không thể tải danh sách nhóm.");
    } finally {
        loading.value = false;
    }
}

function setTab(id) {
    if (tab.value === id) return;
    tab.value = id;
    page.value = 1;
    query.value = "";
    loadGroups(1);
}

function openGroup(groupId) {
    router.push({ name: "social.groups.show", params: { id: groupId } });
}

function goToSocial() {
    router.push({ name: "social.feed" });
}

function groupInitial(group) {
    return (group?.name || "?").trim().charAt(0).toUpperCase() || "?";
}

function actionLabel(group) {
    if (group.is_member) return "Xem nhóm";
    if (group.has_pending_invite) return "Chấp nhận lời mời";
    if (group.visibility === "private") {
        return group.has_pending_request
            ? "Đã gửi yêu cầu"
            : "Yêu cầu tham gia";
    }
    return "Tham gia";
}

function visibilityLabel(group) {
    return group.visibility === "private" ? "Nhóm bảo mật" : "Nhóm công khai";
}

async function onGroupAction(group) {
    if (group.is_member) {
        openGroup(group.id);
        return;
    }
    if (group.has_pending_invite && group.pending_invite_id) {
        await acceptInviteById(group.pending_invite_id, group);
        return;
    }
    if (group.visibility === "private" && group.has_pending_request) return;

    try {
        const { data } = await window.axios.post(
            `/api/social/groups/${group.id}/join`,
        );
        if (data.status === "joined") {
            showClientToast("success", `Đã tham gia nhóm "${group.name}".`);
        } else {
            showClientToast(
                "success",
                `Đã gửi yêu cầu tham gia nhóm "${group.name}".`,
            );
        }
        groups.value = groups.value.map((g) =>
            g.id === group.id ? data.group : g,
        );
    } catch (error) {
        showClientToast(
            "error",
            error?.response?.data?.message ?? "Không thể thực hiện thao tác.",
        );
    }
}

async function acceptInviteById(requestId, group) {
    try {
        const { data } = await window.axios.post(
            `/api/social/groups/invites/${requestId}/accept`,
        );
        showClientToast(
            "success",
            `Đã tham gia nhóm "${group?.name ?? data.group?.name ?? ""}".`,
        );
        if (data.group) {
            groups.value = groups.value.map((g) =>
                g.id === data.group.id ? data.group : g,
            );
        }
        myRequests.value = myRequests.value.filter((r) => r.id !== requestId);
        if (data.group?.id) openGroup(data.group.id);
    } catch (error) {
        showClientToast(
            "error",
            error?.response?.data?.message ?? "Không thể chấp nhận lời mời.",
        );
    }
}

async function acceptRequest(request) {
    if (request.kind === "invite") {
        await acceptInviteById(request.id, request.group);
        return;
    }
}

async function declineInvite(request) {
    try {
        await window.axios.post(
            `/api/social/groups/invites/${request.id}/decline`,
        );
        myRequests.value = myRequests.value.filter((r) => r.id !== request.id);
        showClientToast("success", "Đã từ chối lời mời.");
    } catch {
        showClientToast("error", "Không thể từ chối lời mời.");
    }
}

async function cancelRequest(request) {
    if (request.kind === "invite") {
        await declineInvite(request);
        return;
    }
    try {
        await window.axios.delete(`/api/social/groups/requests/${request.id}`);
        myRequests.value = myRequests.value.filter((r) => r.id !== request.id);
        showClientToast("success", "Đã huỷ yêu cầu tham gia.");
    } catch {
        showClientToast("error", "Không thể huỷ yêu cầu.");
    }
}

function onGroupSaved(group) {
    formOpen.value = false;
    router.push({ name: "social.groups.show", params: { id: group.id } });
}

function goPage(target) {
    if (target < 1 || target > lastPage.value || target === page.value) return;
    loadGroups(target);
}

const emptyMessage = computed(() => {
    if (tab.value === "requests")
        return "Bạn chưa có lời mời hay yêu cầu tham gia nào.";
    if (tab.value === "mine")
        return "Bạn chưa tham gia nhóm nào. Hãy tạo nhóm mới hoặc khám phá nhóm công khai.";
    return "Chưa có nhóm nào phù hợp.";
});

const rangeLabel = computed(() => {
    if (!total.value) return "";
    const from = (page.value - 1) * perPage.value + 1;
    const to = Math.min(page.value * perPage.value, total.value);
    return `${from} - ${to} / ${total.value}`;
});

const firstName = computed(() => {
    const parts = (auth.user?.name ?? "").trim().split(/\s+/).filter(Boolean);
    return parts[parts.length - 1] || auth.user?.name || "";
});

let searchTimer = null;
watch(query, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadGroups(1), 350);
});

onMounted(() => loadGroups(1));
</script>

<template>
    <section class="groups-page">
        <svg class="groups-page__wm-defs" aria-hidden="true" focusable="false">
            <filter
                id="groups-watermark-boost"
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
            class="groups-page__watermark"
            aria-hidden="true"
            :style="{ filter: 'url(#groups-watermark-boost)' }"
        />
        <img
            :src="MASCOT"
            alt=""
            class="groups-page__mascot"
            aria-hidden="true"
        />

        <PageHeader
            title="Nhóm"
            icon="users"
            :primary-action="{
                label: 'Tạo nhóm mới',
                icon: 'plus',
                onClick: () => (formOpen = true),
            }"
        >
            <template #title>
                <span class="groups-head">
                    <span class="groups-head-brand">
                        <AppIcon name="users" :size="16" />
                        Nhóm <span class="groups-head-brand__sub">nội bộ</span>
                    </span>
                </span>
            </template>
        </PageHeader>

        <div class="groups-page__layout">
            <aside class="groups-page__sidebar hide-scrollbar">
                <section class="groups-brand" aria-label="Cộng đồng VAS">
                    <div class="groups-brand__banner" aria-hidden="true">
                        <img
                            :src="MASCOT"
                            alt=""
                            class="groups-brand__mascot"
                        />
                    </div>
                    <div class="groups-brand__body">
                        <p class="groups-brand__hello">
                            Xin chào{{ firstName ? `, ${firstName}` : "" }}
                        </p>
                        <h2 class="groups-brand__title">Cộng đồng VAS</h2>
                        <p class="groups-brand__meta">Nhóm làm việc nội bộ</p>
                    </div>
                </section>

                <nav class="groups-page__nav" aria-label="Chọn danh mục nhóm">
                    <button
                        type="button"
                        class="groups-page__nav-item groups-page__nav-item--back"
                        @click="goToSocial"
                    >
                        <span class="groups-page__nav-icon" aria-hidden="true">
                            <AppIcon
                                name="chevronLeft"
                                :size="16"
                                :stroke-width="1.75"
                            />
                        </span>
                        <span class="groups-page__nav-label"
                            >Quay lại bảng tin</span
                        >
                    </button>
                    <button
                        v-for="item in tabs"
                        :key="item.id"
                        type="button"
                        class="groups-page__nav-item"
                        :class="{
                            'groups-page__nav-item--active': tab === item.id,
                        }"
                        :aria-current="tab === item.id ? 'page' : undefined"
                        @click="setTab(item.id)"
                    >
                        <span class="groups-page__nav-icon" aria-hidden="true">
                            <AppIcon
                                :name="item.icon"
                                :size="16"
                                :stroke-width="1.75"
                            />
                        </span>
                        <span class="groups-page__nav-label">{{
                            item.label
                        }}</span>
                    </button>
                </nav>

                <label
                    v-if="tab !== 'requests'"
                    class="groups-page__search-field"
                >
                    <AppIcon name="search" :size="15" :stroke-width="1.75" />
                    <input
                        v-model="query"
                        type="search"
                        placeholder="Tìm theo tên nhóm..."
                    />
                </label>
            </aside>

            <div class="groups-page__content hide-scrollbar">
                <div
                    v-if="loading"
                    class="groups-page__grid"
                    aria-hidden="true"
                >
                    <div
                        v-for="n in 6"
                        :key="n"
                        class="group-card group-card--skeleton"
                    >
                        <div class="group-card__cover skeleton-block" />
                        <div class="group-card__avatar skeleton-block" />
                        <div class="group-card__body">
                            <div class="skeleton-line skeleton-line--title" />
                            <div class="skeleton-line skeleton-line--sub" />
                        </div>
                        <div class="skeleton-line skeleton-line--action" />
                    </div>
                </div>

                <template v-else-if="tab === 'requests'">
                    <ul v-if="myRequests.length" class="groups-page__requests">
                        <li
                            v-for="request in myRequests"
                            :key="request.id"
                            class="groups-page__request"
                        >
                            <div
                                class="groups-page__request-media"
                                aria-hidden="true"
                            >
                                <img
                                    v-if="request.group?.avatar_url"
                                    :src="request.group.avatar_url"
                                    alt=""
                                />
                                <span v-else>{{
                                    groupInitial(request.group)
                                }}</span>
                            </div>
                            <div class="groups-page__request-info">
                                <p class="groups-page__request-name">
                                    {{ request.group?.name }}
                                </p>
                                <p class="groups-page__request-message">
                                    <template v-if="request.kind === 'invite'">
                                        {{
                                            request.invited_by?.name
                                                ? `${request.invited_by.name} mời bạn tham gia`
                                                : "Bạn được mời tham gia nhóm này"
                                        }}
                                    </template>
                                    <template v-else>
                                        {{
                                            request.message ||
                                            "Đang chờ quản trị viên duyệt yêu cầu của bạn"
                                        }}
                                    </template>
                                </p>
                            </div>
                            <div class="groups-page__request-actions">
                                <template v-if="request.kind === 'invite'">
                                    <button
                                        type="button"
                                        class="groups-page__accept-btn"
                                        @click="acceptRequest(request)"
                                    >
                                        Chấp nhận
                                    </button>
                                    <button
                                        type="button"
                                        class="groups-page__cancel-btn"
                                        @click="declineInvite(request)"
                                    >
                                        Từ chối
                                    </button>
                                </template>
                                <button
                                    v-else
                                    type="button"
                                    class="groups-page__cancel-btn"
                                    @click="cancelRequest(request)"
                                >
                                    Huỷ yêu cầu
                                </button>
                            </div>
                        </li>
                    </ul>
                    <div v-else class="groups-page__empty">
                        <span
                            class="groups-page__empty-mark"
                            aria-hidden="true"
                        >
                            <img :src="MASCOT" alt="" />
                        </span>
                        <p>{{ emptyMessage }}</p>
                    </div>
                </template>

                <template v-else>
                    <div v-if="groups.length" class="groups-page__grid">
                        <article
                            v-for="(group, index) in groups"
                            :key="group.id"
                            class="group-card"
                            :style="{
                                animationDelay: `${Math.min(index, 8) * 30}ms`,
                            }"
                        >
                            <div class="group-card__cover">
                                <img
                                    v-if="group.cover_url"
                                    :src="group.cover_url"
                                    alt=""
                                />
                                <img
                                    v-else
                                    :src="MASCOT"
                                    alt=""
                                    class="group-card__cover-mascot"
                                />
                            </div>
                            <div class="group-card__avatar" aria-hidden="true">
                                <img
                                    v-if="group.avatar_url"
                                    :src="group.avatar_url"
                                    alt=""
                                />
                                <span v-else>{{ groupInitial(group) }}</span>
                            </div>
                            <div class="group-card__body">
                                <button
                                    type="button"
                                    class="group-card__name"
                                    @click="openGroup(group.id)"
                                >
                                    {{ group.name }}
                                </button>
                                <p class="group-card__visibility">
                                    <AppIcon
                                        :name="
                                            group.visibility === 'private'
                                                ? 'lock'
                                                : 'globe'
                                        "
                                        :size="12"
                                        :stroke-width="1.75"
                                    />
                                    {{ visibilityLabel(group) }}
                                    · {{ group.members_count }} thành viên
                                </p>
                                <p
                                    v-if="tab === 'discover' && group.is_member"
                                    class="group-card__joined"
                                >
                                    Bạn đã là thành viên
                                </p>
                                <p
                                    v-else-if="group.has_pending_invite"
                                    class="group-card__joined"
                                >
                                    Bạn được mời vào nhóm này
                                </p>
                                <p
                                    v-if="group.description"
                                    class="group-card__desc"
                                >
                                    {{ group.description }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="group-card__action"
                                :class="{
                                    'group-card__action--muted':
                                        group.visibility === 'private' &&
                                        group.has_pending_request &&
                                        !group.has_pending_invite,
                                }"
                                :disabled="
                                    group.visibility === 'private' &&
                                    group.has_pending_request &&
                                    !group.has_pending_invite
                                "
                                @click="onGroupAction(group)"
                            >
                                {{ actionLabel(group) }}
                            </button>
                        </article>
                    </div>
                    <div v-else class="groups-page__empty">
                        <span
                            class="groups-page__empty-mark"
                            aria-hidden="true"
                        >
                            <img :src="MASCOT" alt="" />
                        </span>
                        <p>{{ emptyMessage }}</p>
                        <button
                            v-if="tab === 'mine'"
                            type="button"
                            class="groups-page__empty-btn"
                            @click="formOpen = true"
                        >
                            Tạo nhóm mới
                        </button>
                    </div>

                    <div
                        v-if="groups.length && lastPage > 1"
                        class="groups-page__pager"
                    >
                        <button
                            type="button"
                            class="groups-page__pager-btn"
                            :disabled="page <= 1"
                            aria-label="Trang trước"
                            @click="goPage(page - 1)"
                        >
                            <AppIcon
                                name="chevronLeft"
                                :size="16"
                                :stroke-width="1.75"
                            />
                            Trước
                        </button>
                        <span class="groups-page__pager-label">{{
                            rangeLabel
                        }}</span>
                        <button
                            type="button"
                            class="groups-page__pager-btn"
                            :disabled="page >= lastPage"
                            aria-label="Trang sau"
                            @click="goPage(page + 1)"
                        >
                            Sau
                            <AppIcon
                                name="chevronRight"
                                :size="16"
                                :stroke-width="1.75"
                            />
                        </button>
                    </div>
                </template>
            </div>
        </div>

        <SocialGroupFormModal
            :open="formOpen"
            @close="formOpen = false"
            @saved="onGroupSaved"
        />
    </section>
</template>

<style scoped>
.groups-page {
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

.groups-page__wm-defs {
    position: absolute;
    width: 0;
    height: 0;
    overflow: hidden;
}

.groups-page__watermark {
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

.groups-page__mascot {
    position: absolute;
    right: -2.5rem;
    bottom: -1.5rem;
    z-index: 0;
    width: min(22rem, 42vw);
    height: auto;
    pointer-events: none;
    opacity: 0.07;
    filter: brightness(0) saturate(100%);
}

.groups-head {
    display: flex;
    align-items: center;
    gap: var(--space-4);
    width: 100%;
    min-width: 0;
}

.groups-head-brand {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
    color: var(--color-primary);
}

.groups-head-brand__sub {
    font-weight: 500;
    color: var(--color-primary-700);
    opacity: 0.78;
}

.groups-page__layout {
    position: relative;
    z-index: 1;
    flex: 1;
    min-height: 0;
    display: flex;
    gap: var(--space-3);
    overflow: hidden;
}

.groups-page__sidebar {
    flex: 0 0 16.5rem;
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
    overflow-y: auto;
}

.groups-brand {
    overflow: hidden;
    border-radius: var(--radius-lg);
    background: var(--color-surface);
    box-shadow: var(--shadow-sm);
}

.groups-brand__banner {
    position: relative;
    height: 5.25rem;
    overflow: hidden;
    background: linear-gradient(
        135deg,
        var(--color-primary-800) 0%,
        var(--color-primary) 52%,
        var(--color-primary-400) 100%
    );
}

.groups-brand__mascot {
    position: absolute;
    right: -0.5rem;
    bottom: -1.25rem;
    width: 7.25rem;
    height: auto;
    opacity: 0.92;
}

.groups-brand__body {
    padding: var(--space-3);
}

.groups-brand__hello {
    margin: 0;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--color-text-muted);
}

.groups-brand__title {
    margin: 2px 0 0;
    font-size: 1rem;
    font-weight: 800;
    color: var(--color-text);
}

.groups-brand__meta {
    margin: 2px 0 0;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--color-text-muted);
}

.groups-page__nav {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: var(--space-2);
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
}

.groups-page__nav-item {
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
    border-radius: var(--radius-md);
    cursor: pointer;
    transition:
        background 0.2s ease,
        color 0.2s ease;
}

.groups-page__nav-item:hover {
    background: var(--color-surface-muted);
}

.groups-page__nav-item--back {
    margin-bottom: var(--space-1);
    box-shadow: 0 1px 0 var(--color-border);
    border-radius: var(--radius-md) var(--radius-md) 0 0;
}

.groups-page__nav-item--active {
    background: var(--color-primary-surface);
    color: var(--color-primary);
}

.groups-page__nav-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    flex-shrink: 0;
    border-radius: var(--radius-md);
    background: var(--color-surface-muted);
    color: var(--color-text-muted);
}

.groups-page__nav-item--active .groups-page__nav-icon {
    background: color-mix(
        in srgb,
        var(--color-primary) 14%,
        var(--color-surface)
    );
    color: var(--color-primary);
}

.groups-page__nav-label {
    min-width: 0;
    font-size: 0.8125rem;
    font-weight: 700;
}

.groups-page__search-field {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    padding: 0.5rem var(--space-3);
    border-radius: var(--radius-lg);
    color: var(--color-text-muted);
    background: var(--color-surface);
    box-shadow: var(--shadow-sm);
    transition: box-shadow 0.15s ease;
}

.groups-page__search-field:focus-within {
    color: var(--color-primary);
    box-shadow:
        0 0 0 1px var(--color-primary),
        var(--shadow-sm);
}

.groups-page__search-field input {
    flex: 1;
    min-width: 0;
    border: none;
    padding: 0;
    font-family: inherit;
    font-size: 0.8125rem;
    color: var(--color-text);
    background: transparent;
}

.groups-page__search-field input:focus {
    outline: none;
}

.groups-page__content {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
    overflow-y: auto;
}

.groups-page__empty {
    position: relative;
    isolation: isolate;
    display: flex;
    flex: 1;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: var(--space-3);
    text-align: center;
    color: var(--color-text-muted);
    padding: var(--space-8) var(--space-6);
    overflow: hidden;
    border-radius: var(--radius-lg);
}

.groups-page__empty::before {
    content: "";
    position: absolute;
    inset: 0;
    z-index: 0;
    background: url("/images/background/background-logo.png") center / cover
        no-repeat;
    filter: url(#groups-watermark-boost);
    opacity: 0.055;
    pointer-events: none;
}

.groups-page__empty > * {
    position: relative;
    z-index: 1;
}

.groups-page__empty-mark {
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

.groups-page__empty-mark img {
    width: 4.25rem;
    height: auto;
}

.groups-page__empty p {
    margin: 0;
    max-width: 22rem;
    font-size: 0.875rem;
}

.groups-page__empty-btn {
    height: 2.125rem;
    padding: 0 0.875rem;
    border: none;
    border-radius: var(--radius-md);
    background: var(--color-primary);
    color: var(--color-on-primary);
    font-family: inherit;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
}

.group-card--skeleton {
    animation: none;
    cursor: default;
}

.group-card--skeleton:hover {
    box-shadow: var(--shadow-sm);
    transform: none;
}

.skeleton-block,
.skeleton-line {
    border-radius: var(--radius-sm);
    background: linear-gradient(
        100deg,
        var(--color-surface-muted) 30%,
        color-mix(in srgb, var(--color-surface-muted) 40%, var(--color-surface))
            50%,
        var(--color-surface-muted) 70%
    );
    background-size: 200% 100%;
    animation: skeleton-shimmer 1.4s ease-in-out infinite;
}

.group-card__cover.skeleton-block {
    border-radius: 0;
}

.group-card--skeleton .group-card__avatar {
    background: var(--color-surface-muted);
}

.skeleton-line {
    height: 0.75rem;
}

.skeleton-line--title {
    width: 65%;
    height: 0.9375rem;
}

.skeleton-line--sub {
    width: 85%;
}

.skeleton-line--action {
    height: 2.125rem;
    margin: var(--space-2) var(--space-3) var(--space-3);
}

@keyframes skeleton-shimmer {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .group-card,
    .skeleton-block,
    .skeleton-line {
        animation: none;
    }
}

.groups-page__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr));
    gap: var(--space-4);
}

.group-card {
    position: relative;
    display: flex;
    flex-direction: column;
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    transition:
        box-shadow 0.18s ease,
        transform 0.18s ease;
    animation: group-card-in 0.25s ease backwards;
}

@keyframes group-card-in {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.group-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.group-card__cover {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 7rem;
    overflow: hidden;
    background: linear-gradient(
        135deg,
        var(--color-primary-800),
        var(--color-primary)
    );
}

.group-card__cover img:not(.group-card__cover-mascot) {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.group-card__cover-mascot {
    width: 5.75rem;
    height: auto;
    opacity: 0.9;
    transform: translate(12%, 10%);
}

.group-card__avatar {
    position: absolute;
    top: 5.15rem;
    left: var(--space-3);
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 3.5rem;
    height: 3.5rem;
    overflow: hidden;
    border-radius: var(--radius-full);
    background: var(--color-primary-surface);
    color: var(--color-primary);
    font-size: 1rem;
    font-weight: 800;
    box-shadow: 0 0 0 3px var(--color-surface);
}

.group-card__avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.group-card__body {
    display: flex;
    flex-direction: column;
    gap: 0.3125rem;
    padding: 2.15rem var(--space-3) 0;
}

.group-card__name {
    border: none;
    background: none;
    padding: 0;
    text-align: left;
    font-family: inherit;
    font-size: 0.9375rem;
    font-weight: 700;
    color: var(--color-text);
    cursor: pointer;
    transition: color 0.15s ease;
}

.group-card__name:hover {
    color: var(--color-primary);
}

.group-card__visibility {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    margin: 0;
    font-size: 0.75rem;
    color: var(--color-text-muted);
}

.group-card__joined {
    margin: 0;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--color-primary);
}

.group-card__desc {
    margin: 0;
    font-size: 0.8125rem;
    line-height: 1.45;
    color: var(--color-text-muted);
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.group-card__action {
    margin: var(--space-2) var(--space-3) var(--space-3);
    height: 2.125rem;
    border: none;
    border-radius: var(--radius-md);
    background: var(--color-primary);
    color: var(--color-on-primary);
    font-family: inherit;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    transition:
        filter 0.15s ease,
        background-color 0.15s ease;
}

.group-card__action:hover:not(:disabled) {
    filter: brightness(0.95);
}

.group-card__action--muted,
.group-card__action:disabled {
    background: var(--color-surface-muted);
    color: var(--color-text-muted);
    cursor: not-allowed;
}

.groups-page__requests {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
}

.groups-page__request {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-3) var(--space-4);
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
}

.groups-page__request-media {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.75rem;
    height: 2.75rem;
    flex-shrink: 0;
    overflow: hidden;
    border-radius: var(--radius-full);
    background: var(--color-primary-surface);
    color: var(--color-primary);
    font-weight: 800;
}

.groups-page__request-media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.groups-page__request-info {
    min-width: 0;
    flex: 1;
}

.groups-page__request-name {
    margin: 0;
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--color-text);
}

.groups-page__request-message {
    margin: 2px 0 0;
    font-size: 0.8125rem;
    color: var(--color-text-muted);
}

.groups-page__request-actions {
    display: flex;
    flex-shrink: 0;
    gap: var(--space-2);
}

.groups-page__accept-btn,
.groups-page__cancel-btn {
    flex-shrink: 0;
    height: 2.125rem;
    padding: 0 0.875rem;
    border: none;
    border-radius: var(--radius-md);
    font-family: inherit;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
}

.groups-page__accept-btn {
    background: var(--color-primary);
    color: var(--color-on-primary);
}

.groups-page__cancel-btn {
    background: var(--color-surface-muted);
    color: var(--color-text);
    box-shadow: inset 0 0 0 1px var(--color-border);
}

.groups-page__cancel-btn:hover {
    color: var(--color-primary);
    background: var(--color-primary-surface);
}

.groups-page__pager {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-3);
    padding-top: var(--space-2);
}

.groups-page__pager-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    height: 2.125rem;
    padding: 0 0.875rem;
    border: none;
    border-radius: var(--radius-md);
    background: var(--color-surface);
    box-shadow: inset 0 0 0 1px var(--color-border);
    color: var(--color-text);
    font-family: inherit;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
}

.groups-page__pager-btn:hover:not(:disabled) {
    color: var(--color-primary);
    background: var(--color-primary-surface);
}

.groups-page__pager-btn:disabled {
    opacity: 0.4;
    cursor: default;
}

.groups-page__pager-label {
    font-size: 0.75rem;
    color: var(--color-text-muted);
    white-space: nowrap;
}

@media (max-width: 768px) {
    .groups-page__layout {
        flex-direction: column;
        overflow-y: auto;
    }

    .groups-page__sidebar {
        flex: 0 0 auto;
    }

    .groups-page__nav {
        flex-direction: row;
        overflow-x: auto;
    }

    .groups-page__nav-item {
        flex: 1 0 auto;
    }

    .groups-page__nav-item--back {
        margin-bottom: 0;
        box-shadow: none;
        border-radius: var(--radius-md);
    }

    .groups-page__mascot {
        display: none;
    }
}

@media (max-width: 480px) {
    .groups-page {
        padding: var(--space-2);
    }

    .groups-page__grid {
        grid-template-columns: 1fr;
    }

    .groups-page__request {
        flex-wrap: wrap;
    }
}
</style>
