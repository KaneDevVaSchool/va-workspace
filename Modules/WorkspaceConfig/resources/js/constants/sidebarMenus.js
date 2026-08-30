//
// Catalog UI cho tab Cấu hình menu — đồng bộ thủ công với
// CONFIGURABLE_MENUS (DepartmentSidebarConfigService) và các item
// `configurableByDepartment: true` trong AppSidebar.vue.
//
export const SIDEBAR_MENU_CATALOG = {
  home: {
    icon: 'dashboard',
    section: 'general',
    sectionLabel: 'Điều hướng',
    description: 'Trang chủ sau khi đăng nhập. Ẩn thì thành viên không thấy mục Tổng quan trên menu trái.',
  },
  'social.feed': {
    icon: 'megaphone',
    section: 'general',
    sectionLabel: 'Điều hướng',
    description: 'Bảng tin nội bộ của trường. Ẩn thì thành viên không vào được tường từ menu trái.',
  },
  'manager.evaluation.view': {
    icon: 'clipboardCheck',
    section: 'general',
    sectionLabel: 'Điều hướng',
    description: 'Xem tiêu chí đánh giá của phòng ban (chỉ đọc).',
  },
  'manager.evaluation-score-kit.index': {
    icon: 'layers',
    section: 'manager',
    sectionLabel: 'Quản lý',
    description: 'Chọn cách tính điểm của phòng: điểm gốc ± theo việc, hoặc theo trọng số khó/dễ và mức dự án.',
  },
  'manager.project.index': {
    icon: 'layers',
    section: 'manager',
    sectionLabel: 'Quản lý',
    description: 'Danh sách dự án. Superadmin và mọi tài khoản có quyền xem dự án đều thấy mục này.',
  },
  'manager.project.tasks': {
    icon: 'layoutList',
    section: 'manager',
    sectionLabel: 'Quản lý',
    description: 'Tất cả công việc xuyên dự án. Superadmin và mọi tài khoản có quyền xem công việc đều thấy mục này.',
  },
};

export const SIDEBAR_SECTIONS = {
  general: { id: 'general', defaultLabel: 'Điều hướng' },
  admin: { id: 'admin', defaultLabel: 'Quản trị' },
  manager: { id: 'manager', defaultLabel: 'Quản lý' },
  'superadmin-workspace-config': { id: 'superadmin-workspace-config', defaultLabel: 'Cấu hình Workspace' },
};

const SECTION_ORDER = ['general', 'manager', 'other'];

export const LABEL_MAX_LENGTH = 40;

export function catalogFor(menuKey) {
  return (
    SIDEBAR_MENU_CATALOG[menuKey] ?? {
      icon: 'layoutList',
      section: 'other',
      sectionLabel: 'Khác',
      description: 'Mục trên menu trái của phòng ban.',
    }
  );
}

export function enrichMenu(menu) {
  const meta = catalogFor(menu.menu_key);
  return {
    ...meta,
    ...menu,
    icon: menu.icon || meta.icon,
    section: menu.section || meta.section,
    default_section: menu.default_section || meta.section,
    description: menu.description !== undefined ? menu.description : meta.description,
  };
}

function sectionDefaultLabel(sectionId) {
  return SIDEBAR_SECTIONS[sectionId]?.defaultLabel || 'Khác';
}

export function sectionLabelMap(sectionConfigs = []) {
  const map = {};
  for (const section of sectionConfigs) {
    if (section?.id) map[section.id] = section.label || sectionDefaultLabel(section.id);
  }
  return map;
}

function sectionMeta(sectionConfigs, id, fallbackLabel) {
  const cfg = (sectionConfigs || []).find((section) => section?.id === id);
  const fallback = fallbackLabel || sectionDefaultLabel(id);
  return {
    label: cfg?.label || fallback,
    defaultLabel: cfg?.defaultLabel || cfg?.default_label || fallback,
  };
}

export function groupMenus(menus, sectionConfigs = [], { includeEmpty = false, sectionOrder: customSectionOrder = null } = {}) {
  const effectiveSectionOrder = customSectionOrder ?? SECTION_ORDER;
  const groups = new Map();

  for (const id of effectiveSectionOrder) {
    if (id === 'other') continue;
    const meta = sectionMeta(sectionConfigs, id);
    groups.set(id, {
      id,
      label: meta.label,
      defaultLabel: meta.defaultLabel,
      items: [],
    });
  }

  for (const menu of menus) {
    const item = enrichMenu(menu);
    const sectionId = item.section || 'other';
    if (!groups.has(sectionId)) {
      const meta = sectionMeta(sectionConfigs, sectionId, item.sectionLabel);
      groups.set(sectionId, {
        id: sectionId,
        label: meta.label,
        defaultLabel: meta.defaultLabel,
        items: [],
      });
    }
    groups.get(sectionId).items.push(item);
  }

  for (const group of groups.values()) {
    // sort ổn định: cùng sort_order giữ thứ tự catalog/API (khớp AppSidebar),
    // không xếp alphabet theo menu_key — lệch với menu trái.
    group.items.sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
  }

  return effectiveSectionOrder.filter((id) => groups.has(id))
    .map((id) => groups.get(id))
    .filter((group) => includeEmpty || group.items.length > 0)
    .concat([...groups.values()].filter((group) => !effectiveSectionOrder.includes(group.id) && group.items.length > 0));
}

export function layoutPayload(menus) {
  return menus.map((menu, index) => ({
    menu_key: menu.menu_key,
    section: menu.section,
    sort_order: index,
  }));
}

/**
 * Chuyển một mục sang nhóm `toSectionId` tại vị trí `toIndex` (0 = đầu nhóm).
 * Trả về mảng mới đã gán lại section + sort_order theo thứ tự nhóm.
 *
 * @param {string[]|null} sectionOrder - thứ tự nhóm tuỳ chỉnh (null = dùng SECTION_ORDER mặc định)
 */
export function moveMenuItem(menus, fromKey, toSectionId, toIndex, sectionOrder = null) {
  const effectiveSectionOrder = sectionOrder ?? SECTION_ORDER;
  const next = menus.map((menu) => ({ ...menu }));
  const fromIdx = next.findIndex((menu) => menu.menu_key === fromKey);
  if (fromIdx === -1) return menus;

  const [item] = next.splice(fromIdx, 1);
  item.section = toSectionId;

  const grouped = new Map();
  for (const id of effectiveSectionOrder) grouped.set(id, []);
  for (const menu of next) {
    const id = menu.section || 'other';
    if (!grouped.has(id)) grouped.set(id, []);
    grouped.get(id).push(menu);
  }

  const target = grouped.get(toSectionId) ?? [];
  const clamped = Math.max(0, Math.min(toIndex, target.length));
  target.splice(clamped, 0, item);
  grouped.set(toSectionId, target);

  let order = 0;
  const flat = [];
  for (const id of effectiveSectionOrder) {
    for (const menu of grouped.get(id) ?? []) {
      flat.push({ ...menu, section: id === 'other' ? menu.section : id, sort_order: order });
      order += 1;
    }
  }
  for (const [id, list] of grouped) {
    if (effectiveSectionOrder.includes(id)) continue;
    for (const menu of list) {
      flat.push({ ...menu, sort_order: order });
      order += 1;
    }
  }

  return flat;
}
