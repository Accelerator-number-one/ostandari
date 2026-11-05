import type { PortalUser } from '@/types/portal';
import { countyReturn } from '../workspace-routes';

export const ADMIN_HOME = '/panel/admin';
export const ADMIN_LOGIN = '/panel/admin/login';

export function adminDestination(user: PortalUser, requested?: string | null): string {
  if (user.person_type === 'citizen' || user.is_active === false || !user.role?.permissions?.length)
    throw new Error('این حساب اجازهٔ ورود به پنل مدیریت را ندارد.');
  const workspace = user.portal_context;
  if (workspace?.type === 'staff') {
    const slug = workspace.staff_slug;
    if (!slug || !/^[a-z0-9-]+$/.test(slug)) throw new Error('صفحهٔ این مدیر مشخص نیست.');
    const home = '/staff-admin/' + slug;
    if (!requested || /[\\\u0000-\u001f\u007f]/.test(requested)) return home;
    try {
      const target = new URL(requested, 'https://portal.invalid');
      if (target.origin !== 'https://portal.invalid' || !(target.pathname === home || target.pathname.startsWith(home + '/')) ||
          /\/(?:login|logout)$/.test(target.pathname)) return home;
      return target.pathname + target.search + target.hash;
    } catch { return home; }
  }
  if (workspace?.type === 'county') {
    const slug = workspace.county?.slug;
    if (!slug || !/^[a-z0-9-]+$/.test(slug)) throw new Error('فرمانداری این حساب مشخص نیست.');
    return countyReturn('/county-admin/' + slug, requested);
  }
  if (!requested || /[\\\u0000-\u001f\u007f]/.test(requested)) return ADMIN_HOME;
  try {
    const url = new URL(requested, 'https://portal.invalid');
    if (url.origin !== 'https://portal.invalid' || !/^\/panel\/admin(?:\/|$)/.test(url.pathname))
      return ADMIN_HOME;
    if ([ADMIN_LOGIN, ADMIN_HOME + '/logout'].includes(url.pathname)) return ADMIN_HOME;
    return url.pathname + url.search + url.hash;
  } catch {
    return ADMIN_HOME;
  }
}

export function citizenDestination(user: PortalUser): string {
  if (user.person_type !== 'citizen' || user.is_active === false)
    throw new Error('این حساب برای ورود شهروندی معتبر نیست.');
  return '/citizen';
}
