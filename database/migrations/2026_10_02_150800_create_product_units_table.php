<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * The units a product is sold or purchased in.
         *
         * conversion_factor expresses how many BASE units one of this unit
         * equals: a Box with factor 12 means 1 Box = 12 Pieces.
         *
         * Exactly one row per product carries is_base = true, and its factor is
         * always 1. Inventory is denominated in that base unit, so every other
         * quantity has to be converted through this table.
         */
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();

            /*
             * Stored as DECIMAL, not integer: a supplier may buy 1.5 Kg, so a
             * factor of 0.5 (500 Gram base) has to be representable.
             */
            $table->decimal('conversion_factor', 15, 4);

            // Integer minor units — see the note on the products table.
            $table->unsignedBigInteger('selling_price')->default(0);

            $table->boolean('is_base')->default(false);

            $table->timestamps();

            // A product may not list the same unit twice.
            $table->unique(['product_id', 'unit_id'], 'product_units_product_unit_unique');
            $table->index(['unit_id', 'is_base'], 'product_units_unit_base_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};
