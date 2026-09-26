<?php

namespace App\Console\Meva\Commands;

use Database\Seeders\Meva\Cutouts;
use Database\Seeders\Meva\MevaExport;
use Illuminate\Console\Command;
use Lunar\Models\ProductVariant;
use Meva\Entities\Catalogue\CatalogueCache;

/**
 * Bring every product's cutout up to date with the files in the repository.
 *
 * Re-running the whole catalogue seeder would do this too, but it also writes
 * the exported names, descriptions and prices back over whatever has been
 * edited in the admin since. This touches the cutouts and nothing else, so it
 * is what to run on a live shop after the cutouts are regenerated.
 */
class SyncCutouts extends Command
{
    protected $signature = 'meva:cutouts';

    protected $description = 'Replace each product\'s cutout with the current file beside its photographs';

    public function handle(): int
    {
        $replaced = 0;
        $missing = 0;

        foreach (MevaExport::products() as $row) {
            $product = ProductVariant::query()->where('sku', MevaExport::sku($row))->first()?->product;

            if ($product === null) {
                $missing++;

                continue;
            }

            if (Cutouts::sync($product, (string) ($row['folder'] ?? $row['slug'] ?? ''))) {
                $replaced++;
                $this->line('  '.$row['naziv']);
            }
        }

        if ($replaced > 0) {
            CatalogueCache::bump();
        }

        $this->info("Cutouts replaced: {$replaced}".($missing > 0 ? ", products not in the database: {$missing}" : ''));

        return self::SUCCESS;
    }
}
