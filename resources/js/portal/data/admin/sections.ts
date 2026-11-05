export const adminSections = [
  {
    id: 'content',
    title: 'اخبار و محتوای سایت',
    description: 'خبرها، اطلاعیه‌ها، اسناد و صفحات داخلی',
  },
  {
    id: 'people',
    title: 'مدیران و اطلاعات استان',
    description: 'استاندار، فرمانداران، مدیران و اطلاعات استان',
  },
  {
    id: 'appearance',
    title: 'نمایش و منوهای سایت',
    description: 'منوها، اسلایدها، بنرها و پیوندها',
  },
  { id: 'services', title: 'خدمات و راهنما', description: 'خدمات الکترونیکی و پرسش‌های متداول' },
  {
    id: 'forms',
    title: 'فرم‌ها و کارمندان',
    description: 'ساخت فرم، کارشناسان و بررسی درخواست‌ها',
  },
  {
    id: 'community',
    title: 'مشارکت و ارتباطات',
    description: 'تالار گفتگو، نظرات، نظرسنجی و خبرنامه',
  },
  {
    id: 'reports',
    title: 'گزارش‌ها و پیام‌های مردمی',
    description: 'رسیدگی، ارجاع، تغییر وضعیت و پاسخ به مردم',
  },
  { id: 'media', title: 'تصاویر و فایل‌ها', description: 'کتابخانهٔ رسانه و گالری‌های سایت' },
  { id: 'users', title: 'کاربران و دسترسی‌ها', description: 'حساب‌های مدیریتی و مردمی و نقش‌ها' },
  { id: 'system', title: 'تنظیمات و نظارت', description: 'تنظیمات سایت و تاریخچهٔ عملیات مدیران' },
] as const;
export type AdminSectionId = (typeof adminSections)[number]['id'];

const paths: Record<AdminSectionId, readonly string[]> = {
  content: [
    'news',
    'news-groups',
    'tags',
    'announcements',
    'resolutions',
    'documents',
    'document-groups',
    'tenders',
    'contracts',
    'events',
    'duties',
    'pages',
    'calls',
    'publications',
  ],
  people: [
    'governors',
    'county-governors',
    'advisors',
    'members',
    'member-groups',
    'counties',
    'organizations',
    'organization-contact',
  ],
  appearance: [
    'annual-slogan',
    'menu-groups',
    'menu-items',
    'slide-groups',
    'slides',
    'advertisements',
    'links',
    'quotes',
  ],
  services: ['services', 'service-groups', 'faqs'],
  forms: [
    'forms',
    'form-categories',
    'form-groups',
    'submissions',
    'employee-list',
    'employees',
    'experts',
  ],
  community: [
    'polls',
    'poll-options',
    'forum-groups',
    'forum-topics',
    'forum-replies',
    'comments/pending',
    'comments/replied',
    'subscribers',
  ],
  reports: [
    'reports',
    'governor/pending',
    'governor/replied',
    'contact/pending',
    'contact/replied',
    'contact/approved',
  ],
  media: ['files', 'images', 'media-groups', 'galleries', 'gallery-groups', 'gallery-items'],
  users: ['external-users', 'internal-users', 'roles'],
  system: ['settings', 'item-order', 'audit-logs'],
};

export function adminSectionFor(path: string): AdminSectionId {
  return (
    adminSections.find((section) =>
      paths[section.id].includes(path.replace(/^\/panel\/admin\//, '')),
    )?.id ?? 'system'
  );
}

export function reviewResource(resource: string): boolean {
  return (
    ['tickets', 'submissions', 'subscribers'].includes(resource) ||
    resource.startsWith('moderation/')
  );
}

export function adminSectionOrder(path: string): number {
  const key = path.replace(/^\/panel\/admin\//, '');
  const section = adminSections.findIndex((item) => paths[item.id].includes(key));
  return section < 0 ? 10000 : section * 100 + paths[adminSections[section].id].indexOf(key);
}
