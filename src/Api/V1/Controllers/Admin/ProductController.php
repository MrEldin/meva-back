<?php

namespace Meva\Api\V1\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use Lunar\FieldTypes\Text;
use Lunar\Models\Currency;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Meva\Api\V1\Controllers\Controller;
use Meva\Entities\Catalogue\CatalogueCache;
use Meva\Entities\Catalogue\Ingredients;
use Meva\Entities\Catalogue\Transparency;
use Meva\Api\V1\Requests\Product\ProductCreateRequest;
use Meva\Api\V1\Requests\Product\ProductUpdateRequest;
use Meva\Api\V1\Transformers\Commerce\ProductTransformer;
use Meva\Entities\Product\Services\ProductCreateService;
use Meva\Entities\Product\Services\SetService;

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
            ->with(['variants.prices.currency', 'media', 'productType', 'collections'])
            ->when($request->filled('status'), fn ($query) => $query->status($request->string('status')))
            ->when($request->filled('q'), fn ($query) => $query->whereRaw(
                "attribute_data->'name'->>'value' ilike ?",
                ['%'.trim($request->string('q')).'%']
            ))
            // Sets or single products.
            ->when(in_array($request->input('type'), ['set', 'product'], true), fn ($query) => $query->whereHas(
                'productType',
                fn ($q) => $q->where('name', $request->input('type') === 'set' ? SetService::TYPE_SET : SetService::TYPE_PRODUCT),
            ))
            // One shelf.
            ->when($request->filled('category'), fn ($query) => $query->whereHas(
                'collections',
                fn ($q) => $q->whereJsonContains('attribute_data->slug->value', $request->string('category')->toString()),
            ))
            // What the photographs are like: none at all, none lifted off its
            // background, or at least one that is.
            ->when($request->input('image') === 'none', fn ($query) => $query->whereDoesntHave('media', fn ($q) => $q->where('collection_name', 'images')))
            ->when($request->input('image') === 'cutout', fn ($query) => $query->whereHas('media', fn ($q) => $q->where('collection_name', 'images')->where('custom_properties->cutout', true)))
            ->when($request->input('image') === 'no-cutout', fn ($query) => $query
                ->whereHas('media', fn ($q) => $q->where('collection_name', 'images'))
                ->whereDoesntHave('media', fn ($q) => $q->where('collection_name', 'images')->where('custom_properties->cutout', true)))
            ->tap(fn ($query) => $this->sort($query, (string) $request->input('sort', 'newest')))
            ->paginate($request->integer('per_page', 25));

        // Built by hand rather than through Dingo's paginator: Fractal costs
        // about twenty milliseconds an item here, which on a page of
        // twenty five is most of the request.
        $transformer = new ProductTransformer;

        return $this->response->array([
            'data' => collect($products->items())->map(fn ($product): array => $transformer->transform($product))->all(),
            'meta' => [
                'pagination' => [
                    'total' => $products->total(),
                    'count' => $products->count(),
                    'per_page' => $products->perPage(),
                    'current_page' => $products->currentPage(),
                    'total_pages' => $products->lastPage(),
                ],
            ],
        ])->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Order the listing: newest first unless the desk asks for the name or
     * the price, which lives two tables away on the first variant.
     */
    protected function sort(\Illuminate\Database\Eloquent\Builder $query, string $sort): void
    {
        $price = DB::table('lunar_prices')
            ->join('lunar_product_variants', 'lunar_product_variants.id', '=', 'lunar_prices.priceable_id')
            ->whereColumn('lunar_product_variants.product_id', 'lunar_products.id')
            ->where('lunar_prices.priceable_type', (new \Lunar\Models\ProductVariant)->getMorphClass())
            ->selectRaw('min(lunar_prices.price)');

        match ($sort) {
            'name' => $query->orderBy('attribute_data->name->value'),
            'price_asc' => $query->orderBy($price, 'asc')->orderByDesc('id'),
            'price_desc' => $query->orderBy($price, 'desc')->orderByDesc('id'),
            'oldest' => $query->orderBy('id'),
            default => $query->orderByDesc('id'),
        };
    }

    /**
     * Show a single product.
     */
    public function show(int $id)
    {
        $product = Product::query()->with(['variants.prices.currency', 'media', 'productType', 'collections'])->findOrFail($id);

        return $this->response
            ->item($product, new ProductTransformer)
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Every shelf a product can be put on, whether or not anything is on it yet.
     */
    public function categories()
    {
        $collections = \Lunar\Models\Collection::query()->withCount('products')->get()
            ->map(fn ($c): array => [
                'id' => (int) $c->id,
                'slug' => (string) $c->attribute_data?->get('slug'),
                'name' => (string) $c->attribute_data?->get('name'),
                'products_count' => (int) $c->products_count,
            ])
            ->sortBy('name')
            ->values();

        return $this->response->array(['data' => $collections->all()])->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Create a product, with its variants and prices.
     */
    public function create(ProductCreateRequest $request, ProductCreateService $products)
    {
        $data = $request->validated();
        $product = $products->handle($data);

        if (array_key_exists('categories', $data)) {
            $this->shelve($product, $data['categories']);
        }

        return $this->response
            ->item($product->load(['productType', 'collections']), new ProductTransformer)
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Put the product on exactly these shelves.
     *
     * @param  array<int, int>  $categoryIds
     */
    protected function shelve(Product $product, array $categoryIds): void
    {
        $product->collections()->sync($categoryIds);
        $product->unsetRelation('collections');
        CatalogueCache::bump();
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

        $product->attribute_data = $this->mergedAttributes($product, $data);
        $product->save();

        if (array_key_exists('price', $data)) {
            $this->setPrice($product, (float) $data['price']);
        }

        if (array_key_exists('categories', $data)) {
            $this->shelve($product, $data['categories']);
        }

        if (array_key_exists('ingredients', $data)) {
            Ingredients::replace($product->id, $data['ingredients'] ?? []);
        }

        return $this->response
            ->item($product->load(['variants.prices', 'productType', 'collections']), new ProductTransformer)
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Put a price on the product's first variant, in dinars.
     *
     * The shop sells one size per product, so there is a single price to keep;
     * anything more elaborate belongs in a variants endpoint of its own.
     */
    protected function setPrice(Product $product, float $dinars): void
    {
        $variant = $product->variants()->first();

        if ($variant === null) {
            return;
        }

        $currency = Currency::where('code', 'RSD')->first() ?? Currency::getDefault();
        $minor = (int) round($dinars * 100);

        $price = $variant->prices()->where('currency_id', $currency->id)->first();

        if ($price === null) {
            Price::create([
                'price' => $minor,
                'currency_id' => $currency->id,
                'priceable_type' => $variant->getMorphClass(),
                'priceable_id' => $variant->id,
                'min_quantity' => 1,
            ]);

            return;
        }

        $price->update(['price' => $minor]);
    }

    /**
     * Add photographs.
     *
     * A product has a gallery, not a picture: the cutout it leads with and
     * the studio photographs behind it. This used to clear the collection and
     * keep only the file just sent, which meant nobody could tell whether the
     * desk took one image or several -- it took one, and destroyed the rest.
     * It appends now, and the first image is still the one the storefront,
     * the share card and the advert feed lead with.
     *
     * A file with a see-through background is a cutout, and is marked as one
     * here, before the conversions are made: only a marked cutout gets the
     * transparent sizes, and the storefront leads with it. Unmarked, it would
     * be painted white like a photograph.
     */
    public function uploadImage(Request $request, int $id)
    {
        $request->validate([
            'images' => ['required', 'array', 'max:10'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $product = Product::query()->findOrFail($id);
        $first = $product->getMedia('images')->isEmpty();

        foreach ($request->file('images') as $file) {
            $cutout = Transparency::has($file->getRealPath(), $file->getMimeType());

            $product->addMedia($file)
                ->withCustomProperties(['primary' => $first, 'cutout' => $cutout])
                ->toMediaCollection('images');

            $first = false;
        }

        CatalogueCache::bump();

        return $this->gallery($product->refresh());
    }

    /**
     * Every photograph a product has.
     */
    public function images(int $id)
    {
        return $this->gallery(Product::query()->findOrFail($id));
    }

    /**
     * Remove one photograph.
     */
    public function deleteImage(int $id, int $mediaId)
    {
        $product = Product::query()->findOrFail($id);
        $media = $product->getMedia('images')->firstWhere('id', $mediaId);

        abort_if($media === null, Response::HTTP_NOT_FOUND, 'Slika nije pronađena.');

        $wasPrimary = (bool) $media->getCustomProperty('primary');
        $media->delete();

        // Something has to lead the gallery, so the next one does.
        if ($wasPrimary) {
            $next = $product->refresh()->getMedia('images')->first();
            $next?->setCustomProperty('primary', true)->save();
        }

        CatalogueCache::bump();

        return $this->gallery($product->refresh());
    }

    /**
     * Choose which photograph the product leads with.
     */
    public function primaryImage(int $id, int $mediaId)
    {
        $product = Product::query()->findOrFail($id);
        $media = $product->getMedia('images');

        abort_if($media->firstWhere('id', $mediaId) === null, Response::HTTP_NOT_FOUND, 'Slika nije pronađena.');

        foreach ($media as $item) {
            $item->setCustomProperty('primary', $item->id === $mediaId)->save();
        }

        CatalogueCache::bump();

        return $this->gallery($product->refresh());
    }

    /**
     * A small version of a photograph for the desk, or the original.
     *
     * Which conversions a piece of media has depends on when it was uploaded
     * and whether it is a cutout, and asking for one it does not have throws
     * rather than returning nothing -- which took the whole gallery down with
     * a 500 and left the editor looking as though the product had no images
     * at all. So: ask for the small ones in turn, and fall back to the file
     * itself, which always exists.
     */
    protected function thumbnail($media): string
    {
        foreach (['cutout-sm', 'medium', 'small'] as $conversion) {
            try {
                if ($media->hasGeneratedConversion($conversion)) {
                    return $media->getFullUrl($conversion);
                }
            } catch (\Throwable) {
                // This one is not registered for this media; try the next.
            }
        }

        return $media->getFullUrl();
    }

    /**
     * Every photograph a product has, in the order it is shown.
     */
    protected function gallery(Product $product)
    {
        return $this->response->array([
            'data' => $product->getMedia('images')
                ->sortByDesc(fn ($media): bool => (bool) $media->getCustomProperty('primary'))
                ->map(fn ($media): array => [
                    'id' => (int) $media->id,
                    'url' => $this->thumbnail($media),
                    'full' => $media->getFullUrl(),
                    'name' => $media->file_name,
                    'size' => (int) $media->size,
                    'primary' => (bool) $media->getCustomProperty('primary'),
                    'cutout' => (bool) $media->getCustomProperty('cutout'),
                ])
                ->values()
                ->all(),
        ])->setStatusCode(Response::HTTP_OK);
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

        foreach (['name', 'slug', 'description', 'short_description', 'usage'] as $handle) {
            if (array_key_exists($handle, $data) && $data[$handle] !== null) {
                $attributes->put($handle, new Text($data[$handle]));
            }
        }

        return $attributes;
    }
}
