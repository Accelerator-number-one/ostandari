export const ticketStatuses = {
  pending: 'در انتظار بررسی',
  review: 'در حال بررسی',
  referred: 'ارجاع‌شده',
  resolved: 'رسیدگی‌شده',
  rejected: 'ردشده',
  closed: 'بسته‌شده',
} as const;

export type TicketStatus = keyof typeof ticketStatuses;

export const ticketTransitions: Record<TicketStatus, readonly TicketStatus[]> = {
  pending: ['review', 'referred', 'rejected', 'closed'],
  review: ['referred', 'resolved', 'rejected', 'closed'],
  referred: ['review', 'resolved', 'rejected', 'closed'],
  resolved: ['closed', 'review'],
  rejected: ['review', 'closed'],
  closed: ['review'],
};

export const ticketUrgencies = {
  low: 'کم',
  medium: 'عادی',
  high: 'زیاد',
  urgent: 'فوری',
} as const;

export const ticketTypes = { report: 'گزارش مردمی', governor: 'پیام به استاندار' } as const;

export const ticketCategories: Record<string, string> = {
  waste: 'پسماند',
  roads: 'راه و حمل‌ونقل',
  water: 'آب و فاضلاب',
  electricity: 'برق',
  environment: 'محیط زیست',
  health: 'بهداشت',
  security: 'امنیت',
  other: 'سایر موارد',
};
