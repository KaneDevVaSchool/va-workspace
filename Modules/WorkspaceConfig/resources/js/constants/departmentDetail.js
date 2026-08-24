export {
  FALLBACK_AVATAR_SRC,
  FALLBACK_AVATAR_SRCSET,
  MEMBER_COLUMNS as DETAIL_COLUMNS,
  MEMBER_FILTERS as DETAIL_FILTERS,
  MEMBER_STATUS_OPTIONS,
  loadVisibility,
  memberRoles,
  memberRolesText,
  memberStatusLabel,
  saveVisibility,
} from './members.js';

export const COLUMN_STORAGE_KEY = 'va-wc-dept-detail-columns-v1';
export const FILTER_STORAGE_KEY = 'va-wc-dept-detail-filters';
export const COLUMN_WIDTH_KEY = 'va-wc-dept-detail-column-widths';
export const ZOOM_STORAGE_KEY = 'va-wc-dept-detail-zoom';

export function menuVisibilityLabel(isVisible) {
  return isVisible ? 'Đang hiện' : 'Đang ẩn';
}
