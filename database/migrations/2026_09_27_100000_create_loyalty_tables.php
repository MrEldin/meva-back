<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Meva Klub.
     *
     * `loyalty_entries` is a ledger: every point given or taken is a row, and a
     * member's balance is simply the sum of theirs. Nothing is ever edited; a
     * returned order is answered with a reversing row, not by deleting the
     * credit. An order can be credited, and reversed, once each -- the unique
     * index is what makes crediting safe to run twice.
     *
     * `loyalty_coupons` are what points buy: single-use, worth a fixed amount
     * in minor units, and good for a limited time.
     */
    public function up(): void
    {
        Schema::create('loyalty_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->unsignedInteger('value');
            $table->string('reward_key', 60);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('lunar_orders')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('loyalty_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('points');
            $table->string('reason', 20);
            $table->foreignId('order_id')->nullable()->constrained('lunar_orders')->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained('loyalty_coupons')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'reason']);
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_entries');
        Schema::dropIfExists('loyalty_coupons');
    }
};
