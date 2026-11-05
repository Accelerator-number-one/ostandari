import entries from './templates.json';
import type { TemplatePage } from '@/types/portal';

export const templates = entries as TemplatePage[];

export function resolveTemplate(path: string): TemplatePage | undefined {
  const clean = '/' + path.replace(/^\/+|\/+$/g, '');
  const exact = templates.find((page) => page.path === clean);
  if (exact) return exact;
  const original = clean.replace(/^\//, '');
  const alias = templates.find(
    (page) => page.source === original || '/panel/admin/' + page.source === clean,
  );
  if (alias) return alias;
  if (/^\/news\/[^/]+$/.test(clean)) return templates.find((page) => page.path === '/news');
  if (/^\/county\/[^/]+$/.test(clean)) return templates.find((page) => page.path === '/county');
  if (/^\/governorate\/[^/]+$/.test(clean))
    return templates.find((page) => page.path === '/governorate');
  return undefined;
}
