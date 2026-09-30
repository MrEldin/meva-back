<?php

namespace Meva\Entities\Catalogue;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Product;

/**
 * What a product is made of, one row per ingredient, in label order.
 *
 * An ingredient has the INCI name the label must carry and the Serbian
 * name a customer reads ("Aqua", "Voda"). The rows are the product's own;
 * a set has none, and shows its parts' instead.
 */
class Ingredients
{
    public const TABLE = 'product_ingredients';

    /**
     * The product's ingredients, in order.
     *
     * @return Collection<int, array{inci: string, name: string}>
     */
    public static function of(int $productId): Collection
    {
        return DB::table(self::TABLE)
            ->where('product_id', $productId)
            ->orderBy('position')
            ->get()
            ->map(fn ($row): array => ['inci' => (string) $row->inci, 'name' => (string) $row->name])
            ->values();
    }

    /**
     * Replace the product's ingredients with these, in this order.
     *
     * @param  array<int, array{inci?: ?string, name?: ?string}>  $rows
     */
    public static function replace(int $productId, array $rows): void
    {
        $now = now();
        $clean = collect($rows)
            ->map(fn (array $row): array => ['inci' => trim((string) ($row['inci'] ?? '')), 'name' => trim((string) ($row['name'] ?? ''))])
            ->filter(fn (array $row): bool => $row['inci'] !== '' || $row['name'] !== '')
            ->values();

        DB::transaction(function () use ($productId, $clean, $now): void {
            DB::table(self::TABLE)->where('product_id', $productId)->delete();

            if ($clean->isNotEmpty()) {
                DB::table(self::TABLE)->insert($clean->map(fn (array $row, int $i): array => [
                    'product_id' => $productId,
                    'position' => $i + 1,
                    'inci' => $row['inci'],
                    'name' => $row['name'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            }
        });

        CatalogueCache::bump();
    }

    /**
     * The ingredients as the storefront shows them: one group for a plain
     * product, one group per part for a set, each titled with the part's name.
     *
     * @return array<int, array{title: ?string, items: array<int, array{inci: string, name: string}>}>
     */
    public static function groups(Product $product): array
    {
        $own = self::of($product->id);

        if ($own->isNotEmpty()) {
            return [['title' => null, 'items' => $own->all()]];
        }

        $parts = DB::table('product_bundle_items as b')
            ->join('lunar_products as p', 'p.id', '=', 'b.item_product_id')
            ->where('b.bundle_product_id', $product->id)
            ->orderBy('b.id')
            ->select('p.id', 'p.attribute_data')
            ->get();

        return $parts
            ->map(fn ($row): array => [
                'title' => (string) (json_decode($row->attribute_data, true)['name']['value'] ?? ''),
                'items' => self::of((int) $row->id)->all(),
            ])
            ->filter(fn (array $group): bool => $group['items'] !== [])
            ->values()
            ->all();
    }

    /**
     * Read ingredients out of free text or HTML, as the old labels had them:
     * list items or comma-separated, each "INCI (srpski naziv)" or just a name.
     *
     * @return array<int, array{inci: string, name: string}>
     */
    public static function parse(string $source): array
    {
        $text = html_entity_decode(strip_tags(preg_replace('~<\s*(li|p|br|h[1-6])[^>]*>~i', "\n", $source)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return collect(preg_split('~\n+|,|;~u', $text))
            ->map(fn (string $piece): string => trim($piece, " \t-•·\u{A0}"))
            ->filter(fn (string $piece): bool => $piece !== '' && $piece !== '/')
            ->map(function (string $piece): array {
                if (preg_match('~^(.*?)\s*\(([^()]*)\)\s*$~u', $piece, $m)) {
                    return ['inci' => trim($m[1]), 'name' => trim($m[2])];
                }

                return ['inci' => $piece, 'name' => ''];
            })
            ->values()
            ->all();
    }
}
