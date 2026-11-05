import { api } from '@/lib/api/client';
import { prepareUpload } from './uploads';

export const imageAlbumClasses = [
  'portal-image-album',
  'portal-image-album-item',
  'portal-image-album-caption',
  'portal-image-album-columns-1',
  'portal-image-album-columns-2',
  'portal-image-album-columns-3',
] as const;

export function createImageAlbum(
  images: { url: string; alt?: string }[],
  columns = 3,
  caption = '',
): HTMLDivElement {
  if (!images.length || images.length > 20 || ![2, 3].includes(columns))
    throw new Error('آلبوم باید بین ۱ تا ۲۰ تصویر و چیدمان دو یا سه ستونی داشته باشد.');
  const album = document.createElement('div');
  album.className = `portal-image-album portal-image-album-columns-${Math.min(columns, images.length)}`;
  for (const { url, alt } of images) {
    if (!/^(https?:\/\/|\/(?!\/))/.test(url)) throw new Error('نشانی تصویر معتبر نیست.');
    const item = document.createElement('div');
    item.className = 'portal-image-album-item';
    const image = document.createElement('img');
    image.src = url;
    image.alt = alt ?? '';
    item.append(image);
    album.append(item);
  }
  if (caption.trim()) {
    const text = document.createElement('p');
    text.className = 'portal-image-album-caption';
    text.textContent = caption.trim().slice(0, 500);
    album.append(text);
  }
  return album;
}

type AlbumContext = {
  uploadEndpoint?: string;
  wrapper: HTMLElement;
  surface: HTMLElement;
  tools: HTMLElement;
  status: HTMLElement;
  signal: AbortSignal;
  preview: boolean;
  version: () => number;
  openTools: (nodes: Node[]) => void;
  insert: (node: HTMLElement) => void;
};

/** Each request uses the existing optimized, tenant-scoped media upload path. */
export function imageAlbumButton(context: AlbumContext): HTMLButtonElement {
  const { wrapper, surface, tools, status, signal, preview } = context;
  const toggle = document.createElement('button');
  toggle.type = 'button';
  toggle.textContent = 'آلبوم تصاویر';
  toggle.addEventListener(
    'click',
    () => {
      if (wrapper.dataset.uploading === 'true') return;
      const version = context.version();
      const active = () => !signal.aborted && context.version() === version;
      const label = (text: string, control: HTMLElement) => {
        const field = document.createElement('label');
        field.textContent = text;
        field.append(control);
        return field;
      };
      const file = document.createElement('input');
      file.type = 'file';
      file.multiple = true;
      file.accept = 'image/jpeg,image/png,image/webp';
      file.dataset.cmsFile = 'true';
      file.dataset.cmsAlbumFile = 'true';
      file.setAttribute('aria-label', 'انتخاب چند تصویر برای آلبوم');
      const columns = document.createElement('select');
      columns.setAttribute('aria-label', 'چیدمان آلبوم');
      columns.append(new Option('سه ستون', '3'), new Option('دو ستون', '2'));
      const caption = document.createElement('input');
      caption.type = 'text';
      caption.maxLength = 500;
      caption.setAttribute('aria-label', 'توضیح زیر آلبوم (اختیاری)');
      const help = document.createElement('p');
      help.textContent =
        'چند عکس را هم‌زمان انتخاب کنید. JPEG، PNG یا WebP؛ حداکثر ۲۰ تصویر در متن و ۴ مگابایت برای هر فایل پس از بهینه‌سازی. تصاویر کامل داخل کادر نمایش داده می‌شوند.';
      const uploaded = new Map<File, string>();
      file.addEventListener('change', () => uploaded.clear());
      const send = document.createElement('button');
      send.type = 'button';
      send.textContent = 'بارگذاری و درج آلبوم';
      const controls = [file, columns, caption, send];
      send.addEventListener('click', () => {
        if (!active() || wrapper.dataset.uploading === 'true') return;
        if (preview) {
          status.textContent = 'برای ذخیرهٔ تصاویر، اتصال واقعی به سرور لازم است.';
          return;
        }
        const files = Array.from(file.files ?? []);
        if (!files.length) {
          status.textContent = 'حداقل یک تصویر برای آلبوم انتخاب کنید.';
          return;
        }
        if (files.length + surface.querySelectorAll('img').length > 20) {
          status.textContent = 'حداکثر ۲۰ تصویر در متن مجاز است.';
          return;
        }
        if (
          files.some((image) => !['image/jpeg', 'image/png', 'image/webp'].includes(image.type))
        ) {
          status.textContent = 'فقط تصاویر JPEG، PNG یا WebP را انتخاب کنید.';
          return;
        }
        wrapper.dataset.uploading = 'true';
        wrapper.dataset.albumUploading = 'true';
        for (const control of controls) control.disabled = true;
        void (async () => {
          for (const [index, image] of files.entries()) {
            if (!active()) return;
            status.textContent = `در حال بارگذاری تصویر ${index + 1} از ${files.length}…`;
            if (uploaded.has(image)) continue;
            const prepared = await prepareUpload(image);
            if (!active()) return;
            const body = new FormData();
            body.set('file', prepared);
            body.set('visibility', 'public');
            body.set('alt', image.name.replace(/\.[^.]+$/, '').slice(0, 255));
            const result = await api<{ id: number; url: string }>(context.uploadEndpoint??'media', {
              method: 'POST',
              body,
              signal,
            });
            if (!active()) return;
            if (!/^(https?:\/\/|\/(?!\/))/.test(result.data.url ?? ''))
              throw new Error('نشانی تصویر از سرور دریافت نشد.');
            uploaded.set(image, result.data.url);
          }
          if (!active()) return;
          if (files.length + surface.querySelectorAll('img').length > 20)
            throw new Error('حداکثر ۲۰ تصویر در متن مجاز است.');
          const album = createImageAlbum(
            files.map((image) => ({
              url: uploaded.get(image)!,
              alt: image.name.replace(/\.[^.]+$/, '').slice(0, 255),
            })),
            Number(columns.value),
            caption.value,
          );
          context.insert(album);
          tools.hidden = true;
          status.textContent = `آلبوم با ${files.length} تصویر در متن درج شد.`;
        })()
          .catch((error: unknown) => {
            if (active())
              status.textContent = `${error instanceof Error ? error.message : 'بارگذاری ناموفق بود.'} آلبوم ناقص درج نشد؛ دوباره تلاش کنید. تصاویر بارگذاری‌شده دوباره ارسال نمی‌شوند.`;
          })
          .finally(() => {
            if (!active()) return;
            wrapper.dataset.uploading = 'false';
            delete wrapper.dataset.albumUploading;
            for (const control of controls) control.disabled = false;
          });
      });
      context.openTools([
        label('انتخاب چند تصویر', file),
        label('چیدمان آلبوم', columns),
        label('توضیح زیر آلبوم (اختیاری)', caption),
        help,
        send,
      ]);
    },
    { signal },
  );
  return toggle;
}
