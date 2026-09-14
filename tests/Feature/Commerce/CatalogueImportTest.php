<?php

use Database\Seeders\Meva\CatalogueSeeder;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Collection;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
});

/**
 * Re-seed with photography enabled. Copying 82 MB through the media library is
 * too slow to do for every test, so only the tests that assert on images pay
 * for it.
 */
function seedWithImages(): void
{
    config()->set('meva.catalogue.import_images', true);
    test()->seed(CatalogueSeeder::class);
}

it('imports every exported product', function () {
    expect(Product::count())->toBe(73)
        ->and(ProductVariant::count())->toBe(73);
});

it('publishes only the products that have a price', function () {
    expect(Product::where('status', 'published')->count())->toBe(68)
        ->and(Product::where('status', 'draft')->count())->toBe(5);

    // Nothing published may be unbuyable.
    Product::where('status', 'published')->with('variants.prices')->get()
        ->each(fn (Product $p) => expect($p->variants->first()->prices)->not->toBeEmpty());
});

it('gives every product a traceable SKU', function () {
    expect(ProductVariant::where('sku', 'like', 'MEVA-%')->count())->toBe(73)
        ->and(ProductVariant::distinct('sku')->count('sku'))->toBe(73);
});

it('stores prices in minor units, in RSD and EUR', function () {
    $variant = ProductVariant::where('sku', 'MEVA-15395')->sole();

    $rsd = $variant->prices->first(fn (Price $p): bool => $p->currency->code === 'RSD');
    $eur = $variant->prices->first(fn (Price $p): bool => $p->currency->code === 'EUR');

    expect($rsd->price->value)->toBe(120000)      // 1.200,00 RSD
        ->and($rsd->price->decimal())->toBe(1200.0)
        ->and($eur->price->value)->toBe(1400);    // 14,00 EUR
});

it('leaves no product without a category', function () {
    expect(Product::doesntHave('collections')->count())->toBe(0);
});

it('parks the uncategorised products in Nesvrstano', function () {
    $nesvrstano = Collection::get()
        ->first(fn (Collection $c): bool => (string) $c->attribute_data->get('name') === 'Nesvrstano');

    expect($nesvrstano)->not->toBeNull()
        ->and($nesvrstano->products()->count())->toBe(16);
});

it('keeps what each set is made of', function () {
    expect(DB::table('product_bundle_items')->count())->toBe(66);

    // Every recorded component resolves to a real product.
    $productIds = Product::pluck('id');

    DB::table('product_bundle_items')->get()->each(function ($row) use ($productIds): void {
        expect($productIds)->toContain($row->bundle_product_id)
            ->and($productIds)->toContain($row->item_product_id);
    });
});

it('attaches the product photography', function () {
    seedWithImages();

    $product = ProductVariant::where('sku', 'MEVA-15395')->sole()->product;

    expect($product->getMedia('images'))->toHaveCount(5);
});

it('never imports the marketing artwork', function () {
    seedWithImages();

    $paths = Spatie\MediaLibrary\MediaCollections\Models\Media::pluck('file_name');

    expect($paths->filter(fn (string $name): bool => str_contains($name, 'galerija')))->toBeEmpty();
});

it('can be run twice without duplicating anything', function () {
    $this->seed(CatalogueSeeder::class);

    expect(Product::count())->toBe(73)
        ->and(ProductVariant::count())->toBe(73)
        ->and(DB::table('product_bundle_items')->count())->toBe(66);
});
