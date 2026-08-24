function toDate(iso) {
  if (!iso) {
    return null;
  }

  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? null : date;
}

export function formatDateTime(iso) {
  const date = toDate(iso);
  if (!date) {
    return '';
  }

  return date.toLocaleString('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export function formatDate(iso) {
  const date = toDate(iso);
  if (!date) {
    return '';
  }

  return date.toLocaleDateString('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}

export function formatTime(iso) {
  const date = toDate(iso);
  if (!date) {
    return '';
  }

  return date.toLocaleTimeString('vi-VN', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
}

export function formatRelativeTime(iso) {
  if (!iso) {
    return '';
  }

  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return '';
  }

  const minutes = Math.floor((Date.now() - date.getTime()) / 60000);
  if (minutes < 1) {
    return 'Vừa xong';
  }
  if (minutes < 60) {
    return `${minutes} phút trước`;
  }

  const hours = Math.floor(minutes / 60);
  if (hours < 24) {
    return `${hours} giờ trước`;
  }

  const days = Math.floor(hours / 24);
  if (days < 7) {
    return `${days} ngày trước`;
  }

  return formatDateTime(iso);
}
