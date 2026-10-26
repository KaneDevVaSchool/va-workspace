const INTRO_KEY = 'employee-mobile-workspace-intro-v2';

export function hasSeenWorkspaceIntro() {
  try {
    return localStorage.getItem(INTRO_KEY) === '1';
  } catch {
    return false;
  }
}

export function markWorkspaceIntroSeen() {
  try {
    localStorage.setItem(INTRO_KEY, '1');
  } catch {
    /* ignore quota */
  }
}
