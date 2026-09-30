<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Meva\Entities\Catalogue\Ingredients;

/**
 * Ingredients as rows, not as a paragraph.
 *
 * The label's ingredient list was HTML in an attribute, which the desk could
 * only edit as prose and the page could only guess at. One row per
 * ingredient, in label order, with the INCI name and the Serbian one. What
 * the attribute held is read into rows here; sets keep none, since a set
 * shows its parts' ingredients.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('lunar_products')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('inci');
            $table->string('name')->default('');
            $table->timestamps();
            $table->index(['product_id', 'position']);
        });

        $setType = DB::table('lunar_product_types')->where('name', 'Set')->value('id');

        foreach (DB::table('lunar_products')->select('id', 'product_type_id', 'attribute_data')->cursor() as $product) {
            $html = (string) (json_decode($product->attribute_data, true)['ingredients']['value'] ?? '');

            if ($html === '' || ($setType !== null && (int) $product->product_type_id === (int) $setType)) {
                continue;
            }

            $rows = Ingredients::parse($html);

            if ($rows !== []) {
                Ingredients::replace((int) $product->id, $rows);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_ingredients');
    }
};
