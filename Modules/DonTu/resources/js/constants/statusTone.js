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
  main: 'Đi lần lượt',
  conditional: 'Khi cần thêm việc',
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
  requester: 'Người nộp đơn',
  assignee: 'Người được giao xử lý',
  closure_actor: 'Người xác nhận xong',
  admin: 'Người quản trị',
};

const SET_CODE_DISPLAY = {
  STATUS_SET_DEFAULT: 'Mặc định',
  BMTT01: 'BM-TT-01',
  BMTT02: 'BM-TT-02',
};

/** Tên hiển thị — dữ liệu cũ còn chữ Matrix / mã kỹ thuật. */
export function friendlySetName(name, code) {
  if (code === 'STATUS_SET_DEFAULT' || /matrix/i.test(name || '')) {
    return 'Trạng thái hồ sơ mặc định';
  }
  return name || '';
}

/** Nhãn mã gợi nhớ trên giao diện — không lộ mã kỹ thuật tiếng Anh. */
export function friendlySetCodeLabel(code) {
  const raw = String(code || '').trim();
  if (!raw) return '';
  const upper = raw.toUpperCase();
  if (SET_CODE_DISPLAY[upper]) {
    return SET_CODE_DISPLAY[upper];
  }
  const bmtt = /^BMTT(\d+)$/i.exec(raw);
  if (bmtt) {
    return `BM-TT-${bmtt[1].padStart(2, '0')}`;
  }
  if (/STATUS|DEFAULT|MATRIX|PENDING_|_SET_/i.test(raw) || (raw.includes('_') && /[A-Za-z]{4,}/.test(raw))) {
    return '';
  }
  if (/^[A-Za-z0-9_-]+$/.test(raw) && !/[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/i.test(raw)) {
    return '';
  }
  return raw;
}

/** Một dòng ngắn dưới tên bước — chỉ mã đã biết, không câu hướng dẫn. */
const STATUS_STEP_LINE = {
  DRAFT: 'Đơn chưa gửi',
  PENDING_HANDLING: 'Đợi được tiếp nhận',
  PENDING_APPROVAL: 'Đợi cấp trên duyệt',
  PENDING_SUPPLEMENTAL_FORM: 'Cần nộp thêm phiếu',
  PENDING_PAYMENT: 'Đợi chi tiền',
  PENDING_CLOSURE_CONFIRMATION: 'Đợi xác nhận xong',
  COMPLETED: 'Đã hoàn tất',
  REJECTED: 'Không được duyệt',
  CANCELLED: 'Đã hủy hồ sơ',
  PENDING_UNIT_HEAD: 'Đợi trưởng đơn vị',
  PENDING_HCNS: 'Đợi phòng nhân sự',
  PENDING_CEO: 'Đợi tổng giám đốc',
  PENDING_HANDOVER: 'Đợi bàn giao',
  PENDING_HCNS_PROCEDURE: 'Đang làm thủ tục',
  WITHDRAWN: 'Người nộp đã rút',
};

export function statusStepLine(code) {
  return STATUS_STEP_LINE[code] || '';
}

/** Ẩn mô tả dành cho người làm kỹ thuật. */
export function humanSetDescription(text) {
  const value = (text || '').trim();
  if (!value || /seed|matrix|\bp0\b/i.test(value)) {
    return '';
  }
  return value;
}

/** Điều kiện chuyển trạng thái, viết cho người không rành mã. */
export function guardHint(guard) {
  if (!guard || typeof guard !== 'object') {
    return '';
  }
  const parts = [];
  if (guard.form_complete) parts.push('phiếu đã điền đủ');
  if (guard.step_type === 'receive') parts.push('đúng bước tiếp nhận');
  if (guard.has_next_approve) parts.push('còn người duyệt phía sau');
  if (guard.next_step === 'form_completion') parts.push('bước sau là bổ sung phiếu');
  if (guard.next_step === 'payment') parts.push('bước sau là chi tiền');
  if (guard.unc_required) parts.push('đã có giấy ủy nhiệm chi');
  if (guard.no_specialist_steps_left) parts.push('không còn bước chuyên môn');
  if (guard.reason_min) parts.push('có ghi lý do');
  if (guard.revise_open) parts.push('đang mở cho sửa lại');
  if (!parts.length) return '';
  const text = parts.join(', ');
  return text.charAt(0).toUpperCase() + text.slice(1);
}

export function toneOfStatusCode(code) {
  return STATUS_TONE[code] || 'info';
}
