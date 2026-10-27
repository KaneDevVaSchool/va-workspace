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

/** Một dòng mô tả ngắn trên thẻ chọn bộ. */
export function setPickerBlurb(set) {
  const desc = humanSetDescription(set?.description);
  if (desc) {
    const short = desc.length > 72 ? `${desc.slice(0, 69).trim()}…` : desc;
    return short;
  }
  if (set?.is_system) {
    return 'Mẫu sẵn có — dùng trực tiếp hoặc sao chép làm nền.';
  }
  return 'Bộ do đơn vị tự khai báo.';
}

/** Chữ cái đầu cho huy hiệu thẻ bộ. */
export function setPickerGlyph(name, code) {
  const label = friendlySetName(name, code).trim();
  if (!label) return 'B';
  const first = [...label][0];
  return first ? first.toUpperCase() : 'B';
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
