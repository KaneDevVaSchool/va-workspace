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
        <AppIcon name="search" :size="18" aria-hidden="true" />
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
      <p class="member-directory__count">{{ memberCountLabel }} thành viên</p>
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
      <AppIcon name="users" :size="28" aria-hidden="true" />
      <p>{{ query.trim() ? 'Không tìm thấy thành viên phù hợp.' : 'Chưa có dữ liệu danh bạ.' }}</p>
    </div>

    <div v-else class="member-directory__sections hide-scrollbar">
      <section v-for="section in groupedSections" :key="section.letter" class="member-directory__section">
        <h3 class="member-directory__letter" :id="`dir-letter-${section.letter}`">
          {{ section.letter }}
        </h3>
        <ul class="member-directory__list">
          <li v-for="member in section.rows" :key="memberKey(member)">
            <div class="member-directory__row">
              <UserAvatarTip :user="avatarUser(member)" label="Thông tin liên hệ" />
              <span class="member-directory__info">
                <span class="member-directory__name">{{ member.name }}</span>
                <a
                  v-if="member.email"
                  class="member-directory__email"
                  :href="mailHref(member.email)"
                >
                  {{ member.email }}
                </a>
                <span
                  v-if="member.job_title || member.department_name"
                  class="member-directory__meta-block"
                >
                  <span v-if="member.department_name" class="member-directory__meta">
                    {{ member.department_name }}
                  </span>
                  <span v-if="member.job_title" class="member-directory__meta">
                    {{ member.job_title }}
                  </span>
                </span>
              </span>
              <a
                v-if="member.email"
                class="member-directory__mail-btn"
                :href="mailHref(member.email)"
                aria-label="Gửi email"
              >
                <AppIcon name="mail" :size="18" aria-hidden="true" />
              </a>
            </div>
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
  gap: var(--space-3);
  min-height: 0;
}

.member-directory__toolbar {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.member-directory__search {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0 var(--space-3);
  min-height: 2.75rem;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  color: var(--color-text-muted);
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
  width: 1.5rem;
  height: 1.5rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  cursor: pointer;
}

.member-directory__count {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.member-directory__sections {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  max-height: min(70vh, 42rem);
  overflow-y: auto;
}

.member-directory__letter {
  position: sticky;
  top: 0;
  z-index: 1;
  margin: 0;
  padding: var(--space-1) var(--space-3);
  font-size: 0.8125rem;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary-surface) 65%, var(--color-surface));
  border-radius: var(--radius-sm);
}

.member-directory__list {
  margin: var(--space-2) 0 0;
  padding: 0;
  list-style: none;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  overflow: hidden;
}

.member-directory__row {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  width: 100%;
  padding: var(--space-3);
  text-decoration: none;
  color: inherit;
  background: var(--color-surface);
  box-shadow: inset 0 -1px 0 var(--color-border);
}

.member-directory__list li:last-child .member-directory__row {
  box-shadow: none;
}

.member-directory__info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
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
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-full);
  color: var(--color-text-muted);
  text-decoration: none;
  transition: background 140ms ease, color 140ms ease;
}

.member-directory__mail-btn:hover {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.member-directory__meta-block {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.member-directory__meta {
  font-size: 0.6875rem;
  font-weight: 500;
  color: var(--color-text-muted);
  line-height: 1.35;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.member-directory__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-6) var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  text-align: center;
  color: var(--color-text-muted);
}

.member-directory__empty p {
  margin: 0;
  font-size: 0.875rem;
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
  box-shadow: inset 0 0 0 1px var(--color-border);
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
