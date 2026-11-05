const maximumBytes = 4_000_000;

/** Keep requests below the proxy payload ceiling and reduce image storage without another package. */
export async function prepareUpload(file: File): Promise<File> {
  let result = file;
  if (
    ['image/jpeg', 'image/png', 'image/webp'].includes(file.type) &&
    typeof createImageBitmap === 'function'
  ) {
    let bitmap: ImageBitmap | undefined;
    try {
      bitmap = await createImageBitmap(file);
      if (bitmap.width * bitmap.height > 40_000_000)
        throw new Error('ابعاد تصویر بیش از حد بزرگ است.');
      const scale = Math.min(1, 2000 / Math.max(bitmap.width, bitmap.height));
      const canvas = document.createElement('canvas');
      canvas.width = Math.max(1, Math.round(bitmap.width * scale));
      canvas.height = Math.max(1, Math.round(bitmap.height * scale));
      const context = canvas.getContext('2d');
      if (context) {
        context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise<Blob | null>((resolve) =>
          canvas.toBlob(resolve, 'image/webp', 0.84),
        );
        if (blob && blob.type === 'image/webp' && blob.size < file.size)
          result = new File([blob], file.name.replace(/\.[^.]+$/, '') + '.webp', {
            type: 'image/webp',
          });
      }
    } finally {
      bitmap?.close();
    }
  }
  if (result.size > maximumBytes)
    throw new Error('حجم فایل باید حداکثر ۴ مگابایت باشد. تصویر یا فایل را کوچک‌تر کنید.');
  return result;
}
