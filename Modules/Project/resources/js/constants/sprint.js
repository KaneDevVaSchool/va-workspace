import { matchesProjectTaskFilter } from './task.js';

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
 *
 * Mỗi task giữ lại nhãn danh mục cha gần nhất (node type=category, ví dụ
 * "Development") qua field tạm `categoryTitle` — dùng để nhóm phụ bên
 * trong 1 sprint ở giao diện bảng (xem ProjectSprintBoard.vue). Task không
 * nằm dưới danh mục nào thì `categoryTitle` là null.
 */
export function groupProjectTasksBySprint(nodes, sprints, { filter = 'all', query = '' } = {}) {
  const q = String(query || '').trim().toLowerCase();
  const noSprintKey = '__no_sprint__';

  const groups = new Map();
  for (const sprint of sprints || []) {
    groups.set(sprint.id, { ...sprint, tasks: [] });
  }
  groups.set(noSprintKey, { id: null, name: 'Chưa thuộc sprint nào', tasks: [] });

  const walk = (list, categoryNode) => {
    for (const node of list || []) {
      const nextCategoryNode = node.type === 'category' ? node : categoryNode;
      if (node.type === 'task' && matchesProjectTaskFilter(node, filter)) {
        const hay = `${node.title || ''} ${node.code || ''}`.toLowerCase();
        if (!q || hay.includes(q)) {
          const key = node.sprint_id && groups.has(node.sprint_id) ? node.sprint_id : noSprintKey;
          groups.get(key).tasks.push({ ...node, categoryTitle: nextCategoryNode?.title || null });
        }
      }
      walk(node.children, nextCategoryNode);
    }
  };
  walk(nodes, null);

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
    const estimatedHours = group.tasks.reduce((sum, task) => sum + Number(task.estimated_hours || 0), 0);
    const worklogHours = group.tasks.reduce((sum, task) => sum + Number(task.worklog_hours || 0), 0);
    return { ...group, avgProgress, estimatedHours, worklogHours };
  });
}

/**
 * Nhóm nhỏ các công việc của 1 sprint theo danh mục cha gần nhất
 * (categoryTitle từ groupProjectTasksBySprint) — giữ nguyên thứ tự xuất
 * hiện đầu tiên của mỗi danh mục. Công việc không có danh mục gom vào
 * nhóm cuối không tiêu đề.
 */
export function groupSprintTasksByCategory(tasks) {
  const order = [];
  const map = new Map();
  const noCategoryKey = '__no_category__';

  for (const task of tasks || []) {
    const key = task.categoryTitle || noCategoryKey;
    if (!map.has(key)) {
      map.set(key, { key, title: task.categoryTitle || null, tasks: [] });
      order.push(key);
    }
    map.get(key).tasks.push(task);
  }

  return order.map((key) => map.get(key));
}
