<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A campaign is a subject line, a preheader and an ordered list of blocks.
     *
     * The blocks are kept as data rather than as HTML so the same campaign can
     * be re-rendered whenever the templates improve, and so the editor and the
     * sent message are built by one renderer.
     */
    public function up(): void
    {
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('template')->default('blank');
            $table->string('subject')->nullable();
            $table->string('preheader')->nullable();
            $table->string('audience')->default('subscribers');
            $table->json('blocks');
            $table->string('status')->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_campaigns');
    }
};
