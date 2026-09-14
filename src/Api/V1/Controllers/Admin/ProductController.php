<?php

namespace Meva\Api\V1\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Lunar\FieldTypes\Text;
use Lunar\Models\Product;
use Meva\Api\V1\Controllers\Controller;
use Meva\Api\V1\Requests\Product\ProductCreateRequest;
use Meva\Api\V1\Requests\Product\ProductUpdateRequest;
use Meva\Api\V1\Transformers\Commerce\ProductTransformer;
use Meva\Entities\Product\Services\ProductCreateService;

/**
 * Catalogue management for the back-office client.
 *
 * Lunar's own Filament panel is not installed: this is the admin surface, and
 * it is a web service like everything else here.
 */
class ProductController extends Controller
{
    /**
     * List products.
     */
    public function index(Request $request)
    {
        $products = Product::query()
            ->with('variants.prices')
            ->when($request->filled('status'), fn ($query) => $query->status($request->string('status')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25));

        return $this->response
            ->paginator($products, new ProductTransformer)
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Show a single product.
     */
    public function show(int $id)
    {
        $product = Product::query()->with('variants.prices')->findOrFail($id);

        return $this->response
            ->item($product, new ProductTransformer)
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Create a product, with its variants and prices.
     */
    public function create(ProductCreateRequest $request, ProductCreateService $products)
    {
        $product = $products->handle($request->validated());

        return $this->response
            ->item($product, new ProductTransformer)
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update a product's own fields. Variants are managed separately.
     */
    public function update(int $id, ProductUpdateRequest $request)
    {
        $product = Product::query()->findOrFail($id);
        $data = $request->validated();

        $product->fill(array_filter(
            [
                'product_type_id' => $data['product_type_id'] ?? null,
                'brand_id' => $data['brand_id'] ?? null,
                'status' => $data['status'] ?? null,
            ],
            fn ($value): bool => $value !== null
        ));

        if (array_key_exists('name', $data) || array_key_exists('description', $data)) {
            $product->attribute_data = $this->mergedAttributes($product, $data);
        }

        $product->save();

        return $this->response
            ->item($product->load('variants.prices'), new ProductTransformer)
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Delete a product.
     */
    public function destroy(int $id)
    {
        Product::query()->findOrFail($id)->delete();

        return $this->response->noContent()->setStatusCode(Response::HTTP_NO_CONTENT);
    }

    /**
     * Merge incoming editorial fields into the product's existing attribute data.
     *
     * Assigning a fresh collection would silently drop any attribute the client
     * did not send.
     *
     * @param  array<string, mixed>  $data
     */
    protected function mergedAttributes(Product $product, array $data): \Illuminate\Support\Collection
    {
        $attributes = $product->attribute_data ?? collect();

        foreach (['name', 'description'] as $handle) {
            if (array_key_exists($handle, $data) && $data[$handle] !== null) {
                $attributes->put($handle, new Text($data[$handle]));
            }
        }

        return $attributes;
    }
}
