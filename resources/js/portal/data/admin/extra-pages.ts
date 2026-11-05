export const adminExtraPages: Record<
  string,
  { title: string; resource: string; defaults: Record<string, string> }
> = {
  '/panel/admin/announcements': {
    title: 'اطلاعیه‌ها',
    resource: 'contents',
    defaults: { type: 'announcement' },
  },
  '/panel/admin/resolutions': {
    title: 'مصوبات',
    resource: 'contents',
    defaults: { type: 'resolution' },
  },
  '/panel/admin/documents': {
    title: 'اسناد و قوانین',
    resource: 'contents',
    defaults: { type: 'document' },
  },
  '/panel/admin/tenders': {
    title: 'مناقصه‌ها و مزایده‌ها',
    resource: 'contents',
    defaults: { type: 'tender' },
  },
  '/panel/admin/contracts': {
    title: 'قراردادها',
    resource: 'contents',
    defaults: { type: 'contract' },
  },
  '/panel/admin/events': {
    title: 'رویدادها',
    resource: 'contents',
    defaults: { type: 'event' },
  },
  '/panel/admin/duties': {
    title: 'شرح وظایف واحدها',
    resource: 'contents',
    defaults: { type: 'duty' },
  },

  '/panel/admin/news': { title: 'اخبار', resource: 'contents', defaults: { type: 'news' } },
  '/panel/admin/pages': { title: 'صفحات داخلی', resource: 'contents', defaults: { type: 'page' } },
  '/panel/admin/counties': { title: 'شهرستان‌ها', resource: 'counties', defaults: {} },
  '/panel/admin/services': { title: 'خدمات', resource: 'services', defaults: {} },
  '/panel/admin/galleries': { title: 'آلبوم‌ها', resource: 'galleries', defaults: {} },
  '/panel/admin/gallery-items': { title: 'تصاویر گالری', resource: 'gallery-items', defaults: {} },
  '/panel/admin/poll-options': {
    title: 'گزینه‌های نظرسنجی',
    resource: 'poll-options',
    defaults: {},
  },
  '/panel/admin/governors': {
    title: 'استانداران',
    resource: 'members',
    defaults: { kind: 'governor' },
  },
  '/panel/admin/county-governors': {
    title: 'فرمانداران',
    resource: 'members',
    defaults: { kind: 'county_governor' },
  },
  '/panel/admin/advisors': {
    title: 'مشاوران',
    resource: 'members',
    defaults: { kind: 'advisor' },
  },
  '/panel/admin/form-categories': {
    title: 'گروه‌بندی فرم‌ها',
    resource: 'categories',
    defaults: { type: 'form' },
  },
  '/panel/admin/forum-replies': {
    title: 'پاسخ‌های تالار',
    resource: 'moderation/forum-replies',
    defaults: {},
  },
};
