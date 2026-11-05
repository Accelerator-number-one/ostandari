import { all, digits } from '@/lib/dom';
import type { RecordData } from '@/types/portal';

const numbers = new Set([
  'priority',
  'parent_id',
  'category_id',
  'organization_id',
  'county_id',
  'menu_id',
  'media_id',
  'cover_media_id',
  'role_id',
]);

export function collectFields(root: ParentNode): RecordData {
  const record: RecordData = {};
  for (const input of all<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>(
    root,
    '[data-field]',
  )) {
    const key = input.dataset.field;
    if (
      !key ||
      (input instanceof HTMLInputElement &&
        ['file', 'radio'].includes(input.type) &&
        !input.checked)
    )
      continue;
    if (input instanceof HTMLInputElement && input.type === 'file') continue;
    if (input instanceof HTMLSelectElement && input.multiple) {
      record[key] = Array.from(input.selectedOptions)
        .filter((option) => !option.disabled && option.value.trim() !== '')
        .map((option) => Number(option.value))
        .filter(Number.isFinite);
      continue;
    }
    if (input instanceof HTMLInputElement && input.type === 'checkbox') {
      record[key] = input.checked;
      continue;
    }
    const raw =
      'value' in input
        ? key === 'password'
          ? input.value
          : input.value.trim()
        : (input as HTMLElement).innerHTML;
    if (raw === '' && ['password', 'password_confirmation'].includes(key)) continue;
    if (input.dataset.lines) {
      record[key] = raw
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean);
    } else if (input.dataset.json) {
      if (!raw) {
        record[key] = [];
        continue;
      }
      try {
        record[key] = JSON.parse(raw);
      } catch {
        throw new Error('مقدار ' + key + ' باید JSON معتبر باشد.');
      }
    } else
      record[key] =
        numbers.has(key) || key.endsWith('_id') ? (raw === '' ? null : Number(digits(raw))) : raw;
  }
  return record;
}

export function fillFields(root: ParentNode, record: RecordData): void {
  for (const file of all<HTMLInputElement>(root, 'input[type="file"]')) file.value = '';
  for (const input of all<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>(
    root,
    '[data-field]',
  )) {
    const value = record[input.dataset.field ?? ''];
    if (input instanceof HTMLInputElement && input.type === 'file') {
      input.value = '';
      continue;
    }
    if (input instanceof HTMLInputElement && input.type === 'checkbox')
      input.checked = value === undefined ? input.defaultChecked : Boolean(value);
    else if ('value' in input) {
      if (input instanceof HTMLSelectElement && input.multiple) {
        const values = Array.isArray(value)
          ? value
          : input.dataset.field === 'tag_ids' && Array.isArray(record.tags)
            ? (record.tags as RecordData[]).map((tag) => tag.id)
            : input.dataset.field === 'media_ids' && Array.isArray(record.media)
              ? (record.media as RecordData[])
                  .filter((file) => (file.pivot as RecordData | undefined)?.purpose !== 'inline')
                  .map((file) => file.id)
              : [];
        if (input.dataset.lookup)
          for (const value of values)
            if (!Array.from(input.options).some((option) => option.value === String(value)))
              input.add(new Option('گزینهٔ فعلی — ' + value, String(value)));
        for (const option of Array.from(input.options))
          option.selected = values.map(String).includes(option.value);
      } else if (input.dataset.lines) input.value = Array.isArray(value) ? value.join('\n') : '';
      else if (input.dataset.json)
        input.value = value === undefined || value === null ? '' : JSON.stringify(value, null, 2);
      else if (input instanceof HTMLInputElement && input.type === 'datetime-local' && value) {
        const date = new Date(String(value));
        date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
        input.value = Number.isNaN(date.valueOf()) ? '' : date.toISOString().slice(0, 16);
      } else if (input instanceof HTMLSelectElement && value === undefined)
        input.value =
          Array.from(input.options).find((option) => option.defaultSelected)?.value ??
          input.options[0]?.value ??
          '';
      else {
        if (
          input instanceof HTMLSelectElement &&
          input.dataset.lookup &&
          value !== null &&
          value !== undefined &&
          value !== '' &&
          !Array.from(input.options).some((option) => option.value === String(value))
        )
          input.add(new Option('گزینهٔ فعلی — ' + value, String(value)));
        input.value = value === null || value === undefined ? '' : String(value);
      }
    }
  }
  root.dispatchEvent(
    new window.CustomEvent('portal:fields-filled', { bubbles: true, detail: record }),
  );
}

export function fieldForColumn(label: string, resource = ''): string {
  if (label.includes('مسئول فعلی')) return 'is_current';
  if (resource === 'roles' && label.includes('نام نقش')) return 'label';
  if (resource === 'roles' && label.includes('نوع نقش')) return 'name';
  if (resource === 'polls' && /عنوان|نام/.test(label)) return 'question';
  if (resource === 'quotes' && label.includes('گوینده')) return 'author';
  if (resource === 'quotes' && label.includes('متن')) return 'text';
  if (resource === 'settings' && label.includes('عنوان')) return 'key';
  if (resource === 'menus' && label.includes('گروه')) return 'name';
  if (resource === 'submissions' && label.includes('نام فرم')) return '_form_title';
  const mapping: [string[], string][] = [
    [['ردیف'], '_index'],
    [['عملیات'], '_actions'],
    [['غیرفعال'], '_inactive'],
    [['تاریخ شروع'], 'starts_at'],
    [['تاریخ پایان'], 'ends_at'],
    [['کد پیگیری'], 'reference'],
    [['کد ملی'], 'national_code'],
    [['کد پرسنلی'], 'personnel_code'],
    [['نمایش', 'فعال'], 'is_active'],
    [['شناسه', 'کد'], 'id'],
    [['زبان'], 'locale'],
    [['اولویت', 'ترتیب'], 'priority'],
    [['پدر'], 'parent_id'],
    [['عنوان اصلی'], 'title'],
    [['عنوان فرعی'], 'subtitle'],
    [['سوال', 'سؤال', 'پرسش'], 'question'],
    [['جواب', 'پاسخ'], 'answer'],
    [['نام کاربری'], 'username'],
    [['همراه', 'موبایل'], 'mobile'],
    [['ایمیل'], 'email'],
    [['فکس'], 'fax'],
    [['تلفن'], 'phone'],
    [['آدرس لینک', 'آدرس تبلیغ', 'آدرس اینترنتی'], 'url'],
    [['آدرس'], 'address'],
    [['تاریخ'], 'created_at'],
    [['تصویر', 'عکس'], 'image'],
    [['سمت'], 'position'],
    [['نام خانوادگی'], 'name'],
    [['موضوع'], 'subject'],
    [['توصیف', 'توضیح', 'خلاصه'], 'description'],
    [['نوع کاربری'], 'person_type'],
    [['وضعیت'], 'status'],
    [['نام'], 'name'],
    [['عنوان'], 'title'],
    [['لینک'], 'url'],
    [['متن'], 'body'],
  ];
  return mapping.find(([words]) => words.some((word) => label.includes(word)))?.[1] ?? '';
}
