import { adminModuleCopy } from '@/data/admin/module-copy';
import { mountImageFields } from './image-fields';
import { normalizeAdminSearch } from '@/lib/admin-search';
import { api, ApiError, jsonBody, upload } from '@/lib/api/client';
import { all, date, feedback, fieldError, one, setImage, setOptions } from '@/lib/dom';
import { closeOverlays, rowActions, toggleModal } from '@/lib/alpine/ui';
import { collectFields, fieldForColumn, fillFields } from './fields';
import { lookupRecords, lookupResource, resourceRows, resourceScope } from './resources';
import type { PageContext, RecordData } from '@/types/portal';
import { mountRichText, validateRichText } from './rich-text';
import { prepareUpload } from './uploads';
import { mountFormBuilder, validateFormBuilder } from './form-builder';
import { mountPermissionFields } from './permission-fields';
import { contextualFilters, relatedAdminLinks } from './relations';
import { ticketTransitions } from '@/data/ticket-status';
import { adminEditorHref } from '@/lib/admin-routes';
import { canonicalAdminPath, workspaceAdminHref } from '@/lib/workspace-routes';
import { mountCountyPortal } from './county-portal';

export function adminController(context: PageContext) {
  const { root, page, signal, preview } = context;
  const itemName = adminModuleCopy(canonicalAdminPath(page.path), page.resource, page.title).itemName;
  const workspaceHref = (path: string) => workspaceAdminHref(root.dataset.workspaceHome ?? '/panel/admin', path);
  const readOnly = root.dataset.canWrite === 'false';
  const ownOrganization =
    root.dataset.workspaceGlobal === 'false' &&
    ['organizations', 'counties'].includes(page.resource);
  let records: RecordData[] = [];
  let editing: RecordData | null = null;
  let busy = false;
  let editorLoaded = !page.editor || page.editor.mode === 'create';
  let editorSaved = false;
  const listPages = root.dataset.adminView === 'list';
  const navigate = context.navigate ?? ((href: string) => window.location.assign(href));
  let imageFields: ReturnType<typeof mountImageFields> | undefined;
  let uploaded: { file: File; id: number } | null = null;
  let currentQuery = new URLSearchParams(window.location.search).get('q')?.slice(0, 100) ?? '';
  const initialPage = new URLSearchParams(window.location.search).get('page') ?? '';
  let currentPage = /^[1-9]\d{0,6}$/.test(initialPage) ? Number(initialPage) : 1;
  let loadVersion = 0;
  let editVersion = 0;
  let optionsReady = Promise.resolve();
  const filters = contextualFilters(window.location.search);
  const tbody = one<HTMLTableSectionElement>(
    root,
    '#educationTableBody, .main-content table tbody',
  );
  const prototype = tbody?.querySelector('tr')?.cloneNode(true) as HTMLTableRowElement | undefined;
  const headers = all(root, '.main-content table thead th').map(
    (cell) =>
      cell.getAttribute('data-column') ?? fieldForColumn(cell.textContent ?? '', page.resource),
  );
  const modal = () =>
    one<HTMLElement>(root, '[data-admin-record-editor],#cbnCreateMenuModalOverlay');
  const endpoint = page.resource === 'media' ? 'media' : 'admin/' + page.resource;
  const reviewOnly =
    ['tickets', 'submissions', 'subscribers'].includes(page.resource) ||
    page.resource.startsWith('moderation/');
  if (prototype)
    all(prototype, '#cbnOpenCreateMenuModalBtn').forEach((button) => button.removeAttribute('id'));
  all(root, 'tbody #cbnOpenCreateMenuModalBtn').forEach((button) => button.removeAttribute('id'));
  const writeable = () => {
    if (readOnly) throw new Error('این حساب فقط امکان مشاهدهٔ اطلاعات را دارد.');
    if (preview)
      throw new Error(
        'حالت نمایش طرح فعال است. برای ثبت واقعی، TEMPLATE_PREVIEW=false و بک‌اند را اجرا کنید.',
      );
  };

  function listSearch() {
    const query = new URLSearchParams(filters);
    if (currentQuery) query.set('q', currentQuery);
    if (currentPage > 1) query.set('page', String(currentPage));
    return query.size ? '?' + query : '';
  }

  function pageLink(control: HTMLElement, id?: string | number) {
    const link = document.createElement('a');
    for (const attribute of Array.from(control.attributes))
      if (
        !['type', 'role', 'data-command', 'data-command-event', 'data-command-args'].includes(
          attribute.name,
        )
      )
        link.setAttribute(attribute.name, attribute.value);
    link.append(...control.childNodes);
    link.href = adminEditorHref(page.path, id, listSearch());
    link.dataset.navigate = link.getAttribute('href')!;
    control.replaceWith(link);
    return link;
  }

  function editorState(ready: boolean, message: string) {
    editorLoaded = ready;
    const fields = one<HTMLFieldSetElement>(root, '[data-editor-fields]');
    if (fields) {
      fields.disabled = !ready || busy;
      fields.inert = !ready || busy;
    }
    for (const button of all<HTMLButtonElement>(root, '[data-editor-save]'))
      button.disabled = !ready || busy;
    const status = one(root, '[data-editor-status]');
    if (status) status.textContent = message;
  }

  async function loadEditor() {
    if (!page.editor) return;
    const version = ++editVersion;
    const retry = one<HTMLElement>(root, '[data-editor-retry]');
    if (retry) retry.hidden = true;
    if (page.editor.mode === 'create') {
      fillFields(modal()!, {
        locale: 'fa',
        priority: 0,
        is_active: true,
        ...(root.dataset.organizationId
          ? { organization_id: Number(root.dataset.organizationId) }
          : {}),
        ...filters,
        ...resourceScope(page.resource),
        ...page.defaults,
      });
      editorState(
        true,
        preview
          ? 'نمایش طرح؛ ذخیرهٔ واقعی به اتصال بک‌اند نیاز دارد.'
          : 'اطلاعات مورد جدید را وارد کنید.',
      );
      root.dataset.ready = 'true';
      return;
    }
    editorState(false, 'در حال دریافت اطلاعات…');
    try {
      if (preview) throw new Error('برای ویرایش اطلاعات واقعی، وارد حساب متصل به بک‌اند شوید.');
      const [result] = await Promise.all([
        api<RecordData | RecordData[]>(
          endpoint +
            (page.resource === 'settings' ? '' : '/' + encodeURIComponent(page.editor.id!)),
          { signal },
        ),
        optionsReady,
      ]);
      if (signal.aborted || version !== editVersion) return;
      if (page.resource === 'settings') {
        records = resourceRows('settings', result.data);
        editing = {};
      } else {
        const next = result.data as RecordData;
        if (
          !next ||
          typeof next !== 'object' ||
          Array.isArray(next) ||
          String(next.reference ?? next.id) !== page.editor.id
        )
          throw new Error('اطلاعات این مورد قابل دریافت نیست.');
        for (const [key, value] of Object.entries({
          ...resourceScope(page.resource),
          ...page.defaults,
        }))
          if (['type', 'kind', 'person_type'].includes(key) && String(next[key]) !== value)
            throw new Error('این مورد متعلق به بخش انتخاب‌شده نیست. از فهرست همان بخش وارد شوید.');
        editing = next;
      }
      showEditing();
      if (!one(root, '[data-image-field]'))
        setImage(
          one<HTMLImageElement>(root, '[data-editor-current-image]'),
          editing?.image ?? editing?.cover,
        );
      const systemRole = page.resource === 'roles' && editing?.is_system;
      editorState(
        !systemRole,
        systemRole
          ? 'نقش سیستمی قابل ویرایش نیست؛ برای دسترسی‌های سفارشی، یک نقش جدید بسازید.'
          : 'اطلاعات دریافت شد. پس از تغییر، ذخیره را انتخاب کنید.',
      );
    } catch (error) {
      if (signal.aborted || version !== editVersion) return;
      editing = null;
      editorState(false, error instanceof Error ? error.message : 'دریافت اطلاعات انجام نشد.');
      if (retry) retry.hidden = false;
    }
    root.dataset.ready = 'true';
  }

  function paint() {
    if (!tbody || !prototype) return;
    tbody.replaceChildren();
    records.forEach((record, index) => {
      const row = prototype.cloneNode(true) as HTMLTableRowElement;
      row.dataset.record = String(record.reference ?? record.id ?? index);
      if (listPages && !readOnly && page.resource !== 'media')
        all<HTMLElement>(
          row,
          '[data-command="editRecord"],[data-command="openEditCommitmentModal"]',
        ).forEach((button) => {
          const link = pageLink(button, String(record.reference ?? record.id));
          link.setAttribute(
            'aria-label',
            (reviewOnly ? 'بررسی ' : 'ویرایش ') +
              String(
                record.title ||
                  record.name ||
                  (['slides', 'advertisements'].includes(page.resource)
                    ? 'اسلاید تصویری ' + record.id
                    : record.reference || record.id),
              ),
          );
        });
      all<HTMLAnchorElement>(row, '[data-related-resource="menu-items"]').forEach((link) => {
        link.href = workspaceHref('/panel/admin/menu-items?menu_id=' + encodeURIComponent(String(record.id)));
      });
      all<HTMLAnchorElement>(row, '[data-download-media]').forEach((link) => {
        link.href = '/web-api/v1/media/' + encodeURIComponent(String(record.id)) + '/download';
      });
      if (page.resource === 'media')
        all(row, '[data-command="editRecord"],[data-command="openEditCommitmentModal"]').forEach(
          (button) => button.remove(),
        );
      all<HTMLTableCellElement>(row, 'td').forEach((cell, i) => {
        const key = headers[i];
        if (key === '_actions' || cell.querySelector('[data-command]')) {
          const links = relatedAdminLinks(page.resource, record);
          if (links.length) {
            const navigation = document.createElement('span');
            navigation.className = 'portal-admin-related';
            for (const item of links) {
              const link = document.createElement('a');
              link.href = workspaceHref(item.href);
              link.textContent = item.label;
              navigation.append(link);
            }
            cell.append(navigation);
          }
          return;
        }
        if (key === '_inactive' || cell.querySelector('input[type=checkbox]')) {
          const input = one<HTMLInputElement>(cell, 'input');
          if (input) {
            input.checked = key === '_inactive' ? !record.is_active : Boolean(record.is_active);
            input.dataset.toggleActive = 'true';
          }
          return;
        }
        if (key === 'image') {
          if (page.resource === 'media' && record.mime && !String(record.mime).startsWith('image/'))
            return;
          setImage(
            one<HTMLImageElement>(cell, 'img'),
            record.image ?? record.cover ?? (page.resource === 'media' ? record : null),
          );
          return;
        }
        if (page.resource === 'media' && cell.textContent?.includes('دانلود')) {
          const link = document.createElement('a');
          link.href = '/web-api/v1/media/' + encodeURIComponent(String(record.id)) + '/download';
          link.textContent = 'دانلود فایل';
          cell.replaceChildren(link);
          return;
        }
        const data =
          key === 'title' &&
          ['slides', 'advertisements'].includes(page.resource) &&
          !String(record.title ?? '').trim()
            ? 'فقط تصویر (بدون نوشته)'
            : key === '_index'
              ? (currentPage - 1) * 100 + index + 1
              : key === '_form_title'
                ? (record.form as RecordData | undefined)?.title
                : key?.endsWith('_at')
                  ? date(record[key])
                  : key === 'locale'
                    ? ({ fa: 'فارسی', en: 'انگلیسی', ar: 'عربی' }[String(record.locale) as 'fa'] ??
                      record.locale)
                    : key === 'is_active'
                      ? record.is_active
                        ? 'فعال'
                        : 'غیرفعال'
                      : key === 'name' && page.resource === 'media'
                        ? record.original_name
                        : key === 'status'
                          ? ({
                              draft: 'پیش‌نویس',
                              published: 'منتشرشده',
                              archived: 'بایگانی',
                              pending: 'در انتظار',
                              review: 'در حال بررسی',
                              referred: 'ارجاع‌شده',
                              resolved: 'پاسخ داده‌شده',
                              closed: 'بسته‌شده',
                              rejected: 'ردشده',
                              accepted: 'پذیرفته‌شده',
                              approved: 'تأییدشده',
                              active: 'فعال',
                            }[String(record.status)] ?? record.status)
                          : key === 'is_current'
                            ? record.is_current
                              ? 'بله'
                              : 'خیر'
                            : record[key];
        cell.textContent =
          data === null || data === undefined || typeof data === 'object' ? '' : String(data);
      });
      if (readOnly) {
        all(
          row,
          '[data-command="editRecord"],[data-command="openEditCommitmentModal"],[data-command="deleteRecord"]',
        ).forEach((button) => button.remove());
        all<HTMLInputElement>(row, '[data-toggle-active]').forEach((input) => {
          input.disabled = true;
        });
      }
      if (ownOrganization) {
        all(row, '[data-command="deleteRecord"]').forEach((button) => button.remove());
        all<HTMLInputElement>(row, '[data-toggle-active]').forEach((input) => {
          input.disabled = true;
        });
      }
      tbody.append(row);
    });
    if (!records.length) tableMessage('موردی برای نمایش وجود ندارد.');
  }

  function tableMessage(message: string) {
    if (!tbody) return;
    const row = document.createElement('tr');
    const cell = document.createElement('td');
    cell.colSpan = Math.max(headers.length, 1);
    cell.textContent = message;
    row.append(cell);
    tbody.replaceChildren(row);
  }

  function pagination(meta?: { current_page: number; last_page: number }) {
    let navigation = one<HTMLElement>(root, '[data-admin-pagination]');
    if (!navigation) {
      navigation = document.createElement('nav');
      navigation.dataset.adminPagination = 'true';
      navigation.setAttribute('aria-label', 'صفحه‌های فهرست');
      tbody?.closest('table')?.parentElement?.after(navigation);
    }
    navigation.replaceChildren();
    if (!meta || meta.last_page <= 1) return;
    const status = document.createElement('span');
    status.setAttribute('aria-live', 'polite');
    status.textContent = ` صفحهٔ ${meta.current_page.toLocaleString('fa-IR')} از ${meta.last_page.toLocaleString('fa-IR')} `;
    for (const [label, target] of [
      ['صفحهٔ قبل', meta.current_page - 1],
      ['صفحهٔ بعد', meta.current_page + 1],
    ] as const) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'button-designv2';
      button.textContent = label;
      button.dataset.adminPage = String(target);
      button.disabled = target < 1 || target > meta.last_page;
      navigation.append(button);
      if (target < meta.current_page) navigation.append(status);
    }
  }

  async function load(query = currentQuery, selectedPage = currentPage) {
    if (preview || !page.resource) return;
    const version = ++loadVersion;
    const params = new URLSearchParams({ ...filters, ...page.defaults, per_page: '100' });
    params.set('page', String(selectedPage));
    if (page.resource === 'menu-items') {
      const menu = new URLSearchParams(window.location.search).get('menu_id');
      if (menu && /^\d+$/.test(menu)) params.set('menu_id', menu);
    }
    if (query) params.set('q', query);
    if (['settings', 'roles'].includes(page.resource)) params.delete('per_page');
    const result = await api<RecordData[] | RecordData>(endpoint + '?' + params, { signal });
    if (signal.aborted || version !== loadVersion) return;
    currentQuery = query;
    currentPage = result.meta?.current_page ?? selectedPage;
    records = resourceRows(page.resource, result.data);
    if (query && ['roles', 'settings'].includes(page.resource)) {
      const term = normalizeAdminSearch(query);
      records = records.filter((record) =>
        normalizeAdminSearch(
          [record.label, record.name, record.key].filter(Boolean).join(' '),
        ).includes(term),
      );
    }
    if (!records.length && currentPage > 1) return load(query, currentPage - 1);
    paint();
    if (listPages) {
      const plus = one<HTMLAnchorElement>(root, 'a#cbnOpenCreateMenuModalBtn');
      if (plus) {
        plus.href = adminEditorHref(page.path, undefined, listSearch());
        plus.dataset.navigate = plus.getAttribute('href')!;
      }
    }
    pagination(result.meta);
    root.dataset.ready = 'true';
  }

  async function relatedOptions() {
    if (preview) return;
    const node = modal();
    if (!node) return;
    const mapping: Record<string, string> = {
      organization_id: 'organizations',
      county_id: 'counties',
      menu_id: 'menus',
      parent_id: page.resource,
      category_id: 'categories',
      role_id: 'roles',
      assigned_to: 'users',
    };
    const requests = new Map<string, Promise<RecordData[]>>();
    await Promise.all(
      all<HTMLSelectElement>(node, 'select[data-field]').map(async (select) => {
        const key = select.dataset.field ?? '';
        const lookup = select.dataset.lookup ?? mapping[key];
        const resource = lookup ? lookupResource(lookup) : '';
        if (!resource) return;
        try {
          const lookupFilters: Record<string, string> = {};
          const family =
            page.resource === 'categories'
              ? page.defaults.type
              : page.resource === 'members'
                ? 'member'
                : page.resource === 'services'
                  ? 'service'
                  : page.resource === 'forms'
                    ? 'form'
                    : page.resource === 'galleries'
                      ? 'gallery'
                      : page.resource === 'slides' || page.resource === 'advertisements'
                        ? 'slide'
                        : page.defaults.type === 'news' || page.resource === 'news'
                          ? 'news'
                          : ['contents', 'calls', 'publications'].includes(page.resource)
                            ? 'document'
                            : '';
          if (resource === 'media' && select.hasAttribute('data-image-value'))
            lookupFilters.kind = 'image';
          if (resource === 'categories' && family) lookupFilters.type = family;
          if (resource === 'menu-items' && filters.menu_id) lookupFilters.menu_id = filters.menu_id;
          const requestKey = resource + '?' + new URLSearchParams(lookupFilters);
          if (!requests.has(requestKey))
            requests.set(requestKey, lookupRecords(resource, signal, lookupFilters));
          const records = await requests.get(requestKey)!;
          if (signal.aborted) return;
          const retained =
            select.hasAttribute('data-image-value') && select.value
              ? (select.selectedOptions[0]?.cloneNode(true) as HTMLOptionElement)
              : null;
          const current = select.value;
          setOptions(
            select,
            records,
            resource === 'media'
              ? 'original_name'
              : resource === 'roles'
                ? 'label'
                : records[0]?.name !== undefined
                  ? 'name'
                  : 'title',
          );
          if (select.hasAttribute('data-image-value')) {
            for (const record of records) {
              const option = Array.from(select.options).find(
                (option) => option.value === String(record.id),
              );
              if (option && record.url) option.dataset.imageUrl = String(record.url);
            }
            if (
              retained &&
              !Array.from(select.options).some((option) => option.value === retained.value)
            )
              select.add(retained);
            if (current) select.value = current;
          }
        } catch {
          if (signal.aborted) return;
          if (select.multiple) setOptions(select, []);
          else if (select.hasAttribute('data-image-value')) {
            if (!select.value)
              select.replaceChildren(
                new Option('کتابخانه دریافت نشد؛ می‌توانید همین‌جا عکس بارگذاری کنید', ''),
              );
          } else select.replaceChildren(new Option('دسترسی یا داده‌ای موجود نیست', ''));
        }
      }),
    );
  }

  async function save() {
    writeable();
    const node = modal();
    if (!node || busy || editorSaved) return;
    if (page.editor && !editorLoaded)
      throw new Error('ابتدا دریافت اطلاعات این مورد را کامل کنید.');
    busy = true;
    try {
      imageFields ??= mountImageFields(root, signal, preview);
      await imageFields.ready();
      imageFields?.validate();
      validateRichText(node);
      validateFormBuilder(node);
      const form = one<HTMLFormElement>(node, 'form');
      const fileInput = one<HTMLInputElement>(
        node,
        'input[type=file][data-media-field]:not([data-image-upload]), input[type=file]:not([data-cms-file]):not([data-image-upload])',
      );
      const file = fileInput?.files?.[0];
      const choice = fileInput?.dataset.mediaField
        ? one<HTMLSelectElement>(node, 'select[data-field="' + fileInput.dataset.mediaField + '"]')
        : null;
      const required = choice?.required ?? false;
      if (file && choice) choice.required = false;
      const valid = !form || form.reportValidity();
      if (choice) choice.required = required;
      if (!valid) return;
      if (page.editor) editorState(true, 'در حال ذخیره…');
      let data: RecordData = {
        ...filters,
        ...page.defaults,
        ...collectFields(node),
        ...resourceScope(page.resource),
      };
      if (root.dataset.workspaceGlobal === 'false' && root.dataset.organizationId) {
        if (page.resource === 'organizations') {
          for (const key of ['parent_id', 'county_id', 'slug', 'type', 'is_active'])
            delete data[key];
        } else data.organization_id = Number(root.dataset.organizationId);
      }
      for (const key of ['type', 'kind']) if (page.defaults[key]) data[key] = page.defaults[key];
      delete data.person_type;
      if (page.resource === 'users') {
        data.person_type = page.defaults.person_type ?? 'staff';
        if (!editing) {
          if (!data.role_id) {
            const result = await api<unknown>('admin/roles');
            data.role_id = resourceRows('roles', result.data).find(
              (role) => role.name === (data.person_type === 'citizen' ? 'citizen' : 'editor'),
            )?.id;
          }
        }
      }
      if (page.resource === 'menus' && !editing && !data.key)
        data.key = 'menu-' + crypto.randomUUID().slice(0, 8);
      if (page.resource === 'categories' && !data.type) data.type = 'news';
      if (page.resource === 'organizations' && !data.type) data.type = 'department';
      if (page.resource === 'members' && !data.kind) data.kind = 'manager';
      if (['calls', 'publications'].includes(page.resource) && !data.body)
        data.body = String(data.description ?? '');
      if (file) {
        if (uploaded?.file !== file)
          uploaded = { file, id: await upload(await prepareUpload(file), 'public', signal) };
        data[fileInput?.dataset.mediaField ?? 'media_id'] = uploaded.id;
      }
      if (page.resource === 'media') {
        if (!file) throw new Error('فایل را انتخاب کنید.');
        if (page.editor) {
          finishEditor();
          return;
        }
        closeOverlays(root);
        await load();
        return;
      }
      if (editing) delete data.password_confirmation;
      if (editing && page.resource === 'roles' && editing.is_system)
        throw new Error('نقش‌های سیستمی قابل تغییر نیستند. یک نقش جدید بسازید.');
      if (
        ['tickets', 'submissions', 'subscribers'].includes(page.resource) ||
        page.resource.startsWith('moderation/')
      ) {
        if (!editing) throw new Error('ابتدا یک مورد را از فهرست برای بررسی انتخاب کنید.');
      }
      const key = editing ? String(editing.reference ?? editing.id) : '';
      if (page.resource === 'tickets' && !String(data.reply ?? '').trim()) delete data.reply;
      await api(endpoint + (page.resource === 'settings' ? '' : editing ? '/' + key : ''), {
        method: page.resource === 'settings' ? 'PUT' : editing ? 'PATCH' : 'POST',
        body: jsonBody(data),
        signal,
      });
      if (page.editor) {
        finishEditor();
        return;
      }
      closeOverlays(root);
      editing = null;
      feedback(root, 'اطلاعات با موفقیت ذخیره شد.');
      try {
        await load();
      } catch {
        feedback(
          root,
          'اطلاعات ذخیره شد؛ دریافت دوبارهٔ فهرست انجام نشد. فهرست را تازه‌سازی کنید.',
        );
      }
    } finally {
      busy = false;
      if (page.editor && !editorSaved)
        editorState(editorLoaded, 'اطلاعات فرم حفظ شده است. نتیجهٔ عملیات را بررسی کنید.');
    }
  }

  function finishEditor() {
    if (!page.editor) return;
    editorSaved = true;
    editorState(false, 'اطلاعات ذخیره شد. در حال بازگشت به فهرست…');
    const target = new URL(page.editor.returnTo, window.location.origin);
    target.searchParams.set('saved', page.editor.mode);
    navigate(target.pathname + target.search);
  }

  function showEditing() {
    if (!modal()) return;
    fillFields(
      modal()!,
      page.resource === 'settings'
        ? {
            settings: records.map((record) => ({
              key: record.key,
              value: record.value,
              is_public: record.is_public,
            })),
          }
        : (editing ?? {}),
    );
    if (editing && page.resource === 'tickets') {
      const status = one<HTMLSelectElement>(modal()!, 'select[data-field="status"]');
      const current = String(editing.status) as keyof typeof ticketTransitions;
      for (const option of Array.from(status?.options ?? []))
        option.disabled =
          option.value !== current &&
          !(ticketTransitions[current] as readonly string[] | undefined)?.includes(option.value);
    }
    if (
      editing &&
      (['tickets', 'submissions'].includes(page.resource) ||
        page.resource.startsWith('moderation/'))
    ) {
      let details = one<HTMLElement>(modal()!, '[data-record-detail]');
      if (!details) {
        details = document.createElement('div');
        details.dataset.recordDetail = 'true';
        details.className = 'portal-record-detail';
        modal()!.querySelector('.cbn-modal-body')?.prepend(details);
      }
      details.textContent =
        String(editing.subject ?? editing.reference ?? '') +
        '\n' +
        String(editing.body ?? '') +
        (page.resource === 'submissions' ? '\n' + JSON.stringify(editing.data ?? {}, null, 2) : '');
      const attachments = (parent: HTMLElement, media: unknown) => {
        if (!Array.isArray(media)) return;
        for (const file of media as RecordData[]) {
          if (!file.id) continue;
          const link = document.createElement('a');
          link.href = '/web-api/v1/media/' + encodeURIComponent(String(file.id)) + '/download';
          link.textContent = 'دریافت پیوست: ' + String(file.original_name ?? file.id);
          const line = document.createElement('p');
          line.append(link);
          parent.append(line);
        }
      };
      attachments(details, editing.media);
      if (Array.isArray(editing.messages))
        for (const message of editing.messages as RecordData[]) {
          const paragraph = document.createElement('p');
          const author = message.author as RecordData | undefined;
          paragraph.textContent =
            (message.is_internal ? 'یادداشت داخلی — ' : '') +
            String(author?.name ?? '') +
            ': ' +
            String(message.body ?? '');
          attachments(paragraph, message.media);
          details.append(paragraph);
        }
    }
    if (!page.editor) toggleModal(root, true);
  }

  async function dispatch(command: string, element: HTMLElement) {
    try {
      if (command === 'reloadEditor') {
        optionsReady = relatedOptions();
        await loadEditor();
        return;
      }
      if (command === 'printSection') {
        window.print();
        return;
      }
      const key = element.closest<HTMLElement>('tr[data-record]')?.dataset.record;
      const record = records.find((item) => String(item.reference ?? item.id) === key);
      if (command === 'cbnToggleActionsMenu') {
        rowActions(root, element);
        return;
      }
      if (['closeModal', 'closeEditCommitmentModal'].includes(command)) {
        editVersion++;
        closeOverlays(root);
        return;
      }
      if (['openEditCommitmentModal', 'editRecord'].includes(command)) {
        if (busy) return;
        if (!record) throw new Error('این مورد دیگر در فهرست نیست. فهرست را تازه‌سازی کنید.');
        if (listPages) {
          navigate(adminEditorHref(page.path, String(record.reference ?? record.id), listSearch()));
          return;
        }
        const version = ++editVersion;
        uploaded = null;
        let next = record;
        await optionsReady;
        if (
          !preview &&
          !['roles', 'settings', 'subscribers'].includes(page.resource) &&
          !page.resource.startsWith('moderation/')
        )
          next = (
            await api<RecordData>(endpoint + '/' + String(next.reference ?? next.id), { signal })
          ).data;
        if (signal.aborted || version !== editVersion) return;
        editing = next;
        showEditing();
        return;
      }
      if (command === 'saveRecord') {
        await save();
        return;
      }
      if (command === 'deleteRecord' && record) {
        if (busy) return;
        if (
          ['tickets', 'submissions', 'settings'].includes(page.resource) ||
          page.resource.startsWith('moderation/')
        )
          throw new Error('این بخش از طریق بررسی و تغییر وضعیت مدیریت می‌شود.');
        writeable();
        if (!window.confirm('این مورد حذف شود؟')) return;
        busy = true;
        try {
          await api(endpoint + '/' + String(record.reference ?? record.id), {
            method: 'DELETE',
            signal,
          });
          await load();
        } finally {
          busy = false;
        }
      }
    } catch (error) {
      const editor = modal();
      if (page.editor || editor?.classList.contains('active')) fieldError(editor!, error);
      else feedback(root, error instanceof Error ? error.message : 'عملیات ناموفق بود.');
    }
  }

  function init() {
    const homePath = root.dataset.workspaceHome ?? '/panel/admin';
    const loginPath = root.dataset.workspaceLogin ?? '/panel/admin/login';
    if (!root.querySelector('[data-custom-page-builder]') && root.dataset.workspaceName && root.dataset.organizationType !== 'county') {
      const main = one<HTMLElement>(root, '.main-content');
      if (main) {
        const notice = document.createElement('nav');
        notice.className = 'portal-workspace-bar';
        notice.setAttribute('aria-label', 'فضای کاری فعلی');
        const home = document.createElement('a');
        home.href = homePath;
        home.textContent = root.dataset.workspaceName;
        const scope = document.createElement('span');
        scope.textContent = readOnly
          ? 'مشاهدهٔ اطلاعات استان'
          : root.dataset.workspaceGlobal === 'false'
            ? 'مدیریت محتوای استان'
            : 'مدیریت مرکزی سامانه';
        notice.append(home, scope);
        main.prepend(notice);
        signal.addEventListener('abort', () => notice.remove(), { once: true });
      }
    }
    const countyPortal=one(root,'[data-county-portal-form]');
    if(countyPortal)mountCountyPortal(root,signal);
    if (!listPages && !countyPortal) {
      imageFields ??= mountImageFields(root, signal, preview);
      mountRichText(root, signal, preview);
      mountFormBuilder(root, signal);
      mountPermissionFields(root, signal);
    }
    if (page.resource && tbody && modal()) {
      if (reviewOnly)
        one<HTMLElement>(root, '#cbnOpenCreateMenuModalBtn')?.setAttribute('hidden', '');
      else if (!one(root, '#cbnOpenCreateMenuModalBtn')) {
        const button = document.createElement('button');
        button.type = 'button';
        button.id = 'cbnOpenCreateMenuModalBtn';
        button.className = 'button-designv1';
        button.textContent = 'افزودن ' + itemName;
        one(root, '.main-content section header,.main-content-header')?.append(button);
      }
      if (
        !['settings', 'roles', 'media'].includes(page.resource) &&
        !one(root, '.main-content form.search-box-wrapper')
      ) {
        const form = document.createElement('form');
        form.className = 'search-box-wrapper';
        const input = document.createElement('input');
        input.id = 'searchInput';
        input.type = 'search';
        input.placeholder = 'جستجو…';
        input.setAttribute('aria-label', 'جستجو در فهرست');
        const submit = document.createElement('button');
        submit.type = 'submit';
        submit.className = 'button-designv1';
        submit.textContent = 'جستجو';
        form.append(input, submit);
        tbody.closest('table')?.parentElement?.before(form);
      }
      if (
        !reviewOnly &&
        page.resource !== 'settings' &&
        prototype &&
        !prototype.querySelector('[data-command="deleteRecord"]')
      ) {
        const cell = prototype.children[headers.indexOf('_actions')];
        if (cell) {
          const remove = document.createElement('button');
          remove.type = 'button';
          remove.className = 'button-designv2';
          remove.dataset.command = 'deleteRecord';
          remove.textContent = 'حذف';
          cell.append(remove);
        }
      }
    }
    if (listPages) {
      const plus = one<HTMLElement>(root, '#cbnOpenCreateMenuModalBtn');
      if (plus && !reviewOnly) {
        const link = pageLink(plus);
        link.setAttribute(
          'aria-label',
          (page.resource === 'settings' ? 'ویرایش ' : 'افزودن ') + itemName,
        );
        if (page.resource === 'settings') link.textContent = 'ویرایش تنظیمات';
      }
      if (currentQuery) {
        const input = one<HTMLInputElement>(root, '#searchInput');
        if (input) input.value = currentQuery;
      }
      if (new URLSearchParams(window.location.search).has('saved'))
        feedback(root, 'اطلاعات با موفقیت ذخیره شد.');
      all<HTMLElement>(root, 'tbody tr[data-record]').forEach((row) => {
        all<HTMLElement>(
          row,
          '[data-command="editRecord"],[data-command="openEditCommitmentModal"]',
        ).forEach((button) => pageLink(button, row.dataset.record));
      });
    }
    if (readOnly) {
      one(root, '#cbnOpenCreateMenuModalBtn')?.remove();
      all<HTMLElement>(root, '.sadyar-header-left-righ-createbtn').forEach((button) => {
        button.hidden = true;
      });
    }
    if (ownOrganization) one(root, '#cbnOpenCreateMenuModalBtn')?.remove();
    if (root.dataset.workspaceGlobal === 'false' && page.resource === 'organizations') {
      for (const key of ['parent_id', 'county_id', 'slug', 'type', 'is_active']) {
        const field = one<HTMLInputElement>(root, '[data-field="' + key + '"]');
        if (field) {
          field.disabled = true;
          field.closest<HTMLElement>('.portal-resource-field')?.setAttribute('hidden', '');
        }
      }
    }
    const setProfile = (name: string, mobile = '') => {
      all<HTMLElement>(
        root,
        '#clickdiv1 .header-bigitem-text,.sidebar-profile-top-left-item:first-child,.bigitem-menu1 .bigitem-menu-top p',
      ).forEach((element) => {
        element.textContent = name;
      });
      all(root, '.sidebar-profile-top-left-item-number span').forEach((element) => {
        element.textContent = mobile;
      });
    };
    if (preview) setProfile('مدیر محلی');
    else if (!root.dataset.organizationId)
      void api<{ name: string; mobile?: string }>('auth/me', { signal })
        .then((result) => {
          setProfile(result.data.name, result.data.mobile);
          root.dispatchEvent(
            new window.CustomEvent('portal:admin-user', { detail: result.data, bubbles: true }),
          );
        })
        .catch(() => {});

    all<HTMLElement>(root, '.bigitem-menu-bottom-item').forEach((item) => {
      const label = item.textContent?.trim();
      if (label === 'خروج') item.setAttribute('data-admin-logout', 'true');
      else if (label === 'تنظیمات حساب') item.setAttribute('data-navigate', workspaceHref('/panel/admin/account'));
      else if (label === 'وظایف') item.setAttribute('data-navigate', '/duties');
      if (label === 'خروج' || label === 'تنظیمات حساب' || label === 'وظایف') {
        item.setAttribute('role', 'button');
        item.tabIndex = 0;
      }
    });
    root.addEventListener(
      'click',
      (event) => {
        const target = event.target as HTMLElement;
        const paging = target.closest<HTMLElement>('[data-admin-page]');
        if (paging)
          void load(currentQuery, Number(paging.dataset.adminPage)).catch((error) =>
            feedback(root, error.message),
          );
        if (target.closest('[data-admin-logout],a[href="'+workspaceHref('/panel/admin/logout')+'"]')) {
          event.preventDefault();
          void import('@/components/admin/account')
            .then((module) => module.logoutAdmin(preview,loginPath))
            .catch((error) => feedback(root, error.message));
        }
      },
      { signal },
    );
    if (preview) {
      all(root, '.header-bigitem-text,.sidebar-profile-top-left-item').forEach((item) => {
        if (/کامیار|حق شناس|093607/.test(item.textContent ?? '')) item.textContent = 'مدیر محلی';
      });
      const area = one(root, '.main-content');
      if (area) {
        const note = document.createElement('p');
        note.className = 'portal-preview-note';
        note.textContent =
          'نمایش محلی طرح‌ها؛ برای ثبت و ویرایش اطلاعات پایگاه داده، بک‌اند را اجرا کنید و TEMPLATE_PREVIEW=false قرار دهید.';
        area.prepend(note);
      }
    }
    try {
      if (localStorage.getItem('portal-admin-theme') === 'dark') root.classList.add('portal-dark');
    } catch {}

    if (!listPages)
      one(root, '#cbnOpenCreateMenuModalBtn')?.addEventListener(
        'click',
        () => {
          editVersion++;
          editing = null;
          uploaded = null;
          const node = modal();
          if (node)
            fillFields(
              node,
              page.resource === 'settings'
                ? {
                    settings: records.map((record) => ({
                      key: record.key,
                      value: record.value,
                      is_public: record.is_public,
                    })),
                  }
                : {
                    locale: 'fa',
                    priority: 0,
                    is_active: true,
                    ...Object.fromEntries(
                      Object.entries(filters).map(([key, value]) => [key, Number(value)]),
                    ),
                    ...resourceScope(page.resource),
                    ...page.defaults,
                  },
            );
        },
        { signal },
      );
    root.addEventListener(
      'submit',
      (event) => {
        event.preventDefault();
        const form = event.target as HTMLFormElement;
        if (form.closest('[data-admin-record-editor],#cbnCreateMenuModalOverlay'))
          void save().catch((error) => fieldError(form, error));
        else if (form.classList.contains('search-box-wrapper'))
          void load(one<HTMLInputElement>(root, '#searchInput')?.value ?? '', 1).catch((error) =>
            feedback(root, String(error)),
          );
      },
      { signal },
    );
    one(root, '#resetSearchBtn')?.addEventListener(
      'click',
      (event) => {
        event.preventDefault();
        const input = one<HTMLInputElement>(root, '#searchInput');
        if (input) input.value = '';
        void load('', 1).catch((error) => feedback(root, error.message));
      },
      { signal },
    );
    root.addEventListener(
      'change',
      (event) => {
        const input = event.target as HTMLInputElement;
        if (!input.dataset.toggleActive) return;
        const record = records.find(
          (item) => String(item.id) === input.closest<HTMLElement>('tr')?.dataset.record,
        );
        if (!record) return;
        const previous =
          headers[Array.from(input.closest('tr')!.children).indexOf(input.closest('td')!)] ===
          '_inactive'
            ? !record.is_active
            : Boolean(record.is_active);
        input.disabled = true;
        try {
          writeable();
          void api(endpoint + '/' + record.id, {
            method: 'PATCH',
            body: jsonBody({ is_active: !record.is_active }),
          })
            .then(() => load())
            .catch((error) => {
              input.checked = previous;
              feedback(root, error.message);
            })
            .finally(() => {
              input.disabled = false;
            });
        } catch (error) {
          input.checked = previous;
          input.disabled = false;
          feedback(root, String(error));
        }
      },
      { signal },
    );
    if (page.editor) {
      optionsReady = relatedOptions();
      void loadEditor();
    } else if (!preview && page.resource) {
      tableMessage('در حال دریافت اطلاعات…');
      optionsReady = listPages ? Promise.resolve() : relatedOptions();
      void Promise.all([load(), optionsReady]).catch((error) => {
        tableMessage('دریافت اطلاعات انجام نشد. دوباره تلاش کنید.');
        root.dataset.ready = 'true';
        if (error instanceof ApiError && error.status === 401)
          window.location.assign(loginPath + '?return=' + encodeURIComponent(context.path));
        else feedback(root, error.message);
      });
    }
  }
  return { init, dispatch };
}
