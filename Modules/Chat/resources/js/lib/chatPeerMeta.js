/**
 * Dòng phụ hiển thị đồng nghiệp trong chat (chức danh · phòng ban).
 *
 * @param {{ job_title_name?: string|null, department?: string|null } | null | undefined} user
 * @returns {string}
 */
export function formatPeerMeta(user) {
  if (!user) return '';
  const parts = [];
  const title = user.job_title_name?.trim();
  const dept = user.department?.trim?.() ?? (typeof user.department === 'string' ? user.department.trim() : '');
  if (title) parts.push(title);
  if (dept) parts.push(dept);
  return parts.join(' · ');
}
