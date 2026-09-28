<?php

use Illuminate\Database\Migrations\Migration;
use Lunar\FieldTypes\Text;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Meva\Entities\Catalogue\CatalogueCache;

/**
 * Tidies the shelves the app filters by.
 *
 * "Kosa" held an acne lotion, a body milk and a charcoal soap, and missed the
 * three hair-loss sets. Rozacea and Akne did not exist as shelves at all, and
 * "Set za rozaceu" was filed as a plain product, so it never showed as a set.
 */
return new class extends Migration
{
    private const NEW = [
        'rozacea' => ['Rozacea', [25, 52, 57, 59]],
        'akne' => ['Akne', [15, 50, 55, 66]],
    ];

    private const OUT_OF_HAIR = [66, 68, 70];

    private const INTO_HAIR = [17, 18, 19];

    private const ROSACEA_SET = 25;

    public function up(): void
    {
        $group = CollectionGroup::where('handle', 'kategorije')->firstOrFail();

        foreach (self::NEW as $slug => [$name, $products]) {
            $collection = $this->collection($slug) ?? Collection::create([
                'collection_group_id' => $group->id,
                'attribute_data' => collect([
                    'name' => new Text($name),
                    'slug' => new Text($slug),
                    'description' => new Text(''),
                ]),
            ]);
            $collection->products()->syncWithoutDetaching($products);
        }

        $this->collection('kosa')->products()->detach(self::OUT_OF_HAIR);
        $this->collection('kosa')->products()->syncWithoutDetaching(self::INTO_HAIR);
        $this->collection('preparati-za-lice')->products()->syncWithoutDetaching(self::OUT_OF_HAIR);

        Product::whereKey(self::ROSACEA_SET)->update(['product_type_id' => ProductType::where('name', 'Set')->value('id')]);
        $this->collection('setovi')->products()->syncWithoutDetaching([self::ROSACEA_SET]);

        CatalogueCache::bump();
    }

    public function down(): void
    {
        foreach (array_keys(self::NEW) as $slug) {
            if ($collection = $this->collection($slug)) {
                $collection->products()->detach();
                $collection->delete();
            }
        }

        $this->collection('kosa')->products()->detach(self::INTO_HAIR);
        $this->collection('kosa')->products()->syncWithoutDetaching(self::OUT_OF_HAIR);
        $this->collection('preparati-za-lice')->products()->detach(self::OUT_OF_HAIR);

        Product::whereKey(self::ROSACEA_SET)->update(['product_type_id' => ProductType::where('name', 'Proizvod')->value('id')]);
        $this->collection('setovi')->products()->detach([self::ROSACEA_SET]);

        CatalogueCache::bump();
    }

    private function collection(string $slug): ?Collection
    {
        return Collection::all()->first(fn (Collection $c): bool => (string) $c->attribute_data?->get('slug') === $slug);
    }
};
