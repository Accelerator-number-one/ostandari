import { all } from '@/lib/dom';
import { adminPermissions } from '@/data/admin/permissions';

export function mountPermissionFields(root: HTMLElement, signal: AbortSignal): void {
  for (const source of all<HTMLTextAreaElement>(root, 'textarea[data-permissions]')) {
    const initialRequired = source.required;
    const initialHidden = source.hidden;
    const choices = document.createElement('fieldset');
    choices.className = 'portal-permission-choices';
    const legend = document.createElement('legend');
    legend.textContent = 'دسترسی‌های نقش';
    choices.append(legend);
    source.before(choices);
    source.hidden = true;
    source.required = false;
    signal.addEventListener(
      'abort',
      () => {
        choices.remove();
        source.required = initialRequired;
        source.hidden = initialHidden;
      },
      { once: true },
    );
    for (const [key, title] of Object.entries(adminPermissions)) {
      const label = document.createElement('label');
      const input = document.createElement('input');
      input.type = 'checkbox';
      input.value = key;
      label.append(input, title);
      choices.append(label);
      input.addEventListener(
        'change',
        () => {
          source.value = JSON.stringify(
            all<HTMLInputElement>(choices, 'input:checked').map((item) => item.value),
          );
        },
        { signal },
      );
    }
    const reload = () => {
      let values: string[] = [];
      try {
        values = JSON.parse(source.value || '[]');
      } catch {
        /* Validation reports an invalid advanced value on save. */
      }
      for (const input of all<HTMLInputElement>(choices, 'input'))
        input.checked = values.includes('*') || values.includes(input.value);
    };
    root.addEventListener('portal:fields-filled', reload, { signal });
    reload();
  }
}
