<?php

namespace Meva\Api\V1\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Order;
use Meva\Api\V1\Controllers\Controller;
use Meva\Api\V1\Transformers\Commerce\OrderTransformer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The order book.
 *
 * Every sale the shop has taken, searchable by reference, customer, e-mail or
 * phone, filterable by status and date, and exportable for the courier.
 */
class OrderController extends Controller
{
    /**
     * List orders, newest first.
     */
    public function index(Request $request)
    {
        $orders = $this->query($request)
            ->with(['lines', 'shippingAddress', 'billingAddress'])
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return $this->response
            ->paginator($orders, new OrderTransformer)
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * One order, with its lines and the person who placed it.
     */
    public function show(int $id)
    {
        $order = Order::with(['lines', 'shippingAddress', 'billingAddress'])->findOrFail($id);

        return $this->response
            ->item($order, new OrderTransformer)
            ->parseIncludes(['lines', 'customer'])
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Move an order along: dispatched, delivered, returned, cancelled.
     */
    public function updateStatus(Request $request, int $id)
    {
        $request->validate([
            'status' => 'required|string|in:'.implode(',', array_keys(OrderTransformer::STATUSES)),
            'notes' => 'nullable|string|max:2000',
        ]);

        $order = Order::findOrFail($id);
        $order->status = $request->input('status');

        if ($request->filled('notes')) {
            $order->notes = $request->input('notes');
        }

        $order->save();

        return $this->response
            ->item($order->refresh(), new OrderTransformer)
            ->parseIncludes(['lines', 'customer'])
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * How many orders sit in each status, for the badges above the list.
     */
    public function summary()
    {
        $counts = Order::query()
            ->whereNotNull('placed_at')
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return $this->response->array([
            'data' => collect(OrderTransformer::STATUSES)
                ->map(fn ($label, $status): array => [
                    'status' => $status,
                    'label' => $label,
                    'orders' => (int) ($counts[$status] ?? 0),
                ])
                ->values()
                ->all(),
        ])->setStatusCode(Response::HTTP_OK);
    }

    /**
     * The same list as a spreadsheet, for the courier or the accountant.
     */
    public function export(Request $request): StreamedResponse
    {
        $orders = $this->query($request)->with(['lines', 'shippingAddress'])->get();

        return response()->streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['Broj', 'Datum', 'Status', 'Kupac', 'Telefon', 'E-mail', 'Adresa', 'Grad', 'Artikli', 'Iznos (RSD)']);

            foreach ($orders as $order) {
                $address = $order->shippingAddress;

                fputcsv($out, [
                    $order->reference,
                    $order->placed_at?->format('d.m.Y H:i'),
                    OrderTransformer::STATUSES[$order->status] ?? $order->status,
                    trim(($address?->first_name ?? '').' '.($address?->last_name ?? '')),
                    $address?->contact_phone,
                    $order->customer_reference,
                    $address?->line_one,
                    $address?->city,
                    $order->lines->where('type', 'physical')
                        ->map(fn ($l): string => "{$l->description} ×{$l->quantity}")
                        ->implode('; '),
                    number_format($order->total / 100, 2, ',', ''),
                ]);
            }

            fclose($out);
        }, 'meva-porudzbine-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * The filters the list and the export share.
     */
    protected function query(Request $request)
    {
        $query = Order::query()->whereNotNull('placed_at')->latest('placed_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->date('od')) {
            $query->where('placed_at', '>=', $from->startOfDay());
        }

        if ($to = $request->date('do')) {
            $query->where('placed_at', '<=', $to->endOfDay());
        }

        if ($term = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($term) {
                $like = '%'.$term.'%';

                $q->where('reference', 'ilike', $like)
                    ->orWhere('customer_reference', 'ilike', $like)
                    ->orWhereHas('addresses', fn ($a) => $a
                        ->where('first_name', 'ilike', $like)
                        ->orWhere('last_name', 'ilike', $like)
                        ->orWhere('contact_phone', 'ilike', $like)
                        ->orWhere('city', 'ilike', $like));
            });
        }

        return $query;
    }
}
