<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Per-supplier purchasing terms for a product.
         *
         * This is why products carry no cost column: the same product may cost
         * 300 EGP per Carton from one supplier and 15 EGP per Piece from
         * another, and that difference is a property of the pairing.
         *
         * There is deliberately no is_default flag: no default supplier exists.
         */
        Schema::create('product_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();

            /*
             * Which unit this supplier sells in (Carton vs Box vs Piece).
             * Must be one of the product's own configured units — enforced by
             * the application, which is the only place that knows the product.
             */
            $table->foreignId('purchase_unit_id')->constrained('units')->restrictOnDelete();

            // Integer minor units — see the note on the products table.
            $table->unsignedBigInteger('purchase_price')->default(0);

            $table->timestamps();

            $table->unique(['product_id', 'supplier_id'], 'product_suppliers_unique');
            $table->index('supplier_id', 'product_suppliers_supplier_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_suppliers');
    }
};
