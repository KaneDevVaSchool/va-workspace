<script setup>
import { computed, onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import { showClientToast } from '@/lib/clientToast';

const members = ref([]);
const loading = ref(true);
const query = ref('');
const filteredMembers = computed(() => {
  const needle = query.value.trim().toLowerCase();
  if (!needle) return members.value;
  return members.value.filter((member) => {
    const haystack = [
      member.name,
      member.email,
      member.job_title,
      member.department_name,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();
    return haystack.includes(needle);
  });
});

const groupedSections = computed(() => {
  const map = new Map();
  for (const member of filteredMembers.value) {
    const letter = sectionLetter(member.name);
    if (!map.has(letter)) {
      map.set(letter, []);
    }
    map.get(letter).push(member);
  }
  return [...map.entries()]
    .sort(([a], [b]) => a.localeCompare(b, 'vi'))
    .map(([letter, rows]) => ({
      letter,
      rows: [...rows].sort((left, right) =>
        String(left.name || '').localeCompare(String(right.name || ''), 'vi'),
      ),
    }));
});

const memberCountLabel = computed(() => {
  const n = filteredMembers.value.length;
  if (loading.value) return '—';
  return n.toLocaleString('vi-VN');
});

const isFiltering = computed(() => query.value.trim().length > 0);

const countCaption = computed(() => {
  if (loading.value) return 'Đang tải danh bạ…';
  if (isFiltering.value) return 'kết quả tìm kiếm';
  return 'thành viên trong hệ thống';
});

function sectionLetter(name) {
  const trimmed = String(name || '').trim();
  if (!trimmed) return '#';
  const first = trimmed.charAt(0).toUpperCase();
  if (/[A-ZÀÁẢÃẠĂẰẮẲẴẶÂẦẤẨẪẬĐÈÉẺẼẸÊỀẾỂỄỆÌÍỈĨỊÒÓỎÕỌÔỒỐỔỖỘƠỜỚỞỠỢÙÚỦŨỤƯỪỨỬỮỰỲÝỶỸỴ0-9]/.test(first)) {
    return first;
  }
  return '#';
}

function avatarUser(member) {
  return {
    id: member.id,
    name: member.name,
    email: member.email,
    avatar_url: member.avatar_url,
    job_title_name: member.job_title,
    department: member.department_name ? { name: member.department_name } : null,
    status: 'active',
  };
}

function memberKey(member) {
  return String(member.id ?? member.email ?? member.name);
}

function mailHref(email) {
  if (!email) return null;
  return `mailto:${encodeURIComponent(email)}`;
}

async function loadDirectory() {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/dashboard/me/directory');
    members.value = Array.isArray(data?.members) ? data.members : [];
  } catch (error) {
    members.value = [];
    showClientToast('error', error?.response?.data?.message || 'Không tải được danh bạ.');
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  loadDirectory();
});

defineExpose({ reload: loadDirectory });
</script>

<template>
  <div class="member-directory">
    <div class="member-directory__toolbar">
      <label class="member-directory__search">
        <span class="member-directory__search-icon" aria-hidden="true">
          <AppIcon name="search" :size="18" />
        </span>
        <input
          v-model="query"
          type="search"
          class="member-directory__search-input"
          placeholder="Tìm tên, email, phòng ban…"
          autocomplete="off"
          spellcheck="false"
        />
        <button
          v-if="query"
          type="button"
          class="member-directory__search-clear"
          aria-label="Xóa tìm kiếm"
          @click="query = ''"
        >
          <AppIcon name="close" :size="14" />
        </button>
      </label>
      <div class="member-directory__stat" aria-live="polite">
        <span class="member-directory__stat-icon" aria-hidden="true">
          <AppIcon name="users" :size="16" />
        </span>
        <span class="member-directory__stat-text">
          <strong class="member-directory__stat-num">{{ memberCountLabel }}</strong>
          {{ countCaption }}
        </span>
      </div>
    </div>

    <div v-if="loading" class="member-directory__loading" aria-busy="true">
      <div v-for="n in 8" :key="n" class="member-directory__sk">
        <span class="member-directory__sk-avatar" />
        <span class="member-directory__sk-lines">
          <span class="member-directory__sk-line" />
          <span class="member-directory__sk-line member-directory__sk-line--short" />
        </span>
      </div>
    </div>

    <div v-else-if="filteredMembers.length === 0" class="member-directory__empty">
      <span class="member-directory__empty-icon" aria-hidden="true">
        <AppIcon name="users" :size="26" />
      </span>
      <p class="member-directory__empty-title">
        {{ query.trim() ? 'Không tìm thấy ai' : 'Chưa có danh bạ' }}
      </p>
      <p class="member-directory__empty-hint">
        {{
          query.trim()
            ? 'Thử từ khóa khác hoặc xóa bộ lọc.'
            : 'Dữ liệu sẽ hiển thị khi được đồng bộ từ HRM.'
        }}
      </p>
    </div>

    <div v-else class="member-directory__sections hide-scrollbar">
      <section v-for="section in groupedSections" :key="section.letter" class="member-directory__section">
        <div class="member-directory__letter-row">
          <h3 class="member-directory__letter" :id="`dir-letter-${section.letter}`">
            {{ section.letter }}
          </h3>
          <span class="member-directory__letter-line" aria-hidden="true" />
        </div>
        <ul class="member-directory__list">
          <li v-for="member in section.rows" :key="memberKey(member)">
            <article class="member-directory__card">
              <UserAvatarTip :user="avatarUser(member)" label="Thông tin liên hệ" />
              <div class="member-directory__info">
                <span class="member-directory__name">{{ member.name }}</span>
                <a
                  v-if="member.email"
                  class="member-directory__email"
                  :href="mailHref(member.email)"
                >
                  {{ member.email }}
                </a>
                <div
                  v-if="member.job_title || member.department_name"
                  class="member-directory__chips"
                >
                  <span v-if="member.department_name" class="member-directory__chip">
                    <AppIcon name="building" :size="12" aria-hidden="true" />
                    {{ member.department_name }}
                  </span>
                  <span v-if="member.job_title" class="member-directory__chip member-directory__chip--muted">
                    {{ member.job_title }}
                  </span>
                </div>
              </div>
              <a
                v-if="member.email"
                class="member-directory__mail-btn"
                :href="mailHref(member.email)"
                aria-label="Gửi email"
              >
                <AppIcon name="mail" :size="18" aria-hidden="true" />
              </a>
            </article>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>

<style scoped>
.member-directory {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  min-height: 0;
}

.member-directory__toolbar {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.member-directory__search {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0 var(--space-3);
  min-height: 3rem;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm), inset 0 0 0 1px var(--color-border);
  color: var(--color-text-muted);
  transition: box-shadow 160ms ease;
}

.member-directory__search:focus-within {
  box-shadow:
    0 0 0 3px color-mix(in srgb, var(--color-primary) 18%, transparent),
    var(--shadow-sm),
    inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 35%, var(--color-border));
}

