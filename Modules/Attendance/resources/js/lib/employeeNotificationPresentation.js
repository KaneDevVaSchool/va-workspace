//
// Câu chữ + huy hiệu loại thông báo cho hộp thư PWA.
// Dòng "Tên: nội dung" tách như một tin Messenger (tên / lời nhắn).
// Dòng "Tên thích bài viết…" giữ nguyên một câu, tên in đậm.
//

const GLYPHS = {
  like_post: ['heart', 'like'],
  like_comment: ['heart', 'like'],
  social_comment: ['messageCircle', 'comment'],
  mention_comment: ['messageCircle', 'mention'],
  mention_post: ['messageCircle', 'mention'],
  chat_message: ['messageCircle', 'chat'],
  share_post: ['share', 'share'],
  group_post: ['users', 'comment'],
  group_join_request: ['userPlus', 'task'],
  group_join_approved: ['check', 'ok'],
  group_join_rejected: ['close', 'alert'],
  group_invite: ['userPlus', 'task'],
  task_assigned: ['clipboardCheck', 'task'],
  task_delegated: ['share', 'task'],
  task_due_soon: ['clock', 'alert'],
  task_worklog_added: ['activity', 'task'],
  task_scored: ['star', 'task'],
  discussion_comment: ['messageCircle', 'comment'],
  feature_request_created: ['sparkles', 'task'],
  feature_request_approved: ['check', 'ok'],
  feature_request_rejected: ['close', 'alert'],
  feature_request_done: ['check', 'ok'],
  social_post_pending_review: ['eye', 'task'],
  social_post_approved: ['check', 'ok'],
  social_post_rejected: ['close', 'alert'],
  hrm_timesheet_pending: ['calendar', 'task'],
  hrm_timesheet_rejected: ['alertTriangle', 'alert'],
  hrm_timesheet_finalized: ['check', 'ok'],
};

function glyphFor(type) {
  return GLYPHS[type] ?? ['bell', 'system'];
}

function initialOf(name) {
  const trimmed = String(name || '').trim();
  return trimmed ? trimmed.charAt(0).toUpperCase() : 'V';
}

function splitHeadline(actor, headline) {
  const name = String(actor || '').trim();
  const line = String(headline || '').trim();
  if (!name || !line) {
    return { lead: line || 'Thông báo', inline: '', preview: '' };
  }

  if (!line.toLowerCase().startsWith(name.toLowerCase())) {
    return { lead: line, inline: '', preview: '' };
  }

  const boundary = line.slice(name.length, name.length + 1);
  if (boundary && !/[\s:·,.\-–—]/.test(boundary)) {
    return { lead: line, inline: '', preview: '' };
  }

  const after = line.slice(name.length);
  if (/^\s*:/.test(after)) {
    return {
      lead: name,
      inline: '',
      preview: after.replace(/^\s*:\s*/, '').trim(),
    };
  }

  return {
    lead: name,
    inline: after.replace(/^[\s·\-–—]+/, '').trim(),
    preview: '',
  };
}

export function presentEmployeeNotification(item) {
  const actorName = String(item?.actor?.name || '').trim();
  const headline = String(item?.push_line || item?.title || '').trim();
  const body = String(item?.body || '').trim();
  const [glyph, tone] = glyphFor(item?.type);
  const split = splitHeadline(actorName, headline);
  let preview = split.preview;

  if (!preview && body && body !== headline && !headline.includes(body)) {
    preview = body;
  }

  return {
    lead: split.lead,
    inline: split.inline,
    preview,
    glyph,
    tone,
    actorName,
    initial: initialOf(actorName || split.lead),
    avatarUrl: item?.actor?.avatar_url || '',
    system: !actorName,
  };
}

/** Mốc thời gian ngắn, đặt cạnh tên như danh sách Messenger. */
export function notificationTimeLabel(iso) {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';

  const minutes = Math.floor((Date.now() - date.getTime()) / 60000);
  if (minutes < 1) return 'Vừa xong';
  if (minutes < 60) return `${minutes} phút`;

  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours} giờ`;

  const days = Math.floor(hours / 24);
  if (days < 7) return `${days} ngày`;

  return date.toLocaleDateString('vi-VN', { day: 'numeric', month: 'short' });
}
