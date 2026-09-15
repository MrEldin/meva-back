<?php

namespace Meva\Api\V1\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Order;
use Meva\Api\V1\Controllers\Controller;
use Meva\Api\V1\Transformers\Commerce\OrderTransformer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The order book.
 *
 * Every sale the shop has ever taken, the five thousand from the old shop
 * included: the two live side by side in one list, searchable by reference,
 * customer, e-mail, phone or city. Archived orders are history and cannot be
 * moved along; the ones this shop took can.
 */
class OrderController extends Controller
{
    /**
     * List orders, newest first.
     */
    public function index(Request $request)
    {
        $perPage = min((int) $request->input('per_page', 25), 100);
        $page = max(1, (int) $request->input('page', 1));

        $query = $this->unified($request);

        $total = DB::query()->fromSub($query, 'orders')->count();

        $rows = DB::query()
            ->fromSub($query, 'orders')
            ->orderByDesc('placed_at')
            ->forPage($page, $perPage)
            ->get();

        $rows = $this->withItemCounts($rows)->map(fn ($row): array => $this->present($row));

        $paginator = new LengthAwarePaginator($rows, $total, $perPage, $page);

        return $this->response->array([
            'data' => $rows->all(),
            'meta' => [
                'pagination' => [
                    'total' => $total,
                    'count' => $rows->count(),
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'total_pages' => $paginator->lastPage(),
                ],
            ],
        ])->setStatusCode(Response::HTTP_OK);
    }