.member-directory__search-icon {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  color: var(--color-primary);
  opacity: 0.85;
}

.member-directory__search-input {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  font-family: inherit;
  font-size: 0.9375rem;
  color: var(--color-text);
  outline: none;
}

.member-directory__search-input::placeholder {
  color: var(--color-text-muted);
}

.member-directory__search-clear {
  display: grid;
  place-items: center;
  width: 1.625rem;
  height: 1.625rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  cursor: pointer;
  transition: background 140ms ease, color 140ms ease;
}

.member-directory__search-clear:hover {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.member-directory__stat {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-primary-surface) 55%, var(--color-surface));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 12%, var(--color-border));
}

.member-directory__stat-icon {
  display: grid;
  place-items: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-primary);
  box-shadow: var(--shadow-sm);
}

.member-directory__stat-text {
  font-size: 0.8125rem;
  line-height: 1.4;
  color: var(--color-text-muted);
}

.member-directory__stat-num {
  font-weight: 800;
  color: var(--color-text);
}

.member-directory__sections {
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
  max-height: min(70vh, 42rem);
  overflow-y: auto;
}

.member-directory__letter-row {
  position: sticky;
  top: 0;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding-block: var(--space-1);
  background: color-mix(in srgb, var(--color-bg) 92%, transparent);
  backdrop-filter: blur(6px);
}

