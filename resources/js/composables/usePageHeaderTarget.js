import { inject, provide, ref } from 'vue';

const PAGE_HEADER_TARGET_KEY = Symbol('pageHeaderTarget');

/**
 * Ô gắn content header trên AppHeader. provide() trong AppLayout;
 * AppHeader gán element, PageHeader teleport vào đó.
 */
export function providePageHeaderTarget() {
  const el = ref(null);
  provide(PAGE_HEADER_TARGET_KEY, el);
  return el;
}

/** Trả về null nếu không nằm trong AppLayout. */
export function usePageHeaderTarget() {
  return inject(PAGE_HEADER_TARGET_KEY, null);
}
