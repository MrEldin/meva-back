<?php

namespace Meva\Api\V1\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Meva\Api\V1\Controllers\Controller;
use Meva\Entities\Marketing\Models\Subscriber;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The marketing desk: the mailing list, and what the shop's adverts feed on.
 */
class MarketingController extends Controller
{
    /**
     * Join the mailing list. Open to the storefront.
     */
    public function subscribe(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:190',
            'name' => 'nullable|string|max:120',
            'source' => 'nullable|string|max:60',
            'utm_source' => 'nullable|string|max:60',
            'utm_campaign' => 'nullable|string|max:60',
        ]);

        $subscriber = Subscriber::query()->updateOrCreate(
            ['email' => mb_strtolower(trim($request->input('email')))],
            [
                'name' => $request->input('name'),
                'source' => $request->input('source', 'storefront'),
                'utm_source' => $request->input('utm_source'),
                'utm_campaign' => $request->input('utm_campaign'),
                'unsubscribed_at' => null,
            ],
        );

        return $this->response->array([
            'data' => ['email' => $subscriber->email, 'subscribed' => true],
        ])->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * The mailing list, newest first.
     */
    public function subscribers(Request $request)
    {
        $subscribers = Subscriber::query()
            ->active()
            ->latest()
            ->paginate(min((int) $request->input('per_page', 50), 200));

        return $this->response->array([
            'data' => $subscribers->map(fn (Subscriber $s): array => [
                'email' => $s->email,
                'name' => $s->name,
                'source' => $s->source,
                'utm_source' => $s->utm_source,
                'utm_campaign' => $s->utm_campaign,
                'created_at' => $s->created_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'total' => $subscribers->total(),
                'per_page' => $subscribers->perPage(),
                'current_page' => $subscribers->currentPage(),
                'last_page' => $subscribers->lastPage(),
            ],
        ])->setStatusCode(Response::HTTP_OK);
    }

    /**
     * The mailing list as a spreadsheet, for whichever mail tool the shop uses.
     */
    public function exportSubscribers(): StreamedResponse
    {
        $subscribers = Subscriber::query()->active()->latest()->get();

        return response()->streamDownload(function () use ($subscribers) {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['E-mail', 'Ime', 'Izvor', 'UTM izvor', 'Kampanja', 'Prijavljen']);

            foreach ($subscribers as $s) {
                fputcsv($out, [$s->email, $s->name, $s->source, $s->utm_source, $s->utm_campaign, $s->created_at?->format('d.m.Y')]);
            }

            fclose($out);
        }, 'meva-lista-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The lists worth acting on this week.
     *
     * A shop whose customers come back about one and a half times lives or
     * dies on the second order. These are the people most likely to make one:
     * the ones whose bottle is running out, the ones who bought once and
     * drifted, and the ones who buy often enough to be worth thanking.
     */
    public function insights(Request $request)
    {
        $customers = $this->customerHistory();
        $today = now();

        $days = fn ($date): int => $date ? (int) $today->diffInDays($date) : 9999;

        $due = $customers
            ->filter(fn ($c): bool => $days($c->last_order) >= 45 && $days($c->last_order) <= 110)
            ->sortByDesc('spent')
            ->take(200)
            ->values();

        $winback = $customers
            ->filter(fn ($c): bool => $days($c->last_order) > 180 && $days($c->last_order) < 900)
            ->sortByDesc('spent')
            ->take(200)
            ->values();

        $loyal = $customers
            ->filter(fn ($c): bool => (int) $c->orders >= 3)
            ->sortByDesc('spent')
            ->take(50)
            ->values();

        $repeat = $customers->filter(fn ($c): bool => (int) $c->orders > 1)->count();
        $total = max(1, $customers->count());

        return $this->response->array([
            'data' => [
                'summary' => [
                    'customers' => $customers->count(),
                    'repeat_customers' => $repeat,
                    'repeat_rate' => round(($repeat / $total) * 100, 1),
                    'average_gap_days' => (int) round($customers->where('orders', '>', 1)->avg('gap_days') ?? 0),
                    'average_spend' => (int) round($customers->avg('spent') ?? 0),
                ],
                'due' => $this->people($due),
                'winback' => $this->people($winback),
                'loyal' => $this->people($loyal),
                'pairs' => $this->pairs(),
            ],
        ])->setStatusCode(Response::HTTP_OK);
    }

    /**
     * One of those lists as a spreadsheet, ready for a mail or Viber campaign.
     */
    public function exportList(Request $request, string $list): StreamedResponse
    {
        $insights = json_decode($this->insights($request)->getContent(), true)['data'] ?? [];
        $rows = $insights[$list] ?? [];

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['Ime', 'E-mail', 'Telefon', 'Grad', 'Porudžbina', 'Potrošeno (RSD)', 'Poslednja porudžbina', 'Dana od tada', 'Poslednji put kupio']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['name'], $row['email'], $row['phone'], $row['city'],
                    $row['orders'], number_format($row['spent'] / 100, 0, ',', ''),
                    $row['last_order'], $row['days_since'], $row['last_products'],
                ]);
            }

            fclose($out);
        }, "meva-{$list}-".now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Every customer with what they have bought, from archive and live sales.
     */
    protected function customerHistory(): \Illuminate\Support\Collection
    {
        return collect(DB::select(<<<'SQL'
            with sales as (
                select lower(customer_email) as email, ordered_at, total
                from order_analytics
                where status <> 'cancelled' and customer_email is not null and customer_email <> ''
            ),
            grouped as (
                select
                    email,
                    count(*)                                   as orders,
                    sum(total)                                 as spent,
                    max(ordered_at)                            as last_order,
                    min(ordered_at)                            as first_order
                from sales
                group by email
            )
            select
                g.*,
                case when g.orders > 1
                     then extract(day from (g.last_order - g.first_order)) / (g.orders - 1)
                     else null end                             as gap_days,
                a.customer_name, a.customer_phone, a.city
            from grouped g
            left join lateral (
                select customer_name, customer_phone, city
                from archive_orders
                where lower(customer_email) = g.email
                order by ordered_at desc
                limit 1
            ) a on true
        SQL));
    }

    /**
     * Present a list of customers, with the last thing each of them bought.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function people(\Illuminate\Support\Collection $customers): array
    {
        $emails = $customers->pluck('email')->all();

        $lastProducts = $emails === [] ? collect() : collect(DB::select(<<<'SQL'
            select lower(o.customer_email) as email, string_agg(distinct i.name, ', ') as products
            from archive_orders o
            join archive_order_items i on i.archive_order_id = o.id
            where lower(o.customer_email) = any(?)
              and o.ordered_at = (
                select max(ordered_at) from archive_orders
                where lower(customer_email) = lower(o.customer_email)
              )
            group by 1
        SQL, ['{'.implode(',', array_map(fn ($e): string => '"'.str_replace('"', '', (string) $e).'"', $emails)).'}']))
            ->pluck('products', 'email');

        return $customers->map(fn ($c): array => [
            'email' => $c->email,
            'name' => $c->customer_name,
            'phone' => $c->customer_phone,
            'city' => $c->city,
            'orders' => (int) $c->orders,
            'spent' => (int) $c->spent,
            'spent_formatted' => number_format($c->spent / 100, 0, ',', '.').' RSD',
            'last_order' => $c->last_order ? date('Y-m-d', strtotime($c->last_order)) : null,
            'days_since' => $c->last_order ? (int) now()->diffInDays($c->last_order) : null,
            'last_products' => $lastProducts[$c->email] ?? null,
        ])->all();
    }

    /**
     * Which preparations end up in the same basket, for bundles and the
     * "goes well with" line on a product page.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function pairs(int $limit = 12): array
    {
        return collect(DB::select(<<<'SQL'
            select a.name as first, b.name as second, count(*) as together
            from archive_order_items a
            join archive_order_items b
              on b.archive_order_id = a.archive_order_id and b.name > a.name
            group by a.name, b.name
            having count(*) >= 5
            order by together desc
            limit ?
        SQL, [$limit]))
            ->map(fn ($row): array => [
                'first' => $row->first,
                'second' => $row->second,
                'together' => (int) $row->together,
            ])
            ->all();
    }

    /**
     * Which campaigns actually earned money, from the sales archive and the
     * live orders alike.
     */
    public function campaigns(Request $request)
    {
        $from = $request->date('od') ?? now()->subYear();
        $to = $request->date('do') ?? now()->endOfDay();

        $rows = DB::table('order_analytics')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('ordered_at', [$from, $to])
            ->selectRaw("coalesce(nullif(utm_source, ''), 'direktno') as source, coalesce(source_type, 'nepoznato') as type, count(*) as orders, sum(total) as revenue")
            ->groupBy('source', 'type')
            ->orderByDesc('revenue')
            ->limit(40)
            ->get();

        return $this->response->array([
            'data' => $rows->map(fn ($row): array => [
                'source' => $row->source,
                'type' => $row->type,
                'orders' => (int) $row->orders,
                'revenue' => (int) $row->revenue,
                'average_order' => $row->orders > 0 ? (int) round($row->revenue / $row->orders) : 0,
            ])->all(),
            'meta' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ])->setStatusCode(Response::HTTP_OK);
    }
}
