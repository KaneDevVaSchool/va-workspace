/** Màu badge trạng thái vĩ mô — token light trong theme.css */

export const STATUS_TONE = {
  DRAFT: 'muted',
  PENDING_HANDLING: 'info',
  PENDING_APPROVAL: 'warning',
  PENDING_SUPPLEMENTAL_FORM: 'purple',
  PENDING_PAYMENT: 'accent',
  PENDING_CLOSURE_CONFIRMATION: 'primary',
  COMPLETED: 'success',
  REJECTED: 'danger',
  CANCELLED: 'muted',
};

export const GROUP_KIND_LABEL = {
  main: 'Luồng chính',
  conditional: 'Cột mốc / điều kiện',
  terminal: 'Kết thúc',
};

export const ACTION_LABEL = {
  SAVE_DRAFT: 'Lưu nháp',
  SUBMIT: 'Gửi đơn',
  ACCEPT: 'Tiếp nhận',
  APPROVE: 'Duyệt',
  REJECT: 'Từ chối',
  CANCEL: 'Hủy hồ sơ',
  CLOSE: 'Xác nhận đóng',
  REQUEST_CHANGES: 'Yêu cầu bổ sung',
  SUPPLEMENT: 'Nộp bổ sung',
  CONFIRM_PAYMENT: 'Xác nhận chi',
};

export const ACTOR_LABEL = {
  requester: 'Người nộp',
  assignee: 'Người được giao',
  closure_actor: 'Người đóng hồ sơ',
  admin: 'Quản trị',
};

export function toneOfStatusCode(code) {
  return STATUS_TONE[code] || 'info';
}
