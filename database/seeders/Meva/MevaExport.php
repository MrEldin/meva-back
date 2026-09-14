<?php

namespace Database\Seeders\Meva;

use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Reads the WooCommerce export that the catalogue seeders share.
 */
class MevaExport
{
    /**
     * Absolute path to the exported data directory.
     */
    public static function dataPath(string $file = ''): string
    {
        return database_path('seeders/data/'.$file);
    }

    /**
     * Absolute path to the exported product photography.
     */
    public static function imagePath(string $relative = ''): string
    {
        return database_path('seeders/assets/images/'.$relative);
    }

    /**
     * Read a JSON file from the export.
     *
     * @return array<mixed>
     */
    public static function json(string $file): array
    {
        $path = self::dataPath($file);

        if (! is_file($path)) {
            throw new RuntimeException("Missing export file [{$path}]. See database/seeders/data/README.md.");
        }

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * All exported products.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function products(): Collection
    {
        $data = self::json('products.json');

        return collect($data['proizvodi'] ?? $data);
    }

    /**
     * All exported categories.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function categories(): Collection
    {
        $data = self::json('categories.json');

        return collect($data['kategorije'] ?? $data);
    }

    /**
     * Build the SKU for a product.
     *
     * The old shop had none, so they are derived from the WordPress id, which
     * also keeps a traceable link back to the original records and their order
     * history.
     */
    public static function sku(array $product): string
    {
        return 'MEVA-'.$product['wp_id'];
    }
}
