<?php

// One-off, run once on the server:
//
//   php artisan tinker deploy/shelves.php
//
// "Set za rozaceu" becomes the Rozacea shelf, Akne gets a shelf of its own,
// and the products that answer each are put on them. Safe to run twice.

use Lunar\Models\Collection;
use Lunar\Models\Url;
use Meva\Entities\Catalogue\CatalogueCache;

$text = fn (string $v) => ['value' => $v, 'field_type' => 'Lunar\\FieldTypes\\Text'];

$shelves = [
    ['id' => 6, 'name' => 'Rozacea', 'slug' => 'rozacea', 'products' => [25, 52, 57, 59]],
    ['id' => null, 'name' => 'Akne', 'slug' => 'akne', 'products' => [15, 46, 50, 55, 66]],
];

foreach ($shelves as $shelf) {
    $collection = $shelf['id']
        ? Collection::findOrFail($shelf['id'])
        : (Collection::query()->whereJsonContains('attribute_data->slug->value', $shelf['slug'])->first()
            ?? Collection::create(['collection_group_id' => 2, 'type' => 'static', 'sort' => 'custom']));

    // Lunar keeps the name and slug as typed attributes in one JSON column.
    $data = json_decode($collection->getRawOriginal('attribute_data') ?: '{}', true);
    $data['name'] = $text($shelf['name']);
    $data['slug'] = $text($shelf['slug']);
    $data['description'] = $data['description'] ?? $text('');
    $collection->setRawAttributes(array_merge($collection->getAttributes(), ['attribute_data' => json_encode($data)]));
    $collection->save();

    Url::updateOrCreate(
        ['element_type' => 'collection', 'element_id' => $collection->id, 'language_id' => 1, 'default' => true],
        ['slug' => $shelf['slug']],
    );

    $have = $collection->products()->pluck('lunar_products.id')->all();
    $position = (int) DB::table('lunar_collection_product')->where('collection_id', $collection->id)->max('position');
    foreach ($shelf['products'] as $productId) {
        if (! in_array($productId, $have, true)) {
            $collection->products()->attach($productId, ['position' => ++$position]);
        }
    }

    echo $shelf['name'].' (#'.$collection->id.'): '.$collection->products()->count().' proizvoda'.PHP_EOL;
}

CatalogueCache::bump();
echo 'cache bumped'.PHP_EOL;
