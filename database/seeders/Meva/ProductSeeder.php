<?php

namespace Database\Seeders\Meva;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Lunar\FieldTypes\Text;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\Currency;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;

/**
 * Imports the 73 exported products, each with one variant, its prices, its
 * photography and its categories.
 */
class ProductSeeder extends Seeder
{
    protected ProductType $simpleType;

    protected ProductType $setType;

    protected Currency $rsd;

    protected ?Currency $eur;

    protected TaxClass $taxClass;

    /** @var array<string, int> category name => collection id */
    protected array $collections = [];

    protected ?int $uncategorised = null;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->prepare();

        $imported = 0;
        $drafted = 0;

        foreach (MevaExport::products() as $row) {
            $product = $this->importProduct($row);

            $product->status === 'draft' ? $drafted++ : $imported++;
        }

        $this->command?->info("Products: {$imported} published, {$drafted} draft");
    }

    /**
     * Resolve the records every product import depends on.
     */
    protected function prepare(): void
    {
        // Sets are a distinct type so they can be told apart in the catalogue
        // and priced as a unit, even though Lunar has no native bundle.
        $this->simpleType = ProductType::firstOrCreate(['name' => 'Proizvod']);
        $this->setType = ProductType::firstOrCreate(['name' => 'Set']);

        $this->rsd = Currency::where('code', 'RSD')->sole();
        $this->eur = Currency::where('code', 'EUR')->first();
        $this->taxClass = TaxClass::getDefault() ?? TaxClass::sole();

        // Products reference categories by display name, but a category's slug
        // is not always derived from it -- "Nega kože" lives at
        // "preparati-za-lice". The export carries both, so the name is mapped
        // through the slug rather than guessed from it.
        $bySlug = LunarCollection::query()
            ->get()
            ->mapWithKeys(fn (LunarCollection $c): array => [
                (string) $c->attribute_data?->get('slug') => $c->id,
            ]);

        $this->collections = MevaExport::categories()
            ->mapWithKeys(fn (array $category): array => [
                $category['naziv'] => $bySlug->get($category['slug']),
            ])
            ->filter()
            ->all();

        $this->uncategorised = $bySlug->get('uncategorized') ?? $bySlug->get('nesvrstano');
    }

    /**
     * Import a single exported product.
     *
     * @param  array<string, mixed>  $row
     */
    protected function importProduct(array $row): Product
    {
        $sku = MevaExport::sku($row);

        // Lunar products carry no external reference column, so the SKU on the
        // single variant is what links a record back to its WordPress origin.
        $product = ProductVariant::query()->where('sku', $sku)->first()?->product ?? new Product;

        $product->fill([
            'product_type_id' => $row['tip'] === 'set' ? $this->setType->id : $this->simpleType->id,
            // Five exported products carry no price at all; they were never
            // finished in the old shop, so they land as drafts instead of
            // being dropped or published without one.
            'status' => $this->hasPrice($row) ? 'published' : 'draft',
            'attribute_data' => collect(array_filter([
                'name' => new Text($row['naziv']),
                'slug' => new Text($row['slug']),
                'description' => new Text($row['opis_html'] ?: ''),
                'short_description' => new Text($row['kratak_opis'] ?: ''),
            ])),
        ]);

        $product->save();

        $this->syncVariant($product, $row);
        $this->syncCollections($product, $row);
        $this->syncImages($product, $row);

        return $product;
    }

    /**
     * Create or update the product's single variant and its prices.
     *
     * Every exported product is a simple one -- the old shop had no variations
     * at all -- so one variant per product is the faithful shape.
     *
     * @param  array<string, mixed>  $row
     */
    protected function syncVariant(Product $product, array $row): void
    {
        $variant = $product->variants()->firstOrNew(['sku' => MevaExport::sku($row)]);

        $variant->fill([
            'sku' => MevaExport::sku($row),
            'tax_class_id' => $this->taxClass->id,
            // Stock was never tracked in the old shop, so there is no opening
            // figure to carry over.
            'stock' => 0,
            'purchasable' => 'always',
            'shippable' => true,
            'unit_quantity' => 1,
            'weight_value' => (float) ($row['tezina_kg'] ?: 0),
            'weight_unit' => 'kg',
        ]);

        $product->variants()->save($variant);

        $this->syncPrice($variant, $this->rsd, (string) ($row['akcijska_cena_rsd'] ?: $row['cena_rsd']));

        if ($this->eur !== null) {
            $this->syncPrice($variant, $this->eur, (string) ($row['cena_eur'] ?? ''));
        }
    }

    /**
     * Store one price, in the currency's minor units.
     */
    protected function syncPrice($variant, Currency $currency, ?string $amount): void
    {
        if ($amount === null || $amount === '' || ! is_numeric($amount)) {
            return;
        }

        $minor = (int) round(((float) $amount) * (10 ** $currency->decimal_places));

        $variant->prices()->updateOrCreate(
            ['currency_id' => $currency->id, 'min_quantity' => 1],
            ['price' => $minor]
        );
    }

    /**
     * Attach the product to its exported categories.
     *
     * @param  array<string, mixed>  $row
     */
    protected function syncCollections(Product $product, array $row): void
    {
        $ids = collect($row['kategorije'] ?? [])
            ->map(fn (string $name): ?int => $this->collectionIdFor($name))
            ->filter()
            ->values()
            ->all();

        // Sixteen products came across with no category at all. They are parked
        // in "Nesvrstano" rather than left unreachable, so they show up
        // somewhere until someone categorises them properly.
        if ($ids === [] && $this->uncategorised !== null) {
            $ids = [$this->uncategorised];
        }

        $product->collections()->sync($ids);
    }

    /**
     * Resolve a collection id from the exported category name.
     */
    protected function collectionIdFor(string $name): ?int
    {
        return $this->collections[$name] ?? null;
    }

    /**
     * Attach the product's photography.
     *
     * @param  array<string, mixed>  $row
     */
    protected function syncImages(Product $product, array $row): void
    {
        if (! config('meva.catalogue.import_images', true)) {
            return;
        }

        if ($product->getMedia('images')->isNotEmpty()) {
            return;
        }

        foreach ($row['slike'] ?? [] as $image) {
            // galerija/ holds marketing artwork reused across products, not
            // photographs of this one, so only the main shot and the studio
            // photographs are imported.
            if (str_contains($image['putanja'], 'galerija/')) {
                continue;
            }

            $path = MevaExport::imagePath($image['putanja']);

            if (! is_file($path)) {
                continue;
            }

            $product->addMedia($path)
                ->preservingOriginal()
                ->withCustomProperties(['primary' => (bool) ($image['glavna'] ?? false)])
                ->toMediaCollection('images');
        }
    }

    /**
     * Whether the export gave this product a usable price.
     *
     * @param  array<string, mixed>  $row
     */
    protected function hasPrice(array $row): bool
    {
        return is_numeric((string) ($row['akcijska_cena_rsd'] ?: $row['cena_rsd']));
    }
}
