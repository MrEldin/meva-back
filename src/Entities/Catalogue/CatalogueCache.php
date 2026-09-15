<?php

namespace Meva\Entities\Catalogue;

use Illuminate\Support\Facades\Cache;

/**
 * The storefront's catalogue, held in Redis.
 *
 * Sixty-eight products with their prices, photographs and categories cost
 * about a second to assemble, and every page of the shop asks for them. They
 * change when someone edits a product, which is rarely -- so the answer is
 * kept until that happens, rather than rebuilt for every visitor.
 *
 * Invalidation is a version number in the key: bumping it retires every
 * catalogue entry at once, with no need to know which ones exist.
 */
class CatalogueCache
{
    protected const VERSION_KEY = 'catalogue:version';

    /**
     * Remember a catalogue answer under the current version.
     */
    public static function remember(string $key, callable $callback): mixed
    {
        if (! config('meva.catalogue.cache', true)) {
            return $callback();
        }

        return Cache::remember(
            'catalogue:v'.self::version().':'.$key,
            config('meva.catalogue.ttl', 3600),
            $callback,
        );
    }

    /**
     * The current catalogue version.
     */
    public static function version(): int
    {
        return (int) Cache::rememberForever(self::VERSION_KEY, fn (): int => 1);
    }

    /**
     * Retire every cached catalogue answer.
     */
    public static function bump(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }
}
