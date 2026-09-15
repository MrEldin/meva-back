<?php

namespace Meva\Web;

use Illuminate\Support\Facades\Cache;

/**
 * How big a share image actually is.
 *
 * Messengers read og:image:width and og:image:height and lay the card out
 * before the picture has finished arriving. Declare the wrong numbers and the
 * card collapses to the small one-line preview -- which is what happened here:
 * every page claimed 1200x1200 while the file was 1200x630.
 *
 * Reading the file is cheap for media on this disk and a short HTTP range
 * request otherwise, and the answer is cached for a day because an image at a
 * given address does not change shape.
 */
class ImageSize
{
    /**
     * @return array{0: int, 1: int}|null
     */
    public static function of(?string $url): ?array
    {
        if ($url === null || $url === '') {
            return null;
        }

        return Cache::remember('share:size:'.md5($url), 86400, function () use ($url): ?array {
            $path = self::localPath($url);

            $size = @getimagesize($path ?? $url);

            return $size === false ? null : [(int) $size[0], (int) $size[1]];
        });
    }

    /**
     * The file on this machine, when the address points at something we serve.
     *
     * Reading the disk avoids a round trip through nginx for our own pictures,
     * and works even when the host is not reachable from inside the server.
     */
    protected static function localPath(string $url): ?string
    {
        $storefront = rtrim((string) config('meva.storefront_url'), '/');
        $appUrl = rtrim((string) config('app.url'), '/');

        foreach ([$storefront => config('meva.storefront_root'), $appUrl => public_path()] as $base => $root) {
            if ($root && str_starts_with($url, $base.'/')) {
                $candidate = rtrim((string) $root, '/').'/'.ltrim(parse_url(substr($url, strlen($base)), PHP_URL_PATH) ?? '', '/');

                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }
}
