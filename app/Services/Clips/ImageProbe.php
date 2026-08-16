<?php

namespace App\Services\Clips;

/**
 * Cheap image inspection used to describe a clip image (uploaded or generated):
 * its average tone (for background contrast) and whether it has transparency.
 */
class ImageProbe
{
    /** Average luminance of the opaque pixels → 'light' | 'dark' | 'mixed'. */
    public static function tone(string $path): string
    {
        $data = @file_get_contents($path);
        $im = $data ? @imagecreatefromstring($data) : false;
        if (! $im) {
            return 'mixed';
        }
        if (! imageistruecolor($im)) {
            imagepalettetotruecolor($im);
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $stepX = max(1, (int) ($w / 24));
        $stepY = max(1, (int) ($h / 24));
        $sum = 0.0;
        $count = 0;
        for ($y = 0; $y < $h; $y += $stepY) {
            for ($x = 0; $x < $w; $x += $stepX) {
                $c = imagecolorat($im, $x, $y);
                if ((($c >> 24) & 0x7F) > 100) {
                    continue; // near-transparent — ignore
                }
                $lum = (0.2126 * (($c >> 16) & 0xFF) + 0.7152 * (($c >> 8) & 0xFF) + 0.0722 * ($c & 0xFF)) / 255;
                $sum += $lum;
                $count++;
            }
        }
        imagedestroy($im);
        if ($count === 0) {
            return 'mixed';
        }
        $avg = $sum / $count;

        return $avg > 0.62 ? 'light' : ($avg < 0.4 ? 'dark' : 'mixed');
    }

    /** Cheap transparency check: PNG colour type (4=GA, 6=RGBA); webp/gif may also have alpha. */
    public static function hasAlpha(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'png') {
            $bytes = @file_get_contents($path, false, null, 0, 26);

            return strlen((string) $bytes) >= 26 && in_array(ord($bytes[25]), [4, 6], true);
        }

        return in_array($ext, ['webp', 'gif'], true);
    }
}
