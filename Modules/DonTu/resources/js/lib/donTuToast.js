import { showClientToast } from '@/lib/clientToast';

/** Gom lỗi Laravel validation + message API thành một chuỗi đọc được. */
export function formatDonTuApiError(err, fallback) {
  const data = err?.response?.data;
  if (!data) {
    return fallback;
  }

  const errors = data.errors;
  if (errors && typeof errors === 'object') {
    const parts = [];
    for (const val of Object.values(errors)) {
      if (Array.isArray(val)) {
        for (const line of val) {
          if (line) parts.push(String(line));
        }
      } else if (val) {
        parts.push(String(val));
      }
    }
    if (parts.length) {
      return parts.join(' ');
    }
  }

  const message = data.message;
  if (typeof message === 'string' && message.trim()) {
    const generic = message === 'The given data was invalid.' || message === 'validation.failed';
    if (!generic) {
      return message.trim();
    }
  }

  return fallback;
}

export function donTuToastSuccess(message) {
  showClientToast('success', message);
}

export function donTuToastError(message) {
  showClientToast('error', message);
}

export function donTuToastErrorFrom(err, fallback) {
  showClientToast('error', formatDonTuApiError(err, fallback));
}
