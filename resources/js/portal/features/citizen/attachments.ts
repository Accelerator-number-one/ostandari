import { feedback, one } from '@/lib/dom';

const allowedTypes = new Set(['image/jpeg', 'image/png', 'image/webp']);
const maximumSize = 20 * 1024 * 1024;

export function bindAttachments(root: HTMLElement, signal: AbortSignal) {
  const box = one<HTMLElement>(root, '.upload-box');
  const label = box?.querySelector('b');
  const originalLabel = label?.textContent ?? '';
  let selected: File[] = [];
  const input = document.createElement('input');
  input.type = 'file';
  input.multiple = true;
  input.accept = [...allowedTypes].join(',');
  input.hidden = true;
  input.setAttribute('aria-label', originalLabel);
  box?.append(input);
  box?.setAttribute('role', 'button');
  box?.setAttribute('tabindex', '0');

  function select(files: File[]) {
    if (
      files.length > 5 ||
      files.some((file) => !allowedTypes.has(file.type) || file.size > maximumSize)
    ) {
      feedback(root, 'حداکثر ۵ تصویر JPG، PNG یا WebP، هر تصویر تا ۲۰ مگابایت انتخاب کنید.');
      return;
    }
    selected = files;
    if (label)
      label.textContent = files.length ? files.map((file) => file.name).join('، ') : originalLabel;
  }

  box?.addEventListener(
    'click',
    (event) => {
      if (event.target !== input) input.click();
    },
    { signal },
  );
  box?.addEventListener(
    'keydown',
    (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        input.click();
      }
    },
    { signal },
  );
  box?.addEventListener('dragover', (event) => event.preventDefault(), { signal });
  box?.addEventListener(
    'drop',
    (event) => {
      event.preventDefault();
      select(Array.from(event.dataTransfer?.files ?? []));
    },
    { signal },
  );
  input.addEventListener('change', () => select(Array.from(input.files ?? [])), { signal });
  signal.addEventListener('abort', () => input.remove(), { once: true });

  return {
    files: () => [...selected],
    clear: () => {
      input.value = '';
      select([]);
    },
  };
}
