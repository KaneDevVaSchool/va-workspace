/**
 * Mục xem trước của Đơn từ. Một mục sidebar; các màn nằm trong hub này.
 * Khớp UI-01 … UI-15 trong plans/2026-10-26-don-tu-detailed-design.md §0.4.
 */
export const DON_TU_GROUPS = [
  {
    id: 'daily',
    label: 'Làm việc',
    items: [
      { name: 'manager.don-tu.hub', path: '', title: 'Nộp đơn', ui: 'UI-01', phase: 'P1' },
      { name: 'manager.don-tu.compose', path: 'new', title: 'Soạn đơn', ui: 'UI-02', phase: 'P1' },
      { name: 'manager.don-tu.mine', path: 'mine', title: 'Hồ sơ của tôi', ui: 'UI-03', phase: 'P1' },
      { name: 'manager.don-tu.show', path: 'submissions/mau', title: 'Chi tiết hồ sơ', ui: 'UI-04', phase: 'P1' },
      { name: 'manager.don-tu.inbox', path: 'inbox', title: 'Cần tôi duyệt', ui: 'UI-05', phase: 'P1' },
      { name: 'manager.don-tu.registry', path: 'registry', title: 'Tra cứu', ui: 'UI-06', phase: 'P2' },
    ],
  },
  {
    id: 'admin',
    label: 'Cấu hình',
    items: [
      { name: 'manager.don-tu.statuses', path: 'admin', title: 'Bộ trạng thái', ui: 'UI-11', phase: 'P0' },
      { name: 'manager.don-tu.forms', path: 'admin/forms', title: 'Mẫu biểu', ui: 'UI-07', phase: 'P2' },
      { name: 'manager.don-tu.form', path: 'admin/forms/mau', title: 'Cấu hình mẫu biểu', ui: 'UI-08', phase: 'P2' },
      { name: 'manager.don-tu.workflows', path: 'admin/workflows', title: 'Quy trình', ui: 'UI-09', phase: 'P2' },
      { name: 'manager.don-tu.workflow', path: 'admin/workflows/mau', title: 'Cấu hình quy trình', ui: 'UI-10', phase: 'P2' },
    ],
  },
  {
    id: 'monitor',
    label: 'Giám sát',
    items: [
      { name: 'manager.don-tu.monitor', path: 'monitor', title: 'Tiến độ', ui: 'UI-12', phase: 'P3' },
      { name: 'manager.don-tu.sla', path: 'monitor/sla', title: 'Báo cáo SLA', ui: 'UI-13', phase: 'P3' },
      { name: 'manager.don-tu.reports', path: 'monitor/reports', title: 'Báo cáo nghiệp vụ', ui: 'UI-14', phase: 'P3' },
      { name: 'manager.don-tu.audits', path: 'monitor/audits', title: 'Nhật ký kiểm toán', ui: 'UI-15', phase: 'P3' },
    ],
  },
];

export const DON_TU_SCREENS = DON_TU_GROUPS.flatMap((group) => group.items);
