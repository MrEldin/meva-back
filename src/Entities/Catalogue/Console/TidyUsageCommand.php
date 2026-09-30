<?php

namespace Meva\Entities\Catalogue\Console;

use Illuminate\Console\Command;
use Lunar\FieldTypes\Text;
use Lunar\Models\Product;
use Meva\Entities\Catalogue\CatalogueCache;
use Meva\Entities\Catalogue\Ingredients;
use Meva\Entities\Catalogue\Label;

/**
 * Some labels went on after the directions -- scent, packaging, size,
 * storage -- and one carried its ingredient list there. Move the facts to
 * the end of the description as tidy paragraphs, and the ingredients into
 * their rows, leaving the directions as directions. Safe to run again.
 */
class TidyUsageCommand extends Command
{
    protected $signature = 'meva:label:tidy {--dry : Only say what would change}';

    protected $description = 'Move trailing facts and stray ingredients out of Način upotrebe';

    public function handle(): int
    {
        $changed = 0;

        foreach (Product::query()->with('productType')->cursor() as $product) {
            $data = $product->attribute_data ?? collect();
            $usage = (string) $data->get('usage');

            if (trim($usage) === '') {
                continue;
            }

            $tidy = Label::tidyUsage($usage);
            $rows = $tidy['ingredients'] !== '' && $product->productType?->name !== 'Set' && Ingredients::of($product->id)->isEmpty()
                ? Ingredients::parse($tidy['ingredients'])
                : [];

            if ($tidy['facts'] === '' && $rows === [] && $tidy['usage'] === trim($usage)) {
                continue;
            }

            $this->line(($this->option('dry') ? 'would tidy ' : 'tidying ').$data->get('name').' (#'.$product->id.'): '
                .($tidy['facts'] !== '' ? 'facts ' : '').($rows !== [] ? 'sastav' : ''));

            if ($this->option('dry')) {
                $changed++;

                continue;
            }

            $data->put('usage', new Text($tidy['usage']));

            if ($tidy['facts'] !== '') {
                $data->put('description', new Text(trim((string) $data->get('description')).$tidy['facts']));
            }

            if ($rows !== []) {
                Ingredients::replace($product->id, $rows);
            }

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
