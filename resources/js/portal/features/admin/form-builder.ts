import { all } from '@/lib/dom';

type FormField = {
  key: string;
  label: string;
  type: string;
  required?: boolean;
  options?: string[];
  min?: number;
  max?: number;
};
const kinds: Record<string, string> = {
  text: 'متن کوتاه',
  textarea: 'متن بلند',
  email: 'ایمیل',
  number: 'عدد',
  date: 'تاریخ',
  select: 'انتخاب یک گزینه',
  multi_select: 'انتخاب چند گزینه',
  boolean: 'بله یا خیر',
  file: 'پیوست',
};

function action(label: string, run: () => void): HTMLButtonElement {
  const element = document.createElement('button');
  element.type = 'button';
  element.textContent = label;
  element.addEventListener('click', run);
  return element;
}

export function mountFormBuilder(root: HTMLElement, signal: AbortSignal): void {
  for (const source of all<HTMLTextAreaElement>(root, 'textarea[data-form-builder]')) {
    const initialRequired = source.required;
    const wrapper = document.createElement('div');
    wrapper.className = 'portal-form-builder';
    const list = document.createElement('div');
    const message = document.createElement('p');
    message.setAttribute('role', 'status');
    const advanced = document.createElement('details');
    const caption = document.createElement('summary');
    caption.textContent = 'ویرایش پیشرفتهٔ ساختار فرم';
    advanced.append(caption);
    source.before(wrapper);
    wrapper.append(list, message, advanced);
    advanced.append(source);
    source.required = false;
    signal.addEventListener(
      'abort',
      () => {
        if (wrapper.contains(source)) wrapper.replaceWith(source);
        source.required = initialRequired;
      },
      { once: true },
    );
    let fields: FormField[] = [];
    const sync = () => {
      source.value = JSON.stringify(fields);
      source.dispatchEvent(new window.Event('input', { bubbles: true }));
    };
    const render = () => {
      list.replaceChildren();
      fields.forEach((field, index) => {
        const card = document.createElement('fieldset');
        const legend = document.createElement('legend');
        legend.textContent = 'فیلد ' + (index + 1).toLocaleString('fa-IR');
        card.append(legend);
        const control = (
          name: string,
          label: string,
          type: string,
          value: string,
          update: (input: HTMLInputElement) => void,
        ) => {
          const tag = document.createElement('label');
          tag.textContent = label;
          const input = document.createElement('input');
          input.type = type;
          input.value = value;
          input.setAttribute('aria-label', label + ' ' + (index + 1));
          input.dataset.builderControl = name;
          input.addEventListener('input', () => {
            update(input);
            sync();
          });
          tag.append(input);
          card.append(tag);
          return input;
        };
        control('label', 'عنوان فیلد', 'text', field.label, (input) => {
          field.label = input.value;
        }).maxLength = 150;
        const key = control('key', 'شناسهٔ فیلد', 'text', field.key, (input) => {
          field.key = input.value.trim();
        });
        key.dir = 'ltr';
        key.pattern = '[a-z][a-z0-9_]{0,49}';
        key.maxLength = 50;
        const label = document.createElement('label');
        label.textContent = 'نوع فیلد';
        const select = document.createElement('select');
        select.dataset.builderControl = 'type';
        select.setAttribute('aria-label', 'نوع فیلد ' + (index + 1));
        select.append(
          ...Object.entries(kinds).map(
            ([value, text]) => new Option(text, value, false, value === field.type),
          ),
        );
        select.addEventListener('change', () => {
          field.type = select.value;
          if (['select', 'multi_select'].includes(field.type)) field.options ??= ['گزینهٔ اول'];
          else delete field.options;
          sync();
          render();
        });
        label.append(select);
        card.append(label);
        const required = control('required', 'پاسخ الزامی است', 'checkbox', '', (input) => {
          field.required = input.checked;
        });
        required.checked = field.required ?? false;
        if (['select', 'multi_select'].includes(field.type)) {
          const label = document.createElement('label');
          label.textContent = 'گزینه‌ها، هر مورد در یک خط';
          const options = document.createElement('textarea');
          options.value = field.options?.join('\n') ?? '';
          options.dataset.builderControl = 'options';
          options.rows = 3;
          options.setAttribute('aria-label', 'گزینه‌های فیلد ' + (index + 1));
          options.addEventListener('input', () => {
            field.options = options.value
              .split('\n')
              .map((item) => item.trim())
              .filter(Boolean);
            sync();
          });
          label.append(options);
          card.append(label);
        }
        if (['text', 'textarea', 'email', 'number'].includes(field.type))
          for (const [name, title] of [
            ['min', 'حداقل'],
            ['max', 'حداکثر'],
          ] as const) {
            if (name === 'min' && field.type !== 'number') continue;
            const input = control(
              name,
              title,
              'number',
              field[name] === undefined ? '' : String(field[name]),
              (input) => {
                if (input.value === '') delete field[name];
                else field[name] = Number(input.value);
              },
            );
            input.min = name === 'max' ? '1' : '0';
            input.max = '1000000';
          }
        const move = (offset: number) => {
          const next = index + offset;
          if (next < 0 || next >= fields.length) return;
          [fields[index], fields[next]] = [fields[next], fields[index]];
          sync();
          render();
        };
        const up = action('بالاتر', () => move(-1));
        up.disabled = index === 0;
        const down = action('پایین‌تر', () => move(1));
        down.disabled = index === fields.length - 1;
        card.append(
          up,
          down,
          action('حذف فیلد', () => {
            fields.splice(index, 1);
            sync();
            render();
          }),
        );
        list.append(card);
      });
    };
    wrapper.append(
      action('افزودن فیلد', () => {
        if (fields.length >= 40) {
          message.textContent = 'حداکثر ۴۰ فیلد می‌توانید تعریف کنید.';
          return;
        }
        let number = fields.length + 1;
        while (fields.some((field) => field.key === 'field_' + number)) number++;
        fields.push({ key: 'field_' + number, label: 'عنوان فیلد', type: 'text', required: false });
        sync();
        render();
        list.lastElementChild?.querySelector<HTMLInputElement>('input')?.focus();
      }),
    );
    const reload = () => {
      try {
        const data: unknown = JSON.parse(source.value || '[]');
        if (!Array.isArray(data)) throw new Error();
        fields = data;
        message.textContent = '';
        render();
      } catch {
        message.textContent = 'ساختار فرم معتبر نیست؛ مقدار پیشرفته را اصلاح کنید.';
      }
    };
    source.addEventListener('change', reload, { signal });
    root.addEventListener('portal:fields-filled', reload, { signal });
    reload();
  }
}

export function validateFormBuilder(root: HTMLElement): void {
  for (const source of all<HTMLTextAreaElement>(root, '[data-form-builder]')) {
    const fields: FormField[] = JSON.parse(source.value || '[]');
    if (!Array.isArray(fields) || !fields.length || fields.length > 40)
      throw new Error('فرم باید بین ۱ تا ۴۰ فیلد داشته باشد.');
    const keys = new Set<string>();
    for (const field of fields) {
      if (
        !field.label?.trim() ||
        !/^[a-z][a-z0-9_]{0,49}$/.test(field.key) ||
        keys.has(field.key) ||
        !kinds[field.type]
      )
        throw new Error('عنوان، نوع و شناسهٔ یکتای هر فیلد را بررسی کنید.');
      if (
        ['select', 'multi_select'].includes(field.type) &&
        (!field.options?.length || new Set(field.options).size !== field.options.length)
      )
        throw new Error('گزینه‌های فیلد انتخابی باید تکمیل شده و تکراری نباشند.');
      keys.add(field.key);
    }
  }
}
