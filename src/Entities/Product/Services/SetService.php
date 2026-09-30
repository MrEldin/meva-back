<?php

namespace Meva\Entities\Product\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Meva\Entities\Catalogue\CatalogueCache;
use Meva\Entities\Catalogue\Money;

/**
 * A set is a product made of other products.
 *
 * Lunar has no bundle type, so the composition lives in product_bundle_items:
 * one row per part, with how many of it the set holds. This is the one place
 * that table is written from the desk -- putting a set together, changing
 * what is in it, and taking it apart again.
 *
 * A set is sold and stocked as one article; the parts say what the buyer gets,
 * they do not move stock of their own.
 */
class SetService
{
    public const TYPE_SET = 'Set';

    public const TYPE_PRODUCT = 'Proizvod';

    public function __construct(protected ProductCreateService $products) {}

    /**
     * Whether the product is a set.
     */
    public static function isSet(Product $product): bool
    {
        return $product->productType?->name === self::TYPE_SET;
    }

    /**
     * What the set is made of, with what the desk needs to show each part.
     *
     * @return Collection<int, array{id: int, name: string, slug: string, price: ?float, image: ?string, quantity: int}>
     */
    public function items(Product $set): Collection
    {
        $rows = DB::table('product_bundle_items')
            ->where('bundle_product_id', $set->id)
            ->orderBy('id')
            ->get()
            ->keyBy('item_product_id');

        if ($rows->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->with(['variants.prices.currency', 'media'])
            ->whereIn('id', $rows->keys())
            ->get()
            ->sortBy(fn (Product $p): int => (int) $rows[$p->id]->id)
            ->map(fn (Product $p): array => [
                'id' => (int) $p->id,
                'name' => (string) $p->attribute_data?->get('name'),
                'slug' => (string) $p->attribute_data?->get('slug'),
                'price' => $this->price($p),
                'image' => $p->getFirstMediaUrl('images') ?: null,
                'quantity' => (int) $rows[$p->id]->quantity,
            ])
            ->values();
    }

    /**
     * Make a new set out of existing products.
     *
     * The price is the sum of the parts unless the desk names one, which it
     * usually does: a set is cheaper than buying its parts one by one.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array{product_id: int, quantity?: int}>  $items
     */
    public function create(array $data, array $items): Product
    {
        $parts = $this->parts($items, exclude: null);

        return DB::transaction(function () use ($data, $items, $parts): Product {
            $set = $this->products->handle([
                ...$data,
                'product_type_id' => $this->type(self::TYPE_SET)->id,
                'price' => $data['price'] ?? $this->sum($parts, $items),
            ]);

            $this->write($set, $items);

            return $set;
        });
    }

    /**
     * Replace what a set is made of. A plain product given parts becomes a set.
     *
     * @param  array<int, array{product_id: int, quantity?: int}>  $items
     */
    public function compose(Product $product, array $items): void
    {
        $this->parts($items, exclude: $product->id);

        DB::transaction(function () use ($product, $items): void {
            if (! self::isSet($product)) {
                $product->product_type_id = $this->type(self::TYPE_SET)->id;
                $product->save();
                $product->unsetRelation('productType');
            }

            $this->write($product, $items);
        });
    }

    /**
     * Take a set apart: it keeps its name, price and photographs, and goes on
     * as an ordinary product; the parts are simply no longer listed under it.
     */
    public function dissolve(Product $set): void
    {
        DB::transaction(function () use ($set): void {
            DB::table('product_bundle_items')->where('bundle_product_id', $set->id)->delete();

            $set->product_type_id = $this->type(self::TYPE_PRODUCT)->id;
            $set->save();
            $set->unsetRelation('productType');
        });

        CatalogueCache::bump();
    }

    /**
     * Write the rows: parts no longer named are dropped, the rest kept or added.
     *
     * @param  array<int, array{product_id: int, quantity?: int}>  $items
     */
    protected function write(Product $set, array $items): void
    {
        $now = now();
        $rows = collect($items)
            ->keyBy(fn (array $item): int => (int) $item['product_id'])
            ->map(fn (array $item, int $id): array => [
                'bundle_product_id' => $set->id,
                'item_product_id' => $id,
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        DB::table('product_bundle_items')
            ->where('bundle_product_id', $set->id)
            ->whereNotIn('item_product_id', $rows->keys())
            ->delete();

        DB::table('product_bundle_items')->upsert(
            $rows->values()->all(),
            ['bundle_product_id', 'item_product_id'],
            ['quantity', 'updated_at'],
        );

        CatalogueCache::bump();
    }

    /**
     * The products the items name, checked: each must exist, none may be a
     * set (a set inside a set is a box inside a box), and a set cannot hold
     * itself.
     *
     * @param  array<int, array{product_id: int, quantity?: int}>  $items
     * @return Collection<int, Product>
     */
    protected function parts(array $items, ?int $exclude): Collection
    {
        $ids = collect($items)->pluck('product_id')->map(fn ($id): int => (int) $id);

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Set mora da ima bar jedan proizvod.']);
        }

        if ($ids->count() !== $ids->unique()->count()) {
            throw ValidationException::withMessages(['items' => 'Isti proizvod je naveden dva puta.']);
        }

        if ($exclude !== null && $ids->contains($exclude)) {
            throw ValidationException::withMessages(['items' => 'Set ne može da sadrži sam sebe.']);
        }

        $parts = Product::query()->with(['productType', 'variants.prices.currency'])->whereIn('id', $ids)->get();

        if ($parts->count() !== $ids->count()) {
            throw ValidationException::withMessages(['items' => 'Neki od izabranih proizvoda ne postoji.']);
        }

        $nested = $parts->first(fn (Product $p): bool => self::isSet($p));

        if ($nested !== null) {
            throw ValidationException::withMessages([
                'items' => '"'.$nested->attribute_data?->get('name').'" je set; set ne može da bude deo drugog seta.',
            ]);
        }

        return $parts;
    }

    /**
     * The parts' prices added up, in dinars, each times its quantity.
     *
     * @param  Collection<int, Product>  $parts
     * @param  array<int, array{product_id: int, quantity?: int}>  $items
     */
    protected function sum(Collection $parts, array $items): float
    {
        $quantities = collect($items)->keyBy('product_id');

        return round($parts->sum(function (Product $p) use ($quantities): float {
            $quantity = max(1, (int) ($quantities[$p->id]['quantity'] ?? 1));

            return ($this->price($p) ?? 0) * $quantity;
        }), 2);
    }

    /**
     * A product's price in dinars, as the desk shows it.
     */
    protected function price(Product $product): ?float
    {
        $variant = $product->variants->first();
        $price = $variant?->prices->first(fn ($p): bool => $p->currency?->code === 'RSD') ?? $variant?->prices->first();
        $minor = Money::minor($price);

        return $minor === null ? null : round($minor / 100, 2);
    }

    protected function type(string $name): ProductType
    {
        return ProductType::query()->firstOrCreate(['name' => $name]);
    }
}
