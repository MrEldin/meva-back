<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Lunar has no native bundle product type, but the old shop sold 14 sets
     * made up of 66 individual products. Keeping that composition here means a
     * set can later be priced, picked or stock-checked from its parts instead
     * of the relationship being flattened away at import time.
     */
    public function up(): void
    {
        Schema::create('product_bundle_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bundle_product_id')
                ->constrained('lunar_products')
                ->cascadeOnDelete();

            $table->foreignId('item_product_id')
                ->constrained('lunar_products')
                ->cascadeOnDelete();

            $table->unsignedInteger('quantity')->default(1);

            $table->timestamps();

            $table->unique(['bundle_product_id', 'item_product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_bundle_items');
    }
};
