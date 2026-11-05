import type { RecordData } from '@/types/portal';
import { sanitizedFragment } from './html';

export const all = <T extends Element = HTMLElement>(root: ParentNode, selector: string): T[] =>
  Array.from(root.querySelectorAll<T>(selector));
export const one = <T extends Element = HTMLElement>(root: ParentNode, selector: string) =>
  root.querySelector<T>(selector);
export const text = (root: ParentNode, selector: string, value: unknown) => {
  const element = one(root, selector);
  if (element) element.textContent = String(value ?? '');
};
export const value = (root: ParentNode, selector: string) =>
  one<HTMLInputElement>(root, selector)?.value.trim() ?? '';
export const digits = (input: string) =>
  input
    .replace(/[۰-۹]/g, (char) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(char)))
    .replace(/[٠-٩]/g, (char) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(char)));
export const date = (input: unknown) =>
  input
    ? new Intl.DateTimeFormat('fa-IR', { dateStyle: 'short' }).format(new Date(String(input)))
    : '';

export function setOptions(select: HTMLSelectElement, records: RecordData[], label = 'name'): void {
  if (select.multiple) {
    const retained = Array.from(select.selectedOptions)
      .filter((option) => !option.disabled && option.value.trim() !== '')
      .map((option) => option.cloneNode(true) as HTMLOptionElement);
    const options = records
      .filter(
        (record) =>
          record.id !== null && record.id !== undefined && String(record.id).trim() !== '',
      )
      .map(
        (record) =>
          new Option(String(record[label] ?? record.title ?? record.id), String(record.id)),
      );
    for (const option of retained)
      if (!options.some((next) => next.value === option.value)) options.push(option);
    const selected = new Set(retained.map((option) => option.value));
    for (const option of options) option.selected = selected.has(option.value);
    if (!options.length) {
      const empty = new Option('هنوز موردی برای انتخاب وجود ندارد', '');
      empty.disabled = true;
      options.push(empty);
    }
    select.replaceChildren(...options);
    return;
  }
  const current = select.value;
  select.replaceChildren(
    new Option('یک گزینه را انتخاب کنید', ''),
    ...records.map(
      (record) =>
        new Option(
          String(record[label] ?? record.title ?? record.id ?? ''),
          String(record.id ?? ''),
        ),
    ),
  );
  if (Array.from(select.options).some((option) => option.value === current)) select.value = current;
}

export function setSafeHtml(element: Element, html: string): void {
  element.replaceChildren(sanitizedFragment(html));
}

export function feedback(root: HTMLElement, message: string): void {
  let container = one<HTMLElement>(root, '#toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.setAttribute('aria-live', 'polite');
    root.append(container);
  }
  const notice = document.createElement('div');
  notice.className = 'portal-status';
  notice.setAttribute('role', 'status');
  notice.textContent = message;
  container.replaceChildren(notice);
  setTimeout(() => notice.remove(), 6000);
}

export function fieldError(root: HTMLElement, error: unknown): void {
  for (const previous of all<HTMLElement>(root, '[data-server-field-error]')) {
    const errorId = previous.id;
    for (const field of all<HTMLElement>(root, '[aria-describedby]')) {
      const ids = field
        .getAttribute('aria-describedby')!
        .split(' ')
        .filter((id) => id && id !== errorId);
      if (ids.length) field.setAttribute('aria-describedby', ids.join(' '));
      else field.removeAttribute('aria-describedby');
    }
    previous.remove();
  }
  for (const field of all<HTMLElement>(root, '[data-server-invalid]')) {
    field.removeAttribute('aria-invalid');
    field.removeAttribute('data-server-invalid');
  }
  const message = error instanceof Error ? error.message : 'عملیات ناموفق بود.';
  let status = one<HTMLElement>(root, '[data-form-error]');
  if (!status) {
    status = document.createElement('p');
    status.dataset.formError = 'true';
    status.className = 'portal-form-message';
    status.setAttribute('role', 'alert');
    root.prepend(status);
  }
  status.textContent = message;
  const errors = (error as { errors?: Record<string, string[]> } | null)?.errors ?? {};
  let first: HTMLElement | undefined;
  for (const [name, messages] of Object.entries(errors)) {
    const field = all<HTMLElement>(root, '[data-field]').find(
      (item) => item.dataset.field === name || item.dataset.field === name.split('.')[0],
    );
    if (!field) continue;
    const wrapper = field.closest<HTMLElement>('.portal-resource-field') ?? field.parentElement;
    if (!wrapper || wrapper.querySelector('[data-server-field-error]')) continue;
    const note = document.createElement('small');
    note.dataset.serverFieldError = 'true';
    note.id = (field.id || 'field-' + name.replace(/[^a-z0-9_-]/gi, '-')) + '-error';
    note.className = 'portal-field-error';
    note.textContent = messages.join(' ');
    wrapper.append(note);
    field.setAttribute('aria-invalid', 'true');
    field.dataset.serverInvalid = 'true';
    field.setAttribute(
      'aria-describedby',
      [field.getAttribute('aria-describedby'), note.id].filter(Boolean).join(' '),
    );
    for (let parent = field.parentElement; parent && parent !== root; parent = parent.parentElement)
      if (parent.tagName === 'DETAILS') (parent as HTMLDetailsElement).open = true;
    const focus =
      wrapper.querySelector<HTMLElement>('[data-image-upload], [contenteditable="true"]') ?? field;
    first ??= focus;
  }
  first?.focus();
}

export function commandArgs(source: string): string[] {
  return Array.from(
    source.matchAll(/'([^']*)'|"([^"]*)"|\b(\d+)\b/g),
    (match) => match[1] ?? match[2] ?? match[3],
  );
}

export function setImage(element: HTMLImageElement | SVGImageElement | null, media: unknown): void {
  if (!element) return;
  const item = media as { url?: string } | null;
  const url = item?.url && /^(https?:\/\/|\/)/.test(item.url) ? item.url : null;
  if (element.tagName.toLowerCase() === 'image') {
    if (url) {
      element.setAttribute('href', url);
      element.removeAttribute('xlink:href');
      element.removeAttribute('display');
    } else element.setAttribute('display', 'none');
  } else {
    const image = element as HTMLImageElement;
    if (url) {
      image.src = url;
      image.hidden = false;
    } else image.hidden = true;
  }
}
