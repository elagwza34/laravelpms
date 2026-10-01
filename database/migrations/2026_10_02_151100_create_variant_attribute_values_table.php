<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Which attribute values make up each variant combination.
         *
         * This is the only place a variant stores its "shape"; all commercial
         * data stays on the parent product.
         */
        Schema::create('variant_attribute_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained('attribute_values')->cascadeOnDelete();

            $table->timestamps();

            // A variant cannot use the same value twice.
            $table->unique(
                ['product_variant_id', 'attribute_value_id'],
                'variant_attribute_values_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_attribute_values');
    }
};
