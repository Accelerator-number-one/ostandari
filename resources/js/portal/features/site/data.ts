import { api } from '@/lib/api/client';
import { safeDestination } from '@/lib/urls';
import { all, date, one, setImage, setSafeHtml, text } from '@/lib/dom';
import { bindCollection as collection } from '@/lib/template-collection';
import type { PageContext, RecordData } from '@/types/portal';

function contentCard(element: HTMLElement, record: RecordData): void {
  text(element, '.akhbar-onvan,h2,h3,.news-title,.card-title', record.title);
  text(element, '.akhbar-date,.news-date,time', date(record.published_at));
  setImage(
    one<HTMLImageElement | SVGImageElement>(element, 'img,image'),
    record.cover ?? record.image,
  );
  all<HTMLAnchorElement>(element, 'a').forEach((link) => (link.href = '/news/' + record.id));
  if (element.tagName === 'A') element.setAttribute('href', '/news/' + record.id);
  element.dataset.newsId = String(record.id);
}

export async function bindPublicData(context: PageContext): Promise<void> {
  if (context.preview) return;
  const { root, page, signal } = context;
  const locale = 'locale=' + page.lang;
  if (['/', '/en', '/ar', '/governorate'].includes(page.path)) {
    const slug = context.path.split('/')[2];
    const home = (
      await api<RecordData>(
        (page.path === '/governorate' && slug ? 'portals/' + encodeURIComponent(slug) : 'home') +
          '?' +
          locale,
        { signal },
      )
    ).data;
    collection(root, '.akhbar-container', Array.isArray(home.news) ? home.news : [], contentCard);
    const governor = home.governor as RecordData | null;
    text(root, '.container-body-left .name span', governor?.name);
    setImage(one<HTMLImageElement>(root, '.container-body-left img'), governor?.image);
    const slides = Array.isArray(home.slides) ? home.slides : [];
    collection(root, '.swiper .swiper-slide', slides, (element, record) => {
      setImage(one<HTMLImageElement | SVGImageElement>(element, 'img,image'), record.image);
      all<HTMLAnchorElement>(element, 'a').forEach(
        (link) =>
          (link.href = safeDestination(record.url, record.id ? '/service/' + record.id : '/links')),
      );
    });
    const quote = home.quote as RecordData | null;
    if (quote) text(root, '.news-ticker,.marquee-text', quote.text);
  }
  if (page.path === '/news') {
    const id = context.path.split('/')[2];
    const first = !id
      ? (await api<RecordData[]>('news?per_page=1&' + locale, { signal })).data[0]
      : null;
    if (!id && !first) {
      text(root, '.singletitle h1', '');
      return;
    }
    const detail = (
      await api<{ content: RecordData; related: RecordData[] }>('contents/' + (id ?? first?.id), {
        signal,
      })
    ).data;
    text(root, '.singletitle h1', detail.content.title);
    const section = one(root, '.singletitle')?.closest('section');
    if (section) {
      const paragraphs = all<HTMLElement>(
        section,
        '#content,.single-text,.single-content,.post-content,.singledesc,.news-body',
      );
      const body =
        paragraphs[0] ??
        all<HTMLElement>(section, 'p').find((p) => (p.textContent?.length ?? 0) > 80);
      if (body) setSafeHtml(body, String(detail.content.body ?? ''));
      setImage(
        one<HTMLImageElement>(section, '.post-image img,.singleimg img,img'),
        detail.content.cover,
      );
    }
    root.dataset.contentId = String(detail.content.id);
  }
  const listings: Record<string, [string, string]> = {
    '/governors': ['members?kind=governor', '.advar-card'],
    '/services': ['services', '.testimonial-card'],
    '/links': ['links', '.container-peyvand .mz-link-btn'],
    '/gallery': ['galleries', '.gal-item'],
  };
  const listing = listings[page.path];
  if (listing) {
    const result = (
      await api<RecordData[]>(
        listing[0] + (listing[0].includes('?') ? '&' : '?') + locale + '&per_page=100',
        { signal },
      )
    ).data;
    collection(root, listing[1], result, (element, record) => {
      text(element, 'h2,h3,h4,.title,.name,.advar-name,.gal-caption', record.title ?? record.name);
      text(element, 'p,.advar-desc', record.description ?? record.bio);
      if (page.path !== '/services')
        setImage(
          one<HTMLImageElement | SVGImageElement>(element, 'img,image'),
          record.image ?? (record.items as RecordData[] | undefined)?.[0]?.image,
        );
      if (element.tagName === 'A') {
        element.setAttribute(
          'href',
          safeDestination(record.url, record.id ? '/service/' + record.id : '/links'),
        );
        const node = Array.from(element.childNodes).find(
          (node) => node.nodeType === 3 && node.textContent?.trim(),
        );
        if (node) node.textContent = String(record.title ?? record.name ?? '');
      } else
        all<HTMLAnchorElement>(element, 'a').forEach((link) => {
          if (record.url) link.href = String(record.url);
        });
    });
  }
  if (page.path === '/services/provincial') {
    const services = (await api<RecordData[]>('services?' + locale + '&per_page=100', { signal }))
      .data;
    collection(root, 'table tbody tr', services, (row, service) => {
      const cells = all(row, 'td');
      if (cells[0]) cells[0].textContent = String(service.code ?? service.id);
      if (cells[1]) cells[1].textContent = String(service.name ?? '');
      if (cells[2]) cells[2].textContent = String(service.description ?? '');
      all<HTMLAnchorElement>(row, 'a').forEach(
        (link) => (link.href = safeDestination(service.url, '/service/' + service.id)),
      );
    });
  }
  if (page.path === '/county') {
    let slug = context.path.split('/')[2];
    if (!slug)
      slug = String(
        (await api<RecordData[]>('counties?per_page=1', { signal })).data[0]?.slug ?? '',
      );
    if (slug) {
      const endpoint = /^\d+$/.test(slug)
        ? 'counties/' + slug
        : 'counties/by-slug/' + encodeURIComponent(slug);
      const data = (await api<RecordData>(endpoint + '?' + locale, { signal })).data;
      const county = (data.county ?? data) as RecordData;
      text(root, '.county-title,.cb2-title', county.name);
    }
  }
  root.dataset.ready = 'true';
}
