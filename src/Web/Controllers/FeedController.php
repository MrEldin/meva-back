<?php

namespace Meva\Web\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Lunar\Models\Product;

/**
 * The product feed Meta and Google read to build a shopping catalogue.
 *
 * One file keeps Instagram shopping, Facebook advert catalogues and Google
 * Merchant in step with the shop, without anyone re-typing prices.
 */
class FeedController extends Controller
{
    public function products()
    {
        $storefront = rtrim(config('meva.storefront_url'), '/');

        $items = Product::query()
            ->where('status', 'published')
            ->with(['variants.prices', 'media', 'collections'])
            ->get()
            ->map(function ($product) use ($storefront): ?array {
                $slug = (string) $product->attribute_data?->get('slug');
                $variant = $product->variants->first();
                $price = $variant?->prices->firstWhere('currency.code', 'RSD') ?? $variant?->prices->first();

                if ($slug === '' || $price === null) {
                    return null;
                }

                $description = trim(Str::of((string) $product->attribute_data?->get('description'))->stripTags()->squish());

                return [
                    'id' => $variant?->sku ?: 'MEVA-'.$product->id,
                    'title' => (string) $product->attribute_data?->get('name'),
                    'description' => Str::limit($description !== '' ? $description : (string) $product->attribute_data?->get('name'), 4800),
                    'link' => $storefront.'/proizvod/'.$slug,
                    'image' => $product->getFirstMediaUrl('images'),
                    'price' => number_format(((int) \Meva\Entities\Catalogue\Money::minor($price)) / 100, 2, '.', '').' RSD',
                    'category' => $product->collections->first()?->attribute_data?->get('name'),
                ];
            })
            ->filter()
            ->values();

        return response()
            ->view('feed.products', ['items' => $items, 'storefront' => $storefront])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
