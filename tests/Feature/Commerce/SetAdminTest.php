<?php

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Meva\Entities\Product\Services\ProductCreateService;

// Two plain products to build sets from, at 1000 and 2500 dinars.
beforeEach(function () {
    $this->plain = ProductType::firstOrCreate(['name' => 'Proizvod']);
    $create = app(ProductCreateService::class);

    $this->cream = $create->handle(['name' => 'Krema', 'price' => 1000, 'product_type_id' => $this->plain->id, 'status' => 'published']);
    $this->lotion = $create->handle(['name' => 'Losion', 'price' => 2500, 'product_type_id' => $this->plain->id, 'status' => 'published']);
});

function makeSet(array $overrides = []): \Illuminate\Testing\TestResponse
{
    return test()->post(url('/api/admin/products/sets'), array_merge([
        'name' => 'Set protiv flekica',
        'items' => [
            ['product_id' => test()->cream->id, 'quantity' => 2],
            ['product_id' => test()->lotion->id],
        ],
    ], $overrides), authHeaders());
}

it('creates a set out of products, priced as the sum of its parts', function () {
    $response = makeSet();

    $response->assertStatus(Response::HTTP_CREATED);

    $set = Product::query()->with(['productType', 'variants.prices'])->findOrFail($response->json('data.id'));

    expect($set->productType->name)->toBe('Set')
        ->and($response->json('data.is_set'))->toBeTrue()
        ->and($set->variants->first()->prices->first()->price->value)->toBe(450000)
        ->and(DB::table('product_bundle_items')->where('bundle_product_id', $set->id)->pluck('quantity', 'item_product_id')->all())
        ->toBe([$this->cream->id => 2, $this->lotion->id => 1]);
});

it('takes the price the desk names over the sum', function () {
    $response = makeSet(['price' => 3990]);

    $set = Product::query()->with('variants.prices')->findOrFail($response->json('data.id'));

    expect($set->variants->first()->prices->first()->price->value)->toBe(399000);
});

it('lists what a set is made of', function () {
    $id = makeSet()->json('data.id');

    $response = $this->get(url("/api/admin/products/{$id}/set"), authHeaders());

    $response->assertStatus(Response::HTTP_OK);
    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('data.0.name'))->toBe('Krema')
        ->and($response->json('data.0.quantity'))->toBe(2)
        ->and($response->json('data.0.price'))->toBe(1000);
});

it('refuses a set inside a set', function () {
    $inner = makeSet()->json('data.id');

    $response = makeSet(['name' => 'Kutija u kutiji', 'items' => [['product_id' => $inner]]]);

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    expect(DB::table('product_bundle_items')->count())->toBe(2);
});

it('refuses a set with no parts', function () {
    makeSet(['items' => []])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
});

it('changes what a set is made of', function () {
    $id = makeSet()->json('data.id');

    $response = $this->put(url("/api/admin/products/{$id}/set"), [
        'items' => [['product_id' => $this->lotion->id, 'quantity' => 3]],
    ], authHeaders());

    $response->assertStatus(Response::HTTP_OK);
    expect(DB::table('product_bundle_items')->where('bundle_product_id', $id)->pluck('quantity', 'item_product_id')->all())
        ->toBe([$this->lotion->id => 3]);
});

it('will not let a set hold itself', function () {
    $id = makeSet()->json('data.id');

    $this->put(url("/api/admin/products/{$id}/set"), [
        'items' => [['product_id' => $id]],
    ], authHeaders())->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
});

it('turns a plain product into a set when given parts', function () {
    $this->put(url("/api/admin/products/{$this->cream->id}/set"), [
        'items' => [['product_id' => $this->lotion->id]],
    ], authHeaders())->assertStatus(Response::HTTP_OK);

    expect($this->cream->fresh()->productType->name)->toBe('Set');
});

it('takes a set apart into an ordinary product', function () {
    $id = makeSet()->json('data.id');

    $response = $this->delete(url("/api/admin/products/{$id}/set"), [], authHeaders());

    $response->assertStatus(Response::HTTP_OK);
    expect($response->json('data.is_set'))->toBeFalse()
        ->and(Product::query()->with('productType')->findOrFail($id)->productType->name)->toBe('Proizvod')
        ->and(DB::table('product_bundle_items')->where('bundle_product_id', $id)->count())->toBe(0)
        ->and(Product::query()->count())->toBe(3);
});

it('will not take apart a product that is not a set', function () {
    $this->delete(url("/api/admin/products/{$this->cream->id}/set"), [], authHeaders())
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
});

it('filters and sorts the listing for the desk', function () {
    $set = makeSet(['price' => 100])->json('data.id');

    $only = fn (array $params) => collect($this->get(url('/api/admin/products?'.http_build_query($params)), authHeaders())->json('data'))->pluck('id')->all();

    expect($only(['type' => 'set']))->toBe([$set])
        ->and($only(['type' => 'product']))->toEqualCanonicalizing([$this->cream->id, $this->lotion->id])
        ->and($only(['image' => 'none']))->toHaveCount(3)
        ->and($only(['image' => 'cutout']))->toBe([])
        ->and($only(['sort' => 'price_asc']))->toBe([$set, $this->cream->id, $this->lotion->id])
        ->and($only(['sort' => 'price_desc']))->toBe([$this->lotion->id, $this->cream->id, $set])
        ->and($only(['sort' => 'name']))->toBe([$this->cream->id, $this->lotion->id, $set]);
});
