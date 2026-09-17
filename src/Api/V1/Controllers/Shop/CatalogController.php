<?php

namespace Meva\Api\V1\Controllers\Shop;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\Product;
use Meva\Api\V1\Controllers\Controller;
use Meva\Api\V1\Transformers\Commerce\ReviewTransformer;
use Meva\Api\V1\Transformers\Commerce\ShopCollectionTransformer;
use Meva\Api\V1\Transformers\Commerce\ShopProductTransformer;
use Meva\Entities\Catalogue\CatalogueCache;
use Meva\Entities\Catalogue\Models\Review;

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
        $key = 'products:'.md5(json_encode($request->only(['kategorija', 'tip', 'trazi', 'per_page', 'page'])));

        return $this->cached($key, fn () => $this->browse($request));
    }

    /**
     * The query behind the listing.
     */
    protected function browse(Request $request)
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
        return $this->cached('product:'.$slug.':'.request()->input('include', ''), fn () => $this->product($slug));
    }

    /**
     * The query behind a single product.
     */
    protected function product(string $slug)
    {
        // Matched in the database rather than by loading the whole catalogue
        // and searching it in PHP.
        $product = Product::query()
            ->where('status', 'published')
            ->whereRaw("attribute_data->'slug'->>'value' = ?", [$slug])
            ->with(['variants.prices.currency', 'collections', 'productType', 'media'])
            ->first();

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
        return $this->cached('collections', fn () => $this->categories());
    }

    /**
     * The query behind the category list.
     */
    protected function categories()
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

    /**
     * What customers wrote.
     *
     * The ones attached to a product come first: a review means more beside
     * the jar it was left on, and those are the ones a reader can act on.
     */
    public function reviews(Request $request)
    {
        $limit = min($request->integer('per_page', 12), 50);

        return $this->cached('reviews:'.$limit, fn () => $this->response
            ->collection(
                Review::query()
                    ->published()
                    ->with(['product.media'])
                    ->orderByRaw('(product_id is null) asc')
                    ->orderByDesc('published_at')
                    ->orderByDesc('id')
                    ->limit($limit)
                    ->get(),
                new ReviewTransformer
            )
            ->setStatusCode(Response::HTTP_OK));
    }

    /**
     * Answer from the catalogue cache, and let the browser hold it briefly too.
     *
     * Dingo responses are built from Eloquent models, which do not survive a
     * round trip through the cache, so what is stored is the rendered body.
     */
    protected function cached(string $key, callable $build)
    {
        $payload = CatalogueCache::remember($key, function () use ($build): string {
            $response = $build();

            return $response instanceof \Dingo\Api\Http\Response
                ? $response->morph()->getContent()
                : json_encode($response);
        });

        return response($payload, Response::HTTP_OK)
            ->header('Content-Type', 'application/json')
            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=600');
    }
}
