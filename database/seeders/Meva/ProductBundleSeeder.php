<?php

namespace Database\Seeders\Meva;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Lunar\Models\ProductVariant;

/**
 * Records what each of the 14 sets is made of.
 *
 * The old shop built sets with a WooCommerce bundle plugin. Lunar has no native
 * bundle type, so the composition is kept in product_bundle_items instead of
 * being flattened into a standalone product -- it is what makes per-component
 * stock, picking lists and "what is in this set" possible later.
 */
class ProductBundleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // wp_id => lunar product id, via the SKU the product seeder assigned.
        $byWpId = ProductVariant::query()
            ->where('sku', 'like', 'MEVA-%')
            ->pluck('product_id', 'sku')
            ->mapWithKeys(fn (int $productId, string $sku): array => [
                (int) str_replace('MEVA-', '', $sku) => $productId,
            ]);

        $rows = [];
        $missing = 0;

        foreach (MevaExport::products() as $product) {
            if ($product['tip'] !== 'set') {
                continue;
            }

            $bundleId = $byWpId->get($product['wp_id']);

            foreach ($product['set_sadrzi'] ?? [] as $item) {
                $itemId = $byWpId->get($item['id']);

                if ($bundleId === null || $itemId === null) {
                    $missing++;

                    continue;
                }

                $rows[] = [
                    'bundle_product_id' => $bundleId,
                    'item_product_id' => $itemId,
                    'quantity' => $item['kolicina'] ?? 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('product_bundle_items')->upsert(
            $rows,
            ['bundle_product_id', 'item_product_id'],
            ['quantity', 'updated_at']
        );

        $this->command?->info('Bundle items: '.count($rows).($missing > 0 ? " ({$missing} unresolved)" : ''));
    }
}
