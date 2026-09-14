<?php

namespace Database\Seeders\Meva;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Lunar\Models\ProductVariant;
use Meva\Entities\Archive\Models\ArchiveCustomer;
use Meva\Entities\Archive\Models\ArchiveOrder;

/**
 * Imports the sales history of the old WooCommerce shop.
 *
 * The export holds personal data for roughly four thousand people and is kept
 * outside the repository, so this reads from config('meva.archive.path') and
 * skips quietly when nothing is there.
 */
class ArchiveSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // The export decodes to well over the default limit: 5,480 orders with
        // their nested lines. This is a one-off bulk import, so the ceiling is
        // raised here rather than pushed onto every process.
        ini_set('memory_limit', '512M');

        $path = rtrim((string) config('meva.archive.path'), '/');

        if (! is_file("{$path}/orders.json")) {
            $this->command?->warn("No order export at [{$path}]; skipping the archive. Set MEVA_ARCHIVE_PATH.");

            return;
        }

        $customers = $this->importCustomers("{$path}/customers.json");
        $this->importOrders("{$path}/orders.json", $customers);
    }

    /**
     * Import customers, returning a map of email to archive customer id.
     *
     * @return array<string, int>
     */
    protected function importCustomers(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }

        $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $rows = [];

        foreach ($data['kupci'] ?? $data as $customer) {
            $rows[] = [
                'email' => $this->email($customer['email']),
                'first_name' => $customer['ime'] ?? null,
                'last_name' => $customer['prezime'] ?? null,
                'phone' => $customer['telefon'] ?? null,
                'address' => $customer['adresa'] ?? null,
                'city' => $customer['grad'] ?? null,
                'postcode' => $customer['postanski_broj'] ?? null,
                'district' => $customer['okrug'] ?? null,
                'country' => $customer['zemlja'] ?? null,
                'orders_count' => $customer['broj_porudzbina'] ?? 0,
                'total_spent' => $this->minor($customer['ukupno_potroseno'] ?? 0),
                'first_order_at' => $customer['prva_porudzbina'] ?? null,
                'last_order_at' => $customer['poslednja_porudzbina'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            ArchiveCustomer::query()->upsert($chunk, ['email'], [
                'first_name', 'last_name', 'phone', 'address', 'city', 'postcode',
                'district', 'country', 'orders_count', 'total_spent',
                'first_order_at', 'last_order_at', 'updated_at',
            ]);
        }

        $this->command?->info('Archived customers: '.count($rows));

        return ArchiveCustomer::query()->pluck('id', 'email')->all();
    }

    /**
     * Import orders and their lines.
     *
     * @param  array<string, int>  $customers
     */
    protected function importOrders(string $file, array $customers): void
    {
        $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $orders = $data['porudzbine'] ?? $data;

        // wp product id => lunar product id, so historical lines can be
        // reported against the live catalogue.
        $productIds = ProductVariant::query()
            ->where('sku', 'like', 'MEVA-%')
            ->pluck('product_id', 'sku')
            ->mapWithKeys(fn (int $id, string $sku): array => [
                (int) str_replace('MEVA-', '', $sku) => $id,
            ]);

        $orderRows = [];

        foreach ($orders as $order) {
            $buyer = $order['kupac'] ?? [];
            $source = $order['izvor'] ?? [];

            $orderRows[] = [
                'wp_id' => $order['wp_id'],
                'number' => $order['broj'] ?? null,
                'status' => $order['status'],
                'ordered_at' => $order['datum'],
                'currency' => $order['valuta'] ?? 'RSD',
                'total' => $this->minor($order['ukupno'] ?? 0),
                'goods' => $this->minor($order['roba'] ?? 0),
                'tax' => $this->minor($order['porez'] ?? 0),
                'shipping' => $this->minor($order['dostava'] ?? 0),
                'discount' => $this->minor($order['popust'] ?? 0),
                'payment_method' => $order['nacin_placanja'] ?? null,
                'customer_note' => $order['napomena_kupca'] ?? null,
                'archive_customer_id' => $customers[$this->email($buyer['email'] ?? '')] ?? null,
                'customer_email' => $this->email($buyer['email'] ?? '') ?: null,
                'customer_name' => trim(($buyer['ime'] ?? '').' '.($buyer['prezime'] ?? '')) ?: null,
                'customer_phone' => $buyer['telefon'] ?? null,
                'city' => $buyer['grad'] ?? null,
                'postcode' => $buyer['postanski_broj'] ?? null,
                'district' => $buyer['okrug'] ?? null,
                'country' => $buyer['zemlja'] ?? null,
                'utm_source' => $source['utm_source'] ?? null,
                'source_type' => $source['source_type'] ?? null,
                'device_type' => $source['device_type'] ?? null,
                'referrer' => $source['referrer'] ?? null,
                'session_pages' => is_numeric($source['session_pages'] ?? null)
                    ? (int) $source['session_pages']
                    : null,
                'created_via' => $order['kreirano_kroz'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($orderRows, 500) as $chunk) {
            ArchiveOrder::query()->upsert($chunk, ['wp_id'], [
                'status', 'ordered_at', 'total', 'goods', 'tax', 'shipping', 'discount',
                'archive_customer_id', 'utm_source', 'source_type', 'device_type', 'updated_at',
            ]);
        }

        $this->command?->info('Archived orders: '.count($orderRows));

        $this->importItems($orders, $productIds);
    }

    /**
     * Import order lines.
     *
     * @param  array<int, array<string, mixed>>  $orders
     */
    protected function importItems(array $orders, $productIds): void
    {
        $orderIds = ArchiveOrder::query()->pluck('id', 'wp_id');

        // Lines have no stable identifier in the export, so they are rebuilt
        // rather than upserted; re-running therefore stays consistent.
        DB::table('archive_order_items')->delete();

        // Sixteen thousand lines are flushed as they are built rather than
        // collected first; holding them all alongside the decoded export
        // exhausts the default memory limit.
        $buffer = [];
        $total = 0;

        foreach ($orders as $order) {
            $orderId = $orderIds->get($order['wp_id']);

            foreach ($order['stavke'] ?? [] as $item) {
                $buffer[] = [
                    'archive_order_id' => $orderId,
                    'wp_product_id' => $item['product_id'] ?? null,
                    'product_id' => $productIds->get($item['product_id'] ?? 0),
                    'name' => $item['naziv'] ?? '',
                    'quantity' => $item['kolicina'] ?? 1,
                    'total' => $this->minor($item['cena_ukupno'] ?? 0),
                    'total_before_discount' => $this->minor($item['cena_pre_popusta'] ?? 0),
                    'set_parent_wp_id' => $item['deo_seta_id'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($buffer) >= 1000) {
                    DB::table('archive_order_items')->insert($buffer);
                    $total += count($buffer);
                    $buffer = [];
                }
            }
        }

        if ($buffer !== []) {
            DB::table('archive_order_items')->insert($buffer);
            $total += count($buffer);
        }

        $this->command?->info('Archived order lines: '.$total);
    }

    /**
     * Normalise an email address for matching.
     *
     * The customer export aggregated on a lowercased address while orders kept
     * whatever the buyer typed, so 37 orders would otherwise never find their
     * customer.
     */
    protected function email(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    /**
     * Convert an exported amount to minor units.
     *
     * The export writes RSD as whole dinars; the rest of the application keeps
     * money in minor units, so history stays comparable with new orders.
     */
    protected function minor(int|float|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
