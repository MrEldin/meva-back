<?php

use Illuminate\Http\Response;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Meva\Entities\Catalogue\Ingredients;
use Meva\Entities\Product\Services\ProductCreateService;

beforeEach(function () {
    $this->type = ProductType::firstOrCreate(['name' => 'Proizvod']);
});

it('keeps the ingredients as rows and the directions as a field', function () {
    $product = app(ProductCreateService::class)->handle([
        'name' => 'Šampon', 'slug' => 'sampon', 'price' => 700, 'status' => 'published', 'product_type_id' => $this->type->id,
    ]);

    $this->put(url("/api/admin/products/{$product->id}"), [
        'ingredients' => [['inci' => 'Aqua', 'name' => 'Voda'], ['inci' => 'Glycerin', 'name' => 'Glicerin'], ['inci' => '', 'name' => '']],
        'usage' => '<p>Naneti na mokru kosu.</p>',
    ], authHeaders())->assertStatus(Response::HTTP_OK);

    $desk = $this->get(url("/api/admin/products/{$product->id}"), authHeaders())->json('data');
    $shop = $this->get(url('/api/shop/products/sampon?include=description'))->json('data.description');
    $shop = $shop['data'] ?? $shop;

    expect($desk['ingredients'])->toBe([['inci' => 'Aqua', 'name' => 'Voda'], ['inci' => 'Glycerin', 'name' => 'Glicerin']])
        ->and($desk['usage'])->toBe('<p>Naneti na mokru kosu.</p>')
        ->and($shop['ingredients'])->toBe([['title' => null, 'items' => [['inci' => 'Aqua', 'name' => 'Voda'], ['inci' => 'Glycerin', 'name' => 'Glicerin']]]])
        ->and($shop['usage'])->toBe('<p>Naneti na mokru kosu.</p>');

    // Reordered and shortened: the rows follow.
    $this->put(url("/api/admin/products/{$product->id}"), [
        'ingredients' => [['inci' => 'Glycerin', 'name' => 'Glicerin']],
    ], authHeaders())->assertStatus(Response::HTTP_OK);

    expect(Ingredients::of($product->id)->all())->toBe([['inci' => 'Glycerin', 'name' => 'Glicerin']]);
});

it('shows a set the ingredients of its parts, grouped by part', function () {
    $create = app(ProductCreateService::class);
    $cream = $create->handle(['name' => 'Krema', 'price' => 600, 'status' => 'published', 'product_type_id' => $this->type->id, 'ingredients' => [['inci' => 'Aqua', 'name' => 'Voda']]]);
    $tea = $create->handle(['name' => 'Čaj', 'price' => 800, 'status' => 'published', 'product_type_id' => $this->type->id]);

    $this->post(url('/api/admin/products/sets'), [
        'name' => 'Set', 'slug' => 'set', 'status' => 'published',
        'items' => [['product_id' => $cream->id], ['product_id' => $tea->id]],
    ], authHeaders())->assertStatus(Response::HTTP_CREATED);

    $shop = $this->get(url('/api/shop/products/set?include=description'))->json('data.description');
    $shop = $shop['data'] ?? $shop;

    // The tea has none, so only the cream's group is there.
    expect($shop['ingredients'])->toBe([['title' => 'Krema', 'items' => [['inci' => 'Aqua', 'name' => 'Voda']]]]);
});

it('moves Sastav and Način upotrebe out of the old descriptions', function () {
    $product = app(ProductCreateService::class)->handle([
        'name' => 'Šampon', 'price' => 700, 'product_type_id' => $this->type->id,
        'description' => '<p>Savršen izbor.</p><strong>Sastav:</strong><ul><li>Aqua (Voda)</li><li>Cetearyl Alcohol</li></ul><strong>Način upotrebe:</strong><p>Naneti.</p>',
        'short_description' => "Savršen izbor.\n\nSastav:\n\n - Aqua\n\nNačin upotrebe: Naneti.",
    ]);

    $this->artisan('meva:label:split')->assertSuccessful();

    $data = Product::query()->findOrFail($product->id)->attribute_data;

    expect((string) $data->get('description'))->toBe('<p>Savršen izbor.</p>')
        ->and((string) $data->get('short_description'))->toBe('Savršen izbor.')
        ->and(Ingredients::of($product->id)->all())->toBe([['inci' => 'Aqua', 'name' => 'Voda'], ['inci' => 'Cetearyl Alcohol', 'name' => '']])
        ->and((string) $data->get('usage'))->toBe('<p>Naneti.</p>');

    $this->artisan('meva:label:split')->expectsOutputToContain('0 changed')->assertSuccessful();
});

it('reads an old ingredient list into rows', function () {
    expect(Ingredients::parse('<ul><li>Aqua (Voda)</li><li>Olea Europaea (Maslinovo ulje)</li><li>/</li></ul>'))
        ->toBe([['inci' => 'Aqua', 'name' => 'Voda'], ['inci' => 'Olea Europaea', 'name' => 'Maslinovo ulje']])
        ->and(Ingredients::parse('<p>Cocamidopropyl Betaine, Aqua (Voda), Glycerol (Glicerin)</p>'))
        ->toBe([['inci' => 'Cocamidopropyl Betaine', 'name' => ''], ['inci' => 'Aqua', 'name' => 'Voda'], ['inci' => 'Glycerol', 'name' => 'Glicerin']]);
});

it('finds the ingredients under "Sastojci" too, even when the directions were already moved', function () {
    $product = app(ProductCreateService::class)->handle([
        'name' => 'Čaj', 'price' => 800, 'product_type_id' => $this->type->id,
        'description' => 'Biljna mešavina. <strong>Sastojci:</strong> <ul><li>Cynara Scolymus (Artičoka),</li><li>Melissa Officinalis (Matičnjak).</li></ul>',
        'usage' => '<p>Piti dva puta dnevno.</p>',
    ]);

    $this->artisan('meva:label:split')->assertSuccessful();

    $data = Product::query()->findOrFail($product->id)->attribute_data;

    expect(Ingredients::of($product->id)->all())->toBe([['inci' => 'Cynara Scolymus', 'name' => 'Artičoka'], ['inci' => 'Melissa Officinalis', 'name' => 'Matičnjak']])
        ->and((string) $data->get('description'))->toBe('Biljna mešavina.')
        ->and((string) $data->get('usage'))->toBe('<p>Piti dva puta dnevno.</p>');
});
