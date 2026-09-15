<?php

namespace Meva\Entities\Marketing\Email;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Meva\Entities\Marketing\Models\Subscriber;

/**
 * Who a campaign goes to.
 *
 * A segment is a question about behaviour, not a saved list: "people whose
 * bottle should be running out" is recomputed at send time, so a campaign
 * written today and sent next week reaches the right people then. Every
 * segment excludes anyone who has unsubscribed, which is checked here rather
 * than trusted to whoever builds the campaign.
 */
class AudienceResolver
{
    /**
     * The segments the editor offers, in the order it shows them.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function segments(): array
    {
        return [
            ['key' => 'subscribers', 'name' => 'Prijavljeni na listu', 'why' => 'Tražili su da im pišete. Najviše otvaranja, najmanje žalbi.'],
            ['key' => 'customers', 'name' => 'Svi kupci', 'why' => 'Svako ko je ikada poručio. Koristite za velike najave.'],
            ['key' => 'new', 'name' => 'Kupili jednom', 'why' => 'Druga porudžbina je najteža. Ovde se dobija ili gubi kupac.'],
            ['key' => 'due', 'name' => 'Vreme za dopunu', 'why' => 'Prošlo im je koliko obično traje pakovanje. Najisplativija grupa.'],
            ['key' => 'loyal', 'name' => 'Verni kupci', 'why' => 'Tri i više porudžbina. Njima se šalje prvo i najlepše.'],
            ['key' => 'winback', 'name' => 'Nisu se javili 6 meseci', 'why' => 'Vraćanje starog kupca košta manje od pronalaska novog.'],
            ['key' => 'city-belgrade', 'name' => 'Beograd', 'why' => 'Za lokalne povode i brzu dostavu.'],
            ['key' => 'test', 'name' => 'Samo ja (proba)', 'why' => 'Šalje samo na vašu adresu. Uvek probajte pre pravog slanja.'],
        ];
    }

    /**
     * The recipients of one segment.
     *
     * @return array<int, array{email: string, name: string|null}>
     */
    public function recipients(string $segment, ?string $testEmail = null): array
    {
        $rows = match ($segment) {
            'subscribers' => $this->subscribers(),
            'customers' => $this->customers('1 = 1'),
            'new' => $this->customers('orders = 1'),
            'due' => $this->customers('orders > 1 and days_since >= coalesce(gap_days, 60) * 0.85'),
            'loyal' => $this->customers('orders >= 3'),
            'winback' => $this->customers('days_since >= 180'),
            'city-belgrade' => $this->customers("lower(city) like '%beograd%'"),
            'test' => $testEmail ? [['email' => $testEmail, 'name' => null]] : [],
            default => [],
        };

        return $this->withoutUnsubscribed($rows);
    }

    /** How many people a segment currently holds. */
    public function count(string $segment, ?string $testEmail = null): int
    {
        return count($this->recipients($segment, $testEmail));
    }

    /**
     * Sizes for every segment, for the editor's audience picker.
     *
     * Counted in one pass over the order history rather than once per segment:
     * asking eight separate questions of five and a half thousand orders took
     * two seconds, and this panel has to open instantly. Opt-outs are excluded
     * inside the query, so the number shown is the number that will be sent.
     *
     * @return array<string, int>
     */
    public function sizes(?string $testEmail = null): array
    {
        $counts = Cache::remember('email:audience-sizes', 300, function (): array {
            $row = DB::selectOne(<<<'SQL'
                with sales as (
                    select lower(customer_email) as email, ordered_at, city
                    from order_analytics
                    where status <> 'cancelled' and customer_email is not null and customer_email <> ''
                ),
                paced as (
                    select
                        email,
                        count(*)::int                               as orders,
                        (current_date - max(ordered_at)::date)::int as days_since,
                        max(city)                                   as city,
                        case when count(*) > 1
                             then ((max(ordered_at)::date - min(ordered_at)::date)::numeric / (count(*) - 1))
                             else null end                          as gap_days
                    from sales
                    group by email
                ),
                eligible as (
                    -- Anyone who has opted out is not in any segment, so they
                    -- are dropped here rather than subtracted afterwards.
                    select p.* from paced p
                    where not exists (
                        select 1 from subscribers s
                        where lower(s.email) = p.email and s.unsubscribed_at is not null
                    )
                )
                select
                    count(*)::int                                                                        as customers,
                    count(*) filter (where orders = 1)::int                                              as new_customers,
                    count(*) filter (where orders > 1 and days_since >= coalesce(gap_days, 60) * 0.85)::int as due,
                    count(*) filter (where orders >= 3)::int                                             as loyal,
                    count(*) filter (where days_since >= 180)::int                                       as winback,
                    count(*) filter (where lower(city) like '%beograd%')::int                            as belgrade
                from eligible
            SQL);

            return [
                'customers' => (int) $row->customers,
                'new' => (int) $row->new_customers,
                'due' => (int) $row->due,
                'loyal' => (int) $row->loyal,
                'winback' => (int) $row->winback,
                'city-belgrade' => (int) $row->belgrade,
                'subscribers' => Subscriber::query()->active()->count(),
            ];
        });

        $counts['test'] = $testEmail ? 1 : 0;

        return $counts;
    }

    /**
     * @return array<int, array{email: string, name: string|null}>
     */
    protected function subscribers(): array
    {
        return Subscriber::query()
            ->active()
            ->whereNotNull('email')
            ->get(['email', 'name'])
            ->map(fn (Subscriber $s): array => ['email' => strtolower($s->email), 'name' => $s->name])
            ->all();
    }

    /**
     * Customers matching a condition over their order history.
     *
     * @return array<int, array{email: string, name: string|null}>
     */
    protected function customers(string $having): array
    {
        $sql = <<<SQL
            with sales as (
                select lower(customer_email) as email, ordered_at, city
                from order_analytics
                where status <> 'cancelled' and customer_email is not null and customer_email <> ''
            ),
            grouped as (
                select
                    email,
                    count(*)::int                                   as orders,
                    max(ordered_at)                                 as last_order,
                    min(ordered_at)                                 as first_order,
                    (current_date - max(ordered_at)::date)::int     as days_since,
                    max(city)                                       as city
                from sales
                group by email
            ),
            paced as (
                select *,
                    case when orders > 1
                         then ((last_order::date - first_order::date)::numeric / (orders - 1))
                         else null end as gap_days
                from grouped
            ),
            named as (
                select distinct on (lower(customer_email))
                    lower(customer_email) as email,
                    customer_name         as name
                from archive_orders
                where customer_email is not null and customer_email <> ''
                order by lower(customer_email), ordered_at desc
            )
            select p.email, n.name
            from paced p
            left join named n on n.email = p.email
            where {$having}
        SQL;

        return collect(DB::select($sql))
            ->map(fn ($row): array => ['email' => $row->email, 'name' => $row->name])
            ->all();
    }

    /**
     * Drop anyone who has opted out, and any duplicate address.
     *
     * @param  array<int, array{email: string, name: string|null}>  $rows
     * @return array<int, array{email: string, name: string|null}>
     */
    protected function withoutUnsubscribed(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $opted = Subscriber::query()
            ->whereNotNull('unsubscribed_at')
            ->pluck('email')
            ->map(fn ($e): string => strtolower((string) $e))
            ->flip();

        $seen = [];
        $out = [];

        foreach ($rows as $row) {
            $email = strtolower(trim((string) $row['email']));

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            if (isset($seen[$email]) || $opted->has($email)) {
                continue;
            }

            $seen[$email] = true;
            $out[] = ['email' => $email, 'name' => $row['name'] ?? null];
        }

        return $out;
    }
}
