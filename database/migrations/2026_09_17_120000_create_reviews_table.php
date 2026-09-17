<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What customers wrote.
     *
     * These lived in a JavaScript file in the storefront, copied by hand off
     * the old WooCommerce shop, which meant nobody could add one without a
     * deploy. They belong here instead.
     *
     * `product_id` is the product in this catalogue the review was left on,
     * where that is known; `product_label` keeps the wording the review
     * arrived with even when it names nothing the shop sells under that name
     * ("Hidratantna krema", "Nega tela"), so the review can still be shown
     * without being attached to the wrong jar.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('product_id')->nullable()->constrained('lunar_products')->nullOnDelete();
            $table->string('product_label')->nullable();
            $table->unsignedSmallInteger('rating')->default(5);
            $table->text('body');
            $table->string('source')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['published_at', 'id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
