<?php

namespace Meva\Entities\Catalogue;

/**
 * Whether an image file has a see-through background.
 *
 * A product photographed and then lifted off its background arrives as a PNG
 * or WebP with transparent corners. That is the one thing that tells a cutout
 * from a studio photograph, and it decides how the storefront draws it: a
 * cutout stands free on a tinted circle, a photograph sits in its frame.
 * Every conversion Lunar makes paints the background white, so telling the
 * two apart has to happen before the conversions are made.
 */
class Transparency
{
    /**
     * True when the image can carry transparency and its corners are see-through.
     */
    public static function has(string $path, ?string $mime = null): bool
    {
        $mime ??= (string) (mime_content_type($path) ?: '');

        if (! in_array($mime, ['image/png', 'image/webp'], true) || ! function_exists('imagecreatefrompng')) {
            return false;
        }

        $image = match ($mime) {
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        };

        if ($image === false) {
            return false;
        }

        $w = imagesx($image);
        $h = imagesy($image);

        if ($w < 2 || $h < 2) {
            return false;
        }

        // Alpha in GD runs from 0 (opaque) to 127 (fully transparent). A cutout
        // is see-through at its corners; a photograph is not, whatever it is
        // in the middle.
        $corners = [[0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1]];
        $clear = 0;

        foreach ($corners as [$x, $y]) {
            $alpha = (imagecolorat($image, $x, $y) >> 24) & 0x7F;

            if ($alpha >= 64) {
                $clear++;
            }
        }

        return $clear >= 3;
    }
}
