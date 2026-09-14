<?php

use Illuminate\Http\Response;
use Lunar\FieldTypes\Text;
use Lunar\Models\Product;
use Lunar\Models\ProductType;

// The Lunar baseline (language, currency, channel, tax class) is seeded for
// every test by TestingDatabaseSeeder; only a product type is test-specific.
beforeEach(function () {
    $this->productType = ProductType::factory()->create(['name' => 'Simple']);
});

it('creates a product with a variant and a price', function () {
    $response = $this->post(url('/api/admin/products'), [
        'name' => 'Meva Hoodie',
        'description' => 'Heavy cotton, unisex.',
        'status' => 'published',
        'product_type_id' => $this->productType->id,
        'variants' => [
            ['sku' => 'HOODIE-M', 'stock' => 12, 'price' => 4999],
        ],
    ], authHeaders());

    $response->assertStatus(Response::HTTP_CREATED);

    $product = Product::query()->with('variants.prices')->sole();

    expect((string) $product->attribute_data->get('name'))->toBe('Meva Hoodie')
        ->and($product->status)->toBe('published')
        ->and($product->variants)->toHaveCount(1)
        ->and($product->variants->first()->sku)->toBe('HOODIE-M')
        ->and($product->variants->first()->prices->first()->price->value)->toBe(4999);
});

it('rejects a product without a name', function () {
    $response = $this->post(url('/api/admin/products'), [
        'product_type_id' => $this->productType->id,
    ], authHeaders());

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

    expect($response->getOriginalContent()['errors']->get('name')[0])
        ->toBe('The name field is required.');
});

it('rejects a variant without a price', function () {
    $response = $this->post(url('/api/admin/products'), [
        'name' => 'No price',
        'product_type_id' => $this->productType->id,
        'variants' => [['sku' => 'X-1']],
    ], authHeaders());

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    expect(Product::query()->count())->toBe(0);
});

it('rolls the whole product back when a variant fails', function () {
    // An unknown currency makes the price insert fail after the product row
    // has already been written.
    $attempt = fn () => $this->post(url('/api/admin/products'), [
        'name' => 'Doomed',
        'product_type_id' => $this->productType->id,
        'variants' => [['sku' => 'D-1', 'price' => 100, 'currency_id' => 999999]],
    ], authHeaders());

    expect($attempt()->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
    expect(Product::query()->count())->toBe(0);
});

it('lists products', function () {
    Product::factory()->count(3)->create(['product_type_id' => $this->productType->id]);

    $response = $this->get(url('/api/admin/products'), authHeaders());

    $response->assertStatus(Response::HTTP_OK);
    expect($response->getOriginalContent())->toHaveCount(3);
});

it('shows a single product with its name flattened', function () {
    $product = Product::factory()->create([
        'product_type_id' => $this->productType->id,
        'attribute_data' => collect(['name' => new Text('Espresso Cup')]),
    ]);

    $response = $this->get(url('/api/admin/products', ['id' => $product->id]), authHeaders());

    $response->assertStatus(Response::HTTP_OK);
    expect(json_decode($response->getContent(), true)['data']['name'])->toBe('Espresso Cup');
});

it('updates a product without losing untouched attributes', function () {
    $product = Product::factory()->create([
        'product_type_id' => $this->productType->id,
        'attribute_data' => collect([
            'name' => new Text('Old name'),
            'description' => new Text('Keep me'),
        ]),
    ]);

    $response = $this->put(
        url('/api/admin/products', ['id' => $product->id]),
        ['name' => 'New name', 'status' => 'published'],
        authHeaders()
    );

    $response->assertStatus(Response::HTTP_OK);

    $product->refresh();

    expect((string) $product->attribute_data->get('name'))->toBe('New name')
        ->and((string) $product->attribute_data->get('description'))->toBe('Keep me')
        ->and($product->status)->toBe('published');
});

it('deletes a product', function () {
    $product = Product::factory()->create(['product_type_id' => $this->productType->id]);

    $this->delete(url('/api/admin/products', ['id' => $product->id]), [], authHeaders());

    expect(Product::query()->count())->toBe(0);
});
