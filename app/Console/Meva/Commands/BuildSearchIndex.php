<?php

namespace App\Console\Meva\Commands;

use Illuminate\Console\Command;
use Meva\Entities\Search\SearchIndex;

/**
 * Rebuild the search indexes from the database.
 *
 * Run after seeding, after a catalogue import, and whenever the reading pages
 * change. The whole thing is a couple of hundred documents, so it rebuilds
 * rather than reconciles.
 */
class BuildSearchIndex extends Command
{
    protected $signature = 'meva:search-index';

    protected $description = 'Rebuild the Meilisearch indexes for products, categories, reviews and pages';

    public function handle(SearchIndex $index): int
    {
        if (! SearchIndex::available()) {
            $this->error('Search is not configured: set MEILI_KEY (and MEILI_HOST if it is not on localhost).');

            return self::FAILURE;
        }

        foreach ($index->rebuild() as $name => $count) {
            $this->info(str_pad($name, 14).$count);
        }

        return self::SUCCESS;
    }
}
