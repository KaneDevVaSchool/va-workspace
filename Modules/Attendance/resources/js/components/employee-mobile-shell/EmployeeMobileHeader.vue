<script setup>
//
// Header shell mobile nhân viên — lời chào (trang chủ), tiêu đề trang
// (subpage), hoặc tiêu đề "Hồ sơ cá nhân" (profile). CSS dùng chung:
// resources/css/employee-mobile-shell/base.css (khung) +
// resources/css/employee-mobile-shell/theme.css (skin màu brand).
//
import { computed } from "vue";
import AppIcon from "@/components/AppIcon.vue";

const props = defineProps({
    isHome: { type: Boolean, default: false },
    isProfile: { type: Boolean, default: false },
    /** Nút logo → quay lại (hồ sơ hoặc hub con của Lịch sử). */
    brandBack: { type: Boolean, default: false },
    greeting: { type: String, default: "" },
    firstName: { type: String, default: "" },
    displayName: { type: String, default: "" },
    departmentName: { type: String, default: "" },
    jobTitleName: { type: String, default: "" },
    todayLabel: { type: String, default: "" },
    pageTitle: { type: String, default: "" },
    isAttendanceRoute: { type: Boolean, default: false },
    showSearch: { type: Boolean, default: false },
    searchPlaceholder: { type: String, default: "Tìm tiện ích nhanh..." },
    searchQuery: { type: String, default: "" },
    headerRefreshing: { type: Boolean, default: false },
    chatUnreadLabel: { type: String, default: "" },
    chatHeaderAria: { type: String, default: "Trò chuyện" },
    hideChatShortcut: { type: Boolean, default: false },
    hasAvatarPhoto: { type: Boolean, default: false },
    avatarInitial: { type: String, default: "?" },
    avatarUrl: { type: String, default: "" },
});

const emit = defineEmits([
    "update:searchQuery",
    "back",
    "open-profile",
    "refresh",
    "open-chat",
    "avatar-error",
]);

const searchModel = computed({
    get: () => props.searchQuery,
    set: (value) => emit("update:searchQuery", value),
});
</script>

