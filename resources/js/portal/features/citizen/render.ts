import original from './template-data.json';
import { all, date, one, setSafeHtml, text } from '@/lib/dom';
import type { RecordData } from '@/types/portal';
import { ticketStatuses, type TicketStatus } from '@/data/ticket-status';

export const reportCategories = [
  'waste',
  'roads',
  'water',
  'electricity',
  'environment',
  'health',
  'security',
  'other',
];

export function renderCategories(root: HTMLElement): void {
  const grid = one(root, '#cat-grid');
  if (!grid) return;
  grid.replaceChildren();
  original.categories.forEach((category, index) => {
    const element = document.createElement('div');
    element.className = 'cat-card';
    element.dataset.category = reportCategories[index];
    const icon = document.createElement('div');
    icon.className = 'cico';
    setSafeHtml(
      icon,
      '<svg viewBox="0 0 24 24" fill="none" stroke-width="1.8">' +
        original.catIcons[category.icon as keyof typeof original.catIcons] +
        '</svg>',
    );
    const label = document.createElement('span');
    label.textContent = category.label;
    element.append(icon, label);
    grid.append(element);
  });
}

export function renderReports(root: HTMLElement, records: RecordData[], filter = 'all'): void {
  const target = one(root, '#reports-list');
  if (!target) return;
  target.replaceChildren();
  records
    .filter((record) => filter === 'all' || record.status === filter)
    .forEach((record) => {
      const status = String(record.status ?? 'pending');
      const state =
        original.statusMap[
          (status === 'pending' ? 'review' : status) as keyof typeof original.statusMap
        ] ?? original.statusMap.review;
      const card = document.createElement('div');
      card.className = 'report-card';
      card.dataset.ticket = String(record.reference ?? record.id);
      setSafeHtml(
        card,
        '<div class="report-top"><span class="report-id num"></span><span class="status-badge ' +
          state.cls +
          '"><svg viewBox="0 0 24 24" fill="none" stroke-width="2">' +
          state.icon +
          '</svg><span></span></span></div><div class="report-title"></div><div class="report-meta"><span class="report-city"></span><span class="report-category"></span><span class="num report-date"></span></div><div class="report-detail"><div class="timeline"></div></div>',
      );
      text(card, '.report-id', '#' + String(record.reference ?? record.id));
      text(card, '.status-badge span', ticketStatuses[status as TicketStatus] ?? state.label);
      text(card, '.report-title', record.subject ?? record.title);
      text(
        card,
        '.report-city',
        record.city ?? (record.organization as RecordData | undefined)?.name ?? '',
      );
      const categoryIndex = reportCategories.indexOf(String(record.category));
      text(card, '.report-category', original.categories[categoryIndex]?.label ?? record.cat ?? '');
      text(card, '.report-date', record.created_at ? date(record.created_at) : record.date);
      const timeline = one(card, '.timeline');
      const events = Array.isArray(record.events) ? (record.events as RecordData[]) : [];
      events.forEach((event) => {
        const item = document.createElement('div');
        item.className = 'tl-item';
        setSafeHtml(
          item,
          '<div class="tl-dot done"></div><div class="tl-body"><b></b><span class="num"></span></div>',
        );
        text(
          item,
          'b',
          (
            {
              pending: 'ثبت گزارش',
              review: 'بررسی اولیه',
              referred: 'ارجاع به سازمان اجرایی',
              resolved: 'نتیجه نهایی',
              rejected: 'رد گزارش',
              closed: 'بسته شده',
            } as Record<string, string>
          )[String(event.to_status)] ?? event.to_status,
        );
        text(item, 'span', date(event.created_at));
        timeline?.append(item);
      });
      if (Array.isArray(record.messages))
        (record.messages as RecordData[]).forEach((message) => {
          const paragraph = document.createElement('p');
          paragraph.textContent = String(message.body ?? '');
          one(card, '.report-detail')?.append(paragraph);
        });
      target.append(card);
    });
  text(root, '#count-reports', records.length);
  all(root, '[data-report-count]').forEach(
    (element) => (element.textContent = String(records.length)),
  );
}

export function previewReports(): RecordData[] {
  return original.state.reports;
}
