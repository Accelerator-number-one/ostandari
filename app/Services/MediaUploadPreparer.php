<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class MediaUploadPreparer
{
    public function prepare(UploadedFile $file): PreparedUpload
    {
        $extension = $file->guessExtension();
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'docx', 'xlsx', 'mp4', 'mp3', 'txt'], true)) {
            $this->invalid('نوع واقعی فایل مجاز نیست.');
        }
        $path = $file->getRealPath();
        $mime = $file->getMimeType();
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        if (! str_starts_with($mime, 'image/')) {
            return new PreparedUpload($path, $extension, $mime, $file->getSize(), mb_substr($name, 0, 255));
        }

        $dimensions = @getimagesize($path);
        if (! $dimensions || ! in_array($dimensions[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            $this->invalid('تصویر معتبر نیست یا فرمت آن پشتیبانی نمی‌شود.');
        }
        [$width, $height] = $dimensions;
        if ($width < 1 || $height < 1 || $width * $height > max(1, (int) config('portal.images.max_pixels', 40000000))) {
            $this->invalid('ابعاد تصویر بیش از حد مجاز است. تصویر کوچک‌تری انتخاب کنید.');
        }
        $name = mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 240).'.webp';
        // A browser may have already optimized this file. Keep its bytes, quality and animation.
        if ($dimensions[2] === IMAGETYPE_WEBP) {
            return new PreparedUpload($path, 'webp', 'image/webp', $file->getSize(), $name, $width, $height);
        }

        abort_unless(function_exists('imagewebp') && (imagetypes() & IMG_WEBP), 503,
            'پردازش تصویر روی سرور فعال نیست. افزونه GD با پشتیبانی WebP باید فعال شود.');
        $edge = max(1, (int) config('portal.images.max_edge', 2000));
        $scale = min(1, $edge / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $this->checkMemory($width * $height, $targetWidth * $targetHeight);

        $source = $dimensions[2] === IMAGETYPE_JPEG ? @imagecreatefromjpeg($path) : @imagecreatefrompng($path);
        if (! $source) {
            $this->invalid('خواندن تصویر ممکن نیست. فایل سالمی انتخاب کنید.');
        }
        $image = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagecopyresampled($image, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        unset($source);
        if ($dimensions[2] === IMAGETYPE_JPEG) {
            $exif = @exif_read_data($path, 'IFD0', true);
            $image = $this->orient($image, (int) ($exif['IFD0']['Orientation'] ?? 1));
        }

        $temporary = tempnam(sys_get_temp_dir(), 'portal-webp-');
        abort_if($temporary === false, 503, 'فضای موقت پردازش تصویر در دسترس نیست.');
        try {
            $quality = max(1, min(100, (int) config('portal.images.quality', 84)));
            $written = imagewebp($image, $temporary, $quality);
            unset($image);
            clearstatcache(true, $temporary);
            $size = filesize($temporary);
            $output = @getimagesize($temporary);
            // Some GD versions report success even when the encoder writes no valid file.
            abort_unless($written && $size > 0 && $output && $output[2] === IMAGETYPE_WEBP, 503,
                'تبدیل تصویر انجام نشد. دوباره تلاش کنید.');

            return new PreparedUpload($temporary, 'webp', 'image/webp', $size, $name, $output[0], $output[1], true);
        } catch (\Throwable $e) {
            @unlink($temporary);
            throw $e;
        }
    }

    private function orient(GdImage $image, int $orientation): GdImage
    {
        if (in_array($orientation, [2, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif (in_array($orientation, [4, 5], true)) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }
        $angle = match ($orientation) {
            3 => 180,
            5, 6, 7 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);
            abort_if($rotated === false, 503, 'اصلاح جهت تصویر انجام نشد.');
            return $rotated;
        }

        return $image;
    }

    private function checkMemory(int $pixels, int $targetPixels): void
    {
        $limit = trim((string) ini_get('memory_limit'));
        if ($limit === '-1' || $limit === '') {
            return;
        }
        $bytes = (float) $limit * match (strtolower(substr($limit, -1))) {
            'g' => 1073741824,
            'm' => 1048576,
            'k' => 1024,
            default => 1,
        };
        $estimate = max($pixels * 5 + $targetPixels * 4, $targetPixels * 8);
        if ($estimate + memory_get_usage(true) + 8388608 > $bytes) {
            $this->invalid('حافظه کافی برای پردازش این تصویر وجود ندارد. تصویر کوچک‌تری انتخاب کنید.');
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