.member-directory__letter {
  flex-shrink: 0;
  margin: 0;
  width: 1.75rem;
  height: 1.75rem;
  display: grid;
  place-items: center;
  font-size: 0.8125rem;
  font-weight: 800;
  letter-spacing: 0.02em;
  color: var(--color-on-primary);
  background: linear-gradient(
    145deg,
    var(--color-primary-600),
    var(--color-primary-800)
  );
  border-radius: var(--radius-full);
  box-shadow: var(--shadow-sm);
}

.member-directory__letter-line {
  flex: 1;
  height: 1px;
  background: linear-gradient(
    90deg,
    var(--color-border),
    color-mix(in srgb, var(--color-border) 25%, transparent)
  );
}

.member-directory__list {
  margin: var(--space-2) 0 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.member-directory__card {
  position: relative;
  display: flex;
  align-items: center;
  gap: var(--space-3);
  width: 100%;
  padding: var(--space-3);
  padding-left: calc(var(--space-3) + var(--space-2) + 3px);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm), inset 0 0 0 1px var(--color-border);
  transition: box-shadow 160ms ease, transform 160ms ease;
}

.member-directory__card::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: color-mix(in srgb, var(--color-primary) 45%, var(--color-border));
}

.member-directory__card:active {
  transform: scale(0.995);
}

.member-directory__info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.member-directory__name {
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.member-directory__email {
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--color-primary);
  text-decoration: none;
  overflow-wrap: anywhere;
  word-break: break-word;
  line-height: 1.35;
}

.member-directory__email:hover {
  text-decoration: underline;
}

.member-directory__mail-btn {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 2.375rem;
  height: 2.375rem;
  border-radius: var(--radius-full);
  color: var(--color-primary);
  background: var(--color-primary-surface);
  text-decoration: none;
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 15%, transparent);
  transition: background 140ms ease, color 140ms ease, transform 140ms ease;
}

.member-directory__mail-btn:hover {
  background: var(--color-primary-surface-strong);
  color: var(--color-primary-hover);
}

.member-directory__mail-btn:active {
  transform: scale(0.94);
}

.member-directory__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 2px;
}

.member-directory__chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  max-width: 100%;
  padding: 2px 8px;
  border-radius: var(--radius-full);
  font-size: 0.6875rem;
  font-weight: 600;
  line-height: 1.35;
  color: var(--color-secondary-700);
  background: var(--color-secondary-surface);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-secondary) 18%, transparent);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.member-directory__chip--muted {
  color: var(--color-text-muted);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.member-directory__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-8) var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm), inset 0 0 0 1px var(--color-border);
  text-align: center;
}

.member-directory__empty-icon {
  display: grid;
  place-items: center;
  width: 3.25rem;
  height: 3.25rem;
  border-radius: var(--radius-full);
  color: var(--color-primary);
  background: linear-gradient(
    160deg,
    var(--color-primary-surface),
    color-mix(in srgb, var(--color-primary-surface-strong) 80%, var(--color-surface))
  );
  box-shadow: var(--shadow-sm);
}

.member-directory__empty-title {
  margin: var(--space-1) 0 0;
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-text);
}

.member-directory__empty-hint {
  margin: 0;
  max-width: 16rem;
  font-size: 0.8125rem;
  line-height: 1.45;
  color: var(--color-text-muted);
}

.member-directory__loading {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.member-directory__sk {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm), inset 0 0 0 1px var(--color-border);
}

.member-directory__sk-avatar {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
}

.member-directory__sk-lines {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.member-directory__sk-line {
  display: block;
  height: 0.625rem;
  width: 100%;
  border-radius: var(--radius-sm);
  background: linear-gradient(
    90deg,
    var(--color-surface-muted) 0%,
    var(--color-border) 50%,
    var(--color-surface-muted) 100%
  );
  background-size: 200% 100%;
  animation: member-directory-shimmer 1.2s ease infinite;
}

.member-directory__sk-line--short {
  width: 55%;
}

@keyframes member-directory-shimmer {
  0% {
    background-position: 100% 0;
  }
  100% {
    background-position: -100% 0;
  }
}
</style>
