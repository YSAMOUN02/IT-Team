<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Media
{
    public const DISK = 'public';

    /** Picture types the admin's image fields take. */
    public const IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

    /** Pictures straight from a phone can be 10 MB+; storeImage() scales them down. */
    public const IMAGE_MAX_KB = 20480;

    /** Upload limit in KB: $wanted, or less when PHP on this server (upload_max_filesize / post_max_size) takes less. */
    public static function uploadMaxKb(int $wanted = self::IMAGE_MAX_KB): int
    {
        return (int) min($wanted, floor(UploadedFile::getMaxFilesize() / 1024));
    }

    /** "20 MB" for a size in KB. */
    public static function sizeLabel(int $kb): string
    {
        return $kb >= 1024 ? round($kb / 1024, 1).' MB' : $kb.' KB';
    }

    /** For <input accept="…">: ".jpg,.jpeg,.png…" */
    public static function accept(array $types = self::IMAGE_TYPES): string
    {
        return implode(',', array_map(fn ($type) => '.'.$type, $types));
    }

    /** Bytes PHP takes in one form post (post_max_size); a form with several files must stay under it. */
    public static function postMaxBytes(): int|float
    {
        return self::iniBytes('post_max_size') ?: INF;
    }

    /**
     * Store an uploaded picture under the type it really is (not the name it came with: a PNG
     * called "x.html" is saved as .png), turned upright and scaled down to $maxWidth.
     */
    public static function storeImage(UploadedFile $file, string $folder, int $maxWidth = 2400): string
    {
        $extension = $file->guessExtension() ?: strtolower($file->getClientOriginalExtension());

        return self::put(self::shrink($file->get(), $extension, $maxWidth), $folder, pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), $extension);
    }

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
     * JPG / PNG / WebP down to $max pixels. Anything else comes back unchanged,
     * and so does a picture too big to open in the memory PHP has left.
     *
     * Phone photos are often stored sideways with an EXIF "turn me" note that
     * re-saving would lose, so they are turned upright first.
     */
    public static function shrink(string $bytes, string $extension, int $max = 1600): string
    {
        $extension = strtolower($extension);
        $size = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? @getimagesizefromstring($bytes) : false;
        if (! $size) {
            return $bytes;
        }

        $orientation = in_array($extension, ['jpg', 'jpeg'], true) ? self::exifOrientation($bytes) : 1;
        $width = $orientation >= 5 ? $size[1] : $size[0];
        // A decoded picture takes ~5 bytes a pixel, and turning it makes a second copy.
        if ($width <= $max || $size[0] * $size[1] * 10 > self::memoryLeft() || ! ($image = @imagecreatefromstring($bytes))) {
            return $bytes;
        }

        // EXIF 2, 4, 5, 7 are mirrored; imagerotate() turns anticlockwise.
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }
        if ($turn = [3 => 180, 4 => 180, 5 => 90, 6 => 270, 7 => 270, 8 => 90][$orientation] ?? 0) {
            $image = imagerotate($image, $turn, 0);
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

    /** EXIF orientation of a JPEG (1 = upright, 6 = turn right, 8 = turn left …). */
    private static function exifOrientation(string $bytes): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }
        $stream = fopen('php://memory', 'r+b');
        fwrite($stream, $bytes);
        rewind($stream);
        $orientation = (int) (@exif_read_data($stream)['Orientation'] ?? 1);
        fclose($stream);

        return $orientation;
    }

    /** Bytes PHP may still allocate before hitting memory_limit. */
    private static function memoryLeft(): int|float
    {
        $limit = self::iniBytes('memory_limit');

        return $limit > 0 ? $limit - memory_get_usage() : INF;
    }

    /** A php.ini size ("512M", "40M", "-1") in bytes; 0 when unset or unlimited. */
    private static function iniBytes(string $key): int
    {
        $value = trim((string) ini_get($key));
        if ($value === '' || $value === '-1') {
            return 0;
        }

        return (int) $value * match (strtolower(substr($value, -1))) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };
    }

    /** Delete a file we stored, ignoring external URLs. */
    public static function delete(?string $path): void
    {
        if (filled($path) && ! Str::startsWith($path, ['http://', 'https://', '//', '/'])) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
