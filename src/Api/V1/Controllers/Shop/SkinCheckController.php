<?php

namespace Meva\Api\V1\Controllers\Shop;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Files\Image;
use Lunar\Models\Product;
use Meva\AI\Agents\SkinCheckAgent;
use Meva\Api\V1\Controllers\Controller;
use Meva\Api\V1\Transformers\Commerce\ShopProductTransformer;
use Throwable;

/**
 * The camera button in the app.
 *
 * A photograph comes in, a concern and a shelf of products go out. The
 * agent names the concern; this maps it onto the catalogue -- by the words
 * in the product names first, because "Krema protiv akni" says what it is
 * for more reliably than any category, and by category as the fallback.
 */
class SkinCheckController extends Controller
{
    /** What each concern is called, and the product names and categories that answer it. */
    protected const SHELVES = [
        'seboreja' => ['label' => 'Seboreja', 'names' => ['sebore'], 'category' => 'seboreja'],
        'psorijaza' => ['label' => 'Psorijaza', 'names' => ['psorijaz'], 'category' => 'psorijaza'],
        'ekcem' => ['label' => 'Ekcem', 'names' => ['ekcem'], 'category' => 'ekcem'],
        'akne' => ['label' => 'Akne', 'names' => ['akn', 'fleki'], 'category' => 'preparati-za-lice'],
        'perut' => ['label' => 'Perut', 'names' => ['perut', 'sebore', 'šampon'], 'category' => 'kosa'],
        'opadanje-kose' => ['label' => 'Opadanje kose', 'names' => ['opadanj', 'keratin', 'regenerat'], 'category' => 'kosa'],
        'rozacea' => ['label' => 'Rozacea', 'names' => ['rozace'], 'category' => 'preparati-za-lice'],
        'bore' => ['label' => 'Bore', 'names' => ['bora', 'hijaluron', 'hyaluron'], 'category' => 'preparati-za-lice'],
        'suva-koza' => ['label' => 'Suva koža', 'names' => ['hijaluron', 'hyaluron', 'body butter', 'mleko za telo'], 'category' => 'preparati-za-lice'],
    ];

    public function __invoke(Request $request)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,heic,webp', 'max:10240'],
        ]);

        if (blank(config('ai.providers.anthropic.key'))) {
            return $this->response->error('Analiza slike trenutno nije dostupna.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        try {
            $result = SkinCheckAgent::make()->prompt(
                'Ovo je fotografija koju je kupac upravo snimio telefonom. Šta vidite i koji problem najviše liči?',
                attachments: [Image::fromUpload($request->file('image'))],
                timeout: 60,
            )->toArray();
        } catch (Throwable $e) {
            Log::warning('Skin check failed: '.$e->getMessage());

            return $this->response->error('Nismo uspeli da obradimo sliku. Pokušajte ponovo.', Response::HTTP_BAD_GATEWAY);
        }

        $concern = $result['concern'] ?? 'none';
        $shelf = self::SHELVES[$concern] ?? null;

        return $this->response->array([
            'data' => [
                'concern' => $concern,
                'label' => $shelf['label'] ?? 'Ništa za brigu',
                'confidence' => round((float) ($result['confidence'] ?? 0), 2),
                'summary' => (string) ($result['summary'] ?? ''),
                'advice' => (string) ($result['advice'] ?? ''),
                'see_doctor' => (bool) ($result['see_doctor'] ?? false),
                'products' => $shelf ? $this->shelf($shelf) : [],
            ],
        ]);
    }

    /**
     * Up to four products for a concern: named for it first, then from its category.
     *
     * @param  array{label: string, names: list<string>, category: string}  $shelf
     * @return list<array<string, mixed>>
     */
    protected function shelf(array $shelf): array
    {
        $products = Product::query()
            ->where('status', 'published')
            ->with(['variants.prices.currency', 'collections', 'productType', 'media'])
            ->get();

        $name = fn (Product $p): string => mb_strtolower((string) $p->attribute_data?->get('name'));

        $named = $products->filter(fn (Product $p): bool => collect($shelf['names'])
            ->contains(fn (string $needle): bool => str_contains($name($p), $needle)));

        $shelved = $products->filter(fn (Product $p): bool => $p->collections
            ->contains(fn ($c): bool => (string) $c->attribute_data?->get('slug') === $shelf['category']));

        $transformer = new ShopProductTransformer;

        return $named->concat($shelved)
            ->unique('id')
            // Sets first: they answer the whole concern, not one step of it.
            ->sortByDesc(fn (Product $p): int => $p->productType?->name === 'Set' ? 1 : 0)
            ->take(4)
            ->map(fn (Product $p): array => $transformer->transform($p))
            ->values()
            ->all();
    }
}
