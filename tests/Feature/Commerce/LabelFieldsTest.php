<?php

use Illuminate\Http\Response;
use Lunar\FieldTypes\Text;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Meva\Entities\Product\Services\ProductCreateService;

beforeEach(function () {
    $this->type = ProductType::firstOrCreate(['name' => 'Proizvod']);
});

it('keeps the ingredients and the directions as fields of their own', function () {
    $product = app(ProductCreateService::class)->handle([
        'name' => 'Šampon', 'slug' => 'sampon', 'price' => 700, 'status' => 'published', 'product_type_id' => $this->type->id,
    ]);

    $this->put(url("/api/admin/products/{$product->id}"), [
        'ingredients' => '<ul><li>Aqua</li></ul>',
        'usage' => '<p>Naneti na mokru kosu.</p>',
    ], authHeaders())->assertStatus(Response::HTTP_OK);

    $desk = $this->get(url("/api/admin/products/{$product->id}"), authHeaders())->json('data');
    $shop = $this->get(url('/api/shop/products/sampon?include=description'))->json('data.description');
    $shop = $shop['data'] ?? $shop;

    expect($desk['ingredients'])->toBe('<ul><li>Aqua</li></ul>')
        ->and($desk['usage'])->toBe('<p>Naneti na mokru kosu.</p>')
        ->and($shop['ingredients'])->toBe('<ul><li>Aqua</li></ul>')
        ->and($shop['usage'])->toBe('<p>Naneti na mokru kosu.</p>');
});

it('moves Sastav and Način upotrebe out of the old descriptions', function () {
    $product = app(ProductCreateService::class)->handle([
        'name' => 'Šampon', 'price' => 700, 'product_type_id' => $this->type->id,
        'description' => '<p>Savršen izbor.</p><strong>Sastav:</strong><ul><li>Aqua</li></ul><strong>Način upotrebe:</strong><p>Naneti.</p>',
        'short_description' => "Savršen izbor.\n\nSastav:\n\n - Aqua\n\nNačin upotrebe: Naneti.",
    ]);

    $this->artisan('meva:label:split')->assertSuccessful();

    $data = Product::query()->findOrFail($product->id)->attribute_data;

    expect((string) $data->get('description'))->toBe('<p>Savršen izbor.</p>')
        ->and((string) $data->get('short_description'))->toBe('Savršen izbor.')
        ->and((string) $data->get('ingredients'))->toBe('<ul><li>Aqua</li></ul>')
        ->and((string) $data->get('usage'))->toBe('<p>Naneti.</p>');

    // Run again: nothing left to move, nothing touched.
    $this->artisan('meva:label:split')->expectsOutputToContain('0 changed')->assertSuccessful();
});

it('leaves a product that already has the fields alone', function () {
    $product = app(ProductCreateService::class)->handle([
        'name' => 'Krema', 'price' => 600, 'product_type_id' => $this->type->id,
        'description' => '<p>Opis.</p><strong>Sastav:</strong><p>Staro.</p>',
        'ingredients' => '<p>Novo.</p>',
    ]);

    $this->artisan('meva:label:split')->assertSuccessful();

    $data = Product::query()->findOrFail($product->id)->attribute_data;

    expect((string) $data->get('ingredients'))->toBe('<p>Novo.</p>')
        ->and((string) $data->get('description'))->toBe('<p>Opis.</p><strong>Sastav:</strong><p>Staro.</p>');
});
