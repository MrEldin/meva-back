<?php

use Database\Seeders\Meva\ArchiveSeeder;
use Database\Seeders\Meva\CatalogueSeeder;
use Illuminate\Support\Facades\DB;
use Meva\Entities\Archive\Models\ArchiveCustomer;
use Meva\Entities\Archive\Models\ArchiveOrder;

/**
 * The export carries personal data and lives outside the repository, so these
 * only run where MEVA_ARCHIVE_PATH points at it.
 */
beforeEach(function () {
    $path = rtrim((string) config('meva.archive.path'), '/');

    if (! is_file("{$path}/orders.json")) {
        test()->markTestSkipped('No order export configured; set MEVA_ARCHIVE_PATH.');
    }

    $this->seed(CatalogueSeeder::class);
    $this->seed(ArchiveSeeder::class);
});

it('imports the whole sales history', function () {
    expect(ArchiveOrder::count())->toBe(5480)
        ->and(ArchiveCustomer::count())->toBe(3964)
        ->and(DB::table('archive_order_items')->count())->toBe(16351);
});

it('reproduces the revenue the export reported', function () {
    $revenue = ArchiveOrder::revenue()->sum('total');

    // 14.651.481 RSD, in minor units.
    expect($revenue)->toBe(1465148100)
        ->and(ArchiveOrder::revenue()->count())->toBe(5479);
});

it('reproduces the yearly order counts', function () {
    $byYear = ArchiveOrder::query()
        ->get()
        ->groupBy(fn (ArchiveOrder $o): string => $o->ordered_at->format('Y'))
        ->map->count();

    expect($byYear['2024'])->toBe(1908)
        ->and($byYear['2025'])->toBe(2329)
        ->and($byYear['2026'])->toBe(1243);
});

it('links orders to their customer', function () {
    expect(ArchiveOrder::whereNull('archive_customer_id')->count())->toBe(0);
});

it('links order lines to the live catalogue', function () {
    $linked = DB::table('archive_order_items')->whereNotNull('product_id')->count();

    // Not every historical line resolves -- some products were deleted before
    // the export -- but the great majority must, or per-product reporting is
    // worthless.
    expect($linked)->toBeGreaterThan(15000);
});

it('keeps the campaign attribution', function () {
    expect(ArchiveOrder::whereNotNull('utm_source')->count())->toBeGreaterThan(5000)
        ->and(ArchiveOrder::where('device_type', 'Mobile')->count())->toBeGreaterThan(5000);
});

it('reports everything through one view', function () {
    $byChannel = DB::table('order_analytics')
        ->where('status', '!=', 'cancelled')
        ->selectRaw('utm_source, count(*) as orders, sum(total) as revenue')
        ->groupBy('utm_source')
        ->orderByDesc('revenue')
        ->get();

    expect($byChannel)->not->toBeEmpty()
        ->and($byChannel->sum('orders'))->toBe(5479)
        ->and($byChannel->sum('revenue'))->toBe(1465148100);
});

it('can be re-run without duplicating anything', function () {
    $this->seed(ArchiveSeeder::class);

    expect(ArchiveOrder::count())->toBe(5480)
        ->and(ArchiveCustomer::count())->toBe(3964)
        ->and(DB::table('archive_order_items')->count())->toBe(16351);
});
