<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Media
{
    public const DISK = 'public';

    /** Turn a stored path (or an absolute URL) into a browser URL. */
    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }
        if (Str::startsWith($path, ['http://', 'https://', '//', '/'])) {
            return $path;
        }

        return Storage::disk(self::DISK)->url($path);
    }

    public static function store(UploadedFile $file, string $folder = 'uploads'): string
    {
        $folder = trim($folder, '/').'/'.now()->format('Y/m');
        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';
        $name .= '-'.Str::lower(Str::random(6)).'.'.strtolower($file->getClientOriginalExtension() ?: $file->extension());

        return $file->storeAs($folder, $name, self::DISK);
    }

    /** Store raw bytes (a pasted or converted image) like an upload and return the path. */
    public static function put(string $bytes, string $folder, string $name, string $extension): string
    {
        $path = trim($folder, '/').'/'.now()->format('Y/m').'/'
            .(Str::slug($name) ?: 'file').'-'.Str::lower(Str::random(6)).'.'.strtolower($extension);
        Storage::disk(self::DISK)->put($path, $bytes);

        return $path;
    }

    /**
     * Screenshots straight from the screen are often 2–4k pixels wide: scale
     * JPG / PNG / WebP down to $max pixels. Anything else comes back unchanged.
     */
    public static function shrink(string $bytes, string $extension, int $max = 1600): string
    {
        $extension = strtolower($extension);
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) || ! ($image = @imagecreatefromstring($bytes)) || imagesx($image) <= $max) {
            return $bytes;
        }

        $image = imagescale($image, $max, -1, IMG_BICUBIC);
        ob_start();
        match ($extension) {
            'png' => imagesavealpha($image, true) && imagepng($image, null, 9),
            'webp' => imagewebp($image, null, 84),
            default => imagejpeg($image, null, 84),
        };

        return ob_get_clean() ?: $bytes;
    }

    /** Delete a file we stored, ignoring external URLs. */
    public static function delete(?string $path): void
    {
        if (filled($path) && ! Str::startsWith($path, ['http://', 'https://', '//', '/'])) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
