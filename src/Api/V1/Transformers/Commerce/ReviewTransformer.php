<?php

namespace Meva\Api\V1\Transformers\Commerce;

use Meva\Entities\Catalogue\Models\Review;
use PHPOpenSourceSaver\Fractal\TransformerAbstract;

/**
 * A review as the storefront shows it: the words, who wrote them, and -- when
 * the review is attached to a product -- enough of that product to draw it
 * beside them and link to it.
 */
class ReviewTransformer extends TransformerAbstract
{
    public function transform(Review $review): array
    {
        $product = $review->product;

        return [
            'id' => (int) $review->id,
            'name' => (string) $review->name,
            'rating' => (int) $review->rating,
            'body' => (string) $review->body,
            'published_at' => $review->published_at?->toDateString(),
            'product' => $product === null ? null : [
                'name' => (string) $product->attribute_data?->get('name'),
                'slug' => (string) $product->attribute_data?->get('slug'),
                'image' => $this->cutout($product),
                'has_cutout' => $this->cutout($product) !== null,
            ],
            // What the review named, even when that is not a product here.
            'product_label' => $review->product_label,
        ];
    }

    /**
     * The product's cutout at listing size, or nothing when it has none.
     */
    protected function cutout($product): ?string
    {
        $media = $product->getMedia('images')
            ->first(fn ($item): bool => (bool) $item->getCustomProperty('cutout'));

        if ($media === null) {
            return null;
        }

        return $media->hasGeneratedConversion('cutout-sm')
            ? $media->getFullUrl('cutout-sm')
            : $media->getFullUrl();
    }
}
