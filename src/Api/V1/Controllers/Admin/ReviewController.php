<?php

namespace Meva\Api\V1\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Lunar\Models\Product;
use Meva\Api\V1\Controllers\Controller;
use Meva\Entities\Catalogue\CatalogueCache;
use Meva\Entities\Catalogue\Models\Review;

/**
 * The reviews desk.
 *
 * Reviews are the one thing on the front page written by someone other than
 * the shop, so they are edited here rather than in a file: attached to the
 * product they were left on, published or held back, and added as they come
 * in over the phone or in messages.
 */
class ReviewController extends Controller
{
    /**
     * Every review, newest first, published or not.
     */
    public function index(Request $request)
    {
        $reviews = Review::query()
            ->with('product')
            ->latest('id')
            ->paginate(min((int) $request->input('per_page', 50), 200));

        return $this->response->array([
            'data' => $reviews->map(fn (Review $review): array => $this->present($review))->all(),
            'meta' => [
                'total' => $reviews->total(),
                'per_page' => $reviews->perPage(),
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
            ],
        ])->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Add one.
     */
    public function store(Request $request)
    {
        $review = Review::create($this->validated($request) + ['source' => 'admin']);

        CatalogueCache::bump();

        return $this->response->array(['data' => $this->present($review->fresh('product'))])
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Change one.
     */
    public function update(Request $request, int $id)
    {
        $review = Review::findOrFail($id);
        $review->update($this->validated($request));

        CatalogueCache::bump();

        return $this->response->array(['data' => $this->present($review->fresh('product'))])
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Remove one.
     */
    public function destroy(int $id)
    {
        Review::findOrFail($id)->delete();

        CatalogueCache::bump();

        return $this->response->array(['data' => ['deleted' => true]])
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * The catalogue, as a list to attach a review to.
     */
    public function products()
    {
        $products = Product::query()
            ->where('status', 'published')
            ->get()
            ->map(fn (Product $product): array => [
                'id' => (int) $product->id,
                'name' => (string) $product->attribute_data?->get('name'),
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return $this->response->array(['data' => $products->all()])
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * What may be written, and what "published" means on the wire.
     *
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'body' => 'required|string|max:2000',
            'rating' => 'required|integer|min:1|max:5',
            'product_id' => 'nullable|integer|exists:lunar_products,id',
            'product_label' => 'nullable|string|max:120',
            'published' => 'required|boolean',
        ]);

        $published = (bool) $data['published'];
        unset($data['published']);

        // Holding a review back and putting it out again should not move the
        // date it carries, so an existing one keeps what it had.
        $data['published_at'] = $published
            ? ($request->input('published_at') ? now()->parse($request->input('published_at')) : now())
            : null;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(Review $review): array
    {
        return [
            'id' => (int) $review->id,
            'name' => $review->name,
            'body' => $review->body,
            'rating' => (int) $review->rating,
            'product_id' => $review->product_id,
            'product_name' => $review->product
                ? (string) $review->product->attribute_data?->get('name')
                : null,
            'product_label' => $review->product_label,
            'published' => $review->published_at !== null,
            'published_at' => $review->published_at?->toIso8601String(),
            'source' => $review->source,
            'created_at' => $review->created_at?->toIso8601String(),
        ];
    }
}
