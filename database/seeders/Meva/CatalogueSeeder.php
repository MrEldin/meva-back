<?php

namespace Database\Seeders\Meva;

use Illuminate\Database\Seeder;

/**
 * Imports the catalogue exported from the old WooCommerce shop.
 *
 * The export lives in database/seeders/data/products.json with the photography
 * in database/seeders/assets/images. Running this repeatedly updates existing
 * records rather than duplicating them, so it is safe to re-run after the
 * export is regenerated.
 */
class CatalogueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            CollectionSeeder::class,
            ProductSeeder::class,
            ProductBundleSeeder::class,
            ReviewSeeder::class,
        ]);
    }
}
