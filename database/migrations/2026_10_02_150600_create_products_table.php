<?php

use App\Enums\ProductType;
use App\Enums\RecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * A product is the single commercial and inventory entity.
         *
         * Money is stored as integer minor units (piastres) rather than DECIMAL:
         * floating point money drifts over repeated arithmetic, and an integer
         * makes aggregation exact. Prices vary per product_unit; tax_value is
         * DECIMAL because a percentage may need more precision than a price.
         */
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('name');

            /*
             * Exactly one SKU per product, unique within the tenant.
             * Nullable because a product may be created before a SKU exists,
             * and MySQL/MariaDB allow many NULLs in a unique index.
             */
            $table->string('sku')->nullable();

            // Exactly one barcode per product, unique within the tenant.
            $table->string('barcode')->nullable();

            $table->string('product_type')->default(ProductType::Simple->value);

            $table->string('short_description', 500)->nullable();
            $table->longText('description')->nullable();

            // Storage path/reference only; no binary image data in the database.
            $table->string('image')->nullable();

            // At most one brand per product; a brand may serve many products.
            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete();

            /*
             * Reorder threshold, expressed in the product's BASE unit.
             * Deliberately no maximum_stock: not part of the product model.
             * Nullable because an unconfigured threshold is legitimate.
             */
            $table->decimal('minimum_stock', 15, 3)->nullable();

            /*
             * Product-level tax configuration only. A product with no tax leaves
             * BOTH columns null rather than storing a meaningless "none" type.
             */
            $table->string('tax_type')->nullable();
            $table->decimal('tax_value', 15, 2)->nullable();

            $table->string('status')->default(RecordStatus::Active->value);

            $table->timestamps();

            /*
             * Products are archived, never destroyed: future sales, purchases
             * and stock movements must stay referentially intact.
             */
            $table->softDeletes();

            $table->unique(['company_id', 'sku'], 'products_company_sku_unique');
            $table->unique(['company_id', 'barcode'], 'products_company_barcode_unique');

            /*
             * Referenced by product_variants' composite foreign key, which makes
             * a variant impossible to create against another company's product.
             */
            $table->unique(['id', 'company_id'], 'products_id_company_unique');

            // Supports the listing filters and the scoped default query.
            $table->index(['company_id', 'status'], 'products_company_status_index');
            $table->index(['company_id', 'product_type'], 'products_company_type_index');
            $table->index(['company_id', 'brand_id'], 'products_company_brand_index');
            $table->index(['company_id', 'name'], 'products_company_name_index');
            $table->index(['company_id', 'deleted_at'], 'products_company_deleted_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
