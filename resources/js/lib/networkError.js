/**
 * Lỗi mạng tạm thời (tab ngủ, đổi WiFi, Chrome suspend IO) — không phải lỗi API.
 */
export function isTransientClientNetworkError(error) {
  if (!error) {
    return false;
  }

  const code = String(error.code ?? '');
  if (code === 'ERR_NETWORK' || code === 'ECONNABORTED' || code === 'ERR_CANCELED') {
    return true;
  }

  const msg = String(error.message ?? '').toLowerCase();

  return msg.includes('network error') || msg.includes('network_io_suspended') || msg.includes('network_changed');
}
