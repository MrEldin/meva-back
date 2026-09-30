<?php

namespace Meva\Api\V1\Controllers\Admin;

use Illuminate\Http\Response;
use Lunar\Models\Product;
use Meva\Api\V1\Controllers\Controller;
use Meva\Api\V1\Requests\Product\SetCreateRequest;
use Meva\Api\V1\Requests\Product\SetItemsRequest;
use Meva\Api\V1\Transformers\Commerce\ProductTransformer;
use Meva\Entities\Product\Services\SetService;

/**
 * Sets, for the desk: put one together out of products, change what is in
 * it, take it apart. The set itself is a product and is edited as one.
 */
class SetController extends Controller
{
    public function __construct(protected SetService $sets) {}

    /**
     * Make a set out of existing products.
     */
    public function create(SetCreateRequest $request)
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        $set = $this->sets->create($data, $items);

        return $this->response
            ->item($set->load(['productType', 'collections']), new ProductTransformer)
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * What a set is made of.
     */
    public function show(int $id)
    {
        $product = Product::query()->with('productType')->findOrFail($id);

        return $this->response->array([
            'data' => $this->sets->items($product)->all(),
        ])->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Change what a set is made of. A plain product given parts becomes a set.
     */
    public function update(int $id, SetItemsRequest $request)
    {
        $product = Product::query()->with('productType')->findOrFail($id);

        $this->sets->compose($product, $request->validated()['items']);

        return $this->response->array([
            'data' => $this->sets->items($product)->all(),
        ])->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Take a set apart: it goes on as an ordinary product.
     */
    public function destroy(int $id)
    {
        $product = Product::query()->with('productType')->findOrFail($id);

        abort_unless(SetService::isSet($product), Response::HTTP_UNPROCESSABLE_ENTITY, 'Ovaj proizvod nije set.');

        $this->sets->dissolve($product);

        return $this->response
            ->item($product->load(['productType', 'variants.prices.currency']), new ProductTransformer)
            ->setStatusCode(Response::HTTP_OK);
    }
}
