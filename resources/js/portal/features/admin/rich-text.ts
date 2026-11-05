import { api } from '@/lib/api/client';
import { all, setSafeHtml } from '@/lib/dom';
import { sanitizedFragment } from '@/lib/html';
import { imageAlbumButton, imageAlbumClasses } from './image-album';

const tags = new Set([
  'P',
  'BR',
  'STRONG',
  'B',
  'EM',
  'I',
  'U',
  'UL',
  'OL',
  'LI',
  'BLOCKQUOTE',
  'H2',
  'H3',
  'H4',
  'A',
  'IMG',
  'TABLE',
  'CAPTION',
  'THEAD',
  'TBODY',
  'TFOOT',
  'TR',
  'TH',
  'TD',
  'DIV',
]);

export function cleanEditorHtml(html: string): string {
  const container = document.createElement('div');
  container.append(sanitizedFragment(html));
  for (const node of all(container, '*').reverse()) {
    if (
      node.tagName === 'DIV' &&
      !node.classList.contains('portal-image-album') &&
      !node.classList.contains('portal-image-album-item')
    ) {
      const paragraph = document.createElement('p');
      paragraph.append(...node.childNodes);
      node.replaceWith(paragraph);
    } else if (!tags.has(node.tagName)) node.replaceWith(...node.childNodes);
    else if (
      node.tagName === 'IMG' &&
      !/^(https?:\/\/|\/(?!\/))/.test(node.getAttribute('src') ?? '')
    )
      node.remove();
    for (const attribute of Array.from(node.attributes))
      if (
        ![
          'class',
          'src',
          'alt',
          'title',
          'width',
          'height',
          'href',
          'colspan',
          'rowspan',
          'scope',
        ].includes(attribute.name)
      )
        node.removeAttribute(attribute.name);
    const classes = Array.from(node.classList).filter(
      (name) =>
        ['DIV', 'P'].includes(node.tagName) &&
        imageAlbumClasses.some((allowed) => allowed === name),
    );
    if (classes.length) node.className = classes.join(' ');
    else node.removeAttribute('class');
  }
  return container.innerHTML;
}

export function createEditorTable(rows: number, columns: number): HTMLTableElement {
  if (
    !Number.isInteger(rows) ||
    !Number.isInteger(columns) ||
    rows < 1 ||
    rows > 30 ||
    columns < 1 ||
    columns > 12
  )
    throw new Error('جدول باید بین ۱ تا ۳۰ ردیف و ۱ تا ۱۲ ستون داشته باشد.');
  const table = document.createElement('table');
  const head = table.createTHead().insertRow();
  for (let column = 0; column < columns; column++) {
    const cell = document.createElement('th');
    cell.scope = 'col';
    cell.textContent = 'عنوان';
    head.append(cell);
  }
  const body = table.createTBody();
  for (let row = 0; row < rows; row++) {
    const line = body.insertRow();
    for (let column = 0; column < columns; column++)
      line.insertCell().append(document.createElement('br'));
  }
  return table;
}

function button(label: string, action: () => void): HTMLButtonElement {
  const node = document.createElement('button');
  node.type = 'button';
  node.textContent = label;
  node.addEventListener('click', action);
  return node;
}

