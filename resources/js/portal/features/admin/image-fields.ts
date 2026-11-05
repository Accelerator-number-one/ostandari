import { api } from '@/lib/api/client';
import { all, one, setImage } from '@/lib/dom';
import type { RecordData } from '@/types/portal';
import { prepareUpload } from './uploads';

/** One upload lifecycle per relation, so two county images cannot overwrite each other. */
export function mountImageFields(root: HTMLElement, signal: AbortSignal, preview: boolean) {
  const states = all<HTMLElement>(root, '[data-image-field]').map((node) => {
    const input = one<HTMLInputElement>(node, '[data-image-upload]')!;
    const select = one<HTMLSelectElement>(node, '[data-image-value]')!;
    const figure = one<HTMLElement>(node, '[data-image-preview]')!;
    const status = one<HTMLElement>(node, '[data-image-status]')!;
    const retry = one<HTMLButtonElement>(node, '[data-image-retry]')!;
    const remove = one<HTMLButtonElement>(node, '[data-image-remove]')!;
    const field = node.dataset.imageField!;
    let version = 0;
    let abort: AbortController | undefined;
    let pending: Promise<void> | undefined;
    let uploaded: File | undefined;
    let error: Error | undefined;
    const message = (text: string, failed = false) => {
      status.textContent = text;
      node.dataset.imageState = failed ? 'error' : 'ready';
      retry.hidden = !failed;
    };
    const updatePreview = () => {
      const option = select.selectedOptions[0];
      const url = option?.dataset.imageUrl;
      setImage(one<HTMLImageElement>(figure, 'img'), url ? { url } : null);
      figure.hidden = !url;
      one(figure, 'figcaption')!.textContent = select.value ? (option?.textContent ?? '') : '';
      remove.hidden = !select.value;
    };
    const reset = () => {
      version++;
      abort?.abort();
      pending = undefined;
      uploaded = undefined;
      error = undefined;
      retry.hidden = true;
      node.removeAttribute('aria-busy');
    };
    const start = (file: File): Promise<void> => {
      reset();
      const requestVersion = version;
      abort = new AbortController();
      const uploadSignal = abort.signal;
      node.setAttribute('aria-busy', 'true');
      message('در حال بهینه‌سازی و بارگذاری تصویر…');
      pending = (async () => {
        // Assign pending before synchronous validation can complete.
        await Promise.resolve();
        try {
          if (signal.aborted) throw new Error('این فرم بسته شده است.');
          if (preview || root.dataset.canWrite === 'false')
            throw new Error('این فرم امکان بارگذاری ندارد.');
          if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type))
            throw new Error('فقط عکس JPG، PNG یا WebP انتخاب کنید.');
          const prepared = await prepareUpload(file);
          if (uploadSignal.aborted || signal.aborted) return;
          const body = new FormData();
          body.set('file', prepared);
          body.set('visibility', 'public');
          const { data } = await api<{ id: number; url?: string; original_name?: string }>(
            'media',
            {
              method: 'POST',
              body,
              signal: uploadSignal,
            },
          );
          if (signal.aborted || uploadSignal.aborted || version !== requestVersion) return;
          if (!data?.id) throw new Error('پاسخ بارگذاری تصویر معتبر نبود. دوباره تلاش کنید.');
          let option = Array.from(select.options).find((item) => item.value === String(data.id));
          if (!option) {
            option = new Option(data.original_name || prepared.name, String(data.id));
            select.add(option);
          }
          if (data.url) option.dataset.imageUrl = data.url;
          select.value = String(data.id);
          uploaded = file;
          error = undefined;
          updatePreview();
          message('تصویر بارگذاری شد. برای ثبت آن در سایت، فرم را ذخیره کنید.');
        } catch (caught) {
          if (signal.aborted || uploadSignal.aborted || version !== requestVersion) return;
          error = caught instanceof Error ? caught : new Error('بارگذاری تصویر انجام نشد.');
          message(error.message + ' تصویر قبلی حفظ شده است.', true);
        } finally {
          if (version === requestVersion) {
            pending = undefined;
            node.removeAttribute('aria-busy');
          }
        }
      })();
      return pending;
    };
    input.addEventListener(
      'change',
      () => {
        const file = input.files?.[0];
        if (file) void start(file);
      },
      { signal },
    );
    retry.addEventListener(
      'click',
      () => {
        const file = input.files?.[0];
        if (file) void start(file);
      },
      { signal },
    );
    remove.addEventListener(
      'click',
      () => {
        reset();
        input.value = '';
        select.value = '';
        updatePreview();
        message(
          'تصویر از این فرم برداشته شد؛ فایل اصلی در کتابخانه باقی می‌ماند. برای ثبت تغییر، ذخیره کنید.',
        );
      },
      { signal },
    );
    select.addEventListener(
      'change',
      () => {
        reset();
        input.value = '';
        updatePreview();
        message(
          select.value
            ? 'تصویر انتخاب شد. برای ثبت تغییر، فرم را ذخیره کنید.'
            : 'تصویری انتخاب نشده است.',
        );
      },
      { signal },
    );
    root.addEventListener(
      'portal:fields-filled',
      (event) => {
        reset();
        const record = (event as CustomEvent<RecordData>).detail ?? {};
        const relation = (
          {
            media_id: 'image',
            cover_media_id: 'cover',
            banner_media_id: 'banner',
            aerial_media_id: 'aerial',
            logo_media_id: 'logo',
            slogan_media_id: 'slogan',
          } as Record<string, string>
        )[field];
        const media = record[relation] as RecordData | null;
        const option = select.selectedOptions[0];
        if (option && media?.url) {
          option.dataset.imageUrl = String(media.url);
          option.textContent = String(media.original_name || 'تصویر فعلی');
        }
        updatePreview();
        message(
          select.value
            ? 'تصویر فعلی؛ برای جایگزینی، عکس تازه‌ای انتخاب کنید.'
            : 'هنوز تصویری انتخاب نشده است.',
        );
      },
      { signal },
    );
    signal.addEventListener('abort', reset, { once: true });
    return {
      async ready() {
        if (signal.aborted) throw new Error('این فرم بسته شده است.');
        const file = input.files?.[0];
        if (!pending && file && file !== uploaded && !error) void start(file);
        while (pending) await pending;
        if (signal.aborted) throw new Error('این فرم بسته شده است.');
        if (error) {
          input.focus();
          throw error;
        }
      },
      validate() {
        if (select.dataset.imageRequired && !select.value) {
          input.focus();
          throw new Error(
            'لطفاً ' + (one(node, 'header label')?.textContent || 'تصویر') + ' را انتخاب کنید.',
          );
        }
      },
    };
  });
  return {
    async ready() {
      await Promise.all(states.map((state) => state.ready()));
    },
    validate() {
      states.forEach((state) => state.validate());
    },
  };
}
