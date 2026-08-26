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
  'manager.evaluation-templates.index': {
    icon: 'listChecks',
    section: 'manager',
    sectionLabel: 'Quản lý',
    description: 'Tạo và sửa mẫu đánh giá. Chỉ hiện với người có quyền quản lý đánh giá.',
  },
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
    icon: meta.icon,
    section: meta.section,
    sectionLabel: meta.sectionLabel,
    description: meta.description,
  };
}

export function groupMenus(menus) {
  const groups = new Map();

  for (const menu of menus) {
    const item = enrichMenu(menu);
    if (!groups.has(item.section)) {
      groups.set(item.section, {
        id: item.section,
        label: item.sectionLabel,
        items: [],
      });
    }
    groups.get(item.section).items.push(item);
  }

  return SECTION_ORDER.filter((id) => groups.has(id)).map((id) => groups.get(id));
}
