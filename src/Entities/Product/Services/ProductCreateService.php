<?php

namespace Meva\Entities\Product\Services;

use Illuminate\Support\Facades\DB;
use Lunar\FieldTypes\Text;
use Lunar\Models\Currency;
use Lunar\Models\Product;

/**
 * Creates a Lunar product together with its variants and prices.
 *
 * Lunar keeps editorial fields inside attribute_data as field-type objects and
 * money in minor units on a separate prices table, so a usable "create a
 * product" call spans three models. That assembly lives here rather than in the
 * controller, and runs in one transaction so a failed price cannot leave a
 * product with a variant that has none.
 */
class ProductCreateService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $product = Product::create([
                'product_type_id' => $data['product_type_id'],
                'brand_id' => $data['brand_id'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'attribute_data' => collect(array_filter([
                    'name' => new Text($data['name']),
                    'description' => isset($data['description']) ? new Text($data['description']) : null,
                ])),
            ]);

            foreach ($data['variants'] ?? [] as $variantData) {
                $this->addVariant($product, $variantData);
            }

            return $product->load('variants.prices');
        });
    }

    /**
     * Attach a single variant, with its price, to the product.
     *
     * @param  array<string, mixed>  $data
     */
    protected function addVariant(Product $product, array $data): void
    {
        $variant = $product->variants()->create([
            'sku' => $data['sku'],
            'stock' => $data['stock'] ?? 0,
            'tax_class_id' => \Lunar\Models\TaxClass::getDefault()?->id,
        ]);

        $variant->prices()->create([
            'price' => $data['price'],
            'currency_id' => $data['currency_id'] ?? Currency::getDefault()?->id,
            'min_quantity' => 1,
        ]);
    }
}
