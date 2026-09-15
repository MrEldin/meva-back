<?php

namespace Meva\Api\V1\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Meva\Api\V1\Controllers\Controller;

/**
 * Sales reporting for the dashboard.
 *
 * Everything is read from the order_analytics view, which unions the historical
 * WooCommerce orders with the ones this shop takes, so a report never has to
 * know where a sale came from. Every headline figure is answered twice -- for
 * the chosen period and for the one before it -- because a number without
 * something to compare it to says very little.
 */
class AnalyticsController extends Controller
{
    /**
     * The figures the dashboard shows.
     */
    public function index(Request $request)
    {
        [$from, $to] = $this->period($request);
        $length = $from->diffInDays($to) + 1;
        $previousFrom = (clone $from)->subDays($length);
        $previousTo = (clone $from)->subDay()->endOfDay();

        $totals = $this->totals($from, $to);
        $previous = $this->totals($previousFrom, $previousTo);

        return $this->response->array([
            'data' => [
                'period' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                    'days' => $length,
                    'previous_from' => $previousFrom->toDateString(),
                    'previous_to' => $previousTo->toDateString(),
                ],
                'totals' => $totals,
                'previous' => $previous,
                'change' => $this->change($totals, $previous),
                'series' => $this->series($from, $to, $length),
                'statuses' => $this->statuses(),
                'products' => $this->topProducts($from, $to),
                'categories' => $this->categories($from, $to),
                'channels' => $this->groupedBy('utm_source', $from, $to),
                'devices' => $this->groupedBy('device_type', $from, $to),
                'cities' => $this->groupedBy('city', $from, $to, 8),
                'customers' => $this->customers($from, $to),
                'busiest' => $this->busiest($from, $to),
            ],
        ])->setStatusCode(Response::HTTP_OK);
    }

    /**
     * The window being reported on. Defaults to the last 30 days.
     */
    protected function period(Request $request): array
    {
        $to = ($request->date('do') ?? Carbon::now())->endOfDay();
        $from = ($request->date('od') ?? (clone $to)->subDays(29))->startOfDay();

        return [$from, $to];
    }

    /**
     * Headline numbers for a window.
     *
     * @return array<string, int|float>
     */
    protected function totals(Carbon $from, Carbon $to): array
    {
        $row = $this->scope($from, $to)
            ->selectRaw('count(*) as orders, coalesce(sum(total), 0) as revenue, coalesce(sum(goods), 0) as goods')
            ->first();

        $customers = $this->scope($from, $to)
            ->whereNotNull('customer_email')
            ->where('customer_email', '!=', '')
            ->distinct()
            ->count('customer_email');

        // Someone is "returning" if they had also ordered before this window.
        $returning = DB::table('order_analytics as o')
            ->where('o.status', '!=', 'cancelled')
            ->whereBetween('o.ordered_at', [$from, $to])
            ->whereNotNull('o.customer_email')
            ->where('o.customer_email', '!=', '')
            ->whereExists(fn ($q) => $q
                ->select(DB::raw(1))
                ->from('order_analytics as p')
                ->whereColumn('p.customer_email', 'o.customer_email')
                ->where('p.ordered_at', '<', $from))
            ->distinct()
            ->count('o.customer_email');

        $orders = (int) $row->orders;
        $revenue = (int) $row->revenue;

        return [
            'orders' => $orders,
            'revenue' => $revenue,
            'average_order' => $orders > 0 ? (int) round($revenue / $orders) : 0,
            'customers' => $customers,
            'returning_customers' => $returning,
            'new_customers' => max(0, $customers - $returning),
            'orders_per_customer' => $customers > 0 ? round($orders / $customers, 2) : 0,
            'revenue_per_day' => $revenue > 0 ? (int) round($revenue / max(1, $from->diffInDays($to) + 1)) : 0,
        ];
    }

    /**
     * How each headline figure moved against the previous window, in percent.
     *
     * @param  array<string, int|float>  $now
     * @param  array<string, int|float>  $before
     * @return array<string, float|null>
     */
    protected function change(array $now, array $before): array
    {
        $change = [];

        foreach (['orders', 'revenue', 'average_order', 'customers'] as $key) {
            $previous = (float) $before[$key];
            $change[$key] = $previous > 0
                ? round((((float) $now[$key] - $previous) / $previous) * 100, 1)
                : null;
        }

        return $change;
    }

    /**
     * Revenue and orders over time: by day for a short window, by month for a
     * long one, so the chart always has a readable number of points.
     *
     * @return array<string, mixed>
     */
    protected function series(Carbon $from, Carbon $to, int $days): array
    {
        $byMonth = $days > 92;
        $format = $byMonth ? 'YYYY-MM' : 'YYYY-MM-DD';

        $rows = $this->scope($from, $to)
            ->selectRaw("to_char(ordered_at, '{$format}') as bucket, count(*) as orders, sum(total) as revenue")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get()
            ->keyBy('bucket');

        // Fill the gaps, so a quiet day reads as zero rather than disappearing.
        $points = [];
        $cursor = (clone $from);

        while ($cursor <= $to) {
            $key = $cursor->format($byMonth ? 'Y-m' : 'Y-m-d');
            $row = $rows->get($key);

            $points[] = [
                'bucket' => $key,
                'orders' => (int) ($row->orders ?? 0),
                'revenue' => (int) ($row->revenue ?? 0),
            ];

            $byMonth ? $cursor->addMonth() : $cursor->addDay();
        }

        return ['granularity' => $byMonth ? 'month' : 'day', 'points' => $points];
    }

    /**
     * What is waiting to be done: orders by status, live orders only.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function statuses(): array
    {
        return DB::table('lunar_orders')
            ->whereNotNull('placed_at')
            ->selectRaw('status, count(*) as orders, coalesce(sum(total), 0) as revenue')
            ->groupBy('status')
            ->orderByDesc('orders')
            ->get()
            ->map(fn ($row): array => [
                'label' => $row->status,
                'orders' => (int) $row->orders,
                'revenue' => (int) $row->revenue,
            ])
            ->all();
    }

    /**
     * Best sellers in the window, from both the archive and the live orders.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function topProducts(Carbon $from, Carbon $to, int $limit = 10): array
    {
        $archive = DB::table('archive_order_items as i')
            ->join('archive_orders as o', 'o.id', '=', 'i.archive_order_id')
            ->whereBetween('o.ordered_at', [$from, $to])
            ->where('o.status', '!=', 'cancelled')
            ->selectRaw('i.name as label, sum(i.quantity) as units, sum(i.total) as revenue')
            ->groupBy('i.name');

        $live = DB::table('lunar_order_lines as l')
            ->join('lunar_orders as o', 'o.id', '=', 'l.order_id')
            ->whereNotNull('o.placed_at')
            ->whereBetween('o.placed_at', [$from, $to])
            ->where('o.status', '!=', 'cancelled')
            ->where('l.type', 'physical')
            ->selectRaw('l.description as label, sum(l.quantity) as units, sum(l.total) as revenue')
            ->groupBy('l.description');

        return DB::query()
            ->fromSub($archive->unionAll($live), 'sales')
            ->selectRaw('label, sum(units) as units, sum(revenue) as revenue')
            ->groupBy('label')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'label' => $row->label,
                'orders' => (int) $row->units,
                'revenue' => (int) $row->revenue,
            ])
            ->all();
    }

    /**
     * Which categories earn, by matching sold line items back to the catalogue.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function categories(Carbon $from, Carbon $to): array
    {
        return DB::table('archive_order_items as i')
            ->join('archive_orders as o', 'o.id', '=', 'i.archive_order_id')
            ->join('lunar_product_variants as v', 'v.sku', '=', DB::raw("concat('MEVA-', i.wp_product_id)"))
            ->join('lunar_collection_product as cp', 'cp.product_id', '=', 'v.product_id')
            ->join('lunar_collections as c', 'c.id', '=', 'cp.collection_id')
            ->whereBetween('o.ordered_at', [$from, $to])
            ->where('o.status', '!=', 'cancelled')
            ->selectRaw("c.attribute_data->'name'->>'value' as label, sum(i.quantity) as units, sum(i.total) as revenue")
            ->groupBy('label')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'label' => $row->label ?: 'Nesvrstano',
                'orders' => (int) $row->units,
                'revenue' => (int) $row->revenue,
            ])
            ->all();
    }

    /**
     * The customers worth knowing by name.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function customers(Carbon $from, Carbon $to, int $limit = 10): array
    {
        return $this->scope($from, $to)
            ->whereNotNull('customer_email')
            ->where('customer_email', '!=', '')
            ->selectRaw('customer_email as label, count(*) as orders, sum(total) as revenue')
            ->groupBy('label')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'label' => $row->label,
                'orders' => (int) $row->orders,
                'revenue' => (int) $row->revenue,
            ])
            ->all();
    }

    /**
     * When people actually order: by weekday and by hour, so adverts and posts
     * can be timed for when the shop is being read.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function busiest(Carbon $from, Carbon $to): array
    {
        $days = ['Nedelja', 'Ponedeljak', 'Utorak', 'Sreda', 'Četvrtak', 'Petak', 'Subota'];

        $weekday = $this->scope($from, $to)
            ->selectRaw('extract(dow from ordered_at) as bucket, count(*) as orders, sum(total) as revenue')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get()
            ->map(fn ($row): array => [
                'label' => $days[(int) $row->bucket] ?? (string) $row->bucket,
                'orders' => (int) $row->orders,
                'revenue' => (int) $row->revenue,
            ])
            ->all();

        $hour = $this->scope($from, $to)
            ->selectRaw('extract(hour from ordered_at) as bucket, count(*) as orders, sum(total) as revenue')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get()
            ->map(fn ($row): array => [
                'label' => str_pad((string) (int) $row->bucket, 2, '0', STR_PAD_LEFT).'h',
                'orders' => (int) $row->orders,
                'revenue' => (int) $row->revenue,
            ])
            ->all();

        return ['weekday' => $weekday, 'hour' => $hour];
    }

    /**
     * Orders and revenue grouped by one column of the view.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function groupedBy(string $column, Carbon $from, Carbon $to, int $limit = 10): array
    {
        return $this->scope($from, $to)
            ->selectRaw("coalesce(nullif({$column}, ''), 'nepoznato') as label, count(*) as orders, sum(total) as revenue")
            ->groupBy('label')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'label' => $row->label,
                'orders' => (int) $row->orders,
                'revenue' => (int) $row->revenue,
            ])
            ->all();
    }

    /**
     * The base query: everything that earned money in the period.
     */
    protected function scope(Carbon $from, Carbon $to)
    {
        return DB::table('order_analytics')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('ordered_at', [$from, $to]);
    }
}
