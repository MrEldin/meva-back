<?php

namespace Meva\Api\V1\Controllers\Shop;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\Product;
use Meva\Api\V1\Controllers\Controller;
use Meva\Api\V1\Transformers\Commerce\ShopCollectionTransformer;
use Meva\Api\V1\Transformers\Commerce\ShopProductTransformer;

/**
 * The public catalogue. No authentication: this is what the storefront reads.
 */
class CatalogController extends Controller
{
    /**
     * Browse published products.
     */
    public function index(Request $request)
    {
        $products = Product::query()
            ->where('status', 'published')
            ->with(['variants.prices.currency', 'collections', 'productType', 'media'])
            ->when($request->filled('kategorija'), function ($query) use ($request): void {
                $slug = $request->string('kategorija');

                $query->whereHas('collections', fn ($q) => $q->whereJsonContains('attribute_data->slug->value', (string) $slug));
            })
            ->when($request->filled('tip'), function ($query) use ($request): void {
                $type = $request->string('tip') === 'set' ? 'Set' : 'Proizvod';

                $query->whereHas('productType', fn ($q) => $q->where('name', $type));
            })
            ->when($request->filled('trazi'), function ($query) use ($request): void {
                $term = '%'.$request->string('trazi').'%';

                $query->where('attribute_data', 'like', $term);
            })
            ->orderBy('id')
            ->paginate($request->integer('per_page', 24));

        return $this->response
            ->paginator($products, new ShopProductTransformer)
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Show one product by its slug.
     */
    public function show(string $slug)
    {
        $product = Product::query()
            ->where('status', 'published')
            ->with(['variants.prices.currency', 'collections', 'productType', 'media'])
            ->get()
            ->first(fn (Product $p): bool => (string) $p->attribute_data?->get('slug') === $slug);

        abort_if($product === null, Response::HTTP_NOT_FOUND, 'Proizvod nije pronađen.');

        return $this->response
            ->item($product, new ShopProductTransformer)
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * List the categories, with how many published products each holds.
     */
    public function collections()
    {
        $collections = LunarCollection::query()
            ->withCount(['products as products_count' => fn ($q) => $q->where('status', 'published')])
            ->get()
            ->filter(fn (LunarCollection $c): bool => $c->products_count > 0)
            ->sortByDesc('products_count')
            ->values();

        return $this->response
            ->collection($collections, new ShopCollectionTransformer)
            ->setStatusCode(Response::HTTP_OK);
    }
}
