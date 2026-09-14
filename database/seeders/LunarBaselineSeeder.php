<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\Channel;
use Lunar\Models\CollectionGroup;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\TaxClass;

/**
 * The baseline records Lunar needs before anything can be priced or published.
 *
 * Lunar normally creates these from its own interactive installer, which ships
 * with the admin panel. This project is headless, so the same baseline is
 * seeded here -- without it, creating a product fails on a missing default
 * language or currency.
 *
 * Every record is created only when absent, so running this repeatedly is safe.
 */
class LunarBaselineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Channel::whereDefault(true)->exists()) {
            Channel::create([
                'name' => 'Webstore',
                'handle' => 'webstore',
                'default' => true,
                'url' => config('app.url'),
            ]);
        }

        if (! Language::count()) {
            Language::create([
                'code' => 'en',
                'name' => 'English',
                'default' => true,
            ]);
        }

        if (! Currency::whereDefault(true)->exists()) {
            Currency::create([
                'code' => 'EUR',
                'name' => 'Euro',
                'exchange_rate' => 1,
                'decimal_places' => 2,
                'default' => true,
                'enabled' => true,
            ]);
        }

        if (! CustomerGroup::whereDefault(true)->exists()) {
            CustomerGroup::create([
                'name' => 'Retail',
                'handle' => 'retail',
                'default' => true,
            ]);
        }

        if (! CollectionGroup::count()) {
            CollectionGroup::create([
                'name' => 'Main',
                'handle' => 'main',
            ]);
        }

        if (! TaxClass::count()) {
            TaxClass::create([
                'name' => 'Default Tax Class',
                'default' => true,
            ]);
        }
    }
}
