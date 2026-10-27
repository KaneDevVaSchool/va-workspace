/** URL giới thiệu phần mềm — khớp config app.share_intro_path (/share). */
export function workspaceIntroShareUrl() {
  const path = '/share';
  if (typeof window === 'undefined') {
    return path;
  }
  return new URL(path, window.location.origin).toString();
}

export async function copyWorkspaceIntroLink() {
  const url = workspaceIntroShareUrl();
  await navigator.clipboard.writeText(url);
  return url;
}
