<?php

namespace Database\Seeders\Meva;

use Illuminate\Database\Seeder;
use Lunar\Models\Product;
use Meva\Entities\Catalogue\Models\Review;

/**
 * The reviews copied off the old WooCommerce shop.
 *
 * Verbatim -- nothing here is rewritten or invented. `slug` is filled in only
 * where the product the review names matches a product in this catalogue
 * exactly; "Hidratantna krema", "Serum za lice" and "Nega tela" are not names
 * of anything the shop sells, so those keep their wording in `product_label`
 * and are shown without a picture rather than against the wrong jar.
 *
 * Re-running matches on the customer's name and the opening of what they
 * wrote, so seeding twice does not double them up, and a review edited in the
 * database is not overwritten by this file.
 */
class ReviewSeeder extends Seeder
{
    protected const REVIEWS = [
        [
            'name' => 'Anastasija',
            'slug' => 'sampon-za-kosu-200ml',
            'label' => 'Šampon za kosu',
            'body' => 'U životu nisam koristila bolji šampon za kosu. Sastav mu je odličan. Ne želim nikad više da koristim neki drugi šampon, ovaj je sve što mojoj kosi treba.',
        ],
        [
            'name' => 'Aleksandra',
            'slug' => 'krema-protiv-akni',
            'label' => 'Krema protiv akni',
            'body' => 'Predobra krema za bubuljice, dva dana mazanja i nestaju.',
        ],
        [
            'name' => 'Aleksandra',
            'slug' => 'losion-protiv-akni-dan',
            'label' => 'Losion protiv akni',
            'body' => 'Zaista predobar losion. Jako mi je drago da neko pravi proizvode koji su mnogo dobri i pristupačnih cena.',
        ],
        [
            'name' => 'Amela Kardović',
            'slug' => null,
            'label' => 'Hidratantna krema',
            'body' => 'Otkrila sam ovu čudesnu hidratantnu kremu i moram priznati da sam oduševljena! Nakon samo nekoliko dana korišćenja, moja koža je postala mekša i sjajnija.',
        ],
        [
            'name' => 'Lara Kežman',
            'slug' => null,
            'label' => 'Serum za lice',
            'body' => 'Ovaj organski serum za lice je pravo otkriće! Bogat je antioksidansima i prirodnim uljima koja intenzivno hrane kožu.',
        ],
        [
            'name' => 'Anđela Đokić',
            'slug' => null,
            'label' => 'Nega tela',
            'body' => 'Miris je osvežavajući i dugotrajan, što je veliki plus. Sviđa mi se što su sastojci etički nabavljeni. Definitivno preporučujem svima koji žele prirodnu, ali luksuznu negu tela.',
        ],
    ];

    public function run(): void
    {
        $products = Product::query()
            ->get()
            ->mapWithKeys(fn (Product $product): array => [
                (string) $product->attribute_data?->get('slug') => $product->id,
            ]);

        $written = 0;

        foreach (self::REVIEWS as $row) {
            $review = Review::firstOrNew([
                'name' => $row['name'],
                'body' => $row['body'],
            ]);

            if ($review->exists) {
                continue;
            }

            $review->fill([
                'product_id' => $row['slug'] ? ($products[$row['slug']] ?? null) : null,
                'product_label' => $row['label'],
                'rating' => 5,
                'source' => 'stari-sajt',
                'published_at' => now(),
            ])->save();

            $written++;
        }

        $this->command?->info("Reviews: {$written} added, ".Review::count().' in total');
    }
}
