import { flattenAllProjectNodes } from './task.js';

export const SPRINT_STATUS_LABELS = {
  planned: 'Lên kế hoạch',
  active: 'Đang chạy',
  completed: 'Hoàn thành',
  cancelled: 'Đã huỷ',
};

export const SPRINT_STATUS_TONES = {
  planned: 'neutral',
  active: 'primary',
  completed: 'success',
  cancelled: 'umber',
};

export const SPRINT_STATUSES = Object.keys(SPRINT_STATUS_LABELS);

export function sprintStatusLabel(status) {
  return SPRINT_STATUS_LABELS[status] || status || '';
}

export function sprintStatusTone(status) {
  return SPRINT_STATUS_TONES[status] || 'neutral';
}

/**
 * Nhóm công việc (type=task, toàn bộ cây, phẳng) theo sprint_id — quan hệ
 * phẳng (task.sprint_id), KHÔNG qua parent_id như phase. Mỗi sprint đã tải
 * từ API /sprints trở thành 1 cột; task không có sprint_id gom vào nhóm
 * cuối "Chưa thuộc sprint nào".
 */
export function groupProjectTasksBySprint(nodes, sprints, { filter = 'all', query = '' } = {}) {
  const flatTasks = flattenAllProjectNodes(nodes, { filter, query }).filter((node) => node.type === 'task');
  const noSprintKey = '__no_sprint__';

  const groups = new Map();
  for (const sprint of sprints || []) {
    groups.set(sprint.id, { ...sprint, tasks: [] });
  }
  groups.set(noSprintKey, { id: null, name: 'Chưa thuộc sprint nào', tasks: [] });

  for (const task of flatTasks) {
    const key = task.sprint_id && groups.has(task.sprint_id) ? task.sprint_id : noSprintKey;
    groups.get(key).tasks.push(task);
  }

  const out = Array.from(groups.values());
  out.sort((a, b) => {
    if (a.id === null) return 1;
    if (b.id === null) return -1;
    return (a.sort_order ?? 0) - (b.sort_order ?? 0);
  });

  return out.map((group) => {
    const withProgress = group.tasks.filter((task) => task.progress_percent != null);
    const avgProgress = withProgress.length
      ? Math.round(withProgress.reduce((sum, task) => sum + Number(task.progress_percent || 0), 0) / withProgress.length)
      : null;
    return { ...group, avgProgress };
  });
}
