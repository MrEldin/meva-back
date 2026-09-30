<?php

namespace Meva\Entities\Catalogue\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Models\Collection;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Meva\Entities\Catalogue\CatalogueCache;
use Meva\Entities\Catalogue\Console\MarkCutoutsCommand;
use Meva\Entities\Catalogue\Console\SplitLabelCommand;
use Meva\Entities\Catalogue\Console\TidyUsageCommand;

/**
 * Keeps the cached catalogue honest.
 */
class CatalogueServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Anything that changes what the storefront shows retires the cache.
        foreach ([Product::class, ProductVariant::class, Price::class, Collection::class] as $model) {
            $model::saved(fn () => CatalogueCache::bump());
            $model::deleted(fn () => CatalogueCache::bump());
        }

        if ($this->app->runningInConsole()) {
            $this->commands([MarkCutoutsCommand::class, SplitLabelCommand::class, TidyUsageCommand::class]);
        }
    }
}
