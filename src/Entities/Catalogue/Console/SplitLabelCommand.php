<?php

namespace Meva\Entities\Catalogue\Console;

use Illuminate\Console\Command;
use Lunar\FieldTypes\Text;
use Lunar\Models\Product;
use Meva\Entities\Catalogue\CatalogueCache;
use Meva\Entities\Catalogue\Label;

/**
 * Move "Sastav" and "Način upotrebe" out of the descriptions and into the
 * two fields made for them.
 *
 * Looks in the description first, then in the short description; whichever
 * held them is left with only what comes before. A product that already has
 * the fields filled is left alone, so this can be run again after new
 * products are imported.
 */
class SplitLabelCommand extends Command
{
    protected $signature = 'meva:label:split {--dry : Only say what would change}';

    protected $description = 'Split Sastav and Način upotrebe out of product descriptions into their own fields';

    public function handle(): int
    {
        $changed = 0;

        foreach (Product::query()->cursor() as $product) {
            $data = $product->attribute_data ?? collect();

            if (trim((string) $data->get('ingredients')) !== '' || trim((string) $data->get('usage')) !== '') {
                continue;
            }

            $html = Label::splitHtml((string) $data->get('description'));
            $text = Label::splitText((string) $data->get('short_description'));

            $ingredients = $html['ingredients'] !== '' ? $html['ingredients'] : Label::textToHtml($text['ingredients']);
            $usage = $html['usage'] !== '' ? $html['usage'] : Label::textToHtml($text['usage']);

            if ($ingredients === '' && $usage === '') {
                continue;
            }

            $name = (string) $data->get('name');
            $this->line(($this->option('dry') ? 'would split ' : 'splitting ').$name.' (#'.$product->id.'): '
                .($ingredients !== '' ? 'sastav ' : '').($usage !== '' ? 'upotreba' : ''));

            if ($this->option('dry')) {
                $changed++;

                continue;
            }

            $data->put('ingredients', new Text($ingredients));
            $data->put('usage', new Text($usage));
            $data->put('description', new Text($html['description']));
            $data->put('short_description', new Text($text['description']));

            $product->attribute_data = $data;
            $product->save();
            $changed++;
        }

        if ($changed > 0 && ! $this->option('dry')) {
            CatalogueCache::bump();
        }

        $this->info($changed.' '.($this->option('dry') ? 'would change' : 'changed').'.');

        return self::SUCCESS;
    }
}
