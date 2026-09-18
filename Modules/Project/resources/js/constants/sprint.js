import { matchesProjectTaskFilter } from './task.js';

export const SPRINT_STATUS_LABELS = {
  planned: 'Chưa bắt đầu',
  active: 'Đang làm',
  completed: 'Đã xong',
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

function toDayTime(value) {
  if (!value) return null;
  const raw = String(value).slice(0, 10);
  const time = Date.parse(`${raw}T00:00:00`);
  return Number.isNaN(time) ? null : time;
}

/** So sánh lịch: bắt đầu → hạn → sort_order. Việc chưa có ngày xuống cuối. */
export function compareTasksBySchedule(a, b) {
  const aStart = toDayTime(a?.start_date);
  const bStart = toDayTime(b?.start_date);
  if (aStart != null && bStart != null && aStart !== bStart) return aStart - bStart;
  if (aStart != null && bStart == null) return -1;
  if (aStart == null && bStart != null) return 1;

  const aEnd = toDayTime(a?.end_date);
  const bEnd = toDayTime(b?.end_date);
  if (aEnd != null && bEnd != null && aEnd !== bEnd) return aEnd - bEnd;
  if (aEnd != null && bEnd == null) return -1;
  if (aEnd == null && bEnd != null) return 1;

  const order = (a?.sort_order ?? 0) - (b?.sort_order ?? 0);
  if (order) return order;
  return (a?.id ?? 0) - (b?.id ?? 0);
}

export function formatCompactTaskDate(value) {
  const time = toDayTime(value);
  if (time == null) return '';
  const date = new Date(time);
  const day = String(date.getDate()).padStart(2, '0');
  const month = String(date.getMonth() + 1).padStart(2, '0');
  return `${day}/${month}`;
}

/**
 * Một cột thời gian dễ đọc: Từ 27/07 đến 29/07. Cùng ngày thì «Ngày 27/07».
 * Khác năm thì thêm /yy để khỏi lẫn.
 */
export function formatTaskScheduleRange(task) {
  const startRaw = toDayTime(task?.start_date);
  const endRaw = toDayTime(task?.end_date);
  if (startRaw == null && endRaw == null) return '';

  const withYear = (value) => {
    const date = new Date(value);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    return `${day}/${month}/${String(date.getFullYear()).slice(-2)}`;
  };

  const startYear = startRaw != null ? new Date(startRaw).getFullYear() : null;
  const endYear = endRaw != null ? new Date(endRaw).getFullYear() : null;
  const needYear = startYear != null && endYear != null && startYear !== endYear;

  const start = startRaw == null
    ? ''
    : (needYear ? withYear(startRaw) : formatCompactTaskDate(task.start_date));
  const end = endRaw == null
    ? ''
    : (needYear ? withYear(endRaw) : formatCompactTaskDate(task.end_date));

  if (start && end) return start === end ? `Ngày ${start}` : `Từ ${start} đến ${end}`;
  if (start) return `Bắt đầu ${start}`;
  if (end) return `Hạn ${end}`;
  return '';
}

/**
 * Vị trí việc trên trục thời gian của sprint (0–100). Không đủ mốc thì null.
 */
export function taskRangeInSprint(task, sprint) {
  const sprintStart = toDayTime(sprint?.start_date);
  const sprintEnd = toDayTime(sprint?.end_date);
  if (sprintStart == null || sprintEnd == null || sprintEnd < sprintStart) return null;

  const taskStart = toDayTime(task?.start_date) ?? sprintStart;
  const taskEnd = toDayTime(task?.end_date) ?? taskStart;
  const total = Math.max(1, sprintEnd - sprintStart);
  const left = ((Math.min(sprintEnd, Math.max(sprintStart, taskStart)) - sprintStart) / total) * 100;
  const right = ((Math.min(sprintEnd, Math.max(sprintStart, taskEnd)) - sprintStart) / total) * 100;
  return {
    left: Math.max(0, Math.min(100, left)),
    width: Math.max(6, Math.min(100, right) - Math.max(0, left)),
  };
}

/**
 * Phẳng cây việc trong 1 sprint: anh em xếp theo lịch, việc con nằm ngay
 * dưới cha. collapsedIds ẩn nhánh đã thu gọn.
 */
export function flattenSprintTaskTree(tasks, collapsedIds = null) {
  const list = tasks || [];
  const ids = new Set(list.map((task) => task.id));
  const byParent = new Map();
  for (const task of list) {
    const key = ids.has(task.parent_id) ? task.parent_id : 0;
    if (!byParent.has(key)) byParent.set(key, []);
    byParent.get(key).push(task);
  }
  for (const siblings of byParent.values()) {
    siblings.sort(compareTasksBySchedule);
  }

  const out = [];
  const walk = (parentKey, depth) => {
    for (const task of byParent.get(parentKey) || []) {
      const kids = byParent.get(task.id) || [];
      out.push({ ...task, depth, hasChildren: kids.length > 0, childCount: kids.length });
      if (kids.length && !(collapsedIds && collapsedIds.has(task.id))) {
        walk(task.id, depth + 1);
      }
    }
  };
  walk(0, 0);
  return out;
}

/**
 * Nhóm công việc (type=task) theo sprint. Việc con không có sprint_id đi
 * theo sprint của việc cha gần nhất; việc con tự gán sprint khác thì đứng
 * thành gốc ở sprint đó. Ẩn nhóm "Chưa thuộc sprint nào" khi trống.
 *
 * categoryTitle = danh mục WBS gần nhất — dùng nhóm phụ trong bảng.
 */
export function groupProjectTasksBySprint(nodes, sprints, { filter = 'all', query = '' } = {}) {
  const q = String(query || '').trim().toLowerCase();
  const noSprintKey = '__no_sprint__';

  const groups = new Map();
  for (const sprint of sprints || []) {
    groups.set(sprint.id, { ...sprint, tasks: [] });
  }
  groups.set(noSprintKey, { id: null, name: 'Chưa xếp vào đợt nào', tasks: [] });

  const walk = (list, categoryNode, ancestorTask, ancestorSprintKey) => {
    for (const node of list || []) {
      const nextCategoryNode = node.type === 'category' ? node : categoryNode;
      if (node.type === 'task') {
        const ownKey = node.sprint_id && groups.has(node.sprint_id) ? node.sprint_id : null;
        const resolvedKey = ownKey || ancestorSprintKey || noSprintKey;
        if (matchesProjectTaskFilter(node, filter)) {
          const hay = `${node.title || ''} ${node.code || ''}`.toLowerCase();
          if (!q || hay.includes(q)) {
            groups.get(resolvedKey).tasks.push({
              ...node,
              categoryTitle: nextCategoryNode?.title || null,
              parentTaskTitle: ancestorTask?.title || null,
            });
          }
        }
        walk(node.children, nextCategoryNode, node, ownKey || ancestorSprintKey);
        continue;
      }
      walk(node.children, nextCategoryNode, ancestorTask, ancestorSprintKey);
    }
  };
  walk(nodes, null, null, null);

  const out = Array.from(groups.values()).filter((group) => group.id != null || group.tasks.length > 0);
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
    const childCount = group.tasks.filter((task) => group.tasks.some((item) => item.id === task.parent_id)).length;
    return { ...group, avgProgress, estimatedHours, worklogHours, childCount };
  });
}

/**
 * Nhóm việc gốc theo danh mục; việc con đi theo cha (không tách nhóm).
 * Thứ tự việc trong nhóm: lịch bắt đầu → hạn.
 */
export function groupSprintTasksByCategory(tasks, collapsedIds = null) {
  const ordered = flattenSprintTaskTree(tasks, collapsedIds);
  const order = [];
  const map = new Map();
  const noCategoryKey = '__no_category__';
  let currentKey = noCategoryKey;

  for (const task of ordered) {
    if (task.depth === 0) {
      currentKey = task.categoryTitle || noCategoryKey;
      if (!map.has(currentKey)) {
        map.set(currentKey, { key: currentKey, title: task.categoryTitle || null, tasks: [] });
        order.push(currentKey);
      }
    }
    if (!map.has(currentKey)) {
      map.set(currentKey, { key: currentKey, title: null, tasks: [] });
      order.push(currentKey);
    }
    map.get(currentKey).tasks.push(task);
  }

  return order.map((key) => map.get(key));
}
