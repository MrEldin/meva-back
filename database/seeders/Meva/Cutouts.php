<?php

namespace Database\Seeders\Meva;

use Lunar\Models\Product;

/**
 * The cutouts: each product photographed and then lifted off its background.
 *
 * They arrived after the WooCommerce export was written, so they are not
 * listed in products.json; they are found by name next to the photographs.
 * The storefront leads with them because a transparent product can stand on
 * a tinted circle, in open white, or in a diagram, where a square photograph
 * can only sit in a box.
 *
 * There are two generations. The first stood each product among sprigs and
 * petals; the client did not care for the flowers, so every one was made
 * again without them, as "<folder>/<folder>-bez-pozadine-bez-cveca.webp".
 * That one is used when it exists, and the flowered
 * "<folder>-bez-pozadine.webp" only for a product that never got a plain one.
 *
 * Sixty-seven of the seventy-three products have a cutout; the rest keep the
 * photograph as their main picture, so looking for one is allowed to find
 * nothing.
 */
class Cutouts
{
    /**
     * The cutout to lead with for this product folder, or null if it has none.
     */
    public static function path(string $folder): ?string
    {
        if ($folder === '') {
            return null;
        }

        foreach (["{$folder}-bez-pozadine-bez-cveca.webp", "{$folder}-bez-pozadine.webp"] as $file) {
            $path = MevaExport::imagePath("{$folder}/{$file}");

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Attach the product's cutout, replacing the one it has if that is a
     * different file.
     *
     * Returns true when a cutout was attached, false when the product either
     * has no cutout or already carries this one. Re-running after a cutout is
     * regenerated replaces it, not stacks a second copy behind the first.
     */
    public static function sync(Product $product, string $folder): bool
    {
        $path = self::path($folder);

        if ($path === null) {
            return false;
        }

        $existing = $product->getMedia('images')
            ->first(fn ($media): bool => (bool) $media->getCustomProperty('cutout'));

        if ($existing !== null) {
            if ($existing->file_name === basename($path) && $existing->size === filesize($path)) {
                return false;
            }

            $existing->delete();
        }

        $product->addMedia($path)
            ->preservingOriginal()
            ->withCustomProperties(['cutout' => true, 'primary' => true])
            ->toMediaCollection('images');

        return true;
    }
}
