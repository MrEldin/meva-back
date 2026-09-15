<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Customers are looked up by a lower-cased e-mail, which the plain index
     * on the column cannot answer.
     */
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS archive_orders_email_lower_idx ON archive_orders (lower(customer_email))');
        DB::statement('CREATE INDEX IF NOT EXISTS archive_orders_email_ordered_idx ON archive_orders (lower(customer_email), ordered_at DESC)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS archive_orders_email_lower_idx');
        DB::statement('DROP INDEX IF EXISTS archive_orders_email_ordered_idx');
    }
};
