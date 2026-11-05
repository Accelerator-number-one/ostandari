import { adminModuleCopy } from './module-copy';
import { templates } from '@/lib/templates';
import schemas from './resource-schema.json';
import { adminExtraPages } from './extra-pages';
import { adminUtilityTitles } from './utility-pages';
import { adminSectionFor, adminSectionOrder, reviewResource } from './sections';

const names: Record<string, string> = {
  '/panel/admin/menu-items': 'آیتم‌های منو',
  '/panel/admin/menu-groups': 'فهرست‌های سایت',
  '/panel/admin/slide-groups': 'گروه‌بندی اسلایدها',
  '/panel/admin/slides': 'اسلایدشو',
  '/panel/admin/member-groups': 'گروه‌های مدیران',
  '/panel/admin/members': 'مدیران',
  '/panel/admin/document-groups': 'گروه‌های اسناد',
  '/panel/admin/service-groups': 'گروه‌های خدمات',
  '/panel/admin/calls': 'فراخوان‌ها',
  '/panel/admin/form-groups': 'ویژگی‌های فرم',
  '/panel/admin/gallery-groups': 'گروه‌های گالری',
  '/panel/admin/item-order': 'تنظیمات پیشرفته',
  '/panel/admin/external-users': 'کاربران مردمی',
  '/panel/admin/internal-users': 'کاربران مدیریتی',
  '/panel/admin/news-groups': 'گروه‌های خبر',
  '/panel/admin/publications': 'انتشارات',
  '/panel/admin/forms': 'فرم‌ساز',
  '/panel/admin/employee-list': 'فهرست کارمندان',
  '/panel/admin/employees': 'کارمندان',
  '/panel/admin/experts': 'کارشناسان فرم',
  '/panel/admin/subscribers': 'اعضای خبرنامه',
  '/panel/admin/organizations': 'استان و فرمانداری‌ها',
  '/panel/admin/files': 'فایل‌ها',
  '/panel/admin/images': 'تصاویر',
  '/panel/admin/submissions': 'درخواست‌های فرم',
  '/panel/admin/media-groups': 'گروه‌های چندرسانه‌ای',
  '/panel/admin/roles': 'نقش‌ها و دسترسی‌ها',
  '/panel/admin/comments/pending': 'نظرات در انتظار',
  '/panel/admin/comments/replied': 'نظرات تأییدشده',
  '/panel/admin/polls': 'نظرسنجی‌ها',
  '/panel/admin/governor/pending': 'پیام‌های جدید استاندار',
  '/panel/admin/governor/replied': 'پاسخ‌های استاندار',
  '/panel/admin/links': 'پیوندها',
  '/panel/admin/forum-topics': 'موضوعات تالار گفتگو',
  '/panel/admin/faqs': 'پرسش‌های متداول',
  '/panel/admin/quotes': 'سخن روز',
  '/panel/admin/advertisements': 'تبلیغات و بنرها',
  '/panel/admin/tags': 'برچسب‌های خبر',
  '/panel/admin/forum-groups': 'گروه‌های تالار گفتگو',
  '/panel/admin/organization-contact': 'اطلاعات تماس استان و فرمانداری‌ها',
  '/panel/admin/contact/pending': 'پیام‌های جدید',
  '/panel/admin/contact/replied': 'پیام‌های پاسخ‌داده‌شده',
  '/panel/admin/contact/approved': 'پیام‌های در حال بررسی',
};
const specialPermissions: Record<string, string> = {
  users: 'users.manage',
  roles: 'roles.manage',
  settings: 'settings.manage',
  media: 'media.manage',
  tickets: 'tickets.manage',
  submissions: 'forms.manage',
  subscribers: 'newsletter.manage',
  'moderation/comments': 'comments.manage',
  'moderation/forum-topics': 'forum.manage',
  'moderation/forum-replies': 'forum.manage',
};
export function resourcePermission(resource: string): string {
  return (
    (schemas as Record<string, { permission: string }>)[resource]?.permission ??
    specialPermissions[resource] ??
    ''
  );
}
export const adminModules = [
  ...new Map(
    [
      ...templates
        .filter((page) => page.group === 'admin' && page.resource)
        .map((page) => ({
          path: page.path,
          title: names[page.path] ?? page.title,
          permission: resourcePermission(page.resource),
          resource: page.resource,
        })),
      ...Object.entries(adminExtraPages).map(([path, page]) => ({
        path,
        title: page.title,
        permission: resourcePermission(page.resource),
        resource: page.resource,
      })),
      ...Object.entries(adminUtilityTitles)
        .filter(([path]) => !['/panel/admin/logout', '/panel/admin/account'].includes(path))
        .map(([path, title]) => ({
          path,
          title,
          resource: '',
          permission:
            path === '/panel/admin/reports'
              ? 'tickets.manage'
              : path === '/panel/admin/annual-slogan'
                ? 'media.manage'
                : path === '/panel/admin/settings'
                  ? 'settings.manage'
                  : 'audit.view',
        })),
    ].map((page) => [page.path, page]),
  ).values(),
]
  .map((page) => ({
    ...page,
    section: adminSectionFor(page.path),
    showInNavigation: page.path !== '/panel/admin/employee-list',
    canCreate:
      Boolean(page.resource) &&
      !reviewResource(page.resource) &&
      page.resource !== 'settings' &&
      page.path !== '/panel/admin/organization-contact',
    canEdit: Boolean(page.resource) && page.resource !== 'media',
    ...adminModuleCopy(page.path, page.resource, page.title),
  }))
  .sort((a, b) => adminSectionOrder(a.path) - adminSectionOrder(b.path));