export function mountRichText(root: HTMLElement, signal: AbortSignal, preview: boolean): void {
  for (const source of all<HTMLTextAreaElement>(root, 'textarea[data-rich-text]')) {
    if(source.closest('.portal-cms'))continue;
    const customPage=!!source.closest('[data-custom-page-builder]');
    const staffEndpoint=source.closest<HTMLElement>('[data-staff-portal-editor]')?.dataset.imageEndpoint;
    const uploadEndpoint=staffEndpoint ?? (customPage?'admin/custom-pages/image':'media');
    const imageEndpoint=staffEndpoint ?? (customPage?'admin/custom-pages/images':'media');
    const initialRequired = source.required;
    const initialHidden = source.hidden;
    const wrapper = document.createElement('div');
    wrapper.className = 'portal-cms';
    const toolbar = document.createElement('div');
    toolbar.className = 'portal-cms-toolbar';
    toolbar.setAttribute('role', 'group');
    toolbar.setAttribute('aria-label', 'قالب‌بندی متن');
    const surface = document.createElement('div');
    surface.className = 'portal-cms-surface';
    surface.contentEditable = 'true';
    surface.setAttribute('role', 'textbox');
    surface.setAttribute('aria-multiline', 'true');
    surface.setAttribute('aria-label', source.labels?.[0]?.textContent?.trim() ?? 'متن کامل');
    surface.dataset.cmsSurface = source.dataset.field;
    source.dataset.cmsRequired = String(source.required);
    source.required = false;
    source.hidden = true;
    source.before(wrapper);
    wrapper.append(toolbar, surface, source);
    signal.addEventListener(
      'abort',
      () => {
        if (wrapper.contains(source)) wrapper.replaceWith(source);
        source.required = initialRequired;
        source.hidden = initialHidden;
      },
      { once: true },
    );
    const tools = document.createElement('div');
    tools.className = 'portal-cms-tools';
    tools.hidden = true;
    const status = document.createElement('p');
    status.className = 'portal-cms-status';
    status.setAttribute('role', 'status');
    wrapper.append(tools, status);
    let saved: Range | null = null;
    let mode: 'visual' | 'source' | 'preview' = 'visual';
    let history: string[] = [];
    let position = 0;
    let recordVersion = 0;
    const remember = () => {
      const selection = window.getSelection();
      if (selection?.rangeCount && surface.contains(selection.anchorNode))
        saved = selection.getRangeAt(0).cloneRange();
    };
    const restore = () => {
      surface.focus();
      const selection = window.getSelection();
      if (!selection) return;
      selection.removeAllRanges();
      if (!saved || !surface.contains(saved.commonAncestorContainer)) {
        saved = document.createRange();
        saved.selectNodeContents(surface);
        saved.collapse(false);
      }
      selection.addRange(saved);
    };
    const sync = () => {
      source.value = cleanEditorHtml(mode === 'source' ? source.value : surface.innerHTML);
      if (source.value !== history[position]) {
        history = [...history.slice(0, position + 1), source.value];
        if (history.length > 50) history.shift();
        while (
          history.length > 2 &&
          history.reduce((size, value) => size + value.length, 0) > 2_000_000
        )
          history.shift();
        position = history.length - 1;
      }
      source.dispatchEvent(new window.Event('input', { bubbles: true }));
    };
    const reload = (reset = true) => {
      setSafeHtml(surface, cleanEditorHtml(source.value));
      saved = null;
      status.textContent = '';
      if (reset) {
        history = [source.value];
        position = 0;
      }
    };
    const undo = (offset: number) => {
      const next = position + offset;
      if (next < 0 || next >= history.length) return;
      position = next;
      source.value = history[position];
      reload(false);
      restore();
    };
    const insert = (node: Node) => {
      restore();
      const range = window.getSelection()!.getRangeAt(0);
      range.deleteContents();
      range.insertNode(node);
      range.setStartAfter(node);
      range.collapse(true);
      saved = range.cloneRange();
      sync();
      restore();
    };
    const insertBlock = (node: HTMLElement) => {
      restore();
      const range = window.getSelection()!.getRangeAt(0);
      range.deleteContents();
      const anchor =
        range.startContainer.nodeType === 1
          ? (range.startContainer as Element)
          : range.startContainer.parentElement;
      const existingAlbum = anchor?.closest('.portal-image-album');
      const paragraph = anchor?.closest('p,h2,h3,h4');
      let following: HTMLElement;
      if (!existingAlbum && paragraph && surface.contains(paragraph)) {
        const tail = range.cloneRange();
        tail.setEnd(paragraph, paragraph.childNodes.length);
        following = paragraph.cloneNode(false) as HTMLElement;
        following.append(tail.extractContents());
        paragraph.after(node, following);
        if (!paragraph.hasChildNodes()) paragraph.append(document.createElement('br'));
      } else {
        if (existingAlbum && surface.contains(existingAlbum)) {
          range.setStartAfter(existingAlbum);
          range.collapse(true);
        }
        range.insertNode(node);
        following = document.createElement('p');
        node.after(following);
      }
      if (!following.hasChildNodes()) following.append(document.createElement('br'));
      saved = document.createRange();
      saved.selectNodeContents(following);
      saved.collapse(true);
      sync();
      restore();
    };
    const format = (command: string, value?: string) => {
      if (command === 'undo' || command === 'redo') {
        undo(command === 'undo' ? -1 : 1);
        return;
      }
      restore();
      if (typeof document.execCommand === 'function') document.execCommand(command, false, value);
      remember();
      sync();
    };
    toolbar.addEventListener(
      'mousedown',
      (event) => {
        if ((event.target as HTMLElement).closest('button')) event.preventDefault();
      },
      { signal },
    );
    for (const [label, command, value] of [
      ['پررنگ', 'bold'],
      ['مورب', 'italic'],
      ['زیرخط', 'underline'],
      ['پاراگراف', 'formatBlock', 'p'],
      ['عنوان ۲', 'formatBlock', 'h2'],
      ['عنوان ۳', 'formatBlock', 'h3'],
      ['فهرست نقطه‌ای', 'insertUnorderedList'],
      ['فهرست شماره‌ای', 'insertOrderedList'],
      ['نقل‌قول', 'formatBlock', 'blockquote'],
      ['پاک‌کردن قالب', 'removeFormat'],
      ['واگرد', 'undo'],
      ['بازانجام', 'redo'],
    ])
      toolbar.append(button(label, () => format(command, value)));
    const openTools = (content: Node[]) => {
      if (wrapper.dataset.uploading === 'true') return;
      remember();
      tools.replaceChildren(...content);
      tools.hidden = false;
      tools.querySelector<HTMLInputElement>('input,select')?.focus();
    };
    const input = (label: string, type = 'text', initial = '') => {
      const element = document.createElement('input');
      element.type = type;
      element.value = initial;
      element.setAttribute('aria-label', label);
      const field = document.createElement('label');
      field.textContent = label;
      field.append(element);
      return { field, element };
    };
    if (source.dataset.field === 'body')
      toolbar.append(
        imageAlbumButton({uploadEndpoint,
          wrapper,
          surface,
          tools,
          status,
          signal,
          preview,
          version: () => recordVersion,
          openTools,
          insert: insertBlock,
        }),
      );
    toolbar.append(
      button('پیوند', () => {
        const url = input('نشانی پیوند', 'url');
        const title = input('متن پیوند');
        openTools([
          url.field,
          title.field,
          button('درج پیوند', () => {
            if (!/^(https?:\/\/|mailto:|\/(?!\/))/.test(url.element.value.trim())) {
              status.textContent = 'نشانی پیوند معتبر نیست.';
              return;
            }
            const link = document.createElement('a');
            link.href = url.element.value.trim();
            link.textContent =
              title.element.value.trim() || saved?.toString() || url.element.value.trim();
            insert(link);
            tools.hidden = true;
          }),
        ]);
      }),
    );
    toolbar.append(
      button('تصویر', () => {
        const file = input('انتخاب تصویر', 'file');
        file.element.accept = 'image/jpeg,image/png,image/webp';
        file.element.dataset.cmsFile = 'true';
        const alt = input('توضیح تصویر');
        const existing = document.createElement('select');
        existing.setAttribute('aria-label', 'انتخاب از تصاویر بارگذاری‌شده');
        existing.append(new Option('انتخاب از کتابخانه', ''));
        let images: { id: number; url?: string; original_name?: string; mime?: string }[] = [];
        const insertImage = (url: string) => {
          if (!/^(https?:\/\/|\/(?!\/))/.test(url)) throw new Error('نشانی تصویر معتبر نیست.');
          const image = document.createElement('img');
          image.src = url;
          image.alt = alt.element.value.trim();
          insert(image);
          tools.hidden = true;
        };
        const send = button('درج تصویر', () => {
          if (wrapper.dataset.uploading === 'true') return;
          if (preview) {
            status.textContent = 'برای ذخیرهٔ تصویر، اتصال واقعی به سرور لازم است.';
            return;
          }
          const chosen = file.element.files?.[0];
          if (!chosen) {
            const record = images.find((image) => String(image.id) === existing.value);
            if (record?.url) insertImage(record.url);
            else status.textContent = 'یک تصویر انتخاب کنید.';
            return;
          }
          wrapper.dataset.uploading = 'true';
          send.disabled = true;
          status.textContent = 'در حال بارگذاری تصویر…';
          void (async () => {
            const { prepareUpload } = await import('./uploads');
            const body = new FormData();
            body.set('file', await prepareUpload(chosen));
            body.set('visibility', 'public');
            body.set('alt', alt.element.value.trim());
            const result = await api<{ id: number; url: string }>(uploadEndpoint, {
              method: 'POST',
              body,
              signal,
            });
            if (!signal.aborted) {
              insertImage(result.data.url);
              status.textContent = 'تصویر در متن درج شد.';
            }
          })()
            .catch((error) => {
              if (!signal.aborted) status.textContent = error.message;
            })
            .finally(() => {
              wrapper.dataset.uploading = 'false';
              send.disabled = false;
            });
        });
        openTools([file.field, existing, alt.field, send]);
        if (!preview)
          void api<typeof images>(imageEndpoint+'?visibility=public&per_page=100', { signal })
            .then((result) => {
              if (signal.aborted) return;
              images = result.data.filter((image) => image.url && image.mime?.startsWith('image/'));
              existing.append(
                ...images.map(
                  (image) => new Option(image.original_name ?? String(image.id), String(image.id)),
                ),
              );
            })
            .catch(() => {
              if (!signal.aborted)
                status.textContent = 'کتابخانه دریافت نشد؛ می‌توانید تصویر تازه بارگذاری کنید.';
            });
      }),
    );
    toolbar.append(
      button('جدول', () => {
        const rows = input('تعداد ردیف', 'number', '3');
        rows.element.min = '1';
        rows.element.max = '30';
        const columns = input('تعداد ستون', 'number', '3');
        columns.element.min = '1';
        columns.element.max = '12';
        openTools([
          rows.field,
          columns.field,
          button('درج جدول', () => {
            try {
              insert(createEditorTable(Number(rows.element.value), Number(columns.element.value)));
              tools.hidden = true;
            } catch (error) {
              status.textContent = (error as Error).message;
            }
          }),
        ]);
      }),
    );
    const tableAction = (action: 'row' | 'column' | 'remove') => {
      restore();
      const node = saved?.commonAncestorContainer;
      const element = node?.nodeType === 1 ? (node as Element) : node?.parentElement;
      const table = element?.closest('table');
      if (!table || !surface.contains(table)) {
        status.textContent = 'ابتدا داخل جدول کلیک کنید.';
        return;
      }
      if (action === 'remove') table.remove();
      else if (action === 'row') {
        if (table.rows.length >= 31) {
          status.textContent = 'حداکثر ۳۰ ردیف مجاز است.';
          return;
        }
        const row = (table.tBodies[0] ?? table.createTBody()).insertRow();
        for (let i = 0; i < table.rows[0].cells.length; i++)
          row.insertCell().append(document.createElement('br'));
      } else {
        if (table.rows[0].cells.length >= 12) {
          status.textContent = 'حداکثر ۱۲ ستون مجاز است.';
          return;
        }
        for (const row of Array.from(table.rows)) {
          const cell = document.createElement(row.parentElement?.tagName === 'THEAD' ? 'th' : 'td');
          cell.append(document.createElement('br'));
          row.append(cell);
        }
      }
      sync();
    };
    toolbar.append(
      button('افزودن ردیف', () => tableAction('row')),
      button('افزودن ستون', () => tableAction('column')),
      button('حذف جدول', () => tableAction('remove')),
    );
    const modes = document.createElement('div');
    modes.className = 'portal-cms-modes';
    modes.setAttribute('role', 'group');
    modes.setAttribute('aria-label', 'حالت ویرایشگر');
    for (const [value, label] of [
      ['visual', 'ویرایش متن'],
      ['source', 'کد HTML'],
      ['preview', 'پیش‌نمایش'],
    ] as const) {
      const tab = button(label, () => {
        if (wrapper.dataset.uploading === 'true') {
          status.textContent = 'ابتدا بارگذاری تصاویر را کامل کنید.';
          return;
        }
        sync();
        mode = value;
        surface.hidden = mode === 'source';
        source.hidden = mode !== 'source';
        toolbar.hidden = mode !== 'visual';
        tools.hidden = true;
        surface.contentEditable = String(mode === 'visual');
        reload(false);
        for (const control of all(modes, 'button'))
          control.setAttribute('aria-pressed', String(control === tab));
      });
      tab.setAttribute('aria-pressed', String(value === mode));
      modes.append(tab);
    }
    wrapper.prepend(modes);
    surface.addEventListener(
      'input',
      () => {
        remember();
        sync();
      },
      { signal },
    );
    surface.addEventListener(
      'keydown',
      (event) => {
        if (!(event.ctrlKey || event.metaKey) || !['z', 'y'].includes(event.key.toLowerCase()))
          return;
        event.preventDefault();
        undo(event.key.toLowerCase() === 'y' || event.shiftKey ? 1 : -1);
      },
      { signal },
    );
    for (const event of ['keyup', 'mouseup', 'blur'])
      surface.addEventListener(event, remember, { signal });
    surface.addEventListener(
      'paste',
      (event) => {
        event.preventDefault();
        remember();
        const transfer = (event as ClipboardEvent).clipboardData;
        const fragment = document.createElement('span');
        if (transfer?.getData('text/html'))
          setSafeHtml(fragment, cleanEditorHtml(transfer.getData('text/html')));
        else fragment.textContent = transfer?.getData('text/plain') ?? '';
        insert(fragment);
        sync();
      },
      { signal },
    );
    source.addEventListener('change', () => reload(), { signal });
    root.addEventListener(
      'portal:fields-filled',
      () => {
        recordVersion++;
        if (wrapper.dataset.albumUploading === 'true') {
          wrapper.dataset.uploading = 'false';
          delete wrapper.dataset.albumUploading;
        }
        mode = 'visual';
        source.hidden = true;
        surface.hidden = false;
        surface.contentEditable = 'true';
        toolbar.hidden = false;
        tools.hidden = true;
        for (const tab of all(modes, 'button'))
          tab.setAttribute('aria-pressed', String(tab.textContent === 'ویرایش متن'));
        reload();
      },
      { signal },
    );
    reload();
  }
}

export function validateRichText(root: HTMLElement): void {
  if (root.querySelector('.portal-cms[data-uploading="true"]'))
    throw new Error('بارگذاری تصویر هنوز تمام نشده است.');
  for (const source of all<HTMLTextAreaElement>(root, 'textarea[data-rich-text]')) {
    source.value = cleanEditorHtml(source.value);
    if (source.value.length > 1_000_000) throw new Error('حجم متن بیش از حد مجاز است.');
    if (source.dataset.cmsRequired !== 'true') continue;
    const element = document.createElement('div');
    element.innerHTML = source.value;
    if (
      !element.textContent?.replace(/\u00a0/g, '').trim() &&
      !element.querySelector('img,table')
    ) {
      source.closest('.portal-cms')?.querySelector<HTMLElement>('.portal-cms-surface')?.focus();
      throw new Error('متن کامل را وارد کنید.');
    }
  }
}
