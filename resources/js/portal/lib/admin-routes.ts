import { adminModules } from '@/data/admin/modules';
import { adminExtraPages } from '@/data/admin/extra-pages';
import { adminUtilityTitles } from '@/data/admin/utility-pages';
import { templates } from '@/lib/templates';
import { contextualFilters } from '@/features/admin/relations';
import type { TemplatePage } from '@/types/portal';
import { canonicalAdminPath } from './workspace-routes';

export function adminResourcePage(path: string): TemplatePage | undefined {
  const requested = path;
  path = canonicalAdminPath(path);
  const module = adminModules.find((item) => item.path === path);
  if (!module?.resource || adminUtilityTitles[path]) return;
  const page = adminExtraPages[path]
    ? {
        ...templates.find((item) => item.path === '/panel/admin/calls')!,
        ...adminExtraPages[path],
        path,
      }
    : templates.find((item) => item.path === path);
  return page
    ? {
        ...page,
        path: requested,
        title: module.title,
        defaults: {
          ...page.defaults,
          ...(page.resource === 'media'
            ? { visibility: 'public', ...(path === '/panel/admin/images' ? { kind: 'image' } : {}) }
            : {}),
        },
      }
    : undefined;
}

export function safeAdminReturn(base: string, value?: string | null): string {
  if (!value || !value.startsWith(base) || value.startsWith('//') || /[\\\r\n]/.test(value))
    return base;
  try {
    const url = new URL(value, 'https://admin.invalid');
    if (url.origin !== 'https://admin.invalid' || url.pathname !== base) return base;
    const query = new URLSearchParams(contextualFilters(url.search));
    if (canonicalAdminPath(base) === '/panel/admin/reports') {
      for (const key of ['status', 'type', 'urgency', 'order', 'overdue']) {
        const value = url.searchParams.get(key);
        if (value && /^[a-z0-9_]{1,30}$/.test(value)) query.set(key, value);
      }
      for (const key of ['from', 'to']) {
        const value = url.searchParams.get(key);
        if (value && /^\d{4}-\d{2}-\d{2}$/.test(value)) query.set(key, value);
      }
    }
    const q = url.searchParams.get('q')?.trim().slice(0, 100);
    const page = url.searchParams.get('page');
    if (q) query.set('q', q);
    if (page && /^[1-9]\d{0,6}$/.test(page)) query.set('page', page);
    return base + (query.size ? '?' + query : '');
  } catch {
    return base;
  }
}

export function adminEditorHref(base: string, id?: string | number, search = ''): string {
  const page = adminResourcePage(base);
  if (!page) return base;
  const query = new URLSearchParams(contextualFilters(search));
  query.set('return', safeAdminReturn(base, base + search));
  const suffix =
    page.resource === 'settings'
      ? '/edit'
      : id === undefined
        ? '/new'
        : '/' + encodeURIComponent(String(id)) + '/edit';
  return base + suffix + '?' + query;
}

export function resolveAdminEditor(
  path: string,
): { page: TemplatePage; mode: 'create' | 'edit'; id?: string } | undefined {
  const match = path.match(/^(\/panel\/admin\/.+?)(?:\/(new)|\/([^/]+)\/edit|\/(edit))$/);
  if (!match) return;
  const [, base, create, rawId, singleton] = match;
  const page = adminResourcePage(base);
  const module = adminModules.find((item) => item.path === base);
  if (!page || !module) return;
  if (create) return module.canCreate ? { page, mode: 'create' } : undefined;
  if (!module.canEdit) return;
  if (singleton) return page.resource === 'settings' ? { page, mode: 'edit' } : undefined;
  if (page.resource === 'settings') return;
  let id: string;
  try {
    id = decodeURIComponent(rawId);
  } catch {
    return;
  }
  if (
    ['tickets', 'submissions'].includes(page.resource)
      ? !/^[a-zA-Z0-9_-]{1,100}$/.test(id)
      : !/^[1-9]\d{0,18}$/.test(id)
  )
    return;
  return { page, mode: 'edit', id };
}

export function resolveReportEditor(path: string): string | undefined {
  return path.match(/^\/panel\/admin\/reports\/([a-zA-Z0-9_-]{1,100})\/edit$/)?.[1];
}
