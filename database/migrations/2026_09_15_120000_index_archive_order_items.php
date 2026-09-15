<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Line items are always read by the order they belong to.
     *
     * Without this the order book scanned all sixteen thousand lines once per
     * order on the page -- tens of millions of reads for a list of twenty five.
     */
    public function up(): void
    {
        Schema::table('archive_order_items', function (Blueprint $table) {
            $table->index('archive_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('archive_order_items', function (Blueprint $table) {
            $table->dropIndex(['archive_order_id']);
        });
    }
};
