<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The 5,480 orders from the old WooCommerce shop live here rather than in
     * Lunar's Order model. Every one of them was a guest checkout paid cash on
     * delivery, none was ever marked completed, and none went through a payment
     * gateway or a shipping zone -- mapping that onto Lunar's carts,
     * transactions and addresses would be a lot of invention, and it would mix
     * historical records into the statistics of the live shop.
     *
     * These tables are an append-only archive, shaped for reporting: the
     * columns a campaign dashboard groups by are indexed.
     */
    public function up(): void
    {
        Schema::create('archive_customers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('postcode')->nullable();
            $table->string('district')->nullable();
            $table->string('country', 2)->nullable();

            // Aggregates carried over from the export so lifetime value does
            // not have to be recomputed on every dashboard load.
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedBigInteger('total_spent')->default(0)->index();
            $table->timestamp('first_order_at')->nullable()->index();
            $table->timestamp('last_order_at')->nullable()->index();

            $table->timestamps();
        });

        Schema::create('archive_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wp_id')->unique();
            $table->string('number')->nullable();
            $table->string('status')->index();
            $table->timestamp('ordered_at')->index();
            $table->string('currency', 3);

            // Money in minor units, matching how Lunar stores it.
            $table->bigInteger('total')->default(0);
            $table->bigInteger('goods')->default(0);
            $table->bigInteger('tax')->default(0);
            $table->bigInteger('shipping')->default(0);
            $table->bigInteger('discount')->default(0);

            $table->string('payment_method')->nullable()->index();
            $table->text('customer_note')->nullable();

            $table->foreignId('archive_customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_email')->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();

            $table->string('city')->nullable()->index();
            $table->string('postcode')->nullable();
            $table->string('district')->nullable();
            $table->string('country', 2)->nullable();

            // What the campaign dashboard exists for.
            $table->string('utm_source')->nullable()->index();
            $table->string('source_type')->nullable()->index();
            $table->string('device_type')->nullable()->index();
            $table->text('referrer')->nullable();
            $table->unsignedInteger('session_pages')->nullable();
            $table->string('created_via')->nullable();

            $table->timestamps();
        });

        Schema::create('archive_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archive_order_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('wp_product_id')->nullable()->index();
            // Resolved against the imported catalogue where the product still
            // exists, so sales can be reported per live product.
            $table->foreignId('product_id')->nullable()->constrained('lunar_products')->nullOnDelete();

            $table->string('name');
            $table->unsignedInteger('quantity')->default(1);
            $table->bigInteger('total')->default(0);
            $table->bigInteger('total_before_discount')->default(0);
            $table->unsignedBigInteger('set_parent_wp_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archive_order_items');
        Schema::dropIfExists('archive_orders');
        Schema::dropIfExists('archive_customers');
    }
};
