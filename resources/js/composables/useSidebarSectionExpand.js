const EXPANDED_SECTIONS_KEY = 'va-workspace-sidebar-sections';

export function loadExpandedSections(sectionIds, defaults, activeSectionIds) {
  const state = { ...defaults };

  try {
    const raw = localStorage.getItem(EXPANDED_SECTIONS_KEY);
    if (raw) {
      const parsed = JSON.parse(raw);
      for (const id of sectionIds) {
        if (typeof parsed[id] === 'boolean') {
          state[id] = parsed[id];
        }
      }
    }
  } catch {
    // ignore corrupt storage
  }

  for (const id of activeSectionIds) {
    state[id] = true;
  }

  return state;
}

export function persistExpandedSections(state) {
  try {
    localStorage.setItem(EXPANDED_SECTIONS_KEY, JSON.stringify(state));
  } catch {
    // ignore quota / private mode
  }
}
