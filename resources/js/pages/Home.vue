<script setup>
import { computed, ref, watch } from 'vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import PageHeader from '../components/PageHeader.vue';

const auth = useAuthStore();

const tabs = [
    { id: 'super_admin', label: 'Super Admin' },
    { id: 'admin', label: 'Admin' },
    { id: 'department_director', label: 'TrÆ°á»Ÿng phÃ²ng ban' },
    { id: 'member', label: 'NhÃ¢n viÃªn' },
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
        lead: 'LÃ m láº§n lÆ°á»£t tá»« trÃªn xuá»‘ng: cáº¥p quyá»n, cáº¥u hÃ¬nh, rá»“i giÃ¡m sÃ¡t. Viá»‡c hÃ ng ngÃ y Ä‘á»ƒ Admin vÃ  trÆ°á»Ÿng phÃ²ng lÃ m.',
        steps: [
            {
                icon: 'shield',
                title: 'Cáº¥p quyá»n cho tá»«ng ngÆ°á»i',
                teaser: 'Má»Ÿ ma tráº­n, gÃ¡n vai trÃ², báº­t quyá»n theo module, khoÃ¡ quyá»n nháº¡y cáº£m.',
                items: ['Chá»‰ Super Admin lÃ m Ä‘Æ°á»£c bÆ°á»›c nÃ y', 'LÃ m xong rá»“i má»›i cáº¥u hÃ¬nh phÃ²ng ban'],
                button: { label: 'Má»Ÿ ma tráº­n phÃ¢n quyá»n', to: { name: 'superadmin.permissions' } },
            },
            {
                icon: 'building',
                title: 'Cáº¥u hÃ¬nh tá»«ng phÃ²ng ban',
                teaser: 'Báº­t menu riÃªng, xem thÃ nh viÃªn vÃ  cÃ¡ch cháº¥m Ä‘iá»ƒm cá»§a tá»«ng phÃ²ng.',
                items: ['LÃ m sau khi Ä‘Ã£ cáº¥p quyá»n'],
                button: { label: 'Má»Ÿ cáº¥u hÃ¬nh Workspace', to: { name: 'superadmin.workspace-config.overview' } },
            },
            {
                icon: 'layoutList',
                title: 'Äáº·t menu cho cáº£ há»‡ thá»‘ng',
                teaser: 'áº¨n hoáº·c hiá»‡n má»¥c menu. ÄÃ¢y lÃ  máº·c Ä‘á»‹nh, trÆ°á»›c khi tá»«ng phÃ²ng tá»± chá»‰nh.',
                items: ['Ãp dá»¥ng cho má»i phÃ²ng ban'],
                button: { label: 'áº¨n hoáº·c hiá»‡n menu', to: { name: 'superadmin.workspace-config.global-menu' } },
            },
            {
                icon: 'search',
                title: 'Xem ai Ä‘Ã£ lÃ m gÃ¬',
                teaser: 'Má»Ÿ nháº­t kÃ½ Ä‘á»ƒ biáº¿t ngÆ°á»i nÃ o thao tÃ¡c lÃºc nÃ o, vÃ  phÃ¡t hiá»‡n viá»‡c báº¥t thÆ°á»ng.',
                result: true,
                items: ['LÃ m thÆ°á»ng xuyÃªn sau khi há»‡ thá»‘ng Ä‘Ã£ cháº¡y'],
                button: { label: 'Má»Ÿ nháº­t kÃ½', to: { name: 'superadmin.activity' } },
            },
        ],
        aside: {
            title: 'Viá»‡c khÃ´ng lÃ m á»Ÿ vai trÃ² nÃ y',
            teaser: 'Giao láº¡i cho Ä‘Ãºng ngÆ°á»i, Ä‘á»«ng Ã´m háº¿t.',
            items: [
                { icon: 'settings', label: 'Admin Ä‘iá»u hÃ nh nghiá»‡p vá»¥ hÃ ng ngÃ y' },
                { icon: 'building', label: 'TrÆ°á»Ÿng phÃ²ng tá»± quáº£n lÃ½ phÃ²ng mÃ¬nh' },
                { icon: 'eye', label: 'Super Admin giÃ¡m sÃ¡t vÃ  cáº¥p quyá»n' },
            ],
        },
        more: [
            { icon: 'shield', title: 'Vai trÃ² trong há»‡ thá»‘ng', teaser: 'Tá»« Super Admin Ä‘áº¿n NhÃ¢n viÃªn' },
            { icon: 'building', title: 'Tá»•ng há»£p má»i phÃ²ng ban', teaser: 'TÃ¬nh hÃ¬nh chung' },
            { icon: 'lock', title: 'TÃ i khoáº£n dÃ¹ng chung', teaser: 'Máº­t kháº©u, tÃ i khoáº£n háº¡ táº§ng' },
        ],
    },
    admin: {
        title: 'Admin',
        lead: 'LÃ m láº§n lÆ°á»£t: thÃªm ngÆ°á»i, má»Ÿ phÃ²ng ban, theo dÃµi viá»‡c, rá»“i xem bÃ¡o cÃ¡o.',
        steps: [
            {
                icon: 'users',
                title: 'ThÃªm vÃ  sáº¯p ngÆ°á»i',
                teaser: 'ThÃªm tÃ i khoáº£n, gáº¯n phÃ²ng ban, chá»n vai trÃ². KhoÃ¡ hoáº·c xoÃ¡ khi ngÆ°á»i nghá»‰.',
                items: ['LÃ m trÆ°á»›c khi giao viá»‡c'],
                button: { label: 'Má»Ÿ danh sÃ¡ch ngÆ°á»i dÃ¹ng', to: { name: 'manager.workspace-config.members' } },
            },
            {
                icon: 'building',
                title: 'Má»Ÿ phÃ²ng ban',
                teaser: 'Chá»n cÃ¡ch cháº¥m Ä‘iá»ƒm, menu riÃªng, rá»“i bá»• nhiá»‡m trÆ°á»Ÿng phÃ²ng.',
                items: ['LÃ m khi cÃ³ phÃ²ng má»›i hoáº·c Ä‘á»•i trÆ°á»Ÿng phÃ²ng'],
                button: { label: 'Má»Ÿ cáº¥u hÃ¬nh phÃ²ng ban', to: { name: 'manager.workspace-config.hub' } },
            },
            {
                icon: 'layers',
                title: 'Theo dÃµi viá»‡c Ä‘ang cháº¡y',
                teaser: 'Xem tiáº¿n Ä‘á»™ má»i dá»± Ã¡n, viá»‡c chuyá»ƒn giá»¯a cÃ¡c phÃ²ng, vÃ  Ä‘iá»ƒm KPI tá»«ng phÃ²ng.',
                items: ['LÃ m trong tuáº§n, khÃ´ng chá» cuá»‘i thÃ¡ng'],
                button: { label: 'Má»Ÿ dá»± Ã¡n vÃ  cÃ´ng viá»‡c', to: { name: 'manager.project.index' } },
            },
            {
                icon: 'barChart',
                title: 'Äá»c bÃ¡o cÃ¡o tá»•ng há»£p',
                teaser: 'Xem tá»· lá»‡ hoÃ n thÃ nh, Ä‘iá»ƒm cháº¥t lÆ°á»£ng vÃ  xu hÆ°á»›ng cá»§a cáº£ trÆ°á»ng.',
                result: true,
                items: ['LÃ m sau khi cÃ¡c phÃ²ng Ä‘Ã£ cáº­p nháº­t viá»‡c'],
                button: { label: 'Má»Ÿ bÃ¡o cÃ¡o', to: { name: 'manager.reports.index' } },
            },
        ],
        aside: {
            title: 'Khi cÃ³ viá»‡c phÃ¡t sinh',
            teaser: 'Xá»­ lÃ½ cÃ¡c viá»‡c nÃ y ngoÃ i bá»‘n bÆ°á»›c trÃªn.',
            items: [
                { icon: 'shield', label: 'Xá»­ lÃ½ bÃ i Ä‘Äƒng vi pháº¡m' },
                { icon: 'lock', label: 'Cáº¥p hoáº·c thu há»“i tÃ i khoáº£n dÃ¹ng chung' },
                { icon: 'messageCircle', label: 'Há»— trá»£ trÆ°á»Ÿng phÃ²ng khi vÆ°á»›ng' },
            ],
        },
        more: [
            { icon: 'lock', title: 'TÃ i khoáº£n dÃ¹ng chung', teaser: 'Máº­t kháº©u háº¡ táº§ng' },
            { icon: 'megaphone', title: 'Duyá»‡t bÃ i viáº¿t', teaser: 'Báº£ng tin ná»™i bá»™' },
            { icon: 'activity', title: 'Nháº­t kÃ½ hoáº¡t Ä‘á»™ng', teaser: 'Thao tÃ¡c gáº§n Ä‘Ã¢y' },
        ],
    },
    department_director: {
        title: 'TrÆ°á»Ÿng phÃ²ng ban',
        lead: 'LÃ m láº§n lÆ°á»£t: thiáº¿t láº­p phÃ²ng, giao viá»‡c, cháº¥m Ä‘iá»ƒm, rá»“i gá»­i bÃ¡o cÃ¡o lÃªn trÃªn.',
        steps: [
            {
                icon: 'settings',
                title: 'Thiáº¿t láº­p phÃ²ng trÆ°á»›c',
                teaser: 'Chá»n tiÃªu chÃ­ Ä‘Ã¡nh giÃ¡, báº­t tÃ­nh nÄƒng cá»§a phÃ²ng, chá»‰ Ä‘á»‹nh phÃ³ phÃ²ng vÃ  trÆ°á»Ÿng nhÃ³m.',
                items: ['LÃ m má»™t láº§n, rá»“i chá»‰ sá»­a khi Ä‘á»•i quy Æ°á»›c'],
                button: { label: 'Má»Ÿ cáº¥u hÃ¬nh phÃ²ng ban', to: { name: 'manager.workspace-config.hub' } },
            },
            {
                icon: 'userPlus',
                title: 'Táº¡o viá»‡c vÃ  giao ngÆ°á»i',
                teaser: 'Táº¡o dá»± Ã¡n hoáº·c cÃ´ng viá»‡c, chá»n ngÆ°á»i lÃ m, Ä‘áº·t háº¡n. Viá»‡c cá»§a phÃ²ng khÃ¡c thÃ¬ chuyá»ƒn giao.',
                items: ['Giao rÃµ ngÆ°á»i vÃ  háº¡n trÆ°á»›c khi nhÃ¢n viÃªn báº¯t Ä‘áº§u'],
                button: { label: 'Táº¡o cÃ´ng viá»‡c má»›i', to: { name: 'manager.project.tasks.create' } },
            },
            {
                icon: 'clipboardCheck',
                title: 'Theo dÃµi rá»“i cháº¥m Ä‘iá»ƒm',
                teaser: 'Xem tiáº¿n Ä‘á»™ tá»«ng ngÆ°á»i, duyá»‡t nháº­t kÃ½ ngÃ y, cháº¥m Ä‘iá»ƒm cháº¥t lÆ°á»£ng khi viá»‡c xong.',
                items: ['Cháº¥m sau khi nhÃ¢n viÃªn Ä‘Ã£ cáº­p nháº­t tiáº¿n Ä‘á»™'],
                button: { label: 'Má»Ÿ cÃ´ng viá»‡c cá»§a phÃ²ng', to: { name: 'manager.project.index' } },
            },
            {
                icon: 'barChart',
                title: 'Gá»­i bÃ¡o cÃ¡o lÃªn trÃªn',
                teaser: 'Xem tá»· lá»‡ hoÃ n thÃ nh vÃ  Ä‘iá»ƒm KPI cá»§a phÃ²ng, rá»“i chá»n ai Ä‘Æ°á»£c xem bÃ¡o cÃ¡o.',
                result: true,
                items: ['LÃ m khi Ä‘Ã£ cháº¥m Ä‘iá»ƒm xong'],
                button: { label: 'Má»Ÿ bÃ¡o cÃ¡o', to: { name: 'manager.reports.index' } },
            },
        ],
        aside: {
            title: 'Khi cÃ³ viá»‡c phÃ¡t sinh',
            teaser: 'Xá»­ lÃ½ cÃ¡c viá»‡c nÃ y trÆ°á»›c khi gá»­i bÃ¡o cÃ¡o.',
            items: [
                { icon: 'messageCircle', label: 'Trao Ä‘á»•i, xá»­ lÃ½ vÆ°á»›ng máº¯c' },
                { icon: 'gitBranch', label: 'Nháº­n hoáº·c tá»« chá»‘i viá»‡c phÃ²ng khÃ¡c chuyá»ƒn tá»›i' },
                { icon: 'clipboardCheck', label: 'XÃ¡c nháº­n káº¿t quáº£ trÆ°á»›c khi tá»•ng káº¿t' },
            ],
        },
        more: [
            { icon: 'barChart', title: 'KPI phÃ²ng ban', teaser: 'Chá»‰ tiÃªu thÃ¡ng nÃ y' },
            { icon: 'layers', title: 'SÆ¡ Ä‘á»“ vÃ  lá»‹ch dá»± Ã¡n', teaser: 'Theo tiáº¿n Ä‘á»™' },
            { icon: 'lock', title: 'TÃ i khoáº£n dÃ¹ng chung', teaser: 'Trong pháº¡m vi phÃ²ng' },
        ],
    },
    member: {
        title: 'NhÃ¢n viÃªn',
        lead: 'LÃ m láº§n lÆ°á»£t: Ä‘á»c viá»‡c Ä‘Æ°á»£c giao, cáº­p nháº­t tiáº¿n Ä‘á»™, bÃ¡o khi vÆ°á»›ng, rá»“i xem káº¿t quáº£.',
        steps: [
            {
                icon: 'users',
                title: 'Äá»c viá»‡c Ä‘Æ°á»£c giao',
                teaser: 'Má»Ÿ viá»‡c tá»« trÆ°á»Ÿng nhÃ³m hoáº·c trÆ°á»Ÿng phÃ²ng. Äá»c tÃªn, mÃ´ táº£, háº¡n vÃ  má»©c Ä‘á»™ quan trá»ng trÆ°á»›c khi lÃ m.',
                items: ['ChÆ°a rÃµ mÃ´ táº£ thÃ¬ há»i láº¡i trÆ°á»›c khi báº¯t Ä‘áº§u'],
            },
            {
                icon: 'fileText',
                title: 'Cáº­p nháº­t tiáº¿n Ä‘á»™',
                teaser: 'Ghi pháº§n trÄƒm Ä‘Ã£ xong, thá»i gian thá»±c táº¿, Ä‘Ã­nh kÃ¨m tÃ i liá»‡u vÃ  nháº­t kÃ½ ngÃ y.',
                items: ['Cáº­p nháº­t trong ngÃ y, Ä‘á»«ng Ä‘á»ƒ cuá»‘i háº¡n'],
                button: { label: 'Má»Ÿ viá»‡c cá»§a tÃ´i', to: { name: 'manager.project.tasks' } },
            },
            {
                icon: 'messageCircle',
                title: 'BÃ¡o khi vÆ°á»›ng',
                teaser: 'BÃ¬nh luáº­n trÃªn Ä‘Ãºng viá»‡c Ä‘Ã³: khÃ³ khÄƒn, cháº­m tiáº¿n Ä‘á»™, hoáº·c Ä‘á» xuáº¥t sá»­a mÃ´ táº£.',
                items: ['BÃ¡o sá»›m cho ngÆ°á»i giao viá»‡c, Ä‘á»«ng chá» bá»‹ há»i'],
            },
            {
                icon: 'star',
                title: 'Xem káº¿t quáº£ cá»§a mÃ¬nh',
                teaser: 'Sau khi xong, xem Ä‘iá»ƒm cháº¥t lÆ°á»£ng, tá»· lá»‡ Ä‘Ãºng háº¡n vÃ  Ä‘iá»ƒm KPI thÃ¡ng nÃ y.',
                result: true,
                items: ['Chá»‰ xem Ä‘Æ°á»£c káº¿t quáº£ cá»§a chÃ­nh mÃ¬nh'],
            },
        ],
        aside: {
            title: 'Viá»‡c khÃ¡c trong ngÃ y',
            teaser: 'LÃ m thÃªm khi Ä‘Ã£ cáº­p nháº­t viá»‡c Ä‘Æ°á»£c giao.',
            items: [
                { icon: 'megaphone', label: 'ÄÄƒng tin lÃªn báº£ng tin ná»™i bá»™' },
                { icon: 'eye', label: 'Xem bÃ¡o cÃ¡o Ä‘Æ°á»£c chia sáº»' },
                { icon: 'home', label: 'Xem táº¥t cáº£ viá»‡c cá»§a tÃ´i' },
            ],
            button: { label: 'Má»Ÿ viá»‡c cá»§a tÃ´i', to: { name: 'manager.project.tasks' } },
        },
        more: [
            { icon: 'barChart', title: 'Äiá»ƒm KPI', teaser: 'Äiá»ƒm thÃ¡ng nÃ y' },
            { icon: 'calendar', title: 'Nháº­t kÃ½ ngÃ y', teaser: 'Ghi chÃ©p hÃ ng ngÃ y' },
            { icon: 'trendingUp', title: 'Tá»•ng káº¿t tuáº§n', teaser: 'Káº¿t quáº£ 7 ngÃ y qua' },
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
            title="HÆ°á»›ng dáº«n"
            icon="gitBranch"
            description="Chá»n vai trÃ², Ä‘á»c tá»« bÆ°á»›c 1 xuá»‘ng. Má»—i bÆ°á»›c nÃ³i viá»‡c cáº§n lÃ m vÃ  chá»— má»Ÿ trong há»‡ thá»‘ng."
            :breadcrumbs="[{ label: 'HÆ°á»›ng dáº«n' }]"
        />

        <div class="guide__body">
            <div class="guide__roles" role="tablist" aria-label="Vai trÃ²">
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
                    <span v-if="mine === tab.id" class="guide__you">cá»§a báº¡n</span>
                </button>
            </div>

            <header class="guide__lead">
                <h2>{{ current.title }}</h2>
                <p>{{ current.lead }}</p>
            </header>

            <ol class="steps" :aria-label="`HÆ°á»›ng dáº«n ${current.title}`">
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
                    <h3 id="guide-more-title">Má»Ÿ tá»« menu khi cáº§n</h3>
                    <p>CÃ¡c má»¥c nÃ y khÃ´ng náº±m trong bá»‘n bÆ°á»›c. VÃ o tá»« menu bÃªn trÃ¡i.</p>
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
