<?php

namespace Database\Seeders\Meva;

use Illuminate\Database\Seeder;
use Lunar\FieldTypes\Text;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;

/**
 * Imports the shop's categories as a Lunar collection group.
 *
 * The old shop also had an "Uncategorized" bucket holding 16 products. It is
 * imported as "Nesvrstano" rather than dropped, so nothing disappears silently
 * while the catalogue is being re-categorised.
 */
class CollectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $group = CollectionGroup::firstOrCreate(
            ['handle' => 'kategorije'],
            ['name' => 'Kategorije']
        );

        $existing = Collection::query()
            ->where('collection_group_id', $group->id)
            ->get()
            ->keyBy(fn (Collection $collection): string => (string) $collection->attribute_data?->get('slug'));

        foreach (MevaExport::categories() as $category) {
            $name = $category['naziv'] === 'Uncategorized' ? 'Nesvrstano' : $category['naziv'];

            // The old slug is carried over so the 301 map in redirects.csv
            // keeps pointing at something real.
            $attributes = collect([
                'name' => new Text($name),
                'slug' => new Text($category['slug']),
                'description' => new Text($category['opis'] ?? ''),
            ]);

            if ($collection = $existing->get($category['slug'])) {
                $collection->update(['attribute_data' => $attributes]);

                continue;
            }

            Collection::create([
                'collection_group_id' => $group->id,
                'attribute_data' => $attributes,
            ]);
        }

        $this->command?->info('Categories: '.MevaExport::categories()->count());
    }
}
