<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Makes a small square picture from an uploaded photo (GD): the middle square is cut out,
 * shrunk to 400 × 400 and saved as WebP (about 20–60 KB), so pages and the app stay fast
 * on weak internet. A phone photo taken sideways is turned upright first (EXIF).
 * The file goes to the private disk: storage/app/private/student-photos.
 */
class StudentPhoto
{
    public const SIZE = 400;

    public static function store(UploadedFile $file, int $studentId): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $source) {
            throw ValidationException::withMessages(['photo' => __('This file is not a picture that can be opened.')]);
        }
        $source = self::upright($source, $file);

        $w = imagesx($source);
        $h = imagesy($source);
        $side = min($w, $h);
        $square = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagecopyresampled($square, $source, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), self::SIZE, self::SIZE, $side, $side);

        ob_start();
        imagewebp($square, null, 80);
        $data = ob_get_clean();
        imagedestroy($source);
        imagedestroy($square);

        $path = "student-photos/{$studentId}-".Str::random(10).'.webp';
        Storage::disk('local')->put($path, $data);

        return $path;
    }

    /** Phone cameras often save the picture sideways and only note the turn in the EXIF data. */
    private static function upright(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }
        $orientation = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;

        return $angle ? imagerotate($image, $angle, 0) : $image;
    }
}
