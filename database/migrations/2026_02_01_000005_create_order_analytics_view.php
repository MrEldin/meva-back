<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One surface to query for sales reporting, so a dashboard never has to
     * know that history lives in the archive while new orders land in Lunar.
     *
     * Everything a campaign report groups by -- date, channel, device, city,
     * revenue -- is a column on this view, so the usual questions are a single
     * GROUP BY with no joins.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS order_analytics');

        DB::statement(<<<'SQL'
            CREATE VIEW order_analytics AS

            SELECT
                'archive'                AS origin,
                a.id                     AS order_id,
                a.wp_id                  AS external_id,
                a.status                 AS status,
                a.ordered_at             AS ordered_at,
                a.currency               AS currency,
                a.total                  AS total,
                a.goods                  AS goods,
                a.shipping               AS shipping,
                a.discount               AS discount,
                a.customer_email         AS customer_email,
                a.city                   AS city,
                a.country                AS country,
                a.utm_source             AS utm_source,
                a.source_type            AS source_type,
                a.device_type            AS device_type,
                a.payment_method         AS payment_method
            FROM archive_orders a

            UNION ALL

            SELECT
                'live'                   AS origin,
                o.id                     AS order_id,
                o.id                     AS external_id,
                o.status                 AS status,
                COALESCE(o.placed_at, o.created_at) AS ordered_at,
                o.currency_code          AS currency,
                o.total                  AS total,
                o.sub_total              AS goods,
                o.shipping_total         AS shipping,
                o.discount_total         AS discount,
                COALESCE(o.customer_reference, '') AS customer_email,
                NULL                     AS city,
                NULL                     AS country,
                NULL                     AS utm_source,
                NULL                     AS source_type,
                NULL                     AS device_type,
                NULL                     AS payment_method
            FROM lunar_orders o
            WHERE o.placed_at IS NOT NULL
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS order_analytics');
    }
};
