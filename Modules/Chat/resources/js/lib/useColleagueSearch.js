import { onBeforeUnmount, ref } from 'vue';
import { searchUsersPage } from '../api/chatApi';

const DEBOUNCE_MS = 250;
const NEAR_BOTTOM_PX = 48;

/**
 * Tìm đồng nghiệp theo trang: gõ từ khoá để tìm, cuộn gần cuối danh sách để tải thêm,
 * nên tìm được toàn bộ đồng nghiệp chứ không dừng ở vài người đầu.
 *
 * `exclude(user)` trả true để ẩn người đó khỏi kết quả (vd. người đã chọn vào nhóm).
 * Offset theo số người máy chủ trả về, không theo số dòng đang hiện — ẩn bớt vài người
 * không làm lệch trang kế tiếp.
 */
export function useColleagueSearch({ exclude = () => false } = {}) {
  const results = ref([]);
  const searching = ref(false);
  const loadingMore = ref(false);
  const hasMore = ref(false);

  let timer = null;
  let term = '';
  let received = 0;
  let ticket = 0;

  function reset() {
    window.clearTimeout(timer);
    ticket += 1;
    term = '';
    received = 0;
    results.value = [];
    hasMore.value = false;
    searching.value = false;
    loadingMore.value = false;
  }

  function append(users) {
    const known = new Set(results.value.map((user) => user.id));
    results.value = [
      ...results.value,
      ...users.filter((user) => !known.has(user.id) && !exclude(user)),
    ];
  }

  async function fetchPage(isFirst) {
    const mine = ++ticket;
    if (isFirst) searching.value = true;
    else loadingMore.value = true;
    try {
      const page = await searchUsersPage(term, received);
      if (mine !== ticket) return;
      received += page.users.length;
      hasMore.value = page.hasMore;
      if (isFirst) results.value = [];
      const before = results.value.length;
      append(page.users);
      // Cả trang toàn người bị ẩn thì danh sách không dài ra → không cuộn được để tải tiếp.
      if (page.hasMore && page.users.length > 0 && results.value.length === before) {
        await fetchPage(false);
      }
    } catch {
      if (mine !== ticket) return;
      hasMore.value = false;
      if (isFirst) results.value = [];
    } finally {
      if (mine === ticket) {
        searching.value = false;
        loadingMore.value = false;
      }
    }
  }

  /** Gọi mỗi khi từ khoá đổi; để trống thì xoá kết quả. */
  function setQuery(value) {
    window.clearTimeout(timer);
    const trimmed = String(value ?? '').trim();
    if (trimmed === '') {
      reset();
      return;
    }
    ticket += 1;
    searching.value = true;
    loadingMore.value = false;
    timer = window.setTimeout(() => {
      term = trimmed;
      received = 0;
      fetchPage(true);
    }, DEBOUNCE_MS);
  }

  function loadMore() {
    if (!hasMore.value || searching.value || loadingMore.value || term === '') return;
    fetchPage(false);
  }

  /** Gắn vào `@scroll` của vùng danh sách có thanh cuộn. */
  function onScroll(event) {
    const el = event.target;
    if (el.scrollHeight - el.scrollTop - el.clientHeight <= NEAR_BOTTOM_PX) loadMore();
  }

  onBeforeUnmount(() => window.clearTimeout(timer));

  return { results, searching, loadingMore, hasMore, setQuery, loadMore, onScroll, reset };
}