<template>
    <header
        class="employee-shell__header employee-shell__header--brand"
        :class="{
            'employee-shell__header--home': isHome,
            'employee-shell__header--subpage': isProfile,
        }"
    >
        <div class="employee-shell__header-deco" aria-hidden="true"></div>
        <div class="employee-shell__header-top">
            <button
                v-if="isProfile || brandBack"
                type="button"
                class="employee-shell__brand-mark employee-shell__back-btn"
                aria-label="Quay lại"
                @click="emit('back')"
            >
                <AppIcon name="chevronLeft" :size="20" :stroke-width="2" />
            </button>
            <button
                v-else
                type="button"
                class="employee-shell__brand-mark employee-shell__brand-mark--avatar"
                aria-label="Mở hồ sơ cá nhân"
                @click="emit('open-profile')"
            >
                <img
                    v-if="hasAvatarPhoto"
                    class="employee-shell__brand-logo employee-shell__brand-logo--avatar"
                    :src="avatarUrl"
                    alt=""
                    width="40"
                    height="40"
                    @error="emit('avatar-error')"
                />
                <span
                    v-else
                    class="employee-shell__brand-initial"
                    aria-hidden="true"
                    >{{ avatarInitial }}</span
                >
            </button>
            <div class="employee-shell__greeting">
                <template v-if="isHome">
                    <p class="employee-shell__kicker">{{ greeting }}</p>
                    <h1 class="employee-shell__hello">
                        {{ firstName || displayName || "bạn" }}
                    </h1>
                    <div
                        class="employee-shell__meta employee-shell__meta--reserve employee-shell__meta--home"
                    >
                        <span class="employee-shell__date">{{
                            todayLabel
                        }}</span>
                        <span
                            v-if="departmentName || jobTitleName"
                            class="employee-shell__meta-sep"
                            aria-hidden="true"
                            >·</span
                        >
                        <span
                            v-if="departmentName"
                            class="employee-shell__meta-extra"
                            >{{ departmentName }}</span
                        >
                        <span
                            v-else-if="jobTitleName"
                            class="employee-shell__meta-extra"
                            >{{ jobTitleName }}</span
                        >
                    </div>
                </template>
                <template v-else-if="isProfile">
                    <p class="employee-shell__kicker">Hồ sơ cá nhân</p>
                    <h1
                        class="employee-shell__hello employee-shell__hello--page"
                    >
                        {{ displayName || "Nhân sự" }}
                    </h1>
                    <div
                        v-if="departmentName || jobTitleName"
                        class="employee-shell__meta employee-shell__meta--reserve"
                    >
                        <span class="employee-shell__meta-extra">{{
                            [jobTitleName, departmentName]
                                .filter(Boolean)
                                .join(" · ")
                        }}</span>
                    </div>
                </template>
                <template v-else>
                    <p class="employee-shell__kicker">VA Workspace</p>
                    <h1
                        class="employee-shell__hello employee-shell__hello--page"
                    >
                        {{ pageTitle || displayName || "Nhân sự" }}
                    </h1>
                    <div
                        v-if="pageTitle && !isAttendanceRoute"
                        class="employee-shell__meta employee-shell__meta--reserve"
                    >
                        <span
                            v-if="departmentName || jobTitleName"
                            class="employee-shell__meta-extra"
                            >{{ departmentName || jobTitleName }}</span
                        >
                    </div>
                    <p
                        v-else-if="!pageTitle && departmentName"
                        class="employee-shell__date"
                    >
                        {{ departmentName }}
                    </p>
                    <p
                        v-else-if="!pageTitle && jobTitleName"
                        class="employee-shell__date"
                    >
                        {{ jobTitleName }}
                    </p>
                </template>
            </div>
            <div class="employee-shell__header-actions">
                <div class="employee-shell__tool-cluster">
                    <button
                        type="button"
                        class="employee-shell__icon-btn"
                        :disabled="headerRefreshing"
                        aria-label="Làm mới trang"
                        @click="emit('refresh')"
                    >
                        <AppIcon
                            name="refresh"
                            :size="20"
                            :stroke-width="1.8"
                            :class="{
                                'employee-shell__icon-btn-spin':
                                    headerRefreshing,
                            }"
                        />
                    </button>
                    <button
                        v-if="!hideChatShortcut"
                        type="button"
                        class="employee-shell__icon-btn employee-shell__icon-btn--chat"
                        :aria-label="chatHeaderAria"
                        @click="emit('open-chat')"
                    >
                        <AppIcon
                            name="messageCircle"
                            :size="20"
                            :stroke-width="1.8"
                        />
                        <span
                            v-if="chatUnreadLabel"
                            class="employee-shell__chat-badge"
                            >{{ chatUnreadLabel }}</span
                        >
                    </button>
                </div>
                <button
                    v-if="!isProfile"
                    type="button"
                    class="employee-shell__avatar employee-shell__avatar-btn"
                    :class="{ 'employee-shell__avatar--home': isHome }"
                    aria-label="Mở hồ sơ cá nhân"
                    @click="emit('open-profile')"
                >
                    <img
                        v-if="hasAvatarPhoto"
                        class="employee-shell__avatar-img"
                        :src="avatarUrl"
                        alt=""
                        @error="emit('avatar-error')"
                    />
                    <span v-else class="employee-shell__avatar-initial">{{
                        avatarInitial
                    }}</span>
                </button>
            </div>
        </div>

        <slot name="toolbar">
            <label
                v-if="showSearch"
                class="employee-shell__search employee-shell__search--elevated"
            >
                <span class="employee-shell__search-leading" aria-hidden="true">
                    <AppIcon
                        name="search"
                        :size="18"
                        class="employee-shell__search-icon"
                    />
                </span>
                <input
                    v-model="searchModel"
                    type="search"
                    class="employee-shell__search-input"
                    :placeholder="searchPlaceholder"
                    autocomplete="off"
                    enterkeyhint="search"
                />
            </label>
        </slot>
    </header>
</template>
