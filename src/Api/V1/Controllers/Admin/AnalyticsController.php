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
 * know where a sale came from.
 */
class AnalyticsController extends Controller
{
    /**
     * The figures the dashboard shows.
     */
    public function index(Request $request)
    {
        $from = $request->date('od') ?? Carbon::parse('2024-01-01');
        $to = $request->date('do') ?? Carbon::now()->endOfDay();

        return $this->response->array([
            'data' => [
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'totals' => $this->totals($from, $to),
                'monthly' => $this->monthly($from, $to),
                'channels' => $this->groupedBy('utm_source', $from, $to),
                'devices' => $this->groupedBy('device_type', $from, $to),
                'cities' => $this->groupedBy('city', $from, $to, 8),
                'products' => $this->topProducts(),
            ],
        ])->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Headline numbers for the period.
     *
     * @return array<string, int|float>
     */
    protected function totals(Carbon $from, Carbon $to): array
    {
        $row = $this->scope($from, $to)
            ->selectRaw('count(*) as orders, coalesce(sum(total), 0) as revenue')
            ->first();

        $customers = $this->scope($from, $to)
            ->whereNotNull('customer_email')
            ->distinct()
            ->count('customer_email');

        return [
            'orders' => (int) $row->orders,
            'revenue' => (int) $row->revenue,
            'average_order' => $row->orders > 0 ? (int) round($row->revenue / $row->orders) : 0,
            'customers' => $customers,
        ];
    }

    /**
     * Revenue and order count per month.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function monthly(Carbon $from, Carbon $to): array
    {
        return $this->scope($from, $to)
            ->selectRaw("to_char(ordered_at, 'YYYY-MM') as month, count(*) as orders, sum(total) as revenue")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($row): array => [
                'month' => $row->month,
                'orders' => (int) $row->orders,
                'revenue' => (int) $row->revenue,
            ])
            ->all();
    }

    /**
     * Orders and revenue grouped by one column.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function groupedBy(string $column, Carbon $from, Carbon $to, int $limit = 10): array
    {
        return $this->scope($from, $to)
            ->selectRaw("coalesce({$column}, 'nepoznato') as label, count(*) as orders, sum(total) as revenue")
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
     * Best sellers, from the archived line items.
     *
     * Live orders are too few to rank yet; once they matter this should union
     * lunar_order_lines the same way the view unions orders.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function topProducts(int $limit = 8): array
    {
        return DB::table('archive_order_items')
            ->selectRaw('name, sum(quantity) as units, sum(total) as revenue')
            ->groupBy('name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'label' => $row->name,
                'orders' => (int) $row->units,
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
