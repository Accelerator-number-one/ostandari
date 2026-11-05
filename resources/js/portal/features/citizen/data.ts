import { api } from '@/lib/api/client';
import { all, date, one, setOptions, text } from '@/lib/dom';
import { bindCollection } from '@/lib/template-collection';
import type { PageContext, RecordData } from '@/types/portal';

function plainText(value: unknown): string {
  return new DOMParser().parseFromString(String(value ?? ''), 'text/html').body.textContent ?? '';
}

export function renderCitizenCatalog(
  root: HTMLElement,
  news: RecordData[],
  faqs: RecordData[],
  organizations: RecordData[],
) {
  bindCollection(root, '#view-news .news-card', news, (card, article) => {
    text(card, 'h4', article.title);
    text(card, 'p', article.excerpt ?? plainText(article.body));
    const category = article.category as RecordData | null;
    text(
      card,
      '.news-tag',
      [date(article.published_at), category?.name].filter(Boolean).join(' · '),
    );
  });
  bindCollection(root, '#faq-list .faq-item', faqs, (item, faq) => {
    item.classList.remove('open');
    const question = one(item, '.faq-q');
    const label = Array.from(question?.childNodes ?? []).find((node) => node.nodeType === 3);
    if (label) label.textContent = String(faq.question ?? '');
    text(item, '.faq-a', plainText(faq.answer));
  });
  bindCollection(root, '#view-directory .dir-card.util', organizations, (card, organization) => {
    text(card, 'b', organization.name);
    text(card, '.num', organization.phone);
  });
}

export function renderPoll(
  root: HTMLElement,
  poll: RecordData,
  voted = false,
  selectedId?: number,
) {
  text(root, '#view-poll h3', poll.question);
  const options = Array.isArray(poll.options) ? (poll.options as RecordData[]) : [];
  bindCollection(root, '#poll-options .poll-opt', options, (element, option) => {
    element.dataset.option = String(option.id);
    delete element.dataset.opt;
    delete element.dataset.pct;
    element.classList.toggle('voted', voted);
    element.classList.toggle('chosen', option.id === selectedId);
    const percentage = Math.max(0, Math.min(100, Number(option.percentage) || 0));
    text(element, '.poll-opt-inner span:first-child', option.title);
    text(element, '.pct', percentage + '%');
    one<HTMLElement>(element, '.poll-fill')?.style.setProperty(
      'width',
      voted ? percentage + '%' : '0%',
    );
  });
  const note = one<HTMLElement>(root, '#poll-note');
  if (note) {
    note.hidden = !voted;
    note.style.display = voted ? 'flex' : 'none';
  }
}

export async function loadCitizenCatalog(
  context: PageContext,
  read: typeof api = api,
): Promise<RecordData | null> {
  const { root, signal } = context;
  const [counties, polls, news, faqs, organizations] = await Promise.all([
    read<RecordData[]>('counties?per_page=100', { signal }),
    read<RecordData[]>('polls?per_page=100', { signal }),
    read<RecordData[]>('news?per_page=20', { signal }),
    read<RecordData[]>('faqs?per_page=100', { signal }),
    read<RecordData[]>('organizations?per_page=100', { signal }),
  ]);
  if (signal.aborted) return null;
  all<HTMLSelectElement>(root, '#rep-city,#view-profile select').forEach((select) =>
    setOptions(select, counties.data),
  );
  renderCitizenCatalog(root, news.data, faqs.data, organizations.data);
  const poll = polls.data.find(
    (item) =>
      item.status === 'active' &&
      (!item.ends_at || new Date(String(item.ends_at)).getTime() > Date.now()),
  );
  if (!poll) {
    one(root, '#poll-options')?.replaceChildren();
    text(root, '#view-poll h3', '');
    return null;
  }
  const detail = (await read<RecordData>('polls/' + poll.id, { signal })).data;
  if (!signal.aborted) renderPoll(root, detail);
  return detail;
}
