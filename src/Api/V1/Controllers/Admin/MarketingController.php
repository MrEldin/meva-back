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
