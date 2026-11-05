export function canonicalAdminPath(path: string): string {
  return path.replace(/^\/county-admin\/[a-z0-9-]+(?=\/|\?|$)/, '/panel/admin');
}

export function workspaceAdminHref(home: string, path: string): string {
  return /^\/county-admin\/[a-z0-9-]+$/.test(home)
    ? path.replace(/^\/panel\/admin(?=\/|\?|$)/, home)
    : path;
}

export function countyReturn(home: string, value?: string | null): string {
  if (!value || /[\\\u0000-\u001f\u007f]/.test(value)) return home;
  try {
    const url = new URL(value, 'https://portal.invalid');
    if (url.origin !== 'https://portal.invalid') return home;
    const path = workspaceAdminHref(home, url.pathname);
    if (path !== home && !path.startsWith(home + '/')) return home;
    if ([home + '/login', home + '/logout'].includes(path)) return home;
    return path + url.search + url.hash;
  } catch { return home; }
}
