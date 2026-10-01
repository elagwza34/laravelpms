<?php

use App\Enums\RecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Variants describe which ATTRIBUTE VALUES make up one option
         * combination of a variable product (Black/M, Black/L, ...).
         *
         * A variant is NOT an independent stock item. It deliberately has no
         * sku, barcode, price, cost, tax or stock column: all commercial and
         * inventory data belongs to the parent product, which remains a single
         * inventory item.
         *
         * company_id is denormalised from the product so the table obeys the
         * same CompanyScope as every other tenant table. The composite foreign
         * key below guarantees it can never disagree with the product's owner.
         */
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            /*
             * Deterministic identifier built from the sorted attribute value
             * ids, e.g. "12-45-91". Sorting makes the key independent of the
             * order the client listed the values in, so the same combination
             * always produces the same key.
             */
            $table->string('combination_key');

            $table->string('status')->default(RecordStatus::Active->value);

            $table->timestamps();
            $table->softDeletes();

            // The combination must be unique per product.
            $table->unique(['product_id', 'combination_key'], 'product_variants_product_combination_unique');
            $table->index(['company_id', 'product_id'], 'product_variants_company_product_index');

            $table->foreign(
                ['product_id', 'company_id'],
                'product_variants_product_company_fk'
            )->references(['id', 'company_id'])->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