    /**
     * One order, with its lines and the person who placed it.
     *
     * An id prefixed with "a" is an archived order.
     */
    public function show(string $id)
    {
        if (str_starts_with($id, 'a')) {
            return $this->response->array(['data' => $this->archived((int) substr($id, 1))])
                ->setStatusCode(Response::HTTP_OK);
        }

        $order = Order::with(['lines', 'shippingAddress', 'billingAddress'])->findOrFail((int) $id);

        return $this->response
            ->item($order, $this->detailed())
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Move an order along: dispatched, delivered, returned, cancelled.
     */
    public function updateStatus(Request $request, string $id)
    {
        if (str_starts_with($id, 'a')) {
            return response()->json(['message' => 'Arhivirane porudžbine se ne menjaju.'], 422);
        }

        $request->validate([
            'status' => 'required|string|in:'.implode(',', OrderTransformer::CHANGEABLE),
            'notes' => 'nullable|string|max:2000',
        ]);

        $order = Order::findOrFail((int) $id);
        $order->status = $request->input('status');

        if ($request->filled('notes')) {
            $order->notes = $request->input('notes');
        }

        $order->save();

        return $this->response
            ->item($order->refresh(), $this->detailed())
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * How many orders sit in each status, for the badges above the list.
     */
    public function summary()
    {
        $live = DB::table('lunar_orders')->whereNotNull('placed_at')
            ->selectRaw('status, count(*) as total')->groupBy('status');

        $counts = DB::query()
            ->fromSub(
                $live->unionAll(DB::table('archive_orders')->selectRaw('status, count(*) as total')->groupBy('status')),
                'all_orders'
            )
            ->selectRaw('status, sum(total) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $known = collect(OrderTransformer::STATUSES)
            ->map(fn ($label, $status): array => [
                'status' => $status,
                'label' => $label,
                'orders' => (int) ($counts[$status] ?? 0),
            ])
            ->values();

        // The old shop used statuses of its own; show them rather than hide them.
        $extra = $counts->except(array_keys(OrderTransformer::STATUSES))
            ->map(fn ($total, $status): array => [
                'status' => $status,
                'label' => OrderTransformer::STATUSES[$status] ?? ucfirst($status),
                'orders' => (int) $total,
            ])
            ->values();

        return $this->response->array([
            'data' => $known->concat($extra)->filter(fn ($row): bool => $row['orders'] > 0)->values()->all(),
        ])->setStatusCode(Response::HTTP_OK);
    }

    /**
     * The same list as a spreadsheet, for the courier or the accountant.
     */
    public function export(Request $request): StreamedResponse
    {
        $rows = $this->withItemCounts(
            DB::query()->fromSub($this->unified($request), 'orders')->orderByDesc('placed_at')->get()
        );

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['Broj', 'Datum', 'Status', 'Kupac', 'Telefon', 'E-mail', 'Grad', 'Artikli', 'Iznos (RSD)', 'Poreklo']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->reference,
                    $row->placed_at ? date('d.m.Y H:i', strtotime($row->placed_at)) : '',
                    OrderTransformer::STATUSES[$row->status] ?? $row->status,
                    $row->customer_name,
                    $row->customer_phone,
                    $row->customer_email,
                    $row->city,
                    (int) $row->items,
                    number_format($row->total / 100, 2, ',', ''),
                    $row->origin === 'live' ? 'nova' : 'arhiva',
                ]);
            }

            fclose($out);
        }, 'meva-porudzbine-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Live and archived orders as one list of the same shape.
     */
    protected function unified(Request $request)
    {
        $live = DB::table('lunar_orders as o')
            ->leftJoin('lunar_order_addresses as a', function ($join) {
                $join->on('a.order_id', '=', 'o.id')->where('a.type', '=', 'shipping');
            })
            ->whereNotNull('o.placed_at')
            ->selectRaw("
                'live' as origin,
                o.id::text as key,
                o.reference as reference,
                o.status as status,
                o.placed_at as placed_at,
                o.total as total,
                coalesce(trim(concat(a.first_name, ' ', a.last_name)), '') as customer_name,
                coalesce(o.customer_reference, '') as customer_email,
                coalesce(a.contact_phone, '') as customer_phone,
                coalesce(a.city, '') as city,
                '' as utm_source,
                '' as device_type,
                'cod' as payment_method
            ");

        $archive = DB::table('archive_orders as o')
            ->selectRaw("
                'archive' as origin,
                concat('a', o.id::text) as key,
                coalesce(o.number, o.wp_id::text) as reference,
                o.status as status,
                o.ordered_at as placed_at,
                o.total as total,
                coalesce(o.customer_name, '') as customer_name,
                coalesce(o.customer_email, '') as customer_email,
                coalesce(o.customer_phone, '') as customer_phone,
                coalesce(o.city, '') as city,
                coalesce(o.utm_source, '') as utm_source,
                coalesce(o.device_type, '') as device_type,
                coalesce(o.payment_method, '') as payment_method
            ");

        $union = DB::query()->fromSub($live->unionAll($archive), 'orders');

        if ($origin = $request->input('origin')) {
            $union->where('origin', $origin);
        }

        if ($status = $request->input('status')) {
            $union->where('status', $status);
        }

        if ($from = $request->date('od')) {
            $union->where('placed_at', '>=', $from->startOfDay());
        }

        if ($to = $request->date('do')) {
            $union->where('placed_at', '<=', $to->endOfDay());
        }

        if ($city = trim((string) $request->input('grad'))) {
            $union->where('city', 'ilike', '%'.$city.'%');
        }

        if ($source = $request->input('izvor')) {
            $source === 'direktno'
                ? $union->where('utm_source', '')
                : $union->where('utm_source', $source);
        }

        if ($device = $request->input('uredjaj')) {
            $union->where('device_type', $device);
        }

        // Amounts are typed in dinars and stored in minor units.
        if ($request->filled('min')) {
            $union->where('total', '>=', (int) round(((float) $request->input('min')) * 100));
        }

        if ($request->filled('max')) {
            $union->where('total', '<=', (int) round(((float) $request->input('max')) * 100));
        }

        if ($term = trim((string) $request->input('q'))) {
            $like = '%'.$term.'%';

            $union->where(function ($q) use ($like) {
                $q->where('reference', 'ilike', $like)
                    ->orWhere('customer_name', 'ilike', $like)
                    ->orWhere('customer_email', 'ilike', $like)
                    ->orWhere('customer_phone', 'ilike', $like)
                    ->orWhere('city', 'ilike', $like);
            });
        }

        return $union;
    }

    /**
     * How many articles each order on this page holds.
     *
     * Two grouped queries over the page's own ids, rather than a correlated
     * subquery per row over the whole history.
     */
    protected function withItemCounts(\Illuminate\Support\Collection $rows): \Illuminate\Support\Collection
    {
        $liveIds = $rows->where('origin', 'live')->pluck('key')->all();
        $archiveIds = $rows->where('origin', 'archive')->map(fn ($r): int => (int) substr($r->key, 1))->all();

        $live = $liveIds === [] ? collect() : DB::table('lunar_order_lines')
            ->whereIn('order_id', $liveIds)
            ->where('type', 'physical')
            ->selectRaw('order_id, sum(quantity) as items')
            ->groupBy('order_id')
            ->pluck('items', 'order_id');

        $archive = $archiveIds === [] ? collect() : DB::table('archive_order_items')
            ->whereIn('archive_order_id', $archiveIds)
            ->selectRaw('archive_order_id, sum(quantity) as items')
            ->groupBy('archive_order_id')
            ->pluck('items', 'archive_order_id');

        return $rows->each(function ($row) use ($live, $archive): void {
            $row->items = $row->origin === 'live'
                ? (int) ($live[(int) $row->key] ?? 0)
                : (int) ($archive[(int) substr($row->key, 1)] ?? 0);
        });
    }

    /**
     * The values the filters offer, taken from the orders themselves.
     */
    public function filters()
    {
        $cities = DB::table('archive_orders')
            ->selectRaw("coalesce(nullif(city, ''), 'nepoznato') as label, count(*) as orders")
            ->groupBy('label')->orderByDesc('orders')->limit(25)->get();

        $sources = DB::table('archive_orders')
            ->selectRaw("coalesce(nullif(utm_source, ''), 'direktno') as label, count(*) as orders")
            ->groupBy('label')->orderByDesc('orders')->limit(15)->get();

        $devices = DB::table('archive_orders')
            ->selectRaw("coalesce(nullif(device_type, ''), 'nepoznato') as label, count(*) as orders")
            ->groupBy('label')->orderByDesc('orders')->get();

        return $this->response->array([
            'data' => [
                'cities' => $cities->map(fn ($r): array => ['value' => $r->label, 'orders' => (int) $r->orders])->all(),
                'sources' => $sources->map(fn ($r): array => ['value' => $r->label, 'orders' => (int) $r->orders])->all(),
                'devices' => $devices->map(fn ($r): array => ['value' => $r->label, 'orders' => (int) $r->orders])->all(),
                'statuses' => collect(OrderTransformer::STATUSES)->map(fn ($label, $value): array => ['value' => $value, 'label' => $label])->values()->all(),
            ],
        ])->setStatusCode(Response::HTTP_OK);
    }

    /**
     * One row of the unified list, as the client reads it.
     *
     * @return array<string, mixed>
     */
    protected function present(object $row): array
    {
        return [
            'id' => $row->key,
            'origin' => $row->origin,
            'reference' => $row->reference,
            'status' => $row->status,
            'status_label' => OrderTransformer::STATUSES[$row->status] ?? ucfirst((string) $row->status),
            'placed_at' => $row->placed_at ? date('c', strtotime($row->placed_at)) : null,
            'total' => (int) $row->total,
            'total_formatted' => OrderTransformer::money((int) $row->total),
            'items' => (int) $row->items,
            'customer_name' => $row->customer_name ?: null,
            'customer_email' => $row->customer_email ?: null,
            'city' => $row->city ?: null,
        ];
    }

    /**
     * An archived order and its lines, read-only.
     *
     * @return array<string, mixed>
     */
    protected function archived(int $id): array
    {
        $order = DB::table('archive_orders')->where('id', $id)->first();

        abort_if($order === null, 404);

        $lines = DB::table('archive_order_items')
            ->where('archive_order_id', $id)
            ->get()
            ->map(fn ($line): array => [
                'description' => $line->name,
                'identifier' => $line->wp_product_id ? 'MEVA-'.$line->wp_product_id : '',
                'quantity' => (int) $line->quantity,
                'unit_price' => $line->quantity > 0 ? (int) round($line->total / $line->quantity) : 0,
                'total' => (int) $line->total,
                'total_formatted' => OrderTransformer::money((int) $line->total),
            ])
            ->all();

        return [
            'id' => 'a'.$order->id,
            'origin' => 'archive',
            'reference' => $order->number ?: (string) $order->wp_id,
            'status' => $order->status,
            'status_label' => OrderTransformer::STATUSES[$order->status] ?? ucfirst((string) $order->status),
            'placed_at' => $order->ordered_at ? date('c', strtotime($order->ordered_at)) : null,
            'currency' => $order->currency,
            'sub_total' => (int) $order->goods,
            'shipping_total' => (int) $order->shipping,
            'discount_total' => (int) $order->discount,
            'total' => (int) $order->total,
            'total_formatted' => OrderTransformer::money((int) $order->total),
            'items' => collect($lines)->sum('quantity'),
            'notes' => $order->customer_note,
            'lines' => $lines,
            'customer' => [
                'name' => $order->customer_name,
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
                'address' => null,
                'city' => $order->city,
                'postcode' => $order->postcode,
            ],
            'source' => [
                'utm_source' => $order->utm_source,
                'type' => $order->source_type,
                'device' => $order->device_type,
                'payment' => $order->payment_method,
            ],
        ];
    }

    /**
     * A transformer that always returns the lines and the customer, for the
     * views where detail is the whole point.
     */
    protected function detailed(): OrderTransformer
    {
        $transformer = new OrderTransformer;
        $transformer->setDefaultIncludes(['lines', 'customer']);

        return $transformer;
    }
}
